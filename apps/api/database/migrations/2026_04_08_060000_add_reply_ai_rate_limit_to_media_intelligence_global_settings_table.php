<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_intelligence_global_settings', function (Blueprint $table) {
            $table->boolean('reply_ai_rate_limit_enabled')->default(false)->after('reply_prompt_preset_id');
            $table->unsignedInteger('reply_ai_rate_limit_max_messages')->default(10)->after('reply_ai_rate_limit_enabled');
            $table->unsignedInteger('reply_ai_rate_limit_window_minutes')->default(10)->after('reply_ai_rate_limit_max_messages');
        });
    }

    public function down(): void
    {
        Schema::table('media_intelligence_global_settings', function (Blueprint $table) {
            $table->dropColumn([
                'reply_ai_rate_limit_enabled',
                'reply_ai_rate_limit_max_messages',
                'reply_ai_rate_limit_window_minutes',
            ]);
        });
    }
};
