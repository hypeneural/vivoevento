# Code Review Contract

Use this file for `/review`, review-oriented prompts, and reviewer agents.

## Review Order

Prioritize:

1. correctness and regressions
2. contract drift across API, events, queues, and shared types
3. missing validation or incomplete test coverage
4. security, data integrity, and destructive edge cases
5. performance problems with real product impact

Do not lead with style or formatting comments unless they hide a real bug or make the code materially harder to maintain.

## Output Shape

When issues exist:

- list findings first, ordered by severity
- include concrete file references
- explain the risk, not just the symptom
- note the missing test or validation when relevant

After findings:

- list open questions or assumptions
- add a short summary only if it helps decision-making

When no issues exist:

- say that clearly
- still mention residual risk or test gaps if they remain

## What To Check

- public HTTP payloads
- request validation, policies, and authorization boundaries
- queue, event, and broadcast contract changes
- schema or migration impact
- shared types or frontend consumers
- accessibility and keyboard behavior for user-facing UI
- cache, realtime, and state synchronization risks
- rollback or fallback behavior when the path is operationally sensitive

## Validation Expectations

- prefer the smallest relevant validation for the changed scope
- call out when tests were not run
- distinguish between local proof, partial proof, and full-suite proof
- do not claim full confidence when only a subset was validated
