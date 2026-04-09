<?php

namespace App\Modules\Billing\Services\Pagarme;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class PagarmeClient
{
    public function createCustomer(array $payload): array
    {
        return $this->request()->post('/customers', $payload)->throw()->json() ?? [];
    }

    public function createCustomerCard(string $customerId, array $payload): array
    {
        return $this->request()->post("/customers/{$customerId}/cards", $payload)->throw()->json() ?? [];
    }

    public function listCustomerCards(string $customerId): array
    {
        return $this->request()->get("/customers/{$customerId}/cards")->throw()->json() ?? [];
    }

    public function createOrder(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->requestWithIdempotency($idempotencyKey)
            ->post('/orders', $payload)
            ->throw()
            ->json() ?? [];
    }

    public function getOrder(string $orderId): array
    {
        return $this->request()->get("/orders/{$orderId}")->throw()->json() ?? [];
    }

    public function createPlan(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->requestWithIdempotency($idempotencyKey)
            ->post('/plans', $payload)
            ->throw()
            ->json() ?? [];
    }

    public function listPlans(array $query = []): array
    {
        return $this->request()->get('/plans', $query)->throw()->json() ?? [];
    }

    public function getPlan(string $planId): array
    {
        return $this->request()->get("/plans/{$planId}")->throw()->json() ?? [];
    }

    public function createSubscription(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->requestWithIdempotency($idempotencyKey)
            ->post('/subscriptions', $payload)
            ->throw()
            ->json() ?? [];
    }

    public function getSubscription(string $subscriptionId): array
    {
        return $this->request()->get("/subscriptions/{$subscriptionId}")->throw()->json() ?? [];
    }

    public function listSubscriptions(array $query = []): array
    {
        return $this->request()->get('/subscriptions', $query)->throw()->json() ?? [];
    }

    public function listSubscriptionCycles(string $subscriptionId, array $query = []): array
    {
        return $this->request()->get("/subscriptions/{$subscriptionId}/cycles", $query)->throw()->json() ?? [];
    }

    public function cancelSubscription(string $subscriptionId, array $payload = []): array
    {
        return $this->request()->delete("/subscriptions/{$subscriptionId}", $payload)->throw()->json() ?? [];
    }

    public function updateSubscriptionCard(string $subscriptionId, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->requestWithIdempotency($idempotencyKey)
            ->patch("/subscriptions/{$subscriptionId}/card", $payload)
            ->throw()
            ->json() ?? [];
    }

    public function updateSubscriptionPaymentMethod(string $subscriptionId, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->requestWithIdempotency($idempotencyKey)
            ->patch("/subscriptions/{$subscriptionId}/payment-method", $payload)
            ->throw()
            ->json() ?? [];
    }

    public function updateSubscriptionStartAt(string $subscriptionId, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->requestWithIdempotency($idempotencyKey)
            ->patch("/subscriptions/{$subscriptionId}/start-at", $payload)
            ->throw()
            ->json() ?? [];
    }

    public function updateSubscriptionMetadata(string $subscriptionId, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->requestWithIdempotency($idempotencyKey)
            ->patch("/subscriptions/{$subscriptionId}/metadata", $payload)
            ->throw()
            ->json() ?? [];
    }

    public function listInvoices(array $query = []): array
    {
        return $this->request()->get('/invoices', $query)->throw()->json() ?? [];
    }

    public function listCharges(array $query = []): array
    {
        return $this->request()->get('/charges', $query)->throw()->json() ?? [];
    }

    public function getCharge(string $chargeId): array
    {
        return $this->request()->get("/charges/{$chargeId}")->throw()->json() ?? [];
    }

    public function listHooks(array $query = []): array
    {
        return $this->request()->get('/hooks', $query)->throw()->json() ?? [];
    }

    public function getHook(string $hookId): array
    {
        return $this->request()->get("/hooks/{$hookId}")->throw()->json() ?? [];
    }

    public function retryHook(string $hookId): array
    {
        return $this->request()->post("/hooks/{$hookId}/retry")->throw()->json() ?? [];
    }

    public function cancelCharge(string $chargeId): array
    {
        return $this->request()->delete("/charges/{$chargeId}")->throw()->json() ?? [];
    }

    public function captureCharge(string $chargeId, array $payload = []): array
    {
        return $this->request()->post("/charges/{$chargeId}/capture", $payload)->throw()->json() ?? [];
    }

    private function requestWithIdempotency(?string $idempotencyKey = null): PendingRequest
    {
        $request = $this->request();

        if (! filled($idempotencyKey)) {
            return $request;
        }

        return $request->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ]);
    }

    private function request(): PendingRequest
    {
        $config = $this->config();

        $request = Http::baseUrl(rtrim((string) $config['base_url'], '/'))
            ->acceptJson()
            ->asJson()
            ->withBasicAuth((string) $config['secret_key'], '')
            ->timeout((int) ($config['timeout'] ?? 15))
            ->connectTimeout((int) ($config['connect_timeout'] ?? 5));

        $retryTimes = max(1, (int) ($config['retry_times'] ?? 2));
        $retrySleepMs = max(0, (int) ($config['retry_sleep_ms'] ?? 100));

        return $request->retry($retryTimes, $retrySleepMs, function (\Exception $exception) {
            if ($exception instanceof ConnectionException) {
                return true;
            }

            if ($exception instanceof RequestException) {
                $status = $exception->response->status();

                return $status === 429 || $status >= 500;
            }

            return false;
        });
    }

    private function config(): array
    {
        $config = config('services.pagarme', []);
        $baseUrl = (string) ($config['base_url'] ?? '');
        $secretKey = (string) ($config['secret_key'] ?? '');

        if ($baseUrl === '') {
            throw new InvalidArgumentException('services.pagarme.base_url is not configured.');
        }

        if ($secretKey === '') {
            throw new InvalidArgumentException('services.pagarme.secret_key is not configured.');
        }

        return $config;
    }
}
