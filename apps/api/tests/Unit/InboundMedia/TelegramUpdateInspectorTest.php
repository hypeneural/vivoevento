<?php

use App\Modules\InboundMedia\Support\TelegramUpdateInspector;

it('derives the webhook and message identity keys from official telegram identifiers', function () {
    $inspector = new TelegramUpdateInspector();

    $result = $inspector->inspect([
        'update_id' => 987654321,
        'message' => [
            'message_id' => 81,
            'media_group_id' => 'album-42',
            'from' => [
                'id' => 9007199254740991,
                'is_bot' => false,
                'first_name' => 'Ana',
            ],
            'chat' => [
                'id' => 9007199254740991,
                'type' => 'private',
            ],
            'date' => 1775461200,
            'text' => '/start EVT_ABC123',
            'entities' => [
                ['offset' => 0, 'length' => 6, 'type' => 'bot_command'],
            ],
        ],
    ]);

    expect($result['update_key'])->toBe('987654321')
        ->and($result['message_key'])->toBe('9007199254740991:81')
        ->and($result['chat_id'])->toBe('9007199254740991')
        ->and($result['sender_id'])->toBe('9007199254740991')
        ->and($result['sender_name'])->toBe('Ana')
        ->and($result['message_id'])->toBe('81')
        ->and($result['message_thread_id'])->toBeNull()
        ->and($result['media_group_id'])->toBe('album-42')
        ->and($result['message_type'])->toBe('text')
        ->and($result['text'])->toBe('/start EVT_ABC123');
});

it('keeps telegram message types aligned with the Bot API instead of collapsing them into generic labels', function () {
    $inspector = new TelegramUpdateInspector();

    $text = $inspector->inspect([
        'update_id' => 1,
        'message' => [
            'message_id' => 10,
            'chat' => ['id' => 111, 'type' => 'private'],
            'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Ana'],
            'text' => 'Ola',
        ],
    ]);

    $video = $inspector->inspect([
        'update_id' => 2,
        'message' => [
            'message_id' => 11,
            'chat' => ['id' => 111, 'type' => 'private'],
            'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Ana'],
            'video' => ['file_id' => 'VID_001', 'file_unique_id' => 'VID_UNIQ_001'],
        ],
    ]);

    $document = $inspector->inspect([
        'update_id' => 3,
        'message' => [
            'message_id' => 12,
            'chat' => ['id' => 111, 'type' => 'private'],
            'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Ana'],
            'document' => ['file_id' => 'DOC_001', 'file_unique_id' => 'DOC_UNIQ_001'],
        ],
    ]);

    $voice = $inspector->inspect([
        'update_id' => 4,
        'message' => [
            'message_id' => 13,
            'chat' => ['id' => 111, 'type' => 'private'],
            'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Ana'],
            'voice' => ['file_id' => 'VOICE_001', 'file_unique_id' => 'VOICE_UNIQ_001'],
        ],
    ]);

    $audio = $inspector->inspect([
        'update_id' => 5,
        'message' => [
            'message_id' => 14,
            'chat' => ['id' => 111, 'type' => 'private'],
            'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Ana'],
            'audio' => ['file_id' => 'AUDIO_001', 'file_unique_id' => 'AUDIO_UNIQ_001'],
        ],
    ]);

    expect($text['message_type'])->toBe('text')
        ->and($video['message_type'])->toBe('video')
        ->and($document['message_type'])->toBe('document')
        ->and($voice['message_type'])->toBe('voice')
        ->and($audio['message_type'])->toBe('audio');
});

it('selects the largest telegram photo size and preserves file_unique_id plus caption metadata', function () {
    $inspector = new TelegramUpdateInspector();

    $result = $inspector->inspect([
        'update_id' => 124,
        'message' => [
            'message_id' => 81,
            'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Ana'],
            'chat' => ['id' => 111, 'type' => 'private'],
            'date' => 1775461210,
            'photo' => [
                [
                    'file_id' => 'AAA_small',
                    'file_unique_id' => 'U_small',
                    'width' => 90,
                    'height' => 90,
                    'file_size' => 1200,
                ],
                [
                    'file_id' => 'AAA_big',
                    'file_unique_id' => 'U_big',
                    'width' => 1080,
                    'height' => 1350,
                    'file_size' => 245000,
                ],
            ],
            'caption' => 'Evento ao vivo',
            'caption_entities' => [
                ['offset' => 0, 'length' => 6, 'type' => 'bold'],
            ],
        ],
    ]);

    expect($result['message_type'])->toBe('photo')
        ->and($result['file_id'])->toBe('AAA_big')
        ->and($result['file_unique_id'])->toBe('U_big')
        ->and($result['width'])->toBe(1080)
        ->and($result['height'])->toBe(1350)
        ->and($result['file_size'])->toBe(245000)
        ->and($result['caption'])->toBe('Evento ao vivo')
        ->and($result['caption_entities'])->toHaveCount(1);
});
