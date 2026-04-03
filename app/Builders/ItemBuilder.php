<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class ItemBuilder extends Builder
{
    public function updateMetadata(int $id, string $name, ?string $icon): void
    {
        $this->updateOrInsert(
            ['id' => $id],
            [
                'name' => $name,
                'icon' => $icon,
                'updated_at' => now(),
            ]
        );
    }
}
