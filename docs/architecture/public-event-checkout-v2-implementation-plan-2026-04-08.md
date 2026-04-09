# Checkout Publico De Evento V2 - Plano De Implementacao

Data: `2026-04-08`

Documento base:

- `docs/architecture/public-event-checkout-ux-analysis-2026-04-08.md`

## Objetivo

Transformar `/checkout/evento` de uma tela tecnicamente correta, mas operacional/tecnica demais, em uma jornada comercial curta, clara e confiavel para usuario final.

A V2 deve:

- parecer compra, nao operacao
- usar linguagem humana
- reduzir campos visiveis por vez
- manter Pix como caminho principal
- sugerir login apenas quando realmente ajudar
- preservar a seguranca e os contratos reais de backend

## Validacao Do Toolkit De UI

### Stack local confirmado no projeto

Arquivos inspecionados:

- `apps/web/package.json`
- `apps/web/components.json`
- `apps/web/src/components/ui/*`
- `apps/web/src/hooks/use-mobile.tsx`

Stack confirmado:

- `shadcn/ui` configurado localmente
- `Radix UI` como base dos primitives
- `Vaul` para drawer
- `React Hook Form + Zod`
- `framer-motion`
- `TailwindCSS`

### Componentes locais ja disponiveis

Disponiveis no repo e prontos para uso:

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

Conclusoes praticas:

- nao precisamos adicionar uma nova biblioteca de formulario
- nao precisamos adicionar uma nova biblioteca de drawer/modal
- nao existe `Stepper` pronto instalado no projeto
- a jornada V2 deve ser montada por composicao

### Componentes modernos validados nas docs oficiais

Referencias oficiais verificadas:

- shadcn Accordion:
  - https://ui.shadcn.com/docs/components/accordion
- shadcn Progress:
  - https://ui.shadcn.com/docs/components/progress
- shadcn Tabs:
  - https://ui.shadcn.com/docs/components/tabs
- shadcn Drawer:
  - https://ui.shadcn.com/docs/components/drawer
- shadcn Forms:
  - https://ui.shadcn.com/docs/forms
- shadcn Collapsible:
  - https://ui.shadcn.com/docs/components/collapsible
- shadcn Dialog:
  - https://ui.shadcn.com/docs/components/dialog

Leitura das docs oficiais:

- `Accordion` e apropriado para secoes empilhadas e composicao linear
- `Progress` e apropriado para indicar progresso de tarefa
- `Tabs` e apropriado para conteudo em paines alternados
- `Drawer` e suportado oficialmente e o proprio shadcn documenta o padrao de `Responsive Dialog`
- `Collapsible` e apropriado para revelar detalhes extras sem poluir a tela
- `Forms` seguem a integracao oficial com `React Hook Form`

### Decisoes de arquitetura de UI

Com base no toolkit local + docs oficiais:

- o stepper da V2 sera composto com estado proprio + `Accordion` + `Progress`
- `Tabs` nao serao o stepper principal; serao usados, se necessario, apenas para alternancia de meio de pagamento
- `Collapsible` sera usado para `Adicionar mais detalhes`
- `Drawer` e `Dialog` ficam reservados para uso responsivo em conteudo secundario, nao como fluxo principal
- `Form` do shadcn continua como wrapper oficial do RHF

## Principios Da Implementacao

### Produto

- etapa 1: `Escolha seu pacote`
- etapa 2: `Seus dados`
- etapa 3: `Pagamento`

### UX

- mostrar so o necessario na etapa atual
- Pix por padrao
- cartao revelado apenas quando escolhido
- login como assistencia, nao como etapa universal
- linguagem de comprador, nao de plataforma

### Tecnico

- preservar contratos reais existentes
- quebrar o monolito atual em componentes e hooks
- adicionar pre-check silencioso de identidade antes da etapa final
- manter compatibilidade com retomada segura ja existente

## Arquitetura Alvo

### Frontend

Nova pasta alvo:

```txt
apps/web/src/modules/billing/public-checkout/
  PublicCheckoutPageV2.tsx
  components/
    CheckoutHeroSimple.tsx
    CheckoutStepper.tsx
    PackageSelectionStep.tsx
    PackageCard.tsx
    PackageBenefitList.tsx
    PackageRecommendedBadge.tsx
    BuyerEventStep.tsx
    BuyerIdentityFields.tsx
    EventBasicsFields.tsx
    IdentityAssistInline.tsx
    PaymentStep.tsx
    PaymentMethodSwitch.tsx
    PixPaymentPanel.tsx
    CreditCardPaymentPanel.tsx
    PaymentTrustNote.tsx
    CheckoutSidebar.tsx
    OrderSummaryCard.tsx
    NextStepCard.tsx
    TrustSignalsCard.tsx
    PaymentStatusCard.tsx
  hooks/
    usePublicCheckoutWizard.ts
    useCheckoutIdentityPrecheck.ts
    useCheckoutResumeDraft.ts
    useCheckoutStatusPolling.ts
  mappers/
    packageCommercialCopy.ts
  services/
    public-checkout-identity.service.ts
```

### Backend

Novos pontos esperados:

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

Rotas novas esperadas:

- `POST /api/v1/public/checkout-identity/check`

## Fases De Implementacao

## Fase 0 - Baseline E Congelamento De Contrato

Objetivo:

- garantir que a base atual esta verde antes de refatorar
- congelar os contratos que nao podem quebrar

### Subtarefas

1. mapear os contratos que devem permanecer compativeis:
   - `GET /public/event-packages`
   - `POST /public/event-checkouts`
   - `GET /public/event-checkouts/{uuid}`
   - retomada segura via `resume=auth`

2. registrar no plano os testes atuais que nao podem regredir

3. validar os comandos baseline antes de qualquer refactor

### TDD obrigatorio da fase

Backend:

- `php artisan test --filter=PublicEventCheckoutTest`
- `php artisan test --filter=EventPackageCatalogTest`
- `php artisan test --filter=BillingTest`

Frontend:

- `npm run test -- PublicEventCheckoutPage.test.tsx public-event-packages.service.test.ts PlansPage.test.tsx`
- `npm run type-check`

## Fase 1 - Pre-Check Silencioso De Identidade

Objetivo:

- descobrir cedo se o contato ja tem cadastro
- manter a etapa 2 como `Seus dados`
- sugerir login so quando isso realmente ajudar

### Subtarefas de backend

1. criar `CheckPublicCheckoutIdentityRequest`
   - validar `whatsapp`
   - validar `email` opcional

2. criar `CheckPublicCheckoutIdentityAction`
   - reutilizar `PublicJourneyIdentityService`
   - retornar estados:
     - `new_account`
     - `login_suggested`
     - `authenticated_match`

3. criar `PublicCheckoutIdentityController`

4. registrar rota:
   - `POST /api/v1/public/checkout-identity/check`

5. garantir resposta neutra
   - sem enumeracao agressiva
   - sem vazar se email/telefone existem de forma crua

6. aplicar rate limit

7. opcional desta fase:
   - registrar activity ou log estrutural do pre-check

### Subtarefas de frontend

1. criar `public-checkout-identity.service.ts`

2. criar `useCheckoutIdentityPrecheck.ts`
   - debounce
   - cancelamento de requisicao
   - estados:
     - `idle`
     - `checking`
     - `new_account`
     - `login_suggested`
     - `authenticated_match`

3. criar `IdentityAssistInline.tsx`
   - mensagem positiva para novo contato
   - mensagem neutra sugerindo login quando houver conta
   - CTA `Entrar para continuar`

4. integrar o hook na futura etapa `Seus dados`

### TDD obrigatorio da fase

Backend:

- criar `apps/api/tests/Feature/Billing/PublicCheckoutIdentityPrecheckTest.php`

Cenarios obrigatorios:

- retorna `new_account` para contato inedito
- retorna `login_suggested` para WhatsApp ja existente
- retorna `login_suggested` para email ja existente
- retorna resposta neutra e sem erro 422 de enumeracao de conta
- respeita rate limit
- aceita usuario autenticado compatível como `authenticated_match`

Frontend:

- criar `apps/web/src/modules/billing/public-checkout/services/public-checkout-identity.service.test.ts`
- criar `apps/web/src/modules/billing/public-checkout/hooks/useCheckoutIdentityPrecheck.test.tsx`
- criar `apps/web/src/modules/billing/public-checkout/components/IdentityAssistInline.test.tsx`

Cenarios obrigatorios:

- nao dispara pre-check com WhatsApp invalido
- dispara com debounce quando WhatsApp fica valido
- cancela requisicao anterior quando o usuario continua digitando
- mostra `checking` sem bloquear digitacao
- renderiza `login_suggested` com CTA correto
- falha de rede nao quebra a etapa

### Gate de fase

- todos os testes novos verdes
- suites baseline continuam verdes

## Fase 2 - Refactor Estrutural Do Frontend

Objetivo:

- quebrar `PublicEventCheckoutPage.tsx` em blocos de jornada
- sair do monolito atual

### Subtarefas

1. criar `PublicCheckoutPageV2.tsx`
   - nova orquestracao
   - mesma rota final `/checkout/evento`

2. criar `PublicCheckoutShell`
   - layout macro
   - tema claro
   - grid principal

3. criar `CheckoutHeroSimple`

4. criar `CheckoutStepper`
   - `Accordion` como base de secao
   - `Progress` como indicador discreto

5. criar `CheckoutSidebar`
   - resumo
   - proximo passo
   - confianca
   - status de pagamento

6. mover logica de wizard para `usePublicCheckoutWizard`

7. manter a pagina antiga funcionando ate a V2 estar completa

### TDD obrigatorio da fase

Frontend:

- criar `apps/web/src/modules/billing/public-checkout/hooks/usePublicCheckoutWizard.test.tsx`
- criar `apps/web/src/modules/billing/public-checkout/components/CheckoutStepper.test.tsx`
- criar `apps/web/src/modules/billing/public-checkout/components/CheckoutSidebar.test.tsx`
- criar `apps/web/src/modules/billing/public-checkout/PublicCheckoutPageV2.test.tsx`

Cenarios obrigatorios:

- exibe so uma etapa principal aberta por vez
- nao mostra pagamento antes de concluir a etapa 2
- atualiza progresso conforme a etapa muda
- mostra lateral coerente com o estado atual da jornada
- renderiza layout valido em desktop e mobile

### Gate de fase

- `PublicEventCheckoutPage.test.tsx` adaptado ou substituido por testes V2 equivalentes
- `npm run type-check` verde

## Fase 3 - Etapa 1: Pacote Comercial

Objetivo:

- transformar pacote tecnico em escolha comercial clara

### Subtarefas

1. criar `packageCommercialCopy.ts`
   - traduz `modules`, `feature_map` e `limits`
   - gera:
     - subtitulo curto
     - 3 a 5 beneficios
     - selo recomendado opcional
     - copy `ideal para`

2. criar `PackageCard`

3. criar `PackageSelectionStep`

4. remover linguagem de modulo interno da UI publica
   - sair de `Wall on/off`
   - entrar em beneficios

5. opcional desta fase:
   - escolher pacote recomendado por regra simples

### TDD obrigatorio da fase

Frontend:

- criar `apps/web/src/modules/billing/public-checkout/mappers/packageCommercialCopy.test.ts`
- criar `apps/web/src/modules/billing/public-checkout/components/PackageCard.test.tsx`
- criar `apps/web/src/modules/billing/public-checkout/components/PackageSelectionStep.test.tsx`

Cenarios obrigatorios:

- converte pacote tecnico em bullets comerciais
- nao exibe flags internas ao usuario
- seleciona pacote corretamente
- exibe preco e resumo coerentes na lateral

Backend de regressao:

- `php artisan test --filter=EventPackageCatalogTest`

## Fase 4 - Etapa 2: Seus Dados

Objetivo:

- reduzir densidade do formulario
- pedir apenas o essencial primeiro

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
   - nome da organizacao
   - descricao do evento

4. remover `descricao do evento` da area principal

5. renomear `organization_name` na UI para rotulo humano
   - `Nome do casal, empresa ou responsavel`

6. integrar `IdentityAssistInline`

7. manter CTA secundario `Ja tenho conta`

### TDD obrigatorio da fase

Frontend:

- criar `apps/web/src/modules/billing/public-checkout/components/BuyerIdentityFields.test.tsx`
- criar `apps/web/src/modules/billing/public-checkout/components/EventBasicsFields.test.tsx`
- criar `apps/web/src/modules/billing/public-checkout/components/BuyerEventStep.test.tsx`

Cenarios obrigatorios:

- etapa principal mostra apenas os campos essenciais
- detalhes extras ficam fechados por padrao
- CTA `Continuar para pagamento` so habilita com dados minimos validos
- CTA `Ja tenho conta` aponta para `returnTo=/checkout/evento?resume=auth`
- pre-check sugere login inline quando aplicavel

Backend de regressao:

- `php artisan test --filter=PublicEventCheckoutTest`

## Fase 5 - Etapa 3: Pagamento Seguro

Objetivo:

- deixar Pix como caminho mais rapido
- simplificar cartao sem perder seguranca

### Subtarefas

1. criar `PaymentMethodSwitch`
   - Pix default
   - cartao como opcao secundaria

2. criar `PixPaymentPanel`
   - copy curta
   - CTA `Gerar meu Pix`

3. criar `CreditCardPaymentPanel`
   - revelar so quando escolhido
   - manter campos necessarios
   - esconder jargao tecnico

4. criar `PaymentTrustNote`
   - `Pagamento seguro por cartao`
   - `Seus dados sao protegidos`
   - `Confirmacao rapida apos a analise do pagamento`

5. simplificar ou mover o checklist atual
   - pode continuar existindo internamente
   - nao deve dominar a tela

6. preservar a tokenizacao atual e o contrato com o backend

### TDD obrigatorio da fase

Frontend:

- criar `apps/web/src/modules/billing/public-checkout/components/PaymentMethodSwitch.test.tsx`
- criar `apps/web/src/modules/billing/public-checkout/components/PixPaymentPanel.test.tsx`
- criar `apps/web/src/modules/billing/public-checkout/components/CreditCardPaymentPanel.test.tsx`

Cenarios obrigatorios:

- Pix vem selecionado por padrao
- cartao nao aparece ate ser escolhido
- copy tecnica some da UI publica
- submit Pix gera payload minimo correto
- submit cartao continua exigindo os campos obrigatorios corretos
- cartao nao persiste numero/CVV em retomada segura

Backend de regressao:

- `php artisan test --filter=PublicEventCheckoutTest`
- `php artisan test --filter=BillingTest`

## Fase 6 - Status Publico E Pos-Pagamento

Objetivo:

- traduzir operacao em linguagem de comprador

### Subtarefas de backend

1. simplificar o payload publico com campos semanticos
   - `payment_status_label`
   - `payment_status_description`
   - `next_action`
   - `expires_in_seconds`
   - `is_waiting_payment`
   - `can_retry`

2. manter campos tecnicos para operacao interna, se necessario
   - mas parar de depender deles na UI publica

### Subtarefas de frontend

1. reescrever `PaymentStatusCard`

2. remover da UI publica:
   - `UUID`
   - `gateway status`
   - `status local`
   - `polling`
   - `webhook`
   - `billing`

3. manter somente estados compreensiveis:
   - pedido criado
   - aguardando pagamento
   - pagamento confirmado
   - pagamento nao confirmado

4. preservar acompanhamento de Pix e CTA `Atualizar pagamento`

### TDD obrigatorio da fase

Backend:

- criar `apps/api/tests/Feature/Billing/PublicEventCheckoutPayloadContractTest.php`

Cenarios obrigatorios:

- devolve labels semanticamente amigaveis para Pix pendente
- devolve labels semanticamente amigaveis para pagamento pago
- devolve expiracao restante quando houver

Frontend:

- criar `apps/web/src/modules/billing/public-checkout/components/PaymentStatusCard.test.tsx`

Cenarios obrigatorios:

- nunca exibe `UUID` ao comprador
- nunca exibe `gateway status` ao comprador
- exibe `Aguardando pagamento` para Pix pendente
- exibe `Pagamento confirmado` quando pago
- CTA de atualizar continua disponivel

## Fase 7 - Retomada, Mobile E Hardening

Objetivo:

- garantir experiencia boa em cenarios reais

### Subtarefas

1. validar retomada apos login no wizard V2

2. garantir mobile-first real
   - stack vertical
   - sidebar colapsada ou reposicionada
   - componentes secundarios em `Drawer`/`Dialog` responsivo quando fizer sentido

3. garantir estado de erro elegante
   - falha de catalogo
   - falha de pre-check
   - falha de criacao de checkout
   - pagamento pendente/expirado

4. revisar contraste e tema claro

### TDD obrigatorio da fase

Frontend:

- criar `apps/web/src/modules/billing/public-checkout/PublicCheckoutResumeFlow.test.tsx`
- criar `apps/web/src/modules/billing/public-checkout/PublicCheckoutMobileLayout.test.tsx`

Cenarios obrigatorios:

- resume Pix funciona apos login
- cartao retomado nao restaura PAN/CVV
- layout mobile continua utilizavel
- erro de pre-check nao trava jornada

Backend de regressao:

- `php artisan test --filter=PublicEventCheckoutTest`

## Bateria TDD Permanente

Esta bateria deve rodar sempre antes de considerar o checkout V2 como pronto.

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

### Frontend de fluxo

- `PublicCheckoutPageV2.test.tsx`
- `PublicCheckoutResumeFlow.test.tsx`
- `PublicCheckoutMobileLayout.test.tsx`

### Backend feature

- `PublicCheckoutIdentityPrecheckTest.php`
- `PublicEventCheckoutTest.php`
- `EventPackageCatalogTest.php`
- `BillingTest.php`
- `PublicEventCheckoutPayloadContractTest.php`

### Comandos obrigatorios

Frontend:

- `npm run test -- PublicEventCheckoutPage.test.tsx public-event-packages.service.test.ts PlansPage.test.tsx`
- `npm run test -- src/modules/billing/public-checkout`
- `npm run type-check`

Backend:

- `php artisan test --filter=PublicCheckoutIdentityPrecheckTest`
- `php artisan test --filter=PublicEventCheckoutTest`
- `php artisan test --filter=EventPackageCatalogTest`
- `php artisan test --filter=BillingTest`
- `php artisan test --filter=PublicEventCheckoutPayloadContractTest`

### Regra de aceite tecnico

Nao considerar a jornada pronta se qualquer um destes falhar:

- contrato do checkout publico
- pre-check silencioso
- retomada segura
- compatibilidade Pix
- compatibilidade cartao
- type-check do frontend

## Sequencia Recomendada De PRs

### PR 1

- Fase 1
- pre-check silencioso de identidade
- testes backend + hook/service do frontend

### PR 2

- Fase 2
- shell, stepper, sidebar, wizard state

### PR 3

- Fase 3
- pacote comercial

### PR 4

- Fase 4
- etapa `Seus dados`

### PR 5

- Fase 5
- pagamento Pix/cartao simplificado

### PR 6

- Fase 6
- payload semantico + status publico simplificado

### PR 7

- Fase 7
- retomada, mobile e hardening final

## Definition Of Done

O checkout V2 so pode ser considerado pronto quando:

- a primeira dobra nao fala em `billing`, `gateway`, `UUID`, `webhook`, `polling`, `tokenizacao`
- o usuario ve no maximo uma etapa principal por vez
- a etapa 2 se comporta como `Seus dados`, nao como `cadastro de sistema`
- Pix e o caminho mais rapido
- o cartao parece seguro sem aula tecnica
- login so aparece quando ajuda
- o backend continua fechando a compra avulsa ponta a ponta
- toda a bateria TDD permanente esta verde

## Decisao Final

Nao implementar a V2 como "troca de texto" dentro do monolito atual.

Implementar a V2 como:

- refactor estrutural de frontend
- pre-check silencioso no backend
- bateria TDD permanente em volta dos contratos

Esse caminho reduz risco, preserva o que ja funciona e deixa a jornada pronta para evoluir sem voltar a virar uma tela tecnica.
