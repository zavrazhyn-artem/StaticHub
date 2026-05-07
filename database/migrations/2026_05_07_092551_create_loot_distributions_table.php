<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Loot awards captured by RCLootCouncil in-game and pushed back to
     * blastr.pro by the BlastR Desktop bridge. Idempotent on
     * `event_uuid` — the addon stamps every event with a UUID at write
     * time so retries (network blip mid-push) cannot create duplicates.
     */
    public function up(): void
    {
        Schema::create('loot_distributions', function (Blueprint $t) {
            $t->id();
            $t->uuid('event_uuid')->unique();

            $t->foreignId('static_id')->constrained('statics')->cascadeOnDelete();

            $t->timestamp('awarded_at')->index();

            $t->string('raid_slug', 64);
            $t->string('raid_difficulty', 4); // M | H | N | R
            $t->string('boss_name', 128)->nullable();
            $t->string('pull_id', 64)->nullable();

            $t->string('recipient_name', 128);
            $t->foreignId('recipient_character_id')
                ->nullable()->constrained('characters')->nullOnDelete();

            $t->string('awarded_by_name', 128)->nullable();

            $t->unsignedInteger('item_id')->index();
            $t->unsignedSmallInteger('item_level')->nullable();
            $t->json('bonus_ids')->nullable();
            $t->unsignedInteger('enchant_id')->nullable();
            $t->json('gem_ids')->nullable();

            // bis | ms | os | trash | free — RC's award method label
            $t->string('method', 16)->index();

            $t->text('note')->nullable();

            // Server-clock timestamp for audit; awarded_at is wall-clock
            // from the loot master's machine.
            $t->timestamp('received_at')->useCurrent();

            $t->timestamps();

            $t->index(['static_id', 'awarded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loot_distributions');
    }
};
