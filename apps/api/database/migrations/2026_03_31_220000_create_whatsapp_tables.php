<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 1. Providers ────────────────────────────────────────
        Schema::create('whatsapp_providers', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->jsonb('config_json')->nullable();
            $table->timestamps();
        });

        // ─── 2. Instances ────────────────────────────────────────
        Schema::create('whatsapp_instances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('whatsapp_providers')->restrictOnDelete();
            $table->string('provider_key', 30);
            $table->string('name', 120);
            $table->string('external_instance_id', 180);
            $table->text('provider_token');          // encrypted via cast
            $table->text('provider_client_token');   // encrypted via cast
            $table->string('phone_number', 40)->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('last_status_sync_at')->nullable();
            $table->text('webhook_secret')->nullable(); // encrypted via cast
            $table->jsonb('settings_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['provider_key', 'external_instance_id']);
            $table->index(['organization_id', 'status']);
        });

        // ─── 3. Chats ───────────────────────────────────────────
        Schema::create('whatsapp_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('whatsapp_instances')->cascadeOnDelete();
            $table->string('external_chat_id', 180);
            $table->string('type', 20)->default('private');
            $table->string('phone', 40)->nullable();
            $table->string('group_id', 120)->nullable();
            $table->string('display_name', 180)->nullable();
            $table->boolean('is_group')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->jsonb('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(['instance_id', 'external_chat_id']);
        });

        // ─── 4. Messages ────────────────────────────────────────
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('whatsapp_instances')->cascadeOnDelete();
            $table->foreignId('chat_id')->nullable()->constrained('whatsapp_chats')->nullOnDelete();
            $table->string('direction', 10);   // inbound | outbound
            $table->string('provider_message_id', 180)->nullable();
            $table->string('provider_zaap_id', 180)->nullable();
            $table->string('reply_to_provider_message_id', 180)->nullable();
            $table->string('type', 30);        // text, image, audio, reaction, etc.
            $table->text('text_body')->nullable();
            $table->text('media_url')->nullable();
            $table->string('mime_type', 60)->nullable();
            $table->string('status', 30)->default('queued');
            $table->string('sender_phone', 40)->nullable();
            $table->string('recipient_phone', 40)->nullable();
            $table->jsonb('payload_json')->nullable();
            $table->jsonb('normalized_payload_json')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['instance_id', 'direction', 'created_at']);
            $table->index('provider_message_id');
        });

        // ─── 5. Inbound Events ──────────────────────────────────
        Schema::create('whatsapp_inbound_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('whatsapp_instances')->cascadeOnDelete();
            $table->string('provider_key', 30);
            $table->string('external_event_id', 180)->nullable();
            $table->string('provider_message_id', 180)->nullable();
            $table->string('event_type', 60);
            $table->jsonb('payload_json');
            $table->jsonb('normalized_json')->nullable();
            $table->string('processing_status', 30)->default('pending');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['provider_key', 'provider_message_id']);
            $table->index(['instance_id', 'processing_status']);
        });

        // ─── 6. Dispatch Logs ───────────────────────────────────
        Schema::create('whatsapp_dispatch_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('whatsapp_instances')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
            $table->string('provider_key', 30);
            $table->string('endpoint_used', 120);
            $table->jsonb('request_json');
            $table->jsonb('response_json')->nullable();
            $table->smallInteger('http_status')->nullable();
            $table->boolean('success');
            $table->text('error_message')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // ─── 7. Group Bindings ──────────────────────────────────
        Schema::create('whatsapp_group_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('instance_id')->constrained('whatsapp_instances')->cascadeOnDelete();
            $table->string('group_external_id', 180);
            $table->string('group_name', 180)->nullable();
            $table->string('binding_type', 40)->default('general');
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(['instance_id', 'group_external_id', 'binding_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_group_bindings');
        Schema::dropIfExists('whatsapp_dispatch_logs');
        Schema::dropIfExists('whatsapp_inbound_events');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_chats');
        Schema::dropIfExists('whatsapp_instances');
        Schema::dropIfExists('whatsapp_providers');
    }
};
