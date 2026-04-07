<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbound_messages', function (Blueprint $table) {
            $table->string('chat_external_id', 180)->nullable()->after('message_type');
            $table->string('sender_external_id', 180)->nullable()->after('chat_external_id');
            $table->string('sender_lid', 180)->nullable()->after('sender_phone');
            $table->text('sender_avatar_url')->nullable()->after('sender_name');
            $table->boolean('from_me')->nullable()->after('reference_message_id');

            $table->index(['event_id', 'sender_external_id'], 'inbound_messages_event_sender_external_idx');
            $table->index(['event_id', 'sender_phone'], 'inbound_messages_event_sender_phone_idx');
            $table->index(['event_id', 'sender_lid'], 'inbound_messages_event_sender_lid_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inbound_messages', function (Blueprint $table) {
            $table->dropIndex('inbound_messages_event_sender_external_idx');
            $table->dropIndex('inbound_messages_event_sender_phone_idx');
            $table->dropIndex('inbound_messages_event_sender_lid_idx');

            $table->dropColumn([
                'chat_external_id',
                'sender_external_id',
                'sender_lid',
                'sender_avatar_url',
                'from_me',
            ]);
        });
    }
};
