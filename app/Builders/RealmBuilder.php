<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class RealmBuilder extends Builder
{
    /**
     * Scope a query to sort realms by name.
     */
    public function orderedByName(): self
    {
        return $this->orderBy('name');
    }

    /**
     * Find a realm by its slug.
     */
    public function findBySlug(string $slug): ?\App\Models\Realm
    {
        return $this->where('slug', $slug)->first();
    }
}
