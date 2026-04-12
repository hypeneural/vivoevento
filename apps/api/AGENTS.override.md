# API Override

## Scope

This override applies to work under `apps/api`.

## Working Rules

- Keep important behavior inside `app/Modules/<Module>`.
- Controllers stay thin: request in, action/service call, resource out.
- Put write-side business logic in `Actions`, technical integrations in `Services`, queued work in `Jobs`, and complex reads in `Queries`.
- Use `Requests`, `Resources`, and `Policies` as explicit HTTP boundaries when the endpoint needs them.
- Avoid direct cross-module coupling. If one module depends on another, prefer a contract, shared interface, or existing shared service boundary.
- When routes, payloads, broadcasts, queues, or persistence contracts change, update the relevant tests and docs in the same task.

## Validation

- Run the smallest backend validation that proves the touched scope.
- Prefer targeted `php artisan test` filters before full-suite runs.
- If HTTP behavior changes, include a feature test.
- If jobs, actions, or services change, include focused unit coverage when practical.
