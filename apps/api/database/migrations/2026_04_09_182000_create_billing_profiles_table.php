<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('gateway_provider', 30)->default('pagarme');
            $table->string('gateway_customer_id', 120)->nullable();
            $table->string('gateway_default_card_id', 120)->nullable();
            $table->string('payer_name', 120)->nullable();
            $table->string('payer_email', 120)->nullable();
            $table->string('payer_document', 40)->nullable();
            $table->string('payer_phone', 40)->nullable();
            $table->jsonb('billing_address_json')->nullable();
            $table->jsonb('metadata_json')->nullable();
            $table->timestamps();

            $table->unique('organization_id', 'billing_profiles_org_unique');
            $table->unique('gateway_customer_id', 'billing_profiles_gateway_customer_unique');
            $table->index(['gateway_provider', 'gateway_customer_id'], 'billing_profiles_provider_customer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_profiles');
    }
};
