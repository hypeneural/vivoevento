<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\MediaReplyPromptPreset;
use App\Modules\MediaIntelligence\Models\MediaReplyTestRun;
use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;
use App\Modules\Users\Models\User;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
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
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function run(User $user, array $payload): MediaReplyTestRun
    {
        $traceId = (string) Str::uuid();
        $event = isset($payload['event_id']) ? Event::query()->find($payload['event_id']) : null;
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
        $status = 'failed';
        $errorMessage = null;

        try {
            $startedAt = microtime(true);
            $response = $this->request($providerKey, $config)->post('chat/completions', $requestPayload);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $response->throw();

            $responsePayload = $response->json();
            $responseText = $this->extractReplyText($responsePayload);
            $status = 'success';
        } catch (Throwable $exception) {
            $latencyMs ??= isset($startedAt) ? (int) round((microtime(true) - $startedAt) * 1000) : null;
            $errorMessage = $exception->getMessage();
            $responsePayload = $this->exceptionResponsePayload($exception);
        }

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
        Log::channel('ai-media-reply-tests')->{$status === 'success' ? 'info' : 'error'}(
            $status === 'success'
                ? 'ai_media_reply_test.completed'
                : 'ai_media_reply_test.failed',
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
                'request_payload' => $run->request_payload_json,
                'response_payload' => $run->response_payload_json,
                'response_text' => $run->response_text,
                'error_message' => $run->error_message,
            ],
        );
    }
}
