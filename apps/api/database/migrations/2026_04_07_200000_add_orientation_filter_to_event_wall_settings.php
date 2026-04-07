<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_wall_settings', function (Blueprint $table) {
            $table->string('accepted_orientation', 20)
                ->default('all')
                ->after('queue_limit')
                ->comment('Filter: all | landscape | portrait');

            $table->boolean('show_side_thumbnails')
                ->default(true)
                ->after('show_sender_credit')
                ->comment('Show side thumbnails stream (Fase 5)');
        });
    }

    public function down(): void
    {
        Schema::table('event_wall_settings', function (Blueprint $table) {
            $table->dropColumn(['accepted_orientation', 'show_side_thumbnails']);
        });
    }
};
