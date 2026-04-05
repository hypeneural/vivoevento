<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_media', function (Blueprint $table) {
            $table->string('perceptual_hash', 32)->nullable()->after('client_filename');
            $table->string('duplicate_group_key', 80)->nullable()->after('perceptual_hash');

            $table->index(['event_id', 'perceptual_hash'], 'event_media_event_phash_idx');
            $table->index(['event_id', 'duplicate_group_key'], 'event_media_event_duplicate_group_idx');
        });
    }

    public function down(): void
    {
        Schema::table('event_media', function (Blueprint $table) {
            $table->dropIndex('event_media_event_phash_idx');
            $table->dropIndex('event_media_event_duplicate_group_idx');
            $table->dropColumn([
                'perceptual_hash',
                'duplicate_group_key',
            ]);
        });
    }
};
