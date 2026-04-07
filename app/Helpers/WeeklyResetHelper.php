<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Pure utility for WoW weekly-reset timestamps and period keys.
 * All methods are stateless and region-aware.
 */
final class WeeklyResetHelper
{
    /**
     * Unix timestamp of the most recent weekly reset for the given region.
     */
    public static function resetTimestamp(string $region, ?int $now = null): int
    {
        $now  = $now ?? time();
        $cfg  = self::regionConfig($region);
        $day  = $cfg['day'];  // ISO day: 1=Mon … 7=Sun
        $hour = $cfg['hour']; // UTC hour

        // Find the most recent occurrence of $day at $hour:00 UTC
        $todayDow = (int) gmdate('N', $now); // 1=Mon … 7=Sun
        $diff     = ($todayDow - $day + 7) % 7;

        // Start of today in UTC, then subtract $diff days, add $hour hours
        $todayStart = gmmktime(0, 0, 0, (int) gmdate('n', $now), (int) gmdate('j', $now), (int) gmdate('Y', $now));
        $candidate  = $todayStart - ($diff * 86400) + ($hour * 3600);

        // If candidate is in the future, go back 7 days
        if ($candidate > $now) {
            $candidate -= 7 * 86400;
        }

        return $candidate;
    }

    /**
     * ISO week key for the current WoW period, e.g. "2026-W14".
     */
    public static function periodKey(string $region, ?int $now = null): string
    {
        $ts = self::resetTimestamp($region, $now);

        return gmdate('o-\WW', $ts);
    }

    /**
     * Numeric period ID compatible with wowaudit formula:
     * period = 641 + ((reset_timestamp + 302400) - 1523372400) / 604799
     */
    public static function periodNumber(string $region, ?int $now = null): int
    {
        $ts = self::resetTimestamp($region, $now);

        return (int) (641 + (($ts + 302400) - 1523372400) / 604799);
    }

    /**
     * Normalized region key (lowercase).
     */
    public static function normalizeRegion(string $region): string
    {
        return strtolower(trim($region));
    }

    /**
     * Get config for a region, falling back to EU defaults.
     *
     * @return array{day: int, hour: int}
     */
    private static function regionConfig(string $region): array
    {
        $all = config('wow_season.weekly_reset', []);

        return $all[self::normalizeRegion($region)]
            ?? $all['eu']
            ?? ['day' => 3, 'hour' => 4];
    }
}
