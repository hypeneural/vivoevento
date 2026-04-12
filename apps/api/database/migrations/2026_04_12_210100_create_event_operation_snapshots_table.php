<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_operation_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('schema_version')->default(1);
            $table->unsignedBigInteger('snapshot_version')->default(1);
            $table->unsignedBigInteger('latest_event_sequence')->default(0);
            $table->string('timeline_cursor', 120)->nullable();
            $table->jsonb('snapshot_json');
            $table->timestamp('server_time')->nullable();
            $table->timestamp('updated_at')->useCurrent();

            $table->index(['event_id', 'snapshot_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_operation_snapshots');
    }
};
