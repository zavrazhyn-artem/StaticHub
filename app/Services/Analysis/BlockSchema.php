<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Validates the block-based AI report structure.
 *
 * Blocks are a flat array of typed objects — each renders to a Vue component.
 * Invalid blocks are replaced with a paragraph containing a warning so the
 * whole report never dies on a single malformed block.
 */
class BlockSchema
{
    public const TYPES = [
        'heading',
        'paragraph',
        'metrics_grid',
        'table',
        'bar_chart',
        'progress_bar',
        'alert',
        'directive_list',
        'comparison',
        'rotation_issues',
        'player_card',
        'divider',
    ];

    private const REQUIRED = [
        'heading'         => ['level', 'text'],
        'paragraph'       => ['text'],
        'metrics_grid'    => ['items'],
        'table'           => ['columns', 'rows'],
        'bar_chart'       => ['bars'],
        'progress_bar'    => ['label', 'value'],
        'alert'           => ['severity', 'text'],
        'directive_list'  => ['items'],
        'comparison'      => ['left', 'right'],
        'rotation_issues' => ['issues'],
        'player_card'     => ['name', 'sections'],
        'divider'         => [],
    ];

    private const ALERT_SEVERITIES = ['danger', 'warning', 'success', 'info'];
    private const HEADING_LEVELS = [1, 2, 3];

    /**
     * Validate the full blocks payload (flat array). Returns the sanitized list.
     * Invalid blocks are replaced with a paragraph warning.
     *
     * @param mixed $raw
     * @return array<int, array<string, mixed>>
     */
    public function sanitize(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [$this->fallback('Report data was not an array.')];
        }

        // Allow Gemini to wrap output in {"blocks": [...]} — unwrap gracefully.
        if (isset($raw['blocks']) && is_array($raw['blocks'])) {
            $raw = $raw['blocks'];
        }

        if (!array_is_list($raw)) {
            return [$this->fallback('Report blocks were not a flat list.')];
        }

        $out = [];
        foreach ($raw as $index => $block) {
            $out[] = $this->sanitizeBlock($block, $index);
        }

        return $out;
    }

    private function sanitizeBlock(mixed $block, int $index): array
    {
        if (!is_array($block) || !isset($block['type'])) {
            return $this->fallback("Block #{$index} had no type.");
        }

        $type = $block['type'];
        if (!in_array($type, self::TYPES, true)) {
            return $this->fallback("Block #{$index} used unknown type '{$type}'.");
        }

        foreach (self::REQUIRED[$type] as $field) {
            if (!array_key_exists($field, $block)) {
                return $this->fallback("Block #{$index} ({$type}) missing required field '{$field}'.");
            }
        }

        return match ($type) {
            'heading'         => $this->sanitizeHeading($block),
            'alert'           => $this->sanitizeAlert($block),
            'progress_bar'    => $this->sanitizeProgressBar($block),
            default           => $block,
        };
    }

    private function sanitizeHeading(array $block): array
    {
        $level = (int) ($block['level'] ?? 2);
        if (!in_array($level, self::HEADING_LEVELS, true)) {
            $level = 2;
        }
        $block['level'] = $level;
        return $block;
    }

    private function sanitizeAlert(array $block): array
    {
        if (!in_array($block['severity'] ?? null, self::ALERT_SEVERITIES, true)) {
            $block['severity'] = 'info';
        }
        return $block;
    }

    private function sanitizeProgressBar(array $block): array
    {
        $value = $block['value'] ?? 0;
        if (!is_numeric($value)) {
            $value = 0;
        }
        $block['value'] = max(0, min(100, (float) $value));
        return $block;
    }

    private function fallback(string $message): array
    {
        return [
            'type' => 'paragraph',
            'text' => "[Report rendering warning] {$message}",
        ];
    }
}
