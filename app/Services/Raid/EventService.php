<?php

declare(strict_types=1);

namespace App\Services\Raid;

use App\Models\Event;
use App\Models\StaticGroup;
use App\Models\User;
use App\Models\Character;
use App\Services\StaticGroup\StaticService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EventService
{
    public function __construct(
        protected RaidAttendanceService $attendanceService,
        protected CalendarService       $calendarService,
        protected StaticService         $staticService,
    ) {}

    /**
     * Process RSVP for a raid event.
     */
    public function executeRsvp(Event $event, User $user, array $data): bool
    {
        if ($event->raid_started) {
            Log::warning('RSVP rejected: event already started', ['event_id' => $event->id]);
            return false;
        }

        Log::info('RSVP Request processing', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'data' => $data,
        ]);

        $character = $this->validateAndFetchRsvpCharacter($data, $user->id, $event->static_id);

        if (!$character) {
            Log::warning('RSVP failed: character not found or not in static', [
                'character_id' => $data['character_id'] ?? null,
                'user_id' => $user->id,
                'static_id' => $event->static_id,
            ]);
            return false;
        }

        $event->clearUserAttendance($user->id);

        $specId = isset($data['spec_id']) ? (int) $data['spec_id'] : null;

        $this->attendanceService->updateAttendance(
            $event,
            $character,
            $data['status'],
            $data['comment'] ?? null,
            $specId
        );

        Log::info('RSVP success', [
            'event_id' => $event->id,
            'character_id' => $character->id,
        ]);

        return true;
    }

    /**
     * Create a new raid event with membership validation.
     */
    public function createEvent(array $data, int $userId): Event
    {
        /** @var StaticGroup $static */
        $static = StaticGroup::findOrFail($data['static_id']);

        if (!$static->hasMember($userId)) {
            throw new \Exception('Unauthorized', 403);
        }

        $timezone = $data['timezone'] ?? 'UTC';
        $startTime = Carbon::parse($data['date'] . ' ' . $data['start_time'], $timezone)->setTimezone('UTC');
        $endTime = isset($data['end_time'])
            ? Carbon::parse($data['date'] . ' ' . $data['end_time'], $timezone)->setTimezone('UTC')
            : null;

        $dayStart = $startTime->copy()->startOfDay();
        $dayEnd = $startTime->copy()->endOfDay();

        $exists = Event::where('static_id', $data['static_id'])
            ->whereBetween('start_time', [$dayStart, $dayEnd])
            ->exists();

        if ($exists) {
            throw new \Exception('На цей день вже заплановано івент.', 422);
        }

        if ($endTime && $endTime->lessThan($startTime)) {
            $endTime->addDay();
        }

        return Event::create([
            'static_id' => $data['static_id'],
            'start_time' => $startTime,
            'end_time' => $endTime,
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Validate if the selected character belongs to the user and the static.
     */
    private function validateAndFetchRsvpCharacter(array $data, int $userId, int $staticId): ?Character
    {
        if (empty($data['character_id'])) {
            return null;
        }

        return Character::query()->findForRsvp(
            (int) $data['character_id'],
            $userId,
            $staticId
        );
    }

    // -----------------------------------------------------------------------
    // Schedule methods (merged from ScheduleService)
    // -----------------------------------------------------------------------

    /**
     * Get the schedule index payload for a user.
     */
    public function buildSchedulePayload(int $year, int $month, int $userId): array
    {
        $static = $this->staticService->getDefaultStaticForUser($userId);

        $calendarData = $this->calendarService->buildMonthGrid($year, $month, $static->id);

        return array_merge($calendarData, [
            'static' => $static,
        ]);
    }

    /**
     * Update an existing raid event.
     */
    public function executeEventUpdate(Event $event, array $data, int $userId): Event
    {
        if ($event->raid_started) {
            throw new \Exception(__('Event already started. Changes are not allowed.'));
        }

        $timezone = $data['timezone'] ?? 'UTC';
        $date = $data['date'] ?? $event->start_time->format('Y-m-d');

        $startTime = Carbon::parse($date . ' ' . $data['start_time'], $timezone)->setTimezone('UTC');
        $endTime = Carbon::parse($date . ' ' . $data['end_time'], $timezone)->setTimezone('UTC');

        if ($endTime->lessThan($startTime)) {
            $endTime->addDay();
        }

        $event->update([
            'start_time' => $startTime,
            'end_time' => $endTime,
            'description' => $data['description'] ?? $event->description,
        ]);

        return $event;
    }

    /**
     * Delete a raid event.
     */
    public function executeEventDeletion(Event $event): void
    {
        if ($event->raid_started) {
            throw new \Exception(__('Event already started. Changes are not allowed.'));
        }

        $event->delete();
    }
}
