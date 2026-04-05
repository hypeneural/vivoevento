<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('play_game_moves', function (Blueprint $table) {
            $table->unique(['game_session_id', 'move_number'], 'play_game_moves_session_move_unique');
        });
    }

    public function down(): void
    {
        Schema::table('play_game_moves', function (Blueprint $table) {
            $table->dropUnique('play_game_moves_session_move_unique');
        });
    }
};
