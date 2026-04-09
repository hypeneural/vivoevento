<?php

namespace App\Modules\MediaIntelligence\Http\Controllers;

use App\Modules\ContentModeration\Models\ContentModerationGlobalSetting;
use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Http\Requests\ListMediaReplyEventHistoryRequest;
use App\Modules\MediaIntelligence\Http\Resources\MediaReplyEventHistoryResource;
use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;
use App\Modules\MediaProcessing\Enums\MediaDecisionSource;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
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
                'event:id,title,moderation_mode',
                'event.contentModerationSettings',
                'event.mediaIntelligenceSettings',
                'inboundMessage:id,event_id,trace_id,message_id,message_type,sender_name,sender_phone,sender_external_id',
                'latestSafetyEvaluation',
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

        if ($request->filled('reason_code')) {
            $reasonCode = (string) $request->string('reason_code');

            $query->whereHas('latestVlmEvaluation', fn (Builder $evaluationQuery) => $evaluationQuery->where('reason_code', $reasonCode));
        }

        if ($request->filled('publish_eligibility')) {
            $publishEligibility = (string) $request->string('publish_eligibility');

            $query->whereHas('latestVlmEvaluation', fn (Builder $evaluationQuery) => $evaluationQuery->where('publish_eligibility', $publishEligibility));
        }

        if ($request->filled('effective_media_state')) {
            $this->applyEffectiveStateFilter($query, (string) $request->string('effective_media_state'));
        }

        $runs = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated(MediaReplyEventHistoryResource::collection($runs));
    }

    public function show(EventMedia $historicoEvento): JsonResponse
    {
        $historicoEvento->loadMissing([
            'event:id,title,moderation_mode',
            'event.contentModerationSettings',
            'event.mediaIntelligenceSettings',
            'inboundMessage:id,event_id,trace_id,message_id,message_type,sender_name,sender_phone,sender_external_id',
            'latestSafetyEvaluation',
            'latestVlmEvaluation',
            'latestVlmRun',
            'variants',
        ]);

        return $this->success(new MediaReplyEventHistoryResource($historicoEvento));
    }

    private function applyEffectiveStateFilter(Builder $query, string $state): void
    {
        match ($state) {
            'published' => $this->applyPublishedStateFilter($query),
            'approved' => $this->applyApprovedStateFilter($query),
            'pending_moderation' => $this->applyPendingModerationStateFilter($query),
            'rejected' => $this->applyRejectedStateFilter($query),
            default => null,
        };
    }

    private function applyPublishedStateFilter(Builder $query): void
    {
        $query
            ->where('moderation_status', ModerationStatus::Approved->value)
            ->where('publication_status', PublicationStatus::Published->value)
            ->where(function (Builder $builder): void {
                $builder
                    ->where('vlm_status', 'completed')
                    ->orWhere(function (Builder $nonBlockingContext): void {
                        $nonBlockingContext->where(function (Builder $blockedContext): void {
                            $this->applyBlockingContextFilter($blockedContext, ['completed']);
                        });
                    });
            })
            ->where(function (Builder $builder): void {
                $builder
                    ->where('safety_status', 'pass')
                    ->orWhere(function (Builder $nonBlockingSafety): void {
                        $nonBlockingSafety->where(function (Builder $blockedSafety): void {
                            $this->applyBlockingSafetyFilter($blockedSafety, ['pass']);
                        });
                    });
            })
            ->where(function (Builder $builder): void {
                $builder
                    ->where('decision_source', '!=', MediaDecisionSource::UserOverride->value)
                    ->orWhere('moderation_status', '!=', ModerationStatus::Rejected->value);
            });
    }

    private function applyApprovedStateFilter(Builder $query): void
    {
        $query
            ->where('moderation_status', ModerationStatus::Approved->value)
            ->where('publication_status', '!=', PublicationStatus::Published->value)
            ->where('publication_status', '!=', PublicationStatus::Hidden->value)
            ->where(function (Builder $builder): void {
                $builder
                    ->where('vlm_status', 'completed')
                    ->orWhere(function (Builder $nonBlockingContext): void {
                        $nonBlockingContext->where(function (Builder $blockedContext): void {
                            $this->applyBlockingContextFilter($blockedContext, ['completed']);
                        });
                    });
            })
            ->where(function (Builder $builder): void {
                $builder
                    ->where('safety_status', 'pass')
                    ->orWhere(function (Builder $nonBlockingSafety): void {
                        $nonBlockingSafety->where(function (Builder $blockedSafety): void {
                            $this->applyBlockingSafetyFilter($blockedSafety, ['pass']);
                        });
                    });
            });
    }

    private function applyPendingModerationStateFilter(Builder $query): void
    {
        $query->where(function (Builder $builder): void {
            $builder
                ->where('moderation_status', ModerationStatus::Pending->value)
                ->orWhere(function (Builder $safetyPending): void {
                    $this->applyBlockingSafetyFilter($safetyPending, ['queued', 'review', 'failed'], true);
                })
                ->orWhere(function (Builder $contextPending): void {
                    $this->applyBlockingContextFilter($contextPending, ['queued', 'review', 'failed'], true);
                });
        });
    }

    private function applyRejectedStateFilter(Builder $query): void
    {
        $query->where(function (Builder $builder): void {
            $builder
                ->where(function (Builder $operatorRejected): void {
                    $operatorRejected
                        ->where('decision_source', MediaDecisionSource::UserOverride->value)
                        ->where('moderation_status', ModerationStatus::Rejected->value);
                })
                ->orWhere('moderation_status', ModerationStatus::Rejected->value)
                ->orWhere(function (Builder $safetyRejected): void {
                    $this->applyBlockingSafetyFilter($safetyRejected, ['block']);
                })
                ->orWhere(function (Builder $contextRejected): void {
                    $this->applyBlockingContextFilter($contextRejected, ['rejected']);
                });
        });
    }

    private function applyBlockingSafetyFilter(Builder $query, array $statuses, bool $includeNull = false): void
    {
        $global = ContentModerationGlobalSetting::query()->firstOrNew(
            ['id' => 1],
            ContentModerationGlobalSetting::defaultAttributes(),
        );

        $query
            ->where('media_type', 'image')
            ->where(function (Builder $statusQuery) use ($statuses, $includeNull): void {
                if ($includeNull) {
                    $statusQuery
                        ->whereNull('safety_status')
                        ->orWhereIn('safety_status', $statuses);

                    return;
                }

                $statusQuery->whereIn('safety_status', $statuses);
            })
            ->whereHas('event', function (Builder $eventQuery) use ($global): void {
                $eventQuery
                    ->where('moderation_mode', 'ai')
                    ->where(function (Builder $blockingQuery) use ($global): void {
                        $blockingQuery->whereHas('contentModerationSettings', function (Builder $settingsQuery): void {
                            $settingsQuery
                                ->where('enabled', true)
                                ->where('mode', '!=', 'observe_only');
                        });

                        if (($global->enabled ?? false) && $global->mode !== 'observe_only') {
                            $blockingQuery->orWhereDoesntHave('contentModerationSettings');
                        }
                    });
            });
    }

    private function applyBlockingContextFilter(Builder $query, array $statuses, bool $includeNull = false): void
    {
        $global = MediaIntelligenceGlobalSetting::query()->firstOrNew(
            ['id' => 1],
            MediaIntelligenceGlobalSetting::defaultAttributes(),
        );

        $query
            ->where('media_type', 'image')
            ->where(function (Builder $statusQuery) use ($statuses, $includeNull): void {
                if ($includeNull) {
                    $statusQuery
                        ->whereNull('vlm_status')
                        ->orWhereIn('vlm_status', $statuses);

                    return;
                }

                $statusQuery->whereIn('vlm_status', $statuses);
            })
            ->whereHas('event', function (Builder $eventQuery) use ($global): void {
                $eventQuery
                    ->where('moderation_mode', 'ai')
                    ->where(function (Builder $blockingQuery) use ($global): void {
                        $blockingQuery->whereHas('mediaIntelligenceSettings', function (Builder $settingsQuery): void {
                            $settingsQuery
                                ->where('enabled', true)
                                ->where('mode', 'gate');
                        });

                        if (($global->enabled ?? false) && $global->mode === 'gate') {
                            $blockingQuery->orWhereDoesntHave('mediaIntelligenceSettings');
                        }
                    });
            });
    }
}
