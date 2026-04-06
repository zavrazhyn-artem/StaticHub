<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Expansion: Midnight
    |--------------------------------------------------------------------------
    */

    /*
    | Maximum character level for the current expansion.
    | Characters below this level are excluded from the roster/characters list.
    */
    'max_player_level' => 90,

    /*
    | Gear slots that require a permanent enchant. The compiler flags any of
    | these slots that are missing a PERMANENT-type enchantment.
    | Values must match the `slot.type` key from the Blizzard equipment API.
    */
    'enchantable_slots' => [
        'CHEST',
        'LEGS',
        'FEET',
        'FINGER_1',
        'FINGER_2',
        'MAIN_HAND',
        'OFF_HAND',
    ],

    /*
    | Maps a minimum item level to a difficulty shorthand used for tier-set
    | classification. Keys are minimum ilvl integers; values are labels.
    | The compiler walks this map from highest key to lowest and assigns
    | the first key whose threshold the item level meets or exceeds.
    |
    |  'M' = Mythic   'H' = Heroic   'N' = Normal   'F' = LFR (Finder)
    |
    | Sorted descending at runtime — order here does not matter.
    */
    'tier_ilvl_thresholds' => [
        730 => 'M',
        717 => 'H',
        704 => 'N',
        0   => 'F',
    ],

    /*
    | Maps each raid instance to its strictly ordered boss list.
    |
    | The compiler iterates this config list — NOT the API encounter order —
    | to guarantee a uniform boss array for every character in the DTO, even
    | for bosses that have never been attempted on any difficulty.
    |
    | Boss names must match the `encounter.name` values returned by the
    | Blizzard raid API exactly so that kill data can be correlated.
    |
    | Key   = instance name (must match Blizzard API `instance.name`)
    | Value = ordered array of boss names
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
    ],

    /*
    | Maps the short abbreviation used as the DTO key to the Blizzard API
    | `slot.type` value for each class tier-set slot.
    |
    |  H = Head   S = Shoulder   C = Chest   G = Gloves (Hands)   L = Legs
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
    | Specializations
    |--------------------------------------------------------------------------
    |
    | Авторитетне джерело ролей для кожного спека.
    | Ключ = Bnet spec ID, значення = роль (tank|heal|mdps|rdps).
    |
    | Blizzard API повертає лише TANK/HEALER/DPS — тому mdps/rdps розрізняємо тут.
    | Команда `php artisan wow:sync-specializations` тягне name/class/icon з API,
    | а роль бере з цього конфігу. Якщо Blizzard змінить роль спека —
    | оновіть цей список і перезапустіть команду.
    */
    'specializations' => [
        // Death Knight
        250 => 'tank',  // Blood
        251 => 'mdps',  // Frost
        252 => 'mdps',  // Unholy

        // Demon Hunter
        577 => 'mdps',  // Havoc
        581 => 'tank',  // Vengeance
        1480 => 'rdps',  // Devourer

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

    /*
    |--------------------------------------------------------------------------
    | Item Upgrade Tracks
    |--------------------------------------------------------------------------
    |
    | Maps a Bonus ID to its corresponding upgrade track name, current level,
    | and maximum possible level. These are used to render badges on items
    | and for future mathematical calculations.
    */
    'item_upgrade_tracks' => [
        // Placeholder keys -> Structured data
        12801 => ['track' => 'Myth', 'level' => 1, 'max' => 6],
        12802 => ['track' => 'Myth', 'level' => 2, 'max' => 6],
        12803 => ['track' => 'Myth', 'level' => 3, 'max' => 6],
        12804 => ['track' => 'Myth', 'level' => 4, 'max' => 6],
        12805 => ['track' => 'Myth', 'level' => 5, 'max' => 6],
        12806 => ['track' => 'Myth', 'level' => 6, 'max' => 6],
        12793 => ['track' => 'Hero', 'level' => 1, 'max' => 6],
        12794 => ['track' => 'Hero', 'level' => 2, 'max' => 6],
        12795 => ['track' => 'Hero', 'level' => 3, 'max' => 6],
        12796 => ['track' => 'Hero', 'level' => 4, 'max' => 6],
        12797 => ['track' => 'Hero', 'level' => 5, 'max' => 6],
        12798 => ['track' => 'Hero', 'level' => 6, 'max' => 6],
        12785 => ['track' => 'Champion', 'level' => 1, 'max' => 6],
        12786 => ['track' => 'Champion', 'level' => 2, 'max' => 6],
        12787 => ['track' => 'Champion', 'level' => 3, 'max' => 6],
        12788 => ['track' => 'Champion', 'level' => 4, 'max' => 6],
        12789 => ['track' => 'Champion', 'level' => 5, 'max' => 6],
        12790 => ['track' => 'Champion', 'level' => 6, 'max' => 6],
        12777 => ['track' => 'Veteran', 'level' => 1, 'max' => 6],
        12778 => ['track' => 'Veteran', 'level' => 2, 'max' => 6],
        12779 => ['track' => 'Veteran', 'level' => 3, 'max' => 6],
        12780 => ['track' => 'Veteran', 'level' => 4, 'max' => 6],
        12781 => ['track' => 'Veteran', 'level' => 5, 'max' => 6],
        12782 => ['track' => 'Veteran', 'level' => 6, 'max' => 6],
        12769 => ['track' => 'Adventurer', 'level' => 1, 'max' => 6],
        12770 => ['track' => 'Adventurer', 'level' => 2, 'max' => 6],
        12771 => ['track' => 'Adventurer', 'level' => 3, 'max' => 6],
        12772 => ['track' => 'Adventurer', 'level' => 4, 'max' => 6],
        12773 => ['track' => 'Adventurer', 'level' => 5, 'max' => 6],
        12774 => ['track' => 'Adventurer', 'level' => 6, 'max' => 6],
    ],

];
