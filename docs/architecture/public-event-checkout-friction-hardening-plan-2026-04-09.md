# Checkout Publico De Evento - Friction Hardening Plan

Data: `2026-04-09`

Documentos base:

- `docs/architecture/public-event-checkout-ux-analysis-2026-04-08.md`
- `docs/execution-plans/public-event-checkout-v2-implementation-plan-2026-04-08.md`

## Objetivo

Planejar a proxima rodada de melhoria do checkout publico V2 com foco em:

- reduzir friccao na etapa `Seus dados`
- corrigir continuidade da jornada quando o comprador ja tem conta
- melhorar a captura de telefone e agenda do evento
- refinar a experiencia mobile-first
- decidir com precisao o que realmente precisa de backend e o que e apenas frontend

Este plano nao inicia a implementacao da rodada.

Ele fecha primeiro:

- analise da logica atual
- testes de caracterizacao para remover duvidas
- escopo real de backend e frontend
- sequencia recomendada de TDD

## Validacao Preparatoria Executada

### Bateria rodada

Frontend:

- `npm run test -- src/modules/billing/public-checkout/PublicCheckoutFrictionCharacterization.test.tsx`
- `npm run test -- src/modules/billing/public-checkout/PublicCheckoutFrictionCharacterization.test.tsx src/modules/billing/public-checkout/PublicCheckoutPageV2.test.tsx src/modules/billing/public-checkout/PublicCheckoutMobileLayout.test.tsx src/modules/billing/PublicEventCheckoutEntryPage.test.tsx`
- `npm run type-check`

Backend:

- `php artisan test --filter="accepts an event_date with local time in the public checkout contract and persists it on the event schedule"`
- `php artisan test --filter=PublicEventCheckoutTest`

### Testes de caracterizacao adicionados

Frontend:

- `apps/web/src/modules/billing/public-checkout/PublicCheckoutFrictionCharacterization.test.tsx`
  - confirma que o campo de WhatsApp hoje aceita digitacao crua sem mascara
  - confirma que o campo `event_date` ainda e `type="date"`
  - foi atualizado para confirmar que `Ja tenho conta` agora persiste draft seguro antes da saida
  - confirma que o rodape mobile atual prioriza `Ver resumo`, nao um CTA fixo da etapa
- `apps/web/e2e/public-checkout-manual-login.spec.ts`
  - cobre o clique manual em `Ja tenho conta`, login com `returnTo`, retomada em `payment` e preservacao dos dados ao voltar para `details`

Backend:

- `apps/api/tests/Feature/Billing/PublicEventCheckoutTest.php`
  - novo caso validando que `event.event_date = 2026-11-15T18:30` ja passa no contrato atual e persiste horario em `events.starts_at`

### Resultado

- a bateria preparatoria ficou verde
- o backend atual ja aceita `datetime-local` sem refatoracao do contrato
- os maiores gaps desta rodada estao no frontend de jornada, nao no core do billing

## Logica Atual Validada

## 1. WhatsApp sem mascara e problema de UX, nao de payload

Leitura confirmada:

- o input visual em `BuyerIdentityFields.tsx` nao aplica `formatPhone`
- a validacao atual em `checkoutFormSchema.ts` so rejeita quando faltam digitos
- o payload final ja normaliza com `digitsOnly()` em `checkoutFormUtils.ts`

Implicacao:

- o backend nao e o gargalo
- a friccao aqui e do campo visual, do teclado mobile e da previsibilidade da digitacao

Decisao:

- tratar como P0 de frontend
- manter a normalizacao atual no payload
- adicionar mascara visual, `inputMode="tel"` e `autoComplete="tel"`

## 2. Data e hora do evento ja cabem no backend atual

Leitura confirmada:

- `StorePublicEventCheckoutRequest` usa `event.event_date => ['nullable', 'date']`
- `CreatePublicEventCheckoutAction` grava `event.event_date` em `events.starts_at`
- o novo teste confirmou que `2026-11-15T18:30` passa no contrato atual e persiste horario

Implicacao:

- a mudanca principal e de UX/campo no frontend
- a rodada nao depende de endpoint novo nem de migration

Decisao:

- manter o payload key `event_date`
- trocar o campo da UI para `datetime-local`
- usar copy final `Quando seu evento acontece?`
- manter opcional dentro de `Adicionar mais detalhes`

## 3. O CTA `Ja tenho conta` era semanticamente certo, mas estava operacionalmente incompleto

Leitura confirmada:

- `buildV2LoginResumePath()` monta corretamente `/login?returnTo=/checkout/evento?...resume=auth...`
- `LoginRoute` e `LoginPage` ja respeitam `returnTo`
- `PublicCheckoutPageV2` ja sabe retomar a jornada quando existe `resumeDraft`
- o gap original era o clique manual em `Ja tenho conta`, que nao gravava draft antes da navegacao
- a implementacao desta rodada passou a gravar draft seguro com `source = manual_login`
- o fluxo agora pula o `/login` quando o comprador ja esta autenticado e abre `payment` diretamente

Impacto original:

- o usuario percebe reload ou ida/volta sem continuidade clara
- o pacote escolhido esta no `returnTo`, mas os demais dados nao ficam garantidos
- se o usuario ja esta autenticado, o redirect automatico do `/login` parece recarregamento

Decisao executada:

- tratar como P0 de jornada
- persistir draft seguro no clique manual
- preservar `package` no `returnTo`
- se o usuario ja estiver autenticado, nao ir para `/login`; entrar direto na retomada em `payment`

Validacao concluida:

- `PublicCheckoutPageV2.test.tsx`
  - grava draft seguro manual
  - preserva `package` no `returnTo`
  - pula a tela de login quando ja autenticado
- `PublicCheckoutFrictionCharacterization.test.tsx`
  - confirma a persistencia do draft seguro no clique manual
- `apps/web/e2e/public-checkout-manual-login.spec.ts`
  - cobre login manual com retomada em `payment` e dados preservados ao voltar para `details`

## 4. O mobile atual esta summary-first, nao CTA-first

Leitura confirmada:

- o `MobileCheckoutFooter` fixa `Ver resumo`
- o CTA principal da etapa continua dentro do conteudo rolavel
- o shell ainda preserva espacamento e destaque de hero mais proximos de desktop

Impacto:

- no celular, o fluxo principal de compra nao fica sempre ao alcance do polegar
- o resumo esta melhor que antes, mas ainda nao e o melhor uso do espaÃ§o fixo

Decisao:

- tratar como P1 alto
- transformar o sticky mobile em CTA-first da etapa atual
- manter `Resumo` como acao secundaria

## O Que Realmente E Importante Nesta Rodada

Ordem real de importancia:

1. continuidade do fluxo `Ja tenho conta`
2. mascara e teclado do WhatsApp
3. data e hora do evento na UI
4. rodape mobile CTA-first
5. compressao visual do hero e da dobra inicial no celular

O que nao e prioritario agora:

- refatorar o contrato publico do backend
- mudar payload de billing
- criar endpoint novo
- tornar data/hora obrigatoria
- reabrir a arquitetura de pagamento

## Escopo Recomendado

## Fase 0 - Caracterizacao e base de TDD

Status:

- concluida nesta rodada

Arquivos relevantes:

- `apps/web/src/modules/billing/public-checkout/PublicCheckoutFrictionCharacterization.test.tsx`
- `apps/api/tests/Feature/Billing/PublicEventCheckoutTest.php`

Objetivo:

- congelar o comportamento atual antes da melhoria
- distinguir claramente bug de jornada versus limite de backend

## Fase 1 - Continuity Fix para `Ja tenho conta`

Status:

- concluida e validada localmente

Prioridade:

- P0

Objetivo:

- o CTA manual deve cumprir a promessa de entrar e voltar direto para continuar

Frontend:

- `apps/web/src/modules/billing/public-checkout/components/BuyerEventStep.tsx`
- `apps/web/src/modules/billing/public-checkout/PublicCheckoutPageV2.tsx`
- `apps/web/src/modules/billing/public-checkout/hooks/useCheckoutResumeDraft.ts`
- `apps/web/src/modules/billing/public-checkout/support/checkoutFormUtils.ts`

Mudancas:

- substituir o link puro por fluxo controlado pelo page orchestrator
- salvar draft seguro no clique manual
- preservar `package`, `step=payment` e dados seguros
- se `isAuthenticated = false`, navegar para `/login?returnTo=...`
- se `isAuthenticated = true`, evitar ida ao login e abrir retomada diretamente em `payment`

Red tests a escrever primeiro:

- clicar em `Ja tenho conta` grava draft seguro antes da saida
- o pacote deep-linked continua no `returnTo`
- usuario autenticado nao vai para `/login` e cai direto em `payment`
- usuario nao autenticado volta com `resume=auth` e encontra a jornada pronta para continuar

Aceite:

- o clique manual nao parece mais apenas reload
- o comprador nao perde o pacote nem os dados seguros
- a retomada abre em `payment`

Implementado:

- `BuyerEventStep.tsx`
  - CTA virou fluxo controlado por `onUseExistingAccount`
- `PublicCheckoutPageV2.tsx`
  - grava draft com `manual_login`
  - navega para login quando anonimo
  - pula login e vai para retomada quando autenticado
  - abre `payment` na retomada manual sem auto-submeter Pix
- `useCheckoutResumeDraft.ts`
  - aceita escrita com `source` explicita
- `checkoutFormUtils.ts`
  - centraliza `buildV2CheckoutResumePath()` e reaproveita em `buildV2LoginResumePath()`

Bateria executada nesta fase:

- `npm run test -- src/modules/billing/public-checkout/PublicCheckoutPageV2.test.tsx src/modules/billing/public-checkout/PublicCheckoutFrictionCharacterization.test.tsx src/modules/billing/public-checkout/PublicCheckoutMobileLayout.test.tsx src/modules/billing/PublicEventCheckoutEntryPage.test.tsx`
- `npm run type-check`
- `php artisan test --filter=PublicEventCheckoutTest`
- `npx playwright test e2e/public-checkout-resume.spec.ts e2e/public-checkout-manual-login.spec.ts e2e/public-checkout-mobile.spec.ts e2e/public-checkout-pix.spec.ts`

## Fase 2 - Hardening do campo WhatsApp

Status:

- concluida e validada localmente

Prioridade:

- P0

Objetivo:

- o campo se comporta como telefone desde a digitacao

Frontend:

- `apps/web/src/modules/billing/public-checkout/components/BuyerIdentityFields.tsx`
- `apps/web/src/modules/billing/public-checkout/support/checkoutFormUtils.ts`
- `apps/web/src/modules/billing/public-checkout/PublicCheckoutPageV2.tsx`

Mudancas:

- aplicar `formatPhone()` no `onChange`
- adicionar `inputMode="tel"`
- adicionar `autoComplete="tel"`
- manter `digitsOnly()` no payload e no pre-check
- suportar colagem sem quebrar o formato

Red tests a escrever primeiro:

- letras nao permanecem no valor visual
- digitacao progressiva formata para `(48) 99977-1111`
- colar numero cru formata corretamente
- o pre-check continua disparando com o valor formatado no input

Aceite:

- o comprador nao consegue deixar o campo visualmente â€œsujoâ€
- o teclado mobile de telefone abre corretamente
- o contrato enviado continua identico do ponto de vista do backend

Implementado:

- `BuyerIdentityFields.tsx`
  - aplica `formatPhone()` no `onChange`
  - expoe `inputMode="tel"` e `autoComplete="tel"`
- `BuyerIdentityFields.test.tsx`
  - cobre mascara visual e hints de teclado
- `PublicCheckoutPageV2.test.tsx`
  - cobre digitacao ruidosa e payload final em digitos

Bateria executada nesta fase:

- `npm run test -- src/modules/billing/public-checkout/components/BuyerIdentityFields.test.tsx src/modules/billing/public-checkout/PublicCheckoutPageV2.test.tsx`
- `npm run type-check`

## Fase 3 - `Data e hora do evento`

Status:

- concluida e validada localmente

Prioridade:

- P1

Objetivo:

- coletar agenda do servico com semantica mais util, sem aumentar friccao cedo demais

Frontend:

- `apps/web/src/modules/billing/public-checkout/components/BuyerEventStep.tsx`
- `apps/web/src/modules/billing/public-checkout/support/checkoutFormSchema.ts`
- `apps/web/src/modules/billing/public-checkout/PublicCheckoutFrictionCharacterization.test.tsx`

Backend:

- nenhum ajuste obrigatorio para a primeira entrega
- manter o teste de contrato que ja provou compatibilidade com `datetime-local`

Mudancas:

- trocar `type="date"` por `type="datetime-local"`
- trocar label para `Quando seu evento acontece?`
- manter dentro de `Adicionar mais detalhes`
- opcionalmente adicionar helper de copy: `Se ainda estiver definindo, voce pode preencher depois`

Red tests a escrever primeiro:

- a UI renderiza `datetime-local`
- o payload enviado preserva a hora escolhida
- o backend continua persistindo em `starts_at`

Aceite:

- o horario do evento deixa de se perder
- a jornada continua leve porque o campo segue opcional

Implementado:

- `BuyerEventStep.tsx`
  - campo opcional mudou para `type="datetime-local"`
  - label final de UX ajustado depois para `Quando seu evento acontece?`
- `BuyerEventStep.test.tsx`
  - cobre o campo com data e hora
- `PublicCheckoutPageV2.test.tsx`
  - cobre envio de `event.event_date` com horario
- `PublicEventCheckoutTest.php`
  - segue cobrindo persistencia do horario em `events.starts_at`

Bateria executada nesta fase:

- `npm run test -- src/modules/billing/public-checkout/components/BuyerEventStep.test.tsx src/modules/billing/public-checkout/PublicCheckoutPageV2.test.tsx`
- `php artisan test --filter=\"accepts an event_date with local time in the public checkout contract and persists it on the event schedule\"`
- `npm run type-check`

## Fase 4 - Mobile-first CTA

Status:

- CTA-first concluido e validado localmente
- compactacao extra de hero e resumo compacto concluidos na Fase 5

Prioridade:

- P1 alto

Objetivo:

- no celular, a compra deve ser conduzida pela acao principal da etapa

Frontend:

- `apps/web/src/modules/billing/public-checkout/components/MobileCheckoutFooter.tsx`
- `apps/web/src/modules/billing/public-checkout/components/PublicCheckoutShell.tsx`
- `apps/web/src/modules/billing/public-checkout/PublicCheckoutPageV2.tsx`
- `apps/web/src/modules/billing/public-checkout/mappers/checkoutResponseAdapters.ts`
- `apps/web/src/modules/billing/public-checkout/components/CheckoutHeroSimple.tsx`

Mudancas:

- sticky footer passa a exibir CTA principal da etapa
- `Resumo` vira CTA secundaria
- compactar hero no mobile
- exibir resumo compacto do pacote escolhido acima da etapa ativa ou dentro do sticky
- preservar `safe-area-inset-bottom`

CTA por etapa:

- `package`: `Escolher este pacote`
- `details`: `Continuar para pagamento`
- `payment/pix`: `Gerar meu Pix`
- `payment/card`: `Finalizar com cartao`
- `status`: sem sticky extra

Red tests a escrever primeiro:

- o sticky footer mobile mostra CTA principal contextual
- `Resumo` continua acessivel como acao secundaria
- o conteudo principal nao fica escondido pelo footer
- ao voltar de `payment` para `details`, os dados continuam preservados

Aceite:

- o CTA principal fica sempre facil de alcancar
- o mobile deixa de ser summary-first e vira checkout-first

Implementado:

- `MobileCheckoutFooter.tsx`
  - ganhou CTA principal contextual por etapa
  - `Resumo` ficou como acao secundaria
- `PublicCheckoutPageV2.tsx`
  - passou a orquestrar label e handler do CTA fixo no mobile
- `PublicCheckoutMobileLayout.test.tsx`
  - cobre CTA fixo em `details`
  - cobre CTA fixo em `payment`
  - cobre preservacao de dados ao voltar
- `apps/web/e2e/public-checkout-mobile.spec.ts`
  - cobre o fluxo mobile usando o CTA fixo

Bateria executada nesta fase:

- `npm run test -- src/modules/billing/public-checkout/PublicCheckoutMobileLayout.test.tsx`
- `npx playwright test e2e/public-checkout-mobile.spec.ts`
- `npm run type-check`

## Fase 5 - Polish de UX apos os fixes estruturais

Status:

- concluida e validada localmente nesta rodada de polish mobile/copy

Prioridade:

- P2

Refinamentos implementados:

- reduzir altura do hero no mobile
- trocar a copy do hero para `Contrate seu evento em poucos minutos`
- ocultar a trust row na primeira dobra mobile, mantendo os selos a partir de `sm`
- mostrar resumo compacto do pacote escolhido acima do step ativo no mobile
- ajustar rotulos para maior clareza:
  - `Seu nome completo`
  - `WhatsApp com DDD`
  - `Quando seu evento acontece?`

Ainda opcional:

- mostrar `Trocar pacote` de forma mais visivel depois da selecao
- prefill mais explicito do telefone do pagador quando cartao for escolhido

Implementado:

- `CheckoutHeroSimple.tsx`
  - hero mais baixo no mobile
  - heading menor na primeira dobra
  - trust row escondida no mobile para reduzir ruÃ­do
- `PublicCheckoutShell.tsx`
  - padding/gap mobile reduzidos sem afetar desktop
- `MobileSelectedPackageSummary.tsx`
  - resumo compacto do pacote escolhido somente em mobile e somente apos a etapa de pacote
- `BuyerIdentityFields.tsx`
  - labels mais explicitos para nome e WhatsApp
- `BuyerEventStep.tsx`
  - label do campo opcional de agenda orientado a usuario final

Bateria executada nesta fase:

- `npm run test -- src/modules/billing/public-checkout/components/CheckoutHeroSimple.test.tsx src/modules/billing/public-checkout/components/BuyerIdentityFields.test.tsx src/modules/billing/public-checkout/components/BuyerEventStep.test.tsx src/modules/billing/public-checkout/PublicCheckoutMobileLayout.test.tsx`
- `npm run test -- src/modules/billing/public-checkout/PublicCheckoutPageV2.test.tsx src/modules/billing/public-checkout/PublicCheckoutFrictionCharacterization.test.tsx src/modules/billing/PublicEventCheckoutEntryPage.test.tsx src/modules/billing/public-checkout/mappers/checkoutResponseAdapters.test.ts`
- `npx playwright test e2e/public-checkout-mobile.spec.ts e2e/public-checkout-pix.spec.ts e2e/public-checkout-manual-login.spec.ts e2e/public-checkout-card.spec.ts`
- `npm run type-check`
- `php artisan test --filter=PublicEventCheckoutTest`

## Backend: O Que Realmente Precisa Mudar

### Obrigatorio nesta rodada

- nada estrutural

### Ja validado e suficiente

- contrato publico aceita `event.event_date` com horario
- `starts_at` ja persiste o horario
- retomada apos login ja existe do lado do backend
- o problema de `Ja tenho conta` e de persistencia/orquestracao no frontend

### Opcional apenas se quisermos endurecer o contrato

- adicionar um teste de request mais especifico para `datetime-local`
- documentar explicitamente no contrato que `event_date` aceita data com hora

## Bateria TDD Recomendada

## Frontend de caracterizacao

- `PublicCheckoutFrictionCharacterization.test.tsx`

## Frontend de implementacao

- `PublicCheckoutPageV2.test.tsx`
- `PublicCheckoutMobileLayout.test.tsx`
- novo bloco sugerido:
  - `PublicCheckoutManualResumeFlow.test.tsx`
  - `BuyerIdentityFields.test.tsx`
  - `BuyerEventStep.test.tsx`
  - `MobileCheckoutFooter.test.tsx`

## Backend

- `PublicEventCheckoutTest.php`

## E2E a atualizar depois dos fixes

- `apps/web/e2e/public-checkout-resume.spec.ts`
- `apps/web/e2e/public-checkout-mobile.spec.ts`
- opcional:
  - `apps/web/e2e/public-checkout-manual-login.spec.ts`

## Sequencia Recomendada De PRs

### PR 1

- continuity fix do `Ja tenho conta`
- status: concluido
- cobertura: unitarios, feature e E2E

### PR 2

- mascara e teclado do WhatsApp
- testes do campo e regressao do pre-check
- status: concluido

### PR 3

- data e hora do evento
- ajuste de copy e payload
- confirmacao final do contrato backend
- status: concluido

### PR 4

- mobile CTA-first
- compactacao visual mobile
- regressao E2E
- status: concluido

### PR 5

- polish visual mobile da primeira dobra
- resumo compacto do pacote escolhido
- ajustes finos de copy dos campos
- status: concluido

## Criterios De Aceite

- `Ja tenho conta` salva draft e retoma sem parecer reload vazio
- o pacote escolhido continua preservado na retomada
- o WhatsApp e guiado visualmente como telefone
- `event_date` passa a carregar horario sem perder compatibilidade
- o sticky mobile exibe a acao principal da etapa
- a primeira dobra mobile fica mais curta e direta
- o pacote escolhido fica visivel em resumo compacto apos a selecao
- backend continua verde sem necessidade de refatoracao do contrato

## Decisao Recomendada

A proxima rodada nao deve reabrir o checkout inteiro.

Ela deve focar em quatro ajustes cirurgicos:

1. continuidade real do `Ja tenho conta`
2. telefone com mascara e teclado corretos
3. `data e hora` no campo opcional do evento
4. mobile com CTA-first

Leitura final:

- o problema principal agora e de jornada e input UX
- o backend ja esta maduro o suficiente para essa rodada
- o plano correto e melhorar comportamento e interface antes de mexer no contrato
