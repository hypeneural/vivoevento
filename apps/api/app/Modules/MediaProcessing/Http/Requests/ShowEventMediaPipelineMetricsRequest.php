<?php

namespace App\Modules\MediaProcessing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowEventMediaPipelineMetricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('media.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'include_deleted' => ['nullable', 'boolean'],
        ];
    }
}
