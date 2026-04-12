# Status

## Feature

`vibecoding-agent-friendly-repo`

## Goal

Transform the monorepo from documentation-heavy into a repo with clear agent-facing boundaries, reusable IDE-native workflows, and predictable validation paths.

## Current State

- root baseline is in place with `AGENTS.md`, `.github/copilot-instructions.md`, `.github/instructions/`, `.codex/config.toml`, and area overrides
- `docs/execution-plans/` is separated from `docs/architecture/`
- `apps/api/README.md` is now an operational backend README
- `apps/landing` tests are green again
- the previously failing API unit test is fixed
- prompt files, custom agents, `code_review.md`, `docs/active/`, and an API-suite CI workflow now exist in the repo
- full local revalidation of the entire API suite is still pending because the local run exceeded the available timeout window

## Next Steps

1. publish the agent-native changes on an isolated branch
2. trigger `api-suite.yml` in CI and inspect the full API-suite result
3. start using `docs/active/<feature>/STATUS.md` and `VERIFY.md` for long-running product work

## Risks

- the repo still has unrelated local changes in the working tree, so publish scope must stay explicit
- the API full suite currently has proof in CI design, not yet in a completed remote run

## Updated

- `2026-04-12`
