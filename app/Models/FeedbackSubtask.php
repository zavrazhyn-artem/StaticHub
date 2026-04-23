<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\FeedbackSubtaskBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $feedback_post_id
 * @property string $title
 * @property string $status
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read FeedbackPost $post
 * @method static FeedbackSubtaskBuilder query()
 */
class FeedbackSubtask extends Model
{
    public const STATUSES = [
        'todo',
        'in_progress',
        'done',
    ];

    protected $fillable = [
        'feedback_post_id',
        'title',
        'status',
        'sort_order',
    ];

    public function newEloquentBuilder($query): FeedbackSubtaskBuilder
    {
        return new FeedbackSubtaskBuilder($query);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedbackPost::class, 'feedback_post_id');
    }
}
