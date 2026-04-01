<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('event_media_id')->nullable();
            $table->string('event_name', 80);
            $table->string('actor_type', 40)->nullable();
            $table->string('actor_id', 120)->nullable();
            $table->string('channel', 40)->nullable();
            $table->jsonb('metadata_json')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['event_id', 'event_name']);
            $table->index(['organization_id', 'event_name']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
