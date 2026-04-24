@extends('admin.layouts.app')

@php
    $sortLink = function (string $column, string $label) use ($filters, $subject) {
        $nextDir = ($filters['sort'] === $column && $filters['dir'] === 'asc') ? 'desc' : 'asc';
        $arrow = $filters['sort'] === $column ? ($filters['dir'] === 'asc' ? '▲' : '▼') : '';
        $url = route('admin.user-activity.show', array_filter([
            'user' => $subject->id,
            'q' => $filters['q'] !== '' ? $filters['q'] : null,
            'sort' => $column,
            'dir' => $nextDir,
        ]));
        return [
            'url' => $url,
            'label' => $label,
            'arrow' => $arrow,
            'active' => $filters['sort'] === $column,
        ];
    };
@endphp

@section('content')
    <div style="margin-bottom: 1.5rem;">
        <a href="{{ route('admin.user-activity') }}" style="color: #888; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
            <span class="material-symbols-outlined" style="font-size: 16px;">arrow_back</span>
            Back to User Activity
        </a>
    </div>

    <div style="margin-bottom: 2rem;">
        <h1 style="font-family: 'Space Grotesk', sans-serif; font-size: 1.5rem; font-weight: 700;">
            {{ $subject->name ?? $subject->battletag ?? ('User #' . $subject->id) }}
        </h1>
        @if($subject->battletag && $subject->name && $subject->battletag !== $subject->name)
            <p style="color: #888; font-size: 0.875rem;">{{ $subject->battletag }}</p>
        @endif
    </div>

    {{-- Summary --}}
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="admin-card" style="padding: 1.25rem;">
            <div class="admin-metric-label">Page views (14d)</div>
            <div class="admin-metric-value">{{ number_format($summary['total_views_14d']) }}</div>
        </div>
        <div class="admin-card" style="padding: 1.25rem;">
            <div class="admin-metric-label">First seen (14d)</div>
            <div class="admin-metric-value" style="font-size: 1rem;">{{ $summary['first_seen']?->diffForHumans() ?? '—' }}</div>
        </div>
        <div class="admin-card" style="padding: 1.25rem;">
            <div class="admin-metric-label">Last seen (14d)</div>
            <div class="admin-metric-value" style="font-size: 1rem;">{{ $summary['last_seen']?->diffForHumans() ?? '—' }}</div>
        </div>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.user-activity.show', $subject->id) }}" style="margin-bottom: 1.5rem; display: flex; gap: 0.75rem; align-items: center; max-width: 500px;">
        <div style="position: relative; flex: 1;">
            <span class="material-symbols-outlined" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #666; font-size: 18px;">search</span>
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search in route or URL…"
                   class="admin-input" style="width: 100%; padding-left: 2.25rem;">
        </div>
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
        <button type="submit" class="admin-btn admin-btn-primary">
            <span class="material-symbols-outlined" style="font-size: 18px;">search</span>
            Search
        </button>
        @if($filters['q'] !== '')
            <a href="{{ route('admin.user-activity.show', ['user' => $subject->id, 'sort' => $filters['sort'], 'dir' => $filters['dir']]) }}" class="admin-btn admin-btn-ghost">Clear</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="admin-card" style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    @foreach([
                        'created_at' => 'When',
                        'route_name' => 'Route',
                        'url' => 'URL',
                        'method' => 'Method',
                    ] as $col => $label)
                        @php $link = $sortLink($col, $label); @endphp
                        <th>
                            <a href="{{ $link['url'] }}" style="display: inline-flex; gap: 0.4rem; align-items: center; color: {{ $link['active'] ? '#ef4444' : '#888' }}; text-decoration: none;">
                                {{ $link['label'] }}
                                @if($link['arrow'])
                                    <span style="font-size: 0.6rem;">{{ $link['arrow'] }}</span>
                                @endif
                            </a>
                        </th>
                    @endforeach
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td style="color: #888; font-size: 0.8rem; white-space: nowrap;" title="{{ $log->created_at }}">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td style="color: #aaa; font-family: ui-monospace, monospace; font-size: 0.75rem;">{{ $log->route_name ?? '—' }}</td>
                        <td style="color: #888; font-family: ui-monospace, monospace; font-size: 0.75rem; max-width: 380px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $log->url }}">{{ parse_url($log->url, PHP_URL_PATH) }}</td>
                        <td style="color: #aaa; font-size: 0.75rem;">{{ $log->method }}</td>
                        <td style="color: #666; font-family: ui-monospace, monospace; font-size: 0.75rem;">{{ $log->ip ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align: center; color: #666; padding: 2rem;">No activity for this user.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div style="margin-top: 1.5rem;">
            {{ $logs->links() }}
        </div>
    @endif
@endsection
