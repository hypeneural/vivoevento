<?php

/*
|--------------------------------------------------------------------------
| Pest Configuration
|--------------------------------------------------------------------------
*/

use Tests\TestCase;
use Tests\Concerns\CreatesUsers;

// Apply to Feature and Integration test folders
uses(TestCase::class, CreatesUsers::class)
    ->in('Feature', 'Integration');

// Apply TestCase to Unit tests (no DB)
uses(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Custom Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeApiSuccess', function () {
    return $this->toHaveKey('success')
        ->and($this->value['success'])->toBeTrue();
});

expect()->extend('toHaveRequestId', function () {
    return $this->toHaveKey('meta')
        ->and($this->value['meta'])->toHaveKey('request_id');
});
