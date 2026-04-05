<?php

namespace App\Modules\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadEventBrandingAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('events.create') || $this->user()?->can('events.update');
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(['cover', 'logo'])],
            'file' => ['required', 'image', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
            'previous_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'kind.required' => 'Informe o tipo do asset.',
            'kind.in' => 'Tipo de asset invalido.',
            'file.required' => 'Selecione uma imagem para enviar.',
            'file.image' => 'O arquivo precisa ser uma imagem valida.',
            'file.max' => 'A imagem nao pode ter mais de 10MB.',
            'file.mimes' => 'Formato aceito: JPG, PNG ou WebP.',
        ];
    }
}
