<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class OAuthDeviceException extends Exception
{
    public static function userCodeNotFound(): self
    {
        return new self('User code not found, already used, or expired.');
    }

    public static function couldNotGenerateUniqueCode(): self
    {
        return new self('Failed to generate a unique user_code after several retries.');
    }
}
