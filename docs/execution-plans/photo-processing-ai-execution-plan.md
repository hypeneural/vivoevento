# Photo Processing AI Execution Plan

## Objetivo

Este documento transforma a arquitetura de processamento inteligente de fotos em ordem real de implementacao.

Referencia primaria:

- `docs/architecture/photo-processing-ai-architecture.md`

Este plano existe para responder 5 perguntas de execucao:

1. o que ja existe no repositorio e pode ser reaproveitado;
2. o que ainda precisa ser fechado no dominio antes de plugar novas camadas;
3. em que ordem as fases devem entrar para nao quebrar o fluxo ao vivo;
4. quais testes automatizados precisam existir em cada entrega;
5. qual e a primeira execucao recomendada depois da criacao deste backlog.

## Como usar este plano

Regra simples:

- a arquitetura continua sendo a fonte de direcao, modelo de dominio e desenho geral;
- este arquivo vira a fonte de backlog, sequenciamento, dependencias e validacao automatizada;
- cada task abaixo precisa sair do papel com TTD explicito antes de ser considerada pronta.

Cada task aponta para:

- objetivo;
- referencia na arquitetura;
- estado atual do codigo;
- subtasks detalhadas;
- TTD obrigatorio;
- dependencias;
- criterio de aceite;
- arquivos e modulos mais provaveis de impacto.

## Status Das Primeiras Execucoes

Em 2026-04-01, a primeira execucao deste plano fechou a base de `M0-T1` no codigo:

- `moderation_mode` consolidado em `none/manual/ai`;
- compatibilidade de leitura para legado `auto -> none`;
- payloads de evento e upload publico alinhados;
- matriz de decisao do pipeline ajustada para os 3 modos;
- frontend tipado e validado para os 3 modos.

TTD executado nesta rodada:

- `cd apps/api && php artisan test --filter=CreateEventTest`
- `cd apps/api && php artisan test --filter=MediaPipelineJobsTest`
- `cd apps/api && php artisan test --filter=ContentModerationPipelineTest`
- `cd apps/web && npm run type-check`

Resultado:

- todos os testes acima passaram;
- proxima execucao recomendada: `M0-T2` e depois `M0-T3`.

Na sequencia, a segunda execucao fechou `M0-T3` no codigo:

- modulo minimo de `FaceSearch` criado no backend;
- `event_face_search_settings` criado com contrato inicial por evento;
- editor de evento passou a persistir:
  - `enabled`
  - `allow_public_selfie_search`
  - `selfie_retention_hours`
- `Event` ganhou relacao e helpers semanticos de `FaceSearch`;
- `EventDetailResource` e payloads de evento passaram a expor a configuracao;
- `PublicUploadController` e `FinalizeMediaDecisionAction` passaram a respeitar o toggle:
  - desligado -> `face_index_status=skipped`
  - ligado -> `face_index_status=queued`

TTD executado nesta rodada:

- `cd apps/api && php artisan test --filter=CreateEventTest`
- `cd apps/api && php artisan test --filter=PublicUploadTest`
- `cd apps/api && php artisan test --filter=MediaPipelineJobsTest`
- `cd apps/api && php artisan test --filter=FinalizeMediaDecisionActionTest`
- `cd apps/api && php artisan test --filter=ModelsTest`
- `cd apps/web && npm run type-check`

Resultado:

- todos os testes acima passaram;
- proxima execucao recomendada: fechar `M1`, preparando o fast lane real antes do provider de safety.

Na sequencia, a terceira execucao fechou `M1-T1`, `M1-T2` e `M1-T3` no codigo:

- `media-fast` entrou no `Horizon` com wait threshold, supervisor dedicado e envs de escala;
- `GenerateMediaVariantsJob` e `RunModerationJob` passaram a operar no fast lane;
- `fast_preview` entrou como variante canonica do pipeline;
- `AnalyzeContentSafetyJob` passou a referenciar preferencialmente `fast_preview` quando disponivel;
- `media_processing_runs` foi expandida com:
  - `provider_version`
  - `model_snapshot`
  - `queue_name`
  - `worker_ref`
  - `cost_units`
  - `failure_class`
- o detalhe da midia passou a expor runs enriquecidos com contexto operacional.

TTD executado nesta rodada:

- `cd apps/api && php artisan test tests/Unit/MediaProcessing tests/Feature/MediaProcessing/MediaPipelineJobsTest.php tests/Feature/MediaProcessing/EventMediaListTest.php tests/Feature/InboundMedia/PublicUploadTest.php tests/Feature/ContentModeration/ContentModerationPipelineTest.php`
- `cd apps/web && npm run type-check`

Resultado:

- todos os testes acima passaram;
- proxima execucao recomendada: `M2-T1` e `M2-T2`, com provider real de safety e configuracao administrativa por evento.

Na sequencia, a quarta execucao fechou `M2-T1` e `M2-T2` no codigo:

- `ContentModerationProviderManager` passou a selecionar provider por `provider_key`;
- `OpenAiContentModerationProvider` foi conectado a `omni-moderation-latest`, com `input` multimodal, thresholds por categoria e fallback configuravel;
- `EvaluateContentSafetyAction` passou a pular provider externo quando o evento nao esta em `moderation_mode=ai`;
- `AnalyzeContentSafetyJob` agora grava `provider_version`, `model_snapshot` e fallback operacional na etapa `safety`;
- endpoint dedicado por evento entrou em `/events/{event}/content-moderation/settings`;
- o detalhe do evento passou a expor e editar settings de safety na aba de moderacao com formulario tipado;
- o setup global do Vitest foi ajustado para suportar componentes `Radix` com `ResizeObserver`.

TTD executado nesta rodada:

- `cd apps/api && php artisan test tests/Unit/ContentModeration tests/Feature/ContentModeration/ContentModerationPipelineTest.php tests/Feature/ContentModeration/ContentModerationSettingsTest.php tests/Feature/Events/CreateEventTest.php tests/Feature/Events/EventDetailAndLinksTest.php`
- `cd apps/web && npm run test -- EventContentModerationSettingsForm.test.tsx`
- `cd apps/web && npm run type-check`

Resultado:

- todos os testes acima passaram;
- proxima execucao recomendada: `M3-T1` e `M3-T2`, com `MediaIntelligence` real via `vLLM` e contrato de settings por evento.

Na sequencia, a quinta execucao fechou `M3-T1`, `M3-T2` e `M3-T3` no codigo:

- o modulo `MediaIntelligence` entrou no backend com `ServiceProvider`, rotas e `README`;
- `event_media_intelligence_settings` e `event_media_vlm_evaluations` entraram no banco com factories, casts e indices;
- `VisualReasoningProviderInterface` passou a isolar o dominio de provider-specific code;
- `VllmVisualReasoningProvider` foi conectado em modo OpenAI-compatible com `response_format=json_schema`;
- `NullVisualReasoningProvider` continua disponivel para local/testes;
- endpoint dedicado por evento entrou em `/events/{event}/media-intelligence/settings`;
- o detalhe do evento passou a expor e editar settings de `MediaIntelligence` na aba de moderacao;
- `docs/modules/module-map.md` passou a registrar `MediaIntelligence` como modulo de processamento.

TTD executado nesta rodada:

- `cd apps/api && php artisan test tests/Unit/MediaIntelligence tests/Feature/MediaIntelligence/MediaIntelligenceSettingsTest.php tests/Feature/Events/EventDetailAndLinksTest.php`
- `cd apps/web && npm run test -- EventMediaIntelligenceSettingsForm.test.tsx`
- `cd apps/web && npm run type-check`

Resultado:

- todos os testes acima passaram;
- proxima execucao recomendada: `M3-T4`, ligando `EvaluateMediaPromptJob` ao pipeline e persistindo `vlm_status` real por foto.

Na sequencia, a sexta execucao fechou `M3-T4` e `M3-T5` no codigo:

- `EvaluateMediaPromptAction` e `EvaluateMediaPromptJob` passaram a rodar no pipeline real com `fast_preview`, `media-vlm` e `RunModerationJob` encadeado quando `mode=gate`;
- `event_media_vlm_evaluations` passou a ser persistida e `event_media.vlm_status` passou a refletir a etapa real por foto;
- o detalhe da midia agora expoe a ultima avaliacao estruturada de safety e VLM;
- a central de moderacao passou a consumir essa leitura estruturada no painel lateral, com reason, caption curta e tags;
- docs operacionais de filas e fluxo de ingestao foram sincronizadas com a etapa `media-vlm`.

TTD executado nesta rodada:

- `cd apps/api && php artisan test tests/Feature/MediaIntelligence/MediaIntelligencePipelineTest.php tests/Feature/MediaProcessing/EventMediaListTest.php tests/Feature/MediaProcessing/MediaPipelineJobsTest.php tests/Unit/MediaProcessing/FinalizeMediaDecisionActionTest.php`
- `cd apps/web && npm run test -- ModerationReviewPanel.test.tsx`
- `cd apps/web && npm run type-check`

Resultado:

- todos os testes acima passaram;
- proxima execucao recomendada: `M4-T1`, iniciando `FaceSearch` real com schema de faces indexadas.

Na sequencia, a setima execucao fechou `M4-T1`, `M4-T2`, `M4-T3`, `M4-T4` e `M4-T5` no codigo:

- `FaceSearch` deixou de ser foundation minima e passou a ter `ServiceProvider`, rotas, `README`, settings dedicados e contratos de dominio;
- `event_face_search_settings` foi expandida com provider, modelo, thresholds e `top_k`;
- `event_media_faces` e `event_face_search_requests` entraram no banco com factories, scopes e fallback compativel com SQLite nos testes;
- `FaceDetectionProviderInterface`, `FaceEmbeddingProviderInterface` e `FaceVectorStoreInterface` agora isolam detection, embedding e armazenamento vetorial;
- `PgvectorFaceVectorStore` passou a ser a implementacao inicial do dominio, com busca por `event_id` e fallback em memoria para o ambiente de teste;
- `IndexMediaFacesJob` entrou na fila `face-index`, com quality gate, crops privados em `ai-private`, embeddings por face e persistencia non-blocking;
- `RunModerationJob` agora dispara a indexacao facial quando `face_index_status=queued`, sem bloquear publish;
- `ApproveEventMediaAction` e `RejectEventMediaAction` agora religam ou desligam `searchable` nas faces indexadas;
- endpoint dedicado por evento entrou em `/events/{event}/face-search/settings`;
- o detalhe do evento passou a expor e editar settings de `FaceSearch` na aba de moderacao;
- o detalhe da midia passou a expor `indexed_faces_count`, e a central de moderacao passou a refletir `face_index_status`.

TTD executado nesta rodada:

- `cd apps/api && php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch tests/Feature/MediaProcessing/MediaPipelineJobsTest.php tests/Feature/Events/CreateEventTest.php tests/Feature/Events/EventDetailAndLinksTest.php`
- `cd apps/api && php artisan test tests/Feature/MediaProcessing/EventMediaListTest.php tests/Feature/MediaProcessing/ModerationMediaTest.php`
- `cd apps/web && npm run test -- EventFaceSearchSettingsForm.test.tsx ModerationReviewPanel.test.tsx`
- `cd apps/web && npm run type-check`

Resultado:

- todos os testes acima passaram;
- proxima execucao recomendada: `M5-T1`, abrindo a busca por selfie com consentimento, retention e escopo por evento.

Na sequencia, a oitava execucao fechou `M5-T1`, `M5-T2` e `M5-T3` no codigo:

- endpoint interno de busca por selfie entrou em `/events/{event}/face-search/search`, com selfie temporaria, validacao de face unica, embedding da selfie, busca vetorial por `event_id` e reranking por `event_media_id`;
- `CollapseFaceSearchMatchesQuery` passou a colapsar matches por foto, preservando melhor distancia, qualidade e area facial;
- `event_face_search_requests` agora registra auditoria das buscas internas e publicas com `consent_version`, `selfie_storage_strategy`, `query_face_quality_score`, `best_distance`, `result_photo_ids_json` e `expires_at`;
- bootstrap publico entrou em `/public/events/{slug}/face-search`, com availability, messaging, consent version e retention;
- endpoint publico de busca entrou em `/public/events/{slug}/face-search/search`, com consentimento explicito, `throttle:public-face-search`, filtro estrito para `approved + published` e bloqueio funcional quando `allow_public_selfie_search=false`;
- `EventPublicLinksService` e `EventQrController` agora expõem `find_me` como link publico quando a busca publica estiver habilitada;
- o frontend ganhou rota publica `/e/:slug/find-me` e card interno de busca por selfie na aba de moderacao do detalhe do evento;
- o componente compartilhado `FaceSearchSearchPanel` cobre envio, consentimento, estados de busca, sem resultado e resultado encontrado.

TTD executado nesta rodada:

- `cd apps/api && php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch tests/Feature/Events/EventDetailAndLinksTest.php`
- `cd apps/web && npm run test -- FaceSearchSearchPanel.test.tsx`
- `cd apps/web && npm run type-check`

Resultado:

- todos os testes acima passaram;
- proxima execucao recomendada: iniciar `M6`, com reprocessamento seletivo, exclusao propagada e observabilidade operacional mais profunda.

Na sequencia, a nona execucao fechou `M6-T1`, `M6-T2`, `M6-T3` e `M6-T4` no codigo:

- `ReprocessEventMediaStageAction` entrou no backend com endpoint por etapa em `/media/{id}/reprocess/{stage}` para `safety`, `vlm` e `face_index`;
- o reprocessamento agora cria nova avaliacao para `ContentModeration` e `MediaIntelligence`, preservando historico e registrando auditoria `media.reprocess_requested`;
- `IndexMediaFacesAction` agora limpa projection antiga antes de reindexar faces, removendo crop legado e regravando `event_media_faces`;
- `PipelineFailureClassifier` passou a classificar falhas entre `transient`, `permanent` e `policy`, e os jobs do pipeline passaram a registrar essa classificacao;
- `ProviderCircuitBreaker` entrou na camada de providers de `ContentModeration` e `MediaIntelligence`, com configuracao por provider e fallback seguro para itens saudaveis;
- `DeleteEventMediaAction` agora faz exclusao logica segura, desliga `searchable` nas faces e despacha cleanup assincrono por `MediaDeleted`;
- `CleanupDeletedMediaArtifactsJob` e `CleanupDeletedMediaArtifactsAction` agora removem original, variantes, crops faciais, projection vetorial, avaliacoes, resultados de busca e referencias residuais;
- `MediaPipelineMetricsService` e `/events/{event}/media/pipeline-metrics` agora expoem summary, SLA, backlog por fila e breakdown de falhas;
- `AppServiceProvider` passou a logar `LongWaitDetected` do Horizon para backlog operacional sensivel;
- `RolesAndPermissionsSeeder` agora inclui `media.reprocess` nas roles operacionais do produto.

TTD executado nesta rodada:

- `cd apps/api && php artisan test tests/Feature/MediaProcessing/MediaReprocessTest.php tests/Feature/MediaProcessing/MediaDeletionPropagationTest.php tests/Feature/MediaProcessing/MediaPipelineMetricsTest.php tests/Feature/ContentModeration/ContentModerationCircuitBreakerTest.php tests/Unit/MediaProcessing/PipelineFailureClassifierTest.php`
- `cd apps/api && php artisan test tests/Feature/InboundMedia tests/Feature/ContentModeration tests/Feature/MediaIntelligence tests/Feature/FaceSearch tests/Feature/MediaProcessing tests/Feature/Events/EventDetailAndLinksTest.php tests/Feature/Events/CreateEventTest.php tests/Unit/MediaProcessing tests/Unit/FaceSearch tests/Unit/MediaIntelligence`
- `cd apps/web && npm run type-check`

Resultado:

- todos os testes acima passaram;
- o fluxo completo de upload, safety, VLM, face index, selfie search, publish, reprocessamento e exclusao propagada ficou validado em automacao;
- proxima execucao recomendada: sair do backlog funcional e entrar em tuning operacional de carga, superficie administrativa de reprocessamento e trilha de escala vetorial.

## Referencias Da Arquitetura

Secoes que devem ser lidas junto com este plano:

- `Resumo executivo`
- `Decisoes que precisam ficar fechadas para arquitetura definitiva`
- `Fluxo recomendado de ponta a ponta`
- `Filas recomendadas`
- `Modelagem de banco recomendada`
- `Contratos e integracao entre servicos`
- `Fallback behavior`
- `Model governance`
- `Deletion propagation`

## Estado Atual Reaproveitavel

Antes de executar as proximas fases, estes pontos devem ser tratados como base pronta:

- `MediaProcessing` ja separa `original_disk`, `original_path` e `client_filename`;
- `GenerateMediaVariantsJob` ja gera variantes reais e calcula `perceptual_hash`;
- `GenerateMediaVariantsJob` ja gera `fast_preview` no `media-fast`;
- `RunModerationJob` ja centraliza a decisao automatica via `FinalizeMediaDecisionAction`;
- `ApproveEventMediaAction` e `RejectEventMediaAction` ja permitem override humano;
- `ContentModeration` ja existe com settings, historico, `AnalyzeContentSafetyJob` e provider real via adapter;
- `MediaIntelligence` ja existe com settings, historico, `VisualReasoningProviderInterface`, `EvaluateMediaPromptJob` e adapter real via `vLLM`;
- `FaceSearch` ja existe com settings por evento, `event_media_faces`, `event_face_search_requests`, interfaces faciais e `PgvectorFaceVectorStore`;
- `IndexMediaFacesJob` ja roda na fila `face-index`, com quality gate, crops privados e embeddings por face;
- `ReprocessEventMediaStageAction` ja permite reprocessamento seletivo por `safety`, `vlm` e `face_index` sem refazer o pipeline inteiro;
- `CleanupDeletedMediaArtifactsJob` ja cobre exclusao propagada de original, variants, crops, projection vetorial e telemetria de busca;
- `ProviderCircuitBreaker` e `PipelineFailureClassifier` ja endurecem falhas de provider com fallback e classificacao por severidade;
- `MediaPipelineMetricsService` ja consolida backlog, funil de status e SLA operacional por evento;
- `pgvector` ja esta provisionado no init local do Postgres;
- `Reverb` e a central de moderacao ja permitem feedback progressivo para a UI, incluindo a ultima leitura estruturada de safety e VLM;
- `media_processing_runs` ja guarda `stage_key`, provider, modelo, fila, worker, custo, resultado e metricas basicas.

Arquivos-base que precisam ficar no radar durante toda a execucao:

- `apps/api/app/Modules/InboundMedia/Http/Controllers/PublicUploadController.php`
- `apps/api/app/Modules/MediaProcessing/Actions/FinalizeMediaDecisionAction.php`
- `apps/api/app/Modules/MediaProcessing/Actions/ApproveEventMediaAction.php`
- `apps/api/app/Modules/MediaProcessing/Actions/RejectEventMediaAction.php`
- `apps/api/app/Modules/MediaProcessing/Jobs/GenerateMediaVariantsJob.php`
- `apps/api/app/Modules/MediaProcessing/Jobs/RunModerationJob.php`
- `apps/api/app/Modules/MediaProcessing/Jobs/PublishMediaJob.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaVariantGeneratorService.php`
- `apps/api/app/Modules/ContentModeration/Jobs/AnalyzeContentSafetyJob.php`
- `apps/api/app/Modules/ContentModeration/Actions/EvaluateContentSafetyAction.php`
- `apps/api/app/Modules/MediaIntelligence/Services/VllmVisualReasoningProvider.php`
- `apps/api/app/Modules/MediaIntelligence/Http/Controllers/EventMediaIntelligenceSettingsController.php`
- `apps/api/app/Modules/Events/Models/Event.php`
- `apps/api/app/Modules/Events/Http/Requests/StoreEventRequest.php`
- `apps/api/app/Modules/Events/Http/Requests/UpdateEventRequest.php`
- `apps/api/config/horizon.php`
- `apps/web/src/modules/moderation/ModerationPage.tsx`
- `apps/web/src/modules/upload/PublicEventUploadPage.tsx`

## Regras De Produto Que Precisam Ser Mantidas

Estas regras sao contratuais para qualquer implementacao abaixo:

1. o evento tera 3 modos de moderacao:
   - `none`
   - `manual`
   - `ai`
2. override humano precisa funcionar em todos os modos;
3. `FaceSearch` e opcional por evento e nao pode ser acoplado ao `moderation_mode`;
4. `FaceSearch` nunca bloqueia publish;
5. `safety` e o unico gate obrigatorio em `ai`, com VLM podendo virar gate so quando configurado;
6. falha tecnica nunca pode resultar em aprovacao automatica.

## Regra De Sequenciamento

Executar na ordem abaixo:

1. travar o dominio de moderacao e override;
2. separar fast lane de heavy lane na infraestrutura atual;
3. conectar `ContentModeration` real ao fluxo de produto;
4. introduzir `MediaIntelligence` com VLM rapido e JSON estruturado;
5. introduzir `FaceSearch` como enriquecimento opcional;
6. abrir busca por selfie e reprocessamentos;
7. endurecer operacao, observabilidade e exclusao propagada.

Motivo:

- se o time tentar criar `FaceSearch` antes do contrato de moderacao, vai indexar dado sem lifecycle claro;
- se o time tentar ligar VLM sem separar fast lane e heavy lane, vai disputar recursos com publish;
- se o time tentar abrir busca publica antes de consentimento, retention e `searchable=false` em rejeicao, cria risco de produto e LGPD;
- se o time tentar plugar provider real sem TTD por status, o pipeline vira um acumulado de heuristicas.

## Milestones De Execucao

## M0 - Fechar Dominio De Moderacao E Override

Objetivo:

- migrar o produto de `manual/auto` para `none/manual/ai`;
- tornar a origem da decisao auditavel;
- preservar override humano como comportamento de primeira classe.

Relaciona-se com arquitetura:

- `Modelo de moderacao do produto`
- `O que bloqueia publish`
- `Fallback behavior`

### TASK M0-T1 - Evoluir `moderation_mode` para `none/manual/ai`

Estado atual:

- `Event` ainda usa `manual` e `auto`;
- requests e controllers validam apenas `manual,auto`;
- `isAutoModeration()` ainda carrega semantica antiga.

Status em 2026-04-01:

- enum e migration de compatibilidade ja existem no repo;
- model `Event` ja normaliza legado `auto -> none` no acesso;
- requests e controller ja aceitam `none/manual/ai`;
- `PublicUploadController`, `EventEditorPage` e `EventDetailPage` ja estao alinhados com os 3 modos;
- TTD desta task cobre create `none/manual/ai`, migration legado e payload publico.

Subtasks:

1. criar `EventModerationMode` enum dedicado;
2. ajustar `events` para usar valores `none`, `manual`, `ai`;
3. criar migration de compatibilidade mapeando `auto -> none`;
4. ajustar `Event` para usar helpers semanticos novos;
5. remover dependencia de `isAutoModeration()` dos fluxos novos;
6. ajustar `StoreEventRequest`, `UpdateEventRequest` e controller;
7. ajustar resources de evento e payload do upload publico;
8. revisar seeders, factories e mocks.

TTD obrigatorio:

- Feature:
  - criar evento com `moderation_mode=none`;
  - criar evento com `moderation_mode=manual`;
  - criar evento com `moderation_mode=ai`;
  - atualizar evento legado `auto` para `none` sem perda de comportamento.
- Unit:
  - helpers do model `Event` para cada modo;
  - enum e normalizacao de compatibilidade.
- Regression:
  - upload publico continua retornando payload valido para eventos antigos ja existentes.

Dependencias:

- nenhuma.

Criterio de aceite:

- API nao aceita mais ambiguidade de `auto`;
- qualquer evento novo consegue operar claramente em um dos tres modos.

Arquivos provaveis:

- `apps/api/app/Modules/Events/Models/Event.php`
- `apps/api/app/Modules/Events/Http/Requests/StoreEventRequest.php`
- `apps/api/app/Modules/Events/Http/Requests/UpdateEventRequest.php`
- `apps/api/app/Modules/Events/Http/Controllers/EventController.php`
- `apps/api/database/migrations/*`
- `apps/api/database/seeders/EventsDemoSeeder.php`

### TASK M0-T2 - Formalizar origem da decisao e override auditavel em `event_media`

Estado atual:

- existe approve/reject manual;
- nao existe trilha de `decision_source` nem override com contexto.

Status em 2026-04-01:

- `event_media` ja possui `decision_source`, `decision_overridden_at`, `decision_overridden_by_user_id` e `decision_override_reason`;
- `FinalizeMediaDecisionAction` ja preenche a origem automatica por `none/manual/ai`;
- `ApproveEventMediaAction` e `RejectEventMediaAction` ja registram override humano e despublicacao quando necessario;
- `EventMediaResource` e `EventMediaDetailResource` ja expoem a trilha de decisao;
- TTD desta task cobre origem automatica, approve/reject/reapprove e contrato de resource.

Subtasks:

1. criar migration aditiva em `event_media` com:
   - `decision_source`
   - `decision_overridden_at`
   - `decision_overridden_by_user_id`
   - `decision_override_reason`
2. atualizar `EventMedia` model e resources;
3. ajustar `FinalizeMediaDecisionAction` para preencher `decision_source`;
4. ajustar `ApproveEventMediaAction` para registrar override humano;
5. ajustar `RejectEventMediaAction` para registrar override humano;
6. definir politica para reaprovar midia rejeitada;
7. garantir que rejeicao humana despublique quando necessario;
8. garantir que rejeicao humana force `searchable=false` nas faces indexadas, quando o modulo existir.

TTD obrigatorio:

- Feature:
  - aprovar midia pendente por usuario autenticado;
  - rejeitar midia aprovada por usuario autenticado;
  - reaprovar midia rejeitada por usuario autenticado.
- Unit:
  - `FinalizeMediaDecisionAction` preenche `decision_source` correto por modo;
  - actions de approve/reject preenchem metadados de override.
- Resource:
  - `EventMediaResource` e `EventMediaDetailResource` expoem trilha de decisao sem quebrar frontend atual.

Dependencias:

- M0-T1.

Criterio de aceite:

- qualquer mudanca humana de status fica rastreada;
- a origem da decisao automatica ou manual deixa de ser implicita.

Arquivos provaveis:

- `apps/api/app/Modules/MediaProcessing/Models/EventMedia.php`
- `apps/api/app/Modules/MediaProcessing/Actions/FinalizeMediaDecisionAction.php`
- `apps/api/app/Modules/MediaProcessing/Actions/ApproveEventMediaAction.php`
- `apps/api/app/Modules/MediaProcessing/Actions/RejectEventMediaAction.php`
- `apps/api/app/Modules/MediaProcessing/Http/Resources/EventMediaResource.php`
- `apps/api/app/Modules/MediaProcessing/Http/Resources/EventMediaDetailResource.php`
- `apps/api/database/migrations/*`

### TASK M0-T3 - Separar toggle de `FaceSearch` da moderacao

Estado atual:

- a arquitetura ja define `FaceSearch` como opcional por evento;
- o backend ainda nao possui modulo de busca facial nem settings reais dessa feature.

Subtasks:

1. definir contrato minimo de configuracao por evento:
   - `enabled`
   - `allow_public_selfie_search`
   - `selfie_retention_hours`
2. reservar tabela `event_face_search_settings` nesta fase ou criar migration junto da fase `FaceSearch`;
3. expor esse toggle no contrato de evento ou settings dedicados;
4. garantir que desligar `FaceSearch` implique `face_index_status=skipped` no pipeline;
5. documentar que isso e independente de `moderation_mode`.

TTD obrigatorio:

- Unit:
  - policy de gating de `FaceSearch` por evento.
- Feature:
  - evento com `FaceSearch` desligado nao enfileira indexacao;
  - evento com `FaceSearch` ligado prepara status `queued`.

Dependencias:

- M0-T1.

Criterio de aceite:

- produto consegue ligar/desligar busca por pessoa sem alterar moderacao.

Arquivos provaveis:

- `apps/api/app/Modules/Events/*`
- `apps/api/app/Modules/MediaProcessing/Actions/FinalizeMediaDecisionAction.php`
- migrations futuras de `FaceSearch`

## M1 - Fast Lane Real Na Infra Atual

Objetivo:

- manter upload curto;
- separar fast lane de heavy lane no Horizon;
- preparar preview rapido, safety e VLM sem contaminar publish.

Relaciona-se com arquitetura:

- `Como o fast lane roda nesta stack`
- `Fast lane vs heavy lane na stack atual`
- `Filas recomendadas`

### TASK M1-T1 - Criar fila e supervisor dedicados para `media-fast`

Estado atual:

- pipeline base ainda se concentra em `media-process`;
- `horizon.php` ainda nao reserva capacidade para fast lane.

Status em 2026-04-01:

- `media-fast` ja foi adicionado em `horizon.php`;
- `GenerateMediaVariantsJob` e `RunModerationJob` ja foram movidos para essa fila;
- `.env.example` ja documenta `HORIZON_WAIT_MEDIA_FAST`, `HORIZON_WAIT_MEDIA_SAFETY` e `HORIZON_MEDIA_FAST_MAX_PROCESSES`;
- TTD desta task cobre config carregada de Horizon, dispatch do upload e regressao do pipeline.

Subtasks:

1. adicionar fila `media-fast` em `horizon.php`;
2. adicionar wait threshold de `media-fast`;
3. criar supervisor dedicado com capacidade reservada;
4. separar supervisor para lane pesado quando entrar `face-index`;
5. documentar envs de escala por ambiente;
6. revisar tags e logs dos jobs impactados.

TTD obrigatorio:

- Config test:
  - asserts em config carregada de Horizon para `media-fast`.
- Feature:
  - dispatch de job de preview/variants vai para fila esperada.
- Regression:
  - filas atuais `media-process` e `media-publish` continuam operando.

Dependencias:

- nenhuma.

Criterio de aceite:

- fast lane tem capacidade dedicada e deixa de competir cegamente com pipeline pesado.

Arquivos provaveis:

- `apps/api/config/horizon.php`
- jobs do pipeline base

### TASK M1-T2 - Introduzir variante `fast_preview` / `preview_512`

Estado atual:

- variantes existentes sao `thumb`, `gallery` e `wall`;
- safety e VLM ainda nao tem uma variante canonica curta para consumo rapido.

Status em 2026-04-01:

- `fast_preview` ja foi adicionado em `MediaVariantGeneratorService`;
- `GenerateMediaVariantsJob` ja persiste essa variante e a run de `variants` registra `generated_count=4`;
- `MediaAssetUrlService` ja prefere `fast_preview` para `preview_url` de imagens;
- `AnalyzeContentSafetyJob` ja referencia essa variante como `input_ref` quando ela existe;
- TTD desta task cobre generator unitario, pipeline feature e payload detalhado da API.

Subtasks:

1. adicionar definicao de variante `fast_preview` em `MediaVariantGeneratorService`;
2. decidir se `fast_preview` e gerado antes das demais variantes no mesmo job ou em job encadeado;
3. persistir essa variante em `event_media_variants`;
4. ajustar `GenerateMediaVariantsJob` para registrar metrica da variante curta;
5. garantir que safety e VLM consumam preferencialmente `fast_preview`.

TTD obrigatorio:

- Unit:
  - generator cria `fast_preview` com dimensoes e mime corretos;
  - generator continua criando `thumb`, `gallery`, `wall`.
- Feature:
  - pipeline salva `fast_preview` e run da etapa `variants`;
  - `MediaVariantsGenerated` continua sendo emitido.
- Regression:
  - public gallery e wall continuam consumindo `thumb/gallery/wall`.

Dependencias:

- M1-T1 recomendada, mas nao obrigatoria.

Criterio de aceite:

- existe uma variante curta padrao para inferencia rapida.

Arquivos provaveis:

- `apps/api/app/Modules/MediaProcessing/Services/MediaVariantGeneratorService.php`
- `apps/api/app/Modules/MediaProcessing/Jobs/GenerateMediaVariantsJob.php`
- testes de `MediaProcessing`

### TASK M1-T3 - Introduzir classificacao de falha operacional em `media_processing_runs`

Estado atual:

- `media_processing_runs` ja tem `stage_key`, provider e metricas;
- ainda faltam `queue_name`, `worker_ref`, `failure_class` e custo.

Status em 2026-04-01:

- migration aditiva ja entrou no repo com campos operacionais e de versao;
- `MediaProcessingRun` e `MediaProcessingRunService` ja foram atualizados;
- detalhe da midia ja expoe `queue_name`, `worker_ref`, `cost_units` e `failure_class`;
- TTD desta task cobre service unitario, detalhe HTTP e regressao do pipeline.

Subtasks:

1. criar migration aditiva;
2. ajustar model `MediaProcessingRun`;
3. ajustar `MediaProcessingRunService` para preencher:
   - `queue_name`
   - `worker_ref`
   - `failure_class`
   - `cost_units`
4. padronizar classificacao:
   - `transient`
   - `permanent`
   - `policy`
5. revisar `failed()` dos jobs atuais.

TTD obrigatorio:

- Unit:
  - `MediaProcessingRunService` grava campos novos corretamente.
- Feature:
  - falha de variants grava `failure_class` coerente;
  - falha de policy grava classe correta sem marcar como sucesso.
- Resource:
  - detalhe da midia expoe runs enriquecidos.

Dependencias:

- nenhuma.

Criterio de aceite:

- diagnostico operacional deixa de depender de log textual solto.

Arquivos provaveis:

- `apps/api/app/Modules/MediaProcessing/Models/MediaProcessingRun.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaProcessingRunService.php`
- jobs do pipeline
- migration nova

## M2 - ContentModeration Em Producao

Objetivo:

- sair do provider nulo;
- plugar safety real por evento;
- consolidar comportamento de `none/manual/ai`.

Relaciona-se com arquitetura:

- `Safety moderation`
- `Fallback behavior`
- `Threshold calibration per event type`

### TASK M2-T1 - Criar provider real de `ContentModeration`

Estado atual:

- `NullContentModerationProvider` existe para local/testes;
- `ContentModerationProviderManager` e `OpenAiContentModerationProvider` ja estao plugados no repo;
- `EvaluateContentSafetyAction` ja ignora provider externo fora de `moderation_mode=ai`;
- TTD cobre mapping OpenAI, thresholds, fallback e gating por modo.

Status em 2026-04-02:

- task concluida no codigo.

Subtasks:

1. implementar adapter real de `ContentModerationProviderInterface`;
2. mapear resposta externa para DTO interno;
3. registrar:
   - `provider_key`
   - `provider_version`
   - `model_key`
   - `model_snapshot`
4. suportar thresholds por categoria;
5. manter provider nulo como fallback de ambiente local/testes;
6. garantir timeout curto e fallback para `pending`.

TTD obrigatorio:

- Unit:
  - mapping do provider para `ContentSafetyEvaluationResult`;
  - thresholds geram `pass`, `review` e `block`.
- Integration fake:
  - provider fake responde payload esperado e o action persiste historico;
  - falha do provider marca `safety_status=failed`.
- Feature:
  - evento em `ai` com safety `block` termina rejeitado;
  - evento em `ai` com safety `review` termina pendente;
  - evento em `none` ignora gate de moderacao e segue aprovado.

Dependencias:

- M0-T1.

Criterio de aceite:

- `ContentModeration` deixa de ser apenas foundation e passa a influenciar o fluxo de produto.

Arquivos provaveis:

- `apps/api/app/Modules/ContentModeration/Services/*`
- `apps/api/app/Modules/ContentModeration/Actions/EvaluateContentSafetyAction.php`
- `apps/api/app/Modules/ContentModeration/Jobs/AnalyzeContentSafetyJob.php`
- config/envs novos

### TASK M2-T2 - Expor settings de safety por evento

Estado atual:

- tabelas ja existem;
- endpoint dedicado e card administrativo no detalhe do evento ja existem;
- TTD cobre leitura default, update autorizado, bloqueio sem permissao e formulario RHF + Zod.

Status em 2026-04-02:

- task concluida no codigo.

Subtasks:

1. criar requests, actions e controller fino para settings;
2. permitir:
   - habilitar/desabilitar;
   - provider por evento;
   - thresholds de `block` e `review`;
   - `fallback_mode`;
3. expor resource de leitura;
4. revisar policy de acesso;
5. refletir isso no frontend administrativo.

TTD obrigatorio:

- Feature:
  - criar ou atualizar settings por evento;
  - negar alteracao sem permissao.
- Unit:
  - normalizacao de thresholds e fallback.
- Frontend:
  - teste do form com React Hook Form + Zod;
  - teste de submit e loading state.

Dependencias:

- M2-T1 pode rodar em paralelo na infraestrutura, mas a integracao final depende dele.

Criterio de aceite:

- operador consegue configurar safety sem editar banco manualmente.

Arquivos provaveis:

- `apps/api/app/Modules/ContentModeration/Http/*`
- `apps/api/app/Modules/ContentModeration/Actions/*`
- `apps/web/src/modules/moderation/*`

### TASK M2-T3 - Ajustar matriz final de moderacao para os 3 modos

Estado atual:

- `FinalizeMediaDecisionAction` ainda carrega semantica antiga de `manual/auto`.

Subtasks:

1. reescrever a matriz de decisao para:
   - `none`
   - `manual`
   - `ai`
2. garantir que `none` aprove ao final do pipeline base;
3. garantir que `manual` sempre termine em `pending`;
4. garantir que `ai` respeite `safety` e depois VLM `gate`;
5. preencher `decision_source` coerente;
6. nao permitir aprovacao por falha tecnica.

TTD obrigatorio:

- Unit:
  - tabela de cenarios cobrindo todos os modos e combinacoes de `safety_status` / `vlm_status`.
- Feature:
  - midia em evento `none` termina aprovada;
  - midia em evento `manual` termina pendente;
  - midia em evento `ai` respeita safety.
- Regression:
  - approve/reject manual continua funcionando.

Dependencias:

- M0-T1
- M2-T1

Criterio de aceite:

- o pipeline deixa de depender de semantica antiga de `auto`.

Arquivos provaveis:

- `apps/api/app/Modules/MediaProcessing/Actions/FinalizeMediaDecisionAction.php`
- testes de `MediaProcessing`

## M3 - MediaIntelligence E VLM Rapido

Objetivo:

- introduzir VLM rapido em JSON estruturado;
- suportar modos `enrich_only` e `gate`;
- devolver caption curta e decisao sem duplicar chamadas.

Relaciona-se com arquitetura:

- `VLM`
- `Avaliacao do VLM rapido`
- `Fallback behavior`

### TASK M3-T1 - Criar modulo `MediaIntelligence`

Estado atual:

- implementado no codigo atual;
- provider do modulo registrado em `config/modules.php`;
- rotas e `README` do modulo ja existem.

Subtasks:

1. criar estrutura completa do modulo:
   - `Actions`
   - `DTOs`
   - `Enums`
   - `Http`
   - `Jobs`
   - `Models`
   - `Providers`
   - `Services`
   - `routes`
   - `README.md`
2. registrar service provider no carregamento de modulos;
3. atualizar `docs/modules/module-map.md` se existir no fluxo do time.

TTD obrigatorio:

- Smoke:
  - provider do modulo sobe sem quebrar o bootstrap;
  - rotas base podem ser registradas.

Dependencias:

- nenhuma.

Criterio de aceite:

- existe espaco de dominio limpo para VLM.

Status:

- concluida.

Arquivos provaveis:

- `apps/api/app/Modules/MediaIntelligence/*`
- config de modulos

### TASK M3-T2 - Criar schema e modelos de `MediaIntelligence`

Estado atual:

- implementado no codigo atual;
- tabelas, models, casts e factories ja existem no repo.

Subtasks:

1. criar migration de settings;
2. criar migration de historico de avaliacoes;
3. criar models e casts;
4. criar indices relevantes por `event_id` e `event_media_id`;
5. decidir nullable/defaults para rollout incremental.

TTD obrigatorio:

- Migration:
  - sobe e desce sem erro;
  - indices sao criados.
- Model:
  - casts de json e datas funcionam.

Dependencias:

- M3-T1.

Criterio de aceite:

- o dominio tem onde persistir configuracao e historico do VLM.

Status:

- concluida.

Arquivos provaveis:

- migrations novas
- `apps/api/app/Modules/MediaIntelligence/Models/*`

### TASK M3-T3 - Criar contratos e adapter de `VisualReasoningProviderInterface`

Estado atual:

- implementado no codigo atual;
- `VisualReasoningProviderInterface`, `VisualReasoningProviderManager`, `NullVisualReasoningProvider` e `VllmVisualReasoningProvider` ja existem.

Subtasks:

1. criar `VisualReasoningProviderInterface`;
2. criar DTOs de request/response estruturados;
3. implementar adapter base para `vLLM` OpenAI-compatible;
4. suportar `response_schema_version`;
5. suportar `mode_applied`:
   - `enrich_only`
   - `gate`
6. garantir uma unica chamada para:
   - `decision`
   - `reason`
   - `short_caption`
   - `tags`

TTD obrigatorio:

- Unit:
  - mapping do payload JSON do provider;
  - validacao de schema estruturado;
  - comportamento quando o provider devolve JSON invalido.
- Integration fake:
  - adapter recebe imagem e devolve DTO coerente.

Dependencias:

- M3-T1.

Criterio de aceite:

- o dominio fala com um contrato proprio e recebe resposta estruturada estavel.

Status:

- concluida.

Arquivos provaveis:

- `apps/api/app/Modules/MediaIntelligence/Services/*`
- `apps/api/app/Modules/MediaIntelligence/DTOs/*`

### TASK M3-T4 - Implementar `EvaluateMediaPromptJob`

Estado atual:

- job ja existe em `media-vlm`;
- fast lane ja chama VLM via `AnalyzeContentSafetyJob` e respeita `gate` vs `enrich_only`.

Subtasks:

1. criar `EvaluateMediaPromptJob` em fila `media-vlm`;
2. consumir `fast_preview`;
3. ler settings do evento;
4. pular a etapa quando `MediaIntelligence` estiver desabilitado;
5. persistir `event_media_vlm_evaluations`;
6. atualizar `event_media.vlm_status`;
7. reencaminhar para decisao final quando `mode=gate`;
8. nao bloquear publish quando `mode=enrich_only`.

TTD obrigatorio:

- Job:
  - job roda com provider fake e persiste avaliacao;
  - job falha corretamente e marca `vlm_status=failed`.
- Feature:
  - evento com `mode=enrich_only` publica mesmo com caption falha;
  - evento com `mode=gate` cai em `pending` na falha.
- Regression:
  - pipeline sem `MediaIntelligence` continua operando.

Dependencias:

- M1-T2
- M3-T2
- M3-T3

Criterio de aceite:

- VLM entra no fluxo sem dupla chamada nem acoplamento ao provider.

Status:

- concluida.

Arquivos provaveis:

- `apps/api/app/Modules/MediaIntelligence/Jobs/*`
- `apps/api/app/Modules/MediaIntelligence/Actions/*`
- `apps/api/app/Modules/MediaProcessing/*`

### TASK M3-T5 - Criar UI de settings e superficie de leitura do VLM

Estado atual:

- frontend ja expõe settings por evento e a central de moderacao ja consome a ultima leitura estruturada do VLM.

Subtasks:

1. criar modulo frontend `media-intelligence`;
2. criar tela de settings por evento;
3. permitir configurar:
   - `enabled`
   - `mode`
   - `prompt_version`
   - `approval_prompt`
   - `caption_style_prompt`
   - `timeout_ms`
   - `fallback_mode`
4. expor caption e reason na tela de moderacao;
5. adicionar acao de reprocessamento futuro quando endpoint existir.

TTD obrigatorio:

- Frontend:
  - testes de hooks de query e mutation;
  - teste de form com validacao Zod;
  - teste de render de badge/resultado na moderacao.
- Contract:
  - types em `packages/contracts` ou `packages/shared-types`.

Dependencias:

- M3-T2
- M3-T4

Criterio de aceite:

- operador consegue configurar e enxergar o resultado sem usar banco ou log.

Status:

- concluida.

Arquivos provaveis:

- `apps/web/src/modules/media-intelligence/*`
- `apps/web/src/modules/moderation/*`
- `packages/contracts/*`

## M4 - FaceSearch Foundation

Objetivo:

- introduzir indexacao facial como enrichment;
- guardar embedding por face, nao por foto inteira;
- manter `FaceSearch` como opcional por evento.

Relaciona-se com arquitetura:

- `Face search`
- `Busca facial: logica recomendada`
- `Implementacao vetorial inicial: PostgreSQL + pgvector`

### TASK M4-T1 - Criar modulo `FaceSearch`

Estado atual:

- modulo ja existe com `ServiceProvider`, `README`, rotas dedicadas e superficie administrativa por evento.

Subtasks:

1. criar estrutura completa do modulo backend;
2. registrar provider do modulo;
3. criar `README.md` do modulo com responsabilidades e nao-responsabilidades;
4. reservar rotas para settings, busca e reprocessamento futuro.

TTD obrigatorio:

- Smoke:
  - bootstrap do modulo e rotas base.

Dependencias:

- nenhuma.

Criterio de aceite:

- existe fronteira de dominio limpa para facial.

Arquivos provaveis:

- `apps/api/app/Modules/FaceSearch/*`

### TASK M4-T2 - Criar schema de `FaceSearch`

Estado atual:

- `event_face_search_settings` ja existe e foi expandida com provider, thresholds e `top_k`;
- `event_media_faces` e `event_face_search_requests` ja entraram no banco com fallback compativel com SQLite nos testes.

Subtasks:

1. criar migration de settings;
2. criar migration de faces indexadas com `vector(512)` ou dimensao configurada;
3. criar indices HNSW e filtros por `event_id`;
4. criar migration de `event_face_search_requests`;
5. criar models, casts e scopes;
6. garantir `searchable` por default seguro.

TTD obrigatorio:

- Migration:
  - sobe e desce com `pgvector`;
  - indice vetorial e indices relacionais sobem corretamente.
- Model:
  - casts de numeros e datas;
  - scope por evento e searchable.

Dependencias:

- M4-T1.

Criterio de aceite:

- o banco suporta indexacao por face e busca por evento.

Arquivos provaveis:

- migrations novas
- `apps/api/app/Modules/FaceSearch/Models/*`

### TASK M4-T3 - Criar interfaces faciais e porta vetorial

Estado atual:

- interfaces faciais e DTOs ja existem no codigo;
- `PgvectorFaceVectorStore` ja e a implementacao inicial do dominio.

Subtasks:

1. criar `FaceDetectionProviderInterface`;
2. criar `FaceEmbeddingProviderInterface`;
3. criar `FaceVectorStoreInterface`;
4. criar DTOs de bbox, face crop, embedding e resultado de busca;
5. criar adapter inicial de arranque:
   - detection/embedding provider MVP
   - `pgvector` store adapter
6. isolar detalhes do provider do dominio.

TTD obrigatorio:

- Unit:
  - DTOs e mapping de adapter;
  - adapter vetorial em `pgvector`.
- Integration fake:
  - detection devolve duas faces;
  - embedding store persiste e busca por `event_id`.

Dependencias:

- M4-T1
- M4-T2

Criterio de aceite:

- `FaceSearch` nasce desacoplado de engine especifica.

Arquivos provaveis:

- `apps/api/app/Modules/FaceSearch/Services/*`
- `apps/api/app/Modules/FaceSearch/DTOs/*`

### TASK M4-T4 - Implementar `IndexMediaFacesJob`

Estado atual:

- pipeline ja indexa faces por `IndexMediaFacesJob`;
- `RunModerationJob` ja dispara a etapa quando `face_index_status=queued`;
- `ApproveEventMediaAction` e `RejectEventMediaAction` ja controlam `searchable` apos override humano.

Subtasks:

1. criar `IndexMediaFacesJob` na fila `face-index`;
2. pular a etapa quando `FaceSearch` estiver desligado;
3. carregar variante apropriada para indexacao;
4. detectar faces;
5. descartar faces ruins por tamanho/qualidade;
6. gerar crop privado por face;
7. gerar embedding por face;
8. persistir em `event_media_faces`;
9. marcar `face_index_status=indexed/skipped/failed`;
10. garantir `searchable=false` quando a midia estiver rejeitada.

TTD obrigatorio:

- Job:
  - evento com `FaceSearch` desligado gera `skipped`;
  - evento com `FaceSearch` ligado e faces validas gera linhas em `event_media_faces`;
  - falha do provider marca `face_index_status=failed`.
- Feature:
  - midia rejeitada nao deixa faces `searchable` para busca publica.
- Unit:
  - filtro de qualidade de faces.

Dependencias:

- M0-T3
- M4-T2
- M4-T3

Criterio de aceite:

- indexacao facial passa a existir como enrichment isolado.

Arquivos provaveis:

- `apps/api/app/Modules/FaceSearch/Jobs/*`
- `apps/api/app/Modules/FaceSearch/Actions/*`
- `apps/api/app/Modules/MediaProcessing/*`

### TASK M4-T5 - Expor settings administrativos de `FaceSearch`

Estado atual:

- endpoint dedicado e card administrativo no detalhe do evento ja existem;
- a central de moderacao ja reflete `face_index_status` e `indexed_faces_count`.

Subtasks:

1. criar endpoints de leitura/escrita dos settings;
2. criar pagina `FaceSearchSettingsPage`;
3. permitir:
   - ligar/desligar;
   - definir limiares de qualidade;
   - definir `allow_public_selfie_search`;
   - definir retention da selfie;
4. refletir status de indexacao na central de moderacao.

TTD obrigatorio:

- Feature:
  - CRUD de settings por evento.
- Frontend:
  - form validado;
  - render de status de indexacao.

Dependencias:

- M4-T2
- M4-T4

Criterio de aceite:

- operador consegue controlar a feature por evento.

Arquivos provaveis:

- `apps/api/app/Modules/FaceSearch/Http/*`
- `apps/web/src/modules/face-search/*`
- `apps/web/src/modules/moderation/*`

## M5 - Busca Por Selfie E Exposicao De Resultado

Objetivo:

- abrir busca por pessoa de forma segura;
- manter escopo por evento;
- diferenciar uso interno e uso publico.

Relaciona-se com arquitetura:

- `Busca facial: logica recomendada`
- `Busca publica por selfie`
- `Privacidade, seguranca e LGPD`

### TASK M5-T1 - Criar endpoint interno de busca por selfie

Estado atual:

- endpoint implementado em `/events/{event}/face-search/search`;
- `SearchFacesBySelfieAction` agora valida face unica, aplica quality gate, gera embedding da selfie, busca por `event_id` e reranqueia por `event_media_id`;
- `CollapseFaceSearchMatchesQuery` cobre o colapso por foto em teste unitario;
- TTD cobre isolamento por evento, erro claro quando a selfie nao tem face valida e colapso por foto.

Subtasks:

1. criar request e controller fino;
2. aceitar selfie temporaria;
3. validar face unica e qualidade minima;
4. gerar embedding da selfie;
5. buscar top-k por `event_id`;
6. reranquear e colapsar por `event_media_id`;
7. retornar apenas midias permitidas para o contexto interno.

TTD obrigatorio:

- Feature:
  - busca retorna fotos do evento correto;
  - busca nao retorna fotos de outro evento;
  - selfie sem face valida retorna erro claro.
- Unit:
  - reranking e colapso por `event_media_id`.

Dependencias:

- M4-T4.

Criterio de aceite:

- backoffice consegue buscar pessoa por selfie com isolamento por evento.

Status:

- concluida.

Arquivos provaveis:

- `apps/api/app/Modules/FaceSearch/Http/*`
- `apps/api/app/Modules/FaceSearch/Queries/*`

### TASK M5-T2 - Criar endpoint e fluxo publico com consentimento

Estado atual:

- bootstrap publico implementado em `/public/events/{slug}/face-search`;
- busca publica implementada em `/public/events/{slug}/face-search/search`;
- `event_face_search_requests` agora persiste consentimento, retention e telemetria da request;
- TTD cobre bloqueio quando a feature publica esta desligada, exigencia de consentimento e filtro apenas de midias `approved + published`.

Subtasks:

1. criar endpoint publico dedicado;
2. persistir `event_face_search_requests` com:
   - `consent_version`
   - `selfie_storage_strategy`
   - `query_face_quality_score`
3. limitar resultado a `approved + published`;
4. aplicar TTL da selfie;
5. impedir uso quando `allow_public_selfie_search=false`;
6. registrar auditoria e rate limit.

TTD obrigatorio:

- Feature:
  - evento com busca publica desligada retorna bloqueio funcional;
  - evento com busca publica ligada retorna apenas midias publicadas;
  - selfie expira conforme retention.
- Security:
  - sem consentimento explicito, request falha.

Dependencias:

- M4-T5
- M5-T1

Criterio de aceite:

- busca publica fica protegida por consentimento e escopo correto.

Status:

- concluida.

Arquivos provaveis:

- `apps/api/app/Modules/FaceSearch/Http/*`
- `apps/api/routes/*` ou rotas do modulo

### TASK M5-T3 - Criar UI interna e publica de busca por selfie

Estado atual:

- rota publica implementada em `/e/:slug/find-me`;
- card interno implementado na aba de moderacao do detalhe do evento;
- `FaceSearchSearchPanel` agora cobre estados de upload, processamento, sem resultado, resultado encontrado e consentimento explicito quando necessario;
- TTD cobre submit publico com consentimento obrigatorio e render do estado vazio.

Subtasks:

1. criar UI interna para operadores;
2. criar rota publica por evento, por exemplo `/e/:slug/find-me`;
3. explicar consentimento de uso;
4. mostrar estados:
   - enviando
   - processando
   - sem resultado
   - resultado encontrado
5. tratar mensagens de erro claras para:
   - nenhuma face
   - varias faces
   - evento sem feature habilitada
6. integrar com realtime se a busca virar assincrona no futuro.

TTD obrigatorio:

- Frontend:
  - testes de form/upload;
  - testes de estados de tela;
  - testes de exibicao apenas de midias publicadas no fluxo publico.

Dependencias:

- M5-T1
- M5-T2

Criterio de aceite:

- operador e convidado conseguem usar a busca com UX previsivel.

Status:

- concluida.

Arquivos provaveis:

- `apps/web/src/modules/face-search/*`
- `apps/web/src/App.tsx`

## M6 - Operacao, Reprocessamento E Exclusao

Objetivo:

- endurecer o pipeline para evento ao vivo;
- permitir reprocessar por etapa;
- garantir exclusao propagada e observabilidade.

Relaciona-se com arquitetura:

- `Fallback behavior`
- `Observabilidade, auditoria e analytics`
- `Deletion propagation`

### TASK M6-T1 - Implementar reprocessamento seletivo por etapa

Estado atual:

- implementado no codigo atual;
- endpoint `POST /api/v1/media/{id}/reprocess/{stage}` ja existe para `safety`, `vlm` e `face_index`;
- `ReprocessEventMediaStageAction` ja atualiza status, registra auditoria e despacha a etapa correta.

Subtasks:

1. criar action e endpoints por etapa:
   - reprocessar safety
   - reprocessar VLM
   - reprocessar face index
2. evitar refazer o pipeline inteiro sem necessidade;
3. versionar reprocessamento por `model_snapshot` e `prompt_version`;
4. atualizar `media_processing_runs`.

TTD obrigatorio:

- Feature:
  - reprocessar safety cria nova avaliacao sem apagar historico;
  - reprocessar VLM cria nova avaliacao;
  - reprocessar face index reescreve projection mantendo trilha.

Dependencias:

- M2
- M3
- M4

Criterio de aceite:

- operador consegue corrigir ou atualizar uma etapa isoladamente.

Status:

- concluida.

### TASK M6-T2 - Endurecer DLQ, retries e circuit breaker

Estado atual:

- implementado no codigo atual;
- `PipelineFailureClassifier` ja classifica falhas e os jobs do pipeline persistem `failure_class`;
- `ProviderCircuitBreaker` ja protege `ContentModeration` e `MediaIntelligence`, com fallback seguro.

Subtasks:

1. definir estrategia de falha permanente por fila;
2. configurar retries curtos para fast lane;
3. implementar circuit breaker por provider onde fizer sentido;
4. emitir alertas de backlog e falha repetida;
5. documentar playbook de incidente.

TTD obrigatorio:

- Unit:
  - classificacao de `failure_class`.
- Integration:
  - provider fake em falha repetida aciona comportamento de fallback;
  - fila continua processando itens saudaveis.

Dependencias:

- M1-T3

Criterio de aceite:

- falha de provider nao derruba o evento inteiro.

Status:

- concluida.

### TASK M6-T3 - Implementar exclusao propagada de artefatos e vetores

Estado atual:

- implementado no codigo atual;
- `DeleteEventMediaAction` ja desliga `searchable` e despacha cleanup dedicado;
- `CleanupDeletedMediaArtifactsJob` ja remove artefatos de storage, projection vetorial e referencias de busca.

Subtasks:

1. escutar `MediaDeleted`;
2. apagar crops e selfies temporarias;
3. apagar ou invalidar vetor no store atual;
4. limpar requests e caches associados;
5. registrar auditoria do cleanup;
6. garantir `searchable=false` imediatamente na exclusao logica.

TTD obrigatorio:

- Feature:
  - excluir midia apaga registros e artefatos associados;
  - midia deletada deixa de aparecer em busca publica.
- Integration:
  - adapter vetorial recebe comando de delete.

Dependencias:

- M4
- M5

Criterio de aceite:

- lifecycle de exclusao passa a cobrir todo o ecossistema da midia.

Status:

- concluida.

### TASK M6-T4 - Observabilidade e metricas finais do pipeline

Estado atual:

- implementado no codigo atual;
- `/events/{event}/media/pipeline-metrics` ja expone summary, SLA, backlog e falhas;
- `AppServiceProvider` ja loga `LongWaitDetected` do Horizon para espera anomala de fila.

Subtasks:

1. definir dashboards minimos:
   - tempo do upload ate publish
   - tempo do upload ate primeira atualizacao de tela
   - tempo do upload ate face index
   - taxa de review e block
   - backlog por fila
2. instrumentar metricas faltantes;
3. expor indicadores no painel interno ou sistema de observabilidade;
4. validar SLA inicial do produto com benchmark real.

TTD obrigatorio:

- Unit:
  - agregadores e serializers de metricas.
- Integration:
  - pipeline feliz grava runs suficientes para alimentar dashboard.

Dependencias:

- M1
- M2
- M3
- M4

Criterio de aceite:

- o time consegue medir o que prometeu em SLA.

Status:

- concluida.

## Proxima Execucao Recomendada

Se o time for sair deste slice e continuar a implementacao agora, a ordem mais segura e esta:

1. criar superficie administrativa para reprocessamento seletivo na central de moderacao e no detalhe da midia;
2. validar benchmark real por volume de evento e ajustar `maxProcesses`, thresholds e warm pool por fila;
3. endurecer deploy/producao de `pgvector` e preparar a porta de saida para `Qdrant`;
4. evoluir a busca publica por selfie para modo assincrono curto apenas se a carga real justificar.

Motivo:

- o fast lane e o heavy lane ja estao separados, com `FaceSearch` non-blocking funcionando no pipeline;
- `FaceSearch` ja foi exposto com consentimento, retention, reranking por foto e filtro `approved + published` no fluxo publico;
- `M6` ja fechou reprocessamento seletivo, exclusao propagada e observabilidade minima de SLA;
- o proximo risco deixou de ser abertura funcional e passou a ser tuning de carga, superficie operacional e trilha de escala vetorial.

## TTD Matrix Consolidada

Nenhuma milestone abaixo deve ser fechada sem estes niveis minimos:

| Camada | Obrigatorio |
| --- | --- |
| Backend Feature | endpoints, fluxo HTTP e integracao de modulo |
| Backend Unit | actions, services, mapeamento de provider e matriz de decisao |
| Backend Job | sucesso, falha, retry, fila correta e persistencia de run |
| Migration | sobe, desce e preserva compatibilidade |
| Frontend | form, estados de tela, query/mutation e render de status |
| Regression | fluxo atual de upload, moderacao, publish e wall |

Regra final:

- se uma task mexe com `moderation_status`, `publication_status`, `searchable` ou publish, ela obrigatoriamente precisa de testes de regressao do fluxo completo.
