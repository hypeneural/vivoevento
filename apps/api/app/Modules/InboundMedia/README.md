# InboundMedia Module

## Responsabilidade
Recepção, normalização e rastreamento de mídia recebida via webhooks.

## Entidades
- **ChannelWebhookLog** — log bruto de cada webhook recebido
- **InboundMessage** — mensagem normalizada pronta para processamento

## Fluxo
1. Webhook chega → `ProcessInboundWebhookJob` (fila: webhooks)
2. Log salvo → `NormalizeInboundMessageJob` (fila: webhooks)
3. Mensagem normalizada → dispatch para MediaProcessing

## Rotas
| Método | Rota | Descrição |
|--------|------|-----------|
| POST | /api/v1/webhooks/telegram | Webhook Telegram |

Observacao:

- o webhook Z-API foi migrado para o modulo `WhatsApp`;
- rota atual: `/api/v1/webhooks/whatsapp/{provider}/{instanceKey}/inbound`.

## Dependências
- Channels (resolver evento pelo canal)
- MediaProcessing (dispatch de download)
