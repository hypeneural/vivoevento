<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_person_reference_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_person_id')->constrained('event_people')->cascadeOnDelete();
            $table->string('source', 40)->default('event_face');
            $table->foreignId('event_media_id')->nullable()->constrained('event_media')->nullOnDelete();
            $table->foreignId('event_media_face_id')->nullable()->constrained('event_media_faces')->nullOnDelete();
            $table->foreignId('reference_upload_media_id')->nullable()->constrained('event_media')->nullOnDelete();
            $table->string('purpose', 40)->default('matching');
            $table->string('status', 40)->default('active');
            $table->decimal('quality_score', 6, 4)->nullable();
            $table->boolean('is_primary_avatar')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'event_person_id', 'status'], 'event_person_reference_photos_person_status_idx');
            $table->index(['event_id', 'event_media_face_id'], 'event_person_reference_photos_face_idx');
            $table->index(['event_id', 'is_primary_avatar'], 'event_person_reference_photos_avatar_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_person_reference_photos');
    }
};
