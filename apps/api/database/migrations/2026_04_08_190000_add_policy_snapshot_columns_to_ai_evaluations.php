<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_media_safety_evaluations', function (Blueprint $table) {
            $table->json('policy_snapshot_json')->nullable()->after('request_payload_json');
            $table->json('policy_sources_json')->nullable()->after('policy_snapshot_json');
        });

        Schema::table('event_media_vlm_evaluations', function (Blueprint $table) {
            $table->json('policy_snapshot_json')->nullable()->after('prompt_context_json');
            $table->json('policy_sources_json')->nullable()->after('policy_snapshot_json');
        });
    }

    public function down(): void
    {
        Schema::table('event_media_safety_evaluations', function (Blueprint $table) {
            $table->dropColumn(['policy_snapshot_json', 'policy_sources_json']);
        });

        Schema::table('event_media_vlm_evaluations', function (Blueprint $table) {
            $table->dropColumn(['policy_snapshot_json', 'policy_sources_json']);
        });
    }
};
