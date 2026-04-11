#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/eventovivo/current/apps/api"
WALL_CODE=""
SENDER_PHONE=""
EVENT_ID=""
LOOKBACK_HOURS="6"
LIMIT="20"
BOOT_BASE_URL="https://api.eventovivo.com.br"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --app-dir)
      APP_DIR="$2"
      shift 2
      ;;
    --wall-code)
      WALL_CODE="$2"
      shift 2
      ;;
    --sender-phone)
      SENDER_PHONE="$2"
      shift 2
      ;;
    --event-id)
      EVENT_ID="$2"
      shift 2
      ;;
    --lookback-hours)
      LOOKBACK_HOURS="$2"
      shift 2
      ;;
    --limit)
      LIMIT="$2"
      shift 2
      ;;
    --boot-base-url)
      BOOT_BASE_URL="$2"
      shift 2
      ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 1
      ;;
  esac
done

if [[ -z "$WALL_CODE" ]]; then
  echo "--wall-code is required" >&2
  exit 1
fi

cd "$APP_DIR"

export VALIDATION_WALL_CODE="$WALL_CODE"
export VALIDATION_SENDER_PHONE="$SENDER_PHONE"
export VALIDATION_EVENT_ID="$EVENT_ID"
export VALIDATION_LOOKBACK_HOURS="$LOOKBACK_HOURS"
export VALIDATION_LIMIT="$LIMIT"
export VALIDATION_BOOT_BASE_URL="$BOOT_BASE_URL"

php <<'PHP'
<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$wallCode = (string) getenv('VALIDATION_WALL_CODE');
$senderPhone = trim((string) getenv('VALIDATION_SENDER_PHONE'));
$eventIdArg = trim((string) getenv('VALIDATION_EVENT_ID'));
$lookbackHours = max(1, (int) getenv('VALIDATION_LOOKBACK_HOURS'));
$limit = max(1, (int) getenv('VALIDATION_LIMIT'));
$bootBaseUrl = rtrim((string) getenv('VALIDATION_BOOT_BASE_URL'), '/');
$lookback = Carbon::now()->subHours($lookbackHours);

$wall = DB::table('event_wall_settings')
    ->where('wall_code', $wallCode)
    ->first();

if (! $wall) {
    fwrite(STDERR, "Wall code not found: {$wallCode}\n");
    exit(2);
}

$eventId = $eventIdArg !== '' ? (int) $eventIdArg : (int) $wall->event_id;

$event = DB::table('events')
    ->where('id', $eventId)
    ->first();

$messageQuery = DB::table('whatsapp_messages')
    ->where('created_at', '>=', $lookback)
    ->orderByDesc('id');

if ($senderPhone !== '') {
    $messageQuery->where(function ($query) use ($senderPhone) {
        $query->where('sender_phone', $senderPhone)
            ->orWhere('recipient_phone', $senderPhone);
    });
}

$messages = $messageQuery
    ->limit($limit)
    ->get([
        'id',
        'instance_id',
        'direction',
        'type',
        'status',
        'provider_message_id',
        'reply_to_provider_message_id',
        'sender_phone',
        'recipient_phone',
        'mime_type',
        'media_url',
        'created_at',
    ]);

$providerMessageIds = $messages
    ->pluck('provider_message_id')
    ->filter()
    ->values()
    ->all();

$inboundEventsQuery = DB::table('whatsapp_inbound_events')
    ->where('created_at', '>=', $lookback)
    ->orderByDesc('id');

if ($providerMessageIds !== []) {
    $inboundEventsQuery->whereIn('provider_message_id', $providerMessageIds);
}

$inboundEvents = $inboundEventsQuery
    ->limit($limit)
    ->get([
        'id',
        'instance_id',
        'provider_message_id',
        'event_type',
        'processing_status',
        'error_message',
        'created_at',
    ]);

$inboundMessageQuery = DB::table('inbound_messages')
    ->where('event_id', $eventId)
    ->where('created_at', '>=', $lookback)
    ->orderByDesc('id');

if ($senderPhone !== '') {
    $inboundMessageQuery->where('sender_phone', $senderPhone);
}

$inboundMessages = $inboundMessageQuery
    ->limit($limit)
    ->get([
        'id',
        'event_channel_id',
        'provider',
        'message_id',
        'message_type',
        'sender_phone',
        'media_url',
        'mime_type',
        'capture_target',
        'status',
        'stored_disk',
        'stored_path',
        'reference_message_id',
        'received_at',
        'created_at',
    ]);

$inboundMessageIds = $inboundMessages->pluck('id')->all();

$eventMedia = DB::table('event_media')
    ->leftJoin('inbound_messages', 'inbound_messages.id', '=', 'event_media.inbound_message_id')
    ->where('event_media.event_id', $eventId)
    ->where('event_media.created_at', '>=', $lookback)
    ->when($senderPhone !== '', function ($query) use ($senderPhone) {
        $query->where('inbound_messages.sender_phone', $senderPhone);
    })
    ->orderByDesc('event_media.id')
    ->limit($limit)
    ->get([
        'event_media.id',
        'event_media.inbound_message_id',
        'event_media.media_type',
        'event_media.processing_status',
        'event_media.moderation_status',
        'event_media.publication_status',
        'event_media.duration_seconds',
        'event_media.width',
        'event_media.height',
        'event_media.has_audio',
        'event_media.video_codec',
        'event_media.audio_codec',
        'event_media.container',
        'event_media.original_disk',
        'event_media.original_path',
        'event_media.published_at',
        'event_media.created_at',
        'inbound_messages.sender_phone as inbound_sender_phone',
        'inbound_messages.reference_message_id as inbound_reference_message_id',
    ]);

$eventMediaIds = $eventMedia->pluck('id')->all();

$variants = [];
if ($eventMediaIds !== []) {
    $variants = DB::table('event_media_variants')
        ->whereIn('event_media_id', $eventMediaIds)
        ->orderBy('event_media_id')
        ->orderBy('variant_key')
        ->get([
            'id',
            'event_media_id',
            'variant_key',
            'disk',
            'path',
            'width',
            'height',
            'size_bytes',
            'mime_type',
            'created_at',
        ]);
}

$runtimeStatuses = DB::table('wall_player_runtime_statuses')
    ->where('event_wall_setting_id', $wall->id)
    ->orderByDesc('last_heartbeat_at')
    ->limit(5)
    ->get([
        'id',
        'player_instance_id',
        'runtime_status',
        'connection_status',
        'current_item_id',
        'current_media_type',
        'current_video_phase',
        'current_video_exit_reason',
        'current_video_failure_reason',
        'current_video_position_seconds',
        'current_video_duration_seconds',
        'current_video_ready_state',
        'current_video_stall_count',
        'current_video_poster_visible',
        'hardware_concurrency',
        'device_memory_gb',
        'network_effective_type',
        'network_downlink_mbps',
        'network_rtt_ms',
        'last_heartbeat_at',
    ]);

$bootUrl = "{$bootBaseUrl}/api/v1/public/wall/{$wallCode}/boot";
$bootJson = @file_get_contents($bootUrl);
$bootPayload = null;
$bootError = null;

if ($bootJson === false) {
    $bootError = error_get_last()['message'] ?? 'failed to fetch boot payload';
} else {
    $bootPayload = json_decode($bootJson, true);
    if (! is_array($bootPayload)) {
        $bootError = 'boot payload is not valid JSON';
    }
}

$bootSummary = null;
if (is_array($bootPayload)) {
    $files = collect($bootPayload['files'] ?? []);
    $bootSummary = [
        'url' => $bootUrl,
        'status' => $bootPayload['status'] ?? null,
        'files_count' => $files->count(),
        'video_files_count' => $files->where('type', 'video')->count(),
        'current_video_candidates' => $files
            ->where('type', 'video')
            ->values()
            ->take(5)
            ->map(fn (array $item) => [
                'id' => $item['id'] ?? null,
                'type' => $item['type'] ?? null,
                'url' => $item['url'] ?? null,
                'served_variant_key' => $item['served_variant_key'] ?? null,
                'preview_variant_key' => $item['preview_variant_key'] ?? null,
                'duration_seconds' => $item['duration_seconds'] ?? null,
                'video_admission' => $item['video_admission'] ?? null,
                'created_at' => $item['created_at'] ?? null,
            ])
            ->all(),
        'settings' => [
            'video_enabled' => data_get($bootPayload, 'settings.video_enabled'),
            'public_upload_video_enabled' => data_get($bootPayload, 'settings.public_upload_video_enabled'),
            'private_inbound_video_enabled' => data_get($bootPayload, 'settings.private_inbound_video_enabled'),
            'video_playback_mode' => data_get($bootPayload, 'settings.video_playback_mode'),
            'video_max_seconds' => data_get($bootPayload, 'settings.video_max_seconds'),
            'video_preferred_variant' => data_get($bootPayload, 'settings.video_preferred_variant'),
            'video_multi_layout_policy' => data_get($bootPayload, 'settings.video_multi_layout_policy'),
            'layout' => data_get($bootPayload, 'settings.layout'),
        ],
    ];
}

$variantMatrix = collect($variants)
    ->groupBy('event_media_id')
    ->map(fn ($rows) => $rows->pluck('variant_key')->values()->all());

$summary = [
    'collected_at' => Carbon::now()->toIso8601String(),
    'lookback_hours' => $lookbackHours,
    'wall' => [
        'id' => $wall->id,
        'wall_code' => $wall->wall_code,
        'status' => $wall->status,
        'is_enabled' => (bool) $wall->is_enabled,
        'event_id' => $wall->event_id,
        'layout' => $wall->layout,
        'video_enabled' => $wall->video_enabled,
        'public_upload_video_enabled' => $wall->public_upload_video_enabled,
        'private_inbound_video_enabled' => $wall->private_inbound_video_enabled,
        'video_playback_mode' => $wall->video_playback_mode,
        'video_max_seconds' => $wall->video_max_seconds,
        'video_preferred_variant' => $wall->video_preferred_variant,
    ],
    'event' => $event ? [
        'id' => $event->id,
        'title' => $event->title ?? null,
        'status' => $event->status ?? null,
    ] : null,
    'counts' => [
        'whatsapp_inbound_events' => $inboundEvents->count(),
        'whatsapp_messages' => $messages->count(),
        'inbound_messages' => $inboundMessages->count(),
        'event_media' => $eventMedia->count(),
        'event_media_variants' => is_array($variants) ? count($variants) : $variants->count(),
        'wall_player_runtime_statuses' => $runtimeStatuses->count(),
        'published_approved_videos' => collect($eventMedia)
            ->where('media_type', 'video')
            ->where('moderation_status', 'approved')
            ->where('publication_status', 'published')
            ->count(),
    ],
    'recent_whatsapp_inbound_events' => $inboundEvents,
    'recent_whatsapp_messages' => $messages,
    'recent_inbound_messages' => $inboundMessages,
    'recent_event_media' => collect($eventMedia)->map(function ($row) use ($variantMatrix) {
        $row->variant_keys = $variantMatrix[$row->id] ?? [];
        return $row;
    })->values(),
    'recent_event_media_variants' => $variants,
    'recent_wall_player_runtime_statuses' => $runtimeStatuses,
    'boot_summary' => $bootSummary,
    'boot_error' => $bootError,
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
PHP
