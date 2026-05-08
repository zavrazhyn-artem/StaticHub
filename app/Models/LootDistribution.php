<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\LootDistributionBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $external_id
 * @property int $static_id
 * @property Carbon $awarded_at
 * @property string $raid_difficulty
 * @property int|null $encounter_id
 * @property string|null $pull_id
 * @property bool $is_test_mode
 * @property string $recipient_name
 * @property int|null $recipient_character_id
 * @property int|null $recipient_spec_id
 * @property string|null $awarded_by_name
 * @property int $item_id
 * @property string|null $item_slot
 * @property int|null $item_level
 * @property array|null $bonus_ids
 * @property int|null $enchant_id
 * @property array|null $gem_ids
 * @property string $method
 * @property string|null $response_text
 * @property string|null $response_color
 * @property int|null $council_same_vote
 * @property bool $is_award_reason
 * @property string|null $note
 * @property Carbon $received_at
 */
class LootDistribution extends Model
{
    protected $fillable = [
        'external_id', 'static_id', 'awarded_at',
        'raid_difficulty', 'encounter_id', 'pull_id',
        'recipient_name', 'recipient_character_id', 'recipient_spec_id',
        'awarded_by_name',
        'item_id', 'item_slot', 'item_level', 'bonus_ids', 'enchant_id', 'gem_ids',
        'method', 'response_text', 'response_color', 'council_same_vote', 'is_award_reason',
        'is_test_mode',
        'note', 'received_at',
    ];

    protected $casts = [
        'awarded_at'      => 'datetime',
        'received_at'     => 'datetime',
        'bonus_ids'       => 'array',
        'gem_ids'         => 'array',
        'is_award_reason' => 'boolean',
        'is_test_mode'    => 'boolean',
    ];

    public function static(): BelongsTo
    {
        return $this->belongsTo(StaticGroup::class, 'static_id');
    }

    public function recipientCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'recipient_character_id');
    }

    public function newEloquentBuilder($query): LootDistributionBuilder
    {
        return new LootDistributionBuilder($query);
    }
}
