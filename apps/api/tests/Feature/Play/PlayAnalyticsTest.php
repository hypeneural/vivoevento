<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\Play\Enums\PlayGameSessionStatus;
use App\Modules\Play\Models\EventPlaySetting;
use App\Modules\Play\Models\PlayEventGame;
use App\Modules\Play\Models\PlayGameMove;
use App\Modules\Play\Models\PlayGameSession;
use App\Modules\Play\Models\PlayGameType;

it('returns administrative analytics for play sessions grouped by game and timeline', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'play',
        'is_enabled' => true,
    ]);

    EventPlaySetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'ranking_enabled' => true,
    ]);

    $memoryType = PlayGameType::factory()->create([
        'key' => 'memory',
        'name' => 'Jogo da Memoria',
    ]);

    $puzzleType = PlayGameType::factory()->puzzle()->create();

    $memoryGame = PlayEventGame::factory()->create([
        'event_id' => $event->id,
        'game_type_id' => $memoryType->id,
        'title' => 'Memory Principal',
        'slug' => 'memory-principal',
        'sort_order' => 1,
    ]);

    $puzzleGame = PlayEventGame::factory()->create([
        'event_id' => $event->id,
        'game_type_id' => $puzzleType->id,
        'title' => 'Puzzle Principal',
        'slug' => 'puzzle-principal',
        'sort_order' => 2,
    ]);

    $sessionOne = PlayGameSession::factory()->finished()->create([
        'event_game_id' => $memoryGame->id,
        'player_identifier' => 'player-1',
        'player_name' => 'Ana',
        'started_at' => now()->subDay()->setTime(10, 0),
        'finished_at' => now()->subDay()->setTime(10, 2),
        'result_json' => [
            'score' => 910,
            'completed' => true,
            'time_ms' => 40000,
            'moves' => 14,
            'mistakes' => 2,
            'accuracy' => 0.86,
        ],
    ]);

    $sessionTwo = PlayGameSession::factory()->finished()->create([
        'event_game_id' => $memoryGame->id,
        'player_identifier' => 'player-2',
        'player_name' => 'Bruno',
        'started_at' => now()->setTime(14, 0),
        'finished_at' => now()->setTime(14, 3),
        'result_json' => [
            'score' => 940,
            'completed' => true,
            'time_ms' => 38000,
            'moves' => 12,
            'mistakes' => 1,
            'accuracy' => 0.91,
        ],
    ]);

    $sessionThree = PlayGameSession::factory()->create([
        'event_game_id' => $puzzleGame->id,
        'player_identifier' => 'player-3',
        'player_name' => 'Carlos',
        'status' => PlayGameSessionStatus::Started->value,
        'started_at' => now()->setTime(16, 0),
        'finished_at' => null,
        'result_json' => [],
    ]);

    foreach ([
        [$sessionOne, 1, 'flip'],
        [$sessionOne, 2, 'match'],
        [$sessionTwo, 1, 'flip'],
        [$sessionTwo, 2, 'match'],
        [$sessionTwo, 3, 'match'],
        [$sessionThree, 1, 'drop'],
    ] as [$session, $moveNumber, $moveType]) {
        PlayGameMove::query()->create([
            'game_session_id' => $session->id,
            'move_number' => $moveNumber,
            'move_type' => $moveType,
            'payload_json' => [],
            'occurred_at' => $session->started_at->copy()->addSeconds($moveNumber),
            'created_at' => $session->started_at->copy()->addSeconds($moveNumber),
        ]);
    }

    $response = $this->apiGet("/events/{$event->id}/play/analytics?session_limit=2");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.summary.total_sessions', 3)
        ->assertJsonPath('data.summary.finished_sessions', 2)
        ->assertJsonPath('data.summary.active_sessions', 1)
        ->assertJsonPath('data.summary.unique_players', 3)
        ->assertJsonPath('data.summary.total_moves', 6)
        ->assertJsonPath('data.games.0.game.title', 'Memory Principal')
        ->assertJsonPath('data.games.0.analytics.total_sessions', 2)
        ->assertJsonPath('data.games.1.game.title', 'Puzzle Principal')
        ->assertJsonPath('data.games.1.analytics.active_sessions', 1)
        ->assertJsonPath('data.recent_sessions.0.game.title', 'Puzzle Principal')
        ->assertJsonPath('data.recent_sessions.0.move_count', 1)
        ->assertJsonPath('data.recent_sessions.1.game.title', 'Memory Principal')
        ->assertJsonCount(2, 'data.timeline');
});
