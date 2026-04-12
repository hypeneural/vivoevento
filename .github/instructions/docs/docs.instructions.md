---
name: Documentation Rules
description: Documentation conventions for architecture, plans, runbooks, and active feature docs.
applyTo: "docs/**/*.md"
---
# Documentation rules

- Write docs as operational artifacts, not prose dumps.
- Make the document type explicit: active context, execution plan, architecture, or runbook.
- Include context, decision, impact, and validation when relevant.
- Use exact file paths, commands, and ownership boundaries.
- Avoid duplicating source truth from code when a direct path reference is enough.
- `docs/active/<feature>/` is live context.
- `docs/architecture/` is historical analysis and reference unless a task explicitly says otherwise.
