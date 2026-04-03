<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Expansion: Midnight
    |--------------------------------------------------------------------------
    */

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

];
