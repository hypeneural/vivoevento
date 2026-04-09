<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Actions\PromoteEventFaceSearchFallbackAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class PromoteAwsFallbackRolloutCommand extends Command
{
    protected $signature = 'face-search:promote-aws-fallback
        {event_ids* : Stable event IDs to promote (max 3)}
        {--sync-index : Reindex event images synchronously before promotion}
        {--sync-reconcile : Reconcile the AWS collection synchronously before promotion}
        {--report-dir= : Optional absolute report directory override}';

    protected $description = 'Promote 1 to 3 stable events from aws_primary_local_shadow to aws_primary_local_fallback with rollback-ready reporting.';

    public function handle(PromoteEventFaceSearchFallbackAction $action): int
    {
        $eventIds = array_values(array_unique(array_map('intval', (array) $this->argument('event_ids'))));

        if ($eventIds === [] || count($eventIds) > 3) {
            $this->error('Informe de 1 a 3 event IDs para a promocao controlada.');

            return self::FAILURE;
        }

        $report = [
            'report_version' => 1,
            'executed_at' => now()->toIso8601String(),
            'command' => 'face-search:promote-aws-fallback',
            'event_ids' => $eventIds,
            'sync_index' => (bool) $this->option('sync-index'),
            'sync_reconcile' => (bool) $this->option('sync-reconcile'),
            'events' => [],
        ];

        foreach ($eventIds as $eventId) {
            $event = Event::query()->with('faceSearchSettings')->find($eventId);

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
                    syncIndex: (bool) $this->option('sync-index'),
                    syncReconcile: (bool) $this->option('sync-reconcile'),
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
            'promoted' => collect($report['events'])->where('status', 'promoted')->count(),
            'rolled_back' => collect($report['events'])->where('status', 'rolled_back')->count(),
            'skipped' => collect($report['events'])->where('status', 'skipped')->count(),
            'failed' => collect($report['events'])->where('status', 'failed')->count(),
        ];

        $reportPath = $this->storeReport($report + ['summary' => $summary]);

        $report['summary'] = $summary + [
            'report_path' => $reportPath,
            'rollback_command' => sprintf('php artisan face-search:rollback-aws-fallback --report="%s"', $reportPath),
        ];

        File::put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->info("Report saved to {$reportPath}");
        $this->line(sprintf(
            'promoted=%d skipped=%d rolled_back=%d failed=%d',
            $report['summary']['promoted'],
            $report['summary']['skipped'],
            $report['summary']['rolled_back'],
            $report['summary']['failed'],
        ));
        $this->line($report['summary']['rollback_command']);

        return $report['summary']['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function storeReport(array $report): string
    {
        $directory = (string) ($this->option('report-dir') ?: storage_path('app/private/face-search-rollout'));

        File::ensureDirectoryExists($directory);

        $path = $directory . DIRECTORY_SEPARATOR . sprintf(
            '%s-face-search-aws-fallback-rollout.json',
            now()->format('Ymd-His'),
        );

        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
