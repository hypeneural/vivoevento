<?php

namespace App\Modules\Auth\Enums;

use App\Modules\Organizations\Enums\OrganizationType;

enum RegisterJourneyType: string
{
    case PartnerSignup = 'partner_signup';
    case TrialEvent = 'trial_event';
    case SingleEventCheckout = 'single_event_checkout';
    case AdminAssisted = 'admin_assisted';

    public static function fallback(): self
    {
        return self::PartnerSignup;
    }

    public function organizationType(): OrganizationType
    {
        return match ($this) {
            self::SingleEventCheckout => OrganizationType::DirectCustomer,
            self::PartnerSignup,
            self::TrialEvent,
            self::AdminAssisted => OrganizationType::Partner,
        };
    }

    public function nextPath(): string
    {
        return match ($this) {
            self::PartnerSignup => '/plans',
            self::TrialEvent,
            self::SingleEventCheckout => '/events/create',
            self::AdminAssisted => '/events',
        };
    }

    public function onboardingDescription(): string
    {
        return match ($this) {
            self::PartnerSignup => 'Sua conta ja esta pronta. Agora escolha um plano para ativar seu primeiro evento.',
            self::TrialEvent => 'Sua conta ja esta pronta. Agora crie seu evento teste para validar a experiencia.',
            self::SingleEventCheckout => 'Sua conta ja esta pronta. Agora configure o seu evento para seguir com a contratacao.',
            self::AdminAssisted => 'Seu acesso inicial ja esta pronto. Continue para revisar ou configurar o evento.',
        };
    }
}
