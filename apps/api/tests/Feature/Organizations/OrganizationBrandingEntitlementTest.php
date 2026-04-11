<?php

use App\Modules\Billing\Models\Subscription;
use App\Modules\Plans\Models\Plan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('blocks custom domains when the organization plan does not include the entitlement', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPatch('/organizations/current', [
        'custom_domain' => 'eventos.sem-plano.test',
    ]);

    $this->assertApiValidationError($response, ['custom_domain']);

    expect($organization->fresh()->custom_domain)->toBeNull();
});

it('allows custom domains when the organization plan includes the entitlement', function () {
    [$user, $organization] = $this->actingAsOwner();

    attachOrganizationPlanFeatures($organization->id, [
        'custom_domain' => 'true',
    ]);

    $response = $this->apiPatch('/organizations/current', [
        'custom_domain' => 'eventos.com-plano.test',
    ]);

    $this->assertApiSuccess($response);

    expect($organization->fresh()->custom_domain)->toBe('eventos.com-plano.test');
});

it('blocks expanded branding assets when white-label branding is not enabled', function () {
    Storage::fake('public');

    [$user, $organization] = $this->actingAsOwner();

    $response = $this->withHeaders($this->defaultHeaders())
        ->post('/api/v1/organizations/current/branding/assets', [
            'kind' => 'favicon',
            'asset' => UploadedFile::fake()->image('favicon.png', 512, 512),
        ]);

    $this->assertApiValidationError($response, ['kind']);

    expect($organization->fresh()->favicon_path)->toBeNull();
});

it('uploads expanded branding assets when white-label branding is enabled', function () {
    Storage::fake('public');

    [$user, $organization] = $this->actingAsOwner();

    attachOrganizationPlanFeatures($organization->id, [
        'white_label.enabled' => 'true',
    ]);

    $response = $this->withHeaders($this->defaultHeaders())
        ->post('/api/v1/organizations/current/branding/assets', [
            'kind' => 'favicon',
            'asset' => UploadedFile::fake()->image('favicon.png', 512, 512),
        ]);

    $this->assertApiSuccess($response);

    $organization->refresh();

    expect($organization->favicon_path)->not->toBeNull();
    expect($organization->favicon_path)->toEndWith('.webp');
    expect(Storage::disk('public')->exists($organization->favicon_path))->toBeTrue();
    expect($response->json('data.kind'))->toBe('favicon');
    expect($response->json('data.path'))->toBe($organization->favicon_path);
});

function attachOrganizationPlanFeatures(int $organizationId, array $features): void
{
    $plan = Plan::query()->create([
        'code' => 'branding-premium-' . uniqid(),
        'name' => 'Branding Premium',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    foreach ($features as $key => $value) {
        $plan->features()->create([
            'feature_key' => $key,
            'feature_value' => $value,
        ]);
    }

    Subscription::query()->create([
        'organization_id' => $organizationId,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
    ]);
}
