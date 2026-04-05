<?php

namespace App\Modules\Play\Queries;

use App\Modules\Play\Models\PlayGameSession;
use App\Shared\Concerns\HasPortableLike;
use App\Shared\Contracts\QueryInterface;
use Illuminate\Database\Eloquent\Builder;

class ListPlaySessionsQuery implements QueryInterface
{
    use HasPortableLike;

    public function __construct(
        protected int $eventId,
        protected ?int $playGameId = null,
        protected ?string $dateFrom = null,
        protected ?string $dateTo = null,
        protected ?string $status = null,
        protected ?string $search = null,
    ) {}

    public function query(): Builder
    {
        $query = PlayGameSession::query()
            ->with([
                'eventGame:id,event_id,game_type_id,title,slug,uuid,is_active,ranking_enabled',
                'eventGame.gameType:id,key,name',
            ])
            ->withCount('moves')
            ->whereHas('eventGame', fn (Builder $gameQuery) => $gameQuery->where('event_id', $this->eventId));

        if ($this->playGameId) {
            $query->where('event_game_id', $this->playGameId);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->dateFrom) {
            $query->whereDate('started_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('started_at', '<=', $this->dateTo);
        }

        if ($this->search) {
            $like = $this->likeOperator();

            $query->where(function (Builder $sessionQuery) use ($like) {
                $sessionQuery
                    ->where('player_name', $like, "%{$this->search}%")
                    ->orWhere('player_identifier', $like, "%{$this->search}%");
            });
        }

        return $query;
    }
}
