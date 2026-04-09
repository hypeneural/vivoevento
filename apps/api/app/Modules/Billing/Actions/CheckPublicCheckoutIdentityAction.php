<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Services\PublicJourneyIdentityService;
use App\Modules\Users\Models\User;
use Illuminate\Validation\ValidationException;

class CheckPublicCheckoutIdentityAction
{
    public function __construct(
        private readonly PublicJourneyIdentityService $identity,
    ) {}

    public function execute(array $data, ?User $authenticatedUser = null): array
    {
        $normalizedPhone = $this->identity->normalizePhone($data['whatsapp']);
        $normalizedEmail = $this->identity->normalizeEmail($data['email'] ?? null);

        if ($authenticatedUser !== null) {
            try {
                $this->identity->alignAuthenticatedIdentity($authenticatedUser, $normalizedPhone, $normalizedEmail);

                return [
                    'identity_status' => 'authenticated_match',
                    'title' => 'Conta reconhecida',
                    'description' => 'Seus dados batem com a conta atual. Voce pode continuar normalmente.',
                    'action_label' => null,
                    'login_url' => null,
                    'cooldown_seconds' => null,
                ];
            } catch (ValidationException) {
                return [
                    'identity_status' => 'authenticated_mismatch',
                    'title' => 'Use os dados da conta atual',
                    'description' => 'Para continuar com seguranca, ajuste os dados para combinar com a conta autenticada.',
                    'action_label' => null,
                    'login_url' => null,
                    'cooldown_seconds' => null,
                ];
            }
        }

        $identity = $this->identity->detectExistingIdentity($normalizedPhone, $normalizedEmail);

        if ($identity['exists']) {
            return [
                'identity_status' => 'login_suggested',
                'title' => 'Ja encontramos seu cadastro',
                'description' => 'Entrar agora costuma ser mais rapido para continuar sua compra.',
                'action_label' => 'Entrar para continuar',
                'login_url' => $this->loginUrl(),
                'cooldown_seconds' => null,
            ];
        }

        return [
            'identity_status' => 'new_account',
            'title' => 'Tudo certo para continuar',
            'description' => 'Voce pode seguir normalmente para o pagamento.',
            'action_label' => null,
            'login_url' => null,
            'cooldown_seconds' => null,
        ];
    }

    private function loginUrl(): string
    {
        return '/login?returnTo=' . rawurlencode('/checkout/evento?resume=auth');
    }
}
