<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_intelligence_global_settings', function (Blueprint $table) {
            $table->foreignId('reply_prompt_preset_id')
                ->nullable()
                ->after('reply_text_fixed_templates_json')
                ->constrained('ai_media_reply_prompt_presets')
                ->nullOnDelete();
        });

        Schema::table('event_media_intelligence_settings', function (Blueprint $table) {
            $table->foreignId('reply_prompt_preset_id')
                ->nullable()
                ->after('reply_fixed_templates_json')
                ->constrained('ai_media_reply_prompt_presets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_media_intelligence_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_prompt_preset_id');
        });

        Schema::table('media_intelligence_global_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_prompt_preset_id');
        });
    }
};
