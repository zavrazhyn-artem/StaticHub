<?php

namespace App\Helpers;

/**
 * PHP-side mirror of resources/js/composables/useWowClasses.js so blade
 * templates (admin/server-rendered pages) can colour characters by class
 * without duplicating the hex map. Keep both in sync when Blizzard adds a
 * class — Vue side reads the same constants.
 */
class WowClassHelper
{
    private const CLASS_HEX = [
        'Warrior'      => '#C69B6D',
        'Paladin'      => '#F48CBA',
        'Hunter'       => '#ABD473',
        'Rogue'        => '#FFF468',
        'Priest'       => '#FFFFFF',
        'Death Knight' => '#C41F3B',
        'Shaman'       => '#0070DD',
        'Mage'         => '#3FC7EB',
        'Warlock'      => '#8788EE',
        'Monk'         => '#00FF98',
        'Druid'        => '#FF7C0A',
        'Demon Hunter' => '#A330C9',
        'Evoker'       => '#33937F',
    ];

    public static function hex(?string $playableClass): string
    {
        return self::CLASS_HEX[$playableClass] ?? '#ffffff';
    }
}
