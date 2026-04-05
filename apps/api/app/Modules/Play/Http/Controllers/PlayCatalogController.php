<?php

namespace App\Modules\Play\Http\Controllers;

use App\Modules\Play\Http\Resources\PlayGameTypeResource;
use App\Modules\Play\Services\GameCatalogService;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class PlayCatalogController extends BaseController
{
    public function index(GameCatalogService $catalog): JsonResponse
    {
        return $this->success(
            PlayGameTypeResource::collection($catalog->catalog()),
        );
    }
}
