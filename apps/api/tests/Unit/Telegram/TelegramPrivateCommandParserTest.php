<?php

use App\Modules\Telegram\Support\TelegramPrivateCommandParser;

it('extracts the activation code from the private /start command', function () {
    $parser = app(TelegramPrivateCommandParser::class);

    expect($parser->extractActivationCode('/start anaejoao'))->toBe('ANAEJOAO')
        ->and($parser->extractActivationCode('/start   evt_abc123  '))->toBe('EVT_ABC123');
});

it('returns null when the private /start command does not carry an activation code', function () {
    $parser = app(TelegramPrivateCommandParser::class);

    expect($parser->extractActivationCode('/start'))->toBeNull()
        ->and($parser->extractActivationCode('start ANAEJOAO'))->toBeNull();
});

it('extracts a standalone activation code from a plain private message', function () {
    $parser = app(TelegramPrivateCommandParser::class);

    expect($parser->extractStandaloneActivationCode('TGTEST406'))->toBe('TGTEST406')
        ->and($parser->extractStandaloneActivationCode('evt_abc123'))->toBe('EVT_ABC123')
        ->and($parser->extractStandaloneActivationCode('/start TGTEST406'))->toBeNull()
        ->and($parser->extractStandaloneActivationCode('codigo com espaco'))->toBeNull();
});

it('recognizes the private stop commands used to close the session', function () {
    $parser = app(TelegramPrivateCommandParser::class);

    expect($parser->isExitCommand('SAIR'))->toBeTrue()
        ->and($parser->isExitCommand('/sair'))->toBeTrue()
        ->and($parser->isExitCommand('/stop'))->toBeTrue()
        ->and($parser->isExitCommand('/start ANAEJOAO'))->toBeFalse();
});
