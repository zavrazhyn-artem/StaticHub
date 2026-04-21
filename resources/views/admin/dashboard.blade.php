@extends('admin.layouts.app')

@section('content')
    <div style="margin-bottom: 2rem;">
        <h1 style="font-family: 'Space Grotesk', sans-serif; font-size: 1.5rem; font-weight: 700;">Dashboard</h1>
        <p style="color: #888; font-size: 0.875rem;">System overview and quick access to tools</p>
    </div>

    {{-- TEMP DEBUG: remove after verifying ghost-mode activation --}}
    @php
        $ghost = app(\App\Services\Ghost\GhostModeService::class);
        $ghostUid = config('ghost.user_id');
    @endphp
    <div class="admin-card" style="padding: 1rem; margin-bottom: 1.5rem; font-family: monospace; font-size: 0.8rem;">
        <div style="color: #fbbf24; margin-bottom: 0.5rem; font-weight: 700;">Ghost activation debug</div>
        <div>config('ghost.user_id') = <strong>{{ var_export($ghostUid, true) }}</strong></div>
        <div>auth()->check() = <strong>{{ var_export(auth()->check(), true) }}</strong></div>
        <div>auth()->id() = <strong>{{ var_export(auth()->id(), true) }}</strong></div>
        <div>session('admin_authenticated') = <strong>{{ var_export(session('admin_authenticated'), true) }}</strong></div>
        <div>(int)auth id === (int)ghost id : <strong>{{ var_export((int) auth()->id() === (int) $ghostUid, true) }}</strong></div>
        <div>canActivate() = <strong style="color: {{ $ghost->canActivate() ? '#4ade80' : '#f87171' }};">{{ var_export($ghost->canActivate(), true) }}</strong></div>
        <div>request scheme = <strong>{{ request()->getScheme() }}</strong></div>
        <div>request host = <strong>{{ request()->getHost() }}</strong></div>
    </div>

    {{-- Metrics Grid --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <div class="admin-card" style="padding: 1.25rem;">
            <div class="admin-metric-label">Users</div>
            <div class="admin-metric-value">{{ number_format($metrics['users']) }}</div>
        </div>
        <div class="admin-card" style="padding: 1.25rem;">
            <div class="admin-metric-label">Static Groups</div>
            <div class="admin-metric-value">{{ number_format($metrics['statics']) }}</div>
        </div>
        <div class="admin-card" style="padding: 1.25rem;">
            <div class="admin-metric-label">Characters</div>
            <div class="admin-metric-value">{{ number_format($metrics['characters']) }}</div>
        </div>
        <div class="admin-card" style="padding: 1.25rem;">
            <div class="admin-metric-label">Events</div>
            <div class="admin-metric-value">{{ number_format($metrics['events']) }}</div>
        </div>
        <div class="admin-card" style="padding: 1.25rem;">
            <div class="admin-metric-label">Available Invites</div>
            <div class="admin-metric-value admin-primary">{{ number_format($metrics['invite_codes_available']) }}</div>
        </div>
        <div class="admin-card" style="padding: 1.25rem;">
            <div class="admin-metric-label">AI Requests Today</div>
            <div class="admin-metric-value">{{ number_format($metrics['ai_requests_today']) }}</div>
        </div>
        <div class="admin-card" style="padding: 1.25rem;">
            <div class="admin-metric-label">API Requests Today</div>
            <div class="admin-metric-value">{{ number_format($metrics['api_requests_today']) }}</div>
        </div>
        <div class="admin-card" style="padding: 1.25rem;">
            <div class="admin-metric-label">API Errors Today</div>
            <div class="admin-metric-value" style="color: {{ $metrics['api_errors_today'] > 0 ? '#f87171' : '#4ade80' }};">
                {{ number_format($metrics['api_errors_today']) }}
            </div>
        </div>
    </div>

    {{-- External Tools --}}
    <div style="margin-bottom: 1rem;">
        <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 1.125rem; font-weight: 600;">External Tools</h2>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
        @foreach($toolLinks as $tool)
            <a href="{{ $tool['url'] }}" target="_blank"
               class="admin-card" style="padding: 1.25rem; text-decoration: none; display: flex; align-items: center; gap: 1rem; transition: border-color 0.15s;"
               onmouseover="this.style.borderColor='rgba(239,68,68,0.3)'" onmouseout="this.style.borderColor='rgba(255,255,255,0.08)'">
                <span class="material-symbols-outlined admin-primary" style="font-size: 28px;">
                    @if(str_contains($tool['name'], 'Pulse'))monitor_heart
                    @elseif(str_contains($tool['name'], 'Horizon'))queue
                    @else description
                    @endif
                </span>
                <div>
                    <div style="font-weight: 600; color: #e0e0e0;">{{ $tool['name'] }}</div>
                    <div style="font-size: 0.8rem; color: #888;">{{ $tool['description'] }}</div>
                </div>
                <span class="material-symbols-outlined" style="margin-left: auto; font-size: 18px; color: #555;">open_in_new</span>
            </a>
        @endforeach
    </div>
@endsection
