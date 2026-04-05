<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\ReceiveBillingWebhookAction;
use App\Modules\Billing\Actions\VerifyBillingWebhookBasicAuthAction;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingWebhookController extends BaseController
{
    public function handle(
        Request $request,
        string $provider,
        VerifyBillingWebhookBasicAuthAction $verifyWebhookBasicAuth,
        ReceiveBillingWebhookAction $action,
    ): JsonResponse {
        if (! $verifyWebhookBasicAuth->execute(
            $provider,
            $request->getUser(),
            $request->getPassword(),
            $request->headers->all(),
            $request->server->all(),
        )) {
            return $this->error('Unauthorized webhook credentials.', 401);
        }

        return $this->success(
            $action->execute($provider, $request->all(), $request->headers->all()),
            message: 'Billing webhook accepted.'
        );
    }
}
