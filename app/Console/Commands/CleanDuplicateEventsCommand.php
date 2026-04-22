<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\StaticGroup;
use App\Services\Raid\RaidScheduleService;
use Illuminate\Console\Command;

class CleanDuplicateEventsCommand extends Command
{
    protected $signature = 'raid:clean-duplicate-events
                            {--static= : Process only this static ID (omit to process all)}
                            {--force : Apply changes. Without this flag, runs in dry-run mode.}';

    protected $description = 'Clean duplicate auto-generated future events left over from the old schedule-update bug. Preserves events that have a Discord message or any RSVPs — those are promoted to manual (is_auto_generated=false) so future regenerations never touch them.';

    public function handle(RaidScheduleService $scheduleService): int
    {
        $force    = (bool) $this->option('force');
        $staticId = $this->option('static');

        $query = StaticGroup::query();
        if ($staticId !== null) {
            $query->where('id', (int) $staticId);
        }
        $statics = $query->get();

        if ($statics->isEmpty()) {
            $this->error('No statics matched the given filter.');
            return self::FAILURE;
        }

        $mode = $force ? '[APPLY]' : '[DRY-RUN]';
        $this->info("{$mode} Scanning {$statics->count()} static(s)...");

        $totals = ['kept' => 0, 'promoted' => 0, 'deleted' => 0];

        foreach ($statics as $static) {
            $result = $this->processStatic($static, $scheduleService, $force);
            foreach ($totals as $key => $_) {
                $totals[$key] += $result[$key];
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s Summary: kept=%d  promoted=%d  deleted=%d',
            $mode,
            $totals['kept'],
            $totals['promoted'],
            $totals['deleted']
        ));

        if (!$force) {
            $this->warn('Nothing was modified. Re-run with --force to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{kept:int, promoted:int, deleted:int}
     */
    private function processStatic(StaticGroup $static, RaidScheduleService $scheduleService, bool $force): array
    {
        $this->newLine();
        $this->line("<fg=cyan>Static #{$static->id}: {$static->name}</>");

        $raidDays = $static->getRaidDaysArray();
        if (empty($raidDays) || empty($static->raid_start_time)) {
            $this->warn('  Skipping: no raid schedule configured.');
            return ['kept' => 0, 'promoted' => 0, 'deleted' => 0];
        }

        $timezone  = $static->timezone ?? 'UTC';
        $daysAhead = (int) config('raid.schedule_days_ahead', 30);

        // Expected UTC start timestamps for the current settings, as a fast lookup set.
        $expected = collect($scheduleService->calculateEventTimestamps(
            $raidDays,
            $static->raid_start_time,
            $static->raid_end_time,
            $timezone,
            $daysAhead
        ))
            ->map(fn ($slot) => $slot['start']->copy()->utc()->format('Y-m-d H:i:s'))
            ->flip();

        // Match by flag OR legacy description marker — so the command works even on
        // databases where the backfill migration hasn't normalized the flag yet.
        $events = Event::query()
            ->forStatic($static->id)
            ->where('start_time', '>', now())
            ->where(function ($q) {
                $q->where('is_auto_generated', true)
                  ->orWhere('description', 'Auto-generated raid session.');
            })
            ->orderBy('start_time')
            ->get();

        if ($events->isEmpty()) {
            $this->line('  No future auto-generated events.');
            return ['kept' => 0, 'promoted' => 0, 'deleted' => 0];
        }

        // Normalize: any event matched by the legacy marker should get the flag
        // before we make decisions. Non-destructive and keeps state consistent.
        if ($force) {
            foreach ($events as $event) {
                if (!$event->is_auto_generated) {
                    $event->is_auto_generated = true;
                    $event->save();
                }
            }
        }

        $kept = 0; $promoted = 0; $deleted = 0;
        $rows = [];

        foreach ($events as $event) {
            $utcKey    = $event->start_time->copy()->utc()->format('Y-m-d H:i:s');
            $matches   = $expected->has($utcKey);
            $hasMsg    = !empty($event->discord_message_id);
            $rsvpCount = $event->attendances()->count();
            $hasRsvp   = $rsvpCount > 0;

            if ($matches) {
                $action = 'keep';
                $kept++;
            } elseif ($hasMsg || $hasRsvp) {
                $action = 'promote';
                $promoted++;
                if ($force) {
                    $event->is_auto_generated = false;
                    $event->save();
                }
            } else {
                $action = 'delete';
                $deleted++;
                if ($force) {
                    $event->delete();
                }
            }

            $rows[] = [
                $event->id,
                $event->start_time->setTimezone($timezone)->format('Y-m-d D H:i'),
                $matches ? 'yes' : 'no',
                $hasMsg ? 'yes' : '-',
                $rsvpCount ?: '-',
                $action,
            ];
        }

        $this->table(
            ['event_id', 'start (local)', 'matches_schedule', 'discord', 'rsvps', 'action'],
            $rows
        );
        $this->line("  <fg=green>kept={$kept}</>  <fg=yellow>promoted={$promoted}</>  <fg=red>deleted={$deleted}</>");

        return ['kept' => $kept, 'promoted' => $promoted, 'deleted' => $deleted];
    }
}
