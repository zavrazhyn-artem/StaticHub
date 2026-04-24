<?php

declare(strict_types=1);

namespace App\Jobs\Admin;

use App\Services\Admin\UserActivityLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessUserActivityLogsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    private const MAX_ITERATIONS = 20;
    private const BATCH_SIZE = 500;

    public function handle(UserActivityLogService $service): void
    {
        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $inserted = $service->flushToDatabase(self::BATCH_SIZE);
            if ($inserted < self::BATCH_SIZE) {
                break;
            }
        }
    }
}
