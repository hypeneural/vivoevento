<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_person_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('slug');
            $table->string('group_type', 60)->default('custom');
            $table->string('side', 40)->default('neutral');
            $table->text('notes')->nullable();
            $table->unsignedInteger('importance_rank')->default(0);
            $table->string('status', 40)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'slug'], 'event_person_groups_event_slug_unique');
            $table->index(['event_id', 'status'], 'event_person_groups_event_status_idx');
            $table->index(['event_id', 'group_type'], 'event_person_groups_event_type_idx');
            $table->index(['event_id', 'importance_rank'], 'event_person_groups_event_importance_idx');
        });

        Schema::create('event_person_group_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_person_group_id')->constrained('event_person_groups')->cascadeOnDelete();
            $table->foreignId('event_person_id')->constrained('event_people')->cascadeOnDelete();
            $table->string('role_label')->nullable();
            $table->string('source', 40)->default('manual');
            $table->decimal('confidence', 6, 4)->nullable();
            $table->string('status', 40)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'event_person_group_id', 'event_person_id'], 'event_person_group_memberships_unique');
            $table->index(['event_id', 'event_person_group_id', 'status'], 'event_person_group_memberships_group_status_idx');
            $table->index(['event_id', 'event_person_id', 'status'], 'event_person_group_memberships_person_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_person_group_memberships');
        Schema::dropIfExists('event_person_groups');
    }
};
