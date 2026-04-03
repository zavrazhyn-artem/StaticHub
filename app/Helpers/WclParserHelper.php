<?php

declare(strict_types=1);

namespace App\Helpers;

class WclParserHelper
{
    /**
     * Extract the 16-character Report ID from the Warcraft Logs URL.
     */
    public static function extractReportIdFromUrl(string $url): ?string
    {
        if (preg_match('/reports\/([a-zA-Z0-9]{16})/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
