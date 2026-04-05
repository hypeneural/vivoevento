<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_media_intelligence_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('provider_key', 40);
            $table->string('model_key', 160);
            $table->boolean('enabled')->default(false);
            $table->string('mode', 40)->default('enrich_only');
            $table->string('prompt_version', 100)->nullable();
            $table->text('approval_prompt')->nullable();
            $table->text('caption_style_prompt')->nullable();
            $table->string('response_schema_version', 100)->nullable();
            $table->unsignedInteger('timeout_ms')->default(12000);
            $table->string('fallback_mode', 40)->default('review');
            $table->boolean('require_json_output')->default(true);
            $table->timestamps();

            $table->index(['event_id', 'enabled'], 'idx_event_media_intel_settings_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_media_intelligence_settings');
    }
};
