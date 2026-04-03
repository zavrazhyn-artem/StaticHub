<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoTalentData extends Data
{
    public function __construct(
        public RaiderIoTalentNodeData $node,
        public int $entryIndex,
        public int $rank,
        public ?bool $includeInSummary = null,
    ) {
    }
}
