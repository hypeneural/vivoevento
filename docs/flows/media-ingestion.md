# Fluxo: Ingestao de Midia

## Visao Geral

Este fluxo cobre o caminho completo de uma midia desde a entrada via webhook ate a publicacao na galeria e no telao.

## Diagrama

```text
Fonte Externa
(WhatsApp, Web, etc.)
      |
      v
Webhook -> InboundMedia -> MediaProcessing -> Gallery
                                      |
                                      v
                                     Wall
```

## Etapas Detalhadas

### 1. Receber webhook
- Job: `ProcessInboundWebhookJob` na fila `webhooks`
- Acao: valida assinatura e salva payload cru
- Modulo: `InboundMedia`

### 2. Normalizar mensagem
- Job: `NormalizeInboundMessageJob` na fila `webhooks`
- Acao: extrai tipo, remetente e URL da midia
- Modulo: `InboundMedia`

### 3. Rotear para evento
- Service: `InboundMessageRouterService`
- Acao: identifica o evento correto pelo canal e vincula o `event_id`
- Regras minimas:
  - grupo: binding ativo por `group_external_id` ou autovinculo por comando
    `#ATIVAR#<group_bind_code>`
  - privado: sessao ativa por `instance_id + sender_external_id`, aberta por
    `media_inbox_code`
  - blacklist do evento e avaliada antes de criar `event_media`
- Modulo: `InboundMedia`

### 4. Download da midia
- Job: `DownloadInboundMediaJob` na fila `media-download`
- Service: `MediaDownloadService`
- Acao: baixa o arquivo e cria `event_media`
- Modulo: `MediaProcessing`

### 5. Gerar variantes
- Job: `GenerateMediaVariantsJob` na fila `media-fast`
- Service: `MediaVariantGeneratorService`
- Acao: gera `fast_preview`, `thumb`, `gallery` e `wall`, atualiza dimensoes, calcula `perceptual_hash`, agrupa duplicatas leves por `duplicate_group_key` e registra a run da etapa
- Evento de dominio esperado: `MediaVariantsGenerated`
- Modulo: `MediaProcessing`

### 6. Safety moderation
- Job: `AnalyzeContentSafetyJob` na fila `media-safety`
- Action: `EvaluateContentSafetyAction`
- Acao: roda a etapa de safety moderation, persiste avaliacoes e atualiza `safety_status`
- Modulo: `ContentModeration`

### 7. VLM rapido
- Job: `EvaluateMediaPromptJob` na fila `media-vlm`
- Action: `EvaluateMediaPromptAction`
- Acao: consome `fast_preview`, persiste `event_media_vlm_evaluations`, atualiza `vlm_status` e gera caption curta quando aplicavel
- Regra: em `mode=gate`, reencaminha para decisao final; em `mode=enrich_only`, roda sem bloquear publish
- Modulo: `MediaIntelligence`

### 8. Decisao final
- Job: `RunModerationJob` na fila `media-fast`
- Action: `FinalizeMediaDecisionAction`
- Acao: aplica a matriz final do pipeline base e decide `approved`, `pending` ou `rejected`
- Evento de dominio esperado: `MediaRejected` quando a midia sair do wall
- Modulo: `MediaProcessing`
- Feedback esperado:
  - `approved + published` -> reacao positiva na mensagem original
  - `rejected` -> reacao negativa + reply `Sua midia nao segue as diretrizes do evento. 🛡️`

### 9. Indexacao facial non-blocking
- Job: `IndexMediaFacesJob` na fila `face-index`
- Action: `IndexMediaFacesAction`
- Acao: carrega `gallery` ou original, detecta faces, aplica quality gate, salva crop privado em `ai-private`, persiste `event_media_faces` e atualiza `face_index_status`
- Regra: se `FaceSearch` estiver desligado, a etapa termina em `skipped`; se falhar, a midia continua no fluxo e a busca facial fica indisponivel para aquela foto
- Modulo: `FaceSearch`

### 10. Publicar na galeria
- Job: `PublishMediaJob` na fila `media-publish`
- Acao: muda `publication_status` para `published` e registra analytics
- Evento de dominio esperado: `MediaPublished`
- Modulo: `Gallery` / `MediaProcessing`

### 11. Traduzir pipeline para broadcast do telao
- Fila: `broadcasts`
- Eventos de dominio consumidos: `MediaPublished`, `MediaVariantsGenerated`, `MediaRejected`, `MediaDeleted`
- Eventos de broadcast emitidos: `WallMediaPublished`, `WallMediaUpdated`, `WallMediaDeleted`
- Canal publico do telao: `wall.{wallCode}`
- Modulo: `Wall`

## Status Tracking

| Campo | Valores |
|-------|---------|
| `processing_status` | `received -> downloaded -> processed -> failed` |
| `moderation_status` | `pending -> approved / rejected` |
| `publication_status` | `draft -> published -> hidden -> deleted` |
| `safety_status` | `queued / skipped / pass / review / block / failed` |
| `face_index_status` | `queued / processing / indexed / skipped / failed` |
| `vlm_status` | `queued / completed / review / rejected / skipped / failed` |

## Filas Envolvidas

1. `webhooks` - etapas 1 e 2
2. `media-download` - etapa 4
3. `media-fast` - etapas 5 e 8
4. `media-safety` - etapa 6
5. `media-vlm` - etapa 7
6. `face-index` - etapa 9
7. `media-publish` - etapa 10
8. `broadcasts` - etapa 11

## Busca por selfie

Esse fluxo nao faz parte do trilho de ingestao e publish da midia.

Estado atual:

- busca interna: `POST /api/v1/events/{event}/face-search/search`
- bootstrap publico: `GET /api/v1/public/events/{slug}/face-search`
- busca publica: `POST /api/v1/public/events/{slug}/face-search/search`
- experiencia publica: `/e/:slug/find-me`

Leitura operacional:

- usa `event_media_faces` ja indexadas no heavy lane;
- roda de forma sincronica no request HTTP, sem fila dedicada nesta fase;
- registra `event_face_search_requests` para auditoria, consentimento e retention;
- a busca publica retorna apenas midias `approved + published`.

## Reprocessamento seletivo

Estado atual:

- `POST /api/v1/media/{id}/reprocess/safety`
- `POST /api/v1/media/{id}/reprocess/vlm`
- `POST /api/v1/media/{id}/reprocess/face_index`

Leitura operacional:

- cada endpoint reencaminha apenas a etapa pedida, sem refazer o pipeline inteiro;
- `safety` e `vlm` criam nova avaliacao e preservam historico;
- `face_index` limpa projection antiga e reindexa faces da midia;
- toda solicitacao gera auditoria `media.reprocess_requested`.

## Exclusao propagada

Estado atual:

- `DELETE /api/v1/media/{id}` faz exclusao logica da midia e dispara cleanup assicrono;
- `CleanupDeletedMediaArtifactsJob` remove original, variants, crops faciais, projection vetorial e referencias residuais em requests de busca;
- a midia deixa de ser buscavel imediatamente via `searchable=false` antes do cleanup pesado.

## Observabilidade operacional

Estado atual:

- `GET /api/v1/events/{event}/media/pipeline-metrics` consolida summary, SLA, backlog por fila e breakdown de falhas;
- `media_processing_runs` guarda `queue_name`, `worker_ref`, `failure_class` e `cost_units`;
- o app loga `LongWaitDetected` do Horizon para filas sensiveis do pipeline.
