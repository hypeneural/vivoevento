<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_public_link_qr_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('link_key', 40);
            $table->string('config_version', 80);
            $table->json('config_json');
            $table->string('svg_path')->nullable();
            $table->string('png_path')->nullable();
            $table->timestamp('last_rendered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'link_key'], 'event_public_link_qr_configs_event_link_unique');
            $table->index(['event_id', 'updated_at'], 'event_public_link_qr_configs_event_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_public_link_qr_configs');
    }
};
