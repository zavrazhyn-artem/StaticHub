<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class SpecializationBuilder extends Builder
{
    public function findByNameAndClass(string $specName, string $className): ?object
    {
        return $this->where('name', $specName)->where('class_name', $className)->first();
    }

    public function forClass(string $className): self
    {
        return $this->where('class_name', $className);
    }
}
