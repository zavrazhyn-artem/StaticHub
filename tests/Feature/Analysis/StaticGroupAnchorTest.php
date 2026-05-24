<?php

namespace Tests\Feature\Analysis;

use App\Models\StaticGroup;
use Tests\TestCase;

class StaticGroupAnchorTest extends TestCase
{
    public function test_returns_configured_anchor_ids_as_ints(): void
    {
        $s = new StaticGroup();
        $s->automation_settings = ['intent_anchor_character_ids' => ['5', 9]];
        $this->assertSame([5, 9], $s->intentAnchorCharacterIds());
    }

    public function test_returns_empty_when_unset(): void
    {
        $s = new StaticGroup();
        $s->automation_settings = [];
        $this->assertSame([], $s->intentAnchorCharacterIds());
    }
}
