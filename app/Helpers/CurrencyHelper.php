<?php

namespace App\Helpers;

class CurrencyHelper
{
    /**
     * Format an amount of copper (integer) into a gold string.
     * 1 Gold = 100 Silver * 100 Copper = 10,000 Copper.
     * Example: 2017223400 -> 201 722 G
     */
    public static function formatGold(int|float $amountCopper, bool $includeSymbol = true): string
    {
        $amountGold = $amountCopper / 10000;
        $formatted = number_format($amountGold, 0, '.', ' ');

        return $includeSymbol ? $formatted . ' G' : $formatted;
    }

    /**
     * Format an amount of copper for tooltips (Gold Silver Copper).
     */
    public static function formatGoldLong(int|float $amountCopper): string
    {
        $gold = floor($amountCopper / 10000);
        $silver = floor(($amountCopper % 10000) / 100);
        $copper = $amountCopper % 100;

        $result = [];
        if ($gold > 0) $result[] = number_format($gold, 0, '.', ' ') . 'g';
        if ($silver > 0) $result[] = $silver . 's';
        if ($copper > 0 || empty($result)) $result[] = $copper . 'c';

        return implode(' ', $result);
    }

    /**
     * Convert gold to copper for database storage.
     * 1 Gold = 10 000 Copper.
     */
    public static function goldToCopper(int|float $gold): int
    {
        return (int) round($gold * 10000);
    }

    /**
     * Calculate weeks of autonomy based on total reserves and weekly cost.
     */
    public static function calculateAutonomy(int|float $reserves, int|float $weeklyCost): float
    {
        if ($weeklyCost <= 0) {
            return 0;
        }

        return round($reserves / $weeklyCost, 1);
    }
}
