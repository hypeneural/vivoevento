<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\ConfirmPublicEventCheckoutAction;
use App\Modules\Billing\Actions\CreatePublicEventCheckoutAction;
use App\Modules\Billing\Actions\ShowPublicEventCheckoutAction;
use App\Modules\Billing\Http\Requests\ConfirmPublicEventCheckoutRequest;
use App\Modules\Billing\Http\Requests\StorePublicEventCheckoutRequest;
use App\Modules\Billing\Http\Resources\PublicEventCheckoutResource;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\BillingOrderItem;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicEventCheckoutController extends BaseController
{
    public function store(
        Request $httpRequest,
        StorePublicEventCheckoutRequest $request,
        CreatePublicEventCheckoutAction $action,
    ): JsonResponse {
        return $this->created(
            new PublicEventCheckoutResource(
                $action->execute(
                    $request->validated(),
                    $httpRequest->user('sanctum'),
                )
            )
        );
    }

    public function confirm(
        ConfirmPublicEventCheckoutRequest $request,
        BillingOrder $billingOrder,
        ConfirmPublicEventCheckoutAction $action,
    ): JsonResponse {
        $this->ensurePublicEventCheckout($billingOrder);

        return $this->success(
            new PublicEventCheckoutResource(
                $action->execute($billingOrder, $request->validated())
            )
        );
    }

    public function show(
        BillingOrder $billingOrder,
        ShowPublicEventCheckoutAction $action,
    ): JsonResponse {
        $this->ensurePublicEventCheckout($billingOrder);

        return $this->success(
            new PublicEventCheckoutResource(
                $action->execute($billingOrder)
            )
        );
    }

    private function ensurePublicEventCheckout(BillingOrder $billingOrder): void
    {
        $hasEventPackageItem = BillingOrderItem::query()
            ->where('billing_order_id', $billingOrder->id)
            ->where('item_type', 'event_package')
            ->exists();

        if (! $billingOrder->mode?->value || $billingOrder->mode->value !== 'event_package' || ! $hasEventPackageItem) {
            throw new NotFoundHttpException();
        }
    }
}
