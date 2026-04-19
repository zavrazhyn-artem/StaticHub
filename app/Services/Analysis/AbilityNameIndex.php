<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Yaml\Yaml;

/**
 * Aggregates ability name → spell ID mappings from spec baselines, tactics, and combat
 * reference YAMLs. Used by the frontend to auto-link ability mentions with WoWHead tooltips.
 *
 * Cached at application level since the underlying YAMLs change infrequently.
 */
class AbilityNameIndex
{
    private const CACHE_KEY = 'ability_name_index:v1';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * @return array<string, int>  Lowercase ability name → spell ID
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn() => $this->build());
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function build(): array
    {
        $map = [];

        foreach (glob(resource_path('spec-baselines/*.yaml')) as $path) {
            $this->ingestYaml($path, 'rotation_checks', 'ability', 'ability_id', $map);
        }

        foreach (glob(resource_path('combat-references/*.yaml')) as $path) {
            $data = $this->parse($path);
            if (is_array($data)) {
                foreach ($data as $entry) {
                    $this->absorb($entry['name'] ?? null, $entry['id'] ?? null, $map);
                    if (is_array($entry)) {
                        foreach ($entry as $inner) {
                            if (is_array($inner)) {
                                $this->absorb($inner['name'] ?? null, $inner['id'] ?? null, $map);
                            }
                        }
                    }
                }
                foreach ($data as $entryGroup) {
                    if (is_array($entryGroup) && !isset($entryGroup['id'])) {
                        foreach ($entryGroup as $e) {
                            if (is_array($e)) $this->absorb($e['name'] ?? null, $e['id'] ?? null, $map);
                        }
                    }
                }
            }
        }

        foreach (glob(resource_path('tactics/*.md')) as $path) {
            $this->ingestTacticsFile($path, $map);
        }

        ksort($map);
        return $map;
    }

    private function ingestYaml(string $path, string $listKey, string $nameKey, string $idKey, array &$map): void
    {
        $data = $this->parse($path);
        if (!is_array($data) || !isset($data[$listKey]) || !is_array($data[$listKey])) return;
        foreach ($data[$listKey] as $entry) {
            $this->absorb($entry[$nameKey] ?? null, $entry[$idKey] ?? null, $map);
        }
    }

    private function ingestTacticsFile(string $path, array &$map): void
    {
        $raw = @file_get_contents($path);
        if (!$raw) return;
        if (!preg_match('/^---\s*\n(.*?)\n---/s', $raw, $m)) return;
        $yaml = $this->parseString($m[1]);
        if (!is_array($yaml)) return;

        foreach ($yaml['mechanics'] ?? [] as $mechanic) {
            $name = $mechanic['name'] ?? null;
            foreach ((array) ($mechanic['ability_ids'] ?? []) as $aid) {
                $this->absorb($name, $aid, $map);
            }
        }
    }

    private function parse(string $path): mixed
    {
        try { return Yaml::parseFile($path); } catch (\Throwable) { return null; }
    }

    private function parseString(string $yaml): mixed
    {
        try { return Yaml::parse($yaml); } catch (\Throwable) { return null; }
    }

    private function absorb(?string $name, mixed $id, array &$map): void
    {
        if (!$name || !is_numeric($id) || (int) $id <= 0) return;
        $key = mb_strtolower(trim($name));
        // Prefer first-seen ID to avoid polluting with lookalikes.
        if (!isset($map[$key])) $map[$key] = (int) $id;
    }
}
