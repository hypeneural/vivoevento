<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_wall_settings', function (Blueprint $table) {
            $table->boolean('video_enabled')
                ->default(true)
                ->after('accepted_orientation');
            $table->string('video_playback_mode', 40)
                ->default('play_to_end_if_short_else_cap')
                ->after('video_enabled');
            $table->unsignedInteger('video_max_seconds')
                ->default(30)
                ->after('video_playback_mode');
            $table->string('video_resume_mode', 40)
                ->default('resume_if_same_item_else_restart')
                ->after('video_max_seconds');
            $table->string('video_audio_policy', 20)
                ->default('muted')
                ->after('video_resume_mode');
            $table->string('video_multi_layout_policy', 20)
                ->default('disallow')
                ->after('video_audio_policy');
            $table->string('video_preferred_variant', 40)
                ->default('wall_video_720p')
                ->after('video_multi_layout_policy');
        });

        Schema::table('wall_player_runtime_statuses', function (Blueprint $table) {
            $table->string('current_media_type', 20)->nullable()->after('current_sender_key');
            $table->string('current_video_phase', 40)->nullable()->after('current_media_type');
            $table->string('current_video_exit_reason', 60)->nullable()->after('current_video_phase');
            $table->string('current_video_failure_reason', 60)->nullable()->after('current_video_exit_reason');
            $table->decimal('current_video_position_seconds', 8, 2)->nullable()->after('current_video_failure_reason');
            $table->decimal('current_video_duration_seconds', 8, 2)->nullable()->after('current_video_position_seconds');
            $table->unsignedTinyInteger('current_video_ready_state')->nullable()->after('current_video_duration_seconds');
            $table->unsignedInteger('current_video_stall_count')->default(0)->after('current_video_ready_state');
            $table->boolean('current_video_poster_visible')->nullable()->after('current_video_stall_count');
            $table->boolean('current_video_first_frame_ready')->nullable()->after('current_video_poster_visible');
            $table->boolean('current_video_playback_ready')->nullable()->after('current_video_first_frame_ready');
            $table->boolean('current_video_playing_confirmed')->nullable()->after('current_video_playback_ready');
            $table->boolean('current_video_startup_degraded')->nullable()->after('current_video_playing_confirmed');
        });
    }

    public function down(): void
    {
        Schema::table('wall_player_runtime_statuses', function (Blueprint $table) {
            $table->dropColumn([
                'current_media_type',
                'current_video_phase',
                'current_video_exit_reason',
                'current_video_failure_reason',
                'current_video_position_seconds',
                'current_video_duration_seconds',
                'current_video_ready_state',
                'current_video_stall_count',
                'current_video_poster_visible',
                'current_video_first_frame_ready',
                'current_video_playback_ready',
                'current_video_playing_confirmed',
                'current_video_startup_degraded',
            ]);
        });

        Schema::table('event_wall_settings', function (Blueprint $table) {
            $table->dropColumn([
                'video_enabled',
                'video_playback_mode',
                'video_max_seconds',
                'video_resume_mode',
                'video_audio_policy',
                'video_multi_layout_policy',
                'video_preferred_variant',
            ]);
        });
    }
};
