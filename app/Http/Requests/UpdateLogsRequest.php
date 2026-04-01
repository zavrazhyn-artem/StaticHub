<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
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
        ];
    }
}
