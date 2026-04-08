<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
{
    /**
     * Known IANA timezone aliases that browsers may send
     * but PHP's timezone database no longer recognizes.
     */
    private const TIMEZONE_ALIASES = [
        'Europe/Kiev' => 'Europe/Kyiv',
        'Asia/Calcutta' => 'Asia/Kolkata',
        'Asia/Saigon' => 'Asia/Ho_Chi_Minh',
        'US/Eastern' => 'America/New_York',
        'US/Central' => 'America/Chicago',
        'US/Mountain' => 'America/Denver',
        'US/Pacific' => 'America/Los_Angeles',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('timezone') && isset(self::TIMEZONE_ALIASES[$this->timezone])) {
            $this->merge([
                'timezone' => self::TIMEZONE_ALIASES[$this->timezone],
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'raid_days' => 'nullable|array',
            'raid_days.*' => 'in:mon,tue,wed,thu,fri,sat,sun',
            'raid_start_time' => 'nullable|date_format:H:i',
            'raid_end_time' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|timezone',
            'automation_settings' => 'nullable|array',
            'automation_settings.post_next_after_raid' => 'nullable|boolean',
            'automation_settings.reminder_hours_before' => 'nullable|integer|min:1|max:72',
            'weekly_tax_per_player' => 'nullable|integer|min:0',
        ];
    }
}
