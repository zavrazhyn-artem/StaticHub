<?php

namespace App\Services\Backup;

use Spatie\Backup\BackupDestination\BackupDestination;
use Throwable;

class BackupHealthService
{
    private const FRESHNESS_THRESHOLD_HOURS = 27;

    public function check(): array
    {
        $appName = config('backup.backup.name');
        $disks = (array) config('backup.backup.destination.disks', []);
        $disks = array_values(array_filter($disks));

        $results = [];
        $allOk = true;

        foreach ($disks as $diskName) {
            $result = $this->checkDisk($diskName, $appName);
            $results[$diskName] = $result;
            if ($result['status'] !== 'ok') {
                $allOk = false;
            }
        }

        if ($disks === []) {
            $allOk = false;
        }

        return [
            'status' => $allOk ? 'ok' : 'fail',
            'threshold_hours' => self::FRESHNESS_THRESHOLD_HOURS,
            'disks' => $results,
        ];
    }

    private function checkDisk(string $diskName, string $appName): array
    {
        try {
            $destination = BackupDestination::create($diskName, $appName);
            $latest = $destination->newestBackup();
        } catch (Throwable $e) {
            return ['status' => 'fail', 'reason' => 'unreachable', 'error' => $e->getMessage()];
        }

        if ($latest === null) {
            return ['status' => 'fail', 'reason' => 'no_backups'];
        }

        $ageHours = (int) round(abs($latest->date()->diffInMinutes(now())) / 60);
        $sizeMb = round($latest->sizeInBytes() / 1024 / 1024, 1);

        if ($latest->sizeInBytes() <= 0) {
            return ['status' => 'fail', 'reason' => 'empty', 'age_hours' => $ageHours, 'size_mb' => $sizeMb];
        }

        if ($ageHours > self::FRESHNESS_THRESHOLD_HOURS) {
            return ['status' => 'fail', 'reason' => 'stale', 'age_hours' => $ageHours, 'size_mb' => $sizeMb];
        }

        return ['status' => 'ok', 'age_hours' => $ageHours, 'size_mb' => $sizeMb];
    }
}
