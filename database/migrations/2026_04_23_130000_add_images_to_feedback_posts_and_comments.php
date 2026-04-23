<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback_posts', function (Blueprint $table) {
            $table->json('images')->nullable()->after('body');
        });

        Schema::table('feedback_comments', function (Blueprint $table) {
            $table->json('images')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('feedback_posts', function (Blueprint $table) {
            $table->dropColumn('images');
        });

        Schema::table('feedback_comments', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};
