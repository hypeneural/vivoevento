<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('target_audience', 30)->default('both');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('event_package_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_package_id')->constrained()->cascadeOnDelete();
            $table->string('billing_mode', 20)->default('one_time');
            $table->string('currency', 10)->default('BRL');
            $table->integer('amount_cents');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['event_package_id', 'is_active', 'is_default'], 'event_package_prices_pkg_active_default_idx');
        });

        Schema::create('event_package_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_package_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 120);
            $table->string('feature_value', 255)->nullable();
            $table->timestamps();

            $table->unique(['event_package_id', 'feature_key'], 'event_package_features_unique_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_package_features');
        Schema::dropIfExists('event_package_prices');
        Schema::dropIfExists('event_packages');
    }
};
