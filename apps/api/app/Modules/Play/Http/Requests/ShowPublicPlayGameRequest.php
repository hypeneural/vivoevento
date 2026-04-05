<?php

namespace App\Modules\Play\Http\Requests;

use App\Modules\Play\Support\RuntimeAssetProfile;
use Illuminate\Foundation\Http\FormRequest;

class ShowPublicPlayGameRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'platform' => $this->query('platform'),
            'viewport_width' => $this->query('viewport_width', $this->query('viewportWidth')),
            'viewport_height' => $this->query('viewport_height', $this->query('viewportHeight')),
            'pixel_ratio' => $this->query('pixel_ratio', $this->query('pixelRatio')),
            'save_data' => $this->query('save_data', $this->query('saveData')),
            'effective_type' => $this->query('effective_type', $this->query('effectiveType')),
            'downlink' => $this->query('downlink'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => ['nullable', 'string', 'max:50'],
            'viewport_width' => ['nullable', 'integer', 'min:200', 'max:4096'],
            'viewport_height' => ['nullable', 'integer', 'min:200', 'max:4096'],
            'pixel_ratio' => ['nullable', 'numeric', 'min:0.5', 'max:5'],
            'save_data' => ['nullable', 'boolean'],
            'effective_type' => ['nullable', 'string', 'max:20'],
            'downlink' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function assetProfile(): ?RuntimeAssetProfile
    {
        return RuntimeAssetProfile::fromQuery($this->validated());
    }
}
