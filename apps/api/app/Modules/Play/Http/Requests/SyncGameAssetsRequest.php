<?php

namespace App\Modules\Play\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncGameAssetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('play.manage');
    }

    public function rules(): array
    {
        return [
            'assets' => ['required', 'array'],
            'assets.*.media_id' => ['required', 'integer', 'exists:event_media,id'],
            'assets.*.role' => ['required', 'string', 'max:40'],
            'assets.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'assets.*.metadata' => ['nullable', 'array'],
        ];
    }
}
