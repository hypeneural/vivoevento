<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\WhatsApp\Clients\DTOs\CreateGroupData;
use App\Modules\WhatsApp\Clients\DTOs\GroupParticipantsData;
use App\Modules\WhatsApp\Clients\DTOs\UpdateGroupData;
use App\Modules\WhatsApp\Clients\DTOs\UpdateGroupSettingsData;
use App\Modules\WhatsApp\Http\Requests\CreateGroupRequest;
use App\Modules\WhatsApp\Http\Requests\GroupParticipantsRequest;
use App\Modules\WhatsApp\Http\Requests\ListRemoteGroupParticipantsRequest;
use App\Modules\WhatsApp\Http\Requests\ListRemoteGroupsRequest;
use App\Modules\WhatsApp\Http\Requests\UpdateGroupDescriptionRequest;
use App\Modules\WhatsApp\Http\Requests\UpdateGroupNameRequest;
use App\Modules\WhatsApp\Http\Requests\UpdateGroupPhotoRequest;
use App\Modules\WhatsApp\Http\Requests\UpdateGroupSettingsRequest;
use App\Modules\WhatsApp\Http\Resources\WhatsAppRemoteGroupResource;
use App\Modules\WhatsApp\Http\Resources\WhatsAppRemoteParticipantResource;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Services\WhatsAppProviderResolver;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppGroupManagementController extends BaseController
{
    public function __construct(
        private readonly WhatsAppProviderResolver $providerResolver,
    ) {}

    /**
     * GET /whatsapp/group-management/catalog
     */
    public function catalog(ListRemoteGroupsRequest $request): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));
        $this->authorize('view', $instance);

        $provider = $this->providerResolver->forInstance($instance);
        $result = $provider->fetchGroups(
            $instance,
            (bool) $request->validated('include_participants', false),
        );

        if (! $result->success) {
            return $this->error($result->error ?? 'Falha ao listar grupos remotos.', 422);
        }

        return $this->success([
            'includes_participants' => $result->includesParticipants,
            'groups' => WhatsAppRemoteGroupResource::collection(collect($result->groups))->resolve(),
        ]);
    }

    /**
     * POST /whatsapp/group-management/create
     */
    public function create(CreateGroupRequest $request): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));
        $this->authorize('update', $instance);
        $provider = $this->providerResolver->forInstance($instance);

        $result = $provider->createGroup($instance, new CreateGroupData(
            groupName: $request->validated('group_name'),
            phones: $request->validated('phones'),
            autoInvite: (bool) $request->validated('auto_invite', true),
        ));

        return $this->success([
            'success' => $result->success,
            'group_id' => $result->groupId,
            'invitation_link' => $result->invitationLink,
            'error' => $result->error,
        ], $result->success ? 201 : 422);
    }

    /**
     * POST /whatsapp/group-management/{groupId}/update-name
     */
    public function updateName(UpdateGroupNameRequest $request, string $groupId): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));
        $this->authorize('update', $instance);
        $provider = $this->providerResolver->forInstance($instance);

        $result = $provider->updateGroupName($instance, new UpdateGroupData(
            groupId: $groupId,
            groupName: $request->validated('group_name'),
        ));

        return $this->success(['success' => $result->success]);
    }

    /**
     * POST /whatsapp/group-management/{groupId}/update-photo
     */
    public function updatePhoto(UpdateGroupPhotoRequest $request, string $groupId): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));
        $this->authorize('update', $instance);
        $provider = $this->providerResolver->forInstance($instance);

        $result = $provider->updateGroupPhoto($instance, new UpdateGroupData(
            groupId: $groupId,
            groupPhoto: $request->validated('group_photo'),
        ));

        return $this->success(['success' => $result->success]);
    }

    /**
     * POST /whatsapp/group-management/{groupId}/update-description
     */
    public function updateDescription(UpdateGroupDescriptionRequest $request, string $groupId): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));
        $this->authorize('update', $instance);
        $provider = $this->providerResolver->forInstance($instance);

        $result = $provider->updateGroupDescription($instance, new UpdateGroupData(
            groupId: $groupId,
            groupDescription: $request->validated('group_description'),
        ));

        return $this->success(['success' => $result->success]);
    }

    /**
     * POST /whatsapp/group-management/{groupId}/update-settings
     */
    public function updateSettings(UpdateGroupSettingsRequest $request, string $groupId): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));
        $this->authorize('update', $instance);
        $provider = $this->providerResolver->forInstance($instance);

        $result = $provider->updateGroupSettings($instance, new UpdateGroupSettingsData(
            groupId: $groupId,
            adminOnlyMessage: (bool) $request->validated('admin_only_message', false),
            adminOnlySettings: (bool) $request->validated('admin_only_settings', false),
            requireAdminApproval: (bool) $request->validated('require_admin_approval', false),
            adminOnlyAddMember: (bool) $request->validated('admin_only_add_member', false),
        ));

        return $this->success(['success' => $result->success]);
    }

    /**
     * GET /whatsapp/group-management/{groupId}/invitation-link
     */
    public function invitationLink(Request $request, string $groupId): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->input('instance_id'));
        $this->authorize('view', $instance);
        $provider = $this->providerResolver->forInstance($instance);

        $result = $provider->getGroupInvitationLink($instance, $groupId);

        return $this->success([
            'success' => $result->success,
            'invitation_link' => $result->message,
        ]);
    }

    /**
     * GET /whatsapp/group-management/{groupId}/participants
     */
    public function participants(ListRemoteGroupParticipantsRequest $request, string $groupId): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));
        $this->authorize('view', $instance);

        $provider = $this->providerResolver->forInstance($instance);
        $result = $provider->getGroupParticipants($instance, $groupId);

        if (! $result->success) {
            return $this->error($result->error ?? 'Falha ao listar participantes.', 422);
        }

        return $this->success([
            'group_id' => $result->groupId,
            'participants' => WhatsAppRemoteParticipantResource::collection(collect($result->participants))->resolve(),
        ]);
    }

    /**
     * POST /whatsapp/group-management/{groupId}/add-participants
     */
    public function addParticipants(GroupParticipantsRequest $request, string $groupId): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));
        $this->authorize('update', $instance);
        $provider = $this->providerResolver->forInstance($instance);

        $result = $provider->addParticipant($instance, new GroupParticipantsData(
            groupId: $groupId,
            phones: $request->validated('phones'),
            autoInvite: (bool) $request->validated('auto_invite', true),
        ));

        return $this->success(['success' => $result->success]);
    }

    /**
     * POST /whatsapp/group-management/{groupId}/remove-participants
     */
    public function removeParticipants(GroupParticipantsRequest $request, string $groupId): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));
        $this->authorize('update', $instance);
        $provider = $this->providerResolver->forInstance($instance);

        $result = $provider->removeParticipant($instance, new GroupParticipantsData(
            groupId: $groupId,
            phones: $request->validated('phones'),
        ));

        return $this->success(['success' => $result->success]);
    }

    /**
     * POST /whatsapp/group-management/{groupId}/promote-admin
     */
    public function promoteAdmin(GroupParticipantsRequest $request, string $groupId): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));
        $this->authorize('update', $instance);
        $provider = $this->providerResolver->forInstance($instance);

        $result = $provider->promoteAdmin($instance, new GroupParticipantsData(
            groupId: $groupId,
            phones: $request->validated('phones'),
        ));

        return $this->success(['success' => $result->success]);
    }

    /**
     * POST /whatsapp/group-management/{groupId}/leave
     */
    public function leave(Request $request, string $groupId): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->input('instance_id'));
        $this->authorize('update', $instance);
        $provider = $this->providerResolver->forInstance($instance);

        $result = $provider->leaveGroup($instance, $groupId);

        return $this->success(['success' => $result->success]);
    }
}
