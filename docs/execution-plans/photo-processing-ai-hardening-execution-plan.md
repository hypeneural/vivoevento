# Photo Processing AI Hardening Execution Plan

## Objetivo

Este documento transforma a validacao atual de IA de fotos em um plano de implementacao executavel para a proxima fase do produto.

Referencia primaria:

- `docs/architecture/photo-processing-ai-status-validation.md`

Referencias complementares:

- `docs/architecture/photo-processing-ai-architecture.md`
- `docs/execution-plans/photo-processing-ai-execution-plan.md`

Este plano existe para responder 6 perguntas de execucao:

1. o que virou decisao fechada de arquitetura;
2. o que ainda precisa ser implementado no codigo;
3. em que ordem as entregas devem entrar;
4. quais duvidas de negocio precisam ser validadas por testes;
5. quais smoke tests reais devem existir localmente;
6. qual e a definicao de pronto desta fase.

---

## Status De Execucao

- [x] `H5-T1` politica de segredos locais validada e mantida sem gravar chaves expostas no repositorio.
- [x] `H1-T1` fallback `image_url -> data_url` implementado com testes automatizados.
- [x] `H1-T2` persistencia granular e payload normalizado do provider implementados com testes automatizados.
- [x] `H1-T3` caracterizacao do comportamento atual de `observe_only` concluida com testes automatizados.
- [x] `H1-T3` mudanca semantica de `observe_only` implementada no pipeline com TDD end-to-end.
- [x] `H2-T0` topologia e capacidade operacional do `CompreFace` documentadas com base na arquitetura oficial.
- [x] `H2-T1` client/config do `CompreFace` implementados com TDD, sem liberar provider real ainda.
- [x] `H2-T2` detection provider real implementado com TDD, sem liberar provider global ainda.
- [x] `H2-T3` embedding provider real implementado com TDD, sem segunda chamada ao provider.
- [x] `H2-T4` liberacao de `compreface` no backend/frontend implementada com testes automatizados.
- [x] `H5-T3a` contrato do dataset curado criado com manifesto, README, pastas e teste automatizado.
- [x] `H5-T3b1` manifesto local do acervo consentido criado para homologacao repetivel fora do repositorio.
- [x] `H5-T2a` suporte do adapter `CompreFace` a chave dedicada de verification implementado com TDD.
- [x] `H5-T2b` comando de smoke semiautomatizado do `CompreFace` implementado com TDD.
- [x] `H5-T2c` `dry-run` do smoke executado com sucesso sobre o manifesto `vipsocial`.
- [x] `H5-T3b` dataset local consentido populado e derivativos <= 5 MB gerados para smoke/benchmark.
- [x] `H5-T2d` stack Docker local do `CompreFace` subida em porta dedicada e UI validada.
- [x] `H5-T2` smoke real do `CompreFace` executado com `detection` e `verification` reais, com relatorio salvo.
- [x] `H3-T1a` contrato/configuracao `exact|ann` implementados em settings, API, frontend e vector store com TDD.
- [x] `H3-T1b1` comando de benchmark `exact|ann` implementado com TDD em cima de smoke report.
- [x] `H3-T1b` benchmark comparativo `exact|ann` executado com smoke report real do `CompreFace`.
- [x] `H3-T2` quality tiers implementados com TDD no dominio, persistencia e ranking.
- [x] `H3-T3a` relatorio/command de benchmark local implementado com TDD.
- [x] `H3-T3` benchmark real e observabilidade local executados com dataset consentido e relatorio salvo.
- [x] `Calibracao local inicial do FaceSearch` executada com selecao explicita da face-alvo em fotos multi-face e `search_threshold=0.5`.
- [x] `Metas minimas locais de FaceSearch` atingidas no dataset consentido atual com `exact` e threshold `0.5`.
- [x] `Throughput real do lane face-index` medido com worker subprocesso, fila isolada e memoria alinhada ao `Horizon`.
- [x] `Investigacao inicial de latencia multi-face` concluida com relatorio por `scene_type` e `ms_per_detected_face`.
- [x] `H4-T0` contrato multimodal comum de `MediaIntelligence` implementado com TDD.
- [x] `H4-T1` provider opcional `openrouter` implementado em backend/frontend com TDD.
- [x] `H4-T2` politica de roteamento do `OpenRouter` fechada com catalogo local homologado e smoke real.
- [x] `H4-T3` ownership de caption caracterizado com TDD e auditabilidade no detalhe da midia.
- [x] `ReplyText` global e por evento persistidos em banco com TDD.
- [x] `ReplyText` persistido no historico VLM e entregue no feedback de midia publicada com TDD.
- [x] `H5-T2 OpenAI` smoke real bem-sucedido executado em `POST /v1/moderations` com `data_url`, relatorio salvo e integracao validada.
- [x] `UI de reply_text no painel` implementada para prompt global em `Settings` e override por evento em `MediaIntelligence`.
- [ ] `Aprovacao completa da fase` ainda depende da ampliacao do dataset curado do `FaceSearch` e da homologacao de prompts reais de `reply_text` em ambiente operacional.

Ultima rodada executada:

- `OpenAiContentModerationProviderTest`
- `ContentModerationPipelineTest`
- `ContentModerationObserveOnlyTest`
- `ContentModerationCircuitBreakerTest`
- `ContentModerationSettingsTest`
- `ContentSafetyThresholdEvaluatorTest`
- `FinalizeMediaDecisionActionTest`
- `MediaIntelligencePipelineTest`
- `EventMediaListTest`
- `MediaReprocessTest`
- `CompreFaceClientTest`
- `CompreFaceDetectionProviderTest`
- `CompreFaceEmbeddingProviderTest`
- `CollapseFaceSearchMatchesQueryTest`
- `FaceSearchSupportTest`
- `FaceIndexingPipelineTest`
- `FaceSearchSelfieEndpointsTest`
- `FaceSearchSettingsTest`
- `MediaDeletionPropagationTest`
- `CreateEventTest`
- `EventDetailAndLinksTest`
- `EventFaceSearchSettingsForm.test.tsx`
- `npm run type-check`
- `FaceSearchCuratedDatasetManifestTest`
- `FaceSearchLocalDatasetManifestTest`
- `RunCompreFaceSmokeCommandTest`
- `RunFaceSearchBenchmarkCommandTest`
- `ArtisanQueueFaceIndexLaneExecutorTest`
- `RunFaceIndexLaneThroughputCommandTest`
- `OpenAiCompatibleMultimodalPayloadNormalizerTest`
- `OpenRouterModelPolicyTest`
- `OpenRouterVisualReasoningProviderTest`
- `MediaIntelligenceSettingsTest`
- `MediaIntelligenceGlobalSettingsTest`
- `UpsertEventMediaIntelligenceSettingsActionTest`
- `OpenAiCompatibleVisualReasoningPayloadFactoryTest`
- `RunOpenRouterSmokeCommandTest`
- `RunOpenAiContentModerationSmokeCommandTest`
- `EventMediaIntelligenceSettingsForm.test.tsx`
- `SettingsPage.test.tsx`
- `TelegramFeedbackAutomationTest`
- `WhatsAppEventAutomationTest`
- `media-intelligence:smoke-openrouter --entry-id=vipsocial-person-a-01`
- `content-moderation:smoke-openai --entry-id=vipsocial-person-a-01`
- `face-search:smoke-compreface --dry-run`
- `face-search:smoke-compreface`
- `face-search:benchmark --smoke-report=...`
- `face-search:lane-throughput`
- `H3-T2` battery:
  - `FaceSearchSupportTest`
  - `CollapseFaceSearchMatchesQueryTest`
  - `FaceIndexingPipelineTest`
  - `FaceSearchSelfieEndpointsTest`

---

## Decisoes Fechadas

As decisoes abaixo deixam de ser "analise" e passam a ser premissa de implementacao:

1. `ContentModeration` continua em `OpenAI` direto.
2. `MediaIntelligence` continua sendo enrichment assincrono por padrao.
3. `FaceSearch` passa a ser prioridade 1 do produto.
4. o MVP de `FaceSearch` deve usar `CompreFace` atras de adapter interno.
5. o ranking final da busca facial continua dentro do proprio app, com `PostgreSQL + pgvector`.
6. a busca facial nasce com foco em recall:
   - `exact` primeiro
   - `ann` apenas por metrica
7. quality gate facial vira elemento central de performance e observabilidade.
8. `OpenRouter` so entra como provider opcional de `MediaIntelligence`, nunca como peca central de `ContentModeration` ou `FaceSearch`.

---

## Fora De Escopo Desta Fase

Para evitar escopo difuso, esta fase nao inclui:

1. substituir `PostgreSQL + pgvector` por `Qdrant`;
2. trocar `ContentModeration` para `OpenRouter`;
3. mover `FaceSearch` para `InsightFace/ArcFace` como trilha principal ja no MVP;
4. expandir `reply_text` transacional para fluxos alem da midia aprovada em WhatsApp/Telegram;
5. redesenhar o pipeline historico que ja esta validado em `photo-processing-ai-execution-plan.md`.

---

## Contrato Oficial De Endpoint Por Modulo

Esta secao congela o endpoint oficial de cada trilha. Qualquer desvio precisa virar decisao arquitetural formal.

1. `ContentModeration`
   - endpoint oficial: `POST /v1/moderations`
   - provider oficial desta fase: `OpenAI`
   - observacao: nao usar `Responses API` como caminho principal desta trilha.

2. `MediaIntelligence`
   - endpoint oficial: `POST /v1/chat/completions`
   - providers aceitos nesta fase:
     - `vLLM`
     - `OpenRouter`
   - observacao: o contrato do app continua sendo `chat/completions` OpenAI-compatible.

3. `FaceSearch`
   - endpoint oficial do MVP: `POST /api/v1/detection/detect`
   - provider oficial do MVP: `CompreFace`
   - observacao: a deteccao deve usar `face_plugins=calculator,landmarks` como trilha principal.

4. `FaceSearch` QA e calibracao
   - endpoint auxiliar oficial: `POST /api/v1/verification/embeddings/verify`
   - uso permitido:
     - smoke local
     - calibracao de threshold
     - debug operacional
   - uso proibido:
     - ranking principal de producao
     - substituicao do `pgvector`

5. busca final do produto
   - nao usa endpoint externo
   - contrato oficial: `PostgreSQL + pgvector` dentro do app Laravel

---

## Contrato De Payload Aceito Pelo App

Esta secao define o payload multimodal aceito pelo app, independentemente do provider.

### 1. ContentModeration

Entrada aceita pelo app:

- 1 imagem por request
- imagem em:
  - `image_url`
  - `data_url`
- texto associado opcional:
  - `caption`
  - `inboundMessage.body_text`

Saida normalizada obrigatoria:

- buckets internos:
  - `nudity`
  - `violence`
  - `self_harm`
- payload granular normalizado do provider
- `raw_response_json`

### 2. MediaIntelligence

Entrada aceita pelo app:

- `messages`
- 1 bloco `text`
- 1 bloco `image_url`
  - `url` publica ou `data_url`
- `response_format.type=json_schema` quando `require_json_output=true`

Campos explicitamente fora do contrato do app nesta fase:

- `image_url.detail`
- multiplas imagens por request
- campos provider-specific nao normalizados

Regra:

se um provider suportar mais do que isso, o excesso nao vira contrato do app automaticamente.

### 3. FaceSearch

Entrada aceita pelo app para provider facial:

- binario da imagem vindo do storage interno
- preferencia por JSON com base64
- multipart apenas como fallback tecnico

Saida minima exigida do detector:

- bounding box
- confidence
- embedding quando `calculator` estiver habilitado
- landmarks quando `landmarks` estiver habilitado
- metadata suficiente para quality gate

---

## Duvidas De Negocio Que Precisam Virar Teste

Estas duvidas nao devem ser resolvidas "na conversa". Elas devem sair como teste de caracterizacao, benchmark ou smoke test.

### BQ-01 Safety `observe_only`

Pergunta:

`event_content_moderation_settings.mode=observe_only` deve:

- apenas registrar a avaliacao e nunca segurar publicacao;
- ou manter comportamento atual de gate.

Estado atual validado e implementado:

- o campo existe em settings e frontend;
- `observe_only` agora persiste a avaliacao de safety, mas nao bloqueia a decisao final;
- em `observe_only`:
  - `review` nao segura publish por safety;
  - `block` nao rejeita por safety;
  - falha tecnica de safety tambem nao bloqueia;
  - se `MediaIntelligence` estiver em `gate`, o pipeline ainda espera o VLM.

### BQ-02 Categorias granulares do provider

Pergunta:

queremos apenas buckets internos (`nudity`, `violence`, `self_harm`) ou tambem um espelho granular normalizado do provider para recalibracao futura.

Decisao recomendada:

- manter buckets internos para negocio;
- adicionar persistencia granular do provider.

### BQ-03 Dimensao e estabilidade do embedding no CompreFace

Pergunta:

qual vetor o provider devolve no modo operacional escolhido:

- 512
- 128
- outro valor por modelo/configuracao.

Decisao recomendada:

- medir no smoke test real;
- travar a dimensao no config e no schema operacional depois da primeira validacao;
- tratar isso como gate de merge do provider real.

### BQ-04 Estrategia de busca `exact` vs `ann`

Pergunta:

qual deve ser o comportamento default por evento e qual e o threshold operacional para mudar para ANN.

Decisao recomendada:

- `exact` como default;
- `ann` so com benchmark e p95 medidos.

### BQ-05 Threshold de matching e quality gate

Pergunta:

qual combinacao minima de:

- `min_face_size_px`
- `min_quality_score`
- `search_threshold`

entrega bom equilibrio entre hit rate e falso positivo.

### BQ-06 Caption vs reply transacional

Pergunta:

quando existir resposta automatica no canal, ela deve:

- reutilizar `caption`;
- ou nascer como `reply_text` separado.

Decisao recomendada:

- manter separado;
- deixar como backlog de produto apos esta fase.

### BQ-07 Politica de resultado em busca publica

Pergunta:

a busca publica continua restrita a:

- `approved + published`

ou o produto quer flexibilizacao.

Decisao recomendada:

- manter `approved + published`;
- tratar qualquer excecao como decisao de negocio formal.

---

## Frentes De Implementacao

## H1 - Hardening De ContentModeration

### H1-T1 - Implementar fallback `image_url -> data URL`

Objetivo:

remover dependencia exclusiva de preview publica acessivel.

Entrega:

1. manter `image_url` como caminho preferencial;
2. ler binario de `fast_preview` ou derivado equivalente do storage quando a URL nao estiver disponivel;
3. enviar `data:image/...;base64,...` como fallback para a OpenAI;
4. registrar no `raw_response_json` e `result_json` qual caminho foi usado:
   - `image_url`
   - `data_url`

Arquivos provaveis de impacto:

- `apps/api/app/Modules/ContentModeration/Services/OpenAiContentModerationProvider.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaAssetUrlService.php`
- `apps/api/tests/Unit/ContentModeration/OpenAiContentModerationProviderTest.php`
- `apps/api/tests/Feature/ContentModeration/ContentModerationPipelineTest.php`

TTD obrigatorio:

1. unit test:
   - usa `image_url` quando preview publica existir;
   - usa `data_url` quando preview publica nao existir;
   - preserva `model_snapshot`, `provider_version` e fallback mode.

2. feature test:
   - pipeline persiste avaliacao mesmo sem preview publica;
   - fallback tecnico continua respeitando `fallback_mode=review|block`.

Criterio de aceite:

- nenhum item de safety falha apenas porque a preview publica nao esta acessivel.

### H1-T2 - Persistir categorias granulares do provider

Objetivo:

separar claramente:

- buckets internos de negocio;
- categorias granulares da OpenAI.

Entrega:

1. manter `category_scores_json` para buckets internos do produto;
2. adicionar colunas especificas para granularidade do provider, por exemplo:
   - `provider_categories_json`
   - `provider_category_scores_json`
   - `provider_category_input_types_json`
3. expor isso no DTO e no model;
4. incluir isso no detalhe da midia para auditoria interna.

Arquivos provaveis de impacto:

- migration nova em `apps/api/database/migrations/`
- `apps/api/app/Modules/ContentModeration/DTOs/ContentSafetyEvaluationResult.php`
- `apps/api/app/Modules/ContentModeration/Models/EventMediaSafetyEvaluation.php`
- `apps/api/app/Modules/ContentModeration/Services/OpenAiContentModerationProvider.php`
- `apps/api/app/Modules/MediaProcessing/Http/Resources/EventMediaDetailResource.php`

TTD obrigatorio:

1. unit test:
   - mapeia buckets internos sem perder o raw granular;
   - persiste `provider_categories_json` e `provider_category_scores_json`.

2. feature test:
   - `latest_safety_evaluation` retorna buckets internos e campos granulares separados.

Criterio de aceite:

- recalibracao futura pode ser feita sem reinterpretar `raw_response_json` bruto em cada analise.
- o app consegue auditar com clareza o que veio de imagem, o que veio de texto e o que ja esta normalizado.

Observacao de implementacao:

alem de `raw_response_json`, faz sentido persistir um `normalized_provider_json` derivado do provider, contendo:

- `categories`
- `category_scores`
- `category_applied_input_types`

Isso evita espalhar parsing do raw em resources, jobs e scripts de calibracao.

### H1-T3 - Caracterizar e decidir `observe_only`

Objetivo:

remover ambiguidade funcional do campo `mode`.

Entrega:

1. criar teste de caracterizacao do comportamento atual;
2. fechar regra de negocio:
   - `enforced` = safety influencia decisao;
   - `observe_only` = safety persiste avaliacao, mas nao bloqueia/segura publish;
3. implementar a regra escolhida no pipeline e na UI.

Arquivos provaveis de impacto:

- `apps/api/app/Modules/ContentModeration/Jobs/AnalyzeContentSafetyJob.php`
- `apps/api/app/Modules/MediaProcessing/Actions/FinalizeMediaDecisionAction.php`
- `apps/api/tests/Feature/ContentModeration/`
- `apps/web/src/modules/events/components/content-moderation/`

TTD obrigatorio:

1. characterization test:
   - documenta comportamento atual.

2. feature tests novos:
   - `observe_only` persiste avaliacao mas nao muda decisao final;
   - `enforced` continua segurando review/block como hoje.

Criterio de aceite:

- o campo `mode` deixa de ser decorativo e passa a ter semantica verificavel.

---

## H2 - FaceSearch MVP Com CompreFace

### H2-T0 - Fechar topologia minima e capacidade operacional do CompreFace

Objetivo:

evitar que o provider facial entre como "SDK no app" sem plano de operacao real.

Entrega:

1. documentar topologia minima por ambiente:
   - local
   - homologacao
   - producao
2. registrar sizing inicial:
   - CPU vs GPU
   - throughput esperado por fila `face-index`
   - latencia alvo por selfie search
3. definir topologia minima de producao:
   - API server
   - embedding servers
   - storage e rede
4. cravar regra operacional:
   - embedding servers sao stateless
   - em producao, planejar pelo menos 2 embedding servers para resiliencia e banda
5. checklist minima recomendada para producao:
   - pelo menos 2 API servers
   - pelo menos 2 embedding servers
   - API servers e embedding servers em maquinas diferentes
   - embedding servers em nos fortes
   - GPU como preferencia para embedding servers
   - fila `face-index` com throughput medido em cima dessa topologia

Arquivos provaveis de impacto:

- `docs/execution-plans/photo-processing-ai-hardening-execution-plan.md`
- `docker/`
- docs operacionais futuras

TTD obrigatorio:

1. checklist operacional:
   - consegue subir stack local;
   - consegue validar health basico;
   - consegue identificar gargalo no embedding server.

Criterio de aceite:

- o provider facial entra no plano com topologia minima e premissas de capacidade, nao como caixa-preta.
- a topologia minima recomendada de producao fica documentada de forma prescritiva e revisavel.

Status de execucao:

- topologia minima documentada com base na arquitetura oficial do CompreFace;
- producao deve planejar pelo menos 2 API servers e 2 embedding servers em maquinas diferentes;
- embedding servers sao tratados como stateless, ponto principal de custo computacional e preferencialmente com GPU;
- rollout real ainda depende de smoke de capacidade e throughput com `face-index`.

### H2-T1 - Criar cliente e config do CompreFace

Objetivo:

introduzir o provider facial real sem acoplamento direto ao dominio.

Entrega:

1. adicionar bloco `compreface` em `apps/api/config/face_search.php`;
2. criar client HTTP fino para `CompreFace`;
3. adicionar env vars locais/producao, por exemplo:
   - `FACE_SEARCH_DETECTION_PROVIDER=compreface`
   - `FACE_SEARCH_EMBEDDING_PROVIDER=compreface`
   - `FACE_SEARCH_COMPRE_FACE_BASE_URL=...`
   - `FACE_SEARCH_COMPRE_FACE_API_KEY=...`
   - `FACE_SEARCH_COMPRE_FACE_FACE_PLUGINS=calculator,landmarks`
   - `FACE_SEARCH_COMPRE_FACE_DET_PROB_THRESHOLD=...`
4. suportar desde o inicio:
   - envio JSON com base64
   - fallback para multipart quando necessario

Arquivos provaveis de impacto:

- `apps/api/config/face_search.php`
- `apps/api/.env.example`
- `deploy/examples/apps-api.env.production.example`
- `apps/api/app/Modules/FaceSearch/Services/`

TTD obrigatorio:

1. unit test do client:
   - monta headers `x-api-key`;
   - usa endpoint esperado;
   - propaga erro util quando provider falhar.

Criterio de aceite:

- provider facial pode ser configurado sem alterar o dominio.

Status de execucao:

- bloco `providers.compreface` adicionado em `apps/api/config/face_search.php`;
- variaveis de ambiente adicionadas em `apps/api/.env.example` e `deploy/examples/apps-api.env.production.example`;
- client HTTP fino criado em `apps/api/app/Modules/FaceSearch/Services/CompreFaceClient.php`;
- client usa `POST /api/v1/detection/detect`;
- caminho principal base64 usa `Content-Type: application/json` e payload `{"file": "<base64>"}`, conforme regra oficial do CompreFace;
- fallback tecnico multipart tambem existe no client;
- provider foi liberado posteriormente no manager, request e frontend em `H2-T4`.

### H2-T2 - Implementar detection provider via `detection/detect`

Objetivo:

usar `CompreFace` para deteccao facial real e obter dados suficientes para indexacao.

Entrega:

1. criar `CompreFaceDetectionProvider`;
2. chamar `POST /api/v1/detection/detect` com `face_plugins=calculator,landmarks`;
3. suportar entrada principal via base64/JSON e manter multipart apenas como fallback tecnico;
4. mapear bbox, confidence e sinais auxiliares para `DetectedFaceData`;
5. enriquecer `DetectedFaceData` com `providerPayload` ou estrutura equivalente para evitar segunda chamada desnecessaria no embedder.

Observacao tecnica:

o DTO atual `DetectedFaceData` nao carrega payload do provider. Para usar `calculator` de forma eficiente, vale estender esse DTO com dados opcionais do provider.

Arquivos provaveis de impacto:

- `apps/api/app/Modules/FaceSearch/DTOs/DetectedFaceData.php`
- `apps/api/app/Modules/FaceSearch/Services/CompreFaceDetectionProvider.php`
- `apps/api/app/Modules/FaceSearch/Providers/FaceSearchServiceProvider.php`

TTD obrigatorio:

1. unit test:
   - mapeia bbox corretamente;
   - marca `isPrimaryCandidate` coerentemente;
   - reaproveita payload do `calculator` para embedding quando disponivel.

2. feature test:
   - `IndexMediaFacesJob` deixa de ficar sempre em `skipped` quando provider real estiver habilitado.

Criterio de aceite:

- `detect()` passa a retornar faces reais em imagens validas.

Status de execucao:

- criado `CompreFaceDetectionProvider`;
- mapeamento implementado para:
  - `box.x_min`
  - `box.y_min`
  - `box.x_max`
  - `box.y_max`
  - `box.probability`
  - `landmarks`
  - `embedding` retornado pelo plugin `calculator`
- `DetectedFaceData` agora carrega:
  - `landmarks`
  - `providerEmbedding`
  - `providerPayload`
- `qualityScore` usa `box.probability` como proxy inicial, porque o payload oficial de `detection/detect` nao traz quality score facial separado;
- o provider suporta caminho principal base64 e fallback multipart via `CompreFaceClient`;
- pipeline `IndexMediaFacesJob` validado com `CompreFaceDetectionProvider` e `CompreFaceEmbeddingProvider` via manager global em `H2-T4`;
- provider foi adicionado ao manager global em `H2-T4`.

### H2-T3 - Implementar embedding provider via payload do `calculator`

Objetivo:

gerar embeddings faciais reais sem inventar pipeline paralelo.

Entrega:

1. criar `CompreFaceEmbeddingProvider`;
2. usar embedding retornado pelo `calculator` quando vier na deteccao;
3. nao fazer segunda chamada ao provider quando o `calculator` ja tiver devolvido embedding aproveitavel;
4. se necessario, prever fallback de recalc por endpoint auxiliar;
5. persistir metadados do provider:
   - `provider_version`
   - `model_key`
   - `model_snapshot`
   - dimensao real do vetor

Arquivos provaveis de impacto:

- `apps/api/app/Modules/FaceSearch/Services/CompreFaceEmbeddingProvider.php`
- `apps/api/app/Modules/FaceSearch/DTOs/FaceEmbeddingData.php`
- `apps/api/app/Modules/FaceSearch/Actions/IndexMediaFacesAction.php`

TTD obrigatorio:

1. unit tests:
   - embedding retorna vetor nao-zero;
   - dimensao do vetor e registrada;
   - falha clara quando o provider nao devolver embedding.

2. feature tests:
   - indexing grava `event_media_faces.embedding_model_key`;
   - `SearchFacesBySelfieAction` encontra match real usando provider mockado com vetor consistente.

Criterio de aceite:

- embeddings faciais deixam de ser zerados e passam a sustentar busca real.
- o provider real nao pode ser mergeado sem smoke que congele a dimensao do embedding e confirme compatibilidade com o schema vetorial.

Status de execucao:

- criado `CompreFaceEmbeddingProvider`;
- o provider usa exclusivamente `DetectedFaceData.providerEmbedding`, preenchido pelo plugin `calculator` na deteccao;
- nao existe segunda chamada HTTP ao CompreFace quando o embedding ja veio no payload de deteccao;
- o provider falha com erro claro quando:
  - `calculator` nao retorna embedding;
  - embedding contem valor nao numerico;
  - embedding e vetor zero;
  - dimensao medida diverge de `FACE_SEARCH_EMBEDDING_DIMENSION`;
- `FaceEmbeddingData.rawResponse` registra:
  - `provider`
  - `provider_version`
  - `model_key`
  - `model_snapshot`
  - `embedding_dimension`
  - `provider_payload`
- pipeline `IndexMediaFacesJob` validado com `CompreFaceDetectionProvider` + `CompreFaceEmbeddingProvider` e apenas uma chamada HTTP total;
- busca por selfie validada com `CompreFaceEmbeddingProvider` usando vetor consistente do `calculator`;
- provider foi liberado no manager global/request/frontend em `H2-T4`.

Plano de contingencia obrigatorio:

se a dimensao medida no smoke divergir da coluna vetorial atual:

1. bloquear merge do provider real;
2. abrir trilha obrigatoria de:
   - migration de schema vetorial
   - ajuste de config
   - reindexacao
   - reprocessamento do acervo afetado
3. so liberar rollout depois que schema e dimensao medida estiverem alinhados.

### H2-T4 - Liberar `compreface` em backend e frontend

Objetivo:

remover o bloqueio atual em `noop`.

Entrega:

1. registrar provider no `FaceSearchServiceProvider`;
2. liberar `provider_key=in:noop,compreface` no request backend;
3. liberar `compreface` no form frontend e tipos de API;
4. refletir provider/modelo atual no detalhe do evento.

Arquivos provaveis de impacto:

- `apps/api/app/Modules/FaceSearch/Providers/FaceSearchServiceProvider.php`
- `apps/api/app/Modules/FaceSearch/Http/Requests/UpsertEventFaceSearchSettingsRequest.php`
- `apps/web/src/modules/events/api.ts`
- `apps/web/src/modules/events/components/face-search/EventFaceSearchSettingsForm.tsx`

TTD obrigatorio:

1. feature tests:
   - settings aceitam `compreface`;
   - usuario sem permissao continua proibido.

2. validacao frontend minima:
   - `npm run type-check`;
   - form permite selecionar `CompreFace`;
   - payload serializa provider corretamente.

Criterio de aceite:

- o produto consegue habilitar FaceSearch real por evento.

Status de execucao:

- `compreface` liberado em `UpsertEventFaceSearchSettingsRequest`;
- `FaceDetectionProviderManager` agora resolve:
  - `noop`
  - `compreface`
- `FaceEmbeddingProviderManager` agora resolve:
  - `noop`
  - `compreface`
- formulario web de settings permite selecionar `CompreFace`;
- payload frontend `UpdateEventFaceSearchSettingsPayload` aceita `noop|compreface`;
- types de evento/API foram atualizados para refletir `compreface`;
- pipeline `IndexMediaFacesJob` validado usando o manager global com provider `compreface`;
- busca por selfie continua validada com embedding consistente;
- smoke real do `CompreFace` executado em `2026-04-07` congelou a dimensao operacional do embedding em `512`.

### H2-T5 - Adicionar verificacao por embeddings como ferramenta de QA

Objetivo:

usar a capacidade do provider para calibrar threshold, nao para substituir o ranking do produto.

Entrega:

1. criar utilitario interno opcional de comparacao usando endpoint de verificacao por embeddings;
2. usar isso apenas em:
   - smoke tests locais
   - calibracao de threshold
   - debug operacional

TTD obrigatorio:

1. smoke test manual:
   - compara selfie e crop indexado do mesmo sujeito;
   - compara selfie e crop de sujeito diferente.

Criterio de aceite:

- time consegue calibrar `search_threshold` com referencia auxiliar do provider.

---

## H3 - Estrategia De Busca E Quality Gate

### H3-T1 - Introduzir estrategia operacional `exact|ann`

Objetivo:

tirar a escolha de recall/performance do implicito.

Entrega:

1. adicionar `search_strategy` em `EventFaceSearchSetting`:
   - `exact`
   - `ann`
2. criar config global default;
3. ajustar `PgvectorFaceVectorStore` para respeitar a estrategia;
4. em `ann`, controlar explicitamente os knobs de sessao:
   - `hnsw.ef_search`
   - `hnsw.iterative_scan`
5. documentar a estrategia de filtro por `event_id`:
   - index da coluna de filtro
   - avaliar partial index quando houver poucos valores distintos
   - avaliar partitioning quando houver muitos valores distintos
6. tratar o indice `HNSW` como acelerador opcional, nao como premissa do produto.

Observacao tecnica:

como a migration atual ja cria HNSW, a implementacao deve incluir uma spike tecnica para garantir que `exact` continue realmente exato.

Arquivos provaveis de impacto:

- migration nova em `apps/api/database/migrations/`
- `apps/api/app/Modules/FaceSearch/Models/EventFaceSearchSetting.php`
- `apps/api/app/Modules/FaceSearch/Services/PgvectorFaceVectorStore.php`
- frontend de settings de `FaceSearch`

Status de execucao:

- `H3-T1a` implementado em `2026-04-07`.
- `search_strategy` foi adicionado ao schema, defaults, request, resource, action de upsert, API de eventos e formulario web.
- `SearchFacesBySelfieAction` repassa a estrategia do evento para o `FaceVectorStoreInterface`.
- `PgvectorFaceVectorStore` aplica `exact` com CTE materializada filtrada por `event_id`, evitando HNSW no ranking final e preservando o filtro operacional do produto.
- `PgvectorFaceVectorStore` aplica `ann` com `hnsw.ef_search` e `hnsw.iterative_scan` configuraveis.
- `H3-T1b1` implementado em `2026-04-07`.
- comando `php artisan face-search:benchmark` foi criado para ler um smoke report real e emitir benchmark estruturado por `search_strategy`;
- `H3-T1b` executado em `2026-04-07` usando `C:\laragon\www\eventovivo\apps\api\storage\app\face-search-smoke\20260407-193247-compreface-real-run.json`;
- resultado atual do benchmark local:
  - `exact`:
    - `top_1_hit_rate=0.2857`
    - `top_5_hit_rate=0.5714`
    - `p95_search_ms=15.32`
  - `ann`:
    - `top_1_hit_rate=0.2857`
    - `top_5_hit_rate=0.5714`
    - `p95_search_ms=135.21`
- decisao operacional reforcada pelo dado real:
  - `exact` continua sendo o default recomendado;
  - `ann` nao trouxe ganho de recall neste dataset local e ainda foi mais lento neste ambiente.
- recalibracao executada em `2026-04-07` apos fixar `target_face_selection` no manifesto local para fotos multi-face;
- novo baseline local com `search_threshold=0.5` e smoke report `C:\laragon\www\eventovivo\apps\api\storage\app\face-search-smoke\20260407-195052-compreface-real-run.json`:
  - `exact`:
    - `top_1_hit_rate=1.0`
    - `top_5_hit_rate=1.0`
    - `false_positive_top_1_rate=0.0`
    - `p95_search_ms=11.55`
  - `ann`:
    - `top_1_hit_rate=1.0`
    - `top_5_hit_rate=1.0`
    - `false_positive_top_1_rate=0.0`
    - `p95_search_ms=55.89`
- decisao calibrada desta frente:
  - `search_threshold` default passa a `0.5`;
  - `exact` segue como default operacional porque manteve o mesmo recall de `ann` com latencia menor;
  - `ann` continua opcional para volume futuro, nao para o baseline atual.

TTD obrigatorio:

1. unit test:
   - `exact` e `ann` seguem caminhos distintos.

2. benchmark automatizado:
   - compara top-1/top-5 e p95 entre `exact` e `ann`.

Criterio de aceite:

- a estrategia de busca deixa de depender do planner e passa a ser configuravel e mensuravel.
- `ann` nasce com knobs observaveis e auditaveis, nao como comportamento implicito.
- `H3-T1a` fica aceito quando `search_strategy` existir em schema/config/settings/API/frontend, a busca por selfie repassar a estrategia ao vector store, e `PgvectorFaceVectorStore` aplicar `exact` ou `ann` no caminho PostgreSQL.
- `H3-T1b` fica aceito somente quando houver benchmark reproduzivel com dataset real de `H5-T3b`.

Caso de referencia obrigatorio:

o benchmark principal desta frente deve ser o caso real do produto:

- busca filtrada por `event_id`
- respeitando `searchable`
- respeitando threshold do evento

Busca vetorial generica sem filtro pode existir como apoio tecnico, mas nao define default operacional.

### H3-T2 - Transformar quality gate em tiers operacionais

Objetivo:

evoluir de um booleano simples para uma linguagem de negocio observavel.

Entrega:

1. introduzir tiers conceituais:
   - `reject`
   - `index_only`
   - `search_priority`
2. manter compatibilidade inicial com `min_face_size_px` e `min_quality_score`;
3. guardar motivo principal de rejeicao da face/selfie.

Arquivos provaveis de impacto:

- `apps/api/app/Modules/FaceSearch/Services/FaceQualityGateService.php`
- `apps/api/app/Modules/FaceSearch/Actions/IndexMediaFacesAction.php`
- `apps/api/app/Modules/FaceSearch/Actions/SearchFacesBySelfieAction.php`

TTD obrigatorio:

1. unit tests:
   - selfie ruim vira `reject`;
   - face util mas fraca vira `index_only`;
   - face boa vira `search_priority`.

2. feature tests:
   - faces `reject` nao viram embedding;
   - faces `index_only` nao dominam ranking.

Criterio de aceite:

- time consegue explicar por que uma face entrou ou nao entrou no indice.

Status de execucao:

- `H3-T2` implementado em `2026-04-07`.
- `FaceQualityGateService` agora classifica cada face em:
  - `reject`
  - `index_only`
  - `search_priority`
- `event_media_faces` agora persiste `quality_tier` e `quality_rejection_reason`;
- `event_face_search_requests` agora persiste `query_face_quality_tier` e `query_face_rejection_reason`;
- `CollapseFaceSearchMatchesQuery` e `SearchFacesBySelfieAction` agora preferem `search_priority` sobre `index_only` quando a distancia empata;
- faces `reject` continuam fora do embedding/indexacao;
- faces `index_only` continuam elegiveis ao indice, mas deixam de dominar ranking por desempate.

### H3-T3 - Observabilidade e benchmark de FaceSearch

Objetivo:

medir robustez real do produto e nao apenas "funciona no happy path".

Entrega:

1. criar relatorio/command de benchmark local, por exemplo:
   - `php artisan face-search:benchmark`
2. separar duas familias de metricas:
   - funcionais
   - operacionais
3. medir por dataset:

Metricas funcionais:

- top-1 hit rate
- top-5 hit rate
- taxa de selfie rejeitada
- taxa de falso positivo percebido

Metricas operacionais:

- p95 de busca
- latencia de detect
- latencia de embed
- throughput da fila `face-index`
- saturacao do provider facial

Saidas minimas do relatorio:

- top-1 hit rate
- top-5 hit rate
- p95 de busca
- taxa de selfie rejeitada
- p95 de detect
- p95 de embed
- throughput da fila `face-index`
- taxa de faces detectadas que viram embedding
- taxa de falso positivo percebido em revisao
3. emitir relatorio por:
   - `search_strategy`
   - threshold
   - quality tier

Arquivos provaveis de impacto:

- novo comando em `apps/api/app/Modules/FaceSearch/Console/Commands/`
- service novo em `apps/api/app/Modules/FaceSearch/Services/`
- docs de operacao

TTD obrigatorio:

1. teste do comando:
   - gera resumo estruturado;
   - falha com mensagem clara sem dataset/config.

Criterio de aceite:

- o time consegue tomar decisoes de threshold e estrategia com base em numeros, nao intuicao.

Pre-condicao obrigatoria:

- este benchmark so entra depois de `H5-T3`, porque sem dataset curado a medicao vira observacao ad-hoc e nao regressao reproduzivel.

Status de execucao:

- `php artisan face-search:benchmark` implementado em `2026-04-07`;
- o comando consome um smoke report real como fonte de embeddings benchmarkaveis;
- o comando gera relatorio estruturado com:
  - `top_1_hit_rate`
  - `top_5_hit_rate`
  - `p95_search_ms`
  - `p95_detect_ms`
  - `p95_embed_ms`
  - `false_positive_top_1_rate`
- a execucao real foi concluida em `2026-04-07`;
- smoke report real gerado em:
  - `C:\laragon\www\eventovivo\apps\api\storage\app\face-search-smoke\20260407-193247-compreface-real-run.json`
- benchmark report real gerado em:
  - `C:\laragon\www\eventovivo\apps\api\storage\app\face-search-benchmark\20260407-193356-face-search-benchmark.json`
- observabilidade local obtida nesta rodada:
  - `embedding_dimension=512`
  - `positive_similarity(person-a)=0.99849`
  - `positive_similarity(person-b)=0.98539`
  - `negative_similarity(person-a vs person-b)=0.42967`
  - `p95_detect_ms=781`
  - `p95_embed_ms=3559`
  - `selfie_rejection_rate=0`
- conclusao operacional desta rodada:
  - a integracao real do `CompreFace` ficou homologada localmente;
  - as metas minimas da fase ainda nao foram atingidas no dataset local, principalmente em `top-1`, `top-5` e `p95_embed_ms`;
  - o proximo trabalho desta frente deixa de ser integracao e passa a ser calibracao de threshold, ranking e ampliacao do dataset curado.
- sweep exploratorio de threshold com `exact` mostrou o trade-off atual:
  - `threshold=0.5`:
    - `top_1_hit_rate=0.4286`
    - `top_5_hit_rate=0.7143`
    - `false_positive_top_1_rate=0.4286`
  - `threshold=1.0`:
    - `top_1_hit_rate=0.4286`
    - `top_5_hit_rate=1.0`
    - `false_positive_top_1_rate=0.5714`
- leitura pratica:
  - o gargalo atual e calibracao de produto, nao integracao do provider;
  - aumentar threshold melhora recall, mas ainda sem resolver `top-1` e piorando falso positivo.
- segunda rodada de benchmark local, apos selecao explicita da face-alvo em fotos multi-face e default `search_threshold=0.5`, mudou o estado desta frente:
  - smoke report real atualizado:
    - `C:\laragon\www\eventovivo\apps\api\storage\app\face-search-smoke\20260407-195052-compreface-real-run.json`
  - benchmark report real atualizado:
    - `C:\laragon\www\eventovivo\apps\api\storage\app\face-search-benchmark\20260407-195423-face-search-benchmark.json`
  - metricas locais atuais:
    - `top_1_hit_rate=1.0`
    - `top_5_hit_rate=1.0`
    - `false_positive_top_1_rate=0.0`
    - `p95_search_ms(exact)=11.55`
    - `p95_search_ms(ann)=55.89`
    - `p95_detect_ms=577`
    - `p95_embed_ms=332`
    - `throughput_face_index_per_minute=12.32`
- diagnostico de latencia local:
  - as 3 deteccoes mais lentas desta rodada foram:
    - `vipsocial-person-a-03`
    - `vipsocial-person-b-02`
    - `vipsocial-person-a-01`
  - os 2 primeiros casos sao justamente cenas multi-face, o que reforca que a latencia do lane cresce mais por custo de deteccao/scene complexity do que pelo embedding em si.
- conclusao operacional atual:
  - o lane facial local passou a atender as metas minimas de `FaceSearch` no dataset consentido atual;
  - o proximo gargalo desta frente passa a ser ampliacao do dataset e medicao com fila real, nao mais integracao ou threshold baseline.
- medicao real do lane `face-index` foi executada depois desta calibracao, com worker subprocesso e fila isolada:
  - relatorio real:
    - `C:\laragon\www\eventovivo\apps\api\storage\app\face-search-lane-throughput\20260407-205310-face-index-lane-throughput.json`
  - ajuste operacional necessario antes de obter esse relatorio:
    - a primeira tentativa com `queue:work` em processo interno nao era confiavel para homologacao;
    - a primeira tentativa com subprocesso isolado saiu com `exit_code=12`, que no Laravel corresponde a `EXIT_MEMORY_LIMIT`;
    - o executor foi alinhado com a memoria do `supervisor-face-index` (`512 MB`) e passou a usar fila dedicada `face-index-benchmark-*`;
  - metricas reais atuais do lane:
    - `jobs_completed=7`
    - `jobs_failed=0`
    - `jobs_skipped_or_missing=0`
    - `throughput_face_index_per_minute=10.06`
    - `p50_run_duration_ms=4000`
    - `p95_run_duration_ms=11000`
  - leitura por `scene_type`:
    - `single_prominent`:
      - `avg_run_duration_ms=2666.67`
      - `avg_ms_per_detected_face=2666.67`
    - `group_four`:
      - `avg_run_duration_ms=11000`
      - `avg_ms_per_detected_face=2750`
    - `group_two`:
      - `avg_run_duration_ms=9000`
      - `avg_ms_per_detected_face=4500`
    - `conversation_group`:
      - `avg_run_duration_ms=7000`
      - `avg_ms_per_detected_face=1166.67`
  - interpretacao objetiva:
    - cenas multi-face continuam concentrando maior latencia total porque geram mais deteccoes, crops e embeddings no mesmo job;
    - a latencia por face nao cresce linearmente com a contagem de faces;
    - neste dataset local, `group_two` ficou pior por face do que `conversation_group`, o que sugere que tamanho do crop, pose e custo de deteccao da cena influenciam mais do que a contagem bruta de rostos isoladamente.

---

## H4 - MediaIntelligence Como Enrichment Opcional

### H4-T0 - Normalizar payload multimodal entre providers

Objetivo:

evitar divergencia silenciosa entre `vllm` e `OpenRouter` por causa de parametros visuais aceitos por um provider e rejeitados por outro.

Entrega:

1. criar normalizador de payload multimodal no modulo;
2. remover ou ignorar campos nao suportados uniformemente, como `image_url.detail` quando aplicavel;
3. garantir que `vllm` e `OpenRouter` recebam contrato comum e previsivel;
4. registrar em teste quais campos sao:
   - suportados por ambos
   - suportados por apenas um
   - proibidos no contrato do app

Arquivos provaveis de impacto:

- `apps/api/app/Modules/MediaIntelligence/Services/`
- `apps/api/tests/Unit/MediaIntelligence/`

TTD obrigatorio:

1. unit tests:
   - payload invalido ou provider-specific e normalizado antes da chamada;
   - `image_url.detail` nao vaza para `vllm`.

Criterio de aceite:

- o contrato multimodal do app deixa de depender de detalhes acidentais de um provider especifico.

Status de execucao:

- `OpenAiCompatibleMultimodalPayloadNormalizer` implementado em `2026-04-07`;
- `OpenAiCompatibleVisualReasoningPayloadFactory` passou a concentrar o contrato comum do payload multimodal;
- o contrato comum agora:
  - mantem apenas `text` e `image_url`;
  - remove `image_url.detail`;
  - remove `uuid` e demais campos provider-specific fora do contrato do app;
  - limita o payload a uma unica imagem por request;
  - preserva `response_format=json_schema` apenas no formato aceito pelo app;
- `VllmVisualReasoningProvider` foi migrado para esse contrato comum;
- teste `OpenAiCompatibleMultimodalPayloadNormalizerTest` cobre a normalizacao do payload;
- teste `VllmVisualReasoningProviderTest` agora prova que `image_url.detail` e `uuid` nao vazam para o provider.

### H4-T1 - Adicionar provider `openrouter` de forma opcional

Objetivo:

permitir roteamento externo apenas para VLM, sem tocar safety ou matching facial.

Entrega:

1. adicionar provider `openrouter` em `media_intelligence.php`;
2. implementar `OpenRouterVisualReasoningProvider`;
3. liberar provider na validacao backend/frontend;
4. manter `vllm` como default;
5. validar configuracao antes de salvar:
   - modelo suporta imagem
   - modelo suporta structured outputs quando `require_json_output=true`

TTD obrigatorio:

1. unit tests:
   - monta `chat/completions` com `response_format`;
   - usa base URL e headers corretos;
   - falha claramente em modelo nao suportado.

2. feature tests:
   - `enrich_only` com OpenRouter nao bloqueia publish;
   - falha no provider respeita `fallback_mode`.

Criterio de aceite:

- `MediaIntelligence` pode trocar de provider sem impacto no resto do pipeline.
- o painel nao salva configuracao de OpenRouter incompativel com imagem ou `json_schema`.

Status de execucao:

- `OpenRouterVisualReasoningProvider` implementado em `2026-04-07` sobre a mesma base OpenAI-compatible do `vllm`;
- `media_intelligence.php` agora possui bloco `providers.openrouter`;
- `.env.example` e `deploy/examples/apps-api.env.production.example` agora expÃµem:
  - `MEDIA_INTELLIGENCE_OPENROUTER_BASE_URL`
  - `MEDIA_INTELLIGENCE_OPENROUTER_API_KEY`
  - `MEDIA_INTELLIGENCE_OPENROUTER_MODEL`
  - `MEDIA_INTELLIGENCE_OPENROUTER_SITE_URL`
  - `MEDIA_INTELLIGENCE_OPENROUTER_APP_NAME`
- `MediaIntelligenceServiceProvider` agora registra:
  - `vllm`
  - `openrouter`
  - `noop`
- `UpsertEventMediaIntelligenceSettingsRequest` agora aceita `provider_key=openrouter`;
- o painel de settings de `MediaIntelligence` agora aceita `OpenRouter`;
- `OpenRouterVisualReasoningProviderTest` cobre:
  - `Bearer token`
  - `HTTP-Referer`
  - `X-Title`
  - parse estruturado da resposta
  - uso do contrato multimodal normalizado;
- `MediaIntelligenceSettingsTest` cobre update com `openrouter`;
- `UpsertEventMediaIntelligenceSettingsActionTest` cobre persistencia com `openrouter`.

### H4-T2 - Travar politica de roteamento

Objetivo:

evitar instabilidade de custo e comportamento.

Entrega:

1. nao expor `openrouter/free` como default no painel;
2. nao expor `openrouter/auto` como default aberto;
3. se `openrouter/auto` for usado, exigir lista restrita de `allowed_models`;
4. documentar claramente:
   - `fixed`
   - `auto-restricted`

TTD obrigatorio:

1. unit/feature tests:
   - configuracao invalida com router aberto gera erro de validacao ou warning operacional.

Criterio de aceite:

- enrichment externo nao nasce como caixa-preta imprevisivel.

Status de execucao:

- `UpsertEventMediaIntelligenceSettingsRequest` rejeita `openrouter/auto` e `openrouter/free` como configuracao salva;
- `OpenRouterModelPolicy` continua exigindo `model_key` fixo no formato `provider/model`;
- `media_intelligence.php` agora possui catalogo local explicito de compatibilidade para modelos homologados do `OpenRouter`;
- nesta fase, a politica operacional foi fechada como:
  - `fixed` = permitido
  - `auto-restricted` = fora do escopo e nao homologado para salvar no painel
- `MediaIntelligenceSettingsTest` agora rejeita tambem modelos fixos nao homologados no catalogo local;
- `OpenRouterModelPolicyTest` cobre:
  - modelo fixo homologado
  - bloqueio de routers abertos
  - bloqueio de modelo sem homologacao de imagem
  - bloqueio de modelo sem suporte a `json_schema`
- smoke real do `OpenRouter` executado em `2026-04-07` com:
  - modelo fixo `openai/gpt-4.1-mini`
  - `path_used=data_url`
  - `structured_output_valid=true`
  - relatorio salvo em:
    - `C:\laragon\www\eventovivo\apps\api\storage\app\media-intelligence-smoke\20260407-215035-openrouter-real-run.json`
- o smoke real confirmou no metadata live do modelo:
  - `input_modalities=image,text,file`
  - `supported_parameters` incluindo `response_format` e `structured_outputs`
- conclusao operacional:
  - esta frente ficou fechada em `fixed-only`;
  - qualquer ampliacao do catalogo do `OpenRouter` passa a depender de novo smoke real e de inclusao explicita no catalogo local.

### H4-T3 - Caracterizar comportamento de caption

Objetivo:

fechar comportamento de produto antes de tocar `reply_text`.

Perguntas a congelar:

1. VLM pode sobrescrever caption humana?
2. caption vazia deve ser preenchida automaticamente sempre?
3. o painel deve mostrar claramente origem:
   - humana
   - VLM

TTD obrigatorio:

1. characterization test:
   - caption humana nao e sobrescrita.

2. feature tests:
   - caption vazia recebe `short_caption`;
   - origem do enriquecimento fica auditavel.

Criterio de aceite:

- negocio para de tratar caption gerada e caption humana como o mesmo tipo de dado.

Status de execucao:

- `EvaluateMediaPromptJob` continua preenchendo `caption` com `short_caption` apenas quando a midia ainda nao tem legenda humana;
- `MediaIntelligencePipelineTest` agora cobre explicitamente:
  - caption vazia recebe `short_caption`
  - caption humana nao e sobrescrita pelo VLM
- `EventMediaDetailResource` agora expoe `caption_source_hint` quando `latestVlmEvaluation` esta carregada;
- `EventMediaListTest` cobre auditabilidade de origem:
  - `caption_source_hint=vlm` quando `caption === latest_vlm_evaluation.short_caption`
  - `caption_source_hint=human` quando a legenda persistida difere da sugestao do VLM
- conclusao operacional:
  - a fase fecha `caption` como enrichment auditavel;
  - `reply_text` segue trilha propria e separada de `caption`.

### H4-T4 - ReplyText aprovado com prompt global + override por evento

Objetivo:

permitir que midias aprovadas recebam uma resposta curta, contextual e com emoji baseada na foto, sem acoplar isso ao campo `caption`.

Decisao de produto:

1. `caption` continua enrichment publico da midia;
2. `reply_text` vira saida transacional separada;
3. `reply_text` so roda quando:
   - `event_media_intelligence_settings.reply_text_enabled=true`;
   - existir prompt efetivo resolvido;
   - a avaliacao VLM retornar `reply_text`;
   - a midia ja estiver publicada ou for publicada depois da avaliacao.

Persistencia em banco:

1. prompt universal:
   - `media_intelligence_global_settings.reply_text_prompt`
2. override por evento:
   - `event_media_intelligence_settings.reply_text_enabled`
   - `event_media_intelligence_settings.reply_prompt_override`
3. resposta gerada:
   - `event_media_vlm_evaluations.reply_text`

Prioridade de prompt:

1. `reply_prompt_override` do evento
2. `reply_text_prompt` global

Comportamento operacional:

1. `OpenAiCompatibleVisualReasoningPayloadFactory` injeta no prompt as instrucoes efetivas de `reply_text` apenas quando o evento habilita a feature;
2. `VisualReasoningResponseSchemaFactory` aceita `reply_text` como propriedade do contrato JSON;
3. `EvaluateMediaPromptAction` persiste `reply_text` no historico VLM;
4. `SendFeedbackOnMediaPublished` e `SendTelegramFeedbackOnMediaPublished` enviam:
   - reacao sempre;
   - `reply_text` apenas quando a ultima avaliacao VLM ja o tiver produzido;
5. `EvaluateMediaPromptJob` tambem tenta enviar o `reply_text` quando a midia ja estava publicada antes do VLM terminar, evitando depender da ordem `publish -> VLM`.

Status de execucao:

- `MediaIntelligenceGlobalSetting` criado como singleton operacional para prompt universal;
- endpoints autenticados adicionados:
  - `GET /api/v1/media-intelligence/global-settings`
  - `PATCH /api/v1/media-intelligence/global-settings`
- `MediaIntelligenceGlobalSettingsTest` cobre:
  - default global
  - update global
  - autorizacao restrita a `super-admin|platform-admin`
- `MediaIntelligenceSettingsTest` e `UpsertEventMediaIntelligenceSettingsActionTest` agora cobrem:
  - `reply_text_enabled`
  - `reply_prompt_override`
- `OpenAiCompatibleVisualReasoningPayloadFactoryTest` cobre:
  - uso do prompt global
  - precedencia do override do evento
- `MediaIntelligencePipelineTest` agora garante que `reply_text` entra em `event_media_vlm_evaluations`
- `EventMediaDetailResource` expoe `latest_vlm_evaluation.reply_text`
- `TelegramFeedbackAutomationTest` cobre o caminho sincrono:
  - midia publicada + `reply_text` existente => reacao + reply
- `WhatsAppEventAutomationTest` cobre o caminho assincrono:
  - midia publicada primeiro
  - `reply_text` chega depois via `EvaluateMediaPromptJob`
  - so o reply faltante e enviado, sem duplicar a reacao
- `SettingsPage.test.tsx` cobre a edicao do prompt global no painel para `platform-admin`
- `EventMediaIntelligenceSettingsForm.test.tsx` cobre:
  - `reply_text_enabled`
  - limpeza de `reply_prompt_override` para `null`
  - preservacao do payload completo no submit
- `SettingsPage` agora expoe a aba `IA` para `super-admin|platform-admin`, com o prompt global auditavel e editavel
- `EventMediaIntelligenceSettingsForm` agora expoe:
  - chave de habilitacao do `reply_text`
  - `reply_prompt_override` do evento
  - hint explicito de heranca do prompt global

Conclusao operacional:

- `reply_text` aprovado deixou de ser backlog e passou a integrar a fase atual;
- `caption` e `reply_text` agora tem contratos separados e auditaveis.

---

## H5 - Segredos, Smoke Tests Reais E Validacao De Campo

### H5-T1 - Padrao de segredos locais

Objetivo:

permitir smoke tests reais sem vazar segredos no repositorio.

Regra:

1. nenhuma chave real deve entrar em:
   - `docs/`
   - `.env.example`
   - codigo versionado
2. smoke tests reais usam apenas `.env` local ou secret manager de ambiente.

Variaveis esperadas para smoke local:

- `OPENAI_API_KEY`
- `OPENAI_PROJECT`
- `MEDIA_INTELLIGENCE_OPENROUTER_API_KEY`
- `MEDIA_INTELLIGENCE_OPENROUTER_BASE_URL`
- `FACE_SEARCH_COMPRE_FACE_BASE_URL`
- `FACE_SEARCH_COMPRE_FACE_DETECTION_API_KEY`
- `FACE_SEARCH_COMPRE_FACE_VERIFICATION_API_KEY`

Fallback aceito nesta fase:

- `FACE_SEARCH_COMPRE_FACE_API_KEY` pode ser usado como chave generica enquanto detection e verification ainda nao estiverem separados.

Observacao:

qualquer chave exposta em chat deve ser tratada como comprometida e rotacionada antes de uso.

Observacao operacional:

- `FACE_SEARCH_COMPRE_FACE_BASE_URL` precisa apontar para a instancia real do `CompreFace`; nao pode apontar para o servidor web do Evento Vivo se ele estiver ocupando a mesma porta local.
- o `.env` local desta maquina ja foi preparado com:
  - `VIPSOCIAL_DATASET_ROOT`
  - `FACE_SEARCH_COMPRE_FACE_BASE_URL`
  - `FACE_SEARCH_COMPRE_FACE_DETECTION_API_KEY`
  - `FACE_SEARCH_COMPRE_FACE_VERIFICATION_API_KEY`
- os valores de base URL e API keys continuam dependentes da criacao real dos services na UI do `CompreFace`.

### H5-T2 - Smoke tests reais por provider

Objetivo:

ter um conjunto pequeno de testes manuais/semiautomatizados para validar integracoes reais fora dos mocks.

Smoke OpenAI:

1. moderation com URL publica acessivel;
2. moderation com data URL/base64;
3. moderation com fallback tecnico e `fallback_mode=review`.

Smoke OpenRouter:

1. `chat/completions` com imagem e `json_schema`;
2. validacao de modelo fixo;
3. erro claro para `openrouter/free` ou `openrouter/auto` fora da politica definida.

Smoke CompreFace:

1. `detection/detect` com `face_plugins=calculator,landmarks`;
2. confirmar dimensao do embedding;
3. confirmar match positivo e negativo em verificacao auxiliar;
4. confirmar comportamento com base64.

Precondicao operacional:

- `detection/detect` usa a chave do service de detection;
- `verification/embeddings/verify` usa a chave do service de verification;
- o fallback para chave generica continua aceito localmente, mas nao e o rollout recomendado.

Status de execucao:

- comando `php artisan content-moderation:smoke-openai` implementado com TDD;
- smoke command da OpenAI salva relatorio tanto em sucesso quanto em falha;
- o smoke real da OpenAI foi reexecutado com sucesso em `2026-04-07` contra `tests/Fixtures/AI/local/vipsocial.manifest.json`, forÃ§ando `data_url`;
- o smoke real da OpenAI confirmou:
  - `POST /v1/moderations` funcionando com `omni-moderation-latest`
  - `path_used=data_url`
  - `fallback_triggered=true`
  - `decision=pass`
  - `provider_flagged=false`
  - `latency_ms=3942`
  - `provider_response_model=omni-moderation-latest`
- relatorio de sucesso salvo em:
  - `C:\laragon\www\eventovivo\apps\api\storage\app\content-moderation-smoke\20260407-232634-openai-real-run.json`
- o smoke real da OpenAI foi executado em `2026-04-07` contra `tests/Fixtures/AI/local/vipsocial.manifest.json`, forÃ§ando `data_url`;
- comando `php artisan face-search:smoke-compreface` implementado;
- `dry-run` real executado com sucesso contra `tests/Fixtures/AI/local/vipsocial.manifest.json`;
- stack Docker local do `CompreFace` foi subida em `http://127.0.0.1:8002`;
- UI validada com `HTTP 200`;
- `compreface-core` estabilizou em `healthy` apos warm-up local;
- as `x-api-key` reais dos services `detection` e `verification` foram configuradas no `.env` local;
- smoke real executado com sucesso em `2026-04-07` contra o manifesto `vipsocial`;
- o smoke real confirmou:
  - `POST /api/v1/detection/detect` funcionando com `face_plugins=calculator,landmarks`
  - `embedding_dimension=512`
  - `plugins_versions.detector=facenet.FaceDetector`
  - `plugins_versions.calculator=facenet.Calculator`
  - verificacao auxiliar positiva:
    - `person-a similarity=0.99849`
    - `person-b similarity=0.98539`
  - verificacao auxiliar negativa:
    - `person-a vs person-b similarity=0.42967`
- relatorio salvo em:
  - `C:\laragon\www\eventovivo\apps\api\storage\app\face-search-smoke\20260407-193247-compreface-real-run.json`
- smoke real do `OpenRouter` executado com sucesso em `2026-04-07` sobre o manifesto `vipsocial`, usando `data_url` e modelo fixo homologado;
- o smoke real do `OpenRouter` confirmou:
  - `POST /api/v1/chat/completions` funcionando com `response_format=json_schema`
  - `path_used=data_url`
  - `structured_output_valid=true`
  - `provider_response_model=openai/gpt-4.1-mini-2025-04-14`
  - metadata live do modelo com suporte a:
    - `image`
    - `response_format`
    - `structured_outputs`
- relatorio salvo em:
  - `C:\laragon\www\eventovivo\apps\api\storage\app\media-intelligence-smoke\20260407-215035-openrouter-real-run.json`

Leitura operacional da OpenAI:

- a integracao do app com `POST /v1/moderations` ficou provada por testes automatizados e por smoke real bem-sucedido;
- o caminho de fallback `image_url -> data_url` tambem ficou provado em execucao real;
- a frente de `ContentModeration` deixa de ter bloqueio externo nesta fase.

Artefatos obrigatorios de cada smoke:

- `provider`
- `model`
- `provider_version`
- `path_used=image_url|data_url|base64`
- `embedding_dimension`
- `top_match_distance` ou `similarity`
- `threshold_tested`
- `latency_ms`
- `fallback_triggered=true|false`
- `request_outcome=success|failed|degraded`

### H5-T3 - Dataset curado para validacao de negocio

Objetivo:

tirar a validacao do "teste solto" e levar para um dataset repetivel.

Conjuntos minimos:

1. `safety-safe`
   - fotos benignas de grupo, festa, retrato.

2. `safety-review-block`
   - amostras internas/controladas para validar review/block e threshold.

3. `face-search-positive`
   - mesma pessoa em multiplas fotos do mesmo evento.

4. `face-search-negative`
   - pessoas diferentes com aparencia parcialmente parecida.

5. `face-search-low-quality`
   - borrada
   - escura
   - ocluida
   - perfil extremo
   - rosto muito pequeno

6. `cross-event-isolation`
   - mesma pessoa em eventos diferentes
   - pessoas diferentes em eventos diferentes

Entrega complementar:

1. versionar fixtures anonimizadas em:
   - `apps/api/tests/Fixtures/AI/`
2. adicionar `README.md` curto na pasta:
   - origem
   - criterio de anonimizacao
   - finalidade do conjunto
3. exigir manifesto minimo por fixture ou grupo:
   - `event_id`
   - `person_id`
   - `expected_positive_set`
   - `quality_label`
   - `is_public_search_eligible`
   - `expected_moderation_bucket`
4. permitir que benchmark e smoke usem exatamente o mesmo dataset curado.

Criterio:

o benchmark e os smoke tests precisam rodar sempre sobre o mesmo conjunto curado.

Status de execucao:

- criado `apps/api/tests/Fixtures/AI/README.md`;
- criado `apps/api/tests/Fixtures/AI/manifest.json`;
- criado `apps/api/tests/Fixtures/AI/local/README.md`;
- criado `apps/api/tests/Fixtures/AI/local/vipsocial.manifest.json`;
- criadas as pastas obrigatorias:
  - `safety-safe`
  - `safety-review-block`
  - `face-search-positive`
  - `face-search-negative`
  - `face-search-low-quality`
- `cross-event-isolation`
- criado teste `FaceSearchCuratedDatasetManifestTest`;
- criado teste `FaceSearchLocalDatasetManifestTest`;
- o manifesto valida grupos obrigatorios, campos minimos, privacidade e extensoes permitidas quando fixtures reais forem adicionadas;
- status atual do dataset: contrato pronto e acervo local consentido conectado ao manifesto;
- o acervo local consentido agora tem manifesto rastreavel fora do repositorio, com `event_id`, `person_id`, `expected_positive_set` e `quality_label`;
- o manifesto local agora tambem fixa:
  - `scene_type`
  - `target_face_selection`
- 2 imagens do acervo local excediam o limite de 5 MB do `CompreFace` e receberam derivado local <= 5 MB:
  - `55160408924_b566098f08_o.jpg`
  - `55160539330_600b6f30cc_o.jpg`
- os derivados foram ligados ao manifesto local via `smoke_relative_path`;
- o dataset local consentido agora pode ser usado tanto pelo smoke quanto pelo benchmark local;
- a calibracao local mostrou que fotos multi-face precisam de selecao explicita da face-alvo; caso contrario o benchmark pode medir embedding da pessoa errada e derrubar recall artificialmente;
- a fase `H3` de benchmark `exact|ann` nao deve ser considerada valida ate que o manifesto seja populado com imagens anonimizadas ou consentidas reais.

## Backlog Operacional Imediato

### B1 - Backfill assÃ­ncrono via OpenAI Batch API

Objetivo:

preparar reprocessamento economico de acervo sem contaminar o caminho quente do upload.

Escopo:

1. safety em lote via `/v1/moderations`;
2. enrichment em lote via `/v1/chat/completions`;
3. uso apenas para:
   - reclassificacao
   - backfill de captions
   - recalibracao historica

Regra:

- nao entra no upload hot path desta fase;
- usar apenas para reprocessamento offline;
- em multimodal, preferir `image_url` remoto no `.jsonl` sempre que possivel, evitando `base64` pesado;
- entra como backlog operacional logo apos H1 e H4 estabilizarem.

---

## Matriz De Falhas Por Provider

Esta secao congela o comportamento esperado do app diante das falhas mais importantes.

### ContentModeration / OpenAI

1. preview publica indisponivel
   - comportamento esperado: tentar `data_url`
   - se falhar: aplicar `fallback_mode`

2. timeout ou indisponibilidade total
   - comportamento esperado: marcar `failed` ou `block` conforme `fallback_mode`
   - registrar erro e request context

3. `429`
   - comportamento esperado: respeitar throttling/circuit breaker e aplicar fallback do lane

4. payload invalido do provider
   - comportamento esperado: falha tecnica, nao reinterpretacao silenciosa

### MediaIntelligence / vLLM ou OpenRouter

1. modelo incompativel com imagem
   - comportamento esperado: bloquear configuracao no painel

2. modelo incompativel com `json_schema`
   - comportamento esperado: bloquear configuracao quando `require_json_output=true`

3. structured output invalido
   - comportamento esperado: falha tecnica clara e respeito ao `fallback_mode`

4. parametro visual fora do contrato do app
   - comportamento esperado: normalizar ou remover antes da chamada

### FaceSearch / CompreFace

1. provider nao retorna embedding no `calculator`
   - comportamento esperado: falhar indexacao da face com erro claro

2. dimensao do embedding diverge do schema
   - comportamento esperado: bloquear merge e acionar trilha de migration + reindex + reprocess

3. indisponibilidade total do provider
   - comportamento esperado: `face_index_status=failed` para o item ou pausa operacional por lane

4. busca publica precisa ser desligada
   - comportamento esperado: desabilitar `allow_public_selfie_search` sem desligar indexacao interna

---

## Metas Minimas De Aprovacao Da Fase

Os testes nao bastam sozinhos. Esta fase so e considerada aprovada se os numeros minimos abaixo forem atingidos no dataset curado da fase:

1. `FaceSearch`
   - top-1 hit rate minimo: `>= 0.85`
   - top-5 hit rate minimo: `>= 0.95`
   - p95 de busca por `event_id`: `<= 500 ms`
   - p95 de detect no hardware de referencia: `<= 1500 ms`
   - p95 de embed no hardware de referencia: `<= 1000 ms`
   - taxa de selfie rejeitada por quality gate: `<= 20%` no dataset curado principal

2. `ContentModeration`
   - taxa de falha tecnica por provider no smoke controlado: `<= 2%`
   - sucesso em ambos os caminhos:
     - `image_url`
     - `data_url`

3. `MediaIntelligence`
   - structured output valido no smoke do modelo homologado: `>= 95%`

4. capacidade
   - throughput da fila `face-index` precisa ser medido e registrado no relatorio de homologacao
   - saturacao do provider facial precisa ter baseline documentada antes do rollout

Observacao:

esses numeros podem ser recalibrados depois do primeiro ciclo de campo, mas precisam existir ja nesta fase para impedir que "pronto" signifique apenas "compila e passa teste".

---

## Rollback Path Do FaceSearch Real

Como `FaceSearch` e a principal mudanca desta fase, o rollback precisa ficar documentado.

1. rollback por evento
   - `enabled=false` ou `provider_key=noop` no evento

2. rollback operacional do lane
   - `OPS_DEGRADE_FACE_INDEX_ENABLED=false`

3. desligar apenas busca publica
   - `allow_public_selfie_search=false`

4. embeddings ja persistidos
   - permanecem armazenados
   - nao precisam ser apagados no rollback inicial
   - cleanup fisico vira acao operacional separada, se necessario

5. regra de produto
   - rollback publico nao deve interromper indexacao interna quando o objetivo for apenas reduzir exposicao externa

---

## Merge Blockers Por Frente

Esta secao transforma a ordem do plano em regra executiva de merge.

1. `H1-T1`
   - nao mergeia sem provar `image_url` e `data_url`

2. `H1-T2`
   - nao mergeia sem persistencia granular e payload normalizado do provider

3. `H2-T3`
   - nao mergeia sem smoke que congele a dimensao do embedding

4. `H3-T1b` e `H3-T3`
   - nao mergeiam sem `H5-T3b` populado com imagens anonimizadas/consentidas reais

5. `H4-T1`
   - nao mergeia sem matriz de compatibilidade do modelo:
     - suporta imagem
     - suporta structured outputs quando exigido

6. `H5-T2`
   - nao fecha a fase sem relatorio de smoke com os artefatos obrigatorios

---

## Bateria De Testes Da Fase

## 1. Characterization tests

Servem para congelar comportamento atual antes de alterar regra.

Arquivos sugeridos:

- `apps/api/tests/Feature/ContentModeration/ContentModerationObserveOnlyTest.php`
- `apps/api/tests/Feature/MediaIntelligence/MediaCaptionOwnershipTest.php`
- `apps/api/tests/Feature/FaceSearch/FaceSearchStrategyCharacterizationTest.php`

## 2. Unit tests

Servem para validar adapters e mapeamentos.

Arquivos sugeridos:

- `apps/api/tests/Unit/ContentModeration/OpenAiContentModerationProviderTest.php`
- `apps/api/tests/Unit/FaceSearch/CompreFaceDetectionProviderTest.php`
- `apps/api/tests/Unit/FaceSearch/CompreFaceEmbeddingProviderTest.php`
- `apps/api/tests/Unit/MediaIntelligence/OpenRouterVisualReasoningProviderTest.php`

## 3. Feature tests

Servem para validar pipeline e endpoints administrativos/publicos.

Arquivos sugeridos:

- `apps/api/tests/Feature/ContentModeration/ContentModerationPipelineTest.php`
- `apps/api/tests/Feature/FaceSearch/FaceIndexingPipelineTest.php`
- `apps/api/tests/Feature/FaceSearch/FaceSearchSelfieEndpointsTest.php`
- `apps/api/tests/Feature/MediaIntelligence/MediaIntelligencePipelineTest.php`
- `apps/api/tests/Feature/MediaProcessing/MediaReprocessTest.php`

## 4. Benchmark tests

Servem para responder duvidas de negocio com numero.

Saidas minimas:

- top-1 hit rate
- top-5 hit rate
- p95 de busca
- taxa de selfie rejeitada
- taxa de falso positivo percebido
- comparativo `exact` vs `ann`

## 5. Smoke tests reais locais

Servem para validar provider real fora do ambiente de mock.

Importante:

- nao entram no CI;
- usam chaves locais rotacionadas;
- geram relatorio curto no final.

---

## Ordem Recomendada De Entrega

### Fase 1

1. `H5-T1` segredos locais
2. `H1-T1` fallback base64 em safety
3. `H1-T2` categorias granulares do provider
4. `H1-T3` fechar `observe_only`

Motivo:

essa fase protege credenciais, endurece o que ja esta pronto e remove ambiguidade de safety.

### Fase 2

1. `H2-T0` capacidade/topologia do CompreFace
2. `H2-T1` config/client do CompreFace
3. `H2-T2` detection provider
4. `H2-T3` embedding provider
5. `H2-T4` liberar `compreface` em backend/frontend

Motivo:

essa e a fase que coloca `FaceSearch` real no ar sem pular o desenho operacional do provider.

### Fase 3

1. `H5-T3a` contrato do dataset curado
2. `H3-T1a` estrategia configuravel `exact|ann`
3. `H5-T3b` imagens anonimizadas/consentidas reais
4. `H3-T1b` benchmark comparativo `exact|ann`
5. `H3-T2` quality tiers
6. `H3-T3` benchmark e observabilidade

Motivo:

essa fase permite implementar o contrato operacional sem esperar imagens reais, mas mantem benchmark e tuning bloqueados ate existirem dados repetiveis e nao casos soltos.

### Fase 4

1. `H4-T0` compatibilidade de payload multimodal
2. `H4-T1` provider opcional OpenRouter
3. `H4-T2` politica de roteamento
4. `H4-T3` caption ownership

Motivo:

essa fase melhora enrichment sem contaminar o nucleo do matching.

### Fase 5

1. `H5-T2` smoke tests reais finais
2. `B1` backlog operacional de Batch API

Motivo:

essa fase fecha validacao de campo e deixa o proximo passo operacional preparado.

---

## Definicao De Pronto Desta Fase

Esta fase so pode ser considerada pronta quando:

1. `ContentModeration` funciona com `image_url` e com fallback base64;
2. safety persiste buckets internos e granularidade do provider;
3. `observe_only` tem semantica testada;
4. `FaceSearch` aceita `compreface` no backend e no frontend;
5. `IndexMediaFacesJob` e `SearchFacesBySelfieAction` usam embedding facial real;
6. a busca final continua no `pgvector` do proprio app;
7. a dimensao real do embedding do provider foi medida e congelada;
8. existe estrategia explicita `exact|ann`;
9. `ann` tem knobs observaveis para `hnsw.ef_search` e `hnsw.iterative_scan`;
10. existe dataset curado versionado para benchmark e smoke;
11. existe benchmark reproduzivel com hit rate e p95;
12. as metas minimas de aprovacao desta fase foram atingidas;
13. `MediaIntelligence` continua opcional e assincrono;
14. comandos e relatorios de smoke existem para OpenAI, OpenRouter e CompreFace;
15. nenhuma chave real foi comitada em arquivo versionado.

---

## Proxima Execucao Recomendada

Com o estado atual desta branch, a ordem util de continuidade e:

1. ampliar o dataset curado com mais pares positivos/negativos e mais variacao de pose/oclusao
2. repetir `php artisan face-search:lane-throughput` com lote maior ou hardware de homologacao para confirmar capacidade fora do dataset minimo atual
3. manter `exact` como default e reavaliar `ann` so quando o volume crescer
4. reexecutar `php artisan face-search:smoke-compreface` sempre que o dataset local mudar
5. reexecutar `php artisan face-search:benchmark --smoke-report=...` apos cada ampliacao material do dataset
6. repetir `php artisan media-intelligence:smoke-openrouter --entry-id=...` sempre que um novo modelo for candidato a homologacao
7. ampliar o catalogo local do `OpenRouter` apenas depois de smoke real e compatibilidade confirmada
8. consolidar a calibracao de `reply_text` em homologacao com prompts globais e overrides de evento reais
9. decidir se a UI global de `reply_text` deve permanecer em `Settings` ou ganhar uma tela dedicada de IA no painel
10. ampliar o dataset curado de `FaceSearch` quando o acervo adicional estiver pronto

Isso responde primeiro:

- consolidacao do baseline facial agora que a integracao e a calibracao inicial ficaram homologadas;
- ampliacao de cobertura do dataset antes de abrir novas frentes semanticas;
- throughput real agora ja medido, mas ainda dependente de lote maior para virar baseline de homologacao;
- camada semantica agora ja tem contrato comum, provider `openrouter`, smoke real homologado, ownership de caption fechado e `reply_text` aprovado operacionalmente separado de `caption`;
- `ContentModeration` ja teve smoke real bem-sucedido na OpenAI e deixou de ser o bloqueio externo da fase.

Para a homologacao operacional em evento real com WhatsApp grupo/DM, moderacao por IA, `reply_text` em thread, logs ampliados e checklist de execucao, seguir a doc dedicada:

- `docs/execution-plans/whatsapp-ai-media-reply-real-validation-execution-plan.md`

Para a evolucao da area dedicada de IA, presets, teste do prompt com ate `3` imagens, historico de testes e write set de front/backend/banco dessa frente, seguir tambem:

- `docs/execution-plans/ia-respostas-automaticas-de-midia-execution-plan.md`
