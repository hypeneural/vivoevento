<?php

use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CreatesUsers;

uses(CreatesUsers::class);

it('denies operational dashboards to guests and low privilege users', function () {
    expect(Gate::allows('viewHorizon'))->toBeFalse()
        ->and(Gate::allows('viewTelescope'))->toBeFalse()
        ->and(Gate::allows('viewPulse'))->toBeFalse();

    [$viewer] = $this->actingAsViewer();

    expect(Gate::forUser($viewer)->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser($viewer)->allows('viewTelescope'))->toBeFalse()
        ->and(Gate::forUser($viewer)->allows('viewPulse'))->toBeFalse();
});

it('allows operational dashboards to admins and audit viewers', function () {
    [$superAdmin] = $this->actingAsSuperAdmin();

    expect(Gate::forUser($superAdmin)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser($superAdmin)->allows('viewTelescope'))->toBeTrue()
        ->and(Gate::forUser($superAdmin)->allows('viewPulse'))->toBeTrue();

    $this->seedPermissions();
    $auditViewer = $this->createUser();
    $auditViewer->givePermissionTo('audit.view');

    expect(Gate::forUser($auditViewer)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser($auditViewer)->allows('viewTelescope'))->toBeTrue()
        ->and(Gate::forUser($auditViewer)->allows('viewPulse'))->toBeTrue();
});
