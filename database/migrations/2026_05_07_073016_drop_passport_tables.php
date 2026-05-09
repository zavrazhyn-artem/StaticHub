<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Removes leftover Passport tables. Passport (laravel/passport) was
     * present in vendor/ but never referenced from app code; it was
     * uninstalled in favor of Sanctum for the BlastR Desktop bridge auth.
     *
     * All token tables verified empty at migration time; oauth_clients
     * held two seed rows from `passport:install` with no code references.
     */
    public function up(): void
    {
        // Order matters — drop child tables before parents (FKs).
        Schema::dropIfExists('oauth_personal_access_clients');
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_refresh_tokens');
        Schema::dropIfExists('oauth_auth_codes');
        Schema::dropIfExists('oauth_device_codes');
        Schema::dropIfExists('oauth_clients');

        DB::table('migrations')->whereIn('migration', [
            '2026_04_23_093917_create_oauth_auth_codes_table',
            '2026_04_23_093918_create_oauth_access_tokens_table',
            '2026_04_23_093919_create_oauth_refresh_tokens_table',
            '2026_04_23_093920_create_oauth_clients_table',
            '2026_04_23_093921_create_oauth_device_codes_table',
        ])->delete();
    }

    public function down(): void
    {
        // Not reversible — the laravel/passport package is no longer
        // installed, so its migration files are gone. To restore Passport,
        // re-add the package and run its own migrations.
    }
};
