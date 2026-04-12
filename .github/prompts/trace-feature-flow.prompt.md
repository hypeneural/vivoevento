---
name: trace-feature-flow
description: Map a feature flow through backend, jobs, events, and frontend consumers.
argument-hint: /trace-feature-flow <route, endpoint, module, or feature>
agent: Planner
---
Goal:
Trace the real implementation flow for the requested feature.

Instructions:
- Start from the most concrete entry point available: route, page, controller, endpoint, or event
- Map the path through controller, request, action, service, query, job, event, broadcast, store, hook, page, and UI
- Prefer file references over paraphrase
- Flag contract boundaries and likely impact points
- Separate confirmed flow from inference

Return:
1. Entry point
2. Execution path
3. Contract boundaries
4. Realtime, queue, or persistence touchpoints
5. Risky change points
