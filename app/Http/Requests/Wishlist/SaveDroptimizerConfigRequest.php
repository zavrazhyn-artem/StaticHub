<?php

declare(strict_types=1);

namespace App\Http\Requests\Wishlist;

use App\Models\WishlistDroptimizerConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDroptimizerConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $levels = WishlistDroptimizerConfig::upgradeLevelOptions();

        return [
            'display_name'              => ['required', 'string', 'max:64'],
            'fight_style'               => ['required', 'string', Rule::in(WishlistDroptimizerConfig::FIGHT_STYLES)],
            'num_bosses_op'             => ['required', 'string', Rule::in(WishlistDroptimizerConfig::OPS)],
            'num_bosses'                => ['required', 'integer', 'min:1', 'max:30'],
            'fight_length_op'           => ['required', 'string', Rule::in(WishlistDroptimizerConfig::OPS)],
            'fight_length_minutes'      => ['required', 'integer', 'min:1', 'max:30'],
            'weight'                    => ['required', 'numeric', 'min:0.1', 'max:10'],
            'require_vault_socket'      => ['boolean'],
            'require_pi'                => ['boolean'],
            'voidforged'                => ['boolean'],
            'allow_expert'              => ['boolean'],
            'require_upgrade_all_same'  => ['boolean'],
            'upgrade_level_mythic'      => ['nullable', 'string', Rule::in($levels['mythic'])],
            'upgrade_level_heroic'      => ['nullable', 'string', Rule::in($levels['heroic'])],
            'upgrade_level_normal'      => ['nullable', 'string', Rule::in($levels['normal'])],
            'upgrade_level_lfr'         => ['nullable', 'string', Rule::in($levels['lfr'])],
        ];
    }
}
