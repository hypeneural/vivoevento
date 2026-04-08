<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_moderation_global_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider_key', 80)->default('openai');
            $table->string('mode', 40)->default('enforced');
            $table->string('threshold_version', 80)->nullable();
            $table->json('hard_block_thresholds_json')->nullable();
            $table->json('review_thresholds_json')->nullable();
            $table->string('fallback_mode', 40)->default('review');
            $table->string('analysis_scope', 40)->default('image_and_text_context');
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        Schema::table('event_content_moderation_settings', function (Blueprint $table) {
            $table->string('analysis_scope', 40)
                ->default('image_and_text_context')
                ->after('fallback_mode');
        });
    }

    public function down(): void
    {
        Schema::table('event_content_moderation_settings', function (Blueprint $table) {
            $table->dropColumn('analysis_scope');
        });

        Schema::dropIfExists('content_moderation_global_settings');
    }
};
