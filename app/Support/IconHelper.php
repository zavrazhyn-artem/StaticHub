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
        $id = config("discord_emojis.classes.{$slug}");
        return $id ? "<:{$slug}:{$id}>" : '';
    }

    public static function roleEmoji(?string $role): string
    {
        $slug = self::roleSlug($role);
        if (!$slug) {
            return '';
        }
        $id = config("discord_emojis.roles.{$slug}");
        return $id ? "<:{$slug}:{$id}>" : '';
    }

    public static function statusEmoji(?string $status): string
    {
        $key = in_array($status, ['present', 'late', 'tentative', 'absent'], true) ? $status : 'pending';
        $id = config("discord_emojis.rsvp.{$key}");
        return $id ? "<:rsvp_{$key}:{$id}>" : '';
    }

    public static function benchEmoji(): string
    {
        $id = config('discord_emojis.rsvp.bench');
        return $id ? "<:rsvp_bench:{$id}>" : '';
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
