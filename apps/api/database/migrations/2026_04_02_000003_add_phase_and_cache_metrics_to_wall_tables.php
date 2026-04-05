<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_wall_settings', function (Blueprint $table) {
            $table->string('event_phase', 20)
                ->default('flow')
                ->after('selection_mode');
        });

        Schema::table('wall_player_runtime_statuses', function (Blueprint $table) {
            $table->unsignedBigInteger('cache_usage_bytes')->nullable()->after('persistent_storage');
            $table->unsignedBigInteger('cache_quota_bytes')->nullable()->after('cache_usage_bytes');
            $table->unsignedInteger('cache_hit_count')->default(0)->after('cache_quota_bytes');
            $table->unsignedInteger('cache_miss_count')->default(0)->after('cache_hit_count');
            $table->unsignedInteger('cache_stale_fallback_count')->default(0)->after('cache_miss_count');
        });

        Schema::table('wall_diagnostic_summaries', function (Blueprint $table) {
            $table->unsignedInteger('cache_hit_rate_avg')->default(0)->after('persistent_storage_players');
            $table->unsignedBigInteger('cache_usage_bytes_max')->nullable()->after('cache_hit_rate_avg');
            $table->unsignedBigInteger('cache_quota_bytes_max')->nullable()->after('cache_usage_bytes_max');
            $table->unsignedInteger('cache_stale_fallback_count')->default(0)->after('cache_quota_bytes_max');
        });
    }

    public function down(): void
    {
        Schema::table('wall_diagnostic_summaries', function (Blueprint $table) {
            $table->dropColumn([
                'cache_hit_rate_avg',
                'cache_usage_bytes_max',
                'cache_quota_bytes_max',
                'cache_stale_fallback_count',
            ]);
        });

        Schema::table('wall_player_runtime_statuses', function (Blueprint $table) {
            $table->dropColumn([
                'cache_usage_bytes',
                'cache_quota_bytes',
                'cache_hit_count',
                'cache_miss_count',
                'cache_stale_fallback_count',
            ]);
        });

        Schema::table('event_wall_settings', function (Blueprint $table) {
            $table->dropColumn('event_phase');
        });
    }
};
