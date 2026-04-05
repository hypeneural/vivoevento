<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\EntitlementMergeStrategy;
use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Billing\Enums\EventAccessGrantStatus;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Billing\Services\PublicJourneyIdentityService;
use App\Modules\Events\Actions\CreateEventAction;
use App\Modules\Events\Support\EventCommercialStatusService;
use App\Modules\Organizations\Actions\CreateOrganizationAction;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePublicTrialEventAction
{
    private const TRIAL_RETENTION_DAYS = 7;
    private const TRIAL_MAX_ACTIVE_EVENTS = 1;
    private const TRIAL_MAX_PHOTOS = 20;

    public function __construct(
        private readonly CreateOrganizationAction $createOrganizationAction,
        private readonly CreateEventAction $createEventAction,
        private readonly EventCommercialStatusService $commercialStatus,
        private readonly PublicJourneyIdentityService $identity,
    ) {}

    public function execute(array $data): array
    {
        $normalizedPhone = $this->identity->normalizePhone($data['whatsapp']);
        $normalizedEmail = $this->identity->normalizeEmail($data['email'] ?? null);

        $this->identity->ensureIdentityAvailable($normalizedPhone, $normalizedEmail);

        return DB::transaction(function () use ($data, $normalizedPhone, $normalizedEmail) {
            $user = User::create([
                'name' => trim((string) $data['responsible_name']),
                'email' => $normalizedEmail ?? $this->identity->buildInternalEmail($normalizedPhone),
                'phone' => $normalizedPhone,
                'password' => Str::password(32),
                'status' => 'active',
                'last_login_at' => now(),
            ]);

            $organization = $this->createOrganizationAction->execute([
                'name' => trim((string) ($data['organization_name'] ?? $data['responsible_name'])),
                'type' => 'partner',
                'status' => 'active',
                'timezone' => 'America/Sao_Paulo',
                'email' => $normalizedEmail,
                'phone' => $normalizedPhone,
            ]);

            OrganizationMember::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'role_key' => 'partner-owner',
                'is_owner' => true,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $user->assignRole('partner-owner');

            $event = $this->createEventAction->execute([
                'organization_id' => $organization->id,
                'title' => $data['event']['title'],
                'event_type' => $data['event']['event_type'],
                'starts_at' => $data['event']['event_date'] ?? null,
                'location_name' => $data['event']['city'] ?? null,
                'description' => $data['event']['description'] ?? null,
                'modules' => [
                    'live' => true,
                    'wall' => false,
                    'play' => false,
                    'hub' => true,
                ],
                'privacy' => [
                    'visibility' => 'public',
                    'moderation_mode' => 'manual',
                    'retention_days' => self::TRIAL_RETENTION_DAYS,
                ],
            ], $user->id);

            $grant = EventAccessGrant::create([
                'organization_id' => $organization->id,
                'event_id' => $event->id,
                'source_type' => EventAccessGrantSourceType::Trial->value,
                'source_id' => null,
                'package_id' => null,
                'status' => EventAccessGrantStatus::Active->value,
                'priority' => EventAccessGrantSourceType::Trial->defaultPriority(),
                'merge_strategy' => EntitlementMergeStrategy::Replace->value,
                'starts_at' => now(),
                'ends_at' => now()->addDays(self::TRIAL_RETENTION_DAYS),
                'features_snapshot_json' => [
                    'live.enabled' => true,
                    'hub.enabled' => true,
                    'wall.enabled' => false,
                    'play.enabled' => false,
                    'gallery.watermark' => true,
                ],
                'limits_snapshot_json' => [
                    'media.retention_days' => self::TRIAL_RETENTION_DAYS,
                    'events.max_active' => self::TRIAL_MAX_ACTIVE_EVENTS,
                    'media.max_photos' => self::TRIAL_MAX_PHOTOS,
                ],
                'granted_by_user_id' => $user->id,
                'notes' => 'Trial publico criado via jornada event-first.',
                'metadata_json' => [
                    'journey' => 'public_trial_event',
                ],
            ]);

            $event = $this->commercialStatus->sync($event->fresh(['organization', 'modules']));
            $commercialStatus = $this->commercialStatus->build($event);
            $token = $user->createToken($data['device_name'] ?? 'public-trial-event')->plainTextToken;

            activity()
                ->performedOn($event)
                ->causedBy($user)
                ->withProperties([
                    'organization_id' => $organization->id,
                    'grant_id' => $grant->id,
                    'journey' => 'public_trial_event',
                ])
                ->log('Evento trial criado via jornada publica');

            return [
                'message' => 'Evento trial criado com sucesso.',
                'token' => $token,
                'user' => $user->load(['roles', 'organizations']),
                'organization' => $organization,
                'event' => $event->load(['organization', 'modules']),
                'commercial_status' => $commercialStatus,
                'trial' => [
                    'grant_id' => $grant->id,
                    'status' => $grant->status?->value,
                    'starts_at' => $grant->starts_at?->toISOString(),
                    'ends_at' => $grant->ends_at?->toISOString(),
                    'modules' => [
                        'live' => true,
                        'wall' => false,
                        'play' => false,
                        'hub' => true,
                    ],
                    'limits' => [
                        'retention_days' => self::TRIAL_RETENTION_DAYS,
                        'max_active_events' => self::TRIAL_MAX_ACTIVE_EVENTS,
                        'max_photos' => self::TRIAL_MAX_PHOTOS,
                    ],
                    'branding' => [
                        'watermark' => true,
                    ],
                ],
                'onboarding' => [
                    'title' => 'Evento teste criado com sucesso!',
                    'description' => 'Sua conta leve ja esta pronta. Agora voce pode revisar o evento e comecar a testar a experiencia.',
                    'next_path' => "/events/{$event->id}",
                ],
            ];
        });
    }
}
