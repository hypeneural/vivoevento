<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 40)->default('partner');
            $table->string('legal_name', 200)->nullable();
            $table->string('trade_name', 160);
            $table->string('document_number', 30)->nullable();
            $table->string('slug', 180)->unique();
            $table->string('email', 160)->nullable();
            $table->string('billing_email', 160)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('primary_color', 20)->nullable();
            $table->string('secondary_color', 20)->nullable();
            $table->string('subdomain', 80)->nullable()->unique();
            $table->string('custom_domain', 255)->nullable();
            $table->string('timezone', 64)->default('America/Sao_Paulo');
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
