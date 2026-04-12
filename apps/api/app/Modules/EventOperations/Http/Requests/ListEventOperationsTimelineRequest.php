<?php

namespace App\Modules\EventOperations\Http\Requests;

use App\Modules\Events\Models\Event;
use App\Shared\Support\EventAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListEventOperationsTimelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $event = $this->route('event');

        return $user !== null
            && $event instanceof Event
            && app(EventAccessService::class)->can($user, $event, 'operations.view');
    }

    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string', 'regex:/^evt_\\d{6,}$/'],
            'station_key' => ['nullable', Rule::in([
                'intake',
                'download',
                'variants',
                'safety',
                'intelligence',
                'human_review',
                'gallery',
                'wall',
                'feedback',
                'alerts',
            ])],
            'severity' => ['nullable', Rule::in(['info', 'warning', 'critical'])],
            'event_media_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
