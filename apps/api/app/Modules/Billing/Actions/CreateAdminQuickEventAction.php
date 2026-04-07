<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\EntitlementMergeStrategy;
use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Billing\Enums\EventAccessGrantStatus;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Billing\Models\EventPackage;
use App\Modules\Billing\Services\AdminQuickEventAccessDeliveryService;
use App\Modules\Billing\Services\EventPackageSnapshotService;
use App\Modules\Billing\Services\PublicJourneyIdentityService;
use App\Modules\Events\Actions\CreateEventAction;
use App\Modules\Events\Support\EventCommercialStatusService;
use App\Modules\Organizations\Actions\CreateOrganizationAction;
use App\Modules\Organizations\Enums\OrganizationType;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateAdminQuickEventAction
{
    public function __construct(
        private readonly CreateOrganizationAction $createOrganizationAction,
        private readonly CreateEventAction $createEventAction,
        private readonly EventPackageSnapshotService $packageSnapshots,
        private readonly PublicJourneyIdentityService $identity,
        private readonly EventCommercialStatusService $commercialStatus,
        private readonly AdminQuickEventAccessDeliveryService $accessDelivery,
    ) {}

    public function execute(array $data, User $actor): array
    {
        $normalizedPhone = $this->identity->normalizePhone($data['whatsapp']);
        $normalizedEmail = $this->identity->normalizeEmail($data['email'] ?? null);

        $payload = DB::transaction(function () use ($data, $actor, $normalizedPhone, $normalizedEmail) {
            [$responsibleUser, $userReused] = $this->resolveResponsibleUser(
                name: trim((string) $data['responsible_name']),
                phone: $normalizedPhone,
                email: $normalizedEmail,
            );

            [$organization, $organizationReused] = $this->resolveOrganization(
                data: $data,
                normalizedEmail: $normalizedEmail,
                normalizedPhone: $normalizedPhone,
            );

            $membership = $this->ensureOrganizationMembership(
                organization: $organization,
                user: $responsibleUser,
                invitedByUserId: $actor->id,
                preferOwnerRole: ! $organizationReused,
            );

            [$package, $snapshot] = $this->resolveGrantPackageAndSnapshot($data['grant']);

            $event = $this->createEventAction->execute([
                'organization_id' => $organization->id,
                'title' => $data['event']['title'],
                'event_type' => $data['event']['event_type'],
                'starts_at' => $data['event']['event_date'] ?? null,
                'location_name' => $data['event']['city'] ?? null,
                'description' => $data['event']['description'] ?? null,
                'modules' => $snapshot['modules'],
                'privacy' => [
                    'visibility' => $data['event']['visibility'] ?? 'public',
                    'moderation_mode' => $data['event']['moderation_mode'] ?? 'manual',
                    'retention_days' => $snapshot['limits']['retention_days'] ?? 30,
                ],
            ], $actor->id);

            $sourceType = EventAccessGrantSourceType::from($data['grant']['source_type']);
            $startsAt = isset($data['grant']['starts_at']) ? Carbon::parse($data['grant']['starts_at']) : now();
            $endsAt = isset($data['grant']['ends_at']) ? Carbon::parse($data['grant']['ends_at']) : null;
            $mergeStrategy = isset($data['grant']['merge_strategy'])
                ? EntitlementMergeStrategy::from($data['grant']['merge_strategy'])
                : EntitlementMergeStrategy::Replace;

            $grant = EventAccessGrant::create([
                'organization_id' => $organization->id,
                'event_id' => $event->id,
                'source_type' => $sourceType->value,
                'source_id' => null,
                'package_id' => $package?->id,
                'status' => EventAccessGrantStatus::Active->value,
                'priority' => $sourceType->defaultPriority(),
                'merge_strategy' => $mergeStrategy->value,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'features_snapshot_json' => $snapshot['grant_features_snapshot'],
                'limits_snapshot_json' => $snapshot['grant_limits_snapshot'],
                'granted_by_user_id' => $actor->id,
                'notes' => $data['grant']['notes'] ?? null,
                'metadata_json' => [
                    'journey' => 'admin_quick_event',
                    'reason' => $data['grant']['reason'],
                    'origin' => $data['grant']['origin'] ?? null,
                    'responsible_user_id' => $responsibleUser->id,
                    'responsible_phone' => $normalizedPhone,
                    'responsible_email' => $normalizedEmail,
                    'organization_reused' => $organizationReused,
                    'responsible_user_reused' => $userReused,
                    'membership_role_key' => $membership->role_key,
                    'access_delivery_requested' => (bool) ($data['send_access'] ?? false),
                    'package_code' => $package?->code,
                ],
            ]);

            $event = $this->commercialStatus->sync($event->fresh(['organization', 'modules']));
            $commercialStatus = $this->commercialStatus->build($event);

            return [
                'message' => 'Evento operacional criado com sucesso.',
                'responsible_user' => $responsibleUser->load(['roles', 'organizations']),
                'organization' => $organization,
                'event' => $event->load(['organization', 'modules']),
                'commercial_status' => $commercialStatus,
                'grant_model' => $grant,
                'grant' => [
                    'id' => $grant->id,
                    'source_type' => $grant->source_type?->value,
                    'status' => $grant->status?->value,
                    'priority' => $grant->priority,
                    'merge_strategy' => $grant->merge_strategy?->value,
                    'package_id' => $package?->id,
                    'package_code' => $package?->code,
                    'package_name' => $package?->name,
                    'starts_at' => $grant->starts_at?->toISOString(),
                    'ends_at' => $grant->ends_at?->toISOString(),
                    'reason' => $data['grant']['reason'],
                    'origin' => $data['grant']['origin'] ?? null,
                    'notes' => $grant->notes,
                    'granted_by_user_id' => $actor->id,
                    'granted_by_name' => $actor->name,
                ],
                'setup' => [
                    'organization_reused' => $organizationReused,
                    'responsible_user_reused' => $userReused,
                    'membership_role_key' => $membership->role_key,
                    'membership_is_owner' => (bool) $membership->is_owner,
                ],
                'onboarding' => [
                    'title' => 'Evento operacional criado',
                    'description' => 'O evento e o grant comercial ja foram configurados. Agora a operacao pode seguir no painel.',
                    'next_path' => "/events/{$event->id}",
                ],
            ];
        });

        $accessDelivery = (bool) ($data['send_access'] ?? false)
            ? $this->accessDelivery->sendViaWhatsApp(
                event: $payload['event'],
                organization: $payload['organization'],
                responsibleUser: $payload['responsible_user'],
                targetPhone: $normalizedPhone,
            )
            : $this->accessDelivery->notRequested();

        $this->persistAccessDeliveryMetadata($payload['grant_model'], $accessDelivery);

        activity()
            ->performedOn($payload['event'])
            ->causedBy($actor)
            ->withProperties([
                'organization_id' => $payload['organization']->id,
                'grant_id' => $payload['grant']['id'],
                'grant_source_type' => $payload['grant']['source_type'],
                'grant_reason' => $data['grant']['reason'],
                'grant_origin' => $data['grant']['origin'] ?? null,
                'grant_starts_at' => $payload['grant']['starts_at'],
                'grant_ends_at' => $payload['grant']['ends_at'],
                'responsible_user_id' => $payload['responsible_user']->id,
                'organization_reused' => $payload['setup']['organization_reused'],
                'responsible_user_reused' => $payload['setup']['responsible_user_reused'],
                'journey' => 'admin_quick_event',
                'access_delivery' => $accessDelivery,
            ])
            ->log('Evento criado via jornada admin rapida');

        unset($payload['grant_model']);
        $payload['access_delivery'] = $accessDelivery;

        return $payload;
    }

    /**
     * @param array<string, mixed> $grantData
     * @return array{0: ?EventPackage, 1: array<string, mixed>}
     */
    private function resolveGrantPackageAndSnapshot(array $grantData): array
    {
        $package = null;

        if (filled($grantData['package_id'] ?? null)) {
            $package = EventPackage::query()
                ->with(['prices', 'features'])
                ->findOrFail($grantData['package_id']);
        }

        $flatFeatures = Arr::dot((array) ($grantData['features'] ?? []));
        $flatLimits = Arr::dot((array) ($grantData['limits'] ?? []));

        if ($flatFeatures === [] && $flatLimits === [] && $package) {
            return [$package, $this->packageSnapshots->build($package)];
        }

        return [$package, $this->packageSnapshots->buildManualOverride(
            features: $flatFeatures,
            limits: $flatLimits,
            package: $package,
        )];
    }

    /**
     * @param array<string, mixed> $accessDelivery
     */
    private function persistAccessDeliveryMetadata(EventAccessGrant $grant, array $accessDelivery): void
    {
        $metadata = $grant->metadata_json ?? [];
        $metadata['access_delivery'] = $accessDelivery;

        EventAccessGrant::withoutEvents(function () use ($grant, $metadata): void {
            $grant->forceFill([
                'metadata_json' => $metadata,
            ])->save();
        });
    }

    private function resolveResponsibleUser(string $name, string $phone, ?string $email): array
    {
        $phoneVariants = array_values(array_unique(array_filter([
            $phone,
            str_starts_with($phone, '55') ? substr($phone, 2) : null,
        ])));

        $phoneUser = User::query()->whereIn('phone', $phoneVariants)->first();
        $emailUser = $email !== null
            ? User::query()->whereRaw('LOWER(email) = ?', [$email])->first()
            : null;

        if ($phoneUser && $emailUser && $phoneUser->id !== $emailUser->id) {
            throw ValidationException::withMessages([
                'email' => ['O e-mail informado pertence a outro usuario. Revise a identidade do responsavel.'],
                'whatsapp' => ['O WhatsApp informado pertence a outro usuario. Revise a identidade do responsavel.'],
            ]);
        }

        $user = $phoneUser ?? $emailUser;
        $reused = $user !== null;

        if (! $user) {
            $user = User::create([
                'name' => $name,
                'email' => $email ?? $this->identity->buildInternalEmail($phone),
                'phone' => $phone,
                'password' => Str::password(32),
                'status' => 'active',
                'last_login_at' => null,
            ]);

            return [$user, false];
        }

        $dirty = false;

        if (($user->phone === null || $user->phone === '') && $phone !== '') {
            $user->phone = $phone;
            $dirty = true;
        }

        if (($user->email === null || $user->email === '') && $email !== null) {
            $user->email = $email;
            $dirty = true;
        }

        if (trim((string) $user->name) === '' && $name !== '') {
            $user->name = $name;
            $dirty = true;
        }

        if ($dirty) {
            $user->save();
        }

        return [$user, $reused];
    }

    private function resolveOrganization(array $data, ?string $normalizedEmail, string $normalizedPhone): array
    {
        if (! empty($data['organization_id'])) {
            $organization = Organization::query()->findOrFail($data['organization_id']);

            return [$organization, true];
        }

        $organization = $this->createOrganizationAction->execute([
            'name' => trim((string) ($data['organization_name'] ?? $data['responsible_name'])),
            'type' => $data['organization_type'] ?? OrganizationType::DirectCustomer->value,
            'status' => 'active',
            'timezone' => 'America/Sao_Paulo',
            'email' => $normalizedEmail,
            'phone' => $normalizedPhone,
        ]);

        return [$organization, false];
    }

    private function ensureOrganizationMembership(
        Organization $organization,
        User $user,
        int $invitedByUserId,
        bool $preferOwnerRole,
    ): OrganizationMember {
        $membership = OrganizationMember::query()->firstOrNew([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);

        $organizationHasMembers = $organization->members()->exists();
        $shouldBeOwner = $preferOwnerRole || ! $organizationHasMembers;
        $roleKey = $shouldBeOwner ? 'partner-owner' : 'partner-manager';

        $membership->fill([
            'role_key' => $membership->role_key ?: $roleKey,
            'is_owner' => $membership->exists ? (bool) $membership->is_owner : $shouldBeOwner,
            'invited_by' => $membership->invited_by ?? $invitedByUserId,
            'status' => 'active',
            'joined_at' => $membership->joined_at ?? now(),
        ]);
        $membership->save();

        if (! $user->hasRole($roleKey)) {
            $user->assignRole($roleKey);
        }

        return $membership->fresh();
    }
}
