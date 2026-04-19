<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use Symfony\Component\Yaml\Yaml;

/**
 * Loads shared reference tables for combat analysis — tank mitigation buffs per spec,
 * external defensive cooldowns (who can cast what on whom).
 */
class CombatReferenceLoader
{
    private ?array $tankMitigation = null;
    private ?array $externalCds = null;
    private ?array $raidBurst = null;

    /**
     * @return array<int, array{id:int,name:string}> Mitigation buffs for the given class-spec slug
     */
    public function tankMitigationFor(?string $classSlug, ?string $specSlug): array
    {
        if (!$classSlug || !$specSlug) return [];
        $this->tankMitigation ??= $this->loadYaml('tank-mitigation.yaml');

        $key = $this->normalize($classSlug) . '-' . $this->normalize($specSlug);
        return $this->tankMitigation[$key] ?? [];
    }

    /**
     * @return array<int, array{id:int,name:string,class:string,scope:string}>
     */
    public function externalCooldowns(): array
    {
        return $this->externalCds ??= $this->loadYaml('external-cooldowns.yaml');
    }

    /**
     * @return array<int, array{id:int,name:string,class:string}>
     */
    public function raidBurstCooldowns(): array
    {
        return $this->raidBurst ??= $this->loadYaml('raid-burst.yaml');
    }

    private function loadYaml(string $file): array
    {
        $path = resource_path("combat-references/{$file}");
        if (!file_exists($path)) return [];
        try {
            $parsed = Yaml::parseFile($path);
            return is_array($parsed) ? $parsed : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function normalize(string $s): string
    {
        return mb_strtolower(preg_replace('/[^A-Za-z0-9]+/', '', $s) ?? '');
    }
}
