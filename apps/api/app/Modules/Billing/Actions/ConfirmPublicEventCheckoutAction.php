<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Services\PublicEventCheckoutPayloadBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ConfirmPublicEventCheckoutAction
{
    public function __construct(
        private readonly ActivatePaidEventPackageOrderAction $activatePaidEventPackageOrder,
        private readonly PublicEventCheckoutPayloadBuilder $payloads,
    ) {}

    public function execute(BillingOrder $billingOrder, array $data): array
    {
        $confirmedAt = isset($data['confirmed_at']) ? Carbon::parse($data['confirmed_at']) : now();
        $billingOrder->loadMissing(['event']);

        if (! $billingOrder->status?->isPendingPayment() && ! $billingOrder->status?->isPaid()) {
            throw ValidationException::withMessages([
                'checkout' => ['Este checkout nao esta em estado valido para confirmacao.'],
            ]);
        }

        $activation = $this->activatePaidEventPackageOrder->execute($billingOrder, [
            'gateway_provider' => $data['gateway_provider'] ?? $billingOrder->gateway_provider ?? 'manual',
            'gateway_order_id' => $data['gateway_order_id'] ?? $billingOrder->gateway_order_id,
            'gateway_payment_id' => $data['gateway_payment_id'] ?? $data['gateway_order_id'] ?? $billingOrder->gateway_order_id,
            'confirmed_at' => $confirmedAt,
            'payment_payload' => [
                'source' => 'public_event_checkout_confirm',
                'billing_order_uuid' => $billingOrder->uuid,
            ],
        ]);

        return $this->payloads->build($activation['order'], [
            'message' => $billingOrder->status?->isPaid()
                ? 'Checkout ja estava confirmado.'
                : 'Pagamento confirmado e evento ativado com sucesso.',
            'event' => $activation['event'],
            'onboarding' => [
                'title' => $billingOrder->status?->isPaid()
                    ? 'Pagamento ja confirmado'
                    : 'Evento ativado com sucesso!',
                'description' => $billingOrder->status?->isPaid()
                    ? 'Este checkout ja foi convertido em compra do evento.'
                    : 'O pacote escolhido ja esta liberado e o evento pode seguir para configuracao final.',
                'next_path' => $activation['event'] ? "/events/{$activation['event']->id}" : '/events',
            ],
        ]);
    }
}
