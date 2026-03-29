<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class RecipeBuilder extends Builder
{
    public function withNames(array $names): self
    {
        return $this->whereIn('name', $names);
    }
}
