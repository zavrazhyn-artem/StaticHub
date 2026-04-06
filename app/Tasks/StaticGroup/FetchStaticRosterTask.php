<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup;

use App\Models\CharacterStaticSpec;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class FetchStaticRosterTask
{
    /**
     * Fetch all users of a static with their characters loaded for that static.
     * Also attaches main_spec attribute on each character for frontend use.
     */
    public function run(int $staticId): Collection
    {
        $users = User::query()->inStatic($staticId)
            ->with(['characters' => function ($query) use ($staticId) {
                $query->whereHas('statics', function ($q) use ($staticId) {
                    $q->where('statics.id', $staticId);
                })
                ->with(['statics' => function ($q) use ($staticId) {
                    $q->where('statics.id', $staticId);
                }]);
            }])
            ->get();

        // Pre-load main specs for all characters in this static in one query
        $allCharacterIds = $users->flatMap(fn ($u) => $u->characters->pluck('id'));

        $mainSpecRecords = CharacterStaticSpec::whereIn('character_id', $allCharacterIds)
            ->where('static_id', $staticId)
            ->where('is_main', true)
            ->with('specialization')
            ->get()
            ->keyBy('character_id');

        // Set main_spec attribute on each character
        $users->each(function ($user) use ($mainSpecRecords) {
            $user->characters->each(function ($char) use ($mainSpecRecords) {
                $specRecord = $mainSpecRecords->get($char->id);
                $spec = $specRecord?->specialization;

                $char->setAttribute('main_spec', $spec ? [
                    'id'       => $spec->id,
                    'name'     => $spec->name,
                    'role'     => $spec->role,
                    'icon_url' => $spec->icon_url,
                ] : null);
            });
        });

        return $users;
    }
}
