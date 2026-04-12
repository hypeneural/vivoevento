<?php

namespace App\Modules\Gallery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListGalleryPresetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gallery.builder.manage') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
