<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `recipient_class` was a denormalised snapshot of the recipient's
     * playable_class — fully derivable from the FK `recipient_character_id`
     * → `characters.playable_class`. Drop it; the stats service joins
     * characters anyway. Falling back to white when character_id is null
     * (pug / unrostered alt) is acceptable since we still surface the
     * raw `recipient_name` in that case.
     *
     * `recipient_spec_id` STAYS as a snapshot — class doesn't change but
     * spec might (loot awarded to off-spec etc.) and we want the
     * historic record of which spec asked for the item.
     */
    public function up(): void
    {
        Schema::table('loot_distributions', function (Blueprint $t) {
            $t->dropColumn('recipient_class');
        });
    }

    public function down(): void
    {
        Schema::table('loot_distributions', function (Blueprint $t) {
            $t->string('recipient_class', 16)->nullable()->after('recipient_character_id');
        });
    }
};
