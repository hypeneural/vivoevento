<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wall_display_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_wall_setting_id')
                ->unique()
                ->constrained('event_wall_settings')
                ->cascadeOnDelete();
            $table->unsignedInteger('displayed_count')->default(0);
            $table->string('current_item_id', 120)->nullable();
            $table->timestamp('current_item_started_at')->nullable();
            $table->string('last_player_instance_id', 120)->nullable();
            $table->timestamp('last_counted_at')->nullable();
            $table->timestamps();

            $table->index('current_item_started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wall_display_counters');
    }
};
