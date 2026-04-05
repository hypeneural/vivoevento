<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_purchases', function (Blueprint $table) {
            $table->foreignId('billing_order_id')
                ->nullable()
                ->after('event_id')
                ->constrained('billing_orders')
                ->nullOnDelete();

            $table->index(['billing_order_id', 'status'], 'event_purchases_order_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('event_purchases', function (Blueprint $table) {
            $table->dropIndex('event_purchases_order_status_idx');
            $table->dropForeign(['billing_order_id']);
            $table->dropColumn('billing_order_id');
        });
    }
};
