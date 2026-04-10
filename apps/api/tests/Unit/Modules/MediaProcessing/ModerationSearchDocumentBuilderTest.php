<?php

use App\Modules\MediaProcessing\Services\ModerationSearchDocumentBuilder;

it('builds a compact moderation search document from media event and sender parts', function () {
    $document = app(ModerationSearchDocumentBuilder::class)->buildFromParts([
        '  Entrada   principal ',
        'Festival da Identidade',
        'Ana Martins',
        null,
        '',
        'sender-ana-001',
    ]);

    expect($document)->toBe('Entrada principal Festival da Identidade Ana Martins sender-ana-001');
});

it('returns null for an empty moderation search document', function () {
    $document = app(ModerationSearchDocumentBuilder::class)->buildFromParts([
        null,
        '',
        '   ',
    ]);

    expect($document)->toBeNull();
});
