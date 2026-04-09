<?php

use App\Modules\Billing\Models\EventPackage;
use App\Modules\Billing\Services\EventPackageCheckoutMarketingService;

it('builds native checkout marketing metadata from configured package features', function () {
    $package = EventPackage::factory()->make([
        'code' => 'interactive-event',
        'description' => 'Descricao tecnica qualquer.',
    ]);

    $service = app(EventPackageCheckoutMarketingService::class);

    $metadata = $service->build($package, [
        'checkout.slug' => 'casamento-interativo',
        'checkout.subtitle' => 'O pacote mais equilibrado para eventos sociais com compra rapida.',
        'checkout.ideal_for' => 'Casamentos e aniversarios com telao ao vivo.',
        'checkout.badge' => 'Mais escolhido',
        'checkout.recommended' => 'true',
        'checkout.benefit_1' => 'Telao ao vivo para os convidados',
        'checkout.benefit_2' => 'Pagina do evento pronta para compartilhar',
        'checkout.benefit_3' => 'Pix e cartao com confirmacao automatica',
    ]);

    expect($metadata)->toMatchArray([
        'slug' => 'casamento-interativo',
        'subtitle' => 'O pacote mais equilibrado para eventos sociais com compra rapida.',
        'ideal_for' => 'Casamentos e aniversarios com telao ao vivo.',
        'badge' => 'Mais escolhido',
        'recommended' => true,
    ]);
    expect($metadata['benefits'])->toBe([
        'Telao ao vivo para os convidados',
        'Pagina do evento pronta para compartilhar',
        'Pix e cartao com confirmacao automatica',
    ]);
});

it('falls back to derived commercial metadata when checkout overrides are missing', function () {
    $package = EventPackage::factory()->make([
        'code' => 'premium-event',
        'description' => null,
    ]);

    $service = app(EventPackageCheckoutMarketingService::class);

    $metadata = $service->build($package, [
        'hub.enabled' => 'true',
        'wall.enabled' => 'true',
        'play.enabled' => 'true',
        'media.max_photos' => '800',
        'media.retention_days' => '180',
    ]);

    expect($metadata['slug'])->toBe('premium-event');
    expect($metadata['subtitle'])->toBe('Pacote pensado para uma compra rapida e sem complicacao.');
    expect($metadata['ideal_for'])->toBe('Eventos que querem experiencia mais interativa para os convidados.');
    expect($metadata['badge'])->toBeNull();
    expect($metadata['recommended'])->toBeFalse();
    expect($metadata['benefits'])->toBe([
        'Telao ao vivo para os convidados',
        'Pagina do evento pronta para compartilhar',
        'Experiencias interativas para engajar o publico',
        'Ate 800 fotos no evento',
        'Memorias disponiveis por 180 dias',
    ]);
});
