<?php

declare(strict_types=1);

namespace App\Services\Gear;

use App\Exceptions\WishlistImportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Fetches an icy-veins BiS guide page and extracts the **Overall BiS** table:
 * Slot | Item | Source/Note. Item IDs come from data-wowhead="item=N"
 * attributes inside the second cell.
 *
 * Returns a normalized DTO. Caller (GearListService) decides which
 * (character, spec) to attach the result to — we don't try to auto-detect
 * spec from URL because the format varies and a wrong guess would silently
 * create a list against the wrong spec.
 */
final class IcyVeinsBisImportService
{
    private const SLOT_MAP = [
        'weapon'     => 'main_hand',
        'main hand'  => 'main_hand',
        'main-hand'  => 'main_hand',
        'off hand'   => 'off_hand',
        'off-hand'   => 'off_hand',
        'shield'     => 'off_hand',
        'helm'       => 'head',
        'helmet'     => 'head',
        'head'       => 'head',
        'neck'       => 'neck',
        'shoulder'   => 'shoulder',
        'shoulders'  => 'shoulder',
        'cloak'      => 'back',
        'cape'       => 'back',
        'back'       => 'back',
        'chest'      => 'chest',
        'bracers'    => 'wrist',
        'wrist'      => 'wrist',
        'wrists'     => 'wrist',
        'gloves'     => 'hands',
        'hands'      => 'hands',
        'belt'       => 'waist',
        'waist'      => 'waist',
        'legs'       => 'legs',
        'pants'      => 'legs',
        'boots'      => 'feet',
        'feet'       => 'feet',
        'ring #1'    => 'finger_1',
        'ring 1'     => 'finger_1',
        'ring #2'    => 'finger_2',
        'ring 2'     => 'finger_2',
        'trinket #1' => 'trinket_1',
        'trinket 1'  => 'trinket_1',
        'trinket #2' => 'trinket_2',
        'trinket 2'  => 'trinket_2',
    ];

    /**
     * Slot labels that pack two items in a single row (either listed as a
     * `<ul>` of recommendations or just both items in the same cell). The
     * parser splits them across the paired slots in order.
     */
    private const PAIRED_SLOTS = [
        'rings'    => ['finger_1', 'finger_2'],
        'trinkets' => ['trinket_1', 'trinket_2'],
        'fingers'  => ['finger_1', 'finger_2'],
    ];

    /**
     * Bare ambiguous labels — rows are written as "Ring" twice in a row
     * instead of "Ring #1" / "Ring #2". Tracked via a per-label counter at
     * parse time.
     */
    private const POSITIONAL_SLOTS = [
        'ring'    => ['finger_1', 'finger_2'],
        'trinket' => ['trinket_1', 'trinket_2'],
        'finger'  => ['finger_1', 'finger_2'],
    ];

    public function isOwnUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host !== null && str_contains($host, 'icy-veins.com');
    }

    /**
     * @return array{
     *   source_url: string,
     *   items: array<int, array{slot:string, item_id:int, name:string, source_note:string}>
     * }
     */
    public function importFromUrl(string $url): array
    {
        if (! $this->isOwnUrl($url)) {
            throw WishlistImportException::unsupportedSource($url);
        }

        $html = $this->fetch($url);
        $section = $this->extractOverallBisSection($html);
        $items = $this->parseRows($section);

        if (empty($items)) {
            throw WishlistImportException::invalidPayload(
                'Overall BiS table not found on the icy-veins page.'
            );
        }

        return [
            'source_url' => $url,
            'items'      => $items,
        ];
    }

    private function fetch(string $url): string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'BlastrBot/0.1 (+https://blastr.pro)',
                'Accept'     => 'text/html',
            ])
                ->timeout(20)
                ->retry(3, 500, function ($exception) {
                    return $exception instanceof ConnectionException
                        || ($exception->response?->status() ?? 0) >= 500;
                }, throw: false)
                ->get($url);
        } catch (ConnectionException $e) {
            throw WishlistImportException::fetchFailed($url, 0);
        }

        if (! $response->ok()) {
            throw WishlistImportException::fetchFailed($url, $response->status());
        }

        return $response->body();
    }

    /**
     * Locate the Overall BiS table on the page. Across legacy and current
     * layouts the table always starts with the same `<th>Slot</th>
     * <th>Item</th><th>Source/Note</th>` header — we anchor on that pattern
     * directly and ignore any surrounding section markup, which has shifted
     * between `id="area_1"` anchors, h3 headers, and other variants over
     * time.
     */
    private function extractOverallBisSection(string $html): string
    {
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

        // Walk every <table> and return the first one whose header row reads
        // Slot / Item / Source/Note (Overall BiS is always the first such
        // table; the M+ and Raid subtables come later and use the same shape,
        // so first-match is what we want).
        if (preg_match_all('/<table[^>]*>(.+?)<\/table>/is', $html, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as $table) {
                $body = $table[0];
                if (preg_match('/<th[^>]*>\s*Slot\s*<\/th>\s*<th[^>]*>\s*Item\s*<\/th>\s*<th[^>]*>\s*Source\s*\/\s*Note\s*<\/th>/i', $body)) {
                    return $body;
                }
            }
        }

        throw WishlistImportException::invalidPayload(
            'icy-veins page layout changed — no Slot/Item/Source-Note table found.'
        );
    }

    /**
     * @return array<int, array{slot:string, item_id:int, name:string, source_note:string}>
     */
    private function parseRows(string $section): array
    {
        if (! preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $section, $rows)) {
            return [];
        }

        $items = [];
        $positionalCounts = [];

        foreach ($rows[1] as $rowHtml) {
            preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $rowHtml, $cells);
            if (count($cells[1]) < 2) continue;

            $slotLabel = strtolower(trim(strip_tags($cells[1][0])));
            $itemCell  = $cells[1][1];
            $sourceNote = isset($cells[1][2])
                ? trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($cells[1][2]))))
                : '';

            // Paired slots — single row containing 2+ items (e.g. <ul> of
            // trinket recommendations). Pull the first N item entries from
            // the cell and distribute across the paired slots in order.
            if (isset(self::PAIRED_SLOTS[$slotLabel])) {
                $slots = self::PAIRED_SLOTS[$slotLabel];
                $entries = $this->extractItemEntries($itemCell);
                foreach ($slots as $i => $slot) {
                    if (! isset($entries[$i])) break;
                    $items[] = [
                        'slot'        => $slot,
                        'item_id'     => $entries[$i]['item_id'],
                        'name'        => $entries[$i]['name'],
                        'source_note' => $sourceNote,
                    ];
                }
                continue;
            }

            // Positional slots — bare label that repeats across rows;
            // counter assigns finger_1 then finger_2, trinket_1 then _2, etc.
            if (isset(self::POSITIONAL_SLOTS[$slotLabel])) {
                $idx  = $positionalCounts[$slotLabel] ?? 0;
                $slot = self::POSITIONAL_SLOTS[$slotLabel][$idx] ?? null;
                $positionalCounts[$slotLabel] = $idx + 1;
                if (! $slot) continue;
                $entries = $this->extractItemEntries($itemCell);
                if (! $entries) continue;
                $items[] = [
                    'slot'        => $slot,
                    'item_id'     => $entries[0]['item_id'],
                    'name'        => $entries[0]['name'],
                    'source_note' => $sourceNote,
                ];
                continue;
            }

            $slot = self::SLOT_MAP[$slotLabel] ?? null;
            if (! $slot) continue;

            $entries = $this->extractItemEntries($itemCell);
            if (! $entries) continue;
            $items[] = [
                'slot'        => $slot,
                'item_id'     => $entries[0]['item_id'],
                'name'        => $entries[0]['name'],
                'source_note' => $sourceNote,
            ];
        }

        return $items;
    }

    /**
     * Pull every item entry (id + name) out of a cell. Multiple-recommendation
     * cells use `<li>` per choice; single-item cells just have one inline
     * `<span data-wowhead="item=…">`.
     *
     * @return list<array{item_id:int, name:string}>
     */
    private function extractItemEntries(string $cellHtml): array
    {
        if (preg_match_all(
            '/<span[^>]*data-wowhead=["\']item=(\d+)[^"\']*["\'][^>]*class=["\'][^"\']*q\d[^"\']*["\'][^>]*>(.*?)<\/span>/is',
            $cellHtml,
            $m,
            PREG_SET_ORDER
        )) {
            return array_map(fn (array $entry) => [
                'item_id' => (int) $entry[1],
                'name'    => trim(html_entity_decode(strip_tags($entry[2]))),
            ], $m);
        }

        // Fallback: pick up bare data-wowhead ids without a matching name span.
        if (preg_match_all('/data-wowhead=["\']item=(\d+)/i', $cellHtml, $idsOnly)) {
            return array_map(fn ($id) => ['item_id' => (int) $id, 'name' => ''], $idsOnly[1]);
        }
        return [];
    }
}
