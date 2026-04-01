<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\WhatsApp\Http\Resources\WhatsAppDispatchLogResource;
use App\Modules\WhatsApp\Http\Resources\WhatsAppInboundEventResource;
use App\Modules\WhatsApp\Models\WhatsAppDispatchLog;
use App\Modules\WhatsApp\Models\WhatsAppInboundEvent;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppLogController extends BaseController
{
    public function dispatch(Request $request): JsonResponse
    {
        $logs = WhatsAppDispatchLog::query()
            ->when($request->input('instance_id'), fn ($q, $v) => $q->where('instance_id', $v))
            ->when($request->input('success'), fn ($q, $v) => $q->where('success', filter_var($v, FILTER_VALIDATE_BOOLEAN)))
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated(WhatsAppDispatchLogResource::collection($logs));
    }

    public function inbound(Request $request): JsonResponse
    {
        $events = WhatsAppInboundEvent::query()
            ->when($request->input('instance_id'), fn ($q, $v) => $q->where('instance_id', $v))
            ->when($request->input('processing_status'), fn ($q, $v) => $q->where('processing_status', $v))
            ->latest('received_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated(WhatsAppInboundEventResource::collection($events));
    }
}
