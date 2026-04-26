<?php

namespace App\Builders;

use App\Models\ReportFeedback;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @method ReportFeedback|null first($columns = ['*'])
 */
class ReportFeedbackBuilder extends Builder
{
    public function forUser(int $userId): self
    {
        $this->where('user_id', $userId);
        return $this;
    }

    public function forReport(int $reportId): self
    {
        $this->where('tactical_report_id', $reportId);
        return $this;
    }

    public function withRatingAtMost(int $rating): self
    {
        $this->where('report_rating', '<=', $rating);
        return $this;
    }

    public function findByUserAndReport(int $userId, int $reportId): ?ReportFeedback
    {
        return $this->where('user_id', $userId)
            ->where('tactical_report_id', $reportId)
            ->first();
    }

    /**
     * Mutation: upsert a user's feedback for a report. Updates existing row
     * (matching the unique key) or creates a new one.
     */
    public function upsertForUser(int $userId, int $reportId, array $attributes): ReportFeedback
    {
        return ReportFeedback::updateOrCreate(
            ['tactical_report_id' => $reportId, 'user_id' => $userId],
            $attributes
        );
    }

    /**
     * Aggregate counts of liked + disliked tags across all feedback for a static.
     * Used by the admin feedback dashboard.
     *
     * @return array{liked: array<string,int>, disliked: array<string,int>, count: int, avg_report: float, avg_chat: float|null}
     */
    public function aggregateForStatic(int $staticId): array
    {
        $rows = $this
            ->join('tactical_reports', 'tactical_reports.id', '=', 'report_feedback.tactical_report_id')
            ->where('tactical_reports.static_id', $staticId)
            ->get(['report_feedback.report_rating', 'report_feedback.chat_rating', 'report_feedback.liked_tags', 'report_feedback.disliked_tags']);

        return $this->summariseRows($rows);
    }

    /**
     * Platform-wide aggregate (all statics). Used by the admin dashboard.
     *
     * @return array{liked: array<string,int>, disliked: array<string,int>, count: int, avg_report: float, avg_chat: float|null}
     */
    public function aggregateGlobal(?\DateTimeInterface $since = null): array
    {
        $query = $this->newQuery();
        if ($since) {
            $query->where('created_at', '>=', $since);
        }
        $rows = $query->get(['report_rating', 'chat_rating', 'liked_tags', 'disliked_tags']);
        return $this->summariseRows($rows);
    }

    /**
     * Tag breakdown filtered by rating window. Used to surface "what users
     * who LIKE the report mention" vs "what users who DISLIKE the report
     * mention" — the gap drives prompt-tuning priorities.
     *
     * @param  string  $window  'positive' (rating >= 4) or 'negative' (rating <= 3)
     * @return array{liked: array<string,int>, disliked: array<string,int>}
     */
    public function tagBreakdownByRating(string $window, ?\DateTimeInterface $since = null): array
    {
        $query = $this->newQuery();
        if ($window === 'positive') {
            $query->where('report_rating', '>=', 4);
        } else {
            $query->where('report_rating', '<=', 3);
        }
        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        $liked = [];
        $disliked = [];
        foreach ($query->get(['liked_tags', 'disliked_tags']) as $row) {
            foreach ((array) $row->liked_tags as $tag) {
                $liked[$tag] = ($liked[$tag] ?? 0) + 1;
            }
            foreach ((array) $row->disliked_tags as $tag) {
                $disliked[$tag] = ($disliked[$tag] ?? 0) + 1;
            }
        }

        return ['liked' => $liked, 'disliked' => $disliked];
    }

    /**
     * Weekly timeseries: avg report_rating + count for the last N weeks.
     *
     * @return array<int, array{week_start:string, count:int, avg_report:float, avg_chat:float|null}>
     */
    public function weeklyTimeseries(int $weeks = 12): array
    {
        $since = now()->subWeeks($weeks)->startOfWeek();
        $rows = $this->newQuery()
            ->where('created_at', '>=', $since)
            ->orderBy('created_at')
            ->get(['report_rating', 'chat_rating', 'created_at']);

        $buckets = [];
        foreach ($rows as $r) {
            $weekStart = $r->created_at->startOfWeek()->toDateString();
            $buckets[$weekStart] ??= ['count' => 0, 'report_sum' => 0, 'chat_sum' => 0, 'chat_count' => 0];
            $buckets[$weekStart]['count']++;
            $buckets[$weekStart]['report_sum'] += (int) $r->report_rating;
            if ($r->chat_rating !== null) {
                $buckets[$weekStart]['chat_sum'] += (int) $r->chat_rating;
                $buckets[$weekStart]['chat_count']++;
            }
        }

        $out = [];
        foreach ($buckets as $weekStart => $b) {
            $out[] = [
                'week_start' => $weekStart,
                'count'      => $b['count'],
                'avg_report' => round($b['report_sum'] / $b['count'], 2),
                'avg_chat'   => $b['chat_count'] > 0 ? round($b['chat_sum'] / $b['chat_count'], 2) : null,
            ];
        }
        return $out;
    }

    /**
     * Per-prompt-version aggregates. The join+group-by lets us see at a
     * glance whether bumping prompt_version moved ratings up or down.
     *
     * @return array<int, array{
     *   version:string, count:int, avg_report:float, avg_chat:float|null,
     *   liked: array<string,int>, disliked: array<string,int>
     * }>
     */
    public function aggregateByPromptVersion(): array
    {
        $rows = $this
            ->join('tactical_reports', 'tactical_reports.id', '=', 'report_feedback.tactical_report_id')
            ->select([
                'tactical_reports.prompt_version',
                'report_feedback.report_rating',
                'report_feedback.chat_rating',
                'report_feedback.liked_tags',
                'report_feedback.disliked_tags',
            ])
            ->get();

        $byVersion = [];
        foreach ($rows as $r) {
            $version = $r->prompt_version ?: 'v0-legacy';
            $byVersion[$version] ??= collect();
            $byVersion[$version]->push($r);
        }

        $out = [];
        foreach ($byVersion as $version => $versionRows) {
            $summary = $this->summariseRows($versionRows);
            $out[] = [
                'version'    => $version,
                'count'      => $summary['count'],
                'avg_report' => $summary['avg_report'],
                'avg_chat'   => $summary['avg_chat'],
                'liked'      => $summary['liked'],
                'disliked'   => $summary['disliked'],
            ];
        }

        // Newest version first when versions follow vN naming, else alphabetical desc.
        usort($out, function ($a, $b) {
            $av = (int) preg_replace('/\D/', '', $a['version']);
            $bv = (int) preg_replace('/\D/', '', $b['version']);
            if ($av !== $bv) return $bv <=> $av;
            return strcmp($b['version'], $a['version']);
        });

        return $out;
    }

    /**
     * @param \Illuminate\Support\Collection $rows
     */
    private function summariseRows($rows): array
    {
        $liked = [];
        $disliked = [];
        $reportSum = 0;
        $chatSum = 0;
        $chatCount = 0;

        foreach ($rows as $row) {
            $reportSum += (int) $row->report_rating;
            if ($row->chat_rating !== null) {
                $chatSum += (int) $row->chat_rating;
                $chatCount++;
            }
            foreach ((array) $row->liked_tags as $tag) {
                $liked[$tag] = ($liked[$tag] ?? 0) + 1;
            }
            foreach ((array) $row->disliked_tags as $tag) {
                $disliked[$tag] = ($disliked[$tag] ?? 0) + 1;
            }
        }

        $count = $rows->count();
        arsort($liked);
        arsort($disliked);

        return [
            'count'      => $count,
            'avg_report' => $count > 0 ? round($reportSum / $count, 2) : 0.0,
            'avg_chat'   => $chatCount > 0 ? round($chatSum / $chatCount, 2) : null,
            'liked'      => $liked,
            'disliked'   => $disliked,
        ];
    }
}
