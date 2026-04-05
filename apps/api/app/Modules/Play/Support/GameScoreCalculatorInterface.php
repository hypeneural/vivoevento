<?php

namespace App\Modules\Play\Support;

use App\Modules\Play\DTOs\FinishGameSessionDTO;
use App\Modules\Play\Models\PlayGameSession;

interface GameScoreCalculatorInterface
{
    public function calculate(PlayGameSession $session, FinishGameSessionDTO $dto): array;
}
