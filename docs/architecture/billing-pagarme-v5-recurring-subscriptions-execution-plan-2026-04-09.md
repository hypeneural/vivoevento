# Billing Pagar.me v5 Recurring Subscriptions Execution Plan - 2026-04-09

## Objetivo

Transformar [billing-pagarme-v5-recurring-subscriptions-2026-04-09.md](c:\laragon\www\eventovivo\docs\architecture\billing-pagarme-v5-recurring-subscriptions-2026-04-09.md) em um plano de execucao implementavel para sair do checkout local/manual de assinatura e entrar em recorrencia real com `plan + subscription` na Pagar.me V5.

Este plano responde 8 perguntas:

1. o que foi revalidado nesta rodada por testes e fonte oficial;
2. qual e o corte exato da primeira entrega;
3. quais arquivos precisam mudar no backend;
4. quais arquivos precisam mudar no frontend;
5. quais novos modelos e estados precisam existir;
6. como a sincronizacao por webhook e reconcile deve funcionar;
7. quais testes precisam sair de `todo()` e virar cobertura real;
8. qual e a definicao de pronto antes de ligar recorrencia real em ambiente controlado.

## Referencias primarias

- [billing-pagarme-v5-recurring-subscriptions-2026-04-09.md](c:\laragon\www\eventovivo\docs\architecture\billing-pagarme-v5-recurring-subscriptions-2026-04-09.md)
- [billing-pagarme-v5-single-event-checkout.md](c:\laragon\www\eventovivo\docs\architecture\billing-pagarme-v5-single-event-checkout.md)
- [billing-pagarme-v5-execution-plan.md](c:\laragon\www\eventovivo\docs\architecture\billing-pagarme-v5-execution-plan.md)
- [api.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\routes\api.php)
- [SubscriptionController.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Http\Controllers\SubscriptionController.php)
- [StoreSubscriptionCheckoutRequest.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Http\Requests\StoreSubscriptionCheckoutRequest.php)
- [CreateSubscriptionCheckoutAction.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Actions\CreateSubscriptionCheckoutAction.php)
- [CancelCurrentSubscriptionAction.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Actions\CancelCurrentSubscriptionAction.php)
- [ProcessBillingWebhookAction.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Actions\ProcessBillingWebhookAction.php)
- [ReceiveBillingWebhookAction.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Actions\ReceiveBillingWebhookAction.php)
- [RecordBillingGatewayWebhookAction.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Actions\RecordBillingGatewayWebhookAction.php)
- [RegisterBillingGatewayPaymentAction.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Actions\RegisterBillingGatewayPaymentAction.php)
- [PagarmeBillingGateway.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Services\Pagarme\PagarmeBillingGateway.php)
- [PagarmeClient.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Services\Pagarme\PagarmeClient.php)
- [PagarmeHomologationService.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Services\Pagarme\PagarmeHomologationService.php)
- [PagarmeStatusMapper.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Services\Pagarme\PagarmeStatusMapper.php)
- [PlanSnapshotService.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Services\PlanSnapshotService.php)
- [Subscription.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Models\Subscription.php)
- [Invoice.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Models\Invoice.php)
- [Payment.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Models\Payment.php)
- [BillingGatewayEvent.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Models\BillingGatewayEvent.php)
- [BillingOrder.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Models\BillingOrder.php)
- [BillingServiceProvider.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Providers\BillingServiceProvider.php)
- [ProcessBillingWebhookJob.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Jobs\ProcessBillingWebhookJob.php)
- [PagarmeHomologationCommand.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Console\Commands\PagarmeHomologationCommand.php)
- [Plan.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Plans\Models\Plan.php)
- [PlanPrice.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Plans\Models\PlanPrice.php)
- [PlansPage.tsx](c:\laragon\www\eventovivo\apps\web\src\modules\plans\PlansPage.tsx)
- [api.ts](c:\laragon\www\eventovivo\apps\web\src\modules\plans\api.ts)
- [pagarme-tokenization.ts](c:\laragon\www\eventovivo\apps\web\src\lib\pagarme-tokenization.ts)
- [pagarme-tokenization.test.ts](c:\laragon\www\eventovivo\apps\web\src\lib\pagarme-tokenization.test.ts)
- [PublicEventCheckoutPage.tsx](c:\laragon\www\eventovivo\apps\web\src\modules\billing\PublicEventCheckoutPage.tsx)

## Validacao executada nesta rodada

### Fonte oficial Pagar.me revalidada

Rotas e guias relidas na documentacao publica oficial:

- criar plano: `https://docs.pagar.me/reference/criar-plano-1`
- assinaturas: `https://docs.pagar.me/reference/assinaturas-1`
- criar assinatura: `https://docs.pagar.me/reference/criar-assinatura-de-plano-1`
- listar assinaturas: `https://docs.pagar.me/reference/listar-assinaturas-1`
- listar ciclos: `https://docs.pagar.me/reference/listar-ciclos-1`
- listar faturas: `https://docs.pagar.me/reference/listar-faturas-1`
- listar cobrancas: `https://docs.pagar.me/reference/listar-cobran%C3%A7as`
- listar cartoes do cliente: `https://docs.pagar.me/reference/listar-cart%C3%A3o`
- webhooks: `https://docs.pagar.me/docs/webhooks`
- eventos de webhook: `https://docs.pagar.me/reference/eventos-de-webhook-1`
- listar webhooks: `https://docs.pagar.me/reference/listar-webhooks`
- obter webhook: `https://docs.pagar.me/reference/obter-webhook`
- reenviar webhook: `https://docs.pagar.me/reference/enviar-webhook`
- chargeback: `https://docs.pagar.me/page/chargeback-novo-status-na-cobran%C3%A7a`
- tokenizacao: `https://docs.pagar.me/reference/tokeniza%C3%A7%C3%A3o-1`
- tokenizecard.js: `https://docs.pagar.me/docs/tokenizecard`
- bibliotecas: `https://docs.pagar.me/docs/bibliotecas-1`
- paginacao: `https://docs.pagar.me/reference/pagina%C3%A7%C3%A3o-1`

Confirmacoes objetivas desta rodada:

- a recorrencia continua modelada em `plan + subscription`;
- `subscription.status` continua exposto de forma simplificada, entao `invoice` e `charge` seguem como fonte financeira principal;
- `cycle` continua sendo entidade de primeira classe da API;
- `GET /customers/{customer_id}/cards` continua oficial para wallet do cliente;
- `GET /charges` continua parte do arsenal oficial de reconciliacao;
- a documentacao publica de webhooks continua deixando claro `HTTP POST`, selecao de eventos, consulta e retry;
- `charge.chargedback` continua exigindo tratamento explicito por causa da nota oficial de chargeback;
- nao apareceu rota publica dedicada para `cancel_at_period_end` nem para `pause subscription`;
- a pagina oficial de bibliotecas continua apontando PHP para `PagarmeCoreApi`, e o repositorio oficial `pagarme/pagarme-core-api-php` continua arquivado no GitHub.

Nuances importantes para nao repetir erro de implementacao:

- a estrategia padrao deve ser versionar plano externo por `plan_price`, nao depender de `updatePlan` em tempo de execucao;
- `GET /plans` e `GET /subscriptions` servem melhor para diagnose, auditoria e reconcile do que para o caminho quente do checkout;
- a guia `O que sao webhooks` continua sendo a fonte publica mais util para os eventos recorrentes, e a nota de chargeback completa `charge.chargedback`;
- o endurecimento operacional deve combinar URL secreta, Basic Auth, `payload_hash`, allowlist/IP control e dominio autorizado para tokenizacao.

### Testes backend rodados

Comando:

```bash
cd apps/api && php artisan test --filter=Billing
```

Resultado:

- `99 passed`
- `5 todos`
- `948 assertions`
- `PASS`

Leituras confirmadas pelos testes:

- o fluxo atual de billing do repo esta estavel para compra unica, webhook e assinatura local/manual;
- `Plans` e `SubscriptionController` ainda operam com checkout de assinatura local, nao com assinatura recorrente real no provider;
- o backlog recorrente continua corretamente exposto pelos `todo()` em `PagarmeClientTest`, `PagarmeStatusMapperTest` e `RecurringBillingContractTest`.

Rodada complementar focada em aprendizado operacional da integracao atual:

```bash
cd apps/api && php artisan test tests/Unit/Billing/Pagarme/PagarmeHomologationServiceTest.php tests/Feature/Billing/BillingTest.php
```

Resultado:

- `26 passed`
- `243 assertions`
- `PASS`

O que isso trava para recorrencia:

- a conta ja tem cobertura de homologacao orientada aos simuladores oficiais e aos probes diretos do gateway;
- a integracao atual ja provou retry seguro com a mesma `Idempotency-Key`;
- a conciliacao autenticada via `GET /orders/{id}` e `GET /charges/{id}` ja e padrao operacional do modulo;
- `chargeback` ja aparece como caso de reconciliacao no fluxo atual e deve ser tratado como caso de primeira classe tambem na recorrencia.

Rodada complementar focada em resiliencia do modulo:

```bash
cd apps/api && php artisan test tests/Unit/Billing/BillingResilienceConfigTest.php
```

Resultado:

- `1 passed`
- `PASS`

Leitura confirmada:

- o processamento assincrono do webhook atual ja esta travado como job unico na fila `billing`;
- esse padrao deve ser reaproveitado na recorrencia para webhook, replay e reconcile concorrente.

### Testes frontend rodados

Comando:

```bash
cd apps/web && npx.cmd vitest run src/modules/plans/PlansPage.test.tsx src/modules/billing/PublicEventCheckoutPage.test.tsx src/modules/billing/services/public-event-checkout.service.test.ts src/modules/billing/public-checkout/components/PaymentStep.test.tsx
```

Resultado:

- `4 arquivos`
- `14 testes`
- `PASS`

Leituras confirmadas pelos testes:

- [PlansPage.test.tsx](c:\laragon\www\eventovivo\apps\web\src\modules\plans\PlansPage.test.tsx) ainda valida um painel de checkout pendente com acao para abrir checkout do provider, nao um formulario real de assinatura no cartao;
- o frontend ja tem base de formulario de cartao e validacao em checkout publico que pode ser reaproveitada;
- a tokenizacao e a UX de cartao ja existem no produto, mas hoje estao orientadas a compra unica.

Rodada complementar focada em tokenizacao:

```bash
cd apps/web && npx.cmd vitest run src/lib/pagarme-tokenization.test.ts
```

Resultado:

- `1 arquivo`
- `3 testes`
- `PASS`

Leitura confirmada:

- o repo ja cobre a tokenizacao com a chave publica de teste documentada pela Pagar.me;
- a integracao do navegador ja usa `POST /tokens?appId=<public_key>` e pode ser reaproveitada na recorrencia;
- o fluxo recorrente deve nascer em cima dessa trilha validada, e nao reabrir envio de dados sensiveis ao backend.

## Restricoes oficiais validadas

- em recorrencia, `installments` deve continuar em `1`;
- `billing_type` segue com `prepaid`, `postpaid` e `exact_day`;
- `billing_days` e exigido quando `billing_type = exact_day`;
- `start_at` continua opcional na criacao da assinatura;
- alterar plano nao atualiza assinaturas existentes automaticamente;
- `invoice.created`, `invoice.paid`, `invoice.payment_failed`, `invoice.canceled`, `subscription.created`, `subscription.updated`, `subscription.canceled`, `charge.paid`, `charge.payment_failed`, `charge.pending` e `charge.refunded` continuam na lista publica de eventos;
- a nota de chargeback continua sendo a evidencia forte para incluir `charge.chargedback` na automacao;
- a API publica continua usando `page` e `size` nos recursos de listagem;
- a documentacao publica atual nao deixou claro um contrato V5 de assinatura criptografica de webhook que substitua com evidencia suficiente a semantica antiga de postback.

## Licoes reaproveitaveis da integracao Pagar.me atual

O repo ja pagou alguns custos de integracao na trilha de compra unica. O plano de recorrencia deve reaproveitar esses aprendizados, nao redescobri-los.

### 1. Homologacao ja existe como disciplina operacional

O modulo ja tem:

- [PagarmeHomologationService.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Services\Pagarme\PagarmeHomologationService.php)
- [PagarmeHomologationCommand.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Console\Commands\PagarmeHomologationCommand.php)
- cobertura em [PagarmeHomologationServiceTest.php](c:\laragon\www\eventovivo\apps\api\tests\Unit\Billing\Pagarme\PagarmeHomologationServiceTest.php)

Isso importa para recorrencia porque:

- o projeto ja valida probes diretos no gateway com as chaves de teste documentadas;
- a homologacao ja produz evidencias versionaveis em `apps/api/storage/app/pagarme-homologation/`;
- a recorrencia deve ganhar probes equivalentes para `plan`, `subscription`, `cycle`, `invoice` e `charge`, em vez de depender apenas de clique manual no painel.

### 2. Retry com a mesma Idempotency-Key ja e aprendizado provado

O checkout atual ja provou em teste e em documentacao operacional que:

- retry local seguro depende de reaproveitar a mesma `Idempotency-Key` quando a operacao semantica ainda e a mesma;
- lock local evita duplicidade concorrente;
- refresh autenticado do snapshot remoto e parte do fluxo, nao um remendo.

Para recorrencia, isso vira regra:

- `Idempotency-Key` nao pode ser derivada apenas de tenant ou `plan_price`;
- a chave deve incorporar a semantica do payload normalizado;
- criar plano, assinatura e troca de cartao precisam respeitar esse padrao desde o primeiro corte.

### 3. afterCommit e job unico ja sao padrao do modulo

O modulo `Billing` ja usa `afterCommit` no provider e o job de webhook atual ja implementa `ShouldBeUnique`.

Isso reduz risco de corrida e deve ser preservado na recorrencia:

- jobs de webhook recorrente devem continuar pos-commit;
- reconcile por `gateway_subscription_id` e `gateway_invoice_id` deve usar lock ou job unico;
- `ensurePlan`, replay de webhook e cancelamento agendado nao devem rodar em paralelo sobre o mesmo recurso.

### 4. A tokenizacao com chave publica de teste ja esta estabilizada

O repo ja cobre a tokenizacao no navegador com a public key de teste usada nas suites locais:

- `pk_test_jGWvy7PhpBukl396`
- `sk_test_7611662845434f72bdb0986b69d54ce1`

Leitura pratica:

- a recorrencia deve reutilizar a mesma trilha segura de tokenizacao;
- o cartao deve ser tokenizado apenas no submit final do checkout, nao ao abrir modal nem em step intermediario;
- o backend recorrente deve continuar recebendo apenas `card_token` ou `card_id`.

### 5. Billing profile precisa aprender com a wallet real

Na compra unica atual, o checkout monta customer/cartao para cada operacao. Na recorrencia, esse comportamento deixa de ser suficiente.

Regra recomendada:

- `billing_profile.gateway_customer_id` vira a fonte canonica da identidade de cobranca do tenant;
- e-mail do pagador pode ajudar a localizar ou enriquecer dados, mas nao deve ser a chave primaria de fusao entre organizacoes;
- wallet e assinatura nao podem dividir a mesma responsabilidade conceitual.

## Principios de execucao

- nao adaptar `orders` para imitar mensalidade; usar recorrencia nativa;
- nao misturar estado contratual, financeiro e de acesso na mesma coluna;
- persistir `subscription_cycle` local como competencia de faturamento;
- `billing_profile.gateway_customer_id` e a identidade canonica de cobranca do tenant;
- tratar webhook como trilha principal de sync, mas nunca como trilha unica;
- manter um client HTTP proprio em Laravel e subir a logica de produto para um gateway/service do modulo;
- manter `afterCommit`, lock local e job unico como padrao de resiliencia;
- derivar `Idempotency-Key` do payload semantico normalizado, nao apenas do tenant ou do plano;
- manter `plan_price -> gateway_plan_id` como relacao oficial;
- preservar `cancel_at_period_end` como regra do produto Eventovivo;
- tokenizar cartao apenas no submit final do checkout;
- endurecer operacao com URL secreta de webhook, Basic Auth, `payload_hash`, allowlist/IP control e dominio autorizado para tokenizacao;
- manter o checkout publico por evento fora desta entrega;
- priorizar backend, reconcile e webhook antes da UX final do admin.

## Status de execucao atual

- [x] analise arquitetural e validacao de fonte oficial
- [x] characterization test para selecao de `PlanSnapshotService` por `billing_cycle`
- [x] backlog recorrente exposto em `todo()` no backend
- [x] baseline real do schema e backlog da Fase 1 detalhados arquivo por arquivo
- [x] superficie HTTP recorrente do `PagarmeClient` implementada e coberta por teste
- [ ] schema recorrente real
- [ ] client/gateway recorrente Pagar.me
- [ ] webhook recorrente com `subscription.*` e `invoice.*`
- [ ] reconcile recorrente por assinatura/ciclo/fatura/cobranca
- [ ] checkout real da conta no cartao
- [ ] autosservico de cartao, meio de pagamento e cancelamento sincronizado
- [ ] homologacao de recorrencia ponta a ponta

## Escopo da primeira entrega

O que entra em `P0`:

- assinatura mensal e anual em `credit_card`;
- `billing_type = prepaid` como default;
- `subscription_cycles` locais;
- `billing_profiles` locais;
- `contract_status`, `billing_status` e `access_status`;
- `POST /subscriptions`, `GET /subscriptions/{id}`, `GET /subscriptions/{id}/cycles`, `GET /invoices`, `GET /charges`, `GET /charges/{charge_id}`;
- `PATCH /subscriptions/{id}/card`;
- `DELETE /subscriptions/{id}`;
- webhook recorrente com projection local;
- reconcile pontual e batch paginado;
- tela administrativa para contratar, ver assinatura, trocar cartao e cancelar.

O que entra em `P1`:

- wallet completa do cliente no painel;
- `PATCH /subscriptions/{id}/payment-method` quando o produto realmente abrir outros meios;
- diagnostico administrativo de hooks falhos;
- reconcile manual assistido por UI;
- melhora da operacao de inadimplencia;
- surfaces de suporte com motivo detalhado de falha.

O que fica para `P2`:

- boleto recorrente;
- debit card ou cash para assinatura;
- add-ons, usage e `minimum_price`;
- migracao assistida de assinaturas antigas para planos novos em escala;
- card updater e retentativas mais sofisticadas.

## Fase 0 - Travar contrato funcional

Objetivo:

- fechar o corte do MVP recorrente antes de abrir migrations e endpoints.

### 0.1 Fechar o contrato do produto

Decisoes a travar:

- [ ] `billing_type` default do SaaS sera `prepaid`
- [ ] ciclos comerciais suportados no MVP: `monthly`, `yearly`
- [ ] `payment_method` do MVP: `credit_card`
- [ ] `cancel_at_period_end` segue local e agenda o `DELETE` real no boundary
- [ ] `charge.chargedback` bloqueia acesso imediatamente ou apos politica definida
- [ ] `grace_period` apos `invoice.payment_failed` tera duracao fechada pelo produto

Criterio de aceite:

- existe uma tabela de decisoes unica para contrato, cobranca e acesso.

### 0.2 Fechar a matriz de endpoints Pagar.me que entram no MVP

Entram nesta entrega:

- [ ] `POST /plans`
- [ ] `GET /plans`
- [ ] `POST /subscriptions`
- [ ] `GET /subscriptions/{subscription_id}`
- [ ] `GET /subscriptions`
- [ ] `GET /subscriptions/{subscription_id}/cycles`
- [ ] `GET /invoices`
- [ ] `GET /charges`
- [ ] `GET /charges/{charge_id}`
- [ ] `PATCH /subscriptions/{subscription_id}/card`
- [ ] `DELETE /subscriptions/{subscription_id}`
- [ ] `GET /customers/{customer_id}/cards`
- [ ] `GET /hooks`
- [ ] `GET /hooks/{hook_id}`
- [ ] `POST /hooks/{hook_id}/retry`

Nao entram no MVP:

- [ ] `usage`
- [ ] `subscription items`
- [ ] `minimum_price`
- [ ] `PATCH /subscriptions/{subscription_id}/payment-method` como fluxo visivel de usuario, enquanto o MVP seguir `credit_card` apenas
- [ ] `pause`
- [ ] `cancel_at_period_end` nativo, porque nao foi achado na referencia publica

## Fase 1 - Schema e read models locais

Objetivo:

- criar o modelo local minimo para recorrencia real sem quebrar compra unica.

### 1.0 Baseline real do schema atual

Antes de abrir migration nova, a equipe precisa partir do estado real do repo hoje:

- `subscriptions` ainda nasce em `2024_01_01_000006_create_subscriptions_table.php` com um shape enxuto: `organization_id`, `plan_id`, `status`, `billing_cycle`, `starts_at`, `trial_ends_at`, `renews_at`, `ends_at`, `canceled_at`, `gateway_provider`, `gateway_customer_id` e `gateway_subscription_id`;
- `plan_prices` ainda guarda apenas `gateway_price_id`, sem `gateway_plan_id`, `billing_type` nem payload externo do plano;
- `payments` e `invoices` ainda foram desenhadas para a trilha de compra unica e dependem principalmente de `billing_order_id`;
- `billing_gateway_events` ainda correlaciona fortemente `provider_key + event_key + gateway_order_id/gateway_charge_id`, sem ids recorrentes como `gateway_subscription_id` ou `gateway_invoice_id`;
- `billing_orders` ja tem boa parte do trilho operacional de idempotencia e charge, mas ainda nao conhece `subscription_id` nem `order_kind` de renovacao;
- nao existe tabela local para `subscription_cycles`;
- nao existe tabela local para `billing_profiles`;
- tambem nao existem factories dedicadas para `Subscription`, `SubscriptionCycle` ou `BillingProfile`.

Regras de transicao obrigatorias:

- nenhuma migration da Fase 1 deve reaproveitar semanticamente `plan_prices.gateway_price_id` para guardar `plan_...`;
- os campos novos de recorrencia entram primeiro como `nullable` sempre que o rollout precisar conviver com compra unica e assinatura local antiga;
- a trilha atual de compra unica por `billing_order_id` continua funcionando durante a migracao, mesmo que `invoice` e `payment` passem a ganhar vinculos adicionais por `subscription_id` e `subscription_cycle_id`;
- a migracao do read model local deve ser additive-first, deixando remocao ou endurecimento de nulidade para uma janela posterior, depois da homologacao recorrente.

### 1.1 Migrations

Arquivos a criar:

- [x] `apps/api/database/migrations/*_add_gateway_plan_fields_to_plan_prices_table.php`
- [x] `apps/api/database/migrations/*_add_recurring_projection_fields_to_subscriptions_table.php`
- [ ] `apps/api/database/migrations/*_create_subscription_cycles_table.php`
- [ ] `apps/api/database/migrations/*_add_recurring_fields_to_invoices_table.php`
- [ ] `apps/api/database/migrations/*_add_recurring_fields_to_payments_table.php`
- [ ] `apps/api/database/migrations/*_add_recurring_fields_to_billing_gateway_events_table.php`
- [ ] `apps/api/database/migrations/*_add_recurring_fields_to_billing_orders_table.php`
- [x] `apps/api/database/migrations/*_create_billing_profiles_table.php`

Sequencia recomendada de migrations:

1. [x] expandir `plan_prices`, porque `gateway_plan_id` e prerequisito do checkout recorrente;
2. [x] expandir `subscriptions`, porque o contrato local precisa aceitar `plan_price_id`, statuses separados e janelas de periodo;
3. [ ] criar `subscription_cycles`, porque `invoice` e `payment` vao depender desse vinculo;
4. [ ] expandir `invoices` e `payments`, mantendo `billing_order_id` para compatibilidade;
5. [ ] expandir `billing_gateway_events` para ids recorrentes, `payload_hash` e `hook_id`;
6. [ ] expandir `billing_orders` com `subscription_id` e `order_kind`;
7. [x] criar `billing_profiles`, porque o fluxo recorrente precisa de identidade estavel antes da UX final do admin.

Sugestao de nomes concretos:

- [ ] `2026_04_09_120000_add_gateway_plan_fields_to_plan_prices_table.php`
- [ ] `2026_04_09_121000_add_recurring_projection_fields_to_subscriptions_table.php`
- [ ] `2026_04_09_122000_create_subscription_cycles_table.php`
- [ ] `2026_04_09_123000_add_recurring_fields_to_invoices_table.php`
- [ ] `2026_04_09_124000_add_recurring_fields_to_payments_table.php`
- [ ] `2026_04_09_125000_add_recurring_fields_to_billing_gateway_events_table.php`
- [ ] `2026_04_09_126000_add_recurring_fields_to_billing_orders_table.php`
- [ ] `2026_04_09_127000_create_billing_profiles_table.php`

Campos minimos a introduzir:

- `plan_prices.gateway_plan_id`
- `plan_prices.gateway_plan_payload_json`
- `plan_prices.billing_type`
- `plan_prices.billing_day`
- `plan_prices.trial_period_days`
- `plan_prices.payment_methods_json`
- `subscriptions.plan_price_id`
- `subscriptions.gateway_plan_id`
- `subscriptions.gateway_card_id`
- `subscriptions.payment_method`
- `subscriptions.billing_type`
- `subscriptions.contract_status`
- `subscriptions.billing_status`
- `subscriptions.access_status`
- `subscriptions.gateway_status_reason`
- `subscriptions.current_period_started_at`
- `subscriptions.current_period_ends_at`
- `subscriptions.next_billing_at`
- `subscriptions.cancel_at_period_end`
- `subscriptions.cancel_requested_at`
- `subscriptions.metadata_json`
- `subscription_cycles.subscription_id`
- `subscription_cycles.gateway_cycle_id`
- `subscription_cycles.status`
- `subscription_cycles.billing_at`
- `subscription_cycles.period_start_at`
- `subscription_cycles.period_end_at`
- `subscription_cycles.closed_at`
- `subscription_cycles.raw_gateway_json`
- `invoices.subscription_id`
- `invoices.subscription_cycle_id`
- `invoices.gateway_invoice_id`
- `invoices.gateway_charge_id`
- `invoices.gateway_cycle_id`
- `invoices.gateway_status`
- `invoices.period_start_at`
- `invoices.period_end_at`
- `invoices.raw_gateway_json`
- `payments.subscription_id`
- `payments.invoice_id`
- `payments.gateway_invoice_id`
- `payments.gateway_charge_status`
- `payments.card_brand`
- `payments.card_last_four`
- `payments.attempt_sequence`
- `payments.gateway_response_json`
- `billing_gateway_events.hook_id`
- `billing_gateway_events.gateway_subscription_id`
- `billing_gateway_events.gateway_invoice_id`
- `billing_gateway_events.gateway_cycle_id`
- `billing_gateway_events.gateway_customer_id`
- `billing_gateway_events.headers_json`
- `billing_gateway_events.payload_hash`
- `billing_orders.subscription_id`
- `billing_orders.order_kind`
- `billing_profiles.organization_id`
- `billing_profiles.gateway_provider`
- `billing_profiles.gateway_customer_id`
- `billing_profiles.gateway_default_card_id`
- `billing_profiles.payer_name`
- `billing_profiles.payer_email`
- `billing_profiles.payer_document`
- `billing_profiles.payer_phone`
- `billing_profiles.billing_address_json`
- `billing_profiles.metadata_json`

Regra funcional obrigatoria:

- `billing_profiles.gateway_customer_id` e a chave primaria de identidade de cobranca do tenant no provider;
- resolucao por e-mail do pagador e apenas auxiliar, nunca o primeiro criterio de fusao entre organizacoes.

Detalhes de modelagem para evitar regressao:

- `subscriptions.plan_id` continua coexistindo com `subscriptions.plan_price_id` no primeiro corte para nao quebrar entitlements e queries existentes que ainda leem o plano raiz;
- `subscriptions.status` pode coexistir temporariamente com `contract_status`, `billing_status` e `access_status`, mas a projecao recorrente nova deve escrever nos tres campos separados desde o primeiro fluxo real;
- `invoices.snapshot_json` e `payments.raw_payload_json` continuam uteis para backward compatibility, mas a recorrencia nova deve preferir `raw_gateway_json` e chaves externas normalizadas;
- `billing_gateway_events.headers_json` ja existe hoje; a migration recorrente precisa apenas complementar com `payload_hash`, `hook_id` e ids recorrentes, sem quebrar o ingest atual.

Indices minimos:

- [x] `UNIQUE(plan_prices.gateway_plan_id)`
- [x] `UNIQUE(subscriptions.gateway_subscription_id)`
- [ ] `UNIQUE(subscription_cycles.gateway_cycle_id)`
- [ ] `UNIQUE(invoices.gateway_invoice_id)`
- [ ] indice `(organization_id, contract_status)` em `subscriptions`
- [ ] indice `(subscription_id, period_end_at)` em `subscription_cycles`

### 1.2 Models, relations e enums

Arquivos a criar:

- [ ] `apps/api/app/Modules/Billing/Models/SubscriptionCycle.php`
- [x] `apps/api/app/Modules/Billing/Models/BillingProfile.php`
- [ ] `apps/api/database/factories/SubscriptionFactory.php`
- [ ] `apps/api/database/factories/SubscriptionCycleFactory.php`
- [x] `apps/api/database/factories/BillingProfileFactory.php`
- [ ] `apps/api/app/Modules/Billing/Enums/SubscriptionContractStatus.php`
- [ ] `apps/api/app/Modules/Billing/Enums/SubscriptionBillingStatus.php`
- [ ] `apps/api/app/Modules/Billing/Enums/SubscriptionAccessStatus.php`

Arquivos a alterar:

- [x] [Subscription.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Models\Subscription.php)
- [ ] [Invoice.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Models\Invoice.php)
- [ ] [Payment.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Models\Payment.php)
- [ ] [BillingGatewayEvent.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Models\BillingGatewayEvent.php)
- [ ] [BillingOrder.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Models\BillingOrder.php)
- [x] [PlanPrice.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Plans\Models\PlanPrice.php)
- [ ] `apps/api/database/factories/InvoiceFactory.php`
- [ ] `apps/api/database/factories/PaymentFactory.php`
- [ ] `apps/api/database/factories/BillingGatewayEventFactory.php`
- [ ] `apps/api/database/factories/BillingOrderFactory.php`

Relacoes minimas a expor na Fase 1:

- [ ] `Subscription belongsTo Plan`
- [ ] `Subscription belongsTo PlanPrice`
- [ ] `Subscription hasMany SubscriptionCycle`
- [ ] `SubscriptionCycle belongsTo Subscription`
- [ ] `SubscriptionCycle hasMany Invoice`
- [ ] `Invoice belongsTo Subscription`
- [ ] `Invoice belongsTo SubscriptionCycle`
- [ ] `Invoice belongsTo BillingOrder` mantendo compatibilidade com compra unica
- [ ] `Payment belongsTo Subscription`
- [ ] `Payment belongsTo Invoice`
- [ ] `Payment belongsTo BillingOrder` mantendo compatibilidade com compra unica
- [ ] `BillingGatewayEvent belongsTo BillingOrder` mantendo ingest atual
- [ ] `BillingOrder belongsTo Subscription` para `signup`, `renewal` e `manual_rebill`

Decisoes de cast para travar desde ja:

- [ ] `contract_status`, `billing_status` e `access_status` devem usar enums dedicados
- [ ] `billing_profiles.billing_address_json`, `billing_profiles.metadata_json`, `subscription_cycles.raw_gateway_json`, `invoices.raw_gateway_json` e `payments.gateway_response_json` devem ser `array`
- [ ] timestamps recorrentes novos entram com cast explicito em todas as models alteradas

Criterio de aceite:

- as models ja suportam navegar `subscription -> cycles -> invoices -> payments` sem heuristica em `snapshot_json`.

### 1.3 Testes da fase

- [ ] transformar [RecurringBillingContractTest.php](c:\laragon\www\eventovivo\apps\api\tests\Feature\Billing\RecurringBillingContractTest.php) em teste real para `subscription_cycle`
- [ ] adicionar teste de model/relationship para `billing_profile`
- [ ] criar `apps/api/tests/Unit/Billing/Models/SubscriptionRelationsTest.php`
- [ ] criar `apps/api/tests/Unit/Billing/Models/SubscriptionCycleRelationsTest.php`
- [ ] criar `apps/api/tests/Unit/Billing/Models/BillingProfileTest.php`
- [ ] criar testes de factory para `SubscriptionFactory`, `SubscriptionCycleFactory` e `BillingProfileFactory`
- [ ] ajustar factories atuais de `Invoice`, `Payment`, `BillingGatewayEvent` e `BillingOrder` para aceitar contexto recorrente sem quebrar compra unica
- [ ] manter [BillingResilienceConfigTest.php](c:\laragon\www\eventovivo\apps\api\tests\Unit\Billing\BillingResilienceConfigTest.php) verde como guardrail de resiliência do modulo
- [x] manter [PlanSnapshotServiceTest.php](c:\laragon\www\eventovivo\apps\api\tests\Unit\Billing\PlanSnapshotServiceTest.php) verde

Checklist de saida da Fase 1:

- [ ] migrations sobem em banco limpo e em banco com dados da trilha atual;
- [ ] nenhum teste da compra unica quebra por causa da coexistencia com colunas novas;
- [ ] existe pelo menos um teste que cria `subscription -> cycle -> invoice -> payment` inteiramente no banco local;
- [x] existe pelo menos um teste que garante que `billing_profile.gateway_customer_id` e mais prioritario do que qualquer lookup por e-mail.

### 1.4 Backlog executavel arquivo por arquivo

#### Migrations

- [x] `apps/api/database/migrations/2026_04_09_180000_expand_plan_prices_for_recurring_gateway_plans.php`
  Objetivo: adicionar `gateway_plan_id`, `gateway_plan_payload_json`, `billing_type`, `billing_day`, `trial_period_days` e `payment_methods_json`, mantendo `gateway_price_id` intacto para compatibilidade retroativa.
- [x] `apps/api/database/migrations/2026_04_09_181000_expand_subscriptions_for_recurring_gateway_state.php`
  Objetivo: introduzir `plan_price_id`, `payment_method`, `gateway_plan_id`, `gateway_card_id`, `gateway_status_reason`, `billing_type`, `contract_status`, `billing_status`, `access_status`, `current_period_started_at`, `current_period_ends_at`, `next_billing_at`, `cancel_at_period_end`, `cancel_requested_at` e `metadata_json`.
- [ ] `apps/api/database/migrations/2026_04_09_122000_create_subscription_cycles_table.php`
  Objetivo: criar a competencia recorrente local com `subscription_id`, `gateway_cycle_id`, `status`, `billing_at`, `period_start_at`, `period_end_at`, `closed_at` e `raw_gateway_json`.
- [ ] `apps/api/database/migrations/2026_04_09_123000_add_recurring_fields_to_invoices_table.php`
  Objetivo: ligar `invoices` a `subscription_id` e `subscription_cycle_id`, adicionando `gateway_invoice_id`, `gateway_charge_id`, `gateway_cycle_id`, `gateway_status`, `period_start_at`, `period_end_at` e `raw_gateway_json` sem remover `billing_order_id`.
- [ ] `apps/api/database/migrations/2026_04_09_124000_add_recurring_fields_to_payments_table.php`
  Objetivo: ligar `payments` a `subscription_id` e `invoice_id`, adicionando `gateway_invoice_id`, `gateway_charge_status`, `card_brand`, `card_last_four`, `attempt_sequence` e enriquecendo `gateway_response_json` para a trilha recorrente.
- [ ] `apps/api/database/migrations/2026_04_09_125000_add_recurring_fields_to_billing_gateway_events_table.php`
  Objetivo: adicionar `hook_id`, `gateway_subscription_id`, `gateway_invoice_id`, `gateway_cycle_id`, `gateway_customer_id` e `payload_hash`, preservando `headers_json`, `gateway_order_id` e `gateway_charge_id` da compra unica.
- [ ] `apps/api/database/migrations/2026_04_09_126000_add_recurring_fields_to_billing_orders_table.php`
  Objetivo: adicionar `subscription_id` e `order_kind` para diferenciar `signup`, `renewal` e `manual_rebill`, sem quebrar `event_package`.
- [x] `apps/api/database/migrations/2026_04_09_182000_create_billing_profiles_table.php`
  Objetivo: criar a identidade estavel de cobranca por organizacao com `gateway_customer_id`, cartao default, dados do pagador e endereco de cobranca.

#### Models

- [ ] [Subscription.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Models\Subscription.php)
  Ajustes: adicionar fillable/casts dos novos campos recorrentes, relacionamentos com `PlanPrice` e `SubscriptionCycle`, e preparar coexistencia temporaria entre `status` legado e `contract_status`/`billing_status`/`access_status`.
- [ ] `apps/api/app/Modules/Billing/Models/SubscriptionCycle.php`
  Novo model: representar a competencia local, com casts de periodo e relacoes para `Subscription` e `Invoice`.
- [ ] [Invoice.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Models\Invoice.php)
  Ajustes: adicionar fillable/casts para ids recorrentes e relacoes com `Subscription`, `SubscriptionCycle` e `BillingOrder`.
- [ ] [Payment.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Models\Payment.php)
  Ajustes: adicionar fillable/casts recorrentes e relacoes com `Subscription`, `Invoice` e `BillingOrder`.
- [ ] [BillingGatewayEvent.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Models\BillingGatewayEvent.php)
  Ajustes: adicionar fillable/casts para `hook_id`, ids recorrentes e `payload_hash`, sem perder o path atual por `billing_order_id`.
- [ ] [BillingOrder.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Models\BillingOrder.php)
  Ajustes: adicionar `subscription_id`, `order_kind` e relacao com `Subscription`.
- [ ] [PlanPrice.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Plans\Models\PlanPrice.php)
  Ajustes: adicionar fillable/casts para `gateway_plan_id`, payload do plano e configuracoes comerciais da recorrencia.
- [x] `apps/api/app/Modules/Billing/Models/BillingProfile.php`
  Novo model: representar o profile estavel de cobranca do tenant, com casts de `billing_address_json` e `metadata_json`.

#### Enums

- [ ] `apps/api/app/Modules/Billing/Enums/SubscriptionContractStatus.php`
  Conteudo: `pending_activation`, `trialing`, `active`, `future`, `canceled`, `expired`.
- [ ] `apps/api/app/Modules/Billing/Enums/SubscriptionBillingStatus.php`
  Conteudo: `pending`, `paid`, `grace_period`, `past_due`, `failed`, `chargedback`, `refunded`.
- [ ] `apps/api/app/Modules/Billing/Enums/SubscriptionAccessStatus.php`
  Conteudo: `provisioning`, `enabled`, `grace_period`, `blocked`, `canceled`.

#### Factories

- [ ] `apps/api/database/factories/SubscriptionFactory.php`
  Objetivo: cobrir o novo `Subscription` recorrente com defaults compatíveis para `organization_id`, `plan_id`, `plan_price_id` e statuses separados.
- [ ] `apps/api/database/factories/SubscriptionCycleFactory.php`
  Objetivo: criar ciclos locais com periodo coerente e `gateway_cycle_id` fake unico.
- [x] `apps/api/database/factories/BillingProfileFactory.php`
  Objetivo: criar profiles de cobranca completos para testes de lookup por `gateway_customer_id`.
- [ ] `apps/api/database/factories/InvoiceFactory.php`
  Ajustes: permitir contexto recorrente opcional com `subscription_id` e `subscription_cycle_id`, sem exigir abandono de `billing_order_id`.
- [ ] `apps/api/database/factories/PaymentFactory.php`
  Ajustes: permitir contexto recorrente opcional com `subscription_id`, `invoice_id`, `gateway_invoice_id` e dados de cartao.
- [ ] `apps/api/database/factories/BillingGatewayEventFactory.php`
  Ajustes: aceitar ids recorrentes e payloads de webhook para `subscription.*`, `invoice.*` e `charge.*`.
- [ ] `apps/api/database/factories/BillingOrderFactory.php`
  Ajustes: aceitar `subscription_id` e `order_kind`, preservando comportamento atual de compra unica.

#### Testes

- [ ] [RecurringBillingContractTest.php](c:\laragon\www\eventovivo\apps\api\tests\Feature\Billing\RecurringBillingContractTest.php)
  Meta: os checks de `billing_profile` e prioridade de `gateway_customer_id` ja sairam de `todo()`. Ainda faltam `subscription_cycle` e deduplicacao por ids recorrentes.
- [ ] `apps/api/tests/Unit/Billing/Models/SubscriptionRelationsTest.php`
  Meta: travar relacoes `Subscription -> PlanPrice -> SubscriptionCycle`.
- [ ] `apps/api/tests/Unit/Billing/Models/SubscriptionCycleRelationsTest.php`
  Meta: travar `SubscriptionCycle -> Invoice` e periodos recorrentes.
- [ ] `apps/api/tests/Unit/Billing/Models/BillingProfileTest.php`
  Meta: travar casts e identidade canonica do provider.
- [ ] [BillingResilienceConfigTest.php](c:\laragon\www\eventovivo\apps\api\tests\Unit\Billing\BillingResilienceConfigTest.php)
  Meta: permanecer verde durante a transicao.
- [ ] [PlanSnapshotServiceTest.php](c:\laragon\www\eventovivo\apps\api\tests\Unit\Billing\PlanSnapshotServiceTest.php)
  Meta: permanecer verde durante a coexistencia de `gateway_price_id` e `gateway_plan_id`.
- [ ] adaptar os testes de factories e feature atuais que assumem `Invoice` e `Payment` exclusivamente por `billing_order_id`.
  Meta: manter a trilha de compra unica intacta enquanto a recorrencia entra no schema.

## Fase 2 - Client HTTP Pagar.me e gateway recorrente

Objetivo:

- colocar no modulo a superficie HTTP necessaria para recorrencia real, com idempotencia e paginacao padronizadas.

### 2.1 Expandir PagarmeClient

Arquivos a alterar:

- [x] [PagarmeClient.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Services\Pagarme\PagarmeClient.php)

Metodos implementados nesta rodada:

- [x] `createPlan(array $payload, ?string $idempotencyKey = null): array`
- [x] `listPlans(array $query = []): array`
- [x] `getPlan(string $planId): array`
- [x] `createSubscription(array $payload, ?string $idempotencyKey = null): array`
- [x] `getSubscription(string $subscriptionId): array`
- [x] `listSubscriptions(array $query = []): array`
- [x] `listSubscriptionCycles(string $subscriptionId, array $query = []): array`
- [x] `listInvoices(array $query = []): array`
- [x] `listCharges(array $query = []): array`
- [x] `getCharge(string $chargeId): array`
- [x] `cancelSubscription(string $subscriptionId, array $payload = []): array`
- [x] `updateSubscriptionCard(string $subscriptionId, array $payload, ?string $idempotencyKey = null): array`
- [x] `updateSubscriptionPaymentMethod(string $subscriptionId, array $payload, ?string $idempotencyKey = null): array`
- [x] `updateSubscriptionStartAt(string $subscriptionId, array $payload, ?string $idempotencyKey = null): array`
- [x] `updateSubscriptionMetadata(string $subscriptionId, array $payload, ?string $idempotencyKey = null): array`
- [x] `getHook(string $hookId): array`
- [x] `listCustomerCards(string $customerId): array`

Regra transversal:

- [x] toda escrita relevante do client recorrente aceita `Idempotency-Key`
- [ ] toda listagem aceita `page` e `size`
- [x] a `Idempotency-Key` de plano e assinatura nasce do payload semantico normalizado no gateway recorrente

Observacao de uso:

- `listPlans` e `listSubscriptions` nao devem virar dependencia do caminho quente do checkout;
- essas listagens entram como apoio de diagnose, auditoria e reconcile.

### 2.2 Criar payload factories

Arquivos a criar:

- [x] `apps/api/app/Modules/Billing/Services/Pagarme/PagarmePlanPayloadFactory.php`
- [x] `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeSubscriptionPayloadFactory.php`

Responsabilidades:

- [x] mapear `PlanPrice` para payload de `POST /plans`
- [x] mapear `BillingProfile + BillingOrder + PlanPrice` para `POST /subscriptions`
- [x] padronizar `metadata` de tenant, organization e `plan_price`
- [x] isolar regras de `billing_type`, `billing_day`, `installments = 1`

Regra de produto:

- versionamento de plano externo por `plan_price` e o caminho default;
- update de plano em place entra apenas como excecao administrativa conscientemente controlada.

### 2.3 Criar gateway/service recorrente

Arquivos a criar:

- [x] `apps/api/app/Modules/Billing/Services/BillingSubscriptionGatewayInterface.php`
- [x] `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeSubscriptionGatewayService.php`

Responsabilidades:

- [x] `ensurePlan`
- [x] `createSubscription`
- [ ] `getSubscription`
- [ ] `listSubscriptionCycles`
- [ ] `listInvoices`
- [ ] `listCharges`
- [ ] `cancelSubscription`
- [ ] `updateSubscriptionCard`
- [ ] `updateSubscriptionPaymentMethod`
- [ ] `updateSubscriptionStartAt`
- [ ] `updateSubscriptionMetadata`

### 2.4 Ajustar provider atual

Arquivos a alterar:

- [ ] [PagarmeBillingGateway.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Services\Pagarme\PagarmeBillingGateway.php)
- [ ] [BillingGatewayManager.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Services\BillingGatewayManager.php)
- [ ] [BillingGatewayInterface.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Services\BillingGatewayInterface.php)
- [ ] [billing.php](c:\laragon\www\eventovivo\apps\api\config\billing.php)
- [x] [BillingServiceProvider.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Providers\BillingServiceProvider.php)

Decisao recomendada:

- manter `BillingGatewayInterface` para compra unica
- introduzir interface/servico paralelo para assinatura recorrente
- nao forcar o mesmo contrato de `order` a representar `subscription`

### 2.5 Testes da fase

- [x] tirar os `todo()` de [PagarmeClientTest.php](c:\laragon\www\eventovivo\apps\api\tests\Unit\Billing\Pagarme\PagarmeClientTest.php)
- [x] criar `PagarmePlanPayloadFactoryTest.php`
- [x] criar `PagarmeSubscriptionPayloadFactoryTest.php`
- [x] cobrir idempotencia nas escritas recorrentes do `PagarmeClient`

## Fase 3 - Orquestracao de checkout e lifecycle da assinatura

Objetivo:

- transformar o checkout da conta em criacao real de assinatura no provider.

### 3.1 Expandir request e controller

Arquivos a alterar:

- [x] [StoreSubscriptionCheckoutRequest.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Http\Requests\StoreSubscriptionCheckoutRequest.php)
- [x] [SubscriptionController.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Http\Controllers\SubscriptionController.php)
- [ ] [api.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\routes\api.php)

Payload novo minimo:

- `plan_id`
- `billing_cycle`
- `payment_method`
- `payer.name`
- `payer.email`
- `payer.document`
- `payer.phone`
- `payer.address.*`
- `credit_card.card_token`

Rotas novas ou ajustadas:

- [ ] `GET /api/v1/billing/subscription`
- [ ] `GET /api/v1/billing/subscription/cycles`
- [ ] `GET /api/v1/billing/subscription/cards`
- [ ] `PATCH /api/v1/billing/subscription/card`
- [ ] `PATCH /api/v1/billing/subscription/payment-method` apenas se a equipe decidir expor a extensibilidade ja no backend, mesmo sem UI no P0
- [ ] `GET /api/v1/billing/profile`
- [ ] `PATCH /api/v1/billing/profile`

### 3.2 Reescrever CreateSubscriptionCheckoutAction

Arquivos a alterar:

- [x] [CreateSubscriptionCheckoutAction.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Actions\CreateSubscriptionCheckoutAction.php)
- [x] [PlanSnapshotService.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Services\PlanSnapshotService.php)

Fluxo alvo:

1. resolve `Plan + PlanPrice`
2. garante `gateway_plan_id` por `plan_price`
3. cria ou atualiza `billing_profile`
4. cria `BillingOrder` de `signup`
5. cria assinatura real na Pagar.me
6. persiste `gateway_subscription_id`, `gateway_customer_id`, `gateway_plan_id`, `gateway_card_id`
7. projeta `contract_status`, `billing_status` e `access_status`
8. responde com estado inicial local e aguarda `invoice/charge` por webhook/reconcile

Regra adicional:

- a resolucao do customer no provider tenta primeiro `billing_profile.gateway_customer_id`;
- e-mail do pagador entra apenas como apoio de lookup e enriquecimento, nunca como chave primaria de merge entre organizacoes.

### 3.3 Ajustar cancelamento real

Arquivos a alterar:

- [ ] [CancelCurrentSubscriptionAction.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Actions\CancelCurrentSubscriptionAction.php)
- [ ] [CancelCurrentSubscriptionRequest.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Http\Requests\CancelCurrentSubscriptionRequest.php)

Fluxos a suportar:

- [ ] `immediately`: chama `DELETE /subscriptions/{id}` e projeta cancelamento local
- [ ] `period_end`: grava intencao local e agenda job para efetivar no boundary

### 3.4 Card update e payment method

Arquivos a criar:

- [ ] `apps/api/app/Modules/Billing/Actions/UpdateSubscriptionCardAction.php`
- [ ] `apps/api/app/Modules/Billing/Actions/UpdateSubscriptionPaymentMethodAction.php`
- [ ] `apps/api/app/Modules/Billing/Actions/UpsertBillingProfileAction.php`
- [ ] `apps/api/app/Modules/Billing/Http/Requests/UpdateSubscriptionCardRequest.php`
- [ ] `apps/api/app/Modules/Billing/Http/Requests/UpdateSubscriptionPaymentMethodRequest.php`

### 3.5 Testes da fase

- [ ] transformar o fluxo atual de `it creates subscription via checkout` em cobertura de assinatura real
- [x] adicionar feature test de checkout recorrente com `card_token`
- [ ] adicionar feature test de cancelamento imediato sincronizado com provider
- [ ] adicionar feature test de cancelamento ao fim do ciclo com job agendado

## Fase 4 - Webhook recorrente, projection e reconcile

Objetivo:

- fazer `subscription`, `subscription_cycle`, `invoice` e `payment` convergirem para o estado real do provider.

### 4.1 Persistencia e seguranca do ingress

Arquivos a alterar:

- [ ] [BillingWebhookController.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Http\Controllers\BillingWebhookController.php)
- [ ] [ReceiveBillingWebhookAction.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Actions\ReceiveBillingWebhookAction.php)
- [ ] [RecordBillingGatewayWebhookAction.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Actions\RecordBillingGatewayWebhookAction.php)
- [ ] [VerifyBillingWebhookBasicAuthAction.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Actions\VerifyBillingWebhookBasicAuthAction.php)

Regras:

- [ ] guardar `raw body`
- [ ] guardar `headers_json`
- [ ] guardar `payload_hash`
- [ ] guardar `hook_id` quando consultado depois
- [ ] manter autenticacao atual por basic auth enquanto a assinatura criptografica V5 nao estiver fechada com evidencia oficial ou homologacao controlada
- [ ] prever URL secreta/imutavel e endurecimento com allowlist/IP control do lado operacional

### 4.2 Expandir parse do provider

Arquivos a alterar:

- [ ] [PagarmeBillingGateway.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Services\Pagarme\PagarmeBillingGateway.php)
- [ ] [PagarmeStatusMapper.php](c:\laragon\www\eventovivo\apps\api\app\Modules\Billing\Services\Pagarme\PagarmeStatusMapper.php)

Eventos minimos:

- [ ] `subscription.created`
- [ ] `subscription.updated`
- [ ] `subscription.canceled`
- [ ] `invoice.created`
- [ ] `invoice.paid`
- [ ] `invoice.payment_failed`
- [ ] `invoice.canceled`
- [ ] `charge.pending`
- [ ] `charge.paid`
- [ ] `charge.payment_failed`
- [ ] `charge.refunded`
- [ ] `charge.chargedback`

Correlacoes novas:

- [ ] `gateway_subscription_id`
- [ ] `gateway_invoice_id`
- [ ] `gateway_charge_id`
- [ ] `gateway_cycle_id`
- [ ] `gateway_customer_id`

### 4.3 Projection local

Arquivos a criar:

- [ ] `apps/api/app/Modules/Billing/Actions/SyncGatewaySubscriptionAction.php`
- [ ] `apps/api/app/Modules/Billing/Actions/SyncSubscriptionCycleAction.php`
- [ ] `apps/api/app/Modules/Billing/Actions/UpsertSubscriptionInvoiceAction.php`
- [ ] `apps/api/app/Modules/Billing/Actions/ReconcileSubscriptionChargeAction.php`
- [ ] `apps/api/app/Modules/Billing/Jobs/ReconcileSubscriptionBillingJob.php`

Fluxo alvo:

- `subscription.created` cria ou atualiza assinatura local
- `subscription.updated` atualiza proximo ciclo, status e metadados
- `subscription.canceled` encerra contrato local
- `invoice.created` cria `subscription_cycle` e `invoice`
- `invoice.paid` marca ciclo pago
- `invoice.payment_failed` projeta `billing_status = grace_period|past_due`
- `charge.paid` enriquece `payment`
- `charge.payment_failed` registra detalhe operacional
- `charge.chargedback` encerra contrato, marca invoice `failed`, bloqueia acesso conforme politica

### 4.4 Reconcile pontual e batch

Arquivos a criar:

- [ ] `apps/api/app/Modules/Billing/Console/Commands/ReconcileRecurringBillingCommand.php`
- [ ] agendamento correspondente em `apps/api/routes/console.php`

Algoritmo minimo:

- [ ] reconcile pontual apos evento sensivel
- [ ] reconcile batch paginado por assinatura/status/periodo
- [ ] ability de consultar `getSubscription`, `listCycles`, `listInvoices`, `listCharges`
- [ ] diagnostico de hooks falhos via `listHooks`, `getHook`, `retryHook`
- [ ] jobs batch com `withoutOverlapping` e `onOneServer` quando o ambiente tiver mais de uma instancia da aplicacao

Controles de concorrencia recomendados:

- [ ] `ShouldBeUnique` ou lock equivalente por `gateway_subscription_id` para sync concorrente
- [ ] `ShouldBeUnique` ou lock equivalente por `gateway_invoice_id` para replay/reconcile do mesmo ciclo
- [ ] cancelamento agendado com lock por assinatura

### 4.5 Testes da fase

- [ ] tirar o `todo()` de [PagarmeStatusMapperTest.php](c:\laragon\www\eventovivo\apps\api\tests\Unit\Billing\Pagarme\PagarmeStatusMapperTest.php)
- [ ] adicionar feature de `subscription.created`
- [ ] adicionar feature de `invoice.created`
- [ ] adicionar feature de `invoice.paid`
- [ ] adicionar feature de `invoice.payment_failed`
- [ ] adicionar feature de `charge.chargedback`
- [ ] adicionar integration/feature de reconcile paginado

## Fase 5 - Frontend administrativo e autosservico

Objetivo:

- trocar a UX atual de checkout pendente por uma UX real de assinatura recorrente da conta.

### 5.1 API client e contratos do frontend

Arquivos a alterar:

- [ ] [api.ts](c:\laragon\www\eventovivo\apps\web\src\modules\plans\api.ts)
- [ ] `apps/web/src/shared/types` ou `packages/shared-types/src/billing.ts` se a equipe quiser centralizar tipos

Metas:

- [ ] novo payload de checkout recorrente
- [ ] endpoints de leitura de assinatura atual, ciclos, invoices e cartoes
- [ ] endpoints de troca de cartao e cancelamento

### 5.2 Reescrever a tela /plans para checkout real

Arquivos a alterar:

- [ ] [PlansPage.tsx](c:\laragon\www\eventovivo\apps\web\src\modules\plans\PlansPage.tsx)
- [ ] [PlansPage.test.tsx](c:\laragon\www\eventovivo\apps\web\src\modules\plans\PlansPage.test.tsx)
- [ ] [pagarme-tokenization.ts](c:\laragon\www\eventovivo\apps\web\src\lib\pagarme-tokenization.ts)

Reaproveitar do checkout publico:

- [ ] [PublicEventCheckoutPage.tsx](c:\laragon\www\eventovivo\apps\web\src\modules\billing\PublicEventCheckoutPage.tsx)
- [ ] `apps/web/src/modules/billing/public-checkout/components/PaymentStep.tsx`
- [ ] `apps/web/src/modules/billing/public-checkout/support/checkoutFormSchema.ts`

Comportamentos alvo:

- [ ] coletar `payer`
- [ ] coletar `billing_address`
- [ ] tokenizar cartao no navegador apenas no submit final
- [ ] enviar apenas `card_token`
- [ ] exibir assinatura atual, proxima cobranca e status

Guardrails adicionais:

- [ ] nao gerar `card_token` quando o usuario apenas navega entre steps
- [ ] falha de tokenizacao precisa ser tratada no mesmo submit, sem salvar rascunho sensivel
- [ ] dominio autorizado para tokenizacao e chave publica correta devem entrar no checklist de ambiente

### 5.3 Autosservico minimo

Superficies do MVP:

- [ ] trocar cartao
- [ ] visualizar invoices reais da recorrencia
- [ ] visualizar proxima cobranca
- [ ] cancelar agora
- [ ] cancelar ao fim do ciclo

Superficie recomendada para `P1`:

- [ ] trocar meio de pagamento quando o produto abrir meios alem de `credit_card`

### 5.4 Testes da fase

- [ ] reescrever [PlansPage.test.tsx](c:\laragon\www\eventovivo\apps\web\src\modules\plans\PlansPage.test.tsx) para formulario real
- [ ] adicionar teste de tokenizacao e envio apenas de `card_token`
- [ ] adicionar teste de troca de cartao
- [ ] adicionar teste de cancelamento imediato versus fim do ciclo

## Fase 6 - Homologacao e rollout

Objetivo:

- validar ponta a ponta antes de qualquer ligacao em producao controlada.

### 6.1 Homologacao funcional

Fluxos obrigatorios:

1. [ ] criar plano mensal e anual
2. [ ] criar assinatura `prepaid`
3. [ ] validar `invoice.created`
4. [ ] validar `invoice.paid`
5. [ ] forcar `invoice.payment_failed`
6. [ ] trocar cartao e recuperar a assinatura
7. [ ] cancelar imediatamente
8. [ ] cancelar ao fim do ciclo
9. [ ] validar replay de webhook
10. [ ] validar reconcile manual por `subscription_id`
11. [ ] validar chargeback e politica local
12. [ ] validar `GET /charges/{charge_id}` como trilha de troubleshooting fino
13. [ ] validar criaçao/atualizacao usando chaves de teste e simuladores oficiais aplicaveis

### 6.2 Observabilidade minima

- [ ] logs estruturados por `gateway_subscription_id`, `gateway_invoice_id`, `gateway_charge_id`, `gateway_cycle_id`
- [ ] dashboard de hooks falhos
- [ ] alerta para `invoice.payment_failed` e `charge.chargedback`
- [ ] comando operacional de reconcile documentado
- [ ] comando ou probes equivalentes ao `PagarmeHomologationCommand` atual para recorrencia

### 6.2.1 Chaves e simuladores de teste

Base operacional ja usada no repo e reaproveitavel nesta trilha:

- `PAGARME_PUBLIC_KEY=pk_test_jGWvy7PhpBukl396`
- `PAGARME_SECRET_KEY=sk_test_7611662845434f72bdb0986b69d54ce1`

Leitura de execucao:

- a suite local ja usa essas credenciais de teste em unit e feature tests;
- o plano de recorrencia deve manter testes automatizados e probes guiados pelas mesmas chaves de homologacao;
- quando a Pagar.me fornecer simuladores especificos aplicaveis ao contexto de assinatura, eles devem ser incorporados ao mesmo dossie operacional de homologacao.

### 6.3 Sequencia de rollout

1. [ ] ligar apenas em homologacao
2. [ ] validar uma organizacao interna
3. [ ] validar um tenant piloto controlado
4. [ ] manter fallback operacional de cancelamento manual ate a primeira janela estavel
5. [ ] so depois permitir `BILLING_GATEWAY_SUBSCRIPTION=pagarme` em producao parcial

## Definicao de pronto

Este plano so pode ser considerado concluido quando:

- o checkout da conta cria assinatura real na Pagar.me;
- `plan_price` ja estiver mapeado para `gateway_plan_id`;
- a aplicacao tiver `subscription_cycle` local persistido;
- `contract_status`, `billing_status` e `access_status` estiverem separados;
- `invoice` e `payment` estiverem vinculados ao ciclo correto;
- webhook recorrente estiver coberto por testes de `subscription`, `invoice` e `charge`;
- existir reconcile pontual e batch;
- o admin conseguir trocar cartao e cancelar sem divergencia entre Eventovivo e provider;
- a homologacao de `charge.chargedback` estiver executada e documentada;
- a suite de testes recorrentes tiver saindo de `todo()` para cobertura real.

## Sequencia recomendada de execucao

Ordem pratica:

1. fase 0
2. fase 1
3. fase 2
4. fase 4
5. fase 3
6. fase 5
7. fase 6

Motivo:

- recorrencia sem schema, webhook e reconcile maduros vira divida operacional;
- o admin pode esperar alguns dias a mais;
- divergencia silenciosa entre provider e read model local custa mais caro do que atraso de UI.
