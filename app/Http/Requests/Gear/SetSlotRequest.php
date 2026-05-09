<?php

declare(strict_types=1);

namespace App\Http\Requests\Gear;

use App\Services\Gear\GearViewService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'slot'        => ['required', 'string', Rule::in(GearViewService::ALL_SLOTS)],
            'item_id'     => ['nullable', 'integer', 'min:1'],
            'item_level'  => ['nullable', 'integer', 'min:1', 'max:9999'],
            'enchant_id'  => ['nullable', 'integer', 'min:0'],
            'bonus_ids'   => ['nullable', 'array'],
            'bonus_ids.*' => ['integer'],
            // When supplied, server resolves (track, level) → bonus_id + ilvl
            // from wow_season.item_upgrade_tracks. Used by the slot picker so
            // the client doesn't need to embed the upgrade-track config.
            'track'         => ['nullable', 'string'],
            'level'         => ['nullable', 'integer', 'min:1', 'max:6'],
            // Crafted-item secondary picks: exactly two of crit/haste/mastery/
            // versatility. Stored on gear_list_items.chosen_stats; the
            // aggregator distributes the secondary budget across them.
            'chosen_stats'   => ['nullable', 'array', 'size:2'],
            'chosen_stats.*' => ['string', Rule::in(['crit', 'haste', 'mastery', 'versatility'])],
        ];
    }
}
