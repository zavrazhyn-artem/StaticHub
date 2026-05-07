# BlastR SavedVariables Contract

This is the data contract between the **BlastR Desktop bridge** and the
**`BlastR_RCLootCouncil`** in-game addon. Both sides serialize/deserialize
the files described below; if you change a field, change it in three
places: the spec, the bridge codec (`internal/lua/`), and the addon
modules under `external/addon/`.

The format is a strict subset of WoW SavedVariables:

- File body is a series of top-level Lua assignments: `Name = value`.
- Values are nil / boolean / number / string / table.
- Tables are either array-style (`{1, 2, 3}`), key-value (`{["k"]="v"}`),
  or both mixed.
- No metatables, no functions, no userdata.

## Addons & files

The bridge ships **two** addons that install side-by-side:

| Addon                    | Title in AddOns list             | SavedVariables file |
| ------------------------ | -------------------------------- | ------------------- |
| `BlastR`                 | `BlastR` (top-level)             | `BlastR.lua`        |
| `BlastR_RCLootCouncil`   | `RCLootCouncil - BlastR` (indents under RC) | `BlastR_RCLootCouncil.lua` |

| File                            | Producer | Consumer | Globals it owns | Lifecycle |
| ------------------------------- | -------- | -------- | --------------- | --------- |
| `BlastR.lua`                    | Bridge   | Addons (both)   | `BlastRSchema`, `BlastRTimestamp`, `BlastRTeamID`, `BlastRWishlistData`, `BlastRBridgeVersion` | Bridge writes on every successful pull. Addons read on `/reload`. |
| `BlastR_RCLootCouncil.lua`      | Addon    | Bridge   | `BlastROutbox*` family | Addon appends events on RC awards. Bridge drains + truncates after a successful push. |

Both files live in `WTF/Account/<account>/SavedVariables/`. WoW writes
SVs on `PLAYER_LOGOUT` / `/reload`; the bridge writes them while WoW is
**not** running (file is locked at runtime).

Globals declared by `BlastR` are visible to `BlastR_RCLootCouncil` at
runtime via Lua's shared `_G` — that's why the wishlist data sits in
the parent addon: every future module (`BlastR_PersonalLoot`, etc.)
will read from the same source without bridge changes.

---

## `BlastR_RCLootCouncil.lua` (bridge → addon)

### Globals

```lua
-- Schema version. The addon refuses to load if this exceeds its known
-- maximum. The bridge always writes the current version.
--
-- Bumped to 2 (2026-05-07): added per-item raid_slug/raid_name; added
-- character-level bis_items, current_items, list_items maps sourced
-- from GearLists (bis | current | custom).
BlastRSchema = 2

-- ISO-8601 UTC timestamp of the moment this snapshot was minted on the
-- backend. Addon shows it in the loot frame as "last sync".
BlastRTimestamp = "2026-05-07T08:30:00Z"

-- Numeric static_id this snapshot belongs to. Addon refuses to apply if
-- the player is in a raid associated with a different team.
BlastRTeamID = 42

-- Wishlist payload. See "WishlistData" below.
BlastRWishlistData = { ... }
```

### `WishlistData`

```lua
BlastRWishlistData = {
  -- Keyed by character full name "Name-Realm" (lowercased server slug
  -- with dashes preserved per Blizzard's GetUnitName convention).
  ["Zaavrik-tarren-mill"] = {
    class = "PRIEST",                  -- uppercase Blizzard class token
    user_id = 1,                       -- backend numeric user id
    role = "main",                     -- main | alt
    last_updated_at = "2026-04-30T12:00:00Z",  -- max(imported_at) over this char's wishlists

    -- Items in this character's curated Best-in-Slot GearList. Drives
    -- the addon's BiS column ★ marker — DECOUPLED from the wishlist
    -- `status='b'` flag (which is just whatever the upstream sim
    -- happened to label top — not the player's deliberate pick).
    bis_items = { [210000] = true, [210001] = true },

    -- Items currently equipped (Battle.net inventory snapshot, kept by
    -- the existing GearList type=current sync). Drives the Eq warning.
    current_items = { [210099] = true },

    -- Item id → list of human-readable names of custom GearLists
    -- ("M+ keys", "PvP", off-spec, …) that contain this item. Drives
    -- the List column marker; tooltip names every list.
    list_items = {
      [210050] = { "M+ keys" },
      [210051] = { "PvP", "Off-spec" },
    },

    -- Per spec block; spec_id is the Blizzard spec id.
    specs = {
      [257] = {                        -- Holy
        spec_name = "Holy",
        is_main_spec = true,
        -- Difficulty subtree. Keys: M (Mythic) | H (Heroic) | N (Normal) | R (LFR).
        diff = {
          ["M"] = {
            items = {
              -- Keyed by item id (numeric).
              [210000] = {
                status    = "b",       -- b | n | o (Best | Not best | Outdated)
                value     = 12500,     -- absolute upgrade DPS/HPS
                percent   = 3.21,      -- relative upgrade %
                source    = "raidbots",-- raidbots | qe-live | icy-veins | manual | …
                boss      = "Sikran",  -- nullable; raid items only
                note      = "...",     -- free text from the upstream sim
                raid_slug = "instance-1307",      -- bnet raid slug from the wishlist row
                raid_name = "The Voidspire",      -- resolved display name (SeasonItem::SOURCE_DISPLAY_NAMES)
              },
              ...
            },
          },
          ["H"] = { items = { ... } },
        },
      },
    },
  },
  ...
}
```

#### Field rules

- **`status`** — three-letter enum. Mirror of the value the upstream sim
  (Raidbots / QE Live / icy-veins) produced. The addon renders it as
  the column "Status" in the RC voting frame.
- **`value` / `percent`** — both required. `0` is valid (sidegrade);
  negative is downgrade and the addon renders the row dimmed.
- **`source`** — used by the addon to filter. Phase 1 only `raid` is
  in scope; future sources are written but the addon may ignore.
- **`boss`** — nullable. If present, the addon shows it next to the
  item name as `Sikran ▸ Weight of Command`.
- **`note`** — free text. Addon shows on hover.

#### Marker fields (per character)

- **`bis_items`** — flat `{itemId = true}` map sourced from the user's
  curated Best-in-Slot `GearList` (type=bis). Powers the BiS column ★.
  The wishlist `status='b'` flag intentionally does NOT drive the marker
  — sim-flagged ≠ player-curated.
- **`current_items`** — `{itemId = true}` from `GearList` type=current
  (Battle.net inventory snapshot). Powers the Eq column ⚠ warning
  ("candidate already has this equipped").
- **`list_items`** — `{itemId = {listName, …}}` from `GearList`
  type=custom. Powers the List column ◆; tooltip enumerates every
  list the item lives in (e.g. "M+ keys", "PvP").

---

## `BlastROutbox.lua` (addon → bridge)

The addon **appends** events; the bridge **drains** them on every
successful push to `/api/v1/sync/loot-history`.

### Globals

```lua
BlastROutboxSchema = 1
BlastROutboxEvents = {
  {
    -- Per-event UUID stamped by the addon at write time. Backend
    -- enforces uniqueness so retries (network blip mid-push) cannot
    -- create duplicates regardless of how many cycles a single event
    -- ends up living in the outbox.
    event_uuid = "8f3a2bec-91d4-4a7e-9c12-1234567890ab",

    -- Monotonic sequence number per session; resets on /reload.
    -- Helps the addon decide which in-memory events have been
    -- confirmed-pushed when the outbox is rewritten by the bridge.
    seq = 17,

    kind = "loot_award",       -- only kind in Phase 1

    -- Wall-clock UTC at the moment RC fired the event.
    awarded_at = "2026-05-07T20:14:32Z",

    -- The raid this happened in. The addon resolves it from the
    -- current instance + boss kill context.
    raid = {
      slug       = "manaforge-omega",
      difficulty = "M",
      boss       = "Plexus Sentinel",
      pull_id    = "M+omega:1746649671",   -- our own id, see addon code
    },

    -- The character who received the item. Same naming convention as
    -- the wishlist payload.
    recipient = "Zaavrik-tarren-mill",

    -- The character (master looter) who awarded it.
    awarded_by = "Zavrikk-tarren-mill",

    -- Item details. bonus_ids ordered exactly as Blizzard returned them.
    item = {
      id        = 210000,
      ilvl      = 645,
      bonus_ids = { 12806, 9627, 9633 },
      enchant   = nil,
      gems      = { 213423 },
    },

    -- How RC labelled the award. One of:
    --   "ms"  — main spec
    --   "os"  — off spec
    --   "bis" — wishlist BiS
    --   "trash" — disenchant / vendor
    --   "free" — free-roll / random
    method = "bis",

    -- Optional free-text reason the loot master typed in RC.
    note = nil,
  },
  ...
}
```

After a successful POST to `/api/v1/sync/loot-history` the bridge sets
`BlastROutboxEvents = {}` and bumps a monotonic file-level marker:

```lua
BlastROutboxLastDrainedAt = "2026-05-07T20:15:05Z"
BlastROutboxLastDrainedSeq = 17
```

The addon uses `LastDrainedSeq` to know that any in-memory events with
`seq <= LastDrainedSeq` are confirmed-pushed and may be discarded if
they're still around (e.g. the addon was reloaded without WoW logout
and the in-memory queue diverged from disk).

---

## Out of scope for Phase 1

The following globals are reserved for later phases and **must not** be
written by the bridge until their phase ships, so older addon versions
don't trip on unknown data.

| Global                       | Phase | Purpose                                     |
| ---------------------------- | ----- | ------------------------------------------- |
| `BlastRGearData`             | 2     | Equipped + bag inventory snapshot           |
| `BlastRCurrencyData`         | 2     | Crests, Valorstones, etc.                   |
| `BlastRVaultData`            | 3     | Great Vault progress                        |
| `BlastRMythicPlusKeystones`  | 3     | Per-character keystone level + dungeon      |
| `BlastRGuildRoster`          | 3     | Roster diff for officer-only sync           |

---

## Versioning

`BlastRSchema` and `BlastROutboxSchema` are independent integers. Bumps:

- **Backwards-compatible additive change** (new optional field) — same
  schema number; both sides ignore unknown fields.
- **Breaking change** (rename, removal, type change) — bump the schema
  number. The addon refuses to load data with an unknown schema and
  shows the user a "please update BlastR Desktop" message; the bridge
  refuses to push to addons reporting a too-old schema.

The bridge writes its own version into `BlastRBridgeVersion` (string,
e.g. `"0.1.0-dev"`) so the addon can show it in the diagnostics page.
