@extends('admin.layouts.app')

@section('content')
    <div style="margin-bottom: 2rem;">
        <h1 style="font-family: 'Space Grotesk', sans-serif; font-size: 1.5rem; font-weight: 700;">API Usage Logs</h1>
        <p style="color: #888; font-size: 0.875rem;">Last 7 days summary</p>
    </div>

    {{-- Summary --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="admin-card" style="padding: 1rem;">
            <div class="admin-metric-label">Total Requests</div>
            <div class="admin-metric-value" style="font-size: 1.5rem;">{{ number_format($summary['total_requests']) }}</div>
        </div>
        <div class="admin-card" style="padding: 1rem;">
            <div class="admin-metric-label">Errors</div>
            <div class="admin-metric-value" style="font-size: 1.5rem; color: {{ $summary['error_count'] > 0 ? '#f87171' : '#4ade80' }};">
                {{ number_format($summary['error_count']) }}
            </div>
        </div>
        <div class="admin-card" style="padding: 1rem;">
            <div class="admin-metric-label">Avg Response</div>
            <div class="admin-metric-value" style="font-size: 1.5rem;">{{ number_format($summary['avg_response_time']) }}ms</div>
        </div>
    </div>

    {{-- Per-service breakdown --}}
    @if($summary['by_service']->isNotEmpty())
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            @foreach($summary['by_service'] as $service => $stats)
                <div class="admin-card" style="padding: 1rem;">
                    <div class="admin-metric-label">{{ ucfirst($service) }}</div>
                    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-top: 0.5rem;">
                        <span style="font-family: 'Space Grotesk', sans-serif; font-weight: 600; font-size: 1.25rem;">{{ number_format($stats->total) }}</span>
                        <span style="font-size: 0.8rem; color: #888;">{{ number_format((float)$stats->avg_time) }}ms avg</span>
                    </div>
                    @if($stats->errors > 0)
                        <div style="font-size: 0.8rem; color: #f87171; margin-top: 0.25rem;">{{ $stats->errors }} errors</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: end;">
        <div>
            <label style="display: block; font-size: 0.75rem; color: #888; margin-bottom: 0.25rem;">Service</label>
            <select name="service" class="admin-input" style="min-width: 140px;">
                <option value="">All</option>
                @foreach($services as $service)
                    <option value="{{ $service }}" {{ request('service') === $service ? 'selected' : '' }}>{{ ucfirst($service) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 0.75rem; color: #888; margin-bottom: 0.25rem;">Errors Only</label>
            <select name="errors_only" class="admin-input" style="min-width: 100px;">
                <option value="">No</option>
                <option value="1" {{ request('errors_only') ? 'selected' : '' }}>Yes</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 0.75rem; color: #888; margin-bottom: 0.25rem;">From</label>
            <input type="date" name="date_from" class="admin-input" value="{{ request('date_from') }}">
        </div>
        <div>
            <label style="display: block; font-size: 0.75rem; color: #888; margin-bottom: 0.25rem;">To</label>
            <input type="date" name="date_to" class="admin-input" value="{{ request('date_to') }}">
        </div>
        <button type="submit" class="admin-btn admin-btn-primary">Filter</button>
        <a href="{{ route('admin.api-logs') }}" class="admin-btn admin-btn-ghost">Reset</a>
    </form>

    {{-- Table --}}
    <div class="admin-card" style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Service</th>
                    <th>Endpoint</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Response</th>
                    <th>Rate Limit</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td style="white-space: nowrap;">{{ $log->created_at->format('M d H:i:s') }}</td>
                        <td>{{ ucfirst($log->service) }}</td>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.8rem; color: #aaa;">
                            {{ $log->endpoint }}
                        </td>
                        <td>
                            <span class="admin-badge admin-badge-neutral">{{ $log->method }}</span>
                        </td>
                        <td>
                            <span class="admin-badge {{ $log->status_code < 400 ? 'admin-badge-success' : 'admin-badge-error' }}">
                                {{ $log->status_code }}
                            </span>
                        </td>
                        <td>{{ number_format($log->response_time_ms) }}ms</td>
                        <td>
                            @if($log->rate_limit_remaining !== null)
                                <span style="font-size: 0.8rem; {{ $log->rate_limit_remaining < 10 ? 'color: #f87171;' : 'color: #aaa;' }}">
                                    {{ $log->rate_limit_remaining }}/{{ $log->rate_limit_limit ?? '?' }}
                                </span>
                            @else
                                <span style="color: #555;">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: #666; padding: 2rem;">No API usage logs found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1rem;">
        {{ $logs->links() }}
    </div>
@endsection
