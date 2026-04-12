<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadEventGalleryAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gallery.builder.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'image', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
            'previous_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Selecione uma imagem para enviar.',
            'file.image' => 'O arquivo precisa ser uma imagem valida.',
            'file.max' => 'A imagem nao pode ter mais de 10MB.',
            'file.mimes' => 'Formato aceito: JPG, PNG ou WebP.',
        ];
    }
}
