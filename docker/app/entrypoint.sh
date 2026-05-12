#!/bin/sh
# =============================================================================
# Unified entrypoint — dispatches to the right command based on $1.
#
#   web              → FrankenPHP server (Octane worker mode). Default.
#   scheduler        → php artisan schedule:work (one tick per minute).
#   worker-default   → supervisord: default + compile + discord (pool=core).
#   worker-sync      → supervisord: bnet + rio + wcl (pool=sync).
#   worker-ai        → supervisord: ai (pool=ai, KEDA-scaled).
#   worker           → legacy alias for worker-default (local-dev compatibility).
#   migrate          → php artisan migrate --force (one-shot, used by k8s Job).
#   shell            → drop into /bin/bash for debugging.
#   custom cmd       → exec arbitrary command (php artisan ..., /bin/sh ..., etc.)
# =============================================================================
set -e

# Always clear stale Laravel package/service caches that may have been baked
# in on the host (e.g. dev-only providers like laravel/pail). Without this,
# bootstrap/cache/packages.php from a local `composer install` carries dev
# packages that aren't in vendor/ inside the prod image, causing fatals.
prepare_runtime() {
    rm -f bootstrap/cache/packages.php bootstrap/cache/services.php

    # Rebuild config cache from THIS pod's env (k8s Secret values) for prod-
    # like environments. Dev/local skips so .env edits land without restart.
    # route:cache + view:cache are already baked into the image at build time.
    case "${APP_ENV:-production}" in
        local|development|testing)
            php artisan config:clear >/dev/null 2>&1 || true
            php artisan route:clear >/dev/null 2>&1 || true
            ;;
        *)
            php artisan config:cache >/dev/null
            # Route:cache must run AFTER config:cache so Route::domain() resolves
            # APP_URL and ADMIN_SUBDOMAIN from the actual pod env (k8s Secret),
            # not the build-time defaults (admin.localhost).
            php artisan route:cache >/dev/null
            ;;
    esac
}

case "${1:-web}" in
    web)
        prepare_runtime
        # Octane in FrankenPHP worker mode. The Caddyfile defines the worker
        # via `frankenphp.worker.file = .../frankenphp-worker.php`. The actual
        # serve command is just `frankenphp run` which reads /etc/frankenphp/Caddyfile.
        exec frankenphp run --config /etc/frankenphp/Caddyfile
        ;;
    worker|worker-default)
        prepare_runtime
        # pool=core: default + compile + discord queues. Always-on, latency-
        # sensitive light jobs. `worker` alias for legacy local-dev compose.
        exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/default.conf
        ;;
    worker-sync)
        prepare_runtime
        # pool=sync: bnet (4 procs) + rio (2 procs) + wcl (1 proc). External
        # API hammering with ShouldBeUnique + RateLimited middleware.
        exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/sync.conf
        ;;
    worker-ai)
        prepare_runtime
        # pool=ai: Gemini analysis. KEDA-scaled, 1h grace period, PDB pinned.
        exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/ai.conf
        ;;
    scheduler)
        prepare_runtime
        # Long-running tick that fires Laravel scheduled tasks at the right
        # minute. In k8s we may replace this with native CronJobs per task,
        # but `schedule:work` keeps local/prod parity for now.
        exec php artisan schedule:work
        ;;
    migrate)
        exec php artisan migrate --force
        ;;
    shell)
        exec /bin/bash
        ;;
    *)
        # Pass-through: `docker run app php artisan tinker` etc.
        exec "$@"
        ;;
esac
