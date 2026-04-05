<?php

namespace App\Modules\InboundMedia\Http\Controllers;

use App\Modules\Analytics\Services\AnalyticsTracker;
use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Jobs\GenerateMediaVariantsJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\ModerationBroadcasterService;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PublicUploadController extends BaseController
{
    private const MAX_FILES = 10;
    private const MAX_FILE_SIZE_KB = 20480;
    private const SINGLE_UPLOAD_MIMES = 'jpg,jpeg,png,gif,webp,heic,heif,mp4,mov';
    private const MULTI_UPLOAD_MIMES = 'jpg,jpeg,png,gif,webp,heic,heif';

    public function show(string $uploadSlug, Request $request, AnalyticsTracker $analytics): JsonResponse
    {
        $event = Event::with(['modules', 'faceSearchSettings'])
            ->where('upload_slug', $uploadSlug)
            ->firstOrFail();

        $analytics->trackEvent(
            $event,
            'upload.page_view',
            $request,
            ['surface' => 'upload'],
            channel: 'upload',
        );

        return $this->success($this->payloadFor($event));
    }

    /**
     * Public media upload via guest link / QR code.
     * Does not require authentication and accepts one or multiple images.
     */
    public function upload(Request $request, string $uploadSlug, AnalyticsTracker $analytics): JsonResponse
    {
        $event = Event::with(['modules', 'faceSearchSettings'])
            ->where('upload_slug', $uploadSlug)
            ->firstOrFail();

        $availability = $this->availabilityFor($event);

        if (! $availability['enabled']) {
            return $this->error(
                $availability['message'],
                422,
                ['event' => [$availability['reason']]]
            );
        }

        $request->validate([
            'file' => ['required_without:files', 'file', 'mimes:' . self::SINGLE_UPLOAD_MIMES, 'max:' . self::MAX_FILE_SIZE_KB],
            'files' => ['required_without:file', 'array', 'min:1', 'max:' . self::MAX_FILES],
            'files.*' => ['file', 'mimes:' . self::MULTI_UPLOAD_MIMES, 'max:' . self::MAX_FILE_SIZE_KB],
            'sender_name' => ['nullable', 'string', 'max:120'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $senderName = $request->input('sender_name', 'Convidado');
        $caption = $request->input('caption');
        $files = $this->extractFiles($request);
        $createdMedia = [];

        foreach ($files as $file) {
            $media = $this->storePublicUpload(
                event: $event,
                file: $file,
                senderName: $senderName,
                caption: $caption,
            );

            $analytics->trackEvent(
                $event,
                'upload.completed',
                $request,
                [
                    'surface' => 'upload',
                    'media_type' => $media->media_type,
                    'source_type' => $media->source_type,
                ],
                eventMediaId: $media->id,
                channel: 'upload',
            );

            $createdMedia[] = $media;
        }

        return $this->success([
            'message' => count($createdMedia) > 1
                ? 'Imagens recebidas com sucesso!'
                : 'Imagem recebida com sucesso!',
            'uploaded_count' => count($createdMedia),
            'media_ids' => array_map(static fn (EventMedia $media) => $media->id, $createdMedia),
            'moderation' => 'pending',
        ], 201);
    }

    private function payloadFor(Event $event): array
    {
        $availability = $this->availabilityFor($event);

        return [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'upload_slug' => $event->upload_slug,
                'cover_image_path' => $event->cover_image_path,
                'cover_image_url' => $this->publicAssetUrl($event->cover_image_path),
                'logo_path' => $event->logo_path,
                'logo_url' => $this->publicAssetUrl($event->logo_path),
                'primary_color' => $event->primary_color,
                'secondary_color' => $event->secondary_color,
                'starts_at' => $event->starts_at?->toISOString(),
                'location_name' => $event->location_name,
            ],
            'upload' => [
                'enabled' => $availability['enabled'],
                'status' => $availability['status'],
                'reason' => $availability['reason'],
                'message' => $availability['message'],
                'accepts_multiple' => true,
                'max_files' => self::MAX_FILES,
                'max_file_size_mb' => (int) floor(self::MAX_FILE_SIZE_KB / 1024),
                'accept_hint' => 'image/*',
                'moderation_mode' => $event->moderation_mode?->value,
                'instructions' => $event->isNoModeration()
                    ? 'As fotos entram no ar automaticamente apos o processamento base.'
                    : ($event->isAiModeration()
                        ? 'As fotos passam por moderacao por IA antes de aparecer no evento.'
                        : 'As fotos enviadas passam por moderacao manual antes de aparecer no evento.'),
            ],
            'links' => [
                'upload_url' => $event->publicUploadUrl(),
                'upload_api_url' => $event->publicUploadApiUrl(),
                'hub_url' => $event->publicHubUrl(),
            ],
        ];
    }

    private function availabilityFor(Event $event): array
    {
        if (! $event->isModuleEnabled('live')) {
            return [
                'enabled' => false,
                'status' => 'disabled',
                'reason' => 'live_module_disabled',
                'message' => 'O recebimento de imagens ainda nao foi habilitado para este evento.',
            ];
        }

        if (! $event->isActive()) {
            return [
                'enabled' => false,
                'status' => 'inactive',
                'reason' => 'event_inactive',
                'message' => 'O envio de imagens esta temporariamente indisponivel.',
            ];
        }

        return [
            'enabled' => true,
            'status' => 'available',
            'reason' => null,
            'message' => 'Envie uma ou mais fotos direto do seu celular.',
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function extractFiles(Request $request): array
    {
        $files = [];

        if ($request->hasFile('file')) {
            $files[] = $request->file('file');
        }

        $multiple = $request->file('files', []);

        if (is_array($multiple)) {
            foreach ($multiple as $file) {
                if ($file instanceof UploadedFile) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    private function storePublicUpload(Event $event, UploadedFile $file, string $senderName, ?string $caption): EventMedia
    {
        $path = $file->store("events/{$event->id}/originals", 'public');

        $media = EventMedia::create([
            'event_id' => $event->id,
            'media_type' => str_starts_with((string) $file->getMimeType(), 'video') ? 'video' : 'image',
            'source_type' => 'public_upload',
            'source_label' => $senderName,
            'caption' => $caption,
            'original_filename' => $file->getClientOriginalName(),
            'original_disk' => 'public',
            'original_path' => $path,
            'client_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'processing_status' => 'received',
            'moderation_status' => 'pending',
            'publication_status' => 'draft',
            'safety_status' => 'queued',
            'face_index_status' => $this->shouldQueueFaceIndex($event, $file) ? 'queued' : 'skipped',
            'vlm_status' => 'queued',
            'pipeline_version' => 'media_ai_foundation_v1',
        ]);

        app(ModerationBroadcasterService::class)->broadcastCreated(
            $media->fresh(['event', 'variants', 'inboundMessage']),
        );

        GenerateMediaVariantsJob::dispatch($media->id);

        return $media;
    }

    private function publicAssetUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        return url(Storage::disk('public')->url($path));
    }

    private function shouldQueueFaceIndex(Event $event, UploadedFile $file): bool
    {
        return str_starts_with((string) $file->getMimeType(), 'image/')
            && $event->isFaceSearchEnabled();
    }
}
