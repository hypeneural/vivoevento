<?php

use App\Modules\Billing\Enums\BillingOrderMode;
use App\Modules\Billing\Enums\BillingOrderStatus;
use App\Modules\Billing\Enums\EntitlementMergeStrategy;
use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Billing\Enums\EventAccessGrantStatus;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Clients\Models\Client;
use App\Modules\Events\Models\Event;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Plans\Models\Plan;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

it('allows a super admin to list only partner organizations with aggregate metrics', function () {
    [$admin] = $this->actingAsSuperAdmin(
        Organization::factory()->create(['type' => 'internal'])
    );

    $plan = createPartnerContractPlan('pro-parceiro', 'Pro Parceiro');
    $partner = createPartnerContractOrganization([
        'trade_name' => 'Cerimonial Viva',
        'legal_name' => 'Cerimonial Viva LTDA',
        'email' => 'contato@cerimonialviva.test',
        'status' => 'active',
    ]);

    createPartnerContractOrganization([
        'trade_name' => 'Cliente Direto',
        'type' => 'direct_customer',
    ]);

    createPartnerContractSubscription($partner, $plan);
    createPartnerContractMember($partner, 'partner-owner', true);
    createPartnerContractMember($partner, 'partner-manager');
    Client::factory()->count(2)->create(['organization_id' => $partner->id]);
    Event::factory()->active()->count(3)->create(['organization_id' => $partner->id]);
    Event::factory()->draft()->create(['organization_id' => $partner->id]);
    createPartnerContractPaidInvoice($partner, BillingOrderMode::Subscription->value, 9900, $admin);
    createPartnerContractPaidInvoice($partner, BillingOrderMode::EventPackage->value, 19900, $admin);

    $response = $this->apiGet('/partners?per_page=10');

    $this->assertApiSuccess($response);
    $this->assertApiPaginated($response);
    expect($response->json('data'))->toHaveCount(1);
    $response
        ->assertJsonPath('data.0.id', $partner->id)
        ->assertJsonPath('data.0.type', 'partner')
        ->assertJsonPath('data.0.name', 'Cerimonial Viva')
        ->assertJsonPath('data.0.clients_count', 2)
        ->assertJsonPath('data.0.events_count', 4)
        ->assertJsonPath('data.0.active_events_count', 3)
        ->assertJsonPath('data.0.team_size', 2)
        ->assertJsonPath('data.0.current_subscription.plan_key', 'pro-parceiro')
        ->assertJsonPath('data.0.current_subscription.status', 'active')
        ->assertJsonPath('data.0.revenue.subscription_cents', 9900)
        ->assertJsonPath('data.0.revenue.event_package_cents', 19900)
        ->assertJsonPath('data.0.revenue.total_cents', 29800);
});

it('allows legacy global admin roles to access partners even before scoped permissions are reseeded', function () {
    [$admin] = $this->actingAsSuperAdmin(
        Organization::factory()->create(['type' => 'internal'])
    );

    $admin->roles()->firstOrFail()->revokePermissionTo([
        'partners.view.any',
        'partners.manage.any',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin->unsetRelation('roles');
    $admin->unsetRelation('permissions');

    $partner = createPartnerContractOrganization([
        'trade_name' => 'Parceiro Seed Legado',
    ]);

    $this->apiGet('/partners?per_page=10')
        ->assertOk()
        ->assertJsonPath('data.0.id', $partner->id);

    $this->apiPost('/partners', [
        'name' => 'Parceiro Criado Com Role Global',
        'email' => 'global-role@partner.test',
        'owner' => [
            'name' => 'Owner Global',
            'email' => 'owner-global@partner.test',
        ],
    ])->assertCreated();
});

it('lists partners even when partner projection tables are not available yet', function () {
    $this->actingAsSuperAdmin();

    $plan = createPartnerContractPlan('pro-parceiro', 'Pro Parceiro');
    $partner = createPartnerContractOrganization([
        'trade_name' => 'Parceiro Sem Projecao',
        'email' => 'fallback@partner.test',
    ]);

    createPartnerContractSubscription($partner, $plan);

    Schema::dropIfExists('partner_stats');
    Schema::dropIfExists('partner_profiles');

    $response = $this->apiGet('/partners?per_page=10');

    $this->assertApiSuccess($response);
    $this->assertApiPaginated($response);

    $partnerPayload = collect($response->json('data'))->firstWhere('id', $partner->id);

    expect($partnerPayload)->not->toBeNull();
    expect($partnerPayload['name'])->toBe('Parceiro Sem Projecao');
    expect($partnerPayload['segment'])->toBeNull();
    expect($partnerPayload['clients_count'])->toBe(0);
    expect($partnerPayload['events_count'])->toBe(0);
    expect($partnerPayload['revenue']['total_cents'])->toBe(0);
    expect($partnerPayload['current_subscription']['plan_key'])->toBe('pro-parceiro');
});

it('shows partner detail even when partner projection tables are not available yet', function () {
    $this->actingAsSuperAdmin();

    $partner = createPartnerContractOrganization([
        'trade_name' => 'Detalhe Sem Projecao',
    ]);

    Client::factory()->create(['organization_id' => $partner->id]);
    Event::factory()->active()->create(['organization_id' => $partner->id]);

    Schema::dropIfExists('partner_stats');
    Schema::dropIfExists('partner_profiles');

    $response = $this->apiGet("/partners/{$partner->id}");

    $this->assertApiSuccess($response);
    $response
        ->assertJsonPath('data.id', $partner->id)
        ->assertJsonPath('data.name', 'Detalhe Sem Projecao')
        ->assertJsonPath('data.segment', null)
        ->assertJsonPath('data.events_summary.total', 1)
        ->assertJsonPath('data.events_summary.active', 1)
        ->assertJsonPath('data.clients_summary.total', 1);
});

it('supports searching partners by commercial segment stored in partner profiles', function () {
    $this->actingAsSuperAdmin();

    $cerimonial = createPartnerContractOrganization(['trade_name' => 'Cerimonial Segmentado']);
    $fotografo = createPartnerContractOrganization(['trade_name' => 'Fotografo Segmentado']);

    DB::table('partner_profiles')->insert([
        [
            'organization_id' => $cerimonial->id,
            'segment' => 'cerimonialista',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'organization_id' => $fotografo->id,
            'segment' => 'fotografo',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $response = $this->apiGet('/partners?search=cerimonialista');

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toHaveCount(1);
    $response
        ->assertJsonPath('data.0.id', $cerimonial->id)
        ->assertJsonPath('data.0.segment', 'cerimonialista');
});

it('filters partners by search status plan active events and sorting', function () {
    [$admin] = $this->actingAsSuperAdmin();

    $proPlan = createPartnerContractPlan('pro-parceiro', 'Pro Parceiro');
    $starterPlan = createPartnerContractPlan('starter', 'Starter');

    $matchingPartner = createPartnerContractOrganization([
        'trade_name' => 'Cerimonial Horizonte',
        'email' => 'agenda@horizonte.test',
        'status' => 'active',
    ]);

    $wrongPlanPartner = createPartnerContractOrganization([
        'trade_name' => 'Cerimonial Norte',
        'status' => 'active',
    ]);

    $inactivePartner = createPartnerContractOrganization([
        'trade_name' => 'Cerimonial Horizonte Pausada',
        'status' => 'inactive',
    ]);

    createPartnerContractSubscription($matchingPartner, $proPlan);
    createPartnerContractSubscription($wrongPlanPartner, $starterPlan);
    createPartnerContractSubscription($inactivePartner, $proPlan);
    Event::factory()->active()->create(['organization_id' => $matchingPartner->id]);

    createPartnerContractPaidInvoice($matchingPartner, BillingOrderMode::Subscription->value, 30000, $admin);
    createPartnerContractPaidInvoice($wrongPlanPartner, BillingOrderMode::Subscription->value, 50000, $admin);

    $response = $this->apiGet('/partners?' . http_build_query([
        'search' => 'Horizonte',
        'status' => 'active',
        'plan_code' => 'pro-parceiro',
        'has_active_events' => 1,
        'sort_by' => 'revenue_cents',
        'sort_direction' => 'desc',
        'per_page' => 10,
    ]));

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.id', $matchingPartner->id);
});

it('filters partners by active bonus and manual override grants', function () {
    [$admin] = $this->actingAsSuperAdmin();

    $withBonus = createPartnerContractOrganization(['trade_name' => 'Bonus Cerimonial']);
    $withoutBonus = createPartnerContractOrganization(['trade_name' => 'Sem Bonus']);

    $bonusEvent = Event::factory()->active()->create(['organization_id' => $withBonus->id]);
    Event::factory()->active()->create(['organization_id' => $withoutBonus->id]);

    EventAccessGrant::factory()->forEvent($bonusEvent)->create([
        'source_type' => EventAccessGrantSourceType::Bonus->value,
        'status' => EventAccessGrantStatus::Active->value,
        'priority' => EventAccessGrantSourceType::Bonus->defaultPriority(),
        'merge_strategy' => EntitlementMergeStrategy::Expand->value,
        'granted_by_user_id' => $admin->id,
    ]);

    $response = $this->apiGet('/partners?has_active_bonus_grants=1');

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toHaveCount(1);
    $response
        ->assertJsonPath('data.0.id', $withBonus->id)
        ->assertJsonPath('data.0.active_bonus_grants_count', 1);
});

it('filters partners by subscription status and by existence of clients', function () {
    $this->actingAsSuperAdmin();

    $withClients = createPartnerContractOrganization(['trade_name' => 'Com Clientes']);
    $withoutClients = createPartnerContractOrganization(['trade_name' => 'Sem Clientes']);
    $trialingPartner = createPartnerContractOrganization(['trade_name' => 'Trialing']);

    $plan = createPartnerContractPlan('pro-parceiro', 'Pro Parceiro');

    createPartnerContractSubscription($withClients, $plan, 'active');
    createPartnerContractSubscription($withoutClients, $plan, 'active');
    createPartnerContractSubscription($trialingPartner, $plan, 'trialing');

    Client::factory()->count(2)->create(['organization_id' => $withClients->id]);

    $response = $this->apiGet('/partners?' . http_build_query([
        'subscription_status' => 'active',
        'has_clients' => 1,
    ]));

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toHaveCount(1);
    $response
        ->assertJsonPath('data.0.id', $withClients->id)
        ->assertJsonPath('data.0.clients_count', 2)
        ->assertJsonPath('data.0.current_subscription.status', 'active');
});

it('allows a super admin to create a partner with an owner membership', function () {
    [$admin] = $this->actingAsSuperAdmin();

    $response = $this->apiPost('/partners', [
        'name' => 'Cerimonial Nova Era',
        'legal_name' => 'Cerimonial Nova Era LTDA',
        'document_number' => '00.000.000/0001-00',
        'email' => 'contato@novaera.test',
        'billing_email' => 'financeiro@novaera.test',
        'phone' => '11999990000',
        'timezone' => 'America/Sao_Paulo',
        'segment' => 'cerimonialista',
        'owner' => [
            'name' => 'Maria Cerimonial',
            'email' => 'maria@novaera.test',
            'phone' => '11999990001',
            'send_invite' => true,
        ],
    ]);

    $this->assertApiSuccess($response, 201);
    $response
        ->assertJsonPath('data.type', 'partner')
        ->assertJsonPath('data.name', 'Cerimonial Nova Era')
        ->assertJsonPath('data.segment', 'cerimonialista')
        ->assertJsonPath('data.owner.email', 'maria@novaera.test');

    $partnerId = $response->json('data.id');

    $this->assertDatabaseHas('organizations', [
        'id' => $partnerId,
        'type' => 'partner',
        'trade_name' => 'Cerimonial Nova Era',
    ]);

    $this->assertDatabaseHas('organization_members', [
        'organization_id' => $partnerId,
        'role_key' => 'partner-owner',
        'is_owner' => true,
        'status' => 'active',
    ]);

    assertPartnerContractActivityLogged($partnerId, 'partner.created', $admin);
});

it('validates required fields when creating a partner', function () {
    $this->actingAsSuperAdmin();

    $response = $this->apiPost('/partners', [
        'email' => 'not-an-email',
        'status' => 'unknown',
        'owner' => [
            'email' => 'owner-without-name.test',
        ],
    ]);

    $this->assertApiValidationError($response, [
        'name',
        'email',
        'status',
        'owner.name',
        'owner.email',
    ]);
});

it('allows a super admin to update partner organization and profile fields', function () {
    [$admin] = $this->actingAsSuperAdmin();
    $partner = createPartnerContractOrganization([
        'trade_name' => 'Nome Antigo',
        'email' => 'antigo@partner.test',
    ]);

    $response = $this->apiPatch("/partners/{$partner->id}", [
        'name' => 'Nome Atualizado',
        'email' => 'novo@partner.test',
        'segment' => 'cerimonialista',
        'status' => 'active',
        'notes' => 'Atende casamentos premium.',
    ]);

    $this->assertApiSuccess($response);
    $response
        ->assertJsonPath('data.id', $partner->id)
        ->assertJsonPath('data.name', 'Nome Atualizado')
        ->assertJsonPath('data.email', 'novo@partner.test')
        ->assertJsonPath('data.segment', 'cerimonialista');

    $this->assertDatabaseHas('organizations', [
        'id' => $partner->id,
        'trade_name' => 'Nome Atualizado',
        'email' => 'novo@partner.test',
    ]);

    assertPartnerContractActivityLogged($partner->id, 'partner.updated', $admin);
});

it('suspends a partner with operational relationships instead of deleting history', function () {
    [$admin] = $this->actingAsSuperAdmin();
    $partner = createPartnerContractOrganization(['status' => 'active']);

    Client::factory()->create(['organization_id' => $partner->id]);
    Event::factory()->active()->create(['organization_id' => $partner->id]);
    createPartnerContractPaidInvoice($partner, BillingOrderMode::Subscription->value, 9900, $admin);

    $response = $this->apiPost("/partners/{$partner->id}/suspend", [
        'reason' => 'Revisao administrativa',
        'notes' => 'Contrato em revisao.',
    ]);

    $this->assertApiSuccess($response);
    $response
        ->assertJsonPath('data.id', $partner->id)
        ->assertJsonPath('data.status', 'suspended');

    $this->assertDatabaseHas('organizations', [
        'id' => $partner->id,
        'status' => 'suspended',
        'deleted_at' => null,
    ]);

    assertPartnerContractActivityLogged($partner->id, 'partner.suspended', $admin);
});

it('allows deleting only an empty partner and rejects delete when operational history exists', function () {
    [$admin] = $this->actingAsSuperAdmin();

    $emptyPartner = createPartnerContractOrganization();
    $partnerWithHistory = createPartnerContractOrganization();

    Client::factory()->create(['organization_id' => $partnerWithHistory->id]);

    $this->apiDelete("/partners/{$partnerWithHistory->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Parceiro possui historico operacional. Use suspensao.');

    $deleteResponse = $this->apiDelete("/partners/{$emptyPartner->id}");

    $deleteResponse->assertStatus(204);
    expect(Organization::withTrashed()->find($emptyPartner->id)?->trashed())->toBeTrue();

    assertPartnerContractActivityLogged($emptyPartner->id, 'partner.deleted', $admin);
});

it('forbids partner owners from listing or viewing other partners', function () {
    [$partnerOwner, $ownOrganization] = $this->actingAsOwner(
        createPartnerContractOrganization(['trade_name' => 'Organizacao Propria'])
    );

    $otherPartner = createPartnerContractOrganization(['trade_name' => 'Outro Parceiro']);

    $this->apiGet('/partners')->assertStatus(403);
    $this->apiGet("/partners/{$otherPartner->id}")->assertStatus(403);
    $this->apiGet("/partners/{$ownOrganization->id}")->assertStatus(403);

    $this->apiGet('/organizations/current')->assertStatus(200);
});

it('forbids partner owners from using the global organizations admin listing', function () {
    $this->actingAsOwner(createPartnerContractOrganization());
    createPartnerContractOrganization(['trade_name' => 'Organizacao de Terceiro']);

    $this->apiGet('/organizations')->assertStatus(403);
});

it('forbids non global admins from mutating partners', function () {
    [$manager] = $this->actingAsManager(createPartnerContractOrganization());
    $partner = createPartnerContractOrganization();

    $this->apiPost('/partners', ['name' => 'Tentativa'])->assertStatus(403);
    $this->apiPatch("/partners/{$partner->id}", ['name' => 'Tentativa'])->assertStatus(403);
    $this->apiPost("/partners/{$partner->id}/suspend", ['reason' => 'Tentativa'])->assertStatus(403);
    $this->apiDelete("/partners/{$partner->id}")->assertStatus(403);
});

it('shows partner detail with events clients staff grants billing revenue and activity summaries', function () {
    [$admin] = $this->actingAsSuperAdmin();

    $plan = createPartnerContractPlan('enterprise', 'Enterprise');
    $partner = createPartnerContractOrganization(['trade_name' => 'Detalhe Completo']);
    createPartnerContractSubscription($partner, $plan);
    createPartnerContractMember($partner, 'partner-owner', true);
    createPartnerContractMember($partner, 'partner-manager');

    $client = Client::factory()->create(['organization_id' => $partner->id]);
    $event = Event::factory()->active()->create([
        'organization_id' => $partner->id,
        'client_id' => $client->id,
        'commercial_mode' => 'bonus',
    ]);

    EventAccessGrant::factory()->forEvent($event)->create([
        'source_type' => EventAccessGrantSourceType::Bonus->value,
        'status' => EventAccessGrantStatus::Active->value,
        'priority' => EventAccessGrantSourceType::Bonus->defaultPriority(),
        'granted_by_user_id' => $admin->id,
    ]);

    createPartnerContractPaidInvoice($partner, BillingOrderMode::Subscription->value, 89700, $admin);

    activity()
        ->causedBy($admin)
        ->performedOn($partner)
        ->event('partner.updated')
        ->withProperties(['partner_id' => $partner->id])
        ->log('Partner updated');

    $response = $this->apiGet("/partners/{$partner->id}");

    $this->assertApiSuccess($response);
    $response->assertJsonStructure([
        'data' => [
            'id',
            'type',
            'name',
            'status',
            'current_subscription' => ['plan_key', 'plan_name', 'status', 'billing_cycle'],
            'revenue' => ['currency', 'subscription_cents', 'event_package_cents', 'total_cents'],
            'events_summary' => ['total', 'active', 'draft', 'bonus', 'manual_override', 'single_purchase', 'subscription_covered'],
            'clients_summary' => ['total'],
            'staff_summary' => ['total', 'owners'],
            'grants_summary' => ['active_bonus', 'active_manual_override'],
            'latest_activity',
        ],
    ]);
});

it('lists partner events scoped to the selected partner organization', function () {
    $this->actingAsSuperAdmin();

    $partner = createPartnerContractOrganization(['trade_name' => 'Eventos Partner']);
    $otherPartner = createPartnerContractOrganization(['trade_name' => 'Outro Partner']);

    $matchingEvent = Event::factory()->active()->create([
        'organization_id' => $partner->id,
        'title' => 'Casamento Horizonte',
    ]);

    Event::factory()->active()->create([
        'organization_id' => $otherPartner->id,
        'title' => 'Casamento Horizonte de Outra Conta',
    ]);

    $response = $this->apiGet("/partners/{$partner->id}/events?search=Horizonte&status=active");

    $this->assertApiSuccess($response);
    $this->assertApiPaginated($response);
    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.id', $matchingEvent->id);
});

it('filters partner events by commercial mode inside the partner scope', function () {
    $this->actingAsSuperAdmin();

    $partner = createPartnerContractOrganization(['trade_name' => 'Eventos Comerciais']);

    $bonusEvent = Event::factory()->active()->create([
        'organization_id' => $partner->id,
        'title' => 'Evento Bonus',
        'commercial_mode' => 'bonus',
    ]);

    Event::factory()->active()->create([
        'organization_id' => $partner->id,
        'title' => 'Evento Subscription',
        'commercial_mode' => 'subscription_covered',
    ]);

    $response = $this->apiGet("/partners/{$partner->id}/events?commercial_mode=bonus");

    $this->assertApiSuccess($response);
    $this->assertApiPaginated($response);
    expect($response->json('data'))->toHaveCount(1);
    $response
        ->assertJsonPath('data.0.id', $bonusEvent->id)
        ->assertJsonPath('data.0.commercial_mode', 'bonus');
});

it('lists partner clients scoped to the selected partner organization', function () {
    $this->actingAsSuperAdmin();

    $partner = createPartnerContractOrganization(['trade_name' => 'Clientes Partner']);
    $otherPartner = createPartnerContractOrganization(['trade_name' => 'Outro Partner']);

    $matchingClient = Client::factory()->empresa()->create([
        'organization_id' => $partner->id,
        'name' => 'Cliente Horizonte',
    ]);

    Client::factory()->empresa()->create([
        'organization_id' => $otherPartner->id,
        'name' => 'Cliente Horizonte de Outra Conta',
    ]);

    $response = $this->apiGet("/partners/{$partner->id}/clients?search=Horizonte&type=empresa");

    $this->assertApiSuccess($response);
    $this->assertApiPaginated($response);
    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.id', $matchingClient->id);
});

it('lists partner staff scoped to the selected partner organization', function () {
    $this->actingAsSuperAdmin();

    $partner = createPartnerContractOrganization();
    $otherPartner = createPartnerContractOrganization();

    $owner = createPartnerContractMember($partner, 'partner-owner', true);
    createPartnerContractMember($otherPartner, 'partner-owner', true);

    $response = $this->apiGet("/partners/{$partner->id}/staff");

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toHaveCount(1);
    $response
        ->assertJsonPath('data.0.user.id', $owner->id)
        ->assertJsonPath('data.0.role_key', 'partner-owner')
        ->assertJsonPath('data.0.is_owner', true);
});

it('lists partner grants scoped to partner events and supports grant filters', function () {
    [$admin] = $this->actingAsSuperAdmin();

    $partner = createPartnerContractOrganization();
    $otherPartner = createPartnerContractOrganization();

    $event = Event::factory()->active()->create(['organization_id' => $partner->id]);
    $otherEvent = Event::factory()->active()->create(['organization_id' => $otherPartner->id]);

    $grant = EventAccessGrant::factory()->forEvent($event)->create([
        'source_type' => EventAccessGrantSourceType::ManualOverride->value,
        'status' => EventAccessGrantStatus::Active->value,
        'priority' => EventAccessGrantSourceType::ManualOverride->defaultPriority(),
        'granted_by_user_id' => $admin->id,
    ]);

    EventAccessGrant::factory()->forEvent($otherEvent)->create([
        'source_type' => EventAccessGrantSourceType::ManualOverride->value,
        'status' => EventAccessGrantStatus::Active->value,
        'priority' => EventAccessGrantSourceType::ManualOverride->defaultPriority(),
        'granted_by_user_id' => $admin->id,
    ]);

    $response = $this->apiGet("/partners/{$partner->id}/grants?source_type=manual_override&status=active");

    $this->assertApiSuccess($response);
    $this->assertApiPaginated($response);
    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.id', $grant->id);
});

it('lists partner activity scoped to the selected partner organization', function () {
    [$admin] = $this->actingAsSuperAdmin();

    $partner = createPartnerContractOrganization(['trade_name' => 'Activity Partner']);
    $otherPartner = createPartnerContractOrganization(['trade_name' => 'Outro Activity Partner']);

    activity()
        ->causedBy($admin)
        ->performedOn($partner)
        ->event('partner.updated')
        ->withProperties(['partner_id' => $partner->id, 'organization_id' => $partner->id])
        ->log('Partner updated');

    activity()
        ->causedBy($admin)
        ->performedOn($otherPartner)
        ->event('partner.updated')
        ->withProperties(['partner_id' => $otherPartner->id, 'organization_id' => $otherPartner->id])
        ->log('Other partner updated');

    $response = $this->apiGet("/partners/{$partner->id}/activity?activity_event=partner.updated");

    $this->assertApiSuccess($response);
    $this->assertApiPaginated($response);
    expect($response->json('data'))->toHaveCount(1);
    $response
        ->assertJsonPath('data.0.event', 'partner.updated')
        ->assertJsonPath('data.0.properties.partner_id', $partner->id);
});

it('records activity logs for partner create update suspend staff and grants', function () {
    [$admin] = $this->actingAsSuperAdmin();
    $partner = createPartnerContractOrganization();
    $event = Event::factory()->active()->create(['organization_id' => $partner->id]);

    $this->apiPatch("/partners/{$partner->id}", ['name' => 'Auditado']);
    $this->apiPost("/partners/{$partner->id}/staff", [
        'user' => [
            'name' => 'Staff Teste',
            'email' => 'staff@partner.test',
        ],
        'role_key' => 'partner-manager',
    ]);
    $this->apiPost("/partners/{$partner->id}/grants", [
        'event_id' => $event->id,
        'source_type' => 'bonus',
        'reason' => 'Cortesia comercial',
        'features' => ['wall.enabled' => true],
    ]);
    $this->apiPost("/partners/{$partner->id}/suspend", [
        'reason' => 'Suspensao administrativa',
    ]);

    expect(DB::table('activity_log')
        ->where('causer_id', $admin->id)
        ->whereJsonContains('properties->partner_id', $partner->id)
        ->whereIn('event', [
            'partner.updated',
            'partner.staff.invited',
            'partner.grant.created',
            'partner.suspended',
        ])
        ->count())->toBe(4);
});

it('uses partner stats projection for list metrics filters and sorting', function () {
    $this->actingAsSuperAdmin();

    $topPartner = createPartnerContractOrganization(['trade_name' => 'Topo Projecao']);
    $lowPartner = createPartnerContractOrganization(['trade_name' => 'Baixo Projecao']);

    DB::table('partner_stats')->insert([
        [
            'organization_id' => $topPartner->id,
            'clients_count' => 5,
            'events_count' => 7,
            'active_events_count' => 4,
            'team_size' => 3,
            'active_bonus_grants_count' => 1,
            'subscription_plan_code' => 'pro-parceiro',
            'subscription_plan_name' => 'Pro Parceiro',
            'subscription_status' => 'active',
            'subscription_billing_cycle' => 'monthly',
            'subscription_revenue_cents' => 9900,
            'event_package_revenue_cents' => 50000,
            'total_revenue_cents' => 59900,
            'last_paid_invoice_at' => now(),
            'refreshed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'organization_id' => $lowPartner->id,
            'clients_count' => 1,
            'events_count' => 1,
            'active_events_count' => 0,
            'team_size' => 1,
            'active_bonus_grants_count' => 0,
            'subscription_plan_code' => 'starter',
            'subscription_plan_name' => 'Starter',
            'subscription_status' => 'active',
            'subscription_billing_cycle' => 'monthly',
            'subscription_revenue_cents' => 9900,
            'event_package_revenue_cents' => 0,
            'total_revenue_cents' => 9900,
            'last_paid_invoice_at' => now(),
            'refreshed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $response = $this->apiGet('/partners?' . http_build_query([
        'plan_code' => 'pro-parceiro',
        'has_active_events' => 1,
        'sort_by' => 'revenue_cents',
        'sort_direction' => 'desc',
    ]));

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toHaveCount(1);
    $response
        ->assertJsonPath('data.0.id', $topPartner->id)
        ->assertJsonPath('data.0.revenue.total_cents', 59900);

    expect($response->json('data.0.stats_refreshed_at'))->toBeString()->not->toBe('');
});

it('rebuilds partner stats when clients events staff invoices and grants change', function () {
    [$admin] = $this->actingAsSuperAdmin();

    $partner = createPartnerContractOrganization();
    $plan = createPartnerContractPlan('pro-parceiro', 'Pro Parceiro');
    $event = Event::factory()->active()->create(['organization_id' => $partner->id]);

    createPartnerContractSubscription($partner, $plan);
    createPartnerContractMember($partner, 'partner-owner', true);
    Client::factory()->count(2)->create(['organization_id' => $partner->id]);
    createPartnerContractPaidInvoice($partner, BillingOrderMode::Subscription->value, 9900, $admin);
    EventAccessGrant::factory()->forEvent($event)->create([
        'source_type' => EventAccessGrantSourceType::Bonus->value,
        'status' => EventAccessGrantStatus::Active->value,
        'priority' => EventAccessGrantSourceType::Bonus->defaultPriority(),
        'granted_by_user_id' => $admin->id,
    ]);

    app(\App\Modules\Partners\Actions\RebuildPartnerStatsAction::class)->execute($partner);

    $this->assertDatabaseHas('partner_stats', [
        'organization_id' => $partner->id,
        'clients_count' => 2,
        'events_count' => 1,
        'active_events_count' => 1,
        'team_size' => 1,
        'active_bonus_grants_count' => 1,
        'subscription_plan_code' => 'pro-parceiro',
        'subscription_status' => 'active',
        'subscription_revenue_cents' => 9900,
        'total_revenue_cents' => 9900,
    ]);
});

function createPartnerContractOrganization(array $attributes = []): Organization
{
    return Organization::factory()->create(array_merge([
        'type' => 'partner',
        'status' => 'active',
    ], $attributes));
}
function createPartnerContractPlan(string $code, string $name): Plan
{
    return Plan::query()->create([
        'code' => $code,
        'name' => $name,
        'audience' => 'b2b',
        'status' => 'active',
        'description' => "{$name} contract plan",
    ]);
}

function createPartnerContractSubscription(
    Organization $organization,
    Plan $plan,
    string $status = 'active',
    string $billingCycle = 'monthly',
): Subscription {
    return Subscription::query()->create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => $status,
        'billing_cycle' => $billingCycle,
        'starts_at' => now()->subMonth(),
        'renews_at' => now()->addMonth(),
    ]);
}

function createPartnerContractPaidInvoice(
    Organization $organization,
    string $mode,
    int $amountCents,
    User $buyer,
): Invoice {
    $order = BillingOrder::query()->create([
        'organization_id' => $organization->id,
        'buyer_user_id' => $buyer->id,
        'mode' => $mode,
        'status' => BillingOrderStatus::Paid->value,
        'currency' => 'BRL',
        'total_cents' => $amountCents,
        'gateway_provider' => 'manual',
        'confirmed_at' => now(),
    ]);

    return Invoice::query()->create([
        'organization_id' => $organization->id,
        'billing_order_id' => $order->id,
        'invoice_number' => sprintf('PARTNER-%s-%s', $organization->id, fake()->unique()->numberBetween(1, 999999)),
        'status' => InvoiceStatus::Paid->value,
        'amount_cents' => $amountCents,
        'currency' => 'BRL',
        'issued_at' => now(),
        'due_at' => now(),
        'paid_at' => now(),
        'snapshot_json' => [],
    ]);
}

function createPartnerContractMember(
    Organization $organization,
    string $roleKey,
    bool $isOwner = false,
): User {
    $user = User::factory()->create();

    OrganizationMember::query()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role_key' => $roleKey,
        'is_owner' => $isOwner,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    return $user;
}

function assertPartnerContractActivityLogged(int $partnerId, string $eventName, User $actor): void
{
    expect(DB::table('activity_log')
        ->where('event', $eventName)
        ->where('causer_id', $actor->id)
        ->whereJsonContains('properties->partner_id', $partnerId)
        ->exists())->toBeTrue();
}
