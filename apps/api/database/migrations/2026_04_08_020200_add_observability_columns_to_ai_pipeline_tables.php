<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_media_safety_evaluations', function (Blueprint $table) {
            $table->json('request_payload_json')->nullable()->after('raw_response_json');
        });

        Schema::table('channel_webhook_logs', function (Blueprint $table) {
            $table->string('trace_id', 120)->nullable()->after('provider_update_id');
            $table->index('trace_id', 'channel_webhook_logs_trace_id_idx');
        });

        Schema::table('inbound_messages', function (Blueprint $table) {
            $table->string('trace_id', 120)->nullable()->after('event_channel_id');
            $table->index('trace_id', 'inbound_messages_trace_id_idx');
        });

        Schema::table('whatsapp_inbound_events', function (Blueprint $table) {
            $table->string('trace_id', 120)->nullable()->after('provider_key');
            $table->index('trace_id', 'whatsapp_inbound_events_trace_id_idx');
        });

        Schema::table('whatsapp_message_feedbacks', function (Blueprint $table) {
            $table->string('trace_id', 120)->nullable()->after('instance_id');
            $table->index('trace_id', 'wa_message_feedbacks_trace_id_idx');
        });

        Schema::table('telegram_message_feedbacks', function (Blueprint $table) {
            $table->string('trace_id', 120)->nullable()->after('event_channel_id');
            $table->index('trace_id', 'telegram_message_feedbacks_trace_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_message_feedbacks', function (Blueprint $table) {
            $table->dropIndex('telegram_message_feedbacks_trace_id_idx');
            $table->dropColumn('trace_id');
        });

        Schema::table('whatsapp_message_feedbacks', function (Blueprint $table) {
            $table->dropIndex('wa_message_feedbacks_trace_id_idx');
            $table->dropColumn('trace_id');
        });

        Schema::table('whatsapp_inbound_events', function (Blueprint $table) {
            $table->dropIndex('whatsapp_inbound_events_trace_id_idx');
            $table->dropColumn('trace_id');
        });

        Schema::table('inbound_messages', function (Blueprint $table) {
            $table->dropIndex('inbound_messages_trace_id_idx');
            $table->dropColumn('trace_id');
        });

        Schema::table('channel_webhook_logs', function (Blueprint $table) {
            $table->dropIndex('channel_webhook_logs_trace_id_idx');
            $table->dropColumn('trace_id');
        });

        Schema::table('event_media_safety_evaluations', function (Blueprint $table) {
            $table->dropColumn('request_payload_json');
        });
    }
};
