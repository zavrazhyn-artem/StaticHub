<?php

namespace App\Builders;

use App\Models\PersonalTacticalReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @method PersonalTacticalReport|null first()
 */
class PersonalTacticalReportBuilder extends Builder
{
    /**
     * Get reports for specific characters with relations.
     *
     * @param array $characterIds
     * @return Collection
     */
    public function forCharacters(array $characterIds): Collection
    {
        return $this->whereIn('character_id', $characterIds)
            ->with(['tacticalReport.event', 'character'])
            ->latest()
            ->get();
    }
}
