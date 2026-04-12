---
name: implement-from-plan
description: Implement a feature from an existing execution plan with minimal drift.
argument-hint: /implement-from-plan <docs/execution-plans/...md>
agent: Implementer
---
Goal:
Implement the requested scope strictly from the referenced execution plan.

Context:
- Follow [AGENTS.md](../../AGENTS.md)
- Reuse [docs/execution-plans](../../docs/execution-plans/README.md)
- Update [docs/active](../../docs/active/README.md) when the task is long-running
- Use [code_review.md](../../code_review.md) as the finish-line review contract

Constraints:
- Keep changes local and reviewable
- Do not broaden scope without stating why
- Preserve public contracts unless the plan explicitly changes them
- Run the smallest relevant validation before closing

Done when:
- the plan scope is implemented
- relevant tests or checks ran
- docs changed if behavior or contracts changed
- remaining risk is stated plainly
