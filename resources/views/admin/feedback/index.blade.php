@extends('admin.layouts.app')

@section('content')
    <div style="margin-bottom: 2rem;">
        <h1 style="font-family: 'Space Grotesk', sans-serif; font-size: 1.5rem; font-weight: 700;">Report Feedback</h1>
        <p style="color: #888; font-size: 0.875rem;">Quality signal from raid leaders + members</p>
    </div>

    {{-- Headline metrics --}}
    @php
        $allTime = $data['all_time'];
        $last30  = $data['last_30_days'];
        $tagsPos = $data['tags_positive'];
        $tagsNeg = $data['tags_negative'];
        $tagLabels = $data['tag_labels'];

        $renderRating = function ($value) {
            if ($value === null) return '<span style="color:#666;">—</span>';
            $color = $value >= 4 ? '#4ade80' : ($value >= 3 ? '#fbbf24' : '#f87171');
            return sprintf('<span style="color:%s;">%s ★</span>', $color, number_format((float) $value, 2));
        };
    @endphp

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <div class="admin-card" style="padding: 1rem;">
            <div class="admin-metric-label">Avg Report (30d)</div>
            <div class="admin-metric-value" style="font-size: 1.5rem;">{!! $renderRating($last30['avg_report'] ?: null) !!}</div>
            <div style="color:#666; font-size:0.75rem; margin-top:0.25rem;">{{ $last30['count'] }} feedbacks</div>
        </div>
        <div class="admin-card" style="padding: 1rem;">
            <div class="admin-metric-label">Avg Chat (30d)</div>
            <div class="admin-metric-value" style="font-size: 1.5rem;">{!! $renderRating($last30['avg_chat']) !!}</div>
        </div>
        <div class="admin-card" style="padding: 1rem;">
            <div class="admin-metric-label">Avg Report (all time)</div>
            <div class="admin-metric-value" style="font-size: 1.5rem;">{!! $renderRating($allTime['avg_report'] ?: null) !!}</div>
            <div style="color:#666; font-size:0.75rem; margin-top:0.25rem;">{{ $allTime['count'] }} feedbacks total</div>
        </div>
        <div class="admin-card" style="padding: 1rem;">
            <div class="admin-metric-label">Avg Chat (all time)</div>
            <div class="admin-metric-value" style="font-size: 1.5rem;">{!! $renderRating($allTime['avg_chat']) !!}</div>
        </div>
    </div>

    {{-- Per-version comparison --}}
    <div class="admin-card" style="padding: 1.25rem; margin-bottom: 2rem;">
        <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 1rem; margin-bottom: 0.25rem;">By Prompt Version</h2>
        <p style="color:#666; font-size:0.75rem; margin-bottom: 1rem;">
            Current: <span style="color:#4ade80; font-family: monospace;">{{ $data['current_version'] }}</span>
            — bump <code style="background:rgba(255,255,255,0.05); padding: 0.1rem 0.3rem; border-radius: 3px;">config/ai_report.php</code> before each prompt change.
        </p>
        @if (empty($data['by_version']))
            <p style="color:#666; font-size:0.875rem;">No feedback recorded yet.</p>
        @else
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>Count</th>
                        <th>Avg Report</th>
                        <th>Avg Chat</th>
                        <th>Top Liked</th>
                        <th>Top Disliked</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['by_version'] as $v)
                        <tr>
                            <td>
                                <span style="font-family: monospace; padding: 0.15rem 0.4rem; background: {{ $v['version'] === $data['current_version'] ? 'rgba(74,222,128,0.15)' : 'rgba(255,255,255,0.05)' }}; color: {{ $v['version'] === $data['current_version'] ? '#4ade80' : '#ddd' }}; border-radius: 4px;">
                                    {{ $v['version'] }}
                                </span>
                            </td>
                            <td>{{ $v['count'] }}</td>
                            <td>{!! $renderRating($v['avg_report']) !!}</td>
                            <td>{!! $renderRating($v['avg_chat']) !!}</td>
                            <td>
                                @foreach (array_slice($v['liked'], 0, 3, true) as $slug => $cnt)
                                    <span style="display:inline-block; padding:0.1rem 0.4rem; margin:0.1rem; background:rgba(74,222,128,0.1); color:#4ade80; border-radius:3px; font-size:0.7rem;">
                                        {{ $tagLabels[$slug] ?? $slug }} · {{ $cnt }}
                                    </span>
                                @endforeach
                            </td>
                            <td>
                                @foreach (array_slice($v['disliked'], 0, 3, true) as $slug => $cnt)
                                    <span style="display:inline-block; padding:0.1rem 0.4rem; margin:0.1rem; background:rgba(248,113,113,0.1); color:#f87171; border-radius:3px; font-size:0.7rem;">
                                        {{ $tagLabels[$slug] ?? $slug }} · {{ $cnt }}
                                    </span>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Weekly trend --}}
    <div class="admin-card" style="padding: 1.25rem; margin-bottom: 2rem;">
        <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 1rem; margin-bottom: 1rem;">Weekly Trend</h2>
        @if (count($data['weekly']) === 0)
            <p style="color:#666; font-size:0.875rem;">No feedback in the last 12 weeks yet.</p>
        @else
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Week</th>
                        <th>Count</th>
                        <th>Avg Report</th>
                        <th>Avg Chat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['weekly'] as $w)
                        <tr>
                            <td>{{ $w['week_start'] }}</td>
                            <td>{{ $w['count'] }}</td>
                            <td>{!! $renderRating($w['avg_report']) !!}</td>
                            <td>{!! $renderRating($w['avg_chat']) !!}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Tag breakdown — split by sentiment --}}
    @php
        $renderTagBars = function ($tags, $labels, $color) {
            if (empty($tags)) {
                echo '<p style="color:#666; font-size:0.875rem;">No data.</p>';
                return;
            }
            $max = max($tags);
            foreach ($tags as $slug => $count) {
                $label = $labels[$slug] ?? $slug;
                $width = $max > 0 ? ($count / $max) * 100 : 0;
                echo '<div style="margin-bottom: 0.5rem;">';
                echo '<div style="display:flex; justify-content:space-between; font-size:0.75rem; margin-bottom:0.25rem;">';
                echo '<span style="color:#ddd;">' . e($label) . '</span>';
                echo '<span style="color:#888;">' . $count . '</span>';
                echo '</div>';
                echo '<div style="height:6px; background:rgba(255,255,255,0.05); border-radius:3px; overflow:hidden;">';
                echo '<div style="height:100%; width:' . number_format($width, 1) . '%; background:' . $color . ';"></div>';
                echo '</div>';
                echo '</div>';
            }
        };
    @endphp

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
        <div class="admin-card" style="padding: 1.25rem;">
            <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 1rem; margin-bottom: 0.25rem;">
                <span style="color:#4ade80;">Positive feedback (rating ≥ 4, last 30d)</span>
            </h2>
            <p style="color:#666; font-size:0.75rem; margin-bottom: 1rem;">What satisfied users mention — keep doing this</p>

            <h3 style="font-size:0.75rem; color:#888; text-transform:uppercase; letter-spacing:0.05em; margin-bottom: 0.5rem;">Liked tags</h3>
            @php $renderTagBars($tagsPos['liked'] ?? [], $tagLabels, '#4ade80'); @endphp

            @if (!empty($tagsPos['disliked']))
                <h3 style="font-size:0.75rem; color:#888; text-transform:uppercase; letter-spacing:0.05em; margin: 1rem 0 0.5rem;">Disliked tags from satisfied users (mixed signal)</h3>
                @php $renderTagBars($tagsPos['disliked'], $tagLabels, '#fbbf24'); @endphp
            @endif
        </div>

        <div class="admin-card" style="padding: 1.25rem;">
            <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 1rem; margin-bottom: 0.25rem;">
                <span style="color:#f87171;">Negative feedback (rating ≤ 3, last 30d)</span>
            </h2>
            <p style="color:#666; font-size:0.75rem; margin-bottom: 1rem;">What dissatisfied users mention — fix this first</p>

            <h3 style="font-size:0.75rem; color:#888; text-transform:uppercase; letter-spacing:0.05em; margin-bottom: 0.5rem;">Disliked tags</h3>
            @php $renderTagBars($tagsNeg['disliked'] ?? [], $tagLabels, '#f87171'); @endphp

            @if (!empty($tagsNeg['liked']))
                <h3 style="font-size:0.75rem; color:#888; text-transform:uppercase; letter-spacing:0.05em; margin: 1rem 0 0.5rem;">Liked tags from dissatisfied users (what worked even when overall failed)</h3>
                @php $renderTagBars($tagsNeg['liked'], $tagLabels, '#4ade80'); @endphp
            @endif
        </div>
    </div>

    {{-- Recent critical feedback --}}
    <div class="admin-card" style="padding: 1.25rem;">
        <h2 style="font-family: 'Space Grotesk', sans-serif; font-size: 1rem; margin-bottom: 0.25rem;">
            Recent critical feedback (rating ≤ 3)
        </h2>
        <p style="color:#666; font-size:0.75rem; margin-bottom: 1rem;">Most recent 20 — drill down to investigate</p>

        @if ($data['critical_recent']->isEmpty())
            <p style="color:#666; font-size:0.875rem;">No critical feedback recently. 🎉</p>
        @else
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Rating</th>
                        <th>Chat</th>
                        <th>User</th>
                        <th>Report</th>
                        <th>Tags</th>
                        <th>Comment</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['critical_recent'] as $fb)
                        <tr>
                            <td style="white-space:nowrap;">{{ $fb->created_at?->diffForHumans() }}</td>
                            <td>{!! $renderRating($fb->report_rating) !!}</td>
                            <td>{!! $renderRating($fb->chat_rating) !!}</td>
                            <td style="color:#ddd;">{{ $fb->user?->name ?? '—' }}</td>
                            <td>
                                @if ($fb->tacticalReport)
                                    <a href="{{ env('APP_URL') }}/logs/{{ $fb->tacticalReport->id }}" target="_blank" style="color:#60a5fa; text-decoration:none;">
                                        {{ Str::limit($fb->tacticalReport->title ?? $fb->tacticalReport->wcl_report_id, 40) }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @foreach ((array) $fb->disliked_tags as $tag)
                                    <span style="display:inline-block; padding:0.15rem 0.5rem; margin:0.1rem; background:rgba(248,113,113,0.1); color:#f87171; border-radius:3px; font-size:0.7rem;">{{ $tagLabels[$tag] ?? $tag }}</span>
                                @endforeach
                            </td>
                            <td style="color:#ccc; font-size:0.825rem; max-width: 300px;">
                                {{ $fb->comment ? Str::limit($fb->comment, 200) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
