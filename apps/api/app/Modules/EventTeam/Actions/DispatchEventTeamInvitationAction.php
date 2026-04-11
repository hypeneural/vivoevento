<?php

namespace App\Modules\EventTeam\Actions;

use App\Modules\EventTeam\Models\EventTeamInvitation;
use App\Modules\EventTeam\Services\EventTeamInvitationWhatsAppInstanceResolver;
use App\Modules\EventTeam\Support\EventAccessPresetRegistry;
use App\Modules\WhatsApp\Clients\DTOs\SendTextData;
use App\Modules\WhatsApp\Services\WhatsAppMessagingService;
use Illuminate\Support\Facades\Log;

class DispatchEventTeamInvitationAction
{
    public function __construct(
        private readonly EventTeamInvitationWhatsAppInstanceResolver $instanceResolver,
        private readonly EventAccessPresetRegistry $presetRegistry,
        private readonly WhatsAppMessagingService $messagingService,
    ) {}

    public function execute(EventTeamInvitation $invitation, bool $sendViaWhatsApp): EventTeamInvitation
    {
        $invitation->loadMissing('event.organization');

        if (! $sendViaWhatsApp) {
            $invitation->forceFill([
                'delivery_channel' => 'manual',
                'delivery_status' => 'manual_link',
                'delivery_error' => null,
            ])->save();

            return $invitation->fresh(['event.organization']);
        }

        $instance = $this->instanceResolver->resolve($invitation->event);

        if (! $instance) {
            $invitation->forceFill([
                'delivery_channel' => 'whatsapp',
                'delivery_status' => 'unavailable',
                'delivery_error' => 'whatsapp_instance_unavailable',
            ])->save();

            return $invitation->fresh(['event.organization']);
        }

        try {
            $this->messagingService->sendText(
                $instance,
                new SendTextData(
                    phone: (string) $invitation->invitee_phone,
                    message: $this->buildMessage($invitation),
                ),
            );

            $invitation->forceFill([
                'delivery_channel' => 'whatsapp',
                'delivery_status' => 'queued',
                'delivery_error' => null,
                'last_sent_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            Log::warning('Event team invitation WhatsApp dispatch failed.', [
                'event_id' => $invitation->event_id,
                'organization_id' => $invitation->organization_id,
                'invitation_id' => $invitation->id,
                'target_phone' => $invitation->invitee_phone,
                'error' => $exception->getMessage(),
            ]);

            $invitation->forceFill([
                'delivery_channel' => 'whatsapp',
                'delivery_status' => 'failed',
                'delivery_error' => 'whatsapp_dispatch_failed',
            ])->save();
        }

        return $invitation->fresh(['event.organization']);
    }

    private function buildMessage(EventTeamInvitation $invitation): string
    {
        $preset = $this->presetRegistry->presetByKey((string) $invitation->preset_key);
        $event = $invitation->event;
        $organization = $event->organization;

        return implode("\n", [
            'Evento Vivo',
            '',
            "Ola, {$invitation->invitee_name}.",
            "Voce recebeu o acesso *{$preset['label']}* para o evento *{$event->title}*.",
            "Organizacao: {$organization->name}",
            '',
            "Abra seu convite: {$invitation->invitation_url}",
            'Se voce ja tiver conta, faca login antes de aceitar.',
        ]);
    }
}
