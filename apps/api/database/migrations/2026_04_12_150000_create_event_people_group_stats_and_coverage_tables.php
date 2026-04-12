<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_person_group_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_person_group_id')->constrained('event_person_groups')->cascadeOnDelete();
            $table->unsignedInteger('member_count')->default(0);
            $table->unsignedInteger('people_with_primary_photo_count')->default(0);
            $table->unsignedInteger('people_with_media_count')->default(0);
            $table->timestamp('projected_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'event_person_group_id'], 'event_person_group_stats_unique');
        });

        Schema::create('event_person_group_media_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_person_group_id')->constrained('event_person_groups')->cascadeOnDelete();
            $table->unsignedInteger('media_count')->default(0);
            $table->unsignedInteger('published_media_count')->default(0);
            $table->timestamp('projected_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'event_person_group_id'], 'event_person_group_media_stats_unique');
        });

        Schema::create('event_coverage_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('target_type', 20);
            $table->foreignId('person_a_id')->nullable()->constrained('event_people')->nullOnDelete();
            $table->foreignId('person_b_id')->nullable()->constrained('event_people')->nullOnDelete();
            $table->foreignId('event_person_group_id')->nullable()->constrained('event_person_groups')->nullOnDelete();
            $table->unsignedInteger('required_media_count')->default(1);
            $table->unsignedInteger('required_published_media_count')->default(0);
            $table->unsignedInteger('importance_rank')->default(0);
            $table->string('source', 40)->default('preset');
            $table->string('status', 40)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'key'], 'event_coverage_targets_event_key_unique');
            $table->index(['event_id', 'target_type', 'status'], 'event_coverage_targets_type_status_idx');
            $table->index(['event_id', 'importance_rank'], 'event_coverage_targets_importance_idx');
        });

        Schema::create('event_must_have_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_a_id')->constrained('event_people')->cascadeOnDelete();
            $table->foreignId('person_b_id')->constrained('event_people')->cascadeOnDelete();
            $table->string('person_pair_key', 80);
            $table->string('label');
            $table->unsignedInteger('required_media_count')->default(1);
            $table->unsignedInteger('importance_rank')->default(0);
            $table->string('status', 40)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'person_pair_key'], 'event_must_have_pairs_pair_unique');
            $table->index(['event_id', 'status', 'importance_rank'], 'event_must_have_pairs_status_importance_idx');
        });

        Schema::create('event_coverage_target_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_coverage_target_id')->constrained('event_coverage_targets')->cascadeOnDelete();
            $table->string('coverage_state', 20)->default('missing');
            $table->decimal('score', 8, 2)->default(0);
            $table->unsignedInteger('resolved_entity_count')->default(0);
            $table->unsignedInteger('media_count')->default(0);
            $table->unsignedInteger('published_media_count')->default(0);
            $table->unsignedInteger('joint_media_count')->default(0);
            $table->unsignedInteger('people_with_primary_photo_count')->default(0);
            $table->json('reason_codes')->nullable();
            $table->timestamp('projected_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'event_coverage_target_id'], 'event_coverage_target_stats_unique');
            $table->index(['event_id', 'coverage_state'], 'event_coverage_target_stats_state_idx');
        });

        Schema::create('event_coverage_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_coverage_target_id')->constrained('event_coverage_targets')->cascadeOnDelete();
            $table->string('alert_key');
            $table->string('severity', 20)->default('weak');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('status', 20)->default('active');
            $table->json('payload')->nullable();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'alert_key'], 'event_coverage_alerts_alert_key_unique');
            $table->index(['event_id', 'status', 'severity'], 'event_coverage_alerts_status_severity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_coverage_alerts');
        Schema::dropIfExists('event_coverage_target_stats');
        Schema::dropIfExists('event_must_have_pairs');
        Schema::dropIfExists('event_coverage_targets');
        Schema::dropIfExists('event_person_group_media_stats');
        Schema::dropIfExists('event_person_group_stats');
    }
};
