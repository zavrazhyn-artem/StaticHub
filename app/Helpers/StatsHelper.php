<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Pure statistical helpers for empirical baselines. No state, no I/O.
 */
class StatsHelper
{
    /**
     * Linear-interpolated percentile. p in [0,100].
     *
     * @param array<int|float> $values
     */
    public static function percentile(array $values, float $p): float
    {
        if (empty($values)) {
            return 0.0;
        }
        sort($values);
        $idx = ($p / 100) * (count($values) - 1);
        $lower = (int) floor($idx);
        $upper = (int) ceil($idx);
        if ($lower === $upper) {
            return (float) $values[$lower];
        }
        $frac = $idx - $lower;
        return $values[$lower] * (1 - $frac) + $values[$upper] * $frac;
    }

    /**
     * Reduce a list of observations to a baseline envelope.
     *
     * @param array<int|float> $values
     * @return array{median: float, p5: float, p95: float, sample: int}|null
     */
    public static function summarize(array $values): ?array
    {
        if (empty($values)) {
            return null;
        }
        return [
            'median' => round(self::percentile($values, 50), 2),
            'p5'     => round(self::percentile($values, 5), 2),
            'p95'    => round(self::percentile($values, 95), 2),
            'sample' => count($values),
        ];
    }
}
