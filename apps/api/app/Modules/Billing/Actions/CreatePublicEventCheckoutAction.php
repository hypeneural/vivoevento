<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\BillingOrderMode;
use App\Modules\Billing\Enums\BillingOrderStatus;
use App\Modules\Billing\Enums\EventPackageAudience;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\EventPackage;
use App\Modules\Billing\Services\EventPackageSnapshotService;
use App\Modules\Billing\Services\PublicEventCheckoutPayloadBuilder;
use App\Modules\Billing\Services\PublicJourneyIdentityService;
use App\Modules\Events\Actions\CreateEventAction;
use App\Modules\Organizations\Actions\CreateOrganizationAction;
use App\Modules\Organizations\Enums\OrganizationType;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePublicEventCheckoutAction
{
    public function __construct(
        private readonly CreateOrganizationAction $createOrganizationAction,
        private readonly CreateEventAction $createEventAction,
        private readonly CreateEventPackageGatewayCheckoutAction $createEventPackageGatewayCheckout,
        private readonly EventPackageSnapshotService $packageSnapshots,
        private readonly PublicJourneyIdentityService $identity,
        private readonly PublicEventCheckoutPayloadBuilder $payloads,
    ) {}

    public function execute(array $data, ?User $authenticatedUser = null): array
    {
        $normalizedPhone = $this->identity->normalizePhone($data['whatsapp']);
        $normalizedEmail = $this->identity->normalizeEmail($data['email'] ?? null);
        $gatewayPayment = (array) ($data['payment'] ?? []);
        $payment = $this->sanitizePaymentPayload($gatewayPayment);
        $authenticatedUser?->loadMissing(['organizations', 'roles']);

        if ($authenticatedUser !== null) {
            ['phone' => $normalizedPhone, 'email' => $normalizedEmail] = $this->identity
                ->alignAuthenticatedIdentity($authenticatedUser, $normalizedPhone, $normalizedEmail);
        } else {
            $this->identity->ensureIdentityAvailable($normalizedPhone, $normalizedEmail);
        }

        $payerSnapshot = $this->buildPayerSnapshot($data, $normalizedPhone, $normalizedEmail);

        $package = EventPackage::query()
            ->with(['prices', 'features'])
            ->findOrFail($data['package_id']);

        $this->ensurePackageAvailableForPublicCheckout($package);

        $snapshot = $this->packageSnapshots->build($package);
        $defaultPrice = $snapshot['default_price'] ?? null;

        if ($defaultPrice === null) {
            throw ValidationException::withMessages([
                'package_id' => ['O pacote selecionado ainda nao possui preco ativo para checkout.'],
            ]);
        }

        return DB::transaction(function () use ($authenticatedUser, $data, $normalizedPhone, $normalizedEmail, $package, $snapshot, $defaultPrice, $gatewayPayment, $payment, $payerSnapshot) {
            if ($authenticatedUser !== null) {
                $user = $authenticatedUser->fresh(['roles', 'organizations']) ?? $authenticatedUser;
                $organization = $user->currentOrganization();

                if ($organization === null) {
                    throw ValidationException::withMessages([
                        'whatsapp' => ['Sua conta autenticada ainda nao possui organizacao ativa para continuar este checkout.'],
                    ]);
                }

                $token = null;
            } else {
                $user = User::create([
                    'name' => trim((string) $data['responsible_name']),
                    'email' => $normalizedEmail ?? $this->identity->buildInternalEmail($normalizedPhone),
                    'phone' => $normalizedPhone,
                    'password' => Str::password(32),
                    'status' => 'active',
                    'last_login_at' => now(),
                ]);

                $organization = $this->createOrganizationAction->execute([
                    'name' => trim((string) ($data['organization_name'] ?? $data['responsible_name'])),
                    'type' => OrganizationType::DirectCustomer->value,
                    'status' => 'active',
                    'timezone' => 'America/Sao_Paulo',
                    'email' => $normalizedEmail,
                    'phone' => $normalizedPhone,
                ]);

                OrganizationMember::create([
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'role_key' => 'partner-owner',
                    'is_owner' => true,
                    'status' => 'active',
                    'joined_at' => now(),
                ]);

                $user->assignRole('partner-owner');
                $token = $user->createToken($data['device_name'] ?? 'public-event-checkout')->plainTextToken;
            }

            $event = $this->createEventAction->execute([
                'organization_id' => $organization->id,
                'title' => $data['event']['title'],
                'event_type' => $data['event']['event_type'],
                'starts_at' => $data['event']['event_date'] ?? null,
                'location_name' => $data['event']['city'] ?? null,
                'description' => $data['event']['description'] ?? null,
                'modules' => $snapshot['modules'],
                'privacy' => [
                    'visibility' => 'public',
                    'moderation_mode' => 'manual',
                    'retention_days' => $snapshot['limits']['retention_days'] ?? 30,
                ],
            ], $user->id);

            $order = BillingOrder::create([
                'organization_id' => $organization->id,
                'event_id' => $event->id,
                'buyer_user_id' => $user->id,
                'mode' => BillingOrderMode::EventPackage->value,
                'status' => BillingOrderStatus::PendingPayment->value,
                'currency' => $defaultPrice['currency'] ?? 'BRL',
                'total_cents' => $defaultPrice['amount_cents'] ?? 0,
                'payment_method' => $payment['method'] ?? 'pix',
                'gateway_provider' => 'manual',
                'gateway_status' => BillingOrderStatus::PendingPayment->value,
                'customer_snapshot_json' => $payerSnapshot,
                'metadata_json' => [
                    'journey' => 'public_event_checkout',
                    'package_id' => $package->id,
                    'package_code' => $package->code,
                    'payment' => $payment,
                ],
            ]);

            $order->items()->create([
                'item_type' => 'event_package',
                'reference_id' => $package->id,
                'description' => "Pacote {$package->name}",
                'quantity' => 1,
                'unit_amount_cents' => $defaultPrice['amount_cents'] ?? 0,
                'total_amount_cents' => $defaultPrice['amount_cents'] ?? 0,
                'snapshot_json' => $snapshot['order_item_snapshot'],
            ]);

            $gatewayCheckout = $this->createEventPackageGatewayCheckout->execute($order, [
                'confirm_url' => url("/api/v1/public/event-checkouts/{$order->uuid}/confirm"),
                'payment' => $gatewayPayment,
                'payer' => $payerSnapshot,
                'checkout_input' => $data,
            ]);
            $order = $gatewayCheckout['order'];

            activity()
                ->performedOn($order)
                ->causedBy($user)
                ->withProperties([
                    'organization_id' => $organization->id,
                    'event_id' => $event->id,
                    'package_id' => $package->id,
                    'journey' => 'public_event_checkout',
                ])
                ->log('Checkout publico de evento iniciado');

            return $this->payloads->build($order->fresh(), [
                'message' => 'Checkout do evento iniciado com sucesso.',
                'token' => $token,
                'user' => $user->loadMissing(['roles', 'organizations']),
                'organization' => $organization,
                'event' => ($gatewayCheckout['event'] ?? $event)->load(['organization', 'modules']),
                'onboarding' => [
                    'title' => $authenticatedUser !== null
                        ? 'Sessao retomada e checkout reiniciado!'
                        : 'Evento criado e checkout iniciado!',
                    'description' => $authenticatedUser !== null
                        ? 'Sua conta existente continuou a jornada. O evento foi criado no painel e o pedido ja pode seguir para pagamento.'
                        : 'Seu evento ja existe no painel. Agora falta confirmar o pagamento para ativar o pacote escolhido.',
                    'next_path' => "/events/{$event->id}",
                ],
            ]);
        });
    }

    private function ensurePackageAvailableForPublicCheckout(EventPackage $package): void
    {
        if (! $package->is_active) {
            throw ValidationException::withMessages([
                'package_id' => ['O pacote selecionado nao esta disponivel para checkout.'],
            ]);
        }

        if (! in_array($package->target_audience, [EventPackageAudience::DirectCustomer, EventPackageAudience::Both], true)) {
            throw ValidationException::withMessages([
                'package_id' => ['O pacote selecionado nao esta disponivel para compra direta por evento.'],
            ]);
        }
    }

    private function buildPayerSnapshot(array $data, string $normalizedPhone, ?string $normalizedEmail): array
    {
        $payer = (array) ($data['payer'] ?? []);
        $address = (array) ($payer['address'] ?? []);

        return array_filter([
            'name' => trim((string) ($payer['name'] ?? $data['responsible_name'] ?? '')),
            'email' => $this->identity->normalizeEmail($payer['email'] ?? $normalizedEmail),
            'document' => $payer['document'] ?? null,
            'document_type' => $payer['document_type'] ?? null,
            'phone' => $this->identity->normalizePhone($payer['phone'] ?? $normalizedPhone),
            'address' => array_filter([
                'street' => $address['street'] ?? null,
                'number' => $address['number'] ?? null,
                'district' => $address['district'] ?? null,
                'complement' => $address['complement'] ?? null,
                'zip_code' => $address['zip_code'] ?? null,
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
                'country' => $address['country'] ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    private function sanitizePaymentPayload(array $payment): array
    {
        $method = $payment['method'] ?? 'pix';

        if ($method === 'credit_card') {
            $creditCard = (array) ($payment['credit_card'] ?? []);
            $billingAddress = (array) ($creditCard['billing_address'] ?? []);

            return [
                'method' => 'credit_card',
                'credit_card' => array_filter([
                    'installments' => isset($creditCard['installments']) ? (int) $creditCard['installments'] : null,
                    'statement_descriptor' => $creditCard['statement_descriptor'] ?? null,
                    'has_card_token' => filled($creditCard['card_token'] ?? null),
                    'billing_address' => array_filter([
                        'street' => $billingAddress['street'] ?? null,
                        'number' => $billingAddress['number'] ?? null,
                        'district' => $billingAddress['district'] ?? null,
                        'complement' => $billingAddress['complement'] ?? null,
                        'zip_code' => $billingAddress['zip_code'] ?? null,
                        'city' => $billingAddress['city'] ?? null,
                        'state' => $billingAddress['state'] ?? null,
                        'country' => $billingAddress['country'] ?? null,
                    ], fn (mixed $value): bool => $value !== null && $value !== ''),
                ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []),
            ];
        }

        $pix = (array) ($payment['pix'] ?? []);

        return [
            'method' => 'pix',
            'pix' => array_filter([
                'expires_in' => isset($pix['expires_in']) ? (int) $pix['expires_in'] : null,
            ], fn (mixed $value): bool => $value !== null),
        ];
    }
}
