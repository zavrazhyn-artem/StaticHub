<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\OAuthDeviceCodeBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $device_code_hash
 * @property string $user_code
 * @property string $client_name
 * @property array $scope
 * @property int|null $user_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $denied_at
 * @property Carbon|null $last_polled_at
 * @property Carbon $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OAuthDeviceCode extends Model
{
    protected $table = 'oauth_device_codes';

    protected $fillable = [
        'device_code_hash',
        'user_code',
        'client_name',
        'scope',
        'user_id',
        'approved_at',
        'denied_at',
        'last_polled_at',
        'expires_at',
    ];

    protected $casts = [
        'scope'          => 'array',
        'approved_at'    => 'datetime',
        'denied_at'      => 'datetime',
        'last_polled_at' => 'datetime',
        'expires_at'     => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function isDenied(): bool
    {
        return $this->denied_at !== null;
    }

    public function newEloquentBuilder($query): OAuthDeviceCodeBuilder
    {
        return new OAuthDeviceCodeBuilder($query);
    }
}
