<?php

namespace App\Modules\WhatsApp\Clients\Providers\ZApi;

use App\Modules\WhatsApp\Clients\Contracts\WhatsAppProviderInterface;
use App\Modules\WhatsApp\Clients\DTOs\CreateGroupData;
use App\Modules\WhatsApp\Clients\DTOs\GroupParticipantsData;
use App\Modules\WhatsApp\Clients\DTOs\ModifyChatData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderActionResultData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderChatsPageData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderGroupCreatedData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderQrCodeData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderSendMessageResultData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderStatusData;
use App\Modules\WhatsApp\Clients\DTOs\RemoveReactionData;
use App\Modules\WhatsApp\Clients\DTOs\SendAudioData;
use App\Modules\WhatsApp\Clients\DTOs\SendCarouselData;
use App\Modules\WhatsApp\Clients\DTOs\SendImageData;
use App\Modules\WhatsApp\Clients\DTOs\SendPixButtonData;
use App\Modules\WhatsApp\Clients\DTOs\SendReactionData;
use App\Modules\WhatsApp\Clients\DTOs\SendTextData;
use App\Modules\WhatsApp\Clients\DTOs\UpdateGroupData;
use App\Modules\WhatsApp\Clients\DTOs\UpdateGroupSettingsData;
use App\Modules\WhatsApp\Models\WhatsAppInstance;

/**
 * Z-API WhatsApp Provider Adapter.
 *
 * Implements WhatsAppProviderInterface using the Z-API HTTP client.
 * Maps Z-API specific responses (zaapId, messageId, id) into standardized DTOs.
 */
class ZApiWhatsAppProvider implements WhatsAppProviderInterface
{
    public function __construct(
        private readonly ZApiApiClient $client,
    ) {}

    // ─── Connection ────────────────────────────────────────

    public function getStatus(WhatsAppInstance $instance): ProviderStatusData
    {
        $response = $this->client->getStatus($instance);
        $body = $response['body'];

        return new ProviderStatusData(
            connected: (bool) ($body['connected'] ?? false),
            smartphoneConnected: (bool) ($body['smartphoneConnected'] ?? false),
            error: $body['error'] ?? null,
            rawResponse: $body,
        );
    }

    public function getQrCode(WhatsAppInstance $instance): ProviderQrCodeData
    {
        $response = $this->client->getQrCode($instance);
        $body = $response['body'];

        if (isset($body['connected']) && $body['connected']) {
            return new ProviderQrCodeData(alreadyConnected: true);
        }

        return new ProviderQrCodeData(
            qrCodeBytes: $body['value'] ?? null,
        );
    }

    public function getQrCodeImage(WhatsAppInstance $instance): ProviderQrCodeData
    {
        $response = $this->client->getQrCodeImage($instance);
        $body = $response['body'];

        if (isset($body['connected']) && $body['connected']) {
            return new ProviderQrCodeData(alreadyConnected: true);
        }

        return new ProviderQrCodeData(
            qrCodeBase64Image: $body['value'] ?? null,
        );
    }

    public function requestPhoneCode(WhatsAppInstance $instance, string $phone): ProviderActionResultData
    {
        $response = $this->client->getPhoneCode($instance, $phone);

        return new ProviderActionResultData(
            success: $response['success'],
            message: $response['body']['value'] ?? null,
            rawResponse: $response['body'],
        );
    }

    public function disconnect(WhatsAppInstance $instance): ProviderActionResultData
    {
        $response = $this->client->disconnect($instance);

        return new ProviderActionResultData(
            success: $response['success'],
            message: $response['body']['value'] ?? 'Disconnected',
            rawResponse: $response['body'],
        );
    }

    // ─── Messaging ─────────────────────────────────────────

    public function sendText(WhatsAppInstance $instance, SendTextData $data): ProviderSendMessageResultData
    {
        $payload = array_filter([
            'phone' => $data->phone,
            'message' => $data->message,
            'mentioned' => $data->mentioned,
            'delayMessage' => $data->delayMessage,
            'delayTyping' => $data->delayTyping,
            'editMessageId' => $data->editMessageId,
        ], fn ($v) => $v !== null);

        $response = $this->client->sendText($instance, $payload);

        return $this->mapSendResult($response);
    }

    public function sendImage(WhatsAppInstance $instance, SendImageData $data): ProviderSendMessageResultData
    {
        $payload = array_filter([
            'phone' => $data->phone,
            'image' => $data->image,
            'caption' => $data->caption,
            'messageId' => $data->messageId,
            'delayMessage' => $data->delayMessage,
            'viewOnce' => $data->viewOnce,
        ], fn ($v) => $v !== null);

        $response = $this->client->sendImage($instance, $payload);

        return $this->mapSendResult($response);
    }

    public function sendAudio(WhatsAppInstance $instance, SendAudioData $data): ProviderSendMessageResultData
    {
        $payload = array_filter([
            'phone' => $data->phone,
            'audio' => $data->audio,
            'delayMessage' => $data->delayMessage,
            'delayTyping' => $data->delayTyping,
            'viewOnce' => $data->viewOnce,
            'waveform' => $data->waveform,
        ], fn ($v) => $v !== null);

        $response = $this->client->sendAudio($instance, $payload);

        return $this->mapSendResult($response);
    }

    public function sendReaction(WhatsAppInstance $instance, SendReactionData $data): ProviderSendMessageResultData
    {
        $payload = array_filter([
            'phone' => $data->phone,
            'reaction' => $data->reaction,
            'messageId' => $data->messageId,
            'delayMessage' => $data->delayMessage,
        ], fn ($v) => $v !== null);

        $response = $this->client->sendReaction($instance, $payload);

        return $this->mapSendResult($response);
    }

    public function removeReaction(WhatsAppInstance $instance, RemoveReactionData $data): ProviderSendMessageResultData
    {
        $payload = array_filter([
            'phone' => $data->phone,
            'messageId' => $data->messageId,
            'delayMessage' => $data->delayMessage,
        ], fn ($v) => $v !== null);

        $response = $this->client->sendRemoveReaction($instance, $payload);

        return $this->mapSendResult($response);
    }

    public function sendCarousel(WhatsAppInstance $instance, SendCarouselData $data): ProviderSendMessageResultData
    {
        $payload = array_filter([
            'phone' => $data->phone,
            'message' => $data->message,
            'carousel' => $data->cards,
            'delayMessage' => $data->delayMessage,
        ], fn ($v) => $v !== null);

        $response = $this->client->sendCarousel($instance, $payload);

        return $this->mapSendResult($response);
    }

    public function sendPixButton(WhatsAppInstance $instance, SendPixButtonData $data): ProviderSendMessageResultData
    {
        $payload = array_filter([
            'phone' => $data->phone,
            'pixKey' => $data->pixKey,
            'type' => $data->type,
            'merchantName' => $data->merchantName,
        ], fn ($v) => $v !== null);

        $response = $this->client->sendButtonPix($instance, $payload);

        return $this->mapSendResult($response);
    }

    // ─── Chats ─────────────────────────────────────────────

    public function getChats(WhatsAppInstance $instance, int $page = 1, int $pageSize = 20): ProviderChatsPageData
    {
        $response = $this->client->getChats($instance, $page, $pageSize);

        if (! $response['success']) {
            return new ProviderChatsPageData(
                success: false,
                error: $response['body']['error'] ?? 'Failed to fetch chats',
            );
        }

        $chats = is_array($response['body']) ? $response['body'] : [];

        return new ProviderChatsPageData(
            success: true,
            chats: $chats,
            page: $page,
            pageSize: $pageSize,
        );
    }

    public function modifyChat(WhatsAppInstance $instance, ModifyChatData $data): ProviderActionResultData
    {
        $response = $this->client->modifyChat($instance, [
            'phone' => $data->phone,
            'action' => $data->action,
        ]);

        return $this->mapActionResult($response);
    }

    // ─── Groups ────────────────────────────────────────────

    public function createGroup(WhatsAppInstance $instance, CreateGroupData $data): ProviderGroupCreatedData
    {
        $response = $this->client->createGroup($instance, [
            'autoInvite' => $data->autoInvite,
            'groupName' => $data->groupName,
            'phones' => $data->phones,
        ]);

        $body = $response['body'];

        return new ProviderGroupCreatedData(
            success: $response['success'],
            groupId: $body['phone'] ?? null,
            invitationLink: $body['invitationLink'] ?? null,
            error: $response['success'] ? null : ($body['error'] ?? 'Failed to create group'),
            rawResponse: $body,
        );
    }

    public function updateGroupName(WhatsAppInstance $instance, UpdateGroupData $data): ProviderActionResultData
    {
        $response = $this->client->updateGroupName($instance, [
            'groupId' => $data->groupId,
            'groupName' => $data->groupName,
        ]);

        return $this->mapActionResult($response);
    }

    public function updateGroupPhoto(WhatsAppInstance $instance, UpdateGroupData $data): ProviderActionResultData
    {
        $response = $this->client->updateGroupPhoto($instance, [
            'groupId' => $data->groupId,
            'groupPhoto' => $data->groupPhoto,
        ]);

        return $this->mapActionResult($response);
    }

    public function updateGroupDescription(WhatsAppInstance $instance, UpdateGroupData $data): ProviderActionResultData
    {
        $response = $this->client->updateGroupDescription($instance, [
            'groupId' => $data->groupId,
            'groupDescription' => $data->groupDescription,
        ]);

        return $this->mapActionResult($response);
    }

    public function updateGroupSettings(WhatsAppInstance $instance, UpdateGroupSettingsData $data): ProviderActionResultData
    {
        $response = $this->client->updateGroupSettings($instance, [
            'phone' => $data->groupId,
            'adminOnlyMessage' => $data->adminOnlyMessage,
            'adminOnlySettings' => $data->adminOnlySettings,
            'requireAdminApproval' => $data->requireAdminApproval,
            'adminOnlyAddMember' => $data->adminOnlyAddMember,
        ]);

        return $this->mapActionResult($response);
    }

    public function getGroupInvitationLink(WhatsAppInstance $instance, string $groupId): ProviderActionResultData
    {
        $response = $this->client->getGroupInvitationLink($instance, $groupId);

        return new ProviderActionResultData(
            success: $response['success'],
            message: $response['body']['invitationLink'] ?? null,
            rawResponse: $response['body'],
        );
    }

    public function addParticipant(WhatsAppInstance $instance, GroupParticipantsData $data): ProviderActionResultData
    {
        $response = $this->client->addParticipant($instance, [
            'autoInvite' => $data->autoInvite,
            'groupId' => $data->groupId,
            'phones' => $data->phones,
        ]);

        return $this->mapActionResult($response);
    }

    public function removeParticipant(WhatsAppInstance $instance, GroupParticipantsData $data): ProviderActionResultData
    {
        $response = $this->client->removeParticipant($instance, [
            'groupId' => $data->groupId,
            'phones' => $data->phones,
        ]);

        return $this->mapActionResult($response);
    }

    public function promoteAdmin(WhatsAppInstance $instance, GroupParticipantsData $data): ProviderActionResultData
    {
        $response = $this->client->addAdmin($instance, [
            'groupId' => $data->groupId,
            'phones' => $data->phones,
        ]);

        return $this->mapActionResult($response);
    }

    public function leaveGroup(WhatsAppInstance $instance, string $groupId): ProviderActionResultData
    {
        $response = $this->client->leaveGroup($instance, [
            'groupId' => $groupId,
        ]);

        return $this->mapActionResult($response);
    }

    // ─── Provider Identity ─────────────────────────────────

    public function getProviderKey(): string
    {
        return 'zapi';
    }

    // ─── Internal Mappers ──────────────────────────────────

    private function mapSendResult(array $response): ProviderSendMessageResultData
    {
        $body = $response['body'];
        $success = $response['success'];

        return new ProviderSendMessageResultData(
            success: $success,
            providerMessageId: $body['messageId'] ?? $body['id'] ?? null,
            providerZaapId: $body['zaapId'] ?? null,
            error: $success ? null : ($body['error'] ?? $body['message'] ?? 'Unknown error'),
            httpStatus: $response['status'],
            rawResponse: $body,
        );
    }

    private function mapActionResult(array $response): ProviderActionResultData
    {
        $body = $response['body'];

        return new ProviderActionResultData(
            success: $response['success'] && ($body['value'] ?? $response['success']),
            message: is_string($body['value'] ?? null) ? $body['value'] : null,
            rawResponse: $body,
        );
    }
}
