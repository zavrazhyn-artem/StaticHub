<?php

declare(strict_types=1);

namespace App\Http\Requests\Gear;

use Illuminate\Foundation\Http\FormRequest;

class ImportSimcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'simc' => ['required', 'string', 'max:20000'],
        ];
    }
}
