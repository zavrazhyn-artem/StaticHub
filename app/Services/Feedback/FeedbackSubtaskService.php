<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Models\FeedbackPost;
use App\Models\FeedbackSubtask;
use Illuminate\Support\Facades\DB;

class FeedbackSubtaskService
{
    public function create(FeedbackPost $post, string $title): FeedbackSubtask
    {
        return DB::transaction(function () use ($post, $title) {
            $nextOrder = FeedbackSubtask::query()->nextSortOrderFor($post->id);

            /** @var FeedbackSubtask $subtask */
            $subtask = FeedbackSubtask::query()->create([
                'feedback_post_id' => $post->id,
                'title' => trim($title),
                'status' => 'todo',
                'sort_order' => $nextOrder,
            ]);

            $post->increment('subtasks_count');

            return $subtask;
        });
    }

    public function updateTitle(FeedbackSubtask $subtask, string $title): FeedbackSubtask
    {
        $subtask->update(['title' => trim($title)]);

        return $subtask->fresh();
    }

    public function updateStatus(FeedbackSubtask $subtask, string $status): FeedbackSubtask
    {
        if (! in_array($status, FeedbackSubtask::STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid subtask status: {$status}");
        }

        $subtask->update(['status' => $status]);

        return $subtask->fresh();
    }

    public function delete(FeedbackSubtask $subtask): void
    {
        DB::transaction(function () use ($subtask) {
            $postId = $subtask->feedback_post_id;
            $subtask->delete();
            FeedbackPost::query()->where('id', $postId)->decrement('subtasks_count');
        });
    }

    /**
     * Reorder subtasks: accepts ordered list of subtask IDs.
     *
     * @param int[] $orderedIds
     */
    public function reorder(FeedbackPost $post, array $orderedIds): void
    {
        DB::transaction(function () use ($post, $orderedIds) {
            foreach ($orderedIds as $index => $id) {
                FeedbackSubtask::query()
                    ->where('id', $id)
                    ->where('feedback_post_id', $post->id)
                    ->update(['sort_order' => $index]);
            }
        });
    }
}
