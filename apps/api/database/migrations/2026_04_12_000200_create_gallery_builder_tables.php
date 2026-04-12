<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_gallery_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->string('event_type_family', 30)->default('wedding');
            $table->string('style_skin', 30)->default('romantic');
            $table->string('behavior_profile', 30)->default('light');
            $table->string('theme_key', 40)->default('event-brand');
            $table->string('layout_key', 40)->default('editorial-masonry');
            $table->jsonb('theme_tokens_json');
            $table->jsonb('page_schema_json');
            $table->jsonb('media_behavior_json');
            $table->unsignedBigInteger('current_draft_revision_id')->nullable();
            $table->unsignedBigInteger('current_published_revision_id')->nullable();
            $table->unsignedBigInteger('preview_revision_id')->nullable();
            $table->integer('draft_version')->nullable();
            $table->integer('published_version')->nullable();
            $table->string('preview_share_token', 120)->nullable();
            $table->timestamp('preview_share_expires_at')->nullable();
            $table->timestamp('last_autosaved_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('preview_share_token');
        });

        Schema::create('event_gallery_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->integer('version_number');
            $table->string('kind', 20);
            $table->string('event_type_family', 30);
            $table->string('style_skin', 30);
            $table->string('behavior_profile', 30);
            $table->string('theme_key', 40);
            $table->string('layout_key', 40);
            $table->jsonb('theme_tokens_json');
            $table->jsonb('page_schema_json');
            $table->jsonb('media_behavior_json');
            $table->jsonb('change_summary_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'version_number']);
            $table->index(['event_id', 'kind']);
        });

        Schema::table('event_gallery_settings', function (Blueprint $table) {
            $table->foreign('current_draft_revision_id')
                ->references('id')
                ->on('event_gallery_revisions')
                ->nullOnDelete();
            $table->foreign('current_published_revision_id')
                ->references('id')
                ->on('event_gallery_revisions')
                ->nullOnDelete();
            $table->foreign('preview_revision_id')
                ->references('id')
                ->on('event_gallery_revisions')
                ->nullOnDelete();
        });

        Schema::create('gallery_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 160);
            $table->string('description', 180)->nullable();
            $table->string('event_type_family', 30);
            $table->string('style_skin', 30);
            $table->string('behavior_profile', 30);
            $table->string('theme_key', 40);
            $table->string('layout_key', 40);
            $table->jsonb('theme_tokens_json');
            $table->jsonb('page_schema_json');
            $table->jsonb('media_behavior_json');
            $table->string('derived_preset_key', 120)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'theme_key']);
            $table->index(['organization_id', 'layout_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_gallery_settings');
        Schema::dropIfExists('event_gallery_revisions');
        Schema::dropIfExists('gallery_presets');
    }
};
