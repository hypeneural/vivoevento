<?php

namespace App\Modules\MediaIntelligence\Http\Requests;

use App\Modules\MediaIntelligence\Services\OpenRouterModelPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpsertEventMediaIntelligenceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('media.moderate') ?? false;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'provider_key' => ['required', 'string', 'in:vllm,openrouter,noop'],
            'model_key' => ['required', 'string', 'max:160'],
            'mode' => ['required', 'string', 'in:enrich_only,gate'],
            'prompt_version' => ['nullable', 'string', 'max:100'],
            'approval_prompt' => ['nullable', 'string', 'max:5000'],
            'caption_style_prompt' => ['nullable', 'string', 'max:5000'],
            'response_schema_version' => ['required', 'string', 'max:100'],
            'timeout_ms' => ['required', 'integer', 'min:1000', 'max:30000'],
            'fallback_mode' => ['required', 'string', 'in:review,skip'],
            'require_json_output' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mode = (string) $this->input('mode', 'enrich_only');
            $fallback = (string) $this->input('fallback_mode', 'review');
            $providerKey = (string) $this->input('provider_key', 'vllm');
            $modelKey = (string) $this->input('model_key', '');
            $requireJsonOutput = (bool) $this->boolean('require_json_output');

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
