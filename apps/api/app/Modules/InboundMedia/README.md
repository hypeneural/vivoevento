# InboundMedia Module

## Responsabilidade
Recepcao, normalizacao e rastreamento da midia recebida por canais que ja chegaram ao envelope canonico de intake.

## Entidades
- **ChannelWebhookLog** - log bruto de cada webhook recebido
- **InboundMessage** - mensagem normalizada pronta para processamento

## Fluxo
1. Webhook ou transporte provider-aware chega -> `ProcessInboundWebhookJob` (fila: webhooks)
2. Log salvo -> `NormalizeInboundMessageJob` (fila: webhooks)
3. Mensagem normalizada -> dispatch para MediaProcessing

## Rotas
| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | /api/v1/public/events/{uploadSlug}/upload | Formulario/link publico de upload |
| POST | /api/v1/public/events/{uploadSlug}/upload | Upload publico direto para o evento |

## Observacoes

- o webhook Z-API foi migrado para o modulo `WhatsApp`;
- o webhook do Telegram foi movido para o modulo `Telegram`;
- rota atual de WhatsApp inbound: `/api/v1/webhooks/whatsapp/{provider}/{instanceKey}/inbound`;
- rota atual de Telegram inbound privado: `/api/v1/webhooks/telegram`.

## Dependencias
- Channels (resolver evento pelo canal)
- MediaProcessing (dispatch de download)
