<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_order_id')->constrained('billing_orders')->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->integer('amount_cents');
            $table->string('currency', 10)->default('BRL');
            $table->string('gateway_provider', 40)->nullable();
            $table->string('gateway_payment_id', 120)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->jsonb('raw_payload_json')->nullable();
            $table->timestamps();

            $table->index(['billing_order_id', 'status'], 'payments_order_status_idx');
            $table->index(['gateway_provider', 'gateway_payment_id'], 'payments_gateway_idx');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_order_id')->nullable()->constrained('billing_orders')->nullOnDelete();
            $table->string('invoice_number', 80)->nullable()->unique();
            $table->string('status', 30)->default('open');
            $table->integer('amount_cents');
            $table->string('currency', 10)->default('BRL');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->jsonb('snapshot_json')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'invoices_org_status_idx');
            $table->index(['billing_order_id', 'status'], 'invoices_order_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payments');
    }
};
