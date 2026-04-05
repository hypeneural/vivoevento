<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_order_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_order_id')->constrained('billing_orders')->cascadeOnDelete();
            $table->string('notification_type', 40);
            $table->string('channel', 20)->default('whatsapp');
            $table->string('status', 30)->default('pending');
            $table->string('recipient_phone', 40)->nullable();
            $table->foreignId('whatsapp_instance_id')->nullable()->constrained('whatsapp_instances')->nullOnDelete();
            $table->foreignId('whatsapp_message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
            $table->jsonb('context_json')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['billing_order_id', 'notification_type', 'channel'],
                'billing_order_notifications_order_type_channel_unique',
            );
            $table->index(['status', 'notification_type'], 'billing_order_notifications_status_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_order_notifications');
    }
};
