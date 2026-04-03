<?php

declare(strict_types=1);

namespace App\Support;

class IconHelper
{
    public static function classSlug(?string $className): string
    {
        $className = $className ?? 'unknown';
        return strtolower(str_replace([" ", "'"], '_', $className));
    }

    public static function roleSlug(?string $role): ?string
    {
        if ($role === null) {
            return null;
        }
        $role = strtolower($role);
        return match ($role) {
            'healer', 'heal' => 'heal',
            'ranged', 'rdps', 'range' => 'range',
            'melee', 'mdps' => 'melee',
            'tank' => 'tank',
            default => null,
        };
    }

    public static function classEmoji(?string $className): string
    {
        $slug = self::classSlug($className);
        return match ($slug) {
            'death_knight' => '<:death_knight:1489274623131517058>',
            'demon_hunter' => '<:demon_hunter:1489274624687472690>',
            'druid' => '<:druid:1489274621877424341>',
            'evoker' => '<:evoker:1489275608545497268>',
            'hunter' => '<:hunter:1489274620652552233>',
            'mage' => '<:mage:1489274619084013588>',
            'monk' => '<:monk:1489274617888768000>',
            'paladin' => '<:paladin:1489274615338369145>',
            'priest' => '<:priest:1489274614012973120>',
            'rogue' => '<:rogue:1489274613060862043>',
            'shaman' => '<:shaman:1489274616722620556>',
            'warlock' => '<:warlock:1489274610607460482>',
            'warrior' => '<:warrior:1489274609323741284>',
            default => '❓',
        };
    }

    public static function roleEmoji(?string $role): string
    {
        $slug = self::roleSlug($role);
        return match ($slug) {
            'tank' => '<:tank:1489274452125552712>',
            'heal' => '<:heal:1489274455820865597>',
            'melee' => '<:melee:1489274454768091277>',
            'range' => '<:range:1489274453212004352>',
            default => '❓',
        };
    }

    public static function classUrl(?string $className): string
    {
        $slug = self::classSlug($className);
        return asset("images/classes/{$slug}.svg");
    }

    public static function classUrlAbsolute(?string $className): string
    {
        $slug = self::classSlug($className);
        return config('app.url') . "/images/classes/{$slug}.svg";
    }

    public static function roleUrl(?string $role): ?string
    {
        $slug = self::roleSlug($role);
        return $slug ? asset("images/roles/{$slug}.svg") : null;
    }

    public static function roleUrlAbsolute(?string $role): ?string
    {
        $slug = self::roleSlug($role);
        return $slug ? config('app.url') . "/images/roles/{$slug}.svg" : null;
    }
}
