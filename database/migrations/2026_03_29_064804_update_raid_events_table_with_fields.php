<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('static_id')->after('id')->constrained('statics')->onDelete('cascade');
            $table->string('title')->after('static_id');
            $table->datetime('start_time')->after('title');
            $table->text('description')->nullable()->after('start_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['static_id']);
            $table->dropColumn(['static_id', 'title', 'start_time', 'description']);
        });
    }
};
