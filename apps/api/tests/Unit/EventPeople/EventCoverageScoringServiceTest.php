<?php

use App\Modules\EventPeople\Enums\EventCoverageState;
use App\Modules\EventPeople\Services\EventCoverageScoringService;

it('scores missing coverage when there is no media', function () {
    $service = new EventCoverageScoringService();

    $result = $service->scorePerson(0, 0, false, 2, 1, 0);

    expect($result['coverage_state'])->toBe(EventCoverageState::Missing->value);
});

it('scores strong coverage when requirements are met', function () {
    $service = new EventCoverageScoringService();

    $result = $service->scorePerson(4, 2, true, 2, 1);

    expect($result['coverage_state'])->toBe(EventCoverageState::Strong->value);
});

it('scores pair and group coverage states', function () {
    $service = new EventCoverageScoringService();

    $pairResult = $service->scorePair(1, false, 3);
    $groupResult = $service->scoreGroup(4, 2, 4, 1, 4, 1);

    expect($pairResult['coverage_state'])->toBe(EventCoverageState::Weak->value)
        ->and($groupResult['coverage_state'])->toBe(EventCoverageState::Ok->value);
});
