<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wall_player_runtime_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_wall_setting_id')
                ->constrained('event_wall_settings')
                ->cascadeOnDelete();
            $table->string('player_instance_id', 120);
            $table->string('runtime_status', 40);
            $table->string('connection_status', 40);
            $table->string('current_item_id', 120)->nullable();
            $table->string('current_sender_key', 180)->nullable();
            $table->unsignedInteger('ready_count')->default(0);
            $table->unsignedInteger('loading_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('stale_count')->default(0);
            $table->boolean('cache_enabled')->default(false);
            $table->string('persistent_storage', 40)->default('none');
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->string('last_fallback_reason', 120)->nullable();
            $table->timestamps();

            $table->unique(['event_wall_setting_id', 'player_instance_id'], 'wall_player_runtime_unique');
            $table->index('last_heartbeat_at');
        });

        Schema::create('wall_diagnostic_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_wall_setting_id')
                ->unique()
                ->constrained('event_wall_settings')
                ->cascadeOnDelete();
            $table->string('health_status', 40)->default('idle');
            $table->unsignedInteger('total_players')->default(0);
            $table->unsignedInteger('online_players')->default(0);
            $table->unsignedInteger('offline_players')->default(0);
            $table->unsignedInteger('degraded_players')->default(0);
            $table->unsignedInteger('ready_count')->default(0);
            $table->unsignedInteger('loading_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('stale_count')->default(0);
            $table->unsignedInteger('cache_enabled_players')->default(0);
            $table->unsignedInteger('persistent_storage_players')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wall_diagnostic_summaries');
        Schema::dropIfExists('wall_player_runtime_statuses');
    }
};
