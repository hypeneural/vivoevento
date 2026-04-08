<?php

use App\Modules\MediaProcessing\Actions\FinalizeMediaDecisionAction;
use App\Modules\MediaProcessing\Enums\MediaDecisionSource;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;

it('assigns the expected decision source for each moderation path', function (
    string $mode,
    ?string $safetyStatus,
    ?string $vlmStatus,
    ModerationStatus $expectedStatus,
    MediaDecisionSource $expectedSource,
) {
    $media = EventMedia::factory()->create([
        'event_id' => \App\Modules\Events\Models\Event::factory()->active()->create([
            'moderation_mode' => $mode,
        ])->id,
        'moderation_status' => ModerationStatus::Pending->value,
        'safety_status' => $safetyStatus,
        'vlm_status' => $vlmStatus,
        'decision_source' => null,
    ]);

    $resolved = app(FinalizeMediaDecisionAction::class)->execute($media);

    expect($resolved->moderation_status)->toBe($expectedStatus)
        ->and($resolved->decision_source)->toBe($expectedSource);
})->with([
    'none mode approves after base pipeline' => ['none', 'skipped', 'skipped', ModerationStatus::Approved, MediaDecisionSource::NoneMode],
    'manual mode keeps pending for review' => ['manual', 'skipped', 'skipped', ModerationStatus::Pending, MediaDecisionSource::ManualReview],
    'ai mode blocks through safety' => ['ai', 'block', 'skipped', ModerationStatus::Rejected, MediaDecisionSource::AiSafety],
    'ai mode keeps pending when safety requests review' => ['ai', 'review', 'skipped', ModerationStatus::Pending, MediaDecisionSource::AiSafety],
    'ai mode keeps pending when vlm becomes the gate' => ['ai', 'pass', 'review', ModerationStatus::Pending, MediaDecisionSource::AiVlm],
    'ai mode approves when safety passes and vlm is skipped' => ['ai', 'pass', 'skipped', ModerationStatus::Approved, MediaDecisionSource::AiSafety],
]);

it('keeps ai moderation pending when vlm gate is still queued', function () {
    $event = \App\Modules\Events\Models\Event::factory()->active()->create([
        'moderation_mode' => 'ai',
    ]);

    \Database\Factories\EventMediaIntelligenceSettingFactory::new()->gate()->create([
        'event_id' => $event->id,
        'enabled' => true,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Pending->value,
        'safety_status' => 'pass',
        'vlm_status' => 'queued',
        'decision_source' => null,
    ]);

    $resolved = app(FinalizeMediaDecisionAction::class)->execute($media);

    expect($resolved->moderation_status)->toBe(ModerationStatus::Pending)
        ->and($resolved->decision_source)->toBe(MediaDecisionSource::AiVlm)
        ->and($resolved->vlm_status)->toBe('queued');
});

it('skips face indexing when face search is disabled for the event', function () {
    $media = EventMedia::factory()->create([
        'event_id' => \App\Modules\Events\Models\Event::factory()->active()->create()->id,
        'face_index_status' => null,
        'safety_status' => 'skipped',
        'vlm_status' => 'skipped',
    ]);

    $resolved = app(FinalizeMediaDecisionAction::class)->execute($media);

    expect($resolved->face_index_status)->toBe('skipped');
});

it('queues face indexing when face search is enabled for the event', function () {
    $event = \App\Modules\Events\Models\Event::factory()->active()->create();
    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'face_index_status' => null,
        'safety_status' => 'skipped',
        'vlm_status' => 'skipped',
    ]);

    $resolved = app(FinalizeMediaDecisionAction::class)->execute($media);

    expect($resolved->face_index_status)->toBe('queued');
});

it('approves ai moderation when safety is observe_only and returns review', function () {
    $event = \App\Modules\Events\Models\Event::factory()->active()->create([
        'moderation_mode' => 'ai',
    ]);

    \Database\Factories\EventContentModerationSettingFactory::new()->create([
        'event_id' => $event->id,
        'mode' => 'observe_only',
        'enabled' => true,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Pending->value,
        'safety_status' => 'review',
        'vlm_status' => 'skipped',
        'decision_source' => null,
    ]);

    $resolved = app(FinalizeMediaDecisionAction::class)->execute($media);

    expect($resolved->moderation_status)->toBe(ModerationStatus::Approved)
        ->and($resolved->decision_source)->toBe(MediaDecisionSource::AiSafety);
});

it('keeps ai moderation waiting for vlm gate when safety is observe_only and already resolved', function () {
    $event = \App\Modules\Events\Models\Event::factory()->active()->create([
        'moderation_mode' => 'ai',
    ]);

    \Database\Factories\EventContentModerationSettingFactory::new()->create([
        'event_id' => $event->id,
        'mode' => 'observe_only',
        'enabled' => true,
    ]);

    \Database\Factories\EventMediaIntelligenceSettingFactory::new()->gate()->create([
        'event_id' => $event->id,
        'enabled' => true,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Pending->value,
        'safety_status' => 'block',
        'vlm_status' => 'queued',
        'decision_source' => null,
    ]);

    $resolved = app(FinalizeMediaDecisionAction::class)->execute($media);

    expect($resolved->moderation_status)->toBe(ModerationStatus::Pending)
        ->and($resolved->decision_source)->toBe(MediaDecisionSource::AiVlm)
        ->and($resolved->vlm_status)->toBe('queued');
});

it('resolves against the latest persisted media row instead of stale in memory attributes', function () {
    $event = \App\Modules\Events\Models\Event::factory()->active()->create([
        'moderation_mode' => 'ai',
    ]);

    \Database\Factories\EventMediaIntelligenceSettingFactory::new()->gate()->create([
        'event_id' => $event->id,
        'enabled' => true,
    ]);

    $staleMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Pending->value,
        'publication_status' => \App\Modules\MediaProcessing\Enums\PublicationStatus::Published->value,
        'safety_status' => 'queued',
        'vlm_status' => 'queued',
        'decision_source' => null,
    ]);

    EventMedia::query()->whereKey($staleMedia->id)->update([
        'safety_status' => 'pass',
        'vlm_status' => 'rejected',
    ]);

    $resolved = app(FinalizeMediaDecisionAction::class)->execute($staleMedia);

    expect($resolved->safety_status)->toBe('pass')
        ->and($resolved->vlm_status)->toBe('rejected')
        ->and($resolved->moderation_status)->toBe(ModerationStatus::Pending)
        ->and($resolved->decision_source)->toBe(MediaDecisionSource::AiVlm);
});
