<?php

declare(strict_types=1);

namespace App\Services\Desktop;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Source-of-truth for the public BlastR Desktop installer (NSIS .exe).
 *
 * Mirror of {@see BridgeReleaseService} — file + version live in
 * resources/desktop/, but unlike the bridge endpoint this one is
 * served PUBLIC (no auth) since it's the first-install bootstrap.
 *
 * Releasing: rebuild via `wails build -nsis`, copy the
 * installer.exe into resources/desktop/installer.exe, write the
 * version to installer-version.txt, commit, deploy. The download
 * page (`/desktop`) reads everything live, no env edits needed.
 */
final class InstallerReleaseService
{
    private const CACHE_TTL = 3600;

    public function path(): string
    {
        return resource_path('desktop/installer.exe');
    }

    public function versionPath(): string
    {
        return resource_path('desktop/installer-version.txt');
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
        $key = 'desktop:installer:sha256:' . $mtime;
        return Cache::remember($key, self::CACHE_TTL, fn () => hash_file('sha256', $this->path()));
    }

    public function size(): ?int
    {
        $size = @filesize($this->path());
        return $size === false ? null : $size;
    }

    /**
     * Public stream — anonymous downloads OK, this is how new users
     * get the bridge in the first place. Filename is fixed so the
     * download lands as `BlastR-Desktop-Setup.exe` regardless of the
     * URL the browser hit.
     */
    public function streamResponse(): BinaryFileResponse
    {
        return response()
            ->download($this->path(), 'BlastR-Desktop-Setup.exe', [
                'Content-Type'        => 'application/vnd.microsoft.portable-executable',
                'X-Installer-Version' => (string) $this->version(),
            ]);
    }
}
