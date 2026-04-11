<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('slug');
            $table->string('type', 40)->default('guest');
            $table->string('side', 40)->default('neutral');
            $table->foreignId('avatar_media_id')->nullable()->constrained('event_media')->nullOnDelete();
            $table->foreignId('avatar_face_id')->nullable()->constrained('event_media_faces')->nullOnDelete();
            $table->unsignedInteger('importance_rank')->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 40)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'slug'], 'event_people_event_slug_unique');
            $table->index(['event_id', 'status'], 'event_people_event_status_idx');
            $table->index(['event_id', 'type'], 'event_people_event_type_idx');
            $table->index(['event_id', 'importance_rank'], 'event_people_event_importance_idx');
        });

        Schema::create('event_person_face_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_person_id')->constrained('event_people')->cascadeOnDelete();
            $table->foreignId('event_media_face_id')->constrained('event_media_faces')->cascadeOnDelete();
            $table->string('source', 40)->default('manual_confirmed');
            $table->decimal('confidence', 6, 4)->nullable();
            $table->string('status', 40)->default('confirmed');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'event_person_id', 'status'], 'event_person_face_assignments_person_status_idx');
            $table->index(['event_id', 'event_media_face_id'], 'event_person_face_assignments_face_idx');
        });

        Schema::create('event_person_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_a_id')->constrained('event_people')->cascadeOnDelete();
            $table->foreignId('person_b_id')->constrained('event_people')->cascadeOnDelete();
            $table->string('person_pair_key', 80);
            $table->string('relation_type', 60);
            $table->string('directionality', 20)->default('undirected');
            $table->string('source', 40)->default('manual');
            $table->decimal('confidence', 6, 4)->nullable();
            $table->decimal('strength', 6, 4)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'person_pair_key', 'relation_type'], 'event_person_relations_pair_type_unique');
            $table->index(['event_id', 'person_a_id'], 'event_person_relations_person_a_idx');
            $table->index(['event_id', 'person_b_id'], 'event_person_relations_person_b_idx');
            $table->index(['event_id', 'relation_type'], 'event_person_relations_type_idx');
        });

        Schema::create('event_person_cooccurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_a_id')->constrained('event_people')->cascadeOnDelete();
            $table->foreignId('person_b_id')->constrained('event_people')->cascadeOnDelete();
            $table->string('person_pair_key', 80);
            $table->unsignedInteger('co_photo_count')->default(0);
            $table->unsignedInteger('solo_photo_count_a')->default(0);
            $table->unsignedInteger('solo_photo_count_b')->default(0);
            $table->decimal('average_face_distance', 8, 4)->nullable();
            $table->decimal('weighted_score', 8, 4)->default(0);
            $table->timestamp('last_seen_together_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'person_pair_key'], 'event_person_cooccurrences_pair_unique');
            $table->index(['event_id', 'person_a_id', 'weighted_score'], 'event_person_cooccurrences_person_a_score_idx');
            $table->index(['event_id', 'person_b_id', 'weighted_score'], 'event_person_cooccurrences_person_b_score_idx');
        });

        Schema::create('event_person_media_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_person_id')->constrained('event_people')->cascadeOnDelete();
            $table->unsignedInteger('media_count')->default(0);
            $table->unsignedInteger('solo_media_count')->default(0);
            $table->unsignedInteger('with_others_media_count')->default(0);
            $table->unsignedInteger('published_media_count')->default(0);
            $table->unsignedInteger('pending_media_count')->default(0);
            $table->foreignId('best_media_id')->nullable()->constrained('event_media')->nullOnDelete();
            $table->foreignId('latest_media_id')->nullable()->constrained('event_media')->nullOnDelete();
            $table->timestamp('projected_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'event_person_id'], 'event_person_media_stats_person_unique');
        });

        Schema::create('event_person_pair_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_a_id')->constrained('event_people')->cascadeOnDelete();
            $table->foreignId('person_b_id')->constrained('event_people')->cascadeOnDelete();
            $table->string('person_pair_key', 80);
            $table->unsignedInteger('co_media_count')->default(0);
            $table->decimal('weighted_score', 8, 4)->default(0);
            $table->timestamp('last_seen_together_at')->nullable();
            $table->timestamp('projected_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'person_pair_key'], 'event_person_pair_scores_pair_unique');
            $table->index(['event_id', 'weighted_score'], 'event_person_pair_scores_score_idx');
        });

        Schema::create('event_person_review_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('queue_key');
            $table->string('type', 60);
            $table->string('status', 40)->default('pending');
            $table->unsignedInteger('priority')->default(0);
            $table->foreignId('event_person_id')->nullable()->constrained('event_people')->nullOnDelete();
            $table->foreignId('event_media_face_id')->nullable()->constrained('event_media_faces')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('last_signal_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'queue_key'], 'event_person_review_queue_key_unique');
            $table->index(['event_id', 'status', 'priority'], 'event_person_review_queue_status_priority_idx');
        });

        Schema::create('event_person_representative_faces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_person_id')->constrained('event_people')->cascadeOnDelete();
            $table->foreignId('event_media_face_id')->constrained('event_media_faces')->cascadeOnDelete();
            $table->decimal('rank_score', 8, 4)->default(0);
            $table->decimal('quality_score', 6, 4)->nullable();
            $table->string('pose_bucket', 40)->nullable();
            $table->string('context_hash', 80)->nullable();
            $table->string('sync_status', 40)->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('sync_payload')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'event_person_id', 'event_media_face_id'], 'event_person_representative_faces_unique');
            $table->index(['event_id', 'event_person_id', 'rank_score'], 'event_person_representative_faces_rank_idx');
            $table->index(['event_id', 'sync_status'], 'event_person_representative_faces_sync_idx');
        });

        Schema::create('event_person_name_search', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_person_id')->constrained('event_people')->cascadeOnDelete();
            $table->string('normalized_name');
            $table->string('alias')->nullable();
            $table->string('normalized_alias')->nullable();
            $table->unsignedInteger('rank')->default(0);
            $table->timestamp('projected_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'event_person_id', 'normalized_name'], 'event_person_name_search_name_unique');
            $table->index(['event_id', 'normalized_name'], 'event_person_name_search_name_idx');
            $table->index(['event_id', 'normalized_alias'], 'event_person_name_search_alias_idx');
        });

        $this->createPartialIndexes();
    }

    public function down(): void
    {
        Schema::dropIfExists('event_person_name_search');
        Schema::dropIfExists('event_person_representative_faces');
        Schema::dropIfExists('event_person_review_queue');
        Schema::dropIfExists('event_person_pair_scores');
        Schema::dropIfExists('event_person_media_stats');
        Schema::dropIfExists('event_person_cooccurrences');
        Schema::dropIfExists('event_person_relations');
        Schema::dropIfExists('event_person_face_assignments');
        Schema::dropIfExists('event_people');
    }

    private function createPartialIndexes(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            return;
        }

        DB::statement(
            "CREATE UNIQUE INDEX event_person_face_assignments_one_confirmed_face
            ON event_person_face_assignments (event_media_face_id)
            WHERE status = 'confirmed'",
        );

        DB::statement(
            "CREATE INDEX event_person_review_queue_active_priority
            ON event_person_review_queue (event_id, priority, last_signal_at)
            WHERE status IN ('pending', 'conflict')",
        );
    }
};
