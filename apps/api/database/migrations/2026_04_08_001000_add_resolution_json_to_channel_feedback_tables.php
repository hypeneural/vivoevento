<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_message_feedbacks', function (Blueprint $table) {
            $table->json('resolution_json')->nullable()->after('reply_text');
        });

        Schema::table('telegram_message_feedbacks', function (Blueprint $table) {
            $table->json('resolution_json')->nullable()->after('reply_text');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_message_feedbacks', function (Blueprint $table) {
            $table->dropColumn('resolution_json');
        });

        Schema::table('telegram_message_feedbacks', function (Blueprint $table) {
            $table->dropColumn('resolution_json');
        });
    }
};
