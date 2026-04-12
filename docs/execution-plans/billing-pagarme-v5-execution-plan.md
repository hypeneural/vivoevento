# Billing Pagar.me v5 Execution Plan

## Objetivo

Este documento transforma o estudo arquitetural da integracao Pagar.me v5 em
ordem real de execucao, com foco em:

- sequenciamento claro de backend, frontend, webhook e operacao;
- checkpoints de contexto para nao perder o fio da arquitetura;
- TTD obrigatorio em toda alteracao de codigo;
- homologacao local baseada nos simuladores oficiais da Pagar.me somente depois
  de a bateria automatizada estar verde.

Referencias primarias:

- [docs/architecture/billing-pagarme-v5-single-event-checkout.md](./billing-pagarme-v5-single-event-checkout.md)
- [docs/architecture/billing-admin-customer-onboarding-discovery.md](./billing-admin-customer-onboarding-discovery.md)
- [docs/execution-plans/billing-admin-customer-onboarding-execution-plan.md](./billing-admin-customer-onboarding-execution-plan.md)
- `https://docs.pagar.me/reference/criar-pedido-2`
- `https://docs.pagar.me/reference/pix-2`
- `https://docs.pagar.me/reference/cart%C3%A3o-de-cr%C3%A9dito-1`
- `https://docs.pagar.me/reference/criar-token-cart%C3%A3o-1`
- `https://docs.pagar.me/reference/eventos-de-webhook-1`
- `https://docs.pagar.me/reference/exemplo-de-webhook-1`
- `https://docs.pagar.me/docs/simulador-psp`
- `https://docs.pagar.me/docs/simulador-pix`
- `https://docs.pagar.me/docs/simulador-de-cart%C3%A3o-de-cr%C3%A9dito`

Este plano existe para responder 6 perguntas de execucao:

1. o que precisa mudar primeiro no repo sem quebrar o checkout atual;
2. em que ordem entram schema, provider, webhook, Pix e cartao;
3. quais testes automatizados precisam nascer antes de cada bloco de codigo;
4. quais validacoes manuais de homologacao entram so depois do TTD verde;
5. quais gates precisam estar verdes para considerar a integracao pronta;
6. o que fica explicitamente fora da fase 1.

## Como usar este plano

Regra simples:

- a doc de arquitetura continua sendo a fonte de diagnostico, contrato e
  direcao tecnica;
- este arquivo vira a fonte de backlog, sequenciamento e criterios de aceite;
- a jornada administrativa de onboarding comercial sem `Event` fica em docs
  separadas e nao deve virar desvio deste plano;
- se surgir duvida de payload, status, evento, tokenizacao ou simulador, a
  referencia final e sempre a documentacao oficial v5 da Pagar.me;
- nenhuma task de codigo fecha sem TTD;
- nenhuma homologacao manual substitui suite automatizada;
- toda mudanca de contrato deve refletir em teste e doc na mesma rodada.

Cada task abaixo aponta para:

- objetivo;
- referencia na arquitetura;
- estado atual;
- subtasks;
- checklist de TTD;
- dependencias;
- criterio de aceite;
- arquivos e areas mais provaveis de impacto.

## Status atual da execucao

Estado consolidado em `2026-04-05`:

- [x] `M0-T1` fonte de verdade documental congelada
- [x] `M0-T2` decisao de cartao fechada para fase 1 com `card_token` no frontend
- [x] `M0-T3` matriz de TTD e homologacao consolidada na documentacao
- [x] `M1-T1` contrato publico do checkout expandido para `payer`, `pix` e `credit_card`
- [x] `M1-T2` schema e snapshots operacionais expandidos
- [x] `M1-T3` `GET /public/event-checkouts/{uuid}` lendo apenas estado local
- [x] `M2-T1` configuracao e registro do provider `pagarme`
- [x] `M2-T2` `PagarmeClient` sobre `Illuminate\Http\Client`
- [x] `M2-T3` normalizacao de customer e payload de `/orders`
- [x] `M2-T4` `PagarmeBillingGateway` funcional para Pix e cartao
- [x] `M3-T1` normalizacao de webhook materializada no parsing dedicado do provider
- [x] `M3-T2` webhook assicrono em fila `billing`
- [x] `M3-T3` reconciliacao local para `paid`, `failed`, `refunded` e `chargeback`
- [x] `M3-T4` autenticacao operacional do webhook via Basic Auth + idempotencia
- [x] `M4-T1` criacao de pedido Pix
- [x] `M4-T2` payload local de status do Pix com `qr_code`, `qr_code_url` e `expires_at`
- [x] `M4-T3` regressao automatizada minima de Pix
- [x] `M5-T1` base oficial de tokenizacao no frontend
- [x] `M5-T2` checkout de cartao com resposta sincrona `paid` e `failed`
- [x] `M5-T3` primeira pagina publica real do checkout entregue no `apps/web`, com Pix, cartao, polling local e UX de erro
- [x] `M5-T4` regressao automatizada de cartao cobre sucesso, falha no `POST /orders`, falha antes da criacao do pedido, snapshot `processing` e reconciliacao `chargeback`
- [x] `M6-T1` cancelamento/estorno administrativo por `charge_id` refletido localmente
- [x] `M6-T2` troubleshooting interno por refresh autenticado com `GET /orders/{id}` e `GET /charges/{id}`
- [x] `M6-T3` retry operacional seguro do mesmo `BillingOrder`, com lock local e reaproveitamento da mesma `Idempotency-Key`
- [x] `M7-T1` `.env` local preparado com credenciais de homologacao e Basic Auth do webhook
- [x] `M7-T2` homologacao real consolidada validou Pix sucesso/falha, cartao PSP aprovado, recusa do emissor, antifraude, retry real de idempotencia, replay real de webhook com hostname fixo, cancelamento Pix, estorno de cartao e o dossie direto dos simuladores `0036`, `0044` e `0069`
- [x] `M7-T3` evidencia operacional consolidada em reports JSON versionaveis por caminho conhecido, ids reais de homologacao, replay real do hook `hook_NQnjE65KiRIyVeKA`, retry real de idempotencia e dossie final da divergencia entre o simulador de cartao Gateway e o fluxo PSP desta conta
- [x] `M8-T1` onboarding publico refinado com CTA para o painel, ramo de login para identidade existente e retomada segura apos autenticacao, com Pix retomado automaticamente e cartao exigindo novo preenchimento dos campos sensiveis
- [x] `M8-T2` UX de Pix refinada com contador visual, copia do codigo, CTA do QR e timeline local, mantendo polling e retomada por estado local salvo
- [x] `M8-T3` UX de cartao refinada com mascaras, agrupamento em blocos, preview seguro, hint de bandeira, checklist visual, prefill assistido do telefone do pagador e validacao mais guiada antes da tokenizacao
- [x] `M8-T4` notificacoes de status ao cliente via Z-API, deduplicadas pela maquina de estados local e persistidas em `billing_order_notifications`, com `send-text` em todos os status e `send-button-pix` adicional em `pix_generated` quando a instancia resolvida usa `zapi`

Revalidacao completa executada no fim da rodada:

- [x] `cd apps/api && php artisan test` verde
- [x] `cd apps/web && npm run test` verde
- [x] `cd apps/web && npm run type-check` verde
- [x] `php artisan billing:pagarme:homologate --scenario=all --poll-attempts=2 --poll-sleep-ms=1000` verde, com report agregado em:
  - `apps/api/storage/app/pagarme-homologation/20260405-210058-all.json`

Proximo foco recomendado:

1. decidir a politica de produto para `chargeback` antes de abrir uma segunda rodada de notificacoes ativas;
2. avaliar uma segunda rodada de UX para transformar o checkout em jornada visual ainda mais orientada a conversao, sem reabrir o contrato tecnico atual;
3. se o time quiser escalar a homologacao, transformar o comando `billing:pagarme:homologate` em trilha operacional padrao com arquivo de evidencias anexado ao release checklist.

## Regra de TTD obrigatoria

Neste plano, `TTD` significa:

1. escrever ou expandir o teste automatizado antes do codigo;
2. validar que o teste falha pela razao certa;
3. implementar o menor codigo necessario para passar;
4. rodar a suite alvo da task;
5. rodar a regressao do modulo;
6. atualizar doc e contratos se o comportamento mudou;
7. so entao marcar a task como concluida.

Checklist padrao de TTD para qualquer task de codigo:

- [ ] existe ao menos um teste novo ou expandido antes da implementacao;
- [ ] o teste cobre o contrato principal da task;
- [ ] o teste falhou antes da implementacao;
- [ ] a implementacao passou no teste alvo;
- [ ] a regressao do modulo `Billing` permaneceu verde;
- [ ] o frontend, quando impactado, passou em `vitest` e `type-check`;
- [ ] a doc de arquitetura ou este plano foram atualizados se houve mudanca de
      contrato.

## Premissas fechadas

Estas decisoes devem ser tratadas como travadas para a fase 1:

- escopo apenas de compra unica de `event_package`;
- meios de pagamento `pix` e `credit_card`;
- `POST /orders` e o centro da venda;
- o checkout e transacional:
  - cria pedido idempotente
  - persiste snapshot do gateway
  - reconcilia por webhook
  - expoe status local
- `GET /api/v1/public/event-checkouts/{uuid}` deve ler somente estado local;
- `POST /api/v1/public/event-checkouts/{uuid}/confirm` fica como fallback do
  provider `manual`, nao como fluxo feliz da Pagar.me;
- a compra unica de `event_package` nao tem cancelamento pelo usuario na UI publica;
- cancelamento e estorno dessa compra acontecem internamente no backoffice ou no painel da Pagar.me e chegam ao `eventovivo` por webhook;
- webhook entra pela rota publica do billing, responde rapido e processa em
  fila `billing`;
- backend nao recebe PAN/CVV/nome/vencimento de cartao;
- integracao HTTP deve usar `Illuminate\Http\Client` com client proprio;
- split, recorrencia, wallet de cartao salvo e antifraude avancado ficam fora
  desta rodada;
- a homologacao local usa as credenciais de teste e os simuladores oficiais;
- a fase 1 fica fechada em `card_token`, gerado no frontend por
  `POST /tokens?appId=<PUBLIC_KEY>`;
- `card_id` so entra se a conta ou o fluxo operacional mudarem em rodada
  futura.

## Artefatos que o repositorio precisa ganhar

Antes de chamar a integracao de pronta, estes artefatos devem existir no repo:

| Caminho alvo | Papel |
| --- | --- |
| `docs/architecture/billing-pagarme-v5-single-event-checkout.md` | Fonte de estudo e contrato |
| `docs/execution-plans/billing-pagarme-v5-execution-plan.md` | Fonte de backlog e ordem de execucao |
| `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeClient.php` | Client HTTP da Pagar.me |
| `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeBillingGateway.php` | Provider real do modulo |
| `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeOrderPayloadFactory.php` | Montagem do payload `/orders` |
| `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeCustomerNormalizer.php` | Normalizacao de `customer` |
| `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeWebhookNormalizer.php` ou parsing dedicado no provider | Normalizacao de webhook |
| `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeStatusMapper.php` | Mapa de status externo -> interno |
| `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeHomologationService.php` | Probes diretos de homologacao e dossie operacional |
| `apps/api/app/Modules/Billing/Console/Commands/PagarmeHomologationCommand.php` | Runner para gerar evidencias JSON reais da conta |
| `apps/api/app/Modules/Billing/Jobs/ProcessBillingWebhookJob.php` | Processamento assincrono de webhook |
| migrations em `apps/api/database/migrations/` | Campos novos de order/payment/event |
| testes unitarios em `apps/api/tests/Unit/Billing/Pagarme/` | Cobertura das pecas puras |
| testes feature em `apps/api/tests/Feature/Billing/` | Cobertura ponta a ponta da API |
| testes frontend em `apps/web/src/**` | Cobertura do contrato de checkout/status |

## Regra de sequenciamento

Executar na ordem abaixo:

1. fechar fonte de verdade e decisions bloqueantes;
2. expandir contrato e persistencia local;
3. criar client e provider isolados;
4. fechar webhook e maquina de estados local;
5. entregar Pix ponta a ponta;
6. entregar cartao transparente ponta a ponta;
7. endurecer operacao, cancelamento, refund e troubleshooting;
8. rodar homologacao local guiada por simuladores oficiais.

Motivo:

- tentar integrar a API antes de expandir schema local gera conciliacao pobre;
- tentar fechar o front antes do status local gera polling cascata no gateway;
- tentar homologar antes do webhook assicrono mascara um problema central da
  integracao;
- tentar cartao antes de fechar a estrategia oficial de tokenizacao abre risco
  de trafegar dado sensivel no backend.

## M0 - Fonte unica, decisions bloqueantes e TTD baseline

Objetivo:

- travar as decisoes que podem bloquear ou desviar a implementacao.

### TASK M0-T1 - Congelar a fonte de verdade documental

Referencia na arquitetura:

- `Objetivo`
- `Veredito executivo`
- `Referencias confirmadas`

Estado atual:

- a doc de arquitetura ja existe;
- ainda nao existe um execution plan dedicado para a integracao Pagar.me;
- o modulo `Billing` ainda referencia apenas o provider `manual`.

Subtasks:

1. tratar `billing-pagarme-v5-single-event-checkout.md` como fonte de contrato;
2. tratar este arquivo como backlog de execucao;
3. deixar explicito que, em caso de duvida, vale a documentacao oficial v5;
4. registrar claramente o que fica fora da fase 1.

Checklist de TTD:

- nao se aplica para esta task de doc.

Criterio de aceite:

- o time consegue responder onde buscar contrato, sequencia e arbitragem de
  duvidas sem depender de conversa paralela.

Dependencias:

- nenhuma.

Arquivos e areas provaveis:

- `docs/architecture/billing-pagarme-v5-single-event-checkout.md`
- `docs/execution-plans/billing-pagarme-v5-execution-plan.md`

### TASK M0-T2 - Fechar a estrategia oficial de cartao da conta

Referencia na arquitetura:

- `3.4 Regras oficiais para cartao e tokenizacao`
- `17. Decisoes abertas`

Estado atual:

- a decisao operacional ja foi fechada em homologacao;
- o frontend segue com `card_token` via `POST /tokens?appId=<PUBLIC_KEY>`;
- no backend, para a conta PSP atual, o `eventovivo` converte o token em
  `card_id` por `POST /customers/{customer_id}/cards` e cria o pedido com
  `customer_id + card_id`.

Subtasks:

1. registrar a decisao fechada no doc de arquitetura e neste plano;
2. manter o frontend em `card_token`, sem trafegar PAN/CVV no backend;
3. manter o detalhe interno `card_id` restrito ao provider PSP do backend;
4. explicitar qual dominio de homologacao sera liberado na dashboard.

Checklist de TTD:

- nao se aplica a esta task de decisao;
- a task so fecha com um adendo documental claro.

Criterio de aceite:

- M5 pode comecar sem ambiguidade sobre tokenizacao e formato aceito pelo
  backend.

Dependencias:

- M0-T1.

Arquivos e areas provaveis:

- `docs/architecture/billing-pagarme-v5-single-event-checkout.md`
- `apps/web/`
- dashboard da conta Pagar.me

### TASK M0-T3 - Fechar a matriz de testes e evidencia obrigatoria

Referencia na arquitetura:

- `13. Suite minima de testes`
- `15. Bateria recomendada de validacao local`
- `16. Ordem recomendada de execucao dos testes`

Estado atual:

- a doc de arquitetura ja tem bateria manual e suite minima;
- ainda falta transformar isso em regra de execucao por milestone.

Subtasks:

1. listar suites obrigatorias por milestone;
2. listar evidencias obrigatorias por homologacao:
   - request
   - response
   - estado no banco
   - payload de webhook
   - screenshot ou evidencia de UX
3. definir nome dos futuros testes unitarios e feature;
4. deixar claro que homologacao manual so entra com TTD verde.

Checklist de TTD:

- nao se aplica para esta task de planejamento.

Criterio de aceite:

- toda task de codigo ja nasce com expectativa de teste e evidencia.

Dependencias:

- M0-T1.

## M1 - Contrato publico e persistencia local

Objetivo:

- fazer o dominio local falar a lingua da integracao antes de tocar no provider
  externo.

### TASK M1-T1 - Expandir o contrato do checkout publico

Referencia na arquitetura:

- `6. Contrato recomendado do checkout publico`
- `10. Frontend necessario`

Estado atual:

- `StorePublicEventCheckoutRequest` ainda so aceita dados simplificados;
- `PublicEventCheckoutResource` e `PublicEventCheckoutPayloadBuilder` ainda nao
  expoem contrato de Pix/cartao.

Subtasks:

1. expandir `StorePublicEventCheckoutRequest` para receber:
   - `payer.*`
   - `payment.method`
   - `payment.pix.*`
   - `payment.credit_card.*`
2. adicionar validacao condicional em `after()`;
3. expandir `PublicEventCheckoutResource`;
4. expandir `PublicEventCheckoutPayloadBuilder`;
5. alinhar `apps/web/src/lib/api-types.ts` com o novo contrato.

Checklist de TTD:

- [ ] expandir `PublicEventCheckoutTest` com casos de validacao para Pix e
      cartao antes da implementacao;
- [ ] criar ou expandir teste cobrindo proibicao de `card_token` em Pix;
- [ ] criar ou expandir teste cobrindo obrigatoriedade de `payer` e
      `billing_address` no cartao;
- [ ] rodar `cd apps/api && php artisan test --filter=PublicEventCheckoutTest`;
- [ ] rodar `cd apps/web && npm run type-check`.

Criterio de aceite:

- a API aceita o shape real do checkout Pagar.me e devolve um contrato local
  coerente para o frontend.

Dependencias:

- M0-T1
- M0-T2

Arquivos e areas provaveis:

- `apps/api/app/Modules/Billing/Http/Requests/StorePublicEventCheckoutRequest.php`
- `apps/api/app/Modules/Billing/Http/Resources/PublicEventCheckoutResource.php`
- `apps/api/app/Modules/Billing/Services/PublicEventCheckoutPayloadBuilder.php`
- `apps/web/src/lib/api-types.ts`
- `apps/api/tests/Feature/Billing/PublicEventCheckoutTest.php`

### TASK M1-T2 - Expandir schema e snapshots operacionais

Referencia na arquitetura:

- `8. Persistencia local recomendada`

Estado atual:

- `BillingOrder`, `Payment` e `BillingGatewayEvent` ainda nao guardam todos os
  campos operacionais necessarios para conciliacao e suporte.

Subtasks:

1. criar migration para adicionar em `billing_orders`:
   - `idempotency_key`
   - `payment_method`
   - `gateway_order_id`
   - `gateway_charge_id`
   - `gateway_transaction_id`
   - `gateway_status`
   - `customer_snapshot_json`
   - `gateway_response_json`
   - `expires_at`
   - `paid_at`
   - `failed_at`
   - `canceled_at`
   - `refunded_at`
2. criar migration para adicionar em `payments`:
   - `payment_method`
   - `gateway_order_id`
   - `gateway_charge_id`
   - `gateway_transaction_id`
   - `gateway_status`
   - `last_transaction_json`
   - `gateway_response_json`
   - `acquirer_return_code`
   - `acquirer_message`
   - `qr_code`
   - `qr_code_url`
   - `expires_at`
   - `paid_at`
   - `failed_at`
   - `canceled_at`
   - `refunded_at`
3. criar migration para adicionar em `billing_gateway_events`:
   - `gateway_charge_id`
   - `gateway_transaction_id`
   - `occurred_at`
4. atualizar models, casts e fillable.

Checklist de TTD:

- [ ] criar teste feature ou unitario que falha ao procurar os novos campos no
      payload persistido;
- [ ] expandir `BillingWebhookTest` ou `PublicEventCheckoutTest` para garantir
      persistencia dos campos operacionais;
- [ ] rodar `cd apps/api && php artisan test --filter=BillingWebhookTest`;
- [ ] rodar `cd apps/api && php artisan test --filter=PublicEventCheckoutTest`.

Criterio de aceite:

- o banco local fica pronto para operar `orders`, `charges`, `last_transaction`
  e erros do adquirente sem depender de JSON solto apenas.

Dependencias:

- M1-T1.

Arquivos e areas provaveis:

- `apps/api/database/migrations/`
- `apps/api/app/Modules/Billing/Models/BillingOrder.php`
- `apps/api/app/Modules/Billing/Models/Payment.php`
- `apps/api/app/Modules/Billing/Models/BillingGatewayEvent.php`
- `apps/api/tests/Feature/Billing/PublicEventCheckoutTest.php`
- `apps/api/tests/Feature/Billing/BillingWebhookTest.php`

### TASK M1-T3 - Criar o endpoint local de status e rebaixar o `confirm`

Referencia na arquitetura:

- `5.3 Rotas locais recomendadas`
- `10.4 Pagina de status separada`

Estado atual:

- ainda nao existe `GET /api/v1/public/event-checkouts/{uuid}`;
- `confirm` ainda e parte do fluxo feliz do provider `manual`.

Subtasks:

1. criar rota `GET /api/v1/public/event-checkouts/{billingOrder:uuid}`;
2. criar action/controller/resource para leitura do estado local;
3. garantir que essa leitura nao consulta a Pagar.me;
4. documentar que `confirm` permanece apenas para `manual`;
5. deixar o payload de status pronto para Pix e cartao.

Checklist de TTD:

- [ ] criar teste feature falhando para `GET /public/event-checkouts/{uuid}`;
- [ ] garantir por teste que a leitura do status nao dispara client externo;
- [ ] expandir `PublicEventCheckoutTest` com leitura de status local;
- [ ] rodar `cd apps/api && php artisan test --filter=PublicEventCheckoutTest`.

Criterio de aceite:

- o frontend tem uma fonte de verdade local para polling e retorno posterior do
  usuario.

Dependencias:

- M1-T1
- M1-T2

Arquivos e areas provaveis:

- `apps/api/app/Modules/Billing/routes/api.php`
- `apps/api/app/Modules/Billing/Http/Controllers/PublicEventCheckoutController.php`
- `apps/api/app/Modules/Billing/Actions/`
- `apps/api/app/Modules/Billing/Services/PublicEventCheckoutPayloadBuilder.php`
- `apps/api/tests/Feature/Billing/PublicEventCheckoutTest.php`

## M2 - Fundacao do provider Pagar.me

Objetivo:

- introduzir o provider real sem acoplar controller e dominio a HTTP externo.

### TASK M2-T1 - Fechar contrato de configuracao e registrar o provider

Referencia na arquitetura:

- `11. Configuracao e ambiente`
- `4. Bibliotecas e estrategia de integracao`

Estado atual:

- `config/billing.php` registra apenas `manual`;
- `services.php` ainda nao tem bloco dedicado a `pagarme`.

Subtasks:

1. adicionar configuracao `services.pagarme`;
2. revisar `.env.example` do backend;
3. registrar `pagarme` em `config/billing.php`;
4. garantir que `BillingGatewayManager` resolve o provider novo;
5. manter compatibilidade com `manual`.

Checklist de TTD:

- [ ] criar teste falhando para resolver provider `pagarme` pelo manager;
- [ ] validar config minima necessaria por teste;
- [ ] rodar `cd apps/api && php artisan test --filter=BillingTest`.

Criterio de aceite:

- a aplicacao consegue resolver o provider real por configuracao, sem quebrar o
  provider manual.

Dependencias:

- M1-T2.

Arquivos e areas provaveis:

- `apps/api/config/services.php`
- `apps/api/config/billing.php`
- `apps/api/app/Modules/Billing/Services/BillingGatewayManager.php`
- `apps/api/tests/Feature/Billing/BillingTest.php`

### TASK M2-T2 - Criar `PagarmeClient` sobre `Illuminate\Http\Client`

Referencia na arquitetura:

- `4.2 O que usar como fundacao`
- `5.2 Classes recomendadas`

Estado atual:

- nao existe client HTTP dedicado para a Pagar.me.

Subtasks:

1. criar `PagarmeClient` com:
   - `createOrder()`
   - `getOrder()`
   - `getCharge()`
   - `cancelCharge()`
   - `captureCharge()`
2. aplicar:
   - `withBasicAuth`
   - `timeout`
   - `connectTimeout`
   - `retry`
3. suportar header `Idempotency-Key` em criacao;
4. padronizar tratamento de erros HTTP;
5. impedir uso acidental do backend para `POST /tokens`.

Checklist de TTD:

- [ ] criar `PagarmeClientTest` com `Http::fake()` antes da implementacao;
- [ ] cobrir Basic Auth, base URL, timeout e `Idempotency-Key`;
- [ ] cobrir parsing basico de erro HTTP;
- [ ] rodar `cd apps/api && php artisan test --filter=PagarmeClientTest`.

Criterio de aceite:

- a comunicacao com a Pagar.me fica isolada e testavel sem chamadas reais.

Dependencias:

- M2-T1.

### TASK M2-T3 - Criar normalizadores e factory de payload

Referencia na arquitetura:

- `5.2 Classes recomendadas`
- `7. Mapeamento do payload local para POST /orders`

Estado atual:

- o modulo ainda nao tem pecas dedicadas para montar `customer`, `payments`,
  `metadata` e mapear status.

Subtasks:

1. criar `PagarmeCustomerNormalizer`;
2. criar `PagarmeOrderPayloadFactory`;
3. criar `PagarmeStatusMapper`;
4. garantir suporte a Pix e cartao no payload factory;
5. centralizar `metadata` local de conciliacao;
6. preparar `idempotency_key` para uso pela camada de gateway.

Checklist de TTD:

- [ ] criar `PagarmeCustomerNormalizerTest`;
- [ ] criar `PagarmeOrderPayloadFactoryTest`;
- [ ] criar `PagarmeStatusMapperTest`;
- [ ] cobrir Pix, cartao, telefone, endereco e metadata;
- [ ] rodar:
  - `cd apps/api && php artisan test --filter=PagarmeCustomerNormalizerTest`
  - `cd apps/api && php artisan test --filter=PagarmeOrderPayloadFactoryTest`
  - `cd apps/api && php artisan test --filter=PagarmeStatusMapperTest`

Criterio de aceite:

- a montagem de payload e a leitura de status ficam desacopladas do gateway e
  do controller.

Dependencias:

- M1-T1
- M1-T2
- M2-T2

Arquivos e areas provaveis:

- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeCustomerNormalizer.php`
- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeOrderPayloadFactory.php`
- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeStatusMapper.php`
- `apps/api/tests/Unit/Billing/Pagarme/`

### TASK M2-T4 - Implementar `PagarmeBillingGateway`

Referencia na arquitetura:

- `5.1 Fluxo macro recomendado`
- `5.2 Classes recomendadas`

Estado atual:

- `ManualBillingGateway` e o unico provider real.

Subtasks:

1. criar `PagarmeBillingGateway` implementando `BillingGatewayInterface`;
2. ligar `CreateEventPackageGatewayCheckoutAction` ao novo provider;
3. reaproveitar `PublicEventCheckoutPayloadBuilder` para resposta local;
4. persistir dados basicos de `order`, `charge` e `status` no retorno sincronico;
5. manter `manual` intacto.

Checklist de TTD:

- [ ] expandir `BillingTest` ou `PublicEventCheckoutTest` para falhar ao
      resolver e usar o provider `pagarme`;
- [ ] criar teste de integracao com `Http::fake()` cobrindo criacao de pedido;
- [ ] rodar:
  - `cd apps/api && php artisan test --filter=BillingTest`
  - `cd apps/api && php artisan test --filter=PublicEventCheckoutTest`

Criterio de aceite:

- o modulo `Billing` cria checkout real pela interface existente, sem vazar
  detalhes HTTP para fora do provider.

Dependencias:

- M2-T1
- M2-T2
- M2-T3

## M3 - Webhook assincrono e reconciliacao local

Objetivo:

- fechar o circuito de conciliacao que torna Pix e cartao confiaveis em
  producao.

### TASK M3-T1 - Criar `PagarmeWebhookNormalizer` e enriquecer o evento bruto

Referencia na arquitetura:

- `9. Webhook alvo no eventovivo`

Estado atual:

- `ProcessBillingWebhookAction` conhece apenas tipos internos do provider
  manual;
- `billing_gateway_events` ja existe, mas ainda pode crescer em ids
  operacionais.

Subtasks:

1. criar `PagarmeWebhookNormalizer`;
2. mapear `order.*` e `charge.*` para tipos internos;
3. extrair:
   - `gateway_order_id`
   - `gateway_charge_id`
   - `gateway_transaction_id`
   - `occurred_at`
4. persistir payload bruto e campos indexaveis no evento.

Checklist de TTD:

- [ ] criar `PagarmeWebhookNormalizerTest`;
- [ ] expandir `BillingWebhookTest` com payloads `order.paid`,
      `order.payment_failed`, `charge.refunded` e `charge.chargedback`;
- [ ] rodar:
  - `cd apps/api && php artisan test --filter=PagarmeWebhookNormalizerTest`
  - `cd apps/api && php artisan test --filter=BillingWebhookTest`

Criterio de aceite:

- o webhook bruto da Pagar.me passa a ser compreendido e indexado sem logica
  espalhada no controller.

Dependencias:

- M1-T2
- M2-T3

### TASK M3-T2 - Mover o processamento para fila `billing`

Referencia na arquitetura:

- `9.1 Fluxo recomendado`
- `9.3 afterCommit e job unico`

Estado atual:

- o webhook do provider atual ainda processa inline.

Subtasks:

1. criar `ProcessBillingWebhookJob`;
2. fazer o controller persistir o evento e responder `200` rapido;
3. despachar o job depois do commit;
4. usar `ShouldBeUnique` ou travamento equivalente por evento;
5. garantir queue `billing`.

Checklist de TTD:

- [ ] expandir `BillingWebhookTest` para provar resposta rapida e job enfileirado;
- [ ] criar teste para nao processar o mesmo evento em paralelo;
- [ ] rodar `cd apps/api && php artisan test --filter=BillingWebhookTest`.

Criterio de aceite:

- webhook deixa de depender de processamento inline e fica seguro para Pix em
  producao.

Dependencias:

- M3-T1.

### TASK M3-T3 - Expandir a maquina de estados local do billing

Referencia na arquitetura:

- `9.2 Mapeamento externo -> interno`
- `9.4 Regra de verdade por metodo de pagamento`

Estado atual:

- o fluxo local ainda cobre basicamente `payment.paid` e `checkout.canceled`.

Subtasks:

1. expandir `ProcessBillingWebhookAction` para:
   - `payment.failed`
   - `payment.refunded`
   - `payment.partially_refunded`
   - `payment.chargeback`
2. criar actions dedicadas quando fizer sentido:
   - `FailBillingOrderAction`
   - `RefundBillingOrderAction`
   - `RegisterBillingChargebackAction`
3. atualizar `BillingOrder`, `Payment` e `Invoice` de forma idempotente;
4. garantir que a ativacao do evento nao duplica.

Checklist de TTD:

- [ ] expandir `BillingWebhookTest` para cada transicao principal;
- [ ] cobrir webhook repetido sem duplicidade de ativacao;
- [ ] rodar:
  - `cd apps/api && php artisan test --filter=BillingWebhookTest`
  - `cd apps/api && php artisan test --filter=BillingTest`

Criterio de aceite:

- o modulo passa a refletir no banco as transicoes reais de Pix, falha,
  refund e chargeback.

Dependencias:

- M3-T2.

### TASK M3-T4 - Endurecer idempotencia e observabilidade do webhook

Referencia na arquitetura:

- `3.6 O que nao esta claro nas paginas publicas v5`
- `9.1 Fluxo recomendado`

Estado atual:

- a trilha basica de evento existe, mas ainda falta endurecimento operacional
  especifico do provider real.

Subtasks:

1. garantir chave unica por `provider_key + event_key`;
2. registrar `webhook_id`, `type`, `gateway_order_id` e `gateway_charge_id` em
   logs estruturados;
3. garantir que nenhuma resposta de erro vaze dado sensivel;
4. registrar claramente que assinatura HMAC nao foi assumida sem fonte oficial.

Checklist de TTD:

- [ ] criar teste de idempotencia por evento repetido;
- [ ] criar teste para payload malformado ou provider invalido;
- [ ] rodar `cd apps/api && php artisan test --filter=BillingWebhookTest`.

Criterio de aceite:

- o webhook fica auditavel, idempotente e pragmaticamente seguro para a fase 1.

Dependencias:

- M3-T1
- M3-T2

## M4 - Pix ponta a ponta

Objetivo:

- entregar o primeiro metodo de pagamento real em producao.

### TASK M4-T1 - Implementar criacao de pedido Pix

Referencia na arquitetura:

- `7.3 Pedido Pix`
- `10.2 Fluxo Pix recomendado`

Estado atual:

- o checkout publico ainda nao cria pedido real em gateway externo.

Subtasks:

1. mapear `payment.method = pix` para o payload `/orders`;
2. enviar `Idempotency-Key`;
3. persistir:
   - `gateway_order_id`
   - `gateway_charge_id`
   - `gateway_status`
   - `qr_code`
   - `qr_code_url`
   - `expires_at`
4. devolver esses campos na resposta local.

Checklist de TTD:

- [ ] expandir `PublicEventCheckoutTest` com caso Pix real usando `Http::fake()`;
- [ ] provar persistencia de QR Code e expiracao;
- [ ] provar que o status inicial local fica `pending`;
- [ ] rodar `cd apps/api && php artisan test --filter=PublicEventCheckoutTest`.

Criterio de aceite:

- o frontend recebe tudo que precisa para UX imediata do Pix sem depender de
  consulta externa.

Dependencias:

- M2-T4
- M3-T3

### TASK M4-T2 - Fechar a UX local de status do Pix

Referencia na arquitetura:

- `10.4 Pagina de status separada`

Estado atual:

- o repo ainda nao tem uma jornada publica completa de status do checkout Pix.

Subtasks:

1. criar hook/servico de polling do endpoint local;
2. exibir QR Code, expiracao e status;
3. tratar refresh e retorno do usuario para a pagina;
4. parar polling quando o pedido sair de `pending`;
5. impedir qualquer polling direto na Pagar.me.

Checklist de TTD:

- [ ] criar testes frontend para o estado `pending`;
- [ ] criar testes frontend para transicao `pending -> paid`;
- [ ] garantir por teste que a consulta e feita no endpoint local da API;
- [ ] rodar:
  - `cd apps/web && npm run test`
  - `cd apps/web && npm run type-check`

Criterio de aceite:

- o fluxo Pix sobrevive a refresh, retorno e eventual consistency sem depender
  do gateway no navegador.

Dependencias:

- M1-T3
- M4-T1

### TASK M4-T3 - Fechar a regressao automatizada de Pix

Referencia na arquitetura:

- `13. Suite minima de testes`
- `15.3 Bateria Pix`

Estado atual:

- o modulo ainda nao tem cobertura automatizada real para Pix.

Subtasks:

1. garantir casos automatizados para:
   - criacao Pix com sucesso
   - webhook `order.paid`
   - webhook repetido
   - falha via webhook
   - leitura de status local
2. revisar factories e fixtures para payloads Pix;
3. consolidar nomes de testes e comandos de validacao.

Checklist de TTD:

- [ ] rodar todas as suites alvo de Pix;
- [ ] rodar `cd apps/api && php artisan test --filter=BillingWebhookTest`;
- [ ] rodar `cd apps/api && php artisan test --filter=PublicEventCheckoutTest`;
- [ ] rodar `cd apps/web && npm run test`;
- [ ] rodar `cd apps/web && npm run type-check`.

Criterio de aceite:

- o metodo Pix fica protegido por cobertura automatizada do request ao webhook.

Dependencias:

- M4-T1
- M4-T2

## M5 - Cartao transparente ponta a ponta

Objetivo:

- entregar cartao de credito sem trafegar dados sensiveis pelo backend.

### TASK M5-T1 - Fechar a tokenizacao do frontend

Referencia na arquitetura:

- `3.4 Regras oficiais para cartao e tokenizacao`
- `10.3 Fluxo cartao recomendado`

Estado atual:

- a estrategia final de tokenizacao ainda depende da decisao de M0-T2.

Subtasks:

1. implementar o adapter do frontend para:
   - chamada direta a `/tokens?appId=<PUBLIC_KEY>`
   - ou `tokenizecard.js`, se essa for a decisao fechada
2. garantir que apenas `card_token` ou `card_id` saia do frontend;
3. tratar expiracao do token e erro de tokenizacao;
4. impedir submit ao backend com PAN aberto.

Checklist de TTD:

- [ ] criar testes frontend para payload enviado ao backend;
- [ ] garantir por teste que PAN/CVV nao entram no request da API local;
- [ ] criar teste para erro de tokenizacao;
- [ ] rodar:
  - `cd apps/web && npm run test`
  - `cd apps/web && npm run type-check`

Criterio de aceite:

- o frontend fecha o checkout transparente sem vazar dado sensivel ao backend.

Dependencias:

- M0-T2
- M1-T1

### TASK M5-T2 - Implementar criacao de pedido cartao

Referencia na arquitetura:

- `7.4 Pedido cartao`
- `15.4 Bateria cartao com Simulador PSP`
- `15.5 Bateria cartao com Simulador de Cartao de Credito`

Estado atual:

- o provider ainda nao trata `credit_card` real.

Subtasks:

1. mapear `payment.method = credit_card` no payload factory;
2. enviar:
   - `installments`
   - `statement_descriptor`
   - `operation_type`
   - `card_token` ou `card_id`
   - `billing_address`
3. persistir:
   - `gateway_status`
   - `acquirer_message`
   - `acquirer_return_code`
   - `last_transaction_json`
4. refletir status sincronico local sem dispensar o webhook.

Checklist de TTD:

- [ ] expandir `PublicEventCheckoutTest` com sucesso e falha de cartao via
      `Http::fake()`;
- [ ] cobrir validacao de `billing_address` e `installments`;
- [ ] rodar `cd apps/api && php artisan test --filter=PublicEventCheckoutTest`.

Criterio de aceite:

- o backend fecha checkout de cartao com tokenizacao oficial e persistencia
  operacional completa.

Dependencias:

- M2-T4
- M3-T3
- M5-T1

### TASK M5-T3 - Tratar estados intermediarios e UX de erro do cartao

Referencia na arquitetura:

- `15.5 Bateria cartao com Simulador de Cartao de Credito`

Estado atual:

- implementado em `2026-04-05` via
  `apps/web/src/modules/billing/PublicEventCheckoutPage.tsx`;
- a rota publica `GET /checkout/evento` ja existe no `apps/web`;
- a pagina cobre Pix, cartao, tokenizacao oficial, falha imediata,
  `processing -> paid` e polling sempre local.

Subtasks:

1. [x] tratar sucesso imediato;
2. [x] tratar falha imediata;
3. [x] tratar `processing` ou `pending` seguido de mudanca por webhook;
4. [x] exibir erro amigavel com `acquirer_message` quando houver;
5. [x] manter polling local ou refresh de status quando aplicavel.

Checklist de TTD:

- [x] criar testes frontend para sucesso;
- [x] criar testes frontend para falha;
- [x] criar testes frontend para estado intermediario;
- [x] rodar:
  - `cd apps/web && npm run test`
  - `cd apps/web && npm run type-check`

Criterio de aceite:

- a UX do cartao suporta sucesso, erro e transicoes tardias sem heuristica
  fraca no navegador.

Dependencias:

- M1-T3
- M5-T2

### TASK M5-T4 - Fechar a regressao automatizada de cartao

Referencia na arquitetura:

- `13. Suite minima de testes`
- `15.4 Bateria cartao com Simulador PSP`
- `15.5 Bateria cartao com Simulador de Cartao de Credito`

Estado atual:

- ainda nao existe cobertura automatizada real para cartao no modulo.

Subtasks:

1. consolidar cenarios automatizados para:
   - sucesso
   - falha
   - estado intermediario
   - webhook `charge.payment_failed`
   - webhook `charge.chargedback`
2. revisar fixtures e builders de payload de cartao;
3. consolidar comandos de validacao da rodada.

Checklist de TTD:

- [ ] rodar `cd apps/api && php artisan test --filter=PublicEventCheckoutTest`;
- [ ] rodar `cd apps/api && php artisan test --filter=BillingWebhookTest`;
- [ ] rodar `cd apps/web && npm run test`;
- [ ] rodar `cd apps/web && npm run type-check`.

Criterio de aceite:

- o metodo cartao fica protegido por cobertura automatizada do checkout ao
  webhook.

Dependencias:

- M5-T2
- M5-T3

## M6 - Operacao, refund, troubleshooting e endurecimento

Objetivo:

- fechar o que suporte e operacao vao precisar no primeiro go-live.

### TASK M6-T1 - Implementar cancelamento ou estorno por `charge_id`

Referencia na arquitetura:

- `3.1 Endpoints externos realmente necessarios`
- `12. Fase 5 - operacao e conciliacao`

Estado atual:

- o plano arquitetural ja pede `DELETE /charges/{charge_id}`, mas o modulo ainda
  nao expoe essa capacidade operacional real.

Subtasks:

1. implementar `cancelCharge()` no client;
2. criar action de cancelamento/refund local;
3. decidir ownership do endpoint administrativo;
4. persistir transicao local e reconciliar por webhook quando vier.

Checklist de TTD:

- [ ] criar teste falhando para cancelamento por `charge_id`;
- [ ] criar teste de transicao local para cancelado/refund;
- [ ] rodar:
  - `cd apps/api && php artisan test --filter=BillingTest`
  - `cd apps/api && php artisan test --filter=BillingWebhookTest`

Criterio de aceite:

- suporte consegue iniciar cancelamento/estorno de forma rastreavel e coerente
  com o estado local.

Dependencias:

- M2-T2
- M3-T3

### TASK M6-T2 - Criar trilha de troubleshooting interno

Referencia na arquitetura:

- `3.1 Endpoints externos realmente necessarios`
- `12. Fase 5 - operacao e conciliacao`

Estado atual:

- implementado em `2026-04-05` via
  `POST /api/v1/billing/orders/{billingOrder:uuid}/refresh`;
- a action interna consulta `GET /orders/{id}` e `GET /charges/{id}` da
  Pagar.me e reconcilia o estado local quando houver drift;
- o endpoint publico `GET /public/event-checkouts/{uuid}` continua 100% local.

Subtasks:

1. [x] criar action interna de refresh por `order_id`;
2. [x] criar action interna de refresh por `charge_id`;
3. [x] garantir que nenhuma rota publica use isso para polling;
4. [x] materializar isso como endpoint admin autenticado;
5. [x] materializar campos operacionais mais recentes no banco local.

Checklist de TTD:

- [x] criar testes para refresh de order e charge;
- [x] provar por teste que o endpoint publico de status nao chama essas actions;
- [x] rodar:
  - `cd apps/api && php artisan test --filter=BillingTest`
  - `cd apps/api && php artisan test --filter=PublicEventCheckoutTest`

Criterio de aceite:

- o time consegue diagnosticar drift entre gateway e banco local sem quebrar a
  regra de polling local.

Dependencias:

- M2-T2
- M3-T3
- M1-T3

### TASK M6-T3 - Endurecer locks, idempotencia e fila do billing

Referencia na arquitetura:

- `3.2 Idempotencia oficial da Pagar.me`
- `9.3 afterCommit e job unico`

Estado atual:

- implementado em `2026-04-05` com
  `POST /api/v1/billing/orders/{billingOrder:uuid}/retry`;
- o retry operacional do mesmo `BillingOrder` agora:
  - reaproveita a mesma `Idempotency-Key` quando ainda nao existe
    `gateway_order_id`
  - bloqueia concorrencia por lock local
  - evita nova chamada externa quando o snapshot do gateway ja existe.

Subtasks:

1. [x] garantir `idempotency_key` estavel por tentativa;
2. [x] garantir que retry local reutiliza a mesma chave quando apropriado;
3. [x] adicionar travas para evitar criacao dupla do mesmo pedido local;
4. [x] revisar queue `billing` para jobs de webhook e conciliacao;
5. [x] documentar quando abrir uma nova tentativa.

Checklist de TTD:

- [x] criar teste falhando para retry com mesma `Idempotency-Key`;
- [x] criar teste para nao gerar duas liquidacoes locais na mesma corrida;
- [x] rodar:
  - `cd apps/api && php artisan test --filter=PublicEventCheckoutTest`
  - `cd apps/api && php artisan test --filter=BillingWebhookTest`
  - `cd apps/api && php artisan test --filter="same idempotency key"`
  - `cd apps/api && php artisan test --filter=BillingTest`
  - `cd apps/api && php artisan test --filter=Pagarme`

Criterio de aceite:

- timeout, retry e webhook duplicado nao geram duplicidade financeira local.

Dependencias:

- M1-T2
- M2-T4
- M3-T2

## M7 - Homologacao local guiada por simuladores oficiais

Objetivo:

- validar o comportamento real da integracao so depois de a cobertura
  automatizada estar verde.

### TASK M7-T1 - Preparar o ambiente local de homologacao

Referencia na arquitetura:

- `14. Homologacao local e .env`

Estado atual:

- a doc ja registra credenciais, `.env` e checklist de webhook local.

Subtasks:

1. configurar `.env` local com as chaves de homologacao;
2. subir API local;
3. abrir tunnel publico temporario;
4. cadastrar o webhook da conta de teste;
5. validar dominio liberado para tokenizacao, se aplicavel.

Checklist de TTD:

- [ ] todas as suites automatizadas dos milestones anteriores estao verdes;
- [ ] `cd apps/api && php artisan test` verde;
- [ ] `cd apps/web && npm run test` verde;
- [ ] `cd apps/web && npm run type-check` verde.

Criterio de aceite:

- o ambiente local esta pronto para receber webhook real e executar a bateria de
  simuladores.

Dependencias:

- M4-T3
- M5-T4
- M6-T3

### TASK M7-T2 - Rodar a bateria oficial de simuladores

Referencia na arquitetura:

- `15. Bateria recomendada de validacao local`
- `16. Ordem recomendada de execucao dos testes`

Estado atual:

- a bateria manual ja foi iniciada com tunnel publico, webhook real e
  simuladores oficiais;
- Pix sucesso/falha e cartao PSP aprovado/recusa ja foram validados;
- os cenarios tardios do simulador de cartao ja foram executados em
  `2026-04-05`, e a divergencia foi consolidada com probe direto salvo em JSON;
- o retry real da mesma `Idempotency-Key` ja foi validado diretamente na v5 em
  `2026-04-05 09:25 BRT`, retornando o mesmo `order_id` mesmo com body alterado;
- o replay real do webhook foi revalidado no mesmo dia, depois da troca para
  `https://webhooks-local.eventovivo.com.br/api/v1/webhooks/billing/pagarme`;
- o hook real `hook_NQnjE65KiRIyVeKA` respondeu `200` para o hostname fixo e a
  reentrega oficial via `POST /hooks/{hook_id}/retry` bateu no mesmo registro
  local sem duplicar `Payment` nem `EventPurchase`;
- o cancelamento Pix e o estorno real de cartao ja foram validados por
  `billing:pagarme:homologate`, com reports em:
  - `apps/api/storage/app/pagarme-homologation/20260405-185658-pix-cancel.json`
  - `apps/api/storage/app/pagarme-homologation/20260405-185659-card-refund.json`
  - `apps/api/storage/app/pagarme-homologation/20260405-185925-simulator-dossier.json`
  - `apps/api/storage/app/pagarme-homologation/20260405-210058-all.json`

Subtasks:

1. [x] rodar Pix sucesso;
2. [x] rodar Pix falha;
3. [x] rodar cartao PSP aprovado;
4. [x] rodar cartao PSP recusa do emissor via `cvv 612`;
5. [x] rodar cartao `processing -> paid` e registrar a divergencia observada;
6. [x] rodar cartao `processing -> failed` e registrar a divergencia observada;
7. [x] rodar cartao antifraude PSP com `document = 11111111111`;
8. [x] rodar `chargeback` e registrar a divergencia observada;
9. [x] rodar cancelamento/estorno em homologacao;
10. [x] reenviar o mesmo webhook real;
11. [x] repetir criacao com a mesma `Idempotency-Key` real.

Checklist de TTD:

- [x] nao aplicar correcoes manuais sem antes converter o problema em teste
      automatizado falhando;
- [x] se um cenario manual falhar, abrir primeiro um teste automatizado que
      reproduza o bug antes de corrigir.

Criterio de aceite:

- a integracao prova, na conta de homologacao, o comportamento real do fluxo
  PSP usado pelo `eventovivo`, incluindo replay, idempotencia, cancelamento Pix
  e estorno de cartao;
- qualquer divergencia frente ao simulador de cartao Gateway fica registrada
  com ids reais, snapshots oficiais e contexto suficiente para follow-up com a
  Pagar.me.

Dependencias:

- M7-T1.

### TASK M7-T3 - Fechar o dossie de evidencia e readiness

Referencia na arquitetura:

- `15.1 Regra geral`
- `16. Ordem recomendada de execucao dos testes`
- `18. Recomendacao final`

Estado atual:

- existe evidencia operacional consolidada com:
  - `billing_order_uuid`, `gateway_order_id` e `gateway_charge_id` reais dos
    cenarios `0036`, `0044`, `0069`, do retry de idempotencia, do Pix
    cancelado e do cartao estornado;
  - `hook_id = hook_NQnjE65KiRIyVeKA` com replay real validado no hostname
    fixo `webhooks-local.eventovivo.com.br`;
  - prova objetiva de que a reentrega real atualiza o mesmo
    `BillingGatewayEvent` sem duplicar `Payment` nem `EventPurchase`;
  - reports JSON gerados pelo comando `billing:pagarme:homologate`.

Subtasks:

1. [x] registrar para cada caso:
   - payload de entrada
   - resposta local
   - estado final no banco
   - payload de webhook
   - resultado de UX
2. [x] registrar os ids reais de:
   - `billing_order_uuid`
   - `gateway_order_id`
   - `gateway_charge_id`
   - `webhook_id`
3. [x] registrar comandos de teste executados;
4. [x] abrir backlog de follow-up para qualquer gap remanescente.

Checklist de TTD:

- [x] suites automatizadas seguem verdes depois das correcoes de homologacao;
- [x] a documentacao final da rodada foi atualizada.

Criterio de aceite:

- existe prova objetiva de que a integracao esta pronta para seguir para staging
  ou primeira subida controlada;
- existe dossie suficiente para explicar por que `0036`, `0044` e `0069`
  divergiram da doc do simulador de cartao Gateway sem confundir isso com bug
  do fluxo PSP validado em homologacao.

Dependencias:

- M7-T2.

## M8 - UX publica, onboarding e notificacoes ao cliente

Objetivo:

- transformar o checkout publico ja funcional em uma jornada comercial de
  produto, com onboarding claro, feedback visual forte e notificacoes ativas ao
  cliente.

### TASK M8-T1 - Refinar o onboarding publico do checkout

Referencia na arquitetura:

- `19.1 O que ja existe no backend e o frontend ainda aproveita pouco`
- `19.2 Melhorias reais de onboarding`

Estado atual:

- o backend ja cria `User`, `Organization`, `Event` e `BillingOrder` na mesma
  transacao;
- a resposta publica ja devolve `token`, `user`, `organization`, `event` e
  `onboarding.next_path`;
- a pagina publica atual ja mostra o bloco de onboarding, o CTA para abrir o
  painel do evento e o ramo de login para identidade ja cadastrada;
- a retomada depois da autenticacao agora usa um rascunho seguro no browser e
  continua a jornada com a conta ja autenticada.

Subtasks:

1. reorganizar a tela em uma jornada visual clara:
   - pacote
   - responsavel
   - evento
   - pagamento
   - acompanhamento
2. expor visualmente que a conta e o evento ja foram criados;
3. exibir CTA real para:
   - acompanhar o pedido
   - entrar no painel do evento
4. tratar identidade ja existente como ramo de jornada:
   - erro contextual
   - CTA para login/OTP
   - retomada do checkout depois da autenticacao
5. usar `onboarding.title`, `onboarding.description` e `onboarding.next_path`
   na composicao da interface.
6. garantir seguranca na retomada:
   - nunca persistir PAN, CVV ou validade do cartao
   - retomar automaticamente apenas Pix
   - restaurar cartao apenas com os campos seguros

Checklist de TTD:

 - [x] expandir `PublicEventCheckoutPage.test.tsx` com o estado de onboarding
      explicito apos criacao do checkout;
 - [x] manter cobertura do CTA visual de continuidade no onboarding;
 - [x] criar teste para erro de identidade existente com CTA de login;
 - [x] criar teste para retomada automatica de Pix apos autenticacao;
 - [x] criar teste para retomada segura de cartao sem persistir campos
      sensiveis;
 - [x] rodar:
   - `cd apps/web && npm run test -- PublicEventCheckoutPage login-navigation`
   - `cd apps/web && npm run type-check`
   - `cd apps/api && php artisan test --filter=PublicEventCheckoutTest`

Criterio de aceite:

- a criacao do checkout deixa de parecer apenas um POST tecnico e passa a
  explicar claramente que conta, evento e pedido ja existem.

Status da rodada atual:

- [x] onboarding visual exposto na pagina publica;
- [x] CTA real para abrir o painel do evento com `next_path`;
- [x] tratamento do caso de identidade existente com CTA de login;
- [x] retomada segura do checkout depois da autenticacao:
  - Pix reenvia automaticamente o checkout com a conta autenticada
  - cartao restaura somente os campos seguros e exige novo preenchimento dos
    dados sensiveis.

Dependencias:

- M5-T3
- M5-T4

### TASK M8-T2 - Refinar a UX de Pix no checkout publico

Referencia na arquitetura:

- `19.3 Melhorias reais de UX para Pix`

Estado atual:

- o QR Code ja aparece;
- o codigo copia e cola ja vem na resposta;
- o polling ja le apenas o estado local;
- nesta rodada, a tela passou a comunicar expiracao, copia do Pix, CTA do QR
  e timeline local de status;
- a retomada continua baseada no `uuid` salvo em `localStorage`, sem polling
  direto no gateway.

Subtasks:

1. adicionar contador visual baseado em `expires_at`;
2. adicionar botao de copiar o codigo Pix;
3. adicionar CTA para abrir ou baixar o QR quando `qr_code_url` existir;
4. adicionar timeline local de status:
   - pedido criado
   - aguardando pagamento
   - pago
   - falhou/expirou
5. reforcar a retomada do checkout salvo quando o usuario volta depois;
6. manter a regra de polling sempre local.

Implementacao atual:

- [x] contador visual de expiracao com base em `expires_at`;
- [x] botao de copiar o codigo Pix com feedback imediato;
- [x] CTA de abrir o `qr_code_url` em nova aba;
- [x] timeline local com `pedido criado`, `aguardando pagamento`, `pago` e
  `falhou/expirou` conforme o estado local;
- [x] retomada continua lendo o checkout salvo localmente;
- [x] polling segue restrito ao endpoint local.

Checklist de TTD:

- [x] expandir `PublicEventCheckoutPage.test.tsx` com contador de expiracao;
- [x] criar teste para copiar o codigo Pix;
- [x] criar teste para transicao `pending_payment -> paid` via polling local;
- [x] rodar:
  - `cd apps/web && npm run test -- PublicEventCheckoutPage`
  - `cd apps/web && npm run type-check`

Criterio de aceite:

- o comprador entende o que fazer com o Pix e consegue retomar a jornada sem
  depender de suporte manual.

Dependencias:

- M4-T2
- M5-T3

### TASK M8-T3 - Refinar a UX de cartao no checkout publico

Referencia na arquitetura:

- `19.4 Melhorias reais de UX para cartao`
- `3.4 Regras oficiais para cartao e tokenizacao`

Estado atual:

- a tokenizacao oficial ja esta funcional;
- a tela publica ja envia apenas `card_token` ao backend;
- nesta rodada, a tela passou a aplicar mascaras, normalizacao, preview seguro,
  hint de bandeira e feedback inline de status.

Subtasks:

1. aplicar mascara e normalizacao para:
   - CPF
   - telefone
   - CEP
   - numero do cartao
   - validade
2. melhorar agrupamento visual entre pagador, endereco e dados do cartao;
3. adicionar hint visual de bandeira quando possivel;
4. melhorar feedback inline de erro e estado `processing`;
5. manter o payload final sem PAN/CVV para o backend.

Implementacao atual:

- [x] mascara visual para CPF, telefone, CEP e numero do cartao;
- [x] normalizacao antes de tokenizar e antes de montar o payload final;
- [x] agrupamento do formulario em `pagador`, `endereco de cobranca` e
  `cartao`;
- [x] preview seguro do cartao com hint visual de bandeira;
- [x] checklist visual de prontidao antes da tokenizacao;
- [x] prefill assistido de `payer_phone` a partir do WhatsApp, sem persistir
  dado sensivel;
- [x] validacao mais guiada para CPF, telefone do pagador, CEP, UF, numero do
  cartao, validade, CVV e nome completo do titular;
- [x] feedback inline para `processing`, `failed`, `paid` e estados revertidos;
- [x] backend segue recebendo apenas `card_token`.

Checklist de TTD:

- [x] expandir `PublicEventCheckoutPage.test.tsx` cobrindo mascaras e
      normalizacao visual;
- [x] criar teste garantindo que o payload final continua enviando apenas
      `card_token`;
- [x] rodar:
  - `cd apps/web && npm run test -- PublicEventCheckoutPage`
  - `cd apps/web && npm run type-check`

Criterio de aceite:

- o formulario de cartao fica mais claro e seguro para o usuario sem quebrar o
  contrato PCI/tokenizacao da Pagar.me.
- o usuario consegue enxergar o que falta antes de tentar pagar, em vez de
  descobrir tudo apenas depois do submit.

Dependencias:

- M5-T1
- M5-T2

### TASK M8-T4 - Notificar o cliente sobre status do pedido via Z-API

Referencia na arquitetura:

- `19.5 Notificacoes ativas ao cliente via Z-API`

Estado atual:

- o modulo `WhatsApp` ja suporta outbound com `sendText` e fila
  `whatsapp-send`;
- o `Billing` agora ja tem uma trilha dedicada para notificacoes ativas de
  pedido em `billing_order_notifications`;
- a resolucao de instancia ficou centralizada em
  `BillingWhatsAppInstanceResolver`;
- o disparo ja nasce da maquina de estados local, nunca do webhook cru;
- `chargeback` segue fora desta primeira rodada de notificacao.

Subtasks:

1. criar um servico dedicado de notificacao de status de pagamento no modulo
   `Billing`;
2. configurar resolucao da instancia WhatsApp para billing;
3. disparar notificacao apenas a partir da maquina de estados local;
4. cobrir pelo menos estes eventos:
   - Pix gerado
   - pagamento confirmado
   - pagamento reprovado
   - pagamento estornado
5. decidir se `chargeback` entra na primeira rodada ou fica bloqueado pela
   politica de produto;
6. deduplicar por `billing_order_id + tipo_de_notificacao`, para replay de
   webhook nao duplicar mensagem;
7. registrar no banco ou em metadado suficiente para troubleshooting.

Implementacao entregue:

1. `BillingPaymentStatusNotificationService` agora registra e entrega
   `pix_generated`, `payment_paid`, `payment_failed` e `payment_refunded`;
2. `BillingOrderNotification` ganhou persistencia dedicada com unique key por
   `billing_order_id + notification_type + channel`;
3. o trigger de `pix_generated` nasceu em
   `CreateEventPackageGatewayCheckoutAction`;
4. os triggers de `paid`, `failed` e `refunded` nasceram nas actions locais de
   mudanca de estado;
5. a mensagem outbound continua usando `WhatsAppMessagingService` e Z-API
   `send-text`, com contexto do billing salvo em
   `whatsapp_messages.payload_json.context`;
6. no `pix_generated`, quando a instancia resolvida usa `zapi`, o servico agora
   enfileira tambem `send-button-pix` com o mesmo valor copia-e-cola retornado
   em `qr_code`, para reduzir friccao na copia pelo WhatsApp.
7. o payload local do checkout agora expoe o resumo dessas notificacoes em
   `checkout.payment.whatsapp`, para a UI mostrar que o Pix tambem foi enviado
   ao WhatsApp do comprador.

Checklist de TTD:

- [x] criar teste feature no modulo `Billing` validando disparo de notificacao
      em `paid`;
- [x] criar teste para `failed`;
- [x] criar teste para replay de webhook nao duplicar notificacao;
- [x] criar teste para `pix_generated`;
- [x] criar teste para `refunded`;
- [x] expor o resumo do envio de WhatsApp no payload local do checkout;
- [x] refletir essa evidencia na UI publica do Pix;
- [x] rodar:
  - `cd apps/api && php artisan test --filter=Billing`
  - `cd apps/api && php artisan test --filter=BillingWebhookTest`
  - `cd apps/api && php artisan test --filter=PublicEventCheckoutTest`
  - `cd apps/api && php artisan test --filter=AdminQuickEventTest`

Criterio de aceite:

- o cliente recebe comunicacao ativa coerente com o estado local do pedido, e
  o replay do provider nao gera spam por WhatsApp.
- quando nao houver instancia operacional disponivel, a tentativa fica
  registrada como `unavailable` ou `skipped`, sem quebrar a reconciliacao do
  pedido.

Dependencias:

- M3-T3
- M3-T4
- M5-T3

## Gates objetivos de readiness

Antes de chamar a integracao de pronta, estes gates precisam estar verdes:

- doc de arquitetura atualizada e usada como fonte de contrato;
- execution plan atualizado com o estado real da rodada;
- `StorePublicEventCheckoutRequest` expandido e validado;
- schema local com snapshots, ids operacionais e timestamps principais;
- `PagarmeClient`, `PagarmeBillingGateway`, normalizadores e status mapper
  implementados;
- webhook assincrono em fila `billing`, com idempotencia e processamento unico;
- `GET /api/v1/public/event-checkouts/{uuid}` lendo somente o estado local;
- Pix validado por testes automatizados e homologacao;
- cartao transparente validado por testes automatizados e homologacao;
- cancelamento/refund e `chargeback` refletidos no estado local;
- frontend sem envio de PAN/CVV ao backend;
- criacao de pedido idempotente validada;
- reentrega do mesmo webhook validada;
- todas as suites de TTD da rodada verdes.

## Validacoes obrigatorias por fase

Validacoes de backend:

- `cd apps/api && php artisan test --filter=PublicEventCheckoutTest`
- `cd apps/api && php artisan test --filter=BillingWebhookTest`
- `cd apps/api && php artisan test --filter=BillingTest`
- `cd apps/api && php artisan test --filter=Pagarme`
- `cd apps/api && php artisan test`

Validacoes de frontend:

- `cd apps/web && npm run test`
- `cd apps/web && npm run type-check`

Validacoes de homologacao local:

- webhook local exposto e recebendo eventos reais;
- Pix sucesso e falha;
- cartao PSP sucesso e recusa;
- cartao com estados intermediarios;
- `chargeback`;
- cancelamento/estorno;
- replay de webhook;
- retry com a mesma `Idempotency-Key`.

## Fora da fase 1

Nao entram neste plano:

- assinatura recorrente via Pagar.me;
- wallet de cartao salvo;
- split;
- antifraude avancado;
- polling do frontend direto no gateway;
- qualquer fluxo que exija PAN aberto no backend;
- refactor geral do modulo `Billing` sem relacao direta com a integracao.

## Primeira execucao recomendada

Se a execucao comecar agora, a ordem recomendada e esta:

1. fechar `M0-T1` ate `M0-T3`;
2. executar `M1-T1`, `M1-T2` e `M1-T3`;
3. executar `M2-T1` ate `M2-T4`;
4. executar `M3-T1` ate `M3-T4`;
5. entregar Pix com `M4-T1` ate `M4-T3`;
6. fechar a decisao de cartao, se ainda aberta, e executar `M5-T1` ate `M5-T4`;
7. endurecer operacao com `M6-T1` ate `M6-T3`;
8. rodar homologacao oficial com `M7-T1` ate `M7-T3`.

Se o objetivo for entregar valor o mais cedo possivel, a recomendacao pratica
e:

- fazer Pix primeiro;
- nao abrir cartao antes da decisao oficial `card_token` vs `card_id`;
- nao homologar manualmente antes de a suite automatizada estar verde;
- nao chamar a integracao de pronta sem idempotencia, webhook assincrono e
  status local.

## Veredito de execucao

Se o objetivo for plugar a Pagar.me v5 sem perder o desenho bom que o modulo
`Billing` ja tem, a ordem correta e:

1. contrato local e persistencia;
2. provider isolado;
3. webhook e reconciliacao;
4. Pix;
5. cartao;
6. operacao;
7. homologacao oficial com evidencia.

Essa ordem preserva a arquitetura existente, ataca primeiro o que mais quebra
em producao e coloca TTD como requisito real de entrega, nao como atividade
opcional.
