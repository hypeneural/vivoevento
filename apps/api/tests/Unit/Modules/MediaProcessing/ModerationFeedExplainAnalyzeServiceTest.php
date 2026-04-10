<?php

use App\Modules\MediaProcessing\Services\ModerationFeedExplainAnalyzeService;

it('summarizes explain plans with scan and trigram metadata', function () {
    $service = app(ModerationFeedExplainAnalyzeService::class);

    $summary = $service->summarizePlan([
        [
            'Plan' => [
                'Node Type' => 'Nested Loop',
                'Plans' => [
                    [
                        'Node Type' => 'Seq Scan',
                        'Relation Name' => 'event_media',
                    ],
                    [
                        'Node Type' => 'Bitmap Index Scan',
                        'Index Name' => 'event_media_moderation_search_trgm_idx',
                    ],
                ],
            ],
            'Planning Time' => 1.25,
            'Execution Time' => 12.75,
        ],
    ], 'search_sender_name_hot', 'search', 500.0);

    expect($summary['within_budget'])->toBeTrue()
        ->and($summary['uses_seq_scan'])->toBeTrue()
        ->and($summary['uses_trigram_index'])->toBeTrue()
        ->and($summary['index_names'])->toContain('event_media_moderation_search_trgm_idx')
        ->and($summary['relation_names'])->toContain('event_media')
        ->and($summary['execution_time_ms'])->toBe(12.75);
});

it('keeps the current search path when search scenarios stay within budget', function () {
    $service = app(ModerationFeedExplainAnalyzeService::class);

    $decision = $service->buildDecision([
        [
            'name' => 'feed_org_hot',
            'kind' => 'feed',
            'within_budget' => true,
        ],
        [
            'name' => 'search_event_title_hot',
            'kind' => 'search',
            'within_budget' => true,
            'uses_trigram_index' => false,
            'probe_without_seqscan' => ['uses_trigram_index' => false],
        ],
    ], 700.0, 500.0);

    expect($decision['all_within_budget'])->toBeTrue()
        ->and($decision['search_document']['promote'])->toBeFalse()
        ->and($decision['search_document']['requires_follow_up'])->toBeFalse()
        ->and($decision['search_document']['reason'])->toBe('search_within_budget_keep_pg_trgm_foundation')
        ->and($decision['partial_index']['promote'])->toBeFalse();
});

it('promotes search document only when search leaves the operational budget', function () {
    $service = app(ModerationFeedExplainAnalyzeService::class);

    $decision = $service->buildDecision([
        [
            'name' => 'feed_org_hot',
            'kind' => 'feed',
            'within_budget' => true,
        ],
        [
            'name' => 'search_sender_name_hot',
            'kind' => 'search',
            'within_budget' => false,
            'uses_trigram_index' => false,
            'probe_without_seqscan' => ['uses_trigram_index' => false],
        ],
    ], 700.0, 500.0);

    expect($decision['all_within_budget'])->toBeFalse()
        ->and($decision['search_document']['promote'])->toBeTrue()
        ->and($decision['search_document']['requires_follow_up'])->toBeFalse()
        ->and($decision['search_document']['reason'])->toBe('search_execution_budget_exceeded')
        ->and($decision['search_document']['over_budget_scenarios'])->toBe(['search_sender_name_hot']);
});

it('requires follow up instead of promoting again when the search document already exists and still exceeds budget', function () {
    $service = app(ModerationFeedExplainAnalyzeService::class);

    $decision = $service->buildDecision([
        [
            'name' => 'feed_event_hot',
            'kind' => 'feed',
            'within_budget' => true,
        ],
        [
            'name' => 'search_event_title_hot',
            'kind' => 'search',
            'within_budget' => false,
            'uses_trigram_index' => true,
            'probe_without_seqscan' => ['uses_trigram_index' => true],
        ],
    ], 700.0, 500.0, searchDocumentPresent: true);

    expect($decision['all_within_budget'])->toBeFalse()
        ->and($decision['search_document']['present'])->toBeTrue()
        ->and($decision['search_document']['promote'])->toBeFalse()
        ->and($decision['search_document']['requires_follow_up'])->toBeTrue()
        ->and($decision['search_document']['reason'])->toBe('search_document_present_but_budget_exceeded')
        ->and($decision['search_document']['next_step'])->toBe('review_search_selectivity_event_filter_or_dedicated_read_model')
        ->and($decision['search_document']['over_budget_scenarios'])->toBe(['search_event_title_hot']);
});
