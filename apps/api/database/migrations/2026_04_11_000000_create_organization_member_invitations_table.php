<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_member_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('existing_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invitee_name', 160);
            $table->string('invitee_email', 160)->nullable();
            $table->string('invitee_phone', 40);
            $table->string('role_key', 40);
            $table->string('delivery_channel', 40)->nullable();
            $table->string('delivery_status', 40)->nullable();
            $table->text('delivery_error')->nullable();
            $table->string('token', 120)->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->text('invitation_url')->nullable();
            $table->string('status', 40)->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['invitee_phone', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_member_invitations');
    }
};
