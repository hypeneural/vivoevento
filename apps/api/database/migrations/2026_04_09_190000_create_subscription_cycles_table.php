<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('gateway_cycle_id', 120)->nullable()->unique();
            $table->string('status', 40)->default('pending');
            $table->timestamp('billing_at')->nullable();
            $table->timestamp('period_start_at')->nullable();
            $table->timestamp('period_end_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->jsonb('raw_gateway_json')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'period_end_at'], 'subscription_cycles_subscription_period_idx');
            $table->index(['subscription_id', 'status'], 'subscription_cycles_subscription_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_cycles');
    }
};
