<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_gateway_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_key', 40);
            $table->string('event_key', 120);
            $table->string('event_type', 80);
            $table->string('status', 30)->default('pending');
            $table->foreignId('billing_order_id')->nullable()->constrained('billing_orders')->nullOnDelete();
            $table->string('gateway_order_id', 120)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->jsonb('headers_json')->nullable();
            $table->jsonb('payload_json');
            $table->jsonb('result_json')->nullable();
            $table->timestamps();

            $table->unique(['provider_key', 'event_key'], 'billing_gateway_events_provider_event_unique');
            $table->index(['billing_order_id', 'status'], 'billing_gateway_events_order_status_idx');
            $table->index(['provider_key', 'gateway_order_id'], 'billing_gateway_events_provider_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_gateway_events');
    }
};
