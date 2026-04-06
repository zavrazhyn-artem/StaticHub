<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateCharacterSpecsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $characterId = (int) $this->input('character_id');

        // Character must belong to authenticated user
        return \App\Models\Character::where('id', $characterId)
            ->where('user_id', Auth::id())
            ->exists();
    }

    public function rules(): array
    {
        return [
            'character_id'  => 'required|integer|exists:characters,id',
            'static_id'     => 'required|integer|exists:statics,id',
            'spec_ids'       => 'required|array|min:1',
            'spec_ids.*'     => 'integer|exists:specializations,id',
            'main_spec_id'  => 'required|integer|exists:specializations,id',
        ];
    }
}
