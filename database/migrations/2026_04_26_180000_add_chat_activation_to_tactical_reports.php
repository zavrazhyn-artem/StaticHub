<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->timestamp('chat_activated_at')->nullable()->after('gemini_cache_expires_at');
            $table->timestamp('chat_active_until')->nullable()->after('chat_activated_at');
        });
    }

    public function down(): void
    {
        Schema::table('tactical_reports', function (Blueprint $table) {
            $table->dropColumn(['chat_activated_at', 'chat_active_until']);
        });
    }
};
