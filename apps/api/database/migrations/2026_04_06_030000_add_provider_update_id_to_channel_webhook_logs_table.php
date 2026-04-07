<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_webhook_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('channel_webhook_logs', 'provider_update_id')) {
                $table->string('provider_update_id', 120)->nullable();
                $table->unique(['provider', 'provider_update_id'], 'channel_webhook_logs_provider_update_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('channel_webhook_logs', function (Blueprint $table) {
            if (Schema::hasColumn('channel_webhook_logs', 'provider_update_id')) {
                $table->dropUnique('channel_webhook_logs_provider_update_unique');
                $table->dropColumn('provider_update_id');
            }
        });
    }
};
