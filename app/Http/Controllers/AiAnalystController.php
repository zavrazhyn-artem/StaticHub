<?php

namespace App\Http\Controllers;

use App\Http\Requests\AiAnalystRequest;
use App\Services\Analysis\AiAnalystService;
use Illuminate\Http\JsonResponse;

class AiAnalystController extends Controller
{
    protected AiAnalystService $aiAnalystService;

    public function __construct(AiAnalystService $aiAnalystService)
    {
        $this->aiAnalystService = $aiAnalystService;
    }

    /**
     * Ask Gemini to analyze the log data.
     *
     * @param AiAnalystRequest $request
     * @return JsonResponse
     */
    public function ask(AiAnalystRequest $request): JsonResponse
    {
        $result = $this->aiAnalystService->analyze(
            $request->integer('report_id'),
            $request->input('message')
        );

        return response()->json(['reply' => $result]);
    }
}
