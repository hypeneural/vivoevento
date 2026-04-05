<?php

namespace App\Modules\Play\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MemorySettingsValidator implements GameSettingsValidatorInterface
{
    public function validate(array $settings): array
    {
        $validated = Validator::make(
            array_merge($this->defaults(), $settings),
            [
                'pairsCount' => ['required', 'integer', Rule::in([6, 8, 10])],
                'difficulty' => ['required', 'string', Rule::in(['easy', 'normal', 'medium', 'hard'])],
                'showPreviewSeconds' => ['required', 'integer', 'min:0', 'max:10'],
                'allowDuplicateSource' => ['required', 'boolean'],
                'flipBackDelayMs' => ['required', 'integer', 'min:200', 'max:3000'],
            ],
        )->validate();

        return [
            'pairsCount' => (int) $validated['pairsCount'],
            'difficulty' => $validated['difficulty'] === 'medium' ? 'normal' : $validated['difficulty'],
            'showPreviewSeconds' => (int) $validated['showPreviewSeconds'],
            'allowDuplicateSource' => (bool) $validated['allowDuplicateSource'],
            'flipBackDelayMs' => (int) $validated['flipBackDelayMs'],
            'scoringVersion' => 'memory_v1',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'pairsCount' => 6,
            'difficulty' => 'normal',
            'showPreviewSeconds' => 2,
            'allowDuplicateSource' => false,
            'flipBackDelayMs' => 800,
        ];
    }
}
