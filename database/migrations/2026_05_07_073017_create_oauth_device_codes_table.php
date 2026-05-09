<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_device_codes', function (Blueprint $table) {
            $table->id();

            // sha256 of the opaque device_code returned to the client.
            // Plaintext never lives in DB — the bridge holds it in memory
            // until exchange, then discards.
            $table->string('device_code_hash', 64)->unique();

            // User-facing code typed in the browser ("ABCD-1234").
            // 9 chars (8 alphanum + dash). 32^8 = 1.1e12 search space,
            // 600s TTL → collision-safe at any realistic concurrency.
            $table->string('user_code', 9)->unique();

            $table->string('client_name', 80);
            $table->json('scope');

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamp('expires_at')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_device_codes');
    }
};
