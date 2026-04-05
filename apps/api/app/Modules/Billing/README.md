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
| POST | `/api/v1/admin/quick-events` | Cria evento assistido com grant `bonus` ou `manual_override` |
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
- a pagina publica `/checkout/evento` agora ja expõe onboarding visivel, CTA para abrir o painel do evento e CTA de login quando o checkout encontra uma identidade ja cadastrada;
- o billing agora deduplica notificacoes de `pix_generated`, `payment_paid`, `payment_failed` e `payment_refunded` em `billing_order_notifications`, sempre a partir do estado local reconciliado;
- essas notificacoes usam `WhatsAppMessagingService` + Z-API `send-text` e gravam contexto do pedido tambem em `whatsapp_messages.payload_json.context`;
- `admin/quick-events` agora consegue enfileirar envio real de acesso por WhatsApp quando existir sender configurado no ambiente de billing;
- quando nao houver instancia operacional disponivel para esse envio, a jornada continua com `access_delivery.status=unavailable`, sem abortar a criacao do evento;
- `chargeback` continua fora da primeira rodada de notificacao ativa, aguardando politica de produto;
- o proximo endurecimento do modulo ficou concentrado na homologacao real de cancelamento/estorno e no dossie da divergencia do simulador de cartao.
