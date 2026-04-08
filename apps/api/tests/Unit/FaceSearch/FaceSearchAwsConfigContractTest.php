<?php

beforeEach(function () {
    if (! filter_var(env('RUN_FACE_SEARCH_AWS_TDD', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('AWS FaceSearch TDD contracts are opt-in. Set RUN_FACE_SEARCH_AWS_TDD=1 to execute them.');
    }
});

it('defines an aws rekognition provider config block with separated thresholds and network settings', function () {
    $provider = config('face_search.providers.aws_rekognition');

    expect($provider)->toBeArray()
        ->and($provider)->toHaveKeys([
            'region',
            'version',
            'retry_mode',
            'max_attempts_query',
            'max_attempts_index',
            'connect_timeout',
            'query_timeout',
            'index_timeout',
            'index_quality_filter',
            'search_faces_quality_filter',
            'search_users_quality_filter',
            'search_face_match_threshold',
            'search_user_match_threshold',
            'associate_user_match_threshold',
            'detection_attributes',
        ]);
});
