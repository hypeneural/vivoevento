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
        public ?string $qrCodeValue = null,
        public ?string $phoneCode = null,
        public bool $alreadyConnected = false,
        public ?string $error = null,
    ) {}

    public function renderMode(): ?string
    {
        if ($this->qrCodeBase64Image) {
            return 'image';
        }

        if ($this->qrCodeValue) {
            return 'value';
        }

        if ($this->qrCodeBytes) {
            return 'bytes';
        }

        return null;
    }

    public function payload(): ?string
    {
        return $this->qrCodeBase64Image
            ?? $this->qrCodeValue
            ?? $this->qrCodeBytes;
    }
}
