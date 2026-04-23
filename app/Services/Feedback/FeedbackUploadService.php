<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FeedbackUploadService
{
    public const DISK = 'public';
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_PER_ITEM = 5;
    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    public const ALLOWED_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Store an uploaded image under storage/app/public/feedback/YYYY/MM/.
     * Returns the relative path (to be saved in DB). Caller is responsible
     * for validating mime/size before calling — throws on malformed input.
     */
    public function store(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        if (! in_array($ext, self::ALLOWED_EXTS, true)) {
            $ext = 'png';
        }

        $filename = Str::uuid()->toString() . '.' . $ext;
        $dir = 'feedback/' . now()->format('Y/m');

        $path = $file->storeAs($dir, $filename, self::DISK);

        return $path;
    }

    /**
     * Delete a list of stored paths from disk. Missing files are skipped silently.
     *
     * @param array<int, string>|null $paths
     */
    public function deleteMany(?array $paths): void
    {
        if ($paths === null || count($paths) === 0) {
            return;
        }

        $disk = Storage::disk(self::DISK);
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    /**
     * Resolve a stored path to a public URL for frontend rendering.
     */
    public function url(string $path): string
    {
        return Storage::disk(self::DISK)->url($path);
    }

    /**
     * Map an array of stored paths to [{path, url}, ...] for frontend payloads.
     *
     * @param array<int, string>|null $paths
     * @return array<int, array{path: string, url: string}>
     */
    public function presentMany(?array $paths): array
    {
        if ($paths === null) {
            return [];
        }

        $out = [];
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                $out[] = ['path' => $path, 'url' => $this->url($path)];
            }
        }

        return $out;
    }

    /**
     * Sanitize an array of image paths received from a form submission:
     * drops non-strings, caps to MAX_PER_ITEM, and ensures each path points
     * at an actually-stored file under the feedback/ prefix.
     *
     * @param mixed $paths
     * @return array<int, string>
     */
    public function sanitizeInputPaths($paths): array
    {
        if (! is_array($paths)) {
            return [];
        }

        $disk = Storage::disk(self::DISK);
        $out = [];

        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }
            if (! str_starts_with($path, 'feedback/')) {
                continue;
            }
            if (! $disk->exists($path)) {
                continue;
            }
            $out[] = $path;
            if (count($out) >= self::MAX_PER_ITEM) {
                break;
            }
        }

        return $out;
    }
}
