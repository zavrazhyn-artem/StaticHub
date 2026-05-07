<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\StaticGroup;
use App\Models\User;
use App\Models\Wishlist;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Builds the SavedVariables-shaped payload that the BlastR Desktop
 * bridge consumes from GET /api/v1/sync/wishlists.
 *
 * The shape is mirrored exactly in external/_spec/savedvariables.md;
 * see that doc for the contract. When fields here change, bump the
 * BlastRSchema constant and update the addon side in lockstep.
 */
final class WishlistSyncService
{
    /**
     * Bump when the response shape changes in a backwards-incompatible
     * way. Bridge writes this directly into BlastRSchema in the SV file.
     */
    public const SCHEMA_VERSION = 1;

    /**
     * @return array{
     *   schema:int,
     *   team_id:int,
     *   synced_at:string,
     *   characters: array<string, array<string,mixed>>
     * }
     */
    public function buildPayload(User $user, ?CarbonImmutable $since = null): array
    {
        $static = $this->resolveStatic($user);
        if ($static === null) {
            return $this->emptyPayload(0);
        }

        $rosterMeta = $this->loadRosterMeta($static->id);
        $wishlists = Wishlist::query()->syncableForStatic($static->id, $since)->get();

        $characters = [];
        foreach ($wishlists as $w) {
            $charKey = $this->characterKey($w->character?->name, $w->character?->realm?->slug);
            if ($charKey === null) {
                continue;
            }
            $meta = $rosterMeta['characters'][$w->character_id] ?? null;
            if ($meta === null) {
                // Wishlist for a character no longer in this static — skip.
                continue;
            }

            $characters[$charKey] ??= [
                // Uppercase to match the in-game UnitClass() token so
                // the addon can use the value directly for class-color
                // lookups via RAID_CLASS_COLORS.
                'class'           => strtoupper(str_replace([' ', '-'], '', $w->character->playable_class)),
                'user_id'         => $w->character->user_id,
                'role'            => $meta['role'],
                // Latest imported_at across all this character's
                // wishlists. Addon shows it as "updated 2h ago" so the
                // loot master can spot stale wishes at a glance.
                'last_updated_at' => null,
                'specs'           => [],
            ];

            // Track the latest imported_at as we walk the character's
            // wishlists; cheaper than a separate aggregate query.
            $importedIso = $w->imported_at?->toIso8601String();
            if ($importedIso !== null) {
                $current = $characters[$charKey]['last_updated_at'];
                if ($current === null || $importedIso > $current) {
                    $characters[$charKey]['last_updated_at'] = $importedIso;
                }
            }

            $specMeta = $rosterMeta['specs'][$w->character_id][$w->spec_id] ?? null;
            $isMainSpec = $specMeta['is_main'] ?? false;

            $specBlock = &$characters[$charKey]['specs'][(string) $w->spec_id];
            $specBlock ??= [
                'spec_name'    => $w->specialization?->name ?? '',
                'is_main_spec' => $isMainSpec,
                'diff'         => [],
            ];

            $diffKey = $this->normalizeDifficulty($w->difficulty);
            $diffBlock = &$specBlock['diff'][$diffKey];
            $diffBlock ??= ['items' => []];

            foreach ($w->items as $item) {
                $diffBlock['items'][(string) $item->item_id] = [
                    'status'  => $item->status,
                    'value'   => (int) $item->value,
                    'percent' => round((float) $item->percent, 3),
                    'source'  => $w->source ?? '',
                    'boss'    => null,
                    'note'    => $item->comment ?? '',
                ];
            }

            unset($specBlock, $diffBlock);
        }

        // Boss attribution comes from season_items; resolve in one pass to
        // avoid an N+1 around the items loop.
        $this->annotateBossNames($characters);

        return [
            'schema'     => self::SCHEMA_VERSION,
            'team_id'    => $static->id,
            'synced_at'  => CarbonImmutable::now('UTC')->toIso8601String(),
            'characters' => $characters,
        ];
    }

    private function resolveStatic(User $user): ?StaticGroup
    {
        // One user belongs to at most one playing static in practice. The
        // pivot allows multi-static (admin tooling), so pick the first
        // deterministically — same pattern the web layer uses for the
        // "current static" middleware default.
        return $user->statics()->orderBy('statics.id')->first();
    }

    /**
     * Resolves per-character role + per-spec is_main flag in two queries
     * so the sync formatter can decorate wishlists without touching the
     * relation lazy loaders.
     *
     * @return array{characters: array<int, array{role:string}>, specs: array<int, array<int, array{is_main:bool}>>}
     */
    private function loadRosterMeta(int $staticId): array
    {
        $charRows = DB::table('character_static')
            ->where('static_id', $staticId)
            ->get(['character_id', 'role']);

        $specRows = DB::table('character_static_specs')
            ->where('static_id', $staticId)
            ->get(['character_id', 'spec_id', 'is_main']);

        $characters = [];
        foreach ($charRows as $r) {
            $characters[(int) $r->character_id] = ['role' => $r->role];
        }

        $specs = [];
        foreach ($specRows as $r) {
            $specs[(int) $r->character_id][(int) $r->spec_id] = [
                'is_main' => (bool) $r->is_main,
            ];
        }

        return ['characters' => $characters, 'specs' => $specs];
    }

    /**
     * Mutates $characters in place, attaching a boss name to every
     * raid-source item that the season_items catalogue can resolve.
     *
     * @param array<string, array<string, mixed>> $characters
     */
    private function annotateBossNames(array &$characters): void
    {
        $itemIds = [];
        foreach ($characters as $char) {
            foreach ($char['specs'] as $spec) {
                foreach ($spec['diff'] as $diff) {
                    foreach (array_keys($diff['items']) as $itemId) {
                        $itemIds[(int) $itemId] = true;
                    }
                }
            }
        }
        if (empty($itemIds)) {
            return;
        }

        $bosses = DB::table('season_items')
            ->whereIn('id', array_keys($itemIds))
            ->where('source_type', 'raid')
            ->pluck('boss_name', 'id')
            ->all();

        foreach ($characters as $charKey => &$char) {
            foreach ($char['specs'] as $specKey => &$spec) {
                foreach ($spec['diff'] as $diffKey => &$diff) {
                    foreach ($diff['items'] as $itemId => &$item) {
                        if (isset($bosses[(int) $itemId])) {
                            $item['boss'] = $bosses[(int) $itemId];
                        }
                    }
                }
            }
        }
    }

    /**
     * Build the canonical "Name-RealmSlug" key the addon uses to look up
     * a character. Realm slug is lowercased per Blizzard convention.
     */
    private function characterKey(?string $name, ?string $realmSlug): ?string
    {
        if (! $name || ! $realmSlug) {
            return null;
        }
        return $name . '-' . strtolower($realmSlug);
    }

    /**
     * Normalize Blizzard's difficulty strings into the single-letter
     * codes the addon expects. Unknown strings pass through unchanged so
     * test fixtures don't silently lose data.
     */
    private function normalizeDifficulty(string $difficulty): string
    {
        return match (strtolower($difficulty)) {
            'mythic', 'm', 'mythic raid' => 'M',
            'heroic', 'h'                => 'H',
            'normal', 'n'                => 'N',
            'lfr', 'r', 'raid finder'    => 'R',
            default                      => $difficulty,
        };
    }

    private function emptyPayload(int $teamId): array
    {
        return [
            'schema'     => self::SCHEMA_VERSION,
            'team_id'    => $teamId,
            'synced_at'  => CarbonImmutable::now('UTC')->toIso8601String(),
            'characters' => (object) [],
        ];
    }
}
