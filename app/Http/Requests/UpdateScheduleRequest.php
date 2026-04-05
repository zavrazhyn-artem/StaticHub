<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'raid_days' => 'required|array',
            'raid_days.*' => 'in:mon,tue,wed,thu,fri,sat,sun',
            'raid_start_time' => 'nullable|date_format:H:i',
            'raid_end_time' => 'nullable|date_format:H:i',
            'timezone' => 'required|timezone',
            'automation_settings' => 'nullable|array',
            'automation_settings.post_next_after_raid' => 'nullable|boolean',
            'automation_settings.reminder_hours_before' => 'nullable|integer|min:1|max:72',
            'weekly_tax_per_player' => 'nullable|integer|min:0',
        ];
    }
}
