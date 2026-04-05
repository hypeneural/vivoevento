<?php

namespace App\Modules\MediaProcessing\Http\Requests;

use App\Modules\MediaProcessing\Enums\MediaReprocessStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReprocessEventMediaStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
            'stage' => ['required', Rule::in(MediaReprocessStage::values())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'stage' => $this->route('stage'),
        ]);
    }
}
