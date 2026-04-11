<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_wall_settings', function (Blueprint $table): void {
            $table->string('transition_mode', 20)
                ->default('fixed')
                ->after('transition_effect');
        });
    }

    public function down(): void
    {
        Schema::table('event_wall_settings', function (Blueprint $table): void {
            $table->dropColumn('transition_mode');
        });
    }
};
