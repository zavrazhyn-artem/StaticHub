<?php

namespace App\Models;

use App\Builders\ReportFeedbackBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tactical_report_id
 * @property int $user_id
 * @property int $report_rating
 * @property int|null $chat_rating
 * @property array|null $liked_tags
 * @property array|null $disliked_tags
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TacticalReport $tacticalReport
 * @property-read User $user
 */
class ReportFeedback extends Model
{
    protected $table = 'report_feedback';

    protected $fillable = [
        'tactical_report_id',
        'user_id',
        'report_rating',
        'chat_rating',
        'liked_tags',
        'disliked_tags',
        'comment',
    ];

    protected $casts = [
        'report_rating' => 'integer',
        'chat_rating'   => 'integer',
        'liked_tags'    => 'array',
        'disliked_tags' => 'array',
    ];

    public function newEloquentBuilder($query): ReportFeedbackBuilder
    {
        return new ReportFeedbackBuilder($query);
    }

    public function tacticalReport(): BelongsTo
    {
        return $this->belongsTo(TacticalReport::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
