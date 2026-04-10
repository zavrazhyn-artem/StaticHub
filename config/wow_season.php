<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Season identity
    |--------------------------------------------------------------------------
    */
    'season_number' => 17,
    'first_period'  => 1052,

    /*
    | Season start date (UTC) — the first weekly reset of the season.
    | Used to generate the full list of weeks for the roster week selector.
    */
    'season_start' => '2026-03-04T04:00:00Z', // EU reset, Season 17 Week 1

    /*
    |--------------------------------------------------------------------------
    | Weekly reset schedule per region
    |--------------------------------------------------------------------------
    | day  = ISO day of week (1=Mon … 7=Sun)
    | hour = UTC hour of the reset
    */
    'weekly_reset' => [
        'eu' => ['day' => 3, 'hour' => 4],  // Wednesday 04:00 UTC
        'us' => ['day' => 2, 'hour' => 15], // Tuesday  15:00 UTC
        'kr' => ['day' => 3, 'hour' => 2],  // Wednesday 02:00 UTC
        'tw' => ['day' => 3, 'hour' => 2],  // Wednesday 02:00 UTC
    ],

    /*
    |--------------------------------------------------------------------------
    | Expansion: Midnight — Season 17 (Midnight Season 1)
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Great Vault ilvl reward tables
    |--------------------------------------------------------------------------
    |
    | Maps activity completion level to the item level reward from the vault.
    | Source: wowaudit/core pve_constants.rb, season 17.
    |
    | raid:    difficulty label  → vault ilvl
    | dungeon: keystone level    → vault ilvl  (key 10 = max, -1 = empty)
    | delve:   tier (1-11)       → vault ilvl
    */
    'great_vault' => [
        'raid' => [
            'mythic'      => ['ilvl' => 272, 'track' => 'Myth'],
            'heroic'      => ['ilvl' => 259, 'track' => 'Hero'],
            'normal'      => ['ilvl' => 246, 'track' => 'Champion'],
            'raid_finder' => ['ilvl' => 233, 'track' => 'Veteran'],
        ],
        'dungeon' => [
            10 => ['ilvl' => 272, 'track' => 'Myth'],
             9 => ['ilvl' => 269, 'track' => 'Hero'],
             8 => ['ilvl' => 269, 'track' => 'Hero'],
             7 => ['ilvl' => 269, 'track' => 'Hero'],
             6 => ['ilvl' => 266, 'track' => 'Hero'],
             5 => ['ilvl' => 263, 'track' => 'Hero'],
             4 => ['ilvl' => 263, 'track' => 'Hero'],
             3 => ['ilvl' => 259, 'track' => 'Hero'],
             2 => ['ilvl' => 259, 'track' => 'Hero'],
             1 => ['ilvl' => 256, 'track' => 'Champion'], // Regular Mythic (no keystone)
             0 => ['ilvl' => 243, 'track' => 'Veteran'],  // Heroic dungeon
            -1 => ['ilvl' => null, 'track' => null],      // No run
        ],
        'delve' => [
            11 => ['ilvl' => 259, 'track' => 'Hero'],      // 8+ all give Hero 1/6
            10 => ['ilvl' => 259, 'track' => 'Hero'],
             9 => ['ilvl' => 259, 'track' => 'Hero'],
             8 => ['ilvl' => 259, 'track' => 'Hero'],
             7 => ['ilvl' => 256, 'track' => 'Champion'],  // Champion 4/6
             6 => ['ilvl' => 253, 'track' => 'Champion'],  // Champion 3/6
             5 => ['ilvl' => 246, 'track' => 'Champion'],  // Champion 1/6
             4 => ['ilvl' => 243, 'track' => 'Veteran'],   // Veteran 4/6
             3 => ['ilvl' => 240, 'track' => 'Veteran'],   // Veteran 3/6
             2 => ['ilvl' => 237, 'track' => 'Veteran'],   // Veteran 2/6
             1 => ['ilvl' => 233, 'track' => 'Veteran'],   // Veteran 1/6
             0 => ['ilvl' => null, 'track' => null],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vault Great Vault Blacklisted Periods
    |--------------------------------------------------------------------------
    | Week IDs (Blizzard period) during which vault slots are disabled.
    | Matches wowaudit GREAT_VAULT_BLACKLISTED_PERIODS.
    */
    'great_vault_blacklisted_periods' => [932, 974, 975, 1000, 1052, 1053, 1054],

    /*
    | Current PvP season ID (used to verify bracket data belongs to this season).
    */
    'pvp_season' => 41,

    /*
    | Achievement statistic IDs for delve tier completions (Blizzard API).
    | Used to track World vault slot progress by computing weekly deltas.
    | Key = tier number (1-11), Value = Blizzard statistic ID.
    */
    'delve_tier_stat_ids' => [
        1  => 40766,
        2  => 40767,
        3  => 40768,
        4  => 40769,
        5  => 40770,
        6  => 40771,
        7  => 40772,
        8  => 40773,
        9  => 40774,
        10 => 40775,
        11 => 40776,
    ],

    'delve_total_stat_id' => 40734,

    'delve_coffer_keys_stat_id' => 40749,

    /*
    |--------------------------------------------------------------------------
    | Crest currency achievement stat IDs (from "Character" category)
    |--------------------------------------------------------------------------
    */
    'crest_stat_ids' => [
        'adventurer' => 62292,
        'veteran'    => 62293,
        'champion'   => 62294,
        'hero'       => 62295,
        'myth'       => 62296,
    ],

    /*
    |--------------------------------------------------------------------------
    | Expansion Dungeon Stat IDs
    |--------------------------------------------------------------------------
    | Used to count heroic & regular-mythic dungeon completions for vault.
    | Source: wowaudit EXPANSION_DUNGEONS.
    | heroic_id / mythic_id are achievement statistic IDs from Blizzard API.
    */
    'expansion_dungeons' => [
        ['name' => "Den of Nalorakk",      'heroic_id' => 61651, 'mythic_id' => 61652],
        ['name' => "Magister's Terrace",   'heroic_id' => 61216, 'mythic_id' => 61217],
        ['name' => "Murder Row",           'heroic_id' => 61274, 'mythic_id' => 61275],
        ['name' => "Windrunner Spire",     'heroic_id' => 41294, 'mythic_id' => 41295],
        ['name' => "Maisara Caverns",      'heroic_id' => 61654, 'mythic_id' => 61655],
        ['name' => "Nexus-Point Xenas",    'heroic_id' => 61657, 'mythic_id' => 61658],
        ['name' => "The Blinding Vale",    'heroic_id' => 61660, 'mythic_id' => 61661],
        ['name' => "Voidscar Arena",       'heroic_id' => 61512, 'mythic_id' => 61513],
    ],

    /*
    |--------------------------------------------------------------------------
    | Keystone Dungeon IDs for current M+ season
    |--------------------------------------------------------------------------
    | mythic_id = achievement stat ID used for vault (0 = not trackable via stats)
    */
    'keystone_dungeons' => [
        ['id' => 558, 'name' => "Magister's Terrace",  'mythic_id' => 61217, 'legacy' => false],
        ['id' => 560, 'name' => "Maisara Caverns",     'mythic_id' => 61655, 'legacy' => false],
        ['id' => 559, 'name' => "Nexus-Point Xenas",   'mythic_id' => 61658, 'legacy' => false],
        ['id' => 557, 'name' => "Windrunner Spire",    'mythic_id' => 41295, 'legacy' => false],
        ['id' => 402, 'name' => "Algeth'ar Academy",   'mythic_id' => 16088, 'legacy' => true],
        ['id' => 556, 'name' => "Pit of Saron",        'mythic_id' => 0,     'legacy' => true],
        ['id' => 239, 'name' => "Seat of the Triumvirate", 'mythic_id' => 12613, 'legacy' => true],
        ['id' => 161, 'name' => "Skyreach",            'mythic_id' => 10195, 'legacy' => true],
    ],

    /*
    |--------------------------------------------------------------------------
    | Weekly Quest IDs
    |--------------------------------------------------------------------------
    | Used to add bonus delves to the world vault calculation.
    | Matching any quest from a group counts the entire quest as done.
    */
    'weekly_quest_ids' => [
        'haranir'    => [88993, 88994, 88996, 88997, 88995, 93891],
        'saltheril'  => [90573, 90574, 90575, 90576, 93889],
        'abundance'  => [89507, 93890],
        'stormarion' => [93892, 94581],
        'unity'      => [93890, 93889, 93891, 93910, 93769, 93909, 93911, 93767, 93912, 93913, 93892, 93766, 94457],
    ],

    /*
    | Weekly event quest IDs (any one = event completed).
    */
    'weekly_event_quest_ids' => [83347, 83345, 83364, 83362, 83366, 83359, 83365],

    /*
    | Prey quest IDs by difficulty tier contribution.
    | normal = +1 tier, hard = +5 tier, nightmare = +8 tier to world vault.
    */
    'prey_quest_ids' => [
        'normal' => [
            91124, 91110, 91100, 91105, 91115, 91114, 91113, 91121, 91107, 91117,
            91101, 91095, 91102, 91106, 91111, 91116, 91098, 91099, 91122, 91123,
            91103, 91097, 91112, 91096, 91118, 91109, 91119, 91108, 91120, 91104,
        ],
        'hard' => [
            91240, 91255, 91245, 91230, 91222, 91220, 91242, 91232, 91252, 91247,
            91246, 91253, 91224, 91251, 91234, 91244, 91218, 91212, 91250, 91238,
            91243, 91249, 91236, 91216, 91214, 91210, 91248, 91254, 91226, 91228,
        ],
        'nightmare' => [
            91241, 91256, 91261, 91269, 91259, 91233, 91231, 91221, 91265, 91225,
            91260, 91258, 91264, 91219, 91235, 91237, 91223, 91239, 91213, 91268,
            91266, 91211, 91267, 91227, 91263, 91217, 91257, 91215, 91229, 91262,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Raid Boss Achievement Stat IDs
    |--------------------------------------------------------------------------
    | Maps each boss name to its Blizzard achievement statistic IDs per difficulty.
    | These IDs live in the "Dungeons & Raids" category of achievement statistics.
    | Used to determine weekly boss kills via last_updated_timestamp comparison.
    | Source: wowaudit VALID_RAIDS[:live] raid_ids.
    */
    'raid_boss_achievement_ids' => [
        'Imperator Averzian'       => ['raid_finder' => [61276], 'normal' => [61277], 'heroic' => [61278], 'mythic' => [61279]],
        'Vorasius'                 => ['raid_finder' => [61280], 'normal' => [61281], 'heroic' => [61282], 'mythic' => [61283]],
        'Fallen-King Salhadaar'    => ['raid_finder' => [61284], 'normal' => [61285], 'heroic' => [61286], 'mythic' => [61287]],
        'Vaelgor & Ezzorak'        => ['raid_finder' => [61288], 'normal' => [61289], 'heroic' => [61290], 'mythic' => [61291]],
        'Lightblinded Vanguard'    => ['raid_finder' => [61292], 'normal' => [61293], 'heroic' => [61294], 'mythic' => [61295]],
        'Crown of the Cosmos'      => ['raid_finder' => [61296], 'normal' => [61297], 'heroic' => [61298], 'mythic' => [61299]],
        'Chimaerus the Undreamt God' => ['raid_finder' => [61474], 'normal' => [61475], 'heroic' => [61476], 'mythic' => [61477]],
        "Belo'ren, Child of Al'ar" => ['raid_finder' => [61300], 'normal' => [61301], 'heroic' => [61302], 'mythic' => [61303]],
        'Midnight Falls'           => ['raid_finder' => [61304], 'normal' => [61305], 'heroic' => [61306], 'mythic' => [61307]],
    ],

    /*
    | The vault counts only the LAST raid tier in a season (for_vault logic).
    | Only bosses in these instances contribute to raid vault slots.
    | "for_vault" = true for the newest raid instance, false for older wings
    | that opened earlier. Currently all Season 1 bosses count.
    */
    'vault_raid_bosses' => [
        'Imperator Averzian',
        'Vorasius',
        'Fallen-King Salhadaar',
        'Vaelgor & Ezzorak',
        'Lightblinded Vanguard',
        'Crown of the Cosmos',
        'Chimaerus the Undreamt God',
        "Belo'ren, Child of Al'ar",
        'Midnight Falls',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum character level
    |--------------------------------------------------------------------------
    */
    'max_player_level' => 90,

    /*
    |--------------------------------------------------------------------------
    | Enchantable slots
    |--------------------------------------------------------------------------
    | Gear slots that require a permanent enchant in Season 17 (Midnight S1).
    | Source: wowaudit gear_constants.rb ENCHANTS keys (mapped to Blizzard slot.type).
    */
    'enchantable_slots' => [
        'HEAD',
        'SHOULDER',
        'CHEST',
        'LEGS',
        'FEET',
        'FINGER_1',
        'FINGER_2',
        'MAIN_HAND',
        'OFF_HAND',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enchant quality map
    |--------------------------------------------------------------------------
    | Maps enchantment_id => quality (2 or 4) per slot.
    | Quality 4 = best-in-slot tier enchant, quality 2 = lower tier.
    | Source: wowaudit gear_constants.rb ENCHANTS hash.
    | Only enchants present here are recognized; unknown enchants get quality 0.
    */
    'enchant_quality_map' => [
        'HEAD' => [
            7988 => 2, 7989 => 2, 7990 => 2, 7991 => 4, // Blessing of Speed
            7958 => 2, 7959 => 2, 7960 => 2, 7961 => 4, // Hex of Leeching
            8014 => 2, 8015 => 2, 8016 => 2, 8017 => 4, // Rune of Avoidance
        ],
        'SHOULDER' => [
            8028 => 2, 8029 => 2, // Thalassian Recovery
            7970 => 2, 7971 => 2, // Flight of the Eagle
            7998 => 2, 7999 => 2, // Nature's Grace
            7972 => 2, 7973 => 4, // Akil'zon's Swiftness
            8000 => 2, 8001 => 4, // Amirdrassil's Grace
            8030 => 2, 8031 => 4, // Silvermoon's Mending
        ],
        'CHEST' => [
            7956 => 2, 7957 => 4, // Mark of Nalorakk
            8012 => 2, 8013 => 4, // Mark of the Magister
            7984 => 2, 7985 => 4, // Mark of the Rootwarden
            7986 => 2, 7987 => 4, // Mark of the Worldsoul
        ],
        'LEGS' => [
            7938 => 2, 7939 => 2, 7936 => 2, 7937 => 4, // Spellthread
            7934 => 2, 7935 => 4, // Sunfire Silk Spellthread
            8160 => 2, 8161 => 2, 8158 => 2, 8159 => 4, // Armor Kits (Scout/Hunter)
            8162 => 2, 8163 => 4, // Blood Knight's Armor Kit
        ],
        'FEET' => [
            8018 => 2, 8019 => 4, // Farstrider's Hunt
            7962 => 2, 7963 => 4, // Lynx's Dexterity
            7992 => 2, 7993 => 4, // Shaladrassil's Roots
        ],
        'FINGER' => [
            7994 => 2, 7995 => 2, 7996 => 2, 7997 => 4, // Nature's Wrath/Fury (crit)
            8020 => 2, 8021 => 2, 8024 => 2, 8025 => 4, // Thalassian Haste / Silvermoon's Alacrity
            8022 => 2, 8023 => 2, 8026 => 2, 8027 => 4, // Thalassian Versatility / Silvermoon's Tenacity
            7964 => 2, 7965 => 2, 7968 => 2, 7969 => 4, // Amani Mastery / Zul'jin's Mastery
            7966 => 2, 7967 => 4, // Eyes of the Eagle
        ],
        'WEAPON' => [
            3370 => 4, 3847 => 4, 3368 => 4, // DK runes (Razorice, Stoneskin, Fallen Crusader)
            6241 => 4, 6242 => 4, 6243 => 4, 6244 => 4, 6245 => 4, // DK runes (Sanguination, Spellwarding, Hysteria, Thirst, Apocalypse)
            8038 => 2, 8039 => 4, // Acuity of the Ren'dorei
            8040 => 2, 8041 => 4, // Arcane Mastery
            7982 => 2, 7983 => 4, // Berserker's Rage
            8036 => 2, 8037 => 4, // Flames of the Sin'dorei
            7980 => 2, 7981 => 4, // Jan'alai's Precision
            7978 => 2, 7979 => 4, // Strength of Halazzi
            8008 => 2, 8009 => 4, // Worldsoul Aegis
            8006 => 2, 8007 => 4, // Worldsoul Cradle
            8010 => 2, 8011 => 4, // Worldsoul Tenacity
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tier set slots
    |--------------------------------------------------------------------------
    */
    'tier_slots' => [
        'H' => 'HEAD',
        'S' => 'SHOULDER',
        'C' => 'CHEST',
        'G' => 'HANDS',
        'L' => 'LEGS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Item track ilvl thresholds (Season 17)
    |--------------------------------------------------------------------------
    | Maps minimum ilvl → upgrade track name. Used as FALLBACK when bonus_id
    | lookup fails (e.g. catalyst/crafted items without a standard bonus_id).
    | Keys sorted descending at runtime — order here does not matter.
    | Source: wowaudit pve_constants.rb track_cutoffs for season 17.
    */
    'tier_ilvl_thresholds' => [
        272 => 'Myth',
        259 => 'Hero',
        246 => 'Champion',
        233 => 'Veteran',
        220 => 'Adventurer',
        207 => 'Explorer',
          0 => 'Explorer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Raid difficulties (Blizzard API difficulty ID → key)
    |--------------------------------------------------------------------------
    */
    'raid_difficulties' => [
        1 => 'raid_finder',
        3 => 'normal',
        4 => 'heroic',
        5 => 'mythic',
    ],

    /*
    |--------------------------------------------------------------------------
    | Current raid instances (for resolveRaids boss kill tracking)
    |--------------------------------------------------------------------------
    | Boss names must match Blizzard API encounter.name exactly.
    | Source: wowaudit VALID_RAIDS[:live] for Season 17.
    */
    'current_raid_instances' => [
        'The Voidspire' => [
            'Imperator Averzian',
            'Vorasius',
            'Fallen-King Salhadaar',
            'Vaelgor & Ezzorak',
            'Lightblinded Vanguard',
            'Crown of the Cosmos',
        ],
        'The Dreamrift' => [
            'Chimaerus the Undreamt God',
        ],
        'March on Quel\'Danas' => [
            "Belo'ren, Child of Al'ar",
            'Midnight Falls',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Spark label for this season
    |--------------------------------------------------------------------------
    | Matches the name_description.display_string of crafted items.
    */
    'spark_label' => 'Radiance Crafted',

    /*
    |--------------------------------------------------------------------------
    | Crafted item ilvl color tiers (passed to frontend)
    |--------------------------------------------------------------------------
    | Each entry: [min_ilvl, max_ilvl, css_color_class].
    | Evaluated top-down, first match wins.
    */
    'crafted_ilvl_tiers' => [
        ['min' => 275, 'max' => 285, 'color' => 'text-orange-400'],
        ['min' => 262, 'max' => 274, 'color' => 'text-purple-400'],
        ['min' => 246, 'max' => 261, 'color' => 'text-green-400'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Item Upgrade Tracks — Season 17 Bonus IDs
    |--------------------------------------------------------------------------
    | Maps a Bonus ID to its upgrade track info.
    | Source: wowaudit bonus_ids.rb BONUS_IDS_BY_SEASON[17].
    |
    | Season 17:
    |   mythic:        12801..12806
    |   heroic:        12793..12798
    |   normal:        12785..12790
    |   raid_finder:   12777..12782
    |   world_advanced:12769..12774  (Adventurer)
    */
    'item_upgrade_tracks' => [
        // Myth (6 levels)
        12801 => ['track' => 'Myth', 'level' => 1, 'max' => 6],
        12802 => ['track' => 'Myth', 'level' => 2, 'max' => 6],
        12803 => ['track' => 'Myth', 'level' => 3, 'max' => 6],
        12804 => ['track' => 'Myth', 'level' => 4, 'max' => 6],
        12805 => ['track' => 'Myth', 'level' => 5, 'max' => 6],
        12806 => ['track' => 'Myth', 'level' => 6, 'max' => 6],
        // Hero (6 levels)
        12793 => ['track' => 'Hero', 'level' => 1, 'max' => 6],
        12794 => ['track' => 'Hero', 'level' => 2, 'max' => 6],
        12795 => ['track' => 'Hero', 'level' => 3, 'max' => 6],
        12796 => ['track' => 'Hero', 'level' => 4, 'max' => 6],
        12797 => ['track' => 'Hero', 'level' => 5, 'max' => 6],
        12798 => ['track' => 'Hero', 'level' => 6, 'max' => 6],
        // Champion / normal (6 levels)
        12785 => ['track' => 'Champion', 'level' => 1, 'max' => 6],
        12786 => ['track' => 'Champion', 'level' => 2, 'max' => 6],
        12787 => ['track' => 'Champion', 'level' => 3, 'max' => 6],
        12788 => ['track' => 'Champion', 'level' => 4, 'max' => 6],
        12789 => ['track' => 'Champion', 'level' => 5, 'max' => 6],
        12790 => ['track' => 'Champion', 'level' => 6, 'max' => 6],
        // Veteran / raid_finder (6 levels)
        12777 => ['track' => 'Veteran', 'level' => 1, 'max' => 6],
        12778 => ['track' => 'Veteran', 'level' => 2, 'max' => 6],
        12779 => ['track' => 'Veteran', 'level' => 3, 'max' => 6],
        12780 => ['track' => 'Veteran', 'level' => 4, 'max' => 6],
        12781 => ['track' => 'Veteran', 'level' => 5, 'max' => 6],
        12782 => ['track' => 'Veteran', 'level' => 6, 'max' => 6],
        // Adventurer / world_advanced (6 levels)
        12769 => ['track' => 'Adventurer', 'level' => 1, 'max' => 6],
        12770 => ['track' => 'Adventurer', 'level' => 2, 'max' => 6],
        12771 => ['track' => 'Adventurer', 'level' => 3, 'max' => 6],
        12772 => ['track' => 'Adventurer', 'level' => 4, 'max' => 6],
        12773 => ['track' => 'Adventurer', 'level' => 5, 'max' => 6],
        12774 => ['track' => 'Adventurer', 'level' => 6, 'max' => 6],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reputation faction IDs for the current expansion
    |--------------------------------------------------------------------------
    | Source: wowaudit reputation_constants.rb REPUTATIONS.
    */
    'reputations' => [
        2710 => 'silvermoon_court',
        2696 => 'amani_tribe',
        2704 => 'harati',
        2699 => 'the_singularity',
    ],

    /*
    |--------------------------------------------------------------------------
    | Specializations
    |--------------------------------------------------------------------------
    */
    'specializations' => [
        // Death Knight
        250 => 'tank',  // Blood
        251 => 'mdps',  // Frost
        252 => 'mdps',  // Unholy

        // Demon Hunter
        577 => 'mdps',  // Havoc
        581 => 'tank',  // Vengeance
        1480 => 'rdps', // Devourer

        // Druid
        102 => 'rdps',  // Balance
        103 => 'mdps',  // Feral
        104 => 'tank',  // Guardian
        105 => 'heal',  // Restoration

        // Evoker
        1467 => 'rdps', // Devastation
        1468 => 'heal', // Preservation
        1473 => 'rdps', // Augmentation

        // Hunter
        253 => 'rdps',  // Beast Mastery
        254 => 'rdps',  // Marksmanship
        255 => 'mdps',  // Survival

        // Mage
        62 => 'rdps',   // Arcane
        63 => 'rdps',   // Fire
        64 => 'rdps',   // Frost

        // Monk
        268 => 'tank',  // Brewmaster
        270 => 'heal',  // Mistweaver
        269 => 'mdps',  // Windwalker

        // Paladin
        65 => 'heal',   // Holy
        66 => 'tank',   // Protection
        70 => 'mdps',   // Retribution

        // Priest
        256 => 'heal',  // Discipline
        257 => 'heal',  // Holy
        258 => 'rdps',  // Shadow

        // Rogue
        259 => 'mdps',  // Assassination
        260 => 'mdps',  // Outlaw
        261 => 'mdps',  // Subtlety

        // Shaman
        262 => 'rdps',  // Elemental
        263 => 'mdps',  // Enhancement
        264 => 'heal',  // Restoration

        // Warlock
        265 => 'rdps',  // Affliction
        266 => 'rdps',  // Demonology
        267 => 'rdps',  // Destruction

        // Warrior
        71 => 'mdps',   // Arms
        72 => 'mdps',   // Fury
        73 => 'tank',   // Protection
    ],

];
