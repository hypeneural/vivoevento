<?php

use App\Shared\Support\NormalizedTextContextBuilder;

it('builds body plus caption normalized text context without duplicating repeated fragments', function () {
    $result = app(NormalizedTextContextBuilder::class)->build(
        'body_plus_caption',
        caption: 'Entrada especial do casal',
        bodyText: 'Entrada especial do casal',
        operatorSummary: null,
    );

    expect($result['mode'])->toBe('body_plus_caption')
        ->and($result['text'])->toBe('Entrada especial do casal');
});

it('builds body plus caption normalized text context in stable order', function () {
    $result = app(NormalizedTextContextBuilder::class)->build(
        'body_plus_caption',
        caption: 'Legenda do evento',
        bodyText: 'Texto recebido',
        operatorSummary: null,
    );

    expect($result['text'])->toBe("Legenda do evento\n\nTexto recebido");
});

it('returns null text when normalized text context mode is none', function () {
    $result = app(NormalizedTextContextBuilder::class)->build(
        'none',
        caption: 'Legenda do evento',
        bodyText: 'Texto recebido',
        operatorSummary: 'Resumo do operador',
    );

    expect($result['mode'])->toBe('none')
        ->and($result['text'])->toBeNull();
});

it('prefers operator summary when the mode requires it', function () {
    $result = app(NormalizedTextContextBuilder::class)->build(
        'operator_summary',
        caption: 'Legenda do evento',
        bodyText: 'Texto recebido',
        operatorSummary: 'Resumo manual do operador',
    );

    expect($result['text'])->toBe('Resumo manual do operador');
});
