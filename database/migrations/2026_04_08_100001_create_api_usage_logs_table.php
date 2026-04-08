<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service')->index();
            $table->string('endpoint');
            $table->string('method', 10);
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('response_time_ms');
            $table->unsignedInteger('rate_limit_remaining')->nullable();
            $table->unsignedInteger('rate_limit_limit')->nullable();
            $table->timestamp('rate_limit_reset_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['service', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_logs');
    }
};
