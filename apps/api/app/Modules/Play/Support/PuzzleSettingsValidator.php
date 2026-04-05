<?php

namespace App\Modules\Play\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PuzzleSettingsValidator implements GameSettingsValidatorInterface
{
    public function validate(array $settings): array
    {
        $validated = Validator::make(
            array_merge($this->defaults(), $settings),
            [
                'gridSize' => ['required', 'string', Rule::in(['2x2', '3x3'])],
                'showReferenceImage' => ['required', 'boolean'],
                'snapEnabled' => ['required', 'boolean'],
                'dragTolerance' => ['required', 'integer', 'min:0', 'max:64'],
            ],
        )->validate();

        return [
            'gridSize' => $validated['gridSize'],
            'showReferenceImage' => (bool) $validated['showReferenceImage'],
            'snapEnabled' => (bool) $validated['snapEnabled'],
            'dragTolerance' => (int) $validated['dragTolerance'],
            'scoringVersion' => 'puzzle_v1',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'gridSize' => '2x2',
            'showReferenceImage' => true,
            'snapEnabled' => true,
            'dragTolerance' => 18,
        ];
    }
}
