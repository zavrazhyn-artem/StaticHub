<?php

namespace Tests\Feature\Gear;

use App\Models\Character;
use App\Services\Gear\GearViewService;
use Tests\TestCase;

class GearReadinessSummaryTest extends TestCase
{
    /** Build a minimal Blizzard-shaped equipped item. */
    private function item(string $slot, int $ilvl, array $opts = []): array
    {
        return [
            'slot'         => ['type' => $slot],
            'item'         => ['id' => $opts['id'] ?? (1000 + $ilvl)],
            'name'         => $opts['name'] ?? ($slot.' Item'),
            'level'        => ['value' => $ilvl],
            'quality'      => ['type' => 'EPIC'],
            'enchantments' => $opts['enchantments'] ?? [],
            'sockets'      => $opts['sockets'] ?? [],
            'bonus_list'   => [],
        ];
    }

    private function character(): Character
    {
        // Non-enchantable slots (NECK/BACK/WRIST/HANDS/WAIST/TRINKET_*) avoid
        // entangling the weakest-slot + socket checks with enchant logic.
        $equipped = [
            $this->item('CHEST', 276),                                   // enchantable, no enchant -> missing
            $this->item('NECK', 289),
            $this->item('BACK', 276),
            $this->item('WRIST', 250, ['name' => 'Weak Bracers']),        // weakest
            $this->item('HANDS', 282, ['sockets' => [                     // one empty socket
                ['socket_type' => ['type' => 'PRISMATIC'], 'item' => null],
            ]]),
            $this->item('WAIST', 278),
            $this->item('TRINKET_1', 298),
            $this->item('TRINKET_2', 298),
        ];

        $char = new Character();
        $char->bnet_equipment_by_spec = ['Elemental' => ['equipped_items' => $equipped]];
        $char->equipped_item_level = 280;

        return $char;
    }

    public function test_builds_readiness_summary_from_equipped_items(): void
    {
        $svc = app(GearViewService::class);

        $r = $svc->buildReadinessSummary($this->character(), 'Elemental');

        $this->assertNotNull($r);
        $this->assertSame(280, $r['ilvl']);

        // Missing enchant: CHEST (lowercased slot + item name)
        $this->assertSame([['slot' => 'chest', 'name' => 'CHEST Item']], $r['missing_enchants']);

        $this->assertSame(1, $r['empty_sockets']);

        // Weakest: WRIST 250, well below the median -> flagged
        $this->assertNotNull($r['weakest']);
        $this->assertSame('wrist', $r['weakest']['slot']);
        $this->assertSame(250, $r['weakest']['ilvl']);
        $this->assertLessThan(0, $r['weakest']['delta']);

        // problems = 1 missing + 0 low-quality + 1 sockets + 1 weakest
        $this->assertSame(3, $r['problems']);

        $this->assertSame(count(config('wow_season.tier_slots')), $r['tier_total']);
        $this->assertSame(0, $r['tier_have']); // no set pieces in the fixture
    }

    public function test_returns_null_when_spec_has_no_equipment(): void
    {
        $char = new Character();
        $char->bnet_equipment_by_spec = [];

        $this->assertNull(app(GearViewService::class)->buildReadinessSummary($char, 'Elemental'));
        $this->assertNull(app(GearViewService::class)->buildReadinessSummary($char, null));
    }

    public function test_excludes_cosmetic_slots_from_weakest(): void
    {
        // Cosmetic TABARD/SHIRT sit at ilvl 1 and must never be the "weakest gear slot".
        $char = $this->character();
        $equipped = $char->bnet_equipment_by_spec['Elemental']['equipped_items'];
        $equipped[] = $this->item('TABARD', 1, ['name' => 'Some Tabard']);
        $equipped[] = $this->item('SHIRT', 1, ['name' => 'Some Shirt']);
        $char->bnet_equipment_by_spec = ['Elemental' => ['equipped_items' => $equipped]];

        $r = app(GearViewService::class)->buildReadinessSummary($char, 'Elemental');

        $this->assertSame('wrist', $r['weakest']['slot']);
        $this->assertSame(250, $r['weakest']['ilvl']);
    }
}
