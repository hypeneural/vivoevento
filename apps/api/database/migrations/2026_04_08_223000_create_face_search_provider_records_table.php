<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_search_provider_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_media_id')->nullable()->constrained('event_media')->cascadeOnDelete();
            $table->string('provider_key', 40);
            $table->string('backend_key', 40);
            $table->string('collection_id', 255)->nullable();
            $table->string('face_id', 255)->nullable();
            $table->string('user_id', 255)->nullable();
            $table->string('image_id', 255)->nullable();
            $table->string('external_image_id', 255)->nullable();
            $table->json('bbox_json')->nullable();
            $table->json('landmarks_json')->nullable();
            $table->json('pose_json')->nullable();
            $table->json('quality_json')->nullable();
            $table->json('unindexed_reasons_json')->nullable();
            $table->boolean('searchable')->default(true);
            $table->timestamp('indexed_at')->nullable();
            $table->json('provider_payload_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'backend_key'], 'idx_face_search_provider_records_event_backend');
            $table->index(['event_media_id', 'backend_key'], 'idx_face_search_provider_records_media_backend');
            $table->index(['collection_id', 'face_id'], 'idx_face_search_provider_records_collection_face');
            $table->index(['event_id', 'external_image_id'], 'idx_face_search_provider_records_event_external_image');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_search_provider_records');
    }
};
