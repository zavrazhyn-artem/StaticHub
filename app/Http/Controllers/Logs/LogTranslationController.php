<?php

declare(strict_types=1);

namespace App\Http\Controllers\Logs;

use App\Enums\Locale;
use App\Http\Controllers\Controller;
use App\Models\PersonalTacticalReport;
use App\Models\StaticGroup;
use App\Services\Analysis\BlockTranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LogTranslationController extends Controller
{
    public function __construct(
        private readonly BlockTranslationService $translationService,
    ) {}

    public function personal(Request $request, StaticGroup $static, PersonalTacticalReport $personalReport): JsonResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_column(Locale::cases(), 'value'))],
        ]);

        $user = auth()->user();
        abort_unless($user && $static->hasMember($user->id), 403);

        $report = $personalReport->tacticalReport()->first();
        abort_unless($report && $report->static_id === $static->id, 403);

        $blocks = $this->translationService->translate($personalReport, $data['locale']);

        return response()->json([
            'locale' => $data['locale'],
            'blocks' => $blocks,
        ]);
    }
}
