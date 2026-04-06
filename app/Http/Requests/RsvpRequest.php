<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RsvpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'character_id' => 'required|exists:characters,id',
            'status'       => 'required|in:present,absent,tentative,late',
            'comment'      => 'nullable|string|max:255',
            'spec_id'      => 'nullable|integer|exists:specializations,id',
        ];
    }
}
