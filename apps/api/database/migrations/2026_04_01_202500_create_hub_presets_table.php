<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('name', 120);
            $table->string('description', 180)->nullable();
            $table->string('theme_key', 40);
            $table->string('layout_key', 40);
            $table->json('preset_payload_json');
            $table->timestamps();

            $table->index(['organization_id', 'theme_key']);
            $table->index(['organization_id', 'layout_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_presets');
    }
};
