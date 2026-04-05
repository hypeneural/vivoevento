<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_media', function (Blueprint $table) {
            $table->string('original_disk', 40)->nullable()->after('original_filename');
            $table->string('original_path', 255)->nullable()->after('original_disk');
            $table->string('client_filename', 255)->nullable()->after('original_path');
            $table->string('safety_status', 40)->nullable()->after('publication_status');
            $table->string('face_index_status', 40)->nullable()->after('safety_status');
            $table->string('vlm_status', 40)->nullable()->after('face_index_status');
            $table->string('pipeline_version', 60)->nullable()->after('vlm_status');
            $table->string('last_pipeline_error_code', 120)->nullable()->after('pipeline_version');
            $table->text('last_pipeline_error_message')->nullable()->after('last_pipeline_error_code');
        });

        Schema::table('media_processing_runs', function (Blueprint $table) {
            $table->string('stage_key', 60)->nullable()->after('run_type');
            $table->string('provider_key', 80)->nullable()->after('stage_key');
            $table->string('model_key', 120)->nullable()->after('provider_key');
            $table->string('input_ref', 255)->nullable()->after('model_key');
            $table->string('decision_key', 60)->nullable()->after('input_ref');
            $table->jsonb('result_json')->nullable()->after('decision_key');
            $table->jsonb('metrics_json')->nullable()->after('result_json');
            $table->string('idempotency_key', 160)->nullable()->after('metrics_json');

            $table->index(['event_media_id', 'stage_key'], 'media_processing_runs_media_stage_idx');
            $table->index('idempotency_key', 'media_processing_runs_idempotency_idx');
        });

        DB::table('event_media')
            ->select(['id', 'original_filename'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $originalFilename = $row->original_filename;

                    if (! is_string($originalFilename) || trim($originalFilename) === '') {
                        continue;
                    }

                    $isStoredPath = str_contains($originalFilename, '/');

                    DB::table('event_media')
                        ->where('id', $row->id)
                        ->update([
                            'original_disk' => $isStoredPath ? 'public' : null,
                            'original_path' => $isStoredPath ? $originalFilename : null,
                            'client_filename' => basename($originalFilename),
                        ]);
                }
            });

        DB::table('media_processing_runs')
            ->whereNull('stage_key')
            ->update([
                'stage_key' => DB::raw('run_type'),
            ]);
    }

    public function down(): void
    {
        Schema::table('media_processing_runs', function (Blueprint $table) {
            $table->dropIndex('media_processing_runs_media_stage_idx');
            $table->dropIndex('media_processing_runs_idempotency_idx');

            $table->dropColumn([
                'stage_key',
                'provider_key',
                'model_key',
                'input_ref',
                'decision_key',
                'result_json',
                'metrics_json',
                'idempotency_key',
            ]);
        });

        Schema::table('event_media', function (Blueprint $table) {
            $table->dropColumn([
                'original_disk',
                'original_path',
                'client_filename',
                'safety_status',
                'face_index_status',
                'vlm_status',
                'pipeline_version',
                'last_pipeline_error_code',
                'last_pipeline_error_message',
            ]);
        });
    }
};
