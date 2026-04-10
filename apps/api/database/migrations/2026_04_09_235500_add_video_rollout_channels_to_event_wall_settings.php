<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_wall_settings', function (Blueprint $table): void {
            $table->boolean('public_upload_video_enabled')
                ->nullable()
                ->after('video_enabled');

            $table->boolean('private_inbound_video_enabled')
                ->nullable()
                ->after('public_upload_video_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('event_wall_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'public_upload_video_enabled',
                'private_inbound_video_enabled',
            ]);
        });
    }
};
