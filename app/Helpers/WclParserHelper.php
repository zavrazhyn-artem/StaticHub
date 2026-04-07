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

    /**
     * Parse a WCL guild URL into structured data.
     *
     * Supported formats:
     *   https://www.warcraftlogs.com/guild/id/802874
     *   https://www.warcraftlogs.com/guild/eu/kazzak/guild-name
     *
     * @return array{type: 'id', guild_id: int}|array{type: 'name', region: string, server: string, name: string}|null
     */
    public static function parseGuildUrl(string $url): ?array
    {
        // Format: /guild/id/DIGITS
        if (preg_match('#/guild/id/(\d+)#i', $url, $m)) {
            return ['type' => 'id', 'guild_id' => (int) $m[1]];
        }

        // Format: /guild/REGION/SERVER/NAME
        if (preg_match('#/guild/([a-z]{2})/([^/]+)/([^/?#]+)#i', $url, $m)) {
            return [
                'type'   => 'name',
                'region' => strtolower($m[1]),
                'server' => strtolower($m[2]),
                'name'   => urldecode($m[3]),
            ];
        }

        return null;
    }
}
