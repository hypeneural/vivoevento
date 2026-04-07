<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_inbox_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_channel_id')->nullable()->constrained('event_channels')->nullOnDelete();
            $table->foreignId('instance_id')->constrained('whatsapp_instances')->cascadeOnDelete();
            $table->string('sender_external_id', 180);
            $table->string('sender_phone', 40)->nullable();
            $table->string('sender_lid', 180)->nullable();
            $table->string('sender_name', 180)->nullable();
            $table->string('chat_external_id', 180);
            $table->string('status', 20)->default('active');
            $table->string('activated_by_provider_message_id', 180)->nullable();
            $table->string('last_inbound_provider_message_id', 180)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->jsonb('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['instance_id', 'sender_external_id', 'status'], 'wa_inbox_sessions_instance_sender_status_idx');
            $table->index(['event_id', 'status'], 'wa_inbox_sessions_event_status_idx');
            $table->index('expires_at', 'wa_inbox_sessions_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_inbox_sessions');
    }
};
