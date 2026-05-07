<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\LootDistributionBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $event_uuid
 * @property int $static_id
 * @property Carbon $awarded_at
 * @property string $raid_slug
 * @property string $raid_difficulty
 * @property string|null $boss_name
 * @property string|null $pull_id
 * @property string $recipient_name
 * @property int|null $recipient_character_id
 * @property string|null $awarded_by_name
 * @property int $item_id
 * @property int|null $item_level
 * @property array|null $bonus_ids
 * @property int|null $enchant_id
 * @property array|null $gem_ids
 * @property string $method
 * @property string|null $note
 * @property Carbon $received_at
 */
class LootDistribution extends Model
{
    protected $fillable = [
        'event_uuid', 'static_id', 'awarded_at',
        'raid_slug', 'raid_difficulty', 'boss_name', 'pull_id',
        'recipient_name', 'recipient_character_id', 'awarded_by_name',
        'item_id', 'item_level', 'bonus_ids', 'enchant_id', 'gem_ids',
        'method', 'note', 'received_at',
    ];

    protected $casts = [
        'awarded_at'  => 'datetime',
        'received_at' => 'datetime',
        'bonus_ids'   => 'array',
        'gem_ids'     => 'array',
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
