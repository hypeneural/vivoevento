<?php

use App\Modules\MediaIntelligence\Services\VisualReasoningResponseSchemaFactory;

it('keeps every declared property inside the required list for the strict json schema', function () {
    $schema = app(VisualReasoningResponseSchemaFactory::class)->schema('contextual-v2');

    $properties = array_keys($schema['properties'] ?? []);
    $required = $schema['required'] ?? [];

    expect($properties)->not->toBeEmpty()
        ->and($required)->toBe($properties)
        ->and($required)->toContain('reply_text')
        ->and($required)->toContain('reason_code')
        ->and($required)->toContain('matched_policies')
        ->and($required)->toContain('matched_exceptions')
        ->and($required)->toContain('input_scope_used')
        ->and($required)->toContain('input_types_considered')
        ->and($required)->toContain('confidence_band')
        ->and($required)->toContain('publish_eligibility');
});
