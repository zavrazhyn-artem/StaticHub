<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TacticalReport;
use App\Services\Analysis\RaidPayloadStorage;
use Illuminate\Console\Command;

class CleanupRaidPayloadsCommand extends Command
{
    protected $signature = 'ai:cleanup-payloads
        {--hours=24 : Delete payloads older than N hours}
        {--dry-run : Report what would be deleted without touching files}';

    protected $description = 'Delete raid payload files older than the cutoff (default 24h) so chat activation expires.';

    public function handle(RaidPayloadStorage $storage): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($hours)->getTimestamp();

        $disk = $storage->disk();
        $files = $disk->files(RaidPayloadStorage::DIR);

        $deleted = 0;
        $kept = 0;
        $bytesFreed = 0;

        foreach ($files as $path) {
            $mtime = $disk->lastModified($path);
            if ($mtime > $cutoff) {
                $kept++;
                continue;
            }

            $size = $disk->size($path);
            $this->line(($dryRun ? '[dry-run] ' : '') . "delete {$path} (" . round($size / 1024) . ' KB)');

            if (!$dryRun) {
                $disk->delete($path);
            }

            $deleted++;
            $bytesFreed += $size;

            // Lock the chat — file is gone, can't recreate cache.
            // Match by report id parsed from filename.
            if (preg_match('#' . preg_quote(RaidPayloadStorage::DIR, '#') . '/(\d+)\.json\.gz$#', $path, $m)) {
                if (!$dryRun) {
                    TacticalReport::where('id', (int) $m[1])
                        ->whereNull('chat_activated_at')
                        ->update(['chat_activated_at' => now()]); // mark as no longer activatable
                }
            }
        }

        $this->info(sprintf(
            '%s%d deleted, %d kept, %.1f MB freed',
            $dryRun ? '[dry-run] ' : '',
            $deleted, $kept, $bytesFreed / 1_048_576
        ));

        return self::SUCCESS;
    }
}
