<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders($this->defaultHeaders());
    }

    // ─── API Helpers ─────────────────────────────────────

    protected function apiGet(string $uri, array $headers = []): TestResponse
    {
        return $this->withHeaders(array_merge($this->defaultHeaders(), $headers))
            ->getJson("/api/v1{$uri}");
    }

    protected function apiPost(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->withHeaders(array_merge($this->defaultHeaders(), $headers))
            ->postJson("/api/v1{$uri}", $data);
    }

    protected function apiPatch(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->withHeaders(array_merge($this->defaultHeaders(), $headers))
            ->patchJson("/api/v1{$uri}", $data);
    }

    protected function apiDelete(string $uri, array $headers = []): TestResponse
    {
        return $this->withHeaders(array_merge($this->defaultHeaders(), $headers))
            ->deleteJson("/api/v1{$uri}");
    }

    // ─── Assert Helpers ──────────────────────────────────

    protected function assertApiSuccess(TestResponse $response, int $status = 200): self
    {
        $response->assertStatus($status)
            ->assertJsonStructure(['success', 'data'])
            ->assertJson(['success' => true]);

        return $this;
    }

    protected function assertApiError(TestResponse $response, int $status = 400): self
    {
        $response->assertStatus($status)
            ->assertJson(['success' => false]);

        return $this;
    }

    protected function assertApiValidationError(TestResponse $response, array $fields = []): self
    {
        $response->assertStatus(422);

        foreach ($fields as $field) {
            $response->assertJsonValidationErrors($field);
        }

        return $this;
    }

    protected function assertApiPaginated(TestResponse $response): self
    {
        $response->assertJsonStructure([
            'success',
            'data',
            'meta' => ['page', 'per_page', 'total', 'last_page', 'request_id'],
        ]);

        return $this;
    }

    protected function assertApiUnauthorized(TestResponse $response): self
    {
        $response->assertStatus(401);
        return $this;
    }

    protected function assertApiForbidden(TestResponse $response): self
    {
        $response->assertStatus(403);
        return $this;
    }
}
