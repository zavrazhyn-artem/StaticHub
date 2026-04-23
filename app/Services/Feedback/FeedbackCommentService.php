<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Models\FeedbackComment;
use App\Models\FeedbackPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FeedbackCommentService
{
    public function __construct(
        private readonly FeedbackUploadService $uploads,
    ) {}

    /**
     * @param array<int, string>|null $images
     */
    public function create(FeedbackPost $post, User $author, string $body, ?array $images = null): FeedbackComment
    {
        $sanitized = $this->uploads->sanitizeInputPaths($images ?? []);

        return DB::transaction(function () use ($post, $author, $body, $sanitized) {
            /** @var FeedbackComment $comment */
            $comment = FeedbackComment::query()->create([
                'feedback_post_id' => $post->id,
                'user_id' => $author->id,
                'body' => trim($body),
                'images' => $sanitized ?: null,
            ]);

            $post->increment('comments_count');

            return $comment->fresh(['user']);
        });
    }

    public function delete(FeedbackComment $comment): void
    {
        $images = $comment->images ?? [];

        DB::transaction(function () use ($comment) {
            $postId = $comment->feedback_post_id;
            $comment->delete();
            FeedbackPost::query()->where('id', $postId)->decrement('comments_count');
        });

        $this->uploads->deleteMany(is_array($images) ? $images : []);
    }
}
