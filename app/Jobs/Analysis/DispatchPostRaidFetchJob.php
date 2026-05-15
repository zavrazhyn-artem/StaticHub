<?php

declare(strict_types=1);

namespace App\Jobs\Analysis;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchPostRaidFetchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $eventId,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        FetchPostRaidLogsJob::dispatch($this->eventId);
    }
}
