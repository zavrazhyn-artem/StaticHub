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
     * Get tactical reports for a specific static group with optional filters.
     *
     * @param int      $staticId
     * @param string[] $difficulties  Lowercase values, e.g. ['mythic', 'heroic']
     * @param string|null $dateFrom   Y-m-d
     * @param string|null $dateTo     Y-m-d
     * @return self
     */
    public function forStatic(int $staticId, array $difficulties = [], ?string $dateFrom = null, ?string $dateTo = null): self
    {
        $this->where('static_id', $staticId)
            ->orderBy('created_at', 'desc');

        if ($difficulties) {
            // Each difficulty is an OR condition: log must contain at least one selected value.
            $this->where(function (self $q) use ($difficulties) {
                foreach ($difficulties as $difficulty) {
                    $q->orWhereRaw('JSON_CONTAINS(difficulties, ?)', [json_encode($difficulty)]);
                }
            });
        }

        if ($dateFrom) {
            $this->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $this->whereDate('created_at', '<=', $dateTo);
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
