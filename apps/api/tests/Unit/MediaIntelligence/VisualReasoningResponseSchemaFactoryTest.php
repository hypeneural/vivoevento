<?php

use App\Modules\MediaIntelligence\Services\VisualReasoningResponseSchemaFactory;

it('keeps every declared property inside the required list for the strict json schema', function () {
    $schema = app(VisualReasoningResponseSchemaFactory::class)->schema('foundation-v1');

    $properties = array_keys($schema['properties'] ?? []);
    $required = $schema['required'] ?? [];

    expect($properties)->not->toBeEmpty()
        ->and($required)->toBe($properties)
        ->and($required)->toContain('reply_text');
});
