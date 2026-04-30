<?php

declare(strict_types=1);

namespace App\Services\Gear;

use App\Exceptions\WishlistImportException;

/**
 * Parses an in-game `/simc` paste — only the equipment block. Supports the
 * standard line layout:
 *
 *   head=,id=250024,enchant_id=7961,bonus_id=6652/13335/12667
 *   neck=,id=151309,gem_id=240971,bonus_id=13440/6652
 *   finger1=,id=...
 *
 * Other lines (talents, spec, race, etc.) are ignored. The leading blank
 * after `=` is what /simc emits when the item has no display name flag.
 */
final class SimcStringParserService
{
    private const SLOT_TOKEN_MAP = [
        'head'      => 'head',
        'neck'      => 'neck',
        'shoulder'  => 'shoulder',
        'shoulders' => 'shoulder',
        'back'      => 'back',
        'cloak'     => 'back',
        'chest'     => 'chest',
        'wrist'     => 'wrist',
        'wrists'    => 'wrist',
        'bracers'   => 'wrist',
        'hands'     => 'hands',
        'gloves'    => 'hands',
        'waist'     => 'waist',
        'belt'      => 'waist',
        'legs'      => 'legs',
        'pants'     => 'legs',
        'feet'      => 'feet',
        'boots'     => 'feet',
        'finger1'   => 'finger_1',
        'finger2'   => 'finger_2',
        'trinket1'  => 'trinket_1',
        'trinket2'  => 'trinket_2',
        'main_hand' => 'main_hand',
        'off_hand'  => 'off_hand',
        'two_hand'  => 'main_hand',
        'ranged'    => 'ranged',
    ];

    /**
     * @return array<int, array{slot:string, item_id:int, item_level:?int, enchant_id:?int, bonus_ids:array<int,int>}>
     */
    public function parse(string $input): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $input);
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            // Match "<slot_token>=,kvpair,kvpair,..."
            if (! preg_match('/^([a-z_0-9]+)=,(.+)$/i', $line, $m)) {
                continue;
            }

            $slot = self::SLOT_TOKEN_MAP[strtolower($m[1])] ?? null;
            if (! $slot) {
                continue;
            }

            $kv = $this->parseKv($m[2]);
            $itemId = (int) ($kv['id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $bonusIds = [];
            if (! empty($kv['bonus_id'])) {
                foreach (explode('/', $kv['bonus_id']) as $b) {
                    $bid = (int) $b;
                    if ($bid > 0) {
                        $bonusIds[] = $bid;
                    }
                }
            }

            $items[] = [
                'slot'       => $slot,
                'item_id'    => $itemId,
                'item_level' => isset($kv['ilevel']) ? (int) $kv['ilevel'] : null,
                'enchant_id' => isset($kv['enchant_id']) && (int) $kv['enchant_id'] > 0 ? (int) $kv['enchant_id'] : null,
                'bonus_ids'  => $bonusIds,
            ];
        }

        if (empty($items)) {
            throw WishlistImportException::invalidPayload(
                'No equipment lines found in the /simc paste.'
            );
        }

        return $items;
    }

    /**
     * Parse "key=value,key=value" segments into a flat associative array.
     *
     * @return array<string, string>
     */
    private function parseKv(string $segment): array
    {
        $out = [];
        foreach (explode(',', $segment) as $pair) {
            $pos = strpos($pair, '=');
            if ($pos === false) {
                continue;
            }
            $k = trim(substr($pair, 0, $pos));
            $v = trim(substr($pair, $pos + 1));
            if ($k !== '') {
                $out[$k] = $v;
            }
        }

        return $out;
    }
}
