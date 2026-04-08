<?php

namespace App\Modules\Wall\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWallAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization via policy/middleware
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:20480', // 20MB
                'mimes:jpg,jpeg,png,webp,gif,mp4',
            ],
            'duration_seconds' => [
                'sometimes',
                'integer',
                'min:3',
                'max:120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'O arquivo do anúncio deve ter no máximo 20MB.',
            'file.mimes' => 'Formato não suportado. Use: JPG, PNG, WebP, GIF ou MP4.',
            'duration_seconds.min' => 'Duração mínima: 3 segundos.',
            'duration_seconds.max' => 'Duração máxima: 120 segundos.',
        ];
    }
}
