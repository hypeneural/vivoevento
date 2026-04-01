<?php

namespace Database\Seeders;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Super Admin ────────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@eventovivo.com.br'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );
        $admin->assignRole('super-admin');

        // ─── Demo Organization ──────────────────────────────
        $org = Organization::firstOrCreate(
            ['slug' => 'demo-fotografia'],
            [
                'name' => 'Demo Fotografia',
                'type' => 'partner',
                'status' => 'active',
                'email' => 'contato@demofotografia.com.br',
                'timezone' => 'America/Sao_Paulo',
            ]
        );

        OrganizationMember::firstOrCreate(
            ['organization_id' => $org->id, 'user_id' => $admin->id],
            [
                'role_key' => 'owner',
                'is_owner' => true,
                'joined_at' => now(),
            ]
        );

        // ─── Demo Partner ───────────────────────────────────
        $partner = User::firstOrCreate(
            ['email' => 'parceiro@eventovivo.com.br'],
            [
                'name' => 'Parceiro Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );
        $partner->assignRole('partner-owner');

        OrganizationMember::firstOrCreate(
            ['organization_id' => $org->id, 'user_id' => $partner->id],
            [
                'role_key' => 'manager',
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        // ─── Demo Event Operator ────────────────────────────
        $operator = User::firstOrCreate(
            ['email' => 'operador@eventovivo.com.br'],
            [
                'name' => 'Operador Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );
        $operator->assignRole('event-operator');
    }
}
