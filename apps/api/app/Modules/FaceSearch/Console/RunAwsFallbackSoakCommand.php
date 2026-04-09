<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Actions\RunAwsFallbackSoakAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class RunAwsFallbackSoakCommand extends Command
{
    protected $signature = 'face-search:soak-aws-fallback
        {event_ids* : Promoted event IDs to observe (max 3)}
        {--queries-per-event=2 : Short soak query count per event}
        {--report-dir= : Optional absolute report directory override}';

    protected $description = 'Run a short soak on aws_primary_local_fallback events and capture health, fallback, latency and drift.';

    public function handle(RunAwsFallbackSoakAction $action): int
    {
        $eventIds = array_values(array_unique(array_map('intval', (array) $this->argument('event_ids'))));

        if ($eventIds === [] || count($eventIds) > 3) {
            $this->error('Informe de 1 a 3 event IDs para o soak curto.');

            return self::FAILURE;
        }

        $queriesPerEvent = max(1, (int) $this->option('queries-per-event'));
        $report = [
            'report_version' => 1,
            'executed_at' => now()->toIso8601String(),
            'command' => 'face-search:soak-aws-fallback',
            'event_ids' => $eventIds,
            'queries_per_event' => $queriesPerEvent,
            'events' => [],
        ];

        foreach ($eventIds as $eventId) {
            $event = Event::query()->find($eventId);

            if (! $event) {
                $report['events'][] = [
                    'status' => 'failed',
                    'event_id' => $eventId,
                    'message' => 'Event not found.',
                ];
                $this->warn("Evento {$eventId} nao encontrado.");

                continue;
            }

            try {
                $result = $action->execute(
                    event: $event,
                    queriesPerEvent: $queriesPerEvent,
                    reconcileBefore: true,
                    reconcileAfter: true,
                );
            } catch (Throwable $exception) {
                $result = [
                    'status' => 'failed',
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'message' => $exception->getMessage(),
                    'exception_class' => $exception::class,
                ];
            }

            $report['events'][] = $result;
            $this->line(sprintf('[%s] evento=%d %s', $result['status'] ?? 'unknown', $event->id, $event->title));
        }

        $summary = [
            'completed' => collect($report['events'])->where('status', 'completed')->count(),
            'skipped' => collect($report['events'])->where('status', 'skipped')->count(),
            'failed' => collect($report['events'])->where('status', 'failed')->count(),
            'avg_fallback_rate' => round((float) collect($report['events'])->avg('metrics.fallback_rate'), 6),
            'avg_response_duration_ms' => round((float) collect($report['events'])->avg('metrics.avg_response_duration_ms'), 2),
            'events_with_drift_after' => collect($report['events'])->filter(
                fn (array $event): bool => (bool) data_get($event, 'metrics.drift_detected_after', false)
            )->count(),
        ];

        $reportPath = $this->storeReport($report + ['summary' => $summary]);
        $report['summary'] = $summary + ['report_path' => $reportPath];

        File::put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->info("Report saved to {$reportPath}");
        $this->line(sprintf(
            'completed=%d skipped=%d failed=%d avg_fallback_rate=%s avg_response_duration_ms=%s drift_after=%d',
            $report['summary']['completed'],
            $report['summary']['skipped'],
            $report['summary']['failed'],
            number_format((float) $report['summary']['avg_fallback_rate'], 6, '.', ''),
            number_format((float) $report['summary']['avg_response_duration_ms'], 2, '.', ''),
            $report['summary']['events_with_drift_after'],
        ));

        return $report['summary']['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function storeReport(array $report): string
    {
        $directory = (string) ($this->option('report-dir') ?: storage_path('app/private/face-search-soak'));

        File::ensureDirectoryExists($directory);

        $path = $directory . DIRECTORY_SEPARATOR . sprintf(
            '%s-face-search-aws-fallback-soak.json',
            now()->format('Ymd-His'),
        );

        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
