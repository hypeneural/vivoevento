<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_hub_settings', function (Blueprint $table) {
            $table->text('welcome_text')->nullable()->after('subheadline');
            $table->string('hero_image_path')->nullable()->after('welcome_text');
            $table->jsonb('button_style_json')->nullable()->after('show_play_button');
            $table->jsonb('buttons_json')->nullable()->after('button_style_json');
        });
    }

    public function down(): void
    {
        Schema::table('event_hub_settings', function (Blueprint $table) {
            $table->dropColumn([
                'welcome_text',
                'hero_image_path',
                'button_style_json',
                'buttons_json',
            ]);
        });
    }
};
