# Evento Vivo API

Backend Laravel do monorepo `eventovivo`.

Este app concentra:

- API HTTP versionada em `/api/v1`
- autenticacao, permissao e contexto multi-tenant
- filas e processamento assincrono
- realtime via Reverb
- modulos de dominio em `app/Modules/*`

## Stack

- PHP `8.3`
- Laravel `13`
- PostgreSQL `16` + `pgvector`
- Redis `7`
- Horizon
- Reverb
- Telescope
- Pulse
- Sanctum + Fortify
- Spatie Activitylog, Data, Medialibrary e Permission

## Estrutura que importa

```text
apps/api/
  app/
    Modules/      # dominio por modulo
    Shared/       # contratos e utilitarios compartilhados da API
  config/
    modules.php   # registro oficial dos modulos
  routes/
    api.php
    channels.php
  tests/
    Feature/
    Unit/
```

Regra operacional:

- comportamento importante fica dentro de `app/Modules/<Modulo>`
- controllers ficam finos
- escrita de negocio vai para `Actions`
- integracao tecnica vai para `Services`
- trabalho assincrono vai para `Jobs`
- leitura complexa vai para `Queries`

## Modulos registrados

Fonte de verdade: `config/modules.php`.

Hoje o backend carrega estes dominios:

- `Organizations`, `Users`, `Roles`, `Auth`, `Clients`
- `Events`, `EventTeam`, `Dashboard`
- `Channels`, `InboundMedia`, `WhatsApp`, `Telegram`
- `MediaProcessing`, `ContentModeration`, `FaceSearch`, `EventPeople`, `MediaIntelligence`
- `Gallery`, `Wall`, `EventOperations`
- `Play`, `Hub`
- `Plans`, `Billing`, `Partners`, `Analytics`, `Audit`, `Notifications`

## Setup local

Pre-requisitos:

- PHP `8.3`
- Composer `2`
- PostgreSQL
- Redis

Bootstrap basico:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

## Desenvolvimento

Servidor HTTP:

```bash
php artisan serve
```

Fila local:

```bash
php artisan queue:work
```

Horizon:

```bash
php artisan horizon
```

Realtime:

```bash
php artisan reverb:start
```

## Validacao

Preferir o menor escopo que prove a mudanca.

Suite completa:

```bash
php artisan test
```

Teste focado:

```bash
php artisan test --filter=MediaProcessing
```

Style:

```bash
vendor/bin/pint
```

## Filas e realtime

Filas recorrentes no codigo e na operacao:

- `webhooks`
- `media-download`
- `media-fast`
- `face-index`
- `media-process`
- `media-safety`
- `media-vlm`
- `media-publish`
- `broadcasts`
- `notifications`
- `analytics`
- `billing`
- `whatsapp-inbound`
- `whatsapp-send`
- `whatsapp-sync`

Quando uma tarefa tocar payload HTTP, broadcast, fila, job ou persistencia:

- atualizar testes do escopo
- revisar docs relevantes
- evitar acoplamento direto entre modulos sem fronteira clara

## Docs de referencia

Ler nesta ordem:

1. `../../AGENTS.md`
2. `./AGENTS.override.md`
3. `../../docs/modules/module-map.md`
4. `../../docs/api/endpoints.md`
5. `../../docs/api/queues.md`
6. `../../docs/execution-plans/`
7. `../../docs/architecture/` como referencia historica

Arquivos uteis:

- `../../README.md`
- `../../docs/flows/media-ingestion.md`
- `../../docs/flows/whatsapp-inbound.md`
- `../../docs/execution-plans/production-vps-execution-plan.md`
- `../../docs/architecture/production-vps-runbook.md`

## Mudando a API com seguranca

Checklist curto:

1. localizar o modulo dono da mudanca
2. manter controller fino e regra dentro do dominio
3. validar impacto em request/resource/policy/job/broadcast
4. rodar o menor teste que cubra a alteracao
5. atualizar docs quando contrato ou operacao mudarem
