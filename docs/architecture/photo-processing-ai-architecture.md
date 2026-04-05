# Arquitetura recomendada para processamento inteligente de fotos

## Objetivo

Este documento descreve como encaixar, na stack real do Evento Vivo, um fluxo de processamento de fotos com tres capacidades separadas:

1. busca por pessoa via reconhecimento facial self-hosted;
2. regra por prompt + frase curta usando um VLM dedicado;
3. moderacao de nudez/violencia em camada propria, sem misturar com facial.

O foco aqui nao e propor uma arquitetura generica. O foco e mostrar o que faz sentido **dentro do monorepo atual**, respeitando:

- backend modular por dominio;
- Laravel + Horizon + Reverb como orquestradores;
- PostgreSQL como fonte transacional principal;
- Redis para filas;
- storage S3-like/MinIO para arquivos;
- frontend React ja existente para upload, moderacao e realtime.

Backlog detalhado de implementacao:

- `docs/architecture/photo-processing-ai-execution-plan.md`

## Resumo executivo

O melhor encaixe para a stack atual e este:

- `MediaProcessing` continua dono do ciclo base da midia: receber, baixar, gerar variantes, decidir publicacao e emitir eventos;
- `ContentModeration` vira um modulo proprio para nudez/violencia e risco de seguranca;
- `FaceSearch` vira um modulo proprio para detectar rostos, gerar embeddings por face e buscar dentro do evento;
- `MediaIntelligence` vira um modulo proprio para regra por prompt, legenda curta e tags;
- Laravel nao executa inferencia pesada diretamente. Ele orquestra jobs, persiste estado, fala com servicos internos e publica eventos;
- `FaceSearch` deve ser non-blocking por default: index facial nao pode travar wall, galeria ou publish;
- `pgvector` deve ser o default inicial da implementacao vetorial, com contrato pronto para migracao futura para `Qdrant`;
- `CompreFace` e util como engine de arranque, mas nao deve ser o nucleo definitivo do dominio;
- o VLM precisa nascer ja com serving OpenAI-compatible e JSON estruturado, para nao virar fonte de latencia e inconsistenca;
- MinIO/S3 deve separar bucket publico de bucket privado para crops faciais, selfies e artefatos sensiveis;
- o frontend atual de moderacao ja e uma base boa para mostrar resultados de safety, VLM e fila humana.

## Backlog executavel da Fase 1

Objetivo:

- endurecer o pipeline existente antes de plugar safety, face search e VLM de verdade.

### 1.1 Schema e storage canonico

Tarefas:

- separar nome enviado pelo cliente de path real de storage;
- adicionar campos de estado do pipeline futuro sem quebrar o fluxo atual;
- preparar discos privados para artefatos sensiveis.

Subtarefas:

- adicionar `original_disk`, `original_path` e `client_filename` em `event_media`;
- adicionar `safety_status`, `face_index_status`, `vlm_status` e `pipeline_version`;
- enriquecer `media_processing_runs` com `stage_key`, provider, modelo, resultado e metricas;
- preparar `media-private` e `ai-private` em `filesystems.php`;
- fazer backfill minimo para os registros legados.

### 1.2 Variantes reais

Tarefas:

- trocar o job de variantes de marcador de status para gerador real de artefatos.

Subtarefas:

- criar `MediaVariantGeneratorService`;
- gerar `thumb`, `gallery` e `wall` reais para imagens;
- atualizar dimensoes da midia a partir do original;
- persistir runs da etapa `variants`.

### 1.3 Decisao final centralizada

Tarefas:

- tirar a mudanca de `moderation_status` de regras espalhadas;
- criar um ponto unico de decisao do pipeline.

Subtarefas:

- criar `FinalizeMediaDecisionAction`;
- normalizar os status ainda nao implementados como `skipped` ou `queued`;
- aprovar automatico so depois do pipeline base terminar;
- manter `manual` em `pending` e `none` em `approved` quando nao houver bloqueio.

### 1.4 Upload publico sem aprovacao precoce

Tarefas:

- impedir que a foto nasca `approved` no momento do upload.

Subtarefas:

- salvar `event_media` sempre como `pending` no upload;
- responder `moderation=pending` para o cliente;
- registrar `original_path` e `client_filename` desde a entrada.

### 1.5 Testes e documentacao viva

Tarefas:

- travar a nova base do pipeline em testes e docs.

Subtarefas:

- atualizar testes de upload;
- atualizar testes dos jobs do pipeline;
- atualizar o `README` do modulo;
- atualizar o fluxo operacional em `docs/flows/media-ingestion.md`.

## Status da execucao atual

Entregue neste ciclo:

- schema aditivo em `event_media` com `original_disk`, `original_path`, `client_filename`, estados de pipeline e erros;
- schema aditivo em `media_processing_runs` com `stage_key`, provider, modelo, resultado, metricas, fila, worker, custo e idempotency;
- discos `media-private` e `ai-private` preparados no Laravel;
- `PublicUploadController` salvando path real separadamente e sem aprovacao precoce;
- `moderation_mode` consolidado em `none/manual/ai`, com compatibilidade de leitura para legado `auto -> none`;
- trilha de decisao e override humano auditavel em `event_media`;
- `GenerateMediaVariantsJob` gerando variantes reais por `MediaVariantGeneratorService`, agora com `fast_preview` no fast lane;
- `media-fast` reservado no Horizon para variantes e consolidacao rapida do pipeline;
- `RunModerationJob` movido para `FinalizeMediaDecisionAction` como ponto unico de decisao do pipeline base;
- `PublishMediaJob` e demais etapas gravando runs enriquecidos;
- resources da API expondo os novos estados do pipeline e runs detalhados;
- testes de upload e pipeline atualizados e aprovados;
- modulo `ContentModeration` criado com settings por evento, historico de avaliacoes e `AnalyzeContentSafetyJob` em fila dedicada;
- `ContentModeration` agora conectado a provider real por adapter proprio, com `OpenAiContentModerationProvider`, thresholds por categoria e fallback configuravel por evento;
- settings de safety expostos por endpoint dedicado e por card administrativo na aba de moderacao do detalhe do evento;
- fundacao de `FaceSearch` criada com toggle por evento e `face_index_status=queued/skipped` conforme configuracao;
- `FaceSearch` agora com schema real de settings por evento, `event_media_faces`, `event_face_search_requests`, interfaces faciais e porta vetorial em `pgvector`;
- `FaceSearchServiceProvider` agora registra detection, embedding e vector store por contratos de dominio;
- `IndexMediaFacesJob` agora existe na fila `face-index`, indexa por face, salva crops privados em `ai-private` e persiste embeddings por face;
- `RunModerationJob` agora dispara o heavy lane de `FaceSearch` sem bloquear publish quando `face_index_status=queued`;
- overrides humanos agora religam ou desligam `searchable` das faces indexadas em approve/reject;
- settings de `FaceSearch` agora expostos por endpoint dedicado e por card administrativo na aba de moderacao do detalhe do evento;
- o detalhe da midia agora expoe `indexed_faces_count`, e a central de moderacao passou a refletir o status de `face_index_status`;
- busca por selfie agora existe no backend com endpoint interno em `/events/{event}/face-search/search`, bootstrap publico em `/public/events/{slug}/face-search` e busca publica em `/public/events/{slug}/face-search/search`;
- `event_face_search_requests` agora registra auditoria, `consent_version`, `selfie_storage_strategy`, TTL e melhores distancias por request;
- a busca publica agora respeita consentimento explicito, `allow_public_selfie_search`, retention por evento e retorno restrito a `approved + published`;
- o frontend agora expoe a experiencia publica em `/e/:slug/find-me` e um card interno de busca por selfie na aba de moderacao do detalhe do evento;
- os links publicos do evento agora incluem `find_me` quando a busca publica por selfie estiver habilitada;
- modulo `MediaIntelligence` criado com settings por evento, historico `event_media_vlm_evaluations`, adapter `vLLM` OpenAI-compatible e schema JSON estruturado;
- `MediaIntelligence` agora exposto na aba de moderacao do detalhe do evento, com `provider_key`, `model_key`, `mode`, prompts, schema versionado e fallback validado por evento;
- `EvaluateMediaPromptJob` agora integrado ao pipeline real, consumindo `fast_preview`, persistindo `vlm_status`, caption curta e `event_media_vlm_evaluations`;
- a central de moderacao agora le o ultimo resultado estruturado de safety e VLM no detalhe da midia;
- reprocessamento seletivo agora existe no backend para `safety`, `vlm` e `face_index`, com auditoria, novo run por etapa e historico preservado;
- exclusao propagada agora remove original, variantes, crops faciais, projection vetorial, avaliacoes e referencias residuais de busca;
- `ProviderCircuitBreaker` e `PipelineFailureClassifier` agora endurecem o pipeline com fallback seguro, classificacao de falha e protecao contra cascata de provider;
- `/events/{event}/media/pipeline-metrics` agora expoe summary, SLA, backlog por fila e breakdown de falhas para observabilidade operacional;
- ambiente local preparado com `pgvector` no Postgres e buckets privados adicionais no MinIO.

Pendente para os proximos ciclos:

- expor reprocessamento seletivo na UI administrativa da central de moderacao e do detalhe da midia;
- endurecer delecao propagada de projection vetorial externa quando `Qdrant` entrar;
- avaliar se a busca publica por selfie precisa migrar para modo assincrono curto com `Reverb` em eventos de alta carga;
- validar benchmark real e tuning operacional de filas em carga de evento;
- mover originais/crops sensiveis para fluxo privado com URL assinada quando essa borda existir.

## Decisoes que precisam ficar fechadas para arquitetura definitiva

### 0. Modelo de moderacao do produto

Para o comportamento que voce descreveu, o produto precisa assumir explicitamente tres modos de moderacao no evento:

- `none`: sem moderacao, a midia segue para `approved` assim que o pipeline base terminar;
- `manual`: a midia sempre termina em `pending` e so vai ao ar por aprovacao humana;
- `ai`: a midia passa por safety e, opcionalmente, por regra semantica; se a IA nao reprovar nem mandar para review, ela pode ir ao ar.

Observacao importante sobre a stack atual:

- o repositorio ja trabalha com `none`, `manual` e `ai` em `moderation_mode`;
- a leitura de legado `auto -> none` foi mantida para compatibilidade de dados existentes;
- para arquitetura definitiva, `auto` deve ser tratado apenas como legado e nunca como contrato novo da API.

Override humano:

- precisa existir em todos os modos;
- uma midia bloqueada pela IA pode ser aprovada depois;
- uma midia aprovada automaticamente tambem pode ser rejeitada depois;
- a trilha de override deve ser auditavel;
- se a midia mudar para `rejected`, qualquer face indexada precisa ficar `searchable=false` imediatamente para busca publica.

### 1. O que bloqueia publish

No desenho final, o que bloqueia publish depende do `moderation_mode`:

- em `none`: nada bloqueia publish no criterio de moderacao; a midia pode ser aprovada direto;
- em `manual`: tudo termina em `pending` ate decisao humana;
- em `ai`: bloqueiam publish o `safety` e a regra semantica quando o evento ativar modo `gate`.

Nao devem bloquear publish:

- face index;
- reranking;
- analytics;
- caption e tags em modo `enrich_only`.

Regra de ouro:

- nunca aprovar por erro;
- nunca deixar enrichment travar a experiencia ao vivo.

### 2. Como o fast lane roda nesta stack

Pela stack real do repositorio, o caminho mais coerente nao e inferencia pesada dentro da mesma request HTTP. O melhor encaixe e:

- request curta: recebe a foto, salva original, cria `event_media`, responde rapido;
- fast lane assincrono de alta prioridade: gera preview pequeno, roda safety, roda VLM rapido, consolida decisao;
- atualizacao da UI por `Reverb`, sem polling pesado.

Do ponto de vista do usuario isso continua parecendo quase-sincrono, mas operacionalmente preserva:

- latencia de upload baixa;
- menos risco de timeout no upload publico;
- melhor isolamento de falhas de provider.

### 3. Qual e o SLA inicial de produto

Sem benchmark proprio, a doc ainda nao deve prometer milissegundos de producao. Mas ela precisa cravar um SLA inicial de produto:

| Etapa | Meta inicial |
| --- | --- |
| resposta do upload | imediata, com alvo sub-segundo |
| preview + safety + VLM rapido + primeira atualizacao de tela | janela curta, alvo p95 de poucos segundos |
| face index completo | eventual-consistente, sem SLA de publish |

Esse SLA precisa ser validado em benchmark real antes de virar compromisso externo.

### 4. Qual e o fallback de falha

Comportamento recomendado:

- se safety falhar: `moderation_status=pending` e review manual;
- se VLM em modo `gate` falhar: `moderation_status=pending` e review manual;
- se VLM em modo `enrich_only` falhar: publica sem caption gerada, com `caption` vazia e `vlm_status=failed`;
- se face indexing falhar: midia continua no fluxo, mas a busca facial fica indisponivel para aquela foto;
- nunca transformar falha tecnica em `approved`.

### 5. Qual e o motor facial inicial de producao

A doc ja esta correta em nao acoplar o dominio a `CompreFace`, mas ainda vale fechar a decisao operacional:

- MVP: `CompreFace` atras de adapter interno;
- arquitetura-alvo: servico facial proprio atras de interfaces;
- criterio de saida do MVP: quando custo, falsos positivos, observabilidade ou manutencao do engine de arranque deixarem de ser aceitaveis.

### 6. Busca por pessoa e opcional por evento

Busca por pessoa nao deve ser acoplada ao `moderation_mode`.

Regra recomendada:

- o evento define separadamente se `FaceSearch` esta habilitado ou nao;
- se estiver desligado, o heavy lane simplesmente nao indexa faces;
- se estiver ligado, a indexacao facial roda como enrichment e a busca respeita o escopo do evento.

Separacoes uteis:

- `enabled`: liga ou desliga indexacao facial para o evento;
- `allow_public_selfie_search`: controla se o convidado pode usar a busca publica;
- busca interna e busca publica podem ter politicas diferentes de exposicao de resultado.

## Recomendacao consolidada para producao

Se a doc for endurecida para producao hoje, a recomendacao mais robusta para o Evento Vivo fica assim:

- control plane: Laravel + Horizon + Reverb + PostgreSQL + Redis + MinIO;
- `ContentModeration`: `omni-moderation-latest`, via adapter proprio do dominio;
- `MediaIntelligence`: `Qwen2.5-VL-3B-Instruct` como padrao custo/beneficio, `Qwen2.5-VL-7B-Instruct` para planos premium ou backoffice, ambos servidos por `vLLM`;
- modo alternativo de VLM/API: `GPT-5.4 nano`, sem mudar contratos de dominio;
- `FaceSearch`: servico facial interno atras de `FaceDetectionProviderInterface` e `FaceEmbeddingProviderInterface`;
- `FaceVectorStore`: `pgvector` agora, `Qdrant` como trilha explicita de escala.

Leitura operacional recomendada:

- fast lane: preview pequeno, `omni-moderation-latest`, VLM rapido e decisao final;
- heavy lane: face index, embeddings por face, reranking, reprocessamentos e analytics.

Leitura de produto recomendada:

- `moderation_mode=none`: publica direto;
- `moderation_mode=manual`: fila humana decide;
- `moderation_mode=ai`: IA decide bloquear, revisar ou deixar seguir;
- `FaceSearch` ligado ou desligado separadamente por evento.

Decisoes de produto:

- publicar rapido;
- enriquecer depois;
- nunca deixar face indexing travar a experiencia ao vivo.

Decisoes de engenharia:

- interfaces de dominio primeiro;
- provider depois;
- sem acoplamento duro a OpenAI, Qwen, CompreFace, pgvector ou Qdrant.

## Leitura da stack real do repositorio

### Backend real observado

No estado atual do codigo, o backend esta em:

- PHP 8.3;
- Laravel 13, conforme `apps/api/composer.json` (nao 12);
- Horizon para filas;
- Reverb para realtime;
- PostgreSQL 16;
- Redis 7;
- suporte a storage local e S3 em `apps/api/config/filesystems.php`;
- `Intervention Image` ja instalado, o que ajuda na camada CPU de variantes;
- `spatie/laravel-data`, que encaixa bem para DTOs e contratos com servicos externos.

### Frontend real observado

No frontend ja existem pontos que facilitam bastante essa evolucao:

- pagina publica de upload em `apps/web/src/modules/upload/PublicEventUploadPage.tsx`;
- central de moderacao em `apps/web/src/modules/moderation/ModerationPage.tsx`;
- canal realtime de moderacao ja operacional;
- `TanStack Query` e `React Hook Form`, bons para telas de busca e configuracao;
- rotas publicas e administrativas ja separadas em `apps/web/src/App.tsx`.

### Pipeline atual de midia

Hoje o fluxo base ja esta desenhado no repositorio:

- `PublicUploadController` cria `event_media`, salva arquivo e despacha `GenerateMediaVariantsJob`;
- `EventMedia`, `EventMediaVariant` e `MediaProcessingRun` ja existem;
- a central de moderacao e a galeria leem `event_media`;
- `PublishMediaJob` ja conversa com o resto do produto via eventos;
- `Wall` ja reage ao pipeline publicado.

Arquivos-chave:

- `apps/api/app/Modules/InboundMedia/Http/Controllers/PublicUploadController.php`
- `apps/api/app/Modules/MediaProcessing/Models/EventMedia.php`
- `apps/api/app/Modules/MediaProcessing/Jobs/GenerateMediaVariantsJob.php`
- `apps/api/app/Modules/MediaProcessing/Jobs/RunModerationJob.php`
- `apps/api/app/Modules/MediaProcessing/Jobs/PublishMediaJob.php`
- `apps/api/app/Modules/MediaProcessing/Queries/ListModerationMediaQuery.php`
- `apps/api/routes/channels.php`

### Gaps importantes do estado atual

Antes de plugar IA, estes gaps precisam ser tratados com clareza:

1. `GenerateMediaVariantsJob` ja gera `fast_preview`, `thumb`, `gallery` e `wall`, e essa variante ja entrou como entrada preferencial de safety e VLM; o proximo ajuste e expandir caches e warm paths para carga real.
2. `DownloadInboundMediaJob`, `ProcessInboundWebhookJob` e `NormalizeInboundMessageJob` do pipeline legado ainda estao em scaffold.
3. `RunModerationJob` ja trabalha com safety real via `AnalyzeContentSafetyJob`, e a central de moderacao ja le a ultima avaliacao estruturada; o proximo gap e expor a superficie de reprocessamento e historico completo no backoffice.
4. A aprovacao precoce no upload foi removida, mas a doc precisa deixar explicito que publish bloqueia em `safety` e, opcionalmente, em VLM `gate`.
5. `event_media.original_filename` ja nao precisa mais ser o path canonico, mas o campo legado ainda existe e deve ser deprecado de vez.
6. `media_processing_runs` ja guarda `queue_name`, `worker_ref`, `failure_class`, custo e runs de reprocessamento; o proximo gap e padronizar `provider_version/model_snapshot` em providers faciais reais alem do `noop`.
7. `docker-compose.yml` ja usa `pgvector/pgvector:pg16`, e o init local ja cria `vector`; o que ainda falta e endurecer isso nas migrations e nos ambientes de deploy.
8. O bucket de midia no `minio-init` e publico por default. Isso nao serve para crops faciais e selfies.
9. O Horizon ja separa `media-fast` e `face-index`, e o app agora loga `LongWaitDetected`; o proximo gap operacional e endurecer batch/warm pool quando houver VLM batch e busca publica por selfie em carga real.
10. O `moderation_mode` ja foi corrigido para `none`, `manual` e `ai`; o proximo passo e impedir que qualquer contrato novo do produto volte a usar `auto`.

## Por que a stack atual e boa para esse fluxo

### O que ja encaixa muito bem

- `Laravel + Horizon`: excelente para orquestrar filas com jobs pequenos e idempotentes.
- `PostgreSQL`: ja e a fonte principal do dominio. Adicionar `pgvector` evita criar outro banco so para embeddings.
- `Redis`: continua sendo a camada correta para filas, retries, locks e desacoplamento.
- `Reverb`: permite mostrar progresso do pipeline na moderacao sem polling pesado.
- `MinIO/S3`: bom para separar midia publica de artefatos privados.
- `React moderation page`: ja existe, entao a triagem humana nao precisa nascer do zero.
- `Analytics` e `Audit`: podem receber metricas e trilhas do pipeline de IA.
- `Pennant` e flags por plano: bom para liberar Face Search ou VLM por organizacao/plano.

### O que nao faz sentido

Nao faz sentido colocar detector facial, embedding e VLM rodando dentro do processo PHP do Laravel. O encaixe certo e:

- Laravel como orquestrador e estado de negocio;
- servicos especializados, preferencialmente em Python, para inferencia;
- contratos tipados entre Laravel e esses servicos;
- persistencia e decisao final sempre no dominio do Evento Vivo.

## Onde cada responsabilidade deve morar

### Estrutura de modulos sugerida

No backend, a implementacao mais coerente com o padrao do repositorio seria:

```text
apps/api/app/Modules/
|-- ContentModeration/
|   |-- Actions/
|   |-- Enums/
|   |-- Events/
|   |-- Http/
|   |-- Jobs/
|   |-- Models/
|   |-- Services/
|   |-- Providers/
|   `-- routes/
|-- FaceSearch/
|   |-- Actions/
|   |-- DTOs/
|   |-- Events/
|   |-- Http/
|   |-- Jobs/
|   |-- Models/
|   |-- Queries/
|   |-- Services/
|   |-- Providers/
|   `-- routes/
`-- MediaIntelligence/
    |-- Actions/
    |-- DTOs/
    |-- Events/
    |-- Http/
    |-- Jobs/
    |-- Models/
    |-- Services/
    |-- Providers/
    `-- routes/
```

No frontend, a extensao natural seria:

```text
apps/web/src/modules/
|-- moderation/          # evolui a central atual
|-- face-search/         # busca por selfie e resultados
`-- media-intelligence/  # configuracao de prompt e regras
```

Quando esses modulos nascerem de verdade, tambem faz sentido:

- registrar providers em `apps/api/config/modules.php`;
- atualizar `docs/modules/module-map.md`;
- registrar rotas novas em `apps/web/src/App.tsx`.

### `MediaProcessing`

Continua como dono de:

- `event_media`;
- `event_media_variants`;
- status base do pipeline;
- decisao final de publicacao;
- override humano de `approved/rejected/pending`;
- eventos `MediaPublished`, `MediaRejected`, `MediaDeleted`.

Nao deve virar deposito de regra facial, VLM e safety.

### Novo modulo `ContentModeration`

Responsabilidade:

- nudez;
- violencia;
- gore;
- categorias de risco que possam bloquear a foto;
- politica de hard-block, review ou pass;
- historico de avaliacoes de safety.

Nao deve:

- gerar embeddings faciais;
- decidir legenda;
- conhecer regra de busca por pessoa.

### Novo modulo `FaceSearch`

Responsabilidade:

- detectar faces;
- recortar cada face;
- medir qualidade;
- gerar embedding por face;
- persistir embedding vinculado a `event_media`, `event_id` e bounding box;
- executar busca vetorial por evento.

Nao deve:

- aprovar ou reprovar nudez/violencia;
- gerar caption;
- publicar midia.

### Novo modulo `MediaIntelligence`

Responsabilidade:

- aplicar prompt do cliente;
- decidir `approved/review/rejected` no contexto semantico do evento;
- gerar frase curta;
- gerar tags e metadados estruturados;
- manter versao de prompt e historico de respostas do VLM.

Nao deve:

- indexar rosto;
- aplicar politica de safety base;
- publicar direto no wall.

### `Gallery`, `Wall`, `Hub` e `Play`

Permanecem consumidores do resultado final. Eles nao devem conhecer detalhes de embedding, bbox, prompt ou scores de nudity.

## Arquitetura recomendada por servico

### Face search

Camada recomendada:

- MVP: `CompreFace` atras de um adapter interno do dominio;
- arquitetura-alvo: servico facial interno atras de `FaceDetectionProviderInterface` e `FaceEmbeddingProviderInterface`;
- implementacao vetorial inicial: `pgvector` no mesmo PostgreSQL do produto;
- trilha explicita de escala: `FaceVectorStoreInterface`, com implementacao futura em `Qdrant`.

Decisao chave:

**nao indexar a foto inteira para busca por pessoa.**

O fluxo correto e:

1. detectar todos os rostos na foto;
2. recortar cada face;
3. gerar um embedding por face;
4. salvar cada embedding ligado a `event_media_id`, `event_id` e `bbox`;
5. quando o usuario enviar uma selfie, gerar embedding dessa selfie;
6. buscar somente nas faces daquele evento;
7. colapsar por foto e reranquear.

Regra operacional:

- `FaceSearch` e non-blocking por default;
- indexacao facial nao participa da politica de aprovacao;
- se o evento estiver com `FaceSearch` desligado, `IndexMediaFacesJob` deve ser pulado e `face_index_status=skipped`;
- se `CompreFace` for usado no MVP, ele entra so como implementacao das interfaces internas, nunca como contrato do dominio.

### VLM

Camada recomendada:

- `Qwen2.5-VL-3B-Instruct` como default custo/beneficio;
- `Qwen2.5-VL-7B-Instruct` para mais qualidade em planos premium ou uso de backoffice;
- serving padronizado via `vLLM`, com API OpenAI-compatible;
- resposta sempre estruturada por JSON schema;
- `GPT-5.4 nano` como modo alternativo por API, sem mudar o contrato do dominio;
- prompt versionado por evento ou template do cliente.

Exemplo de payload esperado do VLM:

```json
{
  "decision": "approve",
  "review": false,
  "reason": "Pessoa sorrindo em contexto compativel com a festa.",
  "short_caption": "Memorias que ficam para sempre.",
  "tags": ["celebracao", "retrato", "familia"]
}
```

Regra operacional:

- por default o VLM nasce em modo `enrich_only`, gerando caption, tags e metadados;
- ele so entra no caminho critico quando a politica do evento ativar `prompt decides approval`;
- nesse modo gating, precisa ter timeout curto e fallback para `pending/review`, nunca travando indefinidamente o publish.

### Safety moderation

Camada recomendada:

- modulo proprio de dominio, com `ContentModerationProviderInterface`;
- provider inicial recomendado: `omni-moderation-latest`;
- thresholds versionados por evento e por tipo de evento;
- persistencia de `provider_version`, `model_snapshot` e `threshold_version`;
- a resposta de safety nunca fica misturada com face search.

A saida de safety deve ser algo como:

```json
{
  "decision": "pass",
  "blocked": false,
  "review": false,
  "categories": {
    "nudity": 0.01,
    "violence": 0.02
  },
  "reason_codes": []
}
```

## Fluxo recomendado de ponta a ponta

### Fast lane vs heavy lane na stack atual

Para o Evento Vivo, a melhor leitura da stack atual e esta:

- o upload HTTP deve continuar curto;
- o fast lane deve rodar em fila de alta prioridade, nao dentro da mesma request;
- a percepcao de rapidez vem de `Reverb` e da atualizacao progressiva da UI;
- o heavy lane fica isolado para nao contaminar publish nem latencia do painel.

Traduzao pratica da ideia de "trilho rapido":

1. recebe a foto;
2. salva original;
3. dispara fast preview;
4. dispara safety;
5. dispara VLM rapido para decisao curta + frase curta;
6. responde o upload imediatamente e atualiza a tela em etapas.

Traduzao pratica do "trilho pesado":

1. indexacao facial completa;
2. embeddings por face;
3. reranking e manutencao do indice;
4. reprocessamentos;
5. analytics.

### Separacao entre trilho critico e trilho de enriquecimento

Trilho critico:

1. ingest;
2. variantes;
3. deduplicacao leve por `pHash`, quando habilitada;
4. safety moderation;
5. VLM gating, apenas quando a politica do evento exigir;
6. decisao final;
7. publish.

Trilho de enriquecimento:

1. face index;
2. caption e tags em modo `enrich_only`;
3. metadados adicionais;
4. reprocessamentos seletivos por versao de modelo.

Regra operacional:

- `FaceSearch` nunca bloqueia publicacao;
- caption e tags tambem nao bloqueiam publicacao por default;
- o unico enriquecimento que pode virar gate e o VLM, e so quando o evento pedir isso explicitamente.

### 1. Entrada da foto

Entradas possiveis no sistema atual:

- upload publico;
- inbound por WhatsApp;
- outros webhooks no futuro.

Fluxo:

1. persistir `event_media` com `processing_status=received`;
2. salvar original em storage;
3. criar `media_processing_runs` para `ingest`;
4. emitir evento interno `MediaReceived`.

Importante:

- mesmo em evento com `moderation_mode=none`, a foto nao deve nascer como `approved` no upload; a aprovacao acontece ao final do pipeline base;
- a foto deve nascer como `moderation_status=pending` ate safety e regra do VLM terminarem.

### 2. Geracao de preview rapido e variantes canonicas

Job sugerido:

- `GenerateMediaVariantsJob` na fila `media-fast`.

Saida:

- `fast_preview` ou `preview_512`, usado por safety e VLM rapido;
- `thumb`;
- `gallery`;
- `wall`;
- eventualmente `face_source` se voce quiser uma variante padrao para deteccao.
- `perceptual_hash`, quando a estrategia de deduplicacao estiver ativa.

Observacao:

- o repositorio ja tem `Intervention Image`, entao essa etapa pode continuar no PHP sem depender de GPU.
- aqui tambem e o melhor ponto para calcular `pHash` e descartar duplicatas obvias antes de chamar IA mais cara.
- se quiser reduzir latencia perceptivel, gere o `fast_preview` primeiro e deixe `gallery/wall` como continuacao do mesmo job ou de um job encadeado.

### 3. Moderacao de safety

Job sugerido:

- `AnalyzeContentSafetyJob` na fila `media-safety`.

Regras:

- se `decision=block`, a imagem vai para `rejected` e o pipeline publico para;
- se `decision=review`, a imagem vai para `pending` e aguarda humano;
- se `decision=pass`, o pipeline pode seguir.

Esta camada decide seguranca. Ela nao decide se o rosto e buscavel e nao decide caption.

### 4. Avaliacao do VLM rapido

Job sugerido:

- `EvaluateMediaPromptJob` na fila `media-vlm`.

Pre-condicao:

- safety nao bloqueou a imagem.

Entrada:

- `fast_preview` ou outra variante curta;
- configuracao do evento;
- prompt do cliente;
- politica do evento dizendo se o VLM e `enrich_only` ou `gate`.

Regras:

- preferir uma unica chamada estruturada para `decision`, `reason`, `short_caption` e `tags`;
- se o VLM estiver em `enrich_only`, ele sai do caminho critico e roda depois do publish;
- se estiver em `gate`, use timeout curto e fallback para `pending/review`;
- nunca deixe o pipeline preso indefinidamente esperando resposta do modelo.

### 5. Agregacao da decisao final e publicacao

Job sugerido:

- `FinalizeMediaDecisionJob` na fila `media-process` ou `media-publish`.

Matriz sugerida:

1. se `moderation_mode=none` -> `moderation_status=approved` e `PublishMediaJob`, sem gate de moderacao;
2. se `moderation_mode=manual` -> `moderation_status=pending`, mesmo que safety e VLM estejam limpos;
3. se `moderation_mode=ai` e `safety=block` -> `moderation_status=rejected`;
4. se `moderation_mode=ai` e `safety=review` -> `moderation_status=pending`;
5. se `moderation_mode=ai` e `vlm_mode=gate` com `vlm=review` -> `moderation_status=pending`;
6. se `moderation_mode=ai` e `vlm_mode=gate` com `vlm=reject` -> `moderation_status=rejected` ou `pending`, conforme politica;
7. se `moderation_mode=ai` e tudo passou -> `moderation_status=approved` e `PublishMediaJob`.

Ponto importante:

- `FaceSearch` nao participa da aprovacao;
- facial e uma capacidade de indexacao e busca, nao uma politica de moderacao;
- se o VLM estiver em `enrich_only`, `FinalizeMediaDecisionJob` ignora a ausencia dessa etapa.

### 6. Enriquecimento assincrono

Jobs sugeridos:

- `IndexMediaFacesJob` na fila `face-index`;
- `EvaluateMediaPromptJob` na fila `media-vlm`, quando o VLM estiver em `enrich_only`;
- `RefreshMediaIntelligenceJob` para reprocessamento seletivo por nova versao de modelo ou prompt.

Fluxo:

1. carregar imagem fonte;
2. detectar faces;
3. descartar faces muito pequenas, borradas ou com baixa qualidade;
4. gerar crop por face;
5. gerar embedding por face;
6. salvar linhas em `event_media_faces`;
7. marcar `face_index_status=indexed` ou `skipped`;
8. gerar caption/tags quando o VLM estiver em `enrich_only`.

### 7. Publicacao e realtime

Se a foto terminar em `approved`:

- `PublishMediaJob` publica;
- `Gallery` e `Wall` continuam consumindo exatamente como hoje;
- a central de moderacao recebe update pelo canal ja existente;
- o wall continua reagindo so a midia `approved + published`.

## Filas recomendadas

As filas atuais ja estao bem separadas para o pipeline base, mas IA vai pedir mais isolamento.

### Filas novas sugeridas

| Fila | Motivo |
| --- | --- |
| `media-fast` | preview rapido, consolidacao inicial e primeira atualizacao perceptivel |
| `media-safety` | Safety nao deve competir com variantes |
| `face-index` | Deteccao/embedding podem ser caros e precisam isolamento |
| `media-vlm` | VLM rapido ou batch, sem disputar com publish |
| `face-search` | opcional, se buscas publicas forem assicronas |

### Filas que devem continuar como estao

| Fila | Papel |
| --- | --- |
| `webhooks` | entrada |
| `media-download` | download |
| `media-process` | etapas de pipeline nao sensiveis a latencia e retrocompatibilidade do fluxo legado |
| `media-publish` | publicacao |
| `broadcasts` | realtime |

### Recomendacao operacional

Nao coloque VLM e face index dentro da fila `media-process`. Isso piora o tempo de resposta do fluxo base e pode gerar backlog justamente na etapa que prepara a foto para a experiencia ao vivo.

Medidas operacionais minimas:

- supervisor dedicado de Horizon para `media-fast`, com capacidade reservada durante eventos;
- DLQ ou estrategia de falha permanente por fila;
- `retry` curto para safety e VLM gating;
- micro-batching em workers de face embedding e VLM;
- warm pool de inferencia em horario de evento para reduzir cold start;
- reprocessamento seletivo por etapa e por versao de modelo, sem refazer o pipeline inteiro.

Leitura da stack atual:

- o repositorio ja moveu variantes e decisao final rapida para `media-fast`;
- para o desenho definitivo, ainda vale separar tambem um supervisor de heavy lane quando `face-index` e `media-vlm` entrarem em producao.

## Modelagem de banco recomendada

## Ajustes nas tabelas existentes

### `event_media`

Campos adicionais recomendados:

| Campo | Tipo | Motivo |
| --- | --- | --- |
| `original_disk` | string | separar storage real do campo legado |
| `original_path` | string | path real do arquivo |
| `client_filename` | string | nome original enviado pelo usuario |
| `perceptual_hash` | string | deduplicacao leve antes de IA |
| `duplicate_group_key` | string nullable | agrupar candidatos duplicados no evento |
| `safety_status` | string | `queued/pass/review/block/failed` |
| `face_index_status` | string | `queued/processing/indexed/skipped/failed` |
| `vlm_status` | string | `queued/completed/review/rejected/skipped/failed` |
| `decision_source` | string | `none_mode`, `manual_review`, `ai_safety`, `ai_vlm`, `user_override` |
| `decision_overridden_at` | timestamp nullable | trilha de override |
| `decision_overridden_by_user_id` | fk nullable | quem sobrescreveu a decisao |
| `decision_override_reason` | text nullable | motivo da intervencao humana |
| `pipeline_version` | string | rastrear versao do fluxo |
| `last_pipeline_error_code` | string | observabilidade |
| `last_pipeline_error_message` | text | observabilidade |

Observacao:

- faca migracao aditiva;
- backfill `original_path` a partir de `original_filename`;
- depreque `original_filename` como path ao longo do tempo.

### `media_processing_runs`

Vale muito mais expandir a tabela existente do que jogar fora.

Observacao do schema atual:

- `attempts`, `started_at` e `finished_at` ja existem no repositorio atual;
- o que falta e enriquecer a tabela para rastrear provider, fila, worker, custo e output estruturado.

Campos adicionais recomendados:

| Campo | Tipo | Motivo |
| --- | --- | --- |
| `stage_key` | string | `ingest`, `variants`, `safety`, `face_index`, `vlm`, `publish` |
| `provider_key` | string | qual motor executou |
| `provider_version` | string | versao do provider ou SDK/engine |
| `model_key` | string | qual modelo executou |
| `model_snapshot` | string | snapshot ou release usada na inferencia |
| `input_ref` | string | referencia do asset processado |
| `decision_key` | string | `pass/review/block`, `approve/reject`, etc |
| `queue_name` | string | fila real usada na execucao |
| `worker_ref` | string | pod, hostname ou worker logical id |
| `result_json` | jsonb | payload estruturado resumido |
| `metrics_json` | jsonb | latencia, tokens, faces detectadas |
| `cost_units` | numeric | custo interno estimado por run |
| `idempotency_key` | string | evitar duplicidade de execucao |
| `failure_class` | string | `transient`, `permanent`, `policy` |

### Novas tabelas de dominio

#### `event_content_moderation_settings`

Configuracao por evento para safety.

| Campo | Tipo |
| --- | --- |
| `id` | bigserial |
| `event_id` | fk unique |
| `provider_key` | string |
| `mode` | string |
| `threshold_version` | string |
| `hard_block_thresholds_json` | jsonb |
| `review_thresholds_json` | jsonb |
| `fallback_mode` | string |
| `enabled` | boolean |
| `created_at`, `updated_at` | timestamps |

#### `event_media_safety_evaluations`

Historico de avaliacoes de safety por foto.

| Campo | Tipo |
| --- | --- |
| `id` | bigserial |
| `event_id` | fk |
| `event_media_id` | fk |
| `provider_key` | string |
| `provider_version` | string |
| `model_key` | string |
| `model_snapshot` | string |
| `threshold_version` | string |
| `decision` | string |
| `blocked` | boolean |
| `review_required` | boolean |
| `category_scores_json` | jsonb |
| `reason_codes_json` | jsonb |
| `raw_response_json` | jsonb |
| `completed_at` | timestamp |
| `created_at`, `updated_at` | timestamps |

#### `event_face_search_settings`

Configuracao por evento para face search.

| Campo | Tipo |
| --- | --- |
| `id` | bigserial |
| `event_id` | fk unique |
| `provider_key` | string |
| `embedding_model_key` | string |
| `vector_store_key` | string |
| `enabled` | boolean |
| `min_face_size_px` | integer |
| `min_quality_score` | numeric |
| `search_threshold` | numeric |
| `top_k` | integer |
| `allow_public_selfie_search` | boolean |
| `selfie_retention_hours` | integer |
| `created_at`, `updated_at` | timestamps |

#### `event_media_faces`

Tabela central para busca por pessoa.

| Campo | Tipo | Observacao |
| --- | --- | --- |
| `id` | bigserial | |
| `event_id` | fk | filtro principal de busca |
| `event_media_id` | fk | foto de origem |
| `face_index` | integer | ordem da face na foto |
| `bbox_x` | integer | |
| `bbox_y` | integer | |
| `bbox_w` | integer | |
| `bbox_h` | integer | |
| `detection_confidence` | numeric | |
| `quality_score` | numeric | |
| `sharpness_score` | numeric | opcional |
| `face_area_ratio` | numeric | opcional |
| `pose_yaw` | numeric | opcional |
| `pose_pitch` | numeric | opcional |
| `pose_roll` | numeric | opcional |
| `searchable` | boolean | nao expor faces ruins ou bloqueadas |
| `crop_disk` | string | privado |
| `crop_path` | string | privado |
| `embedding_model_key` | string | |
| `embedding_version` | string | controlar troca de pesos e calibracao |
| `vector_store_key` | string | `pgvector` agora, `qdrant` depois |
| `vector_ref` | string nullable | id externo quando o vetor sair do Postgres |
| `face_hash` | string | deduplicacao e idempotencia por crop |
| `is_primary_face_candidate` | boolean | ajuda no reranking e na UX |
| `embedding` | `vector(512)` nullable | ajustar dimensao conforme modelo |
| `created_at`, `updated_at` | timestamps | |

Indices recomendados:

```sql
CREATE EXTENSION IF NOT EXISTS vector;

CREATE INDEX idx_event_media_faces_event_id
    ON event_media_faces (event_id);

CREATE INDEX idx_event_media_faces_media_id
    ON event_media_faces (event_media_id);

CREATE INDEX idx_event_media_faces_searchable
    ON event_media_faces (event_id, searchable);

CREATE INDEX idx_event_media_faces_embedding_hnsw
    ON event_media_faces
    USING hnsw (embedding vector_cosine_ops);
```

Observacao:

- na fase `pgvector`, `embedding` fica preenchido localmente no Postgres;
- na fase `Qdrant`, `embedding` pode virar opcional e `vector_ref` passa a apontar para a projection externa;
- em qualquer fase, o registro canonico da face continua no Postgres.

#### `event_media_intelligence_settings`

Configuracao por evento para VLM.

| Campo | Tipo |
| --- | --- |
| `id` | bigserial |
| `event_id` | fk unique |
| `provider_key` | string |
| `model_key` | string |
| `enabled` | boolean |
| `mode` | string |
| `prompt_version` | string |
| `approval_prompt` | text |
| `caption_style_prompt` | text |
| `response_schema_version` | string |
| `timeout_ms` | integer |
| `fallback_mode` | string |
| `require_json_output` | boolean |
| `created_at`, `updated_at` | timestamps |

#### `event_media_vlm_evaluations`

Historico da camada semantica.

| Campo | Tipo |
| --- | --- |
| `id` | bigserial |
| `event_id` | fk |
| `event_media_id` | fk |
| `provider_key` | string |
| `provider_version` | string |
| `model_key` | string |
| `model_snapshot` | string |
| `prompt_version` | string |
| `response_schema_version` | string |
| `mode_applied` | string |
| `decision` | string |
| `review_required` | boolean |
| `reason` | text |
| `short_caption` | string |
| `tags_json` | jsonb |
| `raw_response_json` | jsonb |
| `tokens_input` | integer |
| `tokens_output` | integer |
| `completed_at` | timestamp |
| `created_at`, `updated_at` | timestamps |

#### `event_face_search_requests` (opcional)

Se quiser auditoria de buscas e telemetria:

| Campo | Tipo |
| --- | --- |
| `id` | bigserial |
| `event_id` | fk |
| `requester_type` | string |
| `requester_user_id` | fk nullable |
| `status` | string |
| `consent_version` | string |
| `selfie_storage_strategy` | string |
| `faces_detected` | integer |
| `query_face_quality_score` | numeric |
| `top_k` | integer |
| `best_distance` | numeric |
| `result_photo_ids_json` | jsonb |
| `created_at` | timestamp |
| `expires_at` | timestamp |

Privacidade:

- evite guardar a selfie original permanentemente;
- prefira `memory_only` ou `ephemeral_object`; use `retained` so com base legal e politica clara;
- se armazenar, use bucket privado e TTL curto;
- idealmente guarde so telemetria e descarte a imagem.

## Busca facial: logica recomendada

### Regra principal

Para achar uma pessoa em centenas de fotos:

- nao gere embedding da foto inteira;
- gere embedding de cada face detectada;
- faca busca apenas em `event_media_faces` do `event_id` correspondente.

### Consulta recomendada

Fluxo da selfie:

1. detectar face principal da selfie;
2. validar qualidade minima;
3. gerar embedding;
4. buscar top-k no evento;
5. reranquear;
6. colapsar por `event_media_id`.

Exemplo de consulta:

```sql
WITH candidate_faces AS (
    SELECT
        event_media_id,
        id AS face_id,
        quality_score,
        face_area_ratio,
        embedding <=> :query_embedding AS distance
    FROM event_media_faces
    WHERE event_id = :event_id
      AND searchable = true
    ORDER BY embedding <=> :query_embedding
    LIMIT 200
)
SELECT
    event_media_id,
    MIN(distance) AS best_distance,
    MAX(quality_score) AS best_quality,
    MAX(face_area_ratio) AS best_area
FROM candidate_faces
GROUP BY event_media_id
ORDER BY best_distance ASC, best_quality DESC, best_area DESC
LIMIT 50;
```

### Reranking recomendado

Depois da busca vetorial bruta, vale reranquear por:

- menor distancia;
- maior `quality_score`;
- maior `face_area_ratio`;
- fotos publicadas antes de pendentes;
- opcionalmente diversidade de resultados.

### Regras de UX

- se a selfie tiver zero faces validas, retorne erro claro;
- se tiver varias faces, peca para o usuario reenviar;
- para busca publica, retorne so fotos `approved + published`;
- para uso interno, permita opcionalmente incluir `pending`.

## Contratos e integracao entre servicos

### Recomendacao de contratos

Mesmo com servicos externos ou internos, o dominio do Evento Vivo deve falar com interfaces proprias. Exemplo:

- `ContentModerationProviderInterface`
- `FaceDetectionProviderInterface`
- `FaceEmbeddingProviderInterface`
- `FaceVectorStoreInterface`
- `VisualReasoningProviderInterface`

Regra de contrato:

- modelos centrais do dominio nao devem carregar campos provider-specific;
- qualquer detalhe de `OpenAI`, `Qwen`, `CompreFace`, `pgvector` ou `Qdrant` fica confinado ao adapter, DTO tecnico ou tabela de auditoria;
- o dominio persiste `provider_key`, `model_key`, `model_snapshot` e versoes, mas nao payloads acoplados ao SDK.

Esses contratos podem nascer:

- dentro de cada modulo em `Support` ou `Services`;
- ou em `apps/api/app/Shared/Contracts` quando forem realmente transversais.

`spatie/laravel-data` encaixa muito bem para DTOs dessas respostas.

### Onde tipar payloads compartilhados

O repositorio ja tem:

- `packages/contracts`
- `packages/shared-types`

Hoje esses pacotes ainda estao leves. Eles sao um bom lugar para tipar:

- schema de resposta do VLM;
- schema de resposta do face service;
- payloads publicos de selfie search, se o frontend consumir diretamente.

## Infra e storage

### Implementacao vetorial inicial: PostgreSQL + pgvector

Recomendacao:

1. manter a imagem do banco em uma variante com `pgvector`;
2. manter `CREATE EXTENSION IF NOT EXISTS vector;` no init local;
3. garantir isso tambem nas migrations e no provisionamento de producao.

Hoje o estado real e:

- `docker-compose.yml` usa `pgvector/pgvector:pg16`;
- `docker/postgres/init/01-extensions.sql` ja cria `vector`.

Ou seja: `pgvector` ja esta provisionado no ambiente local atual.

Decisao arquitetural:

- `pgvector` e a implementacao inicial recomendada, nao a decisao definitiva de arquitetura;
- a simplicidade transacional dele combina com o estado atual do monorepo;
- a aplicacao precisa nascer com `FaceVectorStoreInterface` para nao acoplar o dominio ao banco.

### Linha de evolucao vetorial: `pgvector` -> `Qdrant`

Fase 1:

- `pgvector` no mesmo Postgres do produto;
- joins simples com `event_id`, `event_media_id` e status da foto;
- menor custo operacional para o time atual.

Fase 2:

- `pgvector` ainda como default;
- mais particionamento por evento/tempo quando o volume crescer;
- tuning de HNSW, lotes de indexacao e jobs de manutencao.

Fase 3:

- `Qdrant` entra como implementacao de `FaceVectorStoreInterface`;
- o indice vetorial vira projection/search index;
- o Postgres continua como source of truth de faces, fotos, permissoes e ciclo de vida.

Regra de desenho para `Qdrant`:

- uma colecao por modelo de embedding, nao uma colecao por evento;
- isolamento por `tenant_id`, `organization_id` e `event_id` via payload/filter;
- `vector_ref` e `vector_store_key` passam a identificar a projection externa.

### Buckets e discos

Hoje o setup do MinIO cria bucket publico. Para IA sensivel, separe:

- `eventovivo-media-public`: galeria/wall/thumbs;
- `eventovivo-media-private`: originais privados quando necessario;
- `eventovivo-ai-private`: face crops, selfies temporarias, artefatos sensiveis.

No Laravel, adicione discos dedicados. Nao use o mesmo disco publico para face crops.

### Servicos internos e serving padronizado

Topologia recomendada:

- API Laravel orquestra;
- face service interno acessa storage por URL assinada ou path controlado;
- VLM service interno acessa asset seguro;
- provider de safety fica atras de adapter do dominio;
- nenhum job coloca binario de imagem no payload da fila, so IDs e referencias.

Padrao recomendado:

- `vLLM` como serving OpenAI-compatible para `Qwen2.5-VL`;
- JSON schema como contrato obrigatorio da resposta do VLM;
- micro-batching e warm pool nos workers com GPU;
- circuit breaker por provider para evitar backlog em cascata.

## Frontend: onde isso encaixa

### Moderacao

A tela atual de moderacao ja pode evoluir para mostrar:

- badge de `safety_status`;
- motivo do safety;
- decisao e razao do VLM;
- caption sugerida;
- quantidade de faces indexadas;
- botao para reprocessar safety, VLM ou facial.

### Configuracao por evento

Superficie administrativa atual:

- `ContentModeration`, `FaceSearch` e `MediaIntelligence` estao expostos por cards dedicados na aba de moderacao do detalhe do evento;
- essa decisao segue melhor o frontend real do repositorio do que criar tres paginas administrativas isoladas agora.

Evolucao natural futura:

- se a configuracao crescer muito, `FaceSearch`, `ContentModeration` e `MediaIntelligence` podem ganhar paginas proprias sem quebrar os endpoints ja criados.

### Busca publica por selfie

Se o produto quiser expor busca para convidado, o encaixe mais natural e:

- pagina publica nova no frontend;
- rota ligada ao `Hub` do evento, por exemplo `/e/:slug/find-me`;
- upload de selfie com consentimento explicito;
- busca sincronizada ou assicrona curta;
- retorno de fotos publicadas do proprio evento.

Estado atual no repositorio:

- bootstrap publico implementado em `/public/events/{slug}/face-search`;
- busca publica implementada em `/public/events/{slug}/face-search/search`;
- rota publica do frontend implementada em `/e/:slug/find-me`;
- implementacao atual roda de forma sincronica sobre `event_media_faces`, sem fila dedicada de busca publica;
- a evolucao para busca assincrona curta fica reservada para quando a carga real justificar.

## Observabilidade, auditoria e analytics

### O que logar

- tempo de cada etapa;
- provider/modelo usado;
- quantidade de faces detectadas;
- quantidade de fotos bloqueadas por safety;
- taxa de review do VLM;
- distancia media dos matches faciais;
- falhas por provider.

### Onde aproveitar a stack atual

- `media_processing_runs` para trilha operacional por foto;
- `Audit` para acao humana de override;
- `Analytics` para metricas agregadas de uso;
- `Pulse` e `Horizon` para backlog e latencia de filas;
- `Reverb` para atualizacao em tempo real do painel.

### Eventos de dominio sugeridos

- `MediaSafetyEvaluated`
- `MediaFacesIndexed`
- `MediaPromptEvaluated`
- `MediaDecisionFinalized`
- `FaceSearchRequested`
- `FaceSearchCompleted`

## Fallback behavior

Cada etapa precisa nascer com timeout, retry e destino de falha bem definidos.

| Etapa | Timeout | Retry | Destino em falha |
| --- | --- | --- | --- |
| variantes | medio | 2-3 | `failed` tecnico |
| safety | curto | 1-2 | `moderation_status=pending` para review manual, nunca `approved` |
| VLM em `gate` | curto | 1 | `moderation_status=pending` para review manual, nunca `approved` |
| VLM em `enrich_only` | medio | 1-2 | `caption` vazia e `vlm_status=failed`, sem bloquear publish |
| face index | medio | 1-2 | `face_index_status=failed`, sem bloquear publish |
| selfie search publica | curto | 0-1 | resposta amigavel pedindo nova tentativa |

Regras minimas:

- usar circuit breaker por provider;
- registrar `failure_class` e `last_pipeline_error_code`;
- mandar falhas permanentes para DLQ ou fila equivalente;
- expor reprocessamento seletivo por etapa no backoffice.

## Model governance

Essa arquitetura precisa tratar modelo como ativo governado, nao como detalhe de implementacao.

Regras recomendadas:

1. manter registro de `provider_key`, `model_key`, `model_snapshot` e `version`;
2. nao trocar modelo em producao sem changelog, aprovacao tecnica e plano de rollback;
3. calibrar thresholds por categoria de evento, nao globalmente;
4. versionar prompt, response schema e threshold em tabelas de configuracao;
5. permitir rollout gradual por organizacao, plano ou evento via feature flag.

## Licensing & provenance

Isso e especialmente importante na camada facial.

Regras minimas:

1. nao aprovar modelo facial em producao sem validacao juridica;
2. separar licenca do codigo, licenca dos pesos e origem do dataset;
3. registrar `embedding_model_key`, `embedding_version`, origem e link interno da aprovacao;
4. impedir troca direta de pesos por desenvolvedor sem revisao;
5. tratar `CompreFace`, `InsightFace`, `DeepFace` ou equivalentes como engines avaliadas, nunca como licenca presumida.

## Threshold calibration per event type

Os thresholds nao devem nascer unicos para toda a plataforma.

Configuracao inicial recomendada:

- infantil/escolar: safety mais conservador e VLM mais restritivo;
- corporativo: maior peso para dress code, contexto de marca e violencia;
- casamento/festa social: safety padrao e VLM focado em contexto positivo;
- balada/show: safety com calibracao cuidadosa para falso positivo em pele, luz e fumaca.

Esses thresholds precisam ficar versionados e auditaveis por evento.

## Deletion propagation

Quando a midia for removida, o pipeline de exclusao precisa apagar todos os derivados sensiveis.

Fluxo minimo:

1. `MediaDeleted` dispara job dedicado;
2. apagar variants publicas quando aplicavel;
3. apagar crop facial e selfie temporaria no storage privado;
4. apagar ou invalidar vetor no `pgvector` ou `Qdrant`;
5. limpar cache e resultados precomputados de busca;
6. registrar trilha de auditoria da exclusao.

Estado atual no repositorio:

- `DeleteEventMediaAction` ja dispara cleanup assincrono por `MediaDeleted`;
- `CleanupDeletedMediaArtifactsJob` ja remove original, variants, crops, avaliacoes, projection vetorial local e referencias em `event_face_search_requests`;
- faces da midia sao marcadas como nao-buscaveis imediatamente antes do cleanup pesado.

## Privacidade, seguranca e LGPD

Busca facial mexe com dado biometricamente sensivel. A arquitetura precisa nascer com isso em mente.

Regras minimas recomendadas:

1. crops faciais e selfies em storage privado;
2. TTL curto para selfie do usuario;
3. consentimento explicito na busca publica;
4. limite de busca por evento, nunca global por plataforma;
5. trilha de auditoria de busca interna;
6. opcao de exclusao de index facial quando exigido;
7. documentacao de retention e base legal.

Regra pratica:

- a galeria pode ser publica;
- o indice facial nao deve ser publico.

## Melhorias pontuais recomendadas para os proximos ciclos

1. Expor reprocessamento seletivo por etapa na UI administrativa de `ContentModeration`, `MediaIntelligence` e `FaceSearch`.
2. Endurecer o heavy lane de `face-index` e preparar batch/warm pool para VLM e search em carga real.
3. Formalizar DLQ e playbook operacional por fila e provider alem da classificacao de falhas ja entregue.
4. Formalizar feature flags por plano para liberar cada capacidade de IA por organizacao e evento.
5. Endurecer deletion propagation para cache e projection vetorial externa quando `Qdrant` entrar.
6. Consolidar benchmark, observabilidade e SLA real antes de prometer tempos publicos.

## Sequencia recomendada de entrega

### Fase 1 - endurecer o pipeline atual

- variantes reais;
- `FinalizeMediaDecisionAction`;
- ajuste de schema de `event_media`;
- `media_processing_runs` enriquecido;
- storage privado;
- `pgvector` provisionado;
- `pHash`, DLQ e circuit breaker basicos.

### Fase 2 - safety moderation

- evoluir `moderation_mode` para `none/manual/ai`;
- modulo `ContentModeration`;
- settings por evento;
- provider adapter;
- update na moderacao UI;
- bloqueio/review funcional.

Status atual:

- fase entregue para `ContentModeration`, com `OpenAiContentModerationProvider`, endpoint dedicado de settings por evento e card administrativo no detalhe do evento;
- pendente apenas expor reprocessamento e historico mais profundo no backoffice.

### Fase 3 - VLM padronizado

- modulo `MediaIntelligence`;
- `vLLM` com JSON schema;
- modo `enrich_only` primeiro;
- configuracao por evento;
- fallback claro para `pending/review` quando virar gate.

Status atual:

- fase entregue para settings por evento, adapter `vLLM`, `EvaluateMediaPromptJob`, persistencia de `event_media_vlm_evaluations` e leitura da ultima avaliacao na central de moderacao;
- pendente apenas expor reprocessamento e historico mais profundo no backoffice.

### Fase 4 - face index non-blocking

- modulo `FaceSearch`;
- tabela `event_media_faces`;
- `FaceVectorStoreInterface`;
- indexacao por evento;
- controles de qualidade e reprocessamento administrativo.

Status atual:

- fase entregue com `event_face_search_settings`, `event_media_faces`, `event_face_search_requests`, `FaceVectorStoreInterface` e `IndexMediaFacesJob` non-blocking;
- o pipeline ja salva embeddings por face, crops privados e status de indexacao por midia;
- o backend ja suporta reprocessamento seletivo e deletion propagation do lifecycle facial;
- pendente apenas tuning operacional por volume e future-proofing para projection externa.

### Fase 5 - selfie search

- endpoint publico ou administrativo;
- busca vetorial por evento;
- reranking e colapso por foto;
- consentimento e retention.

Status atual:

- fase entregue com endpoint interno, bootstrap e endpoint publico, `event_face_search_requests`, reranking por `event_media_id`, consentimento e retention por evento;
- a UI interna agora existe na aba de moderacao do detalhe do evento, e a UI publica foi aberta em `/e/:slug/find-me`;
- pendente apenas avaliar modo assincrono curto com `Reverb`, rate tuning adicional e observabilidade mais profunda por volume.

### Fase 6 - trilha de escala vetorial

- particionamento mais agressivo em `pgvector`;
- observabilidade de recall/latencia;
- entrada eventual de `Qdrant` sem quebrar o dominio.

Status atual:

- reprocessamento seletivo, exclusao propagada e metricas operacionais ja estao entregues no backend;
- a proxima fronteira desta fase passa a ser tuning de escala, benchmark real e entrada eventual de `Qdrant` sem quebrar os contratos do dominio.

## Conclusao

Com a stack atual, faz muito sentido seguir este desenho:

- Laravel continua sendo o cerebro de orquestracao;
- PostgreSQL ganha `pgvector` como implementacao vetorial inicial e continua sendo a verdade transacional;
- Redis/Horizon seguram o pipeline de forma limpa;
- MinIO/S3 guarda arquivos publicos e privados;
- Face Search, VLM e Safety nascem como modulos separados;
- `MediaProcessing` continua dono do lifecycle da midia, nao dono das tres inteligencias.

O ganho principal desta arquitetura e que ela respeita o que o produto ja tem de melhor hoje:

- pipeline assincrono;
- publish rapido no trilho critico;
- enriquecimento eventual-consistente fora do caminho de publicacao;
- central de moderacao;
- wall realtime;
- modularidade por dominio.

E ao mesmo tempo evita o erro mais caro desse tipo de produto:

- misturar moderacao, busca facial e legenda dentro de um unico bloco de processamento sem fronteiras claras;
- acoplar o dominio a um provider especifico de facial, VLM ou banco vetorial.
