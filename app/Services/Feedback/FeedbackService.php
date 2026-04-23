<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Models\FeedbackPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FeedbackService
{
    public function __construct(
        private readonly FeedbackUploadService $uploads,
    ) {}

    /**
     * @param array<int, string>|null $images
     */
    public function create(User $author, string $title, ?string $body, string $tag, ?array $images = null): FeedbackPost
    {
        /** @var FeedbackPost */
        return FeedbackPost::query()->create([
            'user_id' => $author->id,
            'title' => trim($title),
            'body' => $body !== null ? trim($body) : null,
            'status' => 'under_review',
            'tag' => $this->normalizeTag($tag),
            'images' => $this->uploads->sanitizeInputPaths($images) ?: null,
        ]);
    }

    /**
     * @param array<int, string>|null $images
     */
    public function update(FeedbackPost $post, string $title, ?string $body, ?string $tag = null, ?array $images = null): FeedbackPost
    {
        $attrs = [
            'title' => trim($title),
            'body' => $body !== null ? trim($body) : null,
        ];

        if ($tag !== null) {
            $attrs['tag'] = $this->normalizeTag($tag);
        }

        if ($images !== null) {
            $sanitized = $this->uploads->sanitizeInputPaths($images);
            $previous = $post->images ?? [];
            $removed = array_values(array_diff($previous, $sanitized));
            $this->uploads->deleteMany($removed);
            $attrs['images'] = $sanitized ?: null;
        }

        $post->update($attrs);

        return $post->fresh();
    }

    private function normalizeTag(string $tag): string
    {
        return in_array($tag, FeedbackPost::TAGS, true) ? $tag : 'general';
    }

    public function updateStatus(FeedbackPost $post, string $status): FeedbackPost
    {
        if (! in_array($status, FeedbackPost::STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $post->update(['status' => $status]);

        return $post->fresh();
    }

    public function delete(FeedbackPost $post): void
    {
        $this->uploads->deleteMany($post->images);

        $commentImages = \App\Models\FeedbackComment::query()
            ->where('feedback_post_id', $post->id)
            ->whereNotNull('images')
            ->pluck('images')
            ->all();
        foreach ($commentImages as $paths) {
            $this->uploads->deleteMany(is_array($paths) ? $paths : []);
        }

        $post->delete();
    }

    /**
     * Get single post with its comments, subtasks, and the current user's vote state.
     *
     * @return array{post: FeedbackPost, comments: \Illuminate\Database\Eloquent\Collection, subtasks: \Illuminate\Database\Eloquent\Collection, user_has_voted: bool}
     */
    public function loadDetail(FeedbackPost $post, ?int $viewerId): array
    {
        $post->load('user');
        $comments = \App\Models\FeedbackComment::query()->orderedForPost($post->id);
        $subtasks = \App\Models\FeedbackSubtask::query()->orderedForPost($post->id);
        $userHasVoted = $viewerId !== null
            && DB::table('feedback_votes')
                ->where('feedback_post_id', $post->id)
                ->where('user_id', $viewerId)
                ->exists();

        return [
            'post' => $post,
            'comments' => $comments,
            'subtasks' => $subtasks,
            'user_has_voted' => $userHasVoted,
        ];
    }
}
