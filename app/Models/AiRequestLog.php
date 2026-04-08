<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\AiRequestLogBuilder;
use Illuminate\Database\Eloquent\Model;

class AiRequestLog extends Model
{
    protected $fillable = [
        'provider',
        'model',
        'endpoint',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'cost_estimate',
        'response_time_ms',
        'status',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'cost_estimate' => 'decimal:6',
        'response_time_ms' => 'integer',
        'metadata' => 'array',
    ];

    public function newEloquentBuilder($query): AiRequestLogBuilder
    {
        return new AiRequestLogBuilder($query);
    }
}
