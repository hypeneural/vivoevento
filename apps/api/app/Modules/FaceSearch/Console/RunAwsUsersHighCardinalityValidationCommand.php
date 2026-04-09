<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Actions\RunAwsUsersHighCardinalityValidationAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class RunAwsUsersHighCardinalityValidationCommand extends Command
{
    protected $signature = 'face-search:validate-aws-users-high-cardinality
        {event_id : Event ID already configured for aws_search_mode=users}
        {--sample-users=40 : Number of ready user vectors to probe}
        {--min-ready-users=500 : Minimum ready user vectors required for pass}
        {--target-ready-users=2000 : Informational high-cardinality target for the event}
        {--max-fallback-rate=0.05 : Maximum acceptable fallback rate}
        {--min-users-mode-resolution-rate=0.95 : Minimum acceptable users-mode resolution rate}
        {--min-top1-match-rate=0.85 : Minimum acceptable top-1 match rate}
        {--min-topk-match-rate=0.95 : Minimum acceptable top-k match rate}
        {--max-p95-latency-ms=1500 : Maximum acceptable p95 response duration}
        {--report-dir= : Optional absolute report directory override}';

    protected $description = 'Validate aws_search_mode=users on a high-cardinality event with objective latency, fallback and match-rate criteria.';

    public function handle(RunAwsUsersHighCardinalityValidationAction $action): int
    {
        $eventId = (int) $this->argument('event_id');
        $event = Event::query()->find($eventId);

        if (! $event) {
            $this->error("Evento {$eventId} nao encontrado.");

            return self::FAILURE;
        }

        $criteria = [
            'sample_users' => max(1, (int) $this->option('sample-users')),
            'min_ready_users' => max(1, (int) $this->option('min-ready-users')),
            'target_ready_users' => max(0, (int) $this->option('target-ready-users')),
            'max_fallback_rate' => round((float) $this->option('max-fallback-rate'), 6),
            'min_users_mode_resolution_rate' => round((float) $this->option('min-users-mode-resolution-rate'), 6),
            'min_top_1_match_rate' => round((float) $this->option('min-top1-match-rate'), 6),
            'min_top_k_match_rate' => round((float) $this->option('min-topk-match-rate'), 6),
            'max_p95_latency_ms' => max(1, (int) $this->option('max-p95-latency-ms')),
        ];

        try {
            $report = $action->execute(
                event: $event,
                sampleUsers: $criteria['sample_users'],
                minReadyUsers: $criteria['min_ready_users'],
                targetReadyUsers: $criteria['target_ready_users'] > 0 ? $criteria['target_ready_users'] : null,
                maxFallbackRate: $criteria['max_fallback_rate'],
                minUsersModeResolutionRate: $criteria['min_users_mode_resolution_rate'],
                minTop1MatchRate: $criteria['min_top_1_match_rate'],
                minTopKMatchRate: $criteria['min_top_k_match_rate'],
                maxP95LatencyMs: $criteria['max_p95_latency_ms'],
                reconcileBefore: true,
                reconcileAfter: true,
            );
        } catch (Throwable $exception) {
            $report = [
                'status' => 'failed',
                'event_id' => $event->id,
                'event_title' => $event->title,
                'message' => $exception->getMessage(),
                'exception_class' => $exception::class,
            ];
        }

        $payload = [
            'report_version' => 1,
            'executed_at' => now()->toIso8601String(),
            'command' => 'face-search:validate-aws-users-high-cardinality',
            'event_id' => $event->id,
            'event_title' => $event->title,
            'requested_criteria' => $criteria,
            'report' => $report,
        ];

        $reportPath = $this->storeReport($payload);
        $payload['report_path'] = $reportPath;
        File::put($reportPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $metrics = is_array($report['metrics'] ?? null) ? $report['metrics'] : [];
        $criteriaEvaluation = is_array($report['criteria_evaluation'] ?? null) ? $report['criteria_evaluation'] : [];

        $this->info("Report saved to {$reportPath}");
        $this->line(sprintf(
            'status=%s ready_users=%d users_mode_resolution_rate=%s fallback_rate=%s top1=%s topk=%s p95_ms=%s passed=%s',
            (string) ($report['status'] ?? 'unknown'),
            (int) ($metrics['ready_user_count'] ?? 0),
            number_format((float) ($metrics['users_mode_resolution_rate'] ?? 0), 6, '.', ''),
            number_format((float) ($metrics['fallback_rate'] ?? 0), 6, '.', ''),
            number_format((float) ($metrics['top_1_match_rate'] ?? 0), 6, '.', ''),
            number_format((float) ($metrics['top_k_match_rate'] ?? 0), 6, '.', ''),
            is_numeric($metrics['p95_response_duration_ms'] ?? null)
                ? number_format((float) $metrics['p95_response_duration_ms'], 0, '.', '')
                : 'n/a',
            ($criteriaEvaluation['passed'] ?? false) ? 'true' : 'false',
        ));

        return ($report['status'] ?? null) === 'completed' && (($criteriaEvaluation['passed'] ?? false) === true)
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function storeReport(array $report): string
    {
        $directory = (string) ($this->option('report-dir') ?: storage_path('app/private/face-search-users-high-cardinality'));

        File::ensureDirectoryExists($directory);

        $path = $directory . DIRECTORY_SEPARATOR . sprintf(
            '%s-face-search-aws-users-high-cardinality.json',
            now()->format('Ymd-His'),
        );

        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
