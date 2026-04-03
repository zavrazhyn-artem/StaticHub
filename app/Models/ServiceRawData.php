<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int          $id
 * @property int          $character_id
 * @property array|null   $bnet_profile
 * @property array|null   $bnet_equipment
 * @property array|null   $bnet_media
 * @property array|null   $bnet_mplus
 * @property array|null   $bnet_raid
 * @property array|null   $rio_profile
 * @property Carbon|null  $created_at
 * @property Carbon|null  $updated_at
 * @property-read Character $character
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceRawData whereCharacterId($value)
 * @mixin \Eloquent
 */
class ServiceRawData extends Model
{
    protected $table = 'services_raw_data';

    protected $fillable = [
        'character_id',
        'bnet_profile',
        'bnet_equipment',
        'bnet_media',
        'bnet_mplus',
        'bnet_raid',
        'rio_profile',
    ];

    protected $casts = [
        'bnet_profile'   => 'array',
        'bnet_equipment' => 'array',
        'bnet_media'     => 'array',
        'bnet_mplus'     => 'array',
        'bnet_raid'      => 'array',
        'rio_profile'    => 'array',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
