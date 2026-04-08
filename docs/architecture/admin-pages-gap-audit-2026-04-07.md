# Auditoria Das Paginas Do Projeto

Data da inspecao: `2026-04-07`

## Escopo

Esta auditoria cobre as paginas definidas no frontend em `apps/web/src/App.tsx`, incluindo:

- rotas administrativas
- hubs por modulo
- subpaginas de CRUD e detalhe
- jornadas publicas relevantes

O foco foi encontrar:

- dados ainda mockados
- botoes ou menus sem acao real
- filtros potencialmente nao conectados ao backend
- gaps de integracao entre frontend e backend

## Metodologia

A validacao foi feita por inspecao estatica do monorepo, cruzando:

- roteamento SPA em `apps/web/src/App.tsx`
- preload de rotas em `apps/web/src/app/routing/route-preload.ts`
- imports remanescentes de `apps/web/src/shared/mock/data.ts`
- services frontend por modulo
- controllers, requests e queries no backend Laravel
- pontos com `placeholder`, `TODO`, `not implemented` e similares

Comandos usados como apoio:

- `rg -n "shared/mock/data|mock[A-Z]" apps/web/src`
- `rg -n "TODO|FIXME|placeholder|not implemented|indisponivel" apps/web/src apps/api/app/Modules`
- `npm run type-check` em `apps/web`  -> `ok`

Importante:

- este relatorio nao substitui click-through manual no navegador
- ele valida o wiring real do codigo e separa bem o que esta conectado do que ainda e mock ou capability gap

## Resumo Executivo

Estado geral:

- o painel administrativo principal nao esta mais apoiado em listas mockadas para `dashboard`, `events`, `media`, `moderation`, `gallery`, `partners`, `clients`, `settings`, `analytics`, `audit`, `whatsapp` e boa parte de `plans`
- a maior parte dos CRUDs e filtros inspecionados esta ligada a services reais e a endpoints/backend queries correspondentes
- nao encontrei evidencia de que as paginas centrais de cadastro e operacao ainda estejam lendo `mockEvents`, `mockPartners`, `mockClients`, `mockPlans` ou equivalentes em runtime

Gaps reais encontrados:

1. o header global ainda usa notificacoes mockadas e o backend de notificacoes esta placeholder
2. o app ainda suporta modo auth mock via `VITE_USE_MOCK`, incluindo troca de perfil no header quando essa flag estiver ligada
3. a pagina `/plans` depende do gateway configurado; checkout recorrente via `pagarme` ainda nao foi implementado
4. existe um pequeno gap de consistencia no preload de rota para `/dashboard`, embora a rota administrativa real seja `/`

## Achados Prioritarios

### 1. Notificacoes do header continuam mockadas

Evidencia:

- `apps/web/src/app/layouts/AppHeader.tsx`
- `apps/web/src/shared/mock/data.ts`
- `apps/api/app/Modules/Notifications/routes/api.php`

O que foi encontrado:

- o sino do header calcula `unreadCount` em cima de `mockNotifications`
- o dropdown renderiza `mockNotifications.slice(0, 4)`
- os itens do dropdown sao apenas informativos; nao ha navegacao, `mark as read` ou fetch remoto
- o backend de notificacoes ainda nao expoe endpoints; o arquivo de rotas do modulo esta literalmente placeholder

Impacto:

- o problema afeta transversalmente todas as paginas admin, porque o header aparece em `dashboard`, `events`, `media`, `settings`, `plans` etc
- visualmente parece uma feature pronta, mas operacionalmente ainda e mock

Conclusao:

- este e o principal mock real ainda exposto de forma global no painel

Recomendacao:

- ou implementar o modulo de notificacoes fim a fim
- ou esconder/desligar o sino ate existir backend real

### 2. O modo auth mock ainda existe no app inteiro via `VITE_USE_MOCK`

Evidencia:

- `apps/web/src/modules/auth/services/auth.service.ts`
- `apps/web/src/app/providers/AuthProvider.tsx`
- `apps/web/src/app/layouts/AppHeader.tsx`

O que foi encontrado:

- `AUTH_USE_MOCK` continua baseado em `import.meta.env.VITE_USE_MOCK !== 'false'`
- `auth.service.ts` ainda implementa fluxo completo para login mock, registro mock, forgot password mock e sessao mock
- `AuthProvider` ainda popula `availableUsers` a partir de `mockUsers` quando a flag estiver ativa
- o header ainda exibe `Trocar Perfil (Dev)` quando `USE_MOCK` estiver ativo

Impacto:

- nao e mais um vazamento igual ao quick access antigo da tela de login, mas continua sendo um risco de configuracao
- se um ambiente nao-dev subir com `VITE_USE_MOCK` ligado, o app volta a expor troca de perfil dev e sessao mock

Conclusao:

- isto hoje e um `configuration gap`, nao um mock acidental hardcoded na UI principal

Recomendacao:

- garantir `VITE_USE_MOCK=false` em qualquer ambiente que nao seja local/dev
- idealmente endurecer o gating para tambem depender de `import.meta.env.DEV`

### 3. `/plans` esta conectado, mas o checkout recorrente por `pagarme` nao esta completo

Evidencia:

- `apps/web/src/modules/plans/PlansPage.tsx`
- `apps/web/src/modules/plans/api.ts`
- `apps/api/app/Modules/Billing/Actions/CreateSubscriptionCheckoutAction.php`
- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeBillingGateway.php`
- `apps/api/config/billing.php`
- `apps/api/app/Modules/Billing/Services/ManualBillingGateway.php`

O que foi encontrado:

- a pagina `/plans` consome API real para catalogo, assinatura e invoices
- o botao `Ativar plano` chama `POST /billing/checkout`
- o backend de assinatura chama `createSubscriptionCheckout(...)` no gateway configurado
- se o provider de assinatura for `manual`, o fluxo fecha porque `ManualBillingGateway` retorna pagamento como `paid`
- se o provider de assinatura for `pagarme`, o metodo `createSubscriptionCheckout(...)` lanca `RuntimeException('Pagar.me subscription checkout is not implemented in phase 1.')`

Impacto:

- a pagina em si nao esta mockada
- mas existe gap real para producao recorrente se o projeto habilitar `BILLING_GATEWAY_SUBSCRIPTION=pagarme`

Conclusao:

- `plans` esta funcional como painel administrativo e para fluxo manual
- `plans` ainda nao esta pronta para recorrencia Pagar.me full

Recomendacao:

- tratar isso como gap comercial de backend
- nao habilitar assinatura recorrente Pagar.me sem implementar o metodo faltante

## Achados Secundarios

### 4. Preload de `/dashboard` nao reflete a rota admin real

Evidencia:

- `apps/web/src/App.tsx`
- `apps/web/src/app/routing/route-preload.ts`

O que foi encontrado:

- a rota admin real de dashboard e a rota index `/`
- o arquivo de preload tambem contem matcher para `^/dashboard(?:/|$)`
- nao encontrei rota SPA equivalente exposta em `App.tsx`

Impacto:

- baixo
- nao quebra CRUD nem filtros
- e um pequeno sinal de drift entre roteamento real e aquecimento de rotas

Recomendacao:

- remover o matcher morto
- ou criar redirect explicito `/dashboard -> /`

## Matriz Das Paginas Administrativas

| Area | Paginas / Subpaginas | Estado da integracao | CRUD / filtros | Gaps observados |
|---|---|---|---|---|
| Dashboard | `/` | API real via `/dashboard/stats` e `/dashboard/search` | sem CRUD pesado; cards, charts e busca global ligados | herda o gap global do header mockado |
| Events | `/events`, `/events/create`, `/events/:id`, `/events/:id/edit` | API real | listagem, criacao, edicao, publish/archive, identifiers, blacklist e settings conectados | nenhum mock direto encontrado |
| Event Wall | `/events/:id/wall` | API real | configuracoes, comandos operacionais, simulacao e diagnostico conectados | nenhum gap mockado encontrado |
| Event Play | `/events/:id/play` | API real | configuracoes, criacao de jogo, analytics e catalogo conectados | nenhum gap mockado encontrado |
| Media | `/media` | API real | filtros e detalhamento conectados ao backend | nenhum gap mockado encontrado |
| Moderation | `/moderation` | API real | feed, bulk actions, approve/reject, block sender e realtime conectados | nenhum gap mockado encontrado |
| Gallery | `/gallery` | API real | filtros, publish/hide, favorite/pin e block sender conectados | nenhum gap mockado encontrado |
| Wall Hub | `/wall` | API real | hub de eventos e links operacionais conectados | nenhum gap mockado encontrado |
| Play Hub | `/play` | API real | hub de eventos conectado | nenhum gap mockado encontrado |
| Hub Builder | `/hub` | API real | presets, save, upload hero, sponsor logo e insights conectados | nenhum mock direto encontrado |
| WhatsApp | `/settings/whatsapp`, `/settings/whatsapp/:id` | API real | listagem, create/update/delete, favorite, test connection, QR e explorer conectados | explorer remoto e provider-dependent, nao mock |
| Partners | `/partners` + detail sheet | API real | listagem, create/update/suspend/remove, staff, grants, activity conectados | nenhum gap mockado encontrado |
| Clients | `/clients` | API real | listagem, create/update/delete e filtros conectados | nenhum gap mockado encontrado |
| Plans | `/plans` | API real | catalogo, assinatura, invoices e cancelamento conectados | gap de recorrencia apenas se gateway de assinatura for `pagarme` |
| Analytics | `/analytics` | API real | filtros por periodo, parceiro, cliente, evento, status e modulo conectados | nenhum gap mockado encontrado |
| Audit | `/audit` | API real | filtros, paginacao e refresh conectados | nenhum gap mockado encontrado |
| Settings | `/settings` | API real | organizacao, branding, upload de logo, equipe, preferencias e AI settings conectados | nenhum gap mockado encontrado |
| Profile | `/profile` | API real | update de perfil, senha e avatar conectados | nenhum gap mockado encontrado |
| Login | `/login` | API real em modo normal; mock em modo dev | login, registro e forgot password usam service real quando `VITE_USE_MOCK=false` | risco residual se ambiente subir com modo mock ligado |

## Matriz Das Paginas Publicas

| Area | Paginas | Estado da integracao | Observacao |
|---|---|---|---|
| Wall Player | `/wall/player/:code` | API real | telas de indisponibilidade sao estados de negocio, nao mock |
| Upload publico | `/upload/:code` | API real | estados de indisponibilidade dependem da configuracao do evento/modulo |
| Checkout publico por evento | `/checkout/evento` | API real | fluxo publico esta conectado ao billing de evento; nao depende do mock de `plans` |
| Hub publico | `/e/:slug` | API real | renderer conectado a settings reais do hub |
| Gallery publica | `/e/:slug/gallery` | API real | galeria publica real, com fallback de indisponibilidade |
| Face search publica | `/e/:slug/find-me` | API real | mensagens de indisponibilidade refletem estado do modulo |
| Play publico | `/e/:slug/play`, `/e/:slug/play/:gameSlug` | API real | jornadas publicas conectadas; realtime pode ficar indisponivel conforme ambiente |

## Validacao De Mock Data

Resultado da varredura por uso runtime de `apps/web/src/shared/mock/data.ts`:

- o restante do painel nao importa mais `mockEvents`, `mockPartners`, `mockClients`, `mockPlans`, `mockDashboardStats` etc para uso normal das paginas
- os usos runtime remanescentes estao concentrados em:
  - `apps/web/src/app/layouts/AppHeader.tsx`
  - `apps/web/src/app/providers/AuthProvider.tsx`
  - `apps/web/src/modules/auth/services/auth.service.ts`

Leitura:

- os modulos de negocio principais ja sairam do estado de painel apenas mockado
- o mock remanescente esta hoje concentrado em `header/notifications` e no `auth mock mode`

## Validacao De Filtros E CRUD

### Confirmados como conectados

- `media`
  - frontend envia filtros em `apps/web/src/modules/media/services/media.service.ts`
  - backend recebe em `apps/api/app/Modules/MediaProcessing/Http/Requests/ListCatalogMediaRequest.php`
  - query aplica os filtros em `apps/api/app/Modules/MediaProcessing/Queries/ListCatalogMediaQuery.php`

- `gallery`
  - frontend envia filtros em `apps/web/src/modules/gallery/services/gallery.service.ts`
  - backend recebe em `apps/api/app/Modules/Gallery/Http/Requests/ListGalleryMediaRequest.php`
  - controller monta `ListCatalogMediaQuery` em `apps/api/app/Modules/Gallery/Http/Controllers/GalleryMediaController.php`

- `events`
  - listagem, mutate de publish/archive, details e settings operacionais estao ligados a services reais

- `partners`
  - listagem e drawer com staff, grants, clients, events e activity estao conectados a APIs reais

- `clients`
  - listagem e CRUD conectados a APIs reais, com filtros de organizacao, parceiro e tipo

- `analytics`
  - filtros e selects assincronos ligados a `/analytics/platform`, `/analytics/events/{id}`, `/organizations`, `/clients` e `/events`

- `audit`
  - filtros e paginacao ligados a listagem real de atividades

### Nao apareceu como gap nesta rodada

- botoes CRUD sem mutation correspondente
- listagens principais ainda alimentadas por arrays mockados
- filtros claramente desconectados do backend nas paginas centrais

## Prioridade Recomendada

### P0

- remover ou implementar o dropdown de notificacoes do header
- fixar `VITE_USE_MOCK=false` fora de dev e endurecer o gating do auth mock

### P1

- implementar `createSubscriptionCheckout()` no `PagarmeBillingGateway` antes de vender `/plans` com provider recorrente real

### P2

- alinhar `route-preload.ts` com as rotas reais do router

## Conclusao

O estado atual do frontend/admin ja nao se comporta como um painel majoritariamente mockado.

O que a inspecao mostra e:

- os modulos centrais de operacao e CRUD estao, em geral, conectados a backend real
- os mocks residuais relevantes ficaram concentrados em superficies transversais
- o principal mock ainda visivel para o usuario admin esta no header de notificacoes
- o principal gap funcional nao-mockado, mas ainda incompleto, esta em billing recorrente com `pagarme`

Se fosse para atacar primeiro o que mais reduz discrepancia entre UX e operacao real, a ordem seria:

1. notifications
2. hardening do auth mock mode
3. subscription checkout recorrente real em `plans`
