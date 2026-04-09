<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('billing_order_id')->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->after('subscription_id')->constrained('invoices')->nullOnDelete();
            $table->string('gateway_invoice_id', 120)->nullable()->after('gateway_charge_id');
            $table->string('gateway_charge_status', 40)->nullable()->after('gateway_status');
            $table->string('card_brand', 40)->nullable()->after('gateway_charge_status');
            $table->string('card_last_four', 4)->nullable()->after('card_brand');
            $table->unsignedInteger('attempt_sequence')->nullable()->after('card_last_four');

            $table->index(['subscription_id', 'status'], 'payments_subscription_status_idx');
            $table->index(['invoice_id', 'status'], 'payments_invoice_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_subscription_status_idx');
            $table->dropIndex('payments_invoice_status_idx');
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn([
                'gateway_invoice_id',
                'gateway_charge_status',
                'card_brand',
                'card_last_four',
                'attempt_sequence',
            ]);
        });
    }
};
