<?php

namespace App\Modules\MediaIntelligence\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Http\Requests\ListMediaReplyEventHistoryRequest;
use App\Modules\MediaIntelligence\Http\Resources\MediaReplyEventHistoryResource;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Shared\Http\BaseController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class MediaReplyEventHistoryController extends BaseController
{
    public function events(): JsonResponse
    {
        $events = Event::query()
            ->select(['id', 'title'])
            ->whereHas('media', function (Builder $query): void {
                $query->where(function (Builder $mediaQuery): void {
                    $mediaQuery
                        ->whereIn('vlm_status', ['completed', 'review', 'rejected', 'failed', 'skipped'])
                        ->orWhereHas('latestVlmEvaluation')
                        ->orWhereHas('latestVlmRun', fn (Builder $runQuery) => $runQuery->where('stage_key', 'vlm'));
                });
            })
            ->orderBy('title')
            ->get()
            ->map(fn (Event $event): array => [
                'id' => $event->id,
                'title' => $event->title,
            ])
            ->values()
            ->all();

        return $this->success($events);
    }

    public function index(ListMediaReplyEventHistoryRequest $request): JsonResponse
    {
        $query = EventMedia::query()
            ->with([
                'event:id,title',
                'inboundMessage:id,event_id,trace_id,message_id,message_type,sender_name,sender_phone,sender_external_id',
                'latestVlmEvaluation',
                'latestVlmRun',
                'variants',
            ])
            ->where(function (Builder $builder): void {
                $builder
                    ->whereIn('vlm_status', ['completed', 'review', 'rejected', 'failed', 'skipped'])
                    ->orWhereHas('latestVlmEvaluation')
                    ->orWhereHas('latestVlmRun', fn (Builder $runQuery) => $runQuery->where('stage_key', 'vlm'));
            })
            ->latest('id');

        if ($request->filled('event_id')) {
            $query->where('event_id', (int) $request->integer('event_id'));
        }

        if ($request->filled('provider_key')) {
            $providerKey = (string) $request->string('provider_key');

            $query->where(function (Builder $builder) use ($providerKey): void {
                $builder
                    ->whereHas('latestVlmEvaluation', fn (Builder $evaluationQuery) => $evaluationQuery->where('provider_key', $providerKey))
                    ->orWhereHas('latestVlmRun', fn (Builder $runQuery) => $runQuery->where('provider_key', $providerKey));
            });
        }

        if ($request->filled('model_key')) {
            $modelKey = (string) $request->string('model_key');

            $query->where(function (Builder $builder) use ($modelKey): void {
                $builder
                    ->whereHas('latestVlmEvaluation', fn (Builder $evaluationQuery) => $evaluationQuery->where('model_key', $modelKey))
                    ->orWhereHas('latestVlmRun', fn (Builder $runQuery) => $runQuery->where('model_key', $modelKey));
            });
        }

        if ($request->filled('status')) {
            $status = (string) $request->string('status');

            if ($status === 'success') {
                $query->whereIn('vlm_status', ['completed', 'review', 'rejected']);
            } elseif ($status === 'failed') {
                $query->where('vlm_status', 'failed');
            } else {
                $query->where('vlm_status', $status);
            }
        }

        if ($request->filled('preset_name')) {
            $presetName = mb_strtolower((string) $request->string('preset_name'));

            $query->whereHas('latestVlmEvaluation', function (Builder $evaluationQuery) use ($presetName): void {
                $evaluationQuery->whereRaw(
                    "LOWER(COALESCE(prompt_context_json->>'preset_name', '')) LIKE ?",
                    ['%' . $presetName . '%']
                );
            });
        }

        if ($request->filled('sender_query')) {
            $senderQuery = mb_strtolower((string) $request->string('sender_query'));

            $query->whereHas('inboundMessage', function (Builder $inboundQuery) use ($senderQuery): void {
                $like = '%' . $senderQuery . '%';

                $inboundQuery->where(function (Builder $builder) use ($like): void {
                    $builder
                        ->whereRaw('LOWER(COALESCE(sender_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(sender_phone, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(sender_external_id, \'\')) LIKE ?', [$like]);
                });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', (string) $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', (string) $request->string('date_to'));
        }

        $runs = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated(MediaReplyEventHistoryResource::collection($runs));
    }

    public function show(EventMedia $historicoEvento): JsonResponse
    {
        $historicoEvento->loadMissing([
            'event:id,title',
            'inboundMessage:id,event_id,trace_id,message_id,message_type,sender_name,sender_phone,sender_external_id',
            'latestVlmEvaluation',
            'latestVlmRun',
            'variants',
        ]);

        return $this->success(new MediaReplyEventHistoryResource($historicoEvento));
    }
}
