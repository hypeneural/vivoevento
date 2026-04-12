# Verify

## Scope

- agent-native repo baseline
- `docs/execution-plans/` separation
- API README replacement
- landing test stabilization
- fix for the previously failing API unit test
- prompt files, custom agents, review contract, and API-suite CI workflow

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
git push origin codex/agent-native-p1
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
  - current status at the time of this update: `in_progress`
- local exact reproduction of the workflow bootstrap is not possible on this machine because `ext-redis` is not installed locally
- the focused API unit test still passes with env overrides that avoid local Redis-extension coupling

## Not Validated

- the complete `apps/api` test suite did not finish locally within the available timeout window
- the latest remote `API Suite` run for commit `722d1b4` has not yet finished from this environment

## Follow-up

- inspect the final result of `https://github.com/hypeneural/vivoevento/actions/runs/24316230270`
- if it fails again, capture the next concrete failing step and fix it directly
- once it passes, record the remote run URL and final status here
