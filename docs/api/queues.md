# Filas e Jobs — Evento Vivo

## Filas Configuradas no Horizon

| Fila | Supervisor | Timeout | Memory | Uso |
|------|-----------|---------|--------|-----|
| `webhooks` | supervisor-webhooks | 30s | 128MB | Processamento de webhooks recebidos |
| `media-download` | supervisor-media-download | 120s | 256MB | Download de mídia de fontes externas |
| `media-process` | supervisor-media-process | 180s | 512MB | Geração de variantes, watermark, moderação |
| `media-publish` | supervisor-media-publish | 60s | 128MB | Publicação e broadcasting |
| `notifications` | supervisor-notifications | 60s | 128MB | E-mails, push e alertas |
| `default` | supervisor-default | 60s | 128MB | Tarefas gerais |
| `analytics` | supervisor-default | 60s | 128MB | Aggregação de métricas |
| `billing` | supervisor-default | 60s | 128MB | Cobranças e webhooks de gateway |

## Pipeline de Mídia (Job Chain)

```
1. ProcessInboundWebhookJob    [webhooks]
   └─ Valida signature, salva log
   └─ 2. NormalizeInboundMessageJob    [webhooks]
      └─ Normaliza payload, cria inbound_message
      └─ 3. DownloadInboundMediaJob    [media-download]
         └─ Baixa arquivo da URL, cria event_media
         └─ 4. GenerateMediaVariantsJob    [media-process]
            └─ Gera thumb, gallery, wall, memory_card, puzzle
            └─ 5. RunModerationJob    [media-process]
               └─ Auto-approve ou marca pending
               └─ 6. PublishMediaJob    [media-publish]
                  └─ publication_status = published
                  └─ 7. BroadcastMediaUpdateJob    [media-publish]
                     └─ Broadcast para channels gallery/wall
```

## Scaling em Produção

| Supervisor | maxProcesses Local | maxProcesses Prod |
|-----------|-------------------|-------------------|
| webhooks | 1 | 2 |
| media-download | 1 | 3 |
| media-process | 1 | 3 |
| media-publish | 1 | 2 |
| notifications | 1 | 2 |
| default | 1 | 2 |
