<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('commercial_mode', 40)->default('none')->after('retention_days');
            $table->jsonb('current_entitlements_json')->nullable()->after('commercial_mode');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['commercial_mode', 'current_entitlements_json']);
        });
    }
};
