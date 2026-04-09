<?php

declare(strict_types=1);

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Actions\IndexMediaFacesAction;
use App\Modules\FaceSearch\Actions\SearchFacesBySelfieAction;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\FaceSearchProviderRecord;
use App\Modules\FaceSearch\Models\FaceSearchQuery;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use App\Modules\FaceSearch\Services\SelfiePreflightService;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Laravel\Facades\Image;

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

/**
 * @return string|null
 */
function pilotOptionValue(string $name): ?string
{
    global $argv;

    foreach ((array) $argv as $argument) {
        if (! is_string($argument)) {
            continue;
        }

        if (str_starts_with($argument, "--{$name}=")) {
            $value = substr($argument, strlen($name) + 3);

            return $value !== '' ? $value : null;
        }
    }

    return null;
}

/**
 * @return string|null
 */
function pilotEnvValue(string $key): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    return is_string($value) && $value !== '' ? $value : null;
}

/**
 * @param array<int, string> $paths
 * @return array<int, string>
 */
function shuffledPaths(array $paths, int $seed): array
{
    mt_srand($seed);
    shuffle($paths);

    return array_values($paths);
}

/**
 * @return array{width:int,height:int}
 */
function imageDimensions(string $path): array
{
    $dimensions = getimagesize($path);

    if (! is_array($dimensions) || ! isset($dimensions[0], $dimensions[1])) {
        throw new RuntimeException("Nao foi possivel ler as dimensoes da imagem: {$path}");
    }

    return [
        'width' => (int) $dimensions[0],
        'height' => (int) $dimensions[1],
    ];
}

function createUploadedFile(string $path): UploadedFile
{
    return new UploadedFile(
        path: $path,
        originalName: basename($path),
        test: true,
    );
}

/**
 * @return array<int, DetectedFaceData>
 */
function detectPilotFaces(Event $event, EventFaceSearchSetting $settings, string $binary): array
{
    $probeMedia = new EventMedia([
        'event_id' => $event->id,
        'media_type' => 'image',
        'source_type' => 'pilot_h7_t2_probe',
    ]);

    /** @var FaceDetectionProviderInterface $detector */
    $detector = app(FaceDetectionProviderInterface::class);

    return array_values($detector->detect($probeMedia, $settings, $binary));
}

function primaryPilotFace(array $faces): ?DetectedFaceData
{
    if ($faces === []) {
        return null;
    }

    usort($faces, static function (DetectedFaceData $left, DetectedFaceData $right): int {
        $leftPrimary = $left->isPrimaryCandidate ? 1 : 0;
        $rightPrimary = $right->isPrimaryCandidate ? 1 : 0;

        if ($leftPrimary !== $rightPrimary) {
            return $rightPrimary <=> $leftPrimary;
        }

        $leftArea = $left->boundingBox->area();
        $rightArea = $right->boundingBox->area();

        if ($leftArea !== $rightArea) {
            return $rightArea <=> $leftArea;
        }

        return $right->qualityScore <=> $left->qualityScore;
    });

    return $faces[0] ?? null;
}

/**
 * @return array{x:int,y:int,width:int,height:int}
 */
function pilotCropBox(DetectedFaceData $face, int $imageWidth, int $imageHeight, float $scaleFactor): array
{
    $targetWidth = max(
        $face->boundingBox->width,
        (int) round($face->boundingBox->width * $scaleFactor),
    );
    $targetHeight = max(
        $face->boundingBox->height,
        (int) round($face->boundingBox->height * $scaleFactor),
    );

    $centerX = $face->boundingBox->x + ($face->boundingBox->width / 2);
    $centerY = $face->boundingBox->y + ($face->boundingBox->height / 2);

    $x = (int) round($centerX - ($targetWidth / 2));
    $y = (int) round($centerY - ($targetHeight / 2));

    $x = max(0, min($x, max(0, $imageWidth - $targetWidth)));
    $y = max(0, min($y, max(0, $imageHeight - $targetHeight)));

    $targetWidth = min($targetWidth, $imageWidth);
    $targetHeight = min($targetHeight, $imageHeight);

    return [
        'x' => $x,
        'y' => $y,
        'width' => $targetWidth,
        'height' => $targetHeight,
    ];
}

/**
 * @return array{path:string,source_file:string,derived_from:string,scale_factor:float}|null
 */
function buildEligibleSelfieProbe(
    Event $event,
    EventFaceSearchSetting $settings,
    string $sourcePath,
    string $probePrefix,
): ?array {
    $binary = file_get_contents($sourcePath);

    if (! is_string($binary) || $binary === '') {
        return null;
    }

    $faces = detectPilotFaces($event, $settings, $binary);
    $primaryFace = primaryPilotFace($faces);

    if (! $primaryFace instanceof DetectedFaceData) {
        return null;
    }

    $image = Image::decode($binary);
    $imageWidth = $image->width();
    $imageHeight = $image->height();
    /** @var SelfiePreflightService $preflight */
    $preflight = app(SelfiePreflightService::class);

    foreach ([2.0, 1.8, 1.6, 2.2] as $scaleFactor) {
        $crop = pilotCropBox($primaryFace, $imageWidth, $imageHeight, $scaleFactor);

        $croppedBinary = (string) Image::decode($binary)
            ->crop($crop['width'], $crop['height'], $crop['x'], $crop['y'])
            ->encodeUsingMediaType('image/jpeg', 90);

        try {
            $preflight->validateForSearch($event, $settings, $croppedBinary, false);
        } catch (ValidationException) {
            continue;
        }

        $probePath = "face-search-aws-pilot/probes/{$probePrefix}-" . basename($sourcePath);
        Storage::disk('local')->put($probePath, $croppedBinary);

        return [
            'path' => Storage::disk('local')->path($probePath),
            'source_file' => basename($sourcePath),
            'derived_from' => basename($sourcePath),
            'scale_factor' => $scaleFactor,
        ];
    }

    return null;
}

/**
 * @return array{media:EventMedia, stored_path:string}
 */
function createEventMediaFromSource(Event $event, string $sourcePath, int $slot): array
{
    $binary = file_get_contents($sourcePath);

    if (! is_string($binary) || $binary === '') {
        throw new RuntimeException("Nao foi possivel ler a imagem fonte: {$sourcePath}");
    }

    /** @var \App\Modules\FaceSearch\Services\AwsImagePreprocessor $preprocessor */
    $preprocessor = app(\App\Modules\FaceSearch\Services\AwsImagePreprocessor::class);
    $galleryVariant = $preprocessor->prepare($binary, [
        'max_dimension' => 1920,
        'max_bytes' => 5_242_880,
    ]);

    $filename = basename($sourcePath);
    $mimeType = (string) ($galleryVariant['mime_type'] ?? (mime_content_type($sourcePath) ?: 'image/jpeg'));
    $sizeBytes = (int) ($galleryVariant['size_bytes'] ?? filesize($sourcePath));
    $dimensions = [
        'width' => (int) ($galleryVariant['width'] ?? imageDimensions($sourcePath)['width']),
        'height' => (int) ($galleryVariant['height'] ?? imageDimensions($sourcePath)['height']),
    ];
    $storedPath = "events/{$event->id}/variants/h7-pilot-{$slot}/{$filename}";

    Storage::disk('public')->put($storedPath, (string) $galleryVariant['binary']);

    $media = EventMedia::query()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
        'source_type' => 'pilot_h7_t2',
        'source_label' => 'AWS H7 pilot',
        'original_filename' => $filename,
        'client_filename' => $filename,
        'mime_type' => $mimeType,
        'size_bytes' => $sizeBytes,
        'width' => $dimensions['width'],
        'height' => $dimensions['height'],
        'processing_status' => 'received',
        'moderation_status' => 'approved',
        'publication_status' => 'published',
        'pipeline_version' => 'media_pipeline_v1',
        'published_at' => now(),
        'face_index_status' => 'queued',
        'original_disk' => 'public',
        'original_path' => $storedPath,
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'gallery',
        'disk' => 'public',
        'path' => $storedPath,
        'width' => $dimensions['width'],
        'height' => $dimensions['height'],
        'size_bytes' => $sizeBytes,
        'mime_type' => $mimeType,
    ]);

    return [
        'media' => $media->fresh(['event.faceSearchSettings', 'variants']),
        'stored_path' => $storedPath,
    ];
}

/**
 * @return array<string, mixed>
 */
function latestQueryAuditForRequest(int $requestId): array
{
    $query = FaceSearchQuery::query()
        ->where('event_face_search_request_id', $requestId)
        ->latest('id')
        ->first();

    if (! $query) {
        return [
            'query_id' => null,
            'status' => 'missing',
            'result_count' => 0,
            'backend_key' => null,
            'fallback_triggered' => false,
            'response_duration_ms' => null,
            'shadow_status' => null,
            'shadow_divergence_ratio' => null,
            'shadow_top_match_same' => null,
            'error_code' => null,
            'error_message' => null,
        ];
    }

    $payload = is_array($query->provider_payload_json) ? $query->provider_payload_json : [];

    return [
        'query_id' => $query->id,
        'status' => $query->status?->value ?? (string) $query->status,
        'result_count' => $query->result_count,
        'backend_key' => $query->backend_key,
        'fallback_triggered' => (bool) ($payload['fallback_triggered'] ?? false),
        'response_duration_ms' => isset($payload['response_duration_ms']) ? (int) $payload['response_duration_ms'] : null,
        'shadow_status' => is_array($payload['shadow'] ?? null) ? ($payload['shadow']['status'] ?? null) : null,
        'shadow_divergence_ratio' => is_array($payload['shadow'] ?? null)
            ? ($payload['shadow']['comparison']['divergence_ratio'] ?? null)
            : null,
        'shadow_top_match_same' => is_array($payload['shadow'] ?? null)
            ? ($payload['shadow']['comparison']['top_match_same'] ?? null)
            : null,
        'error_code' => $query->error_code,
        'error_message' => $query->error_message,
    ];
}

/**
 * @param array<int, array<string, mixed>> $queries
 * @return array<string, mixed>
 */
function aggregateEventMetrics(
    int $eventId,
    int $indexedMediaCount,
    int $validationBlockedCount,
    array $queries,
    array $reconcileResult,
    float $groupOnePricePerImage,
    float $storagePerFaceVectorMonth,
): array {
    $eventQueries = FaceSearchQuery::query()
        ->where('event_id', $eventId)
        ->get();

    $providerRecords = FaceSearchProviderRecord::query()
        ->where('event_id', $eventId)
        ->where('backend_key', 'aws_rekognition')
        ->get();

    $unindexedFaces = $providerRecords->filter(function (FaceSearchProviderRecord $record): bool {
        $reasons = is_array($record->unindexed_reasons_json) ? $record->unindexed_reasons_json : [];

        return $record->face_id === null && $reasons !== [];
    });

    $searchableRecords = $providerRecords->filter(
        fn (FaceSearchProviderRecord $record): bool => $record->searchable && is_string($record->face_id) && $record->face_id !== ''
    );

    $fallbackCount = $eventQueries->filter(function (FaceSearchQuery $query): bool {
        $payload = is_array($query->provider_payload_json) ? $query->provider_payload_json : [];

        return (bool) ($payload['fallback_triggered'] ?? false);
    })->count();

    $durations = $eventQueries
        ->map(function (FaceSearchQuery $query): ?int {
            $payload = is_array($query->provider_payload_json) ? $query->provider_payload_json : [];
            $duration = $payload['response_duration_ms'] ?? null;

            return is_numeric($duration) ? (int) $duration : null;
        })
        ->filter(fn (?int $duration): bool => $duration !== null)
        ->values();

    $shadowComparisons = $eventQueries
        ->map(function (FaceSearchQuery $query): ?array {
            $payload = is_array($query->provider_payload_json) ? $query->provider_payload_json : [];
            $shadow = $payload['shadow'] ?? null;

            return is_array($shadow) && ($shadow['status'] ?? null) === 'completed'
                ? (array) ($shadow['comparison'] ?? [])
                : null;
        })
        ->filter()
        ->values();

    $divergenceRatios = $shadowComparisons
        ->map(fn (array $comparison): ?float => is_numeric($comparison['divergence_ratio'] ?? null) ? (float) $comparison['divergence_ratio'] : null)
        ->filter(fn (?float $value): bool => $value !== null)
        ->values();

    $topMatchSameCount = $shadowComparisons
        ->filter(fn (array $comparison): bool => ($comparison['top_match_same'] ?? false) === true)
        ->count();

    $processingImages = $indexedMediaCount + $eventQueries->count();
    $processingEstimate = round($processingImages * $groupOnePricePerImage, 6);
    $storageEstimate = round(((int) ($reconcileResult['remote_face_count'] ?? 0)) * $storagePerFaceVectorMonth, 6);

    return [
        'queries_attempted' => $eventQueries->count(),
        'queries_blocked_by_preflight' => $validationBlockedCount,
        'fallback_count' => $fallbackCount,
        'fallback_rate' => $eventQueries->count() > 0
            ? round($fallbackCount / $eventQueries->count(), 6)
            : 0.0,
        'unindexed_faces' => $unindexedFaces->count(),
        'searchable_remote_faces' => $searchableRecords->count(),
        'shadow_completed_queries' => $shadowComparisons->count(),
        'shadow_divergence_avg' => $divergenceRatios->count() > 0
            ? round((float) $divergenceRatios->avg(), 6)
            : null,
        'shadow_top_match_same_rate' => $shadowComparisons->count() > 0
            ? round($topMatchSameCount / $shadowComparisons->count(), 6)
            : null,
        'response_duration_ms_avg' => $durations->count() > 0
            ? round((float) $durations->avg(), 2)
            : null,
        'response_duration_ms_p95' => $durations->count() > 0
            ? (int) $durations->sort()->values()->get((int) floor(($durations->count() - 1) * 0.95))
            : null,
        'estimated_group1_processing_images' => $processingImages,
        'estimated_processing_cost_usd' => $processingEstimate,
        'estimated_storage_cost_usd_monthly_if_kept' => $storageEstimate,
        'reconcile' => $reconcileResult,
        'queries' => $queries,
    ];
}

$sourceRoot = pilotOptionValue('source-root')
    ?? pilotEnvValue('FACE_SEARCH_AWS_PILOT_SOURCE_ROOT')
    ?? 'C:\Users\Usuario\Desktop\vipsocial';
$seed = (int) (pilotOptionValue('seed') ?? pilotEnvValue('FACE_SEARCH_AWS_PILOT_SEED') ?? '20260409');
$eventCount = min(3, max(1, (int) (pilotOptionValue('event-count') ?? pilotEnvValue('FACE_SEARCH_AWS_PILOT_EVENT_COUNT') ?? '3')));
$cleanup = (pilotOptionValue('cleanup') ?? pilotEnvValue('FACE_SEARCH_AWS_PILOT_CLEANUP') ?? '1') !== '0';
$groupOnePricePerImageUsd = 0.0010;
$storagePerFaceVectorMonthUsd = 0.00001;

$singleCandidates = [
    $sourceRoot . '\55159264527_7f683b08f6_o.jpg',
    $sourceRoot . '\55160538970_ce79eb2997_o.jpg',
    $sourceRoot . '\55160539765_1e28e9e04d_o.jpg',
];
$multiCandidates = [
    $sourceRoot . '\55160322273_dc03572778_o.jpg',
    $sourceRoot . '\55160408924_b566098f08_o.jpg',
    $sourceRoot . '\55160539330_600b6f30cc_o.jpg',
    $sourceRoot . '\55160539780_ffeb73e159_o.jpg',
    $sourceRoot . '\derivatives\55160408924_b566098f08_o-smoke.jpg',
    $sourceRoot . '\derivatives\55160539330_600b6f30cc_o-smoke.jpg',
];

foreach (array_merge($singleCandidates, $multiCandidates) as $candidate) {
    if (! is_file($candidate)) {
        fwrite(STDERR, "Arquivo piloto ausente: {$candidate}\n");
        exit(11);
    }
}

$singleCandidates = shuffledPaths($singleCandidates, $seed);
$multiCandidates = shuffledPaths($multiCandidates, $seed + 99);
$eventCount = min($eventCount, count($singleCandidates), intdiv(count($multiCandidates), 2));

if ($eventCount < 1) {
    fwrite(STDERR, "Nao ha imagens suficientes para montar ao menos um evento piloto.\n");
    exit(12);
}

/** @var AwsRekognitionFaceSearchBackend $awsBackend */
$awsBackend = app(AwsRekognitionFaceSearchBackend::class);
/** @var IndexMediaFacesAction $indexAction */
$indexAction = app(IndexMediaFacesAction::class);
/** @var SearchFacesBySelfieAction $searchAction */
$searchAction = app(SearchFacesBySelfieAction::class);

$report = [
    'executed_at' => now()->toIso8601String(),
    'source_root' => $sourceRoot,
    'seed' => $seed,
    'event_count' => $eventCount,
    'cleanup_enabled' => $cleanup,
    'aws_pricing_reference' => [
        'url' => 'https://aws.amazon.com/pt/rekognition/pricing/',
        'group1_price_per_image_usd_first_million' => $groupOnePricePerImageUsd,
        'face_vector_storage_usd_per_vector_month' => $storagePerFaceVectorMonthUsd,
        'note' => 'Estimativa baseada na primeira faixa de preco das APIs de imagem do Grupo 1 e no armazenamento mensal por vetor facial da pagina oficial.',
    ],
    'events' => [],
];

for ($index = 0; $index < $eventCount; $index++) {
    $singlePath = $singleCandidates[$index];
    $eventMultis = [
        $multiCandidates[$index * 2],
        $multiCandidates[($index * 2) + 1],
    ];
    $eventSources = [$singlePath, ...$eventMultis];
    $sourcePathsByFilename = collect($eventSources)
        ->mapWithKeys(static fn (string $path): array => [basename($path) => $path])
        ->all();

    $event = Event::factory()->active()->create([
        'title' => sprintf('[H7 pilot] AWS shadow %d seed %d', $index + 1, $seed),
    ]);

    $settings = EventFaceSearchSetting::query()->create(array_merge(
        ['event_id' => $event->id],
        EventFaceSearchSetting::defaultAttributes(),
        [
            'enabled' => true,
            'provider_key' => 'compreface',
            'embedding_model_key' => (string) data_get(config('face_search.providers.compreface'), 'model', 'compreface-face-v1'),
            'vector_store_key' => 'pgvector',
            'recognition_enabled' => true,
            'search_backend_key' => 'aws_rekognition',
            'fallback_backend_key' => 'local_pgvector',
            'routing_policy' => 'aws_primary_local_shadow',
            'shadow_mode_percentage' => 100,
            'aws_region' => (string) config('face_search.providers.aws_rekognition.region', 'eu-central-1'),
            'aws_index_quality_filter' => 'AUTO',
            'aws_search_faces_quality_filter' => 'NONE',
            'aws_max_faces_per_image' => 100,
            'top_k' => 10,
        ],
    ));

        $eventReport = [
            'event_id' => $event->id,
            'event_title' => $event->title,
        'settings' => [
            'routing_policy' => $settings->routing_policy,
            'search_backend_key' => $settings->search_backend_key,
            'fallback_backend_key' => $settings->fallback_backend_key,
            'shadow_mode_percentage' => $settings->shadow_mode_percentage,
            'provider_key' => $settings->provider_key,
        ],
        'source_images' => array_map(static fn (string $path): string => basename($path), $eventSources),
        'indexing' => [],
        'queries' => [],
    ];

    try {
        echo sprintf("== Evento %d/%d [%d] ==\n", $index + 1, $eventCount, $event->id);
        echo "Provisionando collection AWS...\n";
        $ensure = $awsBackend->ensureEventBackend($event->fresh(['faceSearchSettings']), $settings->fresh());
        echo 'Collection pronta: ' . ($ensure['collection_id'] ?? 'n/a') . PHP_EOL;
        $healthBefore = $awsBackend->healthCheck($event->fresh(['faceSearchSettings']), $settings->fresh());
        echo 'Health antes da indexacao: ' . ($healthBefore['status'] ?? 'n/a') . PHP_EOL;

        $createdMedia = [];

        foreach ($eventSources as $slot => $sourcePath) {
            echo sprintf("Indexando media %d/%d: %s\n", $slot + 1, count($eventSources), basename($sourcePath));
            $created = createEventMediaFromSource($event, $sourcePath, $slot + 1);
            /** @var EventMedia $media */
            $media = $created['media'];

            $indexResult = $indexAction->execute($media);

            $createdMedia[] = $media;
            $eventReport['indexing'][] = [
                'event_media_id' => $media->id,
                'source_file' => basename($sourcePath),
                'stored_path' => $created['stored_path'],
                'pipeline' => $indexResult,
                'local_shadow_seed' => data_get($indexResult, 'shadow.result'),
            ];
        }

        echo "Reconciliando collection AWS...\n";
        $reconcile = $awsBackend->reconcileCollection($event->fresh(['faceSearchSettings']), $settings->fresh());
        $healthAfter = $awsBackend->healthCheck($event->fresh(['faceSearchSettings']), $settings->fresh());
        echo 'Health depois da indexacao: ' . ($healthAfter['status'] ?? 'n/a') . PHP_EOL;

        $validationBlockedCount = 0;

        $eligibleProbe = null;
        $eligibleSourcePaths = collect($eventReport['indexing'])
            ->filter(fn (array $item): bool => (int) data_get($item, 'pipeline.faces_indexed', 0) > 0)
            ->map(fn (array $item): ?string => $sourcePathsByFilename[$item['source_file']] ?? null)
            ->filter(fn (?string $path): bool => is_string($path) && $path !== '')
            ->values()
            ->all();

        $probeCandidates = $eligibleSourcePaths !== [] ? $eligibleSourcePaths : $eventSources;

        foreach ($probeCandidates as $sourcePath) {
            $eligibleProbe = buildEligibleSelfieProbe(
                event: $event,
                settings: $settings->fresh(),
                sourcePath: $sourcePath,
                probePrefix: "event-{$event->id}",
            );

            if ($eligibleProbe !== null) {
                break;
            }
        }

        if ($eligibleProbe !== null) {
            try {
                echo 'Executando query valida de selfie...' . PHP_EOL;
                $validSearch = $searchAction->execute(
                    event: $event->fresh(['faceSearchSettings']),
                    selfie: createUploadedFile($eligibleProbe['path']),
                    requesterType: 'pilot_script',
                );

                $eventReport['queries'][] = [
                    'scenario' => 'valid_selfie_same_source',
                    'source_file' => basename($eligibleProbe['path']),
                    'derived_from' => $eligibleProbe['derived_from'],
                    'scale_factor' => $eligibleProbe['scale_factor'],
                    'request_id' => $validSearch['request']->id,
                    'request_status' => $validSearch['request']->status,
                    'result_count' => count($validSearch['results']),
                    'top_event_media_id' => $validSearch['results'][0]['event_media_id'] ?? null,
                    'query_audit' => latestQueryAuditForRequest($validSearch['request']->id),
                ];
            } catch (ValidationException $exception) {
                $eventReport['queries'][] = [
                    'scenario' => 'valid_selfie_probe_blocked',
                    'source_file' => basename($eligibleProbe['path']),
                    'derived_from' => $eligibleProbe['derived_from'],
                    'status' => 'blocked_validation',
                    'message' => collect($exception->errors())->flatten()->first(),
                ];
            } catch (Throwable $exception) {
                $eventReport['queries'][] = [
                    'scenario' => 'valid_selfie_probe_failed',
                    'source_file' => basename($eligibleProbe['path']),
                    'derived_from' => $eligibleProbe['derived_from'],
                    'status' => 'failed',
                    'exception_class' => $exception::class,
                    'message' => $exception->getMessage(),
                ];
            }
        } else {
            $eventReport['queries'][] = [
                'scenario' => 'valid_selfie_same_source',
                'source_file' => basename($singlePath),
                'status' => 'failed',
                'message' => 'Nao foi possivel derivar uma selfie elegivel a partir das imagens do evento nesta rodada.',
            ];
        }

        try {
            echo 'Executando query de grupo para validar preflight...' . PHP_EOL;
            $invalidGroup = $searchAction->execute(
                event: $event->fresh(['faceSearchSettings']),
                selfie: createUploadedFile($eventMultis[0]),
                requesterType: 'pilot_script',
            );

            $eventReport['queries'][] = [
                'scenario' => 'group_photo_query',
                'source_file' => basename($eventMultis[0]),
                'request_id' => $invalidGroup['request']->id,
                'request_status' => $invalidGroup['request']->status,
                'result_count' => count($invalidGroup['results']),
                'top_event_media_id' => $invalidGroup['results'][0]['event_media_id'] ?? null,
                'query_audit' => latestQueryAuditForRequest($invalidGroup['request']->id),
                'note' => 'A imagem de grupo passou pelo preflight mesmo com o guard rail mais rigido; manter o caso para medir falso negativo do bloqueio.',
            ];
        } catch (ValidationException $exception) {
            $validationBlockedCount++;
            $eventReport['queries'][] = [
                'scenario' => 'group_photo_query',
                'source_file' => basename($eventMultis[0]),
                'status' => 'blocked_validation',
                'message' => collect($exception->errors())->flatten()->first(),
            ];
        } catch (Throwable $exception) {
            $eventReport['queries'][] = [
                'scenario' => 'group_photo_query',
                'source_file' => basename($eventMultis[0]),
                'status' => 'failed',
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ];
        }

        $eventReport['health_before'] = $healthBefore;
        $eventReport['health_after'] = $healthAfter;
        $eventReport['metrics'] = aggregateEventMetrics(
            eventId: $event->id,
            indexedMediaCount: count($createdMedia),
            validationBlockedCount: $validationBlockedCount,
            queries: $eventReport['queries'],
            reconcileResult: $reconcile,
            groupOnePricePerImage: $groupOnePricePerImageUsd,
            storagePerFaceVectorMonth: $storagePerFaceVectorMonthUsd,
        );
        echo 'Metricas agregadas do evento calculadas.' . PHP_EOL;
    } catch (Throwable $exception) {
        $eventReport['fatal_error'] = [
            'exception_class' => $exception::class,
            'message' => $exception->getMessage(),
        ];
    } finally {
        if ($cleanup) {
            try {
                echo "Limpando collection AWS do evento...\n";
                $awsBackend->deleteEventBackend($event->fresh(['faceSearchSettings']), $settings->fresh());
                $eventReport['cleanup'] = [
                    'status' => 'collection_deleted',
                ];
            } catch (Throwable $cleanupException) {
                $eventReport['cleanup'] = [
                    'status' => 'failed',
                    'exception_class' => $cleanupException::class,
                    'message' => $cleanupException->getMessage(),
                ];
            }
        } else {
            $eventReport['cleanup'] = [
                'status' => 'skipped',
            ];
        }
    }

    $report['events'][] = $eventReport;
}

$eventMetrics = collect($report['events'])->pluck('metrics')->filter();
$report['summary'] = [
    'events_executed' => count($report['events']),
    'estimated_processing_cost_usd' => round((float) $eventMetrics->sum('estimated_processing_cost_usd'), 6),
    'estimated_storage_cost_usd_monthly_if_kept' => round((float) $eventMetrics->sum('estimated_storage_cost_usd_monthly_if_kept'), 6),
    'avg_fallback_rate' => $eventMetrics->count() > 0
        ? round((float) $eventMetrics->avg('fallback_rate'), 6)
        : null,
    'avg_shadow_divergence' => $eventMetrics
        ->pluck('shadow_divergence_avg')
        ->filter(fn (mixed $value): bool => is_numeric($value))
        ->avg(),
    'total_unindexed_faces' => (int) $eventMetrics->sum('unindexed_faces'),
    'total_queries_attempted' => (int) $eventMetrics->sum('queries_attempted'),
    'total_queries_blocked_by_preflight' => (int) $eventMetrics->sum('queries_blocked_by_preflight'),
];

$reportPath = sprintf(
    'face-search-aws-pilot/%s-face-search-aws-h7-pilot.json',
    now()->format('Ymd-His'),
);

Storage::disk('local')->put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Piloto H7-T2 executado.\n";
echo 'Eventos: ' . count($report['events']) . PHP_EOL;
echo 'Custo estimado de processamento (USD): ' . number_format((float) ($report['summary']['estimated_processing_cost_usd'] ?? 0), 6, '.', '') . PHP_EOL;
echo 'Divergencia media de shadow: ' . (isset($report['summary']['avg_shadow_divergence']) ? number_format((float) $report['summary']['avg_shadow_divergence'], 6, '.', '') : 'n/a') . PHP_EOL;
echo 'Relatorio: storage/app/' . $reportPath . PHP_EOL;

exit(0);
