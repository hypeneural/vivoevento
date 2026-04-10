<?php

namespace App\Modules\MediaProcessing\Console;

use App\Modules\MediaProcessing\Services\ModerationFeedExplainAnalyzeService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use RuntimeException;

class RunModerationFeedExplainCommand extends Command
{
    protected $signature = 'media:moderation-feed-explain
        {--organization-id= : Organization id to scope the benchmark}
        {--event-id= : Event id to scope the hot event scenario}
        {--per-page=24 : Limit used for the feed scenarios}
        {--search-event= : Event title token used for the search scenario}
        {--search-sender= : Sender identity token used for the search scenario}
        {--synthetic-media=0 : Insert this many temporary media rows inside a rollback-only transaction before measuring}
        {--feed-budget-ms=700 : Budget in ms for feed scenarios}
        {--search-budget-ms=500 : Budget in ms for search scenarios}
        {--disable-jit : Run the EXPLAIN probe with SET LOCAL jit = off}
        {--output= : Relative or absolute path to persist the JSON report}
        {--json : Print the full report as JSON}
        {--fail-on-budget : Exit with failure when a measured scenario exceeds budget}';

    protected $description = 'Roda EXPLAIN ANALYZE do feed de moderacao em PostgreSQL real e decide se a busca ainda cabe no budget operacional.';

    public function handle(ModerationFeedExplainAnalyzeService $service): int
    {
        try {
            $report = $service->analyze([
                'organization_id' => $this->option('organization-id'),
                'event_id' => $this->option('event-id'),
                'per_page' => $this->option('per-page'),
                'search_event' => $this->option('search-event'),
                'search_sender' => $this->option('search-sender'),
                'synthetic_media' => $this->option('synthetic-media'),
                'feed_budget_ms' => $this->option('feed-budget-ms'),
                'search_budget_ms' => $this->option('search-budget-ms'),
                'disable_jit' => $this->option('disable-jit'),
            ]);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($json)) {
            $this->error('Nao foi possivel serializar o relatorio do benchmark da moderacao.');

            return self::FAILURE;
        }

        if ($output = $this->option('output')) {
            $path = $this->resolveOutputPath((string) $output);
            $directory = dirname($path);

            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($path, $json);
            $this->line('Relatorio salvo em: '.$path);
        }

        if ($this->option('json')) {
            $this->line($json);
        } else {
            $this->renderHumanReport($report);
        }

        if ($this->option('fail-on-budget') && ! ($report['decision']['all_within_budget'] ?? false)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function renderHumanReport(array $report): void
    {
        $dataset = $report['dataset'];
        $decision = $report['decision'];

        $this->line('Database: '.$report['database']['connection'].' ('.$report['database']['driver'].')');
        $this->line('Database name: '.($report['database']['database'] ?? 'n/a'));
        $this->line('Generated at: '.$report['generated_at']);
        $this->newLine();
        $this->line('Dataset quente:');
        $this->line('  event_media total: '.$dataset['event_media_table_total']);
        $this->line('  inbound_messages total: '.$dataset['inbound_messages_total']);
        $this->line('  organization_id: '.$dataset['organization_id'].' ('.$dataset['organization_media_total'].' midias)');
        $this->line('  event_id: '.($dataset['event_id'] ?? 'n/a').' ('.($dataset['hot_event_media_total'] ?? 'n/a').' midias)');
        $this->line('  search_event: '.($dataset['search_event'] ?: 'n/a'));
        $this->line('  search_sender: '.($dataset['search_sender'] ?: 'n/a'));
        $this->line('  disable_jit: '.(($dataset['disable_jit'] ?? false) ? 'yes' : 'no'));

        if (is_array($dataset['synthetic'] ?? null)) {
            $this->line('  synthetic_media: '.$dataset['synthetic']['media_rows'].' (rollback: yes)');
        }

        $this->newLine();

        $rows = Collection::make($report['scenarios'])
            ->map(function (array $scenario) {
                return [
                    'scenario' => $scenario['name'],
                    'kind' => $scenario['kind'],
                    'exec_ms' => number_format((float) $scenario['execution_time_ms'], 3, '.', ''),
                    'budget_ms' => number_format((float) $scenario['budget_ms'], 3, '.', ''),
                    'within_budget' => $scenario['within_budget'] ? 'yes' : 'no',
                    'seq_scan' => $scenario['uses_seq_scan'] ? 'yes' : 'no',
                    'trgm' => $scenario['uses_trigram_index'] ? 'yes' : 'no',
                    'probe_trgm' => isset($scenario['probe_without_seqscan'])
                        ? (($scenario['probe_without_seqscan']['uses_trigram_index'] ?? false) ? 'yes' : 'no')
                        : '-',
                ];
            })
            ->all();

        $this->table(
            ['scenario', 'kind', 'exec_ms', 'budget_ms', 'within_budget', 'seq_scan', 'trgm', 'probe_trgm'],
            $rows,
        );

        $this->line('Decisao:');
        $this->line('  search_document.present: '.(($decision['search_document']['present'] ?? false) ? 'yes' : 'no'));
        $this->line('  search_document.promote: '.(($decision['search_document']['promote'] ?? false) ? 'yes' : 'no'));
        $this->line('  search_document.requires_follow_up: '.(($decision['search_document']['requires_follow_up'] ?? false) ? 'yes' : 'no'));
        $this->line('  search_document.reason: '.($decision['search_document']['reason'] ?? 'n/a'));
        $this->line('  search_document.next_step: '.($decision['search_document']['next_step'] ?? 'n/a'));
        $this->line('  partial_index.promote: '.(($decision['partial_index']['promote'] ?? false) ? 'yes' : 'no'));
        $this->line('  partial_index.reason: '.($decision['partial_index']['reason'] ?? 'n/a'));
        $this->line('  over_budget_scenarios: '.implode(', ', $decision['over_budget_scenarios'] ?: ['none']));
    }

    private function resolveOutputPath(string $path): string
    {
        if (preg_match('/^(?:[A-Za-z]:\\\\|\\/)/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
