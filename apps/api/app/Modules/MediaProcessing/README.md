# MediaProcessing Module

## Responsabilidade

Download, geracao de variantes, moderacao e publicacao de midia.

## Entidades

- `EventMedia` - midia do evento com status de processamento, moderacao e publicacao
- `EventMediaVariant` - variantes geradas (`thumb`, `gallery`, `wall`, etc.)
- `MediaProcessingRun` - log de cada execucao de processamento

## Fundacao atual do pipeline

Base entregue no modulo:

- upload salva `client_filename`, `original_disk` e `original_path` separadamente;
- `GenerateMediaVariantsJob` gera `fast_preview`, `thumb`, `gallery` e `wall` por `MediaVariantGeneratorService`;
- a etapa de variantes tambem calcula `perceptual_hash` e agrupa possiveis duplicatas via `duplicate_group_key`;
- `RunModerationJob` usa `FinalizeMediaDecisionAction` como ponto unico de decisao do pipeline base;
- `media_processing_runs` guarda `stage_key`, provider, modelo, fila, worker, custo, resultado e metricas;
- o pipeline base ja deixa preparado o terreno para `safety_status`, `face_index_status` e `vlm_status`.
- o modulo agora tambem cobre reprocessamento seletivo por etapa, cleanup propagado de artefatos e metricas operacionais por evento.

## Jobs do Pipeline

| Job | Fila | Responsabilidade |
|-----|------|------------------|
| `DownloadInboundMediaJob` | `media-download` | Baixar o arquivo da origem externa |
| `GenerateMediaVariantsJob` | `media-fast` | Gerar `fast_preview` e variantes canonicas, registrar a run da etapa e emitir `MediaVariantsGenerated` |
| `AnalyzeContentSafetyJob` | `media-safety` | Rodar safety moderation por modulo proprio e alimentar `safety_status` |
| `RunModerationJob` | `media-fast` | Executar a matriz final do pipeline base via `FinalizeMediaDecisionAction` |
| `CleanupDeletedMediaArtifactsJob` | `media-process` | Remover artefatos, projections e referencias residuais quando a midia e excluida |
| `PublishMediaJob` | `media-publish` | Publicar midia aprovada, registrar a run da etapa e emitir `MediaPublished` |

## Eventos de Dominio

| Evento | Quando |
|--------|--------|
| `MediaPublished` | midia aprovada foi publicada |
| `MediaVariantsGenerated` | geracao de variantes foi concluida |
| `MediaRejected` | midia foi rejeitada |
| `MediaDeleted` | midia foi removida |

## Rotas

| Metodo | Rota | Descricao |
|--------|------|-----------|
| `GET` | `/api/v1/media` | Catalogo de midias com filtros de consulta e stats |
| `GET` | `/api/v1/media/feed` | Fila cursorizada da moderacao em tempo real |
| `GET` | `/api/v1/events/{id}/media` | Listar midias do evento |
| `GET` | `/api/v1/media/{id}` | Detalhes da midia |
| `POST` | `/api/v1/media/{id}/approve` | Aprovar midia |
| `POST` | `/api/v1/media/{id}/reject` | Rejeitar midia |
| `PATCH` | `/api/v1/media/{id}/favorite` | Favoritar ou desfavoritar midia |
| `PATCH` | `/api/v1/media/{id}/pin` | Fixar ou desafixar midia |
| `POST` | `/api/v1/media/{id}/reprocess/{stage}` | Reprocessar `safety`, `vlm` ou `face_index` |
| `DELETE` | `/api/v1/media/{id}` | Remover midia |
| `GET` | `/api/v1/events/{id}/media/pipeline-metrics` | Consolidar SLA, backlog e falhas do pipeline |

## Estrutura da borda HTTP

- `EventMediaController`
  - controller fino com checagem de acesso via `EventAccessService`
- `ListModerationMediaQuery`
  - centraliza a busca e os stats da central de moderacao
- `ListEventMediaQuery`
  - centraliza a listagem de midias por evento
- `ApproveEventMediaAction`, `RejectEventMediaAction`
  - encapsulam escrita de moderacao
- `FinalizeMediaDecisionAction`
  - centraliza a decisao automatica do pipeline base
- `UpdateEventMediaFeaturedAction`, `UpdateEventMediaPinnedAction`
  - encapsulam destaque e ordenacao manual
- `DeleteEventMediaAction`
  - encapsula exclusao e emissao de `MediaDeleted`
- `CleanupDeletedMediaArtifactsAction`
  - executa a exclusao propagada de original, variants, crops, projections e referencias residuais
- `ReprocessEventMediaStageAction`
  - reencaminha `safety`, `vlm` ou `face_index` sem refazer o pipeline inteiro
- `MediaVariantGeneratorService`
  - gera `fast_preview`, `thumb`, `gallery` e `wall`, alem do fingerprint perceptual da imagem
- `SyncEventMediaDuplicateGroupAction`
  - agrupa midias visualmente iguais ou muito proximas no mesmo evento
- `MediaProcessingRunService`
  - registra e finaliza runs enriquecidos por etapa com fila, worker e classificacao de falha
- `MediaPipelineMetricsService`
  - agrega summary, backlog, SLA e breakdown de falhas por evento

## Dependencias

- `Events`
- `InboundMedia`
- `ContentModeration`
- `Gallery`
- `Wall`
