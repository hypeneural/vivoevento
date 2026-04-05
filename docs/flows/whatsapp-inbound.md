# Fluxo: WhatsApp - Inbound (Webhook)

## Visao Geral

Recepcao de webhooks de providers WhatsApp, normalizacao e roteamento interno.

## Diagrama

```text
  Provider (Z-API)                    WhatsApp Module
       |
       v
+------------------+    +------------------------+    +------------------------+
| WebhookController|--->| ProcessInboundWebhook  |--->| WhatsAppInboundRouter  |
|                  |    | Job                    |    |                        |
| POST /webhooks/  |    | (fila: whatsapp-       |    | 1. Find/create chat    |
| whatsapp/{prov}/ |    | inbound)               |    | 2. Dedup lookup        |
| {key}/inbound    |    |                        |    | 3. Persist message     |
|                  |    | 1. Find instance       |    | 4. Check group binding |
| Returns 200      |    | 2. Save raw event      |    | 5. Dispatch event      |
| immediately      |    | 3. Normalize payload   |    |                        |
+------------------+    | 4. Route               |    +-----------+------------+
                        +------------------------+                |
                                                                / \
                                                               v   v
                                                +--------------------+   +-------------------+
                                                | Midia + Evento     |   | Auto-reaction     |
                                                | vinculado          |   | SendAutoReaction  |
                                                |                    |   | Job               |
                                                | -> InboundMedia    |   +-------------------+
                                                |    Pipeline        |
                                                +--------------------+
```

## Etapas Detalhadas

### 1. Webhook recebido

- Controller: `WhatsAppWebhookController`
- Acao: responde 200 imediato e despacha `ProcessInboundWebhookJob`
- Fila: `whatsapp-inbound`

### 2. Processamento assincrono

- Job: `ProcessInboundWebhookJob`
- Acao:
  1. identifica a instancia por `provider_key` + `instanceKey`
  2. salva payload bruto em `whatsapp_inbound_events`
  3. normaliza via `ZApiWebhookNormalizer` para `NormalizedInboundMessageData`
  4. chama `WhatsAppInboundRouter::route()`
  5. marca o evento como processado

### 3. Roteamento interno

- Service: `WhatsAppInboundRouter`
- Acao:
  1. find ou create de `WhatsAppChat`
  2. lookup rapido por `instance_id + direction + provider_message_id`
  3. persistencia de `WhatsAppMessage`
  4. refetch seguro quando a unique constraint detectar corrida de insert
  5. verificacao de `WhatsAppGroupBinding`
  6. dispatch de `WhatsAppMessageReceived`

### 4. Listener - Pipeline de midia

- Listener: `RouteInboundToMediaPipeline`
- Condicao: `hasMedia() && isBoundToEvent() && binding_type == event_gallery`
- Acao: despacha para `InboundMedia -> MediaProcessing -> Gallery`

### 5. Listener - Auto-reacao

- Job: `SendAutoReactionJob`
- Condicao: `binding.metadata.auto_reaction_enabled == true`
- Acao: envia a reacao configurada para a mensagem original

## Deduplicacao

A deduplicacao inbound opera em duas camadas:

1. lookup previo por `instance_id + direction + provider_message_id`
2. unique constraint em `whatsapp_messages(instance_id, direction, provider_message_id)`

Isso evita trabalho duplicado silencioso mesmo quando o provider reenviar o
mesmo webhook ou duas execucoes tentarem persistir a mesma mensagem quase ao
mesmo tempo.

## Status do Inbound Event

| Status | Descricao |
|--------|-----------|
| pending | Recebido, aguardando processamento |
| processed | Normalizado e roteado com sucesso |
| ignored | Tipo nao suportado ou duplicado |
| failed | Erro no processamento |
