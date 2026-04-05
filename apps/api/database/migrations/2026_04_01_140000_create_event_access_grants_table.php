<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->string('status', 30)->default('active');
            $table->integer('priority')->default(100);
            $table->string('merge_strategy', 20)->default('expand');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->jsonb('features_snapshot_json')->nullable();
            $table->jsonb('limits_snapshot_json')->nullable();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->jsonb('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status', 'priority'], 'event_access_grants_event_status_priority_idx');
            $table->index(['event_id', 'starts_at', 'ends_at'], 'event_access_grants_event_window_idx');
            $table->index(['organization_id', 'source_type'], 'event_access_grants_org_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_access_grants');
    }
};
