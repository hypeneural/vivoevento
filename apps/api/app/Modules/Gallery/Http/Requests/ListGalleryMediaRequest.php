<?php

namespace App\Modules\Gallery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListGalleryMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('gallery.view') || $this->user()?->can('gallery.manage'));
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'search' => ['nullable', 'string', 'max:120'],
            'channel' => ['nullable', Rule::in([
                'upload',
                'link',
                'whatsapp',
                'telegram',
                'qrcode',
            ])],
            'media_type' => ['nullable', Rule::in(['image', 'video'])],
            'featured' => ['nullable', 'boolean'],
            'pinned' => ['nullable', 'boolean'],
            'publication_status' => ['nullable', Rule::in([
                'draft',
                'published',
                'hidden',
                'deleted',
            ])],
            'orientation' => ['nullable', Rule::in(['portrait', 'landscape', 'square'])],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'sort_by' => ['nullable', Rule::in(['sort_order', 'published_at', 'created_at'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
