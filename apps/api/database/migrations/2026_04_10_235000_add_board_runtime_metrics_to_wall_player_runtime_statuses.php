<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wall_player_runtime_statuses', function (Blueprint $table): void {
            $table->unsignedSmallInteger('board_piece_count')
                ->nullable()
                ->after('stale_count');
            $table->unsignedInteger('board_burst_count')
                ->default(0)
                ->after('board_piece_count');
            $table->unsignedInteger('board_budget_downgrade_count')
                ->default(0)
                ->after('board_burst_count');
            $table->unsignedInteger('decode_backlog_count')
                ->default(0)
                ->after('board_budget_downgrade_count');
            $table->unsignedInteger('board_reset_count')
                ->default(0)
                ->after('decode_backlog_count');
            $table->string('board_budget_downgrade_reason', 40)
                ->nullable()
                ->after('board_reset_count');
        });
    }

    public function down(): void
    {
        Schema::table('wall_player_runtime_statuses', function (Blueprint $table): void {
            $table->dropColumn([
                'board_piece_count',
                'board_burst_count',
                'board_budget_downgrade_count',
                'decode_backlog_count',
                'board_reset_count',
                'board_budget_downgrade_reason',
            ]);
        });
    }
};
