<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('events')
            ->where('moderation_mode', 'auto')
            ->update(['moderation_mode' => 'none']);
    }

    public function down(): void
    {
        DB::table('events')
            ->where('moderation_mode', 'none')
            ->update(['moderation_mode' => 'auto']);
    }
};
