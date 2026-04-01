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
| POST | /api/v1/webhooks/zapi | Webhook Z-API |
| POST | /api/v1/webhooks/telegram | Webhook Telegram |

## Dependências
- Channels (resolver evento pelo canal)
- MediaProcessing (dispatch de download)
