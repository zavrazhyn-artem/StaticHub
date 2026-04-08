<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiRequestLog;
use App\Models\ApiUsageLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('logs:purge')]
#[Description('Purge API and AI logs older than configured retention period.')]
class PurgeOldLogsCommand extends Command
{
    public function handle(): int
    {
        $days = (int) config('api_tracking.retention_days', 7);
        $cutoff = now()->subDays($days);

        $aiDeleted = AiRequestLog::where('created_at', '<', $cutoff)->delete();
        $apiDeleted = ApiUsageLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Purged {$aiDeleted} AI logs and {$apiDeleted} API logs older than {$days} days.");

        return self::SUCCESS;
    }
}
