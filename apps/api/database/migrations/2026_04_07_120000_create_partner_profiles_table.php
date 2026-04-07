<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('segment', 80)->nullable();
            $table->string('business_stage', 60)->nullable();
            $table->foreignId('account_owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->jsonb('tags_json')->nullable();
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();

            $table->index('segment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_profiles');
    }
};
