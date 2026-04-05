<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_orders', function (Blueprint $table) {
            $table->string('payment_method', 40)->nullable()->after('total_cents');
            $table->string('idempotency_key', 160)->nullable()->after('gateway_order_id');
            $table->string('gateway_charge_id', 120)->nullable()->after('gateway_order_id');
            $table->string('gateway_transaction_id', 120)->nullable()->after('gateway_charge_id');
            $table->string('gateway_status', 60)->nullable()->after('gateway_transaction_id');
            $table->jsonb('customer_snapshot_json')->nullable()->after('gateway_status');
            $table->jsonb('gateway_response_json')->nullable()->after('customer_snapshot_json');
            $table->timestamp('expires_at')->nullable()->after('confirmed_at');
            $table->timestamp('paid_at')->nullable()->after('expires_at');
            $table->timestamp('failed_at')->nullable()->after('paid_at');
            $table->timestamp('canceled_at')->nullable()->after('failed_at');
            $table->timestamp('refunded_at')->nullable()->after('canceled_at');

            $table->index(['gateway_provider', 'gateway_order_id'], 'billing_orders_provider_order_idx');
            $table->index(['payment_method', 'status'], 'billing_orders_payment_status_idx');
            $table->index('idempotency_key', 'billing_orders_idempotency_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_method', 40)->nullable()->after('currency');
            $table->string('gateway_order_id', 120)->nullable()->after('gateway_provider');
            $table->string('gateway_charge_id', 120)->nullable()->after('gateway_order_id');
            $table->string('gateway_transaction_id', 120)->nullable()->after('gateway_charge_id');
            $table->string('gateway_status', 60)->nullable()->after('gateway_transaction_id');
            $table->jsonb('last_transaction_json')->nullable()->after('raw_payload_json');
            $table->jsonb('gateway_response_json')->nullable()->after('last_transaction_json');
            $table->string('acquirer_return_code', 80)->nullable()->after('gateway_response_json');
            $table->string('acquirer_message', 255)->nullable()->after('acquirer_return_code');
            $table->text('qr_code')->nullable()->after('acquirer_message');
            $table->text('qr_code_url')->nullable()->after('qr_code');
            $table->timestamp('expires_at')->nullable()->after('paid_at');
            $table->timestamp('failed_at')->nullable()->after('expires_at');
            $table->timestamp('canceled_at')->nullable()->after('failed_at');
            $table->timestamp('refunded_at')->nullable()->after('canceled_at');

            $table->index(['gateway_provider', 'gateway_order_id'], 'payments_gateway_order_idx');
            $table->index(['gateway_provider', 'gateway_charge_id'], 'payments_gateway_charge_idx');
        });

        Schema::table('billing_gateway_events', function (Blueprint $table) {
            $table->string('gateway_charge_id', 120)->nullable()->after('gateway_order_id');
            $table->string('gateway_transaction_id', 120)->nullable()->after('gateway_charge_id');
            $table->timestamp('occurred_at')->nullable()->after('gateway_transaction_id');

            $table->index(['provider_key', 'gateway_charge_id'], 'billing_gateway_events_provider_charge_idx');
        });
    }

    public function down(): void
    {
        Schema::table('billing_gateway_events', function (Blueprint $table) {
            $table->dropIndex('billing_gateway_events_provider_charge_idx');
            $table->dropColumn([
                'gateway_charge_id',
                'gateway_transaction_id',
                'occurred_at',
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_gateway_order_idx');
            $table->dropIndex('payments_gateway_charge_idx');
            $table->dropColumn([
                'payment_method',
                'gateway_order_id',
                'gateway_charge_id',
                'gateway_transaction_id',
                'gateway_status',
                'last_transaction_json',
                'gateway_response_json',
                'acquirer_return_code',
                'acquirer_message',
                'qr_code',
                'qr_code_url',
                'expires_at',
                'failed_at',
                'canceled_at',
                'refunded_at',
            ]);
        });

        Schema::table('billing_orders', function (Blueprint $table) {
            $table->dropIndex('billing_orders_provider_order_idx');
            $table->dropIndex('billing_orders_payment_status_idx');
            $table->dropIndex('billing_orders_idempotency_idx');
            $table->dropColumn([
                'payment_method',
                'idempotency_key',
                'gateway_charge_id',
                'gateway_transaction_id',
                'gateway_status',
                'customer_snapshot_json',
                'gateway_response_json',
                'expires_at',
                'paid_at',
                'failed_at',
                'canceled_at',
                'refunded_at',
            ]);
        });
    }
};
