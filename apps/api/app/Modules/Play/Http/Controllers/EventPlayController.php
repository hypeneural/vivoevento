<?php
namespace App\Modules\Play\Http\Controllers;
use App\Modules\Play\Models\EventPlaySetting;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventPlayController extends BaseController
{
    public function show(int $event): JsonResponse
    {
        $settings = EventPlaySetting::firstOrCreate(['event_id' => $event]);
        return $this->success($settings);
    }

    public function update(Request $request, int $event): JsonResponse
    {
        $settings = EventPlaySetting::firstOrCreate(['event_id' => $event]);
        $settings->update($request->all());
        return $this->success($settings->fresh());
    }

    public function generateMemory(int $event): JsonResponse
    {
        $settings = EventPlaySetting::firstOrCreate(['event_id' => $event]);

        // TODO: dispatch job to generate memory card images from approved media
        // GenerateMemoryAssetsJob::dispatch($event, $settings->memory_card_count);

        return $this->success([
            'message' => 'Geração do jogo da memória iniciada',
            'card_count' => $settings->memory_card_count,
        ]);
    }

    public function generatePuzzle(int $event): JsonResponse
    {
        $settings = EventPlaySetting::firstOrCreate(['event_id' => $event]);

        // TODO: dispatch job to generate puzzle pieces from featured media
        // GeneratePuzzleAssetsJob::dispatch($event, $settings->puzzle_piece_count);

        return $this->success([
            'message' => 'Geração do puzzle iniciada',
            'piece_count' => $settings->puzzle_piece_count,
        ]);
    }
}
