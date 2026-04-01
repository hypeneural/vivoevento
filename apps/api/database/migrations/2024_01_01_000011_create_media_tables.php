<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inbound_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('media_type', 20)->default('image');
            $table->string('source_type', 40)->default('channel');
            $table->string('source_label', 120)->nullable();
            $table->string('title', 180)->nullable();
            $table->text('caption')->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->string('processing_status', 40)->default('received');
            $table->string('moderation_status', 40)->default('pending');
            $table->string('publication_status', 40)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('event_media_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_media_id')->constrained('event_media')->cascadeOnDelete();
            $table->string('variant_key', 60);
            $table->string('disk', 40)->default('public');
            $table->string('path', 255);
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->timestamps();

            $table->unique(['event_media_id', 'variant_key']);
        });

        Schema::create('media_processing_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_media_id')->constrained('event_media')->cascadeOnDelete();
            $table->string('run_type', 60);
            $table->string('status', 30)->default('queued');
            $table->integer('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_processing_runs');
        Schema::dropIfExists('event_media_variants');
        Schema::dropIfExists('event_media');
    }
};
