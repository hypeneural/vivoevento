<?php

namespace App\Modules\MediaProcessing\Http\Requests;

use App\Modules\MediaProcessing\Enums\MediaDecisionSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCatalogMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('media.view') || $this->user()?->can('media.moderate');
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in([
                'received',
                'processing',
                'pending_moderation',
                'approved',
                'published',
                'rejected',
                'error',
            ])],
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
            'duplicates' => ['nullable', 'boolean'],
            'has_caption' => ['nullable', 'boolean'],
            'face_search_enabled' => ['nullable', 'boolean'],
            'face_index_status' => ['nullable', Rule::in([
                'queued',
                'processing',
                'indexed',
                'skipped',
                'failed',
            ])],
            'safety_status' => ['nullable', Rule::in([
                'queued',
                'pass',
                'review',
                'block',
                'failed',
                'skipped',
            ])],
            'vlm_status' => ['nullable', Rule::in([
                'queued',
                'completed',
                'review',
                'rejected',
                'failed',
                'skipped',
            ])],
            'decision_source' => ['nullable', Rule::in(array_map(
                static fn (MediaDecisionSource $source) => $source->value,
                MediaDecisionSource::cases(),
            ))],
            'publication_status' => ['nullable', Rule::in([
                'draft',
                'published',
                'hidden',
                'deleted',
            ])],
            'orientation' => ['nullable', Rule::in(['portrait', 'landscape', 'square'])],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'sort_by' => ['nullable', Rule::in(['created_at', 'published_at', 'sort_order'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
