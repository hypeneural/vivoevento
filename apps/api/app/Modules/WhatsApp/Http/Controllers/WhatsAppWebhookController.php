<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\WhatsApp\Jobs\ProcessInboundWebhookJob;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives webhooks from WhatsApp providers.
 *
 * Route: POST /webhooks/whatsapp/{provider}/{instanceKey}/inbound
 *
 * No auth — validated by webhook_secret if configured.
 * Always responds 200 immediately and processes in background.
 */
class WhatsAppWebhookController extends BaseController
{
    /**
     * Handle inbound message webhooks.
     */
    public function inbound(Request $request, string $provider, string $instanceKey): JsonResponse
    {
        ProcessInboundWebhookJob::dispatch($provider, $instanceKey, $request->all());

        return response()->json(['status' => 'received'], 200);
    }

    /**
     * Handle status update webhooks.
     */
    public function status(Request $request, string $provider, string $instanceKey): JsonResponse
    {
        ProcessInboundWebhookJob::dispatch($provider, $instanceKey, array_merge(
            $request->all(),
            ['_webhook_type' => 'status']
        ));

        return response()->json(['status' => 'received'], 200);
    }

    /**
     * Handle delivery/read receipt webhooks.
     */
    public function delivery(Request $request, string $provider, string $instanceKey): JsonResponse
    {
        ProcessInboundWebhookJob::dispatch($provider, $instanceKey, array_merge(
            $request->all(),
            ['_webhook_type' => 'delivery']
        ));

        return response()->json(['status' => 'received'], 200);
    }
}
