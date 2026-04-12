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
- the follow-up remote run for commit `722d1b4` completed as run `24316230270`
  - bootstrap passed
  - failure moved to `Run full API suite`
- a full local API-suite revalidation completed successfully with test-safe env overrides:
  - `1220` passed
  - `7` skipped
  - `2` todos
  - duration `549.13s`
- the latest API test-stability fixes were pushed in commit `29b05a5`
- the next remote validation is live as run `24316799489` and is currently `in_progress`

## Next Steps

1. inspect the final result of remote run `24316799489`
2. compare the remote result against the successful local full-suite run
3. start using `docs/active/<feature>/STATUS.md` and `VERIFY.md` for long-running product work beyond this repo-hardening initiative

## Risks

- the repo still has unrelated local changes in the working tree, so publish scope must stay explicit
- the API full suite still lacks a confirmed successful remote completion from this environment on the latest branch head

## Updated

- `2026-04-12`
