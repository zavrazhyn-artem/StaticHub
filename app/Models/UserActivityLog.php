<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\UserActivityLogBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'static_id',
        'route_name',
        'url',
        'method',
        'ip',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function newEloquentBuilder($query): UserActivityLogBuilder
    {
        return new UserActivityLogBuilder($query);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staticGroup(): BelongsTo
    {
        return $this->belongsTo(StaticGroup::class, 'static_id');
    }
}
