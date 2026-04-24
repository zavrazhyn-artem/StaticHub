<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Admin\ProcessUserActivityLogsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user-activity:drain')]
#[Description('Dispatch a job that drains buffered user activity logs from Redis into the database.')]
class ProcessUserActivityLogsCommand extends Command
{
    public function handle(): int
    {
        ProcessUserActivityLogsJob::dispatch();
        $this->info('Dispatched user activity drain job.');
        return self::SUCCESS;
    }
}
