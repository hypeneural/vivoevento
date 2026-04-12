<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_builder_prompt_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('prompt_text');
            $table->string('persona_key')->nullable();
            $table->string('event_type_key')->nullable();
            $table->string('target_layer')->default('mixed');
            $table->string('base_preset_key')->nullable();
            $table->json('request_payload_json')->nullable();
            $table->json('response_payload_json')->nullable();
            $table->string('selected_variation_id')->nullable();
            $table->unsignedInteger('response_schema_version')->default(1);
            $table->string('status')->default('success');
            $table->string('provider_key')->default('local-guardrailed');
            $table->string('model_key')->default('gallery-builder-local-v1');
            $table->timestamps();

            $table->index(['event_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_builder_prompt_runs');
    }
};
