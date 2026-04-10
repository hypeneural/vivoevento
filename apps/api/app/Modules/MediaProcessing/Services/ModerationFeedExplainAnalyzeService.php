<?php

namespace App\Modules\MediaProcessing\Services;

use App\Modules\MediaProcessing\Queries\ListModerationMediaQuery;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class ModerationFeedExplainAnalyzeService
{
    public function analyze(array $options = []): array
    {
        $connection = DB::connection();
        $this->guardPostgres($connection);

        $syntheticMedia = max(0, (int) ($options['synthetic_media'] ?? 0));

        if ($syntheticMedia > 0) {
            return $this->analyzeWithSyntheticVolume($options, $syntheticMedia);
        }

        return $this->analyzeCurrentDataset($options);
    }

    private function analyzeCurrentDataset(array $options = [], array $syntheticContext = []): array
    {
        $connection = DB::connection();
        $feedBudgetMs = (float) ($options['feed_budget_ms'] ?? 700);
        $searchBudgetMs = (float) ($options['search_budget_ms'] ?? 500);
        $perPage = max(1, (int) ($options['per_page'] ?? 24));
        $disableJit = (bool) ($options['disable_jit'] ?? false);

        $organizationId = isset($options['organization_id'])
            ? (int) $options['organization_id']
            : $this->detectHotOrganizationId();

        if (! $organizationId) {
            throw new RuntimeException('Nenhuma organizacao com midia de moderacao foi encontrada para o benchmark.');
        }

        $eventId = isset($options['event_id'])
            ? (int) $options['event_id']
            : $this->detectHotEventId($organizationId);

        $eventTitle = $options['search_event'] ?? $syntheticContext['search_event'] ?? $this->detectHotEventTitle($organizationId, $eventId);
        $senderName = $options['search_sender'] ?? $syntheticContext['search_sender'] ?? $this->detectHotSenderName($organizationId);

        $scenarios = array_values(array_filter([
            $this->buildScenario(
                name: 'feed_org_hot',
                kind: 'feed',
                budgetMs: $feedBudgetMs,
                disableJit: $disableJit,
                builder: fn () => (new ListModerationMediaQuery(
                    organizationId: $organizationId,
                ))->query()->limit($perPage),
            ),
            $eventId ? $this->buildScenario(
                name: 'feed_event_hot',
                kind: 'feed',
                budgetMs: $feedBudgetMs,
                disableJit: $disableJit,
                builder: fn () => (new ListModerationMediaQuery(
                    organizationId: $organizationId,
                    eventId: $eventId,
                ))->query()->limit($perPage),
            ) : null,
            $this->buildScenario(
                name: 'feed_pending_hot',
                kind: 'feed',
                budgetMs: $feedBudgetMs,
                disableJit: $disableJit,
                builder: fn () => (new ListModerationMediaQuery(
                    organizationId: $organizationId,
                    status: 'pending_moderation',
                ))->query()->limit($perPage),
            ),
            is_string($eventTitle) && trim($eventTitle) !== '' ? $this->buildScenario(
                name: 'search_event_title_hot',
                kind: 'search',
                budgetMs: $searchBudgetMs,
                disableJit: $disableJit,
                builder: fn () => (new ListModerationMediaQuery(
                    organizationId: $organizationId,
                    search: $eventTitle,
                ))->query()->limit($perPage),
            ) : null,
            is_string($senderName) && trim($senderName) !== '' ? $this->buildScenario(
                name: 'search_sender_name_hot',
                kind: 'search',
                budgetMs: $searchBudgetMs,
                disableJit: $disableJit,
                builder: fn () => (new ListModerationMediaQuery(
                    organizationId: $organizationId,
                    search: $senderName,
                ))->query()->limit($perPage),
            ) : null,
        ]));

        $reports = array_map(function (array $scenario) {
            /** @var Builder $builder */
            $builder = ($scenario['builder'])();
            $report = $this->summarizePlan(
                explainPayload: $this->runExplain($builder, disableJit: (bool) $scenario['disable_jit']),
                scenario: $scenario['name'],
                kind: $scenario['kind'],
                budgetMs: $scenario['budget_ms'],
            );

            if ($scenario['kind'] === 'search') {
                $probe = $this->summarizePlan(
                    explainPayload: $this->runExplain($builder, disableSeqScan: true, disableJit: (bool) $scenario['disable_jit']),
                    scenario: $scenario['name'].'_seqscan_off_probe',
                    kind: 'search_probe',
                    budgetMs: $scenario['budget_ms'],
                );

                $report['probe_without_seqscan'] = [
                    'execution_time_ms' => $probe['execution_time_ms'],
                    'planning_time_ms' => $probe['planning_time_ms'],
                    'uses_seq_scan' => $probe['uses_seq_scan'],
                    'uses_trigram_index' => $probe['uses_trigram_index'],
                    'index_names' => $probe['index_names'],
                ];
            }

            unset($scenario['builder']);

            return array_merge($scenario, $report);
        }, $scenarios);

        return [
            'generated_at' => now()->toIso8601String(),
            'database' => [
                'connection' => config('database.default'),
                'driver' => $connection->getDriverName(),
                'database' => $this->resolveDatabaseName($connection),
            ],
            'budgets' => [
                'feed_budget_ms' => $feedBudgetMs,
                'search_budget_ms' => $searchBudgetMs,
            ],
            'dataset' => [
                'event_media_table_total' => DB::table('event_media')->count(),
                'inbound_messages_total' => DB::table('inbound_messages')->count(),
                'organization_id' => $organizationId,
                'organization_media_total' => $this->countOrganizationMedia($organizationId),
                'event_id' => $eventId,
                'hot_event_media_total' => $eventId ? $this->countEventMedia($eventId) : null,
                'search_event' => $eventTitle,
                'search_sender' => $senderName,
                'per_page' => $perPage,
                'disable_jit' => $disableJit,
                'synthetic' => $syntheticContext === [] ? null : [
                    'enabled' => true,
                    'media_rows' => $syntheticContext['media_rows'],
                    'rolled_back' => true,
                ],
            ],
            'scenarios' => $reports,
            'decision' => $this->buildDecision(
                scenarios: $reports,
                feedBudgetMs: $feedBudgetMs,
                searchBudgetMs: $searchBudgetMs,
                searchDocumentPresent: Schema::hasColumn('event_media', 'moderation_search_document'),
            ),
        ];
    }

    private function analyzeWithSyntheticVolume(array $options, int $syntheticMedia): array
    {
        $connection = DB::connection();
        $organizationId = isset($options['organization_id'])
            ? (int) $options['organization_id']
            : $this->detectHotOrganizationId();

        if (! $organizationId) {
            throw new RuntimeException('Nenhuma organizacao foi encontrada para criar a sonda sintetica de moderacao.');
        }

        $connection->beginTransaction();

        try {
            $syntheticContext = $this->seedSyntheticModerationVolume($organizationId, $syntheticMedia);
            $report = $this->analyzeCurrentDataset(array_merge($options, [
                'organization_id' => $organizationId,
                'event_id' => $syntheticContext['event_id'],
                'search_event' => $syntheticContext['search_event'],
                'search_sender' => $syntheticContext['search_sender'],
            ]), $syntheticContext);
        } finally {
            $connection->rollBack();
        }

        return $report;
    }

    private function seedSyntheticModerationVolume(int $organizationId, int $mediaRows): array
    {
        $token = 'moderation-benchmark-'.Str::lower(Str::random(8));
        $senderToken = 'target-sender-'.Str::lower(Str::random(8));
        $eventTitle = 'Moderation Benchmark High Volume '.$token;
        $senderName = $senderToken;
        $now = now();

        $eventId = (int) DB::table('events')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'title' => $eventTitle,
            'slug' => 'moderation-benchmark-'.$token,
            'event_type' => 'corporate',
            'status' => 'active',
            'visibility' => 'public',
            'moderation_mode' => 'manual',
            'starts_at' => $now->copy()->subDay(),
            'ends_at' => $now->copy()->addDay(),
            'retention_days' => 30,
            'commercial_mode' => 'none',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $inboundMessageId = (int) DB::table('inbound_messages')->insertGetId([
            'event_id' => $eventId,
            'provider' => 'benchmark',
            'message_id' => 'moderation-benchmark-'.$token,
            'message_type' => 'image',
            'sender_phone' => '+5500000000000',
            'sender_name' => $senderName,
            'sender_external_id' => 'benchmark-'.$token,
            'sender_lid' => 'lid-'.$token,
            'body_text' => 'Synthetic moderation benchmark payload',
            'status' => 'processed',
            'received_at' => $now,
            'processed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $rows = [];
        $hasSearchDocument = Schema::hasColumn('event_media', 'moderation_search_document');
        $documentBuilder = app(ModerationSearchDocumentBuilder::class);

        for ($i = 1; $i <= $mediaRows; $i++) {
            $statusBucket = $i % 10;
            $moderationStatus = match (true) {
                $statusBucket <= 5 => 'pending',
                $statusBucket <= 7 => 'approved',
                $statusBucket === 8 => 'rejected',
                default => 'pending',
            };
            $publicationStatus = $moderationStatus === 'approved' && $i % 4 === 0 ? 'published' : 'draft';
            $createdAt = $now->copy()->subSeconds($i);

            $row = [
                'event_id' => $eventId,
                'inbound_message_id' => $inboundMessageId,
                'media_type' => 'image',
                'source_type' => 'whatsapp',
                'source_label' => 'Benchmark WhatsApp',
                'title' => 'Benchmark media '.$i,
                'caption' => 'Synthetic moderation feed benchmark '.$token.' item '.$i,
                'original_filename' => 'benchmark-'.$token.'-'.$i.'.jpg',
                'client_filename' => 'benchmark-'.$token.'-'.$i.'.jpg',
                'mime_type' => 'image/jpeg',
                'size_bytes' => 250000,
                'width' => $i % 3 === 0 ? 1080 : 1920,
                'height' => $i % 3 === 0 ? 1920 : 1080,
                'processing_status' => 'processed',
                'moderation_status' => $moderationStatus,
                'publication_status' => $publicationStatus,
                'is_featured' => $i % 25 === 0,
                'sort_order' => $i % 50 === 0 ? (int) ($i / 50) : 0,
                'published_at' => $publicationStatus === 'published' ? $createdAt : null,
                'safety_status' => 'pass',
                'vlm_status' => 'completed',
                'face_index_status' => 'skipped',
                'decision_source' => $moderationStatus === 'pending' ? null : 'benchmark',
                'pipeline_version' => 'moderation_benchmark_v1',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if ($hasSearchDocument) {
                $rowSenderName = $i % 20 === 0
                    ? $senderName
                    : 'other-sender-'.$i;

                $row['moderation_search_document'] = $documentBuilder->buildFromParts([
                    $row['caption'],
                    $row['title'],
                    $row['source_label'],
                    $row['original_filename'],
                    $row['client_filename'],
                    $eventTitle,
                    $rowSenderName,
                    '+5500000000000',
                    'lid-'.$token,
                    'benchmark-'.$token,
                ]);
            }

            $rows[] = $row;

            if (count($rows) >= 1000) {
                DB::table('event_media')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('event_media')->insert($rows);
        }

        return [
            'event_id' => $eventId,
            'media_rows' => $mediaRows,
            'search_event' => $eventTitle,
            'search_sender' => $senderName,
        ];
    }

    public function summarizePlan(array $explainPayload, string $scenario, string $kind, float $budgetMs): array
    {
        $root = $explainPayload[0] ?? $explainPayload;
        $plan = $root['Plan'] ?? [];

        $nodeTypes = [];
        $indexNames = [];
        $relationNames = [];
        $this->collectPlanMetadata($plan, $nodeTypes, $indexNames, $relationNames);

        $executionTimeMs = (float) ($root['Execution Time'] ?? 0.0);
        $planningTimeMs = (float) ($root['Planning Time'] ?? 0.0);

        $normalizedIndexNames = array_values(array_unique($indexNames));
        $usesTrigramIndex = collect($normalizedIndexNames)->contains(
            fn (string $name) => str_contains($name, 'moderation_search_trgm')
                || str_contains($name, 'moderation_search_document_trgm'),
        );

        return [
            'scenario' => $scenario,
            'kind' => $kind,
            'budget_ms' => $budgetMs,
            'planning_time_ms' => round($planningTimeMs, 3),
            'execution_time_ms' => round($executionTimeMs, 3),
            'within_budget' => $executionTimeMs <= $budgetMs,
            'uses_seq_scan' => in_array('Seq Scan', $nodeTypes, true),
            'uses_trigram_index' => $usesTrigramIndex,
            'node_types' => array_values(array_unique($nodeTypes)),
            'index_names' => $normalizedIndexNames,
            'relation_names' => array_values(array_unique($relationNames)),
            'jit' => $root['JIT'] ?? null,
        ];
    }

    public function buildDecision(
        array $scenarios,
        float $feedBudgetMs,
        float $searchBudgetMs,
        bool $searchDocumentPresent = false,
    ): array
    {
        $feedScenarios = array_values(array_filter($scenarios, fn (array $scenario) => $scenario['kind'] === 'feed'));
        $searchScenarios = array_values(array_filter($scenarios, fn (array $scenario) => $scenario['kind'] === 'search'));

        $feedOverBudget = array_values(array_filter($feedScenarios, fn (array $scenario) => ! $scenario['within_budget']));
        $searchOverBudget = array_values(array_filter($searchScenarios, fn (array $scenario) => ! $scenario['within_budget']));

        $searchProbeUsesTrigram = collect($searchScenarios)->contains(function (array $scenario) {
            return (bool) ($scenario['probe_without_seqscan']['uses_trigram_index'] ?? false);
        });
        $searchOverBudgetNames = array_values(array_map(
            fn (array $scenario) => $scenario['name'],
            $searchOverBudget,
        ));
        $searchDocumentReason = match (true) {
            $searchOverBudget === [] && $searchDocumentPresent => 'search_document_present_within_budget',
            $searchOverBudget === [] => 'search_within_budget_keep_pg_trgm_foundation',
            $searchDocumentPresent => 'search_document_present_but_budget_exceeded',
            default => 'search_execution_budget_exceeded',
        };

        return [
            'all_within_budget' => $feedOverBudget === [] && $searchOverBudget === [],
            'feed_budget_ms' => $feedBudgetMs,
            'search_budget_ms' => $searchBudgetMs,
            'over_budget_scenarios' => array_values(array_map(
                fn (array $scenario) => $scenario['name'],
                array_merge($feedOverBudget, $searchOverBudget),
            )),
            'search_document' => [
                'present' => $searchDocumentPresent,
                'promote' => $searchOverBudget !== [] && ! $searchDocumentPresent,
                'requires_follow_up' => $searchOverBudget !== [] && $searchDocumentPresent,
                'reason' => $searchDocumentReason,
                'next_step' => $searchOverBudget !== [] && $searchDocumentPresent
                    ? 'review_search_selectivity_event_filter_or_dedicated_read_model'
                    : null,
                'budget_ms' => $searchBudgetMs,
                'over_budget_scenarios' => $searchOverBudgetNames,
                'trigram_path_confirmed' => collect($searchScenarios)->contains(
                    fn (array $scenario) => (bool) $scenario['uses_trigram_index'],
                ),
                'trigram_probe_confirmed' => $searchProbeUsesTrigram,
            ],
            'partial_index' => [
                'promote' => false,
                'reason' => 'effective_state_predicate_remains_expression_based_and_join_dependent',
                'budget_ms' => $feedBudgetMs,
                'over_budget_scenarios' => array_values(array_map(
                    fn (array $scenario) => $scenario['name'],
                    $feedOverBudget,
                )),
            ],
        ];
    }

    private function runExplain(Builder $builder, bool $disableSeqScan = false, bool $disableJit = false): array
    {
        $sql = $builder->toSql();
        $bindings = $builder->getBindings();
        $statement = 'EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '.$sql;
        $connection = DB::connection();

        $rows = ($disableSeqScan || $disableJit)
            ? $connection->transaction(function () use ($connection, $statement, $bindings, $disableSeqScan, $disableJit) {
                if ($disableSeqScan) {
                    $connection->statement('SET LOCAL enable_seqscan = off');
                }

                if ($disableJit) {
                    $connection->statement('SET LOCAL jit = off');
                }

                return $connection->select($statement, $bindings);
            })
            : $connection->select($statement, $bindings);

        $row = (array) ($rows[0] ?? []);
        $payload = reset($row);

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_array($payload)) {
            return $payload;
        }

        throw new RuntimeException('Nao foi possivel decodificar o payload do EXPLAIN ANALYZE da moderacao.');
    }

    private function collectPlanMetadata(array $plan, array &$nodeTypes, array &$indexNames, array &$relationNames): void
    {
        if ($plan === []) {
            return;
        }

        if (isset($plan['Node Type']) && is_string($plan['Node Type'])) {
            $nodeTypes[] = $plan['Node Type'];
        }

        if (isset($plan['Index Name']) && is_string($plan['Index Name'])) {
            $indexNames[] = $plan['Index Name'];
        }

        if (isset($plan['Relation Name']) && is_string($plan['Relation Name'])) {
            $relationNames[] = $plan['Relation Name'];
        }

        foreach (($plan['Plans'] ?? []) as $childPlan) {
            if (is_array($childPlan)) {
                $this->collectPlanMetadata($childPlan, $nodeTypes, $indexNames, $relationNames);
            }
        }
    }

    private function buildScenario(string $name, string $kind, float $budgetMs, bool $disableJit, callable $builder): array
    {
        return [
            'name' => $name,
            'kind' => $kind,
            'budget_ms' => $budgetMs,
            'disable_jit' => $disableJit,
            'builder' => $builder,
        ];
    }

    private function detectHotOrganizationId(): ?int
    {
        $value = DB::table('event_media')
            ->join('events', 'events.id', '=', 'event_media.event_id')
            ->whereNull('events.deleted_at')
            ->selectRaw('events.organization_id, count(*) as media_count')
            ->groupBy('events.organization_id')
            ->orderByDesc('media_count')
            ->value('events.organization_id');

        return $value ? (int) $value : null;
    }

    private function detectHotEventId(int $organizationId): ?int
    {
        $value = DB::table('event_media')
            ->join('events', 'events.id', '=', 'event_media.event_id')
            ->where('events.organization_id', $organizationId)
            ->whereNull('events.deleted_at')
            ->selectRaw('event_media.event_id, count(*) as media_count')
            ->groupBy('event_media.event_id')
            ->orderByDesc('media_count')
            ->value('event_media.event_id');

        return $value ? (int) $value : null;
    }

    private function detectHotEventTitle(int $organizationId, ?int $eventId): ?string
    {
        if ($eventId) {
            $title = DB::table('events')
                ->where('id', $eventId)
                ->where('organization_id', $organizationId)
                ->value('title');

            return is_string($title) ? $title : null;
        }

        $title = DB::table('events')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->value('title');

        return is_string($title) ? $title : null;
    }

    private function detectHotSenderName(int $organizationId): ?string
    {
        $value = DB::table('event_media')
            ->join('events', 'events.id', '=', 'event_media.event_id')
            ->join('inbound_messages', 'inbound_messages.id', '=', 'event_media.inbound_message_id')
            ->where('events.organization_id', $organizationId)
            ->whereNull('events.deleted_at')
            ->whereNotNull('inbound_messages.sender_name')
            ->where('inbound_messages.sender_name', '<>', '')
            ->selectRaw('inbound_messages.sender_name, count(*) as media_count')
            ->groupBy('inbound_messages.sender_name')
            ->orderByDesc('media_count')
            ->value('inbound_messages.sender_name');

        return is_string($value) ? $value : null;
    }

    private function countOrganizationMedia(int $organizationId): int
    {
        return (int) DB::table('event_media')
            ->join('events', 'events.id', '=', 'event_media.event_id')
            ->where('events.organization_id', $organizationId)
            ->whereNull('events.deleted_at')
            ->count('event_media.id');
    }

    private function countEventMedia(int $eventId): int
    {
        return (int) DB::table('event_media')
            ->where('event_id', $eventId)
            ->count('id');
    }

    private function resolveDatabaseName(ConnectionInterface $connection): ?string
    {
        $config = config('database.connections.'.$connection->getName());

        return is_array($config) ? ($config['database'] ?? null) : null;
    }

    private function guardPostgres(ConnectionInterface $connection): void
    {
        if ($connection->getDriverName() !== 'pgsql') {
            throw new RuntimeException('O benchmark de moderacao com EXPLAIN ANALYZE exige PostgreSQL real no ambiente atual.');
        }
    }
}
