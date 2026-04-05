<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_game_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('supports_ranking')->default(true);
            $table->boolean('supports_photo_assets')->default(true);
            $table->jsonb('config_schema_json')->nullable();
            $table->timestamps();
        });

        Schema::create('play_event_games', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_type_id')->constrained('play_game_types')->restrictOnDelete();
            $table->string('title', 150);
            $table->string('slug', 160);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->boolean('ranking_enabled')->default(true);
            $table->jsonb('settings_json')->default('{}');
            $table->timestamps();

            $table->unique(['event_id', 'slug']);
            $table->index(['event_id', 'is_active'], 'idx_play_event_games_event_active');
        });

        Schema::create('play_game_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_game_id')->constrained('play_event_games')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('event_media')->cascadeOnDelete();
            $table->string('role', 40)->default('primary');
            $table->integer('sort_order')->default(0);
            $table->jsonb('metadata_json')->default('{}');
            $table->timestamps();

            $table->unique(['event_game_id', 'media_id']);
            $table->index(['event_game_id', 'sort_order'], 'idx_play_game_assets_event_game');
        });

        Schema::create('play_game_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_game_id')->constrained('play_event_games')->cascadeOnDelete();
            $table->string('player_identifier', 190);
            $table->string('player_name', 120)->nullable();
            $table->string('status', 30);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->jsonb('result_json')->default('{}');
            $table->timestamps();

            $table->index(['event_game_id', 'created_at'], 'idx_play_game_sessions_event_game');
            $table->index(['event_game_id', 'player_identifier'], 'idx_play_game_sessions_player');
        });

        Schema::create('play_game_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained('play_game_sessions')->cascadeOnDelete();
            $table->integer('move_number');
            $table->string('move_type', 40);
            $table->jsonb('payload_json')->default('{}');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['game_session_id', 'move_number'], 'idx_play_game_moves_session');
        });

        Schema::create('play_game_rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_game_id')->constrained('play_event_games')->cascadeOnDelete();
            $table->string('player_identifier', 190);
            $table->string('player_name', 120)->nullable();
            $table->integer('best_score')->default(0);
            $table->integer('best_time_ms')->nullable();
            $table->integer('best_moves')->nullable();
            $table->integer('total_sessions')->default(0);
            $table->integer('total_wins')->default(0);
            $table->timestamp('last_played_at')->nullable();
            $table->jsonb('metrics_json')->default('{}');
            $table->timestamps();

            $table->unique(['event_game_id', 'player_identifier']);
            $table->index(['event_game_id', 'best_score', 'best_time_ms'], 'idx_play_game_rankings_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_game_rankings');
        Schema::dropIfExists('play_game_moves');
        Schema::dropIfExists('play_game_sessions');
        Schema::dropIfExists('play_game_assets');
        Schema::dropIfExists('play_event_games');
        Schema::dropIfExists('play_game_types');
    }
};
