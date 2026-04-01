<?php

namespace Database\Seeders;

use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanFeature;
use App\Modules\Plans\Models\PlanPrice;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Starter ────────────────────────────────────────
        $starter = Plan::firstOrCreate(
            ['code' => 'starter'],
            [
                'name' => 'Starter',
                'audience' => 'b2b',
                'status' => 'active',
                'description' => 'Para fotógrafos e profissionais que estão começando.',
            ]
        );

        PlanPrice::firstOrCreate(
            ['plan_id' => $starter->id, 'billing_cycle' => 'monthly'],
            ['currency' => 'BRL', 'amount_cents' => 4900, 'is_default' => true]
        );

        $this->seedFeatures($starter, [
            'events.max_active' => '3',
            'media.retention_days' => '30',
            'play.enabled' => 'false',
            'wall.enabled' => 'true',
            'channels.whatsapp' => 'true',
            'white_label.enabled' => 'false',
        ]);

        // ─── Professional ───────────────────────────────────
        $pro = Plan::firstOrCreate(
            ['code' => 'professional'],
            [
                'name' => 'Professional',
                'audience' => 'b2b',
                'status' => 'active',
                'description' => 'Para profissionais que precisam de mais recursos.',
            ]
        );

        PlanPrice::firstOrCreate(
            ['plan_id' => $pro->id, 'billing_cycle' => 'monthly'],
            ['currency' => 'BRL', 'amount_cents' => 9900, 'is_default' => true]
        );
        PlanPrice::firstOrCreate(
            ['plan_id' => $pro->id, 'billing_cycle' => 'yearly'],
            ['currency' => 'BRL', 'amount_cents' => 99900, 'is_default' => false]
        );

        $this->seedFeatures($pro, [
            'events.max_active' => '10',
            'media.retention_days' => '90',
            'play.enabled' => 'true',
            'wall.enabled' => 'true',
            'channels.whatsapp' => 'true',
            'white_label.enabled' => 'false',
        ]);

        // ─── Business ───────────────────────────────────────
        $business = Plan::firstOrCreate(
            ['code' => 'business'],
            [
                'name' => 'Business',
                'audience' => 'b2b',
                'status' => 'active',
                'description' => 'Para agências e operações com alto volume.',
            ]
        );

        PlanPrice::firstOrCreate(
            ['plan_id' => $business->id, 'billing_cycle' => 'monthly'],
            ['currency' => 'BRL', 'amount_cents' => 19900, 'is_default' => true]
        );
        PlanPrice::firstOrCreate(
            ['plan_id' => $business->id, 'billing_cycle' => 'yearly'],
            ['currency' => 'BRL', 'amount_cents' => 199900, 'is_default' => false]
        );

        $this->seedFeatures($business, [
            'events.max_active' => '50',
            'media.retention_days' => '180',
            'play.enabled' => 'true',
            'wall.enabled' => 'true',
            'channels.whatsapp' => 'true',
            'white_label.enabled' => 'true',
        ]);
    }

    private function seedFeatures(Plan $plan, array $features): void
    {
        foreach ($features as $key => $value) {
            PlanFeature::firstOrCreate(
                ['plan_id' => $plan->id, 'feature_key' => $key],
                ['feature_value' => $value]
            );
        }
    }
}
