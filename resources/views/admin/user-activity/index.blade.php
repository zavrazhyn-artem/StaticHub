@extends('admin.layouts.app')

@php
    $maxHourlyViews = max(1, ...array_map(fn ($r) => $r['views'], $hourly));
    $maxHourlyUsers = max(1, ...array_map(fn ($r) => $r['active_users'], $hourly));
    $maxDailyViews = max(1, ...array_map(fn ($r) => $r['views'], $daily));
    $maxDailyUsers = max(1, ...array_map(fn ($r) => $r['active_users'], $daily));
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

    {{-- Mode toggle --}}
    <div style="display: flex; justify-content: flex-end; margin-bottom: 0.75rem;">
        <div id="ua-mode-toggle" style="display: inline-flex; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 0.5rem; padding: 0.2rem;">
            <button type="button" data-mode="users" class="ua-mode-btn admin-btn" style="padding: 0.35rem 0.85rem; font-size: 0.8rem; border-radius: 0.35rem;">Unique users</button>
            <button type="button" data-mode="views" class="ua-mode-btn admin-btn" style="padding: 0.35rem 0.85rem; font-size: 0.8rem; border-radius: 0.35rem;">Page views</button>
        </div>
    </div>

    {{-- Daily chart --}}
    <div class="admin-card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1rem;">
            <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 1rem; font-weight: 600;"><span class="ua-chart-title" data-label-users="Daily unique users (14 days)" data-label-views="Daily page views (14 days)">Daily unique users (14 days)</span></h2>
            <span style="color: #666; font-size: 0.75rem;">peak <span class="ua-peak" data-peak-users="{{ $maxDailyUsers }}" data-peak-views="{{ $maxDailyViews }}">{{ number_format($maxDailyUsers) }}</span></span>
        </div>
        <div class="ua-chart" data-chart="daily" style="display: flex; align-items: stretch; gap: 0.25rem; height: 120px;">
            @foreach($daily as $d)
                @php
                    $pctUsers = (int) round(($d['active_users'] / $maxDailyUsers) * 100);
                    $pctViews = (int) round(($d['views'] / $maxDailyViews) * 100);
                @endphp
                <div class="ua-bar-col" style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.25rem;"
                     data-title-users="{{ $d['day'] }} — {{ $d['active_users'] }} users"
                     data-title-views="{{ $d['day'] }} — {{ $d['views'] }} views"
                     title="{{ $d['day'] }} — {{ $d['active_users'] }} users">
                    <div style="flex: 1; width: 100%; display: flex; align-items: flex-end;">
                        <div class="ua-bar"
                             data-pct-users="{{ max(2, $pctUsers) }}"
                             data-pct-views="{{ max(2, $pctViews) }}"
                             style="width: 100%; background: rgba(239,68,68,0.7); border-radius: 0.25rem 0.25rem 0 0; height: {{ max(2, $pctUsers) }}%;"></div>
                    </div>
                    <span style="font-size: 0.65rem; color: #666;">{{ \Carbon\Carbon::parse($d['day'])->format('d.m') }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Hourly chart --}}
    <div class="admin-card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1rem;">
            <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 1rem; font-weight: 600;"><span class="ua-chart-title" data-label-users="Hourly unique users (last 7 days)" data-label-views="Hourly page views (last 7 days)">Hourly unique users (last 7 days)</span></h2>
            <span style="color: #666; font-size: 0.75rem;">peak <span class="ua-peak" data-peak-users="{{ $maxHourlyUsers }}" data-peak-views="{{ $maxHourlyViews }}">{{ number_format($maxHourlyUsers) }}</span></span>
        </div>
        <div class="ua-chart" data-chart="hourly" style="display: flex; align-items: stretch; gap: 0.15rem; height: 80px;">
            @foreach($hourly as $h)
                @php
                    $pctUsers = (int) round(($h['active_users'] / $maxHourlyUsers) * 100);
                    $pctViews = (int) round(($h['views'] / $maxHourlyViews) * 100);
                @endphp
                <div class="ua-bar-col" style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.25rem;"
                     data-title-users="{{ sprintf('%02d:00', $h['hour']) }} — {{ $h['active_users'] }} users"
                     data-title-views="{{ sprintf('%02d:00', $h['hour']) }} — {{ $h['views'] }} views"
                     title="{{ sprintf('%02d:00', $h['hour']) }} — {{ $h['active_users'] }} users">
                    <div style="flex: 1; width: 100%; display: flex; align-items: flex-end;">
                        <div class="ua-bar"
                             data-pct-users="{{ max(2, $pctUsers) }}"
                             data-pct-views="{{ max(2, $pctViews) }}"
                             style="width: 100%; background: rgba(34,211,238,0.7); border-radius: 0.2rem 0.2rem 0 0; height: {{ max(2, $pctUsers) }}%;"></div>
                    </div>
                    <span style="font-size: 0.6rem; color: #666;">{{ sprintf('%02d', $h['hour']) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <style>
        .ua-mode-btn { background: transparent; color: #a0a0a0; border: none; cursor: pointer; transition: all 0.15s; }
        .ua-mode-btn:hover { color: #e0e0e0; }
        .ua-mode-btn.active { background: rgba(239,68,68,0.15); color: #ef4444; }
    </style>

    <script>
        (function () {
            const STORAGE_KEY = 'ua-chart-mode';
            const buttons = document.querySelectorAll('.ua-mode-btn');
            const formatNumber = (n) => Number(n).toLocaleString('en-US');

            function applyMode(mode) {
                buttons.forEach(b => b.classList.toggle('active', b.dataset.mode === mode));
                document.querySelectorAll('.ua-bar').forEach(bar => {
                    bar.style.height = bar.dataset['pct' + (mode === 'users' ? 'Users' : 'Views')] + '%';
                });
                document.querySelectorAll('.ua-bar-col').forEach(col => {
                    col.title = col.dataset['title' + (mode === 'users' ? 'Users' : 'Views')];
                });
                document.querySelectorAll('.ua-chart-title').forEach(el => {
                    el.textContent = el.dataset['label' + (mode === 'users' ? 'Users' : 'Views')];
                });
                document.querySelectorAll('.ua-peak').forEach(el => {
                    el.textContent = formatNumber(el.dataset['peak' + (mode === 'users' ? 'Users' : 'Views')]);
                });
                try { localStorage.setItem(STORAGE_KEY, mode); } catch (e) {}
            }

            buttons.forEach(b => b.addEventListener('click', () => applyMode(b.dataset.mode)));

            let initial = 'users';
            try {
                const stored = localStorage.getItem(STORAGE_KEY);
                if (stored === 'views' || stored === 'users') initial = stored;
            } catch (e) {}
            applyMode(initial);
        })();
    </script>

    {{-- Top statics + Top users --}}
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
        <div class="admin-card" style="overflow: hidden;">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                <div>
                    <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 1rem; font-weight: 600;">Top statics (14d)</h2>
                    <p style="color: #888; font-size: 0.75rem; margin-top: 0.25rem;">Ranked by total page views. Active-users counts distinct logged-in members.</p>
                </div>
                <a href="{{ route('admin.user-activity.statics') }}" class="admin-btn admin-btn-ghost" style="white-space: nowrap; flex-shrink: 0;">
                    View all
                    <span class="material-symbols-outlined" style="font-size: 16px;">arrow_forward</span>
                </a>
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
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                <div>
                    <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 1rem; font-weight: 600;">Top users (14d)</h2>
                    <p style="color: #888; font-size: 0.75rem; margin-top: 0.25rem;">Click a row to drill down into that user's history.</p>
                </div>
                <a href="{{ route('admin.user-activity.users') }}" class="admin-btn admin-btn-ghost" style="white-space: nowrap; flex-shrink: 0;">
                    View all
                    <span class="material-symbols-outlined" style="font-size: 16px;">arrow_forward</span>
                </a>
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
