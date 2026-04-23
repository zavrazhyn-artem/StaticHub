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
            // Single-tag-per-post. Values enforced at the application layer
            // (FeedbackPost::TAGS) to keep migrations schema-light.
            $table->string('tag', 32)->default('general')->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('feedback_posts', function (Blueprint $table) {
            $table->dropIndex(['tag']);
            $table->dropColumn('tag');
        });
    }
};
