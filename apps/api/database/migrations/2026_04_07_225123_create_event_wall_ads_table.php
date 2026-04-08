<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_wall_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_wall_setting_id')
                ->constrained('event_wall_settings')
                ->cascadeOnDelete();
            $table->string('file_path');
            $table->string('media_type', 10); // image | video
            $table->unsignedSmallInteger('duration_seconds')->default(10);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['event_wall_setting_id', 'is_active', 'position']);
        });

        // Add ad settings fields to event_wall_settings
        Schema::table('event_wall_settings', function (Blueprint $table) {
            $table->string('ad_mode', 20)->default('disabled')->after('accepted_orientation');
            $table->unsignedSmallInteger('ad_frequency')->default(5)->after('ad_mode');
            $table->unsignedSmallInteger('ad_interval_minutes')->default(3)->after('ad_frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_wall_settings', function (Blueprint $table) {
            $table->dropColumn(['ad_mode', 'ad_frequency', 'ad_interval_minutes']);
        });

        Schema::dropIfExists('event_wall_ads');
    }
};
