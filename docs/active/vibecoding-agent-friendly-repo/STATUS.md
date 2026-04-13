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
- the first five narrow skills now exist in `.agents/skills/`, `apps/api/.agents/skills/`, `apps/web/.agents/skills/`, and `docs/.agents/skills/`
- `docs/execution-plans/_template/EXECUTION-PLAN.md` now exists as the canonical plan template
- a diagnostics runbook now exists in `docs/runbooks/codex-customizations-diagnostics-runbook.md`
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
- the latest remote validation for commit `29b05a5` completed as run `24316799489`
  - public status: `failure`
  - public annotations expose only `Process completed with exit code 2`
- run `24323766957` for commit `e46501d` also failed in `Run full API suite`
  - the new `Dump Laravel logs on failure` step ran successfully
  - unauthenticated public access still hides the detailed step log
  - the public page also exposed a GitHub Actions Node 20 deprecation warning for `actions/checkout@v4` and `actions/cache@v4`
- workflows were updated to use current Node 24 action majors:
  - `actions/checkout@v6`
  - `actions/cache@v5`
  - `actions/setup-node@v6` in the moderation workflow
- the exact workflow sequence was revalidated locally without extra env overrides:
  - `1222` passed
  - `7` skipped
  - `2` todos
  - `9995` assertions
  - duration `727.33s`
- `gh` was installed on this machine with `winget`
- `gh` is not authenticated yet, so detailed GitHub Actions log inspection is still blocked from this environment

## Next Steps

1. inspect the latest failing API Suite run with authenticated GitHub Actions logs instead of the public summary only
2. use the diagnostics runbook after each customization change or CI parity check
3. start using `docs/active/<feature>/STATUS.md` and `VERIFY.md` for long-running product work beyond this repo-hardening initiative

## Risks

- the repo still has unrelated local changes in the working tree, so publish scope must stay explicit
- the API full suite still lacks a confirmed successful remote completion on the latest branch head
- GitHub Actions root-cause analysis remains partially blind here until `gh auth login` or another authenticated log path is available

## Updated

- `2026-04-12`
