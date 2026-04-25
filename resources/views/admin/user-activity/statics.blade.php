@extends('admin.layouts.app')

@php
    $sortLink = function (string $column, string $label) use ($filters) {
        $nextDir = ($filters['sort'] === $column && $filters['dir'] === 'desc') ? 'asc' : 'desc';
        $arrow = $filters['sort'] === $column ? ($filters['dir'] === 'asc' ? '▲' : '▼') : '';
        $url = route('admin.user-activity.statics', array_filter([
            'q' => $filters['q'] !== '' ? $filters['q'] : null,
            'period' => $filters['period'],
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
        <h1 style="font-family: 'Space Grotesk', sans-serif; font-size: 1.5rem; font-weight: 700;">Top statics</h1>
        <p style="color: #888; font-size: 0.875rem;">Ranked by total page views over the selected period.</p>
    </div>

    {{-- Period switcher + search --}}
    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.5rem; align-items: center;">
        <div style="display: inline-flex; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 0.5rem; padding: 0.2rem;">
            @foreach($periods as $p)
                @php
                    $url = route('admin.user-activity.statics', array_filter([
                        'q' => $filters['q'] !== '' ? $filters['q'] : null,
                        'period' => $p,
                        'sort' => $filters['sort'],
                        'dir' => $filters['dir'],
                    ]));
                @endphp
                <a href="{{ $url }}"
                   style="padding: 0.35rem 0.85rem; font-size: 0.8rem; border-radius: 0.35rem; text-decoration: none; transition: all 0.15s; {{ $filters['period'] === $p ? 'background: rgba(239,68,68,0.15); color: #ef4444;' : 'color: #a0a0a0;' }}">
                    {{ $p }}d
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.user-activity.statics') }}" style="display: flex; gap: 0.5rem; align-items: center; flex: 1; max-width: 500px;">
            <div style="position: relative; flex: 1;">
                <span class="material-symbols-outlined" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #666; font-size: 18px;">search</span>
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search by static name…"
                       class="admin-input" style="width: 100%; padding-left: 2.25rem;">
            </div>
            <input type="hidden" name="period" value="{{ $filters['period'] }}">
            <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
            <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
            <button type="submit" class="admin-btn admin-btn-primary">
                <span class="material-symbols-outlined" style="font-size: 18px;">search</span>
                Search
            </button>
            @if($filters['q'] !== '')
                <a href="{{ route('admin.user-activity.statics', ['period' => $filters['period'], 'sort' => $filters['sort'], 'dir' => $filters['dir']]) }}" class="admin-btn admin-btn-ghost">Clear</a>
            @endif
        </form>
    </div>

    {{-- Table --}}
    <div class="admin-card" style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    @foreach([
                        'name' => 'Static',
                        'views' => 'Views',
                        'active_users' => 'Users',
                        'last_seen' => 'Last seen',
                    ] as $col => $label)
                        @php $link = $sortLink($col, $label); @endphp
                        <th style="{{ in_array($col, ['views', 'active_users', 'last_seen'], true) ? 'text-align: right;' : '' }}">
                            <a href="{{ $link['url'] }}" style="display: inline-flex; gap: 0.4rem; align-items: center; color: {{ $link['active'] ? '#ef4444' : '#888' }}; text-decoration: none;">
                                {{ $link['label'] }}
                                @if($link['arrow'])
                                    <span style="font-size: 0.6rem;">{{ $link['arrow'] }}</span>
                                @endif
                            </a>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td style="color: #e0e0e0; font-weight: 600;">{{ $row->static_name ?? '—' }}</td>
                        <td style="text-align: right; color: #e0e0e0;">{{ number_format((int) $row->views) }}</td>
                        <td style="text-align: right; color: #aaa;">{{ number_format((int) $row->active_users) }}</td>
                        <td style="text-align: right; color: #888; font-size: 0.8rem;">{{ $row->last_seen ? \Carbon\Carbon::parse($row->last_seen)->diffForHumans() : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align: center; color: #666; padding: 2rem;">No activity in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($rows->hasPages())
        <div style="margin-top: 1.5rem;">
            {{ $rows->links() }}
        </div>
    @endif
@endsection
