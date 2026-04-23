<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\FeedbackCommentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $feedback_post_id
 * @property int $user_id
 * @property string $body
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @property-read FeedbackPost $post
 * @method static FeedbackCommentBuilder query()
 */
class FeedbackComment extends Model
{
    protected $fillable = [
        'feedback_post_id',
        'user_id',
        'body',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    public function newEloquentBuilder($query): FeedbackCommentBuilder
    {
        return new FeedbackCommentBuilder($query);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedbackPost::class, 'feedback_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
