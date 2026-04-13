---
name: feature-delivery
description: Orchestrate long-running feature delivery in `eventovivo` from validated current state to execution plan, active feature docs, implementation order, and verification handoff. Use when a request spans analysis, planning, implementation, and validation across `docs/execution-plans/`, `docs/active/`, `apps/api`, `apps/web`, or `apps/landing`.
---

# Feature Delivery

Use this skill to keep long-running work on one delivery rail instead of scattering truth across prompts and ad hoc docs.

## Workflow

1. Read the root `AGENTS.md` and the nearest `AGENTS.override.md` files for the touched area.
2. Validate the current state from code and tests before trusting historical docs.
3. Reuse an existing plan when it already exists in `docs/execution-plans/`.
4. Create or refine `docs/active/<feature>/STATUS.md`, `DECISIONS.md`, and `VERIFY.md` when the work is multi-step or spans more than one turn.
5. Keep the layer split strict:
   - `docs/active/<feature>/` = live state
   - `docs/execution-plans/` = execution order
   - `docs/architecture/` = historical analysis
6. Define the smallest validation that proves the touched scope.
7. Hand off closure work to `verify-and-close` when implementation is done.

## Required paths

- `docs/execution-plans/_template/EXECUTION-PLAN.md`
- `docs/active/_template/STATUS.md`
- `docs/active/_template/DECISIONS.md`
- `docs/active/_template/VERIFY.md`

## Output checklist

- goal and current state validated from code
- execution order with dependencies and risks
- explicit validation commands
- active feature docs updated when the work is long-running

## Avoid

- treating `docs/architecture/` as the live spec by default
- creating duplicate plans for the same feature
- closing work without an explicit verification path
