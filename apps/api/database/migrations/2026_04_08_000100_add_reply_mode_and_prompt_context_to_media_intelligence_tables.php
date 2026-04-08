<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_media_intelligence_settings', function (Blueprint $table) {
            $table->string('reply_text_mode', 40)->default('disabled')->after('reply_text_enabled');
            $table->json('reply_fixed_templates_json')->nullable()->after('reply_prompt_override');
        });

        Schema::table('media_intelligence_global_settings', function (Blueprint $table) {
            $table->json('reply_text_fixed_templates_json')->nullable()->after('reply_text_prompt');
        });

        Schema::table('event_media_vlm_evaluations', function (Blueprint $table) {
            $table->json('request_payload_json')->nullable()->after('raw_response_json');
            $table->json('prompt_context_json')->nullable()->after('request_payload_json');
        });

        DB::table('event_media_intelligence_settings')
            ->where('reply_text_enabled', true)
            ->update(['reply_text_mode' => 'ai']);
    }

    public function down(): void
    {
        Schema::table('event_media_vlm_evaluations', function (Blueprint $table) {
            $table->dropColumn(['request_payload_json', 'prompt_context_json']);
        });

        Schema::table('media_intelligence_global_settings', function (Blueprint $table) {
            $table->dropColumn(['reply_text_fixed_templates_json']);
        });

        Schema::table('event_media_intelligence_settings', function (Blueprint $table) {
            $table->dropColumn(['reply_text_mode', 'reply_fixed_templates_json']);
        });
    }
};
