<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class WishlistImportException extends Exception
{
    public static function unsupportedSource(string $url): self
    {
        return new self("URL '{$url}' is not from a supported source (raidbots.com or questionablyepic.com).");
    }

    public static function fetchFailed(string $url, int $status): self
    {
        return new self("Failed to fetch '{$url}' — HTTP {$status}.");
    }

    public static function invalidPayload(string $reason): self
    {
        return new self("Raidbots payload is invalid: {$reason}");
    }

    public static function unsupportedSimType(string $type): self
    {
        return new self("Sim type '{$type}' is not supported. Only 'droptimizer' is accepted.");
    }

    public static function characterNotFound(string $name, string $realm): self
    {
        return new self("Character '{$name}-{$realm}' is not in the roster. Sync the character via Battle.net first.");
    }

    public static function characterNotOwned(string $name, string $realm): self
    {
        return new self("Character '{$name}-{$realm}' is not linked to your account. You can only import wishlists for your own characters.");
    }

    public static function specNotFound(string $class, string $spec): self
    {
        return new self("Specialization '{$spec} {$class}' is not in the database.");
    }

    /**
     * @param array<string, list<string>> $perConfigReasons  configName ⇒ reasons it didn't match
     */
    public static function noMatchingConfig(string $reportSummary, array $perConfigReasons): self
    {
        $body = "This Droptimizer report doesn't match any allowed configuration for your static.\n"
              . "Report: {$reportSummary}\n";

        foreach ($perConfigReasons as $name => $reasons) {
            $body .= "\n• {$name}: " . (empty($reasons) ? 'matched (unexpected)' : implode('; ', $reasons));
        }

        $body .= "\n\nRe-run the sim with the right settings, or ask your raid lead to add a matching configuration.";

        return new self($body);
    }
}
