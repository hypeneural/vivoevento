<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\EventPackage;

class EventPackageCheckoutMarketingService
{
    public function build(EventPackage $package, array $flatFeatureMap): array
    {
        $recommended = $this->toBoolean($flatFeatureMap['checkout.recommended'] ?? false);
        $badge = $this->stringValue($flatFeatureMap['checkout.badge'] ?? null)
            ?? ($recommended ? 'Mais escolhido' : null);

        return [
            'slug' => $this->stringValue($flatFeatureMap['checkout.slug'] ?? null) ?? $package->code,
            'subtitle' => $this->stringValue($flatFeatureMap['checkout.subtitle'] ?? null)
                ?? $package->description
                ?? 'Pacote pensado para uma compra rapida e sem complicacao.',
            'ideal_for' => $this->stringValue($flatFeatureMap['checkout.ideal_for'] ?? null)
                ?? $this->fallbackIdealFor($flatFeatureMap),
            'benefits' => $this->resolveBenefits($flatFeatureMap),
            'badge' => $badge,
            'recommended' => $recommended,
        ];
    }

    private function resolveBenefits(array $flatFeatureMap): array
    {
        $configuredBenefits = [];

        for ($index = 1; $index <= 5; $index += 1) {
            $value = $this->stringValue(
                $flatFeatureMap["checkout.benefit_{$index}"]
                    ?? $flatFeatureMap["checkout.benefits.{$index}"]
                    ?? null
            );

            if ($value !== null) {
                $configuredBenefits[] = $value;
            }
        }

        if ($configuredBenefits !== []) {
            return array_values(array_slice(array_unique($configuredBenefits), 0, 5));
        }

        $benefits = [];

        if ($this->toBoolean($flatFeatureMap['wall.enabled'] ?? false)) {
            $benefits[] = 'Telao ao vivo para os convidados';
        }

        if ($this->toBoolean($flatFeatureMap['hub.enabled'] ?? true)) {
            $benefits[] = 'Pagina do evento pronta para compartilhar';
        }

        if ($this->toBoolean($flatFeatureMap['play.enabled'] ?? false)) {
            $benefits[] = 'Experiencias interativas para engajar o publico';
        }

        $maxPhotos = $this->toInteger($flatFeatureMap['media.max_photos'] ?? null);

        if ($maxPhotos !== null) {
            $benefits[] = "Ate {$maxPhotos} fotos no evento";
        }

        $retentionDays = $this->toInteger($flatFeatureMap['media.retention_days'] ?? null);

        if ($retentionDays !== null) {
            $benefits[] = "Memorias disponiveis por {$retentionDays} dias";
        }

        return array_values(array_slice(array_unique($benefits), 0, 5));
    }

    private function fallbackIdealFor(array $flatFeatureMap): string
    {
        if ($this->toBoolean($flatFeatureMap['play.enabled'] ?? false)) {
            return 'Eventos que querem experiencia mais interativa para os convidados.';
        }

        if (
            $this->toBoolean($flatFeatureMap['wall.enabled'] ?? false)
            && $this->toBoolean($flatFeatureMap['hub.enabled'] ?? true)
        ) {
            return 'Casamentos, aniversarios e eventos sociais que querem compra rapida e experiencia completa.';
        }

        return 'Eventos que querem uma contratacao simples e segura.';
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function toInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
