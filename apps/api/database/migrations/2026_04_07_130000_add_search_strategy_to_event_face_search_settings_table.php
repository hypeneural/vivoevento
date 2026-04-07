<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_face_search_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('event_face_search_settings', 'search_strategy')) {
                $table->string('search_strategy', 20)
                    ->default((string) config('face_search.default_search_strategy', 'exact'))
                    ->after('vector_store_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_face_search_settings', function (Blueprint $table) {
            if (Schema::hasColumn('event_face_search_settings', 'search_strategy')) {
                $table->dropColumn('search_strategy');
            }
        });
    }
};
