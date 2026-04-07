<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbound_messages', function (Blueprint $table) {
            $table->dropUnique(['provider', 'message_id']);
            $table->unique(['provider', 'chat_external_id', 'message_id'], 'inbound_messages_provider_chat_message_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inbound_messages', function (Blueprint $table) {
            $table->dropUnique('inbound_messages_provider_chat_message_unique');
            $table->unique(['provider', 'message_id']);
        });
    }
};
