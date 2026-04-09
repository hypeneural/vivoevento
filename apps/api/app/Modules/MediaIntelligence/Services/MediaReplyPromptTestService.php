<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\ContentModeration\Services\ContentModerationPolicySnapshotFactory;
use App\Modules\ContentModeration\Services\ContentModerationProviderInterface;
use App\Modules\ContentModeration\Services\ContentModerationSettingsResolver;
use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;
use App\Modules\MediaIntelligence\Models\MediaReplyPromptPreset;
use App\Modules\MediaIntelligence\Models\MediaReplyTestRun;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Users\Models\User;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

class MediaReplyPromptTestService
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly MediaReplyPromptTestPayloadFactory $payloads,
        private readonly MediaReplyTextPromptResolver $promptResolver,
        private readonly ContentModerationProviderInterface $safetyProvider,
        private readonly ContentModerationSettingsResolver $safetySettingsResolver,
        private readonly ContentModerationPolicySnapshotFactory $safetyPolicySnapshots,
        private readonly VisualReasoningProviderInterface $contextProvider,
        private readonly ContextualModerationPolicyResolver $contextPolicyResolver,
        private readonly MediaReplyPromptTestSummaryService $summary,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function run(User $user, array $payload): MediaReplyTestRun
    {
        $traceId = (string) Str::uuid();
        $event = isset($payload['event_id'])
            ? Event::query()->with(['contentModerationSettings', 'mediaIntelligenceSettings'])->find($payload['event_id'])
            : null;

        $globalSettings = MediaIntelligenceGlobalSetting::query()->firstOrNew(
            ['id' => 1],
            MediaIntelligenceGlobalSetting::defaultAttributes(),
        );
        $eventSettings = $event?->mediaIntelligenceSettings;
        $preset = $this->resolvePreset(
            isset($payload['preset_id']) ? (int) $payload['preset_id'] : null,
            $eventSettings?->reply_prompt_preset_id,
            $globalSettings->reply_prompt_preset_id,
        );
        [$instructionTemplate, $instructionSource] = $this->resolveInstructionTemplate(
            $payload['prompt_template'] ?? null,
            $eventSettings?->reply_prompt_override,
            $globalSettings->reply_text_prompt,
        );
        $promptContext = $this->promptResolver->composePromptContext(
            eventName: $event?->title,
            instructionTemplate: $instructionTemplate,
            instructionSource: $instructionSource,
            preset: $preset['model'],
            presetSource: $preset['source'],
        );

        if ($promptContext === null) {
            throw new RuntimeException('Nao foi possivel montar o contexto do teste de resposta automatica.');
        }

        $providerKey = (string) ($payload['provider_key'] ?? 'vllm');
        $modelKey = trim((string) ($payload['model_key'] ?? ''));
        $files = array_values(array_filter(
            Arr::wrap($payload['images'] ?? []),
            static fn (mixed $file): bool => $file instanceof UploadedFile,
        ));

        $imageInputs = array_map(
            fn (UploadedFile $file, int $index): array => $this->buildImageInput($file, $index),
            $files,
            array_keys($files),
        );

        $safetyRuntimeOverrides = $this->resolveSafetyRuntimeOverrides($payload);
        $contextRuntimeOverrides = $this->resolveContextRuntimeOverrides($providerKey, $modelKey, $payload);

        $resolvedSafety = $this->resolveSafetySettings($event, $safetyRuntimeOverrides);
        $resolvedContext = $event instanceof Event
            ? $this->contextPolicyResolver->resolveForEvent($event, $contextRuntimeOverrides)
            : $this->contextPolicyResolver->resolveForGlobal($contextRuntimeOverrides);

        $safetyResults = [];
        $contextualResults = [];
        $labDisk = 'public';
        $labDirectory = "testing/ai-media-lab/{$traceId}";

        try {
            foreach ($files as $index => $file) {
                $metadata = $imageInputs[$index]['metadata'];
                $path = $this->storeLabImage($labDisk, $labDirectory, $file, $metadata);
                $media = $this->makeLabMedia(
                    event: $event,
                    file: $file,
                    metadata: $metadata,
                    disk: $labDisk,
                    path: $path,
                );

                $safetyResults[] = $this->evaluateSafety(
                    $media,
                    $resolvedSafety['settings'],
                    $metadata,
                );
                $contextualResults[] = $this->evaluateContext(
                    $media,
                    $resolvedContext['settings'],
                    $metadata,
                );
            }
        } finally {
            Storage::disk($labDisk)->deleteDirectory($labDirectory);
        }

        $config = (array) config("media_intelligence.providers.{$providerKey}", []);
        $requestPayload = $this->payloads->build($modelKey, $promptContext['resolved'], $imageInputs, $config);
        $imagesMetadata = array_map(
            static fn (array $input): array => $input['metadata'],
            $imageInputs,
        );
        $sanitizedRequestPayload = $this->payloads->sanitized($requestPayload, $imagesMetadata);

        $responsePayload = null;
        $responseText = null;
        $latencyMs = null;
        $errorMessage = null;

        try {
            $startedAt = microtime(true);
            $response = $this->request($providerKey, $config)->post('chat/completions', $requestPayload);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $response->throw();

            $responsePayload = $response->json();
            $responseText = $this->extractReplyText($responsePayload);
        } catch (Throwable $exception) {
            $latencyMs ??= isset($startedAt) ? (int) round((microtime(true) - $startedAt) * 1000) : null;
            $errorMessage = $exception->getMessage();
            $responsePayload = $this->exceptionResponsePayload($exception);
        }

        $policySnapshot = [
            'safety' => $resolvedSafety['snapshot'],
            'context' => $resolvedContext['snapshot'],
        ];
        $policySources = [
            'safety' => $this->normalizeRuntimeSourceLabels($resolvedSafety['sources']),
            'context' => $this->normalizeRuntimeSourceLabels($resolvedContext['sources']),
        ];

        $finalSummary = $this->summary->build(
            safetyResults: $safetyResults,
            contextualResults: $contextualResults,
            safetyIsBlocking: $this->isSafetyBlocking($resolvedSafety['settings']),
            contextIsBlocking: $this->isContextBlocking($resolvedContext['settings']),
            replySucceeded: $responseText !== null,
        );

        $hasEvaluationErrors = $this->hasEvaluationErrors($safetyResults, $contextualResults);
        $status = $this->resolveRunStatus(
            replySucceeded: $responseText !== null,
            hasEvaluationErrors: $hasEvaluationErrors,
            hasAnyEvaluation: $safetyResults !== [] || $contextualResults !== [],
        );

        $testRun = MediaReplyTestRun::query()->create([
            'trace_id' => $traceId,
            'user_id' => $user->id,
            'event_id' => $event?->id,
            'preset_id' => $preset['model']?->id,
            'provider_key' => $providerKey,
            'model_key' => $modelKey,
            'status' => $status,
            'prompt_template' => $promptContext['template'],
            'prompt_resolved' => $promptContext['resolved'],
            'prompt_variables_json' => $promptContext['variables'],
            'images_json' => $imagesMetadata,
            'safety_results_json' => $safetyResults,
            'contextual_results_json' => $contextualResults,
            'final_summary_json' => $finalSummary,
            'policy_snapshot_json' => $policySnapshot,
            'policy_sources_json' => $policySources,
            'request_payload_json' => $sanitizedRequestPayload,
            'response_payload_json' => $responsePayload,
            'response_text' => $responseText,
            'latency_ms' => $latencyMs,
            'error_message' => $errorMessage,
        ]);

        $this->logRun($status, $testRun);

        return $testRun->loadMissing('preset');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function request(string $providerKey, array $config): PendingRequest
    {
        $request = $this->http
            ->baseUrl(rtrim((string) ($config['base_url'] ?? ''), '/'))
            ->acceptJson()
            ->contentType('application/json')
            ->timeout((int) ($config['timeout'] ?? 20))
            ->connectTimeout((int) ($config['connect_timeout'] ?? 5));

        $apiKey = trim((string) ($config['api_key'] ?? ''));

        if ($apiKey !== '') {
            $request = $request->withToken($apiKey);
        }

        if ($providerKey === 'openrouter') {
            $siteUrl = trim((string) ($config['site_url'] ?? ''));
            $appName = trim((string) ($config['app_name'] ?? ''));

            if ($siteUrl !== '') {
                $request = $request->withHeaders(['HTTP-Referer' => $siteUrl]);
            }

            if ($appName !== '') {
                $request = $request->withHeaders(['X-Title' => $appName]);
            }
        }

        return $request;
    }

    /**
     * @return array{model:MediaReplyPromptPreset|null,source:string|null}
     */
    private function resolvePreset(?int $requestPresetId, ?int $eventPresetId, ?int $globalPresetId): array
    {
        foreach ([
            ['id' => $requestPresetId, 'source' => 'teste'],
            ['id' => $eventPresetId, 'source' => 'evento'],
            ['id' => $globalPresetId, 'source' => 'global'],
        ] as $candidate) {
            $presetId = $candidate['id'];

            if (! is_int($presetId) || $presetId <= 0) {
                continue;
            }

            $preset = MediaReplyPromptPreset::query()->find($presetId);

            if ($preset && $preset->is_active) {
                return [
                    'model' => $preset,
                    'source' => $candidate['source'],
                ];
            }
        }

        return [
            'model' => null,
            'source' => null,
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveInstructionTemplate(
        mixed $manualTemplate,
        mixed $eventTemplate,
        mixed $globalTemplate,
    ): array {
        $manualTemplate = trim((string) $manualTemplate);

        if ($manualTemplate !== '') {
            return [$manualTemplate, 'manual'];
        }

        $eventTemplate = trim((string) $eventTemplate);

        if ($eventTemplate !== '') {
            return [$eventTemplate, 'event'];
        }

        $globalTemplate = trim((string) $globalTemplate);

        if ($globalTemplate !== '') {
            return [$globalTemplate, 'global'];
        }

        return [MediaIntelligenceGlobalSetting::defaultReplyTextPrompt(), 'default'];
    }

    /**
     * @return array{data_url:string,metadata:array<string,mixed>}
     */
    private function buildImageInput(UploadedFile $file, int $index): array
    {
        $binary = file_get_contents($file->getRealPath());

        if ($binary === false) {
            throw new RuntimeException(sprintf('Nao foi possivel ler a imagem [%s].', $file->getClientOriginalName()));
        }

        $mimeType = $file->getMimeType() ?: 'image/jpeg';

        return [
            'data_url' => sprintf('data:%s;base64,%s', $mimeType, base64_encode($binary)),
            'metadata' => [
                'index' => $index,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
                'size_bytes' => $file->getSize(),
                'sha256' => hash('sha256', $binary),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function resolveSafetyRuntimeOverrides(array $payload): array
    {
        $overrides = [];

        if (isset($payload['objective_safety_scope_override'])) {
            $overrides['analysis_scope'] = (string) $payload['objective_safety_scope_override'];
        }

        if (isset($payload['normalized_text_context_mode_override'])) {
            $overrides['normalized_text_context_mode'] = (string) $payload['normalized_text_context_mode_override'];
        }

        return $overrides;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function resolveContextRuntimeOverrides(string $providerKey, string $modelKey, array $payload): array
    {
        $overrides = [
            'provider_key' => $providerKey,
            'model_key' => $modelKey,
        ];

        if (isset($payload['context_scope_override'])) {
            $overrides['context_scope'] = (string) $payload['context_scope_override'];
        }

        if (isset($payload['reply_scope_override'])) {
            $overrides['reply_scope'] = (string) $payload['reply_scope_override'];
        }

        if (isset($payload['normalized_text_context_mode_override'])) {
            $overrides['normalized_text_context_mode'] = (string) $payload['normalized_text_context_mode_override'];
        }

        return $overrides;
    }

    /**
     * @param array<string, mixed> $runtimeOverrides
     * @return array{
     *   settings: EventContentModerationSetting,
     *   snapshot: array<string, mixed>,
     *   sources: array<string, string>
     * }
     */
    private function resolveSafetySettings(?Event $event, array $runtimeOverrides): array
    {
        $baseSettings = $event instanceof Event
            ? $this->safetySettingsResolver->resolveForEvent($event)
            : $this->makeGlobalSafetySettings();

        $policy = $this->safetyPolicySnapshots->build($baseSettings, $runtimeOverrides);
        $settings = clone $baseSettings;

        foreach ($runtimeOverrides as $attribute => $value) {
            $settings->setAttribute($attribute, $value);
        }

        return [
            'settings' => $settings,
            'snapshot' => $policy['snapshot'],
            'sources' => $policy['sources'],
        ];
    }

    private function makeGlobalSafetySettings(): EventContentModerationSetting
    {
        $global = $this->safetySettingsResolver->resolveGlobal();
        $settings = new EventContentModerationSetting(array_merge(
            EventContentModerationSetting::defaultAttributes(),
            $global->only([
                'provider_key',
                'mode',
                'threshold_version',
                'hard_block_thresholds_json',
                'review_thresholds_json',
                'fallback_mode',
                'analysis_scope',
                'normalized_text_context_mode',
                'enabled',
            ]),
        ));
        $settings->setAttribute('inherits_global', true);

        return $settings;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function storeLabImage(string $disk, string $directory, UploadedFile $file, array $metadata): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $baseName = Str::slug(pathinfo((string) ($metadata['original_name'] ?? 'imagem'), PATHINFO_FILENAME));
        $filename = sprintf('%02d-%s.%s', (int) ($metadata['index'] ?? 0), $baseName !== '' ? $baseName : 'imagem', $extension);

        $stored = Storage::disk($disk)->putFileAs($directory, $file, $filename);

        if (! is_string($stored) || $stored === '') {
            throw new RuntimeException('Nao foi possivel armazenar a imagem temporaria do laboratorio.');
        }

        return $stored;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function makeLabMedia(
        ?Event $event,
        UploadedFile $file,
        array $metadata,
        string $disk,
        string $path,
    ): EventMedia {
        $media = new EventMedia([
            'event_id' => $event?->id,
            'media_type' => 'image',
            'source_type' => 'ai_lab',
            'source_label' => 'IA Lab',
            'caption' => null,
            'original_filename' => basename($path),
            'original_disk' => $disk,
            'original_path' => $path,
            'client_filename' => $metadata['original_name'] ?? $file->getClientOriginalName(),
            'mime_type' => $metadata['mime_type'] ?? ($file->getMimeType() ?: 'image/jpeg'),
            'size_bytes' => $metadata['size_bytes'] ?? $file->getSize(),
            'width' => null,
            'height' => null,
        ]);

        if ($event instanceof Event) {
            $media->setRelation('event', $event);
        }

        $media->setRelation('variants', collect());
        $media->setRelation('inboundMessage', null);

        return $media;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function evaluateSafety(
        EventMedia $media,
        EventContentModerationSetting $settings,
        array $metadata,
    ): array {
        try {
            $result = $this->safetyProvider->evaluate($media, $settings);

            return array_merge($this->labImageMetadata($metadata), $result->toRunResult(), [
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            return array_merge($this->labImageMetadata($metadata), [
                'decision' => 'error',
                'blocked' => false,
                'review_required' => true,
                'category_scores' => [],
                'input_scope_used' => $settings->analysis_scope,
                'input_path_used' => null,
                'normalized_text_context' => null,
                'normalized_text_context_mode' => $settings->normalized_text_context_mode,
                'reason_codes' => ['provider.error'],
                'error_message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function evaluateContext(
        EventMedia $media,
        EventMediaIntelligenceSetting $settings,
        array $metadata,
    ): array {
        try {
            $result = $this->contextProvider->evaluate($media, $settings);

            return array_merge($this->labImageMetadata($metadata), $result->toRunResult(), [
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            return array_merge($this->labImageMetadata($metadata), [
                'decision' => 'error',
                'review_required' => true,
                'reason' => 'Falha ao avaliar o gate contextual no laboratorio.',
                'reason_code' => 'provider.error',
                'matched_policies' => [],
                'matched_exceptions' => [],
                'input_scope_used' => $settings->context_scope,
                'input_types_considered' => $settings->context_scope === 'image_and_text_context' ? ['image', 'text'] : ['image'],
                'confidence_band' => 'medium',
                'publish_eligibility' => 'review_only',
                'short_caption' => null,
                'reply_text' => null,
                'tags' => [],
                'response_schema_version' => $settings->response_schema_version,
                'mode_applied' => $settings->mode,
                'normalized_text_context' => null,
                'normalized_text_context_mode' => $settings->normalized_text_context_mode,
                'error_message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function labImageMetadata(array $metadata): array
    {
        return [
            'image_index' => $metadata['index'] ?? null,
            'original_name' => $metadata['original_name'] ?? null,
            'mime_type' => $metadata['mime_type'] ?? null,
            'size_bytes' => $metadata['size_bytes'] ?? null,
            'sha256' => $metadata['sha256'] ?? null,
        ];
    }

    private function isSafetyBlocking(EventContentModerationSetting $settings): bool
    {
        return (bool) ($settings->enabled ?? false)
            && (string) ($settings->mode ?? 'enforced') !== 'observe_only';
    }

    private function isContextBlocking(EventMediaIntelligenceSetting $settings): bool
    {
        return (bool) ($settings->enabled ?? false)
            && (string) ($settings->mode ?? 'enrich_only') === 'gate';
    }

    /**
     * @param array<int, array<string, mixed>> $safetyResults
     * @param array<int, array<string, mixed>> $contextualResults
     */
    private function hasEvaluationErrors(array $safetyResults, array $contextualResults): bool
    {
        foreach (array_merge($safetyResults, $contextualResults) as $result) {
            if (($result['error_message'] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    private function resolveRunStatus(bool $replySucceeded, bool $hasEvaluationErrors, bool $hasAnyEvaluation): string
    {
        if ($replySucceeded && ! $hasEvaluationErrors) {
            return 'success';
        }

        if ($hasAnyEvaluation || $replySucceeded) {
            return 'partial';
        }

        return 'failed';
    }

    /**
     * @param array<string, string> $sources
     * @return array<string, string>
     */
    private function normalizeRuntimeSourceLabels(array $sources): array
    {
        foreach ($sources as $field => $source) {
            if ($source === 'runtime_fallback') {
                $sources[$field] = 'runtime_override';
            }
        }

        return $sources;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractReplyText(array $payload): string
    {
        $content = Arr::get($payload, 'choices.0.message.content');

        if (is_array($content)) {
            $content = collect($content)
                ->filter(fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'text' && is_string($item['text'] ?? null))
                ->map(fn (array $item): string => $item['text'])
                ->implode("\n");
        }

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('O provider nao retornou conteudo textual para o teste de prompt.');
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('O provider retornou JSON invalido no teste de prompt.', previous: $exception);
        }

        return trim((string) ($decoded['reply_text'] ?? ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function exceptionResponsePayload(Throwable $exception): ?array
    {
        if (! $exception instanceof RequestException || $exception->response === null) {
            return null;
        }

        $payload = $exception->response->json();

        return is_array($payload)
            ? $payload
            : ['raw' => $exception->response->body()];
    }

    private function logRun(string $status, MediaReplyTestRun $run): void
    {
        Log::channel('ai-media-reply-tests')->{$status === 'failed' ? 'error' : 'info'}(
            $status === 'failed'
                ? 'ai_media_reply_test.failed'
                : 'ai_media_reply_test.completed',
            [
                'trace_id' => $run->trace_id,
                'test_run_id' => $run->id,
                'event_id' => $run->event_id,
                'provider_key' => $run->provider_key,
                'model_key' => $run->model_key,
                'status' => $run->status,
                'latency_ms' => $run->latency_ms,
                'prompt_template' => $run->prompt_template,
                'prompt_resolved' => $run->prompt_resolved,
                'prompt_variables' => $run->prompt_variables_json,
                'images' => $run->images_json,
                'safety_results' => $run->safety_results_json,
                'contextual_results' => $run->contextual_results_json,
                'final_summary' => $run->final_summary_json,
                'policy_snapshot' => $run->policy_snapshot_json,
                'policy_sources' => $run->policy_sources_json,
                'request_payload' => $run->request_payload_json,
                'response_payload' => $run->response_payload_json,
                'response_text' => $run->response_text,
                'error_message' => $run->error_message,
            ],
        );
    }
}
