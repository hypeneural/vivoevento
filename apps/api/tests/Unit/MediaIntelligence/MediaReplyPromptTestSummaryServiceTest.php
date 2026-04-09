<?php

use App\Modules\MediaIntelligence\Services\MediaReplyPromptTestSummaryService;

it('projects auto publish when blocking layers do not find reject or review signals', function () {
    $summary = app(MediaReplyPromptTestSummaryService::class)->build(
        safetyResults: [[
            'decision' => 'pass',
            'blocked' => false,
            'review_required' => false,
            'reason_codes' => ['safety.pass'],
        ]],
        contextualResults: [[
            'decision' => 'approve',
            'review_required' => false,
            'reason' => 'A imagem combina com o evento.',
            'reason_code' => 'context.match.event',
            'publish_eligibility' => 'auto_publish',
        ]],
        safetyIsBlocking: true,
        contextIsBlocking: true,
        replySucceeded: true,
    );

    expect($summary['final_publish_eligibility'])->toBe('auto_publish')
        ->and($summary['final_effective_state'])->toBe('approved')
        ->and($summary['blocking_layers'])->toBe([]);
});

it('projects review only when a blocking layer returns review or error semantics', function () {
    $summary = app(MediaReplyPromptTestSummaryService::class)->build(
        safetyResults: [[
            'decision' => 'error',
            'blocked' => false,
            'review_required' => true,
            'reason_codes' => ['provider.error'],
            'error_message' => 'timeout',
        ]],
        contextualResults: [[
            'decision' => 'review',
            'review_required' => true,
            'reason' => 'A imagem precisa de revisao manual.',
            'reason_code' => 'context.review.manual',
            'publish_eligibility' => 'review_only',
        ]],
        safetyIsBlocking: true,
        contextIsBlocking: true,
        replySucceeded: false,
    );

    expect($summary['final_publish_eligibility'])->toBe('review_only')
        ->and($summary['final_effective_state'])->toBe('pending_moderation')
        ->and($summary['evaluation_errors_count'])->toBe(2);
});

it('projects rejection when a blocking contextual gate rejects the media', function () {
    $summary = app(MediaReplyPromptTestSummaryService::class)->build(
        safetyResults: [[
            'decision' => 'pass',
            'blocked' => false,
            'review_required' => false,
            'reason_codes' => ['safety.pass'],
        ]],
        contextualResults: [[
            'decision' => 'reject',
            'review_required' => false,
            'reason' => 'A imagem nao representa o contexto esperado.',
            'reason_code' => 'context.out_of_scope',
            'publish_eligibility' => 'reject',
        ]],
        safetyIsBlocking: true,
        contextIsBlocking: true,
        replySucceeded: true,
    );

    expect($summary['final_publish_eligibility'])->toBe('reject')
        ->and($summary['final_effective_state'])->toBe('rejected')
        ->and($summary['blocking_layers'])->toBe(['context']);
});
