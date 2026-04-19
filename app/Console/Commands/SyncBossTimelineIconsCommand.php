<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Yaml\Yaml;

class SyncBossTimelineIconsCommand extends Command
{
    protected $signature = 'cooldowns:sync-icons
        {--season= : Only sync YAMLs under this season (defaults to all)}';

    protected $description = 'Walk every boss-timeline YAML and download any missing ability icons into public/images/cooldowns/{spell_id}.jpg.';

    public function handle(): int
    {
        $root = resource_path('boss-timelines');
        if (!File::isDirectory($root)) {
            $this->warn("No boss-timelines directory at {$root}, nothing to sync.");
            return self::SUCCESS;
        }

        $iconDir = public_path('images/cooldowns');
        if (!File::isDirectory($iconDir)) {
            File::makeDirectory($iconDir, 0755, true);
        }

        $onlySeason = (string) ($this->option('season') ?? '');
        $seasons = $onlySeason !== ''
            ? [$root . '/' . $onlySeason]
            : File::directories($root);

        $downloaded = 0;
        $skipped = 0;
        $missing = 0;
        $errors = 0;

        foreach ($seasons as $seasonDir) {
            if (!File::isDirectory($seasonDir)) continue;
            foreach (File::directories($seasonDir) as $diffDir) {
                foreach (File::files($diffDir) as $file) {
                    if (!in_array($file->getExtension(), ['yml', 'yaml'], true)) continue;
                    $data = Yaml::parseFile($file->getPathname());
                    $abilities = array_merge(
                        $data['abilities'] ?? [],
                        $data['conditional_abilities'] ?? []
                    );
                    foreach ($abilities as $ab) {
                        $spellId = $ab['spell_id'] ?? null;
                        if (!$spellId) {
                            $missing++;
                            continue;
                        }
                        $localPath = $iconDir . '/' . $spellId . '.jpg';
                        if (File::exists($localPath)) {
                            $skipped++;
                            continue;
                        }
                        // Resolve icon name — prefer the YAML's icon hint, otherwise ask
                        // Wowhead's tooltip API which returns the raw icon filename.
                        $iconName = $ab['icon'] ?? null;
                        if (!$iconName) $iconName = $this->resolveIconFromWowhead((int) $spellId);
                        if (!$iconName) {
                            $errors++;
                            $this->warn("  ✗ {$spellId} — could not resolve icon name");
                            continue;
                        }
                        $url = "https://wow.zamimg.com/images/wow/icons/large/{$iconName}.jpg";
                        try {
                            $response = Http::timeout(15)->get($url);
                            if ($response->successful() && $response->body() !== '') {
                                File::put($localPath, $response->body());
                                $downloaded++;
                                $this->line("  ✓ {$spellId} ({$ab['name']})");
                            } else {
                                $errors++;
                                $this->warn("  ✗ {$spellId} — HTTP {$response->status()}");
                            }
                        } catch (\Throwable $e) {
                            $errors++;
                            $this->warn("  ✗ {$spellId} — {$e->getMessage()}");
                        }
                    }
                }
            }
        }

        $this->info("Done — downloaded {$downloaded}, skipped {$skipped} (already present), {$missing} abilities without spell_id+icon, {$errors} errors.");
        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Resolve an icon filename for a spell_id via Wowhead's tooltip API.
     * Returns null if the lookup fails — the caller falls back to skipping.
     */
    private function resolveIconFromWowhead(int $spellId): ?string
    {
        // Wowhead's tooltip JSON endpoint lives on `nether.wowhead.com`; the
        // www domain only serves HTML pages.
        $url = "https://nether.wowhead.com/tooltip/spell/{$spellId}";
        try {
            $response = Http::timeout(10)->get($url);
            if (!$response->successful()) return null;
            $data = $response->json();
            return $data['icon'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
