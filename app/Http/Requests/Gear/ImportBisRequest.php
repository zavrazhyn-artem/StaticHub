<?php

declare(strict_types=1);

namespace App\Http\Requests\Gear;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ImportBisRequest extends FormRequest
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
            'url'          => ['required', 'string', 'url', 'max:2048'],
        ];
    }

    public function withValidator(Validator $v): void
    {
        $v->after(function (Validator $validator) {
            $host = parse_url((string) $this->input('url'), PHP_URL_HOST) ?: '';
            if (! str_contains($host, 'icy-veins.com')) {
                $validator->errors()->add('url', 'Only icy-veins.com URLs are accepted on this phase.');
            }
        });
    }
}
