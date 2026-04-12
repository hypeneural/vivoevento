# Billing Admin Customer Onboarding Discovery

## Objetivo

Este documento consolida a arquitetura recomendada para uma nova jornada
administrativa no modulo `Billing`, separada do checkout publico de
`event_package`.

Nome de trabalho da jornada:

- `AdminCommercialIntent`
- ou `AdminCustomerOnboarding`

Escopo desta discovery:

- super-admin ou platform-admin cria ou reaproveita `User` + `Organization`;
- o sistema gera um `access_code` aleatorio proprio;
- o sistema cria um pedido Pix na Pagar.me v5 sem depender de `Event`;
- o cliente recebe mensagem por WhatsApp com:
  - `access_code`
  - status inicial
  - QR Code Pix em texto ou link
  - instrucoes de continuidade;
- o sistema sabe se foi pago ou nao por estado local reconciliado;
- so depois o credito pago pode ser associado a um evento futuro.

Documento complementar:

- ver
  [billing-admin-customer-onboarding-execution-plan.md](../execution-plans/billing-admin-customer-onboarding-execution-plan.md)
  para a ordem detalhada de execucao em TDD.

## Referencias oficiais confirmadas

- Criar pedido: `https://docs.pagar.me/reference/criar-pedido-2`
- Pix: `https://docs.pagar.me/reference/pix-2`
- Obter cobranca: `https://docs.pagar.me/reference/obter-cobran%C3%A7a`
- Cancelar cobranca: `https://docs.pagar.me/reference/cancelar-cobran%C3%A7a`
- Eventos de webhook: `https://docs.pagar.me/reference/eventos-de-webhook-1`

Pontos oficiais relevantes para esta jornada:

- a venda continua centrada em `POST /orders`;
- para Pix, a resposta retorna `qr_code`, `qr_code_url` e `expires_at` em
  `last_transaction`;
- para PSP, o `customer` precisa estar completo, com endereco e telefone;
- o estado final nao deve depender apenas da resposta sincrona:
  `order.paid`, `order.payment_failed`, `order.canceled`, `charge.refunded`,
  `charge.partial_canceled` e `charge.chargedback` continuam sendo eventos de
  reconciliacao;
- `DELETE /charges/{charge_id}` continua sendo a operacao de cancelamento ou
  estorno, quando o backoffice precisar reverter a cobranca.

## Veredito executivo

Hoje o repo **nao** tem esse fluxo pronto.

O que ja existe e pode ser reaproveitado:

- `admin/quick-events` ja cria ou reaproveita `User` + `Organization`;
- `PublicJourneyIdentityService` ja normaliza telefone e email;
- `BillingOrder`, `Payment`, `Invoice` e `BillingGatewayEvent` ja sustentam
  estado financeiro minimo;
- `PagarmeBillingGateway` ja cria Pix, trata webhook, refresh e refund/cancel;
- `BillingPaymentStatusNotificationService` e Z-API ja notificam o cliente a
  partir da maquina de estados local.

O que nao existe hoje:

- um fluxo administrativo sem `Event`;
- um `BillingOrderMode` novo para onboarding comercial;
- uma entidade canonica para guardar `access_code` e status de associacao a
  evento futuro;
- uma UX administrativa para criar esse onboarding e acompanhar pagamento;
- a regra de consumo posterior desse credito pago ao associar a um evento.

Conclusao pratica:

- isso nao deve entrar no checkout publico atual;
- isso nao deve ser encaixado como um desvio do `admin/quick-events`;
- isso deve nascer como uma jornada propria do modulo `Billing`, com contrato,
  estado e testes proprios.

## Distincao em relacao aos fluxos atuais

### Checkout publico atual

Fluxo atual:

- cria `User`, `Organization`, `Event` e `BillingOrder`;
- compra um `event_package`;
- pagamento aprovado ativa o pacote no evento.

### Admin quick event atual

Fluxo atual:

- cria ou reaproveita `User` + `Organization`;
- cria `Event`;
- cria `EventAccessGrant`;
- opcionalmente envia mensagem de acesso ao evento.

### Nova jornada proposta

Fluxo proposto:

- cria ou reaproveita `User` + `Organization`;
- **nao cria `Event`**;
- gera `access_code`;
- cria `AdminCommercialIntent`;
- cria `BillingOrder` em novo `mode`;
- gera Pix na Pagar.me;
- envia mensagem comercial e de pagamento;
- aguarda pagamento;
- so depois permite associar o credito pago a um evento futuro.

## O que o codigo atual ja oferece e vale reaproveitar

### 1. Reuso de identidade

O padrao ja esta pronto em `CreateAdminQuickEventAction`:

- normalizacao de telefone/email;
- reaproveitamento de `User`;
- reaproveitamento ou criacao de `Organization`;
- criacao de membership administrativa.

Essa parte deve ser extraida ou reaproveitada sem duplicacao.

### 2. Gateway real Pagar.me v5

O `PagarmeBillingGateway` ja resolve:

- `POST /orders`;
- Pix com `qr_code`, `qr_code_url`, `expires_at`;
- parsing de webhook;
- refresh administrativo com `GET /orders/{id}` e `GET /charges/{id}`;
- cancelamento/estorno por `charge_id`.

Ou seja:

- o novo fluxo nao precisa de outro gateway;
- ele precisa de **outro mode de pedido** e outra maquina de ativacao.

### 3. Maquina de estados local

Hoje o `Billing` ja sabe lidar com:

- `pending_payment`
- `paid`
- `failed`
- `canceled`
- `refunded`

Isso continua valendo aqui.

O que muda e o efeito de negocio:

- `paid` nao ativa pacote de evento;
- `paid` libera um onboarding comercial pago;
- `refunded` ou `chargeback` podem bloquear associacao futura ou reverter
  vinculacoes ja consumidas, conforme politica de produto.

### 4. Notificacao proativa

Hoje a base de notificacao ja existe para `event_package`.

Essa nova jornada deve reaproveitar o mesmo padrao:

- persistencia em `billing_order_notifications`;
- deduplicacao;
- envio via `WhatsAppMessagingService`;
- disparo sempre a partir do estado local.

## Proposta de modelagem de dominio

## 1. Nova entidade canonica

Criar uma entidade propria:

- `AdminCommercialIntent`

Responsabilidade:

- representar um onboarding comercial iniciado internamente;
- ligar identidade do cliente, pagamento e futuro consumo em evento;
- guardar `access_code` e seu ciclo de vida.

Campos recomendados:

- `id`
- `uuid`
- `organization_id`
- `responsible_user_id`
- `billing_order_id`
- `status`
- `access_code_encrypted`
- `access_code_last4`
- `access_code_generated_at`
- `payment_status`
- `paid_at`
- `failed_at`
- `canceled_at`
- `refunded_at`
- `linked_event_id`
- `linked_at`
- `consumed_at`
- `metadata_json`
- `gateway_snapshot_json`
- `whatsapp_delivery_json`
- `created_by_user_id`
- `created_at`
- `updated_at`

Observacao importante:

- se o produto precisar reenviar o codigo aleatorio exatamente igual, o codigo
  deve ficar armazenado com cast `encrypted`;
- se o produto aceitar regeneracao em vez de reenvio do mesmo codigo, o ideal e
  guardar so hash + `last4`.

Minha recomendacao pragmatica:

- armazenar `access_code_encrypted` + `access_code_last4`;
- nunca logar o codigo completo;
- usar `last4` para suporte e trilha de auditoria.

## 2. Novo mode em `BillingOrder`

Criar um novo `BillingOrderMode`:

- `commercial_intent`
  ou
- `customer_onboarding`

Minha recomendacao:

- usar `commercial_intent`, porque o objeto pode depois ser associado a mais de
  um tipo de jornada comercial, nao apenas onboarding.

Efeito disso:

- `BillingGatewayManager` precisa saber qual provider usar para esse mode;
- `RegisterBillingGatewayPaymentAction` nao pode mandar esse mode para
  `ActivatePaidEventPackageOrderAction`;
- ele precisa chamar uma action nova, por exemplo
  `MarkAdminCommercialIntentAsPaidAction`.

## 3. Relacao com evento futuro

Nao associe esse pagamento a `Event` na criacao do Pix.

O correto e separar em duas fases:

1. `intent` pago
2. `intent` associado e consumido em um evento futuro

Estados sugeridos:

- `draft`
- `pending_payment`
- `paid`
- `payment_failed`
- `canceled`
- `refunded`
- `linked_to_event`
- `consumed`
- `voided`

Separacao importante:

- `paid` significa "o credito comercial existe";
- `linked_to_event` significa "o credito foi reservado para um evento";
- `consumed` significa "o credito ja produziu grant/pacote no evento".

## Maquina de estados recomendada

### Estado do `BillingOrder`

- `pending_payment`
- `paid`
- `failed`
- `canceled`
- `refunded`

### Estado do `AdminCommercialIntent`

- `pending_payment`
- `paid`
- `failed`
- `canceled`
- `refunded`
- `linked_to_event`
- `consumed`

### Eventos Pagar.me relevantes

- `order.paid` -> pedido pago
- `order.payment_failed` -> pedido falhou
- `order.canceled` -> pedido cancelado
- `charge.refunded` -> estorno
- `charge.partial_canceled` -> estorno parcial
- `charge.chargedback` -> chargeback

Decisao recomendada para esta primeira fase:

- implementar `paid`, `failed`, `canceled` e `refunded`;
- registrar `chargedback`, mas deixar a politica de produto isolada.

## Como saber se foi pago ou nao

Essa pergunta precisa de uma resposta objetiva no contrato:

- **fonte de verdade do admin nao e a dashboard da Pagar.me**;
- a fonte de verdade do admin e o estado local do `BillingOrder` +
  `AdminCommercialIntent`.

Fluxo correto:

1. `POST /orders` cria o Pix e devolve `qr_code`/`expires_at`;
2. o backend persiste snapshot local;
3. a Pagar.me envia webhook;
4. o webhook atualiza `BillingOrder`;
5. uma action local sincroniza `AdminCommercialIntent.status`;
6. o painel administrativo consulta **so o endpoint local**.

Endpoints administrativos recomendados:

- `POST /api/v1/admin/customer-onboarding-intents`
- `GET /api/v1/admin/customer-onboarding-intents/{uuid}`
- `GET /api/v1/admin/customer-onboarding-intents`
- `POST /api/v1/admin/customer-onboarding-intents/{uuid}/refresh`
- `POST /api/v1/admin/customer-onboarding-intents/{uuid}/resend`
- `POST /api/v1/admin/customer-onboarding-intents/{uuid}/attach-event`

O endpoint `show` precisa devolver:

- status do onboarding;
- status do billing local;
- `gateway_status`;
- `paid_at` ou `failed_at`;
- Pix atual (`qr_code`, `qr_code_url`, `expires_at`);
- historico de notificacoes;
- se ja foi associado a algum evento.

## Payload recomendado da criacao administrativa

```json
{
  "responsible_name": "Camila Rocha",
  "whatsapp": "5548999771111",
  "email": "camila@example.com",
  "organization_name": "Camila e Bruno",
  "organization_type": "direct_customer",
  "amount_cents": 19900,
  "send_whatsapp": true,
  "notes": "Lead vindo do comercial",
  "metadata": {
    "origin": "super-admin",
    "campaign": "assistido-whatsapp"
  }
}
```

Resposta recomendada:

- `intent.uuid`
- `intent.status`
- `access_code_masked`
- `billing_order.uuid`
- `payment.status`
- `payment.pix.qr_code`
- `payment.pix.qr_code_url`
- `payment.pix.expires_at`
- `whatsapp_delivery.status`

## Mensagem WhatsApp recomendada

Como a base atual do repo esta em `send-text`, a primeira fase deve usar texto
simples com link do QR e codigo Pix.

Mensagem recomendada:

1. cabecalho:
   - `Evento Vivo`
2. contexto:
   - "Seu onboarding comercial foi iniciado"
3. `access_code`
4. status:
   - `aguardando pagamento`
   - `pagamento confirmado`
   - `pagamento nao aprovado`
5. Pix:
   - valor
   - expiracao
   - `qr_code_url`
   - copia e cola
6. instrucao:
   - "Guarde este codigo. Ele sera usado para vincular seu acesso a um evento futuro."

## Regras de produto recomendadas

### Fase 1

- Pix apenas
- sem `Event` na criacao
- sem autoativacao de pacote
- sem cancelamento pelo cliente
- associacao ao evento feita depois por operador/admin

### Regras importantes

- um `AdminCommercialIntent` pago pode ficar "em carteira" ate ser associado;
- o `access_code` nao substitui autenticacao, ele e um codigo comercial;
- o usuario ainda pode existir sem evento;
- o pagamento pago nao cria grant sozinho.

## Riscos se tentarmos encaixar isso no fluxo atual

Nao faca isso como:

- desvio do checkout publico `event_package`;
- flag dentro de `admin/quick-events`;
- pedido `event_package` sem `event_id`.

Motivo:

- isso mistura onboarding comercial com ativacao de evento;
- dificulta conciliacao;
- cria excecoes na action que hoje ativa pacote diretamente;
- aumenta risco de regressao no checkout que ja esta validado.

## Veredito final da discovery

O melhor caminho e:

1. criar uma nova entidade `AdminCommercialIntent`;
2. criar um novo `BillingOrderMode` para esse fluxo;
3. reaproveitar `PagarmeBillingGateway` apenas para Pix na primeira fase;
4. reaproveitar webhook, refresh e notificacao local;
5. expor UX administrativa propria;
6. so depois ligar o pagamento pago a um evento futuro.

Isso preserva o que ja esta estavel no checkout publico e abre uma trilha
administrativa com menos friccao para o time comercial.
