<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Feedback\FeedbackUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackUploadController extends Controller
{
    public function __construct(
        private readonly FeedbackUploadService $uploads,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,gif,webp',
                'max:' . (FeedbackUploadService::MAX_BYTES / 1024),
            ],
        ]);

        $path = $this->uploads->store($request->file('image'));

        return response()->json([
            'path' => $path,
            'url'  => $this->uploads->url($path),
        ], 201);
    }
}
