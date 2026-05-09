<?php

declare(strict_types=1);

namespace App\Services\Desktop;

/**
 * Builds the sync spec the bridge follows on every cycle.
 *
 * The spec is the contract between server and bridge — server
 * dictates "read this var from this SV file → POST to this URL", and
 * the bridge follows generically. New addon outboxes don't need a
 * bridge release; they need a new spec entry here + a matching ingest
 * endpoint.
 *
 * The spec is shipped inside `/api/desktop/sync` so it always travels
 * with the data it describes — no chance of drift between manifest
 * and bridge behaviour.
 *
 * Spec versioning rules:
 *  - `spec_version` MUST be incremented when the schema changes in a
 *    way old bridges can't ignore (renamed fields, new required
 *    fields, etc).
 *  - Bridges silently skip unknown entry types (forward compat for
 *    Phase B/C entries the runner doesn't know about yet).
 *  - Each entry carries `min_addon_version` — bridge skips entries
 *    pointing at an addon that isn't installed at the right version.
 */
final class SyncSpecService
{
    /**
     * Bumped whenever the spec schema (not contents) changes shape in
     * a way old bridges can't gracefully ignore. New entry types are
     * additive (bridge skips unknown ones) and don't bump this.
     */
    public const SPEC_VERSION = 1;

    /**
     * @param  array<string, mixed>  $wishlistPayload  Payload returned
     *        by WishlistSyncService::buildPayload — passed through so
     *        the spec ships actual data inline (no second HTTP call).
     * @return array<string, mixed>
     */
    public function build(array $wishlistPayload = []): array
    {
        return [
            'spec_version' => self::SPEC_VERSION,
            'drain'        => $this->buildDrain(),
            'writes'       => $this->buildWrites($wishlistPayload),
            'globs'        => [],   // Phase C
        ];
    }

    /**
     * Drain entries — bridge reads, POSTs, then optionally rewrites
     * the SV to mark events as acked.
     *
     * Each entry:
     *   id                — opaque, used by bridge for logging only
     *   sv_file           — addon-name (bridge resolves to
     *                       WTF/.../SavedVariables/<sv_file>.lua); MUST
     *                       be in the bridge's hardcoded whitelist of
     *                       known addons
     *   extract_var       — Lua variable name to read from the file
     *   post_url          — relative URL the bridge POSTs to (bridge
     *                       prepends API base). Must be an existing
     *                       sanctum-auth ingest endpoint.
     *   post_field        — body key the extracted value lands under
     *                       (e.g. {"events": [...] })
     *   drain_mode        — "ack_seq": rewrite SV with cleared list
     *                       and stamp seq markers (current loot flow).
     *                       "noop": leave SV alone, server dedups by
     *                       external_id.
     *   ack_seq_field     — SV var name to stamp with max seq seen
     *   ack_time_field    — SV var name to stamp with ISO timestamp
     *   seq_field         — field on each event used to compute max
     *   min_addon_version — bridge skips entry when installed addon
     *                       version is below this (graceful
     *                       degradation across rolling updates)
     *
     * @return list<array<string, mixed>>
     */
    private function buildDrain(): array
    {
        return [
            // Loot history — first entry. Migrated from the bridge's
            // hardcoded outbox loop to prove the spec runner end-to-
            // end. Schema and SV variable names match what the
            // existing addon already writes; no addon migration
            // needed.
            [
                'id'                => 'loot-events',
                'sv_file'           => 'BlastR_RCLootCouncil',
                'extract_var'       => 'BlastROutboxEvents',
                'post_url'          => '/api/desktop/loot-history',
                'post_field'        => 'events',
                'drain_mode'        => 'ack_seq',
                'ack_seq_field'     => 'BlastROutboxLastDrainedSeq',
                'ack_time_field'    => 'BlastROutboxLastDrainedAt',
                'seq_field'         => 'seq',
                'min_addon_version' => '0.0.0',
            ],
            // Reserved entry for the parent BlastR addon. No reads
            // wired today — kept here so the bridge sees both addons
            // in its watch list and future logic in BlastR can be
            // added by editing the buildDrain() return value alone.
            //
            // The bridge already whitelists both addon names; this
            // entry exists to lock in that "BlastR is also a valid
            // sv_file" so a future server-side add doesn't require a
            // bridge release. extract_var points at a placeholder var
            // the addon doesn't write — bridge sees the empty value
            // and no-ops gracefully.
            [
                'id'                => 'blastr-reserved',
                'sv_file'           => 'BlastR',
                'extract_var'       => 'BlastROutboxEvents',
                'post_url'          => '/api/desktop/sync', // unused — payload would be empty
                'post_field'        => 'events',
                'drain_mode'        => 'noop',
                'ack_seq_field'     => '',
                'ack_time_field'    => '',
                'seq_field'         => 'seq',
                'min_addon_version' => '0.0.0',
            ],
        ];
    }

    /**
     * Write entries — bridge generates Lua source from `vars` and
     * writes to `<sv_file>.lua` in every WoW account's SavedVariables
     * dir. Same trust boundary as drain entries: bridge whitelists
     * sv_file against wow.AddonNames.
     *
     * Each entry:
     *   id                — opaque, used by bridge for logging only
     *   sv_file           — addon-name; bridge writes
     *                       WTF/.../SavedVariables/<sv_file>.lua
     *   vars              — map of Lua var name → value. Bridge
     *                       serializes to `Var = value` assignments
     *                       and writes the file atomically.
     *   min_addon_version — bridge skips entries whose min_addon
     *                       version is above what's installed
     *
     * Data is INLINE (no separate fetch URL) — keeps the bridge
     * stateless about the payload's origin, and /sync stays a single
     * round-trip per cycle.
     *
     * @param  array<string, mixed>  $wishlistPayload
     * @return list<array<string, mixed>>
     */
    private function buildWrites(array $wishlistPayload): array
    {
        return [
            // Wishlist + bridge metadata for the parent BlastR addon.
            // Migrated from the bridge's hardcoded writeFromCache —
            // identical key set, just routed through the spec runner.
            [
                'id'                => 'wishlists',
                'sv_file'           => 'BlastR',
                'vars'              => [
                    'BlastRSchema'        => $wishlistPayload['schema'] ?? null,
                    'BlastRTimestamp'     => $wishlistPayload['synced_at'] ?? null,
                    'BlastRTeamID'        => $wishlistPayload['team_id'] ?? null,
                    'BlastRWishlistData'  => $wishlistPayload['characters'] ?? (object) [],
                    // BlastRBridgeVersion is filled in by the bridge
                    // (it's the only var the bridge knows that the
                    // server doesn't). Spec runner replaces this
                    // sentinel with config.Version at write-time.
                    'BlastRBridgeVersion' => '__BRIDGE_VERSION__',
                ],
                'min_addon_version' => '0.0.0',
            ],
        ];
    }
}
