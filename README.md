# Evento Vivo

Evento Vivo e o monorepo da plataforma SaaS de experiencias vivas para eventos.

O produto hoje combina:

- captura publica de fotos e videos;
- pipeline de midia com variantes, moderacao, IA e publicacao;
- galeria publica;
- wall/telao realtime;
- jogos mobile-first com ranking e analytics;
- hub publico do evento;
- painel administrativo multi-tenant;
- checkout publico por evento e trilha de billing;
- operacao de WhatsApp com adapters por provider;
- assets e scripts de deploy para VPS de producao.

## Panorama atual

- 3 apps ativas no monorepo:
  - `apps/api` -> backend Laravel
  - `apps/web` -> painel admin + experiencias publicas
  - `apps/landing` -> landing page principal
- 4 superficies de producao ja desenhadas:
  - `eventovivo.com.br`
  - `admin.eventovivo.com.br`
  - `api.eventovivo.com.br`
  - `ws.eventovivo.com.br`
- backend modular por dominio, com 25 modulos registrados em `apps/api/config/modules.php`
- painel React com rotas lazy-loaded, autenticacao real, permissoes por modulo, busca global e varias areas ja integradas via API
- landing page separada do admin, com build independente e deploy proprio
- stack operacional de VPS ja versionada em `deploy/` e `scripts/`

## Principais entregas da fase atual

- separacao oficial da landing page em `apps/landing`
- evolucao forte do painel administrativo, com mais modulos consumindo API real
- checkout publico por evento com Pix e cartao via Pagar.me v5
- onboarding do checkout com CTA para abrir o painel e tratamento de identidade existente
- notificacoes de billing por WhatsApp a partir da maquina de estados local
- pipeline de midia com `fast_preview`, variantes canonicas, hash perceptual, agrupamento de duplicatas e reprocessamento por etapa
- camada de `ContentModeration` com provider OpenAI e thresholds por evento
- camada de `MediaIntelligence` com VLM, `enrich_only` e `gate`
- `FaceSearch` com indexacao por rosto, `pgvector` e busca publica por selfie
- `Wall` com manager administrativo, player publico, fila `broadcasts`, boot/state publicos e contrato compartilhado em `packages/shared-types`
- `Play` com jogos `memory` e `puzzle`, sessoes, ranking, analytics e runtime PWA
- `Hub` com builder de hot site, presets por organizacao, hero, logos de patrocinador e tracking publico de CTA
- modulo `WhatsApp` provider-agnostic com adapters para Z-API e Evolution API
- endpoints de health, templates de Nginx/PHP-FPM/systemd, deploy por release, rollback e smoke test

## Superficies do produto

| Superficie | Dominio / rota | App | Papel |
| --- | --- | --- | --- |
| Landing | `eventovivo.com.br` | `apps/landing` | Site principal de captura e conversao |
| Painel admin | `admin.eventovivo.com.br` | `apps/web` | Operacao, configuracao e gestao |
| API | `api.eventovivo.com.br` | `apps/api` | Backend Laravel, auth, filas, webhooks e dominio |
| WebSocket | `ws.eventovivo.com.br` | `apps/api` + Reverb | Realtime do wall e de outros modulos |

### Experiencias publicas entregues hoje

As experiencias publicas do produto ficam principalmente em `apps/web`:

- `/upload/:code` -> upload publico do evento
- `/wall/player/:code` -> player publico do telao
- `/checkout/evento` -> checkout publico por pacote
- `/e/:slug` -> hub publico do evento
- `/e/:slug/gallery` -> galeria publica
- `/e/:slug/find-me` -> busca facial publica por selfie
- `/e/:slug/play` -> hub publico de jogos
- `/e/:slug/play/:gameSlug` -> jogo publico individual

## Stack atual

### Backend

| Camada | Tecnologia |
| --- | --- |
| Runtime | PHP 8.3 |
| Framework | Laravel 13 |
| Auth | Sanctum + Fortify |
| Permissoes | Spatie Laravel Permission |
| DTO / Data | Spatie Laravel Data |
| Auditoria | Spatie Activitylog |
| Midia | Spatie Medialibrary + Intervention Image |
| Filas | Horizon |
| Realtime | Reverb |
| Feature flags | Pennant |
| Observabilidade | Telescope + Pulse |

### Painel e experiencias web

| Camada | Tecnologia |
| --- | --- |
| UI | React 18 |
| Linguagem | TypeScript 5 |
| Build | Vite 5 |
| Estilo | TailwindCSS 3 |
| Componentes | shadcn/ui + Radix UI |
| Fetch / cache | TanStack Query 5 |
| Formularios | React Hook Form + Zod |
| Realtime | `pusher-js` compativel com Reverb |
| Games | Phaser |
| Animacao | Framer Motion |
| PWA | `vite-plugin-pwa` + Workbox |
| Testes | Vitest |

### Landing page

| Camada | Tecnologia |
| --- | --- |
| UI | React 18 |
| Build | Vite 5 |
| Linguagem | TypeScript 5 |
| Motion | GSAP + `@gsap/react` + `motion` |
| Interacao / storytelling | Lenis + Rive |
| Estilo | Sass modular |

### Infra local e producao

| Camada | Tecnologia |
| --- | --- |
| Banco | PostgreSQL 16 + `pgvector` |
| Cache / fila / sessao | Redis 7 |
| Storage local de dev | MinIO |
| E-mail de desenvolvimento | Mailpit |
| Web server | Nginx |
| PHP runtime | PHP-FPM |
| Edge | Cloudflare |

## Estrutura do monorepo

```text
eventovivo/
|-- apps/
|   |-- api/        # Backend Laravel
|   |-- landing/    # Landing page principal
|   `-- web/        # Painel admin + experiencias publicas
|-- deploy/         # Templates de producao (nginx, php-fpm, redis, systemd, etc.)
|-- docker/         # Infra auxiliar para dev
|-- docs/           # Arquitetura, fluxos, planos e mapa de modulos
|-- packages/
|   |-- contracts/      # Reserva para contratos formais e codegen
|   `-- shared-types/   # Tipos compartilhados, hoje com contrato do wall
|-- scripts/
|   |-- deploy/     # Deploy, rollback, healthcheck, smoke test
|   `-- ops/        # Bootstrap e validacao de host
|-- AGENTS.md
|-- Makefile
`-- docker-compose.yml
```

## Backend e API (`apps/api`)

### Fundamentos

O backend segue a regra principal do repositorio: features importantes devem nascer dentro de modulos de dominio em `apps/api/app/Modules/<Modulo>`.

Hoje a API ja entrega:

- envelope padrao `success`, `data`, `meta.request_id`
- `GET /health/live` e `GET /health/ready`
- middleware de contexto com `request_id` e `trace_id`
- configuracao de trusted proxies para operacao atras de Nginx/Cloudflare
- broadcasting via Reverb
- filas dedicadas por lane de processamento
- bootstrap multi-tenant por `Organization`, `User`, `Role` e `Event`

### Modulos de dominio

#### Core e operacao

| Modulo | Papel atual |
| --- | --- |
| `Organizations` | conta parceira, branding e membership |
| `Users` | usuarios, perfil e avatar |
| `Roles` | roles e permissoes |
| `Auth` | login por email/telefone, `me`, access matrix, OTP de registro e reset |
| `Clients` | gestao de clientes finais da organizacao |
| `Events` | entidade central, branding, links publicos, status e modo comercial |
| `EventTeam` | equipe operacional por evento |
| `Dashboard` | KPIs, eventos recentes, uploads por hora, fila de moderacao e alertas |

#### Captura, midia e IA

| Modulo | Papel atual |
| --- | --- |
| `Channels` | canais de entrada por evento |
| `InboundMedia` | upload publico, webhooks e normalizacao inicial |
| `WhatsApp` | instancias, conexao, mensagens, grupos, bindings, inbound e logs |
| `MediaProcessing` | variantes, moderacao final, publish, reprocessamento e metricas do pipeline |
| `ContentModeration` | safety moderation por evento via provider dedicado |
| `FaceSearch` | indexacao facial e busca por selfie |
| `MediaIntelligence` | VLM para caption, tags e decisao semantica |
| `Gallery` | curadoria e publicacao para galeria |
| `Wall` | telao realtime, manager, player e diagnosticos |

#### Experiencia, negocio e suporte

| Modulo | Papel atual |
| --- | --- |
| `Play` | jogos do evento, sessoes, ranking, analytics e runtime publico |
| `Hub` | hot site do evento, presets, hero, patrocinadores e CTA tracking |
| `Plans` | catalogo de planos e features |
| `Billing` | assinatura, trial, compra unica por evento, invoices, pagamentos e webhooks |
| `Partners` | camada B2B da plataforma |
| `Analytics` | metricas por plataforma e por evento |
| `Audit` | trilha de auditoria, timeline e filtros |
| `Notifications` | base de notificacoes e alertas de suporte |

### Capacidades importantes ja implementadas

#### Auth, acesso e sessao

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `GET /api/v1/access/matrix`
- jornada de OTP para registro por WhatsApp e reset de senha
- payload de sessao com:
  - usuario
  - organizacao atual
  - permissoes
  - modulos acessiveis
  - feature flags
  - assinatura atual
  - entitlements resolvidos

#### Events

O modulo `Events` ja cobre:

- CRUD de evento
- listagem com filtros
- detalhe rico do evento
- status comercial e modo de moderacao
- upload de ativos de branding
- links publicos do evento
- regeneracao de `slug`, `upload_slug` e identificadores publicos
- cards de configuracao por evento para:
  - `ContentModeration`
  - `MediaIntelligence`
  - `FaceSearch`

#### MediaProcessing e pipeline

O pipeline atual do repositorio ja passa por estas etapas:

```text
Webhook / Upload -> Download -> Variantes -> Safety -> VLM -> Moderacao -> Face Index -> Publish
```

Entregas atuais do dominio:

- `fast_preview`, `thumb`, `gallery` e `wall`
- `perceptual_hash`
- `duplicate_group_key`
- central de moderacao com feed cursorizado
- acoes de aprovar, rejeitar, favoritar, fixar e remover
- reprocessamento seletivo por `safety`, `vlm` e `face_index`
- cleanup propagado de artefatos ao excluir midia
- metricas por evento em `/api/v1/events/{id}/media/pipeline-metrics`

#### IA aplicada a midia

`ContentModeration`:

- configuracao por evento
- thresholds por categoria
- fila `media-safety`
- provider `OpenAiContentModerationProvider`
- fallback `NullContentModerationProvider`

`MediaIntelligence`:

- configuracao por evento
- `mode=enrich_only` ou `mode=gate`
- resposta estruturada com `decision`, `reason`, `short_caption` e `tags`
- historico em `event_media_vlm_evaluations`

`FaceSearch`:

- indexacao non-blocking em `face-index`
- quality gate por tamanho e score
- embeddings armazenados em `pgvector`
- busca no backoffice e busca publica por selfie
- retention e consentimento por evento

#### Wall realtime

`Wall` e uma das verticais mais maduras do produto hoje.

Backend:

- settings por evento
- start, stop, pause, expire e reset
- uploads de background e logo
- `boot` e `state` publicos por `wall_code`
- eventos em `wall.{wallCode}` e canais privados por evento
- listeners conectados aos eventos tipados do pipeline de midia

Frontend:

- manager administrativo
- player publico em `/wall/player/:code`
- sincronizacao realtime via Reverb
- runtime proprio de player
- auto-resync e suporte a estados do wall

#### Play

`Play` ja saiu do estagio de placeholder.

Hoje o modulo entrega:

- catalogo de jogos
- configuracao por evento
- `memory` e `puzzle`
- assets por jogo
- sessoes publicas
- `heartbeat`, `resume`, `finish` e `abandoned`
- ranking por jogo
- analytics por sessao e por jogo
- leaderboard em tempo real

#### Hub

O `Hub` hoje funciona como hot site mobile-first do evento:

- headline, subheadline e texto livre
- hero image
- logos de patrocinadores
- botoes preset e customizados
- builder config tipado
- presets por organizacao
- tracking de page view e clique
- insights operacionais por evento

#### Billing

O dominio de billing evoluiu bastante.

O que ja existe no codigo:

- catalogo publico de pacotes por evento
- jornada publica de trial
- checkout publico por evento
- leitura de status local do checkout
- assinatura recorrente da organizacao
- invoices e pagamentos
- webhook idempotente de gateway
- retry seguro do mesmo pedido
- refresh administrativo para troubleshooting
- `admin/quick-events`
- grants e entitlements comerciais

Pagar.me v5:

- Pix
- cartao tokenizado no frontend
- reconciliacao por webhook
- cancelamento / refund administrativo
- retry com a mesma `Idempotency-Key`
- notificacoes de `pix_generated`, `payment_paid`, `payment_failed` e `payment_refunded` via WhatsApp

#### WhatsApp

O modulo `WhatsApp` hoje e provider-agnostic e suporta dois adapters:

- Z-API
- Evolution API

Cobertura atual:

- CRUD de instancias
- QR code e pairing
- sync de status
- conexao e disconnect
- chats remotos
- grupos remotos
- binding grupo -> evento
- envio de texto, imagem, audio, reacao, carrossel e botao Pix
- logs de dispatch
- trilha bruta de inbound
- deduplicacao em `whatsapp_messages`

### Filas, realtime e observabilidade

Filas principais documentadas no codigo e nos docs:

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
- `default`
- `analytics`
- `billing`
- `whatsapp-inbound`
- `whatsapp-send`
- `whatsapp-sync`

Observabilidade ja presente no repositorio:

- Horizon
- Telescope
- Pulse
- `queue:monitor` para filas criticas
- `horizon:snapshot` agendado
- metricas de `inbound -> publish`
- health endpoints
- logs dedicados, incluindo canal de WhatsApp

## Painel admin e experiencias web (`apps/web`)

O app `apps/web` concentra o painel administrativo e varias superficies publicas do produto.

### O shell do painel ja entrega

- autenticacao real com `AuthProvider`
- persistencia de sessao
- consumo de `/auth/login` e `/auth/me`
- navegacao guiada por permissao e modulo acessivel
- code splitting por rota
- preloading de rotas do admin
- `AppErrorBoundary`
- busca global por eventos, midias e clientes
- notificacoes com Sonner / shadcn
- service worker e registro de PWA para as experiencias de `Play`

### Areas do painel

| Area | O que ja existe |
| --- | --- |
| Dashboard | KPIs, resumo de operacao e stats do backend |
| Events | listagem, criacao, edicao, detalhe, branding, links publicos e configuracoes por evento |
| Media | catalogo de midias com filtros |
| Moderation | feed em tempo real, detalhe da midia e bulk actions |
| Gallery | curadoria, destaque, pin e publicacao |
| Wall | manager, settings, estado e player publico |
| Play | manager do evento, catalogo, assets, analytics e paginas publicas |
| Hub | builder, presets, insights e pagina publica |
| Clients | CRUD e relacao com organizacao/plano |
| Analytics | plataforma, organizacao, cliente e evento |
| Audit | filtros, timeline e trilha de atividades |
| WhatsApp | settings, detalhe da instancia, conexao, grupos, chats e explorer remoto |
| Billing publico | checkout por pacote em `/checkout/evento` |
| Profile / Settings | perfil do usuario e configuracoes gerais |

### Integracao atual do frontend

O painel ja consome API real em varias frentes importantes, incluindo:

- auth e sessao
- dashboard
- events
- media
- moderation
- gallery
- hub
- play
- analytics
- audit
- clients
- WhatsApp
- checkout publico
- public hub / gallery / face search / play

Algumas areas ainda seguem em refinamento de UX, cobertura automatizada e acabamento operacional, mas o estado atual do app ja esta bem alem de um painel apenas mockado.

### Realtime, PWA e runtime publico

- `Wall` usa Reverb para boot e eventos realtime
- `Play` usa cache de assets e rotas publicas via service worker
- `registerSW({ immediate: true })` ja esta ligado em `src/main.tsx`
- o service worker prioriza shell, imagens e APIs publicas de `Play`

## Landing page (`apps/landing`)

A landing agora vive em app separado e esta voltada para conversao comercial do produto.

### O que ela comunica hoje

O storytelling atual da landing passa por:

- hero de experiencia
- ecossistema do produto
- galeria dinamica
- jogos interativos
- wall dinamico
- moderacao por IA
- busca facial
- confianca tecnica
- comparativo de posicionamento
- depoimentos
- segmentos de publico
- precos
- FAQ
- CTA final

### Implementacao atual

- build independente com Vite
- arquitetura em componentes e secoes dedicadas
- conteudo centralizado em `src/data/landing.ts`
- configuracao de CTA, WhatsApp e links em `src/config/site.ts`
- deploy estatico para o dominio principal

### Variaveis de ambiente da landing

`apps/landing/.env.example` ja cobre:

- `VITE_PUBLIC_SITE_URL`
- `VITE_ADMIN_URL`
- `VITE_PRIMARY_CTA_URL`
- `VITE_WHATSAPP_NUMBER`
- `VITE_WHATSAPP_MESSAGE`
- `VITE_INSTAGRAM_URL`
- `VITE_LINKEDIN_URL`

## Pacotes compartilhados

### `packages/shared-types`

Hoje o pacote compartilhado real em uso e `packages/shared-types`.

Ele ja contem o contrato do wall em `packages/shared-types/src/wall.ts`, cobrindo:

- payloads HTTP do player
- payloads dos eventos realtime
- nomes canonicos dos eventos do wall
- status publicos

### `packages/contracts`

`packages/contracts` continua reservado para a camada futura de contratos formais, schemas e codegen.

## Setup local

### Pre-requisitos

- PHP 8.3+
- Composer 2+
- Node.js 24 LTS
- PostgreSQL 16 com `pgvector`
- Redis 7
- opcionalmente Docker Desktop para a infraestrutura local

### 1. Subir a infraestrutura local

```bash
docker-compose up -d
```

Isso sobe:

- PostgreSQL 16 + `pgvector`
- Redis 7
- MinIO
- Mailpit

### 2. Instalar dependencias

Fluxo rapido:

```bash
make setup
```

Ou manualmente:

```bash
cd apps/api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

```bash
cd apps/web
npm install
cp .env.example .env
```

```bash
cd apps/landing
npm install
cp .env.example .env
```

### 3. Rodar o ambiente de desenvolvimento

Tudo junto:

```bash
make dev
```

Ou por app:

```bash
make api
make queue
make reverb
make web
make landing
```

### 4. Contratos de ambiente importantes

Arquivos principais:

- `/.env.example` -> visao de stack local
- `apps/api/.env.example` -> backend, billing, OpenAI, WhatsApp, filas e realtime
- `apps/web/.env.example` -> API base URL, Reverb, Pagar.me public key e toggles
- `apps/landing/.env.example` -> CTA e links da landing

## Operacao e deploy

O repositorio ja contem a base operacional da VPS dentro do proprio monorepo.

### `deploy/`

Templates versionados para:

- Nginx
- restore de IP real do Cloudflare
- OPcache
- PHP-FPM pool dedicado
- Redis
- PostgreSQL
- systemd
- sudoers minimo do usuario `deploy`
- logrotate
- exemplos de `.env` de producao

### `scripts/deploy/`

- `deploy.sh`
- `rollback.sh`
- `healthcheck.sh`
- `smoke-test.sh`

### `scripts/ops/`

- `bootstrap-host.sh`
- `install-configs.sh`
- `verify-host.sh`

### Documentacao operacional mais importante

- `docs/architecture/production-vps-runbook.md`
- `docs/architecture/production-vps-execution-plan.md`
- `docs/architecture/production-vps-command-sequence.md`
- `docs/architecture/landing-page-deployment.md`
- `docs/api/queues.md`

## Testes e validacoes

### Backend

```bash
cd apps/api
php artisan test
```

### Painel

```bash
cd apps/web
npm run test
npm run type-check
```

### Landing

```bash
cd apps/landing
npm run test
npm run type-check
```

### Atalhos uteis

```bash
make test
make lint
make type-check
```

## Mapa de documentacao

Para entender o estado atual do produto, estes arquivos sao os mais importantes:

- `AGENTS.md`
- `docs/modules/module-map.md`
- `docs/api/endpoints.md`
- `docs/api/queues.md`
- `docs/flows/media-ingestion.md`
- `docs/flows/whatsapp-inbound.md`
- `docs/architecture/billing-pagarme-v5-execution-plan.md`
- `docs/architecture/whatsapp-zapi-webhook-execution-plan.md`
- `docs/architecture/play-games-discovery.md`
- `docs/architecture/telao-ao-vivo-implementation.md`
- `docs/architecture/production-vps-runbook.md`
- `docs/architecture/production-vps-execution-plan.md`
- `docs/architecture/production-vps-command-sequence.md`

## Leitura honesta do estado atual

O repositorio hoje ja nao e mais apenas um esqueleto arquitetural.

O que ja esta forte:

- backend modular consistente
- painel com varias areas integradas a API real
- checkout publico de evento
- wall realtime
- play publico
- hub builder
- pipeline de midia com IA
- base provider-agnostic de WhatsApp
- scripts e templates reais de deploy

Os focos que ainda merecem endurecimento nas proximas rodadas sao:

- fechar completamente o intake canonico `WhatsApp -> InboundMedia -> EventMedia` para todos os cenarios de producao
- concluir a rodada final de homologacao real de cancelamento / estorno no billing
- consolidar a migracao das midias remanescentes para object storage como caminho principal
- continuar o hardening de throughput e readiness da VPS unica para multiplos eventos simultaneos

Em resumo:

> o Evento Vivo ja tem produto, runtime publico, billing, IA, realtime e operacao versionados no repo; o trabalho agora esta muito mais em endurecer e escalar do que em sair do zero.
