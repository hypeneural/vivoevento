<?php

namespace App\Modules\MediaProcessing\Services;

use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\DB;

class ModerationSearchDocumentBuilder
{
    public function buildForMedia(EventMedia $media): ?string
    {
        $eventTitle = $media->relationLoaded('event')
            ? $media->event?->title
            : $this->eventTitle((int) $media->event_id);

        $sender = $media->relationLoaded('inboundMessage')
            ? [
                'sender_name' => $media->inboundMessage?->sender_name,
                'sender_phone' => $media->inboundMessage?->sender_phone,
                'sender_lid' => $media->inboundMessage?->sender_lid,
                'sender_external_id' => $media->inboundMessage?->sender_external_id,
            ]
            : $this->senderFields($media->inbound_message_id ? (int) $media->inbound_message_id : null);

        return $this->buildFromParts([
            $media->caption,
            $media->title,
            $media->source_label,
            $media->original_filename,
            $media->client_filename,
            $eventTitle,
            $sender['sender_name'] ?? null,
            $sender['sender_phone'] ?? null,
            $sender['sender_lid'] ?? null,
            $sender['sender_external_id'] ?? null,
        ]);
    }

    public function buildFromParts(array $parts): ?string
    {
        $document = collect($parts)
            ->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '')
            ->map(fn ($value) => trim(preg_replace('/\s+/', ' ', (string) $value) ?: ''))
            ->implode(' ');

        return $document === '' ? null : $document;
    }

    private function eventTitle(int $eventId): ?string
    {
        $title = DB::table('events')->where('id', $eventId)->value('title');

        return is_string($title) ? $title : null;
    }

    private function senderFields(?int $inboundMessageId): array
    {
        if (! $inboundMessageId) {
            return [];
        }

        $row = DB::table('inbound_messages')
            ->where('id', $inboundMessageId)
            ->first(['sender_name', 'sender_phone', 'sender_lid', 'sender_external_id']);

        return $row ? (array) $row : [];
    }
}
