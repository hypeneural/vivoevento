---
name: Test Rules
description: Testing guidance for backend and frontend test files.
applyTo: "apps/api/tests/**/*.php,apps/web/src/**/*.test.ts,apps/web/src/**/*.test.tsx,apps/web/src/**/*.spec.ts,apps/web/src/**/*.spec.tsx,apps/landing/src/**/*.test.ts,apps/landing/src/**/*.test.tsx,apps/landing/src/**/*.spec.ts,apps/landing/src/**/*.spec.tsx"
---
# Test rules

- Keep tests narrow and tied to observable behavior or contracts.
- Prefer characterization before refactor when the current behavior is risky or unclear.
- Assert user-facing behavior, API contracts, and domain outcomes instead of implementation trivia.
- Update or add tests in the same change when behavior, copy, accessibility labels, or payloads change.
- For frontend tests, keep assertions resilient to harmless layout churn.
- For backend tests, use factories and module boundaries rather than global setup shortcuts when possible.
