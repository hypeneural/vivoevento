<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_media_intelligence_settings', function (Blueprint $table) {
            $table->boolean('reply_text_enabled')->default(false)->after('require_json_output');
            $table->text('reply_prompt_override')->nullable()->after('reply_text_enabled');
        });

        Schema::table('event_media_vlm_evaluations', function (Blueprint $table) {
            $table->text('reply_text')->nullable()->after('short_caption');
        });
    }

    public function down(): void
    {
        Schema::table('event_media_vlm_evaluations', function (Blueprint $table) {
            $table->dropColumn('reply_text');
        });

        Schema::table('event_media_intelligence_settings', function (Blueprint $table) {
            $table->dropColumn(['reply_text_enabled', 'reply_prompt_override']);
        });
    }
};
