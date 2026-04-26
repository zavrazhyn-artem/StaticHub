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

    /**
     * Allowed fields per block type. Anything not listed here is silently dropped
     * during sanitize() to prevent broken blocks where the AI mixes type fields
     * (e.g. a heading carrying rows + columns from a table it forgot to split).
     */
    private const ALLOWED = [
        'heading'         => ['type', 'level', 'text'],
        'paragraph'       => ['type', 'text'],
        'metrics_grid'    => ['type', 'title', 'items'],
        'table'           => ['type', 'title', 'columns', 'rows'],
        'bar_chart'       => ['type', 'title', 'unit', 'bars'],
        'progress_bar'    => ['type', 'label', 'value', 'note', 'tone'],
        'alert'           => ['type', 'severity', 'title', 'text'],
        'directive_list'  => ['type', 'title', 'items'],
        'comparison'      => ['type', 'title', 'left', 'right'],
        'rotation_issues' => ['type', 'title', 'issues'],
        'player_card'     => ['type', 'name', 'spec', 'class', 'role', 'ilvl', 'sections'],
        'divider'         => ['type'],
    ];

    private const ALERT_SEVERITIES = ['danger', 'warning', 'success', 'info'];
    private const HEADING_LEVELS = [1, 2, 3];

    /** Canonical bar_chart units; anything else gets normalised. */
    private const BAR_CHART_UNITS = ['%', 'M', 'K', 'count'];

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

        // Drop any fields not whitelisted for this block type. This is what catches
        // hybrid blocks (e.g. a heading that arrives carrying rows + columns from
        // a table the model forgot to emit separately).
        $allowed = self::ALLOWED[$type] ?? ['type'];
        $block = array_intersect_key($block, array_flip($allowed));

        return match ($type) {
            'heading'         => $this->sanitizeHeading($block),
            'alert'           => $this->sanitizeAlert($block),
            'progress_bar'    => $this->sanitizeProgressBar($block),
            'bar_chart'       => $this->sanitizeBarChart($block),
            'table'           => $this->sanitizeTable($block),
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

    /**
     * Normalise bar_chart unit to one of the canonical values. Maps common
     * synonyms produced by the model (млн, Million, Total, Percentage, …) to
     * the schema's short form so the frontend renders a consistent suffix.
     */
    private function sanitizeBarChart(array $block): array
    {
        $unit = (string) ($block['unit'] ?? '');
        $unit = trim($unit);
        $lower = mb_strtolower($unit);

        $map = [
            'млн'        => 'M',
            'million'    => 'M',
            'mln'        => 'M',
            'mil'        => 'M',
            'thousand'   => 'K',
            'тис'        => 'K',
            'percent'    => '%',
            'percentage' => '%',
            'відсоток'   => '%',
            'відсотків'  => '%',
            'total'      => 'count',
            'casts'      => 'count',
            'кастів'     => 'count',
            ''           => 'count',
        ];

        $normalised = $map[$lower] ?? $unit;
        if (!in_array($normalised, self::BAR_CHART_UNITS, true)) {
            $normalised = 'count';
        }

        $block['unit'] = $normalised;
        return $block;
    }

    /**
     * Validate that table.rows are aligned with table.columns. Drops malformed
     * rows so the renderer never encounters a row with the wrong cell count.
     */
    private function sanitizeTable(array $block): array
    {
        $columns = $block['columns'];
        if (!is_array($columns) || !array_is_list($columns)) {
            $block['columns'] = [];
            $block['rows'] = [];
            return $block;
        }

        $colCount = count($columns);
        $rows = is_array($block['rows'] ?? null) ? $block['rows'] : [];

        $cleanRows = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_is_list($row)) continue;
            // Pad short rows / truncate long ones — a 4-col table with a 5-cell row
            // typically means the model added an extra value; safer to drop overflow.
            $row = array_slice($row, 0, $colCount);
            while (count($row) < $colCount) $row[] = '';
            $cleanRows[] = $row;
        }

        $block['rows'] = $cleanRows;
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
