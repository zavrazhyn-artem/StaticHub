<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLogsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wcl_guild_id' => 'nullable|string|max:50',
            'wcl_region' => 'nullable|string|max:10',
            'wcl_realm' => 'nullable|string|max:100',
            'auto_fetch_logs' => 'nullable|boolean',
            'auto_fetch_delay_minutes' => 'nullable|integer|min:5|max:120',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('auto_fetch_logs')) {
            $this->merge([
                'auto_fetch_logs' => filter_var($this->input('auto_fetch_logs'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
