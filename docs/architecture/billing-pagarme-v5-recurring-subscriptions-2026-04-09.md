# Pagar.me v5 recurring subscriptions for Eventovivo

## Objetivo

Este documento consolida, em `2026-04-09`:

- o estado real do `eventovivo` para billing recorrente da conta;
- o que ja existe hoje da integracao Pagar.me e pode ser reaproveitado;
- o que ainda falta para sair do checkout simplificado/manual e entrar em recorrencia real via `plan + subscription`;
- a leitura validada da documentacao oficial Pagar.me v5 para planos, assinaturas, ciclos, faturas, tokenizacao, webhooks e chargeback;
- um plano de implementacao detalhado para o modulo `Billing`.

Escopo deste documento:

- assinatura recorrente da organizacao;
- pagamento principal em `credit_card`;
- criacao e sincronizacao de `plan` e `subscription` no motor de recorrencia da Pagar.me;
- reconciliacao por `subscription`, `invoice`, `charge` e webhook;
- UX administrativa da conta para contratar, acompanhar, trocar cartao e cancelar.

Fora de escopo desta rodada:

- checkout publico de compra unica por evento, que ja tem documento proprio em
  `docs/architecture/billing-pagarme-v5-single-event-checkout.md`;
- split;
- antifraude avancado;
- boleto recorrente como fluxo principal;
- refactor geral do modulo `Billing` sem relacao com recorrencia.

## Veredito executivo

Para o Eventovivo, o caminho correto para recorrencia na Pagar.me v5 e usar o motor nativo deles com `plan + subscription`, nao recriar cobranca mensal via `POST /orders`.

O repositorio ja tem uma base boa para isso:

- tokenizacao de cartao no frontend;
- integracao Pagar.me v5 para `customers`, `cards`, `orders`, `charges` e `hooks`;
- superficie HTTP recorrente para `plans`, `subscriptions`, `cycles`, `invoices`, `charges`, `hooks` e wallet do cliente;
- trilha local com `BillingOrder`, `Payment`, `Invoice`, `Subscription` e `BillingGatewayEvent`;
- webhook idempotente e processamento assincrono;
- cancelamento local ao fim do ciclo e recalculo de entitlements.

A base recorrente ja existe no caminho da conta, mas ainda restam gaps para fechar o lifecycle completo:

- `PagarmeBillingGateway::createSubscriptionCheckout()` continua legacy/incompleto, mas o checkout real da conta ja segue pela trilha recorrente nova em `CreateSubscriptionCheckoutAction` + `PagarmeSubscriptionGatewayService`;
- a tela `/plans` ja coleta `payer`, `billing_address` e dados de cartao, tokeniza no submit final e envia apenas `card_token`;
- `CancelCurrentSubscriptionAction` ja sincroniza cancelamento imediato com `DELETE /subscriptions/{id}`, e o boundary local de `cancel_at_period_end` ja tem comando e scheduler dedicados;
- `RegisterBillingGatewayPaymentAction` ainda trata a assinatura manual antiga como derivacao de um checkout pago e usa `gateway_order_id` como fallback para `gateway_subscription_id`;
- o checkout da conta ja tem `PagarmePlanPayloadFactory`, `PagarmeSubscriptionPayloadFactory`, `BillingSubscriptionGatewayInterface` e `PagarmeSubscriptionGatewayService`, com `ensurePlan`, `billing_profile`, `customer`, `card` e `POST /subscriptions`;
- `subscription_cycles`, projection por `invoice/charge` e ids recorrentes em `billing_gateway_events` ja existem localmente;
- o webhook atual ja projeta `subscription`, `invoice`, `charge`, `subscription_cycle`, `invoice` e `payment`, e o modulo ja tem reconcile batch assistido, wallet/troca de cartao e homologacao ponta a ponta do lifecycle recorrente.

Conclusao pratica:

- o Eventovivo esta pronto para evoluir para recorrencia real;
- o checkout recorrente da conta ja cria plano externo, billing profile e assinatura real na Pagar.me pelo backend e pela tela administrativa;
- se `billing.gateways.subscription=pagarme` for ligado hoje, a API e a UX de checkout da conta ja respondem ao fluxo recorrente principal;
- os gaps remanescentes ficam concentrados em politicas de inadimplencia/grace period, meios de pagamento alem de `credit_card` e tooling operacional mais refinado;
- a migracao correta nao e reaproveitar `orders` para mensalidade, e sim plugar os endpoints nativos de recorrencia e tratar `invoice/charge` como fonte de verdade operacional do pagamento.

## Atualizacao validada em homologacao

Nesta rodada, a trilha recorrente foi validada com as chaves de homologacao da conta `acc_A1dYEVzhnIzEwG5z`, pelo comando:

```bash
cd apps/api && php artisan billing:pagarme:homologate --scenario=recurring-lifecycle --amount=1990 --poll-attempts=1 --poll-sleep-ms=0 --hook-id=hook_NQnjE65KiRIyVeKA
```

Evidencia gerada:

- `apps/api/storage/app/pagarme-homologation/20260409-235218-recurring-lifecycle.json`

Fluxos comprovados contra o provider:

- tokenizacao real em `POST /tokens`;
- criacao de `customer` e wallet com dois cartoes;
- criacao de `plan` mensal;
- criacao de `subscription` real;
- leitura de `cycles`, `invoices`, `charges` e `GET /charges/{charge_id}`;
- troca de cartao da assinatura;
- cancelamento sincronizado com `DELETE /subscriptions/{id}`;
- entrega real de webhooks recorrentes pela URL Cloudflare ja configurada.

Achados operacionais importantes:

- a referencia publica de `POST /subscriptions` descreve `card` como objeto, mas a homologacao real aceitou `card_id` e `card_token` no topo do payload; o shape aninhado `card.card_id` retornou `422`;
- os eventos `subscription.created`, `subscription.updated` e `subscription.canceled` chegam com o id da assinatura em `data.id`, entao o parser local precisou promover esse valor para `gateway_subscription_id`;
- a trilha real de webhook armazenou eventos `subscription.created`, `invoice.created`, `charge.paid`, `invoice.paid`, `subscription.updated` e `subscription.canceled` em `billing_gateway_events`.

## Fontes validadas

### Codigo revisado no repo

- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeClient.php`
- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeBillingGateway.php`
- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmePlanPayloadFactory.php`
- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeSubscriptionPayloadFactory.php`
- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeSubscriptionGatewayService.php`
- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeStatusMapper.php`
- `apps/api/app/Modules/Billing/Services/BillingSubscriptionGatewayInterface.php`
- `apps/api/app/Modules/Billing/Services/ManualSubscriptionGatewayService.php`
- `apps/api/app/Modules/Billing/Actions/CreateSubscriptionCheckoutAction.php`
- `apps/api/app/Modules/Billing/Actions/ProjectRecurringBillingStateAction.php`
- `apps/api/app/Modules/Billing/Actions/RegisterBillingGatewayPaymentAction.php`
- `apps/api/app/Modules/Billing/Actions/CancelCurrentSubscriptionAction.php`
- `apps/api/app/Modules/Billing/Actions/ProcessBillingWebhookAction.php`
- `apps/api/app/Modules/Billing/Actions/ReceiveBillingWebhookAction.php`
- `apps/api/app/Modules/Billing/Models/Subscription.php`
- `apps/api/app/Modules/Billing/Models/SubscriptionCycle.php`
- `apps/api/app/Modules/Billing/Models/BillingProfile.php`
- `apps/api/app/Modules/Billing/Models/Invoice.php`
- `apps/api/app/Modules/Billing/Models/Payment.php`
- `apps/api/app/Modules/Billing/Models/BillingGatewayEvent.php`
- `apps/api/app/Modules/Billing/Http/Controllers/SubscriptionController.php`
- `apps/api/app/Modules/Billing/Http/Requests/StoreSubscriptionCheckoutRequest.php`
- `apps/api/app/Modules/Billing/Services/PlanSnapshotService.php`
- `apps/api/app/Modules/Plans/Models/Plan.php`
- `apps/api/app/Modules/Plans/Models/PlanPrice.php`
- `apps/api/database/migrations/2024_01_01_000005_create_plans_tables.php`
- `apps/api/database/migrations/2024_01_01_000006_create_subscriptions_table.php`
- `apps/api/database/migrations/2026_04_01_194000_create_billing_order_tables.php`
- `apps/api/database/migrations/2026_04_02_100000_create_billing_payments_and_invoices_tables.php`
- `apps/api/database/migrations/2026_04_02_120000_create_billing_gateway_events_table.php`
- `apps/api/database/migrations/2026_04_04_090000_add_pagarme_operational_fields_to_billing_tables.php`
- `apps/api/database/migrations/2026_04_09_180000_expand_plan_prices_for_recurring_gateway_plans.php`
- `apps/api/database/migrations/2026_04_09_181000_expand_subscriptions_for_recurring_gateway_state.php`
- `apps/api/database/migrations/2026_04_09_182000_create_billing_profiles_table.php`
- `apps/api/database/migrations/2026_04_09_190000_create_subscription_cycles_table.php`
- `apps/api/database/migrations/2026_04_09_191000_add_recurring_fields_to_invoices_table.php`
- `apps/api/database/migrations/2026_04_09_192000_add_recurring_fields_to_payments_table.php`
- `apps/api/database/migrations/2026_04_09_193000_add_recurring_fields_to_billing_gateway_events_table.php`
- `apps/web/src/lib/pagarme-tokenization.ts`
- `apps/web/src/modules/plans/api.ts`
- `apps/web/src/modules/plans/PlansPage.tsx`
- `apps/web/src/modules/plans/components/RecurringPlanCheckoutDialog.tsx`
- `apps/web/src/modules/billing/PublicEventCheckoutPage.tsx`

### Documentacao oficial Pagar.me v5 validada

- Tokenizacao: `https://docs.pagar.me/reference/tokeniza%C3%A7%C3%A3o-1`
- Tokenizecard.js: `https://docs.pagar.me/docs/tokenizecard`
- Carteira de clientes: `https://docs.pagar.me/docs/carteira-de-clientes`
- Carteira de cartoes: `https://docs.pagar.me/docs/wallets`
- Cartoes: `https://docs.pagar.me/reference/cart%C3%B5es-1`
- Listar cartao: `https://docs.pagar.me/reference/listar-cart%C3%A3o`
- Criar plano: `https://docs.pagar.me/reference/criar-plano-1`
- Planos: `https://docs.pagar.me/docs/plano`
- Excluir plano: `https://docs.pagar.me/reference/excluir-plano-1`
- Criar assinatura de plano: `https://docs.pagar.me/reference/criar-assinatura-de-plano-1`
- Assinaturas: `https://docs.pagar.me/reference/assinaturas-1`
- Listar assinaturas: `https://docs.pagar.me/reference/listar-assinaturas-1`
- Obter assinatura: `https://docs.pagar.me/reference/obter-assinatura-1`
- Editar cartao da assinatura: `https://docs.pagar.me/reference/editar-cart%C3%A3o-da-assinatura-1`
- Editar meio de pagamento da assinatura: `https://docs.pagar.me/reference/editar-meio-de-pagamento-da-assinatura`
- Editar data de inicio da assinatura: `https://docs.pagar.me/reference/editar-data-de-in%C3%ADcio-da-assinatura-1`
- Editar metadados da assinatura: `https://docs.pagar.me/reference/editar-metadados-da-assinatura-1`
- Cancelar assinatura: `https://docs.pagar.me/reference/cancelar-assinatura-1`
- Ciclos: `https://docs.pagar.me/reference/ciclos-1`
- Listar ciclos: `https://docs.pagar.me/reference/listar-ciclos-1`
- Renovar ciclo: `https://docs.pagar.me/reference/renovar-ciclo-1`
- Faturas: `https://docs.pagar.me/reference/faturas-1`
- Listar faturas: `https://docs.pagar.me/reference/listar-faturas-1`
- Cobranca: `https://docs.pagar.me/docs/cobran%C3%A7a`
- Listar cobrancas: `https://docs.pagar.me/reference/listar-cobran%C3%A7as`
- Item da assinatura: `https://docs.pagar.me/reference/item-da-assinatura-1`
- Incluir item: `https://docs.pagar.me/reference/incluir-item-1`
- Editar item: `https://docs.pagar.me/reference/editar-item`
- Listar uso: `https://docs.pagar.me/reference/listar-uso`
- Editar preco minimo da assinatura: `https://docs.pagar.me/reference/editar-minimum-price-da-assinatura`
- Idempotencia: `https://docs.pagar.me/docs/o-que-%C3%A9`
- Paginacao: `https://docs.pagar.me/reference/pagina%C3%A7%C3%A3o-1`
- Visao geral sobre Webhooks: `https://docs.pagar.me/reference/vis%C3%A3o-geral-sobre-webhooks`
- O que sao webhooks: `https://docs.pagar.me/docs/webhooks`
- Eventos de webhook: `https://docs.pagar.me/reference/eventos-de-webhook-1`
- Listar webhooks: `https://docs.pagar.me/reference/listar-webhooks`
- Obter webhook: `https://docs.pagar.me/reference/obter-webhook`
- Enviar webhook novamente: `https://docs.pagar.me/reference/enviar-webhook`
- Chargeback - novo status na cobranca: `https://docs.pagar.me/page/chargeback-novo-status-na-cobran%C3%A7a`
- Bibliotecas: `https://docs.pagar.me/docs/bibliotecas-1`
- SDK PHP oficial arquivado: `https://github.com/pagarme/pagarme-core-api-php`

## Leitura oficial da recorrencia Pagar.me v5

### 1. O motor nativo e baseado em plan + subscription

A Pagar.me trata `plan` como template de recorrencia e `subscription` como a assinatura vinculada ao cliente. A criacao de assinatura usa `POST /subscriptions`, com `plan_id`, `payment_method`, `customer_id` ou `customer`, `card` e `installments`.

Leituras importantes da doc oficial:

- em recorrencia, o numero de parcelas deve ser `1`;
- `interval` e `interval_count` definem a frequencia;
- `billing_type` pode ser `prepaid`, `postpaid` ou `exact_day`;
- `billing_days` e obrigatorio quando `billing_type=exact_day`;
- `start_at` e opcional e, se omitido, a assinatura comeca imediatamente;
- o `plan_id` da assinatura precisa existir previamente.

### 2. O pagamento recorrente nao deve ser monitorado so por subscription.status

A listagem oficial de assinaturas exposta pela API publica v5 mostra `status` simplificado:

- `active`
- `canceled`
- `future`

Ao mesmo tempo:

- `invoice` tem estados como `pending`, `paid`, `canceled`, `scheduled` e `failed`;
- `charge` concentra o status mais fino do pagamento, incluindo `chargedback`.

Leitura recomendada para SaaS no Eventovivo:

- `subscription` responde se a recorrencia existe, esta futura, ativa ou cancelada;
- `invoice` e `charge` respondem o estado financeiro do ciclo;
- acesso da conta nao deve depender apenas de `subscription.status`.

### 3. O ciclo recorrente e visivel por cycles + invoices + charges

A doc oficial deixa claro:

- `cycle` representa o ciclo de faturamento da assinatura;
- `invoice` representa os documentos gerados automaticamente ao final de cada ciclo;
- `charge` e a cobranca originada a partir da invoice;
- `GET /subscriptions/{subscription_id}/cycles` devolve `billing_at`, `start_at`, `end_at` e `status` do ciclo.

### 4. Tokenizacao no front continua sendo o caminho certo

O Eventovivo ja tokeniza cartao via frontend em `apps/web/src/lib/pagarme-tokenization.ts`, usando `POST /tokens?appId=<public_key>`. Isso continua correto para recorrencia:

- o backend nao recebe PAN nem CVV;
- o backend recebe `card_token` ou `card_id`;
- a assinatura pode ser criada com `card.card_token` ou `card.card_id`;
- a atualizacao de cartao tambem pode usar `card_id` ou `card_token`.

### 5. Cancelamento ao fim do ciclo nao apareceu como endpoint publico dedicado

O endpoint oficial documentado para cancelamento e `DELETE /subscriptions/{subscription_id}`. A rota aceita `cancel_pending_invoices`, com default `true`.

Eu nao encontrei na referencia publica v5 uma rota explicita para:

- `cancel_at_period_end`;
- `pause subscription`.

Leitura mais segura para o Eventovivo:

- cancelamento imediato: `DELETE /subscriptions/{id}`;
- cancelamento ao fim do ciclo: regra local do produto, disparando `DELETE` apenas no boundary desejado.

### 6. Chargeback em assinatura e evento terminal importante

A pagina oficial de chargeback informa que, para assinaturas:

- a `subscription` muda para `canceled`;
- a `invoice` muda para `failed`;
- a `charge` fica `chargedback`;
- para voltar a cobrar, deve ser criada uma nova assinatura com novo cartao.

Isso exige tratamento explicito no Eventovivo para:

- encerrar a recorrencia local;
- decidir politica de acesso e grace period;
- exigir nova adesao com novo cartao se o chargeback for confirmado.

### 7. Planos alterados nao atualizam assinaturas existentes automaticamente

A guia oficial de `planos` informa que alterar um plano nao altera automaticamente as assinaturas ja vinculadas a ele.

Consequencia direta no Eventovivo:

- alteracao comercial relevante de plano nao deve assumir propagacao magica;
- a sincronizacao de planos externos precisa ser versionada com cuidado;
- em varios casos, o caminho mais seguro sera criar um novo `gateway_plan_id` e migrar assinaturas conscientemente.

Leitura recomendada para implementacao:

- a estrategia padrao do Eventovivo deve ser `versionar plano externo por plan_price`;
- `updatePlan` fica como ferramenta administrativa rara, nao como caminho default do runtime.

### 8. Wallet de cliente e consulta de cobrancas entram no escopo minimo

Além de `plan`, `subscription`, `cycle` e `invoice`, a referencia publica v5 tambem sustenta duas necessidades operacionais que valem entrar no corte minimo:

- `GET /customers/{customer_id}/cards` para listar a wallet do cliente e viabilizar troca de cartao, cobranca assistida e autosservico;
- `GET /charges` e `GET /charges/{charge_id}` como trilha oficial de reconciliacao fina, principalmente em chargeback, replay de webhook e divergencia entre invoice e cobranca.

Leitura arquitetural para o Eventovivo:

- checkout recorrente nao deve depender apenas do payload bruto enviado na contratacao;
- a conta precisa de um perfil estavel de cobranca com `gateway_customer_id`, cartao default e endereco de cobranca reutilizavel;
- reconciliacao financeira recorrente nao deve depender so de `invoice`.

### 9. A API publica ja mostra que assinatura pode evoluir para add-ons e uso

A referencia publica v5 expoe endpoints de `subscription items`, `usage` e `minimum_price`.

Isso nao obriga o Eventovivo a implementar add-ons agora, mas muda a forma certa de desenhar o modulo:

- a interface de assinatura nao deve assumir que toda recorrencia cabe apenas em `plan_id + valor fixo`;
- o modelo interno precisa permitir evolucao futura para assentos, franquias, eventos excedentes ou storage adicional;
- mesmo fora do MVP, vale deixar `BillingSubscriptionGatewayInterface` preparada para expansao.

### 10. Idempotencia e paginacao precisam virar regra de plataforma

A doc oficial trata idempotencia como parte da superficie publica da API e a referencia exposta mostra `Idempotency-Key` em operacoes de escrita. A doc oficial tambem deixa claro que os recursos de listagem relevantes usam paginacao com `page` e `size`.

Leitura recomendada para o Eventovivo:

- toda escrita relevante no client recorrente deve aceitar `idempotencyKey`;
- reconciliacao deve existir em tres trilhas: webhook em tempo real, sync pontual por assinatura e batch paginado;
- jobs operacionais nao devem depender de scans abertos da base externa.

Leitura adicional para caminho quente:

- `GET /plans` e `GET /subscriptions` sao mais uteis para diagnostico, auditoria e reconcile;
- o runtime do checkout deve preferir `PlanPrice` local, `gateway_plan_id`, `billing_profile` e `POST /subscriptions`, sem depender de listagens amplas do provider.

### 11. A referencia oficial de PHP continua fragmentada

A pagina oficial de bibliotecas ainda aponta PHP para o pacote/repositorio `PagarmeCoreApi`, e o repositorio oficial `pagarme/pagarme-core-api-php` aparece arquivado no GitHub.

Leitura recomendada para o Eventovivo:

- manter `PagarmeClient` proprio em Laravel continua sendo a opcao mais previsivel;
- o repo nao precisa migrar para SDK de terceiros para entrar em recorrencia;
- a abstracao certa e separar client HTTP puro de gateway/domain service do modulo `Billing`.

### 12. A validacao de assinatura de webhook permanece pouco clara na V5 publica

Na busca e leitura da referencia publica atual da V5, eu nao encontrei uma pagina equivalente e explicita para validacao criptografica de webhook com contrato atualizado. As paginas publicas lidas deixam claro o envio por `HTTP POST`, a selecao de eventos, a configuracao do endpoint e os endpoints de diagnostico/retry, mas nao deixam igualmente clara a estrategia de assinatura que deve ser aplicada no backend consumidor.

Leitura segura para o Eventovivo:

- receber webhook com corpo bruto e headers persistidos;
- salvar `payload_hash` e `headers_json` para auditoria e replay;
- usar URL secreta/imutavel e controles de origem enquanto a validacao criptografica V5 nao estiver fechada com evidencia oficial ou homologacao controlada.

Complemento operacional:

- a pagina guia `O que sao webhooks` continua sendo a fonte publica mais util para a lista de eventos recorrentes;
- a nota de chargeback continua sendo o complemento obrigatorio para `charge.chargedback`;
- endurecimento com `IP allowlist` e dominio autorizado para tokenizacao deve entrar no checklist de ambiente.

## Estado real do Eventovivo hoje

## O que ja existe e pode ser reaproveitado

### 1. Tokenizacao de cartao pronta

Ja existe:

- `apps/web/src/lib/pagarme-tokenization.ts`

Isso resolve a parte sensivel do fluxo de cartao para recorrencia.

### 2. Client Pagar.me com base operacional solida

Ja existe em `PagarmeClient`:

- `createCustomer`
- `createCustomerCard`
- `listCustomerCards`
- `createOrder`
- `getOrder`
- `createPlan`
- `listPlans`
- `getPlan`
- `createSubscription`
- `getSubscription`
- `listSubscriptions`
- `listSubscriptionCycles`
- `listInvoices`
- `listCharges`
- `getCharge`
- `cancelSubscription`
- `updateSubscriptionCard`
- `updateSubscriptionPaymentMethod`
- `updateSubscriptionStartAt`
- `updateSubscriptionMetadata`
- `listHooks`
- `getHook`
- `retryHook`
- `cancelCharge`
- `captureCharge`

Ou seja:

- customer e card wallet ja estao parcialmente cobertos;
- hooks ja tem diagnostico;
- a superficie HTTP recorrente oficial do provider ja esta exposta no client;
- a base HTTP, auth, retry e timeout ja esta pronta.

### 3. Trilha local de billing madura para compra unica

Ja existem:

- `BillingOrder`
- `Payment`
- `Invoice`
- `Subscription`
- `BillingGatewayEvent`
- pipeline de webhook assincrono
- idempotencia por `provider_key + event_key`

### 4. Entitlements e cancelamento local da conta

Ja existem:

- subscription na organizacao;
- `cancel_at_period_end` local baseado em `ends_at`;
- recalculo de entitlements da conta e dos eventos cobertos;
- UX administrativa em `/plans`.

### 5. Aprendizados operacionais da integracao Pagar.me atual

O repo ja validou na trilha de compra unica alguns pontos que devem ser reaproveitados integralmente na recorrencia:

- `afterCommit` ja e usado no modulo `Billing` para evitar processamento assincrono sobre estado nao commitado;
- `ProcessBillingWebhookJob` ja usa `ShouldBeUnique`, o que reduz replay concorrente do mesmo evento;
- o modulo ja tem `PagarmeHomologationService` e `PagarmeHomologationCommand`, com probes diretos e evidencias JSON versionaveis;
- a integracao atual ja provou retry seguro com a mesma `Idempotency-Key` e troubleshooting por `GET /charges/{id}`;
- a tokenizacao no frontend ja esta coberta com a chave publica de teste oficial usada no repo.

Leitura pratica para a recorrencia:

- o trabalho nao e inventar uma nova disciplina operacional;
- o trabalho e subir `plan + subscription` em cima dos mesmos guardrails de homologacao, idempotencia, `afterCommit`, lock e webhook que a compra unica ja estabilizou.

## O que falta para recorrencia real

### 1. O provider recorrente ja existe, mas o caminho legacy ainda nao deve ser reutilizado

Hoje, em `PagarmeBillingGateway`, o metodo `createSubscriptionCheckout()` continua legacy/incompleto.

Ao mesmo tempo, o repo ja tem uma trilha recorrente nova e separada para a conta:

- `PagarmePlanPayloadFactory`;
- `PagarmeSubscriptionPayloadFactory`;
- `BillingSubscriptionGatewayInterface`;
- `PagarmeSubscriptionGatewayService`;
- `CreateSubscriptionCheckoutAction` com branch recorrente real para `billing.gateways.subscription=pagarme`.

Impacto:

- a assinatura da conta ja consegue fazer `POST /plans` e `POST /subscriptions` no backend;
- `gateway_plan_id`, `gateway_subscription_id`, `gateway_customer_id`, `gateway_card_id` e `billing_profile` ja sao persistidos;
- o metodo legacy nao deve voltar a ser usado como motor do fluxo de conta.

### 2. O frontend da conta ja coleta dados de pagamento reais

Hoje:

- `StoreSubscriptionCheckoutRequest` ja aceita payload real quando `billing.gateways.subscription=pagarme`;
- `plansService.checkout()` ja pode carregar `payment_method`, `payer` e `credit_card`;
- `PlansPage.tsx` + `RecurringPlanCheckoutDialog.tsx` ja coletam `payer`, `billing_address`, tokenizam no submit final e enviam apenas `card_token`.

Impacto:

- o admin ja consegue iniciar assinatura recorrente real na tela `/plans`;
- o fluxo preserva a regra de nao trafegar PAN/CVV pelo backend;
- o gap remanescente na UX fica concentrado em wallet, troca de cartao e autosservico mais amplo.

### 3. O cancelamento imediato ja sincroniza com o provider

`CancelCurrentSubscriptionAction` hoje:

- muda `status`, `canceled_at`, `ends_at` e `renews_at` no banco local;
- chama `DELETE /subscriptions/{id}` quando o cancelamento e imediato e a assinatura esta na Pagar.me;
- preserva `period_end` como regra local do produto.

Impacto:

- cancelamento imediato ja nao fica divergente entre Eventovivo e provider;
- o gap remanescente e executar o `DELETE` real no boundary do `cancel_at_period_end` e fechar a automacao desse scheduler.

### 4. A maquina de estados recorrente ja projeta subscription/invoice/charge

Hoje:

- `PagarmeStatusMapper` ja mapeia `subscription.*`, `invoice.*` e `charge.*` recorrente;
- `ProcessBillingWebhookAction` + `ProjectRecurringBillingStateAction` ja correlacionam `gateway_subscription_id`, `gateway_invoice_id` e `gateway_cycle_id`;
- `billing_gateway_events` ja persiste ids recorrentes, `payload_hash` e correlacao de customer/cycle.

Impacto:

- renewal, payment failure e chargeback ja entram no read model recorrente certo;
- o gap remanescente fica no reconcile batch paginado e na homologacao completa do lifecycle.

### 5. Cycle ja virou entidade de primeira classe

Hoje o repo ja tem `SubscriptionCycle` e projection local com:

- `gateway_cycle_id`;
- `billing_at`;
- `period_start_at`;
- `period_end_at`;
- `status`;
- ligacao direta com `invoice`.

Impacto:

- competencia recorrente, proximo vencimento, auditoria e chargeback ja nao dependem so de heuristica em `invoice`/`charge`;
- o gap remanescente fica em sync batch e operacao manual assistida.

### 6. Invoice e Payment ja representam o ciclo recorrente

Hoje `Invoice` e `Payment`:

- continuam convivendo com `billing_order_id` da compra unica;
- agora tambem guardam `subscription_id`, `subscription_cycle_id`, `gateway_invoice_id`, `gateway_charge_id`, `gateway_cycle_id` e payload bruto recorrente.

Impacto:

- replay de webhook, troubleshoot e chargeback ja tem rastreabilidade fina por ciclo;
- o gap remanescente fica em reconcile batch e surfaces administrativas mais detalhadas.

### 7. O plano externo ja e mapeado corretamente por plan_price

Hoje:

- `Plan` continua representando o produto;
- `PlanPrice` continua representando o ciclo (`monthly`, `yearly`);
- `PlanPrice` ja guarda `gateway_plan_id`, payload externo e configuracao recorrente do plan.

Impacto:

- a relacao `1 plan_price local -> 1 gateway plan` ja esta implementada;
- o gap remanescente e disciplina de versionamento comercial e migracao consciente de assinaturas quando o plano mudar.

### 8. O estado local da assinatura ja separa contrato, cobranca e acesso

Hoje a `Subscription` local ja conhece, alem do shape original:

- `plan_price_id`
- `gateway_plan_id`
- `gateway_card_id`
- `gateway_status_reason`
- `payment_method`
- `billing_type`
- `contract_status`
- `billing_status`
- `access_status`
- `next_billing_at`
- `current_period_started_at`
- `current_period_ends_at`
- `cancel_at_period_end`
- `cancel_requested_at`
- `metadata_json`

Impacto:

- contrato, cobranca e acesso ja nao ficam esmagados em um unico `status`;
- o gap remanescente fica em refinar politicas de inadimplencia, grace period e chargeback.

### 9. Ja existe um billing profile estavel por organizacao

Hoje o billing recorrente da conta ja usa `BillingProfile` para:

- `gateway_customer_id`;
- cartao default ou `gateway_card_id`;
- nome/documento do pagador;
- endereco de cobranca;
- metadata de reconciliacao do tenant.

Impacto:

- checkout recorrente, renovacao e conciliacao ja partem de uma identidade estavel de cobranca;
- o gap remanescente fica em expor wallet e troca de cartao no autosservico.

## Gap analysis resumido

| Area | Ja temos | Gap para recorrencia real |
| --- | --- | --- |
| Tokenizacao | `POST /tokens` no frontend e `/plans` usando tokenizacao no submit final | Expor troca de cartao e wallet |
| Customer/card | `createCustomer`, `createCustomerCard`, wallet e troca de cartao ja expostos no painel | Abrir outros meios de pagamento e suporte mais detalhado |
| Orders/charges | Forte para compra unica, reconcile recorrente e troubleshooting com `GET /charges/{charge_id}` | Tooling operacional mais rico |
| Plans externos | `gateway_plan_id` por `plan_price` ja implementado | Versionamento comercial e migracao consciente |
| Subscriptions externas | Criar, obter, listar, cancelar e trocar cartao ja existem na superficie recorrente | Payment method alem de `credit_card` |
| Cycles/invoices externas | Projection local por `subscription_cycle`, `invoice` e `charge` ja existe | Support tooling e politicas de inadimplencia |
| Cycles locais | `subscription_cycles` ja existe | Observabilidade e tooling de suporte |
| Billing profile | Existe por organizacao e ja aprende com wallet real | Evolucao futura para mais meios de pagamento |
| Webhooks | Idempotencia pronta, projection recorrente e homologacao ponta a ponta validadas | Endurecimento adicional de operacao e telemetria |
| Cancelamento | Imediato sincronizado com `DELETE /subscriptions/{id}` e `period_end` local com scheduler | Politica comercial final de acesso no boundary |
| UX admin | `/plans` ja faz checkout real, mostra assinatura/invoices, wallet, troca de cartao e reconcile assistido | Refinar suporte operacional e inadimplencia |

## Arquitetura recomendada para o Eventovivo

## 1. O motor financeiro deve ser subscription/subscription_cycle/invoice/charge

Recomendacao:

- usar `plan + subscription` na Pagar.me para recorrencia real;
- persistir `subscription_cycle` local como competencia de faturamento;
- usar `invoice` e `charge` como estado financeiro do ciclo;
- usar `Subscription` local como estado contratual e de acesso do produto;
- manter `BillingOrder` como ledger local do evento de contratacao e, se desejado, das renovacoes sintetizadas.

### Decisao recomendada de modelagem

Para minimizar ruptura no repo atual, a melhor estrategia e:

1. manter o `BillingOrder` do signup inicial da assinatura;
2. criar `subscription_cycles` locais como entidade de primeira classe;
3. sintetizar um `BillingOrder` local por renovacao relevante, quando uma `invoice`/`charge` de ciclo for criada ou reconciliada;
4. ligar `Invoice` e `Payment` diretamente a `subscription_id`, `subscription_cycle_id` e aos ids externos do provider.

Motivo:

- preserva o historico financeiro atual do modulo;
- reaproveita `GET /api/v1/billing/invoices`;
- elimina a necessidade de inferir competencia apenas por invoice/charge;
- evita depender apenas de JSONs soltos;
- nao exige criar um modulo paralelo so para renovacao.

## 2. O plano externo deve ser mapeado por plan_price

Recomendacao:

- adicionar `gateway_plan_id` em `plan_prices`;
- guardar tambem o payload/snapshot usado para criar o plan externo;
- tratar `monthly` e `yearly` como plans externos distintos.

Campos recomendados em `plan_prices`:

- `gateway_plan_id`
- `gateway_plan_version`
- `gateway_plan_payload_json`
- `billing_type`
- `billing_day`
- `trial_period_days`
- `payment_methods_json`

## 3. A assinatura local precisa separar contrato, cobranca e acesso

Estados locais recomendados:

- `contract_status`: `pending_activation`, `trialing`, `active`, `future`, `canceled`, `expired`
- `billing_status`: `pending`, `paid`, `grace_period`, `past_due`, `failed`, `chargedback`, `refunded`
- `access_status`: `provisioning`, `enabled`, `grace_period`, `blocked`, `canceled`

Regra recomendada:

- `subscription.status` do provider nao basta;
- `invoice` e `charge` projetam o estado financeiro do ciclo atual;
- o Eventovivo projeta `access_status` com base no contrato, na cobranca atual e na politica interna de grace period;
- `charge.chargedback` deve ser tratado como estado terminal operacional.

## 4. Billing profile deve ser entidade estavel do tenant

Recomendacao:

- criar um `billing_profile` ou `customer_payment_profile` por organizacao;
- separar esse perfil da assinatura ativa;
- usar esse perfil para `gateway_customer_id`, cartao default, endereco de cobranca e metadata de reconciliacao.

Motivo:

- upgrade, downgrade e recuperacao de inadimplencia nao devem depender do payload do checkout inicial;
- a wallet do cliente no provider vira parte do autosservico;
- a assinatura passa a ser uma projecao contratual e nao o unico lugar onde dados de cobranca vivem.

## 5. Cancelamento ao fim do ciclo continua como regra do produto

Como nao encontrei rota publica v5 para `cancel_at_period_end`, a melhor decisao e:

- manter `cancel_at_period_end` no Eventovivo;
- manter `access_until` local;
- executar `DELETE /subscriptions/{id}` apenas no boundary escolhido;
- continuar suportando cancelamento imediato como operacao separada.

## Mudancas recomendadas no backend

## 1. Schema

### Baseline real do repo antes da Fase 1

O delta de schema precisa partir do estado real atual:

- `subscriptions` hoje ainda guarda apenas `plan_id`, `status`, `billing_cycle`, janelas basicas (`starts_at`, `trial_ends_at`, `renews_at`, `ends_at`, `canceled_at`) e ids externos minimos (`gateway_customer_id`, `gateway_subscription_id`);
- `plan_prices` ainda so conhece `gateway_price_id`, o que e suficiente para compra unica, mas insuficiente para o `plan` recorrente da Pagar.me;
- `invoices` e `payments` ainda nascem centradas em `billing_order_id`, sem `subscription_id`, `subscription_cycle_id` ou ids recorrentes do provider;
- `billing_gateway_events` ainda e orientada a `gateway_order_id` e `gateway_charge_id`, com `headers_json` ja existente, mas sem `gateway_subscription_id`, `gateway_invoice_id`, `gateway_cycle_id` ou `payload_hash`;
- `billing_orders` ja carrega idempotencia e trilha de charge da compra unica, mas ainda nao representa `signup` e `renewal` recorrente por `subscription_id`.
- o repo ainda nao tem factories dedicadas para `Subscription`, `SubscriptionCycle` ou `BillingProfile`, entao a Fase 1 precisa fechar esse gap junto com o schema.

Implicacao pratica:

- a migracao correta para recorrencia precisa ser additive-first;
- `gateway_price_id` nao deve ser reaproveitado para guardar `gateway_plan_id`;
- a convivencia entre compra unica e recorrencia precisa durar pelo menos uma janela de homologacao completa.

### `plan_prices`

Adicionar:

- `gateway_plan_id`
- `gateway_plan_payload_json`
- `billing_type`
- `billing_day`
- `trial_period_days`
- `payment_methods_json`

### `subscriptions`

Adicionar:

- `plan_price_id`
- `payment_method`
- `gateway_plan_id`
- `gateway_card_id`
- `gateway_status_reason`
- `billing_type`
- `contract_status`
- `billing_status`
- `access_status`
- `current_period_started_at`
- `current_period_ends_at`
- `next_billing_at`
- `cancel_at_period_end`
- `cancel_requested_at`
- `metadata_json`

### `subscription_cycles`

Criar:

- `subscription_id`
- `gateway_cycle_id`
- `status`
- `billing_at`
- `period_start_at`
- `period_end_at`
- `closed_at`
- `raw_gateway_json`

### `invoices`

Adicionar:

- `subscription_id`
- `subscription_cycle_id`
- `gateway_invoice_id`
- `gateway_charge_id`
- `gateway_cycle_id`
- `gateway_status`
- `period_start_at`
- `period_end_at`
- `raw_gateway_json`

### `payments`

Adicionar:

- `subscription_id`
- `invoice_id`
- `gateway_invoice_id`
- `gateway_charge_status`
- `card_brand`
- `card_last_four`
- `attempt_sequence`
- `gateway_response_json`

### `billing_gateway_events`

Adicionar:

- `hook_id`
- `gateway_subscription_id`
- `gateway_invoice_id`
- `gateway_cycle_id`
- `gateway_customer_id`
- `headers_json`
- `payload_hash`

### `billing_orders`

Opcional, mas recomendado:

- `subscription_id`
- `order_kind` (`signup`, `renewal`, `manual_rebill`)

### `billing_profiles`

Criar:

- `organization_id`
- `gateway_provider`
- `gateway_customer_id`
- `gateway_default_card_id`
- `payer_name`
- `payer_email`
- `payer_document`
- `payer_phone`
- `billing_address_json`
- `metadata_json`

### Indices e unicidade recomendados

Adicionar pelo menos:

- `UNIQUE(gateway_plan_id)` em `plan_prices`
- `UNIQUE(gateway_subscription_id)` em `subscriptions`
- `UNIQUE(gateway_cycle_id)` em `subscription_cycles`
- `UNIQUE(gateway_invoice_id)` em `invoices`
- `UNIQUE(gateway_charge_id)` quando a modelagem final definir a tabela dona da charge
- indices compostos como `(organization_id, contract_status)` e `(subscription_id, period_end_at)`

## 2. Interfaces e services

O `BillingGatewayInterface` atual esta muito orientado a checkout de `order`.

Para recorrencia real, recomendo introduzir:

- `BillingSubscriptionGatewayInterface`
- `BillingProfileGatewayInterface` se a equipe quiser explicitar a camada de wallet/customer

Com responsabilidades minimas:

- `ensurePlan`
- `createSubscription`
- `getSubscription`
- `listSubscriptionCycles`
- `listInvoices`
- `listCharges`
- `cancelSubscription`
- `updateSubscriptionCard`
- `updateSubscriptionPaymentMethod`
- `updateSubscriptionStartAt`
- `updateSubscriptionMetadata`

Novas pecas recomendadas no modulo `Billing`:

- `Services/Pagarme/PagarmeClient.php` como client HTTP puro, orientado a endpoint
- `Services/Pagarme/PagarmeSubscriptionGatewayService.php` como service de dominio do Eventovivo
- `Services/Pagarme/PagarmePlanPayloadFactory.php`
- `Services/Pagarme/PagarmeSubscriptionPayloadFactory.php`
- `Actions/EnsureGatewayPlanAction.php`
- `Actions/CreateGatewaySubscriptionAction.php`
- `Actions/SyncGatewaySubscriptionAction.php`
- `Actions/SyncSubscriptionCycleAction.php`
- `Actions/UpsertSubscriptionInvoiceAction.php`
- `Actions/UpdateSubscriptionCardAction.php`
- `Actions/UpdateSubscriptionPaymentMethodAction.php`
- `Actions/UpsertBillingProfileAction.php`
- `Actions/ScheduleSubscriptionCancellationAction.php`
- `Jobs/ReconcileSubscriptionBillingJob.php`

### Concerns transversais

Recomendacao explicita:

- `PagarmeClient` concentra auth, retry, timeout, paginacao e `Idempotency-Key`;
- o gateway/domain service concentra regras do Eventovivo, correlacao local, logs e mapping de erro;
- o webhook nao deve ser a unica trilha de sync: criar tambem sync pontual por assinatura e reconcile batch paginado.

## 3. PagarmeClient

Adicionar metodos para:

- `createPlan`
- `listPlans`
- `getPlan`
- `updatePlan` ou estrategia de versionamento sem update in place
- `createSubscription`
- `getSubscription`
- `listSubscriptions`
- `listSubscriptionCycles`
- `listInvoices`
- `listCharges`
- `cancelSubscription`
- `updateSubscriptionCard`
- `updateSubscriptionPaymentMethod`
- `updateSubscriptionStartAt`
- `updateSubscriptionMetadata`
- `getHook`
- `listCustomerCards`

Como leitura arquitetural, recomendo manter o client proprio em Laravel em vez de trocar o modulo para um SDK PHP externo. A validacao atual mostra que a propria pagina oficial de bibliotecas aponta para o repositorio `pagarme/pagarme-core-api-php`, que aparece arquivado no GitHub, entao a superficie mais estavel para o repo continua sendo um wrapper HTTP proprio.

## 4. Checkout da conta

`CreateSubscriptionCheckoutAction` precisa deixar de ser um "checkout pago -> cria Subscription local" e virar:

1. resolve `Plan + PlanPrice`;
2. garante `gateway_plan_id` para aquele `plan_price`;
3. cria `BillingOrder` de signup da assinatura;
4. resolve ou cria `billing_profile` local da organizacao;
5. resolve `customer_id` e `card_id` ou usa `card_token`;
6. chama `POST /subscriptions`;
7. persiste `gateway_subscription_id`, `gateway_customer_id`, `gateway_plan_id`;
8. projeta `contract_status`, `billing_status` e `access_status` iniciais;
9. aguarda a fonte operacional real por `invoice/charge` e webhook.

Guardrails obrigatorios:

- o frontend tokeniza o cartao apenas no submit final;
- `billing_profile.gateway_customer_id` e sempre a primeira chave de identidade no provider;
- lookup por e-mail do pagador e apenas auxiliar, nunca criterio primario de merge entre organizacoes.

## 5. Cancelamento real

`CancelCurrentSubscriptionAction` deve ser dividido em dois caminhos:

- `immediately`
  - chama `DELETE /subscriptions/{id}`
  - grava cancelamento local
- `period_end`
  - grava intencao local
  - agenda job para cancelar no provider no boundary

## Mudancas recomendadas no webhook

## 1. Eventos que precisam entrar

No minimo:

- `subscription.created`
- `subscription.updated`
- `subscription.canceled`
- `invoice.created`
- `invoice.paid`
- `invoice.payment_failed`
- `invoice.canceled`
- `charge.pending`
- `charge.paid`
- `charge.payment_failed`
- `charge.refunded`
- `charge.chargedback`

Observacao importante:

- `charge.chargedback` precisa ser tratado como obrigatorio, porque a nota oficial de chargeback reforca esse estado mesmo quando outras listagens resumidas da API mostram enums mais curtas.

## 2. Nova correlacao primaria

Hoje a correlacao principal e:

- `billing_order_uuid`
- `gateway_order_id`

Para recorrencia, precisa virar:

- `gateway_subscription_id`
- `gateway_invoice_id`
- `gateway_charge_id`
- `gateway_cycle_id`
- `gateway_customer_id`

## 3. Fonte de verdade operacional

Recomendacao de mapeamento:

- `subscription.created` -> cria ou atualiza assinatura local
- `subscription.updated` -> sincroniza proximo ciclo, status e status_reason
- `subscription.canceled` -> encerra assinatura local
- `invoice.created` -> cria `subscription_cycle` e `invoice` local pendente, e, se adotado, renewal `BillingOrder`
- `invoice.paid` -> marca competencia paga
- `invoice.payment_failed` -> entra em `grace_period` ou `past_due`
- `charge.paid` -> enriquece gateway details do pagamento
- `charge.payment_failed` -> registra motivo/acquirer e falha operacional
- `charge.chargedback` -> encerra assinatura, marca invoice `failed` e payment `chargedback`

## 4. Seguranca e auditoria do recebimento

Enquanto a referencia publica V5 nao estiver clara sobre a assinatura criptografica do webhook, o recebimento deve guardar:

- corpo bruto recebido;
- `headers_json`;
- `payload_hash`;
- `hook_id` consultado depois via API, quando aplicavel.

Isso reduz risco em replay, troubleshooting e comparacao entre o evento recebido e o evento consultado em `GET /hooks/{hook_id}`.

## Mudancas recomendadas no frontend

## 1. `/plans` precisa de checkout real

Hoje o fluxo da conta e "clicar no plano". Para recorrencia real, a tela precisa coletar:

- nome do pagador
- email
- documento
- telefone
- endereco de cobranca
- dados do cartao para tokenizacao

Payload minimo recomendado para `POST /api/v1/billing/checkout`:

```json
{
  "plan_id": 1,
  "billing_cycle": "monthly",
  "payment_method": "credit_card",
  "payer": {
    "name": "Empresa X",
    "email": "[email protected]",
    "document": "12345678901",
    "phone": "5511999999999",
    "address": {
      "street": "Rua A",
      "number": "100",
      "district": "Centro",
      "zip_code": "01001000",
      "city": "Sao Paulo",
      "state": "SP",
      "country": "BR"
    }
  },
  "credit_card": {
    "card_token": "tok_xxx"
  }
}
```

## 2. Reaproveitamento recomendado

Podem ser reaproveitados diretamente:

- `apps/web/src/lib/pagarme-tokenization.ts`
- patterns de validacao do formulario de cartao em `PublicEventCheckoutPage.tsx`

## 3. Autosservico minimo esperado

Depois do go-live da recorrencia real, a area da conta deve suportar:

- contratar plano;
- ver assinatura atual;
- ver proxima cobranca;
- ver invoices reais da recorrencia;
- trocar cartao;
- trocar meio de pagamento;
- cancelar agora;
- cancelar ao fim do ciclo.

## Plano de implementacao recomendado

## Fase 0 - Travar o modelo

1. Assumir `billing_type=prepaid` como default do SaaS.
2. Assumir `plan_price -> gateway_plan_id`.
3. Assumir `subscription_cycle` como entidade local de primeira classe.
4. Assumir `invoice/charge` como verdade do pagamento.
5. Assumir `cancel_at_period_end` como regra local do produto.
6. Assumir `contract_status`, `billing_status` e `access_status` como projecoes separadas.

## Fase 1 - Schema e contratos

1. Migrations de `plan_prices`, `subscriptions`, `subscription_cycles`, `invoices`, `payments`, `billing_gateway_events` e `billing_profiles`.
2. Criar enums locais de `contract_status`, `billing_status` e `access_status`.
3. Adicionar indices unicos e colunas de payload bruto.

Desdobramento operacional:

- o backlog executavel arquivo por arquivo desta fase fica detalhado em [billing-pagarme-v5-recurring-subscriptions-execution-plan-2026-04-09.md](c:\laragon\www\eventovivo\docs\architecture\billing-pagarme-v5-recurring-subscriptions-execution-plan-2026-04-09.md), na secao `Fase 1 - Schema e read models locais`;
- a implementacao deve seguir a ordem `migrations -> models/enums -> factories -> testes`, preservando a convivencia com a trilha atual de compra unica durante toda a homologacao recorrente.

## Fase 2 - Client e provider recorrente

1. Ampliar `PagarmeClient`.
2. Padronizar `Idempotency-Key` nas escritas.
3. Criar payload factories de `plan` e `subscription`.
4. Criar `PagarmeSubscriptionGatewayService`.
5. Criar action `EnsureGatewayPlanAction`.
6. Implementar `createSubscriptionCheckout` de verdade.

## Fase 3 - Webhooks, sync e reconciliacao

1. Parse de `subscription.*`, `invoice.*` e `charge.*`.
2. Upsert de `subscription_cycle`, `invoice` e `payment` por ciclo.
3. Sync pontual por assinatura em eventos sensiveis.
4. Reconcile batch paginado por status e periodo.
5. Grace period local.
6. Renewal `BillingOrder` sintetizado, se a equipe confirmar essa estrategia.

## Fase 4 - Checkout administrativo

1. Formulario real na tela `/plans`.
2. Tokenizacao no navegador.
3. Persistencia de `gateway_subscription_id`, `gateway_customer_id`, `gateway_plan_id`.
4. Persistencia ou atualizacao do `billing_profile`.

## Fase 5 - Operacao e autosservico

1. Troca de cartao.
2. Troca de payment method.
3. Listagem da wallet do cliente.
4. Cancelamento imediato.
5. Cancelamento ao fim do ciclo.
6. Reconciliacao manual via `getSubscription`, `listCycles`, `listInvoices`, `listCharges`, `listHooks`, `getHook`, `retryHook`.

## Fase 6 - Homologacao real

1. Criar plano mensal.
2. Criar assinatura `prepaid`.
3. Validar `invoice.created` e `invoice.paid`.
4. Forcar falha de pagamento.
5. Trocar cartao.
6. Cancelar imediatamente.
7. Validar `cancel_at_period_end` local.
8. Validar replay de webhook.
9. Validar chargeback e politica de recuperacao.

## Testes obrigatorios

### Backend

- characterization test de `PlanSnapshotService` para garantir selecao por `billing_cycle`
- unit de `PagarmePlanPayloadFactory`
- unit de `PagarmeSubscriptionPayloadFactory`
- unit ou contract test de `PagarmeClient` para `plans`, `subscriptions`, `cycles`, `invoices`, `charges` e `customer cards`
- unit de `PagarmeStatusMapper` para `subscription/invoice/charge`
- unit de projecao de `contract_status`, `billing_status` e `access_status`
- feature de checkout recorrente com `POST /billing/checkout`
- feature de webhook para `subscription.created`
- feature de webhook para `invoice.created`
- feature de webhook para `invoice.paid`
- feature de webhook para `invoice.payment_failed`
- feature de webhook para `charge.chargedback`
- feature de cancelamento imediato sincronizado com provider
- feature de cancelamento ao fim do ciclo com scheduler local
- feature ou integration test de reconcile paginado por assinatura/status

### Frontend

- tokenizacao no fluxo `/plans`
- validacao de pagador e billing address
- envio apenas de `card_token`
- UX de troca de cartao
- UX de cancelamento imediato vs fim do ciclo

## Riscos e decisoes em aberto

1. Se a equipe nao quiser `BillingOrder` sintetizado por renovacao, sera preciso introduzir outra tabela de ciclo/ledger local.
2. A duracao do `grace_period` precisa ser definida pelo produto.
3. A politica de acesso apos `invoice.payment_failed` precisa ser fechada.
4. A politica de chargeback precisa ser fechada antes do go-live.
5. Pode valer avaliar `Card Updater` depois do primeiro corte estavel.

## Observacoes da doc oficial que impactam a implementacao

1. A doc publica v5 mostra `subscription.status` simplificado, entao o Eventovivo nao deve inferir demais desse campo.
2. A doc de `Listar faturas` parece ter um erro editorial: o query param `subscription_id` aparece descrito como "Codigo da fatura". Eu trataria isso como filtro por assinatura, nao como codigo da invoice.
3. Eu nao encontrei na referencia publica v5 uma rota documentada para cadastrar/editar endpoints de webhook; o que esta claramente documentado e:
   - visao geral dizendo que e possivel configurar endpoints e eventos;
   - `GET /hooks`;
   - `GET /hooks/{hook_id}`;
   - `POST /hooks/{hook_id}/retry`.
   Leitura segura: configuracao do endpoint continua sendo responsabilidade da dashboard/conta.
4. A guia publica de webhooks cita os eventos recorrentes principais, mas a nota oficial de chargeback continua relevante para consolidar `charge.chargedback` como estado obrigatorio no mapper e na automacao local.
5. A pagina de `Listar cobrancas` publica filtros/status resumidos, mas a nota oficial de chargeback diz explicitamente que `GET /orders` e `GET /charges` precisam entender o novo estado `chargedback`.
6. A busca e leitura publica da V5 nao deixaram claro um contrato atualizado de assinatura criptografica do webhook. Ate a homologacao provar o contrario, o Eventovivo deve tratar a seguranca do ingress com persistencia de corpo bruto, headers e hash de payload.
7. A pagina oficial de bibliotecas ainda aponta PHP para `PagarmeCoreApi`, e o repositorio oficial `pagarme/pagarme-core-api-php` aparece arquivado no GitHub. Isso reforca a decisao de manter um client HTTP proprio no repo.
8. A homologacao real de `POST /subscriptions` com as chaves de teste retornou `422` quando o cartao foi enviado em `card.card_id`; o shape aceito no probe foi `card_id` ou `card_token` no topo do payload. Para o Eventovivo, isso deve ser tratado como verdade operacional acima da ambiguidade atual da referencia publica.

## Recomendacao final

O Eventovivo nao precisa reescrever o modulo `Billing` para entrar em recorrencia real da Pagar.me v5.

O caminho certo e:

1. manter a tokenizacao de cartao no frontend;
2. mapear `plan_price` para `gateway_plan_id`;
3. persistir `subscription_cycle` como competencia local de primeira classe;
4. criar a assinatura via `POST /subscriptions`;
5. tratar `invoice` e `charge` como verdade operacional de pagamento;
6. separar `contract_status`, `billing_status` e `access_status` na projecao local;
7. manter um `billing_profile` estavel por organizacao;
8. usar webhook, sync pontual e reconcile batch como motor de sincronizacao;
9. preservar `cancel_at_period_end` como regra local do Eventovivo;
10. expandir o autosservico da conta para wallet, troca de cartao, cancelamento e visao dos ciclos reais.

Se essa ordem for seguida, o projeto sai de um checkout de assinatura fase-1/manual e entra em recorrencia real sem misturar o fluxo de conta com o checkout avulso por evento que ja existe.
