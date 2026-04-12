<?php

use App\Modules\Gallery\Support\GalleryBuilderPromptSchemaFactory;

it('builds a strict json schema for gallery ai proposals', function () {
    $factory = app(GalleryBuilderPromptSchemaFactory::class);
    $schema = $factory->schema();

    expect($factory->version())->toBe(1)
        ->and($schema['type'])->toBe('object')
        ->and($schema['additionalProperties'])->toBeFalse()
        ->and($schema['required'])->toContain('response_schema_version', 'target_layer', 'variations')
        ->and($schema['properties']['variations']['minItems'])->toBe(3)
        ->and($schema['properties']['variations']['maxItems'])->toBe(3)
        ->and($schema['properties']['variations']['items']['required'])->toEqual([
            'id',
            'label',
            'summary',
            'scope',
            'model_matrix',
            'patch',
        ]);
});
