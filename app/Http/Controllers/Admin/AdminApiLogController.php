<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiUsageLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminApiLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = ApiUsageLog::query()->latest();

        if ($request->filled('service')) {
            $query->forService($request->input('service'));
        }

        if ($request->filled('errors_only')) {
            $query->withErrors();
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->inDateRange($request->input('date_from'), $request->input('date_to'));
        }

        $logs = $query->paginate(25)->withQueryString();

        $summary = [
            'total_requests' => ApiUsageLog::query()->recent()->count(),
            'error_count' => ApiUsageLog::query()->recent()->withErrors()->count(),
            'avg_response_time' => (int) ApiUsageLog::query()->recent()->avg('response_time_ms'),
            'by_service' => ApiUsageLog::query()->recent()
                ->selectRaw('service, COUNT(*) as total, AVG(response_time_ms) as avg_time, SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors')
                ->groupBy('service')
                ->get()
                ->keyBy('service'),
        ];

        $services = ApiUsageLog::query()->distinct()->pluck('service')->sort()->values();

        return view('admin.api-logs.index', compact('logs', 'summary', 'services'));
    }
}
