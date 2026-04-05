<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $dimension = (int) config('face_search.embedding_dimension', 512);

        Schema::create('event_media_faces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_media_id')->constrained('event_media')->cascadeOnDelete();
            $table->unsignedInteger('face_index');
            $table->unsignedInteger('bbox_x');
            $table->unsignedInteger('bbox_y');
            $table->unsignedInteger('bbox_w');
            $table->unsignedInteger('bbox_h');
            $table->decimal('detection_confidence', 5, 4)->nullable();
            $table->decimal('quality_score', 5, 4)->nullable();
            $table->decimal('sharpness_score', 5, 4)->nullable();
            $table->decimal('face_area_ratio', 6, 4)->nullable();
            $table->decimal('pose_yaw', 6, 2)->nullable();
            $table->decimal('pose_pitch', 6, 2)->nullable();
            $table->decimal('pose_roll', 6, 2)->nullable();
            $table->boolean('searchable')->default(false);
            $table->string('crop_disk', 40)->nullable();
            $table->string('crop_path')->nullable();
            $table->string('embedding_model_key', 120)->nullable();
            $table->string('embedding_version', 80)->nullable();
            $table->string('vector_store_key', 40)->nullable();
            $table->string('vector_ref')->nullable();
            $table->string('face_hash', 80)->nullable();
            $table->boolean('is_primary_face_candidate')->default(false);
            $table->timestamps();

            $table->index('event_id', 'idx_event_media_faces_event_id');
            $table->index('event_media_id', 'idx_event_media_faces_media_id');
            $table->index(['event_id', 'searchable'], 'idx_event_media_faces_searchable');
            $table->index(['event_media_id', 'face_hash'], 'idx_event_media_faces_media_hash');
        });

        if ($driver === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement("ALTER TABLE event_media_faces ADD COLUMN embedding vector({$dimension})");
            DB::statement('CREATE INDEX idx_event_media_faces_embedding_hnsw ON event_media_faces USING hnsw (embedding vector_cosine_ops)');
        } else {
            Schema::table('event_media_faces', function (Blueprint $table) {
                $table->text('embedding')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_media_faces');
    }
};
