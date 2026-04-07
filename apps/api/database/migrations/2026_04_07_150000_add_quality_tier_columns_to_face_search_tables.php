<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_media_faces', function (Blueprint $table) {
            if (! Schema::hasColumn('event_media_faces', 'quality_tier')) {
                $table->string('quality_tier', 30)->nullable()->after('quality_score');
            }

            if (! Schema::hasColumn('event_media_faces', 'quality_rejection_reason')) {
                $table->string('quality_rejection_reason', 60)->nullable()->after('quality_tier');
            }
        });

        Schema::table('event_face_search_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('event_face_search_requests', 'query_face_quality_tier')) {
                $table->string('query_face_quality_tier', 30)->nullable()->after('query_face_quality_score');
            }

            if (! Schema::hasColumn('event_face_search_requests', 'query_face_rejection_reason')) {
                $table->string('query_face_rejection_reason', 60)->nullable()->after('query_face_quality_tier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_face_search_requests', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('event_face_search_requests', 'query_face_quality_tier')) {
                $columns[] = 'query_face_quality_tier';
            }

            if (Schema::hasColumn('event_face_search_requests', 'query_face_rejection_reason')) {
                $columns[] = 'query_face_rejection_reason';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('event_media_faces', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('event_media_faces', 'quality_tier')) {
                $columns[] = 'quality_tier';
            }

            if (Schema::hasColumn('event_media_faces', 'quality_rejection_reason')) {
                $columns[] = 'quality_rejection_reason';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
