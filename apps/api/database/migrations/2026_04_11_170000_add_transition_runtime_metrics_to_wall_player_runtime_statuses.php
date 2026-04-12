<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wall_player_runtime_statuses', function (Blueprint $table): void {
            $table->string('active_transition_effect', 40)
                ->nullable()
                ->after('current_item_started_at');
            $table->string('transition_mode', 20)
                ->nullable()
                ->after('active_transition_effect');
            $table->unsignedInteger('transition_random_pick_count')
                ->default(0)
                ->after('transition_mode');
            $table->unsignedInteger('transition_fallback_count')
                ->default(0)
                ->after('transition_random_pick_count');
            $table->string('transition_last_fallback_reason', 40)
                ->nullable()
                ->after('transition_fallback_count');
        });
    }

    public function down(): void
    {
        Schema::table('wall_player_runtime_statuses', function (Blueprint $table): void {
            $table->dropColumn([
                'active_transition_effect',
                'transition_mode',
                'transition_random_pick_count',
                'transition_fallback_count',
                'transition_last_fallback_reason',
            ]);
        });
    }
};
