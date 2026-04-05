<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_content_moderation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete()->unique();
            $table->string('provider_key', 80)->default('noop');
            $table->string('mode', 40)->default('enforced');
            $table->string('threshold_version', 80)->nullable();
            $table->json('hard_block_thresholds_json')->nullable();
            $table->json('review_thresholds_json')->nullable();
            $table->string('fallback_mode', 40)->default('review');
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('event_media_safety_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_media_id')->constrained('event_media')->cascadeOnDelete();
            $table->string('provider_key', 80)->nullable();
            $table->string('provider_version', 80)->nullable();
            $table->string('model_key', 120)->nullable();
            $table->string('model_snapshot', 120)->nullable();
            $table->string('threshold_version', 80)->nullable();
            $table->string('decision', 40);
            $table->boolean('blocked')->default(false);
            $table->boolean('review_required')->default(false);
            $table->json('category_scores_json')->nullable();
            $table->json('reason_codes_json')->nullable();
            $table->json('raw_response_json')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['event_media_id', 'decision'], 'event_media_safety_eval_media_decision_idx');
            $table->index(['event_id', 'completed_at'], 'event_media_safety_eval_event_completed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_media_safety_evaluations');
        Schema::dropIfExists('event_content_moderation_settings');
    }
};
