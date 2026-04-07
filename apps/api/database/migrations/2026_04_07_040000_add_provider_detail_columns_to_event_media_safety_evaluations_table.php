<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_media_safety_evaluations', function (Blueprint $table) {
            $table->json('provider_categories_json')->nullable()->after('category_scores_json');
            $table->json('provider_category_scores_json')->nullable()->after('provider_categories_json');
            $table->json('provider_category_input_types_json')->nullable()->after('provider_category_scores_json');
            $table->json('normalized_provider_json')->nullable()->after('provider_category_input_types_json');
        });
    }

    public function down(): void
    {
        Schema::table('event_media_safety_evaluations', function (Blueprint $table) {
            $table->dropColumn([
                'provider_categories_json',
                'provider_category_scores_json',
                'provider_category_input_types_json',
                'normalized_provider_json',
            ]);
        });
    }
};
