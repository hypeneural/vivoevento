# Decisions

## Decision Log

### `2026-04-12` - Separate execution plans from historical architecture docs

Context:

- `docs/architecture/` was mixing historical analysis and executable plans
- the audit identified this as a source of context competition for agents

Decision:

- move execution and implementation plans into `docs/execution-plans/`

Why:

- plan discovery becomes predictable
- `docs/architecture/` can remain historical and diagnostic by default

Impact:

- `docs/execution-plans/`
- references across README, tests, and docs

### `2026-04-12` - Use IDE-native workflows before skills

Context:

- the audit prioritized prompt files, custom agents, and a review contract before skills and hooks

Decision:

- create workspace prompt files, custom agents, and `code_review.md` first

Why:

- lower context overhead
- clearer operational path in VS Code
- better alignment with official docs

Impact:

- `.github/prompts/`
- `.github/agents/`
- `code_review.md`

### `2026-04-12` - Revalidate the full API suite in CI instead of forcing more local timeout

Context:

- the complete API suite exceeded the local timeout window more than once
- the previously failing unit test was already fixed and verified in isolation

Decision:

- add a dedicated GitHub Actions workflow for the full API suite

Why:

- CI is the right place for a longer, repeatable run
- avoids claiming full local proof that does not exist

Impact:

- `.github/workflows/api-suite.yml`

### `2026-04-12` - Make customization diagnostics a first-class runbook

Context:

- the repo now has root instructions, overrides, prompt files, agents, skills, and project config
- after this point, the main failure mode shifts from missing context to wrong precedence or missing visibility

Decision:

- add a canonical diagnostics runbook for Codex customizations and CI parity checks

Why:

- the team needs one repeatable way to confirm what Codex is loading
- `codex debug prompt-input` is useful, but only if its use is standardized
- CI failures are now harder to inspect than to reproduce locally

Impact:

- `docs/runbooks/codex-customizations-diagnostics-runbook.md`
- `docs/active/vibecoding-agent-friendly-repo/STATUS.md`
- `docs/active/vibecoding-agent-friendly-repo/VERIFY.md`
