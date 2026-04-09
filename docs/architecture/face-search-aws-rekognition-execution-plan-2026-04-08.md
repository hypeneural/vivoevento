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
- [x] bloco de config `face_search.providers.aws_rekognition` implementado.
- [x] `AwsRekognitionClientFactory` implementado e coberto por testes unitarios.
- [x] `FaceSearchBackendInterface`, `LocalPgvectorFaceSearchBackend` e `FaceSearchRouter` implementados.
- [x] `SelfiePreflightService` implementado e integrado na busca por selfie.
- [x] `SearchFacesBySelfieAction` desacoplada de detector/embedder/vector store diretos.
- [x] settings AWS por evento aceitos e expostos na API backend.
- [x] `FaceSearchProviderRecord` criado como fundacao minima de modelo.
- [x] bootstrap do pacote `aws/aws-sdk-php-laravel` concluido no app.
- [x] persistencia de provider records AWS implementada no banco.
- [x] persistencia de `face_search_queries` implementada.
- [x] provisionamento de collection por evento implementado.
- [x] campos AWS expostos no form frontend do painel.
- [x] `healthCheck` real do backend AWS implementado.
- [x] preprocessamento deterministico para `Image.Bytes` implementado.
- [x] indexacao AWS via `IndexFaces` implementada com persistencia de `UnindexedFaces`.
- [x] gating por evento/backend/searchable implementado no pipeline de indexacao.
- [x] busca AWS via `SearchFacesByImage` implementada com auditoria em `face_search_queries`.
- [x] `H4-T3` fechado com mensagem explicita de selfie-only e bloqueio claro para foto de grupo.
- [x] classificacao de erro AWS alinhada ao padrao do repositorio.
- [x] fallback local e shadow mode inicial implementados no fluxo de selfie.
- [x] shadow comparativo completo e reconciliacao AWS implementados.
- [x] `H7-T1` fechado com endpoints operacionais e painel AWS por evento.
- [x] smoke real AWS com imagem validado para `IndexFaces + SearchFacesByImage + DeleteFaces`.
- [x] preparacao operacional do piloto `H7-T2` documentada no plano.
- [ ] user vectors AWS ainda nao implementados.

Ultima bateria executada:

- comando:
  - `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`
- resultado:
  - `118 passed`
  - `7 skipped`
  - `841 assertions`
- leitura:
  - a trilha `H1 + H2 + H3 + H4 + H5 + H7-T1` continuou verde depois do smoke real com imagem;
  - os `7 skipped` continuam sendo apenas os contratos TDD AWS em modo opt-in.

Bateria frontend executada:

- comandos:
  - `npm run type-check`
  - `npx.cmd vitest run src/modules/events/components/face-search`
- resultado:
  - `type-check = PASS`
  - `EventFaceSearchSettingsForm.test.tsx + EventFaceSearchSettingsCard.test.tsx = 7 passed`
- leitura:
  - o painel agora aceita o contrato AWS por evento e expoe operacao de `health/reindex/reconcile/delete collection` sem quebrar tipagem nem fluxo do form.

Bateria de contratos opt-in reexecutada apos o smoke real com imagem:

- comando:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
- resultado:
  - `7 passed`
  - `42 assertions`
- leitura:
  - a fundacao contratual da integracao AWS continuou intacta depois do rerun operacional e do smoke real com imagem.

Bateria dirigida executada para `H2-T3` e `H3`:

- comando:
  - `php artisan test tests/Unit/FaceSearch/AwsRekognitionHealthCheckTest.php tests/Unit/FaceSearch/AwsImagePreprocessorTest.php tests/Unit/FaceSearch/AwsRekognitionFaceSearchBackendTest.php tests/Feature/FaceSearch/FaceIndexingPipelineTest.php tests/Unit/FaceSearch/FaceSearchRouterTest.php`
- resultado:
  - `18 passed`
  - `103 assertions`
- leitura:
  - `healthCheck`, `Image.Bytes`, `IndexFaces`, `UnindexedFaces` e roteamento de indexacao por backend ficaram cobertos por TDD antes da regressao ampla.

Bateria de contratos opt-in reexecutada apos `H3`:

- comando:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
- resultado:
  - `7 passed`
  - `42 assertions`
- leitura:
  - a fundacao de `H1/H2` continuou intacta depois da entrada de `healthCheck` e `IndexFaces`.

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

Atualizacao apos entrada em `H1` e conclusao de `H2-T1` / `H2-T2`:

- o bloco de config AWS foi implementado com thresholds separados e network settings dedicados;
- o pacote oficial `aws/aws-sdk-php-laravel` foi integrado ao `composer` e resolvido pelo container;
- foi adicionada config dedicada em `config/aws.php` para nao conflitar com `AWS_*` usados por storage local;
- o container agora resolve `AwsRekognitionClientFactory`, `AwsRekognitionFaceSearchBackend`, `LocalPgvectorFaceSearchBackend`, `SelfiePreflightService` e `FaceSearchRouter`;
- a busca por selfie agora passa por:
  - `SelfiePreflightService`
  - `FaceSearchRouter`
  - backend local ou AWS por configuracao;
- `EventFaceSearchSetting`, request, action e resource agora suportam configuracao AWS por evento;
- o form frontend `EventFaceSearchSettingsForm` agora expoe backend, fallback, routing policy, search mode, quality filters e thresholds AWS;
- `face_search_provider_records` e `face_search_queries` agora existem com migrations, models, factories e payload JSON rastreavel;
- `EnsureAwsCollectionJob` agora provisiona collection por evento e persiste:
  - `aws_collection_id`
  - `aws_collection_arn`
  - `aws_face_model_version`
- `ResourceAlreadyExistsException` na criacao de collection ja e tratada como sucesso idempotente.
- `AwsRekognitionHealthCheckTest` e `AwsImagePreprocessorTest` agora cobrem:
  - `STS GetCallerIdentity`
  - `DescribeCollection`
  - `ListFaces`
  - hints de IAM minimo
  - resize/compressao deterministica para `Image.Bytes`
- `IndexMediaFacesAction` agora roteia por backend e nao depende mais do pipeline local para eventos AWS;
- `AwsRekognitionFaceSearchBackend::indexMedia()` agora:
  - gera derivado JPEG para `Image.Bytes`
  - chama `IndexFaces`
  - persiste `FaceRecords`
  - persiste `UnindexedFaces`
  - remove faces remotas que falham no gate local de `searchable`
  - evita custo AWS quando o evento continua na lane local.
- `AwsRekognitionFaceSearchBackend::searchBySelfie()` agora:
  - gera derivado padronizado para `SearchFacesByImage`
  - usa `FaceMatchThreshold` e `QualityFilter` separados do lane local
  - mapeia `FaceId` remoto para `event_media_id` local via `face_search_provider_records`
  - retorna apenas faces `searchable=true`
  - devolve payload bruto do provider para auditoria.
- `SearchFacesBySelfieAction` agora:
  - abre uma linha de auditoria em `face_search_queries`
  - registra `backend_key`, `fallback_backend_key` e `routing_policy`
  - persiste `query_face_bbox_json`, `provider_payload_json`, `result_count` e erro final quando houver.

Estado atual da stack apos `H2-T2`:

- `EventFaceSearchSetting` ja conhece os campos AWS e separa claramente thresholds locais de thresholds nativos da AWS;
- `UpsertEventFaceSearchSettingsRequest` e `EventFaceSearchSettingResource` ja validam e expoem o contrato AWS por evento;
- `FaceSearchServiceProvider` ja registra router, backends e factory AWS com resolucao via container;
- `SearchFacesBySelfieAction` ja nao depende diretamente do trio detector/embedder/vector store;
- o modulo agora ja tem:
  - `face_search_provider_records`
  - `face_search_queries`
  - `EnsureAwsCollectionJob`
- o modulo agora tambem ja tem:
  - `healthCheck` real do backend AWS
  - preprocessamento `Image.Bytes`
  - indexacao AWS via `IndexFaces`
  - persistencia de `UnindexedFaces`
- o modulo agora tambem ja tem:
  - busca AWS via `SearchFacesByImage`
  - auditoria de query em `face_search_queries`
- o modulo agora tambem ja tem:
  - fallback local por politica de rota
  - shadow mode inicial no fluxo de selfie
  - classificacao de falha AWS com `reason codes` alinhados ao app
- o modulo ainda nao tem:
  - shadow comparativo completo com reconciliacao
  - user vectors

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

Rerun operacional validado em `2026-04-09`:

- comando:
  - `php scripts/face-search-aws-smoke.php`
- resultado:
  - `STS GetCallerIdentity = OK`
  - `Account=426912654290`
  - `Arn=arn:aws:iam::426912654290:user/eventovivo`
  - `CreateCollection=OK`
  - `DescribeCollection=OK`
  - `FaceModelVersion=7.0`
  - `ListCollections=OK`
  - `ListFaces=OK`
  - `DeleteCollection=OK`
- leitura:
  - a credencial continua valida em `eu-central-1`;
  - o baseline de operacao de collection segue funcional depois da entrada de `H7-T1`;
  - o smoke real de imagem ficou isolado para uma rodada dedicada com `Image.Bytes` e preprocessamento identico ao do app.

Smoke real AWS com imagem validado em `2026-04-09`:

- comando:
  - `php scripts/face-search-aws-smoke.php --index-image="C:\Users\Usuario\Desktop\vipsocial\55160539780_ffeb73e159_o.jpg" --query-image="C:\Users\Usuario\Desktop\vipsocial\55159264527_7f683b08f6_o.jpg"`
- imagens usadas:
  - indexacao:
    - `55160539780_ffeb73e159_o.jpg`
  - busca:
    - `55159264527_7f683b08f6_o.jpg`
- preprocessamento:
  - `Image.Bytes` gerado pelo mesmo `AwsImagePreprocessor` do modulo
  - index bytes:
    - `129892`
    - `1081x1920`
  - query bytes:
    - `140231`
    - `1920x1081`
- resultado:
  - `STS GetCallerIdentity = OK`
  - `CreateCollection = OK`
  - `DescribeCollection = OK`
  - `IndexFaces = OK`
  - `UnindexedFaces = 0`
  - `SearchFacesByImage = OK`
  - `Top match Similarity = 100.00`
  - `DeleteFaces = OK`
  - `DeleteCollection = OK`
- leitura:
  - a credencial, o IAM e a regiao atual cobrem o fluxo real do MVP com imagem;
  - o preprocessamento deterministico para `Image.Bytes` esta funcional fora dos testes unitarios;
  - o par de imagens real retornou o `FaceId` indexado como top match e confirmou o caminho ponta a ponta:
    - `IndexFaces`
    - `SearchFacesByImage`
    - `DeleteFaces`
    - `DeleteCollection`

Bateria executada apos a implementacao inicial de `H1`:

- bateria dirigida:
  - `php artisan test tests/Unit/FaceSearch/AwsRekognitionClientFactoryTest.php tests/Unit/FaceSearch/FaceSearchRouterTest.php tests/Unit/FaceSearch/SelfiePreflightServiceTest.php tests/Feature/FaceSearch/FaceSearchSettingsTest.php tests/Feature/FaceSearch/FaceSearchSelfieEndpointsTest.php`
  - resultado:
    - `20 passed`
    - `113 assertions`
- contratos opt-in:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
  - resultado:
    - `7 passed`
    - `41 assertions`
- regressao ampla:
  - `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`
  - resultado:
    - `81 passed`
    - `7 skipped`
    - `1 failed`
  - falha residual:
    - `FaceSearchLocalDatasetManifestTest`
    - motivo: fixture local externa ausente em `C:\Users\Usuario\Desktop\vipsocial\55159264527_7f683b08f6_o.jpg`
  - leitura:
    - a falha ampla atual nao veio da trilha AWS;
    - ela veio de dependencia externa do dataset consentido local.

Bateria executada apos conclusao de `H2-T1` e `H2-T2`:

- backend:
  - `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`
  - resultado:
    - `89 passed`
    - `7 skipped`
    - `566 assertions`
- frontend:
  - `npm run type-check`
  - `npx.cmd vitest run src/modules/events/components/face-search`
  - resultado:
    - `PASS`
    - `5 passed`
- leitura:
  - a entrada de `provider_records`, `queries`, provisionamento de collection e form AWS nao abriu regressao visivel na base atual.

Bateria executada apos conclusao de `H2-T3` e `H3`:

- backend:
  - `php artisan test tests/Unit/FaceSearch/AwsRekognitionHealthCheckTest.php tests/Unit/FaceSearch/AwsImagePreprocessorTest.php tests/Unit/FaceSearch/AwsRekognitionFaceSearchBackendTest.php tests/Feature/FaceSearch/FaceIndexingPipelineTest.php tests/Unit/FaceSearch/FaceSearchRouterTest.php`
  - resultado:
    - `18 passed`
    - `103 assertions`
- regressao ampla:
  - `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`
  - resultado:
    - `96 passed`
    - `7 skipped`
    - `623 assertions`
- leitura:
  - `healthCheck`, preprocessamento `Image.Bytes`, `IndexFaces`, `UnindexedFaces` e gating de indexacao ficaram verdes sem regressao na suite do modulo.

Bateria executada para conclusao de `H4-T2`:

- backend dirigida:
  - `php artisan test tests/Unit/FaceSearch/AwsRekognitionFaceSearchBackendTest.php tests/Feature/FaceSearch/FaceSearchSelfieEndpointsTest.php tests/Unit/FaceSearch/FaceSearchQueryTest.php`
  - resultado:
    - `16 passed`
    - `130 assertions`
- contratos opt-in:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
  - resultado:
    - `7 passed`
    - `42 assertions`
- regressao ampla:
  - `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`
  - resultado:
    - `98 passed`
    - `7 skipped`
    - `659 assertions`
- leitura:
  - `SearchFacesByImage` e a auditoria em `face_search_queries` ficaram verdes no backend e na feature principal de selfie;
  - a suite ampla do modulo continuou estavel apos a entrada do fluxo AWS de busca.

Bateria executada para conclusao de `H4-T3`, `H5-T1` e `H5-T2`:

- bateria dirigida:
  - `php artisan test tests/Unit/FaceSearch/FaceSearchFailureClassifierTest.php tests/Unit/FaceSearch/FaceSearchRouterTest.php tests/Unit/FaceSearch/AwsRekognitionFaceSearchBackendTest.php tests/Unit/FaceSearch/SelfiePreflightServiceTest.php tests/Feature/FaceSearch/FaceSearchSelfieEndpointsTest.php`
  - resultado:
    - `29 passed`
    - `203 assertions`
- contratos opt-in:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
  - resultado:
    - `7 passed`
    - `42 assertions`
- regressao ampla:
  - `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`
  - resultado:
    - `108 passed`
    - `7 skipped`
    - `728 assertions`
- leitura:
  - o produto agora bloqueia foto de grupo com mensagem explicita de selfie-only;
  - falhas AWS agora entram no idioma operacional do app com `failure_class` e `reason_code`;
  - `aws_primary_local_fallback`, `local_primary_aws_on_error` e `aws_primary_local_shadow` passaram a ter base executavel no router e na auditoria da query.

Bateria executada para conclusao de `H5-T3`:

- bateria dirigida:
  - `php artisan test tests/Unit/FaceSearch/ShadowModeDecisionTest.php tests/Unit/FaceSearch/ReconcileAwsCollectionJobTest.php tests/Unit/FaceSearch/AwsRekognitionFaceSearchBackendTest.php tests/Unit/FaceSearch/FaceSearchRouterTest.php tests/Feature/FaceSearch/FaceSearchSelfieEndpointsTest.php`
  - resultado:
    - `28 passed`
    - `263 assertions`
- contratos opt-in:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
  - resultado:
    - `7 passed`
    - `42 assertions`
- regressao ampla:
  - `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`
  - resultado:
    - `112 passed`
    - `7 skipped`
    - `799 assertions`
- leitura:
  - o comparativo de shadow agora persiste latencia, interseccao, divergencia e diferenca de top match entre backend primario e shadow;
  - `ReconcileAwsCollectionJob` e a reconciliacao por `DescribeCollection + ListFaces` passaram a cobrir drift remoto/local sem apagar historico no escuro;
  - `H5` fechou com fallback, shadow e reconciliacao verdes na suite ampla do modulo.

Bateria executada para conclusao de `H7-T1`:

- backend dirigida:
  - `php artisan test tests/Feature/FaceSearch/FaceSearchOperationsTest.php tests/Unit/FaceSearch/AwsRekognitionFaceSearchBackendTest.php`
  - resultado:
    - `13 passed`
    - `123 assertions`
- frontend:
  - `npx.cmd vitest run src/modules/events/components/face-search/EventFaceSearchSettingsCard.test.tsx src/modules/events/components/face-search/EventFaceSearchSettingsForm.test.tsx`
  - resultado:
    - `7 passed`
- type-check:
  - `npm run type-check`
  - resultado:
    - `PASS`
- contratos opt-in:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
  - resultado:
    - `7 passed`
    - `42 assertions`
- regressao ampla:
  - `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`
  - resultado:
    - `118 passed`
    - `7 skipped`
    - `841 assertions`
- leitura:
  - o backend agora expoe endpoints operacionais de `health`, `reindex`, `reconcile` e `delete collection`;
  - o painel agora mostra status da collection e o ultimo health rodado na sessao operacional;
  - `DeleteCollection` entrou de forma idempotente com cleanup local de provider records;
  - a suite ampla continuou verde depois da entrada da camada operacional.

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

Status atual:

- [x] `AwsRekognitionClientFactory` implementado.
- [x] config dedicada adicionada em `config/aws.php` para o package oficial.
- [x] bloco `face_search.providers.aws_rekognition` implementado em `config/face_search.php`.
- [x] preparacao minima adicionada em `config/services.php`.
- [x] exemplos de env adicionados em `.env.example`.
- [x] retries e timeouts separados para query e indexacao implementados.
- [x] testes unitarios reais do factory adicionados.
- [x] pacote `aws/aws-sdk-php-laravel` instalado no `composer`.

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

Status atual:

- [x] `FaceSearchBackendInterface` criado.
- [x] `LocalPgvectorFaceSearchBackend` criado.
- [x] `AwsRekognitionFaceSearchBackend` criado com contrato base.
- [x] `FaceSearchRouter` criado e registrado no provider.
- [x] `SearchFacesBySelfieAction` refatorada para usar router.
- [x] `SelfiePreflightService` implementado antes do dispatch de busca.
- [x] testes unitarios reais de router e preflight adicionados.

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

Status atual:

- [x] migration backend dos campos AWS criada.
- [x] `EventFaceSearchSetting` expandido com defaults, fillable e casts AWS.
- [x] request, action e resource backend atualizados.
- [x] contrato feature de settings AWS ficou verde.
- [x] painel frontend agora expoe os campos AWS.
- [x] testes frontend do form foram expandidos e ficaram verdes.

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

Status atual:

- [x] migration `face_search_provider_records` criada.
- [x] migration `face_search_queries` criada.
- [x] models, factories e enum de status adicionados.
- [x] `external_image_id` persistido com contrato deterministico.
- [x] testes de model/factory e payload JSON verdes.

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

Status atual:

- [x] `EnsureAwsCollectionJob` criado.
- [x] `CreateCollection` e `DescribeCollection` implementados no backend AWS.
- [x] persistencia de `aws_collection_id`, `aws_collection_arn` e `aws_face_model_version` implementada.
- [x] `ResourceAlreadyExistsException` tratada como sucesso idempotente.
- [x] testes de job, backend e settings verdes.

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

Status atual:

- [x] `healthCheck` implementado no backend AWS.
- [x] `STS GetCallerIdentity` incorporado como probe de credencial.
- [x] `DescribeCollection` e `ListFaces` incorporados como probes operacionais de collection.
- [x] hints de IAM minimo do MVP adicionados ao payload de health.
- [x] testes unitarios de `healthy` e `misconfigured` verdes.

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

Status atual:

- [x] `AwsImagePreprocessor` criado.
- [x] resize deterministico para `long edge` configuravel implementado.
- [x] normalizacao para `image/jpeg` implementada.
- [x] budget de bytes validado por teste unitario.

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

Status atual:

- [x] `AwsRekognitionFaceSearchBackend::indexMedia()` implementado.
- [x] `IndexFaces` virou o happy path do backend AWS.
- [x] `FaceRecords` e `UnindexedFaces` agora persistem em `face_search_provider_records`.
- [x] cleanup de faces remotas antigas por midia implementado.
- [x] testes unitarios de indexacao e telemetria verdes.

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

Status atual:

- [x] `IndexMediaFacesAction` agora roteia por backend via `FaceSearchRouter`.
- [x] eventos sem `enabled=true` continuam gerando zero custo AWS.
- [x] eventos fora do backend AWS continuam na lane local.
- [x] faces que falham no gate local ou em media rejeitada sao removidas da collection e persistidas como `searchable=false`.
- [x] teste feature do pipeline AWS verde.

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

Status atual:

- [x] `AwsRekognitionFaceSearchBackend::searchBySelfie()` implementado com `SearchFacesByImage`.
- [x] derivado padronizado para `Image.Bytes` reutilizado na busca.
- [x] `FaceId` remoto agora mapeia para `event_media_id` local via `face_search_provider_records`.
- [x] `SearchFacesBySelfieAction` agora grava auditoria em `face_search_queries`.
- [x] request segue sincronico no HTTP.
- [x] testes unitarios e feature do fluxo AWS ficaram verdes.

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

Status atual:

- [x] `SelfiePreflightService` agora devolve mensagem explicita de selfie-only.
- [x] o fluxo principal bloqueia foto de grupo antes de consumir a busca gerenciada.
- [x] testes unitarios e feature cobrem o caso de varias pessoas na imagem.

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

Status atual:

- [x] `FaceSearchFailureClassifier` implementado.
- [x] erros AWS agora mapeiam `failure_class` + `reason_code`.
- [x] `AwsRekognitionFaceSearchBackend` agora usa `ProviderCircuitBreaker`.
- [x] testes de classificador e circuit breaker ficaram verdes.

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

- `FaceSearchFailureClassifierTest`
- testes de circuit breaker no backend AWS

Definicao de pronto:

- erros da AWS entram no mesmo idioma operacional do app.

### H5-T2. Implementar fallback local

Status atual:

- [x] `FaceSearchRouter` agora executa busca com primary/fallback/shadow por politica.
- [x] `aws_primary_local_fallback` implementado.
- [x] `local_primary_aws_on_error` implementado.
- [x] `aws_primary_local_shadow` implementado em modo inicial.
- [x] `SearchFacesBySelfieAction` agora registra qual backend respondeu e quando houve fallback.
- [x] testes unitarios e feature do fallback/shadow ficaram verdes.

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

Status atual:

- [x] `shadow_mode_percentage` agora executa shadow com comparativo rico por query.
- [x] o comparativo persiste:
  - `latency_ms`
  - `shared_event_media_ids`
  - `primary_only_event_media_ids`
  - `shadow_only_event_media_ids`
  - `top_match_same`
  - `divergence_ratio`
- [x] `ReconcileAwsCollectionJob` implementado.
- [x] `AwsRekognitionFaceSearchBackend::reconcileCollection()` implementado com `DescribeCollection + ListFaces`.
- [x] reconciliacao agora restaura registros locais que ainda existem na AWS, cria placeholders para faces remotas sem registro local e faz soft delete de faces locais ausentes na collection.
- [x] testes unitarios/feature da reconciliacao e do comparativo de shadow ficaram verdes.

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

Status atual:

- [x] o form continua expondo backend, fallback, thresholds AWS, `profile_key` e `search_mode`.
- [x] endpoints operacionais criados:
  - `GET /events/{event}/face-search/health`
  - `POST /events/{event}/face-search/reindex`
  - `POST /events/{event}/face-search/reconcile`
  - `DELETE /events/{event}/face-search/collection`
- [x] o painel agora mostra status da collection e o ultimo health rodado.
- [x] `reindex` agora garante provisionamento do backend antes de enfileirar `IndexMediaFacesJob`.
- [x] `delete collection` agora apaga a collection AWS de forma idempotente e faz soft delete dos `provider_records` locais.
- [x] testes backend e frontend de operacao ficaram verdes.

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

Status atual:

- [x] smoke real de imagem validado no fluxo completo `IndexFaces + SearchFacesByImage + DeleteFaces`.
- [x] checklist operacional do piloto definido neste plano.
- [ ] piloto com eventos reais ainda nao executado.
- [ ] metas reais de custo/latencia/fallback ainda nao foram medidas em producao.

Objetivo:

- trocar de backend com seguranca.

Subtarefas:

- definir piloto com poucos eventos;
- medir:
  - custo por evento
  - latencia por query
  - taxa de fallback
  - taxa de `UnindexedFaces`
  - taxa de divergencia do shadow
- so ampliar rollout depois de shadow mode aceitavel.

Preparacao recomendada do piloto:

- selecionar `1` a `3` eventos com:
  - reconhecimento facial explicitamente ativo
  - operador disponivel para acompanhar `health`, `reindex` e `reconcile`
  - galeria inicial pequena ou media na primeira semana de piloto
- comecar em `aws_primary_local_shadow` para medir divergencia sem trocar a resposta do produto;
- promover para `aws_primary_local_fallback` apenas depois de pelo menos `1` rodada de reindex completa e smoke real de selfie valida no evento;
- manter `local_primary_aws_on_error` como fallback reverso pronto para rollback rapido;
- revisar por evento:
  - `healthCheck`
  - `face_search_queries`
  - `face_search_provider_records`
  - `UnindexedFaces`
  - `shadow divergence`

Metas iniciais recomendadas para aceitar o piloto:

- `p95` de query AWS abaixo de `4000 ms`;
- taxa de fallback abaixo de `10%`;
- taxa de `UnindexedFaces` sem dominancia inesperada de `LOW_FACE_QUALITY` ou `SMALL_BOUNDING_BOX`;
- shadow divergence controlada e explicavel nos eventos piloto;
- zero drift critico depois de `reconcile`.

Criticos de rollback:

- `healthCheck` recorrente em estado `misconfigured` ou `provider_unavailable`;
- fallback sustentado acima da meta;
- shadow divergence alta sem explicacao operacional;
- custo por evento acima da banda aprovada para o piloto;
- aumento de reclamacao de selfie sem retorno no conjunto correto.

Testes obrigatorios:

- nao depende de teste unitario novo;
- depende de smoke real e observabilidade.

Definicao de pronto:

- piloto pequeno aprovado com custo, latencia, fallback e divergencia dentro da meta recomendada.

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

Com `H1`, `H2`, `H3`, `H4`, `H5` e `H7-T1` fechados, o proximo bloco deve ser:

1. executar o piloto operacional de `H7-T2` em `1` a `3` eventos com `aws_primary_local_shadow`;
2. medir custo por evento, latencia por query, taxa de fallback, taxa de `UnindexedFaces` e divergencia do shadow;
3. promover apenas eventos estaveis para `aws_primary_local_fallback`;
4. manter `H6` fora do caminho critico ate o piloto AWS estabilizar com shadow e reconciliacao ativos;
5. so depois abrir `SearchUsersByImage` e user vectors.
