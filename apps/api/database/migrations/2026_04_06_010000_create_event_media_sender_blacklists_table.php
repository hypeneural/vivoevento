<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_media_sender_blacklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('identity_type', 30);
            $table->string('identity_value', 180);
            $table->string('normalized_phone', 40)->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event_id', 'identity_type', 'identity_value'], 'event_sender_blacklists_unique');
            $table->index(['event_id', 'is_active'], 'event_sender_blacklists_event_active_idx');
            $table->index(['event_id', 'normalized_phone'], 'event_sender_blacklists_event_phone_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_media_sender_blacklists');
    }
};
