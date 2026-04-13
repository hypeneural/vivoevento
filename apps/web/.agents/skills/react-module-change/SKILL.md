---
name: react-module-change
description: Apply surgical frontend changes inside `apps/web/src/modules/*` using the module-first layout, TanStack Query for server state, accessible UI behavior, and focused validation. Use when editing React, TypeScript, or tests in `apps/web`.
---

# React Module Change

Use this skill to keep web changes aligned with the repo's module boundaries and runtime patterns.

## Workflow

1. Work inside the owning module under `src/modules/*`.
2. Let pages orchestrate. Keep data access in module APIs and hooks. Keep components focused on presentation and interaction.
3. Use TanStack Query for server state and avoid duplicate client-side caches unless the module already has a store for a specific reason.
4. Check accessibility when changing controls, focus behavior, ARIA labels, keyboard interaction, or motion.
5. If the web change depends on an API or payload change, run `contract-impact-check` before closing.

## Validation defaults

- `cd apps/web && npm run type-check`
- targeted `npm run test -- <file>` or the smallest relevant subset
- broader suite only when shared behavior moved

## Checkpoints

- `src/modules/*`
- module-local `api.ts`, hooks, fixtures, and tests
- shared auth, routing, and query-client only when the change truly crosses modules

## Avoid

- creating new shared abstractions before checking existing primitives
- moving server-state logic into ad hoc React state
- shipping accessibility-sensitive UI changes without matching tests
