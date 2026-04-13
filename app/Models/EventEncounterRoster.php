<?php

namespace App\Models;

use App\Builders\EventEncounterRosterBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_id
 * @property string $encounter_slug
 * @property int $character_id
 * @property string $selection_status
 * @property int $position_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Event $event
 * @property-read Character $character
 * @method static EventEncounterRosterBuilder query()
 */
class EventEncounterRoster extends Model
{
    protected $table = 'event_encounter_rosters';

    protected $fillable = [
        'event_id',
        'encounter_slug',
        'character_id',
        'selection_status',
        'position_order',
    ];

    public function newEloquentBuilder($query): EventEncounterRosterBuilder
    {
        return new EventEncounterRosterBuilder($query);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
