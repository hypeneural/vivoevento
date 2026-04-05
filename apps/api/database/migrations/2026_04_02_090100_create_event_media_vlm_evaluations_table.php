<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_media_vlm_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_media_id')->constrained('event_media')->cascadeOnDelete();
            $table->string('provider_key', 40)->nullable();
            $table->string('provider_version', 80)->nullable();
            $table->string('model_key', 160)->nullable();
            $table->string('model_snapshot', 160)->nullable();
            $table->string('prompt_version', 100)->nullable();
            $table->string('response_schema_version', 100)->nullable();
            $table->string('mode_applied', 40)->nullable();
            $table->string('decision', 40);
            $table->boolean('review_required')->default(false);
            $table->text('reason')->nullable();
            $table->string('short_caption', 255)->nullable();
            $table->jsonb('tags_json')->nullable();
            $table->jsonb('raw_response_json')->nullable();
            $table->unsignedInteger('tokens_input')->nullable();
            $table->unsignedInteger('tokens_output')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'event_media_id'], 'idx_event_media_vlm_event_media');
            $table->index(['event_id', 'decision'], 'idx_event_media_vlm_event_decision');
            $table->index(['event_media_id', 'completed_at'], 'idx_event_media_vlm_completed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_media_vlm_evaluations');
    }
};
