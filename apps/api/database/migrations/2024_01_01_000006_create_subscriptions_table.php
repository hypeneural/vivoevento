<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('status', 30)->default('trialing');
            $table->string('billing_cycle', 20)->default('monthly');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('gateway_provider', 30)->nullable();
            $table->string('gateway_customer_id', 120)->nullable();
            $table->string('gateway_subscription_id', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('event_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->integer('price_snapshot_cents');
            $table->string('currency', 10)->default('BRL');
            $table->jsonb('features_snapshot_json')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('purchased_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_purchases');
        Schema::dropIfExists('subscriptions');
    }
};
