<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_media', function (Blueprint $table) {
            $table->boolean('has_audio')->nullable()->after('duration_seconds');
            $table->string('video_codec', 60)->nullable()->after('has_audio');
            $table->string('audio_codec', 60)->nullable()->after('video_codec');
            $table->unsignedInteger('bitrate')->nullable()->after('audio_codec');
            $table->string('container', 40)->nullable()->after('bitrate');
        });
    }

    public function down(): void
    {
        Schema::table('event_media', function (Blueprint $table) {
            $table->dropColumn([
                'has_audio',
                'video_codec',
                'audio_codec',
                'bitrate',
                'container',
            ]);
        });
    }
};
