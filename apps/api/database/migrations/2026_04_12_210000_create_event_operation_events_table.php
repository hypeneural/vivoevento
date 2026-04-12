<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_operation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_media_id')->nullable()->constrained('event_media')->nullOnDelete();
            $table->foreignId('inbound_message_id')->nullable()->constrained('inbound_messages')->nullOnDelete();
            $table->string('station_key', 40);
            $table->string('event_key', 80);
            $table->string('severity', 20)->default('info');
            $table->string('urgency', 20)->default('normal');
            $table->string('title', 160);
            $table->text('summary')->nullable();
            $table->jsonb('payload_json')->nullable();
            $table->string('animation_hint', 40)->nullable();
            $table->decimal('station_load', 5, 2)->nullable();
            $table->unsignedInteger('queue_depth')->default(0);
            $table->string('render_group', 40)->nullable();
            $table->string('dedupe_window_key', 120)->nullable();
            $table->string('correlation_key', 120)->nullable();
            $table->unsignedBigInteger('event_sequence');
            $table->timestamp('occurred_at');

            $table->unique(['event_id', 'event_sequence']);
            $table->index(['event_id', 'occurred_at']);
            $table->index(['event_id', 'station_key', 'occurred_at']);
            $table->index(['event_id', 'correlation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_operation_events');
    }
};
