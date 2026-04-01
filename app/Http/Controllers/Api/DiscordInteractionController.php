<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DiscordInteractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscordInteractionController extends Controller
{
    public function __construct(
        private readonly DiscordInteractionService $interactionService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        return response()->json($this->interactionService->handle($request));
    }
}
