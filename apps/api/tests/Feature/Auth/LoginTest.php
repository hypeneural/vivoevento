<?php

use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

// ─── Login ───────────────────────────────────────────────

it('allows login with valid email and password', function () {
    $this->seedPermissions();

    $user = User::factory()->create([
        'email' => 'rafael@eventovivo.com',
        'password' => Hash::make('secret123'),
        'status' => 'active',
    ]);

    $response = $this->apiPost('/auth/login', [
        'login' => 'rafael@eventovivo.com',
        'password' => 'secret123',
    ]);

    $this->assertApiSuccess($response, 200);

    $response->assertJsonStructure([
        'data' => [
            'user' => ['id', 'name', 'email'],
            'token',
        ],
    ]);

    $activity = Activity::query()
        ->where('description', 'Login realizado')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity?->event)->toBe('auth.login');
    expect($activity?->subject_type)->toBe(User::class);
    expect($activity?->subject_id)->toBe($user->id);
    expect($activity?->causer_id)->toBe($user->id);
    expect($activity?->properties['login_method'])->toBe('email');
});

it('rejects login with wrong password', function () {
    User::factory()->create([
        'email' => 'rafael@eventovivo.com',
        'password' => Hash::make('correct-password'),
    ]);

    $response = $this->apiPost('/auth/login', [
        'login' => 'rafael@eventovivo.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
});

it('rejects login for blocked user', function () {
    User::factory()->create([
        'email' => 'blocked@test.com',
        'password' => Hash::make('secret123'),
        'status' => 'blocked',
    ]);

    $response = $this->apiPost('/auth/login', [
        'login' => 'blocked@test.com',
        'password' => 'secret123',
    ]);

    $response->assertStatus(422);
});

it('validates login requires credentials', function () {
    $response = $this->apiPost('/auth/login', []);

    $this->assertApiValidationError($response, ['login', 'password']);
});

// ─── Logout ──────────────────────────────────────────────

it('allows authenticated user to logout', function () {
    [$user] = $this->actingAsOwner();

    $response = $this->apiPost('/auth/logout');

    $this->assertApiSuccess($response);

    $activity = Activity::query()
        ->where('description', 'Logout realizado')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity?->event)->toBe('auth.logout');
    expect($activity?->subject_type)->toBe(User::class);
    expect($activity?->subject_id)->toBe($user->id);
    expect($activity?->causer_id)->toBe($user->id);
});

it('rejects logout for unauthenticated user', function () {
    $response = $this->apiPost('/auth/logout');

    $this->assertApiUnauthorized($response);
});
