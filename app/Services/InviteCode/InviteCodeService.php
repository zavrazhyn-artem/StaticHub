<?php

declare(strict_types=1);

namespace App\Services\InviteCode;

use App\Models\InviteCode;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InviteCodeService
{
    public function validate(string $code): InviteCode
    {
        $inviteCode = InviteCode::query()->findUnusedByCode($code);

        if (!$inviteCode) {
            throw new HttpException(403, 'Invalid or already used invite code.');
        }

        return $inviteCode;
    }

    public function redeem(string $code, int $userId): void
    {
        $inviteCode = $this->validate($code);
        $inviteCode->markAsUsed($userId);
    }

    public function generateBatch(int $count): Collection
    {
        $codes = collect();

        for ($i = 0; $i < $count; $i++) {
            $codes->push(InviteCode::create([
                'code' => InviteCode::generateCode(),
            ]));
        }

        return $codes;
    }
}
