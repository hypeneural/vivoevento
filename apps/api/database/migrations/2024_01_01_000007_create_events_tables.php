<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->string('upload_slug', 60)->unique()->nullable();
            $table->string('event_type', 40);
            $table->string('status', 30)->default('draft');
            $table->string('visibility', 30)->default('public');
            $table->string('moderation_mode', 30)->default('manual');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('location_name', 180)->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image_path', 255)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('qr_code_path', 255)->nullable();
            $table->string('primary_color', 20)->nullable();
            $table->string('secondary_color', 20)->nullable();
            $table->string('public_url', 255)->nullable();
            $table->string('upload_url', 255)->nullable();
            $table->integer('retention_days')->default(30);
            $table->jsonb('purchased_plan_snapshot_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('module_key', 40);
            $table->boolean('is_enabled')->default(true);
            $table->jsonb('settings_json')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'module_key']);
        });

        Schema::create('event_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('image_path', 255);
            $table->string('link_url', 255)->nullable();
            $table->string('alt_text', 180)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_banners');
        Schema::dropIfExists('event_modules');
        Schema::dropIfExists('events');
    }
};
