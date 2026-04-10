<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\ServiceRawDataBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int          $id
 * @property int          $character_id
 * @property array|null   $bnet_profile
 * @property array|null   $bnet_equipment
 * @property array|null   $bnet_equipment_by_spec
 * @property array|null   $bnet_media
 * @property array|null   $bnet_mplus
 * @property array|null   $bnet_raid
 * @property array|null   $rio_profile
 * @property array|null   $bnet_achievement_statistics
 * @property array|null   $bnet_completed_quests
 * @property array|null   $bnet_pvp_summary
 * @property array|null   $bnet_reputations
 * @property array|null   $bnet_titles
 * @property array|null   $bnet_mounts
 * @property array|null   $bnet_pets
 * @property array|null   $vault_weekly_snapshot
 * @property Carbon|null  $created_at
 * @property Carbon|null  $updated_at
 * @property-read Character $character
 *
 * @method static ServiceRawDataBuilder query()
 * @method static ServiceRawDataBuilder<static>|ServiceRawData newModelQuery()
 * @method static ServiceRawDataBuilder<static>|ServiceRawData newQuery()
 * @method static ServiceRawDataBuilder<static>|ServiceRawData forCharacter(int $characterId)
 * @method static ServiceRawDataBuilder<static>|ServiceRawData whereCharacterId($value)
 * @mixin \Eloquent
 */
class ServiceRawData extends Model
{
    protected $table = 'services_raw_data';

    protected $fillable = [
        'character_id',
        'bnet_profile',
        'bnet_equipment',
        'bnet_equipment_by_spec',
        'bnet_media',
        'bnet_mplus',
        'bnet_raid',
        'rio_profile',
        'bnet_achievement_statistics',
        'bnet_completed_quests',
        'bnet_pvp_summary',
        'bnet_reputations',
        'bnet_titles',
        'bnet_mounts',
        'bnet_pets',
        'vault_weekly_snapshot',
    ];

    protected $casts = [
        'bnet_profile'                 => 'array',
        'bnet_equipment'               => 'array',
        'bnet_equipment_by_spec'       => 'array',
        'bnet_media'                   => 'array',
        'bnet_mplus'                   => 'array',
        'bnet_raid'                    => 'array',
        'rio_profile'                  => 'array',
        'bnet_achievement_statistics'  => 'array',
        'bnet_completed_quests'        => 'array',
        'bnet_pvp_summary'             => 'array',
        'bnet_reputations'             => 'array',
        'bnet_titles'                  => 'array',
        'bnet_mounts'                  => 'array',
        'bnet_pets'                    => 'array',
        'vault_weekly_snapshot'        => 'array',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function newEloquentBuilder($query): ServiceRawDataBuilder
    {
        return new ServiceRawDataBuilder($query);
    }
}
