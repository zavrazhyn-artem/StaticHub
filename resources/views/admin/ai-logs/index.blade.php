@extends('admin.layouts.app')

@section('content')
    <div style="margin-bottom: 2rem;">
        <h1 style="font-family: 'Space Grotesk', sans-serif; font-size: 1.5rem; font-weight: 700;">AI Request Logs</h1>
        <p style="color: #888; font-size: 0.875rem;">Last 7 days summary</p>
    </div>

    {{-- Summary --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <div class="admin-card" style="padding: 1rem;">
            <div class="admin-metric-label">Total Requests</div>
            <div class="admin-metric-value" style="font-size: 1.5rem;">{{ number_format($summary['total_requests']) }}</div>
        </div>
        <div class="admin-card" style="padding: 1rem;">
            <div class="admin-metric-label">Total Tokens</div>
            <div class="admin-metric-value" style="font-size: 1.5rem;">{{ number_format($summary['total_tokens']) }}</div>
        </div>
        <div class="admin-card" style="padding: 1rem;">
            <div class="admin-metric-label">Total Cost</div>
            <div class="admin-metric-value" style="font-size: 1.5rem;">${{ number_format((float) $summary['total_cost'], 4) }}</div>
        </div>
        <div class="admin-card" style="padding: 1rem;">
            <div class="admin-metric-label">Avg Response</div>
            <div class="admin-metric-value" style="font-size: 1.5rem;">{{ number_format($summary['avg_response_time']) }}ms</div>
        </div>
        <div class="admin-card" style="padding: 1rem;">
            <div class="admin-metric-label">Errors</div>
            <div class="admin-metric-value" style="font-size: 1.5rem; color: {{ $summary['error_count'] > 0 ? '#f87171' : '#4ade80' }};">
                {{ $summary['error_count'] }}
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: end;">
        <div>
            <label style="display: block; font-size: 0.75rem; color: #888; margin-bottom: 0.25rem;">Provider</label>
            <select name="provider" class="admin-input" style="min-width: 140px;">
                <option value="">All</option>
                @foreach($providers as $provider)
                    <option value="{{ $provider }}" {{ request('provider') === $provider ? 'selected' : '' }}>{{ ucfirst($provider) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 0.75rem; color: #888; margin-bottom: 0.25rem;">Status</label>
            <select name="status" class="admin-input" style="min-width: 120px;">
                <option value="">All</option>
                <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Success</option>
                <option value="error" {{ request('status') === 'error' ? 'selected' : '' }}>Error</option>
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
        <a href="{{ route('admin.ai-logs') }}" class="admin-btn admin-btn-ghost">Reset</a>
    </form>

    {{-- Table --}}
    <div class="admin-card" style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Provider</th>
                    <th>Model</th>
                    <th>Tokens (in/out)</th>
                    <th>Cost</th>
                    <th>Response</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td style="white-space: nowrap;">{{ $log->created_at->format('M d H:i:s') }}</td>
                        <td>{{ ucfirst($log->provider) }}</td>
                        <td style="font-size: 0.8rem; color: #aaa;">{{ $log->model ?? '-' }}</td>
                        <td>
                            @if($log->input_tokens)
                                {{ number_format($log->input_tokens) }} / {{ number_format($log->output_tokens ?? 0) }}
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $log->cost_estimate ? '$' . number_format((float) $log->cost_estimate, 4) : '-' }}</td>
                        <td>{{ number_format($log->response_time_ms) }}ms</td>
                        <td>
                            <span class="admin-badge {{ $log->status === 'success' ? 'admin-badge-success' : 'admin-badge-error' }}">
                                {{ $log->status }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: #666; padding: 2rem;">No AI request logs found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1rem;">
        {{ $logs->links() }}
    </div>
@endsection
