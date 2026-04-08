<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\InviteCodeBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InviteCode extends Model
{
    protected $fillable = [
        'code',
        'is_used',
        'used_by_user_id',
        'used_at',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'used_at' => 'datetime',
    ];

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }

    public function markAsUsed(int $userId): void
    {
        $this->update([
            'is_used' => true,
            'used_by_user_id' => $userId,
            'used_at' => now(),
        ]);
    }

    public static function generateCode(): string
    {
        return 'BLASTR-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
    }

    public function newEloquentBuilder($query): InviteCodeBuilder
    {
        return new InviteCodeBuilder($query);
    }
}
