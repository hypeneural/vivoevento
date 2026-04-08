<?php

namespace App\Modules\MediaIntelligence\Http\Requests;

use App\Modules\MediaIntelligence\Services\OpenRouterModelPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RunMediaReplyPromptTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'provider_key' => ['required', 'string', 'in:vllm,openrouter,noop'],
            'model_key' => ['required', 'string', 'max:160'],
            'prompt_template' => ['nullable', 'string', 'max:5000'],
            'preset_id' => ['nullable', 'integer', 'exists:ai_media_reply_prompt_presets,id'],
            'images' => ['required', 'array', 'min:1', 'max:3'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $providerKey = (string) $this->input('provider_key', 'vllm');
            $modelKey = (string) $this->input('model_key', '');

            if ($providerKey === 'openrouter') {
                $error = app(OpenRouterModelPolicy::class)->validationError($modelKey, true);

                if ($error !== null) {
                    $validator->errors()->add('model_key', $error);
                }
            }
        });
    }
}
