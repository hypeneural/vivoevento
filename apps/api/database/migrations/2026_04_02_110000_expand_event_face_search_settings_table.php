<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_face_search_settings', function (Blueprint $table) {
            $table->string('provider_key', 40)->default((string) config('face_search.default_detection_provider', 'noop'))->after('event_id');
            $table->string('embedding_model_key', 120)->default((string) config('face_search.default_embedding_model', 'face-embedding-foundation-v1'))->after('provider_key');
            $table->string('vector_store_key', 40)->default((string) config('face_search.default_vector_store', 'pgvector'))->after('embedding_model_key');
            $table->unsignedInteger('min_face_size_px')->default((int) config('face_search.min_face_size_px', 96))->after('enabled');
            $table->decimal('min_quality_score', 5, 4)->default((float) config('face_search.min_quality_score', 0.60))->after('min_face_size_px');
            $table->decimal('search_threshold', 6, 4)->default((float) config('face_search.search_threshold', 0.35))->after('min_quality_score');
            $table->unsignedInteger('top_k')->default((int) config('face_search.top_k', 50))->after('search_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('event_face_search_settings', function (Blueprint $table) {
            $table->dropColumn([
                'provider_key',
                'embedding_model_key',
                'vector_store_key',
                'min_face_size_px',
                'min_quality_score',
                'search_threshold',
                'top_k',
            ]);
        });
    }
};
