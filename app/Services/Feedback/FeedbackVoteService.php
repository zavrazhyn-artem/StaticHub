<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Models\FeedbackPost;
use App\Models\FeedbackVote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FeedbackVoteService
{
    /**
     * Toggle the user's vote on a post. Returns the post's new votes_count.
     *
     * @return array{voted: bool, votes_count: int}
     */
    public function toggle(FeedbackPost $post, User $user): array
    {
        return DB::transaction(function () use ($post, $user) {
            $existing = FeedbackVote::query()
                ->where('feedback_post_id', $post->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->delete();
                $post->decrement('votes_count');

                return ['voted' => false, 'votes_count' => (int) $post->fresh()->votes_count];
            }

            FeedbackVote::query()->create([
                'feedback_post_id' => $post->id,
                'user_id' => $user->id,
            ]);
            $post->increment('votes_count');

            return ['voted' => true, 'votes_count' => (int) $post->fresh()->votes_count];
        });
    }
}
