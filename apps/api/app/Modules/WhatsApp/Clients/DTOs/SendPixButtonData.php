<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Data for sending a PIX button message.
 */
final readonly class SendPixButtonData
{
    /**
     * @param string      $phone        Recipient phone
     * @param string      $pixKey       PIX key value
     * @param string      $type         PIX key type: CPF, CNPJ, PHONE, EMAIL, EVP
     * @param string|null $merchantName Title on the button (default: 'Pix')
     */
    public function __construct(
        public string $phone,
        public string $pixKey,
        public string $type,
        public ?string $merchantName = null,
    ) {}
}
