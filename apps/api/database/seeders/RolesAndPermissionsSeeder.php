<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── Permissions ───────────────────────────────────────
        $permissions = [
            // Organizations
            'organizations.view', 'organizations.create', 'organizations.update', 'organizations.delete',
            // Users
            'users.view', 'users.manage',
            // Roles
            'roles.view', 'roles.manage',
            // Clients
            'clients.view', 'clients.create', 'clients.update', 'clients.delete',
            // Events
            'events.view', 'events.create', 'events.update', 'events.publish', 'events.archive',
            'events.activate', 'events.manage_branding', 'events.manage_team',
            // Channels
            'channels.view', 'channels.manage',
            // Media
            'media.view', 'media.moderate', 'media.reprocess', 'media.delete',
            // Gallery
            'gallery.view', 'gallery.manage', 'gallery.builder.manage',
            // Wall
            'wall.view', 'wall.manage',
            // Play
            'play.view', 'play.manage',
            // Hub
            'hub.view', 'hub.manage',
            // Billing
            'billing.view', 'billing.manage', 'billing.purchase', 'billing.manage_subscription',
            // Analytics
            'analytics.view',
            // Partners
            'partners.view.any',
            'partners.manage.any',
            // Settings
            'settings.manage',
            'branding.manage',
            'team.manage',
            'integrations.manage',
            // White Label
            'white_label.manage',
            // Audit
            'audit.view',
            // Plans
            'plans.view', 'plans.manage',
            // Notifications
            'notifications.view', 'notifications.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ─── Roles ─────────────────────────────────────────────

        // Super Admin — acesso total
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo(Permission::all());

        // Platform Admin — admin da plataforma
        $platformAdmin = Role::firstOrCreate(['name' => 'platform-admin', 'guard_name' => 'web']);
        $platformAdmin->givePermissionTo(Permission::all());

        // Partner Owner — dono da organização parceira
        $partnerOwner = Role::firstOrCreate(['name' => 'partner-owner', 'guard_name' => 'web']);
        $partnerOwner->givePermissionTo([
            'organizations.view', 'organizations.update',
            'users.view', 'users.manage',
            'clients.view', 'clients.create', 'clients.update', 'clients.delete',
            'events.view', 'events.create', 'events.update', 'events.publish', 'events.archive',
            'events.activate', 'events.manage_branding', 'events.manage_team',
            'channels.view', 'channels.manage',
            'media.view', 'media.moderate', 'media.reprocess', 'media.delete',
            'gallery.view', 'gallery.manage', 'gallery.builder.manage',
            'wall.view', 'wall.manage',
            'play.view', 'play.manage',
            'hub.view', 'hub.manage',
            'billing.view', 'billing.manage', 'billing.purchase', 'billing.manage_subscription',
            'analytics.view',
            'settings.manage',
            'branding.manage',
            'team.manage',
            'white_label.manage',
            'notifications.view', 'notifications.manage',
        ]);

        // Partner Manager — gerente da organização
        $partnerManager = Role::firstOrCreate(['name' => 'partner-manager', 'guard_name' => 'web']);
        $partnerManager->givePermissionTo([
            'organizations.view',
            'users.view',
            'clients.view', 'clients.create', 'clients.update',
            'events.view', 'events.create', 'events.update', 'events.publish',
            'events.manage_branding', 'events.manage_team',
            'channels.view', 'channels.manage',
            'media.view', 'media.moderate', 'media.reprocess',
            'gallery.view', 'gallery.manage', 'gallery.builder.manage',
            'wall.view', 'wall.manage',
            'play.view', 'play.manage',
            'hub.view', 'hub.manage',
            'analytics.view',
            'settings.manage',
            'team.manage',
            'notifications.view',
        ]);

        // Event Operator — operador de evento
        $eventOperator = Role::firstOrCreate(['name' => 'event-operator', 'guard_name' => 'web']);
        $eventOperator->givePermissionTo([
            'events.view', 'events.update',
            'channels.view',
            'media.view', 'media.moderate', 'media.reprocess',
            'gallery.view', 'gallery.manage', 'gallery.builder.manage',
            'wall.view', 'wall.manage',
            'play.view', 'play.manage',
            'hub.view',
            'notifications.view',
        ]);

        // Financeiro — acesso billing e analytics
        $financeiro = Role::firstOrCreate(['name' => 'financeiro', 'guard_name' => 'web']);
        $financeiro->givePermissionTo([
            'organizations.view',
            'billing.view', 'billing.purchase', 'billing.manage_subscription',
            'analytics.view',
            'plans.view',
        ]);

        // Cliente Final — acesso limitado ao evento
        $client = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $client->givePermissionTo([
            'events.view',
            'gallery.view',
            'wall.view',
            'play.view',
            'hub.view',
            'analytics.view',
        ]);

        // Viewer — apenas visualização
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->givePermissionTo([
            'events.view',
            'channels.view',
            'media.view',
            'gallery.view',
            'wall.view',
            'play.view',
            'hub.view',
            'analytics.view',
        ]);
    }
}
