<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Models\FeedbackPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class FeedbackListService
{
    public function __construct(
        private readonly FeedbackUploadService $uploads,
    ) {}

    /**
     * Build the feedback index page payload.
     *
     * @return array{posts: array, filters: array, counts: array}
     */
    public function buildIndexPayload(?User $viewer, ?string $status, ?string $tag, string $sort, ?string $search): array
    {
        $query = FeedbackPost::query()
            ->with('user')
            ->withUserVote($viewer?->id);

        if ($status !== null && $status !== 'all') {
            $query->byStatus($status);
        }

        if ($tag !== null && $tag !== 'all') {
            $query->byTag($tag);
        }

        if ($search !== null && trim($search) !== '') {
            $query->search(trim($search));
        }

        $query = match ($sort) {
            'recent' => $query->recent(),
            default  => $query->mostVoted(),
        };

        $posts = $query->limit(60)->get();

        return [
            'posts' => $posts->map(fn (FeedbackPost $p) => $this->presentCard($p))->all(),
            'filters' => [
                'status' => $status ?? 'all',
                'tag' => $tag ?? 'all',
                'sort' => $sort,
                'search' => $search ?? '',
            ],
            'counts' => $this->statusCounts(),
            'tag_counts' => $this->tagCounts(),
        ];
    }

    /**
     * Kanban payload: posts grouped by status. Used for /roadmap.
     */
    public function buildRoadmapPayload(?User $viewer): array
    {
        $posts = FeedbackPost::query()
            ->with('user')
            ->withUserVote($viewer?->id)
            ->whereIn('status', ['under_review', 'planned', 'in_progress', 'done'])
            ->mostVoted()
            ->get();

        $columns = [
            'under_review' => [],
            'planned' => [],
            'in_progress' => [],
            'done' => [],
        ];

        foreach ($posts as $post) {
            $columns[$post->status][] = $this->presentCard($post);
        }

        return ['columns' => $columns];
    }

    /**
     * Full detail payload with comments, subtasks, author.
     */
    public function buildDetailPayload(FeedbackPost $post, ?User $viewer): array
    {
        $service = app(FeedbackService::class);
        $detail = $service->loadDetail($post, $viewer?->id);

        $author = $detail['post']->user;
        $staticId = $viewer?->statics()->first()?->id;

        return [
            'post' => [
                ...$this->presentCard($detail['post']),
                'body' => $detail['post']->body,
                'user_has_voted' => $detail['user_has_voted'],
            ],
            'comments' => $detail['comments']->map(fn ($c) => [
                'id' => $c->id,
                'body' => $c->body,
                'images' => $this->uploads->presentMany($c->images),
                'created_at' => $c->created_at?->toIso8601String(),
                'author' => $this->presentAuthor($c->user),
                'can_delete' => $viewer !== null && ($c->user_id === $viewer->id || $this->viewerIsAdmin()),
            ])->all(),
            'subtasks' => $detail['subtasks']->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'status' => $s->status,
                'sort_order' => $s->sort_order,
            ])->all(),
        ];
    }

    private function presentCard(FeedbackPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'excerpt' => $this->excerpt($post->body),
            'status' => $post->status,
            'tag' => $post->tag ?? 'general',
            'votes_count' => (int) $post->votes_count,
            'comments_count' => (int) $post->comments_count,
            'subtasks_count' => (int) $post->subtasks_count,
            'user_has_voted' => (bool) ($post->user_has_voted ?? false),
            'created_at' => $post->created_at?->toIso8601String(),
            'author' => $this->presentAuthor($post->user),
            'images' => $this->uploads->presentMany($post->images),
        ];
    }

    private function presentAuthor(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $staticGroup = $user->ownedStatics()->first() ?? $user->statics()->first();
        $mainCharacter = $user->getMainCharacterForStatic($staticGroup?->id);
        $displayName = $mainCharacter?->name ?? $user->battletag ?? $user->name;
        $playableClass = $mainCharacter?->playable_class;

        return [
            'id' => $user->id,
            'name' => $displayName,
            'static_name' => $staticGroup?->name,
            'playable_class' => $playableClass,
            'avatar_url' => $user->getEffectiveAvatarUrl($staticGroup?->id),
        ];
    }

    private function excerpt(?string $body, int $max = 200): ?string
    {
        if ($body === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', $body));

        return mb_strlen($text) > $max
            ? mb_substr($text, 0, $max) . '…'
            : $text;
    }

    private function statusCounts(): array
    {
        return FeedbackPost::query()
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();
    }

    private function tagCounts(): array
    {
        return FeedbackPost::query()
            ->selectRaw('tag, COUNT(*) as cnt')
            ->groupBy('tag')
            ->pluck('cnt', 'tag')
            ->toArray();
    }

    private function viewerIsAdmin(): bool
    {
        return session('admin_authenticated') === true;
    }
}
