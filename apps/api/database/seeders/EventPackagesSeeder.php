<?php

namespace Database\Seeders;

use App\Modules\Billing\Enums\EventPackageAudience;
use App\Modules\Billing\Enums\EventPackageBillingMode;
use App\Modules\Billing\Models\EventPackage;
use App\Modules\Billing\Models\EventPackageFeature;
use App\Modules\Billing\Models\EventPackagePrice;
use Illuminate\Database\Seeder;

class EventPackagesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPackage(
            code: 'essential-event',
            name: 'Essencial',
            description: 'Pacote leve para um unico evento com galeria publica e hub basico.',
            targetAudience: EventPackageAudience::Both,
            sortOrder: 10,
            amountCents: 9900,
            features: [
                'hub.enabled' => 'true',
                'wall.enabled' => 'false',
                'play.enabled' => 'false',
                'media.retention_days' => '30',
                'media.max_photos' => '150',
                'gallery.watermark' => 'false',
                'checkout.subtitle' => 'Compra rapida para eventos que querem comecar sem complicacao.',
                'checkout.ideal_for' => 'Cha revelacao, aniversario e eventos menores que querem uma jornada simples.',
                'checkout.benefit_1' => 'Galeria do evento pronta para compartilhar',
                'checkout.benefit_2' => 'Contratacao em poucos minutos',
                'checkout.benefit_3' => 'Pix e cartao com confirmacao automatica',
            ],
        );

        $this->seedPackage(
            code: 'interactive-event',
            name: 'Interativo',
            description: 'Pacote com wall, hub e mais capacidade para eventos sociais com engajamento.',
            targetAudience: EventPackageAudience::Both,
            sortOrder: 20,
            amountCents: 19900,
            features: [
                'hub.enabled' => 'true',
                'wall.enabled' => 'true',
                'play.enabled' => 'false',
                'media.retention_days' => '90',
                'media.max_photos' => '400',
                'gallery.watermark' => 'false',
                'checkout.subtitle' => 'O pacote mais equilibrado para eventos sociais com compra rapida.',
                'checkout.ideal_for' => 'Casamentos, aniversarios e eventos que querem telao ao vivo sem exagero.',
                'checkout.badge' => 'Mais escolhido',
                'checkout.recommended' => 'true',
                'checkout.benefit_1' => 'Telao ao vivo para os convidados',
                'checkout.benefit_2' => 'Pagina do evento pronta para compartilhar',
                'checkout.benefit_3' => 'Mais capacidade para registrar o evento',
            ],
        );

        $this->seedPackage(
            code: 'premium-event',
            name: 'Premium',
            description: 'Pacote completo para experiencias premium com play, wall e maior retencao.',
            targetAudience: EventPackageAudience::Both,
            sortOrder: 30,
            amountCents: 29900,
            features: [
                'hub.enabled' => 'true',
                'wall.enabled' => 'true',
                'play.enabled' => 'true',
                'media.retention_days' => '180',
                'media.max_photos' => '800',
                'gallery.watermark' => 'false',
                'white_label.enabled' => 'true',
                'checkout.subtitle' => 'Experiencia completa para quem quer mais impacto e interacao.',
                'checkout.ideal_for' => 'Eventos premium que querem telao, hub e experiencias interativas no mesmo pacote.',
                'checkout.badge' => 'Experiencia completa',
                'checkout.benefit_1' => 'Telao ao vivo com maior capacidade',
                'checkout.benefit_2' => 'Experiencias interativas para engajar os convidados',
                'checkout.benefit_3' => 'Memorias disponiveis por mais tempo',
            ],
        );
    }

    private function seedPackage(
        string $code,
        string $name,
        string $description,
        EventPackageAudience $targetAudience,
        int $sortOrder,
        int $amountCents,
        array $features,
    ): void {
        $package = EventPackage::firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'description' => $description,
                'target_audience' => $targetAudience->value,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]
        );

        EventPackagePrice::firstOrCreate(
            [
                'event_package_id' => $package->id,
                'billing_mode' => EventPackageBillingMode::OneTime->value,
            ],
            [
                'currency' => 'BRL',
                'amount_cents' => $amountCents,
                'is_active' => true,
                'is_default' => true,
            ]
        );

        foreach ($features as $featureKey => $featureValue) {
            EventPackageFeature::firstOrCreate(
                [
                    'event_package_id' => $package->id,
                    'feature_key' => $featureKey,
                ],
                [
                    'feature_value' => $featureValue,
                ]
            );
        }
    }
}
