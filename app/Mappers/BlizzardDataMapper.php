<?php

namespace App\Mappers;

use App\Data\Analysis\Blizzard\BlizzardData;
use Illuminate\Support\Collection;

class BlizzardDataMapper
{
    public static function map(array $profile, array $equipment, array $media, array $mplus, array $raids): BlizzardData
    {
        return BlizzardData::fromRaw($profile, $equipment, $media, $mplus, $raids);
    }
}
