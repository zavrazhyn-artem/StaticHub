<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FeedbackPost;
use App\Services\Feedback\FeedbackListService;
use App\Services\Feedback\FeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function __construct(
        private readonly FeedbackService $feedbackService,
        private readonly FeedbackListService $listService,
    ) {}

    public function index(Request $request): View
    {
        $payload = $this->listService->buildIndexPayload(
            viewer: $request->user(),
            status: $request->query('status'),
            tag: $request->query('tag'),
            sort: (string) $request->query('sort', 'votes'),
            search: $request->query('search'),
        );

        return view('feedback.index', [
            'payload' => $payload,
            'isAuthenticated' => $request->user() !== null,
            'canManage' => Gate::allows('manage-feedback'),
        ]);
    }

    public function show(Request $request, FeedbackPost $post): View
    {
        $payload = $this->listService->buildDetailPayload($post, $request->user());

        return view('feedback.show', [
            'payload' => $payload,
            'isAuthenticated' => $request->user() !== null,
            'canManage' => Gate::allows('manage-feedback'),
            'isAuthor' => $request->user()?->id === $post->user_id,
        ]);
    }

    public function roadmap(Request $request): View
    {
        $payload = $this->listService->buildRoadmapPayload($request->user());

        return view('feedback.roadmap', [
            'payload' => $payload,
            'isAuthenticated' => $request->user() !== null,
            'canManage' => Gate::allows('manage-feedback'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'nullable|string|max:5000',
            'tag' => 'sometimes|string|in:' . implode(',', FeedbackPost::TAGS),
            'images' => 'sometimes|array|max:5',
            'images.*' => 'string',
        ]);

        $post = $this->feedbackService->create(
            $request->user(),
            $data['title'],
            $data['body'] ?? null,
            $data['tag'] ?? 'general',
            $data['images'] ?? null,
        );

        return response()->json([
            'id' => $post->id,
            'redirect' => route('feedback.show', $post),
        ], 201);
    }

    public function update(Request $request, FeedbackPost $post): JsonResponse
    {
        $this->authorizePostChange($request, $post);

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'nullable|string|max:5000',
            'tag' => 'sometimes|string|in:' . implode(',', FeedbackPost::TAGS),
            'images' => 'sometimes|array|max:5',
            'images.*' => 'string',
        ]);

        $this->feedbackService->update(
            $post,
            $data['title'],
            $data['body'] ?? null,
            $data['tag'] ?? null,
            array_key_exists('images', $data) ? ($data['images'] ?? []) : null,
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, FeedbackPost $post): JsonResponse
    {
        $this->authorizePostChange($request, $post);

        $this->feedbackService->delete($post);

        return response()->json(['ok' => true]);
    }

    /**
     * Authorize a post mutation: admin (session) OR post owner (auth user).
     * Anyone else gets 403; guests get 401.
     */
    private function authorizePostChange(Request $request, FeedbackPost $post): void
    {
        if (\Illuminate\Support\Facades\Gate::allows('manage-feedback')) {
            return;
        }

        $user = $request->user();
        if ($user === null) {
            abort(401, 'Authentication required.');
        }

        if ($user->id !== $post->user_id) {
            abort(403, 'You can only edit your own posts.');
        }
    }

    public function updateStatus(Request $request, FeedbackPost $post): JsonResponse
    {
        Gate::authorize('manage-feedback');

        $data = $request->validate([
            'status' => 'required|string|in:' . implode(',', FeedbackPost::STATUSES),
        ]);

        $this->feedbackService->updateStatus($post, $data['status']);

        return response()->json(['ok' => true, 'status' => $data['status']]);
    }
}
