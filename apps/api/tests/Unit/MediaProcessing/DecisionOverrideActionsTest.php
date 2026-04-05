<?php

use App\Modules\MediaProcessing\Actions\ApproveEventMediaAction;
use App\Modules\MediaProcessing\Actions\RejectEventMediaAction;
use App\Modules\MediaProcessing\Enums\MediaDecisionSource;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Users\Models\User;

it('stores decision override metadata when approving media manually', function () {
    $actor = User::factory()->create();
    $media = EventMedia::factory()->create([
        'moderation_status' => ModerationStatus::Pending->value,
        'publication_status' => PublicationStatus::Draft->value,
    ]);

    $approved = app(ApproveEventMediaAction::class)->execute($media, $actor, 'Liberado pela operacao');

    expect($approved->decision_source)->toBe(MediaDecisionSource::UserOverride)
        ->and($approved->decision_overridden_by_user_id)->toBe($actor->id)
        ->and($approved->decision_override_reason)->toBe('Liberado pela operacao')
        ->and($approved->decision_overridden_at)->not->toBeNull()
        ->and($approved->moderation_status)->toBe(ModerationStatus::Approved);
});

it('stores decision override metadata and unpublishes when rejecting media manually', function () {
    $actor = User::factory()->create();
    $media = EventMedia::factory()->published()->create();

    $rejected = app(RejectEventMediaAction::class)->execute($media, $actor, 'Conteudo fora da politica');

    expect($rejected->decision_source)->toBe(MediaDecisionSource::UserOverride)
        ->and($rejected->decision_overridden_by_user_id)->toBe($actor->id)
        ->and($rejected->decision_override_reason)->toBe('Conteudo fora da politica')
        ->and($rejected->decision_overridden_at)->not->toBeNull()
        ->and($rejected->moderation_status)->toBe(ModerationStatus::Rejected)
        ->and($rejected->publication_status)->toBe(PublicationStatus::Draft)
        ->and($rejected->published_at)->toBeNull();
});
