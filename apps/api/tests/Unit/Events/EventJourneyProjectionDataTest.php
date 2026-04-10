<?php

use App\Modules\Events\Data\EventJourneyBranchData;
use App\Modules\Events\Data\EventJourneyCapabilityData;
use App\Modules\Events\Data\EventJourneyNodeData;
use App\Modules\Events\Data\EventJourneyProjectionData;
use App\Modules\Events\Data\EventJourneyScenarioData;
use App\Modules\Events\Data\EventJourneyStageData;

it('serializes the journey projection contract with stable operational fields', function () {
    $projection = new EventJourneyProjectionData(
        version: 'journey-builder-v1',
        event: [
            'id' => 10,
            'title' => 'Casamento Ana e Pedro',
            'moderation_mode' => 'manual',
            'modules' => ['live' => true, 'wall' => false],
        ],
        intake_defaults: [
            'whatsapp_instance_id' => null,
            'whatsapp_instance_mode' => 'shared',
        ],
        intake_channels: [
            'whatsapp_direct' => ['enabled' => true],
        ],
        settings: [
            'moderation_mode' => 'manual',
        ],
        capabilities: [
            'supports_print' => new EventJourneyCapabilityData(
                id: 'supports_print',
                label: 'Impressao',
                enabled: false,
                available: false,
                editable: false,
                reason: 'Fora da V1',
            ),
        ],
        stages: [
            new EventJourneyStageData(
                id: 'entry',
                label: 'Entrada',
                description: 'Como a midia chega ao evento.',
                position: 0,
                nodes: [
                    new EventJourneyNodeData(
                        id: 'entry_whatsapp_direct',
                        stage: 'entry',
                        kind: 'entry',
                        label: 'WhatsApp privado',
                        description: 'Recebe fotos e videos por conversa privada.',
                        active: true,
                        editable: true,
                        status: 'active',
                        summary: 'Recebe midias por codigo privado.',
                        config_preview: ['enabled' => true],
                        branches: [
                            new EventJourneyBranchData(
                                id: 'default',
                                label: 'Padrao',
                                target_node_id: 'processing_receive_feedback',
                            ),
                        ],
                    ),
                ],
            ),
        ],
        warnings: [],
        simulation_presets: [
            new EventJourneyScenarioData(
                id: 'photo_whatsapp_private_with_caption',
                label: 'Foto com legenda',
                description: 'Simula uma foto recebida pelo WhatsApp privado com legenda.',
                input: ['media_type' => 'photo'],
                expected_node_ids: ['entry_whatsapp_direct'],
            ),
        ],
        summary: [
            'human_text' => 'Quando uma foto chega, o Evento Vivo envia para revisao manual.',
        ],
    );

    $payload = $projection->toArray();

    expect($payload)
        ->toHaveKeys([
            'version',
            'event',
            'intake_defaults',
            'intake_channels',
            'settings',
            'capabilities',
            'stages',
            'warnings',
            'simulation_presets',
            'summary',
        ])
        ->and($payload['stages'][0]['id'])->toBe('entry')
        ->and($payload['stages'][0]['nodes'][0]['branches'][0]['id'])->toBe('default')
        ->and($payload['capabilities']['supports_print']['available'])->toBeFalse()
        ->and($payload['simulation_presets'][0]['expected_node_ids'])->toBe(['entry_whatsapp_direct']);
});
