<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('default_whatsapp_instance_id')
                ->nullable()
                ->after('current_entitlements_json')
                ->constrained('whatsapp_instances')
                ->nullOnDelete();

            $table->string('whatsapp_instance_mode', 20)
                ->nullable()
                ->after('default_whatsapp_instance_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['default_whatsapp_instance_id']);
            $table->dropColumn(['default_whatsapp_instance_id', 'whatsapp_instance_mode']);
        });
    }
};
