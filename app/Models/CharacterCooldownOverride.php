<?php

namespace App\Models;

use App\Builders\CharacterCooldownOverrideBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $character_id
 * @property int $spell_id
 * @property bool $enabled
 * @method static CharacterCooldownOverrideBuilder query()
 */
class CharacterCooldownOverride extends Model
{
    protected $fillable = [
        'character_id',
        'spell_id',
        'enabled',
    ];

    protected $casts = [
        'character_id' => 'integer',
        'spell_id' => 'integer',
        'enabled' => 'boolean',
    ];

    public function newEloquentBuilder($query): CharacterCooldownOverrideBuilder
    {
        return new CharacterCooldownOverrideBuilder($query);
    }
}
