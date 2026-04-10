<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\BillingProfile;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\BillingSubscriptionGatewayInterface;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Validation\ValidationException;

class ListSubscriptionWalletCardsAction
{
    public function __construct(
        private readonly BillingSubscriptionGatewayInterface $subscriptionGateway,
    ) {}

    public function execute(Organization $organization): array
    {
        $subscription = Subscription::query()
            ->where('organization_id', $organization->id)
            ->latest('id')
            ->first();

        if (! $subscription) {
            throw ValidationException::withMessages([
                'subscription' => ['Nenhuma assinatura encontrada para a organizacao atual.'],
            ]);
        }

        $billingProfile = BillingProfile::query()
            ->where('organization_id', $organization->id)
            ->first();
        $defaultCardId = $billingProfile?->gateway_default_card_id ?: $subscription->gateway_card_id;
        $cards = (array) ($this->subscriptionGateway->listCustomerCards($subscription)['data'] ?? []);

        return array_values(array_map(function (array $card) use ($defaultCardId) {
            $cardId = (string) ($card['id'] ?? '');
            $lastFour = (string) ($card['last_four_digits'] ?? $card['last_four'] ?? '');
            $brand = strtolower((string) ($card['brand'] ?? $card['brand_name'] ?? 'cartao'));

            return [
                'id' => $cardId,
                'brand' => $brand !== '' ? $brand : null,
                'holder_name' => $card['holder_name'] ?? null,
                'last_four' => $lastFour !== '' ? $lastFour : null,
                'exp_month' => isset($card['exp_month']) ? (int) $card['exp_month'] : null,
                'exp_year' => isset($card['exp_year']) ? (int) $card['exp_year'] : null,
                'status' => $card['status'] ?? 'active',
                'is_default' => $cardId !== '' && $defaultCardId !== null && $cardId === (string) $defaultCardId,
                'label' => trim(implode(' - ', array_filter([
                    $brand !== '' ? strtoupper($brand) : null,
                    $lastFour !== '' ? "final {$lastFour}" : null,
                    isset($card['exp_month'], $card['exp_year'])
                        ? sprintf('%02d/%02d', (int) $card['exp_month'], (int) $card['exp_year'])
                        : null,
                ]))),
            ];
        }, $cards));
    }
}
