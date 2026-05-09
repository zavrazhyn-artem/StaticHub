<?php

declare(strict_types=1);

namespace App\Services\Desktop;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Source-of-truth for the BlastR Desktop bridge release the server
 * serves. Mirror of {@see AddonReleaseService} — exe + version file
 * live in `resources/desktop/`, sha256 cached by file mtime, served
 * via the auth-protected `/api/desktop/bridge` endpoint that the
 * bridge's self-updater hits.
 *
 * Releasing a new bridge: rebuild the .exe, drop it into
 * `resources/desktop/bridge.exe`, write the version to
 * `bridge-version.txt`, commit, deploy. No env edits.
 */
final class BridgeReleaseService
{
    private const CACHE_TTL = 3600;

    public function path(): string
    {
        return resource_path('desktop/bridge.exe');
    }

    public function versionPath(): string
    {
        return resource_path('desktop/bridge-version.txt');
    }

    public function exists(): bool
    {
        return is_file($this->path()) && is_file($this->versionPath());
    }

    public function version(): ?string
    {
        if (! is_file($this->versionPath())) {
            return null;
        }
        $raw = (string) file_get_contents($this->versionPath());
        $v = trim($raw);
        return $v === '' ? null : $v;
    }

    public function sha256(): ?string
    {
        if (! is_file($this->path())) {
            return null;
        }
        $mtime = filemtime($this->path()) ?: 0;
        $key = 'desktop:bridge:sha256:' . $mtime;
        return Cache::remember($key, self::CACHE_TTL, fn () => hash_file('sha256', $this->path()));
    }

    public function size(): ?int
    {
        $size = @filesize($this->path());
        return $size === false ? null : $size;
    }

    public function streamResponse(): BinaryFileResponse
    {
        return response()
            ->download($this->path(), 'blastr.exe', [
                'Content-Type'     => 'application/vnd.microsoft.portable-executable',
                'X-Bridge-Version' => (string) $this->version(),
            ]);
    }
}
