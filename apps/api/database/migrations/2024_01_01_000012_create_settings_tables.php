<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_wall_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('status', 30)->default('draft');
            $table->string('layout', 40)->default('auto');
            $table->integer('interval_ms')->default(8000);
            $table->integer('queue_limit')->default(100);
            $table->boolean('show_qr')->default(true);
            $table->boolean('show_branding')->default(true);
            $table->string('background_image_path', 255)->nullable();
            $table->string('partner_logo_path', 255)->nullable();
            $table->text('instructions_text')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_play_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('memory_enabled')->default(true);
            $table->boolean('puzzle_enabled')->default(true);
            $table->integer('memory_card_count')->default(12);
            $table->integer('puzzle_piece_count')->default(9);
            $table->boolean('auto_refresh_assets')->default(true);
            $table->boolean('ranking_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('event_hub_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->string('headline', 180)->nullable();
            $table->string('subheadline', 255)->nullable();
            $table->boolean('show_gallery_button')->default(true);
            $table->boolean('show_upload_button')->default(true);
            $table->boolean('show_wall_button')->default(true);
            $table->boolean('show_play_button')->default(true);
            $table->jsonb('sponsor_json')->nullable();
            $table->jsonb('extra_links_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_hub_settings');
        Schema::dropIfExists('event_play_settings');
        Schema::dropIfExists('event_wall_settings');
    }
};
