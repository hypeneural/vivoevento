# Fluxo: WhatsApp — Envio de Mensagens

## Visão Geral

Fluxo assíncrono de envio de mensagens via WhatsApp, provider-agnostic.

## Diagrama

```
  Frontend / API                          WhatsApp Module
       │
       ▼
┌─────────────────┐     ┌─────────────────────────┐     ┌─────────────────────┐
│ MessageController│────▶│ WhatsAppMessagingService │────▶│ SendWhatsAppMessage │
│                 │     │                         │     │ Job                 │
│ POST /messages/ │     │ 1. Valida instância     │     │ (fila: whatsapp-    │
│ text|image|...  │     │ 2. Normaliza destino    │     │  send)              │
│                 │     │ 3. Persiste message     │     │                     │
│ Returns 202     │     │ 4. Dispatch job         │     │ 4. Resolve provider │
└─────────────────┘     └─────────────────────────┘     │ 5. Chama adapter    │
                                                         │ 6. Atualiza status  │
                                                         │ 7. Cria dispatch log│
                                                         └──────────┬──────────┘
                                                                    │
                                                                    ▼
                                                         ┌──────────────────┐
                                                         │ ProviderAdapter  │
                                                         │ (ZApiWhatsApp    │
                                                         │  Provider)       │
                                                         │                  │
                                                         │ POST /send-text  │
                                                         │ POST /send-image │
                                                         │ ...              │
                                                         └──────────────────┘
```

## Etapas Detalhadas

### 1. Request recebido
- **Controller**: `WhatsAppMessageController`
- **Request**: `SendTextRequest`, `SendImageRequest`, etc.
- **Ação**: Valida input, resolve instância

### 2. Orquestração
- **Service**: `WhatsAppMessagingService`
- **Ação**: 
  1. Verifica `instance.status == connected`
  2. Normaliza phone/group via `WhatsAppTargetNormalizer`
  3. Cria `WhatsAppMessage` com status `queued`
  4. Dispatch `SendWhatsAppMessageJob` na fila `whatsapp-send`
- **Retorno**: 202 com message_id (async)

### 3. Envio assíncrono
- **Job**: `SendWhatsAppMessageJob` (fila: `whatsapp-send`)
- **Ação**:
  1. Marca message como `sending`
  2. `WhatsAppProviderResolver` resolve adapter pela instance
  3. Chama método do adapter (`sendText`, `sendImage`, etc.)
  4. Atualiza message: `provider_message_id`, `provider_zaap_id`, status `sent`
  5. Cria `WhatsAppDispatchLog` com request/response/duration
  6. Dispatch event `WhatsAppMessageSent`
- **Em caso de falha**: marca `failed`, cria log, retry até 3x

## Status da Mensagem

| Status | Descrição |
|--------|-----------|
| queued | Criada, aguardando job |
| sending | Job em execução |
| sent | Enviada com sucesso |
| delivered | Entregue (via webhook delivery) |
| read | Lida (via webhook delivery) |
| failed | Falha após retries |
