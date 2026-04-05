<?php

namespace App\Modules\Wall\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WallHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'player_instance_id' => ['required', 'string', 'max:120'],
            'runtime_status' => ['required', Rule::in(['booting', 'idle', 'playing', 'paused', 'stopped', 'expired', 'error'])],
            'connection_status' => ['required', Rule::in(['idle', 'connecting', 'connected', 'reconnecting', 'disconnected', 'error'])],
            'current_item_id' => ['nullable', 'string', 'max:120'],
            'current_sender_key' => ['nullable', 'string', 'max:180'],
            'ready_count' => ['required', 'integer', 'min:0', 'max:5000'],
            'loading_count' => ['required', 'integer', 'min:0', 'max:5000'],
            'error_count' => ['required', 'integer', 'min:0', 'max:5000'],
            'stale_count' => ['required', 'integer', 'min:0', 'max:5000'],
            'cache_enabled' => ['required', 'boolean'],
            'persistent_storage' => ['required', Rule::in(['none', 'localstorage', 'indexeddb', 'cache_api', 'unavailable', 'unknown'])],
            'cache_usage_bytes' => ['nullable', 'integer', 'min:0'],
            'cache_quota_bytes' => ['nullable', 'integer', 'min:0'],
            'cache_hit_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'cache_miss_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'cache_stale_fallback_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'last_sync_at' => ['nullable', 'date'],
            'last_fallback_reason' => ['nullable', 'string', 'max:120'],
        ];
    }
}
