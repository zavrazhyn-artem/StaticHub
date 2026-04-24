@extends('admin.layouts.app')

@php
    $maxHourly = max(1, ...array_map(fn ($r) => $r['views'], $hourly));
    $maxDaily = max(1, ...array_map(fn ($r) => $r['views'], $daily));
@endphp

@section('content')
    <div style="margin-bottom: 2rem;">
        <h1 style="font-family: 'Space Grotesk', sans-serif; font-size: 1.5rem; font-weight: 700;">User Activity</h1>
        <p style="color: #888; font-size: 0.875rem;">Who uses the app and how — navigation over the last 14 days.</p>
    </div>

    {{-- Totals --}}
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="admin-card" style="padding: 1.25rem;">
            <div class="admin-metric-label">Page views (14d)</div>
            <div class="admin-metric-value">{{ number_format($totals['views_14d']) }}</div>
        </div>
        <div class="admin-card" style="padding: 1.25rem;">
            <div class="admin-metric-label">Unique users (14d)</div>
            <div class="admin-metric-value">{{ number_format($totals['unique_users_14d']) }}</div>
        </div>
        <div class="admin-card" style="padding: 1.25rem;">
            <div class="admin-metric-label">Active statics (14d)</div>
            <div class="admin-metric-value">{{ number_format($totals['active_statics_14d']) }}</div>
        </div>
    </div>

    {{-- Daily chart --}}
    <div class="admin-card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1rem;">
            <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 1rem; font-weight: 600;">Daily page views (14 days)</h2>
            <span style="color: #666; font-size: 0.75rem;">peak {{ number_format($maxDaily) }}</span>
        </div>
        <div style="display: flex; align-items: flex-end; gap: 0.25rem; height: 120px;">
            @foreach($daily as $d)
                @php $pct = (int) round(($d['views'] / $maxDaily) * 100); @endphp
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.25rem;" title="{{ $d['day'] }} — {{ $d['views'] }} views, {{ $d['active_users'] }} users">
                    <div style="flex: 1; width: 100%; display: flex; align-items: flex-end;">
                        <div style="width: 100%; background: rgba(239,68,68,0.7); border-radius: 0.25rem 0.25rem 0 0; height: {{ max(2, $pct) }}%;"></div>
                    </div>
                    <span style="font-size: 0.65rem; color: #666;">{{ \Carbon\Carbon::parse($d['day'])->format('d.m') }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Hourly chart --}}
    <div class="admin-card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1rem;">
            <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 1rem; font-weight: 600;">Hourly activity (last 7 days)</h2>
            <span style="color: #666; font-size: 0.75rem;">peak {{ number_format($maxHourly) }}</span>
        </div>
        <div style="display: flex; align-items: flex-end; gap: 0.15rem; height: 80px;">
            @foreach($hourly as $h)
                @php $pct = (int) round(($h['views'] / $maxHourly) * 100); @endphp
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.25rem;" title="{{ sprintf('%02d:00', $h['hour']) }} — {{ $h['views'] }} views">
                    <div style="flex: 1; width: 100%; display: flex; align-items: flex-end;">
                        <div style="width: 100%; background: rgba(34,211,238,0.7); border-radius: 0.2rem 0.2rem 0 0; height: {{ max(2, $pct) }}%;"></div>
                    </div>
                    <span style="font-size: 0.6rem; color: #666;">{{ sprintf('%02d', $h['hour']) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Top statics + Top users --}}
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
        <div class="admin-card" style="overflow: hidden;">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.08);">
                <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 1rem; font-weight: 600;">Top statics (14d)</h2>
                <p style="color: #888; font-size: 0.75rem; margin-top: 0.25rem;">Ranked by total page views. Active-users counts distinct logged-in members.</p>
            </div>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Static</th>
                        <th style="text-align: right;">Views</th>
                        <th style="text-align: right;">Users</th>
                        <th style="text-align: right;">Last seen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($top_statics as $row)
                        <tr>
                            <td style="color: #e0e0e0; font-weight: 600;">{{ $row->static_name ?? '—' }}</td>
                            <td style="text-align: right; color: #e0e0e0;">{{ number_format($row->views) }}</td>
                            <td style="text-align: right; color: #aaa;">{{ number_format($row->active_users) }}</td>
                            <td style="text-align: right; color: #888; font-size: 0.8rem;">{{ $row->last_seen ? \Carbon\Carbon::parse($row->last_seen)->diffForHumans() : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align: center; color: #666; padding: 2rem;">No activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-card" style="overflow: hidden;">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.08);">
                <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 1rem; font-weight: 600;">Top users (14d)</h2>
                <p style="color: #888; font-size: 0.75rem; margin-top: 0.25rem;">Click a row to drill down into that user's history.</p>
            </div>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Static</th>
                        <th style="text-align: right;">Views</th>
                        <th style="text-align: right;">Last seen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($top_users as $row)
                        <tr style="cursor: pointer;" onclick="window.location='{{ route('admin.user-activity.show', $row->user_id) }}'">
                            <td style="color: #e0e0e0; font-weight: 600;">
                                {{ $row->user_name ?? $row->battletag ?? ('#' . $row->user_id) }}
                                @if($row->battletag && $row->user_name && $row->battletag !== $row->user_name)
                                    <span style="color: #666; font-size: 0.75rem;">· {{ $row->battletag }}</span>
                                @endif
                            </td>
                            <td style="color: #aaa;">{{ $row->static_name ?? '—' }}</td>
                            <td style="text-align: right; color: #e0e0e0;">{{ number_format($row->views) }}</td>
                            <td style="text-align: right; color: #888; font-size: 0.8rem;">{{ $row->last_seen ? \Carbon\Carbon::parse($row->last_seen)->diffForHumans() : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align: center; color: #666; padding: 2rem;">No activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Sleeping statics --}}
    <div class="admin-card" style="overflow: hidden; margin-bottom: 1.5rem;">
        <div style="padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.08);">
            <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 1rem; font-weight: 600;">Sleeping statics</h2>
            <p style="color: #888; font-size: 0.75rem; margin-top: 0.25rem;">Registered but no page views in the last 7 days (or never).</p>
        </div>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Static</th>
                    <th>Registered</th>
                    <th>Last seen</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sleeping as $row)
                    <tr>
                        <td style="color: #e0e0e0; font-weight: 600;">{{ $row->static_name ?? '—' }}</td>
                        <td style="color: #888; font-size: 0.8rem;">{{ $row->static_created_at ? \Carbon\Carbon::parse($row->static_created_at)->format('Y-m-d') : '—' }}</td>
                        <td style="color: #888; font-size: 0.8rem;">
                            @if($row->last_seen)
                                {{ \Carbon\Carbon::parse($row->last_seen)->diffForHumans() }}
                            @else
                                <span class="admin-badge admin-badge-error">never</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="text-align: center; color: #666; padding: 2rem;">Every static had activity in the last 7 days.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Recent feed --}}
    <div class="admin-card" style="overflow: hidden;">
        <div style="padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.08);">
            <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 1rem; font-weight: 600;">Recent activity</h2>
            <p style="color: #888; font-size: 0.75rem; margin-top: 0.25rem;">Latest 50 page views across all users.</p>
        </div>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>User</th>
                    <th>Static</th>
                    <th>Route</th>
                    <th>URL</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recent_feed as $log)
                    <tr>
                        <td style="color: #888; font-size: 0.8rem; white-space: nowrap;">{{ $log->created_at?->diffForHumans() }}</td>
                        <td style="color: #e0e0e0;">
                            @if($log->user)
                                <a href="{{ route('admin.user-activity.show', $log->user_id) }}" style="color: #e0e0e0; text-decoration: none;">
                                    {{ $log->user->name ?? $log->user->battletag ?? ('#' . $log->user_id) }}
                                </a>
                            @else
                                #{{ $log->user_id }}
                            @endif
                        </td>
                        <td style="color: #aaa;">{{ $log->staticGroup?->name ?? '—' }}</td>
                        <td style="color: #aaa; font-family: ui-monospace, monospace; font-size: 0.75rem;">{{ $log->route_name ?? '—' }}</td>
                        <td style="color: #888; font-family: ui-monospace, monospace; font-size: 0.75rem; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $log->url }}">{{ parse_url($log->url, PHP_URL_PATH) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align: center; color: #666; padding: 2rem;">No activity logged yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
