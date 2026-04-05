# Filas e Jobs - Evento Vivo

## Filas Configuradas no Horizon

| Fila | Supervisor | Timeout | Memory | Uso |
|------|-----------|---------|--------|-----|
| `webhooks` | `supervisor-webhooks` | 30s | 192MB | Processamento de webhooks recebidos |
| `media-download` | `supervisor-media-download` | 120s | 256MB | Download de midia de fontes externas |
| `media-fast` | `supervisor-media-fast` | 120s | 512MB | Fast lane do upload: variantes canonicas, preview rapido e consolidacao inicial |
| `face-index` | `supervisor-face-index` | 170s | 512MB | Heavy lane de `FaceSearch`: indexacao por face, crop privado e embeddings por face |
| `media-process` | `supervisor-media-process` | 170s | 512MB | Etapas nao sensiveis a latencia e retrocompatibilidade do fluxo legado |
| `media-safety` | `supervisor-media-safety` | 90s | 320MB | Moderacao de safety em fila isolada |
| `media-vlm` | `supervisor-media-vlm` | 120s | 512MB | Avaliacao semantica do VLM, caption curta e gating opcional |
| `media-publish` | `supervisor-media-publish` | 45s | 192MB | Publicacao da midia |
| `broadcasts` | `supervisor-broadcasts` | 30s | 192MB | Broadcasts do telao e eventos realtime sensiveis a latencia |
| `notifications` | `supervisor-notifications` | 60s | 128MB | Emails, push e alertas |
| `default` | `supervisor-default` | 60s | 128MB | Tarefas gerais |
| `analytics` | `supervisor-default` | 60s | 128MB | Agregacao de metricas |
| `billing` | `supervisor-default` | 60s | 128MB | Cobrancas e webhooks de gateway |

## Runtime da Conexao Redis

- `REDIS_QUEUE_RETRY_AFTER` agora nasce em `240s` para cobrir as lanes mais
  pesadas da conexao unica de queue na fase 1.
- `REDIS_QUEUE_BLOCK_FOR=5` reduz polling agressivo no Redis sem bloquear o
  worker indefinidamente.
- `REDIS_QUEUE_AFTER_COMMIT=true` evita consumo de jobs antes do commit quando
  o fluxo depende de estado persistido.
- `retry_after` vale para a conexao Redis de queue, nao para cada fila logica.
- separar `REDIS_DB` e `REDIS_CACHE_DB` ajuda organizacao, mas nao isola
  `maxmemory-policy`; eviction continua sendo politica da instancia Redis.

## Resiliencia de Jobs

- `GenerateMediaVariantsJob`, `RunModerationJob`, `PublishMediaJob`,
  `AnalyzeContentSafetyJob`, `EvaluateMediaPromptJob` e `IndexMediaFacesJob`
  agora usam `ShouldBeUnique` para reduzir duplicidade silenciosa no pipeline.
- os jobs criticos tambem usam `WithoutOverlapping` por `event_media_id` para
  evitar corrida de processamento na mesma etapa.
- `AnalyzeContentSafetyJob`, `EvaluateMediaPromptJob` e `IndexMediaFacesJob`
  agora usam `ThrottlesExceptionsWithRedis` para desacelerar avalanche contra
  providers externos quando ha falha repetida.
- `RunModerationJob` e `PublishMediaJob` ficam protegidos contra dispatch
  duplicado quando safety e VLM convergem no mesmo `event_media_id`.

## Reciclagem de Workers

- `fast_termination` do Horizon fica habilitado para encurtar o recycle no
  deploy.
- lanes de imagem e IA agora nascem com `maxJobs` e `maxTime` ativos para
  evitar acumulo progressivo de memoria em workers long-lived.
- `webhooks`, `media-fast`, `media-publish` e `broadcasts` permanecem como
  lanes sagradas e com capacidade reservada por supervisor dedicado.

## Pipeline de Midia

```text
1. ProcessInboundWebhookJob [webhooks]
2. NormalizeInboundMessageJob [webhooks]
3. DownloadInboundMediaJob [media-download]
4. GenerateMediaVariantsJob [media-fast]
5. AnalyzeContentSafetyJob [media-safety]
6. EvaluateMediaPromptJob [media-vlm]
7. RunModerationJob [media-fast]
8. IndexMediaFacesJob [face-index]
9. PublishMediaJob [media-publish]
10. CleanupDeletedMediaArtifactsJob [media-process]
11. Wall listeners / Gallery listeners [broadcasts quando houver broadcast]
```

## Busca por Selfie

Estado atual:

- a busca interna e publica por selfie roda de forma sincronica no request HTTP;
- nao existe supervisor `face-search` ativo no Horizon nesta fase;
- a fila `face-index` continua sendo a unica fila do dominio facial em producao neste momento;
- uma fila dedicada de busca publica so entra se o produto migrar esse fluxo para modo assincrono curto no futuro.

## Eventos de Dominio Esperados

- `MediaPublished`
- `MediaVariantsGenerated`
- `MediaRejected`
- `MediaDeleted`

## Scaling Inicial

| Supervisor | maxProcesses Local | maxProcesses Prod |
|-----------|-------------------|-------------------|
| `supervisor-webhooks` | 1 | 2 |
| `supervisor-media-download` | 1 | 3 |
| `supervisor-media-fast` | 1 | 2 |
| `supervisor-face-index` | 1 | 2 |
| `supervisor-media-process` | 1 | 3 |
| `supervisor-media-safety` | 1 | 2 |
| `supervisor-media-vlm` | 1 | 2 |
| `supervisor-media-publish` | 1 | 2 |
| `supervisor-broadcasts` | 1 | 2 |
| `supervisor-notifications` | 1 | 2 |
| `supervisor-default` | 1 | 2 |

## Observabilidade

- `horizon:snapshot` deve rodar a cada 5 minutos para materializar metricas de fila no Horizon.
- `queue:monitor` deve rodar a cada minuto para `webhooks`, `media-fast`,
  `media-publish` e `broadcasts`, disparando `QueueBusy` quando o backlog
  ultrapassa o threshold configurado.
- `GenerateMediaVariantsJob`, `AnalyzeContentSafetyJob`, `EvaluateMediaPromptJob`, `IndexMediaFacesJob`, `RunModerationJob` e `PublishMediaJob` expoem `tags()` para filtro por etapa do pipeline.
- `CleanupDeletedMediaArtifactsJob` tambem deve expor tags por `event_media_id` para diagnostico de lifecycle e exclusao.
- `media_processing_runs` agora registra `queue_name`, `worker_ref`, `failure_class` e `cost_units` para diagnostico por etapa.
- Falhas de `media-fast`, `face-index`, `media-safety`, `media-publish` e `broadcasts` agora sao logadas com contexto de `event_media_id` e `wall_code`.
- `LongWaitDetected` do Horizon agora e logado pelo app para filas sensiveis do pipeline.
- `QueueBusy` agora e logado pelo app com tamanho da fila e threshold de alerta.
- `request_id` e `trace_id` agora entram no contexto de request e sao
  propagados para jobs via `Context`, facilitando correlacao entre HTTP e fila.
- `MediaPipelineTelemetryService` agora registra `media_pipeline.published` com
  `upload_to_publish_seconds` e `inbound_to_publish_seconds` para diagnostico
  do tempo ate o wall.
- `ProviderCircuitBreaker` protege `media-safety` e `media-vlm` contra cascata de provider em falha repetida.

## Degradacao Operacional

- `OPS_DEGRADE_MEDIA_SAFETY_MODE=review|block` permite reduzir a lane de
  safety para fallback operacional sem derrubar o fluxo ao vivo.
- `OPS_DEGRADE_MEDIA_VLM_ENABLED=false` pausa `media-vlm` e faz o pipeline
  seguir sem depender de enriquecimento semantico.
- `OPS_DEGRADE_FACE_INDEX_ENABLED=false` pausa `face-index` e preserva
  `webhooks`, `media-fast`, `media-publish` e `broadcasts`.
- a politica vale tanto no dispatch quanto nos jobs que ja estavam enfileirados,
  evitando que backlog pesado continue consumindo CPU durante incidente.

## Tuning Inicial por Ambiente

- `HORIZON_WAIT_BROADCASTS`: threshold de espera da fila `broadcasts` antes de sinalizar degradacao.
- `HORIZON_WAIT_FACE_INDEX`: threshold de espera da fila `face-index`.
- `HORIZON_WAIT_MEDIA_FAST`: threshold de espera da fila `media-fast`.
- `HORIZON_WAIT_MEDIA_PROCESS`: threshold de espera da fila `media-process`.
- `HORIZON_WAIT_MEDIA_SAFETY`: threshold de espera da fila `media-safety`.
- `HORIZON_WAIT_MEDIA_VLM`: threshold de espera da fila `media-vlm`.
- `HORIZON_WAIT_MEDIA_PUBLISH`: threshold de espera da fila `media-publish`.
- `HORIZON_BROADCASTS_MAX_PROCESSES`: teto inicial de workers do supervisor de realtime.
- `HORIZON_FACE_INDEX_MAX_PROCESSES`: teto inicial de workers do supervisor de `FaceSearch`.
- `HORIZON_MEDIA_FAST_MAX_PROCESSES`: teto inicial de workers do fast lane.
- `HORIZON_MEDIA_PROCESS_MAX_PROCESSES`: teto inicial de workers de processamento pesado.
- `HORIZON_MEDIA_SAFETY_MAX_PROCESSES`: teto inicial de workers do supervisor de safety.
- `HORIZON_MEDIA_VLM_MAX_PROCESSES`: teto inicial de workers do supervisor do VLM.
- `HORIZON_MEDIA_PUBLISH_MAX_PROCESSES`: teto inicial de workers de publicacao.
- `HORIZON_WEBHOOKS_MAX_PROCESSES`, `HORIZON_NOTIFICATIONS_MAX_PROCESSES` e
  `HORIZON_DEFAULT_MAX_PROCESSES`: tuning basico das lanes restantes.
- `QUEUE_BUSY_WEBHOOKS_MAX`, `QUEUE_BUSY_MEDIA_FAST_MAX`,
  `QUEUE_BUSY_MEDIA_PUBLISH_MAX` e `QUEUE_BUSY_BROADCASTS_MAX`: thresholds
  iniciais para `queue:monitor`.
- workers de imagem e IA agora nascem com `maxJobs` e `maxTime` ativos para
  reciclagem de memoria em producao.
