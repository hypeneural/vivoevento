# Checkout Publico De Evento V2 - Plano De Implementacao

Data: `2026-04-08`

Documento base:

- `docs/architecture/public-event-checkout-ux-analysis-2026-04-08.md`

Plano complementar de hardening da jornada:

- `docs/architecture/public-event-checkout-friction-hardening-plan-2026-04-09.md`

## Objetivo

Implementar a V2 de `/checkout/evento` como uma jornada comercial curta, clara e confiavel para usuario final.

A V2 deve:

- parecer compra, nao operacao
- usar linguagem humana
- reduzir campos visiveis por vez
- manter Pix como caminho principal
- tratar login como assistencia, nao como etapa universal
- preservar a seguranca e os contratos reais de backend

## Validacao Executada Em `2026-04-09`

### Bateria rodada

Backend:

- `php artisan test --filter=PublicEventCheckoutTest`
- `php artisan test --filter=EventPackageCatalogTest`
- `php artisan test --filter=BillingTest`

Frontend:

- `npm run test -- src/modules/billing/PublicEventCheckoutEntryPage.test.tsx src/modules/billing/public-checkout src/modules/billing/services/public-event-packages.service.test.ts src/modules/plans/PlansPage.test.tsx`
- `npm run type-check`

Resultado:

- `PublicEventCheckoutTest`: `16` testes verdes
- `EventPackageCatalogTest`: `4` testes verdes
- `BillingTest`: `24` testes verdes
- `Vitest`: `11` testes verdes
- `type-check`: verde

Leitura pratica:

- o checkout publico atual esta funcional ponta a ponta para Pix e cartao no contrato atual
- a retomada apos login para Pix ja esta coberta e funcionando
- o rascunho seguro de cartao realmente nao restaura PAN/CVV
- o catalogo publico de pacote para `direct_customer` esta validado
- a tela de `/plans` ja consome o historico financeiro corrigido
- a V2 nao parte de um fluxo quebrado; ela parte de um fluxo funcional, mas com UX e composicao ruins

### Bateria complementar da execucao da Fase 2

Backend:

- `php artisan test --filter=PublicCheckoutIdentityPrecheckTest`
- `php artisan test --filter=PublicEventCheckoutTest`
- `php artisan test --filter=EventPackageCatalogTest`

Frontend:

- `npm run test -- src/modules/billing/PublicEventCheckoutEntryPage.test.tsx src/modules/billing/public-checkout/hooks/usePublicCheckoutWizard.test.tsx src/modules/billing/public-checkout/PublicCheckoutPageV2.test.tsx src/modules/billing/public-checkout/components/IdentityAssistInline.test.tsx src/modules/billing/public-checkout/hooks/useCheckoutIdentityPrecheck.test.tsx src/modules/billing/public-checkout/services/public-checkout-identity.service.test.ts`
- `npm run type-check`

Resultado:

- backend verde na trilha publica de identidade, catalogo e checkout
- frontend verde na entry route, wizard, V2 base e regressao do checkout legado

### Validacao complementar da Fase 7 e Fase 8

Frontend:

- `npm run test -- src/modules/billing/public-checkout`
- `npm run type-check`
- `npx playwright test`

Backend:

- `php artisan test --filter=PublicCheckoutIdentityPrecheckTest`
- `php artisan test --filter=PublicEventCheckoutTest`
- `php artisan test --filter=PublicEventCheckoutPayloadContractTest`
- `php artisan test --filter=EventPackageCatalogTest`
- `php artisan test --filter=BillingTest`

Resultado:

- bateria V2 unit, component, flow, backend contract e E2E verde
- mobile footer, drawer secundario, resume draft endurecido e polling seguem validados localmente
- os riscos de botao voltar, refresh com `checkout.uuid` e retomada apos login passaram no navegador real

### Validacao complementar da rodada final

Backend:

- `php artisan test --filter=EventPackageCheckoutMarketingServiceTest`
- `php artisan test --filter=EventPackageCatalogTest`
- `php artisan billing:pagarme:homologate --scenario=pix-cancel --poll-attempts=1 --poll-sleep-ms=500`

Frontend:

- `npm run test -- src/modules/billing/public-checkout src/modules/billing/PublicEventCheckoutEntryPage.test.tsx src/modules/billing/services/public-event-packages.service.test.ts src/modules/plans/PlansPage.test.tsx`
- `npm run type-check`
- `npx playwright test`

Validacao operacional:

- a conta local continua com chaves de homologacao da Pagar.me configuradas
- o comando de homologacao real executou com sucesso em `2026-04-09`
- a conta da Pagar.me continua com hooks apontando para `https://webhooks-local.eventovivo.com.br/api/v1/webhooks/billing/pagarme`
- o endpoint externo respondeu `401` sem credencial e mudou de comportamento com o `Basic Auth` configurado localmente, confirmando a trilha de protecao

### Validacao complementar da observacao curta e corte do legado

Backend:

- `php artisan test --filter=PublicCheckoutIdentityPrecheckTest`
- `php artisan test --filter=PublicEventCheckoutPayloadContractTest`
- `php artisan test --filter=PublicEventCheckoutTest`
- `php artisan test --filter=EventPackageCatalogTest`
- `php artisan test --filter=BillingTest`
- `php artisan billing:pagarme:homologate --scenario=pix-cancel --poll-attempts=1 --poll-sleep-ms=500`

Frontend:

- `npm run test -- src/modules/billing/public-checkout src/modules/billing/PublicEventCheckoutEntryPage.test.tsx src/modules/billing/services/public-event-packages.service.test.ts src/modules/plans/PlansPage.test.tsx`
- `npm run type-check`
- `npx playwright test`

Resultado:

- `61` testes Vitest verdes
- `6` cenarios Playwright verdes
- `54` testes PHP verdes na bateria rodada do checkout/billing
- homologacao real da Pagar.me verde com evidencia em `apps/api/storage/app/pagarme-homologation/20260409-185345-pix-cancel.json`
- a entry route publica deixou de aceitar rollback por `legacy=1` sem regressao detectada

### Validacao real ponta a ponta final via Cloudflare + Pagar.me

Backend:

- `php artisan migrate --path=database/migrations/2026_04_09_193000_add_recurring_fields_to_billing_gateway_events_table.php --force`
- `php artisan test --filter=BillingWebhookTest`
- `php artisan test --filter=PublicEventCheckoutPayloadContractTest`
- `php artisan test --filter=PublicEventCheckoutTest`
- `php artisan test --filter=PublicCheckoutIdentityPrecheckTest`
- `php artisan test --filter=EventPackageCatalogTest`
- `php artisan test --filter=BillingTest`

Frontend:

- `npm run test -- src/modules/billing/PublicEventCheckoutEntryPage.test.tsx src/modules/billing/public-checkout public-event-packages.service.test.ts PlansPage.test.tsx`
- `npm run type-check`
- `npx playwright test e2e/public-checkout-pix.spec.ts e2e/public-checkout-card.spec.ts e2e/public-checkout-resume.spec.ts e2e/public-checkout-mobile.spec.ts e2e/public-checkout-status-resume.spec.ts`

Validacao operacional:

- o endpoint real de webhook continua em `https://webhooks-local.eventovivo.com.br/api/v1/webhooks/billing/pagarme`
- o primeiro Pix real expôs uma migration pendente no banco local:
  - `2026_04_09_193000_add_recurring_fields_to_billing_gateway_events_table.php`
  - sem essa migration, o webhook falhava ao gravar `hook_id` em `billing_gateway_events`
- a trilha de processamento do webhook tambem expôs um guard faltando em `ProcessBillingWebhookAction`:
  - quando a Pagar.me enviava `code` nao-UUID, o resolver tentava consultar `billing_orders.uuid` com valor invalido
  - a busca agora so consulta por UUID quando `billing_order_uuid` realmente e UUID e cai para `gateway_order_id` no restante
- depois dessas duas correcoes, a trilha publica passou ponta a ponta no webhook real

Evidencias reais:

- Pix inicial que expôs o problema de schema:
  - `apps/api/storage/app/pagarme-homologation/public-checkout-pix-20260409170532.json`
- Pix validado ponta a ponta apos o ajuste:
  - `apps/api/storage/app/pagarme-homologation/public-checkout-pix-retry-20260409171011.json`
- Cartao validado ponta a ponta:
  - `apps/api/storage/app/pagarme-homologation/public-checkout-card-20260409171236.json`

Estados finais confirmados no endpoint publico:

- Pix:
  - `status = refunded`
  - `summary.state = refunded`
  - `payment.meta.gateway_status = canceled`
  - `payment.meta.charge_status = canceled`
- cartao:
  - `status = canceled`
  - `summary.state = refunded`
  - `payment.meta.gateway_status = canceled`
  - `payment.meta.charge_status = canceled`

Leitura pratica:

- o payload semantico ficou responsavel pela linguagem do comprador
- `payment.meta` agora reflete o estado bruto reconciliado do gateway
- no Pix pendente, esse estado bruto real e `pending_payment`, nao `pending`
- o checkout publico ficou validado com Pix e cartao usando a API real da Pagar.me e recebendo webhook pela URL do Cloudflare local

## Status De Execucao Em `2026-04-09`

### Concluido nesta rodada

- Fase 1 backend entregue:
  - `POST /api/v1/public/checkout-identity/check`
  - request validator
  - action dedicada
  - resource dedicada
  - rate limit com chave hash de contato + IP

- Fase 1 frontend base entregue:
  - `publicCheckoutIdentityService`
  - `useCheckoutIdentityPrecheck`
  - `IdentityAssistInline`
  - tipos de API para o pre-check

- Fase 2 base entregue e promovida para a rota publica:
  - `PublicEventCheckoutEntryPage`
  - `PublicCheckoutPageV2`
  - `PublicCheckoutShell`
  - `usePublicCheckoutWizard`
  - `CheckoutStepper`
  - `CheckoutHeroSimple`
  - `PackageSelectionStep`
  - `PackageCard`
  - `BuyerEventStep`
  - `BuyerIdentityFields`
  - `EventBasicsFields`
  - `CheckoutSidebar`
  - `OrderSummaryCard`
  - `NextStepCard`
  - `TrustSignalsCard`
  - `packageCommercialCopy`

- Fase 5 e Fase 6 frontend entregues na rota publica:
  - `PaymentMethodTabs`
  - `PixPaymentPanel`
  - `CreditCardPaymentPanel`
  - `PaymentStep`
  - `PaymentStatusCard`
  - `PixDeliveryNotice`
  - `ResumeNoticeBanner`
  - `CheckoutErrorBanner`
  - `useCheckoutResumeDraft`
  - `useCheckoutStatusPolling`
  - `checkoutStatusViewModel`

- Fase 7 entregue localmente:
  - `checkoutResponseAdapters.ts`
  - `MobileCheckoutFooter`
  - `PublicCheckoutShell` com decisao responsiva real
  - `useCheckoutResumeDraft` com preferencia por `sessionStorage`
  - `useCheckoutStatusPolling.test.tsx`
  - `PublicCheckoutMobileLayout.test.tsx`
  - `CheckoutSidebar.test.tsx`

- Fase 8 entregue localmente:
  - `@playwright/test` instalado em `apps/web`
  - `apps/web/e2e` criado
  - `public-checkout-pix.spec.ts`
  - `public-checkout-resume.spec.ts`
  - `public-checkout-card.spec.ts`
  - `public-checkout-mobile.spec.ts`
  - `public-checkout-status-resume.spec.ts`
  - deep link por pacote coberto em E2E

- Fase 9 entregue localmente:
  - `EventPackageCheckoutMarketingService`
  - `checkout_marketing` no catalogo publico e autenticado de pacotes
  - `packageCommercialCopy` agora consumindo metadata nativa do backend
  - deep link por `?package=<slug|code|id>`
  - `PublicEventCheckoutEntryPage` promovendo a V2 como fluxo padrao
  - regressao de `resume=auth + package=...` coberta
  - janela curta de observacao encerrada em `2026-04-09` sem regressao detectada na bateria automatizada e na homologacao real

- switch final de rollout entregue:
  - `/checkout/evento` agora abre a V2 por padrao
  - `/checkout/evento?v2=1` continua funcionando para links antigos
  - a rota publica continua passando pelo entry page para manter decisao centralizada
  - `legacy=1` deixou de desviar para a tela antiga
  - `PublicEventCheckoutPage.tsx` e a suite legada foram removidos do frontend

- bateria automatizada da fase verde

### Ainda pendente da Fase 1 fora da V2

- o pre-check ja esta integrado na V2
- o monolito legado foi removido do frontend, entao o pre-check segue apenas na V2 publica

### Estado real da Fase 2 agora

Ja esta implementado:

- wizard com estado proprio e sincronizacao de `step` na URL
- `Accordion + Progress` apenas como camada visual
- etapa 1 com pacote comercial e resumo lateral
- etapa 2 com `Seus dados`, extras colapsados e `IdentityAssistInline`
- CTA `Ja tenho conta` apontando para `returnTo=/checkout/evento?resume=auth`
- etapa 3 real de pagamento com Pix default e cartao sob demanda
- tokenizacao de cartao preservada no navegador com `card_token`
- retomada segura de rascunho na V2 com auto-resume de Pix apos login
- estado `status/post_submit` ja separado do accordion
- `PaymentStatusCard` com QR Code, copia e cola, expiracao, aviso de WhatsApp e CTA de refresh
- adapters/view-model no frontend para a UI nao ler `gateway_status` diretamente nos componentes

Ainda segue pendente:

- eventual historico autenticado de pedidos avulsos pendentes

### Proximo passo recomendado

- decidir se o catalogo comercial vai continuar dirigido por `feature_key` ou se merece schema dedicado no futuro

## Validacao Do Toolkit De UI

### Stack local confirmada

Arquivos inspecionados:

- `apps/web/package.json`
- `apps/web/components.json`
- `apps/web/src/components/ui/*`
- `apps/web/src/hooks/use-mobile.tsx`
- `apps/web/playwright.config.ts`

Stack confirmada:

- `shadcn/ui`
- `Radix UI`
- `Vaul`
- `React Hook Form + Zod`
- `TanStack Query`
- `framer-motion`
- `Playwright`

### Componentes locais ja disponiveis

- `Accordion`
- `Alert`
- `Badge`
- `Button`
- `Card`
- `Collapsible`
- `Dialog`
- `Drawer`
- `Form`
- `Input`
- `Popover`
- `Progress`
- `Separator`
- `Sheet`
- `Skeleton`
- `Tabs`
- `Textarea`
- `Tooltip`

### Componentes realmente usados na V2 publica

No estado atual, a jornada publica usa principalmente:

- `Form`
- `Accordion`
- `Progress`
- `Tabs`
- `Collapsible`
- `Drawer`
- `Card`
- `Button`
- `Input`

Leitura pratica:

- a V2 deixou de depender do conjunto minimo do monolito antigo
- a refatoracao estrutural da jornada ja se refletiu na composicao real dos componentes

### Validacao nas docs oficiais

Referencias oficiais verificadas:

- https://ui.shadcn.com/docs/components/accordion
- https://ui.shadcn.com/docs/components/progress
- https://ui.shadcn.com/docs/components/tabs
- https://ui.shadcn.com/docs/components/drawer
- https://ui.shadcn.com/docs/forms
- https://ui.shadcn.com/docs/components/collapsible
- https://ui.shadcn.com/docs/components/dialog
- https://react-hook-form.com/docs/useformcontext
- https://react-hook-form.com/docs/usewatch
- https://react-hook-form.com/docs/useformstate
- https://tanstack.com/query/latest/docs/framework/react/guides/query-cancellation
- https://playwright.dev/docs/writing-tests
- https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html

Leitura pratica:

- `Accordion` e bom para apresentar passos com resumo colapsado
- `Progress` e o primitive correto para indicar avancos da jornada
- `Tabs` nao devem ser o stepper principal; servem melhor para alternancia de pagamento, se necessario
- `Collapsible` e apropriado para `Adicionar mais detalhes`
- `Drawer/Dialog` fazem sentido para conteudo secundario responsivo, nao para o fluxo principal
- o projeto nao tem um `Stepper` pronto instalado
- `FormProvider + useFormContext + useWatch + useFormState` e o caminho certo para quebrar o formulario sem rerender em cascata
- `AbortSignal` em query/service precisa ser tratado como requisito real do pre-check
- `Playwright` ja esta configurado e instalado localmente
- a pasta `apps/web/e2e` ja existe com a bateria minima da jornada publica
- o pre-check deve seguir resposta neutra e rate limit para nao virar enumeracao de conta

## Achados Confirmados Na V2 Publica

### 1. O acoplamento do monolito legado saiu da rota publica

Validado no estado atual:

- `PublicEventCheckoutPage.tsx` foi removido
- a rota publica usa apenas `PublicCheckoutPageV2`
- o estado de jornada, pagamento e acompanhamento ficou dividido em hooks, mappers e componentes finos

Implicacao:

- a V2 deixou de carregar o custo estrutural do checkout antigo

### 2. A UI publica ja nao le semantica tecnica diretamente nos componentes

Validado em `checkoutResponseAdapters.ts`, `checkoutStatusViewModel.ts` e `PublicEventCheckoutPayloadBuilder.php`:

- a UI publica nao depende mais de `gateway_status` nos componentes
- o payload semantico ja cobre labels, descricao e proximo passo

Implicacao:

- o acoplamento mais perigoso do checkout antigo foi removido da interface publica

### 3. O rascunho seguro saiu do desenho antigo

Validado na V2:

- a retomada agora prefere `sessionStorage`
- o draft seguro segue sem restaurar `PAN/CVV`

Implicacao:

- o fluxo ativo de compra ficou mais seguro e previsivel

### 4. O cartao continua sem parcelamento real

Validado na V2 e no backend:

- o frontend continua operando com `installments: 1`
- o backend aceita `installments`, mas a UI publica nao promete parcelamento

Implicacao:

- a copy comercial continua alinhada ao que o backend realmente suporta

## Referencias De Checkout Profissional Com Baixa Friccao

### Padroes externos que reforcam o plano

1. Menos campos visiveis pesa mais do que menos etapas
   - Baymard aponta que o numero total de campos visiveis tem impacto direto na percepcao de esforco e que esconder campos redundantes reduz intimidacao visual.

2. Criacao de conta deve ser adiada ou tratada como opcao assistiva
   - Baymard mostra que convidar o usuario a criar conta no meio do checkout distrai da compra e aumenta atrito.

3. Progressive disclosure melhora formularios longos
   - NN/g reforca a abordagem step-by-step com exibicao incremental de campos conforme a necessidade.

4. Checkout profissional nao vive so de pagamento; ele tambem explicita confianca
   - Stripe recomenda branding consistente, politicas visiveis, contato de suporte, dominio proprio e imagens do produto para aumentar confianca e reduzir abandono.

5. Nome e marca consistentes reduzem confusao e chargeback
   - Stripe orienta manter nome comercial e identidade reconheciveis para o comprador.

### Como isso se traduz para a nossa V2

- manter `Pix` como caminho dominante
- pedir so o minimo antes do pagamento
- tratar login como ajuda contextual, nao etapa universal
- usar `Accordion + Progress` apenas como presentacao de uma jornada controlada por estado
- limpar a UI publica de semantica operacional
- deixar suporte, seguranca e proximo passo visiveis sem transformar a tela em documentacao tecnica

## Decisoes Estruturais

### 1. Fonte de verdade da jornada

O wizard nao pode usar o `Accordion` como fonte de verdade.

A jornada deve ser controlada por um hook ou reducer dedicado, por exemplo:

- `currentStep: 'package' | 'details' | 'payment' | 'status'`
- `completedSteps`
- `canAdvance`
- `summaries`
- `checkoutUuid`
- `isSubmitted`
- `isResumed`

Regra:

- o `Accordion` so reflete esse estado
- o `Accordion` nao decide logica de avancar, voltar, resumir ou concluir

### 2. URL sincronizada com a jornada

A etapa atual deve ser sincronizada com query string.

Parametros esperados:

- `?step=package|details|payment|status`
- `?checkout=<uuid>`
- `?resume=auth`

Regra:

- avancar etapa atualiza a URL
- voltar etapa atualiza a URL
- o botao voltar do navegador deve voltar etapa, nao sair da jornada abruptamente

### 3. RHF sem rerender em cascata

Para nao recriar o monolito atual com outra aparencia:

- `FormProvider` no topo da jornada
- `useFormContext()` dentro dos steps e subcomponentes
- `useWatch()` apenas em campos que realmente dirigem UI dinamica
- `useFormState()` para erros, dirty state e validade por etapa
- evitar `form.watch()` em massa no page root

Regra de negocio preservada:

- e-mail continua opcional para Pix
- cartao continua exigindo e-mail e billing address completos

### 4. Adapter de payload cedo

A UI nova nao deve depender diretamente de `gateway_status`, `uuid`, `provider` e outros campos tecnicos desde o inicio.

Ja nas primeiras fases do frontend, criar adapters/view models:

- `mapCheckoutResponseToStatusCard()`
- `mapCheckoutResponseToPostSubmitState()`
- `mapPackageToCommercialCard()`

Isso reduz retrabalho antes do backend expor payload semantico mais amigavel.

### 5. Pos-Pix vira estado proprio

Depois de gerar o Pix, a pagina deixa de estar no `wizard de compra` e entra em `acompanhamento do pagamento`.

Estado esperado:

- `status` ou `post_submit`

Esse estado deve focar em:

- QR Code
- copia e cola
- expiracao
- evidencias de envio por WhatsApp
- CTA `Atualizar pagamento`

## Arquitetura Alvo

### Frontend

```txt
apps/web/src/modules/billing/public-checkout/
  PublicCheckoutPageV2.tsx
  components/
    PublicCheckoutShell.tsx
    CheckoutHeroSimple.tsx
    CheckoutStepper.tsx
    PackageSelectionStep.tsx
    PackageCard.tsx
    BuyerEventStep.tsx
    BuyerIdentityFields.tsx
    EventBasicsFields.tsx
    IdentityAssistInline.tsx
    PaymentStep.tsx
    PaymentMethodTabs.tsx
    PixPaymentPanel.tsx
    CreditCardPaymentPanel.tsx
    CheckoutSidebar.tsx
    OrderSummaryCard.tsx
    NextStepCard.tsx
    TrustSignalsCard.tsx
    PaymentStatusCard.tsx
    StepSummaryRow.tsx
    ResumeNoticeBanner.tsx
    CheckoutErrorBanner.tsx
    MobileCheckoutFooter.tsx
    PixDeliveryNotice.tsx
  hooks/
    usePublicCheckoutWizard.ts
    useCheckoutIdentityPrecheck.ts
    useCheckoutResumeDraft.ts
    useCheckoutStatusPolling.ts
  mappers/
    packageCommercialCopy.ts
    checkoutStatusViewModel.ts
    checkoutResponseAdapters.ts
  services/
    public-checkout-identity.service.ts
```

### Backend

```txt
apps/api/app/Modules/Billing/
  Actions/
    CheckPublicCheckoutIdentityAction.php
  Http/
    Controllers/
      PublicCheckoutIdentityController.php
    Requests/
      CheckPublicCheckoutIdentityRequest.php
    Resources/
      PublicCheckoutIdentityResource.php
```

Rota nova:

- `POST /api/v1/public/checkout-identity/check`

## Smells Estruturais Ja Registrados

### 1. Semantica de role do comprador direto

Hoje:

- organizacao = `direct_customer`
- role/membership = `partner-owner`

Funciona, mas esta semanticamente torto.

Isso entra no backlog estrutural, nao bloqueia a V2.

### 2. Draft seguro ainda usa `localStorage`

Hoje ele nao guarda PAN/CVV, o que esta correto.

Melhorias planejadas:

- `version`
- `expires_at`
- limpeza automatica
- preferir `sessionStorage` quando o fluxo permitir
- usar `localStorage` so quando realmente necessario

## Fases De Implementacao

## Fase 0 - Baseline E Contratos

Objetivo:

- congelar os contratos existentes
- garantir baseline verde antes do refactor

### Subtarefas

1. mapear contratos que nao podem regredir:
   - `GET /public/event-packages`
   - `POST /public/event-checkouts`
   - `GET /public/event-checkouts/{uuid}`
   - retomada via `resume=auth`

2. registrar testes atuais que nao podem ser perdidos

3. validar infraestrutura de navegador real ja existente
   - `apps/web/playwright.config.ts`
   - criar pasta `apps/web/e2e`

4. instalar dependencia faltante para a fase E2E
   - `@playwright/test` em `apps/web`

5. criar guardrail inicial de copy publica
   - lista de termos tecnicos proibidos na UI publica:
     - `billing`
     - `gateway`
     - `UUID`
     - `webhook`
     - `polling`
     - `tokenizacao`

### TDD obrigatorio

Backend:

- `php artisan test --filter=PublicEventCheckoutTest`
- `php artisan test --filter=EventPackageCatalogTest`
- `php artisan test --filter=BillingTest`

Frontend:

- `npm run test -- src/modules/billing/PublicEventCheckoutEntryPage.test.tsx public-event-packages.service.test.ts PlansPage.test.tsx`
- `npm run type-check`

## Fase 1 - Pre-Check Silencioso De Identidade

Objetivo:

- descobrir cedo se o contato ja tem cadastro
- manter a etapa 2 como `Seus dados`
- sugerir login apenas quando realmente ajuda

### Subtarefas de backend

1. criar `CheckPublicCheckoutIdentityRequest`
   - validar `whatsapp`
   - validar `email` opcional

2. criar `CheckPublicCheckoutIdentityAction`
   - reutilizar `PublicJourneyIdentityService`
   - retornar:
     - `new_account`
     - `login_suggested`
     - `authenticated_match`
     - `authenticated_mismatch`

3. criar `PublicCheckoutIdentityController`

4. registrar rota

5. garantir resposta neutra
   - sem enumeracao agressiva
   - sem vazar existencia de conta em linguagem crua
   - minimizar discrepancia de tempo entre cenarios

6. aplicar rate limit
   - chave segura com hash de contato normalizado + IP/range

7. enriquecer contrato de resposta
   - `identity_status`
   - `title`
   - `description`
   - `action_label`
   - `login_url`
   - `cooldown_seconds` opcional

### Subtarefas de frontend

1. criar `public-checkout-identity.service.ts`
   - suporte a `AbortSignal`

2. criar `useCheckoutIdentityPrecheck.ts`
   - debounce de 600 a 800 ms
   - cancelamento real
   - ultimo estado util por alguns segundos para evitar flicker
   - falha nao bloqueia a etapa

3. criar `IdentityAssistInline.tsx`
   - estado `checking`
   - estado `new_account`
   - estado `login_suggested`
   - estado `authenticated_match`
   - estado `authenticated_mismatch`

### TDD obrigatorio

Backend:

- `apps/api/tests/Feature/Billing/PublicCheckoutIdentityPrecheckTest.php`

Cenarios:

- contato novo retorna `new_account`
- WhatsApp existente retorna `login_suggested`
- email existente retorna `login_suggested`
- usuario autenticado compativel retorna `authenticated_match`
- usuario autenticado com outro contato retorna `authenticated_mismatch`
- resposta neutra nao vaza conta
- rate limit funciona
- contrato devolve `title`, `description`, `action_label`

Frontend:

- `apps/web/src/modules/billing/public-checkout/services/public-checkout-identity.service.test.ts`
- `apps/web/src/modules/billing/public-checkout/hooks/useCheckoutIdentityPrecheck.test.tsx`
- `apps/web/src/modules/billing/public-checkout/components/IdentityAssistInline.test.tsx`

Cenarios:

- nao dispara com WhatsApp invalido
- dispara com debounce
- cancela request anterior
- repassa `AbortSignal`
- mostra `checking` sem travar digitacao
- falha nao bloqueia continuar

## Fase 2 - Infra Da Jornada V2

Objetivo:

- criar a espinha dorsal da V2 sem acoplar logica ao `Accordion`

### Subtarefas

1. criar `PublicCheckoutPageV2.tsx`

2. criar `PublicCheckoutShell.tsx`
   - tema claro
   - layout macro

3. criar `usePublicCheckoutWizard.ts`
   - reducer ou state machine simples
   - `currentStep`
   - `completedSteps`
   - `summaries`
   - `canAdvance`
   - `isResumed`
   - `checkoutUuid`

4. sincronizar etapa com URL
   - `step`
   - `checkout`
   - `resume`

5. criar `CheckoutStepper.tsx`
   - `Accordion` como camada visual
   - `Progress` como indicador
   - `StepSummaryRow` para etapas concluidas

6. criar adapters de payload
   - `checkoutResponseAdapters.ts`
   - `checkoutStatusViewModel.ts`

7. manter a pagina antiga como referencia temporaria, se necessario
   - essa etapa foi encerrada em `2026-04-09`, quando a entry route deixou de aceitar rollback por query string

### TDD obrigatorio

Frontend:

- `apps/web/src/modules/billing/public-checkout/hooks/usePublicCheckoutWizard.test.tsx`
- `apps/web/src/modules/billing/public-checkout/components/CheckoutStepper.test.tsx`
- `apps/web/src/modules/billing/public-checkout/PublicCheckoutPageV2.test.tsx`

Cenarios:

- `Accordion` apenas reflete o estado do wizard
- etapa muda junto com a URL
- `step` invalido cai para etapa segura
- botao voltar retorna para etapa anterior
- pagamento nao aparece antes da etapa 2
- lateral recebe resumos coerentes

### Status atual da fase

Concluido:

- `PublicCheckoutPageV2.tsx`
- `PublicCheckoutShell.tsx`
- `usePublicCheckoutWizard.ts`
- sincronizacao de `step` com URL
- `CheckoutStepper.tsx`
- fallback controlado via `PublicEventCheckoutEntryPage`
- `PublicCheckoutEntryPage.test.tsx`
- `usePublicCheckoutWizard.test.tsx`
- `PublicCheckoutPageV2.test.tsx`

Ainda falta nesta fase:

- `CheckoutStepper.test.tsx`
- adapters `checkoutResponseAdapters.ts`
- `checkoutStatusViewModel.ts`
- sincronizacao completa com `checkout=<uuid>` e `status/post_submit`

## Fase 3 - Etapa 1: Pacote Comercial

Objetivo:

- transformar pacote tecnico em escolha comercial

### Subtarefas

1. criar `packageCommercialCopy.ts`
   - subtitulo curto
   - `ideal para`
   - 3 a 5 beneficios
   - selo recomendado opcional

2. criar `PackageCard`

3. criar `PackageSelectionStep`

4. remover linguagem interna
   - sair de `wall on/off`
   - entrar em beneficios

5. integrar `OrderSummaryCard`

6. criar guardrail de copy comercial
   - hero e cards nao podem renderizar termos tecnicos proibidos

### TDD obrigatorio

Frontend:

- `packageCommercialCopy.test.ts`
- `PackageCard.test.tsx`
- `PackageSelectionStep.test.tsx`
- `PublicCheckoutCopyGuard.test.tsx`

Backend de regressao:

- `php artisan test --filter=EventPackageCatalogTest`

## Fase 4 - Etapa 2: Seus Dados

Objetivo:

- deixar o formulario curto
- manter login como assistencia

### Subtarefas

1. criar `BuyerIdentityFields`
   - nome
   - WhatsApp
   - email

2. criar `EventBasicsFields`
   - nome do evento
   - tipo do evento

3. criar `Collapsible` de `Adicionar mais detalhes`
   - data
   - cidade
   - nome do casal, empresa ou responsavel
   - descricao do evento

4. integrar `IdentityAssistInline`

5. manter CTA discreto `Ja tenho conta`
   - na rodada de friction hardening, o clique manual passou a salvar draft seguro antes da saida
   - comprador autenticado agora pula `/login` e retoma direto em `payment`

6. preservar regra:
   - e-mail nao bloqueia Pix

7. mover foco para o topo da etapa ao avancar
   - importante para teclado, leitor de tela e sensacao de progresso

### TDD obrigatorio

Frontend:

- `BuyerIdentityFields.test.tsx`
- `EventBasicsFields.test.tsx`
- `BuyerEventStep.test.tsx`

Cenarios:

- mostra so campos essenciais por padrao
- extras ficam colapsados
- e-mail nao bloqueia Pix
- CTA `Ja tenho conta` aponta para `returnTo=/checkout/evento?resume=auth`
- pre-check sugere login inline quando aplicavel
- foco vai para o titulo da etapa ao abrir o passo

## Fase 5 - Etapa 3: Pagamento Seguro

Objetivo:

- manter Pix como caminho mais rapido
- simplificar cartao sem perder seguranca

### Subtarefas

1. criar `PaymentMethodTabs`
   - Pix default
   - cartao como opcao secundaria

2. criar `PixPaymentPanel`
   - copy curta
   - CTA `Gerar meu Pix`

3. criar `CreditCardPaymentPanel`
   - revelado apenas quando escolhido
   - sem linguagem de payload
   - sem prometer parcelamento

4. preservar arquitetura atual
   - tokenizacao no navegador
   - `card_token`
   - billing address quando cartao

5. mover checklist tecnico para lugar discreto

### TDD obrigatorio

Frontend:

- `PaymentMethodTabs.test.tsx`
- `PixPaymentPanel.test.tsx`
- `CreditCardPaymentPanel.test.tsx`

Cenarios:

- Pix vem selecionado por padrao
- cartao so aparece quando escolhido
- UI nao sugere parcelamento
- submit Pix gera payload minimo correto
- submit cartao continua exigindo os campos corretos
- retomada segura nao restaura PAN/CVV

Backend de regressao:

- `php artisan test --filter=PublicEventCheckoutTest`
- `php artisan test --filter=BillingTest`

### Status atual da fase

Concluido localmente:

- `PaymentMethodTabs`, `PixPaymentPanel`, `CreditCardPaymentPanel` e `PaymentStep`
- `Pix` como default com CTA `Gerar meu Pix`
- cartao revelado apenas quando escolhido
- preservacao da tokenizacao atual com `card_token`
- retomada segura continua sem restaurar `PAN/CVV`
- cobertura automatizada de `PaymentMethodTabs`, `PaymentStep` e regressao da `PublicCheckoutPageV2`

Ainda pendente nesta fase:

- testes dedicados de `PixPaymentPanel` e `CreditCardPaymentPanel`

## Fase 6 - Pos-Submit E Payload Semantico

Objetivo:

- tratar o pos-Pix como estado proprio
- parar de expor semantica tecnica na UI publica

### Subtarefas de backend

1. enriquecer payload publico com campos semanticos
   - `order_status_label`
   - `payment_status_label`
   - `payment_status_description`
   - `next_action`
   - `expires_in_seconds`
   - `is_waiting_payment`
   - `can_retry`

2. manter campos tecnicos apenas para operacao interna, quando necessario

### Subtarefas de frontend

1. criar estado `status` ou `post_submit` na jornada

2. criar `PaymentStatusCard`
   - foco em comprador
   - sem `UUID`
   - sem `gateway status`
   - foco inicial no QR Code ou heading do estado

3. criar `PixDeliveryNotice`

4. criar `ResumeNoticeBanner`

5. criar `CheckoutErrorBanner`

6. parar de depender diretamente de `gateway_status`

### TDD obrigatorio

Backend:

- `apps/api/tests/Feature/Billing/PublicEventCheckoutPayloadContractTest.php`

Frontend:

- `PaymentStatusCard.test.tsx`
- `CheckoutStatusViewModel.test.ts`

Cenarios:

- Pix pendente vira `Aguardando pagamento`
- pagamento pago vira `Pagamento confirmado`
- expiracao restante aparece de forma amigavel
- UI publica nunca exibe `UUID`
- UI publica nunca exibe `gateway status`
- estado pos-submit deixa de renderizar o wizard como etapa ativa principal

### Status atual da fase

Concluido localmente no frontend:

- estado `status` na jornada da V2
- `PaymentStatusCard`
- `PixDeliveryNotice`
- `ResumeNoticeBanner`
- `CheckoutErrorBanner`
- `checkoutStatusViewModel` isolando a semantica exibida ao comprador
- `CheckoutStepper` agora renderiza o pos-submit fora do accordion da etapa de pagamento
- `checkoutResponseAdapters.ts`
- `MobileCheckoutFooter`
- `useCheckoutStatusPolling.test.tsx`

Concluido localmente no backend:

- payload semantico em `checkout.summary`
- labels e proximo passo orientados a UX
- `expires_in_seconds`, `is_waiting_payment` e `can_retry`
- `PublicEventCheckoutPayloadContractTest.php`

## Fase 7 - Mobile, Hardening E Draft Seguro

Objetivo:

- fechar os riscos de navegador real
- lapidar retomada e storage

### Subtarefas

1. mobile-first claro
   - coluna unica
   - resumo compacto
   - `MobileCheckoutFooter`
   - `Drawer` apenas para detalhes secundarios

2. reforcar draft seguro
   - `version`
   - `expires_at`
   - limpeza automatica
   - avaliar `sessionStorage` primeiro

3. revisar estados de erro
   - falha de catalogo
   - falha de pre-check
   - falha de checkout
   - expiracao de Pix
   - rede lenta

4. revisar contraste do tema claro

5. revisar navegacao por teclado e foco
   - stepper
   - CTA principal
   - QR Code/copia e cola
   - drawer secundario mobile

### TDD obrigatorio

Frontend:

- `PublicCheckoutResumeFlow.test.tsx`
- `PublicCheckoutMobileLayout.test.tsx`
- `useCheckoutResumeDraft.test.tsx`
- `useCheckoutStatusPolling.test.tsx`

Cenarios:

- resume Pix apos login funciona
- cartao retomado nao restaura dados sensiveis
- mobile continua usavel
- erro de pre-check nao trava jornada
- foco e teclado continuam funcionais na troca de etapa

## Fase 8 - E2E De Navegador Real

Objetivo:

- cobrir os riscos mais perigosos de fluxo

Infra existente:

- `apps/web/playwright.config.ts`

Status atual:

- concluida localmente
- `@playwright/test` instalado
- `webServer` configurado para subir a SPA no proprio runner
- cinco cenarios criticos do checkout V2 passaram em navegador real

### Subtarefas

1. criar cenarios E2E em `apps/web/e2e`

2. orquestrar ambiente local minimo para API + web

### Bateria E2E obrigatoria

1. Pix feliz do inicio ao QR
2. conflito de identidade + login + retomada Pix
3. cartao com validacao progressiva e reentrada segura
4. mobile + voltar etapa + manter dados
5. refresh com `?checkout=<uuid>&step=status` reidrata acompanhamento corretamente

## Bateria TDD Permanente

### Frontend unitario

- `packageCommercialCopy.test.ts`
- `usePublicCheckoutWizard.test.tsx`
- `useCheckoutIdentityPrecheck.test.tsx`
- `useCheckoutResumeDraft.test.tsx`
- `useCheckoutStatusPolling.test.tsx`

### Frontend de componentes

- `PackageSelectionStep.test.tsx`
- `BuyerEventStep.test.tsx`
- `IdentityAssistInline.test.tsx`
- `PaymentStep.test.tsx`
- `PaymentStatusCard.test.tsx`
- `CheckoutSidebar.test.tsx`
- `PublicCheckoutCopyGuard.test.tsx`

### Frontend de fluxo

- `PublicCheckoutPageV2.test.tsx`
- `PublicCheckoutResumeFlow.test.tsx`
- `PublicCheckoutMobileLayout.test.tsx`
- `PublicCheckoutAccessibilityFlow.test.tsx`

### Frontend E2E

- `apps/web/e2e/public-checkout-pix.spec.ts`
- `apps/web/e2e/public-checkout-resume.spec.ts`
- `apps/web/e2e/public-checkout-card.spec.ts`
- `apps/web/e2e/public-checkout-mobile.spec.ts`
- `apps/web/e2e/public-checkout-status-resume.spec.ts`

### Backend feature

- `PublicCheckoutIdentityPrecheckTest.php`
- `PublicEventCheckoutTest.php`
- `EventPackageCatalogTest.php`
- `BillingTest.php`
- `PublicEventCheckoutPayloadContractTest.php`

## Comandos Obrigatorios

Frontend:

- `npm run test -- src/modules/billing/PublicEventCheckoutEntryPage.test.tsx src/modules/billing/public-checkout src/modules/billing/services/public-event-packages.service.test.ts src/modules/plans/PlansPage.test.tsx`
- `npm run test -- src/modules/billing/public-checkout`
- `npm run type-check`

Frontend E2E:

- `npx playwright test`

Backend:

- `php artisan test --filter=PublicCheckoutIdentityPrecheckTest`
- `php artisan test --filter=PublicEventCheckoutTest`
- `php artisan test --filter=EventPackageCatalogTest`
- `php artisan test --filter=BillingTest`
- `php artisan test --filter=PublicEventCheckoutPayloadContractTest`

Observacao pratica:

- a bateria publica agora roda apenas sobre a V2 e os contratos ainda ativos

## Sequencia Recomendada De PRs

### PR 1

- pre-check backend
- contrato seguro
- rate limit
- testes backend

### PR 2

- infra V2
- `PublicCheckoutShell`
- `usePublicCheckoutWizard`
- URL sync
- adapters de payload
- feature flag ou fallback de rollout

### PR 3

- etapa 1 pacote comercial
- lateral desktop/mobile

### PR 4

- etapa 2 `Seus dados`
- `IdentityAssistInline`
- resume banner

### PR 5

- etapa 3 pagamento
- Pix dominante
- cartao simplificado
- estado `status/post_submit`

### PR 6

- payload semantico backend
- limpeza de acoplamento com `gateway_status`

### PR 7

- hardening
- mobile
- draft seguro
- E2E

## Definition Of Done

O checkout V2 so esta pronto quando:

- a primeira dobra nao fala em `billing`, `gateway`, `UUID`, `webhook`, `polling`, `tokenizacao`
- o `Accordion` nao e a fonte de verdade da jornada
- a etapa esta sincronizada com a URL
- o usuario ve no maximo uma etapa principal por vez
- a etapa 2 se comporta como `Seus dados`, nao como cadastro de sistema
- Pix e o caminho mais rapido
- o cartao parece seguro sem aula tecnica
- login so aparece quando ajuda
- o pos-Pix e um estado de acompanhamento claro
- o backend continua fechando a compra avulsa ponta a ponta
- os termos tecnicos proibidos nao aparecem na UI publica
- a bateria TDD permanente e a E2E estao verdes

## Decisao Final

Nao implementar a V2 como uma arvore bonita de componentes em volta da logica atual.

Implementar a V2 como:

- uma pequena maquina de jornada
- adapters de payload
- pre-check silencioso seguro
- steps finos em volta desse estado
- bateria TDD e E2E permanente

Esse caminho deixa a experiencia melhor para o comprador e deixa a manutencao muito mais sustentavel para o time.
