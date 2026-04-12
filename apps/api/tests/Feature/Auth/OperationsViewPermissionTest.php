<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('seeds operations view permission for operational room roles without granting it to viewers', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::query()->where('name', 'operations.view')->exists())->toBeTrue();

    foreach (['super-admin', 'platform-admin', 'partner-owner', 'partner-manager', 'event-operator'] as $roleName) {
        expect(Role::findByName($roleName)->hasPermissionTo('operations.view'))->toBeTrue();
    }

    expect(Role::findByName('viewer')->hasPermissionTo('operations.view'))->toBeFalse();
});
