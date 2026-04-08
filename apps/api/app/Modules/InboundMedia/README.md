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
4. `audio` fica vinculado ao evento pelo `InboundMessage`, com artefato persistido em storage proprio, sem gerar `EventMedia`

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
- `sticker` nao entra em galeria/telao;
- `audio` nao entra em galeria/telao, mas continua rastreado e persistido para futuras features de gravacoes do evento.

## Dependencias
- Channels (resolver evento pelo canal)
- MediaProcessing (dispatch de download)
