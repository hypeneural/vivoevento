---
name: verify-feature
description: Validate a changed feature and record exactly what was checked.
argument-hint: /verify-feature <feature, module, or changed area>
agent: Reviewer
---
Goal:
Run the smallest validation that proves the touched scope and summarize the result precisely.

Context:
- Follow [AGENTS.md](../../AGENTS.md)
- Use [code_review.md](../../code_review.md)
- For long-running work, update `docs/active/<feature>/VERIFY.md`

Instructions:
- Choose the smallest relevant commands for the changed scope
- Distinguish clearly between full-suite and targeted validation
- Capture failed, skipped, or timed-out checks honestly
- Call out remaining risk after validation

Return:
1. Commands run
2. Scope validated
3. Result
4. Remaining risk
