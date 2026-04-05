<?php

namespace App\Modules\Billing\Services;

use App\Modules\Events\Models\Event;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;
use App\Modules\WhatsApp\Clients\DTOs\SendTextData;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Services\WhatsAppMessagingService;
use Illuminate\Support\Facades\Log;

class AdminQuickEventAccessDeliveryService
{
    public function __construct(
        private readonly WhatsAppMessagingService $messagingService,
        private readonly BillingWhatsAppInstanceResolver $instanceResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function notRequested(): array
    {
        return [
            'requested' => false,
            'channel' => null,
            'target' => null,
            'status' => 'not_requested',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sendViaWhatsApp(
        Event $event,
        Organization $organization,
        User $responsibleUser,
        string $targetPhone,
    ): array {
        $instance = $this->resolveWhatsAppInstance();

        if (! $instance) {
            return [
                'requested' => true,
                'channel' => 'whatsapp',
                'target' => $targetPhone,
                'status' => 'unavailable',
                'reason_code' => 'whatsapp_instance_unavailable',
            ];
        }

        try {
            $message = $this->messagingService->sendText(
                $instance,
                new SendTextData(
                    phone: $targetPhone,
                    message: $this->buildMessage($event, $organization, $responsibleUser),
                ),
            );

            return [
                'requested' => true,
                'channel' => 'whatsapp',
                'target' => $message->recipient_phone,
                'status' => 'queued',
                'instance_id' => $instance->id,
                'message_id' => $message->id,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Admin quick event access delivery failed.', [
                'event_id' => $event->id,
                'organization_id' => $organization->id,
                'responsible_user_id' => $responsibleUser->id,
                'target_phone' => $targetPhone,
                'error' => $exception->getMessage(),
            ]);

            return [
                'requested' => true,
                'channel' => 'whatsapp',
                'target' => $targetPhone,
                'status' => 'failed',
                'reason_code' => 'whatsapp_dispatch_failed',
            ];
        }
    }

    private function resolveWhatsAppInstance(): ?WhatsAppInstance
    {
        return $this->instanceResolver->resolve(
            configuredInstanceId: is_numeric(config('billing.access_delivery.whatsapp_instance_id'))
                ? (int) config('billing.access_delivery.whatsapp_instance_id')
                : null,
            allowSingleConnectedFallback: (bool) config('billing.access_delivery.allow_single_connected_fallback', true),
        );
    }

    private function buildMessage(Event $event, Organization $organization, User $responsibleUser): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $loginUrl = "{$frontendUrl}/login";
        $eventUrl = "{$frontendUrl}/events/{$event->id}";

        return implode("\n", [
            'Evento Vivo',
            '',
            "O evento *{$event->title}* foi configurado para voce.",
            "Organizacao: {$organization->name}",
            '',
            "Acesse com este WhatsApp em: {$loginUrl}",
            "Depois, abra o painel do evento em: {$eventUrl}",
            '',
            "Responsavel cadastrado: {$responsibleUser->name}",
        ]);
    }
}
