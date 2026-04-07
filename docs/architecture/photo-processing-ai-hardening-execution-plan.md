# Photo Processing AI Hardening Execution Plan

## Objetivo

Este documento transforma a validacao atual de IA de fotos em um plano de implementacao executavel para a proxima fase do produto.

Referencia primaria:

- `docs/architecture/photo-processing-ai-status-validation.md`

Referencias complementares:

- `docs/architecture/photo-processing-ai-architecture.md`
- `docs/architecture/photo-processing-ai-execution-plan.md`

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
- [ ] `H5-T3b` imagens anonimizadas/consentidas reais ainda pendentes para benchmark e smoke.

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
- `EventFaceSearchSettingsForm.test.tsx`
- `npm run type-check`
- `FaceSearchCuratedDatasetManifestTest`

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
4. plugar `reply_text` transacional em WhatsApp/Telegram;
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

- `docs/architecture/photo-processing-ai-hardening-execution-plan.md`
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
- ainda falta smoke real com instancia CompreFace para congelar a dimensao final do embedding antes do rollout.

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

tirar a escolha de recall/performance do implcito.

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

TTD obrigatorio:

1. unit test:
   - `exact` e `ann` seguem caminhos distintos.

2. benchmark automatizado:
   - compara top-1/top-5 e p95 entre `exact` e `ann`.

Criterio de aceite:

- a estrategia de busca deixa de depender do planner e passa a ser configuravel e mensuravel.
- `ann` nasce com knobs observaveis e auditaveis, nao como comportamento implicito.

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
- `FACE_SEARCH_COMPRE_FACE_API_KEY`

Observacao:

qualquer chave exposta em chat deve ser tratada como comprometida e rotacionada antes de uso.

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
- criadas as pastas obrigatorias:
  - `safety-safe`
  - `safety-review-block`
  - `face-search-positive`
  - `face-search-negative`
  - `face-search-low-quality`
  - `cross-event-isolation`
- criado teste `FaceSearchCuratedDatasetManifestTest`;
- o manifesto valida grupos obrigatorios, campos minimos, privacidade e extensoes permitidas quando fixtures reais forem adicionadas;
- status atual do dataset: contrato pronto, assets pendentes;
- a fase `H3` de benchmark `exact|ann` nao deve ser considerada valida ate que o manifesto seja populado com imagens anonimizadas ou consentidas reais.

## Backlog Operacional Imediato

### B1 - Backfill assíncrono via OpenAI Batch API

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

4. `H3-T3`
   - nao mergeia sem dataset curado versionado

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

1. `H5-T3` dataset curado
2. `H3-T1` estrategia `exact|ann`
3. `H3-T2` quality tiers
4. `H3-T3` benchmark e observabilidade

Motivo:

essa fase garante que benchmark e tuning nascam em cima de dados repetiveis e nao de casos soltos.

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
14. smoke tests reais locais existem para OpenAI, OpenRouter e CompreFace;
15. nenhuma chave real foi comitada em arquivo versionado.

---

## Proxima Execucao Recomendada

Se a execucao comecar agora, a ordem sugerida e:

1. implementar `H5-T1`
2. implementar `H1-T1`
3. implementar `H1-T2`
4. fechar `H1-T3`
5. fechar `H2-T0`
6. iniciar `H2-T1`
7. implementar `H2-T2`
8. implementar `H2-T3`
9. liberar `H2-T4`
10. montar `H5-T3`
11. implementar `H3-T1`
12. implementar `H3-T2`
13. implementar `H3-T3`
14. implementar `H4-T0`
15. avaliar `H4-T1`
16. fechar `H4-T2`
17. fechar `H4-T3`
18. rodar `H5-T2`

Isso responde primeiro:

- seguranca operacional de segredos;
- robustez operacional de safety;
- rastreabilidade de calibracao;
- semantica de `observe_only`;
- entrada real do provider facial do MVP;
- benchmark em cima de dataset repetivel.
