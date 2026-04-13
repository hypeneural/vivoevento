# AGENTS.md

## Repository Purpose

`eventovivo` is the Evento Vivo monorepo.

Core stack:

- `apps/api`: Laravel 13 + PHP 8.3
- `apps/web`: React 18 + TypeScript + Vite 5
- `apps/landing`: React 18 + TypeScript + Vite 5
- `packages/*`: shared contracts and types
- `.agents/skills`: shared Codex skills for repeatable workflows
- `docs/*`: architecture, execution plans, runbooks, and active feature context

## Source Of Truth

Use each layer for one job only:

- `.github/copilot-instructions.md`
  - workspace-wide baseline rules in VS Code
- `AGENTS.md`
  - repo-wide agent contract
- `.github/instructions/**/*.instructions.md`
  - stack-specific guidance
- `code_review.md`
  - review contract for review-oriented work
- `docs/active/<feature>/`
  - live feature context
- `docs/execution-plans/`
  - executable plans
- `.agents/skills/`
  - shared workflow skills, loaded on demand
- `docs/architecture/`
  - historical analysis and reference, not the default active spec
- `.kiro/specs/*`
  - imported secondary context only when explicitly referenced

If multiple artifacts disagree, prefer:

1. the nearest `AGENTS.override.md`
2. this root `AGENTS.md`
3. the active feature folder in `docs/active/<feature>/`
4. the execution plan for that feature
5. historical docs as reference only

## Working Rules

- Important features must belong to a domain module.
- Keep changes minimal, local, and reviewable.
- Preserve public contracts unless the task explicitly calls for a contract change.
- Do not add dependencies, global helpers, or broad abstractions without a concrete reason.
- Prefer existing module patterns before inventing new structure.
- For long, ambiguous, or multi-step work, start with a plan before editing.
- Update docs when behavior, contracts, workflows, or operator-facing procedures change.
- For review tasks, follow `code_review.md`.

## Repository Map

- `apps/api`
  - Laravel API, queues, realtime, domain modules in `app/Modules/*`
- `apps/web`
  - admin panel and public event experiences
- `apps/landing`
  - main marketing/capture site
- `packages/contracts`
  - formal contract/codegen reserve
- `packages/shared-types`
  - shared runtime types
- `.agents/skills`
  - shared team skills for repeatable agent workflows
- `scripts`
  - setup, deploy, ops, smoke, and automation scripts
- `docs`
  - architecture, execution plans, runbooks, and active feature state

## Validation

Prefer the smallest validation that proves the touched scope is correct.

Common commands:

- `cd apps/api && php artisan test`
- `cd apps/web && npm run type-check`
- `cd apps/web && npm run test`
- `cd apps/landing && npm run type-check`
- `cd apps/landing && npm run test`
- `make test`
- `make type-check`
- `make lint`

When a change is local, run the relevant subset instead of the whole monorepo.

## Done When

- the implementation is complete
- relevant validation ran for the touched scope
- docs are updated when behavior or contracts changed
- the diff is small enough to review safely
- for long-running features, `docs/active/<feature>/VERIFY.md` reflects what was checked
