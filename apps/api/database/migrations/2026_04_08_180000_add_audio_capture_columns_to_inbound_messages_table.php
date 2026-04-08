<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbound_messages', function (Blueprint $table) {
            $table->string('capture_target', 40)->nullable()->after('media_url');
            $table->string('stored_disk', 40)->nullable()->after('capture_target');
            $table->text('stored_path')->nullable()->after('stored_disk');
            $table->string('client_filename', 255)->nullable()->after('stored_path');
            $table->string('mime_type', 120)->nullable()->after('client_filename');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
            $table->timestamp('captured_at')->nullable()->after('processed_at');

            $table->index(['event_id', 'message_type'], 'inbound_messages_event_message_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inbound_messages', function (Blueprint $table) {
            $table->dropIndex('inbound_messages_event_message_type_idx');
            $table->dropColumn([
                'capture_target',
                'stored_disk',
                'stored_path',
                'client_filename',
                'mime_type',
                'size_bytes',
                'captured_at',
            ]);
        });
    }
};
