<?php

declare(strict_types=1);

namespace App\Http\Requests\Gear;

use Illuminate\Foundation\Http\FormRequest;

class StoreGearListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'character_id' => ['required', 'integer', 'exists:characters,id'],
            'spec_id'      => ['required', 'integer', 'exists:specializations,id'],
            'name'         => ['required', 'string', 'max:80'],
        ];
    }
}
