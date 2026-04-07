<?php

namespace App\Modules\InboundMedia\Support;

final class TelegramUpdateInspector
{
    public function inspect(array $update): array
    {
        $updateType = $this->detectUpdateType($update);
        $payload = $this->extractUpdatePayload($update, $updateType);
        $chatId = $this->stringify($payload['chat']['id'] ?? null);
        $messageId = $updateType === 'message'
            ? $this->stringify($payload['message_id'] ?? null)
            : null;
        $messageType = $updateType === 'message'
            ? $this->detectMessageType($payload)
            : 'unknown';
        $selectedMedia = $this->selectMediaPayload($payload, $messageType);

        return [
            'update_type' => $updateType,
            'update_id' => $this->stringify($update['update_id'] ?? null),
            'update_key' => $this->stringify($update['update_id'] ?? null),
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'message_key' => $chatId !== null && $messageId !== null ? "{$chatId}:{$messageId}" : null,
            'sender_id' => $this->stringify($payload['from']['id'] ?? null),
            'sender_name' => $this->senderName($payload['from'] ?? null),
            'chat_type' => $payload['chat']['type'] ?? null,
            'message_thread_id' => $updateType === 'message' ? $this->stringify($payload['message_thread_id'] ?? null) : null,
            'media_group_id' => $updateType === 'message' ? $this->stringify($payload['media_group_id'] ?? null) : null,
            'occurred_at' => $this->occurredAt($payload['date'] ?? null),
            'message_type' => $messageType,
            'text' => $payload['text'] ?? null,
            'caption' => $payload['caption'] ?? null,
            'entities' => is_array($payload['entities'] ?? null) ? array_values($payload['entities']) : [],
            'caption_entities' => is_array($payload['caption_entities'] ?? null) ? array_values($payload['caption_entities']) : [],
            'file_id' => $this->stringify($selectedMedia['file_id'] ?? null),
            'file_unique_id' => $this->stringify($selectedMedia['file_unique_id'] ?? null),
            'file_name' => $selectedMedia['file_name'] ?? null,
            'mime_type' => $selectedMedia['mime_type'] ?? null,
            'file_size' => $selectedMedia['file_size'] ?? null,
            'width' => $selectedMedia['width'] ?? null,
            'height' => $selectedMedia['height'] ?? null,
            'duration' => $selectedMedia['duration'] ?? null,
            'my_chat_member_old_status' => $updateType === 'my_chat_member' ? $this->nullIfBlank(data_get($payload, 'old_chat_member.status')) : null,
            'my_chat_member_new_status' => $updateType === 'my_chat_member' ? $this->nullIfBlank(data_get($payload, 'new_chat_member.status')) : null,
        ];
    }

    public function detectMessageType(?array $message): string
    {
        if (! is_array($message)) {
            return 'unknown';
        }

        foreach (['photo', 'video', 'document', 'voice', 'audio', 'text'] as $type) {
            if (array_key_exists($type, $message)) {
                return $type;
            }
        }

        return 'unknown';
    }

    public function selectLargestPhoto(mixed $photos): ?array
    {
        if (! is_array($photos)) {
            return null;
        }

        $candidates = array_values(array_filter($photos, 'is_array'));

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (array $left, array $right): int {
            $leftScore = $this->photoScore($left);
            $rightScore = $this->photoScore($right);

            return $leftScore <=> $rightScore;
        });

        return end($candidates) ?: null;
    }

    private function detectUpdateType(array $update): string
    {
        foreach (['message', 'my_chat_member'] as $type) {
            if (is_array($update[$type] ?? null)) {
                return $type;
            }
        }

        return 'unknown';
    }

    private function extractUpdatePayload(array $update, string $updateType): ?array
    {
        $message = $update[$updateType] ?? null;

        return is_array($message) ? $message : null;
    }

    private function selectMediaPayload(?array $message, string $messageType): array
    {
        if (! is_array($message)) {
            return [];
        }

        if ($messageType === 'photo') {
            return $this->selectLargestPhoto($message['photo'] ?? null) ?? [];
        }

        $payload = $message[$messageType] ?? null;

        return is_array($payload) ? $payload : [];
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    private function senderName(mixed $from): ?string
    {
        if (! is_array($from)) {
            return null;
        }

        $name = trim(implode(' ', array_filter([
            $from['first_name'] ?? null,
            $from['last_name'] ?? null,
        ], fn ($part) => is_string($part) && trim($part) !== '')));

        if ($name !== '') {
            return $name;
        }

        $username = $from['username'] ?? null;

        return is_string($username) && trim($username) !== '' ? trim($username) : null;
    }

    private function occurredAt(mixed $date): ?string
    {
        if (! is_numeric($date)) {
            return null;
        }

        return \Illuminate\Support\Carbon::createFromTimestamp((int) $date)->toISOString();
    }

    private function photoScore(array $photo): int
    {
        $fileSize = (int) ($photo['file_size'] ?? 0);
        $area = ((int) ($photo['width'] ?? 0)) * ((int) ($photo['height'] ?? 0));

        return ($fileSize * 1000000000) + $area;
    }

    private function nullIfBlank(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
