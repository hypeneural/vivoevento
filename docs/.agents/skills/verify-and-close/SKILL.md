---
name: verify-and-close
description: Record closure evidence for long-running work in `docs/active/feature-name/STATUS.md` and `VERIFY.md`. Use when finishing, handing off, or reviewing a feature thread that already has active docs or should have them.
---

# Verify And Close

Use this skill to leave a verifiable handoff instead of a vague summary.

## Workflow

1. Update `docs/active/<feature>/VERIFY.md` with exact commands, exact scope, exact result, and exact gaps.
2. Update `docs/active/<feature>/STATUS.md` with current state, next steps, and risks.
3. Update `DECISIONS.md` only when a decision actually changed.
4. Distinguish clearly between:
   - passed
   - failed
   - partial
   - not validated
5. If only a subset ran, say so explicitly. Do not claim full validation.

## Required paths

- `docs/active/_template/STATUS.md`
- `docs/active/_template/VERIFY.md`
- `docs/active/_template/DECISIONS.md`

## Output checklist

- exact commands copied as they were run
- validation scope named precisely
- remaining gap or next CI step recorded
- final handoff written for another agent or engineer to continue without chat history

## Avoid

- summarizing validation without commands
- hiding failures inside generic prose
- updating historical docs when the state belongs in `docs/active/<feature>/`
