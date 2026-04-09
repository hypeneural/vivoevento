<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_face_search_settings', function (Blueprint $table) {
            $table->boolean('recognition_enabled')->default(false)->after('enabled');
            $table->string('search_backend_key', 40)->default('local_pgvector')->after('selfie_retention_hours');
            $table->string('fallback_backend_key', 40)->nullable()->after('search_backend_key');
            $table->string('routing_policy', 60)->default('local_only')->after('fallback_backend_key');
            $table->unsignedTinyInteger('shadow_mode_percentage')->default(0)->after('routing_policy');
            $table->string('aws_region', 40)->default((string) config('face_search.providers.aws_rekognition.region', 'eu-central-1'))->after('shadow_mode_percentage');
            $table->string('aws_collection_id', 255)->nullable()->after('aws_region');
            $table->string('aws_collection_arn', 255)->nullable()->after('aws_collection_id');
            $table->string('aws_face_model_version', 32)->nullable()->after('aws_collection_arn');
            $table->string('aws_search_mode', 20)->default('faces')->after('aws_face_model_version');
            $table->string('aws_index_quality_filter', 20)->default((string) config('face_search.providers.aws_rekognition.index_quality_filter', 'AUTO'))->after('aws_search_mode');
            $table->string('aws_search_faces_quality_filter', 20)->default((string) config('face_search.providers.aws_rekognition.search_faces_quality_filter', 'NONE'))->after('aws_index_quality_filter');
            $table->string('aws_search_users_quality_filter', 20)->default((string) config('face_search.providers.aws_rekognition.search_users_quality_filter', 'NONE'))->after('aws_search_faces_quality_filter');
            $table->decimal('aws_search_face_match_threshold', 5, 2)->default((float) config('face_search.providers.aws_rekognition.search_face_match_threshold', 80))->after('aws_search_users_quality_filter');
            $table->decimal('aws_search_user_match_threshold', 5, 2)->default((float) config('face_search.providers.aws_rekognition.search_user_match_threshold', 80))->after('aws_search_face_match_threshold');
            $table->decimal('aws_associate_user_match_threshold', 5, 2)->default((float) config('face_search.providers.aws_rekognition.associate_user_match_threshold', 75))->after('aws_search_user_match_threshold');
            $table->unsignedSmallInteger('aws_max_faces_per_image')->default(100)->after('aws_associate_user_match_threshold');
            $table->string('aws_index_profile_key', 80)->default('social_gallery_event')->after('aws_max_faces_per_image');
            $table->json('aws_detection_attributes_json')->nullable()->after('aws_index_profile_key');
            $table->boolean('delete_remote_vectors_on_event_close')->default(false)->after('aws_detection_attributes_json');
        });
    }

    public function down(): void
    {
        Schema::table('event_face_search_settings', function (Blueprint $table) {
            $table->dropColumn([
                'recognition_enabled',
                'search_backend_key',
                'fallback_backend_key',
                'routing_policy',
                'shadow_mode_percentage',
                'aws_region',
                'aws_collection_id',
                'aws_collection_arn',
                'aws_face_model_version',
                'aws_search_mode',
                'aws_index_quality_filter',
                'aws_search_faces_quality_filter',
                'aws_search_users_quality_filter',
                'aws_search_face_match_threshold',
                'aws_search_user_match_threshold',
                'aws_associate_user_match_threshold',
                'aws_max_faces_per_image',
                'aws_index_profile_key',
                'aws_detection_attributes_json',
                'delete_remote_vectors_on_event_close',
            ]);
        });
    }
};
