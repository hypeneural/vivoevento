<?php

namespace App\Modules\MediaIntelligence\Http\Requests;

use App\Modules\MediaIntelligence\Services\OpenRouterModelPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpsertMediaIntelligenceGlobalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super-admin', 'platform-admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'provider_key' => ['sometimes', 'string', 'in:vllm,openrouter,noop'],
            'model_key' => ['sometimes', 'string', 'max:160'],
            'mode' => ['sometimes', 'string', 'in:enrich_only,gate'],
            'prompt_version' => ['sometimes', 'string', 'max:100'],
            'response_schema_version' => ['sometimes', 'string', 'max:100'],
            'timeout_ms' => ['sometimes', 'integer', 'min:1000', 'max:30000'],
            'fallback_mode' => ['sometimes', 'string', 'in:review,skip'],
            'context_scope' => ['sometimes', 'string', 'in:image_only,image_and_text_context'],
            'reply_scope' => ['sometimes', 'string', 'in:image_only,image_and_text_context'],
            'normalized_text_context_mode' => ['sometimes', 'string', 'in:none,body_only,caption_only,body_plus_caption,operator_summary'],
            'require_json_output' => ['sometimes', 'boolean'],
            'contextual_policy_preset_key' => ['sometimes', 'string', 'max:80'],
            'policy_version' => ['sometimes', 'string', 'max:100'],
            'allow_alcohol' => ['sometimes', 'boolean'],
            'allow_tobacco' => ['sometimes', 'boolean'],
            'required_people_context' => ['sometimes', 'string', 'in:optional,required'],
            'blocked_terms' => ['nullable', 'array', 'max:30'],
            'blocked_terms.*' => ['string', 'max:120'],
            'allowed_exceptions' => ['nullable', 'array', 'max:30'],
            'allowed_exceptions.*' => ['string', 'max:120'],
            'freeform_instruction' => ['nullable', 'string', 'max:5000'],
            'reply_text_prompt' => ['sometimes', 'string', 'max:5000'],
            'reply_text_fixed_templates' => ['nullable', 'array', 'max:20'],
            'reply_text_fixed_templates.*' => ['string', 'max:500'],
            'reply_prompt_preset_id' => ['nullable', 'integer', 'exists:ai_media_reply_prompt_presets,id'],
            'reply_ai_rate_limit_enabled' => ['nullable', 'boolean'],
            'reply_ai_rate_limit_max_messages' => ['nullable', 'integer', 'min:1', 'max:100'],
            'reply_ai_rate_limit_window_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mode = (string) $this->input('mode', 'enrich_only');
            $fallback = (string) $this->input('fallback_mode', 'review');
            $providerKey = (string) $this->input('provider_key', 'vllm');
            $modelKey = (string) $this->input('model_key', '');
            $requireJsonOutput = (bool) $this->boolean('require_json_output', true);

            if ($mode === 'gate' && $fallback !== 'review') {
                $validator->errors()->add(
                    'fallback_mode',
                    'Eventos com VLM em gate devem usar fallback review para nunca aprovar por erro tecnico.',
                );
            }

            if ($providerKey === 'openrouter') {
                $error = app(OpenRouterModelPolicy::class)->validationError($modelKey, $requireJsonOutput);

                if ($error !== null) {
                    $validator->errors()->add('model_key', $error);
                }
            }
        });
    }
}
