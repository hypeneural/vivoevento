<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->foreignId('subscription_cycle_id')->nullable()->after('subscription_id')->constrained('subscription_cycles')->nullOnDelete();
            $table->string('gateway_invoice_id', 120)->nullable()->after('billing_order_id')->unique();
            $table->string('gateway_charge_id', 120)->nullable()->after('gateway_invoice_id');
            $table->string('gateway_cycle_id', 120)->nullable()->after('gateway_charge_id');
            $table->string('gateway_status', 40)->nullable()->after('status');
            $table->timestamp('period_start_at')->nullable()->after('due_at');
            $table->timestamp('period_end_at')->nullable()->after('period_start_at');
            $table->jsonb('raw_gateway_json')->nullable()->after('snapshot_json');

            $table->index(['subscription_id', 'status'], 'invoices_subscription_status_idx');
            $table->index(['subscription_cycle_id', 'status'], 'invoices_cycle_status_idx');
            $table->index(['gateway_charge_id'], 'invoices_gateway_charge_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_subscription_status_idx');
            $table->dropIndex('invoices_cycle_status_idx');
            $table->dropIndex('invoices_gateway_charge_idx');
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropConstrainedForeignId('subscription_cycle_id');
            $table->dropUnique(['gateway_invoice_id']);
            $table->dropColumn([
                'gateway_invoice_id',
                'gateway_charge_id',
                'gateway_cycle_id',
                'gateway_status',
                'period_start_at',
                'period_end_at',
                'raw_gateway_json',
            ]);
        });
    }
};
