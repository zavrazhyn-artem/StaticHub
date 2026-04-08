<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\ApiUsageLogBuilder;
use Illuminate\Database\Eloquent\Model;

class ApiUsageLog extends Model
{
    protected $fillable = [
        'service',
        'endpoint',
        'method',
        'status_code',
        'response_time_ms',
        'rate_limit_remaining',
        'rate_limit_limit',
        'rate_limit_reset_at',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'response_time_ms' => 'integer',
        'rate_limit_remaining' => 'integer',
        'rate_limit_limit' => 'integer',
        'rate_limit_reset_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function newEloquentBuilder($query): ApiUsageLogBuilder
    {
        return new ApiUsageLogBuilder($query);
    }
}
