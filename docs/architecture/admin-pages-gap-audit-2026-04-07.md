# Auditoria Das Paginas Do Projeto

Data da inspecao inicial: `2026-04-07`
Atualizacao focada em billing e checkout: `2026-04-08`

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
4. o checkout publico avulso existe e esta integrado para Pix/cartao, mas ainda nao tem deep link por pacote nem historico autenticado de pedidos pendentes
5. existe um pequeno gap de consistencia no preload de rota para `/dashboard`, embora a rota administrativa real seja `/`

Correcao aplicada apos a inspecao:

- o bug de contrato em `/billing/invoices` foi corrigido localmente em `2026-04-08`
- o catalogo publico do checkout avulso agora restringe a listagem padrao para `direct_customer` em `2026-04-08`
- a jornada publica agora hidrata a sessao autenticada apos criar a conta no checkout avulso em `2026-04-08`

## Atualizacao Billing E Checkout 2026-04-08

### `/plans`: clicar em um plano hoje leva para checkout ou ativa na hora?

Resposta curta:

- hoje o botao `Ativar plano` nao faz redirect automatico
- ele chama `POST /billing/checkout`
- o que acontece depois depende do gateway configurado para assinatura

Evidencia:

- `apps/web/src/modules/plans/PlansPage.tsx`
- `apps/api/app/Modules/Billing/Http/Controllers/SubscriptionController.php`
- `apps/api/config/billing.php`
- `apps/api/app/Modules/Billing/Services/ManualBillingGateway.php`
- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeBillingGateway.php`

Leitura do fluxo:

- no frontend, o clique so dispara a mutation
- se a resposta vier com `checkout.checkout_url`, a pagina mostra o card `Pagamento pendente` com botao `Abrir checkout`
- a pagina nao navega sozinha para esse link
- se a resposta vier sem `checkout_url`, o frontend considera o fluxo resolvido localmente e troca para a aba de assinatura

Conclusao operacional:

- no estado atual default do projeto, a assinatura tende a ativar de imediato porque `billing.gateways.subscription` cai em `manual` por padrao
- nesse modo, `ManualBillingGateway::createSubscriptionCheckout()` devolve `status=paid`
- se voce trocar a assinatura para `pagarme`, o fluxo nao abre checkout: ele quebra porque `createSubscriptionCheckout()` no `PagarmeBillingGateway` ainda nao foi implementado

### Existe diferenca no front entre admin e parceiro para contratar/pagar assinatura?

Resposta curta:

- nao existe uma pagina separada por perfil
- a diferenca e por permissao, nao por layout dedicado

Evidencia:

- `apps/web/src/App.tsx`
- `apps/web/src/modules/plans/PlansPage.tsx`

O que o codigo faz:

- a rota `/plans` so abre para quem tem alguma destas permissoes:
  - `billing.view`
  - `billing.manage`
  - `billing.purchase`
  - `billing.manage_subscription`
  - `plans.view`
- dentro da pagina, contratar depende de `billing.purchase` ou `billing.manage`
- cancelar assinatura depende de `billing.manage_subscription` ou `billing.manage`

Leitura:

- admin e parceiro usam a mesma UI se ambos tiverem permissao
- nao existe um fluxo visual separado "admin compra" vs "parceiro compra"
- quem esta fora desse mundo B2B nao deveria usar `/plans`; deveria usar o checkout publico avulso

### Por que a aba `Cobrancas` estava mostrando erro?

Resposta curta:

- isso era um bug real de contrato de resposta, nao apenas ausencia de dados

Evidencia:

- `apps/web/src/modules/plans/PlansPage.tsx`
- `apps/web/src/modules/plans/api.ts`
- `apps/api/app/Modules/Billing/Http/Controllers/SubscriptionController.php`
- `apps/api/app/Shared/Http/BaseController.php`

O que foi encontrado:

- o frontend espera que `listInvoices()` retorne algo no formato:
  - `data: ApiBillingInvoice[]`
  - `meta: ApiPaginationMeta`
- o backend hoje responde `success($invoices)`
- isso encapsula o paginator inteiro dentro de `data`
- na pratica, o payload real tende a ficar assim:
  - `data: { current_page, data: [...], per_page, total, last_page, ... }`
  - `meta: { request_id }`
- em `PlansPage.tsx`, a pagina faz:
  - `Array.isArray(invoicesQuery.data?.data)`
  - se isso for falso, marca `hasMalformedInvoices=true`

Conclusao:

- a tela de `Cobrancas` entra em erro mesmo quando a API retorna com sucesso
- o problema principal esta no desacoplamento entre:
  - o shape esperado no frontend
  - o shape realmente entregue pelo controller

Status:

- corrigido localmente em `2026-04-08`
- o backend agora devolve `data` como array de invoices e `meta` paginado no formato esperado pela tela

### Existe pagina de checkout com Pix e cartao do Pagar.me? Qual URL?

Resposta curta:

- sim, para compra avulsa por evento
- a URL publica hoje e ` /checkout/evento `

Evidencia:

- `apps/web/src/App.tsx`
- `apps/web/src/modules/billing/PublicEventCheckoutPage.tsx`
- `apps/web/src/lib/pagarme-tokenization.ts`
- `apps/api/app/Modules/Billing/routes/api.php`
- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeBillingGateway.php`
- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeOrderPayloadFactory.php`

Leitura do fluxo:

- a pagina publica carrega pacotes por `GET /api/v1/public/event-packages`
- o usuario escolhe o pacote na propria pagina
- para Pix:
  - o backend monta checkout avulso e recebe `qr_code` / `qr_code_url`
- para cartao:
  - o navegador tokeniza direto na Pagar.me via `VITE_PAGARME_PUBLIC_KEY`
  - o backend recebe apenas `card_token`
  - o pedido avulso segue via `createEventPackageCheckout()` no `PagarmeBillingGateway`

Importante:

- isso vale para checkout avulso de evento
- nao vale para assinatura recorrente de `/plans`, que ainda nao tem checkout Pagar.me pronto

### Como acesso o checkout ja escolhendo o pacote?

Resposta curta:

- hoje nao existe deep link por pacote no frontend

Evidencia:

- `apps/web/src/modules/billing/PublicEventCheckoutPage.tsx`

O que a pagina faz hoje:

- carrega todos os pacotes publicos ativos
- se nenhum pacote estiver selecionado, auto-seleciona o primeiro
- o usuario escolhe outro pacote clicando no card do pacote dentro da tela
- os query params usados hoje sao internos:
  - `?checkout=<uuid>` para retomar um pedido criado
  - `?resume=auth` para retomar a jornada depois do login

Conclusao:

- o link publico de entrada e um so: `/checkout/evento`
- hoje nao existe `package_id` ou slug de pacote na URL para pre-selecao

### O cliente final, como uma noiva, precisa cadastro antes para comprar avulso?

Resposta curta:

- nao, se o contato ainda nao existir
- sim, se o WhatsApp ou e-mail ja estiverem cadastrados

Evidencia:

- `apps/api/app/Modules/Billing/Actions/CreatePublicEventCheckoutAction.php`
- `apps/api/app/Modules/Billing/Services/PublicJourneyIdentityService.php`
- `apps/web/src/modules/billing/PublicEventCheckoutPage.tsx`

O que acontece hoje:

- se o WhatsApp/e-mail nao existem:
  - o checkout cria usuario
  - cria organizacao do tipo `direct_customer`
  - cria o evento
  - inicia o pedido
  - e ainda retorna token para continuar autenticado
- se o WhatsApp ou e-mail ja existem:
  - o backend bloqueia a criacao duplicada
  - o frontend mostra `Fazer login para continuar`
  - depois do login a jornada tenta retomar o checkout

Observacao importante:

- internamente, esse cliente direto acaba entrando com organizacao `direct_customer`, mas o usuario ainda recebe role `partner-owner`
- ou seja, a semantica de negocio ja distingue cliente direto, mas a role tecnica ainda reaproveita a trilha de parceiro

### Como esta a separacao entre planos avulsos e assinaturas no front?

Resposta curta:

- existe separacao de superficie
- ainda nao existe separacao completa de produto/UX

O que existe hoje:

- assinaturas recorrentes B2B:
  - `/plans`
- compra avulsa/publica por evento:
  - `/checkout/evento`

O que nao existe hoje:

- nao encontrei uma pagina autenticada dedicada para o catalogo avulso usando `GET /api/v1/event-packages`
- o backend ja tem esse endpoint, mas o frontend nao expoe uma tela administrativa especifica para esse catalogo

Atualizacao local em `2026-04-08`:

- o frontend publico passou a chamar `GET /public/event-packages?target_audience=direct_customer`
- o controller publico passou a usar `direct_customer` como audiencia padrao quando nenhuma audiencia e informada
- com isso, a listagem publica ficou alinhada ao submit que ja barrava pacote fora de `direct_customer|both`

Conclusao:

- a separacao conceitual existe
- a separacao operacional ainda esta incompleta, principalmente em:
  - catalogo avulso autenticado inexistente no frontend
  - inexistencia de historico autenticado para pedidos avulsos pendentes
  - ausencia de deep link por pacote

### O comprador final consegue acompanhar pagamento e fatura depois da compra?

Resposta curta:

- parcialmente
- pagamento pendente do checkout avulso fica bem atendido na propria pagina publica
- historico autenticado ainda nao cobre o ciclo completo de pedidos avulsos pendentes

O que esta funcionando hoje:

- a jornada publica em `/checkout/evento` mostra status local do pedido por `checkout.uuid`
- Pix exibe `qr_code`, `qr_code_url`, expiracao, polling local e evidencias de envio no WhatsApp quando existirem
- cartao mostra status do charge e mensagens do adquirente quando retornadas pelo gateway
- quando o checkout cria uma conta nova, a tela agora grava o token e hidrata a sessao autenticada antes de seguir
- a tela publica passou a expor CTA para `/plans`, onde o comprador autenticado consegue ver o historico real de `invoices`

O que continua faltando para ficar 100%:

- `/plans` mostra `invoices`, nao uma lista de `billing_orders`
- entao um pedido avulso Pix ainda pendente nao aparece como historico autenticado no painel; ele continua visivel principalmente na propria URL publica do checkout
- nao encontrei rota SPA dedicada do tipo `minhas-compras` ou `meus-pedidos` para comprador final
- os endpoints administrativos de operacao de pedido (`/billing/orders/{uuid}/refresh|retry|cancel`) exigem `billing.manage` e nao estao expostos em uma UI propria para o comprador

Leitura operacional:

- para compra avulsa paga, o comprador ja consegue consultar a invoice em `/plans`
- para compra avulsa ainda pendente, a jornada principal de acompanhamento continua sendo `/checkout/evento?checkout=<uuid>`
- isso fecha o essencial do pagamento exposto, mas ainda nao fecha uma experiencia completa de historico autenticado

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

### 4. O checkout publico avulso esta integrado, mas a jornada autenticada do comprador ainda nao esta completa

Evidencia:

- `apps/web/src/modules/billing/PublicEventCheckoutPage.tsx`
- `apps/web/src/modules/billing/services/public-event-packages.service.ts`
- `apps/api/app/Modules/Billing/Http/Controllers/EventPackageController.php`
- `apps/api/app/Modules/Billing/Actions/CreatePublicEventCheckoutAction.php`
- `apps/api/app/Modules/Billing/Enums/EventPackageAudience.php`

O que foi encontrado:

- a rota publica existe e suporta Pix e cartao
- nao existe deep link por pacote
- o frontend auto-seleciona o primeiro pacote retornado
- o filtro de audiencia publica foi corrigido localmente em `2026-04-08`
- a sessao autenticada apos a criacao da conta tambem foi corrigida localmente em `2026-04-08`
- o painel autenticado ainda nao mostra uma lista de pedidos avulsos pendentes; ele mostra apenas invoices em `/plans`

Impacto:

- o fluxo continua menos compartilhavel/comercial do que poderia ser, porque voce so consegue mandar a pagina geral de checkout, nao o pacote ja pre-selecionado
- para Pix pendente, o comprador ainda depende da URL publica do checkout para acompanhar o pedido; falta historico autenticado no painel

Recomendacao:

- adicionar deep link por pacote quando essa jornada virar canal comercial de verdade
- criar uma area autenticada de pedidos/cobrancas avulsas, separada do historico de invoices

## Achados Secundarios

### 5. Preload de `/dashboard` nao reflete a rota admin real

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
| Plans | `/plans` | API real | catalogo, assinatura, invoices e cancelamento conectados | assinatura ativa na hora quando o gateway e `manual`; recorrencia `pagarme` nao pronta; pedidos avulsos pendentes nao aparecem aqui |
| Event Packages Auth | nenhuma rota SPA dedicada encontrada | backend existe | `GET /event-packages` existe no backend | nao ha tela admin/autenticada dedicada para catalogo avulso |
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
| Checkout publico por evento | `/checkout/evento` | API real | Pix e cartao passam pela trilha publica; audiencia padrao corrigida para `direct_customer`; sem deep link por pacote; historico autenticado de pedidos pendentes ainda ausente |
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
- criar historico autenticado para pedidos avulsos pendentes ou explicitar no produto que o acompanhamento fica na URL publica do checkout

### P1

- implementar `createSubscriptionCheckout()` no `PagarmeBillingGateway` antes de vender `/plans` com provider recorrente real
- decidir se `/plans` deve continuar com CTA explicita `Abrir checkout` ou se deve redirecionar automaticamente quando houver `checkout_url`
- criar deep link por pacote no checkout publico

### P2

- alinhar `route-preload.ts` com as rotas reais do router

## Conclusao

O estado atual do frontend/admin ja nao se comporta como um painel majoritariamente mockado.

O que a inspecao mostra e:

- os modulos centrais de operacao e CRUD estao, em geral, conectados a backend real
- os mocks residuais relevantes ficaram concentrados em superficies transversais
- o principal mock ainda visivel para o usuario admin esta no header de notificacoes
- em billing, o gap estrutural mais concreto agora e a recorrencia de assinatura por `pagarme`, que segue nao implementada
- no checkout avulso, a trilha Pix/cartao existe e o filtro/a sessao foram corrigidos localmente, mas ainda falta historico autenticado de pedidos pendentes e deep link por pacote

Se fosse para atacar primeiro o que mais reduz discrepancia entre UX e operacao real, a ordem seria:

1. notifications
2. hardening do auth mock mode
3. subscription checkout recorrente real em `plans`
