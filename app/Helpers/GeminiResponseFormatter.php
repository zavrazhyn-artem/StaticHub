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

    /**
     * Extract the first balanced JSON object (`{...}`) from a string.
     *
     * Gemini 3 occasionally appends trailing garbage after a syntactically
     * valid JSON response — typically a stray `}`, a partial duplicate
     * object, or a stray newline+brace. `json_decode` rejects the whole
     * payload because of that trailing junk. This walker tracks brace depth
     * (string-aware, escape-aware) and returns ONLY the first complete
     * top-level object, dropping everything after it.
     *
     * Returns the original string unchanged when no balanced object is
     * found, so callers can still inspect the raw text in their error
     * handler.
     */
    public static function extractFirstJsonObject(string $text): string
    {
        $start = strpos($text, '{');
        if ($start === false) return $text;

        $depth   = 0;
        $inStr   = false;
        $escape  = false;
        $len     = strlen($text);

        for ($i = $start; $i < $len; $i++) {
            $c = $text[$i];

            if ($escape) {
                $escape = false;
                continue;
            }

            if ($c === '\\') {
                $escape = true;
                continue;
            }

            if ($c === '"') {
                $inStr = !$inStr;
                continue;
            }

            if ($inStr) continue;

            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    // Inclusive end position
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        // Unbalanced — return the substring from first { to end so the
        // caller's json_decode failure carries useful diagnostic preview.
        return substr($text, $start);
    }
}
