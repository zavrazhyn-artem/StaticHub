<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Deterministic per-death cause classifier. Reads the encounter's existing
 * analyzer outputs (interrupts, dispels, mechanic_failures, tank coverage,
 * boss_timeline) + per-death state snapshots (positions, recent casts,
 * available defensives, heal-received-in-5s) and emits a category +
 * evidence + responsible players + confidence for each death.
 *
 * The AI then narrates the attribution rather than guessing it. That
 * eliminates the "AI confidently blames the wrong player" failure mode.
 *
 * Categories (in priority order — first match wins). Order is engineered so
 * the AI's prose explains WHY the player got hit (positioning / coordination
 * / mechanic), not just WHAT could have mitigated it (defensives). Defensive
 * lanes only fire when (a) the cast was scheduled-predictable + a CD was
 * ready, or (b) NO other signal explains the death — otherwise saying
 * "didn't press X" produces identical noise on every pull.
 *
 *   missed_external             — tank death + no_external/late on tank_death_coverage
 *   missed_interrupt            — killed by an interruptable cast that was uninterrupted ≥50% this pull
 *   missed_dispel               — killed by a debuff that was dispelled elsewhere but not on dying player
 *   defensive_predictable_miss  — defensive_unused + killing-blow scheduled in boss_timeline (you saw it coming)
 *   soak_uncovered              — shared-damage mechanic + dying player isolated (no friends within 30y)
 *   raid_coordination           — killing_blow's encounter-wide failure count >= 5 (positioning / assignment fix)
 *   healer_lapse                — non-tank, gradual-bleed (tag=normal), zero healing received in last 5s, alive healers in roster
 *   defensive_unused            — LAST RESORT: own CD ready but no mechanic / positional signal fires. Framed as "supplementary".
 *   unavoidable                 — death tag = mechanic_oneshot AND no preventable signal
 *   self_mechanic_miss          — default fallback
 *
 * Confidence:
 *   high   — clear deterministic signal
 *   medium — partial / inferred signal
 *   low    — heuristic with weak evidence
 */
class DeathAttributionBuilder
{
    private const RAID_COORD_THRESHOLD = 5;
    private const INTERRUPT_MISS_RATIO = 0.5;
    // healer_lapse fires when total healing received in [death-5s, death] is
    // below this threshold. Tank deaths are excluded from this lane entirely
    // (tank lane handles its own coverage). 50K is roughly one Renewing Mist
    // tick — anything less is "no healer attention."
    private const HEALER_LAPSE_HEAL_FLOOR = 50_000;
    // boss_timeline entry counts as "scheduled before death" if its cast
    // begins within this window before the killing blow lands.
    private const PREDICTABLE_LOOKBACK_MS = 5_000;

    /**
     * Apply attribution to every death in encounters[].fights[].deaths[] in-place.
     *
     * @param array        $encounters         analyzer output (mutated)
     * @param array        $deathStateByDeath  flat state-snapshots, indexed by global-death-index
     * @param list<string> $healerNames        names of healer-role players in the roster
     * @param list<string> $tankNames          names of tank-role players in the roster
     */
    public function attribute(
        array &$encounters,
        array $deathStateByDeath,
        array $healerNames = [],
        array $tankNames = []
    ): void {
        $tankSet = array_flip($tankNames);
        $healerCount = count($healerNames);

        // NOTE: foreach (... ?? [] as &$x) does NOT propagate the reference
        // back to the original array — `?? []` produces a temporary
        // expression and the reference binds to that temp, so mutations are
        // silently dropped. Index-based access is the only safe way to
        // mutate nested arrays through ?? null-coalesce.
        $globalIdx = 0;
        $encCount = count($encounters);
        for ($e = 0; $e < $encCount; $e++) {
            $enc = $encounters[$e];
            $bossInterrupts = $enc['interrupts'] ?? $enc['interrupt_analysis'] ?? [];
            $bossDispels    = $enc['dispels'] ?? [];
            $bossFailures   = $enc['mechanic_failures'] ?? [];
            $tankCoverage   = $enc['player_stats']['external_cooldowns']['tank_death_coverage'] ?? [];
            $bossTimeline   = $enc['boss_timeline'] ?? [];

            $fights = $enc['fights'] ?? [];
            $fightCount = count($fights);
            for ($f = 0; $f < $fightCount; $f++) {
                $deaths = $fights[$f]['deaths'] ?? [];
                $deathCount = count($deaths);
                for ($d = 0; $d < $deathCount; $d++) {
                    $state = $deathStateByDeath[$globalIdx] ?? null;
                    $encounters[$e]['fights'][$f]['deaths'][$d]['attribution'] = $this->classify(
                        $deaths[$d],
                        $state,
                        $bossInterrupts,
                        $bossDispels,
                        $bossFailures,
                        $tankCoverage,
                        $bossTimeline,
                        $tankSet,
                        $healerCount,
                        $enc
                    );
                    $globalIdx++;
                }
            }
        }
    }

    private function classify(
        array $death,
        ?array $state,
        array $bossInterrupts,
        array $bossDispels,
        array $bossFailures,
        array $tankCoverage,
        array $bossTimeline,
        array $tankSet,
        int $healerCount,
        array $encounter
    ): array {
        $kbName = (string) ($death['killing_blow'] ?? '');
        $kbGuid = $death['killing_blow_guid'] ?? null;
        $tag = (string) ($death['tag'] ?? '');
        $playerName = (string) ($death['player'] ?? '');

        // 1. missed_external — tank deaths with no external coverage
        $tankCov = $tankCoverage[$playerName] ?? null;
        if ($tankCov !== null) {
            $coverage = $tankCov['coverage'] ?? null;
            if ($coverage === 'no_external' || $coverage === 'late') {
                return $this->attribution(
                    'missed_external',
                    $coverage === 'no_external'
                        ? "No external CD active in the 4s before death. Tank-death coverage flagged as no_external."
                        : "External CD active but applied late (within 4-8s window — likely already expired). Coverage flagged as late.",
                    $this->collectExternalSources($state),
                    'high'
                );
            }
        }

        // 2. missed_interrupt — interruptable boss cast slipped past available kickers
        // Runs BEFORE raid_coordination so an interrupt-fixable mechanic gets the
        // more actionable verdict (verification agent flagged the prior order
        // as too generic).
        $interruptStats = $this->interruptStats($kbName, $kbGuid, $bossInterrupts);
        if ($interruptStats !== null) {
            $missRatio = $interruptStats['miss_ratio'];
            if ($missRatio >= self::INTERRUPT_MISS_RATIO && $interruptStats['available_kickers'] > 0) {
                $missedCount = $interruptStats['total'] - $interruptStats['interrupted'];
                return $this->attribution(
                    'missed_interrupt',
                    "Killing blow `{$kbName}` was cast {$interruptStats['total']} times this encounter; {$interruptStats['interrupted']} were interrupted ({$missedCount} slipped through). Roster has {$interruptStats['available_kickers']} eligible kicker(s) — this death is on the interrupt rotation.",
                    $interruptStats['kickers'],
                    'high'
                );
            }
        }

        // 3. missed_dispel — debuff that should have been removed
        $dispelStats = $this->dispelStats($kbName, $kbGuid, $bossDispels);
        if ($dispelStats !== null && $dispelStats['was_ever_dispelled']) {
            return $this->attribution(
                'missed_dispel',
                "Killing blow `{$kbName}` is a dispellable debuff (dispelled {$dispelStats['dispel_count']} times elsewhere this encounter). It was NOT dispelled off {$playerName} before the killing tick.",
                $dispelStats['dispellers'],
                'medium'
            );
        }

        // Pre-compute the "available defensive" signal — used both by the
        // strong `defensive_predictable_miss` lane and by the catch-all
        // `defensive_unused` lane at the end. We DELIBERATELY don't return
        // `defensive_unused` early: most players have *some* CD off cooldown
        // most of the time, so leading with "didn't press X" produces
        // pull_breakdown text that's identical and useless ("didn't use Ice
        // Barrier" / "didn't use Divine Protection" on every entry). We only
        // surface that lane when no positional / mechanic / coordination
        // signal can explain WHY they got hit.
        $unusedSelf = $state ? $this->dyingPlayerOwnDefensives($state) : [];
        $defensiveAbilityList = !empty($unusedSelf)
            ? implode(', ', array_column($unusedSelf, 'ability_name'))
            : null;
        $defensiveCdAge = (function () use ($unusedSelf): string {
            $readyTimes = array_filter(
                array_column($unusedSelf, 'last_cast_s_ago'),
                fn($v) => $v !== null
            );
            return !empty($readyTimes)
                ? round((float) max($readyTimes), 1) . 's'
                : 'the entire fight';
        })();

        // 4. defensive_predictable_miss — strong signal: killing-blow was a
        // SCHEDULED boss cast (boss_timeline match) AND the player had a CD
        // ready. That combo means the death was foreseeable from cadence,
        // worth a high-confidence callout BEFORE generic mechanic categories.
        if ($defensiveAbilityList !== null) {
            $absoluteMs = $state['absolute_timestamp_ms'] ?? null;
            $scheduledCast = $absoluteMs !== null
                ? $this->matchBossTimelineCast($kbName, $absoluteMs, $bossTimeline)
                : null;

            if ($scheduledCast !== null) {
                $leadTime = round(($absoluteMs - $scheduledCast['absolute_timestamp_ms']) / 1000, 1);
                return $this->attribution(
                    'defensive_predictable_miss',
                    "Killing blow `{$kbName}` was a SCHEDULED boss cast on the timeline ({$leadTime}s before death). {$playerName} had {$defensiveAbilityList} off cooldown for {$defensiveCdAge} — the cast was foreseeable from cadence, the defensive was ready, neither was used.",
                    [],
                    'high'
                );
            }
        }

        // 5. soak_uncovered — isolated position during what looks like a shared-damage hit
        if ($state && empty($state['nearby_players'])) {
            return $this->attribution(
                'soak_uncovered',
                "{$playerName} died with no roster member within 30 yards. Either solo-targeted with no soak partners, or out of position.",
                [],
                'low'
            );
        }

        // 6. raid_coordination — same mechanic failed many times raid-wide.
        // Promoted ABOVE defensive_unused: explains WHY (positioning /
        // assignment) instead of "you could have mitigated it."
        $matchedFailureCount = $this->mechanicFailureCount($kbName, $kbGuid, $bossFailures);
        if ($matchedFailureCount >= self::RAID_COORD_THRESHOLD) {
            return $this->attribution(
                'raid_coordination',
                "Killing blow `{$kbName}` shows up as a raid-wide failure {$matchedFailureCount} times across this encounter. The fix is coordination/assignment, not individual reaction.",
                [],
                'high'
            );
        }

        // 7. healer_lapse — non-tank, gradual-bleed death, no heals landed in
        // [death-5s, death], and we have alive healers in the roster.
        // Distinct from `unavoidable`: the death pattern says healers SHOULD
        // have been able to save this player — they weren't.
        if ($state && $tag === 'normal' && !isset($tankSet[$playerName]) && $healerCount >= 1) {
            $healReceived = (int) ($state['heal_received_5s'] ?? -1);
            if ($healReceived >= 0 && $healReceived < self::HEALER_LAPSE_HEAL_FLOOR) {
                $healStr = $healReceived === 0 ? 'zero' : number_format($healReceived);
                return $this->attribution(
                    'healer_lapse',
                    "{$playerName} (non-tank) bled out gradually from `{$kbName}` with only {$healStr} healing landed in the last 5 seconds. Pattern fits a healer-attention gap rather than a survivable individual mistake.",
                    [],
                    $healReceived === 0 ? 'high' : 'medium'
                );
            }
        }

        // 8. defensive_unused — last-resort lane. Only fires when none of the
        // mechanic / positional / coordination signals explained the death,
        // AND the player had a CD ready. The framing is intentionally muted
        // ("supplementary") because "didn't press defensive" is rarely the
        // ROOT cause — usually it's positioning that put them in range to
        // need it in the first place.
        if ($defensiveAbilityList !== null) {
            return $this->attribution(
                'defensive_unused',
                "Supplementary signal only: {$playerName} had personal defensive(s) off cooldown at time of death (longest ready: {$defensiveCdAge}; available: {$defensiveAbilityList}). No stronger mechanic / coordination / positional signal fired — likely a routine personal mistake where the CD WOULD have helped, but the root cause is in the surrounding pull narrative, not the missing CD.",
                [],
                'low'
            );
        }

        // 8. unavoidable — pre-tagged mechanic_oneshot when no other signal fired
        if ($tag === 'mechanic_oneshot') {
            return $this->attribution(
                'unavoidable',
                "Death tagged as mechanic_oneshot by WipeDetector — boss landed a single ability that downed multiple raiders together. Survivable only through pre-mitigation or strict positioning.",
                [],
                'medium'
            );
        }

        // 9. self_mechanic_miss — default
        return $this->attribution(
            'self_mechanic_miss',
            "No shared-failure signal (no missed external, no missed interrupt, no missed dispel, no raid-wide pattern). Likely a personal mechanic-handling miss.",
            [],
            'low'
        );
    }

    /**
     * Find a boss_timeline entry that matches `$kbName` and was scheduled to
     * begin within PREDICTABLE_LOOKBACK_MS before `$deathAbsMs`. Returns the
     * entry with `absolute_timestamp_ms` injected (the timeline format
     * varies, so we compute it from whatever timestamp is available).
     *
     * @return ?array
     */
    private function matchBossTimelineCast(string $kbName, int $deathAbsMs, array $bossTimeline): ?array
    {
        if (empty($bossTimeline)) return null;

        $kbLower = mb_strtolower($kbName);
        $best = null;
        $bestLead = PHP_INT_MAX;

        foreach ($bossTimeline as $entry) {
            if (!is_array($entry)) continue;
            $name = (string) ($entry['ability'] ?? $entry['name'] ?? '');
            $entryLower = mb_strtolower($name);
            if ($entryLower === '') continue;

            // Match exact OR family-prefix (≥4 char prefix), so "Shadow Fracture"
            // matches a "Shadow Fracture (priority)" entry and "Gloomfield"
            // matches "Gloom".
            $matches = $entryLower === $kbLower;
            if (!$matches) {
                $shorter = strlen($entryLower) < strlen($kbLower) ? $entryLower : $kbLower;
                $longer  = $shorter === $entryLower ? $kbLower : $entryLower;
                if (strlen($shorter) >= 4 && str_starts_with($longer, $shorter)) {
                    $matches = true;
                }
            }
            if (!$matches) continue;

            // Resolve absolute cast time. boss_timeline entries variously carry
            // `absolute_timestamp_ms`, `timestamp_ms`, or `time_s` — accept any.
            $absMs = $entry['absolute_timestamp_ms']
                ?? $entry['timestamp_ms']
                ?? (isset($entry['time_s']) ? (int) ((float) $entry['time_s'] * 1000) : null);
            if ($absMs === null) continue;

            $lead = $deathAbsMs - (int) $absMs;
            if ($lead < 0 || $lead > self::PREDICTABLE_LOOKBACK_MS) continue;
            if ($lead < $bestLead) {
                $bestLead = $lead;
                $best = $entry + ['absolute_timestamp_ms' => (int) $absMs];
            }
        }

        return $best;
    }

    private function attribution(string $category, string $evidence, array $responsible, string $confidence): array
    {
        return [
            'category'             => $category,
            'evidence'             => $evidence,
            'responsible_players'  => array_values(array_unique($responsible)),
            'confidence'           => $confidence,
        ];
    }

    /**
     * Pull external-CD sources from death state (raid externals reported in
     * available defensives). Best-effort — we only know the OWN defensives
     * here; cross-class externals come from external-cooldowns.yaml in a
     * future extension.
     */
    private function collectExternalSources(?array $state): array
    {
        if (!$state) return [];
        $sources = [];
        foreach ($state['personal_defensives_available'] ?? [] as $entry) {
            $sources[] = $entry['player'];
        }
        return array_slice(array_values(array_unique($sources)), 0, 5);
    }

    /**
     * @return ?array{total:int, interrupted:int, miss_ratio:float, available_kickers:int, kickers:list<string>}
     */
    private function interruptStats(string $abilityName, ?int $abilityGuid, array $bossInterrupts): ?array
    {
        // bossInterrupts shape: ability => { total_casts, interrupted_count, interrupters: [name,...] }
        // OR sometimes: ability_name => count map. Handle both shapes defensively.
        if (empty($bossInterrupts)) return null;

        $entry = null;
        foreach ($bossInterrupts as $key => $value) {
            if (is_array($value) && (string) $key === $abilityName) {
                $entry = $value;
                break;
            }
        }
        if (!$entry) return null;

        $total = (int) ($entry['total_casts'] ?? $entry['casts'] ?? 0);
        $interrupted = (int) ($entry['interrupted_count'] ?? $entry['interrupted'] ?? 0);
        $kickers = $entry['interrupters'] ?? $entry['kickers'] ?? [];

        if ($total <= 0) return null;
        $missRatio = ($total - $interrupted) / $total;

        return [
            'total'             => $total,
            'interrupted'       => $interrupted,
            'miss_ratio'        => round($missRatio, 2),
            'available_kickers' => count(array_filter($kickers, 'is_string')),
            'kickers'           => array_values(array_filter($kickers, 'is_string')),
        ];
    }

    /**
     * @return ?array{dispel_count:int, dispellers:list<string>, was_ever_dispelled:bool}
     */
    private function dispelStats(string $abilityName, ?int $abilityGuid, array $bossDispels): ?array
    {
        if (empty($bossDispels)) return null;

        $matched = null;
        foreach ($bossDispels as $entry) {
            if (!is_array($entry)) continue;
            if (($entry['debuff'] ?? $entry['ability'] ?? null) === $abilityName) {
                $matched = $entry;
                break;
            }
        }
        if (!$matched) return null;

        $count = (int) ($matched['count'] ?? $matched['dispels'] ?? 0);
        $byPlayer = $matched['by_player'] ?? [];
        $dispellers = is_array($byPlayer) ? array_keys($byPlayer) : [];

        return [
            'dispel_count'       => $count,
            'dispellers'         => $dispellers,
            'was_ever_dispelled' => $count > 0,
        ];
    }

    /**
     * Count how many times the killing-blow ability appears as a mechanic
     * failure across this encounter. Matches on:
     *   1. exact (case-insensitive) name match
     *   2. ability-family fallback — e.g. "Gloomfield" matches "Gloom" entry,
     *      "Nullsnap" matches "Nullzone" entry. We strip a trailing word and
     *      compare prefixes when shorter ≥4 chars (avoids over-matching on
     *      single-letter prefixes).
     */
    private function mechanicFailureCount(string $abilityName, ?int $abilityGuid, array $failures): int
    {
        $kbLower = mb_strtolower($abilityName);
        $total = 0;

        foreach ($failures as $entry) {
            if (!is_array($entry)) continue;
            $name = (string) ($entry['name'] ?? $entry['mechanic'] ?? '');
            $entryLower = mb_strtolower($name);
            $count = (int) ($entry['count'] ?? $entry['failures'] ?? $entry['total_failures'] ?? 1);

            // Exact match
            if ($entryLower === $kbLower) {
                $total += $count;
                continue;
            }

            // Family fallback — when one is a prefix of the other and the
            // shorter side is ≥4 chars, treat as the same family.
            $shorter = strlen($entryLower) < strlen($kbLower) ? $entryLower : $kbLower;
            $longer  = $shorter === $entryLower ? $kbLower : $entryLower;
            if (strlen($shorter) >= 4 && str_starts_with($longer, $shorter)) {
                $total += $count;
            }
        }

        return $total;
    }

    /**
     * Filter `personal_defensives_available` to only the dying player's own
     * unused defensives (not the wider raid).
     */
    private function dyingPlayerOwnDefensives(array $state): array
    {
        $name = $state['dying_player'] ?? '';
        return array_values(array_filter(
            $state['personal_defensives_available'] ?? [],
            fn($e) => ($e['player'] ?? null) === $name
        ));
    }
}
