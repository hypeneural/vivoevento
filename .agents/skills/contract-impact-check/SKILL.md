---
name: contract-impact-check
description: Review cross-boundary impact in `eventovivo` when changing endpoints, payloads, shared types, broadcasts, queues, events, schemas, or config. Use when a change in `apps/api`, `apps/web`, `apps/landing`, or `packages/*` can affect another consumer or producer.
---

# Contract Impact Check

Use this skill before closing any change that can silently break another layer.

## Workflow

1. Identify the changed contract surface:
   - HTTP request or response
   - shared type in `packages/*`
   - broadcast or realtime payload
   - queue or job payload
   - migration or persisted schema
   - config or env contract
2. Identify the producer and every known consumer.
3. Read the closest tests, resources, API clients, and fixtures on both sides of the boundary.
4. Update tests and docs where the contract changes.
5. Record the exact validation in `docs/active/<feature>/VERIFY.md` when the work is long-running.

## Checkpoints

- `apps/api/app/Modules/*/Http`
- `apps/web/src/modules/*/api.ts`
- `packages/contracts`
- `packages/shared-types`
- realtime channels, broadcasts, and queue jobs when relevant

## Output checklist

- changed contract surface named explicitly
- affected producers and consumers listed
- smallest relevant tests updated or rerun
- doc impact called out when behavior changed

## Avoid

- changing payload shape without checking web consumers
- treating fixtures or tests as optional when they are the only executable contract
- claiming no impact without reading both sides of the boundary
