<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_processing_runs', function (Blueprint $table) {
            $table->string('provider_version', 80)->nullable()->after('provider_key');
            $table->string('model_snapshot', 120)->nullable()->after('model_key');
            $table->string('queue_name', 80)->nullable()->after('decision_key');
            $table->string('worker_ref', 120)->nullable()->after('queue_name');
            $table->decimal('cost_units', 10, 4)->nullable()->after('metrics_json');
            $table->string('failure_class', 40)->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('media_processing_runs', function (Blueprint $table) {
            $table->dropColumn([
                'provider_version',
                'model_snapshot',
                'queue_name',
                'worker_ref',
                'cost_units',
                'failure_class',
            ]);
        });
    }
};
