<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Server-side proxy for Icy Veins static assets (sprite webps, spec background
 * images). Icy Veins blocks cross-origin Referer for hotlinking, so the browser
 * cannot fetch them directly from local.blastr.pro. We fetch from server-side
 * and cache on disk forever (filenames are content-hashed, so cache is safe).
 */
class IcyVeinsAssetProxyController extends Controller
{
    private const UPSTREAM   = 'https://static.icy-veins.com';
    private const CACHE_ROOT = 'icy-veins-cache';

    public function show(Request $request, string $path): BinaryFileResponse
    {
        // Allow letters, digits, dashes, underscores, dots, and slashes only.
        // Reject any path traversal attempts.
        if (!preg_match('#^[a-zA-Z0-9_\-./]+$#', $path) || str_contains($path, '..')) {
            abort(400, 'Invalid path');
        }

        $cachePath = storage_path('app/' . self::CACHE_ROOT . '/' . $path);

        if (!is_file($cachePath)) {
            $upstream = self::UPSTREAM . '/' . ltrim($path, '/');

            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'BlastR-AssetProxy/1.0'])
                ->get($upstream);

            abort_if($response->failed(), 404, 'Upstream asset not found');

            $dir = dirname($cachePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($cachePath, $response->body());
        }

        return response()->file($cachePath, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
