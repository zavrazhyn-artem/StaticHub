<?php

declare(strict_types=1);

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

use App\Models\WishlistDroptimizerConfig;

class WishlistDroptimizerConfigBuilder extends Builder
{
    public function forStatic(int $staticId): self
    {
        // Default rows always sort first so the UI lists "Default" at the
        // top regardless of the position counter on extras.
        return $this->where('static_id', $staticId)
            ->orderByDesc('is_default')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function defaultForStatic(int $staticId): ?WishlistDroptimizerConfig
    {
        return $this->where('static_id', $staticId)->where('is_default', true)->first();
    }
}
