<?php

declare(strict_types=1);

namespace App\Builders;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserActivityLogBuilder extends Builder
{
    public function forUser(int $userId): self
    {
        return $this->where('user_id', $userId);
    }

    public function forStatic(int $staticId): self
    {
        return $this->where('static_id', $staticId);
    }

    public function recent(int $days = 14): self
    {
        return $this->where('created_at', '>=', now()->subDays($days));
    }

    public function olderThan(Carbon $cutoff): self
    {
        return $this->where('created_at', '<', $cutoff);
    }

    public function search(string $term): self
    {
        $like = '%' . $term . '%';
        return $this->where(function ($q) use ($like) {
            $q->where('route_name', 'like', $like)
              ->orWhere('url', 'like', $like);
        });
    }

    public function topStatics(int $days = 14, int $limit = 20): Collection
    {
        return $this->getQuery()->newQuery()
            ->from('user_activity_logs as ual')
            ->leftJoin('statics', 'statics.id', '=', 'ual.static_id')
            ->where('ual.created_at', '>=', now()->subDays($days))
            ->groupBy('ual.static_id', 'statics.name')
            ->orderByDesc('views')
            ->limit($limit)
            ->get([
                'ual.static_id',
                'statics.name as static_name',
                DB::raw('COUNT(*) as views'),
                DB::raw('COUNT(DISTINCT ual.user_id) as active_users'),
                DB::raw('MAX(ual.created_at) as last_seen'),
            ]);
    }

    public function topUsers(int $days = 14, int $limit = 20): Collection
    {
        return $this->getQuery()->newQuery()
            ->from('user_activity_logs as ual')
            ->leftJoin('users', 'users.id', '=', 'ual.user_id')
            ->leftJoin('statics', 'statics.id', '=', 'ual.static_id')
            ->where('ual.created_at', '>=', now()->subDays($days))
            ->groupBy('ual.user_id', 'users.name', 'users.battletag', 'ual.static_id', 'statics.name')
            ->orderByDesc('views')
            ->limit($limit)
            ->get([
                'ual.user_id',
                'users.name as user_name',
                'users.battletag',
                'ual.static_id',
                'statics.name as static_name',
                DB::raw('COUNT(*) as views'),
                DB::raw('MAX(ual.created_at) as last_seen'),
            ]);
    }

    public function recentFeed(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return $this->with(['user:id,name,battletag', 'staticGroup:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function hourlyActivity(int $days = 7): Collection
    {
        return $this->getQuery()->newQuery()
            ->from('user_activity_logs')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderBy('hour')
            ->get([
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as views'),
                DB::raw('COUNT(DISTINCT user_id) as active_users'),
            ]);
    }

    public function topStaticsPaginated(
        int $days,
        string $search,
        string $sort,
        string $direction,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $columns = [
            'name' => 'static_name',
            'views' => 'views',
            'active_users' => 'active_users',
            'last_seen' => 'last_seen',
        ];
        $sortColumn = $columns[$sort] ?? 'views';
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $query = $this->getQuery()->newQuery()
            ->from('user_activity_logs as ual')
            ->leftJoin('statics', 'statics.id', '=', 'ual.static_id')
            ->where('ual.created_at', '>=', now()->subDays($days))
            ->groupBy('ual.static_id', 'statics.name')
            ->select([
                'ual.static_id',
                'statics.name as static_name',
                DB::raw('COUNT(*) as views'),
                DB::raw('COUNT(DISTINCT ual.user_id) as active_users'),
                DB::raw('MAX(ual.created_at) as last_seen'),
            ]);

        if ($search !== '') {
            $query->where('statics.name', 'like', '%' . $search . '%');
        }

        return $query->orderBy($sortColumn, $direction)->paginate($perPage);
    }

    public function topUsersPaginated(
        int $days,
        string $search,
        string $sort,
        string $direction,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $columns = [
            'name' => 'user_name',
            'static' => 'static_name',
            'views' => 'views',
            'last_seen' => 'last_seen',
        ];
        $sortColumn = $columns[$sort] ?? 'views';
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $query = $this->getQuery()->newQuery()
            ->from('user_activity_logs as ual')
            ->leftJoin('users', 'users.id', '=', 'ual.user_id')
            ->leftJoin('statics', 'statics.id', '=', 'ual.static_id')
            ->where('ual.created_at', '>=', now()->subDays($days))
            ->groupBy('ual.user_id', 'users.name', 'users.battletag', 'ual.static_id', 'statics.name')
            ->select([
                'ual.user_id',
                'users.name as user_name',
                'users.battletag',
                'ual.static_id',
                'statics.name as static_name',
                DB::raw('COUNT(*) as views'),
                DB::raw('MAX(ual.created_at) as last_seen'),
            ]);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('users.name', 'like', $like)
                  ->orWhere('users.battletag', 'like', $like)
                  ->orWhere('statics.name', 'like', $like);
            });
        }

        return $query->orderBy($sortColumn, $direction)->paginate($perPage);
    }

    public function dailyActivity(int $days = 14): Collection
    {
        return $this->getQuery()->newQuery()
            ->from('user_activity_logs')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get([
                DB::raw('DATE(created_at) as day'),
                DB::raw('COUNT(*) as views'),
                DB::raw('COUNT(DISTINCT user_id) as active_users'),
            ]);
    }

    public function sleepingStatics(int $days = 7): Collection
    {
        $cutoff = now()->subDays($days);

        return $this->getQuery()->newQuery()
            ->from('statics')
            ->leftJoin('user_activity_logs as ual', 'ual.static_id', '=', 'statics.id')
            ->groupBy('statics.id', 'statics.name', 'statics.created_at')
            ->havingRaw('MAX(ual.created_at) IS NULL OR MAX(ual.created_at) < ?', [$cutoff])
            ->orderByRaw('MAX(ual.created_at) IS NULL DESC, MAX(ual.created_at) ASC')
            ->get([
                'statics.id as static_id',
                'statics.name as static_name',
                'statics.created_at as static_created_at',
                DB::raw('MAX(ual.created_at) as last_seen'),
            ]);
    }

    public function forUserPaginated(
        int $userId,
        string $search,
        string $sort,
        string $direction,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $query = $this->forUser($userId);

        if ($search !== '') {
            $query->search($search);
        }

        return $query->orderBy($sort, $direction)->paginate($perPage);
    }

    public function bulkInsert(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $this->getQuery()->insert($rows);
        return count($rows);
    }
}
