<?php

namespace App\Modules\Organizations\Actions;

use App\Modules\Organizations\Models\OrganizationMemberInvitation;
use App\Modules\Organizations\Services\OrganizationInvitationWhatsAppInstanceResolver;
use App\Modules\Organizations\Support\OrganizationTeamRoleRegistry;
use App\Modules\WhatsApp\Clients\DTOs\SendTextData;
use App\Modules\WhatsApp\Services\WhatsAppMessagingService;
use Illuminate\Support\Facades\Log;

class DispatchOrganizationMemberInvitationAction
{
    public function __construct(
        private readonly OrganizationInvitationWhatsAppInstanceResolver $instanceResolver,
        private readonly OrganizationTeamRoleRegistry $roleRegistry,
        private readonly WhatsAppMessagingService $messagingService,
    ) {}

    public function execute(OrganizationMemberInvitation $invitation, bool $sendViaWhatsApp): OrganizationMemberInvitation
    {
        $invitation->loadMissing('organization');

        if (! $sendViaWhatsApp) {
            $invitation->forceFill([
                'delivery_channel' => 'manual',
                'delivery_status' => 'manual_link',
                'delivery_error' => null,
            ])->save();

            return $invitation->fresh('organization');
        }

        $instance = $this->instanceResolver->resolve($invitation->organization);

        if (! $instance) {
            $invitation->forceFill([
                'delivery_channel' => 'whatsapp',
                'delivery_status' => 'unavailable',
                'delivery_error' => 'whatsapp_instance_unavailable',
            ])->save();

            return $invitation->fresh('organization');
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
            Log::warning('Organization team invitation WhatsApp dispatch failed.', [
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

        return $invitation->fresh('organization');
    }

    private function buildMessage(OrganizationMemberInvitation $invitation): string
    {
        $roleLabel = $this->roleRegistry->labelForRoleKey((string) $invitation->role_key);
        $organization = $invitation->organization;

        return implode("\n", [
            'Evento Vivo',
            '',
            "Ola, {$invitation->invitee_name}.",
            "Voce recebeu o acesso *{$roleLabel}* para a equipe de *{$organization->displayName()}*.",
            '',
            "Abra seu convite: {$invitation->invitation_url}",
            'Se voce ja tiver conta na plataforma, faca login antes de aceitar.',
        ]);
    }
}
