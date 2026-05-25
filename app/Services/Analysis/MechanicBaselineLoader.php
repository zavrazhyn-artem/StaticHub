<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use Symfony\Component\Yaml\Yaml;

/**
 * Loads the `empirical:` block (top-kill mechanic baselines) from a
 * boss-timeline YAML. Same file + path scheme as BossTimelineLoader:
 *   resources/boss-timelines/{season}/{difficulty}/{slug}.yml
 *
 * Returns the raw empirical block, or null when the file or block is absent
 * (callers then run informational-only — they flag nobody).
 */
class MechanicBaselineLoader
{
    /** @var array<string, array|null> */
    private array $cache = [];

    public function load(?string $season, ?string $slug, ?string $difficulty): ?array
    {
        if (!$season || !$slug || !$difficulty) {
            return null;
        }

        $key = "{$season}/{$difficulty}/{$slug}";
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $path = resource_path("boss-timelines/{$season}/{$difficulty}/{$slug}.yml");
        if (!is_file($path)) {
            return $this->cache[$key] = null;
        }

        try {
            $raw = Yaml::parseFile($path);
            $empirical = is_array($raw) ? ($raw['empirical'] ?? null) : null;
            return $this->cache[$key] = (is_array($empirical) && !empty($empirical)) ? $empirical : null;
        } catch (\Throwable) {
            return $this->cache[$key] = null;
        }
    }
}
