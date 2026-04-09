<?php

namespace App\Modules\InboundMedia\Http\Controllers;

use App\Modules\Analytics\Services\AnalyticsTracker;
use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Jobs\GenerateMediaVariantsJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaToolingStatusService;
use App\Modules\MediaProcessing\Services\ModerationBroadcasterService;
use App\Modules\MediaProcessing\Services\VideoMetadataExtractorService;
use App\Modules\Wall\Models\EventWallSetting;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PublicUploadController extends BaseController
{
    private const MAX_FILES = 10;
    private const MAX_FILE_SIZE_KB = 20480;
    private const SINGLE_UPLOAD_MIMES = 'jpg,jpeg,png,gif,webp,heic,heif,mp4,mov';
    private const MULTI_UPLOAD_MIMES = 'jpg,jpeg,png,gif,webp,heic,heif';

    public function show(string $uploadSlug, Request $request, AnalyticsTracker $analytics): JsonResponse
    {
        $event = Event::with(['modules', 'faceSearchSettings', 'wallSettings'])
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
     * Does not require authentication and accepts one or multiple images,
     * plus a single short video when the public policy enables it.
     */
    public function upload(Request $request, string $uploadSlug, AnalyticsTracker $analytics): JsonResponse
    {
        $event = Event::with(['modules', 'faceSearchSettings', 'wallSettings'])
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
        $this->assertFilesRespectPublicPolicy($files, $event);
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
            'message' => $this->successMessageFor($createdMedia),
            'uploaded_count' => count($createdMedia),
            'media_ids' => array_map(static fn (EventMedia $media) => $media->id, $createdMedia),
            'moderation' => 'pending',
        ], 201);
    }

    private function payloadFor(Event $event): array
    {
        $availability = $this->availabilityFor($event);
        $acceptsVideo = $this->publicUploadVideoEnabled($event);
        $videoMaxDurationSeconds = $acceptsVideo ? $this->publicUploadVideoMaxDurationSeconds($event) : null;

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
                'accepts_video' => $acceptsVideo,
                'video_single_only' => $acceptsVideo && (bool) config('media_processing.public_upload.video_single_only', true),
                'video_max_duration_seconds' => $videoMaxDurationSeconds,
                'max_files' => self::MAX_FILES,
                'max_file_size_mb' => (int) floor(self::MAX_FILE_SIZE_KB / 1024),
                'accept_hint' => $acceptsVideo
                    ? (string) config('media_processing.public_upload.accept_hint', 'image/*,video/mp4,video/quicktime')
                    : 'image/*',
                'moderation_mode' => $event->moderation_mode?->value,
                'instructions' => $this->instructionsFor($event, $acceptsVideo, $videoMaxDurationSeconds),
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
        $acceptsVideo = $this->publicUploadVideoEnabled($event);
        $mediaLabel = $acceptsVideo ? 'fotos e videos' : 'imagens';
        $sendLabel = $acceptsVideo ? 'fotos ou 1 video curto' : 'uma ou mais fotos';

        if (! $event->isModuleEnabled('live')) {
            return [
                'enabled' => false,
                'status' => 'disabled',
                'reason' => 'live_module_disabled',
                'message' => "O recebimento de {$mediaLabel} ainda nao foi habilitado para este evento.",
            ];
        }

        if (! $event->isActive()) {
            return [
                'enabled' => false,
                'status' => 'inactive',
                'reason' => 'event_inactive',
                'message' => "O envio de {$mediaLabel} esta temporariamente indisponivel.",
            ];
        }

        return [
            'enabled' => true,
            'status' => 'available',
            'reason' => null,
            'message' => "Envie {$sendLabel} direto do seu celular.",
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
        $mediaType = str_starts_with((string) $file->getMimeType(), 'video') ? 'video' : 'image';
        $videoMetadata = [];

        try {
            if ($mediaType === 'video') {
                $videoMetadata = app(VideoMetadataExtractorService::class)->extractFromStoredAsset(
                    disk: 'public',
                    path: $path,
                    mimeType: $file->getMimeType(),
                );

                $this->assertVideoMetadataAllowed($videoMetadata, $event);
            }
        } catch (ValidationException $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        $media = EventMedia::create([
            'event_id' => $event->id,
            'media_type' => $mediaType,
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
            ...$videoMetadata,
        ]);

        app(ModerationBroadcasterService::class)->broadcastCreated(
            $media->fresh(['event', 'variants', 'inboundMessage']),
        );

        GenerateMediaVariantsJob::dispatch($media->id);

        return $media;
    }

    private function assertFilesRespectPublicPolicy(array $files, Event $event): void
    {
        $videoFiles = array_values(array_filter(
            $files,
            fn (UploadedFile $file): bool => str_starts_with((string) $file->getMimeType(), 'video/')
        ));

        if ($videoFiles === []) {
            return;
        }

        if (! $this->publicUploadVideoEnabled($event)) {
            throw ValidationException::withMessages([
                'file' => ['O envio de video ainda nao esta habilitado para este evento.'],
            ]);
        }

        if (count($files) > 1 || count($videoFiles) > 1) {
            throw ValidationException::withMessages([
                'file' => ['Envie apenas 1 video por vez pelo link publico.'],
            ]);
        }
    }

    /**
     * @param  array{
     *   width:int|null,
     *   height:int|null,
     *   duration_seconds:int|null,
     *   has_audio:bool|null,
     *   video_codec:string|null,
     *   audio_codec:string|null,
     *   bitrate:int|null,
     *   container:string|null
     * }  $videoMetadata
     */
    private function assertVideoMetadataAllowed(array $videoMetadata, Event $event): void
    {
        $durationSeconds = (int) ($videoMetadata['duration_seconds'] ?? 0);
        $width = (int) ($videoMetadata['width'] ?? 0);
        $height = (int) ($videoMetadata['height'] ?? 0);
        $maxDurationSeconds = $this->publicUploadVideoMaxDurationSeconds($event);

        if ($durationSeconds <= 0 || $width <= 0 || $height <= 0) {
            throw ValidationException::withMessages([
                'file' => ['Nao foi possivel validar a duracao e as dimensoes do video enviado.'],
            ]);
        }

        if ($durationSeconds > $maxDurationSeconds) {
            throw ValidationException::withMessages([
                'file' => ["Envie um video curto de ate {$maxDurationSeconds} segundos."],
            ]);
        }
    }

    /**
     * @param  array<int, EventMedia>  $createdMedia
     */
    private function successMessageFor(array $createdMedia): string
    {
        if (count($createdMedia) > 1) {
            return 'Imagens recebidas com sucesso!';
        }

        $firstMedia = $createdMedia[0] ?? null;

        if ($firstMedia?->media_type === 'video') {
            return 'Video recebido com sucesso!';
        }

        return 'Imagem recebida com sucesso!';
    }

    private function instructionsFor(Event $event, bool $acceptsVideo, ?int $videoMaxDurationSeconds): string
    {
        if ($acceptsVideo && $videoMaxDurationSeconds) {
            if ($event->isNoModeration()) {
                return "As fotos e os videos curtos de ate {$videoMaxDurationSeconds}s entram no ar automaticamente apos o processamento base.";
            }

            if ($event->isAiModeration()) {
                return "As fotos e os videos curtos de ate {$videoMaxDurationSeconds}s passam por moderacao por IA antes de aparecer no evento.";
            }

            return "As fotos e os videos curtos de ate {$videoMaxDurationSeconds}s passam por moderacao manual antes de aparecer no evento.";
        }

        if ($event->isNoModeration()) {
            return 'As fotos entram no ar automaticamente apos o processamento base.';
        }

        if ($event->isAiModeration()) {
            return 'As fotos passam por moderacao por IA antes de aparecer no evento.';
        }

        return 'As fotos enviadas passam por moderacao manual antes de aparecer no evento.';
    }

    private function publicUploadVideoEnabled(Event $event): bool
    {
        $settings = $this->resolveWallSettings($event);

        if (! $settings?->resolvedVideoEnabled()) {
            return false;
        }

        return $this->videoPipelineReady();
    }

    private function publicUploadVideoMaxDurationSeconds(Event $event): int
    {
        $settings = $this->resolveWallSettings($event);

        if ($settings) {
            return $settings->resolvedVideoMaxSeconds();
        }

        return max(1, (int) config('media_processing.public_upload.video_max_duration_seconds', 30));
    }

    private function videoPipelineReady(): bool
    {
        return (bool) (app(MediaToolingStatusService::class)->payload()['ready'] ?? false);
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

    private function resolveWallSettings(Event $event): ?EventWallSetting
    {
        if ($event->relationLoaded('wallSettings')) {
            return $event->getRelation('wallSettings');
        }

        return $event->wallSettings()->first();
    }
}
