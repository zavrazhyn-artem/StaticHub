<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use App\Models\UserActivityLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class UserActivityDashboardService
{
    private const SORTABLE = ['created_at', 'route_name', 'url', 'method'];

    public function buildDashboardPayload(): array
    {
        $since14 = now()->subDays(14);

        return [
            'top_statics' => UserActivityLog::query()->topStatics(14, 10),
            'top_users' => UserActivityLog::query()->topUsers(14, 10),
            'recent_feed' => UserActivityLog::query()->recentFeed(50),
            'hourly' => $this->paddedHourly(7),
            'daily' => $this->paddedDaily(14),
            'sleeping' => UserActivityLog::query()->sleepingStatics(7),
            'totals' => [
                'views_14d' => UserActivityLog::query()->recent(14)->count(),
                'unique_users_14d' => UserActivityLog::query()->recent(14)->distinct()->count('user_id'),
                'active_statics_14d' => UserActivityLog::query()->recent(14)->distinct()->count('static_id'),
            ],
        ];
    }

    public function buildUserDrilldownPayload(User $user, Request $request): array
    {
        $search = trim((string) $request->query('q', ''));
        $sort = (string) $request->query('sort', 'created_at');
        $direction = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'created_at';
        }

        $logs = UserActivityLog::query()
            ->forUserPaginated($user->id, $search, $sort, $direction, 25)
            ->withQueryString();

        $summary = UserActivityLog::query()
            ->forUser($user->id)
            ->recent(14)
            ->selectRaw('COUNT(*) as total, MIN(created_at) as first_seen, MAX(created_at) as last_seen')
            ->first();

        return [
            'logs' => $logs,
            'filters' => [
                'q' => $search,
                'sort' => $sort,
                'dir' => $direction,
            ],
            'summary' => [
                'total_views_14d' => (int) ($summary->total ?? 0),
                'first_seen' => $summary->first_seen ? Carbon::parse($summary->first_seen) : null,
                'last_seen' => $summary->last_seen ? Carbon::parse($summary->last_seen) : null,
            ],
        ];
    }

    private function paddedHourly(int $days): array
    {
        $rows = UserActivityLog::query()->hourlyActivity($days)->keyBy('hour');
        $out = [];
        for ($h = 0; $h < 24; $h++) {
            $out[] = [
                'hour' => $h,
                'views' => (int) ($rows->get($h)->views ?? 0),
            ];
        }
        return $out;
    }

    private function paddedDaily(int $days): array
    {
        $rows = UserActivityLog::query()->dailyActivity($days)->keyBy('day');
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $row = $rows->get($day);
            $out[] = [
                'day' => $day,
                'views' => (int) ($row->views ?? 0),
                'active_users' => (int) ($row->active_users ?? 0),
            ];
        }
        return $out;
    }
}
