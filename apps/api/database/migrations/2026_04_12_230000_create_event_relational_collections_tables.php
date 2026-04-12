<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_relational_collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('collection_key');
            $table->string('collection_type');
            $table->string('source_type');
            $table->foreignId('person_a_id')->nullable()->constrained('event_people')->nullOnDelete();
            $table->foreignId('person_b_id')->nullable()->constrained('event_people')->nullOnDelete();
            $table->foreignId('event_person_group_id')->nullable()->constrained('event_person_groups')->nullOnDelete();
            $table->string('display_name');
            $table->string('status')->default('active');
            $table->string('visibility')->default('internal');
            $table->string('share_token')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'collection_key'], 'event_relational_collections_event_key_unique');
            $table->index(['event_id', 'collection_type'], 'event_relational_collections_event_type_index');
            $table->index(['event_id', 'visibility'], 'event_relational_collections_event_visibility_index');
        });

        Schema::create('event_relational_collection_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_relational_collection_id')->constrained('event_relational_collections')->cascadeOnDelete();
            $table->foreignId('event_media_id')->constrained('event_media')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->decimal('match_score', 8, 2)->default(0);
            $table->unsignedInteger('matched_people_count')->default(0);
            $table->boolean('is_published')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['event_relational_collection_id', 'event_media_id'], 'event_relational_collection_items_unique');
            $table->index(['event_id', 'event_relational_collection_id'], 'event_relational_collection_items_event_collection_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_relational_collection_items');
        Schema::dropIfExists('event_relational_collections');
    }
};
