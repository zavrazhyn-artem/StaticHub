<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class RaidPayloadStorage
{
    public const DIR = 'raid_payloads';

    public function store(int $reportId, string $content): void
    {
        $compressed = gzencode($content, 6);
        if ($compressed === false) {
            throw new \RuntimeException("Failed to gzip payload for report {$reportId}");
        }

        $this->disk()->put($this->path($reportId), $compressed);
    }

    public function read(int $reportId): ?string
    {
        $path = $this->path($reportId);
        $disk = $this->disk();
        if (!$disk->exists($path)) {
            return null;
        }

        $compressed = $disk->get($path);
        if ($compressed === null) {
            return null;
        }

        $decoded = gzdecode($compressed);
        return $decoded === false ? null : $decoded;
    }

    public function exists(int $reportId): bool
    {
        return $this->disk()->exists($this->path($reportId));
    }

    public function delete(int $reportId): bool
    {
        $path = $this->path($reportId);
        $disk = $this->disk();
        if (!$disk->exists($path)) {
            return false;
        }
        return $disk->delete($path);
    }

    public function path(int $reportId): string
    {
        return self::DIR . '/' . $reportId . '.json.gz';
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function diskName(): string
    {
        return config('services.raid_payloads.disk', 's3');
    }
}
