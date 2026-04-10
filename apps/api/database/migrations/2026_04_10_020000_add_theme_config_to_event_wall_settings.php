<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_wall_settings', function (Blueprint $table): void {
            $table->jsonb('theme_config')->nullable()->after('selection_policy');
        });
    }

    public function down(): void
    {
        Schema::table('event_wall_settings', function (Blueprint $table): void {
            $table->dropColumn('theme_config');
        });
    }
};
