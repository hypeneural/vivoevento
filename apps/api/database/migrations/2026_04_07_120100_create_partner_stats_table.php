<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_stats', function (Blueprint $table) {
            $table->foreignId('organization_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('clients_count')->default(0);
            $table->unsignedInteger('events_count')->default(0);
            $table->unsignedInteger('active_events_count')->default(0);
            $table->unsignedInteger('team_size')->default(0);
            $table->unsignedInteger('active_bonus_grants_count')->default(0);
            $table->string('subscription_plan_code', 80)->nullable();
            $table->string('subscription_plan_name', 160)->nullable();
            $table->string('subscription_status', 30)->nullable();
            $table->string('subscription_billing_cycle', 20)->nullable();
            $table->unsignedInteger('subscription_revenue_cents')->default(0);
            $table->unsignedInteger('event_package_revenue_cents')->default(0);
            $table->unsignedInteger('total_revenue_cents')->default(0);
            $table->timestamp('last_paid_invoice_at')->nullable();
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();

            $table->index('subscription_plan_code');
            $table->index('subscription_status');
            $table->index('total_revenue_cents');
            $table->index('active_events_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_stats');
    }
};
