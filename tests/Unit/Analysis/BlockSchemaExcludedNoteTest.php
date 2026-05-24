<?php

namespace Tests\Unit\Analysis;

use App\Services\Analysis\BlockSchema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class BlockSchemaExcludedNoteTest extends TestCase
{
    public function test_pull_allowed_fields_includes_excluded_note(): void
    {
        $const = (new ReflectionClass(BlockSchema::class))->getConstant('PULL_ALLOWED_FIELDS');
        $this->assertIsArray($const);
        $this->assertContains('excluded_note', $const);
    }
}
