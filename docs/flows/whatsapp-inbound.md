# Fluxo: WhatsApp — Inbound (Webhook)

## Visão Geral

Recepção de webhooks de providers WhatsApp, normalização e roteamento interno.

## Diagrama

```
  Provider (Z-API)                    WhatsApp Module
       │
       ▼
┌──────────────────┐    ┌──────────────────────┐    ┌───────────────────────┐
│ WebhookController│───▶│ ProcessInboundWebhook│───▶│ WhatsAppInboundRouter │
│                  │    │ Job                  │    │                       │
│ POST /webhooks/  │    │ (fila: whatsapp-     │    │ 1. Find/create chat   │
│ whatsapp/{prov}/ │    │  inbound)            │    │ 2. Deduplicação       │
│ {key}/inbound    │    │                      │    │ 3. Persiste message   │
│                  │    │ 1. Find instance     │    │ 4. Check group binding│
│ Returns 200      │    │ 2. Save raw event    │    │ 5. Dispatch event     │
│ immediately      │    │ 3. Normalize payload │    │                       │
└──────────────────┘    │ 4. Route             │    └───────────┬───────────┘
                        └──────────────────────┘                │
                                                    ┌───────────┼────────────┐
                                                    ▼                        ▼
                                          ┌──────────────┐        ┌────────────────┐
                                          │ Mídia + Evento│        │ Auto-reaction  │
                                          │ vinculado     │        │ SendAutoReaction│
                                          │               │        │ Job            │
                                          │ → InboundMedia│        └────────────────┘
                                          │   Pipeline    │
                                          └──────────────┘
```

## Etapas Detalhadas

### 1. Webhook recebido
- **Controller**: `WhatsAppWebhookController`
- **Ação**: Responde 200 imediato, dispatch `ProcessInboundWebhookJob`
- **Fila**: `whatsapp-inbound` (prioridade alta)

### 2. Processamento assíncrono
- **Job**: `ProcessInboundWebhookJob`
- **Ação**:
  1. Identifica instância por `provider_key` + `instanceKey`
  2. Salva payload bruto em `whatsapp_inbound_events` (status: `pending`)
  3. Normaliza via `ZApiWebhookNormalizer` → `NormalizedInboundMessageData`
  4. Chama `WhatsAppInboundRouter.route()`
  5. Marca evento como `processed`

### 3. Roteamento interno
- **Service**: `WhatsAppInboundRouter`
- **Ação**:
  1. Find ou create `WhatsAppChat`
  2. Deduplicação por `provider_message_id`
  3. Cria `WhatsAppMessage` (direction: inbound, status: received)
  4. Verifica `WhatsAppGroupBinding` ativo
  5. Dispatch event `WhatsAppMessageReceived`

### 4. Listener — Pipeline de mídia
- **Listener**: `RouteInboundToMediaPipeline`
- **Condição**: `hasMedia() && isBoundToEvent() && binding_type == event_gallery`
- **Ação**: Dispatch para pipeline InboundMedia → MediaProcessing → Gallery

### 5. Listener — Auto-reação
- **Job**: `SendAutoReactionJob`
- **Condição**: `binding.metadata.auto_reaction_enabled == true`
- **Ação**: Envia reação configurada (ex: ❤️) para a mensagem original

## Deduplicação

A deduplicação por `provider_message_id` evita processar a mesma mensagem duas vezes,
essencial quando webhooks são reenviados pelo provider.

## Status do Inbound Event

| Status | Descrição |
|--------|-----------|
| pending | Recebido, aguardando processamento |
| processed | Normalizado e roteado com sucesso |
| ignored | Tipo não suportado ou duplicado |
| failed | Erro no processamento |
