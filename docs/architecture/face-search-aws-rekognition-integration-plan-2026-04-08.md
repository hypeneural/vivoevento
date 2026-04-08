# FaceSearch AWS Rekognition Integration Plan

## Escopo

Este documento define a estrategia recomendada de integracao do `FaceSearch` com `AWS Rekognition` como opcao inicial de provider gerenciado.

Ele parte de tres premissas:

1. o modulo atual do app continua existindo;
2. `CompreFace + pgvector` continuam uteis como fallback, shadow lane e ambiente de calibracao;
3. imagens so devem ser processadas no backend AWS quando o evento tiver a funcao de reconhecimento facial realmente ativa.

Este documento nao substitui:

- `docs/architecture/face-search-dataset-calibration-analysis-2026-04-07.md`
- `docs/architecture/face-search-stack-assessment-and-provider-strategy-2026-04-08.md`
- `docs/architecture/face-search-aws-rekognition-execution-plan-2026-04-08.md`

Ele detalha especificamente:

- porque `AWS Rekognition` virou a opcao inicial;
- como integrar isso na nossa stack Laravel atual;
- o que precisa mudar no modulo;
- como manter custo baixo;
- como fazer rollout com fallback seguro.

---

## Decisao Recomendada

### Recomendacao objetiva

Para a proxima fase do produto, a recomendacao e:

- `AWS Rekognition` como backend gerenciado inicial para eventos com reconhecimento facial ativo;
- `CompreFace + pgvector` mantidos como backend local, fallback e shadow lane;
- integracao feita atras de adapters proprios do modulo;
- entrada padrao por `Bytes`, sem depender de `S3` no MVP;
- `IndexFaces + SearchFacesByImage` como fase 1;
- `CreateUser + AssociateFaces + SearchUsersByImage` como fase 2.

### Motivo

Isso e o melhor compromisso atual entre:

- qualidade;
- velocidade de entrega;
- custo inicial baixo;
- pouca friccao operacional;
- preservacao do que ja construimos.

### O que realmente vale atualizar na doc agora

Pelo estado atual do repositorio, os ajustes de documentacao que realmente fazem diferenca nesta fase sao:

- tratar `AWS Rekognition` como backend de indexacao e busca, nao como `embedding provider`;
- deixar explicito que a AWS so entra quando o evento estiver com reconhecimento ativo;
- separar os thresholds locais do `pgvector` dos thresholds nativos da AWS;
- documentar `Bytes` como caminho padrao e `S3` apenas como excecao;
- documentar `IndexFaces` como happy path e `SearchFacesByImage` como MVP de selfie;
- documentar preflight local de selfie para reduzir custo e consulta ruim;
- documentar `MaxFaces` por perfil de evento, em vez de um default global agressivo;
- documentar `UnindexedFaces`, idempotencia e reconciliacao;
- alinhar retries, timeouts e batches com a topologia real de `Redis + Horizon` que o app ja usa.

O que nao faz sentido prometer como se ja fosse fase 1:

- fila dedicada de query como default para toda busca por selfie;
- `SearchUsersByImage` antes de revisao humana ou consolidacao de faces;
- dependencia obrigatoria de `S3` no MVP;
- abandono do backend local antes de shadow mode e metricas comparativas.

---

## Validacao Em Documentacao Oficial Da AWS

Leitura consolidada da documentacao oficial em `2026-04-08`:

- `Image.Bytes` aceita imagem carregada do filesystem local; via SDK, em geral nao e necessario base64-encodar manualmente os bytes;
- `S3Object` e opcional, nao obrigatorio para o fluxo basico;
- imagens em `Bytes` tem limite de `5 MB`;
- imagens em `S3Object` tem limite de `15 MB`;
- para detectar uma face, a AWS documenta como referencia minima `40x40 px` em imagem `1920x1080`, e proporcionalmente maior em imagens maiores;
- para busca/comparacao facial, a AWS recomenda `50x50 px` ou maior em imagem `1920x1080`, e destaca que faces maiores tendem a produzir resultados mais precisos;
- `SearchFacesByImage` detecta primeiro a maior face da imagem;
- `SearchUsersByImage` tambem usa a maior face da imagem;
- para buscar todas as faces de uma foto de grupo, a propria AWS recomenda `IndexFaces + SearchFaces` por `FaceId`, ou `DetectFaces + crops`;
- `IndexFaces` ja retorna bounding box, landmarks, pose, quality e pode retornar atributos adicionais como `FACE_OCCLUDED`;
- `IndexFaces` retorna `UnindexedFaces` com motivos como `SMALL_BOUNDING_BOX`, `LOW_SHARPNESS`, `LOW_BRIGHTNESS`, `EXTREME_POSE` e `LOW_FACE_QUALITY`;
- `IndexFaces` usa `QualityFilter=AUTO` por default;
- `SearchFacesByImage` e `SearchUsersByImage` usam `QualityFilter=NONE` por default;
- `QualityFilter` exige collection com face model `v3+`;
- `SearchFacesByImage` usa `FaceMatchThreshold` com default `80`;
- `SearchUsersByImage` usa `UserMatchThreshold` com default `80`;
- `AssociateFaces` usa `UserMatchThreshold` com default `75`;
- `SearchUsersByImage` retorna `UnsearchedFaces` para rostos detectados na imagem mas nao usados na busca;
- `AssociateFaces` aceita ate `100` `FaceIds` por request e ate `100` faces por `UserId`;
- `CreateUser` usa token de idempotencia;
- para collections com busca melhor por identidade, a AWS recomenda indexar pelo menos `5` imagens boas por pessoa com variacao de `yaw` e `pitch`;
- se a mesma imagem for indexada na mesma collection com o mesmo `ExternalImageId`, a AWS nao persiste metadata facial duplicada;
- a orientacao oficial de `photo sharing / social media` e usar `IndexFaces` sem restricoes em imagens com multiplas faces quando o objetivo e agrupar fotos pelas pessoas presentes;
- a propria AWS recomenda `retry_mode=standard` para multi-tenant e desaconselha `adaptive` como default;
- a documentacao do Rekognition recomenda `backoff` exponencial, retries e trafego suavizado para evitar throttling;
- quotas de TPS variam por regiao e operacao, e podem precisar de aumento.

Conclusao:

- a AWS encaixa bem no nosso caso de `search by selfie`;
- a AWS nao resolve magicamente crowd e tiny faces extremos;
- ela melhora muito a chance de um MVP profissional de producao, desde que a arquitetura do modulo seja ajustada corretamente.

---

## Como Nossa Stack Esta Hoje

### O que ja faz sentido reaproveitar do repositorio

O repositorio ja tem padroes que fazem sentido manter na integracao AWS:

- `PipelineFailureClassifier` para distinguir falha `transient` e `permanent`;
- `ProviderCircuitBreaker` na camada de providers externos;
- fila Redis unica com `retry_after=240`;
- supervisor `face-index` no Horizon com `timeout=170` e `backoff=20`;
- `IndexMediaFacesJob` ja como ponto claro de entrada da indexacao facial;
- busca por selfie hoje sincronica no request HTTP.

Leitura:

- a fase 1 deve aproveitar esses padroes, nao inventar outra infraestrutura paralela sem necessidade;
- principalmente para `search by selfie`, faz mais sentido manter o fluxo sincronico no MVP e so empurrar para fila se latencia real ou volume exigirem.

### O que temos de bom

A stack atual do modulo ja tem varios elementos certos:

- `FaceDetectionProviderInterface`
- `FaceEmbeddingProviderInterface`
- `FaceVectorStoreInterface`
- configuracao por evento via `EventFaceSearchSetting`
- indexacao assincorna via `IndexMediaFacesJob`
- busca por selfie bem isolada por `event_id`
- benchmark, smoke, throughput e probes reais
- fallback local viavel com `CompreFace`

Isso significa que o modulo ja esta bem preparado para um provider novo.

### O que ainda limita a integracao AWS

Hoje o fluxo do modulo ainda assume majoritariamente:

1. detectar;
2. gerar embedding;
3. salvar no `pgvector`;
4. buscar via distancia vetorial local.

Esse desenho nao cobre bem um backend gerenciado como o Rekognition, porque:

- ele indexa e busca na propria collection;
- ele nao entra naturalmente como apenas "mais um embedder";
- ele funciona melhor como `search backend` completo.

Conclusao:

- nao basta adicionar `aws` ao `provider_key`;
- precisamos subir uma camada acima do provider.

---

## O Que Precisa Mudar Na Arquitetura

## 1. Criar um backend de busca de mais alto nivel

Hoje o modulo separa:

- detection provider
- embedding provider
- vector store

Para AWS, o desenho certo e adicionar:

- `FaceSearchBackendInterface`

Implementacoes iniciais:

- `LocalPgvectorFaceSearchBackend`
- `AwsRekognitionFaceSearchBackend`

Implementacoes futuras:

- `LuxandManagedFaceSearchBackend`

Esse backend precisa assumir operacoes como:

- `ensureEventBackend`
- `indexMedia`
- `deleteMediaFaces`
- `searchBySelfie`
- `healthCheck`
- `deleteEventBackend`

Leitura:

- o backend AWS vira uma unidade de indexacao e busca completa;
- o backend local continua funcionando do jeito atual.

## 2. Separar configuracao local de configuracao AWS

Hoje `EventFaceSearchSetting` e insuficiente para um backend gerenciado.

Campos recomendados novos:

- `recognition_enabled`
- `search_backend_key`
- `fallback_backend_key`
- `routing_policy`
- `shadow_mode_percentage`
- `aws_region`
- `aws_collection_id`
- `aws_collection_arn`
- `aws_face_model_version`
- `aws_search_mode`
- `aws_index_quality_filter`
- `aws_search_faces_quality_filter`
- `aws_search_users_quality_filter`
- `aws_search_face_match_threshold`
- `aws_search_user_match_threshold`
- `aws_associate_user_match_threshold`
- `aws_max_faces_per_image`
- `aws_index_profile_key`
- `aws_detection_attributes_json`
- `delete_remote_vectors_on_event_close`

Leitura:

- `search_threshold` do `pgvector` nao deve ser reutilizado para AWS;
- `FaceMatchThreshold`, `SearchUsersByImage.UserMatchThreshold` e `AssociateFaces.UserMatchThreshold` nao sao o mesmo conceito;
- a AWS trabalha com threshold de `confidence/similarity` em `0-100`;
- isso precisa ficar explicitamente separado.

## 2.1. Semanticas diferentes de threshold

Para evitar erro conceitual no produto, a doc e o banco precisam separar:

- `search_threshold`
  - usado apenas no lane local `pgvector`
  - semantica: `cosine distance`
- `aws_search_face_match_threshold`
  - usado em `SearchFacesByImage`
  - default oficial: `80`
- `aws_search_user_match_threshold`
  - usado em `SearchUsersByImage`
  - default oficial: `80`
- `aws_associate_user_match_threshold`
  - usado em `AssociateFaces`
  - default oficial: `75`

Leitura:

- reaproveitar um nome unico como `aws_user_match_threshold` deixa a configuracao ambigua;
- aqui vale mais clareza operacional do que economia de coluna.

## 3. Adicionar persistencia de registros do provider

Precisamos de uma tabela nova para mapear nossos `media_id` e `event_id` com IDs remotos da AWS.

Tabela sugerida:

- `face_search_provider_records`

Campos recomendados:

- `event_id`
- `event_media_id`
- `provider_key`
- `backend_key`
- `collection_id`
- `face_id`
- `user_id`
- `image_id`
- `external_image_id`
- `bbox_json`
- `landmarks_json`
- `pose_json`
- `quality_json`
- `unindexed_reasons_json`
- `searchable`
- `indexed_at`
- `deleted_at`
- `provider_payload_json`

Campo critico:

- `external_image_id`

Motivo:

- o `IndexFaces` permite associar `ExternalImageId`;
- a propria doc da AWS recomenda usar isso para criar um indice client-side entre face e imagem.

## 4. Adicionar log proprio de consultas

Hoje existe auditoria de request no modulo, mas o backend AWS vai exigir rastreabilidade mais fina.

Tabela sugerida:

- `face_search_queries`

Campos recomendados:

- `event_id`
- `backend_key`
- `fallback_backend_key`
- `routing_policy`
- `status`
- `query_media_path`
- `query_face_bbox_json`
- `result_count`
- `error_code`
- `error_message`
- `started_at`
- `finished_at`
- `provider_payload_json`

## 5. Idempotencia e reconciliacao

Esse ponto precisa entrar como parte central do plano, nao como detalhe.

Regras recomendadas:

- `external_image_id` deterministico, por exemplo:
  - `evt:{event_id}:media:{media_id}:rev:{content_hash}`
- `CreateUser` com `ClientRequestToken` deterministico;
- `AssociateFaces` com `ClientRequestToken` deterministico;
- `ResourceAlreadyExistsException` em `CreateCollection` tratado como sucesso idempotente operacional;
- `UnindexedFaces` persistidos como telemetria, nao como excecao fatal.

Job novo recomendado:

- `ReconcileAwsCollectionJob`

Responsabilidades:

- `ListFaces`
- `DescribeCollection`
- comparar collection remota com `face_search_provider_records`
- reparar drift de `deleted`, orfaos e duplicatas operacionais

Estados recomendados por midia:

- `pending`
- `indexing`
- `indexed`
- `partially_indexed`
- `failed_functional`
- `failed_retryable`

---

## Regra De Negocio Principal: So Processa Se O Evento Estiver Ativo

Essa regra precisa ser mantida e ficar ainda mais explicita no design.

### Regra recomendada

Somente criar collection, indexar remotamente, buscar remotamente ou manter vetores remotos se:

- o evento tiver `enabled=true` no `FaceSearch`;
- e o backend escolhido para aquele evento for `aws_rekognition`.

Se o evento nao tiver reconhecimento ativo:

- nao cria collection;
- nao indexa nada remoto;
- nao cobra nada de AWS;
- nao processa fila de indexacao AWS.

### Onde aplicar isso

Na pratica, o gating deve acontecer em:

- `UpsertEventFaceSearchSettingsAction`
- `IndexMediaFacesJob`
- jobs de provisionamento AWS
- jobs de reindexacao AWS
- busca publica e interna por selfie

Leitura:

- isso respeita exatamente o objetivo de custo;
- e impede que a feature vire custo lateral silencioso.

---

## Biblioteca E Integracao Laravel

## Pacote recomendado

Pacote recomendado:

```bash
composer require aws/aws-sdk-php-laravel
```

Motivo:

- e o pacote oficial de integracao Laravel para o SDK oficial;
- continua ativo;
- o README oficial mostra uso via service container do Laravel.

Observacao:

- como compatibilidade de framework pode mudar entre releases, a implementacao deve seguir as constraints atuais do pacote no `composer` no momento do rollout, sem fixar a doc a um numero que possa envelhecer rapido.

## Como usar corretamente

Uso recomendado:

- usar o pacote oficial apenas para bootstrapping do SDK e container;
- nao espalhar `AWS::createClient()` pelo core do dominio;
- injetar `RekognitionClient` ou `Aws\Sdk` em adapters proprios.

Errado:

- controller chamando facade AWS direto;
- action de dominio montando request bruto da AWS espalhado no modulo.

Certo:

- `AwsRekognitionClientFactory`
- `AwsRekognitionFaceSearchBackend`
- `FaceSearchRouter`

## IAM Minimo Por Operacao

Para reduzir risco de falha operacional no primeiro rollout, a integracao deve sair com uma politica IAM minima explicitamente mapeada.

Acoes minimas do MVP:

- `rekognition:CreateCollection`
- `rekognition:DescribeCollection`
- `rekognition:IndexFaces`
- `rekognition:SearchFacesByImage`
- `rekognition:ListFaces`
- `rekognition:DeleteFaces`
- `rekognition:DeleteCollection`

Acoes da fase 2:

- `rekognition:CreateUser`
- `rekognition:AssociateFaces`
- `rekognition:SearchUsersByImage`
- `rekognition:ListUsers`

Leitura:

- o rollout inicial nao deve pedir permissoes de video, labels ou custom labels;
- a policy deve ficar restrita ao conjunto de colecoes da conta/regiao usadas pelo `FaceSearch`.

---

## Fluxo Recomendado Com AWS

## 1. Ativacao do evento

Quando o admin ativar reconhecimento facial com backend AWS:

1. salva configuracao do evento;
2. dispara `EnsureAwsCollectionJob`;
3. chama `CreateCollection`;
4. chama `DescribeCollection`;
5. persiste `collection_id`, `arn` e `face_model_version`.

Motivo:

- a collection e o ponto de isolamento por evento;
- `QualityFilter` depende do `face model version`;
- isso precisa ficar validado logo na ativacao.

## 2. Indexacao da galeria

Quando uma imagem entra no pipeline e o evento usa AWS:

1. ler binario local;
2. gerar derivado temporario para AWS;
3. preferir `Bytes`;
4. comprimir/redimensionar para ficar abaixo de `5 MB`;
5. chamar `IndexFaces`;
6. persistir `FaceRecords` e `UnindexedFaces`;
7. mapear `FaceId` e `ExternalImageId` para `event_media_id`.

Importante:

- para o MVP, `IndexFaces` deve ser a chamada principal;
- nao duplicar com `DetectFaces` no caminho feliz sem necessidade;
- os metadados que o `IndexFaces` ja devolve devem alimentar nosso quality score.

## 2.1. Politica de `DetectionAttributes`

Default recomendado do MVP:

- `["DEFAULT", "FACE_OCCLUDED"]`

Motivo:

- `DEFAULT` ja traz `BoundingBox`, `Confidence`, `Pose`, `Quality` e `Landmarks`;
- `FACE_OCCLUDED` adiciona um sinal importante para o nosso caso real;
- pedir `ALL` por default aumenta latencia e custo operacional sem justificativa no MVP.

Politica recomendada:

- nao usar `ALL` como default;
- introduzir `EYE_DIRECTION` so se o score composto mostrar necessidade clara.

## 2.2. Estrategia dinamica de `MaxFaces`

Nao faz sentido fixar `aws_max_faces_per_image=15` ou `20` como default global do produto.

Motivo:

- a propria AWS recomenda `IndexFaces` sem restricoes em cenarios de `photo sharing / social media` quando o objetivo e agrupar fotos pelas pessoas presentes;
- `MaxFaces` baixo tende a amputar exatamente convidados menores e rostos de fundo que sao importantes no evento social.

Politica recomendada:

- `selfie_friendly_event`
  - `MaxFaces=15`
- `social_gallery_event`
  - `MaxFaces=100`
- `corporate_stage_event`
  - `MaxFaces=30`

Leitura:

- custo deve ser controlado principalmente por:
  - ativacao por evento
  - quality gate do app
  - `searchable=true`
  - limpeza de colecoes
- e nao por um `MaxFaces` global agressivo que derruba recall cedo demais.

## 3. Preflight de selfie

Antes de gastar chamada AWS de busca, o app deve fazer um preflight local barato.

Objetivo:

- rejeitar selfie com mais de uma pessoa;
- rejeitar selfie sem face dominante;
- rejeitar imagem inviavel por tamanho/qualidade;
- reduzir custo e match errado.

Implementacao recomendada:

- `SelfiePreflightService`

Regras iniciais:

- uma face dominante apenas;
- bbox minima local;
- score minimo de qualidade composto;
- reforcar a diferenca entre:
  - piso tecnico de deteccao
  - face recomendada para search
- rejeitar ou avisar quando a face dominante estiver abaixo da faixa recomendada para busca;
- derivado local padronizado antes de chamar AWS.

## 3. Busca por selfie

Quando o usuario envia uma selfie:

1. salvar selfie temporaria;
2. gerar derivado para AWS;
3. chamar `SearchFacesByImage`;
4. mapear `FaceId` para `event_media_id`;
5. devolver resultados;
6. se houver erro retryable ou politica de fallback, acionar lane local.

Importante:

- isso funciona muito bem para selfie porque a consulta ja tende a conter uma face principal;
- a doc oficial diz que a operacao usa a maior face da imagem.
- na fase 1, isso deve permanecer sincronico no request HTTP, como o modulo ja faz hoje;
- so faz sentido migrar para fila se benchmark real mostrar que latencia ou volume passaram do ponto aceitavel.

## 4. Busca em foto de grupo

Para foto de grupo, o desenho nao pode ser o mesmo da selfie.

Motivo:

- `SearchFacesByImage` e `SearchUsersByImage` usam a maior face da imagem.

Logo:

- para foto de grupo, precisamos primeiro localizar/cortar faces;
- depois chamar busca por cada face de interesse.

Em outras palavras:

- `selfie search` e `group photo search` sao fluxos diferentes.

## 5. Fase 2 com user vectors

Depois que houver faces revisadas e confiaveis da mesma pessoa:

1. `CreateUser`
2. `AssociateFaces`
3. opcionalmente usar `SearchUsersByImage`

Motivo:

- a propria doc da AWS orienta que agregar multiplos vetores de uma pessoa melhora matching.
- a propria AWS recomenda indexar pelo menos `5` imagens boas por pessoa, com variacao de `yaw` e `pitch`, antes de esperar o melhor ganho de busca em collection.

Regra recomendada:

- nao liberar `SearchUsersByImage` por evento enquanto a pessoa nao tiver um conjunto minimo de faces boas;
- alvo inicial:
  - `>=5` faces boas
  - variacao basica de frontal, yaw esquerdo, yaw direito, pitch baixo e pitch alto

---

## Bytes Primeiro, S3 So Como Excecao

### Regra recomendada

Padrao:

- usar `Image.Bytes`

Fallback:

- usar `S3Object` apenas quando a imagem ainda estiver acima de `5 MB` depois do pre-processamento, ou quando houver algum requisito operacional especifico.

### Porque essa regra e melhor

- reduz acoplamento do MVP a bucket dedicado;
- simplifica arquitetura;
- reduz custo lateral e complexidade;
- combina com nosso pipeline atual, que ja le binario local.

### Politica pratica

Derivado AWS recomendado:

- JPEG
- `long edge` entre `1600` e `1920`
- compressao ajustada
- alvo de bytes abaixo de `5 MB`

Leitura:

- isso encaixa com a doc oficial da AWS;
- e continua honesto com o limite de deteccao por tamanho de face.

---

## O Que Precisamos Melhorar No Modulo Para Integrar Bem

## 1. Quality score composto

Mesmo com AWS, isso continua necessario.

Deve combinar:

- `BoundingBox`
- `Quality`
- `Pose`
- `Landmarks`
- `FACE_OCCLUDED`
- area relativa
- sharpness local
- brilho local
- consistencia geometrica dos landmarks

Motivo:

- o produto nao pode depender so do filtro nativo do provider;
- precisamos de score consistente entre backends.

## 2. Alinhamento facial antes de crop local

Mesmo com AWS como backend primario, ainda precisamos disso:

- para fallback local;
- para shadow lane;
- para comparacao justa entre providers;
- para clustering e tooling interno.

## 3. Router de backend

Classe recomendada:

- `FaceSearchRouter`

Papel:

- escolher backend primario por evento;
- decidir fallback;
- decidir shadow mode;
- classificar erros retryable vs funcionais.

## 4. Classificacao de erro

Precisamos de um classificador claro, mas alinhado ao padrao que o repositorio ja usa.

Leitura:

- o app hoje ja usa `PipelineFailureClassifier` com classes `transient` e `permanent`;
- faz mais sentido reaproveitar isso e adicionar `reason codes` do que inventar uma taxonomia paralela inteira.

Razoes recomendadas:

- `retryable`
- `functional_no_face`
- `throttled`
- `misconfigured`
- `provider_unavailable`

Exemplos AWS:

- `ProvisionedThroughputExceededException` -> retryable
- `ThrottlingException` -> retryable
- `InternalServerError` -> retryable
- `ResourceAlreadyExistsException` em `CreateCollection` -> sucesso idempotente
- `ResourceNotFoundException` em collection configurada -> misconfigured ou drift
- `InvalidParameterException` por imagem invalida/sem face -> funcional
- `ImageTooLargeException` -> funcional + reprocessamento de derivado
- `UnindexedFaces` -> telemetria, nao excecao

## 5. Indexacao por lote com batches reais

Para eventos grandes:

- usar `Bus::batch`
- priorizar busca de selfie em fila alta
- deixar backfill/indexacao em fila separada

Filas recomendadas:

- `face-search-query-high` apenas se a busca sair do modo sincronico
- `face-search-index-default`
- `face-search-maintenance-low`

Alinhamento com a stack atual:

- a conexao Redis atual usa `retry_after=240`;
- o supervisor `face-index` atual trabalha com `timeout=170`;
- se as novas filas AWS ficarem na mesma conexao Redis, os timeouts dos jobs precisam ficar confortavelmente abaixo de `240`;
- se precisarmos de janelas maiores, primeiro devemos separar conexao de queue, nao apenas subir timeout no escuro.

## 6. Persistir `UnindexedFaces`

Isso e obrigatorio para operar direito.

Motivo:

- mostra por que a face falhou;
- reduz leitura subjetiva;
- ajuda a calibrar:
  - `SMALL_BOUNDING_BOX`
  - `LOW_SHARPNESS`
  - `LOW_BRIGHTNESS`
  - `EXTREME_POSE`
  - `LOW_FACE_QUALITY`

## 7. Health check por backend

Cada evento/backend deve ter:

- `healthCheck`
- teste de credencial
- teste de collection
- teste de permissao
- metrica de latencia

---

## Mapeamento Recomendado De Classes

Classes novas sugeridas:

- `FaceSearchBackendInterface`
- `LocalPgvectorFaceSearchBackend`
- `AwsRekognitionFaceSearchBackend`
- `FaceSearchRouter`
- `AwsRekognitionClientFactory`
- `FaceSearchQualityScorer`
- `FaceSearchProviderRecordRepository`

Jobs sugeridos:

- `EnsureAwsCollectionJob`
- `DispatchEventIndexBatchJob`
- `IndexEventMediaChunkJob`
- `SearchSelfieAwsJob` como opcional se a busca virar assincrona
- `SearchSelfieLocalFallbackJob` como opcional se a busca virar assincrona
- `SyncAwsUserVectorJob`
- `DeleteAwsMediaFacesJob`
- `ReconcileAwsCollectionJob`
- `DeleteAwsCollectionJob`

---

## Configuracao Por Evento Recomendada

Campos recomendados na configuracao:

- `enabled`
- `recognition_enabled`
- `search_backend_key`
- `fallback_backend_key`
- `routing_policy`
- `shadow_mode_percentage`
- `aws_region`
- `aws_collection_id`
- `aws_collection_arn`
- `aws_face_model_version`
- `aws_search_mode`
- `aws_index_quality_filter`
- `aws_search_faces_quality_filter`
- `aws_search_users_quality_filter`
- `aws_search_face_match_threshold`
- `aws_search_user_match_threshold`
- `aws_associate_user_match_threshold`
- `aws_max_faces_per_image`
- `aws_index_profile_key`
- `aws_detection_attributes_json`
- `delete_remote_vectors_on_event_close`

Enums recomendados:

- `search_backend_key`
  - `local_pgvector`
  - `aws_rekognition`
  - `luxand_managed`
- `routing_policy`
  - `local_only`
  - `aws_primary_local_fallback`
  - `aws_primary_local_shadow`
  - `local_primary_aws_on_error`
- `aws_search_mode`
  - `faces`
  - `users`

---

## Migrations Recomendadas

### 1. `face_search_provider_records`

Campos principais:

- `event_id`
- `event_media_id`
- `provider_key`
- `backend_key`
- `collection_id`
- `face_id`
- `user_id`
- `image_id`
- `external_image_id`
- `bbox_json`
- `landmarks_json`
- `pose_json`
- `quality_json`
- `unindexed_reasons_json`
- `searchable`
- `indexed_at`
- `deleted_at`
- `provider_payload_json`

### 2. `face_search_queries`

Campos principais:

- `event_id`
- `backend_key`
- `fallback_backend_key`
- `status`
- `input_media_path`
- `query_face_bbox_json`
- `result_count`
- `error_code`
- `error_message`
- `started_at`
- `finished_at`
- `provider_payload_json`

---

## Custo-Beneficio E Controle De Custo

### O que ajuda custo

- ativacao somente por evento;
- bytes locais como default;
- `QualityFilter=AUTO` na indexacao inicial;
- evitar chamadas duplicadas (`IndexFaces` + `DetectFaces`) sem necessidade;
- usar `SearchFacesByImage` primeiro;
- `SearchUsersByImage` so na fase 2;
- limpeza de collection no fechamento do evento quando fizer sentido.

O que nao deve ser usado como regra cega de custo:

- `aws_max_faces_per_image=15|20` como default global.

Melhor regra:

- controlar custo por evento e por perfil de indexacao;
- indexar remoto apenas quando a face do app ficar `searchable=true`;
- usar `UnindexedFaces` para aprender onde o provider esta desperdicando chamada.

## Configuracao inicial recomendada

- `aws_index_quality_filter=AUTO`
- `aws_search_faces_quality_filter=NONE`
- `aws_search_users_quality_filter=NONE`
- `aws_search_face_match_threshold=80`
- `aws_search_user_match_threshold=80`
- `aws_associate_user_match_threshold=75`
- `aws_search_mode=faces`
- `aws_detection_attributes_json=["DEFAULT","FACE_OCCLUDED"]`
- `aws_max_faces_per_image` decidido por perfil do evento, nao por default global unico

Leitura:

- isso reduz ruido de fundo;
- segura custo;
- e respeita o comportamento oficial da AWS.

---

## Resiliencia E Retry

Configuracao recomendada do SDK:

- `retry_mode=standard`
- `max_attempts=3` para query
- `max_attempts=5` para indexacao

Motivo:

- a AWS recomenda `standard` para multi-tenant;
- `adaptive` nao e recomendado como default para multi-tenant sem isolamento fino;
- Rekognition recomenda `backoff` exponencial, retries e suavizacao de trafego.

Timeouts recomendados:

- `connect_timeout=3`
- `timeout=8` para query sincronica
- `timeout=15` para indexacao

Leitura:

- isso conversa melhor com o historico real do modulo, que ja encontrou timeout e backlog em lanes duras;
- e continua alinhado com `retry_after=240` da conexao Redis atual.

Politica do app:

- usar retries automaticos do SDK;
- classificar erros funcionais para nao retryar sem necessidade;
- aplicar backoff do job em throttling e 5xx;
- priorizar query de selfie em fila de maior prioridade.

---

## Estrategia De Rollout

### Fase 1

- integrar SDK oficial ao Laravel;
- criar `FaceSearchBackendInterface`;
- criar backend `aws_rekognition`;
- ativar gating por evento;
- manter `search by selfie` sincronico no request HTTP;
- implementar `CreateCollection`, `DescribeCollection`, `IndexFaces`, `SearchFacesByImage`, `DeleteFaces`, `DeleteCollection`;
- implementar `SelfiePreflightService`;
- persistir provider records.

### Fase 2

- rodar `shadow mode` com `CompreFace`;
- comparar:
  - recall
  - precision
  - latencia
  - fragmentacao
  - sobre-fusao

### Fase 3

- adicionar `CreateUser`, `AssociateFaces`, `SearchUsersByImage`;
- subir precisao em pessoas recorrentes;
- usar revisao humana para consolidar user vectors.

### Fase 4

- UI de revisao de clusters;
- merge/split manual;
- politicas finas por evento;
- fallback e roteamento automatizados.

---

## O Que Nao Esperar Da AWS

Mesmo com AWS como backend inicial:

- nao esperar milagre em crowd extremo com tiny faces;
- nao tratar `SearchFacesByImage` como busca de todas as pessoas de uma foto de grupo;
- nao abandonar benchmark local e datasets duros;
- nao remover `CompreFace` cedo demais.

Leitura:

- AWS melhora bastante o produto;
- mas nao elimina a necessidade de qualidade de input, gating e revisao humana em alguns fluxos.

---

## Decisao Final Recomendada

Hoje, a decisao mais forte para a nossa stack e:

- `AWS Rekognition` como backend gerenciado inicial;
- `CompreFace + pgvector` como fallback, shadow lane e ambiente de calibracao;
- processar remoto somente quando o evento tiver a feature ativa;
- usar `Bytes` como default e `S3` apenas como excecao;
- comecar com `IndexFaces + SearchFacesByImage`;
- subir para `CreateUser + AssociateFaces + SearchUsersByImage` so depois.

Se eu resumisse em uma linha:

- o melhor caminho profissional para o proximo passo nao e reabrir threshold local; e tornar o modulo roteavel e plugar `AWS Rekognition` como backend primario sob demanda por evento.

---

## Fontes Oficiais Validadas Em 2026-04-08

- AWS SDK for PHP Laravel package:
  - https://packagist.org/packages/aws/aws-sdk-php-laravel
- AWS SDK for PHP Laravel repo:
  - https://github.com/aws/aws-sdk-php-laravel
- AWS SDK for PHP credential docs:
  - https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/credentials.html
- AWS SDK for PHP examples:
  - https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/examples_index.html
- Rekognition `Image` bytes vs `S3Object`:
  - https://docs.aws.amazon.com/rekognition/latest/APIReference/API_Image.html
- Rekognition limits:
  - https://docs.aws.amazon.com/rekognition/latest/dg/limits.html
- Rekognition `IndexFaces`:
  - https://docs.aws.amazon.com/rekognition/latest/APIReference/API_IndexFaces.html
- Rekognition `SearchFacesByImage`:
  - https://docs.aws.amazon.com/rekognition/latest/APIReference/API_SearchFacesByImage.html
- Rekognition `SearchUsersByImage`:
  - https://docs.aws.amazon.com/rekognition/latest/APIReference/API_SearchUsersByImage.html
- Rekognition `CreateUser`:
  - https://docs.aws.amazon.com/rekognition/latest/APIReference/API_CreateUser.html
- Rekognition `AssociateFaces`:
  - https://docs.aws.amazon.com/rekognition/latest/APIReference/API_AssociateFaces.html
- Rekognition collections and users:
  - https://docs.aws.amazon.com/rekognition/latest/dg/collections.html
- Rekognition indexing guidance:
  - https://docs.aws.amazon.com/rekognition/latest/dg/guidance-index-faces.html
- Rekognition threshold guidance:
  - https://docs.aws.amazon.com/rekognition/latest/dg/thresholds-collections.html
- Rekognition recommendations for search input images:
  - https://docs.aws.amazon.com/rekognition/latest/dg/recommendations-facial-input-images-search.html
- Rekognition error handling:
  - https://docs.aws.amazon.com/rekognition/latest/dg/error-handling.html
- Rekognition service authorization reference:
  - https://docs.aws.amazon.com/service-authorization/latest/reference/list_amazonrekognition.html
- AWS retry behavior:
  - https://docs.aws.amazon.com/sdkref/latest/guide/feature-retry-behavior.html
