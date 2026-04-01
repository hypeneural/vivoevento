<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * QR code response from provider.
 */
final readonly class ProviderQrCodeData
{
    public function __construct(
        public ?string $qrCodeBytes = null,
        public ?string $qrCodeBase64Image = null,
        public ?string $phoneCode = null,
        public bool $alreadyConnected = false,
        public ?string $error = null,
    ) {}
}
