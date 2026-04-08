<?php

use App\Modules\MediaIntelligence\Models\MediaReplyPromptPreset;

it('lists only active media reply prompt presets for non-admin users', function () {
    [$user] = $this->actingAsOwner();

    MediaReplyPromptPreset::factory()->create([
        'name' => 'Casamentos',
        'slug' => 'casamentos',
        'is_active' => true,
    ]);

    MediaReplyPromptPreset::factory()->create([
        'name' => 'Arquivado',
        'slug' => 'arquivado',
        'is_active' => false,
    ]);

    $response = $this->apiGet('/ia/respostas-de-midia/presets');

    $this->assertApiSuccess($response);
    $slugs = collect($response->json('data'))->pluck('slug')->all();

    expect($slugs)->toContain('casamentos');
    expect($slugs)->not->toContain('arquivado');
});

it('allows platform admins to create update and delete media reply prompt presets', function () {
    [$user] = $this->actingAsSuperAdmin();

    $createResponse = $this->apiPost('/ia/respostas-de-midia/presets', [
        'name' => 'Casamentos',
        'category' => 'casamento',
        'description' => 'Tom leve e romantico.',
        'prompt_template' => 'Gere uma frase curta, delicada e coerente com a cena. Use no maximo 2 emojis.',
        'sort_order' => 10,
        'is_active' => true,
    ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('data.slug', 'casamentos')
        ->assertJsonPath('data.name', 'Casamentos');

    $presetId = $createResponse->json('data.id');

    $this->assertDatabaseHas('ai_media_reply_prompt_presets', [
        'id' => $presetId,
        'slug' => 'casamentos',
        'name' => 'Casamentos',
    ]);

    $updateResponse = $this->apiPatch("/ia/respostas-de-midia/presets/{$presetId}", [
        'slug' => 'casamentos-classicos',
        'name' => 'Casamentos Classicos',
        'category' => 'casamento',
        'description' => 'Tom elegante e romantico.',
        'prompt_template' => 'Gere uma frase curta, elegante e romantica, coerente com a cena.',
        'sort_order' => 20,
        'is_active' => false,
    ]);

    $this->assertApiSuccess($updateResponse);
    $updateResponse->assertJsonPath('data.slug', 'casamentos-classicos')
        ->assertJsonPath('data.is_active', false);

    $deleteResponse = $this->apiDelete("/ia/respostas-de-midia/presets/{$presetId}");

    $deleteResponse->assertNoContent();
    $this->assertDatabaseMissing('ai_media_reply_prompt_presets', [
        'id' => $presetId,
    ]);
});

it('forbids organization owners from mutating media reply prompt presets', function () {
    [$user] = $this->actingAsOwner();
    $preset = MediaReplyPromptPreset::factory()->create();

    $createResponse = $this->apiPost('/ia/respostas-de-midia/presets', [
        'name' => 'Corporativo',
        'prompt_template' => 'Teste',
        'is_active' => true,
    ]);
    $updateResponse = $this->apiPatch("/ia/respostas-de-midia/presets/{$preset->id}", [
        'slug' => $preset->slug,
        'name' => $preset->name,
        'category' => $preset->category,
        'description' => $preset->description,
        'prompt_template' => $preset->prompt_template,
        'sort_order' => $preset->sort_order,
        'is_active' => $preset->is_active,
    ]);
    $deleteResponse = $this->apiDelete("/ia/respostas-de-midia/presets/{$preset->id}");

    $this->assertApiForbidden($createResponse);
    $this->assertApiForbidden($updateResponse);
    $this->assertApiForbidden($deleteResponse);
});
