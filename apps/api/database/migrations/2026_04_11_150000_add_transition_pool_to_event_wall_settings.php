<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('event_wall_settings', 'transition_pool')) {
            return;
        }

        Schema::table('event_wall_settings', function (Blueprint $table): void {
            $table->json('transition_pool')->nullable()->after('transition_mode');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('event_wall_settings', 'transition_pool')) {
            return;
        }

        Schema::table('event_wall_settings', function (Blueprint $table): void {
            $table->dropColumn('transition_pool');
        });
    }
};
