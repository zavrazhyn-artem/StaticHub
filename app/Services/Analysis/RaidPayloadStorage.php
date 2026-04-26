<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use Illuminate\Support\Facades\Storage;

class RaidPayloadStorage
{
    private const DIR = 'raid_payloads';
    private const DISK = 'local';

    public function store(int $reportId, string $content): void
    {
        $compressed = gzencode($content, 6);
        if ($compressed === false) {
            throw new \RuntimeException("Failed to gzip payload for report {$reportId}");
        }

        Storage::disk(self::DISK)->put($this->path($reportId), $compressed);
    }

    public function read(int $reportId): ?string
    {
        $path = $this->path($reportId);
        if (!Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        $compressed = Storage::disk(self::DISK)->get($path);
        if ($compressed === null) {
            return null;
        }

        $decoded = gzdecode($compressed);
        return $decoded === false ? null : $decoded;
    }

    public function exists(int $reportId): bool
    {
        return Storage::disk(self::DISK)->exists($this->path($reportId));
    }

    public function delete(int $reportId): bool
    {
        $path = $this->path($reportId);
        if (!Storage::disk(self::DISK)->exists($path)) {
            return false;
        }
        return Storage::disk(self::DISK)->delete($path);
    }

    public function path(int $reportId): string
    {
        return self::DIR . '/' . $reportId . '.json.gz';
    }
}
