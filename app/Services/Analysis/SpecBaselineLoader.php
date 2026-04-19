<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use Symfony\Component\Yaml\Yaml;

/**
 * Loads rotation baseline YAML for a class+spec combination.
 * Files live under resources/spec-baselines/{class-slug}-{spec-slug}.yaml.
 *
 * Example shape:
 *   spec: Havoc
 *   class: DemonHunter
 *   role: dps
 *   rotation_checks:
 *     - ability: Eye Beam
 *       ability_id: 198013
 *       check: cpm
 *       target_cpm: 1.3
 *       tolerance: 0.3
 *       severity: major
 *       issue: "Eye Beam used at {actual}/min (target {target}/min)."
 */
class SpecBaselineLoader
{
    private array $cache = [];

    public function load(?string $class, ?string $spec): ?array
    {
        if (!$class || !$spec) return null;

        $slug = $this->slug($class) . '-' . $this->slug($spec);

        if (array_key_exists($slug, $this->cache)) {
            return $this->cache[$slug];
        }

        $path = resource_path("spec-baselines/{$slug}.yaml");
        if (!file_exists($path)) {
            return $this->cache[$slug] = null;
        }

        try {
            $parsed = Yaml::parseFile($path);
            return $this->cache[$slug] = is_array($parsed) ? $parsed : null;
        } catch (\Throwable $e) {
            return $this->cache[$slug] = null;
        }
    }

    private function slug(string $name): string
    {
        return mb_strtolower(preg_replace('/[^A-Za-z0-9]+/', '', $name) ?? '');
    }
}
