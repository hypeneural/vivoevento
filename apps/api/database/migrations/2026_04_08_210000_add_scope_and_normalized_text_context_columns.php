<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_moderation_global_settings', function (Blueprint $table) {
            $table->string('normalized_text_context_mode', 40)
                ->default('body_plus_caption')
                ->after('analysis_scope');
        });

        Schema::table('event_content_moderation_settings', function (Blueprint $table) {
            $table->string('normalized_text_context_mode', 40)
                ->default('body_plus_caption')
                ->after('analysis_scope');
        });

        Schema::table('event_media_intelligence_settings', function (Blueprint $table) {
            $table->string('context_scope', 40)
                ->default('image_and_text_context')
                ->after('fallback_mode');
            $table->string('reply_scope', 40)
                ->default('image_and_text_context')
                ->after('context_scope');
            $table->string('normalized_text_context_mode', 40)
                ->default('body_plus_caption')
                ->after('reply_scope');
        });

        Schema::table('event_media_safety_evaluations', function (Blueprint $table) {
            $table->text('normalized_text_context')->nullable()->after('request_payload_json');
            $table->string('normalized_text_context_mode', 40)->nullable()->after('normalized_text_context');
        });

        Schema::table('event_media_vlm_evaluations', function (Blueprint $table) {
            $table->text('normalized_text_context')->nullable()->after('request_payload_json');
            $table->string('normalized_text_context_mode', 40)->nullable()->after('normalized_text_context');
        });
    }

    public function down(): void
    {
        Schema::table('event_media_vlm_evaluations', function (Blueprint $table) {
            $table->dropColumn(['normalized_text_context', 'normalized_text_context_mode']);
        });

        Schema::table('event_media_safety_evaluations', function (Blueprint $table) {
            $table->dropColumn(['normalized_text_context', 'normalized_text_context_mode']);
        });

        Schema::table('event_media_intelligence_settings', function (Blueprint $table) {
            $table->dropColumn(['context_scope', 'reply_scope', 'normalized_text_context_mode']);
        });

        Schema::table('event_content_moderation_settings', function (Blueprint $table) {
            $table->dropColumn(['normalized_text_context_mode']);
        });

        Schema::table('content_moderation_global_settings', function (Blueprint $table) {
            $table->dropColumn(['normalized_text_context_mode']);
        });
    }
};
