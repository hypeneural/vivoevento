# Fluxo: Ingestão de Mídia

## Visão Geral

Este fluxo cobre o caminho completo de uma mídia desde a entrada via webhook até a publicação na galeria/wall.

## Diagrama

```
 Fonte Externa                     Evento Vivo
 (WhatsApp, Web, etc.)
       │
       ▼
┌──────────────┐     ┌───────────────────┐     ┌────────────────────┐
│   Webhook    │────▶│  InboundMedia     │────▶│  MediaProcessing   │
│   Recebido   │     │                   │     │                    │
│              │     │  1. Log webhook   │     │  4. Download mídia │
│              │     │  2. Normalizar    │     │  5. Gerar variantes│
│              │     │  3. Rotear        │     │  6. Moderar        │
└──────────────┘     └───────────────────┘     └────────┬───────────┘
                                                         │
                              ┌──────────────────────────┤
                              ▼                          ▼
                       ┌──────────┐              ┌──────────┐
                       │ Gallery  │              │   Wall   │
                       │          │              │          │
                       │ 7. Add   │              │ 8. Push  │
                       │ to feed  │              │ realtime │
                       └──────────┘              └──────────┘
```

## Etapas Detalhadas

### 1. Receber Webhook
- **Job**: `ProcessInboundWebhookJob` (fila: `webhooks`)
- **Ação**: Valida assinatura, salva payload cru em `channel_webhook_logs`
- **Módulo**: InboundMedia

### 2. Normalizar Mensagem
- **Job**: `NormalizeInboundMessageJob` (fila: `webhooks`)
- **Ação**: Extrai tipo, remetente, URL da mídia → salva em `inbound_messages`
- **Módulo**: InboundMedia

### 3. Rotear para Evento
- **Service**: `InboundMessageRouterService`
- **Ação**: Identifica o evento correto pelo canal → vincula inbound_message ao event_id
- **Módulo**: InboundMedia

### 4. Download da Mídia
- **Job**: `DownloadInboundMediaJob` (fila: `media-download`)
- **Service**: `MediaDownloadService`
- **Ação**: Baixa arquivo da URL, salva no storage, cria registro em `event_media`
- **Módulo**: MediaProcessing

### 5. Gerar Variantes
- **Job**: `GenerateMediaVariantsJob` (fila: `media-process`)
- **Service**: `MediaVariantGeneratorService`
- **Ação**: Gera thumb, gallery, wall, memory_card, puzzle → salva em `event_media_variants`
- **Módulo**: MediaProcessing

### 6. Moderação
- **Job**: `RunModerationJob` (fila: `media-process`)
- **Ação**: Aplica regras de moderação (auto-approve ou pending)
- **Módulo**: MediaProcessing

### 7. Publicar na Galeria
- **Job**: `PublishMediaJob` (fila: `media-publish`)
- **Action**: `AddMediaToGalleryAction`
- **Ação**: Muda publication_status para published, registra em analytics
- **Módulo**: Gallery

### 8. Push Realtime para Wall
- **Job**: `BroadcastMediaUpdateJob` (fila: `media-publish`)
- **Event**: `WallMediaPublished`
- **Canal**: `event.{id}.wall`
- **Ação**: Envia nova mídia para o slideshow em tempo real
- **Módulo**: Wall

## Status Tracking

| Campo | Valores |
|-------|---------|
| `processing_status` | received → downloaded → processed → failed |
| `moderation_status` | pending → approved / rejected |
| `publication_status` | draft → published → hidden → deleted |

## Filas Envolvidas

1. `webhooks` — Steps 1-2
2. `media-download` — Step 4
3. `media-process` — Steps 5-6
4. `media-publish` — Steps 7-8
