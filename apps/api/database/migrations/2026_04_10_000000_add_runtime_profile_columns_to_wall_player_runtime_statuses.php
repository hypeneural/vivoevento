<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wall_player_runtime_statuses', function (Blueprint $table): void {
            $table->unsignedSmallInteger('hardware_concurrency')
                ->nullable()
                ->after('current_video_startup_degraded');
            $table->decimal('device_memory_gb', 6, 2)
                ->nullable()
                ->after('hardware_concurrency');
            $table->string('network_effective_type', 20)
                ->nullable()
                ->after('device_memory_gb');
            $table->boolean('network_save_data')
                ->nullable()
                ->after('network_effective_type');
            $table->decimal('network_downlink_mbps', 6, 2)
                ->nullable()
                ->after('network_save_data');
            $table->unsignedInteger('network_rtt_ms')
                ->nullable()
                ->after('network_downlink_mbps');
            $table->boolean('prefers_reduced_motion')
                ->nullable()
                ->after('network_rtt_ms');
            $table->string('document_visibility_state', 20)
                ->nullable()
                ->after('prefers_reduced_motion');
        });
    }

    public function down(): void
    {
        Schema::table('wall_player_runtime_statuses', function (Blueprint $table): void {
            $table->dropColumn([
                'hardware_concurrency',
                'device_memory_gb',
                'network_effective_type',
                'network_save_data',
                'network_downlink_mbps',
                'network_rtt_ms',
                'prefers_reduced_motion',
                'document_visibility_state',
            ]);
        });
    }
};
