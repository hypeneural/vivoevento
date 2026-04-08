<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_media_reply_test_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('trace_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('preset_id')->nullable()->constrained('ai_media_reply_prompt_presets')->nullOnDelete();
            $table->string('provider_key', 60);
            $table->string('model_key', 160);
            $table->string('status', 40);
            $table->text('prompt_template')->nullable();
            $table->text('prompt_resolved')->nullable();
            $table->json('prompt_variables_json')->nullable();
            $table->json('images_json')->nullable();
            $table->json('request_payload_json')->nullable();
            $table->json('response_payload_json')->nullable();
            $table->text('response_text')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_media_reply_test_runs');
    }
};
