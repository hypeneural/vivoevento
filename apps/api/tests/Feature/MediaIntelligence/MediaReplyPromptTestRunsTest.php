<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\MediaReplyPromptPreset;
use App\Modules\MediaIntelligence\Models\MediaReplyTestRun;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

it('runs a synchronous media reply prompt test with up to three images and stores history in the database', function () {
    [$user] = $this->actingAsSuperAdmin();

    Http::fake([
        'https://openrouter.test/api/v1/chat/completions' => Http::response([
            'id' => 'resp_123',
            'model' => 'openai/gpt-4.1-mini',
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'reply_text' => 'Memorias que fazem o coracao sorrir! 🎉📸',
                    ], JSON_THROW_ON_ERROR),
                ],
            ]],
        ], 200),
    ]);

    config()->set('media_intelligence.providers.openrouter.base_url', 'https://openrouter.test/api/v1');

    $event = Event::factory()->create([
        'title' => 'Formatura 2026',
    ]);
    $preset = MediaReplyPromptPreset::factory()->create([
        'name' => 'Formaturas',
        'slug' => 'formaturas',
        'prompt_template' => 'Use um tom celebrativo e educado, coerente com a cena.',
    ]);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/ia/respostas-de-midia/testes', [
            'event_id' => $event->id,
            'provider_key' => 'openrouter',
            'model_key' => 'openai/gpt-4.1-mini',
            'preset_id' => $preset->id,
            'prompt_template' => 'Mantenha a resposta curta e com no maximo 2 emojis.',
            'images' => [
                UploadedFile::fake()->image('foto-1.jpg', 800, 600)->size(320),
                UploadedFile::fake()->image('foto-2.jpg', 800, 600)->size(330),
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'success')
        ->assertJsonPath('data.provider_key', 'openrouter')
        ->assertJsonPath('data.event_id', $event->id)
        ->assertJsonPath('data.preset_id', $preset->id)
        ->assertJsonPath('data.response_text', 'Memorias que fazem o coracao sorrir! 🎉📸')
        ->assertJsonPath('data.prompt_variables.nome_do_evento', 'Formatura 2026');

    $runId = $response->json('data.id');
    $testRun = MediaReplyTestRun::query()->findOrFail($runId);

    expect($testRun->status)->toBe('success')
        ->and($testRun->images_json)->toHaveCount(2)
        ->and($testRun->request_payload_json)->toBeArray()
        ->and(data_get($testRun->request_payload_json, 'messages.1.content.1.image_url.sha256'))->not->toBeNull()
        ->and(data_get($testRun->request_payload_json, 'messages.1.content.2.image_url.sha256'))->not->toBeNull();
});

it('rejects prompt tests with more than three images', function () {
    [$user] = $this->actingAsSuperAdmin();

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/ia/respostas-de-midia/testes', [
            'provider_key' => 'vllm',
            'model_key' => 'Qwen/Qwen2.5-VL-3B-Instruct',
            'images' => [
                UploadedFile::fake()->image('foto-1.jpg'),
                UploadedFile::fake()->image('foto-2.jpg'),
                UploadedFile::fake()->image('foto-3.jpg'),
                UploadedFile::fake()->image('foto-4.jpg'),
            ],
        ]);

    $this->assertApiValidationError($response, ['images']);
});

it('lists and shows stored media reply prompt test runs', function () {
    [$user] = $this->actingAsSuperAdmin();

    $latest = MediaReplyTestRun::factory()->create([
        'provider_key' => 'openrouter',
        'status' => 'success',
    ]);
    MediaReplyTestRun::factory()->create([
        'provider_key' => 'vllm',
        'status' => 'failed',
    ]);

    $listResponse = $this->apiGet('/ia/respostas-de-midia/testes?provider_key=openrouter&status=success&per_page=10');

    $this->assertApiPaginated($listResponse);
    expect($listResponse->json('data'))->toHaveCount(1);
    $listResponse->assertJsonPath('data.0.id', $latest->id);

    $showResponse = $this->apiGet("/ia/respostas-de-midia/testes/{$latest->id}");

    $this->assertApiSuccess($showResponse);
    $showResponse->assertJsonPath('data.trace_id', $latest->trace_id)
        ->assertJsonPath('data.provider_key', 'openrouter');
});
