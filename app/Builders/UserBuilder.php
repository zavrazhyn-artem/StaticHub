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

    public function hasMainCharacter(int $userId): bool
    {
        return \App\Models\Character::where('user_id', $userId)
            ->whereHas('statics', function ($query) {
                $query->where('role', 'main');
            })->exists();
    }

    public function hasAnyStatic(int $userId): bool
    {
        return \DB::table('static_user')
            ->where('user_id', $userId)
            ->exists();
    }

    public function hasCharacterInAnyStatic(int $userId): bool
    {
        $user = \App\Models\User::find($userId);
        if (!$user) {
            return false;
        }

        return \DB::table('character_static')
            ->whereIn('static_id', $user->statics()->pluck('statics.id'))
            ->whereIn('character_id', $user->characters()->pluck('id'))
            ->exists();
    }
}
