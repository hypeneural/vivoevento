<?php

namespace App\Modules\WhatsApp\Clients\Providers\Evolution;

use App\Modules\WhatsApp\Clients\Contracts\WhatsAppProviderInterface;
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
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class EvolutionWhatsAppProvider implements WhatsAppProviderInterface
{
    public function __construct(
        private readonly EvolutionApiClient $client,
    ) {}

    public function getStatus(WhatsAppInstance $instance): ProviderStatusData
    {
        $response = $this->client->getStatus($instance);
        $body = is_array($response['body']) ? $response['body'] : [];
        $state = strtolower((string) ($body['instance']['state'] ?? $body['state'] ?? ''));

        return new ProviderStatusData(
            connected: in_array($state, ['open', 'connected'], true),
            smartphoneConnected: in_array($state, ['open', 'connected'], true),
            error: $body['error'] ?? $body['message'] ?? null,
            rawResponse: $body,
        );
    }

    public function getConnectionDetails(WhatsAppInstance $instance): ProviderConnectionDetailsData
    {
        $response = $this->client->fetchInstances($instance);
        $body = is_array($response['body']) ? $response['body'] : [];
        $matched = $this->matchInstance($body, $instance);

        if (! $matched) {
            return new ProviderConnectionDetailsData(
                phone: $instance->phone_number,
                meta: ['provider' => 'evolution'],
                rawResponse: $body,
                error: $response['success']
                    ? 'Instancia nao encontrada na Evolution API.'
                    : ($body['error'] ?? $body['message'] ?? 'Falha ao consultar a Evolution API.'),
            );
        }

        return new ProviderConnectionDetailsData(
            phone: data_get($matched, 'owner')
                ?? data_get($matched, 'instance.owner')
                ?? data_get($matched, 'number'),
            statusMessage: data_get($matched, 'status')
                ?? data_get($matched, 'instance.state')
                ?? null,
            profile: [
                'lid' => data_get($matched, 'ownerJid')
                    ?? data_get($matched, 'instance.ownerJid'),
                'name' => data_get($matched, 'profileName')
                    ?? data_get($matched, 'instance.profileName'),
                'about' => data_get($matched, 'profileStatus')
                    ?? data_get($matched, 'instance.profileStatus'),
                'img_url' => data_get($matched, 'profilePicUrl')
                    ?? data_get($matched, 'profilePictureUrl')
                    ?? data_get($matched, 'instance.profilePicUrl'),
                'is_business' => (bool) (
                    data_get($matched, 'business')
                    ?? data_get($matched, 'instance.business')
                    ?? false
                ),
            ],
            device: [
                'session_id' => data_get($matched, 'instanceId')
                    ?? data_get($matched, 'instance.instanceId'),
                'session_name' => data_get($matched, 'instanceName')
                    ?? data_get($matched, 'instance.instanceName'),
                'device_model' => data_get($matched, 'integration')
                    ?? data_get($matched, 'instance.integration'),
                'original_device' => data_get($matched, 'integration')
                    ?? data_get($matched, 'instance.integration'),
            ],
            meta: [
                'provider' => 'evolution',
                'state' => data_get($matched, 'status') ?? data_get($matched, 'instance.state'),
            ],
            rawResponse: $matched,
        );
    }

    public function getQrCode(WhatsAppInstance $instance): ProviderQrCodeData
    {
        $response = $this->client->connect($instance);
        $body = is_array($response['body']) ? $response['body'] : [];
        $state = strtolower((string) ($body['instance']['state'] ?? $body['state'] ?? ''));

        if (in_array($state, ['open', 'connected'], true) && empty($body['code'])) {
            return new ProviderQrCodeData(alreadyConnected: true);
        }

        return new ProviderQrCodeData(
            qrCodeValue: $body['code'] ?? $body['qr'] ?? null,
            error: $response['success'] ? null : ($body['error'] ?? $body['message'] ?? 'QR Code indisponivel.'),
        );
    }

    public function getQrCodeImage(WhatsAppInstance $instance): ProviderQrCodeData
    {
        return $this->getQrCode($instance);
    }

    public function testConnection(WhatsAppInstance $instance): ProviderHealthCheckData
    {
        $catalogResponse = $this->client->fetchInstances($instance);
        $catalogBody = is_array($catalogResponse['body']) ? $catalogResponse['body'] : [];

        if (in_array($catalogResponse['status'], [401, 403], true)) {
            return new ProviderHealthCheckData(
                success: false,
                connected: false,
                status: 'invalid_credentials',
                message: 'A Evolution API rejeitou a API key informada.',
                rawResponse: $catalogBody,
                error: $catalogBody['error'] ?? $catalogBody['message'] ?? 'Credenciais invalidas.',
            );
        }

        if (! $catalogResponse['success']) {
            return new ProviderHealthCheckData(
                success: false,
                connected: false,
                status: 'error',
                message: 'Nao foi possivel consultar a Evolution API.',
                rawResponse: $catalogBody,
                error: $catalogBody['error'] ?? $catalogBody['message'] ?? 'Falha ao validar a instancia.',
            );
        }

        $matched = $this->matchInstance($catalogBody, $instance);

        if (! $matched) {
            return new ProviderHealthCheckData(
                success: false,
                connected: false,
                status: 'error',
                message: 'Instancia nao encontrada na Evolution API.',
                rawResponse: $catalogBody,
                error: 'Confira o nome da instancia configurada.',
            );
        }

        $statusResponse = $this->client->getStatus($instance);
        $statusBody = is_array($statusResponse['body']) ? $statusResponse['body'] : [];
        $state = strtolower((string) ($statusBody['instance']['state'] ?? $statusBody['state'] ?? data_get($matched, 'status') ?? data_get($matched, 'instance.state') ?? ''));
        $connected = in_array($state, ['open', 'connected'], true);

        return new ProviderHealthCheckData(
            success: true,
            connected: $connected,
            status: $connected ? 'connected' : 'disconnected',
            message: $connected
                ? 'Instancia encontrada e conectada na Evolution API.'
                : 'Instancia encontrada, mas ainda desconectada na Evolution API.',
            phone: data_get($matched, 'owner') ?? data_get($matched, 'instance.owner'),
            meta: [
                'instance_id' => data_get($matched, 'instanceId') ?? data_get($matched, 'instance.instanceId'),
                'state' => $state,
            ],
            rawResponse: [
                'catalog' => $matched,
                'status' => $statusBody,
            ],
            error: $statusBody['error'] ?? $statusBody['message'] ?? null,
        );
    }

    public function requestPhoneCode(WhatsAppInstance $instance, string $phone): ProviderActionResultData
    {
        $response = $this->client->connect($instance, $phone);
        $body = is_array($response['body']) ? $response['body'] : [];

        return new ProviderActionResultData(
            success: $response['success'],
            message: $body['pairingCode'] ?? $body['code'] ?? $body['message'] ?? null,
            rawResponse: $body,
        );
    }

    public function disconnect(WhatsAppInstance $instance): ProviderActionResultData
    {
        $response = $this->client->logout($instance);
        $body = is_array($response['body']) ? $response['body'] : [];

        return new ProviderActionResultData(
            success: $response['success'],
            message: $body['message'] ?? ($response['success'] ? 'Disconnected' : 'Falha ao desconectar.'),
            rawResponse: $body,
        );
    }

    public function sendText(WhatsAppInstance $instance, SendTextData $data): ProviderSendMessageResultData
    {
        $mentions = $this->normalizeMentions($data->mentioned);
        $options = array_filter([
            'delay' => $this->toMilliseconds($data->delayMessage),
            'presence' => $data->delayTyping !== null ? 'composing' : null,
            'mentions' => $mentions !== null ? [
                'everyOne' => false,
                'mentioned' => $mentions,
            ] : null,
        ], static fn ($value) => $value !== null && $value !== []);

        $payload = array_filter([
            'number' => $this->normalizeRecipient($data->phone),
            'textMessage' => [
                'text' => $data->message,
            ],
            'options' => $options !== [] ? $options : null,
            'quoted' => $data->messageId !== null ? [
                'key' => [
                    'remoteJid' => $this->normalizeRemoteJid($data->phone),
                    'id' => $data->messageId,
                    'fromMe' => false,
                ],
            ] : null,
        ], static fn ($value) => $value !== null && $value !== []);

        $response = $this->client->sendText($instance, $payload);

        return $this->mapSendResult($response);
    }

    public function sendImage(WhatsAppInstance $instance, SendImageData $data): ProviderSendMessageResultData
    {
        $options = array_filter([
            'delay' => $this->toMilliseconds($data->delayMessage),
            'presence' => $data->delayMessage !== null ? 'composing' : null,
        ], static fn ($value) => $value !== null && $value !== '');

        $payload = array_filter([
            'number' => $this->normalizeRecipient($data->phone),
            'mediaMessage' => array_filter([
                'mediaType' => $this->resolveMediaType($data->image),
                'mimetype' => $this->resolveMimeType($data->image),
                'fileName' => $this->resolveFileName($data->image),
                'caption' => $data->caption,
                'media' => $data->image,
            ], static fn ($value) => $value !== null && $value !== ''),
            'options' => $options !== [] ? $options : null,
        ], static fn ($value) => $value !== null && $value !== [] && $value !== '');

        $response = $this->client->sendMedia($instance, $payload);

        return $this->mapSendResult($response);
    }

    public function sendAudio(WhatsAppInstance $instance, SendAudioData $data): ProviderSendMessageResultData
    {
        $payload = array_filter([
            'number' => $this->normalizeRecipient($data->phone),
            'audio' => $data->audio,
            'delay' => $this->toMilliseconds($data->delayMessage),
        ], static fn ($value) => $value !== null && $value !== '');

        $response = $this->client->sendWhatsAppAudio($instance, $payload);

        return $this->mapSendResult($response);
    }

    public function sendReaction(WhatsAppInstance $instance, SendReactionData $data): ProviderSendMessageResultData
    {
        $response = $this->client->sendReaction($instance, [
            'key' => [
                'remoteJid' => $this->normalizeRemoteJid($data->phone),
                'fromMe' => $data->fromMe,
                'id' => $data->messageId,
            ],
            'reaction' => $data->reaction,
        ]);

        return $this->mapSendResult($response);
    }

    public function removeReaction(WhatsAppInstance $instance, RemoveReactionData $data): ProviderSendMessageResultData
    {
        $response = $this->client->sendReaction($instance, [
            'key' => [
                'remoteJid' => $this->normalizeRemoteJid($data->phone),
                'fromMe' => $data->fromMe,
                'id' => $data->messageId,
            ],
            'reaction' => '',
        ]);

        return $this->mapSendResult($response);
    }

    public function sendCarousel(WhatsAppInstance $instance, SendCarouselData $data): ProviderSendMessageResultData
    {
        return $this->unsupportedSendMessageResult('carrossel');
    }

    public function sendPixButton(WhatsAppInstance $instance, SendPixButtonData $data): ProviderSendMessageResultData
    {
        return $this->unsupportedSendMessageResult('botao PIX');
    }

    public function getChats(WhatsAppInstance $instance, int $page = 1, int $pageSize = 20): ProviderChatsPageData
    {
        $response = $this->client->findChats($instance);
        $body = is_array($response['body']) ? $response['body'] : [];

        if (! $response['success']) {
            return new ProviderChatsPageData(
                success: false,
                page: $page,
                pageSize: $pageSize,
                error: $body['error'] ?? $body['message'] ?? 'Falha ao listar chats na Evolution API.',
            );
        }

        $allChats = $this->extractChatList($body);
        $offset = max(0, ($page - 1) * $pageSize);

        return new ProviderChatsPageData(
            success: true,
            chats: array_slice($allChats, $offset, $pageSize),
            page: $page,
            pageSize: $pageSize,
        );
    }

    public function findMessages(WhatsAppInstance $instance, string $remoteJid, array $filters = []): ProviderChatMessagesData
    {
        $payload = [
            'where' => [
                'key' => array_filter([
                    'remoteJid' => $this->normalizeRemoteJid($remoteJid),
                    'id' => $filters['before_message_id'] ?? null,
                    'fromMe' => array_key_exists('from_me', $filters) ? (bool) $filters['from_me'] : null,
                ], static fn ($value) => $value !== null),
            ],
        ];

        if (isset($filters['limit'])) {
            $payload['limit'] = (int) $filters['limit'];
        }

        $response = $this->client->findMessages($instance, $payload);
        $body = is_array($response['body']) ? $response['body'] : [];

        if (! $response['success']) {
            return new ProviderChatMessagesData(
                success: false,
                remoteJid: $this->normalizeRemoteJid($remoteJid),
                error: $body['error'] ?? $body['message'] ?? 'Falha ao buscar mensagens na Evolution API.',
            );
        }

        return new ProviderChatMessagesData(
            success: true,
            messages: $this->extractMessageList($body),
            remoteJid: $this->normalizeRemoteJid($remoteJid),
        );
    }

    public function modifyChat(WhatsAppInstance $instance, ModifyChatData $data): ProviderActionResultData
    {
        return $this->unsupportedActionResult('pin/unpin de chat com o contrato atual');
    }

    public function createGroup(WhatsAppInstance $instance, CreateGroupData $data): ProviderGroupCreatedData
    {
        $response = $this->client->createGroup($instance, [
            'subject' => $data->groupName,
            'participants' => array_map([$this, 'normalizePhone'], $data->phones),
        ]);

        $body = is_array($response['body']) ? $response['body'] : [];

        if (! $response['success']) {
            return new ProviderGroupCreatedData(
                success: false,
                error: $body['error'] ?? $body['message'] ?? 'Falha ao criar grupo na Evolution API.',
                rawResponse: $body,
            );
        }

        $group = $this->lookupCreatedGroup($instance, $data->groupName);
        $groupId = data_get($body, 'id')
            ?? data_get($body, 'group.id')
            ?? data_get($group, 'id');

        return new ProviderGroupCreatedData(
            success: true,
            groupId: is_string($groupId) ? $groupId : null,
            invitationLink: $this->buildInvitationLink(data_get($body, 'inviteCode') ?? data_get($group, 'inviteCode')),
            rawResponse: [
                'create' => $body,
                'group' => $group,
            ],
        );
    }

    public function fetchGroups(WhatsAppInstance $instance, bool $includeParticipants = false): ProviderGroupCatalogData
    {
        $response = $this->client->fetchAllGroups($instance, $includeParticipants);
        $body = is_array($response['body']) ? $response['body'] : [];

        if (! $response['success']) {
            return new ProviderGroupCatalogData(
                success: false,
                includesParticipants: $includeParticipants,
                error: $body['error'] ?? $body['message'] ?? 'Falha ao listar grupos na Evolution API.',
            );
        }

        return new ProviderGroupCatalogData(
            success: true,
            groups: $this->extractGroupList($body),
            includesParticipants: $includeParticipants,
        );
    }

    public function getGroupParticipants(WhatsAppInstance $instance, string $groupId): ProviderGroupParticipantsData
    {
        $response = $this->client->fetchAllGroups($instance, true);
        $body = is_array($response['body']) ? $response['body'] : [];

        if (! $response['success']) {
            return new ProviderGroupParticipantsData(
                success: false,
                groupId: $groupId,
                error: $body['error'] ?? $body['message'] ?? 'Falha ao listar participantes na Evolution API.',
            );
        }

        $group = collect($this->extractGroupList($body))
            ->first(fn (array $item) => (string) ($item['id'] ?? $item['jid'] ?? '') === $groupId);

        if (! is_array($group)) {
            return new ProviderGroupParticipantsData(
                success: false,
                groupId: $groupId,
                error: 'Grupo nao encontrado no catalogo remoto da Evolution API.',
            );
        }

        return new ProviderGroupParticipantsData(
            success: true,
            participants: Arr::wrap($group['participants'] ?? []),
            groupId: $groupId,
        );
    }

    public function updateGroupName(WhatsAppInstance $instance, UpdateGroupData $data): ProviderActionResultData
    {
        $response = $this->client->updateGroupSubject($instance, $data->groupId, [
            'subject' => $data->groupName,
        ]);

        return $this->mapActionResult($response, 'Nome do grupo atualizado.');
    }

    public function updateGroupPhoto(WhatsAppInstance $instance, UpdateGroupData $data): ProviderActionResultData
    {
        $response = $this->client->updateGroupPicture($instance, $data->groupId, [
            'image' => $data->groupPhoto,
        ]);

        return $this->mapActionResult($response, 'Foto do grupo atualizada.');
    }

    public function updateGroupDescription(WhatsAppInstance $instance, UpdateGroupData $data): ProviderActionResultData
    {
        $response = $this->client->updateGroupDescription($instance, $data->groupId, [
            'description' => $data->groupDescription,
        ]);

        return $this->mapActionResult($response, 'Descricao do grupo atualizada.');
    }

    public function updateGroupSettings(WhatsAppInstance $instance, UpdateGroupSettingsData $data): ProviderActionResultData
    {
        if ($data->requireAdminApproval || $data->adminOnlyAddMember) {
            return new ProviderActionResultData(
                success: false,
                message: 'A Evolution API nao expoe as opcoes require_admin_approval e admin_only_add_member neste contrato.',
            );
        }

        $actions = [
            $data->adminOnlyMessage ? 'announcement' : 'not_announcement',
            $data->adminOnlySettings ? 'locked' : 'unlocked',
        ];

        $responses = [];

        foreach ($actions as $action) {
            $response = $this->client->updateSetting($instance, $data->groupId, [
                'action' => $action,
            ]);

            $responses[] = $response;

            if (! $response['success']) {
                return $this->mapActionResult($response, 'Falha ao atualizar configuracoes do grupo.');
            }
        }

        return new ProviderActionResultData(
            success: true,
            message: 'Configuracoes do grupo atualizadas.',
            rawResponse: [
                'actions' => $actions,
                'responses' => array_map(static fn (array $response) => $response['body'] ?? [], $responses),
            ],
        );
    }

    public function getGroupInvitationLink(WhatsAppInstance $instance, string $groupId): ProviderActionResultData
    {
        $response = $this->client->fetchInviteCode($instance, $groupId);
        $body = is_array($response['body']) ? $response['body'] : [];
        $invite = data_get($body, 'inviteUrl')
            ?? data_get($body, 'inviteCode')
            ?? data_get($body, 'code')
            ?? data_get($body, 'inviteLink');

        return new ProviderActionResultData(
            success: $response['success'],
            message: $this->buildInvitationLink($invite),
            rawResponse: $body,
        );
    }

    public function addParticipant(WhatsAppInstance $instance, GroupParticipantsData $data): ProviderActionResultData
    {
        $response = $this->client->updateParticipant($instance, $data->groupId, [
            'action' => 'add',
            'participants' => array_map([$this, 'normalizePhone'], $data->phones),
        ]);

        return $this->mapActionResult($response, 'Participantes adicionados.');
    }

    public function removeParticipant(WhatsAppInstance $instance, GroupParticipantsData $data): ProviderActionResultData
    {
        $response = $this->client->updateParticipant($instance, $data->groupId, [
            'action' => 'remove',
            'participants' => array_map([$this, 'normalizePhone'], $data->phones),
        ]);

        return $this->mapActionResult($response, 'Participantes removidos.');
    }

    public function promoteAdmin(WhatsAppInstance $instance, GroupParticipantsData $data): ProviderActionResultData
    {
        $response = $this->client->updateParticipant($instance, $data->groupId, [
            'action' => 'promote',
            'participants' => array_map([$this, 'normalizePhone'], $data->phones),
        ]);

        return $this->mapActionResult($response, 'Participantes promovidos a admin.');
    }

    public function leaveGroup(WhatsAppInstance $instance, string $groupId): ProviderActionResultData
    {
        $response = $this->client->leaveGroup($instance, $groupId);

        return $this->mapActionResult($response, 'Grupo encerrado para a instancia.');
    }

    public function getProviderKey(): string
    {
        return 'evolution';
    }

    private function matchInstance(array $payload, WhatsAppInstance $instance): ?array
    {
        $targetName = $instance->providerInstanceKey();
        $candidates = Arr::wrap($payload['instances'] ?? $payload['data'] ?? $payload);

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $instanceName = data_get($candidate, 'instance.instanceName')
                ?? data_get($candidate, 'instanceName')
                ?? data_get($candidate, 'name');

            if ((string) $instanceName === $targetName) {
                return $candidate;
            }
        }

        return null;
    }

    private function unsupportedSendMessageResult(string $feature): ProviderSendMessageResultData
    {
        return new ProviderSendMessageResultData(
            success: false,
            error: "Evolution provider ainda nao implementa {$feature} neste modulo.",
        );
    }

    private function unsupportedActionResult(string $feature): ProviderActionResultData
    {
        return new ProviderActionResultData(
            success: false,
            message: "Evolution provider ainda nao implementa {$feature} neste modulo.",
        );
    }

    private function mapSendResult(array $response): ProviderSendMessageResultData
    {
        $body = is_array($response['body']) ? $response['body'] : [];

        return new ProviderSendMessageResultData(
            success: $response['success'],
            providerMessageId: data_get($body, 'key.id') ?? data_get($body, 'id'),
            error: $response['success']
                ? null
                : ($body['error'] ?? $body['message'] ?? 'Falha ao enviar mensagem via Evolution API.'),
            httpStatus: $response['status'],
            rawResponse: $body,
        );
    }

    private function mapActionResult(array $response, ?string $successMessage = null): ProviderActionResultData
    {
        $body = is_array($response['body']) ? $response['body'] : [];

        return new ProviderActionResultData(
            success: $response['success'],
            message: $response['success']
                ? ($body['message'] ?? $successMessage)
                : ($body['error'] ?? $body['message'] ?? 'Falha ao executar a acao na Evolution API.'),
            rawResponse: $body,
        );
    }

    private function extractChatList(array $payload): array
    {
        $candidates = $payload['chats'] ?? $payload['data'] ?? $payload;
        $list = Arr::wrap($candidates);

        return array_values(array_filter($list, static fn ($chat) => is_array($chat)));
    }

    private function extractMessageList(array $payload): array
    {
        $candidates = $payload['messages'] ?? $payload['data'] ?? $payload;
        $list = Arr::wrap($candidates);

        return array_values(array_filter($list, static fn ($message) => is_array($message)));
    }

    private function extractGroupList(array $payload): array
    {
        $groups = Arr::wrap($payload['groups'] ?? $payload['data'] ?? $payload);

        return array_values(array_filter($groups, static fn ($group) => is_array($group)));
    }

    private function lookupCreatedGroup(WhatsAppInstance $instance, string $groupName): ?array
    {
        $response = $this->client->fetchAllGroups($instance, false);

        if (! $response['success']) {
            return null;
        }

        $groups = Arr::wrap($response['body']);
        $matches = array_values(array_filter($groups, static fn ($group) => is_array($group) && data_get($group, 'subject') === $groupName));

        if ($matches === []) {
            return null;
        }

        usort($matches, static fn (array $left, array $right) => (int) data_get($right, 'creation', 0) <=> (int) data_get($left, 'creation', 0));

        return $matches[0];
    }

    private function normalizeRecipient(string $value): string
    {
        if (str_contains($value, '@g.us') || str_contains($value, '@s.whatsapp.net')) {
            return $value;
        }

        return $this->normalizePhone($value);
    }

    private function normalizeRemoteJid(string $value): string
    {
        if (str_contains($value, '@g.us') || str_contains($value, '@s.whatsapp.net')) {
            return $value;
        }

        return $this->normalizePhone($value) . '@s.whatsapp.net';
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function normalizeMentions(?array $mentioned): ?array
    {
        if (empty($mentioned)) {
            return null;
        }

        return array_values(array_filter(array_map(function ($item) {
            if (! is_string($item) || trim($item) === '') {
                return null;
            }

            if ($item === '{{remoteJID}}' || str_contains($item, '@')) {
                return $item;
            }

            return $this->normalizePhone($item) . '@s.whatsapp.net';
        }, $mentioned)));
    }

    private function toMilliseconds(?int $delay): ?int
    {
        return $delay !== null ? $delay * 1000 : null;
    }

    private function resolveMediaType(string $media): string
    {
        $mimeType = $this->resolveMimeType($media);

        if ($mimeType && str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if ($mimeType && str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        return 'document';
    }

    private function resolveMimeType(string $media): ?string
    {
        if (preg_match('/^data:([^;]+);base64,/', $media, $matches) === 1) {
            return strtolower($matches[1]);
        }

        $path = parse_url($media, PHP_URL_PATH);
        $extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'pdf' => 'application/pdf',
            default => null,
        };
    }

    private function resolveFileName(string $media): ?string
    {
        $path = parse_url($media, PHP_URL_PATH);
        $fileName = basename((string) $path);

        if ($fileName !== '' && $fileName !== '.' && $fileName !== '/') {
            return $fileName;
        }

        $mimeType = $this->resolveMimeType($media);

        if ($mimeType === null) {
            return null;
        }

        return 'upload.' . Str::after($mimeType, '/');
    }

    private function buildInvitationLink(mixed $invite): ?string
    {
        if (! is_string($invite) || trim($invite) === '') {
            return null;
        }

        if (str_starts_with($invite, 'http://') || str_starts_with($invite, 'https://')) {
            return $invite;
        }

        return 'https://chat.whatsapp.com/' . trim($invite);
    }
}
