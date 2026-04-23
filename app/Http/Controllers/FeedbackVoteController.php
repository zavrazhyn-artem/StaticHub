<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FeedbackPost;
use App\Services\Feedback\FeedbackVoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackVoteController extends Controller
{
    public function __construct(
        private readonly FeedbackVoteService $voteService,
    ) {}

    public function toggle(Request $request, FeedbackPost $post): JsonResponse
    {
        $result = $this->voteService->toggle($post, $request->user());

        return response()->json($result);
    }
}
