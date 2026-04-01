<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->string('audience', 30)->default('b2b');
            $table->string('status', 30)->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('billing_cycle', 20);
            $table->string('currency', 10)->default('BRL');
            $table->integer('amount_cents');
            $table->string('gateway_provider', 30)->nullable();
            $table->string('gateway_price_id', 120)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 80);
            $table->string('feature_value', 255);
            $table->timestamps();

            $table->unique(['plan_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plan_prices');
        Schema::dropIfExists('plans');
    }
};
