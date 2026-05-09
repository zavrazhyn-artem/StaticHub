<?php

declare(strict_types=1);

namespace App\Services\Desktop;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Source-of-truth for the BlastR WoW addon release the bridge serves.
 *
 * The zip + version file live in `resources/desktop/` (committed to
 * the Laravel repo) so a release is "build the zip locally, drop it
 * here, commit, deploy". No Git LFS, no separate storage — the same
 * deploy that ships backend code also publishes the addon.
 *
 * Sha256 is cached by file mtime so the manifest endpoint is cheap on
 * every heartbeat without going stale across releases. Cache key
 * carries the mtime; replacing the zip silently invalidates.
 */
final class AddonReleaseService
{
    private const CACHE_TTL = 3600;

    public function path(): string
    {
        return resource_path('desktop/addon.zip');
    }

    public function versionPath(): string
    {
        return resource_path('desktop/addon-version.txt');
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
        $key = 'desktop:addon:sha256:' . $mtime;
        return Cache::remember($key, self::CACHE_TTL, fn () => hash_file('sha256', $this->path()));
    }

    public function size(): ?int
    {
        $size = @filesize($this->path());
        return $size === false ? null : $size;
    }

    /**
     * Streamed download with the version pinned in a header so the
     * bridge can sanity-check it matched what the manifest promised.
     */
    public function streamResponse(): BinaryFileResponse
    {
        return response()
            ->download($this->path(), 'blastr-addon.zip', [
                'Content-Type'    => 'application/zip',
                'X-Addon-Version' => (string) $this->version(),
            ]);
    }
}
