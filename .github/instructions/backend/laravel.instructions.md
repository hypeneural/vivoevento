---
name: Laravel Module Rules
description: Laravel and module conventions for the Evento Vivo API.
applyTo: "apps/api/**/*.php"
---
# Laravel module rules

- Keep ownership inside `apps/api/app/Modules/<Module>`.
- Controllers stay thin: receive request, delegate, return resource.
- Put write flows in Actions, technical logic in Services, async work in Jobs, and complex reads in Queries.
- Validate request input with Form Requests and shape HTTP output with Resources.
- Prefer existing module patterns and `apps/api/app/Shared` before adding new abstractions.
- Avoid direct cross-module coupling when a contract or interface is the cleaner boundary.
- When changing payloads, routes, policies, queues, or broadcasts, update tests and affected docs.
- New important models should come with migration, factory, and tests.
