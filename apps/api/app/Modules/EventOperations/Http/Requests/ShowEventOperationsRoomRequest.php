<?php

namespace App\Modules\EventOperations\Http\Requests;

use App\Modules\Events\Models\Event;
use App\Shared\Support\EventAccessService;
use Illuminate\Foundation\Http\FormRequest;

class ShowEventOperationsRoomRequest extends FormRequest
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
        return [];
    }
}
