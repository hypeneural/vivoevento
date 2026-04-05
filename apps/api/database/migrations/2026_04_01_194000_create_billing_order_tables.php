<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('buyer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 30);
            $table->string('status', 30)->default('draft');
            $table->string('currency', 10)->default('BRL');
            $table->integer('total_cents')->default(0);
            $table->string('gateway_provider', 40)->nullable();
            $table->string('gateway_order_id', 120)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->jsonb('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'billing_orders_org_status_idx');
            $table->index(['event_id', 'status'], 'billing_orders_event_status_idx');
            $table->index(['mode', 'status'], 'billing_orders_mode_status_idx');
        });

        Schema::create('billing_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_order_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 30);
            $table->unsignedBigInteger('reference_id');
            $table->string('description', 255);
            $table->integer('quantity')->default(1);
            $table->integer('unit_amount_cents');
            $table->integer('total_amount_cents');
            $table->jsonb('snapshot_json');
            $table->timestamps();

            $table->index(['billing_order_id', 'item_type'], 'billing_order_items_order_type_idx');
            $table->index(['item_type', 'reference_id'], 'billing_order_items_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_order_items');
        Schema::dropIfExists('billing_orders');
    }
};
