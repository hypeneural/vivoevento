# Verify

## Scope

- agent-native repo baseline
- `docs/execution-plans/` separation
- API README replacement
- landing test stabilization
- fix for the previously failing API unit test
- prompt files, custom agents, review contract, and API-suite CI workflow
- first narrow skill set and execution-plan template
- diagnostics runbook and exact workflow-parity revalidation for the API suite

## Commands Run

```powershell
cd apps/web && npm run type-check
cd apps/landing && npm run type-check
cd apps/landing && npm run test
cd apps/api && php artisan test tests/Unit/Modules/MediaProcessing/VideoMetadataExtractorServiceTest.php
cd c:/laragon/www/eventovivo && git diff --check
cd apps/api && php artisan key:generate --ansi
cd apps/api && php artisan config:clear --ansi
$env:REDIS_CLIENT='predis'; $env:CACHE_STORE='array'; $env:SESSION_DRIVER='array'; $env:QUEUE_CONNECTION='sync'; $env:BROADCAST_CONNECTION='log'; cd apps/api; php artisan test tests/Unit/Modules/MediaProcessing/VideoMetadataExtractorServiceTest.php --compact
$env:REDIS_CLIENT='predis'; $env:CACHE_STORE='array'; $env:SESSION_DRIVER='array'; $env:QUEUE_CONNECTION='sync'; $env:BROADCAST_CONNECTION='log'; cd apps/api; Copy-Item .env.example .env -Force; php artisan key:generate --ansi; php artisan test --compact --stop-on-failure
git push origin codex/agent-native-p1
codex --help
codex debug --help
codex debug prompt-input "check"
cd apps/api && codex debug prompt-input "check"
cd docs && codex debug prompt-input "check"
$headers = @{ 'User-Agent' = 'Codex' }; $uri = 'https://api.github.com/repos/hypeneural/vivoevento/actions/workflows/api-suite.yml/runs?branch=codex/agent-native-p1&per_page=5'; (Invoke-RestMethod -Uri $uri -Headers $headers).workflow_runs | Select-Object id,status,conclusion,head_sha,html_url
cd apps/api && Copy-Item .env.example .env -Force
cd apps/api && php artisan key:generate --ansi
cd apps/api && php artisan config:clear --ansi
cd apps/api && php artisan test --compact
winget install --id GitHub.cli -e --source winget --accept-source-agreements --accept-package-agreements --silent
gh --version
gh auth status
gh run view 24316799489 --repo hypeneural/vivoevento --json name,workflowName,conclusion,status,url,event,headBranch,headSha,jobs
gh run view 24316799489 --repo hypeneural/vivoevento --log
rg "actions/(checkout|cache)@|setup-node@" .github/workflows -n
```

## Result

- `apps/web` type-check passed
- `apps/landing` type-check passed
- `apps/landing` full test suite passed with `157` tests
- the previously failing API unit test passed with `4` tests and `51` assertions
- diff integrity checks passed apart from non-blocking CRLF warnings on Windows
- the first remote `API Suite` execution was triggered and failed before tests in `Prepare environment`
- the failure was reduced to two concrete bootstrap issues:
  - `apps/api/.env.example` had `MEDIA_INTELLIGENCE_OPENROUTER_APP_NAME=Evento Vivo` without quotes
  - `.github/workflows/api-suite.yml` did not install the `redis` PHP extension while the app bootstraps with `REDIS_CLIENT=phpredis`
- both bootstrap issues were fixed and pushed in commit `722d1b4`
- the follow-up `API Suite` run was triggered automatically on branch push:
  - run id: `24316230270`
  - URL: `https://github.com/hypeneural/vivoevento/actions/runs/24316230270`
  - final status for commit `722d1b4`: `failure`
  - bootstrap passed; the failure moved to `Run full API suite`
- local exact reproduction of the workflow bootstrap is not possible on this machine because `ext-redis` is not installed locally
- the focused API unit test still passes with env overrides that avoid local Redis-extension coupling
- the full local API suite completed successfully after isolating the rollback FaceSearch test and giving the testing context a stable `APP_KEY`
  - `1220` passed
  - `7` skipped
  - `2` todos
  - duration `549.13s`
- the API test-stability fixes were pushed in commit `29b05a5`
- the next `API Suite` run was triggered automatically on branch push:
  - run id: `24316799489`
  - URL: `https://github.com/hypeneural/vivoevento/actions/runs/24316799489`
  - final public status: `failure`
  - public annotations expose only `Process completed with exit code 2`
- Codex CLI diagnostics are now validated locally for this repo:
  - root `codex debug prompt-input "check"` shows the root `AGENTS.md` and shared root skills
  - `apps/api` shows `API Override` and `laravel-module-change`
  - `docs` shows `Docs Override` and `verify-and-close`
- the canonical execution-plan template now exists at `docs/execution-plans/_template/EXECUTION-PLAN.md`
- the diagnostics runbook now exists at `docs/runbooks/codex-customizations-diagnostics-runbook.md`
- the exact API workflow sequence now also passes locally without extra env overrides:
  - `1222` passed
  - `7` skipped
  - `2` todos
  - `9995` assertions
  - duration `727.33s`
- GitHub CLI was installed successfully with `winget`
- `gh --version` reports `2.89.0`
- `gh auth status` reports no authenticated GitHub hosts
- unauthenticated `gh run view` is blocked and asks for `gh auth login` or `GH_TOKEN`
- run `24323766957` for commit `e46501d` completed as `failure`
  - failed step: `Run full API suite`
  - `Dump Laravel logs on failure` completed successfully
  - public page still requires sign-in to view logs
- public Actions page exposed a Node 20 deprecation warning for `actions/checkout@v4` and `actions/cache@v4`
- official GitHub action release pages confirm current Node 24 majors:
  - `actions/checkout@v6`
  - `actions/cache@v5`
  - `actions/setup-node@v6`
- local workflows were updated to these current majors
- run `24323890981` for commit `104eb15` completed as `failure`
  - failed step: `Run full API suite`
  - public page no longer shows the Node 20 action-runtime warning
  - public page still requires sign-in to view detailed logs

## Not Validated

- the root cause for remote run `24316799489` is still not visible from this environment because the public run page exposes only summary status
- the latest branch head still lacks a confirmed successful remote completion of the full API suite
- detailed GitHub Actions logs are still not validated because `gh` is installed but not authenticated

## Follow-up

- inspect `https://github.com/hypeneural/vivoevento/actions/runs/24316799489` with authenticated logs or `gh run view --log`
- if the remote failure persists, compare its log against the now-successful exact local workflow sequence
