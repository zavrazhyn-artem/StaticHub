<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class UserBuilder extends Builder
{
    public function inStatic(int $staticId): self
    {
        return $this->whereHas('characters.statics', function ($query) use ($staticId) {
            $query->where('statics.id', $staticId);
        });
    }

    public function withStatics(): self
    {
        return $this->with('statics');
    }

    public function withCharactersAndStatics(): self
    {
        return $this->with('characters.statics');
    }

    public function firstStaticForUser(int $userId): ?\App\Models\StaticGroup
    {
        $user = $this->where('id', $userId)->with('statics')->first();
        return $user ? $user->statics->first() : null;
    }
}
