<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaticRosterMemberResource extends JsonResource
{
    private int $staticId;

    public function setStaticId(int $id): static
    {
        $this->staticId = $id;
        return $this;
    }

    public function toArray(Request $request): array
    {
        // Characters are eager-loaded with their `statics` relationship scoped
        // to this static only, so ->first() on statics gives the single pivot
        // entry for this group. Characters not in this static have an empty
        // statics collection and are excluded by isNotEmpty().
        $characters = $this->characters->filter(
            fn (Character $c) => $c->statics->isNotEmpty()
        );


        $mainCharacter = $characters->first(
            fn (Character $c) => $c->statics->first()?->pivot?->role === 'main'
        );

        $altCharacters = $characters->filter(
            fn (Character $c) => $c->statics->first()?->pivot?->role === 'alt'
        );

        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'access_role'    => $this->pivot->access_role  ?? 'member',
            'roster_status'  => $this->pivot->roster_status ?? 'bench',
            'main_character' => $mainCharacter ? $this->formatCharacter($mainCharacter) : null,
            'alts'           => $altCharacters
                ->map(fn (Character $alt) => $this->formatCharacter($alt))
                ->values()
                ->toArray(),
        ];
    }

    /**
     * Merges compiled_data — the flat compiler output stored on the character —
     * with the character's identity fields.
     * No further parsing or mapping is performed here.
     *
     * @return array<string, mixed>
     */
    private function formatCharacter(Character $character): array
    {
        return array_merge(
            $character->compiled_data ?? [],
            [
                'id'   => $character->id,
                'name' => $character->name,
            ]
        );
    }
}
