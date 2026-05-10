#!/bin/sh
# =============================================================================
# Unified entrypoint — dispatches to the right command based on $1.
#
#   web        → FrankenPHP server (Octane worker mode). Default.
#   worker     → supervisord managing per-queue queue:work processes.
#   scheduler  → php artisan schedule:work (one tick per minute).
#   migrate    → php artisan migrate --force (one-shot, used by k8s Job).
#   shell      → drop into /bin/bash for debugging.
#   custom cmd → exec arbitrary command (php artisan ..., /bin/sh ..., etc.)
# =============================================================================
set -e

case "${1:-web}" in
    web)
        # Octane in FrankenPHP worker mode. The Caddyfile defines the worker
        # via `frankenphp.worker.file = .../frankenphp-worker.php`. The actual
        # serve command is just `frankenphp run` which reads /etc/frankenphp/Caddyfile.
        exec frankenphp run --config /etc/frankenphp/Caddyfile
        ;;
    worker)
        # Same supervisord config as the old php-fpm worker container. Each
        # [program:queue-*] manages one or more `queue:work` processes with
        # ShouldBeUnique + RateLimited middleware enforcing per-API quotas.
        exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/worker.conf
        ;;
    scheduler)
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
