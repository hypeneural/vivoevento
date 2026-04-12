# Docs Override

## Scope

This override applies to work under `docs`.

## Working Rules

- Classify docs as `active`, `execution-plans`, `architecture`, or `runbooks` whenever you add or move files.
- Treat `docs/active/<feature>/` as live feature context.
- Treat `docs/architecture/` as historical analysis and reference by default, not the active spec unless explicitly promoted.
- Keep operational docs concrete: include exact commands, file paths, validation notes, and current assumptions.
- When a doc becomes the new source of truth for an in-flight feature, move or mirror it into the appropriate `docs/active/<feature>/` location instead of relying on memory.

## Validation

- Check that paths, commands, and filenames in the edited doc still exist.
- When a doc describes validation, record what actually ran and what did not.
