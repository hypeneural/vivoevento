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
- the API-suite workflow was pushed to `codex/agent-native-p1` and executed remotely
- the first remote run failed in bootstrap before tests, and the failure was traced to:
  - an unquoted value with spaces in `apps/api/.env.example`
  - missing `redis` extension in the GitHub Actions PHP setup
- both CI bootstrap issues are fixed and pushed
- the follow-up remote run for commit `722d1b4` is live as run `24316230270` and is currently `in_progress`
- full local revalidation of the entire API suite is still pending because the local run exceeded the available timeout window

## Next Steps

1. inspect the final result of remote run `24316230270` for commit `722d1b4`
2. if the run passes, treat CI as the current proof point for full API-suite validation
3. start using `docs/active/<feature>/STATUS.md` and `VERIFY.md` for long-running product work beyond this repo-hardening initiative

## Risks

- the repo still has unrelated local changes in the working tree, so publish scope must stay explicit
- the API full suite still lacks a confirmed successful remote completion from this environment

## Updated

- `2026-04-12`
