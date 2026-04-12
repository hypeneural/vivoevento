---
name: review-module
description: Review a module or diff for bugs, regressions, and missing validation.
argument-hint: /review-module <module path, feature, or diff scope>
agent: Reviewer
---
Goal:
Review the requested module or change set using the repo review contract.

Context:
- Follow [code_review.md](../../code_review.md)
- Use [AGENTS.md](../../AGENTS.md) and the nearest override files for local standards

Instructions:
- Findings first
- Prioritize correctness, regressions, contract drift, and missing tests
- Include file references
- State open questions separately from confirmed findings
- If no issues are found, say so explicitly and note residual risk or test gaps
