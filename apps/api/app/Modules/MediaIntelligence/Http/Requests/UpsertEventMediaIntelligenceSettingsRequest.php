<?php

namespace App\Modules\MediaIntelligence\Http\Requests;

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
            'provider_key' => ['required', 'string', 'in:vllm,noop'],
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

            if ($mode === 'gate' && $fallback !== 'review') {
                $validator->errors()->add(
                    'fallback_mode',
                    'Eventos com VLM em gate devem usar fallback review para nunca aprovar por erro tecnico.',
                );
            }
        });
    }
}
