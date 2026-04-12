# Web Override

## Scope

This override applies to work under `apps/web`.

## Working Rules

- Work module-first in `src/modules/*`.
- Pages orchestrate composition and routing; hooks, services, and module helpers hold logic.
- Use TanStack Query for server state. Do not reimplement fetch/cache flows with ad hoc state.
- Preserve accessibility and existing route behavior when editing screens, forms, or interactive components.
- Review backend contract impact whenever a UI change depends on endpoint, payload, permission, or realtime behavior changes.
- Prefer existing shared primitives in `src/components/ui`, `src/shared/components`, and local module patterns before adding new abstractions.

## Validation

- Run the smallest relevant check first: `npm run type-check`, targeted Vitest files, then broader coverage only when needed.
- If a route, form, or interactive surface changes, include the relevant test or characterization coverage when practical.
