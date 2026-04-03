<?php

declare(strict_types=1);

namespace App\Helpers;

class GeminiResponseFormatter
{
    /**
     * Clean markdown JSON or HTML blocks from the AI response.
     *
     * @param string $text
     * @return string
     */
    public static function cleanMarkdown(string $text): string
    {
        $text = preg_replace('/^```(?:json|html|text|markdown)?\s*/i', '', $text);
        $text = preg_replace('/```\s*$/i', '', $text);

        return trim($text);
    }
}
