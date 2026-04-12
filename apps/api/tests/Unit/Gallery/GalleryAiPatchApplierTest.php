<?php

use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Support\GalleryAiPatchApplier;
use App\Modules\Gallery\Support\GalleryBuilderPresetRegistry;
use Illuminate\Validation\ValidationException;

it('normalizes ai patches and returns only changed gallery layers', function () {
    $event = Event::factory()->create([
        'event_type' => 'wedding',
        'primary_color' => '#111827',
        'secondary_color' => '#f97316',
    ]);

    $current = app(GalleryBuilderPresetRegistry::class)->defaultsForEvent($event);
    $result = app(GalleryAiPatchApplier::class)->applyPatch(
        $event,
        $current,
        [
            'theme_tokens' => [
                'palette' => [
                    'accent' => '#f8fafc',
                    'button_fill' => '#f8fafc',
                    'button_text' => '#020617',
                ],
            ],
            'media_behavior' => [
                'grid' => [
                    'layout' => 'rows',
                    'density' => 'immersive',
                ],
            ],
        ],
        [
            'style_skin' => 'premium',
            'theme_key' => 'black-tie',
            'layout_key' => 'timeless-rows',
        ],
        'mixed',
    );

    expect($result['scope'])->toBe('mixed')
        ->and($result['available_layers'])->toContain('theme_tokens', 'media_behavior')
        ->and($result['model_matrix']['theme_key'])->toBe('black-tie')
        ->and($result['model_matrix']['layout_key'])->toBe('timeless-rows')
        ->and($result['patch']['theme_tokens']['palette']['accent'])->toBe('#f8fafc')
        ->and($result['patch']['media_behavior']['grid']['layout'])->toBe('rows')
        ->and($result['patch']['media_behavior']['grid']['density'])->toBe('immersive');
});

it('restricts normalized ai output to the requested target layer', function () {
    $event = Event::factory()->create([
        'event_type' => 'wedding',
        'primary_color' => '#111827',
        'secondary_color' => '#f97316',
    ]);

    $current = app(GalleryBuilderPresetRegistry::class)->defaultsForEvent($event);
    $result = app(GalleryAiPatchApplier::class)->applyPatch(
        $event,
        $current,
        [
            'theme_tokens' => [
                'palette' => [
                    'accent' => '#d97786',
                ],
            ],
            'page_schema' => [
                'blocks' => [
                    'quote' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ],
        [
            'theme_key' => 'wedding-rose',
            'event_type_family' => 'wedding',
        ],
        'theme_tokens',
    );

    expect(array_keys($result['patch']))->toEqual(['theme_tokens'])
        ->and($result['available_layers'])->toContain('theme_tokens');
});

it('rejects ai output with fields outside the catalog or freeform markup', function () {
    $event = Event::factory()->create([
        'event_type' => 'wedding',
        'primary_color' => '#111827',
        'secondary_color' => '#f97316',
    ]);

    $current = app(GalleryBuilderPresetRegistry::class)->defaultsForEvent($event);

    expect(fn () => app(GalleryAiPatchApplier::class)->applyPatch(
        $event,
        $current,
        [
            'custom_css' => [
                'body' => 'display:grid',
            ],
        ],
    ))->toThrow(ValidationException::class);

    expect(fn () => app(GalleryAiPatchApplier::class)->applyPatch(
        $event,
        $current,
        [
            'page_schema' => [
                'blocks' => [
                    'hero' => [
                        'headline' => '<div className="hero">romantico</div>',
                    ],
                ],
            ],
        ],
    ))->toThrow(ValidationException::class);
});
