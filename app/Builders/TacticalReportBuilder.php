<?php

namespace App\Builders;

use App\Models\TacticalReport;
use Illuminate\Database\Eloquent\Builder;

/**
 * @method TacticalReport findOrFail($id, $columns = ['*'])
 */
class TacticalReportBuilder extends Builder
{
    /**
     * Get tactical reports for a specific static group with optional difficulty filtering.
     *
     * @param int $staticId
     * @param string|null $difficulty
     * @return self
     */
    public function forStatic(int $staticId, ?string $difficulty = null): self
    {
        $this->where('static_id', $staticId)
            ->orderBy('created_at', 'desc');

        if ($difficulty) {
            $this->where('title', 'like', '%' . $difficulty . '%');
        }

        return $this;
    }

    /**
     * Find a report with its static group and characters roster.
     *
     * @param int $id
     * @return TacticalReport
     */
    public function findWithRoster(int $id): TacticalReport
    {
        return $this->with('staticGroup.characters')->findOrFail($id);
    }
}
