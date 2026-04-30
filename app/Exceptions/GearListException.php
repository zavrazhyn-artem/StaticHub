<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class GearListException extends Exception
{
    public static function characterNotOwned(int $characterId): self
    {
        return new self("Character #{$characterId} is not linked to your account.");
    }

    public static function customLimitReached(int $limit): self
    {
        return new self("You can have at most {$limit} custom gear lists per spec.");
    }

    public static function cannotMutateCurrent(): self
    {
        return new self("The Current Equipment list is auto-managed by Battle.net sync and cannot be edited directly.");
    }

    public static function cannotDeleteBis(): self
    {
        return new self("The Best in Slot list cannot be deleted. Re-import an icy-veins URL to replace it instead.");
    }

    public static function listNotFound(int $listId): self
    {
        return new self("Gear list #{$listId} does not exist.");
    }

    public static function specNotForClass(int $specId, string $className): self
    {
        return new self("Specialization #{$specId} does not belong to {$className}.");
    }
}
