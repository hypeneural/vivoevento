---
name: laravel-module-change
description: Apply surgical backend changes inside `apps/api/app/Modules/*` using Laravel module boundaries, thin controllers, explicit requests/resources/policies, and focused test coverage. Use when editing PHP code in `apps/api` for routes, Actions, Services, Jobs, Queries, broadcasts, or migrations.
---

# Laravel Module Change

Use this skill to keep backend edits inside clear Laravel ownership boundaries.

## Workflow

1. Start in the owning module under `app/Modules/*`.
2. Keep controllers thin. Put orchestration in Actions, pure reads in Queries, async work in Jobs, and HTTP shape in Requests and Resources.
3. Preserve public contracts unless the task explicitly changes them.
4. When touching routes, payloads, broadcasts, queues, or migrations, update the smallest relevant tests and docs in the same pass.
5. Prefer focused `php artisan test` subsets over the whole suite unless shared behavior moved.

## Checkpoints

- `Http/Controllers`
- `Http/Requests`
- `Http/Resources`
- `Actions`
- `Jobs`
- `Queries`
- `Providers`
- `routes/api.php`
- module README when operator or integration behavior changed

## Validation defaults

- feature tests for HTTP, authorization, and integration behavior
- unit tests for pure mapping, policy, and orchestration logic
- `php artisan test tests/...` for the touched slice first

## Avoid

- cross-module coupling without an explicit contract boundary
- stuffing business logic into controllers or resources
- shipping route or payload changes without matching tests and docs
