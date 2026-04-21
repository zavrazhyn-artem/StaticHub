<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Replaced by YAML files in resources/boss-timelines/.
        // Per-plan phase overrides continue to live in raid_plans.timeline.phase_segments.
        Schema::dropIfExists('boss_ability_timings');
        Schema::dropIfExists('boss_phase_segments');
    }

    public function down(): void
    {
        // No rollback — reverting would require recreating two tables whose
        // role has fully moved to resources/boss-timelines/*.yml. Restore
        // via git if you need the old schema back.
    }
};
