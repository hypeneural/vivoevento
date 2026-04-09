<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wall_player_runtime_statuses', function (Blueprint $table) {
            $table->timestamp('current_item_started_at')
                ->nullable()
                ->after('current_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('wall_player_runtime_statuses', function (Blueprint $table) {
            $table->dropColumn('current_item_started_at');
        });
    }
};
