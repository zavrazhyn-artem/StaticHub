<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class ItemBuilder extends Builder
{
    public function updateMetadata(int $id, string $name, ?string $icon, ?string $quality = null): void
    {
        $payload = [
            'name' => $name,
            'icon' => $icon,
            'updated_at' => now(),
        ];
        if ($quality !== null) {
            $payload['quality'] = $quality;
        }

        $this->updateOrInsert(['id' => $id], $payload);
    }

    public function incompleteIds(): array
    {
        return $this->whereNull('name')
            ->orWhereNull('icon_url')
            ->pluck('id')
            ->toArray();
    }

    public function allTrackedIds(): array
    {
        return $this->pluck('id')->toArray();
    }
}
