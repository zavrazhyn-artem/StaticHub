<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Raid Buff / Debuff / Utility Checklist
    |--------------------------------------------------------------------------
    |
    | Based on WoW Midnight (12.0) raid buffs.
    | Each entry maps a buff name to the classes that provide it.
    |
    */

    'buffs_debuffs' => [
        '5% Intellect'          => ['Mage'],
        '5% Attack Power'       => ['Warrior'],
        '5% Stamina'            => ['Priest'],
        '5% Physical Damage'    => ['Monk'],
        '3% Magic Damage'       => ['Demon Hunter'],
        'Devotion Aura'         => ['Paladin'],
        '3% Versatility'        => ['Druid'],
        '3.6% Damage Reduction' => ['Rogue'],
        "Hunter's Mark"         => ['Hunter'],
        'Skyfury'               => ['Shaman'],
    ],

    'utility' => [
        'Bloodlust'             => ['Mage', 'Shaman', 'Evoker', 'Hunter'],
        'Combat Resurrection'   => ['Death Knight', 'Druid', 'Paladin', 'Warlock'],
        'Movement Speed'        => ['Druid', 'Shaman'],
        'Healthstone'           => ['Warlock'],
        'Gateway'               => ['Warlock'],
        'Innervate'             => ['Druid'],
        'Anti Magic Zone'       => ['Death Knight'],
        'Blessing of Protection' => ['Paladin'],
        'Rallying Cry'          => ['Warrior'],
        'Darkness'              => ['Demon Hunter'],
        'Immunity'              => ['Paladin', 'Mage', 'Hunter'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Limits per Difficulty
    |--------------------------------------------------------------------------
    */

    'role_limits' => [
        'mythic' => [
            'total' => 20,
            'tank'  => 2,
            'heal'  => ['min' => 2, 'max' => 4],
            'dps'   => ['min' => 14, 'max' => 16],
        ],
        'heroic' => [
            'total' => 30,
            'tank'  => 2,
            'heal'  => ['min' => 3, 'max' => 6],
            'dps'   => ['min' => 22, 'max' => 26],
        ],
        'normal' => [
            'total' => 30,
            'tank'  => 2,
            'heal'  => ['min' => 3, 'max' => 6],
            'dps'   => ['min' => 22, 'max' => 26],
        ],
        'raid_finder' => [
            'total' => 25,
            'tank'  => 2,
            'heal'  => ['min' => 4, 'max' => 6],
            'dps'   => ['min' => 17, 'max' => 19],
        ],
    ],
];
