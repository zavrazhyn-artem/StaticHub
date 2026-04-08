<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\InviteCode;
use Illuminate\Database\Eloquent\Builder;

class InviteCodeBuilder extends Builder
{
    public function unused(): self
    {
        return $this->where('is_used', false);
    }

    public function used(): self
    {
        return $this->where('is_used', true);
    }

    public function findByCode(string $code): ?InviteCode
    {
        return $this->where('code', $code)->first();
    }

    public function findUnusedByCode(string $code): ?InviteCode
    {
        return $this->unused()->where('code', $code)->first();
    }
}
