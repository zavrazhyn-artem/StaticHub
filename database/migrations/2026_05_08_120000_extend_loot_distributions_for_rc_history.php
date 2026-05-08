<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Loot history pivot from event-hook to batch-scan of
     * RCLootCouncilLootDB. RC's own `entry.id` (epoch-counter string,
     * stamped by the master looter at award time) replaces our
     * locally-generated event_uuid as the idempotency key — same
     * approach wowaudit uses.
     *
     * F2 columns added: response_text/color (full RC label, not just
     * the m/o/f bucket), recipient_class + recipient_spec_id (so the
     * UI can render class icons without re-joining characters),
     * item_slot (resolved on backend from season_items), encounter_id
     * (Blizzard journal id for stable boss grouping), council_same_vote
     * (consensus signal), is_award_reason (real award vs disenchant).
     */
    public function up(): void
    {
        Schema::table('loot_distributions', function (Blueprint $t) {
            $t->dropUnique(['event_uuid']);
            $t->dropColumn('event_uuid');

            $t->string('external_id', 64)->after('id')->unique();

            $t->string('response_text', 64)->nullable()->after('method');
            $t->string('response_color', 7)->nullable()->after('response_text');
            $t->string('recipient_class', 16)->nullable()->after('recipient_character_id');
            $t->unsignedInteger('recipient_spec_id')->nullable()->after('recipient_class');
            $t->string('item_slot', 32)->nullable()->after('item_id');
            $t->unsignedInteger('encounter_id')->nullable()->after('boss_name');
            $t->unsignedTinyInteger('council_same_vote')->nullable()->after('response_color');
            $t->boolean('is_award_reason')->default(false)->after('council_same_vote');
        });
    }

    public function down(): void
    {
        Schema::table('loot_distributions', function (Blueprint $t) {
            $t->dropColumn([
                'external_id',
                'response_text',
                'response_color',
                'recipient_class',
                'recipient_spec_id',
                'item_slot',
                'encounter_id',
                'council_same_vote',
                'is_award_reason',
            ]);
            $t->uuid('event_uuid')->unique()->after('id');
        });
    }
};
