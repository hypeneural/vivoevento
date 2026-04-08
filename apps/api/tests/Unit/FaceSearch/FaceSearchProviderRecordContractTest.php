<?php

use App\Modules\FaceSearch\Models\FaceSearchProviderRecord;

beforeEach(function () {
    if (! filter_var(env('RUN_FACE_SEARCH_AWS_TDD', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('AWS FaceSearch TDD contracts are opt-in. Set RUN_FACE_SEARCH_AWS_TDD=1 to execute them.');
    }
});

it('defines a provider record model for remote backend face indexing state', function () {
    expect(class_exists(FaceSearchProviderRecord::class))->toBeTrue();

    $model = new FaceSearchProviderRecord;

    expect($model->getTable())->toBe('face_search_provider_records');
});
