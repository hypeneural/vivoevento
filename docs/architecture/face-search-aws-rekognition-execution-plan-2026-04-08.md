# FaceSearch AWS Rekognition Execution Plan

## Objetivo

Este documento transforma a estrategia de integracao do `AWS Rekognition` em um plano de execucao implementavel no modulo `FaceSearch`.

Referencias primarias:

- `docs/architecture/face-search-aws-rekognition-integration-plan-2026-04-08.md`
- `docs/architecture/face-search-stack-assessment-and-provider-strategy-2026-04-08.md`
- `docs/architecture/face-search-dataset-calibration-analysis-2026-04-07.md`

Este plano existe para responder 7 perguntas de execucao:

1. o que ja esta fechado de arquitetura;
2. o que entra na fase 1 do MVP AWS;
3. o que fica claramente para fase 2;
4. quais arquivos e classes devem ser criados ou alterados;
5. quais testes automatizados precisam existir em cada etapa;
6. quais smokes reais precisam validar a integracao;
7. qual e a definicao de pronto antes de avancar de fase.

---

## Status De Execucao

- [x] analise arquitetural da stack atual concluida.
- [x] validacao da documentacao oficial da AWS concluida para `Bytes`, `IndexFaces`, `SearchFacesByImage`, `SearchUsersByImage`, `AssociateFaces`, `limits`, `retry` e `IAM`.
- [x] baseline atual do modulo `FaceSearch` executado com sucesso.
- [x] bateria TDD de contratos AWS criada em modo opt-in.
- [x] validacao red da bateria TDD executada antes da implementacao.
- [x] smoke real de conectividade AWS executado com credencial valida.
- [x] SDK base `aws/aws-sdk-php` instalado no `apps/api` para smoke e futura integracao.
- [ ] backend roteavel `aws_rekognition` ainda nao implementado.
- [ ] persistencia de provider records AWS ainda nao implementada.
- [ ] preflight de selfie ainda nao implementado.
- [ ] fallback/shadow mode AWS ainda nao implementado.
- [ ] user vectors AWS ainda nao implementados.

Ultima bateria executada:

- comando:
  - `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`
- resultado:
  - `76 passed`
  - `5 skipped`
  - `517 assertions`
- leitura:
  - o baseline atual do modulo esta estavel antes da entrada da trilha AWS;
  - os `5 skipped` correspondem aos contratos TDD AWS em modo opt-in, sem impactar a suite padrao.

Bateria TDD opt-in executada:

- comando:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
- resultado:
  - `7 failed`
- leitura:
  - os contratos red confirmaram exatamente os blocos que ainda faltam antes de qualquer rollout AWS.

Lacunas objetivas confirmadas pelos testes TDD:

- falta bloco `face_search.providers.aws_rekognition` na config;
- falta `AwsRekognitionClientFactory`;
- falta `FaceSearchBackendInterface` e `FaceSearchRouter`;
- falta `SelfiePreflightService`;
- a API de settings ainda nao aceita nem devolve os campos AWS por evento.
- `SearchFacesBySelfieAction` ainda esta acoplada diretamente a detector, embedder e vector store locais;
- falta `FaceSearchProviderRecord` como modelo de persistencia para estado remoto do provider.

Verificacoes finais na stack atual:

- `EventFaceSearchSetting` hoje so conhece campos locais:
  - `provider_key`
  - `embedding_model_key`
  - `vector_store_key`
  - `search_strategy`
  - `min_face_size_px`
  - `min_quality_score`
  - `search_threshold`
- `UpsertEventFaceSearchSettingsRequest` hoje valida apenas configuracao local e publica;
- `EventFaceSearchSettingResource` hoje nao expoe nenhum campo AWS;
- `FaceSearchServiceProvider` hoje so registra:
  - detection providers
  - embedding providers
  - vector store
- `SearchFacesBySelfieAction` hoje depende diretamente de:
  - `FaceDetectionProviderInterface`
  - `FaceEmbeddingProviderInterface`
  - `FaceVectorStoreInterface`
- o modulo hoje nao tem:
  - provider records
  - query records especificos de backend
  - jobs de provisionamento de collection
  - health check de backend gerenciado

Smoke real AWS executado antes da implementacao:

- script:
  - `apps/api/scripts/face-search-aws-smoke.php`
- variaveis locais usadas:
  - `FACE_SEARCH_AWS_SMOKE_ACCESS_KEY_ID`
  - `FACE_SEARCH_AWS_SMOKE_SECRET_ACCESS_KEY`
  - `FACE_SEARCH_AWS_SMOKE_REGION`
- motivo dessas variaveis dedicadas:
  - o projeto ja usa `AWS_*` para storage local/MinIO;
  - sobrescrever `AWS_ACCESS_KEY_ID` e correlatas quebraria o ambiente local de arquivos.

Resultado real validado em `2026-04-08`:

- `STS GetCallerIdentity`:
  - `OK`
  - `Account=426912654290`
  - `Arn=arn:aws:iam::426912654290:user/eventovivo`
- `Rekognition`:
  - `CreateCollection=OK`
  - `DescribeCollection=OK`
  - `FaceModelVersion=7.0`
  - `ListCollections=OK`
  - `ListFaces=OK`
  - `DeleteCollection=OK`

Leitura:

- a credencial esta valida;
- a regiao `eu-central-1` esta funcional;
- a policy ja cobre o baseline operacional do MVP para collections;
- o SDK base ja esta disponivel no projeto;
- ainda falta validar com imagem real:
  - `IndexFaces`
  - `SearchFacesByImage`
  - `DeleteFaces`

---

## Decisoes Fechadas

- `AWS Rekognition` sera o backend gerenciado inicial.
- `CompreFace + pgvector` continuam como fallback, shadow lane e ambiente de calibracao.
- imagens so podem ser processadas remotamente quando o evento estiver com reconhecimento facial ativo.
- o MVP usa `Image.Bytes` como caminho padrao.
- `S3Object` fica como excecao operacional, nao como dependencia estrutural.
- o MVP usa `IndexFaces + SearchFacesByImage`.
- `CreateUser + AssociateFaces + SearchUsersByImage` ficam para fase 2.
- a busca por selfie continua sincronica no request HTTP na fase 1.
- `search_threshold` local nao sera reaberto nesta trilha.
- `min_face_size_px=24` local nao sera reaberto nesta trilha.
- thresholds da AWS e thresholds locais serao separados explicitamente.
- `DetectionAttributes` do MVP ficam em `["DEFAULT","FACE_OCCLUDED"]`.
- `MaxFaces` sera controlado por perfil de evento, nao por default global unico.

---

## Escopo Da Fase 1

Entram na fase 1:

- bootstrap oficial do SDK AWS no Laravel;
- `FaceSearchBackendInterface`;
- backend `AwsRekognitionFaceSearchBackend`;
- roteamento por evento entre backend local e AWS;
- provisionamento de collection por evento;
- indexacao AWS com `IndexFaces`;
- busca por selfie com `SearchFacesByImage`;
- `SelfiePreflightService`;
- persistencia de `face_search_provider_records`;
- persistencia de `face_search_queries`;
- persistencia de `UnindexedFaces`;
- `healthCheck` por backend;
- classificacao de erro alinhada ao padrao atual do repositorio.

Nao entram na fase 1:

- `SearchUsersByImage`;
- consolidacao automatica de user vectors;
- UI de merge/split de clusters;
- busca por todas as faces de uma foto de grupo;
- dependencia obrigatoria de `S3`;
- abandono do fluxo local existente.

---

## Estrategia Tecnica

O que vamos usar:

- pacote:
  - `aws/aws-sdk-php-laravel`
- SDK base:
  - `aws/aws-sdk-php`
- backend primario novo:
  - `AwsRekognitionFaceSearchBackend`
- backend local preservado:
  - `LocalPgvectorFaceSearchBackend`
- roteamento:
  - `FaceSearchRouter`
- entrada padrao:
  - `Image.Bytes`
- indexacao MVP:
  - `IndexFaces`
- busca MVP:
  - `SearchFacesByImage`
- fase 2:
  - `CreateUser`
  - `AssociateFaces`
  - `SearchUsersByImage`

Logica central:

1. se o evento nao estiver com reconhecimento ativo, nao toca AWS;
2. se o evento usar backend AWS, provisiona ou reusa collection do evento;
3. na indexacao, gera derivado JPEG local, tenta `Bytes`, chama `IndexFaces`, persiste faces indexadas e nao indexadas;
4. na selfie, faz preflight local barato antes de gastar request AWS;
5. se a selfie passar no preflight, chama `SearchFacesByImage`;
6. se houver erro retryable ou politica de fallback, usa backend local;
7. em paralelo, manter opcao de shadow mode para medir recall, latencia e custo.

---

## Arquivos E Areas Provaveis De Alteracao

Backend Laravel:

- `apps/api/app/Modules/FaceSearch/Services/`
- `apps/api/app/Modules/FaceSearch/Actions/`
- `apps/api/app/Modules/FaceSearch/Jobs/`
- `apps/api/app/Modules/FaceSearch/Models/`
- `apps/api/app/Modules/FaceSearch/Http/Requests/`
- `apps/api/app/Modules/FaceSearch/Http/Resources/`
- `apps/api/app/Modules/FaceSearch/Providers/`
- `apps/api/config/face_search.php`
- `apps/api/config/services.php`
- `apps/api/config/horizon.php`
- `apps/api/.env.example`

Banco:

- migrations novas para settings e provider records

Frontend:

- `apps/web/src/modules/events/components/face-search/`
- `apps/web/src/modules/events/`

Testes:

- `apps/api/tests/Unit/FaceSearch/`
- `apps/api/tests/Feature/FaceSearch/`
- `apps/web/src/modules/events/components/face-search/`

---

## Fases E Tarefas

## H1. Fundacao E Contratos

### H1-T1. Integrar o SDK oficial da AWS no app

Objetivo:

- instalar o pacote oficial;
- registrar a forma correta de obter `RekognitionClient` via container;
- evitar uso de facade AWS dentro do dominio.

Subtarefas:

- adicionar `aws/aws-sdk-php-laravel` ao `composer`;
- configurar bootstrap minimo em `config/services.php` e `.env.example`;
- criar `AwsRekognitionClientFactory`;
- fixar `retry_mode=standard`, `max_attempts`, `connect_timeout` e `timeout`.

Arquivos alvo provaveis:

- `apps/api/composer.json`
- `apps/api/config/services.php`
- `apps/api/.env.example`
- `apps/api/app/Modules/FaceSearch/Services/AwsRekognitionClientFactory.php`

Testes obrigatorios:

- `AwsRekognitionClientFactoryTest`
- validar fallback claro quando `region`, `key` ou `secret` estiverem ausentes
- validar que query e indexacao usam configuracao de timeout esperada

Definicao de pronto:

- cliente AWS resolvido via container;
- nenhuma classe de dominio usa facade AWS diretamente;
- testes unitarios verdes.

### H1-T2. Criar abstracao de backend de busca

Objetivo:

- subir uma camada acima de `detection + embedding + vector store`.

Subtarefas:

- criar `FaceSearchBackendInterface`;
- criar `LocalPgvectorFaceSearchBackend`;
- criar `AwsRekognitionFaceSearchBackend` vazio com contrato;
- criar `FaceSearchRouter`.

Arquivos alvo provaveis:

- `apps/api/app/Modules/FaceSearch/Services/FaceSearchBackendInterface.php`
- `apps/api/app/Modules/FaceSearch/Services/LocalPgvectorFaceSearchBackend.php`
- `apps/api/app/Modules/FaceSearch/Services/AwsRekognitionFaceSearchBackend.php`
- `apps/api/app/Modules/FaceSearch/Services/FaceSearchRouter.php`
- `apps/api/app/Modules/FaceSearch/Providers/FaceSearchServiceProvider.php`

Testes obrigatorios:

- `FaceSearchRouterTest`
- `LocalPgvectorFaceSearchBackendTest`
- testes de binding do provider

Definicao de pronto:

- o fluxo local atual continua funcionando atras do backend local;
- a introducao do router nao quebra a suite atual de `FaceSearch`.

### H1-T3. Separar configuracao local da configuracao AWS

Objetivo:

- impedir mistura de semantica entre thresholds locais e thresholds da AWS.

Subtarefas:

- adicionar campos AWS em settings por evento;
- manter `search_threshold` e `min_face_size_px` apenas para o backend local;
- expor settings novos na API e no form do painel.

Arquivos alvo provaveis:

- model e migration de `EventFaceSearchSetting`
- `UpsertEventFaceSearchSettingsAction.php`
- requests e resources do modulo
- `EventFaceSearchSettingsForm.tsx`

Testes obrigatorios:

- expandir `FaceSearchSettingsTest`
- expandir testes frontend do form
- validar que thresholds locais e AWS nao se confundem

Definicao de pronto:

- evento consegue salvar backend, politica de fallback e parametros AWS sem quebrar config local.

---

## H2. Persistencia E Provisionamento AWS

### H2-T1. Criar persistencia de provider records e queries

Objetivo:

- rastrear faces indexadas, `UnindexedFaces`, queries e drift operacional.

Subtarefas:

- migration `face_search_provider_records`;
- migration `face_search_queries`;
- models, factories e enums de status;
- persistencia de `external_image_id` deterministico.

Testes obrigatorios:

- testes de model/factory
- testes de persistencia do `external_image_id`
- testes de serializacao de payload AWS e `UnindexedFaces`

Definicao de pronto:

- cada imagem indexada gera rastreabilidade local suficiente para reconciliar com a collection remota.

### H2-T2. Provisionar collection por evento

Objetivo:

- isolar o backend AWS por evento e validar health na ativacao.

Subtarefas:

- criar `EnsureAwsCollectionJob`;
- implementar `CreateCollection` e `DescribeCollection`;
- persistir `aws_collection_id`, `aws_collection_arn` e `aws_face_model_version`;
- tratar `ResourceAlreadyExistsException` como sucesso idempotente.

Testes obrigatorios:

- `EnsureAwsCollectionJobTest`
- `AwsRekognitionFaceSearchBackendTest` para `ensureEventBackend`
- expandir `FaceSearchSettingsTest` para ativacao AWS

Definicao de pronto:

- ao ativar backend AWS em evento valido, a collection fica provisionada e registrada.

### H2-T3. Health check operacional e IAM minimo

Objetivo:

- detectar cedo erro de credencial, permissao e drift de collection.

Subtarefas:

- implementar `healthCheck` no backend AWS;
- validar operacoes minimas:
  - `CreateCollection`
  - `DescribeCollection`
  - `IndexFaces`
  - `SearchFacesByImage`
  - `ListFaces`
  - `DeleteFaces`
  - `DeleteCollection`
- documentar IAM minimo do MVP e IAM adicional da fase 2.

Testes obrigatorios:

- `AwsRekognitionHealthCheckTest`
- falhas claras para credencial invalida e permissao insuficiente

Definicao de pronto:

- operador consegue distinguir `misconfigured`, `provider_unavailable` e `healthy`.

---

## H3. Indexacao AWS Do MVP

### H3-T1. Implementar preprocessamento de imagem para AWS

Objetivo:

- garantir baixo custo, latencia previsivel e compatibilidade com `Image.Bytes`.

Subtarefas:

- criar derivado JPEG local;
- limitar `long edge` entre `1600` e `1920`;
- perseguir payload abaixo de `5 MB`;
- cair para `S3Object` apenas se a politica operacional realmente exigir.

Testes obrigatorios:

- `AwsImagePreprocessorTest`
- validar conversao, resize e limite de bytes
- validar que o caminho padrao nao depende de `S3`

Definicao de pronto:

- imagem padrao do MVP sai pronta para `Bytes` com comportamento deterministico.

### H3-T2. Indexar galeria via `IndexFaces`

Objetivo:

- indexar remotamente sem duplicar chamadas desnecessarias.

Subtarefas:

- usar `IndexFaces` como happy path;
- enviar `DetectionAttributes=["DEFAULT","FACE_OCCLUDED"]`;
- respeitar `aws_index_quality_filter`;
- respeitar `aws_max_faces_per_image` por perfil de evento;
- persistir `FaceId`, `ImageId`, `ExternalImageId`, `bbox`, `quality`, `pose` e `UnindexedFaces`.

Testes obrigatorios:

- `AwsRekognitionFaceSearchBackendTest` para `indexMedia`
- expandir `FaceIndexingPipelineTest` cobrindo backend AWS
- validar que `IndexFaces` nao chama `DetectFaces` no caminho feliz
- validar persistencia de `UnindexedFaces`

Definicao de pronto:

- uma midia de evento AWS gera provider records indexados e nao indexados, com status rastreavel.

### H3-T3. Gating por evento e por `searchable`

Objetivo:

- manter custo baixo sem amputar produto fora da feature.

Subtarefas:

- bloquear qualquer chamada AWS quando `enabled=false`;
- bloquear chamada AWS quando backend do evento nao for `aws_rekognition`;
- indexar remoto apenas quando a face passar pelo gate do app e ficar `searchable=true`.

Testes obrigatorios:

- expandir `FaceIndexingPipelineTest`
- expandir `FaceSearchSettingsTest`
- validar que evento desabilitado gera zero chamadas AWS

Definicao de pronto:

- nenhum custo AWS acontece fora do evento elegivel.

---

## H4. Busca Por Selfie No MVP

### H4-T1. Implementar `SelfiePreflightService`

Objetivo:

- cortar cedo selfie ruim, multi-pessoa ou inviavel.

Subtarefas:

- exigir uma face dominante;
- checar bbox minima local;
- checar score composto minimo;
- separar:
  - piso tecnico de deteccao
  - faixa recomendada para search
- devolver erro claro para UX.

Testes obrigatorios:

- `SelfiePreflightServiceTest`
- expandir `FaceSearchSelfieEndpointsTest`
- casos:
  - sem face
  - duas pessoas
  - face pequena demais para busca
  - face valida

Definicao de pronto:

- selfie ruim falha antes de consumir busca AWS.

### H4-T2. Implementar busca AWS com `SearchFacesByImage`

Objetivo:

- entregar o fluxo principal de selfie search no backend gerenciado.

Subtarefas:

- gerar derivado padronizado;
- chamar `SearchFacesByImage`;
- mapear `FaceId` para `event_media_id`;
- persistir auditoria em `face_search_queries`;
- manter request sincronico;
- acionar fallback local apenas por erro retryable ou politica de rota.

Testes obrigatorios:

- expandir `FaceSearchSelfieEndpointsTest` para backend AWS
- `AwsRekognitionFaceSearchBackendTest` para `searchBySelfie`
- validar que a query continua isolada por `event_id`

Definicao de pronto:

- selfie valida retorna matches AWS com rastreabilidade e fallback controlado.

### H4-T3. Separar claramente selfie de foto de grupo

Objetivo:

- evitar erro conceitual de produto.

Subtarefas:

- manter `SearchFacesByImage` apenas para selfie;
- nao prometer group photo search no MVP;
- documentar que group photo search exigira localizacao/crop por face.

Testes obrigatorios:

- teste de validacao/UX para selfie com varias pessoas

Definicao de pronto:

- o produto nao vende como pronto algo que a API nao faz bem no MVP.

---

## H5. Resiliencia, Fallback E Shadow Mode

### H5-T1. Alinhar classificacao de erro ao padrao do repositorio

Objetivo:

- aproveitar `PipelineFailureClassifier` e `ProviderCircuitBreaker`.

Subtarefas:

- mapear erros AWS para `transient` e `permanent`;
- adicionar `reason codes`:
  - `retryable`
  - `functional_no_face`
  - `throttled`
  - `misconfigured`
  - `provider_unavailable`
- tratar `UnindexedFaces` como telemetria, nao excecao.

Testes obrigatorios:

- `AwsRekognitionFailureClassifierTest`
- testes de circuit breaker no backend AWS

Definicao de pronto:

- erros da AWS entram no mesmo idioma operacional do app.

### H5-T2. Implementar fallback local

Objetivo:

- evitar indisponibilidade dura do produto.

Subtarefas:

- suportar `aws_primary_local_fallback`;
- suportar `local_primary_aws_on_error`;
- manter `aws_primary_local_shadow` para medicao;
- registrar qual backend respondeu.

Testes obrigatorios:

- `FaceSearchRouterTest`
- expandir `FaceSearchSelfieEndpointsTest`
- expandir `FaceIndexingPipelineTest`

Definicao de pronto:

- evento consegue operar com backend primario e fallback definidos por politica.

### H5-T3. Implementar shadow mode e reconciliacao

Objetivo:

- medir qualidade, custo e drift sem trocar o produto no escuro.

Subtarefas:

- implementar `shadow_mode_percentage`;
- persistir comparativo de:
  - recall proxy
  - latencia
  - divergencia de resultados
- criar `ReconcileAwsCollectionJob`;
- usar `ListFaces` e `DescribeCollection` para reconciliar drift.

Testes obrigatorios:

- `ReconcileAwsCollectionJobTest`
- `ShadowModeDecisionTest`

Definicao de pronto:

- o time consegue medir AWS vs local antes de aumentar rollout.

---

## H6. User Vectors E Busca Mais Forte Por Identidade

### H6-T1. Criar readiness gate para `SearchUsersByImage`

Objetivo:

- so liberar fase 2 quando houver base suficiente por pessoa.

Subtarefas:

- exigir faces revisadas e confiaveis;
- exigir pelo menos `5` faces boas por pessoa;
- exigir variacao minima de `yaw` e `pitch`.

Testes obrigatorios:

- `AwsUserVectorReadinessTest`

Definicao de pronto:

- o sistema nao cria `user vectors` em cima de material fraco.

### H6-T2. Implementar `CreateUser` e `AssociateFaces`

Objetivo:

- consolidar multiplas faces boas da mesma pessoa.

Subtarefas:

- criar `SyncAwsUserVectorJob`;
- usar `ClientRequestToken` deterministico;
- associar faces em lotes;
- persistir sucesso e falha por associacao.

Testes obrigatorios:

- `SyncAwsUserVectorJobTest`
- `AwsRekognitionFaceSearchBackendTest` para user vectors

Definicao de pronto:

- user vector fica sincronizado e idempotente.

### H6-T3. Habilitar `SearchUsersByImage`

Objetivo:

- subir precisao em pessoas recorrentes.

Subtarefas:

- respeitar `aws_search_mode=users`;
- manter fallback para `faces` se o user vector ainda nao estiver pronto;
- auditar claramente qual modo respondeu.

Testes obrigatorios:

- expandir `FaceSearchSelfieEndpointsTest`
- `AwsUserSearchModeTest`

Definicao de pronto:

- evento pode optar por `users` sem quebrar o fluxo para quem ainda esta em `faces`.

---

## H7. Operacao, UI E Rollout

### H7-T1. Painel e operacao

Objetivo:

- tornar a operacao AWS administravel por evento.

Subtarefas:

- expor backend, fallback, profile key, thresholds AWS e search mode no form;
- criar endpoints de:
  - health
  - reindex
  - reconcile
  - delete collection
- mostrar status da collection e ultimo health.

Testes obrigatorios:

- expandir `FaceSearchSettingsTest`
- expandir testes frontend do form

Definicao de pronto:

- operador consegue ativar, validar e operar AWS sem mexer manualmente no banco.

### H7-T2. Rollout controlado

Objetivo:

- trocar de backend com seguranca.

Subtarefas:

- definir piloto com poucos eventos;
- medir:
  - custo por evento
  - latencia por query
  - taxa de fallback
  - taxa de `UnindexedFaces`
- so ampliar rollout depois de shadow mode aceitavel.

Testes obrigatorios:

- nao depende de teste unitario novo;
- depende de smoke real e observabilidade.

Definicao de pronto:

- piloto pequeno aprovado com custo e latencia dentro da meta.

---

## Bateria De Testes Obrigatoria

## 1. Bateria automatizada minima por PR

Backend:

- `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`

Frontend:

- testes do form/settings de `FaceSearch`

Regra:

- nenhuma fase fecha com suite vermelha;
- novos testes AWS entram nessa bateria base.

## 2. Bateria nova que precisa nascer

Unit:

- `AwsRekognitionClientFactoryTest`
- `AwsRekognitionFaceSearchBackendTest`
- `FaceSearchRouterTest`
- `SelfiePreflightServiceTest`
- `AwsRekognitionHealthCheckTest`
- `AwsRekognitionFailureClassifierTest`
- `AwsImagePreprocessorTest`
- `ReconcileAwsCollectionJobTest`
- `SyncAwsUserVectorJobTest`
- `AwsUserVectorReadinessTest`
- `AwsUserSearchModeTest`

Contratos TDD ja criados antes da implementacao:

- `FaceSearchAwsConfigContractTest`
- `AwsRekognitionClientFactoryContractTest`
- `FaceSearchRouterContractTest`
- `SelfiePreflightServiceContractTest`
- `FaceSearchAwsSettingsContractTest`

Feature:

- expandir `FaceSearchSettingsTest`
- expandir `FaceIndexingPipelineTest`
- expandir `FaceSearchSelfieEndpointsTest`
- criar testes para health/reindex/reconcile se houver endpoints novos

## 3. Smokes reais obrigatorios

Smokes locais com credenciais AWS validas:

- provisionar collection de teste;
- indexar imagem com uma face clara;
- indexar imagem com multi-face;
- buscar por selfie valida;
- buscar por selfie invalida;
- deletar faces;
- deletar collection.

Smokes de fallback:

- simular erro retryable da AWS e confirmar resposta local;
- simular evento com AWS desativada e confirmar zero chamadas remotas.

## 4. Criterio de passagem de fase

Uma fase so fecha quando:

- testes unitarios/feature da fase passarem;
- a bateria base do `FaceSearch` continuar verde;
- a documentacao do modulo for atualizada;
- o smoke real da fase passar quando a fase tocar AWS de verdade.

---

## Definicao De Pronto Da Fase 1

A fase 1 so pode ser considerada pronta quando:

- evento com backend `aws_rekognition` provisiona collection corretamente;
- evento sem feature ativa gera zero custo AWS;
- indexacao AWS persiste faces indexadas e `UnindexedFaces`;
- busca por selfie via `SearchFacesByImage` funciona com preflight;
- fallback local funciona em erro retryable;
- `healthCheck` por evento funciona;
- suite `FaceSearch` permanece verde;
- smoke real AWS passa em ambiente de desenvolvimento.

---

## Ordem Recomendada De Implementacao

1. `H1-T1`
2. `H1-T2`
3. `H1-T3`
4. `H2-T1`
5. `H2-T2`
6. `H2-T3`
7. `H3-T1`
8. `H3-T2`
9. `H3-T3`
10. `H4-T1`
11. `H4-T2`
12. `H4-T3`
13. `H5-T1`
14. `H5-T2`
15. `H5-T3`
16. `H7-T1`
17. piloto operacional de `H7-T2`
18. so depois iniciar `H6`

Leitura:

- isso entrega valor cedo no MVP;
- segura custo;
- preserva fallback;
- evita entrar cedo demais em `SearchUsersByImage` sem base boa o suficiente.

---

## Riscos Que Precisam Ser Monitorados

- custo escondido por evento com feature ativa e galeria muito grande;
- `MaxFaces` mal calibrado amputando rostos de grupo;
- `QualityFilter` barrando demais no index;
- timeouts ruins sob carga ou imagem grande;
- permissao IAM incompleta;
- drift entre collection remota e provider records locais;
- falsa sensacao de qualidade se o piloto nao rodar shadow mode.

---

## Proximo Passo Recomendado

Se a execucao comecar agora, o primeiro bloco deve ser:

1. instalar o SDK oficial e criar o `AwsRekognitionClientFactory`;
2. criar `FaceSearchBackendInterface` e `FaceSearchRouter`;
3. expandir settings por evento;
4. criar migrations de provider records e queries;
5. so entao entrar no provisionamento e indexacao AWS.
