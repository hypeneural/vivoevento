<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_media_reply_test_runs', function (Blueprint $table) {
            $table->json('safety_results_json')->nullable()->after('images_json');
            $table->json('contextual_results_json')->nullable()->after('safety_results_json');
            $table->json('final_summary_json')->nullable()->after('contextual_results_json');
            $table->json('policy_snapshot_json')->nullable()->after('final_summary_json');
            $table->json('policy_sources_json')->nullable()->after('policy_snapshot_json');
        });
    }

    public function down(): void
    {
        Schema::table('ai_media_reply_test_runs', function (Blueprint $table) {
            $table->dropColumn([
                'safety_results_json',
                'contextual_results_json',
                'final_summary_json',
                'policy_snapshot_json',
                'policy_sources_json',
            ]);
        });
    }
};
