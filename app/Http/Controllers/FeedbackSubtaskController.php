<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FeedbackPost;
use App\Models\FeedbackSubtask;
use App\Services\Feedback\FeedbackSubtaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FeedbackSubtaskController extends Controller
{
    public function __construct(
        private readonly FeedbackSubtaskService $subtaskService,
    ) {}

    public function store(Request $request, FeedbackPost $post): JsonResponse
    {
        Gate::authorize('manage-feedback');

        $data = $request->validate([
            'title' => 'required|string|max:300',
        ]);

        $subtask = $this->subtaskService->create($post, $data['title']);

        return response()->json([
            'id' => $subtask->id,
            'title' => $subtask->title,
            'status' => $subtask->status,
            'sort_order' => $subtask->sort_order,
        ], 201);
    }

    public function update(Request $request, FeedbackSubtask $subtask): JsonResponse
    {
        Gate::authorize('manage-feedback');

        $data = $request->validate([
            'title' => 'sometimes|string|max:300',
            'status' => 'sometimes|string|in:' . implode(',', FeedbackSubtask::STATUSES),
        ]);

        if (isset($data['title'])) {
            $subtask = $this->subtaskService->updateTitle($subtask, $data['title']);
        }

        if (isset($data['status'])) {
            $subtask = $this->subtaskService->updateStatus($subtask, $data['status']);
        }

        return response()->json([
            'id' => $subtask->id,
            'title' => $subtask->title,
            'status' => $subtask->status,
        ]);
    }

    public function destroy(FeedbackSubtask $subtask): JsonResponse
    {
        Gate::authorize('manage-feedback');

        $this->subtaskService->delete($subtask);

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request, FeedbackPost $post): JsonResponse
    {
        Gate::authorize('manage-feedback');

        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $this->subtaskService->reorder($post, $data['ids']);

        return response()->json(['ok' => true]);
    }
}
