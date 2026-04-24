<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UserActivityLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user-activity:purge')]
#[Description('Delete user_activity_logs rows older than 14 days.')]
class PurgeUserActivityLogsCommand extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays(14);
        $deleted = UserActivityLog::query()->olderThan($cutoff)->delete();

        $this->info("Purged {$deleted} user activity rows older than 14 days.");

        return self::SUCCESS;
    }
}
