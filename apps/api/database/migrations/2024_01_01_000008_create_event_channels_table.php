<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('channel_type', 40);
            $table->string('provider', 40);
            $table->string('external_id', 180)->nullable();
            $table->string('label', 120)->nullable();
            $table->string('status', 30)->default('active');
            $table->jsonb('config_json')->nullable();
            $table->string('secret_hash', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_channels');
    }
};
