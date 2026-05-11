<?php

declare(strict_types=1);

/**
 * OpCache preload — runs once at PHP startup (before any worker boots) and
 * compiles hot files into shared memory. Octane workers then skip the parse
 * step on first request, cutting cold-start latency.
 *
 * We only `opcache_compile_file` (parse + cache); we never `require` here, so
 * classes aren't declared at preload time. Composer's autoloader still loads
 * them on demand, and OPcache returns the pre-parsed bytecode — no risk of
 * "Cannot redeclare class" the way a require-based preloader would have.
 *
 * Configured via `opcache.preload` in docker/app/php.ini.
 */

// "Can't preload unlinked class" warnings emit from the PHP preload subsystem
// itself (C-level, not via raise_error) when a child class is compiled before
// its parent/trait — Spatie LaravelData, Sanctum etc. The bytecode is still
// cached and the autoloader handles the class normally at runtime. Suppress
// these to keep worker-boot logs clean.
error_reporting(0);
ini_set('display_errors', '0');

$basePath = realpath(__DIR__ . '/..');
if ($basePath === false) {
    return;
}

$paths = [
    // Laravel framework — vast majority of request-handling code.
    '/vendor/laravel/framework/src/Illuminate',
    // Octane — worker mode glue.
    '/vendor/laravel/octane/src',
    // Symfony components Laravel sits on top of.
    '/vendor/symfony/http-foundation',
    '/vendor/symfony/http-kernel',
    '/vendor/symfony/routing',
    '/vendor/symfony/console',
    // Our app code — Controllers, Services, Builders, Models.
    '/app',
    // Compiled route + config + view caches generated at build / boot.
    '/bootstrap/cache/routes-v7.php',
    '/bootstrap/cache/config.php',
];

foreach ($paths as $rel) {
    $abs = $basePath . $rel;

    if (is_file($abs)) {
        @opcache_compile_file($abs);
        continue;
    }

    if (! is_dir($abs)) {
        continue;
    }

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iter as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            @opcache_compile_file($file->getPathname());
        }
    }
}
