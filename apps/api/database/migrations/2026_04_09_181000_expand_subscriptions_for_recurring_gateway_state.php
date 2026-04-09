<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('plan_price_id')->nullable()->after('plan_id')->constrained('plan_prices')->nullOnDelete();
            $table->string('payment_method', 40)->nullable()->after('billing_cycle');
            $table->string('gateway_plan_id', 120)->nullable()->after('gateway_customer_id');
            $table->string('gateway_card_id', 120)->nullable()->after('gateway_plan_id');
            $table->string('gateway_status_reason', 255)->nullable()->after('gateway_card_id');
            $table->string('billing_type', 30)->nullable()->after('gateway_status_reason');
            $table->string('contract_status', 30)->nullable()->after('billing_type');
            $table->string('billing_status', 30)->nullable()->after('contract_status');
            $table->string('access_status', 30)->nullable()->after('billing_status');
            $table->timestamp('current_period_started_at')->nullable()->after('trial_ends_at');
            $table->timestamp('current_period_ends_at')->nullable()->after('current_period_started_at');
            $table->timestamp('next_billing_at')->nullable()->after('renews_at');
            $table->boolean('cancel_at_period_end')->default(false)->after('canceled_at');
            $table->timestamp('cancel_requested_at')->nullable()->after('cancel_at_period_end');
            $table->jsonb('metadata_json')->nullable()->after('cancel_requested_at');

            $table->unique('gateway_subscription_id', 'subscriptions_gateway_subscription_unique');
            $table->index(['organization_id', 'contract_status'], 'subscriptions_org_contract_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique('subscriptions_gateway_subscription_unique');
            $table->dropIndex('subscriptions_org_contract_status_idx');
            $table->dropConstrainedForeignId('plan_price_id');
            $table->dropColumn([
                'payment_method',
                'gateway_plan_id',
                'gateway_card_id',
                'gateway_status_reason',
                'billing_type',
                'contract_status',
                'billing_status',
                'access_status',
                'current_period_started_at',
                'current_period_ends_at',
                'next_billing_at',
                'cancel_at_period_end',
                'cancel_requested_at',
                'metadata_json',
            ]);
        });
    }
};
