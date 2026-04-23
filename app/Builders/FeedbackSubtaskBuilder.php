<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\FeedbackSubtask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FeedbackSubtaskBuilder extends Builder
{
    public function forPost(int $postId): self
    {
        return $this->where('feedback_post_id', $postId);
    }

    /**
     * @return Collection<int, FeedbackSubtask>
     */
    public function orderedForPost(int $postId): Collection
    {
        return $this->forPost($postId)->orderBy('sort_order')->orderBy('id')->get();
    }

    public function findById(int $id): ?FeedbackSubtask
    {
        /** @var FeedbackSubtask|null */
        return $this->where('id', $id)->first();
    }

    public function nextSortOrderFor(int $postId): int
    {
        return ((int) $this->forPost($postId)->max('sort_order')) + 1;
    }
}
