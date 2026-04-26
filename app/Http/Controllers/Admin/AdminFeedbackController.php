<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analysis\ReportFeedbackService;
use Illuminate\View\View;

class AdminFeedbackController extends Controller
{
    public function __construct(
        private readonly ReportFeedbackService $feedbackService,
    ) {}

    public function index(): View
    {
        return view('admin.feedback.index', [
            'data' => $this->feedbackService->buildAdminDashboardPayload(),
        ]);
    }
}
