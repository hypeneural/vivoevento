<?php

use App\Modules\Billing\Enums\EventPackageAudience;
use App\Modules\Billing\Enums\EventPackageBillingMode;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Billing\Models\EventPackage;
use App\Modules\Events\Models\Event;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Support\Facades\Queue;

it('allows super-admin to create a quick event with bonus grant and new direct-customer organization', function () {
    [$admin] = $this->actingAsSuperAdmin();

    $package = createAdminQuickEventPackage([
        'code' => 'bonus-event',
        'name' => 'Bonus Event',
        'features' => [
            'hub.enabled' => 'true',
            'wall.enabled' => 'true',
            'play.enabled' => 'false',
            'media.retention_days' => '120',
            'media.max_photos' => '500',
        ],
    ]);

    $response = $this->apiPost('/admin/quick-events', [
        'responsible_name' => 'Patricia Martins',
        'whatsapp' => '(48) 99955-1111',
        'email' => 'patricia@example.com',
        'organization_name' => 'Patricia e Fabio',
        'organization_type' => 'direct_customer',
        'event' => [
            'title' => 'Casamento Patricia & Fabio',
            'event_type' => 'wedding',
            'event_date' => '2026-12-20',
            'city' => 'Itajai',
        ],
        'grant' => [
            'source_type' => 'bonus',
            'package_id' => $package->id,
            'reason' => 'Cortesia comercial',
            'origin' => 'parceria',
            'notes' => 'Liberado pela operacao comercial.',
            'ends_at' => '2026-12-31',
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $eventId = $response->json('data.event.id');
    $organizationId = $response->json('data.organization.id');
    $responsibleUserId = $response->json('data.responsible_user.id');
    $grantId = $response->json('data.grant.id');

    expect($response->json('data.organization.type'))->toBe('direct_customer');
    expect($response->json('data.grant.source_type'))->toBe('bonus');
    expect($response->json('data.commercial_status.commercial_mode'))->toBe('bonus');
    expect($response->json('data.setup.organization_reused'))->toBeFalse();
    expect($response->json('data.setup.responsible_user_reused'))->toBeFalse();
    expect($response->json('data.access_delivery.status'))->toBe('not_requested');

    $this->assertDatabaseHas('organizations', [
        'id' => $organizationId,
        'type' => 'direct_customer',
    ]);

    $this->assertDatabaseHas('organization_members', [
        'organization_id' => $organizationId,
        'user_id' => $responsibleUserId,
        'role_key' => 'partner-owner',
        'is_owner' => true,
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('event_access_grants', [
        'id' => $grantId,
        'organization_id' => $organizationId,
        'event_id' => $eventId,
        'source_type' => 'bonus',
        'package_id' => $package->id,
        'granted_by_user_id' => $admin->id,
        'status' => 'active',
    ]);

    $grant = EventAccessGrant::query()->findOrFail($grantId);
    expect($grant->metadata_json['reason'] ?? null)->toBe('Cortesia comercial');
    expect($grant->metadata_json['origin'] ?? null)->toBe('parceria');
    expect($grant->metadata_json['journey'] ?? null)->toBe('admin_quick_event');

    $event = Event::query()->findOrFail($eventId);
    expect($event->commercial_mode?->value)->toBe('bonus');
    expect($event->current_entitlements_json['limits']['max_photos'] ?? null)->toBe(500);
    expect($event->current_entitlements_json['modules']['wall'] ?? null)->toBeTrue();
});

it('reuses existing organization and responsible user for manual override quick event', function () {
    Queue::fake();

    [$admin] = $this->actingAsSuperAdmin();
    $startsAt = now()->subHour()->startOfSecond();
    $endsAt = now()->addDays(10)->endOfDay();

    $existingOrganization = Organization::factory()->create([
        'type' => 'partner',
    ]);

    $existingOwner = User::factory()->create();
    OrganizationMember::create([
        'organization_id' => $existingOrganization->id,
        'user_id' => $existingOwner->id,
        'role_key' => 'partner-owner',
        'is_owner' => true,
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $existingOwner->assignRole('partner-owner');

    $existingUser = User::factory()->create([
        'name' => 'Fernanda Rocha',
        'email' => 'fernanda@example.com',
        'phone' => '5548999441111',
    ]);

    $package = createAdminQuickEventPackage([
        'code' => 'manual-override-event',
        'name' => 'Manual Override Event',
        'features' => [
            'hub.enabled' => 'true',
            'wall.enabled' => 'true',
            'play.enabled' => 'true',
            'media.retention_days' => '180',
            'media.max_photos' => '900',
            'white_label.enabled' => 'true',
        ],
    ]);

    $response = $this->apiPost('/admin/quick-events', [
        'responsible_name' => 'Fernanda Rocha',
        'whatsapp' => '(48) 99944-1111',
        'email' => 'fernanda@example.com',
        'organization_id' => $existingOrganization->id,
        'send_access' => true,
        'event' => [
            'title' => 'Evento Fernanda Premium',
            'event_type' => 'corporate',
            'event_date' => '2026-09-10',
            'city' => 'Florianopolis',
            'moderation_mode' => 'manual',
        ],
        'grant' => [
            'source_type' => 'manual_override',
            'package_id' => $package->id,
            'reason' => 'Upgrade temporario',
            'origin' => 'suporte',
            'notes' => 'Liberacao assistida para o evento premium.',
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->format('Y-m-d H:i:s'),
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $eventId = $response->json('data.event.id');
    $grantId = $response->json('data.grant.id');

    expect($response->json('data.organization.id'))->toBe($existingOrganization->id);
    expect($response->json('data.responsible_user.id'))->toBe($existingUser->id);
    expect($response->json('data.grant.source_type'))->toBe('manual_override');
    expect($response->json('data.commercial_status.commercial_mode'))->toBe('manual_override');
    expect($response->json('data.setup.organization_reused'))->toBeTrue();
    expect($response->json('data.setup.responsible_user_reused'))->toBeTrue();
    expect($response->json('data.access_delivery.status'))->toBe('unavailable');
    expect($response->json('data.access_delivery.channel'))->toBe('whatsapp');
    expect($response->json('data.access_delivery.target'))->toBe('5548999441111');

    $this->assertDatabaseHas('organization_members', [
        'organization_id' => $existingOrganization->id,
        'user_id' => $existingUser->id,
        'role_key' => 'partner-manager',
        'is_owner' => false,
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('event_access_grants', [
        'id' => $grantId,
        'event_id' => $eventId,
        'source_type' => 'manual_override',
        'package_id' => $package->id,
        'granted_by_user_id' => $admin->id,
        'status' => 'active',
    ]);

    $grant = EventAccessGrant::query()->findOrFail($grantId);
    expect($grant->metadata_json['access_delivery_requested'] ?? null)->toBeTrue();
    expect($grant->metadata_json['access_delivery']['status'] ?? null)->toBe('unavailable');
    expect($grant->metadata_json['membership_role_key'] ?? null)->toBe('partner-manager');
    expect($grant->starts_at?->toISOString())->toBe($startsAt->toISOString());

    $event = Event::query()->findOrFail($eventId);
    expect($event->commercial_mode?->value)->toBe('manual_override');
    expect($event->current_entitlements_json['modules']['play'] ?? null)->toBeTrue();
    expect($event->current_entitlements_json['branding']['white_label'] ?? null)->toBeTrue();

    Queue::assertNothingPushed();
});

it('queues whatsapp access delivery when admin quick event requests access and a sender instance is configured', function () {
    Queue::fake();

    [$admin] = $this->actingAsSuperAdmin();

    $senderInstance = WhatsAppInstance::factory()->connected()->create([
        'is_default' => true,
    ]);

    config([
        'billing.access_delivery.whatsapp_instance_id' => $senderInstance->id,
    ]);

    $package = createAdminQuickEventPackage([
        'code' => 'quick-event-access',
        'name' => 'Quick Event Access',
    ]);

    $response = $this->apiPost('/admin/quick-events', [
        'responsible_name' => 'Ricardo Araujo',
        'whatsapp' => '(48) 99966-1122',
        'email' => 'ricardo@example.com',
        'organization_name' => 'Ricardo Eventos',
        'organization_type' => 'direct_customer',
        'send_access' => true,
        'event' => [
            'title' => 'Evento Ricardo VIP',
            'event_type' => 'corporate',
            'event_date' => '2026-11-10',
            'city' => 'Sao Jose',
        ],
        'grant' => [
            'source_type' => 'bonus',
            'package_id' => $package->id,
            'reason' => 'Ativacao assistida',
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $grantId = $response->json('data.grant.id');

    expect($response->json('data.access_delivery.status'))->toBe('queued');
    expect($response->json('data.access_delivery.channel'))->toBe('whatsapp');
    expect($response->json('data.access_delivery.target'))->toBe('5548999661122');
    expect($response->json('data.access_delivery.instance_id'))->toBe($senderInstance->id);
    expect($response->json('data.access_delivery.message_id'))->toBeInt();

    $this->assertDatabaseHas('whatsapp_messages', [
        'instance_id' => $senderInstance->id,
        'recipient_phone' => '5548999661122',
    ]);

    $grant = EventAccessGrant::query()->findOrFail($grantId);
    expect($grant->metadata_json['access_delivery']['status'] ?? null)->toBe('queued');
    expect($grant->metadata_json['access_delivery']['instance_id'] ?? null)->toBe($senderInstance->id);

    Queue::assertPushed(SendWhatsAppMessageJob::class);
});

it('forbids partner-owner from using admin quick event endpoint', function () {
    [$owner] = $this->actingAsOwner();

    $package = createAdminQuickEventPackage();

    $response = $this->apiPost('/admin/quick-events', [
        'responsible_name' => 'Ana Souza',
        'whatsapp' => '(48) 99911-2222',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Evento Ana',
            'event_type' => 'birthday',
        ],
        'grant' => [
            'source_type' => 'bonus',
            'package_id' => $package->id,
            'reason' => 'Tentativa nao autorizada',
        ],
    ]);

    $this->assertApiForbidden($response);
});

function createAdminQuickEventPackage(array $overrides = []): EventPackage
{
    $package = EventPackage::factory()->create([
        'code' => $overrides['code'] ?? fake()->slug(2),
        'name' => $overrides['name'] ?? 'Pacote Admin',
        'target_audience' => $overrides['target_audience'] ?? EventPackageAudience::Both->value,
        'is_active' => $overrides['is_active'] ?? true,
    ]);

    $package->prices()->create([
        'billing_mode' => EventPackageBillingMode::OneTime->value,
        'currency' => 'BRL',
        'amount_cents' => $overrides['amount_cents'] ?? 19900,
        'is_active' => true,
        'is_default' => true,
    ]);

    foreach (($overrides['features'] ?? [
        'hub.enabled' => 'true',
        'wall.enabled' => 'true',
        'play.enabled' => 'false',
        'media.retention_days' => '60',
        'media.max_photos' => '300',
    ]) as $featureKey => $featureValue) {
        $package->features()->create([
            'feature_key' => $featureKey,
            'feature_value' => $featureValue,
        ]);
    }

    return $package->fresh(['prices', 'features']);
}
