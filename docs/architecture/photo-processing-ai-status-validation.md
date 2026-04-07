# Validacao Atual Dos Modulos De IA De Fotos

## Objetivo

Este documento consolida o estado real da stack de IA ligada ao pipeline de fotos do Evento Vivo, cobrindo:

- o que ja esta implementado em `ContentModeration`, `MediaIntelligence` e `FaceSearch`;
- o que depende apenas de configuracao e infra;
- o que ainda depende de implementacao de provider real;
- se faz sentido centralizar parte dessa stack via `https://openrouter.ai/`;
- se a estrutura atual ja suporta gerar texto curto a partir da imagem com prompt por evento.

Data de validacao desta leitura: `2026-04-06`.

---

## Resumo Executivo

### O que ja esta pronto

1. `ContentModeration` ja tem modulo, settings por evento, provider manager, provider real `openai`, thresholds por categoria, historico de avaliacoes, fila dedicada e integracao no pipeline.
2. `MediaIntelligence` ja tem modulo, settings por evento, provider manager, provider real `vllm`, prompts versionados, schema JSON estruturado, persistencia de historico e copia opcional de `short_caption` para `event_media.caption`.
3. `FaceSearch` ja tem settings por evento, endpoints internos/publicos, busca por selfie, quality gate, persistencia de crops privados, armazenamento vetorial em `pgvector`, fila dedicada e testes do fluxo.
4. O pipeline principal ja separa `fast lane` e `heavy lane`, mantendo safety no gate rapido e indexacao facial fora da decisao final de publicacao.

### O que ainda nao esta pronto

1. O `.env` local em `apps/api/.env` nao tem nenhuma variavel de IA preenchida neste momento.
2. `FaceSearch` ainda nao tem provider facial real registrado. Hoje o backend e o frontend aceitam apenas `noop`.
3. `OpenRouter` nao entra como drop-in replacement do modulo atual de safety, porque o codigo usa `/moderations` e o acoplamento atual de `ContentModeration` esta em `openai|noop`.
4. A geracao de texto curto a partir da imagem ja existe para `caption`, mas ainda nao existe um campo/fluxo dedicado de `reply_text` gerado por IA conectado aos modulos de feedback de WhatsApp/Telegram.

### Conclusao pratica

- Safety por IA: pronto em nivel de logica, devendo ficar em `OpenAI` direto.
- VLM com caption curta e tags: pronto em nivel de logica, mas deve continuar opcional e assincrono por padrao.
- Face search real: arquitetura pronta, provider real faltando, e este deve ser tratado como prioridade 1 do produto.
- OpenRouter: bom candidato para `MediaIntelligence`; ruim como substituicao direta do `ContentModeration`; insuficiente sozinho para `FaceSearch`.

---

## Decisoes Arquiteturais Reforcadas

### 1. O nucleo robusto nao e o VLM

O nucleo robusto do Evento Vivo nao deve ser "um VLM que entende imagens".

O nucleo robusto deve ser:

1. safety barato e obrigatorio;
2. pipeline facial especializado para matching por pessoa;
3. enriquecimento semantico opcional e assincrono.

Traduzindo para os modulos atuais:

- `ContentModeration` = gate inicial barato;
- `FaceSearch` = principal diferencial do produto;
- `MediaIntelligence` = enrichment e contexto semantico, nao matching facial.

### 2. A busca por selfie nao deve depender de caption

Para milhares de fotos por evento, a busca por selfie deve continuar dependente de:

- deteccao facial;
- embedding facial especializado;
- busca vetorial por face;
- quality gate;
- ranking proprio por evento, publicacao e regras de negocio.

Ela nao deve depender de:

- caption automatica;
- tags semanticas;
- descricao visual generica.

### 3. A centralizacao deve ser seletiva

Se houver centralizacao via um unico gateway externo, ela deve ocorrer apenas onde o contrato do dominio ja e naturalmente multimodal OpenAI-compatible.

Hoje isso significa:

- sim para `MediaIntelligence`;
- nao para `ContentModeration`;
- nao para `FaceSearch`.

---

## Mapa Dos Modulos

### 1. ContentModeration

Responsabilidade atual:

- safety moderation de imagem;
- thresholds por categoria;
- historico por foto;
- disparo em fila `media-safety`;
- alimentacao de `safety_status` para a decisao final do pipeline.

Arquivos centrais:

- `apps/api/app/Modules/ContentModeration/README.md`
- `apps/api/config/content_moderation.php`
- `apps/api/app/Modules/ContentModeration/Providers/ContentModerationServiceProvider.php`
- `apps/api/app/Modules/ContentModeration/Services/OpenAiContentModerationProvider.php`
- `apps/api/app/Modules/ContentModeration/Actions/EvaluateContentSafetyAction.php`
- `apps/api/app/Modules/ContentModeration/Jobs/AnalyzeContentSafetyJob.php`
- `apps/api/app/Modules/ContentModeration/Http/Requests/UpsertEventContentModerationSettingsRequest.php`

### 2. MediaIntelligence

Responsabilidade atual:

- analise semantica da imagem com VLM;
- `decision`, `reason`, `short_caption` e `tags`;
- prompts e schema versionados por evento;
- `enrich_only` ou `gate`;
- historico em `event_media_vlm_evaluations`.

Arquivos centrais:

- `apps/api/app/Modules/MediaIntelligence/README.md`
- `apps/api/config/media_intelligence.php`
- `apps/api/app/Modules/MediaIntelligence/Providers/MediaIntelligenceServiceProvider.php`
- `apps/api/app/Modules/MediaIntelligence/Services/VllmVisualReasoningProvider.php`
- `apps/api/app/Modules/MediaIntelligence/Models/EventMediaIntelligenceSetting.php`
- `apps/api/app/Modules/MediaIntelligence/Services/VisualReasoningResponseSchemaFactory.php`
- `apps/api/app/Modules/MediaIntelligence/Jobs/EvaluateMediaPromptJob.php`
- `apps/api/app/Modules/MediaIntelligence/Http/Requests/UpsertEventMediaIntelligenceSettingsRequest.php`

### 3. FaceSearch

Responsabilidade atual:

- settings por evento;
- indexacao facial non-blocking;
- persistencia de crops privados;
- embeddings por face;
- busca por selfie interna/publica;
- armazenamento vetorial inicial em `pgvector`.

Arquivos centrais:

- `apps/api/app/Modules/FaceSearch/README.md`
- `apps/api/config/face_search.php`
- `apps/api/app/Modules/FaceSearch/Providers/FaceSearchServiceProvider.php`
- `apps/api/app/Modules/FaceSearch/Actions/IndexMediaFacesAction.php`
- `apps/api/app/Modules/FaceSearch/Actions/SearchFacesBySelfieAction.php`
- `apps/api/app/Modules/FaceSearch/Http/Requests/UpsertEventFaceSearchSettingsRequest.php`
- `apps/api/app/Modules/FaceSearch/Services/PgvectorFaceVectorStore.php`
- `apps/api/database/migrations/2026_04_02_110100_create_event_media_faces_table.php`

---

## Estado Atual Por Modulo

## ContentModeration

### O que ja temos

1. Settings por evento com:
   - `enabled`
   - `provider_key`
   - `mode`
   - `threshold_version`
   - `hard_block_thresholds`
   - `review_thresholds`
   - `fallback_mode`

2. Provider manager desacoplado por contrato:
   - `ContentModerationProviderInterface`
   - `ContentModerationProviderManager`
   - providers registrados hoje: `openai` e `noop`

3. Provider real implementado:
   - `OpenAiContentModerationProvider` usa `OPENAI_API_KEY`
   - faz `POST /moderations`
   - envia `image_url` e opcionalmente texto associado
   - converte scores do provider para categorias internas:
     - `nudity`
     - `violence`
     - `self_harm`

4. Integracao real no pipeline:
   - `MediaVariantsGenerated` dispara a etapa
   - `AnalyzeContentSafetyJob` roda em `media-safety`
   - `EvaluateContentSafetyAction` so chama provider externo quando `event.moderation_mode=ai`
   - a etapa grava auditoria em `media_processing_runs`
   - o resultado e persistido em `event_media_safety_evaluations`

5. Fallbacks e seguranca operacional:
   - `fallback_mode=review|block`
   - circuit breaker por provider
   - degrade mode via `OPS_DEGRADE_MEDIA_SAFETY_MODE`

6. Persistencia de auditoria do provider:
   - `raw_response_json` ja e persistido no historico de safety;
   - `category_scores_json` hoje representa os buckets internos do produto, nao o espelho completo das categorias granulares do provider.

### O que falta

1. Preencher variaveis reais no ambiente:
   - `CONTENT_MODERATION_PROVIDER`
   - `CONTENT_MODERATION_OPENAI_MODEL`
   - `OPENAI_BASE_URL`
   - `OPENAI_API_KEY`
   - `OPENAI_ORGANIZATION`
   - `OPENAI_PROJECT`

2. Garantir URL de preview realmente acessivel ao provider externo.

3. Validar operacao em producao com filas ativas:
   - `media-safety`
   - `media-fast`

4. Adicionar fallback por base64/data URL para reduzir falha operacional quando a imagem nao estiver publicamente acessivel ao provider.

5. Se a intencao for usar `OpenRouter` aqui, sera necessario um novo adapter. Hoje nao basta trocar `base_url`.

### Recomendacao reforcada

`ContentModeration` deve ser mantido em `OpenAI` direto.

Motivos:

- o endpoint de moderacao da OpenAI e gratuito para usuarios da API;
- o modelo `omni-moderation-latest` aceita texto e imagem;
- a API oficial aceita tanto `image_url` quanto data URL/base64;
- o dominio atual ja esta calibrado em torno de `flagged` + `category_scores`.

### Melhoria objetiva no codigo

Hoje o provider depende de `MediaAssetUrlService->preview()` e falha quando nao ha preview publico acessivel.

Melhoria recomendada:

1. manter `image_url` como caminho preferencial;
2. adicionar fallback de data URL/base64 lida do storage quando o preview nao estiver acessivel;
3. idealmente usar um derivado pequeno da imagem para moderacao, reduzindo peso e latencia.
4. manter os buckets internos `nudity`, `violence` e `self_harm` para regra de negocio, mas persistir tambem uma representacao granular normalizada do provider para recalibracao futura.

### Ajuste fino recomendado na persistencia

Hoje o modulo ja guarda `raw_response_json`, o que e bom e suficiente para auditoria completa.

O ajuste que ainda faz sentido e deixar explicito no dominio que existem dois niveis de classificacao:

1. buckets internos do negocio:
   - `nudity`
   - `violence`
   - `self_harm`

2. categorias granulares do provider:
   - por exemplo `sexual`, `sexual/minors`, `violence/graphic`, `self-harm/intent`, `self-harm/instructions` e demais categorias que o provider expuser.

Isso evita retrabalho quando thresholds precisarem ser recalibrados ao longo do tempo.

### Status real

`ContentModeration` esta funcional em termos de dominio e pipeline. O que falta e operacao/configuracao, nao arquitetura.

---

## MediaIntelligence

### O que ja temos

1. Settings por evento com:
   - `provider_key`
   - `model_key`
   - `enabled`
   - `mode`
   - `prompt_version`
   - `approval_prompt`
   - `caption_style_prompt`
   - `response_schema_version`
   - `timeout_ms`
   - `fallback_mode`
   - `require_json_output`

2. Provider manager desacoplado por contrato:
   - `VisualReasoningProviderInterface`
   - `VisualReasoningProviderManager`
   - providers registrados hoje: `vllm` e `noop`

3. Provider real implementado:
   - `VllmVisualReasoningProvider`
   - usa endpoint OpenAI-compatible `chat/completions`
   - envia imagem via `image_url`
   - suporta `response_format` com `json_schema`

4. Schema estruturado pronto:
   - `decision`
   - `review`
   - `reason`
   - `short_caption`
   - `tags`

5. Prompting por evento ja existe:
   - `approval_prompt`
   - `caption_style_prompt`
   - contexto do titulo do evento
   - legenda original enviada
   - texto da mensagem recebida

6. Integracao real no pipeline:
   - roda depois do safety quando aplicavel
   - fila `media-vlm`
   - persistencia em `event_media_vlm_evaluations`
   - historico exposto no detalhe da midia
   - em `mode=gate`, pode influenciar a decisao final
   - em `mode=enrich_only`, nao bloqueia publicacao

7. Atualizacao automatica de caption:
   - `EvaluateMediaPromptJob` copia `short_caption` para `event_media.caption` quando a midia ainda nao tem caption propria

### O que falta

1. Preencher variaveis reais no ambiente:
   - `MEDIA_INTELLIGENCE_PROVIDER`
   - `MEDIA_INTELLIGENCE_VLLM_BASE_URL`
   - `MEDIA_INTELLIGENCE_VLLM_API_KEY`
   - `MEDIA_INTELLIGENCE_VLLM_MODEL`

2. Garantir que o host do VLM consiga acessar a URL da imagem.

3. Rodar a fila dedicada:
   - `media-vlm`

4. Se a intencao for usar `OpenRouter`, sera necessario um novo provider `openrouter` no modulo.

### Recomendacao reforcada

`MediaIntelligence` nao deve ser tratado como o coracao da busca por pessoa.

Ele deve continuar:

- opcional;
- assincrono;
- `enrich_only` por padrao;
- aplicado apenas em fotos aprovadas ou em casos onde caption/tags realmente agreguem valor operacional.

### Regra pratica de produto

Em producao, a camada semantica deve ser usada para:

- gerar `short_caption`;
- gerar `tags`;
- alimentar review semantico opcional;
- responder sobre a foto apenas quando houver caso de uso claro no canal.

Ela nao deve:

- participar do matching facial;
- bloquear `FaceSearch`;
- virar dependencia critica para publicar fotos comuns.

### Status real

`MediaIntelligence` ja esta estruturalmente pronto para gerar texto curto, tags e decisao semantica por prompt. O gap aqui e provider/infra, nao desenho de dominio.

---

## FaceSearch

### O que ja temos

1. Settings por evento com:
   - `enabled`
   - `provider_key`
   - `embedding_model_key`
   - `vector_store_key`
   - `min_face_size_px`
   - `min_quality_score`
   - `search_threshold`
   - `top_k`
   - `allow_public_selfie_search`
   - `selfie_retention_hours`

2. Dominio de indexacao facial pronto:
   - `IndexMediaFacesAction`
   - `IndexMediaFacesJob`
   - quality gate facial
   - crop privado em `ai-private`
   - persistencia de bbox, qualidade, face hash, metadados e embedding por face

3. Busca por selfie pronta:
   - fluxo interno
   - fluxo publico
   - exigencia de uma unica face na selfie
   - limitacao por `event_id`
   - filtros de `approved/published` na busca publica

4. Base vetorial pronta:
   - `pgvector` provisionado
   - coluna `embedding vector(512)`
   - indice HNSW com `vector_cosine_ops`

5. Contrato de provider pronto:
   - `FaceDetectionProviderInterface`
   - `FaceEmbeddingProviderInterface`
   - `FaceVectorStoreInterface`

6. Integracao com o pipeline pronta:
   - `RunModerationJob` pode disparar `IndexMediaFacesJob`
   - `FaceSearch` continua fora do gate principal
   - aprovacao/rejeicao altera `searchable`

### O que falta

1. Provider real de deteccao facial.
2. Provider real de embedding facial.
3. Registro desses providers no container.
4. Liberar provider real nas validacoes backend.
5. Liberar provider real no formulario frontend.
6. Criar env vars especificas do provider facial escolhido.

### Bloqueio tecnico atual

Hoje o modulo esta travado em `noop`:

- `FaceSearchServiceProvider` registra apenas `noop`;
- `UpsertEventFaceSearchSettingsRequest` aceita apenas `provider_key=in:noop`;
- o frontend em `EventFaceSearchSettingsForm.tsx` tambem aceita apenas `noop`;
- `NullFaceDetectionProvider` retorna lista vazia;
- `NullFaceEmbeddingProvider` retorna vetor zerado.

### Status real

`FaceSearch` esta com a arquitetura e o pipeline prontos, mas sem motor facial real. Hoje ele nao entrega reconhecimento/catalogacao real apenas com configuracao.

### Recomendacao reforcada

`FaceSearch` deve ser tratado como o modulo principal de diferenciacao do produto.

Se o objetivo do Evento Vivo e permitir que o convidado encontre a propria foto entre milhares de imagens, este modulo vale mais para o produto do que:

- caption automatica;
- tags semanticas;
- review por prompt.

---

## Estrutura Atual De Caption Curta A Partir Da Imagem

### Resposta curta: sim, a estrutura ja existe

O projeto ja tem a base para olhar a imagem e gerar um texto curto com prompt configuravel por evento.

Isso ja acontece assim:

1. `EventMediaIntelligenceSetting` guarda `approval_prompt` e `caption_style_prompt`.
2. `VllmVisualReasoningProvider` monta o prompt incluindo:
   - prompt principal
   - contexto do evento
   - legenda original da midia
   - texto da mensagem recebida
   - prompt de estilo da caption
3. O provider exige retorno em JSON estruturado.
4. O schema ja pede `short_caption`.
5. `EvaluateMediaPromptJob` persiste a avaliacao e, quando a midia nao tem caption, copia `short_caption` para `event_media.caption`.

### O que isso significa na pratica

Ja existe suporte para:

- gerar legenda curta automatica baseada na imagem;
- controlar o estilo da resposta com prompt;
- guardar o historico da saida do modelo;
- usar isso apenas como enrichment ou tambem como gate.

### O que ainda nao existe

Ainda nao existe um campo funcional dedicado de resposta automatica do tipo:

- `reply_text` gerado pelo VLM para responder ao usuario no canal de origem.

Hoje o modulo preenche `caption`, nao uma mensagem transacional separada.

### Ponto importante

Existe infraestrutura adjacente para feedback com `reply_text` em WhatsApp/Telegram, mas ainda nao ha ponte direta entre `MediaIntelligence.short_caption` e os modulos de automacao de resposta.

Ou seja:

- caption automatica por foto: sim, estrutura pronta;
- resposta automatica do canal baseada na foto: ainda precisa integracao de produto.

### Recomendacao de design

Se houver uma proxima evolucao de produto, o ideal e separar explicitamente:

- `caption` publica da foto;
- `reply_text` transacional para WhatsApp/Telegram;
- `reason` interno de moderation/review.

Hoje essas tres coisas ainda nao estao separadas em um contrato de dominio proprio.

---

## Validacao Do Uso De OpenRouter

## O que a documentacao oficial do OpenRouter confirma

1. OpenRouter usa autenticacao por Bearer token e base `https://openrouter.ai/api/v1`.
2. OpenRouter implementa API muito proxima da OpenAI Chat API.
3. OpenRouter aceita `chat/completions` com imagens via `image_url`.
4. OpenRouter suporta `response_format` com `json_schema`.
5. OpenRouter tem endpoint de `embeddings`, inclusive com suporte a imagem.
6. OpenRouter oferece roteamento de modelo e fallback entre providers/modelos.
7. O `openrouter/free` escolhe modelos gratis aleatoriamente.
8. A documentacao atual de pricing informa que o preco de inferencia e repassado sem markup no pay-as-you-go; a taxa de `5.5%` aparece na compra de creditos e ha taxa separada de `BYOK` apos a franquia.

Referencias externas:

- `https://openrouter.ai/docs/api/reference/authentication`
- `https://openrouter.ai/docs/api/reference/overview`
- `https://openrouter.ai/docs/api/reference/embeddings`
- `https://openrouter.ai/docs/guides/routing/routers/auto-router`

## Onde OpenRouter encaixa bem

### 1. MediaIntelligence

Este e o melhor encaixe atual.

Motivos:

- o provider atual ja usa contrato OpenAI-compatible;
- o payload atual ja usa `chat/completions`;
- o schema estruturado ja esta pronto;
- a logica de prompting ja existe;
- o sistema ja aceita modelos multimodais com `image_url`.

Conclusao:

`MediaIntelligence` pode ser centralizado via OpenRouter com um adapter novo `openrouter`, sem redesenhar o dominio.

### 1.1. Como usar OpenRouter sem piorar o produto

Se `MediaIntelligence` for para OpenRouter, a recomendacao e:

1. usar modelo fixo por evento ou por ambiente;
2. nao usar `openrouter/free` em producao;
3. evitar `openrouter/auto` no inicio do rollout se o objetivo for previsibilidade de custo, comportamento e debugging;
4. tratar OpenRouter como camada de enrichment, nao de matching facial.

### 1.2. Ajuste de wording sobre custo

OpenRouter deve ser descrito como uma camada de:

- simplicidade de integracao;
- resiliencia operacional;
- fallback entre providers;
- API unificada.

Ele nao deve ser descrito genericamente como a opcao de menor custo.

Em carga baixa ou variavel, ele e muito conveniente.

Em carga alta e previsivel, um serving proprio como `vLLM` pode ficar melhor financeiramente se a operacao de GPU ja fizer sentido no produto.

### 2. Embeddings genericos de imagem

OpenRouter tambem suporta embeddings com imagem.

Conclusao:

Ele pode ser usado em experimentos de busca visual generica, similaridade de imagem ou classificacao multimodal.

## Onde OpenRouter nao encaixa como drop-in

### 1. ContentModeration

O modulo atual chama `POST /moderations` e espera semantica especifica de safety da OpenAI.

Hoje o codigo:

- aceita apenas `openai|noop`;
- usa resposta com `flagged` e `category_scores`;
- mapeia isso para thresholds internos.

Conclusao:

Nao da para apenas trocar `OPENAI_BASE_URL` por `openrouter.ai`.

Para centralizar safety via OpenRouter seria preciso:

1. criar um novo provider `openrouter`;
2. decidir um modelo de safety/classificacao;
3. refazer o contrato de resposta para categorias internas;
4. validar thresholds novamente.

Observacao:

Nao encontrei na documentacao oficial do OpenRouter um endpoint publico equivalente ao `/moderations` usado hoje. Essa conclusao e uma inferencia tecnica baseada nas APIs documentadas publicamente por eles.

### 2. FaceSearch

OpenRouter nao resolve sozinho o problema de reconhecimento facial do projeto.

Motivos:

- `FaceSearch` precisa deteccao facial real;
- precisa crop por face;
- precisa embedding facial especializado e estavel por identidade;
- precisa thresholds calibrados para matching de pessoa;
- o modulo atual separa detector e embedder explicitamente.

Mesmo com embeddings multimodais do OpenRouter, ainda faltariam:

- deteccao facial;
- calibracao biometrica;
- garantia de qualidade para same-person matching.

Conclusao:

OpenRouter nao e uma boa centralizacao unica para `FaceSearch`.

### Recomendacao objetiva

- `ContentModeration`: manter OpenAI direto.
- `MediaIntelligence`: opcionalmente centralizar via OpenRouter.
- `FaceSearch`: usar provider facial dedicado, mantendo `pgvector` como storage vetorial.

---

## Viabilidade De Provider Facial Dedicado

Para `FaceSearch`, a arquitetura atual pede um provider facial real. As opcoes mais coerentes com o estado atual do dominio sao:

### Opcao 1. CompreFace atras de adapter interno

Vantagens:

- rapido para MVP;
- API pronta;
- conceito de servico facial dedicado;
- suporta detecao, reconhecimento e verificacao por REST API;
- suporta input de imagem em `base64` nos endpoints que recebem imagem;
- expoe endpoints orientados a embedding para reconhecimento e verificacao;
- tem trilha operacional em CPU e GPU;
- alinhado com a arquitetura ja discutida em `photo-processing-ai-architecture.md`.

Desvantagens:

- adapter ainda nao existe no codigo;
- precisa validar qualidade dos embeddings e thresholds.

### Opcao 2. Microservico proprio com InsightFace/ArcFace

Vantagens:

- embeddings faciais especializados;
- mais controle sobre modelo, versao e calibracao;
- melhor aderencia a uma busca por pessoa de longo prazo.

Desvantagens:

- exige mais implementacao de infra e adapter.
- a trilha de licenciamento precisa ser tratada explicitamente, porque o repositorio informa MIT para o codigo, mas restringe modelos treinados e dados anotados para pesquisa nao comercial e oferece licenciamento comercial separado para modelos.

### Recomendacao

Se o objetivo e colocar `FaceSearch` para funcionar mais rapido:

- CompreFace como MVP.

Se o objetivo e acertar melhor a base tecnica para escala:

- microservico proprio com InsightFace/ArcFace.

### Recomendacao mais dura de arquitetura

Mesmo com `CompreFace`, o catalogo principal do produto nao deve morar dentro de uma colecao proprietaria da engine facial.

O melhor uso do provider facial e:

1. deteccao;
2. embedding;
3. eventualmente verificacao auxiliar;
4. ranking final e filtros de negocio no proprio `PostgreSQL + pgvector`.

### Trilha padrao recomendada para o MVP

A trilha padrao do MVP deve ser declarada de forma fechada:

1. `ContentModeration` com OpenAI direto;
2. `FaceSearch` com adapter real de `CompreFace` atras do dominio;
3. `PostgreSQL + pgvector` como busca final e ranking do produto;
4. `MediaIntelligence` apenas como enrichment assincrono.

Assim, o produto continua dono de:

- `event_id`;
- `searchable`;
- status de moderacao/publicacao;
- retention;
- thresholds e ranking final.

---

## Busca Vetorial E Recall

### Estado atual

Hoje a migration cria `pgvector` com indice `HNSW`.

Isso mostra que a base vetorial esta pronta, mas nao significa que a melhor estrategia de busca para producao inicial seja ANN por padrao.

### Recomendacao

Para o caso do Evento Vivo, a busca facial deve privilegiar recall antes de otimizar ANN prematuramente.

Em termos praticos:

1. fase inicial:
   - benchmark de busca exata por `event_id`;
   - indice normal na coluna de filtro do evento;
   - validar latencia real com volume por evento;
   - medir recall e taxa de acerto.

2. fase de escala:
   - usar `HNSW` apenas quando houver dor real de performance;
   - ajustar iterative scans;
   - ajustar `ef_search` e parametros correlatos;
   - avaliar indices parciais;
   - avaliar particionamento por evento ou por recorte operacional.

### Motivo

A documentacao oficial do `pgvector` e clara em tres pontos:

1. por padrao, a busca e exact nearest neighbor com recall perfeito;
2. `HNSW` e `IVFFlat` trocam recall por velocidade;
3. com filtros, buscas aproximadas podem retornar menos resultados e pedem estrategia especifica como iterative scans.

### Melhoria objetiva no produto

Adicionar um modo operacional explicito para a busca facial:

- `exact`
- `ann`

Isso permitiria comparar recall/latencia sem acoplar o produto a um unico comportamento desde o primeiro rollout.

### Regra de projeto recomendada

Como a busca quase sempre filtra por `event_id`, a estrategia de vetor deve nascer orientada a esse filtro.

Em termos praticos:

1. quando o filtro recorta pequena parcela das linhas:
   - priorizar busca exata;
   - indexar bem a coluna de filtro.

2. quando houver poucos valores de filtro:
   - considerar indice parcial.

3. quando houver muitos valores distintos e volume muito alto:
   - considerar particionamento.

---

## Quality Gate Como Peca Central

### O papel do quality gate

`min_face_size_px` e `min_quality_score` nao sao detalhes. Eles sao parte do nucleo de performance do produto.

Em busca por selfie, a robustez do matching depende muito de:

- selfie nitida;
- face frontal ou quase frontal;
- crop com tamanho minimo;
- pouca oclusao;
- pouca borracao;
- iluminacao aceitavel.

### O que a literatura operacional reforca

As avaliacoes do NIST tratam qualidade facial como preditor de acuracia de reconhecimento. Em outras palavras: se a qualidade cai, a chance de erro sobe.

### Implicacao para o Evento Vivo

O sistema deve:

1. aplicar gate forte na selfie de consulta;
2. aplicar gate forte nas faces indexadas;
3. indexar apenas faces que passem nesse gate;
4. guardar score de qualidade por face;
5. usar qualidade como desempate/ranking no resultado final.

### Melhorias recomendadas

1. explicitar quality tiers no dominio:
   - `reject`
   - `index_only`
   - `search_priority`

2. criar observabilidade por qualidade:
   - percentual de selfies rejeitadas;
   - percentual de faces detectadas que nao viram embedding;
   - top motivos de rejeicao.
   - top-1 hit rate
   - top-5 hit rate
   - p95 de busca
   - taxa de falso positivo percebido na revisao manual

---

## Variaveis De Ambiente Ja Previstas

## ContentModeration

Ja previstas em `apps/api/.env.example`:

- `CONTENT_MODERATION_PROVIDER`
- `CONTENT_MODERATION_OPENAI_MODEL`
- `CONTENT_MODERATION_OPENAI_MODEL_SNAPSHOT`
- `CONTENT_MODERATION_OPENAI_TIMEOUT`
- `CONTENT_MODERATION_OPENAI_CONNECT_TIMEOUT`
- `CONTENT_MODERATION_OPENAI_PROVIDER_VERSION`
- `OPENAI_BASE_URL`
- `OPENAI_API_KEY`
- `OPENAI_ORGANIZATION`
- `OPENAI_PROJECT`

## MediaIntelligence

Ja previstas em `apps/api/.env.example`:

- `MEDIA_INTELLIGENCE_PROVIDER`
- `MEDIA_INTELLIGENCE_VLLM_BASE_URL`
- `MEDIA_INTELLIGENCE_VLLM_API_KEY`
- `MEDIA_INTELLIGENCE_VLLM_MODEL`
- `MEDIA_INTELLIGENCE_VLLM_MODEL_SNAPSHOT`
- `MEDIA_INTELLIGENCE_VLLM_TIMEOUT`
- `MEDIA_INTELLIGENCE_VLLM_CONNECT_TIMEOUT`
- `MEDIA_INTELLIGENCE_VLLM_PROVIDER_VERSION`
- `MEDIA_INTELLIGENCE_VLLM_TEMPERATURE`
- `MEDIA_INTELLIGENCE_VLLM_MAX_COMPLETION_TOKENS`

## FaceSearch

Ja previstas em `apps/api/.env.example`:

- `FACE_SEARCH_DETECTION_PROVIDER`
- `FACE_SEARCH_EMBEDDING_PROVIDER`
- `FACE_SEARCH_VECTOR_STORE`
- `FACE_SEARCH_EMBEDDING_MODEL`
- `FACE_SEARCH_EMBEDDING_DIMENSION`
- `FACE_SEARCH_CROP_DISK`
- `FACE_SEARCH_MIN_FACE_SIZE_PX`
- `FACE_SEARCH_MIN_QUALITY_SCORE`
- `FACE_SEARCH_SEARCH_THRESHOLD`
- `FACE_SEARCH_TOP_K`

Observacao importante:

Essas variaveis faciais hoje so controlam defaults genericos. Ainda faltam variaveis especificas de provider real porque esse adapter ainda nao existe.

### Variaveis sugeridas para um futuro provider OpenRouter em `MediaIntelligence`

Se optarmos por adapter `openrouter` no modulo `MediaIntelligence`, a sugestao e criar algo como:

- `MEDIA_INTELLIGENCE_PROVIDER=openrouter`
- `MEDIA_INTELLIGENCE_OPENROUTER_BASE_URL=https://openrouter.ai/api/v1`
- `MEDIA_INTELLIGENCE_OPENROUTER_API_KEY=...`
- `MEDIA_INTELLIGENCE_OPENROUTER_MODEL=openai/gpt-4.1-mini` ou outro multimodal escolhido
- `MEDIA_INTELLIGENCE_OPENROUTER_SITE_URL=...`
- `MEDIA_INTELLIGENCE_OPENROUTER_APP_NAME=Evento Vivo`

Se insistirmos em testar OpenRouter tambem em safety, seria necessario um bloco separado, por exemplo:

- `CONTENT_MODERATION_PROVIDER=openrouter`
- `CONTENT_MODERATION_OPENROUTER_BASE_URL=https://openrouter.ai/api/v1`
- `CONTENT_MODERATION_OPENROUTER_API_KEY=...`
- `CONTENT_MODERATION_OPENROUTER_MODEL=<modelo-classificador>`

Mas isso exigiria novo provider e recalibracao, e nao e a recomendacao principal.

---

## Pontos De Adaptacao No Codigo Para OpenRouter

## MediaIntelligence

Mudancas necessarias:

1. `apps/api/config/media_intelligence.php`
   - adicionar bloco `providers.openrouter`

2. `apps/api/app/Modules/MediaIntelligence/Providers/MediaIntelligenceServiceProvider.php`
   - registrar `OpenRouterVisualReasoningProvider`

3. `apps/api/app/Modules/MediaIntelligence/Http/Requests/UpsertEventMediaIntelligenceSettingsRequest.php`
   - liberar `provider_key=in:vllm,openrouter,noop`

4. `apps/web/src/modules/events/components/media-intelligence/EventMediaIntelligenceSettingsForm.tsx`
   - liberar `openrouter` na UI

5. `apps/web/src/modules/events/api.ts`
   - ajustar tipos do payload

## ContentModeration

Mudancas necessarias se quiser usar OpenRouter aqui:

1. `apps/api/config/content_moderation.php`
2. `apps/api/app/Modules/ContentModeration/Providers/ContentModerationServiceProvider.php`
3. `apps/api/app/Modules/ContentModeration/Http/Requests/UpsertEventContentModerationSettingsRequest.php`
4. criar um `OpenRouterContentModerationProvider`
5. recalibrar thresholds e mapeamento de categorias

## FaceSearch

OpenRouter nao resolve o modulo sozinho. O caminho real aqui e outro:

1. criar provider facial dedicado;
2. registrar provider no `FaceSearchServiceProvider`;
3. liberar provider no request backend;
4. liberar provider no formulario frontend;
5. criar testes especificos do provider;
6. documentar env vars especificas do adapter escolhido.

---

## Cobertura De Testes Ja Existente

### ContentModeration

- unit:
  - `UpsertEventContentModerationSettingsActionTest`
  - `OpenAiContentModerationProviderTest`
  - `ContentSafetyThresholdEvaluatorTest`
- feature:
  - `ContentModerationSettingsTest`
  - `ContentModerationPipelineTest`
  - `ContentModerationCircuitBreakerTest`

### MediaIntelligence

- unit:
  - `EventMediaIntelligenceModelsTest`
  - `VllmVisualReasoningProviderTest`
  - `UpsertEventMediaIntelligenceSettingsActionTest`
- feature indireta no pipeline:
  - `MediaPipelineJobsTest`
  - `MediaReprocessTest`
  - `EventMediaListTest`

### FaceSearch

- unit:
  - `FaceSearchSupportTest`
  - `CollapseFaceSearchMatchesQueryTest`
- feature:
  - `FaceSearchSettingsTest`
  - `FaceSearchSelfieEndpointsTest`
  - `FaceIndexingPipelineTest`
  - validacoes adicionais em `MediaReprocessTest` e `MediaDeletionPropagationTest`

Conclusao:

Os tres modulos ja contam com cobertura relevante de dominio/pipeline. O maior gap nao e teste do que existe, e sim falta do provider facial real e dos adapters novos desejados.

---

## Decisao Recomendada

## Cenario recomendado de curto prazo

1. Ativar `ContentModeration` com OpenAI direto.
2. Implementar provider facial dedicado para `FaceSearch` como prioridade 1.
3. Manter `pgvector` como storage vetorial e motor de ranking do produto.
4. Rodar `MediaIntelligence` apenas como enrichment, via `vllm` ou `OpenRouter`.
5. Usar `short_caption` como enrichment imediato, nao como dependencia de matching.

## Endpoints Recomendados Do MVP

Para evitar ambiguidade no rollout, o desenho operacional do MVP pode ser resumido assim:

1. `ContentModeration`
   - `POST /v1/moderations` na OpenAI
   - `omni-moderation-latest`
   - `image_url` como caminho padrao
   - data URL/base64 como fallback

2. `MediaIntelligence`
   - `POST /v1/chat/completions` em `vLLM` ou `OpenRouter`
   - structured outputs via `response_format`

3. `FaceSearch`
   - `POST /api/v1/detection/detect` no `CompreFace`
   - `face_plugins=calculator` como base do caminho de indexacao
   - verificacao por embeddings como ferramenta auxiliar de QA/calibracao, nao como ranking final do produto

4. `Busca final`
   - `PostgreSQL + pgvector` dentro do app Laravel

5. `Reprocessamento em massa`
   - `POST /v1/batches` da OpenAI fica como opcional para backlog e reclassificacao de safety/VLM, nao como caminho critico do upload

## Cenario recomendado se quiser centralizar "o maximo possivel"

1. Centralizar apenas `MediaIntelligence` via OpenRouter.
2. Manter safety fora do OpenRouter por enquanto.
3. Manter reconhecimento facial fora do OpenRouter.

Esse e o melhor equilibrio hoje entre:

- reaproveitamento do que ja existe;
- menor risco tecnico;
- menor retrabalho de thresholds;
- melhor aderencia ao desenho atual do monorepo.

---

## Proximos Passos Objetivos

1. Revogar a chave OpenAI que foi exposta fora do cofre de segredos e criar uma nova com permissao restrita.
2. Decidir se `MediaIntelligence` fica em `vllm` ou vai para `OpenRouter`.
3. Se for `OpenRouter`, implementar provider `openrouter` apenas nesse modulo primeiro.
4. Escolher o motor real do `FaceSearch`:
   - CompreFace
   - InsightFace/ArcFace
5. Criar adapter facial real.
6. Adicionar fallback base64 no `ContentModeration`.
7. Preencher segredos no ambiente.
8. Validar public URLs de preview para providers externos.
9. Adicionar estrategia observavel de busca `exact` vs `ann` no `FaceSearch`.
10. Subir filas `media-safety`, `media-vlm` e `face-index` com Horizon.
11. Se quiser responder no canal com base na foto, criar um output adicional alem de `caption`, por exemplo `reply_text`, e ligar isso aos modulos de feedback.

---

## Referencias Externas Validadas

- OpenAI Moderation API: aceita texto e imagem; `image_url` pode ser URL ou data URL/base64.
- OpenAI Help Center: o endpoint de moderacao e gratuito para usuarios da API.
- OpenAI Moderation guide: `category_scores` podem exigir recalibracao com a evolucao do modelo; `categories`, `category_scores` e `category_applied_input_types` existem na resposta.
- OpenRouter API: `chat/completions` OpenAI-compatible, structured outputs e imagens em `image_url`.
- OpenRouter Free Models Router: escolhe modelo gratis aleatoriamente e tem limitacoes de uso.
- OpenRouter FAQ e Auto Router: bom para API unificada, fallback e resiliancia; `openrouter/auto` usa todos os modelos suportados quando nao ha restricao; em alto volume, isso nao implica automaticamente menor custo que um `vLLM` proprio.
- CompreFace: REST API de face detection, verification e recognition; deploy com Docker; suporte operacional em CPU/GPU.
- CompreFace REST API: aceita `base64` nos endpoints de imagem; detection pode usar `face_plugins`; verification e recognition possuem endpoints orientados a embedding.
- InsightFace: codigo sob MIT, mas modelos treinados e dados anotados distribuidos pelo projeto sao restritos a pesquisa nao comercial, com trilha de licenciamento comercial separada.
- pgvector: busca exata por padrao; HNSW/IVFFlat sao aproximados; filtering com ANN pede estrategia extra como iterative scans.
- NIST FATE/FRVT Quality: qualidade facial deve ser tratada como preditor de acuracia de reconhecimento.
