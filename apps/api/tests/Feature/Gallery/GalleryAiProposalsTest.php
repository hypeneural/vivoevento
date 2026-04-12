<?php

use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Models\GalleryBuilderPromptRun;

it('returns three guardrailed ai variations and stores the prompt run', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Galeria com IA',
        'primary_color' => '#111827',
        'secondary_color' => '#f97316',
    ]);

    $response = $this->apiPost("/events/{$event->id}/gallery/ai/proposals", [
        'prompt_text' => 'quero uma galeria romantica em tons rose com hero mais editorial',
        'persona_key' => 'operator',
        'target_layer' => 'mixed',
        'base_preset_key' => 'wedding.romantic.story',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.run.event_id', $event->id)
        ->assertJsonPath('data.run.organization_id', $organization->id)
        ->assertJsonPath('data.run.user_id', $user->id)
        ->assertJsonPath('data.run.persona_key', 'operator')
        ->assertJsonPath('data.run.target_layer', 'mixed')
        ->assertJsonPath('data.run.response_schema_version', 1)
        ->assertJsonPath('data.run.status', 'success')
        ->assertJsonPath('data.run.provider_key', 'local-guardrailed')
        ->assertJsonPath('data.run.model_key', 'gallery-builder-local-v1');

    $variations = $response->json('data.variations');

    expect($variations)->toHaveCount(3)
        ->and($variations[0])->toHaveKeys(['id', 'label', 'summary', 'scope', 'available_layers', 'model_matrix', 'patch'])
        ->and($variations[0]['patch'])->not->toHaveKey('custom_css');

    $run = GalleryBuilderPromptRun::query()->latest('id')->first();

    expect($run)->not->toBeNull()
        ->and($run->event_id)->toBe($event->id)
        ->and(data_get($run->request_payload_json, 'response_format.type'))->toBe('json_schema')
        ->and(data_get($run->response_payload_json, 'variations'))->toHaveCount(3);
});

it('limits the ai response to the requested layer when target_layer is explicit', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Galeria com IA por camada',
        'primary_color' => '#111827',
        'secondary_color' => '#f97316',
    ]);

    $response = $this->apiPost("/events/{$event->id}/gallery/ai/proposals", [
        'prompt_text' => 'so quero ajustar a paleta para ficar mais premium',
        'persona_key' => 'operator',
        'target_layer' => 'theme_tokens',
    ]);

    $this->assertApiSuccess($response);

    collect($response->json('data.variations'))->each(function (array $variation): void {
        expect($variation['scope'])->toBe('theme_tokens')
            ->and(array_keys($variation['patch']))->toEqual(['theme_tokens']);
    });
});
