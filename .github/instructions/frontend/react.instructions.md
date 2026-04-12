---
name: React App Rules
description: Frontend conventions for apps/web and apps/landing.
applyTo: "apps/web/src/**/*.ts,apps/web/src/**/*.tsx,apps/landing/src/**/*.ts,apps/landing/src/**/*.tsx"
---
# React app rules

- Keep feature code inside the relevant module or app boundary.
- Pages should orchestrate composition, not accumulate heavy business logic.
- In `apps/web`, prefer feature modules in `src/modules/*` and shared primitives only when reuse is real.
- In `apps/web`, use TanStack Query for server state and React Hook Form + Zod for forms when applicable.
- In `apps/landing`, protect performance, copy stability, and animation restraint.
- Prefer existing components, hooks, and patterns before creating new ones.
- Preserve accessibility semantics and update UI tests when behavior or copy changes.
- When frontend changes depend on API contracts, review the affected backend/resource/types together.
