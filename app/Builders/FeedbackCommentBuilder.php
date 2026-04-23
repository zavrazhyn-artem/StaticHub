<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\FeedbackComment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FeedbackCommentBuilder extends Builder
{
    public function forPost(int $postId): self
    {
        return $this->where('feedback_post_id', $postId);
    }

    /**
     * @return Collection<int, FeedbackComment>
     */
    public function orderedForPost(int $postId): Collection
    {
        return $this->forPost($postId)
            ->with('user')
            ->orderBy('created_at')
            ->get();
    }

    public function findById(int $id): ?FeedbackComment
    {
        /** @var FeedbackComment|null */
        return $this->where('id', $id)->first();
    }
}
