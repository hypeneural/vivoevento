<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_gallery_settings', function (Blueprint $table): void {
            $table->jsonb('current_preset_origin_json')->nullable()->after('media_behavior_json');
        });
    }

    public function down(): void
    {
        Schema::table('event_gallery_settings', function (Blueprint $table): void {
            $table->dropColumn('current_preset_origin_json');
        });
    }
};
