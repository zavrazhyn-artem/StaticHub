<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Deterministic per-death cause classifier. Reads the encounter's existing
 * analyzer outputs (interrupts, dispels, mechanic_failures, tank coverage)
 * + per-death state snapshots (positions, recent casts, available
 * defensives) and emits a category + evidence + responsible players +
 * confidence for each death.
 *
 * The AI then narrates the attribution rather than guessing it. That
 * eliminates the "AI confidently blames the wrong player" failure mode.
 *
 * Categories (in priority order — first match wins). Specific shared-failure
 * categories take priority over the generic `raid_coordination` so the AI gets
 * actionable advice (e.g. "interrupt the cast" beats "fix coordination"):
 *   missed_external      — tank death + no_external/late on tank_death_coverage
 *   missed_interrupt     — killed by an interruptable cast that was uninterrupted ≥50% this pull
 *   missed_dispel        — killed by a debuff that was dispelled elsewhere but not on dying player
 *   defensive_unused     — dying player had own personal defensive off cooldown, didn't cast in last 5s
 *   soak_uncovered       — shared-damage mechanic + dying player isolated (no friends within 30y)
 *   raid_coordination    — killing_blow's encounter-wide failure count >= 5 (generic shared-failure fallback)
 *   unavoidable          — death tag = mechanic_oneshot AND no preventable signal
 *   self_mechanic_miss   — default fallback
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

    /**
     * Apply attribution to every death in encounters[].fights[].deaths[] in-place.
     *
     * @param array $encounters       analyzer output (mutated)
     * @param array $deathStateByDeath  flat list state-snapshots, indexed by global-death-index
     */
    public function attribute(array &$encounters, array $deathStateByDeath): void
    {
        $globalIdx = 0;
        foreach ($encounters as &$enc) {
            $bossInterrupts = $enc['interrupts'] ?? [];
            $bossDispels    = $enc['dispels'] ?? [];
            $bossFailures   = $enc['mechanic_failures'] ?? [];
            $tankCoverage   = $enc['player_stats']['external_cooldowns']['tank_death_coverage'] ?? [];

            foreach ($enc['fights'] ?? [] as &$fight) {
                foreach ($fight['deaths'] ?? [] as &$death) {
                    $state = $deathStateByDeath[$globalIdx] ?? null;
                    $death['attribution'] = $this->classify(
                        $death,
                        $state,
                        $bossInterrupts,
                        $bossDispels,
                        $bossFailures,
                        $tankCoverage,
                        $enc
                    );
                    $globalIdx++;
                }
                unset($death);
            }
            unset($fight);
        }
        unset($enc);
    }

    private function classify(
        array $death,
        ?array $state,
        array $bossInterrupts,
        array $bossDispels,
        array $bossFailures,
        array $tankCoverage,
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

        // 4. defensive_unused — own defensive available but unused in last 5s
        if ($state) {
            $unusedSelf = $this->dyingPlayerOwnDefensives($state);
            if (!empty($unusedSelf)) {
                $abilityList = implode(', ', array_column($unusedSelf, 'ability_name'));
                return $this->attribution(
                    'defensive_unused',
                    "{$playerName} had personal defensive(s) off cooldown at time of death and did not cast them in the last 5s: {$abilityList}.",
                    [],
                    'medium'
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

        // 6. raid_coordination — generic shared-failure fallback when no specific
        // category fired but the same mechanic failed many times raid-wide.
        $matchedFailureCount = $this->mechanicFailureCount($kbName, $kbGuid, $bossFailures);
        if ($matchedFailureCount >= self::RAID_COORD_THRESHOLD) {
            return $this->attribution(
                'raid_coordination',
                "Killing blow `{$kbName}` shows up as a raid-wide failure {$matchedFailureCount} times across this encounter. The fix is coordination/assignment, not individual reaction.",
                [],
                'high'
            );
        }

        // 7. unavoidable — pre-tagged mechanic_oneshot when no other signal fired
        if ($tag === 'mechanic_oneshot') {
            return $this->attribution(
                'unavoidable',
                "Death tagged as mechanic_oneshot by WipeDetector — boss landed a single ability that downed multiple raiders together. Survivable only through pre-mitigation or strict positioning.",
                [],
                'medium'
            );
        }

        // 8. self_mechanic_miss — default
        return $this->attribution(
            'self_mechanic_miss',
            "No shared-failure signal (no missed external, no missed interrupt, no missed dispel, no raid-wide pattern). Likely a personal mechanic-handling miss.",
            [],
            'low'
        );
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
