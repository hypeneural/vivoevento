<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_face_search_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('requester_type', 40);
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('queued');
            $table->string('consent_version', 80)->nullable();
            $table->string('selfie_storage_strategy', 40)->default('memory_only');
            $table->unsignedInteger('faces_detected')->default(0);
            $table->decimal('query_face_quality_score', 5, 4)->nullable();
            $table->unsignedInteger('top_k')->default((int) config('face_search.top_k', 50));
            $table->decimal('best_distance', 8, 6)->nullable();
            $table->json('result_photo_ids_json')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status'], 'idx_event_face_search_requests_event_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_face_search_requests');
    }
};
