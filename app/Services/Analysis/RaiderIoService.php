<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Data\Analysis\RaiderIo\RaiderIoProfileData;
use App\Tasks\Analysis\FetchRaiderIoProfileTask;

class RaiderIoService
{
    public function __construct(
        private readonly FetchRaiderIoProfileTask $fetchRaiderIoProfileTask
    ) {
    }

    /**
     * Fetch character profile from the Raider.io API.
     */
    public function getCharacterProfile(string $region, string $realm, string $name): ?RaiderIoProfileData
    {
        return $this->fetchRaiderIoProfileTask->run($region, $realm, $name);
    }
}
