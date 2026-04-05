<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_hub_settings', function (Blueprint $table) {
            $table->jsonb('builder_config_json')->nullable()->after('buttons_json');
        });
    }

    public function down(): void
    {
        Schema::table('event_hub_settings', function (Blueprint $table) {
            $table->dropColumn('builder_config_json');
        });
    }
};
