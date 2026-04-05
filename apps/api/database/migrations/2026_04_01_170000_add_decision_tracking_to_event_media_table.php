<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_media', function (Blueprint $table) {
            $table->string('decision_source', 40)->nullable()->after('vlm_status');
            $table->timestamp('decision_overridden_at')->nullable()->after('decision_source');
            $table->foreignId('decision_overridden_by_user_id')
                ->nullable()
                ->after('decision_overridden_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('decision_override_reason')->nullable()->after('decision_overridden_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decision_overridden_by_user_id');
            $table->dropColumn([
                'decision_source',
                'decision_overridden_at',
                'decision_override_reason',
            ]);
        });
    }
};
