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

    private function extractOverallBisSection(string $html): string
    {
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

        $start = strpos($html, 'id="area_1"');
        if ($start === false) {
            throw WishlistImportException::invalidPayload(
                'icy-veins page layout changed — area_1 anchor missing.'
            );
        }

        $end = strpos($html, 'id="area_2"', $start);
        if ($end === false) {
            $end = $start + 50000;
        }

        return substr($html, $start, $end - $start);
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
        foreach ($rows[1] as $rowHtml) {
            preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $rowHtml, $cells);
            if (count($cells[1]) < 2) {
                continue;
            }

            $slotLabel = strtolower(trim(strip_tags($cells[1][0])));
            $slot = self::SLOT_MAP[$slotLabel] ?? null;
            if (! $slot) {
                continue;
            }

            if (! preg_match('/data-wowhead=["\']item=(\d+)/i', $cells[1][1], $idMatch)) {
                continue;
            }
            $itemId = (int) $idMatch[1];

            $name = '';
            if (preg_match('/<span[^>]*class=["\'][^"\']*q\d[^"\']*["\'][^>]*>(.*?)<\/span>/is', $cells[1][1], $nameMatch)) {
                $name = trim(html_entity_decode(strip_tags($nameMatch[1])));
            }

            $sourceNote = '';
            if (isset($cells[1][2])) {
                $sourceNote = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($cells[1][2]))));
            }

            $items[] = [
                'slot'        => $slot,
                'item_id'     => $itemId,
                'name'        => $name,
                'source_note' => $sourceNote,
            ];
        }

        return $items;
    }
}
