<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\CancelBillingOrderViaGatewayAction;
use App\Modules\Billing\Actions\RefreshBillingOrderGatewayAction;
use App\Modules\Billing\Actions\RetryBillingOrderGatewayCheckoutAction;
use App\Modules\Billing\Http\Requests\CancelBillingOrderRequest;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\EventPurchase;
use App\Modules\Billing\Models\Payment;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BillingOrderController extends BaseController
{
    public function cancel(
        CancelBillingOrderRequest $request,
        BillingOrder $billingOrder,
        CancelBillingOrderViaGatewayAction $action,
    ): JsonResponse {
        $this->ensureCanManageBillingOrder($request->user(), $billingOrder);

        $result = $action->execute($billingOrder, $request->validated());
        $order = $result['order'];
        $payment = $result['payment'] ?? null;
        $invoice = $result['invoice'] ?? null;

        return $this->success([
            'message' => $result['message'] ?? 'Operacao de billing executada com sucesso.',
            'order' => $this->serializeOrder($order),
            'payment' => $this->serializePayment($payment),
            'invoice' => $invoice ? [
                'id' => $invoice->id,
                'status' => $invoice->status?->value,
                'paid_at' => $invoice->paid_at?->toISOString(),
            ] : null,
        ]);
    }

    public function refresh(
        BillingOrder $billingOrder,
        RefreshBillingOrderGatewayAction $action,
    ): JsonResponse {
        $this->ensureCanManageBillingOrder(request()->user(), $billingOrder);

        $result = $action->execute($billingOrder);
        $order = $result['order'];
        $payment = $result['payment'] ?? null;
        $purchase = $result['purchase'] ?? null;

        return $this->success([
            'message' => 'Estado do gateway sincronizado com sucesso.',
            'sync' => $result['sync'],
            'order' => $this->serializeOrder($order),
            'payment' => $this->serializePayment($payment),
            'purchase' => $this->serializePurchase($purchase),
            'gateway' => $result['gateway'],
        ]);
    }

    public function retry(
        BillingOrder $billingOrder,
        RetryBillingOrderGatewayCheckoutAction $action,
    ): JsonResponse {
        $this->ensureCanManageBillingOrder(request()->user(), $billingOrder);

        $result = $action->execute($billingOrder);
        $order = $result['order'];
        $payment = $result['payment'] ?? null;
        $purchase = $result['purchase'] ?? null;

        return $this->success([
            'message' => $this->retryMessage((string) data_get($result, 'retry.action')),
            'retry' => $result['retry'],
            'order' => $this->serializeOrder($order),
            'payment' => $this->serializePayment($payment),
            'purchase' => $this->serializePurchase($purchase),
        ]);
    }

    private function ensureCanManageBillingOrder(?object $user, BillingOrder $billingOrder): void
    {
        abort_unless($user && $user->can('billing.manage'), 403);

        $organization = $user->currentOrganization();

        if (! $organization || (int) $billingOrder->organization_id !== (int) $organization->id) {
            throw new NotFoundHttpException();
        }
    }

    private function serializeOrder(BillingOrder $order): array
    {
        return [
            'id' => $order->id,
            'uuid' => $order->uuid,
            'status' => $order->status?->value,
            'gateway_provider' => $order->gateway_provider,
            'gateway_order_id' => $order->gateway_order_id,
            'gateway_charge_id' => $order->gateway_charge_id,
            'gateway_transaction_id' => $order->gateway_transaction_id,
            'gateway_status' => $order->gateway_status,
            'confirmed_at' => $order->confirmed_at?->toISOString(),
            'paid_at' => $order->paid_at?->toISOString(),
            'failed_at' => $order->failed_at?->toISOString(),
            'canceled_at' => $order->canceled_at?->toISOString(),
            'refunded_at' => $order->refunded_at?->toISOString(),
            'expires_at' => $order->expires_at?->toISOString(),
        ];
    }

    private function serializePayment(?Payment $payment): ?array
    {
        if (! $payment) {
            return null;
        }

        return [
            'id' => $payment->id,
            'status' => $payment->status?->value,
            'gateway_charge_id' => $payment->gateway_charge_id,
            'gateway_transaction_id' => $payment->gateway_transaction_id,
            'gateway_status' => $payment->gateway_status,
            'acquirer_message' => $payment->acquirer_message,
            'acquirer_return_code' => $payment->acquirer_return_code,
            'refunded_at' => $payment->refunded_at?->toISOString(),
            'failed_at' => $payment->failed_at?->toISOString(),
            'paid_at' => $payment->paid_at?->toISOString(),
            'expires_at' => $payment->expires_at?->toISOString(),
        ];
    }

    private function serializePurchase(?EventPurchase $purchase): ?array
    {
        if (! $purchase) {
            return null;
        }

        return [
            'id' => $purchase->id,
            'status' => $purchase->status,
            'package_id' => $purchase->package_id,
            'purchased_at' => $purchase->purchased_at?->toISOString(),
        ];
    }

    private function retryMessage(string $action): string
    {
        return match ($action) {
            'gateway_checkout_retried' => 'Retentativa do checkout enviada ao gateway com a chave idempotente atual.',
            'skipped_existing_gateway_snapshot' => 'O pedido ja possui snapshot do gateway. Nenhuma nova chamada externa foi feita.',
            'skipped_terminal_order' => 'O pedido ja esta em estado terminal. Nenhuma nova tentativa foi feita.',
            default => 'Retentativa operacional do checkout concluida.',
        };
    }
}
