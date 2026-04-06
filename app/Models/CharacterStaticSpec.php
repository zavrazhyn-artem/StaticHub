<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\CharacterStaticSpecBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static CharacterStaticSpecBuilder query()
 * @method static CharacterStaticSpecBuilder<static>|CharacterStaticSpec newModelQuery()
 * @method static CharacterStaticSpecBuilder<static>|CharacterStaticSpec newQuery()
 * @method static CharacterStaticSpecBuilder<static>|CharacterStaticSpec forCharacterInStatic(int $characterId, int $staticId)
 */
class CharacterStaticSpec extends Model
{
    protected $table = 'character_static_specs';

    protected $fillable = [
        'character_id',
        'static_id',
        'spec_id',
        'is_main',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function static(): BelongsTo
    {
        return $this->belongsTo(StaticGroup::class, 'static_id');
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class, 'spec_id');
    }

    public function newEloquentBuilder($query): CharacterStaticSpecBuilder
    {
        return new CharacterStaticSpecBuilder($query);
    }
}
