<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_people', function (Blueprint $table) {
            $table->foreignId('primary_reference_photo_id')
                ->nullable()
                ->after('avatar_face_id')
                ->constrained('event_person_reference_photos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_people', function (Blueprint $table) {
            $table->dropConstrainedForeignId('primary_reference_photo_id');
        });
    }
};
