<?php

declare(strict_types=1);

namespace App\Services\Wishlist;

use App\Models\Character;
use App\Models\Wishlist;
use App\Models\WishlistDroptimizerConfig;
use Illuminate\Support\Collection;

/**
 * Per-static "Allowed Droptimizer configurations" — write API for the
 * settings tab + read+match used during wishlist import. Mirrors the
 * wowaudit concept: raid lead defines named configs (Single Target /
 * Cleave / Dungeon Slice / etc) with their fight params and required
 * upgrade tracks; member uploads have to match one of those rows.
 */
final class WishlistConfigService
{
    /**
     * Lists every config for a static, lazy-creating the mandatory
     * Default row on first access. Default's values mirror the wowaudit
     * defaults (Patchwerk / 1 boss / >=5 min / max upgrade tracks) so a
     * brand-new static still has working Raidbots deep-links and import
     * matching from minute one.
     *
     * @return Collection<int, WishlistDroptimizerConfig>
     */
    public function listForStatic(int $staticId): Collection
    {
        $this->ensureDefault($staticId);
        return WishlistDroptimizerConfig::query()->forStatic($staticId)->get();
    }

    public function defaultFor(int $staticId): WishlistDroptimizerConfig
    {
        return $this->ensureDefault($staticId);
    }

    private function ensureDefault(int $staticId): WishlistDroptimizerConfig
    {
        $existing = WishlistDroptimizerConfig::query()->defaultForStatic($staticId);
        if ($existing) return $existing;

        return WishlistDroptimizerConfig::query()->create(
            ['static_id' => $staticId, 'position' => 0] + WishlistDroptimizerConfig::DEFAULT_ATTRS,
        );
    }

    /**
     * @param array<string,mixed> $attrs  Validated FormRequest payload.
     */
    public function create(int $staticId, array $attrs): WishlistDroptimizerConfig
    {
        // is_default is server-managed: only ensureDefault() can mint a
        // default row. Strip it from any incoming payload defensively.
        unset($attrs['is_default']);
        $position = (int) WishlistDroptimizerConfig::query()
            ->forStatic($staticId)
            ->max('position') + 1;
        return WishlistDroptimizerConfig::query()->create(
            ['static_id' => $staticId, 'is_default' => false, 'position' => $position] + $attrs,
        );
    }

    public function update(WishlistDroptimizerConfig $config, array $attrs): WishlistDroptimizerConfig
    {
        unset($attrs['is_default']);
        $config->update($attrs);
        return $config->refresh();
    }

    public function delete(WishlistDroptimizerConfig $config): void
    {
        // Default config is the source of truth for Raidbots deep-links —
        // we let the user reshape its values but never delete the row.
        if ($config->is_default) {
            abort(422, 'The Default config cannot be deleted.');
        }
        $config->delete();
    }

    /**
     * Pick the highest-weight config whose EVERY field is satisfied by
     * the freshly-imported Raidbots payload. Returns null when nothing
     * fully matches; caller is expected to throw a hard error in that
     * case (silently saving "unmatched" defeats the per-static gate).
     *
     * Field paths verified against a live Raidbots Droptimizer payload
     * on 2026-05-09 (report 8UE2QnzY3JDLziT8LQFLc3). All fields exist on
     * either `simbot.*` or `simbot.meta.rawFormData.*` (with droptimizer
     * sub-block for the upgrade-track / voidforged / equipped flags).
     */
    public function matchForImport(int $staticId, array $importDto): ?WishlistDroptimizerConfig
    {
        $configs = $this->listForStatic($staticId);
        if ($configs->isEmpty()) return null;

        $report = $this->extractReportSummary($importDto);

        return $configs
            ->filter(fn (WishlistDroptimizerConfig $c) => empty($this->matchFailureReasons($c, $report)))
            ->sortByDesc('weight')
            ->first();
    }

    /**
     * Pulls every field the matcher needs out of the raw Raidbots payload.
     *
     * @return array{
     *   style: string,
     *   num_bosses: int,
     *   fight_minutes: int,
     *   difficulty: ?string,
     *   upgrade_level: ?string,
     *   voidforged: bool,
     *   power_infusion: bool,
     *   has_custom_apl: bool,
     *   upgrade_all_same: bool
     * }
     */
    public function extractReportSummary(array $importDto): array
    {
        $simbot  = $importDto['raw_payload']['simbot'] ?? [];
        $rawForm = $simbot['meta']['rawFormData'] ?? [];
        $drop    = $rawForm['droptimizer'] ?? [];

        // upgradeLevel is a bonus_id (e.g. 12806). Resolve to "Myth 6/6"
        // through the season's track table so we can compare it to the
        // config's friendly upgrade_level_* field as a string.
        $bonusId = (int) ($drop['upgradeLevel'] ?? 0);
        $tracks  = config('wow_season.item_upgrade_tracks', []);
        $track   = $tracks[$bonusId] ?? null;
        $upgradeName = $track ? sprintf('%s %d/%d', $track['track'], $track['level'], $track['max']) : null;

        $rawDifficulty = (string) ($drop['difficulty'] ?? '');
        $diffMap = [
            'raid-mythic'      => 'mythic',
            'raid-heroic'      => 'heroic',
            'raid-normal'      => 'normal',
            'raid-raid-finder' => 'lfr',
            'raid-lfr'         => 'lfr',
        ];

        $fightSec = (int) ($simbot['fightLength'] ?? $rawForm['fightLength'] ?? 0);

        return [
            'style'            => (string) ($simbot['fightStyle'] ?? $rawForm['fightStyle'] ?? ''),
            'num_bosses'       => max(1, (int) ($simbot['enemyCount'] ?? $rawForm['enemyCount'] ?? 1)),
            'fight_minutes'    => $fightSec > 0 ? (int) round($fightSec / 60) : 0,
            'difficulty'       => $diffMap[$rawDifficulty] ?? null,
            'upgrade_level'    => $upgradeName,
            'voidforged'       => (int) ($drop['warforgeLevel'] ?? 0) > 0,
            'power_infusion'   => (bool) ($rawForm['powerInfusion'] ?? false),
            'has_custom_apl'   => is_string($rawForm['apl'] ?? '') && trim((string) ($rawForm['apl'] ?? '')) !== '',
            'upgrade_all_same' => (bool) ($drop['upgradeEquipped'] ?? false),
        ];
    }

    /**
     * Returns a list of human-readable reasons this config does NOT
     * match the report (empty list = match). Drives both the matcher
     * (filter on empty) and the per-config breakdown in import errors.
     *
     * `require_vault_socket` is intentionally not validated: Raidbots'
     * payload exposes only the per-item `enableSockets` overrides, and
     * those flip on for items with natural sockets too — not a clean
     * form-level signal. Will be revisited if Raidbots adds it.
     *
     * @param array<string,mixed> $report Output of extractReportSummary()
     * @return list<string>
     */
    public function matchFailureReasons(WishlistDroptimizerConfig $c, array $report): array
    {
        $reasons = [];

        if ($report['style'] !== '' && strcasecmp($report['style'], $c->fight_style) !== 0) {
            $reasons[] = "fight style {$report['style']} ≠ {$c->fight_style}";
        }
        if (! $this->compareOp($report['num_bosses'], $c->num_bosses_op, $c->num_bosses)) {
            $reasons[] = "bosses {$report['num_bosses']} not {$c->num_bosses_op} {$c->num_bosses}";
        }
        if ($report['fight_minutes'] > 0 && ! $this->compareOp($report['fight_minutes'], $c->fight_length_op, $c->fight_length_minutes)) {
            $reasons[] = "length {$report['fight_minutes']} min not {$c->fight_length_op} {$c->fight_length_minutes}";
        }

        if ($report['difficulty'] !== null) {
            $field    = "upgrade_level_{$report['difficulty']}";
            $required = $c->{$field} ?? null;
            if ($required !== null && $required !== '' && $report['upgrade_level'] !== $required) {
                $got = $report['upgrade_level'] ?? 'unknown';
                $reasons[] = "{$report['difficulty']} upgrade {$got} ≠ {$required}";
            }
        }

        if ($c->require_pi && ! $report['power_infusion'])           $reasons[] = 'PI required but sim ran without it';
        if ($c->voidforged   && ! $report['voidforged'])             $reasons[] = 'Voidforged required but sim is base';
        if (! $c->allow_expert && $report['has_custom_apl'])         $reasons[] = 'custom APL / expert mode not allowed';
        if ($c->require_upgrade_all_same && ! $report['upgrade_all_same']) $reasons[] = '"Upgrade All Equipped" required';

        return $reasons;
    }

    /** Human-readable summary of a config — used in error messages. */
    public function summariseConfig(WishlistDroptimizerConfig $c): string
    {
        $opLabel = ['is' => 'is', 'at_least' => '≥', 'at_most' => '≤', 'less_than' => '<', 'more_than' => '>'];
        $parts = [
            $c->fight_style,
            'bosses ' . ($opLabel[$c->num_bosses_op] ?? $c->num_bosses_op) . " {$c->num_bosses}",
            'length ' . ($opLabel[$c->fight_length_op] ?? $c->fight_length_op) . " {$c->fight_length_minutes} min",
        ];
        foreach (['mythic' => 'M', 'heroic' => 'H', 'normal' => 'N', 'lfr' => 'L'] as $diff => $tag) {
            $v = $c->{"upgrade_level_{$diff}"} ?? null;
            if ($v) $parts[] = "{$tag}: {$v}";
        }
        if ($c->require_pi)               $parts[] = '+PI';
        if ($c->voidforged)               $parts[] = '+Voidforged';
        if (! $c->allow_expert)           $parts[] = 'no expert APL';
        if ($c->require_upgrade_all_same) $parts[] = '+Upgrade All';
        return implode(' · ', $parts);
    }

    public function summariseReport(array $importDto): string
    {
        $r = $this->extractReportSummary($importDto);
        $parts = [
            $r['style'] !== '' ? $r['style'] : 'unknown style',
            "{$r['num_bosses']} boss(es)",
            "{$r['fight_minutes']} min",
        ];
        if ($r['difficulty'] && $r['upgrade_level']) {
            $parts[] = "{$r['difficulty']}: {$r['upgrade_level']}";
        }
        if ($r['power_infusion'])    $parts[] = '+PI';
        if ($r['voidforged'])        $parts[] = '+Voidforged';
        if ($r['has_custom_apl'])    $parts[] = '+custom APL';
        if ($r['upgrade_all_same'])  $parts[] = '+Upgrade All';
        return implode(' · ', $parts);
    }

    private function compareOp(int $value, string $op, int $threshold): bool
    {
        return match ($op) {
            'is'        => $value === $threshold,
            'at_least'  => $value >= $threshold,
            'at_most'   => $value <= $threshold,
            'less_than' => $value <  $threshold,
            'more_than' => $value >  $threshold,
            default     => true,
        };
    }

    /**
     * Build a Raidbots Droptimizer deep-link prefilled with the
     * character. Raidbots' Droptimizer page reads ONLY `region` /
     * `realm` / `name` from the query string — all form fields
     * (fightStyle, numEnemies, fightLength, maxIlvl, …) are React state
     * and ignored when passed via URL. Verified against the production
     * page on 2026-05-09. The Default-config values still drive import
     * matching server-side; the user just sets the form manually on
     * Raidbots once the page opens.
     */
    public function raidbotsDeepLink(?Character $character = null): string
    {
        if ($character === null) return 'https://www.raidbots.com/simbot/droptimizer';

        $params = array_filter([
            'region' => strtolower((string) ($character->realm?->region ?? '')),
            'realm'  => (string) ($character->realm?->slug ?? ''),
            'name'   => (string) $character->name,
        ], fn ($v) => $v !== '');

        $query = http_build_query($params);
        return 'https://www.raidbots.com/simbot/droptimizer' . ($query ? '?' . $query : '');
    }
}
