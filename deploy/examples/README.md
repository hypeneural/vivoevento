# Production Env Examples

Esta pasta concentra os contratos de ambiente de producao por app.

Arquivos disponiveis:

- `apps-api.env.production.example`
- `apps-web.env.production.example`
- `apps-landing.env.production.example`

Observacoes:

- os segredos reais continuam fora do repo, em `/var/www/eventovivo/shared/.env`;
- `apps/api/.env.example` foi alinhado para `REDIS_CLIENT=phpredis`;
- o go-live base pode manter `MEDIA_INTELLIGENCE_PROVIDER=noop` e
  `FACE_SEARCH_*` em `noop` ate essas features entrarem de forma oficial.
