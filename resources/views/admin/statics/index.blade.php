@extends('admin.layouts.app')

@php
    $sortLink = function (string $column, string $label) use ($sort, $direction, $search) {
        $nextDir = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
        $arrow = $sort === $column ? ($direction === 'asc' ? '▲' : '▼') : '';
        $url = route('admin.statics.index', array_filter([
            'q' => $search !== '' ? $search : null,
            'sort' => $column,
            'dir' => $nextDir,
        ]));
        return [
            'url' => $url,
            'label' => $label,
            'arrow' => $arrow,
            'active' => $sort === $column,
        ];
    };
@endphp

@section('content')
    <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
        <div>
            <h1 style="font-family: 'Space Grotesk', sans-serif; font-size: 1.5rem; font-weight: 700;">Statics</h1>
            <p style="color: #888; font-size: 0.875rem;">Browse every static group. Click "Enter as Ghost" to view one read-only.</p>
        </div>
        <div style="color: #888; font-size: 0.875rem;">
            Total: <strong style="color: #e0e0e0;">{{ $statics->total() }}</strong>
        </div>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.statics.index') }}" style="margin-bottom: 1.5rem; display: flex; gap: 0.75rem; align-items: center; max-width: 500px;">
        <div style="position: relative; flex: 1;">
            <span class="material-symbols-outlined" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #666; font-size: 18px;">search</span>
            <input type="text" name="q" value="{{ $search }}" placeholder="Search by static name or owner…"
                   class="admin-input" style="width: 100%; padding-left: 2.25rem;">
        </div>
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="dir" value="{{ $direction }}">
        <button type="submit" class="admin-btn admin-btn-primary">
            <span class="material-symbols-outlined" style="font-size: 18px;">search</span>
            Search
        </button>
        @if($search !== '')
            <a href="{{ route('admin.statics.index', ['sort' => $sort, 'dir' => $direction]) }}" class="admin-btn admin-btn-ghost">Clear</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="admin-card" style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    @foreach([
                        'name' => 'Name',
                        'owner_name' => 'Owner',
                        'members_count' => 'Members',
                        'region' => 'Region',
                        'created_at' => 'Created',
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
                    <th style="text-align: end;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($statics as $static)
                    <tr>
                        <td style="color: #e0e0e0; font-weight: 600;">{{ $static->name }}</td>
                        <td style="color: #aaa;">{{ $static->owner?->name ?? '—' }}</td>
                        <td style="color: #aaa;">{{ $static->members_count }}</td>
                        <td style="color: #aaa; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">{{ $static->region }}</td>
                        <td style="color: #888; font-size: 0.8rem;">{{ $static->created_at?->format('Y-m-d') ?? '—' }}</td>
                        <td style="text-align: end;">
                            <form action="{{ route('admin.ghost.enter', ['static' => $static->id]) }}" method="POST" style="display: inline;">
                                @csrf
                                <button type="submit" class="admin-btn"
                                        style="background: rgba(6,182,212,0.1); border: 1px solid rgba(6,182,212,0.5); color: #22d3ee; font-size: 0.75rem;"
                                        onmouseover="this.style.background='rgba(6,182,212,0.2)'" onmouseout="this.style.background='rgba(6,182,212,0.1)'">
                                    <span class="material-symbols-outlined" style="font-size: 16px;">visibility</span>
                                    Enter as Ghost
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: #666; padding: 2rem;">
                            No statics match your search.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($statics->hasPages())
        <div style="margin-top: 1.5rem;">
            {{ $statics->links() }}
        </div>
    @endif
@endsection
