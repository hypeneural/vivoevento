<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instance_id')->constrained('whatsapp_instances')->cascadeOnDelete();
            $table->foreignId('inbound_message_id')->nullable()->constrained('inbound_messages')->nullOnDelete();
            $table->foreignId('event_media_id')->nullable()->constrained('event_media')->nullOnDelete();
            $table->foreignId('outbound_message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
            $table->string('inbound_provider_message_id', 180);
            $table->string('chat_external_id', 180)->nullable();
            $table->string('sender_external_id', 180)->nullable();
            $table->string('feedback_kind', 30);
            $table->string('feedback_phase', 30);
            $table->string('status', 30)->default('pending');
            $table->string('reaction_emoji', 20)->nullable();
            $table->text('reply_text')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['instance_id', 'inbound_provider_message_id', 'feedback_kind', 'feedback_phase'],
                'wa_message_feedbacks_unique'
            );
            $table->index(['event_id', 'feedback_phase'], 'wa_message_feedbacks_event_phase_idx');
            $table->index(['event_media_id', 'feedback_phase'], 'wa_message_feedbacks_media_phase_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_feedbacks');
    }
};
