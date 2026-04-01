<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_wall_settings', function (Blueprint $table) {
            $table->string('wall_code', 8)->nullable()->unique()->after('event_id');
            $table->boolean('show_neon')->default(false)->after('show_branding');
            $table->string('neon_text', 180)->nullable()->after('show_neon');
            $table->string('neon_color', 30)->default('#ffffff')->after('neon_text');
            $table->boolean('show_sender_credit')->default(false)->after('neon_color');
            $table->string('transition_effect', 40)->default('fade')->after('layout');
        });
    }

    public function down(): void
    {
        Schema::table('event_wall_settings', function (Blueprint $table) {
            $table->dropUnique(['wall_code']);
            $table->dropColumn([
                'wall_code',
                'show_neon',
                'neon_text',
                'neon_color',
                'show_sender_credit',
                'transition_effect',
            ]);
        });
    }
};
