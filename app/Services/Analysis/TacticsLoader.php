<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use Symfony\Component\Yaml\Yaml;

/**
 * Loads and parses YAML frontmatter from boss tactics markdown files.
 * Tactics files live in resources/tactics/{slug}.md and start with a --- YAML block.
 */
class TacticsLoader
{
    /** @var array<int,array> cache of loaded tactics keyed by wcl_encounter_id */
    private array $cacheByEncounterId = [];
    /** @var array<string,array> cache keyed by normalized boss name */
    private array $cacheByBossName = [];
    private bool $loaded = false;

    /**
     * Load tactics for a boss by its WCL encounter ID.
     * Throws if no tactic file exists.
     *
     * @return array{boss: string, wcl_encounter_id: int|null, difficulty_variants: array, mechanics: array, avoidable_abilities: array, role_mechanics: array, markdown: string}
     */
    public function loadByEncounterId(int $encounterId): array
    {
        $this->ensureLoaded();

        if (!isset($this->cacheByEncounterId[$encounterId])) {
            throw new \RuntimeException(
                "No tactics file found for WCL encounter ID {$encounterId}. "
                . "Add a YAML frontmatter with `wcl_encounter_id: {$encounterId}` to one of resources/tactics/*.md files."
            );
        }

        return $this->cacheByEncounterId[$encounterId];
    }

    /**
     * Load tactics by boss display name (fallback when encounter ID unknown).
     * Matches ignoring case, commas, apostrophes.
     */
    public function loadByBossName(string $bossName): array
    {
        $this->ensureLoaded();

        $normalized = $this->normalizeBossName($bossName);
        if (!isset($this->cacheByBossName[$normalized])) {
            throw new \RuntimeException(
                "No tactics file found for boss '{$bossName}'. "
                . "Normalized form: '{$normalized}'. "
                . "Check that a tactics file exists with matching `boss:` field."
            );
        }

        return $this->cacheByBossName[$normalized];
    }

    /**
     * Return list of all loaded tactics.
     */
    public function all(): array
    {
        $this->ensureLoaded();
        return array_values($this->cacheByBossName);
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) return;

        $tacticsDir = resource_path('tactics');
        foreach (glob("{$tacticsDir}/*.md") as $path) {
            $parsed = $this->parseFile($path);
            if (!$parsed) continue;

            if (isset($parsed['wcl_encounter_id']) && is_int($parsed['wcl_encounter_id'])) {
                $this->cacheByEncounterId[$parsed['wcl_encounter_id']] = $parsed;
            }

            if (!empty($parsed['boss'])) {
                $key = $this->normalizeBossName($parsed['boss']);
                $this->cacheByBossName[$key] = $parsed;
            }
        }

        $this->loaded = true;
    }

    private function parseFile(string $path): ?array
    {
        $content = @file_get_contents($path);
        if (!$content) return null;

        // Extract YAML frontmatter between --- delimiters
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            return null;
        }

        $yaml = $matches[1];
        $markdown = $matches[2];

        try {
            $data = Yaml::parse($yaml) ?? [];
        } catch (\Exception $e) {
            throw new \RuntimeException("Invalid YAML in {$path}: " . $e->getMessage());
        }

        // Normalize the structure
        $data['mechanics'] = $this->normalizeMechanics($data['mechanics'] ?? []);
        $data['avoidable_abilities'] = $data['avoidable_abilities'] ?? [];
        $data['role_mechanics'] = $data['role_mechanics'] ?? [];
        $data['difficulty_variants'] = $data['difficulty_variants'] ?? ['normal', 'heroic', 'mythic'];
        $data['markdown'] = $markdown;
        $data['source_file'] = basename($path);

        // Normalize severity values
        foreach ($data['mechanics'] as &$m) {
            if (isset($m['severity'])) {
                $m['severity'] = $this->normalizeSeverity($m['severity']);
            }
        }
        unset($m);

        return $data;
    }

    /**
     * Flatten mechanics structure into a flat list.
     * Supports 3 input shapes:
     *   1. Flat list — returned as-is
     *   2. {phase_key: [list of mechanics]} — flattened, `phase` key added
     *   3. {slug: {name, type, ...}} — values flattened (keys are slugs, not phases)
     */
    private function normalizeMechanics($mechanics): array
    {
        if (empty($mechanics)) return [];

        if (array_is_list($mechanics)) {
            return $mechanics;
        }

        // Detect shape 3: dict where values are mechanic objects (have `name` field)
        $firstValue = reset($mechanics);
        if (is_array($firstValue) && isset($firstValue['name'])) {
            $flat = [];
            foreach ($mechanics as $slug => $m) {
                if (!is_array($m)) continue;
                if (!isset($m['slug']) && is_string($slug)) {
                    $m['slug'] = $slug;
                }
                $flat[] = $m;
            }
            return $flat;
        }

        // Shape 2: dict of phase => list
        $flat = [];
        foreach ($mechanics as $phaseKey => $phaseList) {
            if (!is_array($phaseList)) continue;
            foreach ($phaseList as $m) {
                if (!is_array($m)) continue;
                $m['phase'] = $phaseKey;
                $flat[] = $m;
            }
        }
        return $flat;
    }

    private function normalizeSeverity(string $s): string
    {
        $s = strtolower($s);
        return match ($s) {
            'high', 'critical' => 'critical',
            'medium', 'major', 'moderate' => 'major',
            'low', 'minor' => 'minor',
            default => $s,
        };
    }

    private function normalizeBossName(string $name): string
    {
        $name = mb_strtolower($name);
        $name = str_replace([',', "'", '’'], '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }
}
