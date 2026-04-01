# Evento Vivo

Evento Vivo e um monorepo para uma plataforma SaaS de experiencias vivas em eventos. A ideia central do produto e transformar fotos, videos, interacoes e canais de captura em experiencias publicas e operacionais para casamentos, festas, eventos corporativos, festivais e parceiros B2B.

No estado atual do codigo, o repositorio combina:

- um backend modular em Laravel, organizado por dominio;
- um frontend SPA em React para painel administrativo;
- um player publico de wall/telao em tempo real;
- uma base de dominio de WhatsApp bastante avancada;
- uma serie de modulos ja funcionais;
- e algumas partes ainda em scaffold/placeholder, principalmente na pipeline completa de ingestao e processamento de midia.

## Resumo rapido

- Entidade central do produto: `Event`.
- Modelo de negocio: multi-tenant por `Organization`, com `Clients`, `Plans` e `Subscriptions`.
- Canais de entrada: upload publico, canais do evento e modulo de WhatsApp.
- Saidas de experiencia: galeria, wall/telao, play e hub publico.
- Backend: API-first, responses padronizadas e filas para trabalho assincrono.
- Frontend: autenticacao e wall ja falam com API real; a maior parte do painel ainda usa mocks.
- Feature mais madura hoje: wall realtime.
- Modulo mais complexo do backend: WhatsApp.

## O que o produto e

O Evento Vivo foi desenhado para parceiros como fotografos, agencias, cerimonialistas e operadores de evento criarem eventos com identidade visual propria, receberem conteudo dos convidados e exibirem esse conteudo em diferentes formatos:

- galeria publica do evento;
- wall/telao com slideshow e atualizacao em tempo real;
- hub publico do evento;
- jogos e experiencias interativas;
- operacao administrativa com permissao por papel;
- trilha de auditoria;
- camada de assinatura e plano.

Na modelagem atual, o fluxo esperado e:

1. uma organizacao entra na plataforma;
2. ela cria clientes e eventos;
3. cada evento habilita modulos como `live`, `wall`, `play` e `hub`;
4. o conteudo entra por upload publico, canais externos ou WhatsApp;
5. a midia e moderada e publicada;
6. os modulos publicos consomem essa midia e entregam a experiencia ao vivo.

## Estado real do repositorio

Esta secao e importante porque parte da documentacao interna descreve a arquitetura-alvo, e o codigo atual ainda esta em uma fase intermediaria entre estrutura pronta e implementacao completa.

| Area | Estado atual |
| --- | --- |
| Arquitetura modular do backend | Estrutura consistente e bem definida. Os modulos estao registrados em `apps/api/config/modules.php`. |
| Autenticacao e sessao | Implementadas. Login por email ou telefone/WhatsApp, token Sanctum, `/auth/me` e matrix de acesso. |
| Organizations, Clients, Events, Roles, Users | CRUD basico implementado. |
| Plans e Billing | Basico implementado. Seed de planos, assinatura simples e checkout local sem gateway real. |
| Audit | Implementado sobre `spatie/laravel-activitylog`, com listagem e timeline. |
| Wall | Implementado e e a vertical mais completa: settings, status, boot publico, eventos broadcast e player React. |
| WhatsApp | Implementado em profundidade: instancias, QR/status, envio assincrono, webhooks inbound, normalizacao e bindings. |
| InboundMedia e MediaProcessing | Estrutura, filas e jobs existem, mas varios jobs ainda estao em stub com comentarios. |
| Gallery | Leitura/admin basica implementada, dependente de `event_media`. |
| Play e Hub | Settings publicos e administrativos implementados de forma simples. Geracao de assets ainda e placeholder. |
| Analytics | Endpoints existem, mas hoje retornam placeholder. |
| Frontend SPA | Muito do painel ainda usa `src/shared/mock/data.ts`; auth e wall sao os pontos mais conectados a API real. |
| Packages compartilhados | `packages/contracts` e `packages/shared-types` ainda sao placeholders. |

## Stack real do codigo

### Backend

| Camada | Tecnologia |
| --- | --- |
| Runtime | PHP 8.3 |
| Framework | Laravel 13 (`apps/api/composer.json`) |
| Auth | Laravel Sanctum + Fortify |
| Permissoes | Spatie Laravel Permission |
| Data/DTO | Spatie Laravel Data |
| Auditoria | Spatie Activitylog |
| Midia | Spatie Medialibrary + Intervention Image |
| Filas | Laravel Horizon |
| Realtime | Laravel Reverb |
| Feature flags | Laravel Pennant |
| Observabilidade | Telescope + Pulse |

### Frontend

| Camada | Tecnologia |
| --- | --- |
| UI | React 18 |
| Linguagem | TypeScript 5 |
| Build | Vite 5 |
| Estilo | TailwindCSS 3 |
| Componentes | shadcn/ui + Radix UI |
| Roteamento | React Router 6 |
| Cache/fetch | TanStack Query 5 |
| Formularios | React Hook Form + Zod |
| Animacao | Framer Motion |
| Realtime | `pusher-js`, compatível com Reverb |
| Testes | Vitest |

### Infra local

| Servico | Tecnologia |
| --- | --- |
| Banco | PostgreSQL 16 |
| Cache e filas | Redis 7 |
| Storage | MinIO, compativel com S3 |
| E-mail de desenvolvimento | Mailpit |

## Estrutura do monorepo

```text
eventovivo/
├── apps/
│   ├── api/                # Backend Laravel
│   └── web/                # Frontend React SPA
├── docs/                   # Arquitetura, fluxos e mapa de modulos
├── packages/
│   ├── contracts/          # Placeholder para contratos e schemas
│   └── shared-types/       # Placeholder para tipos compartilhados
├── scripts/                # Geradores e automacoes
├── docker/                 # Infra auxiliar
├── docker-compose.yml      # Postgres, Redis, MinIO, Mailpit
├── Makefile                # Comandos utilitarios
└── AGENTS.md               # Convencoes do repositorio para agentes
```

## Arquitetura e convencoes

### Backend modular por dominio

O backend segue a regra principal do projeto: features importantes devem nascer dentro de um modulo em `apps/api/app/Modules/<Modulo>`.

Cada modulo tende a concentrar:

- `Http/Controllers` com controllers finos;
- `Actions` para operacoes de escrita;
- `Queries` para leitura complexa;
- `Models`, `Policies`, `Requests`, `Resources`;
- `Jobs` para assincronia;
- `Providers` para registrar rotas, listeners e dependencias.

Os providers de modulo sao carregados por `App\Providers\ModuleServiceProvider`, que le `apps/api/config/modules.php`.

### Envelope de API

Os controllers herdam de `App\Shared\Http\BaseController`, entao a API responde em formato padrao:

```json
{
  "success": true,
  "data": {},
  "meta": {
    "request_id": "req_xxxxxxxxxxxx"
  }
}
```

Respostas paginadas incluem `page`, `per_page`, `total` e `last_page` dentro de `meta`.

### Multi-tenant e controle de acesso

O modelo de acesso atual se organiza assim:

- `Organization` representa a conta parceira;
- `OrganizationMember` conecta usuarios a organizacoes;
- `User` pode ter papeis globais via Spatie e papel contextual na organizacao;
- `/api/v1/auth/me` devolve usuario, organizacao atual, modulos acessiveis e feature flags derivadas do plano.

Isso alimenta diretamente o frontend:

- menu lateral;
- visibilidade de modulos;
- branding da organizacao;
- feature flags;
- papeis e permissoes.

## Modulos de dominio

### Nucleo operacional

| Modulo | Papel |
| --- | --- |
| `Organizations` | Conta parceira, branding, time e escopo de dados |
| `Users` | Usuarios do sistema, perfil e avatar |
| `Roles` | Roles e permissoes |
| `Auth` | Login, logout, reset de senha e sessao bootstrap |
| `Clients` | Clientes finais de uma organizacao |
| `Events` | Agregado principal do produto |
| `EventTeam` | Time operacional por evento |

### Captura e midia

| Modulo | Papel |
| --- | --- |
| `Channels` | Canais de entrada por evento |
| `InboundMedia` | Recepcao e normalizacao de webhooks |
| `MediaProcessing` | Download, variantes, moderacao e publicacao |
| `WhatsApp` | Instancias, mensageria, webhooks inbound e bindings de grupo |

### Experiencia

| Modulo | Papel |
| --- | --- |
| `Gallery` | Galeria ao vivo e listagem publica |
| `Wall` | Telao/slideshow em tempo real |
| `Play` | Configuracoes para jogos do evento |
| `Hub` | Pagina/manifesto publico do evento |

### Suporte e negocio

| Modulo | Papel |
| --- | --- |
| `Plans` | Catalogo de planos e features |
| `Billing` | Assinaturas e checkout local |
| `Partners` | Camada B2B, hoje ainda minima |
| `Analytics` | Estrutura de metricas, hoje com endpoints placeholder |
| `Audit` | Log de atividades e timeline |
| `Notifications` | Estrutura inicial para notificacoes |

## Entidades centrais do dominio

As tabelas e models mais importantes para entender o sistema hoje sao:

- `organizations`: conta parceira, branding, dominio, timezone, status;
- `organization_members`: vinculo user x organizacao;
- `clients`: cliente final da organizacao;
- `events`: evento principal, datas, branding, URLs publicas e modo de moderacao;
- `event_modules`: habilitacao de `live`, `wall`, `play`, `hub` por evento;
- `event_channels`: origem/canal de captura;
- `inbound_messages`: payload normalizado de entrada;
- `event_media`: midia do evento, com status de processamento, moderacao e publicacao;
- `event_media_variants`: variantes como thumb, gallery e wall;
- `event_wall_settings`, `event_play_settings`, `event_hub_settings`: configuracoes 1:1 por evento;
- `plans`, `plan_prices`, `plan_features`: monetizacao;
- `subscriptions`: assinatura da organizacao;
- `whatsapp_*`: toda a base de instancias, chats, mensagens, logs e group bindings.

## Como o sistema funciona

### 1. Login, bootstrap de sessao e montagem do painel

O fluxo de autenticacao do backend e um dos pontos mais completos do repositorio:

1. `POST /api/v1/auth/login` aceita email ou telefone/WhatsApp + senha.
2. `LoginUserAction` localiza o usuario, normaliza telefone e gera token Sanctum.
3. O frontend persiste o token e chama `GET /api/v1/auth/me`.
4. `MeResource` devolve:
   - dados do usuario;
   - organizacao atual;
   - permissoes;
   - modulos acessiveis;
   - feature flags derivadas do plano;
   - assinatura atual.
5. O frontend usa isso para montar menu, branding, protecoes e features.

Tambem existe fluxo de recuperacao de senha com codigo de 6 digitos armazenado em cache. Em ambiente local o codigo e logado; o envio real ainda esta marcado como TODO.

### 2. Criacao e lifecycle do evento

`Events` e o coracao do produto.

Quando um evento e criado:

1. o payload passa por `StoreEventRequest`;
2. `CreateEventAction` gera slug unico;
3. o model `Event` gera `uuid` e `upload_slug`;
4. o sistema grava branding, privacidade e moderacao;
5. sao criados `event_modules` para `live`, `wall`, `play` e `hub`;
6. sao geradas URLs como:
   - `public_url`;
   - `upload_url`.

O evento pode depois ser publicado ou arquivado. O status do evento impacta principalmente as areas publicas e o wall.

### 3. Ingestao de midia

Hoje existem duas realidades diferentes para ingestao:

#### O que ja funciona

- upload publico via `POST /api/v1/public/events/{uploadSlug}/upload`;
- criacao direta de `event_media`;
- armazenamento do arquivo em disco `public`;
- dispatch de `GenerateMediaVariantsJob`.

Esse caminho passa por `PublicUploadController` e e o fluxo mais concreto de entrada de midia no estado atual do codigo.

#### O que ja esta desenhado, mas ainda incompleto

Os modulos `InboundMedia` e `MediaProcessing` ja tem tabelas, rotas, filas e jobs para o pipeline completo:

```text
Webhook -> InboundMedia -> MediaProcessing -> Gallery/Wall
```

Mas varios jobs ainda estao apenas descritos em comentarios:

- `ProcessInboundWebhookJob`
- `NormalizeInboundMessageJob`
- `DownloadInboundMediaJob`
- `GenerateMediaVariantsJob`
- `RunModerationJob`
- `PublishMediaJob`

Ou seja: a arquitetura esta pronta e nomeada, mas a implementacao fim a fim ainda nao esta fechada.

### 4. Moderacao, galeria e publicacao

`EventMedia` concentra tres eixos de status:

- `processing_status`
- `moderation_status`
- `publication_status`

Hoje o backend ja oferece:

- listagem de midias por evento;
- detalhe da midia com variantes;
- aprovar/rejeitar;
- remover;
- galeria admin;
- galeria publica filtrando `approved + published`.

Na pratica, a parte de consulta e moderacao existe. O que ainda depende de maior implementacao e o pipeline automatizado que deveria levar uma midia de "recebida" ate "publicada" de forma completa.

### 5. Wall realtime

O wall e a feature mais redonda do repositorio hoje.

#### Backend

O modulo `Wall` implementa:

- settings por evento;
- `wall_code` publico de 8 caracteres;
- estados `draft`, `live`, `paused`, `stopped`, `expired`;
- layouts `auto`, `polaroid`, `fullscreen`, `split`, `cinematic`;
- transicoes;
- uploads de background e logo;
- boot publico e endpoint leve de estado;
- broadcasts em `wall.{wallCode}`.

O `WallBroadcasterService` centraliza os payloads e a elegibilidade de midia para o telao.

#### Frontend

O player publico em `apps/web/src/modules/wall/player` tem:

- rota publica `/wall/player/:code`;
- bootstrap via API publica;
- conexao websocket via protocolo Pusher/Reverb;
- state machine propria (`useWallEngine`);
- auto-resync periodico;
- rotacao automatica de slides;
- layouts diferentes conforme orientacao da imagem;
- suporte a pause, expiracao e tela idle.

Tambem existe uma pagina admin de wall em `WallPage.tsx` consumindo API real. Hoje ela ainda usa `CURRENT_EVENT_ID = 1`, entao esta funcional como prova real de integracao, mas ainda nao totalmente contextualizada por rota/evento.

### 6. WhatsApp

O modulo `WhatsApp` e o dominio mais rico do backend atual.

Ele foi desenhado para ser provider-agnostic:

```text
Controller -> Service -> ProviderResolver -> ProviderAdapter -> Provider API
```

Hoje o adapter implementado e o da Z-API.

#### O que ja existe

- CRUD de instancias;
- armazenamento de tokens com cast criptografado;
- status e conexao por QR code;
- refresh de QR via job;
- sincronizacao de status via job;
- envio assincrono de mensagens:
  - texto
  - imagem
  - audio
  - reaction
  - carousel
  - botao PIX
- persistencia de `WhatsAppMessage`;
- logs de dispatch;
- webhooks inbound;
- normalizacao do payload do provider;
- deduplicacao de mensagens recebidas;
- `WhatsAppChat`;
- `WhatsAppGroupBinding` para ligar grupo a evento.

#### Como o envio funciona

1. controller valida request;
2. `WhatsAppMessagingService` verifica se a instancia esta conectada;
3. cria `WhatsAppMessage` com status `queued`;
4. dispara `SendWhatsAppMessageJob`;
5. o job resolve o provider correto;
6. chama o metodo do adapter;
7. atualiza status para `sent` ou `failed`;
8. grava `whatsapp_dispatch_logs`.

#### Como o inbound funciona

1. webhook entra sem auth;
2. `ProcessInboundWebhookJob` salva payload bruto em `whatsapp_inbound_events`;
3. normaliza via `ZApiWebhookNormalizer`;
4. `WhatsAppInboundRouter` cria/resolve chat;
5. persiste `WhatsAppMessage` inbound;
6. procura binding de grupo ativo;
7. dispara evento interno `WhatsAppMessageReceived`.

Existe um listener, `RouteInboundToMediaPipeline`, que tenta encaminhar mensagens com midia para a pipeline antiga de `InboundMedia`. Isso e um bom ponto de integracao arquitetural, mas o resultado fim a fim ainda depende da pipeline de midia que continua parcial.

#### Observacao operacional importante

O modulo configura filas dedicadas:

- `whatsapp-inbound`
- `whatsapp-send`
- `whatsapp-sync`

Mas o `config/horizon.php` do repositorio ainda nao supervisiona essas filas. Para operar o modulo de WhatsApp de verdade, e preciso adicionar workers/supervisores para elas.

### 7. Billing, planos, auditoria, hub e play

Esses modulos estao em niveis diferentes de maturidade:

- `Plans`: seed e leitura de planos/precos/features;
- `Billing`: checkout local simples, sem gateway real conectado;
- `Audit`: listagem de atividades e timeline com `spatie/laravel-activitylog`;
- `Hub`: settings e endpoint publico basico;
- `Play`: settings e endpoints para "gerar" memoria/puzzle, mas a geracao em si ainda e TODO;
- `Analytics`: estrutura de rotas e tabela existe, mas controller ainda responde placeholder.

## Frontend: como esta dividido

O frontend em `apps/web` cumpre dois papeis:

1. painel administrativo da operacao;
2. player publico do wall.

### Modulos do painel

Existem modulos visuais para:

- `dashboard`
- `events`
- `media`
- `moderation`
- `gallery`
- `wall`
- `play`
- `hub`
- `partners`
- `clients`
- `plans`
- `analytics`
- `audit`
- `settings`

### O que usa API real hoje

- autenticacao/sessao (`auth.service.ts`);
- pagina admin de wall;
- player publico de wall.

### O que ainda usa mocks

A maior parte das paginas de painel ainda le `apps/web/src/shared/mock/data.ts`.

Na pratica isso significa:

- layout, navegacao, permissoes e UX estao bem adiantados;
- grande parte do painel funciona como prototipo visual e de navegacao;
- a integracao real com backend ainda precisa ser expandida modulo a modulo.

### Modo mock

Por padrao, o frontend opera em modo mock se `VITE_USE_MOCK` nao for definido como `false`.

Isso afeta principalmente:

- login;
- sessao persistida;
- listagens de eventos, midias, parceiros, clientes, planos, auditoria e dashboards.

O login tambem oferece acesso rapido de desenvolvimento com usuarios mockados na propria UI.

## Setup local

### Requisitos

- PHP 8.3+
- Composer 2+
- Node.js 20+
- PostgreSQL 16+
- Redis 7+
- opcionalmente Docker Desktop para Postgres/Redis/MinIO/Mailpit

### 1. Subir a infraestrutura

Se quiser usar containers apenas para a infraestrutura:

```bash
docker-compose up -d
```

Isso sobe:

- PostgreSQL
- Redis
- MinIO
- Mailpit

### 2. Configurar a API

```bash
cd apps/api
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Observacao importante: o arquivo `apps/api/.env.example` ainda esta proximo do stub padrao do Laravel. Para rodar a stack completa com PostgreSQL, Redis, S3/MinIO e Reverb, use o `.env.example` da raiz como referencia e ajuste pelo menos:

- `DB_CONNECTION=pgsql`
- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `SESSION_DRIVER=redis`
- `FILESYSTEM_DISK=s3`
- `BROADCAST_CONNECTION=reverb`

### 3. Configurar o frontend

```bash
cd apps/web
npm install
copy .env.example .env
```

Se quiser usar o wall com websocket real, adicione tambem no `apps/web/.env`:

```env
VITE_REVERB_APP_KEY=eventovivo-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

Se quiser forcar o frontend a sair do modo mock:

```env
VITE_USE_MOCK=false
```

### 4. Rodar os servicos

Backend:

```bash
cd apps/api
php artisan serve
php artisan horizon
php artisan reverb:start
```

Frontend:

```bash
cd apps/web
npm run dev
```

### 5. Usando o Makefile

O repositório tem alguns atalhos uteis:

| Comando | O que faz |
| --- | --- |
| `make setup` | Instala API e Web e roda migrate/seed |
| `make dev` | Sobe API, Horizon, Reverb e frontend em paralelo |
| `make api` | Sobe so a API |
| `make web` | Sobe so o frontend |
| `make queue` | Inicia o Horizon |
| `make test` | Roda testes API + Web |
| `make fresh` | `migrate:fresh --seed` |
| `make lint` | Pint no backend + ESLint no frontend |

## Seeds e acessos de desenvolvimento

`DatabaseSeeder` carrega:

- roles e permissions;
- planos;
- usuarios e organizacao demo.

Credenciais seedadas:

| Perfil | Login | Senha |
| --- | --- | --- |
| Super Admin | `admin@eventovivo.com.br` | `password` |
| Parceiro demo | `parceiro@eventovivo.com.br` | `password` |
| Operador demo | `operador@eventovivo.com.br` | `password` |

## Filas, processamento assincrono e realtime

### Filas ja configuradas no Horizon

- `webhooks`
- `media-download`
- `media-process`
- `media-publish`
- `notifications`
- `default`
- `analytics`
- `billing`

### Filas adicionais esperadas por modulos

- `whatsapp-inbound`
- `whatsapp-send`
- `whatsapp-sync`

### Broadcasting

Os canais principais descritos no codigo hoje sao:

- `wall.{wallCode}` para o player publico;
- `event.{id}.wall`
- `event.{id}.gallery`
- `event.{id}.moderation`
- `event.{id}.play`

No estado atual, o fluxo mais solido de broadcast e o do wall publico.

## Testes

### Backend

O backend usa Pest e ja possui cobertura para areas principais como:

- login;
- `/auth/me` e access matrix;
- organizacoes;
- clients;
- eventos;
- billing;
- audit.

Rodar:

```bash
cd apps/api
php artisan test
```

### Frontend

O frontend esta com estrutura de teste pronta, mas hoje ha apenas um teste exemplo.

Rodar:

```bash
cd apps/web
npm run test
npm run type-check
```

## Documentacao complementar

Os arquivos mais uteis para continuar entendendo o projeto sao:

- `AGENTS.md`
- `docs/architecture/overview.md`
- `docs/modules/module-map.md`
- `docs/flows/media-ingestion.md`
- `docs/flows/whatsapp-inbound.md`
- `docs/flows/whatsapp-messaging.md`
- `docs/api/endpoints.md`
- `docs/api/queues.md`

## Leitura honesta do projeto

Se voce quiser resumir o estado do repositório em uma frase:

> O Evento Vivo ja tem um esqueleto arquitetural muito bom, um backend modular consistente, um wall realtime bem avancado e um modulo de WhatsApp forte; ao mesmo tempo, boa parte da pipeline completa de midia e da integracao do painel web com a API ainda esta em construcao.

Essa combinacao importa porque o repositorio ja responde bem a trabalho incremental: os dominios estao nomeados, as tabelas existem, os endpoints principais estao distribuidos por modulo e a proxima camada natural de evolucao e conectar ponta a ponta o que hoje ainda esta scaffoldado.
