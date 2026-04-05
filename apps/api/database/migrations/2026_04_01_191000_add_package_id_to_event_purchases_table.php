<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_purchases', function (Blueprint $table) {
            $table->foreignId('package_id')
                ->nullable()
                ->constrained('event_packages')
                ->nullOnDelete();

            $table->foreignId('plan_id')->nullable()->change();
            $table->index(['package_id', 'status'], 'event_purchases_package_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('event_purchases', function (Blueprint $table) {
            $table->dropIndex('event_purchases_package_status_idx');
            $table->dropForeign(['package_id']);
            $table->dropColumn('package_id');
            $table->foreignId('plan_id')->nullable(false)->change();
        });
    }
};
