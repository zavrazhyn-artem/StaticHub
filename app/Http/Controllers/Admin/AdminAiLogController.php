<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiRequestLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAiLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AiRequestLog::query()->latest();

        if ($request->filled('provider')) {
            $query->forProvider($request->input('provider'));
        }

        if ($request->filled('status')) {
            $request->input('status') === 'success'
                ? $query->successful()
                : $query->failed();
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->inDateRange($request->input('date_from'), $request->input('date_to'));
        }

        $logs = $query->paginate(25)->withQueryString();

        $summary = [
            'total_requests' => AiRequestLog::query()->recent()->count(),
            'total_tokens' => AiRequestLog::query()->recent()->sum('total_tokens'),
            'total_cost' => AiRequestLog::query()->recent()->sum('cost_estimate'),
            'avg_response_time' => (int) AiRequestLog::query()->recent()->avg('response_time_ms'),
            'error_count' => AiRequestLog::query()->recent()->failed()->count(),
        ];

        $providers = AiRequestLog::query()->distinct()->pluck('provider')->sort()->values();

        return view('admin.ai-logs.index', compact('logs', 'summary', 'providers'));
    }
}
