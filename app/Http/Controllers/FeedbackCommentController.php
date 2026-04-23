<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FeedbackComment;
use App\Models\FeedbackPost;
use App\Services\Feedback\FeedbackCommentService;
use App\Services\Feedback\FeedbackUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FeedbackCommentController extends Controller
{
    public function __construct(
        private readonly FeedbackCommentService $commentService,
        private readonly FeedbackUploadService $uploads,
    ) {}

    public function store(Request $request, FeedbackPost $post): JsonResponse
    {
        $data = $request->validate([
            'body' => 'required|string|max:3000',
            'images' => 'sometimes|array|max:5',
            'images.*' => 'string',
        ]);

        $comment = $this->commentService->create(
            $post,
            $request->user(),
            $data['body'],
            $data['images'] ?? null,
        );
        $comment->loadMissing('user');

        return response()->json([
            'id' => $comment->id,
            'body' => $comment->body,
            'images' => $this->uploads->presentMany($comment->images),
            'created_at' => $comment->created_at?->toIso8601String(),
            'author' => [
                'id' => $comment->user->id,
                'name' => $comment->user->battletag ?? $comment->user->name,
                'avatar_url' => $comment->user->getEffectiveAvatarUrl(),
            ],
        ], 201);
    }

    public function destroy(Request $request, FeedbackComment $comment): JsonResponse
    {
        // Admin can always delete. Everyone else must be logged in AND own the comment.
        if (! Gate::allows('manage-feedback')) {
            $user = $request->user();
            if ($user === null) {
                abort(401);
            }
            if ($user->id !== $comment->user_id) {
                abort(403);
            }
        }

        $this->commentService->delete($comment);

        return response()->json(['ok' => true]);
    }
}
