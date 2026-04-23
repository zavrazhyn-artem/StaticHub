<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\FeedbackPostBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $body
 * @property string $status
 * @property int $votes_count
 * @property int $comments_count
 * @property int $subtasks_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, FeedbackVote> $votes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, FeedbackComment> $comments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, FeedbackSubtask> $subtasks
 * @method static FeedbackPostBuilder query()
 */
class FeedbackPost extends Model
{
    public const STATUSES = [
        'under_review',
        'planned',
        'in_progress',
        'done',
        'closed',
    ];

    public const TAGS = [
        'raid_events',
        'boss_planner',
        'roster',
        'gear',
        'ai_analysis',
        'treasury',
        'character',
        'discord',
        'admin',
        'bug',
        'general',
    ];

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'status',
        'tag',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    public function newEloquentBuilder($query): FeedbackPostBuilder
    {
        return new FeedbackPostBuilder($query);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(FeedbackVote::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(FeedbackComment::class);
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(FeedbackSubtask::class)->orderBy('sort_order')->orderBy('id');
    }
}
