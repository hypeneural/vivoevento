<?php

namespace App\Modules\WhatsApp\Clients\Contracts;

use App\Modules\WhatsApp\Clients\DTOs\CreateGroupData;
use App\Modules\WhatsApp\Clients\DTOs\GroupParticipantsData;
use App\Modules\WhatsApp\Clients\DTOs\ModifyChatData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderActionResultData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderChatMessagesData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderChatsPageData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderConnectionDetailsData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderGroupCreatedData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderGroupCatalogData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderGroupParticipantsData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderHealthCheckData;
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
 * Provider-agnostic interface for WhatsApp operations.
 *
 * Each provider (Z-API, Evolution, etc.) implements this interface,
 * hiding all provider-specific details behind standardized DTOs.
 */
interface WhatsAppProviderInterface
{
    // ─── Connection / Instance ─────────────────────────────

    public function getStatus(WhatsAppInstance $instance): ProviderStatusData;

    public function getConnectionDetails(WhatsAppInstance $instance): ProviderConnectionDetailsData;

    public function getQrCode(WhatsAppInstance $instance): ProviderQrCodeData;

    public function getQrCodeImage(WhatsAppInstance $instance): ProviderQrCodeData;

    public function testConnection(WhatsAppInstance $instance): ProviderHealthCheckData;

    public function requestPhoneCode(WhatsAppInstance $instance, string $phone): ProviderActionResultData;

    public function disconnect(WhatsAppInstance $instance): ProviderActionResultData;

    // ─── Messaging ─────────────────────────────────────────

    public function sendText(WhatsAppInstance $instance, SendTextData $data): ProviderSendMessageResultData;

    public function sendImage(WhatsAppInstance $instance, SendImageData $data): ProviderSendMessageResultData;

    public function sendAudio(WhatsAppInstance $instance, SendAudioData $data): ProviderSendMessageResultData;

    public function sendReaction(WhatsAppInstance $instance, SendReactionData $data): ProviderSendMessageResultData;

    public function removeReaction(WhatsAppInstance $instance, RemoveReactionData $data): ProviderSendMessageResultData;

    public function sendCarousel(WhatsAppInstance $instance, SendCarouselData $data): ProviderSendMessageResultData;

    public function sendPixButton(WhatsAppInstance $instance, SendPixButtonData $data): ProviderSendMessageResultData;

    // ─── Chats ─────────────────────────────────────────────

    public function getChats(WhatsAppInstance $instance, int $page = 1, int $pageSize = 20): ProviderChatsPageData;

    public function findMessages(WhatsAppInstance $instance, string $remoteJid, array $filters = []): ProviderChatMessagesData;

    public function modifyChat(WhatsAppInstance $instance, ModifyChatData $data): ProviderActionResultData;

    // ─── Groups ────────────────────────────────────────────

    public function createGroup(WhatsAppInstance $instance, CreateGroupData $data): ProviderGroupCreatedData;

    public function fetchGroups(WhatsAppInstance $instance, bool $includeParticipants = false): ProviderGroupCatalogData;

    public function getGroupParticipants(WhatsAppInstance $instance, string $groupId): ProviderGroupParticipantsData;

    public function updateGroupName(WhatsAppInstance $instance, UpdateGroupData $data): ProviderActionResultData;

    public function updateGroupPhoto(WhatsAppInstance $instance, UpdateGroupData $data): ProviderActionResultData;

    public function updateGroupDescription(WhatsAppInstance $instance, UpdateGroupData $data): ProviderActionResultData;

    public function updateGroupSettings(WhatsAppInstance $instance, UpdateGroupSettingsData $data): ProviderActionResultData;

    public function getGroupInvitationLink(WhatsAppInstance $instance, string $groupId): ProviderActionResultData;

    public function addParticipant(WhatsAppInstance $instance, GroupParticipantsData $data): ProviderActionResultData;

    public function removeParticipant(WhatsAppInstance $instance, GroupParticipantsData $data): ProviderActionResultData;

    public function promoteAdmin(WhatsAppInstance $instance, GroupParticipantsData $data): ProviderActionResultData;

    public function leaveGroup(WhatsAppInstance $instance, string $groupId): ProviderActionResultData;

    // ─── Provider Identity ─────────────────────────────────

    public function getProviderKey(): string;
}
