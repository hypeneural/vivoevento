<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_wall_settings', function (Blueprint $table) {
            $table->string('selection_mode', 30)
                ->default('balanced')
                ->after('queue_limit');
            $table->json('selection_policy')
                ->nullable()
                ->after('selection_mode');
        });
    }

    public function down(): void
    {
        Schema::table('event_wall_settings', function (Blueprint $table) {
            $table->dropColumn([
                'selection_mode',
                'selection_policy',
            ]);
        });
    }
};
