# Integracao Pagar.me v5 para compra unica de pacote por evento

## Objetivo

Este documento consolida, em 2026-04-05:

- o estado real do `eventovivo` para checkout publico de pacote por evento;
- o fluxo real observado no projeto de referencia `C:/laragon/www/evydencia/crm`;
- os contratos oficiais da Pagar.me v5 que impactam Pix, cartao, idempotencia, rate limit e webhook;
- a arquitetura recomendada para fechar uma compra unica com seguranca operacional.

Escopo desta primeira entrega:

- compra unica de `event_package`;
- meios de pagamento `pix` e `credit_card`;
- criacao idempotente do pedido;
- persistencia local do snapshot do gateway;
- retorno de dados de checkout para o frontend;
- reconciliacao por webhook;
- endpoint local de status;
- cancelamento/estorno administrativo por `charge_id`.
- retry operacional seguro do mesmo `BillingOrder` com a mesma `Idempotency-Key`.

Jornada relacionada, mas separada deste escopo:

- o onboarding comercial administrativo sem `Event`, com Pix avulso e
  associacao futura a evento, fica documentado em:
  - [billing-admin-customer-onboarding-discovery.md](./billing-admin-customer-onboarding-discovery.md)
  - [billing-admin-customer-onboarding-execution-plan.md](./billing-admin-customer-onboarding-execution-plan.md)

Fora de escopo nesta fase:

- assinatura recorrente;
- wallet de cartoes salvos;
- split;
- antifraude avancado;
- consulta publica em tempo real na Pagar.me a cada polling.

---

## Veredito executivo

O ajuste principal de escopo e este:

- a integracao nao e "criar Pix/cartao";
- a integracao e "criar pedido idempotente + persistir snapshot do gateway + reconciliar por webhook + expor status local".

O `eventovivo` ja tem a fundacao correta para isso:

- checkout publico dentro do modulo `Billing`;
- `BillingOrder`, `BillingOrderItem`, `Payment`, `Invoice` e `BillingGatewayEvent`;
- `BillingGatewayInterface`, `BillingGatewayManager` e `ManualBillingGateway`;
- webhook generico em `POST /api/v1/webhooks/billing/{provider}`;
- idempotencia local de eventos por `provider_key + event_key`;
- ativacao do evento e grant comercial depois do pagamento.

Os gaps restantes para Pagar.me v5, depois da rodada atual, sao estes:

- `POST /public/event-checkouts/{uuid}/confirm` continua existindo e deve ser tratado apenas como fallback manual para providers legados;
- os cenarios do simulador de cartao `4000000000000036`, `4000000000000044` e `4000000000000069` continuam divergindo da doc oficial nesta conta, mas a propria doc os classifica no simulador de cartao nao financeiro e manda usar o Simulador PSP para fluxos financeiros;
- a politica final de negocio para `chargeback` ainda precisa ser fechada no produto;
- o cancelamento da compra unica ficou fechado assim:
  - nao existe cancelamento pelo usuario na UI publica
  - se o time cancelar no painel Pagar.me, o `eventovivo` reflete isso por webhook no painel local
  - pedido pendente cancelado no gateway vira `canceled` localmente
  - cobranca ja paga estornada no gateway vira `refunded` localmente e revoga o acesso;
- a pagina publica ainda pode evoluir para uma jornada em etapas, mas ja consome `onboarding`, `token` e `next_path` de forma util;
- a notificacao ativa ao cliente ja existe para `pix_generated`, `paid`, `failed` e `refunded`, mas `chargeback` segue pendente de politica de produto;
- no `pix_generated`, o checkout agora manda `send-text` com resumo do pedido e, quando a instancia de WhatsApp resolvida e `zapi`, tambem enfileira `send-button-pix` com o valor copia-e-cola do `qr_code` para reduzir friccao no WhatsApp;
- o endpoint local do checkout agora tambem expoe `checkout.payment.whatsapp`, para a UI informar que o Pix foi enviado ao WhatsApp do comprador e se houve envio adicional do botao Pix;
- a UX de Pix e cartao ja esta bem mais forte; o gap principal agora e refino de produto, nao continuidade tecnica da retomada.

Revalidacao completa executada no fim desta rodada:

- `cd apps/api && php artisan test` -> verde
- `cd apps/web && npm run test` -> verde
- `cd apps/web && npm run type-check` -> verde
- `php artisan billing:pagarme:homologate --scenario=all --poll-attempts=2 --poll-sleep-ms=1000` -> verde
- consolidado salvo em:
  - `apps/api/storage/app/pagarme-homologation/20260405-210058-all.json`

Conclusao pratica:

- o `eventovivo` nao precisa reescrever o modulo `Billing`;
- o trabalho certo e plugar a Pagar.me sobre a arquitetura ja existente;
- o checkout precisa virar um fluxo transacional de producao, nao so um POST que cria Pix ou cartao.

---

## Referencias confirmadas

### Codigo do `eventovivo` revisado

- `apps/api/app/Modules/Billing/routes/api.php`
- `apps/api/app/Modules/Billing/Http/Controllers/PublicEventCheckoutController.php`
- `apps/api/app/Modules/Billing/Http/Requests/StorePublicEventCheckoutRequest.php`
- `apps/api/app/Modules/Billing/Actions/CreatePublicEventCheckoutAction.php`
- `apps/api/app/Modules/Billing/Actions/CreateEventPackageGatewayCheckoutAction.php`
- `apps/api/app/Modules/Billing/Actions/RetryBillingOrderGatewayCheckoutAction.php`
- `apps/api/app/Modules/Billing/Actions/RefreshBillingOrderGatewayAction.php`
- `apps/api/app/Modules/Billing/Actions/ProcessBillingWebhookAction.php`
- `apps/api/app/Modules/Billing/Actions/RegisterBillingGatewayPaymentAction.php`
- `apps/api/app/Modules/Billing/Actions/ActivatePaidEventPackageOrderAction.php`
- `apps/api/app/Modules/Billing/Actions/MarkBillingOrderAsPaidAction.php`
- `apps/api/app/Modules/Billing/Actions/CancelBillingOrderAction.php`
- `apps/api/app/Modules/Billing/Console/Commands/PagarmeHomologationCommand.php`
- `apps/api/app/Modules/Billing/Services/BillingGatewayInterface.php`
- `apps/api/app/Modules/Billing/Services/BillingGatewayManager.php`
- `apps/api/app/Modules/Billing/Services/ManualBillingGateway.php`
- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeClient.php`
- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeBillingGateway.php`
- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeHomologationService.php`
- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeStatusMapper.php`
- `apps/api/app/Modules/Billing/Http/Controllers/BillingWebhookController.php`
- `apps/api/app/Modules/Billing/Http/Controllers/BillingOrderController.php`
- `apps/api/app/Modules/Billing/Models/BillingOrder.php`
- `apps/api/app/Modules/Billing/Models/Payment.php`
- `apps/api/app/Modules/Billing/Models/Invoice.php`
- `apps/api/app/Modules/Billing/Models/BillingGatewayEvent.php`
- `apps/api/app/Modules/Billing/Providers/BillingServiceProvider.php`
- `apps/api/database/migrations/2026_04_01_194000_create_billing_order_tables.php`
- `apps/api/database/migrations/2026_04_02_100000_create_billing_payments_and_invoices_tables.php`
- `apps/api/database/migrations/2026_04_02_120000_create_billing_gateway_events_table.php`
- `apps/api/tests/Feature/Billing/PublicEventCheckoutTest.php`
- `apps/api/tests/Feature/Billing/BillingWebhookTest.php`
- `apps/api/tests/Feature/Billing/BillingTest.php`
- `apps/api/composer.json`
- `apps/web/src/lib/api-types.ts`
- `apps/web/src/modules/billing/PublicEventCheckoutPage.tsx`
- `apps/web/src/modules/billing/PublicEventCheckoutPage.test.tsx`
- `apps/web/src/modules/billing/services/public-event-checkout.service.ts`
- `apps/web/src/modules/billing/services/public-event-packages.service.ts`
- `apps/web/src/lib/pagarme-tokenization.ts`
- `apps/web/src/App.tsx`
- `apps/web/src/app/routing/route-preload.ts`

### Codigo do CRM de referencia revisado

- `C:/laragon/www/evydencia/crm/app/Services/Payments/PagarmeGateway.php`
- `C:/laragon/www/evydencia/crm/app/Services/Payments/Pagarme/Order.php`
- `C:/laragon/www/evydencia/crm/app/Http/Controllers/PagarmeHookController.php`
- `C:/laragon/www/evydencia/crm/app/Http/Requests/PagarmeHookRequest.php`
- `C:/laragon/www/evydencia/crm/app/Services/Payments/PaymentService.php`
- `C:/laragon/www/evydencia/crm/app/Services/TransactionService.php`
- `C:/laragon/www/evydencia/crm/resources/assets/js/Order/index.js`
- `C:/laragon/www/evydencia/crm/resources/assets/js/Order/Creditcard/index.js`
- `C:/laragon/www/evydencia/crm/app/Services/PagarmeCardProfileService.php`
- `C:/laragon/www/evydencia/crm/tests/Feature/PagarmeHookControllerTest.php`
- `C:/laragon/www/evydencia/crm/tests/Unit/Http/Requests/PagarmeHookRequestTest.php`

### Documentacao oficial Pagar.me v5 validada

- Criar pedido: `https://docs.pagar.me/reference/criar-pedido-2`
- Obter pedido: `https://docs.pagar.me/reference/obter-pedido-1`
- Obter cobranca: `https://docs.pagar.me/reference/obter-cobran%C3%A7a`
- Cancelar cobranca: `https://docs.pagar.me/reference/cancelar-cobran%C3%A7a`
- Capturar cobranca: `https://docs.pagar.me/reference/capturar-cobran%C3%A7a`
- Cartao de credito: `https://docs.pagar.me/reference/cart%C3%A3o-de-cr%C3%A9dito-1`
- Pix: `https://docs.pagar.me/reference/pix-2`
- Criar token: `https://docs.pagar.me/reference/criar-token-cart%C3%A3o-1`
- Tokenizacao: `https://docs.pagar.me/docs/tokenizacao`
- Tokenizecard JS: `https://docs.pagar.me/docs/tokenizecard`
- O que e um simulador: `https://docs.pagar.me/docs/o-que-%C3%A9-um-simulador`
- Simulador PSP: `https://docs.pagar.me/docs/simulador-psp`
- Simulador Pix: `https://docs.pagar.me/docs/simulador-pix`
- Simulador de Cartao de Credito: `https://docs.pagar.me/docs/simulador-de-cart%C3%A3o-de-cr%C3%A9dito`
- Idempotencia: `https://docs.pagar.me/docs/o-que-%C3%A9`
- Rate limit: `https://docs.pagar.me/reference/rate-limit`
- IP allowlist: `https://docs.pagar.me/docs/ip-allowlist`
- Bibliotecas: `https://docs.pagar.me/docs/bibliotecas-1`
- Eventos de webhook: `https://docs.pagar.me/reference/eventos-de-webhook-1`
- Visao geral sobre webhooks: `https://docs.pagar.me/reference/vis%C3%A3o-geral-sobre-webhooks`
- Exemplo de webhook: `https://docs.pagar.me/reference/exemplo-de-webhook-1`

---

## 1. Estado real do `eventovivo`

### 1.1 O que ja existe e esta correto

O checkout publico de evento unico ja nasce dentro do modulo `Billing`, o que esta alinhado com a regra principal do repositorio.

Fluxo atual:

1. `POST /api/v1/public/event-checkouts`
2. `CreatePublicEventCheckoutAction`
3. cria `User`, `Organization`, `Event`, `BillingOrder` e `BillingOrderItem`
4. chama `CreateEventPackageGatewayCheckoutAction`
5. o `BillingGatewayManager` resolve o provider configurado; na rodada atual o checkout publico de evento esta em `pagarme`
6. a resposta volta pelo `PublicEventCheckoutPayloadBuilder`
7. a confirmacao manual ou webhook chama `ActivatePaidEventPackageOrderAction`
8. o evento recebe `EventPurchase`, `EventAccessGrant`, `Payment`, `Invoice` e snapshot comercial
9. o `apps/web` ja expoe a primeira pagina publica real em `/checkout/evento`, consumindo apenas o contrato local do backend
10. a operacao autenticada ja pode usar `POST /api/v1/billing/orders/{uuid}/retry` para repetir com seguranca a criacao externa do pedido quando o `BillingOrder` ainda nao materializou `gateway_order_id`

Pontos fortes do desenho atual:

- o checkout publico ja separa pedido, pagamento, invoice e ativacao do evento;
- o gateway ja esta isolado por interface;
- a trilha de webhook ja existe com persistencia e idempotencia;
- o estado financeiro nao esta acoplado ao controller;
- o evento ja e ativado pelo dominio, nao pelo provider;
- o modulo `Billing` ja usa `afterCommit` em pontos sensiveis, o que ajuda a adotar processamento assicrono sem ler estado nao commitado.

### 1.2 O que o codigo atual ainda nao fecha totalmente

O provider `pagarme` ja define o fluxo real do checkout publico de evento unico, mas ainda faltam partes de produto e de homologacao final.

Gaps objetivos restantes:

1. `POST /public/event-checkouts/{uuid}/confirm` ainda existe por retrocompatibilidade e precisa ficar explicitamente fora do fluxo feliz da Pagar.me;
2. os cenarios tardios do simulador de cartao de credito divergiram do comportamento publicado pela doc oficial nesta conta;
3. a politica final de negocio para `chargeback` de compra unica ainda precisa ser fechada no produto;
4. a pagina publica ainda pode evoluir para uma jornada em etapas, mas ja trata de forma explicita o onboarding do usuario criado no backend;
5. a trilha de notificacao ativa ao cliente por WhatsApp ja existe para `pix_generated`, `paid`, `failed` e `refunded`, mas `chargeback` segue fora da primeira rodada;
6. no Pix, o `BillingPaymentStatusNotificationService` agora tenta dois outbounds quando a instancia e `zapi`:
   - `send-text` com valor, expiracao, link do QR e copia-e-cola
   - `send-button-pix` com o mesmo valor copia-e-cola vindo de `qr_code`, para teste de UX mais fluida no WhatsApp
7. a UX do checkout ja cobre mascara, contexto, feedback visual forte e retomada segura apos autenticacao; o que sobra e refinamento comercial da jornada;
8. vale abrir um follow-up com a Pagar.me se o time quiser confirmacao formal sobre a aplicabilidade de `0036`, `0044` e `0069` no contexto PSP usado aqui.

### 1.3 O que os testes atuais ja provam

Os testes de `apps/api/tests/Feature/Billing/PublicEventCheckoutTest.php` e `apps/api/tests/Feature/Billing/BillingWebhookTest.php` ja provam:

- o checkout publico cria o pedido corretamente;
- a resposta publica devolve QR Code local de Pix e dados operacionais de cartao;
- o checkout de cartao ja fecha `paid`, `failed` e falha antes da criacao do pedido;
- o webhook da Pagar.me entra em fila `billing`, deduplica por `event_key` e processa idempotentemente;
- a operacao autenticada ja consegue repetir com seguranca a criacao externa do mesmo `BillingOrder`, reaproveitando a mesma `Idempotency-Key` quando ainda nao existe `gateway_order_id`;
- o retry operacional do mesmo pedido rejeita concorrencia com lock e evita nova chamada externa quando o snapshot do gateway ja existe;
- o refresh administrativo local consulta `GET /orders/{id}` e `GET /charges/{id}` sem contaminar o polling publico;
- a reconciliacao local ja cobre `paid`, `failed`, `refunded`, `chargeback` e snapshot `processing`;
- o endpoint publico `GET /public/event-checkouts/{uuid}` continua lendo apenas o estado local.

Os testes de frontend em `apps/web/src/modules/billing/PublicEventCheckoutPage.test.tsx` agora tambem provam:

- a pagina publica real do checkout existe em React/SPA;
- Pix mostra QR Code local e faz polling apenas no endpoint local de status;
- o conflito de identidade vira ramo de continuidade com CTA seguro para login;
- a retomada apos autenticacao usa rascunho seguro:
  - Pix pode ser retomado automaticamente
  - cartao nao persiste PAN, CVV nem validade;
- cartao tokeniza fora do backend e envia apenas `card_token` para `/public/event-checkouts`;
- cartao mostra falha imediata com `acquirer_message`;
- cartao suporta `processing -> paid` sem heuristica fraca no navegador.

Isso reduz muito o escopo real da integracao:

- nao estamos partindo do zero;
- o provider real ja esta plugado;
- o trabalho remanescente esta concentrado em UX publica e homologacao final.

---

## 2. Estado real do CRM de referencia

O CRM analisado ja usa a Pagar.me v5 no fluxo de order checkout.

### 2.1 O que ele faz bem

- monta `customer`, `items` e `payments` antes de chamar `POST /orders`;
- usa Basic Auth com `secret_key`;
- tokeniza cartao no browser e envia so `card_token` ao backend;
- salva localmente `order.id`, status e, no Pix, QR Code.

### 2.2 O que vale reaproveitar

- tokenizacao oficial do cartao fora do backend;
- separacao entre criacao do pedido externo e persistencia local do resultado;
- normalizacao de endereco e telefone;
- validacao previa do perfil minimo do pagador.

### 2.3 O que nao vale copiar literalmente

- webhook processado inline;
- filtro aceitando apenas `order.*`;
- persistencia local baseada quase so em `order.id`;
- ausencia de endpoint local de status;
- dependencia do fluxo feliz na resposta imediata do checkout.

O `eventovivo` ja parte de uma base melhor para webhook e dominio:

- `billing_gateway_events` com idempotencia real;
- provider abstrato por interface;
- separacao clara entre pagamento, conciliacao e ativacao do evento.

---

## 3. Contrato oficial da Pagar.me v5 que importa para este caso

### 3.1 Endpoints externos realmente necessarios

| Uso | Endpoint Pagar.me | Obrigatorio nesta fase | Observacao |
|---|---|---:|---|
| Criar venda | `POST /orders` | sim | endpoint central para Pix e cartao |
| Consultar pedido | `GET /orders/{order_id}` | sim | troubleshooting e conciliacao administrativa |
| Consultar cobranca | `GET /charges/{charge_id}` | sim | diagnostico fino e suporte |
| Cancelar ou estornar cobranca | `DELETE /charges/{charge_id}` | sim | fluxo administrativo de cancelamento/estorno |
| Tokenizar cartao no frontend | `POST /tokens?appId=<PUBLIC_KEY>` | sim | checkout transparente sem trafegar PAN no backend |
| Capturar cobranca | `POST /charges/{charge_id}/capture` | nao | so se usar `auth_only` ou `pre_auth` |

Base URL confirmada:

```text
https://api.pagar.me/core/v5
```

### 3.2 Idempotencia oficial da Pagar.me

A documentacao oficial recomenda enviar `Idempotency-Key` no header das requisicoes de criacao.

Pontos que impactam diretamente a arquitetura:

- em producao, a chave tem validade de 24 horas;
- em `sandbox`, a propria doc oficial continua apontando duracao de 5 minutos;
- retries com a mesma chave podem retornar o mesmo pedido mesmo se o corpo mudar;
- se a requisicao com a mesma chave ainda estiver em processamento, a API pode responder `409`.

Implicacao pratica para o `eventovivo`:

- cada tentativa local de `POST /orders` precisa sair com `Idempotency-Key` estavel;
- essa chave precisa ser persistida no banco;
- a chave deve ser derivada do `billing_order_uuid` e do numero da tentativa, nao de um valor aleatorio por request;
- timeout/retry do backend nao pode gerar um segundo pedido por acidente.
- quando a retentativa operacional reutiliza o mesmo `BillingOrder`, ela deve reaproveitar a mesma chave enquanto ainda estiver na mesma tentativa logica.

Regra recomendada:

```text
idempotency_key = "billing-order:{uuid}:attempt:{n}"
```

Regra operacional do `eventovivo` na rodada atual:

- se o mesmo `BillingOrder` ainda esta na mesma tentativa logica e ainda nao
  materializou `gateway_order_id`, o retry operacional reaproveita a mesma
  chave;
- se o pedido ja tem snapshot do gateway, o caminho correto e `refresh`, nao
  novo `POST /orders`;
- se o pedido ja entrou em estado terminal e o produto realmente quiser uma
  nova tentativa comercial, isso deve nascer como um novo `BillingOrder`, nao
  como sobrescrita da tentativa anterior.

### 3.3 Rate limit e implicacao para polling

A pagina oficial de rate limit publica limites por rota. Para este caso, `GET /orders/*` e `GET /charges/*` aparecem com limite de `200` requisicoes por minuto.

Implicacao pratica:

- o frontend nao deve consultar a Pagar.me diretamente;
- o frontend tambem nao deve fazer polling em um endpoint backend que, por sua vez, consulte a Pagar.me a cada hit;
- `GET /api/v1/public/event-checkouts/{uuid}` deve ler apenas o estado local;
- consulta externa a `GET /orders/{id}` e `GET /charges/{id}` deve existir para troubleshooting, sync manual ou job interno, nao para o polling da UX.

### 3.4 Regras oficiais para cartao e tokenizacao

Fatos documentados pela Pagar.me:

- nao se deve trafegar dados abertos do cartao pelo backend neste fluxo;
- `POST /tokens` usa `appId=<PUBLIC_KEY>` na query string;
- esse endpoint nao aceita header de autorizacao;
- o dominio do frontend precisa estar cadastrado na dashboard;
- o `billing_address` nao e tokenizado e continua obrigatorio ao criar o pedido;
- o token e temporario, de uso unico, e expira em ate 60 segundos.

Dois caminhos oficiais existem:

- `tokenizecard.js`;
- chamada direta para `POST /tokens`.

Recomendacao de arquitetura para o `eventovivo`:

- se o checkout publico ficar em React/SPA no `apps/web`, preferir chamada direta a `/tokens?appId=<PUBLIC_KEY>`;
- se existir pagina Blade/form tradicional, `tokenizecard.js` pode fazer sentido;
- em qualquer caso, o backend recebe somente `card_token`, nunca PAN/CVV/nome/vencimento.

Decisao operacional fechada para a fase 1:

- o `eventovivo` segue com `card_token` no checkout publico;
- a tokenizacao acontece no frontend via `POST /tokens?appId=<PUBLIC_KEY>`;
- no backend, para a conta PSP atual, o fluxo validado em homologacao e:
  `card_token` -> `POST /customers/{customer_id}/cards` -> `card_id` -> `POST /orders`
  com `customer_id + card_id`;
- `card_id` nao sai do frontend e nao substitui a tokenizacao oficial; ele e um
  detalhe interno do provider para esta conta.

Achado operacional validado em homologacao em `2026-04-05`:

- enviar `credit_card.card_token` diretamente em `POST /orders` nao se mostrou
  confiavel no fluxo PSP atual;
- o caminho estavel foi criar `customer`, converter o token em `card_id` e so
  entao criar o pedido.

### 3.5 Eventos de webhook recomendados

Minimo de negocio:

- `order.paid`
- `order.payment_failed`
- `order.canceled`

Recomendado para conciliacao:

- `charge.paid`
- `charge.payment_failed`
- `charge.refunded`
- `charge.partial_canceled`
- `charge.chargedback`

Regra de fonte de verdade:

- para Pix, a resposta sincrona serve para exibir QR Code, mas a confirmacao real vem no webhook;
- para cartao, a resposta de criacao pode vir `paid` ou `failed`, mas o webhook deve continuar sendo aceito como reconciliacao complementar.

### 3.6 O que nao esta claro nas paginas publicas v5

Nesta analise, eu confirmei o formato do webhook, os eventos e os endpoints de consulta e retry do hook, mas nao encontrei nas paginas publicas v5 revisadas uma especificacao clara de assinatura HMAC equivalente ao `X-Hub-Signature` antigo.

Por isso, a documentacao interna do `eventovivo` nao deve assumir isso como fato consumado.

Recomendacao ate confirmacao operacional:

- rota dedicada;
- idempotencia;
- persistencia bruta do payload;
- fila;
- observabilidade;
- opcionalmente Basic Auth, allowlist de origem ou outra camada operacional suportada pela infraestrutura.

Decisao operacional atual:

- o webhook da conta de homologacao foi configurado com Basic Auth;
- no `eventovivo`, isso deve ser refletido por `PAGARME_WEBHOOK_BASIC_AUTH_USER` e `PAGARME_WEBHOOK_BASIC_AUTH_PASSWORD`.

---

## 4. Bibliotecas e estrategia de integracao

### 4.1 Base tecnica recomendada

O `eventovivo` hoje usa `Laravel 13` com `PHP 8.3`, segundo `apps/api/composer.json`.

Para este projeto, a melhor base tecnica e:

- `Illuminate\Http\Client`
- `FormRequest`
- `Queue`
- `RateLimiter`
- `API Resources`
- DTOs/factories internos do modulo `Billing`

### 4.2 O que usar como fundacao

Recomendacao:

- usar `Http::withBasicAuth()->timeout()->connectTimeout()->retry()` dentro de um client proprio pequeno, por exemplo `PagarmeClient`;
- testar a integracao com `Http::fake()`, `Http::assertSent()` e `Http::preventStrayRequests()`.

Motivos:

- baixo acoplamento;
- melhor aderencia a arquitetura modular do repositorio;
- mais simples de testar;
- mais simples de evoluir junto com o proprio framework;
- suficiente para o recorte atual de `orders`, `charges`, `tokens` e webhook.

### 4.3 O que nao usar como fundacao

Nao faz sentido usar como base principal:

- wrapper Laravel comunitario para Pagar.me;
- pacote de terceiro que imponha versao de framework ou opiniao de integracao desnecessaria para o modulo.

### 4.4 O que pode ser opcional

A documentacao oficial lista biblioteca PHP da Pagar.me. Ela pode ser util se o projeto realmente precisar de cobertura ampla de endpoints administrativos.

Mesmo assim, para este escopo, a recomendacao continua sendo:

- manter um `PagarmeClient` proprio sobre o HTTP Client nativo;
- considerar SDK oficial apenas se surgir necessidade forte fora do recorte de `orders/charges/tokens`.

---

## 5. Arquitetura alvo no modulo `Billing`

### 5.1 Fluxo macro recomendado

1. o checkout publico cria `BillingOrder` local e seus itens;
2. o backend gera ou recupera `idempotency_key` da tentativa atual;
3. `PagarmeBillingGateway` monta o payload e chama `POST /orders`;
4. o backend persiste snapshot bruto do gateway e campos operacionais estruturados;
5. a resposta inicial volta para o frontend com dados necessarios para UX imediata;
6. o frontend passa a consultar apenas `GET /public/event-checkouts/{uuid}`;
7. o webhook atualiza o estado local e dispara a ativacao do evento quando couber.

### 5.2 Classes recomendadas

```text
apps/api/app/Modules/Billing/Services/Pagarme/PagarmeClient.php
apps/api/app/Modules/Billing/Services/Pagarme/PagarmeBillingGateway.php
apps/api/app/Modules/Billing/Services/Pagarme/PagarmeOrderPayloadFactory.php
apps/api/app/Modules/Billing/Services/Pagarme/PagarmeCustomerNormalizer.php
apps/api/app/Modules/Billing/Services/Pagarme/PagarmeWebhookNormalizer.php
apps/api/app/Modules/Billing/Services/Pagarme/PagarmeStatusMapper.php
apps/api/app/Modules/Billing/Actions/RefreshBillingOrderStatusAction.php
apps/api/app/Modules/Billing/Jobs/ProcessBillingWebhookJob.php
```

Responsabilidades:

- `PagarmeClient`
  - `createOrder()`
  - `getOrder()`
  - `getCharge()`
  - `cancelCharge()`
  - `captureCharge()`

- `PagarmeBillingGateway`
  - implementa `createEventPackageCheckout()`
  - implementa `parseWebhook()`
  - implementa `cancelOrder()`

- `PagarmeOrderPayloadFactory`
  - transforma o checkout interno em JSON da Pagar.me;
  - centraliza o mapeamento de `pix` e `credit_card`.

- `PagarmeCustomerNormalizer`
  - normaliza documento, telefone e endereco;
  - garante shape minimo de `customer`.

- `PagarmeWebhookNormalizer`
  - converte `order.*` e `charge.*` para eventos internos;
  - extrai `gateway_order_id`, `gateway_charge_id`, `gateway_transaction_id` e `occurred_at`.

- `PagarmeStatusMapper`
  - mapeia status externos para `pending`, `paid`, `failed`, `canceled`, `refunded`, `partially_refunded`, `chargeback`.

- `RefreshBillingOrderStatusAction`
  - consulta a Pagar.me sob demanda administrativa;
  - nao deve ser chamada pelo endpoint publico de polling.

### 5.3 Rotas locais recomendadas

Mantidas:

```text
POST /api/v1/public/event-checkouts
POST /api/v1/webhooks/billing/pagarme
```

Novas:

```text
GET  /api/v1/public/event-checkouts/{billingOrder:uuid}
POST /api/v1/billing/orders/{billingOrder:uuid}/cancel
POST /api/v1/billing/orders/{billingOrder:uuid}/refresh
```

Decisoes de escopo:

- `GET /api/v1/public/event-checkouts/{uuid}` deve ler so o estado local;
- `POST /api/v1/public/event-checkouts/{uuid}/confirm` pode continuar existindo para `manual`, mas para Pagar.me deve ser tratado como fallback manual, nao como parte do fluxo feliz.
- `POST /api/v1/billing/orders/{uuid}/refresh` e rota autenticada de troubleshooting, usada para sync manual com `GET /orders/{id}` e `GET /charges/{id}` sem expor o gateway ao frontend publico.

---

## 6. Contrato recomendado do checkout publico

### 6.1 Problema do contrato atual

O payload atual do `eventovivo` so recebe:

- `responsible_name`
- `whatsapp`
- `email`
- `organization_name`
- `package_id`
- `event.*`

Isso nao basta para a Pagar.me v5 porque faltam, no minimo:

- `payer.document`
- `payer.document_type`
- telefone estruturado;
- endereco completo;
- escolha do metodo de pagamento;
- `installments` para cartao;
- `card_token` para checkout transparente.

### 6.2 Forma correta de pensar o request

O `eventovivo` tem dois atores diferentes:

- `responsible_*` e dados da jornada comercial do evento;
- `payer`, que e o snapshot do pagador usado no gateway.

Regra recomendada:

- `responsible_*` continua servindo para criar usuario e organizacao leve;
- `payer` vira o contrato financeiro real do checkout;
- se `payer` nao vier em um fluxo muito simplificado de Pix, o backend pode fazer fallback controlado;
- para cartao, `payer` deve ser obrigatorio com todos os campos minimos.

### 6.3 Validacao condicional no `FormRequest`

O melhor lugar para endurecer esse contrato e o proprio `StorePublicEventCheckoutRequest`, usando:

- `rules()` para o shape base;
- `after()` para regras condicionais por metodo de pagamento.

Regras recomendadas:

- se `payment.method = credit_card`:
  - `payer.document` obrigatorio;
  - `payer.address.*` obrigatorio;
  - `payment.credit_card.installments` obrigatorio;
  - `payment.credit_card.card_token` obrigatorio;
  - `payment.pix` proibido.

- se `payment.method = pix`:
  - `card_token` proibido;
  - `payment.pix.expires_in` pode usar default de config;
  - `payment.credit_card.*` proibido.

### 6.4 Payload local recomendado

Exemplo Pix:

```json
{
  "responsible_name": "Mariana Alves",
  "whatsapp": "5548999881111",
  "email": "mariana@example.com",
  "organization_name": "Mariana e Rafael",
  "package_id": 12,
  "event": {
    "title": "Casamento Mariana e Rafael",
    "event_type": "wedding",
    "event_date": "2026-11-15",
    "city": "Florianopolis",
    "description": "Compra direta de pacote"
  },
  "payer": {
    "name": "Mariana Alves",
    "email": "mariana@example.com",
    "document": "12345678909",
    "document_type": "CPF",
    "phone": "5548999881111",
    "address": {
      "street": "Rua Exemplo",
      "number": "123",
      "district": "Centro",
      "complement": "Sala 2",
      "zip_code": "88000000",
      "city": "Florianopolis",
      "state": "SC",
      "country": "BR"
    }
  },
  "payment": {
    "method": "pix",
    "pix": {
      "expires_in": 1800
    }
  }
}
```

Exemplo cartao:

```json
{
  "payment": {
    "method": "credit_card",
    "credit_card": {
      "installments": 1,
      "statement_descriptor": "EVENTOVIVO",
      "card_token": "tok_xxx"
    }
  }
}
```

---

## 7. Mapeamento do payload local para `POST /orders`

### 7.1 Regras gerais

Mapeamento recomendado:

- `code` = `billingOrder->uuid`
- `items` = `billing_order_items`
- `customer` = `payer` normalizado
- `payments` = bloco por metodo
- `metadata` = ids locais para conciliacao

Metadata recomendada:

```json
{
  "billing_order_uuid": "0d8a2e1d-1b6d-4c77-b3a0-4d457c2d1111",
  "billing_order_id": 87,
  "event_id": 412,
  "organization_id": 55,
  "package_id": 12,
  "journey": "public_event_checkout"
}
```

### 7.2 Chave de idempotencia por tentativa

Antes de chamar `POST /orders`, o backend deve:

1. carregar ou criar a tentativa atual;
2. gerar `idempotency_key` estavel;
3. persistir essa chave localmente;
4. enviar o header `Idempotency-Key`.

Sem isso, timeout e retry do servidor podem duplicar a compra.

### 7.3 Pedido Pix

```json
{
  "code": "0d8a2e1d-1b6d-4c77-b3a0-4d457c2d1111",
  "closed": true,
  "items": [
    {
      "code": "pkg-12",
      "amount": 19900,
      "description": "Pacote Evento Premium",
      "quantity": 1
    }
  ],
  "customer": {
    "name": "Mariana Alves",
    "email": "mariana@example.com",
    "type": "individual",
    "document_type": "CPF",
    "document": "12345678909",
    "phones": {
      "mobile_phone": {
        "country_code": "55",
        "area_code": "48",
        "number": "999881111"
      }
    },
    "address": {
      "line_1": "123, Rua Exemplo, Centro",
      "line_2": "Sala 2",
      "zip_code": "88000000",
      "city": "Florianopolis",
      "state": "SC",
      "country": "BR"
    }
  },
  "payments": [
    {
      "payment_method": "pix",
      "pix": {
        "expires_in": 1800
      }
    }
  ],
  "metadata": {
    "billing_order_uuid": "0d8a2e1d-1b6d-4c77-b3a0-4d457c2d1111"
  }
}
```

### 7.4 Pedido cartao

```json
{
  "code": "0d8a2e1d-1b6d-4c77-b3a0-4d457c2d1112",
  "closed": true,
  "items": [
    {
      "code": "pkg-12",
      "amount": 19900,
      "description": "Pacote Evento Premium",
      "quantity": 1
    }
  ],
  "customer": {
    "name": "Mariana Alves",
    "email": "mariana@example.com",
    "type": "individual",
    "document_type": "CPF",
    "document": "12345678909",
    "phones": {
      "mobile_phone": {
        "country_code": "55",
        "area_code": "48",
        "number": "999881111"
      }
    },
    "address": {
      "line_1": "123, Rua Exemplo, Centro",
      "zip_code": "88000000",
      "city": "Florianopolis",
      "state": "SC",
      "country": "BR"
    }
  },
  "payments": [
    {
      "payment_method": "credit_card",
      "credit_card": {
        "installments": 1,
        "statement_descriptor": "EVENTOVIVO",
        "operation_type": "auth_and_capture",
        "card_token": "tok_xxx",
        "billing_address": {
          "line_1": "123, Rua Exemplo, Centro",
          "zip_code": "88000000",
          "city": "Florianopolis",
          "state": "SC",
          "country": "BR"
        }
      }
    }
  ],
  "metadata": {
    "billing_order_uuid": "0d8a2e1d-1b6d-4c77-b3a0-4d457c2d1112"
  }
}
```

---

## 8. Persistencia local recomendada

### 8.1 Principio

O banco local nao deve guardar apenas "o ultimo status".

Ele deve guardar:

- ids operacionais do gateway;
- snapshot do pagador;
- snapshot bruto da resposta;
- dados necessarios para suporte e conciliacao;
- timestamps relevantes por transicao.

### 8.2 `billing_orders`

Campos considerados obrigatorios para este escopo:

- `payment_method`
- `idempotency_key`
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

Observacao:

- `gateway_order_id` e `idempotency_key` devem ser indexados;
- `gateway_status` nao substitui o status interno do dominio, ele apenas preserva a leitura crua do gateway.

### 8.3 `payments`

Campos considerados obrigatorios para operacao:

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

### 8.4 `billing_gateway_events`

A tabela ja existe e esta correta como base.

Ajustes recomendados:

- `gateway_charge_id`
- `gateway_transaction_id`
- `occurred_at`
- `payload_json` bruto
- chave unica por `provider_key + event_key`

### 8.5 Onde guardar o que o frontend precisa

Recomendacao:

- os snapshots brutos ficam em colunas JSON;
- os campos usados por UX, suporte e conciliacao ficam materializados em colunas simples;
- `PublicEventCheckoutPayloadBuilder` le a versao estruturada do estado local, nunca o payload bruto diretamente.

---

## 9. Webhook alvo no `eventovivo`

### 9.1 Fluxo recomendado

1. controller recebe `POST /api/v1/webhooks/billing/pagarme`;
2. persiste payload bruto em `billing_gateway_events`;
3. responde `200` rapido;
4. despacha `ProcessBillingWebhookJob` para a fila `billing`;
5. o job normaliza o evento;
6. o job atualiza `billing_orders`, `payments`, `invoices` e grant comercial;
7. tudo roda de forma idempotente.

### 9.2 Mapeamento externo -> interno

| Evento Pagar.me | Evento interno sugerido | Acao local |
|---|---|---|
| `order.paid` | `payment.paid` | registrar pagamento e ativar evento |
| `charge.paid` | `payment.paid` | conciliacao complementar |
| `order.payment_failed` | `payment.failed` | marcar pedido como falho |
| `charge.payment_failed` | `payment.failed` | enriquecer diagnostico |
| `order.canceled` | `checkout.canceled` | cancelar pedido pendente |
| `charge.refunded` | `payment.refunded` | marcar refund |
| `charge.partial_canceled` | `payment.partially_refunded` | registrar estorno parcial |
| `charge.chargedback` | `payment.chargeback` | abrir tratamento financeiro/manual |

### 9.3 `afterCommit` e job unico

Para producao, a recomendacao e:

- gravar o evento primeiro;
- despachar o job depois do commit;
- usar job unico ou travamento equivalente para nao processar o mesmo webhook em paralelo.

Isso encaixa bem com o padrao ja usado pelo modulo `Billing`, que hoje ja trabalha com `afterCommit` no `BillingServiceProvider`.

### 9.4 Regra de verdade por metodo de pagamento

Para Pix:

- a resposta sincrona serve para exibir `qr_code`, `qr_code_url` e `expires_at`;
- a confirmacao real do pagamento vem do webhook;
- o frontend so deve observar o estado local.

Para cartao:

- a resposta do `POST /orders` pode resolver boa parte do fluxo feliz e do fluxo de falha;
- mesmo assim, webhook continua sendo reconciliacao complementar obrigatoria.

---

## 10. Frontend necessario

### 10.1 Contrato de resposta

O payload publico precisa crescer para algo proximo de:

```ts
payment: {
  provider: 'pagarme' | 'manual' | string | null;
  method: 'pix' | 'credit_card' | string | null;
  gateway_order_id: string | null;
  gateway_charge_id: string | null;
  gateway_status: string | null;
  status: string | null;
  expires_at: string | null;
  pix: {
    qr_code: string | null;
    qr_code_url: string | null;
    expires_at: string | null;
  } | null;
  credit_card: {
    installments: number | null;
    acquirer_message: string | null;
    acquirer_return_code: string | null;
    last_status: string | null;
  } | null;
}
```

### 10.2 Fluxo Pix recomendado

```text
frontend -> POST /api/v1/public/event-checkouts
backend -> cria BillingOrder local
backend -> POST /orders na Pagar.me com Idempotency-Key
backend -> persiste snapshot e devolve qr_code, qr_code_url, expires_at e checkout_uuid
frontend -> mostra QR Code e navega para pagina de status
frontend -> polling em GET /api/v1/public/event-checkouts/{uuid}
webhook -> marca pedido como paid
polling -> detecta paid e redireciona para o evento
```

### 10.3 Fluxo cartao recomendado

```text
frontend -> tokeniza cartao com PUBLIC_KEY
frontend -> envia card_token para POST /api/v1/public/event-checkouts
backend -> POST /orders na Pagar.me com Idempotency-Key
backend -> persiste snapshot e devolve status local
frontend -> mostra sucesso ou erro amigavel
webhook -> confirma ou complementa conciliacao
```

### 10.4 Pagina de status separada

O checkout precisa de uma pagina de status separada da submissao inicial.

Motivo:

- no Pix, o usuario recebe QR Code, pode sair da pagina, pagar no app do banco e voltar depois;
- o estado precisa sobreviver ao refresh e a navegacao;
- isso pede um `GET /public/event-checkouts/{uuid}` lendo so o banco local.

### 10.5 Validacao progressiva

Se o checkout publico ficar no `apps/web`, vale considerar `Laravel Precognition` como opcional para:

- CPF/CNPJ;
- telefone;
- endereco;
- regras condicionais por metodo de pagamento.

Nao e obrigatorio, mas pode reduzir bastante erro de preenchimento sem duplicar regra de negocio no frontend.

---

## 11. Configuracao e ambiente

### `apps/api/config/services.php`

```php
'pagarme' => [
    'base_url' => env('PAGARME_BASE_URL', 'https://api.pagar.me/core/v5/'),
    'secret_key' => env('PAGARME_SECRET_KEY'),
    'public_key' => env('PAGARME_PUBLIC_KEY'),
    'statement_descriptor' => env('PAGARME_STATEMENT_DESCRIPTOR', 'EVENTOVIVO'),
    'pix_expires_in' => (int) env('PAGARME_PIX_EXPIRES_IN', 1800),
    'timeout' => (int) env('PAGARME_TIMEOUT', 15),
    'connect_timeout' => (int) env('PAGARME_CONNECT_TIMEOUT', 5),
];
```

### `apps/api/config/billing.php`

```php
'gateways' => [
    'default' => env('BILLING_GATEWAY_DEFAULT', 'manual'),
    'event_package' => env('BILLING_GATEWAY_EVENT_PACKAGE', env('BILLING_GATEWAY_DEFAULT', 'manual')),
    'subscription' => env('BILLING_GATEWAY_SUBSCRIPTION', env('BILLING_GATEWAY_DEFAULT', 'manual')),
    'providers' => [
        'manual' => ManualBillingGateway::class,
        'pagarme' => PagarmeBillingGateway::class,
    ],
],
```

### Frontend

```text
VITE_PAGARME_PUBLIC_KEY=...
```

Observacoes:

- o dominio do frontend deve estar autorizado na dashboard da Pagar.me para `/tokens`;
- `statement_descriptor` deve seguir as restricoes da Pagar.me;
- o webhook externo deve apontar para `/api/v1/webhooks/billing/pagarme`.

---

## 12. Ordem recomendada de implementacao

### Fase 1 - contrato e persistencia

1. expandir `StorePublicEventCheckoutRequest` para `payer`, `payment.method`, `payment.pix` e `payment.credit_card`;
2. adicionar validacao condicional com `after()`;
3. expandir `PublicEventCheckoutResponse`;
4. adicionar `idempotency_key`, snapshots e campos operacionais do gateway em `billing_orders` e `payments`;
5. adicionar `gateway_charge_id`, `gateway_transaction_id` e `occurred_at` em `billing_gateway_events`;
6. criar `GET /public/event-checkouts/{uuid}`;
7. manter `confirm` apenas como fallback manual.

### Fase 2 - client e provider

1. criar `PagarmeClient` sobre `Illuminate\Http\Client`;
2. criar `PagarmeOrderPayloadFactory`;
3. criar `PagarmeCustomerNormalizer`;
4. criar `PagarmeWebhookNormalizer`;
5. criar `PagarmeStatusMapper`;
6. implementar `PagarmeBillingGateway`;
7. registrar provider no `BillingGatewayManager`.

### Fase 3 - Pix

1. suportar `payment.method = pix`;
2. enviar `Idempotency-Key` em `POST /orders`;
3. devolver `qr_code`, `qr_code_url` e `expires_at`;
4. implementar pagina de status e polling local;
5. tratar `order.paid` e `order.canceled`.

### Fase 4 - cartao

1. tokenizar cartao fora do backend;
2. suportar `payment.method = credit_card`;
3. exigir `payer` completo e `billing_address`;
4. tratar `order.payment_failed` e `charge.payment_failed`;
5. expor `acquirer_message` e `acquirer_return_code` quando houver.

### Fase 5 - operacao e conciliacao

1. suportar `charge.refunded`, `charge.partial_canceled` e `charge.chargedback`;
2. adicionar `GET /orders/{id}` e `GET /charges/{id}` no client para troubleshooting;
3. adicionar cancelamento/estorno com `DELETE /charges/{charge_id}`;
4. mover webhook para fila `billing`;
5. usar dispatch apos commit e processamento unico por evento.

---

## 13. Suite minima de testes

### Backend

Unit:

- `PagarmeCustomerNormalizerTest`
- `PagarmeOrderPayloadFactoryTest`
- `PagarmeWebhookNormalizerTest`
- `PagarmeStatusMapperTest`

Feature:

- cria checkout Pix e retorna QR Code;
- cria checkout cartao com `card_token`;
- envia `Idempotency-Key` estavel em retry da mesma tentativa;
- webhook `order.paid` ativa o evento;
- webhook repetido e ignorado com idempotencia;
- webhook `order.payment_failed` marca pedido como `failed`;
- webhook `charge.refunded` registra refund;
- `GET /public/event-checkouts/{uuid}` devolve estado local atualizado sem consultar gateway;
- cancelamento/estorno por `charge_id` funciona quando aplicavel.

### Frontend

- tokenizacao de cartao envia apenas `card_token`;
- fluxo Pix exibe QR Code e pagina de status;
- tela de status troca para `paid` apos mudanca local;
- erros de cartao mostram mensagem amigavel;
- checkout nao envia cartao aberto para o backend.

### Smoke manual

1. criar pedido Pix;
2. validar `qr_code`, `qr_code_url` e expiracao;
3. pagar Pix;
4. receber webhook `order.paid`;
5. validar `BillingOrder`, `Payment`, `Invoice`, `EventPurchase` e `EventAccessGrant`;
6. repetir o mesmo webhook e confirmar idempotencia;
7. criar pedido cartao;
8. validar fluxo `paid` e fluxo `refused`.

---

## 14. Homologacao local e `.env`

### 14.1 Credenciais de homologacao para uso local

Para homologacao local, usar as credenciais de teste fornecidas para esta conta:

- `PAGARME_ACCOUNT_ID=acc_A1dYEVzhnIzEwG5z`
- `PAGARME_PUBLIC_KEY=pk_test_jGWvy7PhpBukl396`
- `PAGARME_SECRET_KEY=sk_test_7611662845434f72bdb0986b69d54ce1`

Recomendacao operacional:

- usar essas credenciais apenas em `.env` local ou secret store interna;
- nao replicar essas chaves em `deploy/examples` nem em arquivos de producao;
- se este repositorio sair do escopo interno do time, substituir os valores reais por placeholders.

### 14.2 Exemplo de `.env` local

```dotenv
PAGARME_BASE_URL=https://api.pagar.me/core/v5/
PAGARME_ACCOUNT_ID=acc_A1dYEVzhnIzEwG5z
PAGARME_PUBLIC_KEY=pk_test_jGWvy7PhpBukl396
PAGARME_SECRET_KEY=sk_test_7611662845434f72bdb0986b69d54ce1
PAGARME_WEBHOOK_BASIC_AUTH_USER=...
PAGARME_WEBHOOK_BASIC_AUTH_PASSWORD="..."
PAGARME_STATEMENT_DESCRIPTOR=EVENTOVIVO
PAGARME_PIX_EXPIRES_IN=1800

BILLING_GATEWAY_DEFAULT=manual
BILLING_GATEWAY_SUBSCRIPTION=manual
BILLING_GATEWAY_EVENT_PACKAGE=pagarme
```

Se a senha do webhook tiver `#`, `;` ou espacos, mantenha o valor entre aspas no `.env`.

Se o checkout publico estiver no `apps/web`, usar tambem:

```dotenv
VITE_PAGARME_PUBLIC_KEY=pk_test_jGWvy7PhpBukl396
```

### 14.3 Checklist antes de rodar localmente

Antes de abrir a bateria de testes:

- confirmar que a app local esta usando a base URL v5;
- garantir `Idempotency-Key` unica por tentativa de criacao de pedido;
- preencher `customer` completo para PSP, com telefone e endereco;
- expor o webhook local em uma URL publica temporaria;
- cadastrar no webhook ao menos os eventos:
  - `order.created`
  - `order.paid`
  - `order.payment_failed`
  - `order.canceled`
  - `charge.paid`
  - `charge.payment_failed`
  - `charge.refunded`
  - `charge.chargedback`
- se houver tokenizacao direta no frontend, garantir que o dominio usado no teste esteja liberado na dashboard.

### 14.4 Webhook local em localhost

Como a Pagar.me envia webhook para uma URL configurada, o teste local precisa de uma URL publica temporaria apontando para:

```text
POST /api/v1/webhooks/billing/pagarme
```

Fluxo recomendado:

1. subir API local;
2. abrir um tunnel publico temporario;
3. cadastrar a URL publica no webhook da conta de homologacao;
4. selecionar os eventos necessarios;
5. validar entrega, resposta `200` e persistencia local.

Observacao:

- a documentacao v5 permite configurar varios endpoints de webhook e escolher quais eventos cada endpoint recebe;
- para tokenizacao no frontend, quando `localhost` nao puder ser liberado como dominio, o mesmo tunnel publico costuma ser o caminho mais previsivel para homologacao.

---

## 15. Bateria recomendada de validacao local

### 15.1 Regra geral

Nao basta testar `POST /orders`.

A integracao so deve ser considerada validada quando o time provar:

1. criacao do pedido externo;
2. persistencia local de `gateway_order_id` e `gateway_charge_id`;
3. transicao de status no banco local;
4. recebimento e processamento do webhook;
5. idempotencia do webhook;
6. UX do frontend para Pix e cartao.

### 15.2 Smoke test inicial

Primeiro teste recomendado:

1. criar um pedido Pix de baixo valor;
2. confirmar que a API local salvou `billing_order`, `gateway_order_id`, `gateway_charge_id`, `status`, `qr_code`, `qr_code_url` e `expires_at`;
3. aguardar o webhook;
4. confirmar mudanca local para `paid`;
5. reenviar o mesmo webhook e confirmar que o pagamento nao duplica.

Se esse teste falhar, nao vale avancar para os cenarios seguintes.

### 15.3 Bateria Pix

Regras oficiais do simulador Pix:

- valor `<= 50000` centavos: sucesso;
- valor `> 50000` centavos: falha;
- o simulador Pix nao deve ser usado com split.

Casos obrigatorios:

Pix 1 - sucesso

- usar, por exemplo, `19900` ou `50000`;
- validar resposta com `qr_code`, `qr_code_url` e `expires_at`;
- validar status local inicial `pending`;
- validar webhook e mudanca posterior para `paid`;
- validar que a ativacao do evento acontece uma unica vez.

Pix 2 - falha

- usar `50001` ou maior;
- validar pedido local em `failed`;
- validar UX de falha sem consulta manual na Pagar.me;
- validar processamento de `order.payment_failed` ou `charge.payment_failed`.

Pix 3 - polling local

- repetir o caminho de sucesso e falha;
- validar que o frontend consulta apenas `GET /api/v1/public/event-checkouts/{uuid}`;
- validar que esse endpoint le so o estado local.

### 15.4 Bateria cartao com Simulador PSP

Regras oficiais do simulador PSP para cartao:

- aprovado: cartao valido por Luhn com `cvv = 123`;
- recusa do emissor: CVV iniciado em `6`, por exemplo `612`;
- recusa do antifraude: `customer.document = 11111111111`;
- `analysing`: se a conta tiver Garantia de Chargeback habilitada, usar `amount` com centavos entre `30` e `60`, por exemplo `130` a `160`.

Casos obrigatorios:

Cartao PSP 1 - aprovado

- usar cartao valido por Luhn com `cvv = 123`;
- usar documento normal e valor comum, como `19900`;
- validar `paid` local, conciliacao por webhook e ausencia de duplicidade.

Cartao PSP 2 - recusa do emissor

- usar o mesmo cartao, com `cvv = 612`;
- validar falha local;
- validar persistencia de mensagem correlata em `acquirer_message` ou campo equivalente quando vier no payload;
- validar UX de erro amigavel.

Cartao PSP 3 - recusa por antifraude

- usar `customer.document = 11111111111`;
- validar falha;
- validar que o evento nao e ativado;
- validar persistencia do payload para troubleshooting.

Cartao PSP 4 - `analysing`

- so faz sentido se a conta tiver Garantia de Chargeback habilitada;
- usar `amount` com centavos entre `30` e `60`;
- validar que o sistema nao trata o pedido como `paid` antes da hora.

Achados reais da conta de homologacao, validados em `2026-04-05`:

- `cvv = 123` com cartao valido fechou checkout `paid`;
- `cvv = 612` gerou recusa antes da criacao do pedido externo, e o
  `eventovivo` passou a responder checkout local `failed` em vez de `500`;
- `document = 11111111111` fechou pedido e cobranca em `failed`, sem ativacao
  do evento;
- nesse cenario de antifraude, a Pagar.me retornou
  `last_transaction.status = not_authorized` e
  `antifraud_response.status = reproved`, mesmo com
  `acquirer_message = "Transação aprovada com sucesso"`;
- por isso, suporte e UX nao devem confiar apenas em `acquirer_message` quando
  o status final local/gateway estiver `failed`.

### 15.5 Bateria cartao com Simulador de Cartao de Credito

Esse simulador e util para provar a maquina de estados de cartao. A propria documentacao o trata como simulador nao financeiro e orienta usar o Simulador PSP para cenarios financeiros do cartao.

Observacao operacional do `eventovivo`:

- na homologacao real desta conta PSP, os cenarios financeiros de aceite e
  recusa ficaram estaveis com o Simulador PSP;
- os numeros do simulador de cartao de credito devem ser tratados aqui como
  apoio para maquina de estados e nao como gate principal de aceite financeiro.

Casos oficiais mais uteis para esta integracao:

- `4000000000000010` -> sucesso
- `4000000000000028` -> nao autorizada
- `4000000000000036` -> `processing` -> sucesso
- `4000000000000044` -> `processing` -> falha
- `4000000000000051` -> `processing` -> cancelado
- `4000000000000069` -> `paid` -> `chargedback`
- `4000000000000077` -> pago -> `processing` -> cancelado ao tentar cancelar
- `4000000000000093` -> pago -> `processing` -> volta para pago ao tentar cancelar

Casos obrigatorios:

Cartao 1 - sucesso puro

- `4000000000000010`
- valida o caminho feliz.

Cartao 2 - nao autorizado

- `4000000000000028`
- valida erro imediato.

Cartao 3 - `processing` -> `paid`

- `4000000000000036`
- valida se o sistema nao congela o status cedo demais.

Cartao 4 - `processing` -> `failed`

- `4000000000000044`
- valida mudanca tardia para falha.

Cartao 5 - `paid` -> `chargedback`

- `4000000000000069`
- valida tratamento de `charge.chargedback`.

Cartao 6 - cancelamento/estorno

- `4000000000000077`
- `4000000000000093`
- validam o fluxo de cancelamento quando a operacao ainda transita por `processing`.

### 15.6 Bateria minima de webhook

Validacoes obrigatorias:

- persistir o evento bruto com `webhook_id`;
- deduplicar por `provider + webhook_id`;
- responder rapido `200`;
- processar em fila;
- atualizar `BillingOrder` e `Payment`;
- nao duplicar a ativacao do evento.

Se for necessario reentregar um webhook ja criado, a referencia v5 expoe `POST /hooks/{hook_id}/retry`.

### 15.7 Matriz curta para uso imediato

| Caso | Como simular | Resultado esperado |
|---|---|---|
| Pix sucesso | valor `<= 50000` | `pending` -> `paid` |
| Pix falha | valor `> 50000` | `failed` |
| Cartao PSP aprovado | cartao Luhn + `cvv 123` | `paid` |
| Cartao PSP recusa emissor | `cvv 612` | `failed` |
| Cartao PSP antifraude | `document 11111111111` | `failed` |
| Cartao sucesso | `4000000000000010` | `paid` |
| Cartao falha | `4000000000000028` | `failed` |
| Processing -> sucesso | `4000000000000036` | `pending` -> `paid` |
| Processing -> falha | `4000000000000044` | `pending` -> `failed` |
| Chargeback | `4000000000000069` | `paid` -> `chargedback` |

### 15.8 Validacao real de cancelamento e estorno em 2026-04-05

Para fechar `M7-T2`, eu adicionei um probe operacional direto do gateway:

- comando: `php artisan billing:pagarme:homologate`
- service: `PagarmeHomologationService`
- saida versionavel por caminho conhecido em `apps/api/storage/app/pagarme-homologation/`

Casos reais executados:

Pix cancelado por `DELETE /charges/{charge_id}`

- comando:
  - `php artisan billing:pagarme:homologate --scenario=pix-cancel --poll-attempts=2 --poll-sleep-ms=1000`
- report:
  - `apps/api/storage/app/pagarme-homologation/20260405-185658-pix-cancel.json`
- ids reais:
  - `gateway_order_id = or_Pjepz7cRwcWPM3Lv`
  - `gateway_charge_id = ch_W86MLK7fqPuvEkx9`
- resultado observado:
  - criacao: `order.status = pending`, `charge.status = pending`, `last_transaction.status = waiting_payment`
  - resposta imediata do `DELETE /charges/ch_W86MLK7fqPuvEkx9`: `charge.status = processing`, `last_transaction.status = pending_refund`
  - snapshots seguintes: `order.status = canceled`, `charge.status = canceled`, `last_transaction.status = refunded`

Cartao estornado por `DELETE /charges/{charge_id}`

- comando:
  - `php artisan billing:pagarme:homologate --scenario=card-refund --poll-attempts=2 --poll-sleep-ms=1000`
- report:
  - `apps/api/storage/app/pagarme-homologation/20260405-185659-card-refund.json`
- ids reais:
  - `gateway_order_id = or_vK67XjUm2CPkWGZz`
  - `gateway_charge_id = ch_okAOJQkMujFxbNqP`
- resultado observado:
  - criacao: `order.status = paid`, `charge.status = paid`
  - resposta imediata do `DELETE /charges/ch_okAOJQkMujFxbNqP`: `charge.status = canceled`, `last_transaction.status = refunded`
  - snapshots seguintes: `order.status = canceled`, `charge.status = canceled`, `last_transaction.status = refunded`

Revalidacao agregada da rodada atual:

- comando:
  - `php artisan billing:pagarme:homologate --scenario=all --poll-attempts=2 --poll-sleep-ms=1000`
- report:
  - `apps/api/storage/app/pagarme-homologation/20260405-210058-all.json`
- resumo observado:
  - Pix cancelado permaneceu consistente com `order.status = canceled`,
    `charge.status = canceled` e `last_transaction.status = refunded`
  - cartao estornado permaneceu consistente com `order.status = canceled`,
    `charge.status = canceled` e `last_transaction.status = refunded`
  - o dossie dos simuladores `0036`, `0044` e `0069` continuou reproduzindo o
    mesmo mismatch de contexto do fluxo PSP desta conta

Conclusao operacional:

- o contrato oficial de cancelamento por `DELETE /charges/{charge_id}` foi confirmado na pratica;
- Pix e cartao podem passar por estado intermediario antes de convergir no snapshot final;
- para conciliacao local, `charge.status` e `last_transaction.status` precisam ser lidos juntos, principalmente em cancelamento e estorno.

Politica operacional fechada para compra unica no `eventovivo`:

- o checkout publico nao expoe botao de cancelamento para o comprador;
- cancelamento ou estorno de compra unica acontecem internamente no backoffice ou no painel da Pagar.me;
- quando o pedido ainda nao foi pago, o `order.canceled` recebido por webhook passa o pedido local para `canceled`;
- quando a cobranca ja foi paga e depois revertida, `charge.refunded` ou `charge.partial_canceled` passam o pedido local para `refunded` e revogam a compra/grant do evento;
- `charge.chargedback` continua existindo no estado tecnico local, mas a resposta de produto e notificacao para esse caso ainda depende de decisao comercial.

### 15.9 Observacao real da homologacao dos simuladores `0036`, `0044` e `0069`

Na rodada de homologacao executada em `2026-04-05`, eu revalidei os cenarios
com o fluxo real do `eventovivo`:

- frontend/tokenizacao oficial gerando `card_token`;
- backend convertendo esse token para `customer_id + card_id`;
- criacao do pedido por `POST /orders`;
- polling sempre local em `GET /public/event-checkouts/{uuid}`.

Os resultados observados foram estes:

- `4000000000000036`
  - `billing_order_uuid = f969638a-3da4-44dd-955d-cb07bec6739e`
  - `gateway_order_id = or_Yg093QDcLlf95q1v`
  - `gateway_charge_id = ch_kQM7ma35C0iE1zab`
  - retorno local sincrono: `paid`
  - `GET /orders/or_Yg093QDcLlf95q1v`: `status = paid`, `charge.status = paid`, `last_transaction.status = captured`
- `4000000000000044`
  - `billing_order_uuid = 14ac8425-fd44-469c-9158-f6f73f2da4f6`
  - `gateway_order_id = or_Qv0yO1lIaefQQkwP`
  - `gateway_charge_id = ch_veJK2rrIgpTBeKMa`
  - retorno local sincrono: `paid`
  - `GET /orders/or_Qv0yO1lIaefQQkwP`: `status = paid`, `charge.status = paid`, `last_transaction.status = captured`
- `4000000000000069`
  - `billing_order_uuid = e1c80d96-1754-4c14-b07d-925ba2f5c59f`
  - `gateway_order_id = or_1VW4zztl5i3j430m`
  - `gateway_charge_id = ch_b0jdxoPUquMREVQD`
  - polling local entre `2026-04-05 00:33:38 BRT` e `2026-04-05 00:34:33 BRT`: permaneceu `paid`
  - `GET /orders/or_1VW4zztl5i3j430m` e `GET /charges/ch_b0jdxoPUquMREVQD`: permaneceram `paid`

Para tirar a duvida de contexto, eu rodei um probe direto contra a v5 com o
comando:

- `php artisan billing:pagarme:homologate --scenario=simulator-dossier --poll-attempts=3 --poll-sleep-ms=1000`

Report gerado:

- `apps/api/storage/app/pagarme-homologation/20260405-185925-simulator-dossier.json`
- `apps/api/storage/app/pagarme-homologation/20260405-210058-all.json`

Resumo real do probe direto:

- `4000000000000036`
  - `gateway_order_id = or_qeYr6X2uJFZBV5vk`
  - `gateway_charge_id = ch_y0V19oGPcVCg98w5`
  - criacao: `order.status = paid`, `charge.status = paid`
  - snapshot final: `order.status = paid`, `charge.status = paid`, `last_transaction.status = captured`
- `4000000000000044`
  - `gateway_order_id = or_DN96nJpTZphmv68R`
  - `gateway_charge_id = ch_7k1gr9nU5zH2Wd3n`
  - criacao: `order.status = paid`, `charge.status = paid`
  - snapshot final: `order.status = paid`, `charge.status = paid`, `last_transaction.status = captured`
- `4000000000000069`
  - `gateway_order_id = or_j6nz7qPi3hQY4RVp`
  - `gateway_charge_id = ch_47BjgbDh0IDq9plM`
  - criacao: `order.status = paid`, `charge.status = paid`
  - snapshot final: `order.status = paid`, `charge.status = paid`, `last_transaction.status = captured`

Conclusao tecnica mais segura:

- a divergencia ficou confirmada duas vezes:
  - no fluxo real do checkout do `eventovivo`
  - no probe direto contra a API v5, sem depender do frontend nem do webhook local
- a propria doc oficial do `Simulador de Cartao de Credito` o descreve como
  simulador nao financeiro e orienta usar o `Simulador PSP` para fluxos
  financeiros de cartao;
- entao, hoje, a explicacao mais forte nao e bug da maquina de estados local;
- a explicacao mais forte e mismatch de contexto entre o simulador Gateway e o
  fluxo PSP desta conta.

Se o time precisar fechar isso com o suporte da Pagar.me, o dossie ja esta
pronto com ids reais, snapshots oficiais e report JSON anexavel.

### 15.10 Validacao real de idempotencia em 2026-04-05

Na homologacao real, eu rodei `POST /orders` diretamente contra a Pagar.me com
a mesma `Idempotency-Key = eventovivo-idem-20260405092541` e com corpo
ligeiramente diferente no segundo envio, ainda dentro da janela de sandbox.

Resultado observado:

- primeira resposta: `order_id = or_V38lMVMhwXFQ9MwB`, `status = failed`
- segunda resposta: `order_id = or_V38lMVMhwXFQ9MwB`, `status = failed`
- conclusao: a Pagar.me devolveu o mesmo pedido mesmo com `metadata` diferente
  no segundo request

Isso confirma, na pratica, o contrato oficial que a doc v5 descreve:

- em producao a chave vale 24 horas;
- em sandbox a doc continua apontando 5 minutos;
- mesmo com body diferente, a mesma chave continua apontando para um unico
  pedido.

### 15.11 Replay real de webhook validado em 2026-04-05

Na primeira tentativa do dia, o replay real do provider ainda falhou porque a
dashboard apontava para o Pinggy expirado
`https://gyyyq-186-225-238-115.run.pinggy-free.link/api/v1/webhooks/billing/pagarme`.

Depois da troca para o hostname fixo do Cloudflare
`https://webhooks-local.eventovivo.com.br/api/v1/webhooks/billing/pagarme`, a
validacao foi refeita ponta a ponta.

Caso real usado para validar:

- `billing_order_uuid = e6781d7d-cffc-46a6-95fa-a2faaa9e0c1e`
- `gateway_order_id = or_9nL0kBEjiQHZwJ83`
- `gateway_charge_id = ch_o7PypPTVxt9eyamv`
- `gateway_transaction_id = tran_EDNOld3UkhBoOXWA`
- hook entregue com sucesso: `hook_NQnjE65KiRIyVeKA`

Resultado observado:

- `GET /hooks/hook_NQnjE65KiRIyVeKA` passou a mostrar a URL fixa
  `https://webhooks-local.eventovivo.com.br/api/v1/webhooks/billing/pagarme`;
- o hook real veio com `event = charge.paid`, `status = sent` e
  `response_status = 200`;
- o `eventovivo` processou o pedido localmente como `paid`, criando exatamente
  `1 payment` e `1 purchase`;
- em seguida eu executei `POST /hooks/hook_NQnjE65KiRIyVeKA/retry`;
- no banco local, o mesmo `BillingGatewayEvent` continuou com `id = 69` e
  `status = processed`, mas o `updated_at` andou de `12:38:11Z` para
  `12:39:24Z`, provando que a reentrega real bateu no mesmo registro;
- mesmo depois do replay real, as contagens locais permaneceram
  `payment_count = 1`, `purchase_count = 1` e `gateway_events = 5` para esse
  pedido.

Conclusao operacional:

- o replay real do provider esta validado com hostname fixo;
- a deduplicacao local do `eventovivo` segurou a reentrega sem duplicidade
  financeira;
- o bloqueio anterior era do tunnel expirado, nao do controller ou da fila do
  modulo `Billing`.

---

## 16. Ordem recomendada de execucao dos testes

Rodar nesta ordem:

1. Pix sucesso
2. Pix falha
3. Cartao PSP aprovado
4. Cartao nao autorizado
5. Cartao `processing` -> sucesso
6. Cartao `processing` -> falha
7. Cartao PSP antifraude/recusa
8. Chargeback
9. Cancelamento/estorno
10. Reentrega do mesmo webhook
11. Repeticao da mesma criacao com a mesma `Idempotency-Key`

O item final e obrigatorio:

- a `Idempotency-Key` vale por 24 horas em producao e 5 minutos em sandbox;
- a mesma chave pode continuar produzindo um unico pedido mesmo se o body mudar;
- o backend local precisa provar que timeout e retry nao geram duplicidade.

Ao final dessa bateria, a integracao so deve ser considerada aprovada se o time provar:

- criacao do pedido local e externo;
- Pix com QR Code e liquidacao via webhook;
- cartao com sucesso, falha e estados intermediarios;
- chargeback e estorno atualizando o estado local;
- webhook idempotente;
- criacao do pedido idempotente;
- frontend sem trafegar PAN/CVV para o backend;
- `customer` completo para o modelo operacional da conta.

---

## 17. Decisoes abertas

1. O `payer` sempre coincide com o `responsible_name` da jornada ou pode ser terceiro?
2. Chargeback de compra unica bloqueia imediatamente o evento ou entra em fila de revisao?
3. O cancelamento publico antes do pagamento vai expirar o evento criado ou apenas cancelar a cobranca?

---

## 18. Recomendacao final

O melhor caminho para o `eventovivo` nao e copiar o CRM literalmente.

O caminho certo e:

1. manter `BillingOrder` local como centro do dominio;
2. plugar `PagarmeBillingGateway` na interface ja existente;
3. tratar `POST /orders` como operacao idempotente com chave persistida;
4. persistir snapshot bruto e campos operacionais do gateway;
5. tokenizar cartao oficialmente fora do backend;
6. tratar webhook como fonte de verdade para Pix e reconciliacao complementar para cartao;
7. expor `GET /public/event-checkouts/{uuid}` lendo apenas o estado local;
8. deixar `POST /public/event-checkouts/{uuid}/confirm` como fallback manual, nao fluxo feliz.

---

## 19. Proximo corte de produto: onboarding, UX publica e notificacoes

Depois de plugar a base financeira da Pagar.me v5, o maior gap remanescente ja
nao esta na integracao HTTP. O gap agora esta na jornada publica.

### 19.1 O que ja existe no backend e o frontend ainda aproveita pouco

O `eventovivo` ja entrega mais contexto do que a tela publica atual consome:

- `CreatePublicEventCheckoutAction` ja cria `User`, `Organization`, `Event` e
  `BillingOrder` em uma unica transacao;
- a resposta publica ja devolve `token`, `user`, `organization`, `event` e
  `onboarding.next_path`;
- `GET /api/v1/public/event-checkouts/{uuid}` ja devolve status local,
  `gateway_status`, dados de Pix, dados operacionais de cartao e estado
  comercial do evento.

Conclusao pratica:

- o cadastro do usuario ja esta integrado ao checkout;
- a rodada atual ja expôs esse onboarding na pagina publica, com CTA para abrir
  o painel do evento e ramo de login quando a identidade ja existe;
- a retomada apos autenticacao agora esta fechada de forma segura:
  - Pix pode ser retomado automaticamente com a conta autenticada
  - cartao restaura apenas os campos seguros e exige novo preenchimento dos
    dados sensiveis;
- o que ainda falta agora e refinamento de produto:
  - transformar a tela em jornada em etapas
  - reduzir ainda mais a friccao visual do onboarding.

### 19.2 Melhorias reais de onboarding

O primeiro corte de UX que faz sentido e este:

1. trocar a sensacao de "formulario unico" por uma jornada em etapas:
   - pacote
   - dados do responsavel
   - dados do evento
   - pagamento
   - acompanhamento do pedido
2. mostrar explicitamente que a conta e o evento foram criados com sucesso;
3. usar o `token` retornado para oferecer CTA claro:
   - "acompanhar pedido"
   - "entrar no painel do evento"
4. tratar o caso de identidade existente como ramo de jornada, nao como erro
   seco:
   - "este WhatsApp ja possui cadastro"
   - CTA para login/OTP
   - retomada do checkout depois da autenticacao
5. garantir que a retomada seja segura:
   - sem persistir PAN, CVV ou validade
   - com auto-resume apenas para Pix
   - com restauracao manual assistida para cartao

Veredito:

- o backend ja resolve o cadastro tecnico;
- a retomada tecnica agora esta fechada;
- a melhoria real daqui para frente e a orquestracao visual da jornada.

### 19.3 Melhorias reais de UX para Pix

Nesta rodada, a UX de Pix do checkout publico foi endurecida sem mudar a regra
de arquitetura:

- a tela continua lendo apenas o estado local do `BillingOrder`;
- o QR Code agora aparece com contador visual baseado em `expires_at`;
- o comprador consegue copiar o codigo Pix com feedback imediato;
- quando `qr_code_url` existe, a UI expoe CTA para abrir o QR em nova aba;
- a pagina mostra uma timeline local de status:
  - pedido criado
  - Pix aguardando pagamento
  - pagamento confirmado
  - pagamento expirado ou falho
- o `uuid` do checkout continua salvo localmente para retomada quando o usuario
  volta para a pagina.

O gap que ainda sobra aqui nao e de gateway, e de refinamento de produto:

- deixar a mensagem de eventual consistency ainda mais explicita;
- integrar notificacao proativa ao cliente quando a confirmacao chegar.

### 19.4 Melhorias reais de UX para cartao

Nesta rodada, o checkout publico de cartao deixou de ser apenas funcional e
passou a ter uma UX mais consistente com um fluxo comercial:

- mascaras visuais para CPF, telefone, CEP e numero do cartao;
- normalizacao antes da tokenizacao e antes do payload final;
- formulario reagrupado em blocos de `pagador`, `endereco de cobranca` e
  `cartao`;
- preview seguro do cartao no navegador, com hint de bandeira;
- copy de seguranca explicando que a tokenizacao ocorre na Pagar.me e o backend
  recebe apenas `card_token`;
- feedback inline para:
  - `processing`
  - `failed`
  - `paid`
  - `refunded`
  - `chargedback`

O ponto estrutural continua o mesmo:

- o browser tokeniza;
- o backend nao recebe PAN/CVV;
- a pagina continua lendo o estado local para reconciliacao e polling.

### 19.5 Notificacoes ativas ao cliente via Z-API

Hoje o modulo `WhatsApp` ja tem a base tecnica para outbound:

- `WhatsAppMessagingService`;
- `SendWhatsAppMessageJob`;
- provider Z-API com `send-text`;
- suporte adicional a `send-button-pix` quando a instancia resolvida usa `zapi`;
- fila dedicada `whatsapp-send`.

Esse corte agora ja foi implementado assim:

1. `BillingPaymentStatusNotificationService` centraliza o envio de notificacoes
   ativas do checkout publico;
2. `BillingWhatsAppInstanceResolver` centraliza a resolucao da instancia de
   envio para billing;
3. `billing_order_notifications` persiste deduplicacao e troubleshooting por
   `billing_order_id + notification_type + channel`;
4. os gatilhos nascem apenas de transicoes locais:
   - `CreateEventPackageGatewayCheckoutAction` para `pix_generated`
   - `MarkBillingOrderAsPaidAction` para `payment_paid`
   - `FailBillingOrderAction` para `payment_failed`
   - `RefundBillingOrderAction` para `payment_refunded`
5. o outbound continua usando `WhatsAppMessagingService`, com `send-text` em todos os casos e `send-button-pix` adicional em `pix_generated` quando o provider e `zapi`;
6. o `whatsapp_messages.payload_json.context` agora carrega identificacao do
   pedido e do tipo de notificacao;
7. o payload local do checkout agora devolve o resumo dessas notificacoes em
   `checkout.payment.whatsapp`, sem consultar Z-API nem gateway diretamente;
8. `chargeback` continua fora da primeira rodada, aguardando politica de
   produto.

Variaveis novas para o ambiente:

- `BILLING_PAYMENT_NOTIFICATIONS_ENABLED`
- `BILLING_PAYMENT_NOTIFICATIONS_WHATSAPP_INSTANCE_ID`
- `BILLING_PAYMENT_NOTIFICATIONS_ALLOW_SINGLE_CONNECTED_FALLBACK`

Mensagem operacional importante:

- a fonte de verdade continua sendo o estado local do `BillingOrder`;
- a notificacao sai a partir da conciliacao local, nao direto da resposta
  imediata do provider.
- quando nao houver sender operacional, a tentativa fica registrada como
  `unavailable` ou `skipped`, sem abortar o pedido.

### 19.6 Backlog de produto que faz mais sentido agora

Se a meta atual e melhorar a compra unica sem reabrir a base da integracao, a
ordem certa e esta:

1. endurecer a pagina publica com onboarding visivel e retomada da sessao;
2. fechar a homologacao real de cancelamento/estorno e a politica de
   `chargeback`;
3. so depois disso discutir refinamentos secundarios como parcelamento mais
   rico, `sendPixButton` ou experiencias adicionais.

Se essa ordem for seguida, a integracao fecha sem quebrar o desenho atual do modulo `Billing`, aproveitando quase tudo que o `eventovivo` ja construiu e removendo os pontos mais frageis para producao: duplicidade de pedido, polling cascata no gateway, cartao trafegando no backend e conciliacao pobre.
