<?php

declare(strict_types=1);

namespace App\Data\Analysis\Blizzard;

class BlizzardData
{
    public function __construct(
        public array $stats,
        public ?string $avatar_url,
        public array $equipment,
        public array $mplus,
        public array $raids,
        public array $profile,
    ) {
    }

    public static function fromRaw(array $profile, array $equipment, array $media, array $mplus, array $raids): self
    {
        $avatarUrl = null;
        foreach ($media['assets'] ?? [] as $asset) {
            if (($asset['key'] ?? '') === 'avatar') {
                $avatarUrl = $asset['value'];
                break;
            }
        }

        return new self(
            stats: [
                'item_level'          => $profile['average_item_level'] ?? null,
                'equipped_item_level' => $profile['equipped_item_level'] ?? null,
            ],
            avatar_url: $avatarUrl,
            equipment: $equipment,
            mplus: $mplus,
            raids: $raids,
            profile: $profile,
        );
    }

    public function toArray(): array
    {
        return [
            'stats'     => $this->stats,
            'avatar_url' => $this->avatar_url,
            'equipment' => $this->equipment,
            'mplus'     => $this->mplus,
            'raids'     => $this->raids,
            'profile'   => $this->profile,
        ];
    }
}
