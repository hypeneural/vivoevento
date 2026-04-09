# Checkout Publico De Evento - Analise Atual E Plano De UX

Data: `2026-04-08`

Plano detalhado complementar:

- `docs/architecture/public-event-checkout-v2-implementation-plan-2026-04-08.md`

Validacao complementar executada em:

- `2026-04-09`

## Objetivo

Este documento analisa o estado atual da jornada publica em `/checkout/evento`, cruzando:

- frontend atual
- backend atual
- contrato de API real
- pontos que vazam linguagem tecnica para o comprador final
- o que precisa ser feito para transformar a tela em uma jornada clara para leigo

O foco principal desta rodada e:

- simplificar a linguagem
- separar a jornada em etapas reais
- reduzir atrito para noiva, aniversariante, formanda ou cliente final

## Resumo Executivo

Hoje o checkout publico funciona tecnicamente, mas nao se comporta como uma jornada comercial madura para usuario final.

O principal problema nao e integracao. O principal problema e UX:

- a pagina fala como tela interna de engenharia
- a narrativa esta centrada em contrato tecnico, webhook, tokenizacao e status local
- a tela mistura escolha de pacote, cadastro, dados do evento e pagamento dentro de um formulario unico
- os "cards de etapas" atuais nao sao um wizard real; sao apenas resumo visual
- o usuario so descobre conflito de conta existente depois de preencher e tentar pagar

Conclusao pratica:

- o backend atual ja sustenta bem uma primeira versao em etapas
- boa parte da melhoria pode ser feita so no frontend
- a etapa 2 nao deve ser "cadastro ou login" para todo mundo; ela deve ser "seus dados"
- o pre-check silencioso de identidade ja foi implementado no backend e ja esta plugado na V2 publica
- a V2 ja tem wizard, pagamento real e estado proprio de acompanhamento e agora e o fluxo padrao em `/checkout/evento`
- o payload semantico do backend ja entrou no contrato publico
- `checkoutResponseAdapters.ts`, hardening mobile/storage e suite E2E ja entraram no codigo
- deep link por pacote e metadados comerciais nativos do catalogo ja entraram
- a rota publica agora fixa a V2 e nao expoe mais rollback por `legacy=1`

## Validacao Complementar De `2026-04-09`

### Bateria executada

Backend:

- `php artisan test --filter=PublicEventCheckoutPayloadContractTest`
- `php artisan test --filter=PublicEventCheckoutTest`
- `php artisan test --filter=EventPackageCatalogTest`
- `php artisan test --filter=BillingTest`

Frontend:

- `npm run test -- PublicEventCheckoutPage.test.tsx public-event-packages.service.test.ts PlansPage.test.tsx`
- `npm run test -- src/modules/billing/public-checkout`
- `npm run type-check`
- `npx playwright test`

Resultado:

- tudo verde na bateria rodada

Leitura:

- o checkout atual esta funcional no contrato atual
- os principais problemas seguem sendo UX, linguagem e composicao da tela
- a V2 nao esta corrigindo um fluxo quebrado; ela esta reduzindo friccao e acoplamento

### Achados confirmados apos os testes e leitura do codigo

- a retomada apos login para Pix esta validada no frontend e no backend
- o rascunho seguro nao restaura PAN/CVV
- o rascunho seguro agora prefere `sessionStorage` e remove o draft legado do `localStorage` quando a sessao atual consegue sustentar a retomada
- a tela atual ainda depende fortemente de `gateway_status`
- o formulario atual usa `form.watch(...)` em massa no page root
- a V2 ja moveu parte desse acoplamento para adapters e view-models dedicados
- o checkout de cartao atual opera com `installments: 1`
- `apps/web/playwright.config.ts` ja esta funcional, o runner `@playwright/test` esta instalado e a pasta `apps/web/e2e` ja cobre os cinco cenarios criticos da jornada

Implicacao:

- o plano V2 continua correto
- os pontos de hardening mobile, storage e E2E deixaram de ser hipotese e passaram a estar validados localmente
- a parte de UX precisa nascer junto com uma refatoracao estrutural do formulario, nao apenas com nova copy

### Estado de execucao ja validado localmente

Ja entrou no codigo:

- `POST /api/v1/public/checkout-identity/check`
- `PublicEventCheckoutEntryPage`
- V2 na propria rota `/checkout/evento`
- wizard com `step` na URL
- etapa 1 comercial de pacote
- etapa 2 `Seus dados` com extras colapsados
- `IdentityAssistInline` conectado ao pre-check
- etapa 3 de pagamento com `Pix` default e cartao sob demanda
- estado pos-submit/status fora do accordion principal
- `checkoutStatusViewModel` isolando a semantica exibida ao comprador
- payload semantico em `checkout.summary` vindo do backend
- `PublicEventCheckoutPayloadContractTest.php` cobrindo Pix pendente, pago e cartao falho
- `checkoutResponseAdapters.ts` reduzindo logica no page orchestrator
- `MobileCheckoutFooter` + `Drawer` secundario no mobile
- `useCheckoutStatusPolling.test.tsx`, `PublicCheckoutMobileLayout.test.tsx` e `CheckoutSidebar.test.tsx`
- `apps/web/e2e/public-checkout-pix.spec.ts`
- `apps/web/e2e/public-checkout-resume.spec.ts`
- `apps/web/e2e/public-checkout-card.spec.ts`
- `apps/web/e2e/public-checkout-mobile.spec.ts`
- `apps/web/e2e/public-checkout-status-resume.spec.ts`
- `checkout_marketing` no payload de pacote
- `EventPackageCheckoutMarketingService` no backend
- deep link por `?package=<slug|code|id>`
- V2 como default em `/checkout/evento`
- remocao do escape hatch `legacy=1` da entry route publica em `2026-04-09`

Validacao real adicional:

- `php artisan billing:pagarme:homologate --scenario=pix-cancel --poll-attempts=1 --poll-sleep-ms=500` executado com sucesso em `2026-04-09`
- a conta local ainda tem chaves de homologacao configuradas
- a conta da Pagar.me continua listando hooks para `https://webhooks-local.eventovivo.com.br/api/v1/webhooks/billing/pagarme`

Observacao complementar:

- o fluxo legado ainda existe no repositorio para caracterizacao e regressao isolada
- ele nao e mais roteado pela entrada publica do checkout

## Escopo Da Analise

Arquivos principais inspecionados:

- `apps/web/src/modules/billing/PublicEventCheckoutPage.tsx`
- `apps/web/src/modules/billing/PublicEventCheckoutPage.test.tsx`
- `apps/web/src/modules/billing/services/public-event-packages.service.ts`
- `apps/api/app/Modules/Billing/Http/Controllers/PublicEventCheckoutController.php`
- `apps/api/app/Modules/Billing/Http/Requests/StorePublicEventCheckoutRequest.php`
- `apps/api/app/Modules/Billing/Actions/CreatePublicEventCheckoutAction.php`
- `apps/api/app/Modules/Billing/Services/PublicJourneyIdentityService.php`
- `apps/api/app/Modules/Billing/Services/PublicEventCheckoutPayloadBuilder.php`

## Estado Atual Do Frontend

### 1. Hero e copy inicial estao tecnicos demais

Hoje a abertura da pagina usa mensagens como:

- "Checkout publico com status local, webhook assincrono e tokenizacao oficial"
- "Esta tela usa o contrato do modulo Billing..."

Problema:

- isso informa a equipe tecnica, nao o comprador
- para usuario final, esses termos nao ajudam a decidir nem a completar a compra
- a tela comeca explicando implementacao, nao beneficio ou proximo passo

Impacto:

- queda de confianca
- sensacao de sistema "complicado"
- experiencia com cara de homologacao, nao de compra real

### 2. A pagina tem secoes, mas nao tem etapas reais

Visualmente existe uma tentativa de dividir em:

- etapa 1 conta e evento
- etapa 2 pagamento guiado
- etapa 3 acompanhamento local

Mas na pratica:

- o usuario continua vendo tudo de uma vez
- nao existe bloqueio natural entre passos
- nao existe CTA de "continuar"
- nao existe progresso real da jornada

Conclusao:

- os cards atuais sao resumos
- nao sao um stepper nem um wizard

### 3. O formulario mistura responsabilidades demais

Hoje a mesma tela exibe ao mesmo tempo:

- escolha do pacote
- nome, WhatsApp, email, organizacao
- dados do evento
- selecao de Pix ou cartao
- campos completos de pagador e endereco de cobranca
- area de status do pedido

Problema:

- o usuario leigo nao entende qual e a ordem certa
- a pagina parece grande e pesada antes mesmo da compra comecar
- o cartao expande uma area densa demais cedo demais

### 4. A linguagem de pagamento esta em tom de implementacao

Exemplos atuais no front:

- "cartao tokenizado"
- "tokenizacao segura"
- "backend recebe apenas card_token"
- "conciliacao por webhook"
- "status local"
- "gateway status"
- "UUID local"
- "billing address"
- "PSP v5"

Problema:

- o usuario nao precisa saber isso para pagar
- isso deve ficar escondido na implementacao, testes e observabilidade

O que o usuario realmente quer saber:

- qual o valor
- o que o pacote inclui
- se o pagamento e seguro
- quando o evento sera liberado
- como acompanhar se o Pix foi pago

### 5. O estado de "conta existente" aparece tarde

Hoje o conflito de identidade acontece assim:

1. usuario escolhe pacote
2. preenche dados
3. tenta criar checkout
4. backend rejeita se WhatsApp ou email ja existem
5. frontend mostra "Fazer login para continuar"

Problema:

- a decisao "cadastro ou login" nao aparece cedo
- o usuario pode preencher bastante coisa antes de descobrir que precisa entrar

### 6. O pacote ainda e apresentado de forma pouco comercial

Hoje os cards de pacote mostram:

- nome
- descricao
- preco
- um badge simples como `Wall ativo` ou `Wall off`

Problema:

- o pacote nao e apresentado em linguagem de valor
- faltam beneficios claros
- o badge atual ainda fala em capacidade interna do produto, nao em resultado percebido

### 7. O checkout mostra status operacional demais

No aside e no estado de pagamento aparecem informacoes como:

- UUID local
- gateway status
- timeline local
- atualizando status local

Parte disso e util internamente, mas para o comprador final o ideal e traduzir para:

- pedido criado
- aguardando pagamento
- pagamento aprovado
- pagamento nao confirmado
- expira em X minutos

### 8. A tela de cartao esta boa tecnicamente, mas extensa para leigo

Pontos fortes atuais:

- valida CPF
- valida telefone
- valida endereco
- valida cartao
- tokeniza no navegador

Problema de UX:

- a tela mostra muita informacao de uma vez
- o checklist e correto, mas a copy ainda esta orientada a tokenizacao e consistencia tecnica
- a area do cartao tem linguagem mais adequada para time de plataforma do que para comprador final

### 9. O componente atual esta monolitico demais para uma jornada comercial

Hoje `PublicEventCheckoutPage.tsx` concentra no mesmo arquivo:

- schema
- validacoes
- mascaras
- checklist de cartao
- deteccao de conflito de identidade
- retomada de sessao
- polling de checkout
- montagem de payload
- renderizacao completa da UI

Problema:

- o problema nao esta so na copy
- a tela tambem esta acoplada demais
- isso dificulta transformar a experiencia em passos lineares e mais humanos

Leitura:

- para a V2 ficar realmente boa, nao basta reescrever texto
- e preciso quebrar a tela em blocos de jornada

### 10. O visual atual parece premium tecnico, nao compra simples

O dark mode atual, com brilho, gradientes e atmosfera mais "sistema", funciona para areas operacionais.

Para compra rapida por publico leigo, ele passa mais:

- plataforma
- homologacao
- interface tecnica

do que:

- clareza
- acolhimento
- seguranca simples

Direcao recomendada:

- fundo claro
- cards brancos
- contraste limpo
- um tom principal de destaque
- menos brilho, menos efeito e menos peso visual

## Estado Atual Do Backend

### 1. O backend atual ja fecha a compra avulsa ponta a ponta

O modulo de billing ja expoe:

- `GET /api/v1/public/event-packages`
- `POST /api/v1/public/event-checkouts`
- `GET /api/v1/public/event-checkouts/{uuid}`
- `POST /api/v1/public/event-checkouts/{uuid}/confirm`

Na pratica, isso ja permite:

- listar pacotes
- criar conta nova se o contato nao existir
- reutilizar conta autenticada existente
- criar evento
- criar billing order
- processar Pix ou cartao
- expor status de acompanhamento

### 2. O endpoint principal ainda e "tudo em uma chamada"

`CreatePublicEventCheckoutAction` hoje faz quase toda a jornada em uma transacao:

- normaliza telefone e email
- verifica identidade
- cria usuario se necessario
- cria organizacao `direct_customer`
- vincula membership com `partner-owner`
- cria evento
- cria billing order
- cria item do pedido
- dispara checkout no gateway
- devolve payload consolidado

Leitura:

- isso simplifica o backend
- isso nao impede uma UX por etapas no frontend
- mas hoje ainda empurra a descoberta de conta existente para tarde demais, porque tudo acontece no submit final

### 3. A conta do cliente direto ainda reaproveita a trilha de parceiro

Hoje, para conta nova no checkout publico:

- a organizacao e `direct_customer`
- o membership e criado como `partner-owner`
- a role global atribuida tambem e `partner-owner`

Isso funciona tecnicamente.

Mas semanticamente:

- cliente final nao e parceiro
- a trilha de permissao ainda usa o papel de dono de organizacao parceira

Nao e o foco imediato da UX da tela, mas e um tema estrutural para depois.

### 4. O backend ja suporta retomada apos login

Se a conta ja existe:

- `PublicJourneyIdentityService` rejeita o uso do mesmo telefone/email por conta anonima
- o frontend salva um draft seguro
- o usuario faz login
- a jornada pode ser retomada em `/checkout/evento?resume=auth`

Isso e uma base boa.

O problema atual nao e capacidade tecnica.

O problema e que isso ainda aparece tarde demais na jornada.

### 5. O payload publico devolve mais informacao tecnica do que a UI comercial precisa

`PublicEventCheckoutPayloadBuilder` devolve:

- provider
- gateway ids
- gateway status
- UUID
- meta
- notificacoes operacionais

Isso e bom para observabilidade e reconciliacao.

Mas a UI publica nao deveria expor a maior parte disso diretamente ao comprador final.

### 6. O backend agora ja tem um endpoint proprio para pre-check silencioso de identidade

Agora, para descobrir se o usuario pode seguir normalmente ou se ja existe cadastro, existe:

- `POST /api/v1/public/checkout-identity/check`

Esse endpoint entrega a base da etapa `Seus dados` da V2 com retorno do tipo:

- `new_account`
- `login_suggested`
- `authenticated_match`
- `authenticated_mismatch`

Leitura:

- esse gap de backend ja foi fechado
- o valor agora migra para integrar o pagamento e o pos-submit na V2 sem reintroduzir acoplamento

## O Que Ja Da Para Melhorar So No Frontend

Sem mudar a API principal, ja da para melhorar bastante:

### 1. Trocar o hero inteiro

Em vez de explicar implementacao, abrir com algo como:

- titulo: `Escolha seu pacote e pague do jeito que preferir`
- subtitulo: `Monte seu evento, cadastre seus dados e finalize por Pix ou cartao em poucos passos.`

### 2. Transformar a pagina em wizard real

Proposta de etapas visiveis e progressivas:

1. Escolha o pacote
2. Seus dados
3. Pagamento

Observacao:

- os dados do evento devem entrar no passo 2, junto com os dados do comprador
- isso reduz a sensacao de cinco formularios em uma pagina so
- a autenticacao nao deve ser apresentada como etapa universal
- login entra apenas como assistencia quando houver conta existente

### 3. Esconder o bloco de pagamento ate o usuario concluir os dados basicos

Fluxo desejado:

- primeiro escolhe pacote
- depois informa seus dados e os dados basicos do evento
- so entao a tela mostra Pix/cartao

### 4. Reescrever toda a copy em linguagem de usuario final

Exemplos:

- atual: `Pix gera QR Code imediatamente e continua conciliando pelo status local.`
- proposto: `Ao escolher Pix, voce recebe o QR Code na hora para pagar.`

- atual: `Tokenizacao direta na Pagar.me e backend recebendo apenas card_token.`
- proposto: `Seu cartao e processado com seguranca e os dados sensiveis nao ficam salvos na plataforma.`

- atual: `Atualizar status local`
- proposto: `Atualizar pagamento`

- atual: `Gateway status`
- proposto: `Status do pagamento`

- atual: `UUID local`
- proposto: remover da interface publica

### 5. Trocar termos internos por nomes comerciais

Trocas recomendadas:

| Atual | Proposto |
|---|---|
| Checkout publico | Compra do seu evento |
| Billing | pagamento |
| tokenizacao | processamento seguro do cartao |
| webhook | confirmacao automatica |
| polling | atualizacao automatica |
| status local | status do pagamento |
| gateway status | status do pagamento |
| UUID local | remover da UI publica |
| conciliacao | confirmacao do pagamento |
| organization_name | nome do casal, empresa ou responsavel |

### 6. Reduzir densidade visual da area de cartao

Manter:

- dados necessarios
- validacoes
- seguranca

Remover da tela principal:

- explicacoes sobre `PAN`
- `PSP v5`
- linguagem de payload
- foco em "backend recebe..."

### 7. Reescrever a confirmacao pos-criacao

Hoje o estado de onboarding ainda fala em:

- painel
- evento criado
- pedido

Melhor abordagem:

- `Pedido criado`
- `Agora falta concluir o pagamento para liberar seu pacote`
- `Se preferir, voce pode acompanhar por esta mesma pagina`

### 8. Tratar login como assistencia, nao como etapa para todos

Mesmo sem endpoint novo, da para melhorar bastante no front:

- manter CTA secundario discreto na etapa 2: `Ja tenho conta`
- esse CTA leva para login com `returnTo=/checkout/evento?resume=auth`
- o texto da etapa 2 explica:
  - `Se este contato ja tiver cadastro, voce pode entrar para continuar mais rapido`

Mas a estrategia principal deve ser:

- usuario preenche `Seus dados`
- pre-check roda em silencio
- so se houver cadastro existente a tela sugere login inline

Isso reduz friccao mental cedo demais e deixa a jornada mais alinhada ao objetivo principal da compra.

### 9. Enxugar os campos do checkout inicial

Na etapa 2, os campos principais devem ser:

- nome do responsavel
- WhatsApp
- email
- nome do evento
- tipo do evento

Campos secundarios devem ficar como opcionais ou colapsados em `Adicionar mais detalhes`:

- data
- cidade
- nome da organizacao
- descricao do evento

Leitura:

- `descricao do evento` e campo de configuracao, nao de compra
- `organization_name` nao deve ficar na frente; quando aparecer, deve ter rotulo humano
- o backend atual ja aceita varios desses campos vazios ou opcionais

### 10. Tornar Pix o fluxo dominante

Direcao recomendada:

- Pix selecionado por padrao
- CTA principal simples: `Gerar meu Pix`
- explicacao curta: `Voce recebe o QR Code na hora`

Cartao:

- aparece apenas quando o usuario escolhe cartao
- campos extras so entram nesse momento
- a copy fala em seguranca e rapidez, nao em arquitetura de processamento

### 11. Simplificar a mensagem de seguranca do cartao

Na UI publica, o cartao deve comunicar apenas:

- `Pagamento seguro por cartao`
- `Seus dados sao protegidos`
- `Confirmacao rapida apos a analise do pagamento`

O que deve sair da UI publica:

- `PSP v5`
- `PAN`
- `card_token`
- `backend recebe apenas`
- foco em payload e tokenizacao como argumento principal

## O Que Precisa De Ajuste No Backend Para Ficar Muito Bom

### 1. Endpoint de pre-check silencioso de identidade

Este e o principal ganho de produto no backend.

Objetivo:

- detectar cedo se o contato ja tem conta
- permitir que a etapa 2 continue sendo "seus dados"
- sugerir login apenas quando isso realmente ajudar

Contrato sugerido:

- entrada:
  - `whatsapp`
  - `email` opcional
- saida:
  - `identity_status`: `new_account | login_suggested | authenticated_match`
  - `message`
  - `login_url` opcional

Regras recomendadas:

- debounce no frontend
- resposta neutra
- sem enumeracao agressiva de contas
- rate limit
- sem bloquear a digitacao

Mensagem sugerida:

- `Ja encontramos seu cadastro. Entrar agora costuma ser mais rapido para continuar sua compra.`

### 2. Resposta publica mais orientada a UX

Hoje o payload publico e orientado a operacao.

Seria melhor separar:

- payload interno rico
- payload publico simplificado

Exemplo de campos publicos desejados:

- `order_status_label`
- `payment_status_label`
- `payment_status_description`
- `next_action`
- `expires_in_seconds`
- `is_waiting_payment`
- `can_retry`

### 3. Opcional: metadados de marketing por pacote

Hoje o pacote tem:

- nome
- descricao
- modulo
- limites

Para uma tela comercial melhor, valeria ter:

- destaque comercial curto
- bullets de beneficio
- selo recomendado
- subtitulo por perfil de uso

Isso pode ficar no backend ou inicialmente ser derivado no frontend.

## Proposta De Jornada V2

### Etapa 1 - Escolha seu pacote

Objetivo:

- fazer o usuario entender qual opcao contratar

Deve mostrar:

- nome do pacote
- preco
- 3 a 5 beneficios em linguagem leiga
- selo `mais escolhido` quando fizer sentido

Nao deve mostrar:

- `wall on/off`
- linguagem de modulo interno

CTA:

- `Escolher este pacote`

Resultado:

- avanca para etapa 2

### Etapa 2 - Seus dados

Objetivo:

- identificar quem esta comprando
- coletar dados basicos do evento
- manter o checkout fluido, sem forcar o usuario a pensar em conta cedo demais

Conteudo:

- nome
- WhatsApp
- email
- nome do evento
- tipo do evento

CTAs:

- primario: `Continuar para pagamento`
- secundario: `Ja tenho conta`

Comportamento:

- se o usuario clicar em `Ja tenho conta`, vai para login e volta
- se continuar normalmente, o sistema segue para a etapa 3
- data, cidade, nome da organizacao e descricao ficam em `Adicionar mais detalhes`
- se o pre-check detectar conta existente, a tela mostra inline:
  - `Ja encontramos seu cadastro. Entre para continuar mais rapido.`
- se o usuario ignorar e seguir, o submit final continua protegido do mesmo jeito

Versao ideal:

- antes de ir para etapa 3, chamar pre-check de identidade

### Etapa 3 - Pagamento

Objetivo:

- escolher meio de pagamento e concluir

Subpassos:

- escolher `Pix` ou `Cartao`
- se `Pix`:
  - mostrar resumo final
  - CTA `Gerar meu Pix`
- se `Cartao`:
  - mostrar campos necessarios
  - copy simples de seguranca
  - CTA `Finalizar com cartao`

Texto ideal:

- `Pix`: `Voce recebe o QR Code na hora`
- `Cartao`: `Pagamento seguro por cartao de credito`

### Pos-pagamento

Estados esperados:

- pedido criado
- aguardando pagamento
- pagamento aprovado
- pagamento nao confirmado

CTA principal:

- `Acompanhar pagamento`

CTA secundario:

- `Abrir meu evento`

Sem expor:

- UUID
- termos de gateway
- reconciliacao
- webhook
- polling
- billing

## Arquitetura Recomendada Da Nova Tela

### Shell principal

`PublicCheckoutShell`

Responsavel por:

- layout macro
- coluna do wizard
- coluna lateral de resumo e confianca
- tema visual claro e comercial

### Hero

`CheckoutHeroSimple`

Conteudo:

- titulo curto de compra
- subtitulo objetivo
- trust row discreta:
  - pagamento seguro
  - confirmacao automatica
  - suporte no WhatsApp

### Stepper real

`CheckoutStepper`

Etapas:

1. Pacote
2. Seus dados
3. Pagamento

Direcao:

- accordion linear
- etapa ativa aberta
- etapas anteriores recolhidas com resumo curto

### Etapa de pacote

`PackageSelectionStep`

Subcomponentes:

- `PackageCard`
- `PackageBenefitList`
- `PackageRecommendedBadge`

Objetivo:

- vender a escolha
- traduzir `modules`, `feature_map` e `limits` em beneficios comerciais

### Etapa de dados

`BuyerEventStep`

Subcomponentes:

- `BuyerIdentityFields`
- `EventBasicsFields`
- `IdentityAssistInline`

Objetivo:

- manter formulario curto
- antecipar descoberta de conta existente sem travar o fluxo

### Etapa de pagamento

`PaymentStep`

Subcomponentes:

- `PaymentMethodTabs`
- `PixPaymentPanel`
- `CreditCardPaymentPanel`
- `PaymentTrustNote`

Objetivo:

- progressive disclosure
- Pix como default
- cartao revelado apenas quando escolhido

### Lateral

`CheckoutSidebar`

Subcomponentes:

- `OrderSummaryCard`
- `NextStepCard`
- `TrustSignalsCard`
- `PaymentStatusCard`

Objetivo:

- responder sempre:
  - o que escolhi
  - o que falta agora
  - se esta seguro

## Hooks Recomendados

- `usePublicCheckoutWizard()`
  - controla etapa atual
  - bloqueios de avancar
  - resumo das etapas

- `useCheckoutIdentityPrecheck()`
  - debounce
  - cancelamento de requisicao
  - estados `idle/checking/new_account/login_suggested/authenticated_match`

- `useCheckoutResumeDraft()`
  - centraliza a retomada segura ja existente

- `useCheckoutStatusPolling()`
  - encapsula o polling do pedido criado

- `useCommercialPackageCopy()`
  - deriva bullets comerciais a partir do pacote tecnico

## Estrutura Recomendada De Arquivos

```txt
modules/billing/public-checkout/
  PublicCheckoutPageV2.tsx
  components/
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
    PaymentStatusCard.tsx
    TrustSignalsCard.tsx
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

## O Que O Backend Atual Ja Suporta Sem Refatoracao Grande

O backend atual ja suporta a maior parte da V2 porque:

- o catalogo publico existe
- o checkout final ja aceita pacote + comprador + evento + pagamento
- a retomada apos login ja existe
- o acompanhamento por `checkout.uuid` ja existe
- Pix e cartao ja estao operacionais

Ou seja:

- a V2 de etapas nao exige refatorar o core do billing
- ela exige principalmente reorganizar a experiencia do frontend

## O Que Ainda Nao Fecha So Com Frontend

O pre-check de identidade ja foi resolvido.

Se quisermos uma experiencia comercial mais forte, ainda falta:

- decidir se o metadata comercial continua em `feature_key` ou ganha schema proprio

## Recomendacao De Implementacao

### Fase 1 - Backend de UX minimo

Prioridade alta:

- concluida localmente
- `POST /api/v1/public/checkout-identity/check` implementado
- request validator, action, resource e rate limit entregues
- reutilizacao de `PublicJourneyIdentityService` entregue

Resultado esperado:

- a etapa 2 deixa de descobrir conta existente tarde demais
- esse resultado ja foi validado nos testes

### Fase 2 - Frontend V2

Prioridade alta:

- reescrever hero e copy
- migrar para visual mais claro e menos tecnico
- remover jargao tecnico da interface publica
- transformar a pagina em wizard real de 3 etapas
- esconder pagamento ate concluir etapa de dados
- incluir CTA `Ja tenho conta` como apoio, nao como fluxo principal
- dividir o monolito atual em componentes e hooks dedicados
- simplificar area de Pix e cartao
- simplificar bloco de status

Resultado esperado:

- a jornada passa a parecer compra curta e clara

Status local:

- majoritariamente concluida
- hero, shell, wizard, etapa 1, etapa 2, etapa 3 e acompanhamento ja estao em `/checkout/evento`
- a V2 ja cobre o contrato real de Pix, cartao, retomada de Pix apos login e status pos-submit
- o backend semantico ja cobre labels, descricao, proximo passo e expiracao no contrato publico
- a V2 ja tem `checkoutResponseAdapters.ts`, footer mobile, `sessionStorage` first para draft seguro e bateria E2E verde
- o catalogo ja entrega `checkout_marketing`
- o deep link por pacote ja funciona por `?package=<slug|code|id>`
- a V2 ja virou padrao e a entry route publica deixou de aceitar rollback por `legacy=1`

### Fase 3 - Backend semantico e comercializacao

Prioridade media:

- deep link por pacote
- metadados comerciais do pacote
- payload publico simplificado para UI
- eventualmente historico autenticado de pedidos avulsos pendentes

## Criticos Para Aceite Da Nova Tela

Para considerar a nova versao boa para usuario final:

- a primeira dobra da pagina nao pode conter termos como `Billing`, `webhook`, `polling`, `tokenizacao`, `gateway`, `UUID`
- o fluxo precisa ficar claramente dividido em etapas reais
- a etapa 2 nao pode parecer cadastro de sistema
- o pagamento nao deve aparecer pesado antes do usuario concluir o basico
- Pix precisa ser o caminho mais rapido
- o caminho de login precisa aparecer apenas quando ajudar
- a tela precisa comunicar beneficios, valor e proximo passo
- o status de pagamento precisa ser traduzido para linguagem de comprador
- a lateral precisa sempre responder:
  - o que foi escolhido
  - o que falta fazer agora
  - se o pagamento esta seguro

## Conclusao

O checkout publico atual ja esta tecnicamente pronto para vender.

Mas ele ainda fala a lingua da plataforma, nao a lingua do comprador.

O melhor proximo passo nao e reescrever o billing.

O melhor proximo passo e:

1. transformar `/checkout/evento` em wizard real
2. remover copy tecnica
3. fazer a etapa 2 ser `Seus dados`, nao `cadastro ou login`
4. sugerir login apenas quando o contato ja existir
5. deixar Pix e cartao como etapa final, simples e confiavel

Atualizacao de execucao:

- os itens `1`, `2`, `3`, `4` e `5` ja estao implementados na V2 padrao
- o bloco que resta agora ficou menor:
  - decidir quando apagar o codigo legado que nao e mais roteado
  - decidir se o metadata comercial merece schema dedicado no backend

Em resumo:

- a V2 nao deve vender "tecnologia segura"
- a V2 deve vender facilidade com confianca
- a tecnologia continua por tras; na frente entra uma compra curta, clara e leve
