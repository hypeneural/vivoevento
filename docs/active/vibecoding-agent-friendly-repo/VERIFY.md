# Verify

## Scope

- agent-native repo baseline
- `docs/execution-plans/` separation
- API README replacement
- landing test stabilization
- fix for the previously failing API unit test
- prompt files, custom agents, review contract, and API-suite CI workflow

## Commands Run

```bash
cd apps/web && npm run type-check
cd apps/landing && npm run type-check
cd apps/landing && npm run test
cd apps/api && php artisan test tests/Unit/Modules/MediaProcessing/VideoMetadataExtractorServiceTest.php
cd c:/laragon/www/eventovivo && git diff --check
```

## Result

- `apps/web` type-check passed
- `apps/landing` type-check passed
- `apps/landing` full test suite passed with `157` tests
- the previously failing API unit test passed with `4` tests and `51` assertions
- diff integrity checks passed apart from non-blocking CRLF warnings on Windows

## Not Validated

- the complete `apps/api` test suite did not finish locally within the available timeout window
- the new `api-suite.yml` workflow has not yet been executed remotely from this environment

## Follow-up

- publish the scoped branch
- trigger `api-suite.yml`
- inspect CI output and update this file with the final API-suite result
