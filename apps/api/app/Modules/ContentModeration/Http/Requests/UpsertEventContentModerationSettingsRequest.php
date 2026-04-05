<?php

namespace App\Modules\ContentModeration\Http\Requests;

use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpsertEventContentModerationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('media.moderate') ?? false;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'provider_key' => ['required', 'string', 'in:openai,noop'],
            'mode' => ['nullable', 'string', 'in:enforced,observe_only'],
            'threshold_version' => ['nullable', 'string', 'max:100'],
            'fallback_mode' => ['required', 'string', 'in:review,block'],
            'hard_block_thresholds' => ['required', 'array'],
            'review_thresholds' => ['required', 'array'],
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
