<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_channel_id')->nullable()->constrained('event_channels')->nullOnDelete();
            $table->string('provider', 40);
            $table->string('message_id', 120)->nullable();
            $table->string('detected_type', 40)->default('unknown');
            $table->string('routing_status', 50)->default('received');
            $table->jsonb('payload_json');
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('inbound_message_id')->nullable();
            $table->timestamps();
        });

        Schema::create('inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_channel_id')->nullable()->constrained('event_channels')->nullOnDelete();
            $table->string('provider', 40);
            $table->string('message_id', 120);
            $table->string('message_type', 40);
            $table->string('sender_phone', 40)->nullable();
            $table->string('sender_name', 120)->nullable();
            $table->text('body_text')->nullable();
            $table->text('media_url')->nullable();
            $table->string('reference_message_id', 120)->nullable();
            $table->jsonb('normalized_payload_json')->nullable();
            $table->string('status', 40)->default('received');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_messages');
        Schema::dropIfExists('channel_webhook_logs');
    }
};
