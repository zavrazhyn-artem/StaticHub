<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class FetchStaticRosterTask
{
    /**
     * Fetch all users of a static with their characters loaded for that static.
     *
     * @param int $staticId
     * @return Collection<int, User>
     */
    public function run(int $staticId): Collection
    {
        return User::query()->inStatic($staticId)
            ->with(['characters' => function ($query) use ($staticId) {
                $query->whereHas('statics', function ($q) use ($staticId) {
                    $q->where('statics.id', $staticId);
                })
                ->with(['statics' => function ($q) use ($staticId) {
                    $q->where('statics.id', $staticId);
                }]);
            }])
            ->get();
    }
}
