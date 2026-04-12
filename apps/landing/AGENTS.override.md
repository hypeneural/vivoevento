# Landing Override

## Scope

This override applies to work under `apps/landing`.

## Working Rules

- Optimize for performance, restrained motion, copy stability, and SEO/accessibility.
- Do not leak admin-panel patterns or backoffice abstractions into landing code.
- Keep content-driven sections easy to edit through the existing config/data structure before adding new runtime complexity.
- Heavy animation or runtime changes must stay intentional and local.
- When changing UX copy, structure, or motion behavior, review affected tests because the landing suite is sensitive to drift.

## Validation

- Start with `npm run type-check`.
- Add or update targeted tests when changing copy-sensitive or motion-sensitive sections.
- Pair heavier motion/runtime changes with focused validation instead of relying on manual confidence alone.
