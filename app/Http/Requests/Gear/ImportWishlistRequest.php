<?php

declare(strict_types=1);

namespace App\Http\Requests\Gear;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ImportWishlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'url'          => ['required', 'string', 'url', 'max:2048'],
            'character_id' => ['required', 'integer', 'exists:characters,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $url = (string) $this->input('url');
            $host = parse_url($url, PHP_URL_HOST) ?: '';
            $supported = str_contains($host, 'raidbots.com')
                || str_contains($host, 'questionablyepic.com');

            if (! $supported) {
                $v->errors()->add(
                    'url',
                    'Only Raidbots Droptimizer or QE Live URLs are accepted.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'url.required' => 'Wishlist URL is required.',
            'url.url'      => 'Provide a valid URL.',
        ];
    }
}
