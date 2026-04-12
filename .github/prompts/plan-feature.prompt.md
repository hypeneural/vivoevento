---
name: plan-feature
description: Build or refine a feature plan before implementation.
argument-hint: /plan-feature <feature, module, or doc path>
agent: Planner
---
Goal:
Produce a concrete implementation plan before any code changes.

Context:
- Follow [AGENTS.md](../../AGENTS.md)
- Use [code_review.md](../../code_review.md) as the review standard for risky areas
- Treat [docs/active](../../docs/active/README.md) as live context when it exists
- Treat [docs/execution-plans](../../docs/execution-plans/README.md) as the canonical home for executable plans
- Treat `docs/architecture/` as historical analysis and reference

Instructions:
- Read the nearest `AGENTS.override.md` files that apply to the touched area
- Validate the current implementation state from code, not from docs alone
- If a plan already exists, refine it instead of creating a duplicate
- If the task is large, recommend or create `docs/active/<feature>/STATUS.md` and `VERIFY.md`
- Keep the plan reviewable and execution-oriented

Return:
1. Goal
2. Current state validated from code
3. Constraints
4. Step-by-step implementation order
5. Validation plan
6. Risks and fallback
