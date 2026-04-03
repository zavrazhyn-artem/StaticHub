<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoTalentNodeData extends Data
{
    public function __construct(
        public int $id,
        public int $treeId,
        public int $type,
        public string $name,
        public ?string $spellId = null,
        public string $icon,
    ) {
    }
}
