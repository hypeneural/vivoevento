<?php

use App\Modules\MediaIntelligence\Models\MediaReplyPromptCategory;

it('lists only active media reply prompt categories for non-admin users', function () {
    [$user] = $this->actingAsOwner();

    MediaReplyPromptCategory::factory()->create([
        'slug' => 'casamento-social',
        'name' => 'Casamento Social',
        'is_active' => true,
    ]);

    MediaReplyPromptCategory::factory()->create([
        'slug' => 'oculta',
        'name' => 'Oculta',
        'is_active' => false,
    ]);

    $response = $this->apiGet('/ia/respostas-de-midia/categorias');

    $this->assertApiSuccess($response);
    $slugs = collect($response->json('data'))->pluck('slug')->all();

    expect($slugs)->toContain('casamento-social');
    expect($slugs)->not->toContain('oculta');
});

it('allows platform admins to create update and delete media reply prompt categories', function () {
    [$user] = $this->actingAsSuperAdmin();

    $createResponse = $this->apiPost('/ia/respostas-de-midia/categorias', [
        'name' => 'Infantil',
        'sort_order' => 50,
        'is_active' => true,
    ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('data.slug', 'infantil')
        ->assertJsonPath('data.name', 'Infantil');

    $categoryId = $createResponse->json('data.id');

    $updateResponse = $this->apiPatch("/ia/respostas-de-midia/categorias/{$categoryId}", [
        'slug' => 'infantil-premium',
        'name' => 'Infantil Premium',
        'sort_order' => 60,
        'is_active' => false,
    ]);

    $this->assertApiSuccess($updateResponse);
    $updateResponse->assertJsonPath('data.slug', 'infantil-premium')
        ->assertJsonPath('data.is_active', false);

    $deleteResponse = $this->apiDelete("/ia/respostas-de-midia/categorias/{$categoryId}");

    $deleteResponse->assertNoContent();
    $this->assertDatabaseMissing('ai_media_reply_prompt_categories', [
        'id' => $categoryId,
    ]);
});

it('forbids organization owners from mutating media reply prompt categories', function () {
    [$user] = $this->actingAsOwner();
    $category = MediaReplyPromptCategory::factory()->create();

    $createResponse = $this->apiPost('/ia/respostas-de-midia/categorias', [
        'name' => 'Festas premium',
        'sort_order' => 70,
        'is_active' => true,
    ]);
    $updateResponse = $this->apiPatch("/ia/respostas-de-midia/categorias/{$category->id}", [
        'slug' => $category->slug,
        'name' => $category->name,
        'sort_order' => $category->sort_order,
        'is_active' => $category->is_active,
    ]);
    $deleteResponse = $this->apiDelete("/ia/respostas-de-midia/categorias/{$category->id}");

    $this->assertApiForbidden($createResponse);
    $this->assertApiForbidden($updateResponse);
    $this->assertApiForbidden($deleteResponse);
});
