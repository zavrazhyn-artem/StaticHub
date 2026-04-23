<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\FeedbackPost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FeedbackPostBuilder extends Builder
{
    public function byStatus(string $status): self
    {
        return $this->where('status', $status);
    }

    public function byTag(string $tag): self
    {
        return $this->where('tag', $tag);
    }

    public function search(string $term): self
    {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';

        return $this->where(function ($q) use ($like) {
            $q->where('title', 'like', $like)
              ->orWhere('body', 'like', $like);
        });
    }

    public function mostVoted(): self
    {
        return $this->orderByDesc('votes_count')->orderByDesc('id');
    }

    public function recent(): self
    {
        return $this->orderByDesc('created_at')->orderByDesc('id');
    }

    public function withUserVote(?int $userId): self
    {
        if ($userId === null) {
            return $this;
        }

        return $this->select('feedback_posts.*')->addSelect([
            'user_has_voted' => \App\Models\FeedbackVote::selectRaw('1')
                ->whereColumn('feedback_post_id', 'feedback_posts.id')
                ->where('user_id', $userId)
                ->limit(1),
        ]);
    }

    public function findById(int $id): ?FeedbackPost
    {
        /** @var FeedbackPost|null */
        return $this->where('id', $id)->first();
    }

    /**
     * @return Collection<int, FeedbackPost>
     */
    public function groupedByStatus(): Collection
    {
        return $this->orderBy('status')->orderByDesc('votes_count')->get();
    }
}
