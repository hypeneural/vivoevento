<?php

namespace Tests\Unit\Modules\Wall;

use App\Modules\Wall\Enums\WallAcceptedOrientation;

test('WallAcceptedOrientation::All matches any orientation', function () {
    expect(WallAcceptedOrientation::All->matches('horizontal'))->toBeTrue();
    expect(WallAcceptedOrientation::All->matches('vertical'))->toBeTrue();
    expect(WallAcceptedOrientation::All->matches('squareish'))->toBeTrue();
    expect(WallAcceptedOrientation::All->matches(null))->toBeTrue();
});

test('WallAcceptedOrientation::Landscape accepts horizontal and square', function () {
    expect(WallAcceptedOrientation::Landscape->matches('horizontal'))->toBeTrue();
    expect(WallAcceptedOrientation::Landscape->matches('squareish'))->toBeTrue();
});

test('WallAcceptedOrientation::Landscape rejects vertical', function () {
    expect(WallAcceptedOrientation::Landscape->matches('vertical'))->toBeFalse();
});

test('WallAcceptedOrientation::Portrait accepts vertical and square', function () {
    expect(WallAcceptedOrientation::Portrait->matches('vertical'))->toBeTrue();
    expect(WallAcceptedOrientation::Portrait->matches('squareish'))->toBeTrue();
});

test('WallAcceptedOrientation::Portrait rejects horizontal', function () {
    expect(WallAcceptedOrientation::Portrait->matches('horizontal'))->toBeFalse();
});

test('null orientation always passes any filter', function () {
    expect(WallAcceptedOrientation::All->matches(null))->toBeTrue();
    expect(WallAcceptedOrientation::Landscape->matches(null))->toBeTrue();
    expect(WallAcceptedOrientation::Portrait->matches(null))->toBeTrue();
});
