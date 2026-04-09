<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_search_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_face_search_request_id')->nullable()->constrained('event_face_search_requests')->nullOnDelete();
            $table->string('backend_key', 40);
            $table->string('fallback_backend_key', 40)->nullable();
            $table->string('routing_policy', 60)->nullable();
            $table->string('status', 40)->default('queued');
            $table->string('query_media_path', 255)->nullable();
            $table->json('query_face_bbox_json')->nullable();
            $table->unsignedInteger('result_count')->default(0);
            $table->string('error_code', 120)->nullable();
            $table->text('error_message')->nullable();
            $table->json('provider_payload_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status'], 'idx_face_search_queries_event_status');
            $table->index(['event_id', 'backend_key'], 'idx_face_search_queries_event_backend');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_search_queries');
    }
};
