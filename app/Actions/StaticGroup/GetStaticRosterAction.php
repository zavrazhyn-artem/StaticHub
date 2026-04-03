<?php

namespace App\Actions\StaticGroup;

use App\Models\StaticGroup;
use Illuminate\Database\Eloquent\Collection;

class GetStaticRosterAction
{
    /**
     * Get the roster members for the given static group.
     */
    public function execute(StaticGroup $static): Collection
    {
        return $static->members()
            ->with('characters')
            ->get();
    }
}
