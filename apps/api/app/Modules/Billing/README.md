# Billing Module

## Responsabilidade

Assinaturas, jornadas publicas de trial e compra por evento, catalogo avulso, grants comerciais e a trilha financeira minima do produto.

## Entidades principais

- `Subscription` - assinatura recorrente da organizacao
- `EventPackage` - catalogo avulso por evento
- `EventPurchase` - registro de compra avulsa ainda usado por historico comercial e KPIs
- `EventAccessGrant` - ativacao efetiva do evento
- `BillingOrder` - pedido de assinatura ou pacote
- `BillingOrderItem` - itens do pedido
- `Payment` - liquidacao minima vinculada ao pedido
- `Invoice` - historico financeiro emitido a partir do pedido
- `BillingGatewayEvent` - trilha de webhook, idempotencia e resultado do provider
- `BillingOrderNotification` - notificacoes deduplicadas de status ao cliente a partir da maquina de estados local

## Rotas principais

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | `/api/v1/public/event-packages` | Catalogo publico de pacotes por evento |
| POST | `/api/v1/public/trial-events` | Cria jornada publica de trial |
| POST | `/api/v1/public/event-checkouts` | Inicia checkout publico de evento unico |
| GET | `/api/v1/public/event-checkouts/{billingOrder:uuid}` | Retorna o status local do checkout publico sem consultar o gateway |
| POST | `/api/v1/public/event-checkouts/{billingOrder:uuid}/confirm` | Confirma checkout simplificado e ativa o evento |
| POST | `/api/v1/webhooks/billing/{provider}` | Recebe webhook do gateway e processa idempotentemente |
| POST | `/api/v1/billing/orders/{billingOrder:uuid}/retry` | Repete com seguranca a criacao externa do mesmo pedido, reaproveitando a chave idempotente atual |
| POST | `/api/v1/billing/orders/{billingOrder:uuid}/refresh` | Faz sync administrativo com o gateway para troubleshooting e reconciliacao manual |
| POST | `/api/v1/admin/quick-events` | Cria evento assistido com grant `bonus` ou `manual_override`, inclusive `manual_override` sem pacote usando snapshots diretos |
| GET | `/api/v1/billing/subscription` | Assinatura atual da organizacao autenticada |
| POST | `/api/v1/billing/subscription/cancel` | Cancela a assinatura recorrente atual da organizacao |
| GET | `/api/v1/billing/invoices` | Historico de invoices reais da organizacao |
| POST | `/api/v1/billing/checkout` | Checkout simplificado de assinatura recorrente |
| GET | `/api/v1/event-packages` | Catalogo autenticado de pacotes |
| GET | `/api/v1/event-packages/{id}` | Detalhe de pacote |

## Observacoes

- a compra direta por evento ja existe em versao simplificada, com provider `manual` e confirmacao manual/webhook;
- `billing_orders` e `billing_order_items` sustentam assinatura recorrente e checkout publico de evento;
- `payments` e `invoices` ja existem em trilha minima para assinatura e compra avulsa;
- o cancelamento da assinatura da conta ja existe em versao inicial e suporta:
  - cancelamento imediato
  - cancelamento ao fim do ciclo
- quando o cancelamento ocorre ao fim do ciclo, os entitlements da conta continuam ativos ate `ends_at`;
- `BillingGatewayInterface`, `BillingGatewayManager` e `ManualBillingGateway` ja isolam o provider do dominio;
- `PagarmeBillingGateway` ja cobre Pix, cartao, webhook, refund/cancel, refresh administrativo e retry operacional seguro do mesmo `BillingOrder`;
- a pagina publica `/checkout/evento` agora ja expÃµe onboarding visivel, CTA para abrir o painel do evento e CTA de login quando o checkout encontra uma identidade ja cadastrada;
- o billing agora deduplica notificacoes de `pix_generated`, `payment_paid`, `payment_failed` e `payment_refunded` em `billing_order_notifications`, sempre a partir do estado local reconciliado;
- essas notificacoes usam `WhatsAppMessagingService` e gravam contexto do pedido tambem em `whatsapp_messages.payload_json.context`;
- no caso de `pix_generated`, o checkout agora envia `send-text` com resumo do pedido e, quando a instancia resolvida usa `zapi`, tambem tenta um segundo outbound `send-button-pix` usando o valor copia-e-cola retornado no `qr_code` da cobranca;
- o payload local do checkout agora expoe o resumo dessas notificacoes em `checkout.payment.whatsapp`, para a UI informar que o Pix tambem foi enviado ao WhatsApp do comprador;
- `admin/quick-events` agora consegue enfileirar envio real de acesso por WhatsApp quando existir sender configurado no ambiente de billing;
- `admin/quick-events` agora aceita `manual_override` sem `package_id`, desde que o request informe `grant.features` e/ou `grant.limits`;
- esses snapshots diretos passam a alimentar `features_snapshot_json`, `limits_snapshot_json` e o `current_entitlements_json` do evento;
- o `EntitlementResolverService` agora materializa um bloco `channels` com capacidades de grupos, DM, upload publico, blacklist, instancia compartilhada/dedicada e mensagem padrao de feedback negativo;
- quando nao houver instancia operacional disponivel para esse envio, a jornada continua com `access_delivery.status=unavailable`, sem abortar a criacao do evento;
- `chargeback` continua fora da primeira rodada de notificacao ativa, aguardando politica de produto;
- o proximo endurecimento do modulo ficou concentrado na homologacao real de cancelamento/estorno e no dossie da divergencia do simulador de cartao.
- a nova jornada administrativa de Pix avulso sem `Event`, para onboarding comercial e associacao futura a um evento, ainda e planejada e esta documentada em:
  - `docs/architecture/billing-admin-customer-onboarding-discovery.md`
  - `docs/execution-plans/billing-admin-customer-onboarding-execution-plan.md`
