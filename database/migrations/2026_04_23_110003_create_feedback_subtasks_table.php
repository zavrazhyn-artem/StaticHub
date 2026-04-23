<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_subtasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_post_id')->constrained('feedback_posts')->cascadeOnDelete();
            $table->string('title', 300);
            // todo | in_progress | done
            $table->string('status', 32)->default('todo');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['feedback_post_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_subtasks');
    }
};
