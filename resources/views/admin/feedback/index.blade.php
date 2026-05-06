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
                        <th>Static</th>
                        <th>Report</th>
                        <th>Tags</th>
                        <th>Comment</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['critical_recent'] as $fb)
                        @php
                            $report = $fb->tacticalReport;
                            $static = $report?->staticGroup;
                            $reportLabel = $report ? ($report->title ?? $report->wcl_report_id) : null;
                            $hasLongComment = $fb->comment && mb_strlen($fb->comment) > 200;
                        @endphp
                        <tr>
                            <td style="white-space:nowrap;">{{ $fb->created_at?->diffForHumans() }}</td>
                            <td>{!! $renderRating($fb->report_rating) !!}</td>
                            <td>{!! $renderRating($fb->chat_rating) !!}</td>
                            <td style="white-space:nowrap;">
                                @php $char = $fb->viewer_character; @endphp
                                @if ($char)
                                    <div style="color: {{ \App\Helpers\WowClassHelper::hex($char->playable_class) }}; font-weight:500;">
                                        {{ $char->name }}
                                    </div>
                                    <div style="color:#888; font-size:0.7rem; margin-top:0.1rem;">{{ $fb->user?->name ?? '—' }}</div>
                                @else
                                    <div style="color:#ddd;">{{ $fb->user?->name ?? '—' }}</div>
                                @endif
                            </td>
                            <td style="color:#ddd; white-space:nowrap;">{{ $static?->name ?? '—' }}</td>
                            <td>
                                @if ($report && $static)
                                    {{-- Ghost-mode jump: activates ghost for this report's static and
                                         opens the log in a new tab. Posted because it mutates session. --}}
                                    <form method="POST" action="{{ route('admin.ghost.enter-report', ['static' => $static->id, 'report' => $report->id]) }}" target="_blank" style="display:inline; margin:0;">
                                        @csrf
                                        <button type="submit" style="background:none; border:none; padding:0; color:#60a5fa; cursor:pointer; text-decoration:none; font: inherit;">
                                            {{ Str::limit($reportLabel, 40) }}
                                            <span class="material-symbols-outlined" style="font-size:14px; vertical-align:-2px; opacity:0.7;">open_in_new</span>
                                        </button>
                                    </form>
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
                                @if (!$fb->comment)
                                    —
                                @else
                                    {{ Str::limit($fb->comment, 200) }}
                                    @if ($hasLongComment)
                                        @php
                                            $metaParts = array_filter([
                                                $char?->name,
                                                $fb->user?->name,
                                                $static?->name,
                                                $fb->created_at?->toDayDateTimeString(),
                                            ]);
                                        @endphp
                                        <button type="button"
                                                class="js-show-feedback-comment"
                                                data-comment="{{ $fb->comment }}"
                                                data-meta="{{ implode(' · ', $metaParts) }}"
                                                style="display:inline-block; margin-left:0.25rem; background:none; border:none; padding:0; color:#60a5fa; cursor:pointer; font: inherit; text-decoration:underline;">
                                            Read full
                                        </button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Full-comment modal: cells truncate to 200 chars; clicking
                 "Read full" populates and shows this overlay. One shared modal
                 keeps the table light and avoids per-row hidden DOM. --}}
            <div id="feedback-comment-modal"
                 style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:50; align-items:center; justify-content:center; padding:2rem;">
                <div style="background:#181820; border:1px solid rgba(255,255,255,0.1); border-radius:0.75rem; max-width:720px; width:100%; max-height:80vh; display:flex; flex-direction:column;">
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:1rem 1.25rem; border-bottom:1px solid rgba(255,255,255,0.06);">
                        <div>
                            <div style="font-family: 'Space Grotesk', sans-serif; font-size: 1rem; color:#fff;">Feedback comment</div>
                            <div id="feedback-comment-modal-meta" style="font-size:0.75rem; color:#888; margin-top:0.15rem;"></div>
                        </div>
                        <button type="button" id="feedback-comment-modal-close" class="admin-btn admin-btn-ghost" style="padding:0.35rem 0.6rem;">
                            <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                        </button>
                    </div>
                    <div id="feedback-comment-modal-body" style="padding:1.25rem; overflow-y:auto; white-space:pre-wrap; color:#ddd; font-size:0.9rem; line-height:1.55;"></div>
                </div>
            </div>

            <script>
                (function () {
                    const modal = document.getElementById('feedback-comment-modal');
                    const body = document.getElementById('feedback-comment-modal-body');
                    const meta = document.getElementById('feedback-comment-modal-meta');
                    const close = document.getElementById('feedback-comment-modal-close');

                    function openModal(comment, metaText) {
                        body.textContent = comment;
                        meta.textContent = metaText || '';
                        modal.style.display = 'flex';
                    }
                    function closeModal() {
                        modal.style.display = 'none';
                        body.textContent = '';
                    }

                    document.querySelectorAll('.js-show-feedback-comment').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            openModal(btn.dataset.comment || '', btn.dataset.meta || '');
                        });
                    });
                    close.addEventListener('click', closeModal);
                    modal.addEventListener('click', function (e) {
                        if (e.target === modal) closeModal();
                    });
                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape' && modal.style.display === 'flex') closeModal();
                    });
                })();
            </script>
        @endif
    </div>
@endsection
