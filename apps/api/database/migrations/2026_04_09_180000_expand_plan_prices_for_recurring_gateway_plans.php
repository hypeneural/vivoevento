<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_prices', function (Blueprint $table) {
            $table->string('gateway_plan_id', 120)->nullable()->after('gateway_price_id');
            $table->jsonb('gateway_plan_payload_json')->nullable()->after('gateway_plan_id');
            $table->string('billing_type', 30)->nullable()->after('gateway_plan_payload_json');
            $table->unsignedSmallInteger('billing_day')->nullable()->after('billing_type');
            $table->unsignedInteger('trial_period_days')->nullable()->after('billing_day');
            $table->jsonb('payment_methods_json')->nullable()->after('trial_period_days');

            $table->unique('gateway_plan_id', 'plan_prices_gateway_plan_unique');
        });
    }

    public function down(): void
    {
        Schema::table('plan_prices', function (Blueprint $table) {
            $table->dropUnique('plan_prices_gateway_plan_unique');
            $table->dropColumn([
                'gateway_plan_id',
                'gateway_plan_payload_json',
                'billing_type',
                'billing_day',
                'trial_period_days',
                'payment_methods_json',
            ]);
        });
    }
};
