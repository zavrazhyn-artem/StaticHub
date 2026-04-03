<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup;

use App\Models\StaticGroup;
use Illuminate\Support\Collection;

class FetchRosterOverviewTask
{
    /**
     * Get the overview data for a static roster (mains with their alts).
     *
     * @param StaticGroup $static
     * @return Collection
     */
    public function run(StaticGroup $static): Collection
    {
        $allCharacters = $static->characters()->get();

        $mains = $allCharacters->where(function ($char) {
            return strtolower($char->pivot->role) === 'main';
        })->values();

        foreach ($mains as $main) {
            $main->alts = $allCharacters->where('user_id', $main->user_id)
                ->where('id', '!=', $main->id)
                ->values();
        }

        return $mains;
    }
}
