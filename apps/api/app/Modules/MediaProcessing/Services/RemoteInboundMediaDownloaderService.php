<?php

namespace App\Modules\MediaProcessing\Services;

use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\Telegram\Clients\BotApi\TelegramBotApiClient;
use Illuminate\Support\Facades\Http;

class RemoteInboundMediaDownloaderService
{
    public function __construct(
        private readonly TelegramBotApiClient $telegram,
    ) {}

    public function download(InboundMessage $inboundMessage): array
    {
        if (data_get($inboundMessage->normalized_payload_json, 'media.download_strategy') === 'telegram_file') {
            return $this->downloadTelegramFile($inboundMessage);
        }

        $response = Http::timeout(20)->get($inboundMessage->media_url);
        $response->throw();

        return [
            'body' => $response->body(),
            'mime_type' => $this->mimeTypeFromPayload($inboundMessage) ?? $response->header('Content-Type') ?? 'application/octet-stream',
            'client_filename' => null,
        ];
    }

    private function downloadTelegramFile(InboundMessage $inboundMessage): array
    {
        $fileId = (string) data_get($inboundMessage->normalized_payload_json, 'media.file_id', '');

        if ($fileId === '') {
            throw new \RuntimeException('Missing Telegram file_id for inbound media download.');
        }

        $file = $this->telegram->getFile($fileId);
        $filePath = (string) data_get($file, 'result.file_path', '');

        if ($filePath === '') {
            throw new \RuntimeException('Telegram getFile response did not include file_path.');
        }

        $response = $this->telegram->downloadFile($filePath);
        $response->throw();

        $payload = $inboundMessage->normalized_payload_json ?? [];
        data_set($payload, 'media.file_path', $filePath);

        $inboundMessage->forceFill([
            'normalized_payload_json' => $payload,
        ])->save();

        return [
            'body' => $response->body(),
            'mime_type' => data_get($payload, 'media.mime_type') ?: $response->header('Content-Type') ?: 'application/octet-stream',
            'client_filename' => data_get($payload, 'media.file_name') ?: basename($filePath),
        ];
    }

    private function mimeTypeFromPayload(InboundMessage $inboundMessage): ?string
    {
        return $inboundMessage->normalized_payload_json['image']['mimeType']
            ?? $inboundMessage->normalized_payload_json['video']['mimeType']
            ?? $inboundMessage->normalized_payload_json['audio']['mimeType']
            ?? $inboundMessage->normalized_payload_json['document']['mimeType']
            ?? null;
    }
}
