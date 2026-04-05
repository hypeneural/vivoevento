<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('play_game_sessions', function (Blueprint $table) {
            $table->string('resume_token', 120)->nullable()->after('player_name');
            $table->timestamp('last_activity_at')->nullable()->after('started_at');
            $table->timestamp('expires_at')->nullable()->after('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::table('play_game_sessions', function (Blueprint $table) {
            $table->dropColumn(['resume_token', 'last_activity_at', 'expires_at']);
        });
    }
};
