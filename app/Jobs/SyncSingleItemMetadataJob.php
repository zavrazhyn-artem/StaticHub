<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Blizzard\BlizzardGameDataApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncSingleItemMetadataJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $itemId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(BlizzardGameDataApiService $apiService): void
    {
        $apiService->syncItemMetadata($this->itemId);
    }
}
