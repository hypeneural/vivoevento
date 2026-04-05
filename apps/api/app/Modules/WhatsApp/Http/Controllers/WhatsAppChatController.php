<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\WhatsApp\Clients\DTOs\ModifyChatData;
use App\Modules\WhatsApp\Http\Requests\FindChatMessagesRequest;
use App\Modules\WhatsApp\Http\Resources\WhatsAppRemoteMessageResource;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Services\WhatsAppProviderResolver;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppChatController extends BaseController
{
    public function __construct(
        private readonly WhatsAppProviderResolver $providerResolver,
    ) {}

    /**
     * GET /whatsapp/chats
     * List chats from the provider (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'instance_id' => ['required', 'integer', 'exists:whatsapp_instances,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $instance = WhatsAppInstance::findOrFail($request->input('instance_id'));
        $this->authorize('view', $instance);
        $provider = $this->providerResolver->forInstance($instance);

        $result = $provider->getChats(
            $instance,
            $request->integer('page', 1),
            $request->integer('page_size', 20),
        );

        if (! $result->success) {
            return $this->error($result->error ?? 'Failed to fetch chats', 422);
        }

        return $this->success([
            'chats' => $result->chats,
            'page' => $result->page,
            'page_size' => $result->pageSize,
        ]);
    }

    /**
     * POST /whatsapp/chats/modify
     * Pin or unpin a chat.
     */
    public function modify(Request $request): JsonResponse
    {
        $request->validate([
            'instance_id' => ['required', 'integer', 'exists:whatsapp_instances,id'],
            'phone' => ['required', 'string', 'min:10'],
            'action' => ['required', 'string', 'in:pin,unpin'],
        ]);

        $instance = WhatsAppInstance::findOrFail($request->input('instance_id'));
        $this->authorize('update', $instance);
        $provider = $this->providerResolver->forInstance($instance);

        $result = $provider->modifyChat($instance, new ModifyChatData(
            phone: $request->input('phone'),
            action: $request->input('action'),
        ));

        return $this->success(['success' => $result->success]);
    }

    /**
     * POST /whatsapp/chats/find-messages
     * Search remote provider messages within a chat.
     */
    public function findMessages(FindChatMessagesRequest $request): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));
        $this->authorize('view', $instance);

        $provider = $this->providerResolver->forInstance($instance);
        $result = $provider->findMessages(
            $instance,
            $request->validated('remote_jid'),
            array_filter([
                'limit' => $request->validated('limit'),
                'before_message_id' => $request->validated('before_message_id'),
                'from_me' => $request->validated('from_me'),
            ], static fn ($value) => $value !== null),
        );

        if (! $result->success) {
            return $this->error($result->error ?? 'Failed to fetch messages', 422);
        }

        return $this->success([
            'remote_jid' => $result->remoteJid,
            'messages' => WhatsAppRemoteMessageResource::collection(collect($result->messages))->resolve(),
        ]);
    }
}
