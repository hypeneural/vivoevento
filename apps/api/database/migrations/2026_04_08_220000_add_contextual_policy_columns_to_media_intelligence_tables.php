<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_intelligence_global_settings', function (Blueprint $table) {
            $table->boolean('enabled')->default(false)->after('id');
            $table->string('provider_key', 40)->default('vllm')->after('enabled');
            $table->string('model_key', 160)->default('Qwen/Qwen2.5-VL-3B-Instruct')->after('provider_key');
            $table->string('mode', 40)->default('enrich_only')->after('model_key');
            $table->string('prompt_version', 100)->default('foundation-v1')->after('mode');
            $table->string('response_schema_version', 100)->default('contextual-v2')->after('prompt_version');
            $table->unsignedInteger('timeout_ms')->default(12000)->after('response_schema_version');
            $table->string('fallback_mode', 40)->default('review')->after('timeout_ms');
            $table->string('context_scope', 40)->default('image_and_text_context')->after('fallback_mode');
            $table->string('reply_scope', 40)->default('image_and_text_context')->after('context_scope');
            $table->string('normalized_text_context_mode', 40)->default('body_plus_caption')->after('reply_scope');
            $table->boolean('require_json_output')->default(true)->after('normalized_text_context_mode');
            $table->string('contextual_policy_preset_key', 80)->default('homologacao_livre')->after('require_json_output');
            $table->string('policy_version', 100)->default('contextual-policy-v1')->after('contextual_policy_preset_key');
            $table->boolean('allow_alcohol')->default(true)->after('policy_version');
            $table->boolean('allow_tobacco')->default(true)->after('allow_alcohol');
            $table->string('required_people_context', 40)->default('optional')->after('allow_tobacco');
            $table->json('blocked_terms_json')->nullable()->after('required_people_context');
            $table->json('allowed_exceptions_json')->nullable()->after('blocked_terms_json');
            $table->text('freeform_instruction')->nullable()->after('allowed_exceptions_json');
        });

        Schema::table('event_media_intelligence_settings', function (Blueprint $table) {
            $table->string('contextual_policy_preset_key', 80)->nullable()->after('normalized_text_context_mode');
            $table->string('policy_version', 100)->nullable()->after('contextual_policy_preset_key');
            $table->boolean('allow_alcohol')->nullable()->after('policy_version');
            $table->boolean('allow_tobacco')->nullable()->after('allow_alcohol');
            $table->string('required_people_context', 40)->nullable()->after('allow_tobacco');
            $table->json('blocked_terms_json')->nullable()->after('required_people_context');
            $table->json('allowed_exceptions_json')->nullable()->after('blocked_terms_json');
            $table->text('freeform_instruction')->nullable()->after('allowed_exceptions_json');
        });

        Schema::table('event_media_vlm_evaluations', function (Blueprint $table) {
            $table->string('reason_code', 120)->nullable()->after('reason');
            $table->json('matched_policies_json')->nullable()->after('reason_code');
            $table->json('matched_exceptions_json')->nullable()->after('matched_policies_json');
            $table->string('input_scope_used', 40)->nullable()->after('matched_exceptions_json');
            $table->json('input_types_considered_json')->nullable()->after('input_scope_used');
            $table->string('confidence_band', 40)->nullable()->after('input_types_considered_json');
            $table->string('publish_eligibility', 40)->nullable()->after('confidence_band');
        });
    }

    public function down(): void
    {
        Schema::table('event_media_vlm_evaluations', function (Blueprint $table) {
            $table->dropColumn([
                'reason_code',
                'matched_policies_json',
                'matched_exceptions_json',
                'input_scope_used',
                'input_types_considered_json',
                'confidence_band',
                'publish_eligibility',
            ]);
        });

        Schema::table('event_media_intelligence_settings', function (Blueprint $table) {
            $table->dropColumn([
                'contextual_policy_preset_key',
                'policy_version',
                'allow_alcohol',
                'allow_tobacco',
                'required_people_context',
                'blocked_terms_json',
                'allowed_exceptions_json',
                'freeform_instruction',
            ]);
        });

        Schema::table('media_intelligence_global_settings', function (Blueprint $table) {
            $table->dropColumn([
                'enabled',
                'provider_key',
                'model_key',
                'mode',
                'prompt_version',
                'response_schema_version',
                'timeout_ms',
                'fallback_mode',
                'context_scope',
                'reply_scope',
                'normalized_text_context_mode',
                'require_json_output',
                'contextual_policy_preset_key',
                'policy_version',
                'allow_alcohol',
                'allow_tobacco',
                'required_people_context',
                'blocked_terms_json',
                'allowed_exceptions_json',
                'freeform_instruction',
            ]);
        });
    }
};
