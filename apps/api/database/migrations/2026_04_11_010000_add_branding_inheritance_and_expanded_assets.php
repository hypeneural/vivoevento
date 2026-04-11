<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('logo_dark_path')->nullable()->after('logo_path');
            $table->string('favicon_path')->nullable()->after('logo_dark_path');
            $table->string('watermark_path')->nullable()->after('favicon_path');
            $table->string('cover_path')->nullable()->after('watermark_path');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->boolean('inherit_branding')->default(true)->after('secondary_color');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('inherit_branding');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'logo_dark_path',
                'favicon_path',
                'watermark_path',
                'cover_path',
            ]);
        });
    }
};
