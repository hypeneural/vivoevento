<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_gateway_events', function (Blueprint $table) {
            $table->string('hook_id', 120)->nullable()->after('event_key');
            $table->string('gateway_subscription_id', 120)->nullable()->after('gateway_order_id');
            $table->string('gateway_invoice_id', 120)->nullable()->after('gateway_subscription_id');
            $table->string('gateway_cycle_id', 120)->nullable()->after('gateway_invoice_id');
            $table->string('gateway_customer_id', 120)->nullable()->after('gateway_cycle_id');
            $table->string('payload_hash', 64)->nullable()->after('headers_json');

            $table->index(['provider_key', 'gateway_subscription_id'], 'billing_gateway_events_provider_subscription_idx');
            $table->index(['provider_key', 'gateway_invoice_id'], 'billing_gateway_events_provider_invoice_idx');
            $table->index(['provider_key', 'gateway_cycle_id'], 'billing_gateway_events_provider_cycle_idx');
        });
    }

    public function down(): void
    {
        Schema::table('billing_gateway_events', function (Blueprint $table) {
            $table->dropIndex('billing_gateway_events_provider_subscription_idx');
            $table->dropIndex('billing_gateway_events_provider_invoice_idx');
            $table->dropIndex('billing_gateway_events_provider_cycle_idx');
            $table->dropColumn([
                'hook_id',
                'gateway_subscription_id',
                'gateway_invoice_id',
                'gateway_cycle_id',
                'gateway_customer_id',
                'payload_hash',
            ]);
        });
    }
};
