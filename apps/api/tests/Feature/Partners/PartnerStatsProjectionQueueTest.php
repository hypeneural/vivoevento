<?php

use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Clients\Models\Client;
use App\Modules\Events\Models\Event;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Partners\Jobs\RebuildPartnerStatsJob;
use App\Modules\Plans\Models\Plan;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\Queue;

it('queues a unique partner stats rebuild job when projection sources change and async updates are enabled', function () {
    config()->set('partners.stats.async_updates', true);
    config()->set('partners.stats.queue', 'analytics');

    Queue::fake();

    $partner = Organization::factory()->create([
        'type' => 'partner',
    ]);

    $event = Event::factory()->active()->create([
        'organization_id' => $partner->id,
    ]);

    Client::factory()->create([
        'organization_id' => $partner->id,
    ]);

    OrganizationMember::query()->create([
        'organization_id' => $partner->id,
        'user_id' => User::factory()->create()->id,
        'role_key' => 'partner-manager',
        'is_owner' => false,
        'status' => 'active',
        'invited_at' => now(),
        'joined_at' => now(),
    ]);

    $plan = Plan::query()->create([
        'code' => 'pro-parceiro',
        'name' => 'Pro Parceiro',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    Subscription::query()->create([
        'organization_id' => $partner->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
    ]);

    $billingOrder = BillingOrder::factory()->create([
        'organization_id' => $partner->id,
        'event_id' => $event->id,
        'buyer_user_id' => null,
    ]);

    Invoice::factory()->create([
        'organization_id' => $partner->id,
        'billing_order_id' => $billingOrder->id,
    ]);

    EventAccessGrant::factory()->forEvent($event)->create([
        'organization_id' => $partner->id,
    ]);

    Queue::assertPushed(RebuildPartnerStatsJob::class, function (RebuildPartnerStatsJob $job) use ($partner) {
        return $job->organizationId === $partner->id
            && $job->queue === 'analytics';
    });

    Queue::assertPushedTimes(RebuildPartnerStatsJob::class, 1);
});

it('does not queue partner stats rebuild jobs when async updates are disabled', function () {
    config()->set('partners.stats.async_updates', false);

    Queue::fake();

    $partner = Organization::factory()->create([
        'type' => 'partner',
    ]);

    Client::factory()->create([
        'organization_id' => $partner->id,
    ]);

    Queue::assertNothingPushed();
});

it('rebuild partner stats job refreshes the projection for a partner', function () {
    $partner = Organization::factory()->create([
        'type' => 'partner',
    ]);

    Event::factory()->active()->create([
        'organization_id' => $partner->id,
    ]);

    Client::factory()->count(2)->create([
        'organization_id' => $partner->id,
    ]);

    OrganizationMember::query()->create([
        'organization_id' => $partner->id,
        'user_id' => User::factory()->create()->id,
        'role_key' => 'partner-owner',
        'is_owner' => true,
        'status' => 'active',
        'invited_at' => now(),
        'joined_at' => now(),
    ]);

    $job = new RebuildPartnerStatsJob($partner->id);
    $job->handle(app(\App\Modules\Partners\Actions\RebuildPartnerStatsAction::class));

    $this->assertDatabaseHas('partner_stats', [
        'organization_id' => $partner->id,
        'clients_count' => 2,
        'events_count' => 1,
        'active_events_count' => 1,
        'team_size' => 1,
    ]);
});
