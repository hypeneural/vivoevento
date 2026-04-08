<?php

namespace App\Modules\ContentModeration\Http\Requests;

use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpsertContentModerationGlobalSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('objective_safety_scope') && ! $this->has('analysis_scope')) {
            $this->merge([
                'analysis_scope' => $this->input('objective_safety_scope'),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super-admin', 'platform-admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'provider_key' => ['sometimes', 'string', 'in:openai,noop'],
            'mode' => ['sometimes', 'string', 'in:enforced,observe_only'],
            'threshold_version' => ['sometimes', 'string', 'max:100'],
            'fallback_mode' => ['sometimes', 'string', 'in:review,block'],
            'analysis_scope' => ['sometimes', 'string', 'in:image_only,image_and_text_context'],
            'objective_safety_scope' => ['sometimes', 'string', 'in:image_only,image_and_text_context'],
            'normalized_text_context_mode' => ['sometimes', 'string', 'in:none,body_only,caption_only,body_plus_caption,operator_summary'],
            'hard_block_thresholds' => ['sometimes', 'array'],
            'review_thresholds' => ['sometimes', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $thresholdGroups = [
                'hard_block_thresholds' => (array) $this->input('hard_block_thresholds', []),
                'review_thresholds' => (array) $this->input('review_thresholds', []),
            ];

            $knownCategories = array_keys(EventContentModerationSetting::defaultReviewThresholds());

            foreach ($thresholdGroups as $groupKey => $thresholds) {
                if (! $this->has($groupKey)) {
                    continue;
                }

                foreach ($knownCategories as $category) {
                    $value = $thresholds[$category] ?? null;

                    if (! is_numeric($value)) {
                        $validator->errors()->add("{$groupKey}.{$category}", 'Informe um threshold numerico entre 0 e 1.');
                        continue;
                    }

                    $float = (float) $value;

                    if ($float < 0.0 || $float > 1.0) {
                        $validator->errors()->add("{$groupKey}.{$category}", 'O threshold deve estar entre 0 e 1.');
                    }
                }
            }
        });
    }
}
