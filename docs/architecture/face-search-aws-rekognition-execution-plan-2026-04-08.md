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
- [x] piloto real `H7-T2` executado em `3` eventos unitarios com `aws_primary_local_shadow`.
- [x] baseline local obrigatoria para shadow implementada no pipeline de indexacao.
- [x] seed local do shadow travada em variante `gallery` no fluxo operacional e na bateria automatizada.
- [x] envelope de rede AWS ajustado com `connect_timeout` e `timeout` menos agressivos por perfil.
- [x] preflight de selfie mais rigido para duo/group edge cases implementado.
- [x] rerodada real curta de `H7-T2` executada apos os ajustes do piloto.
- [x] rerodada real de `H7-T2` executada com probes derivados elegiveis para o novo preflight.
- [x] gate de faces pesquisaveis da baseline local alinhado ao resultado primario AWS quando a midia nao sobrevive ao gate remoto.
- [x] rerodada real de `H7-T2` executada com probes saindo apenas de midias que sobreviveram ao gate primario AWS.
- [x] detector local/CompreFace estabilizado para a janela curta do piloto com timeout e retry de deteccao mais tolerantes.
- [x] `H7-T2` aprovado para promocao controlada de eventos estaveis para `aws_primary_local_fallback`.
- [x] promocao controlada executada em `3` eventos estaveis com `aws_primary_local_fallback`, `sync-index`, `sync-reconcile` e rollback pronto.
- [x] soak curto executado nos eventos `346`, `347` e `348` promovidos para `aws_primary_local_fallback`.
- [x] validacao funcional de produto documentada para toggle por evento, reindex do acervo legado e latencia real observada apos envio da selfie.
- [x] primeiro corte de user vectors AWS implementado com readiness gate, `CreateUser`, `AssociateFaces`, `SearchUsersByImage` e fallback seguro para `faces`.
- [x] comando operacional `face-search:validate-aws-users-high-cardinality` implementado com criterio objetivo de latencia, fallback, resolucao de `users` e taxa de match.
- [x] defaults do comando reparados para o corte homolog/prod-like atual de `~30 users` prontos.
- [x] validacao limitada da pasta `FINAL` executada em evento tecnico `349`, registrando achados operacionais reais e um report versionado.
- [x] rodada real de `users` executada em evento tecnico multi-identidade `354`, com `LFW` exportado em `30` identidades.
- [x] validacao final de `users` passou em collection limpa com `ready_users=25`, `users_mode_resolution_rate=1.0`, `fallback_rate=0`, `top_1_match_rate=0.95`, `top_k_match_rate=0.95` e `p95_response_duration_ms=482`.
- [x] observabilidade curta do FaceSearch endurecida com logs estruturados para `query.completed`, `query.validation_failed`, `query.failed`, `router.fallback_triggered`, `router.shadow_failed` e `aws.operation_failed`.
- [ ] ainda e recomendado repetir a rodada em um evento organico antes do rollout amplo final, mas o gating tecnico de `~30 users` ja foi fechado.

Ultima bateria executada:

- comando:
  - `php artisan test tests/Feature/Events/CreateEventTest.php tests/Feature/FaceSearch tests/Unit/FaceSearch`
- resultado:
  - `158 passed`
  - `7 skipped`
  - `1152 assertions`
- leitura:
  - a rodada final incluiu o `CRUD` de `Events` para validar persistencia do toggle base de `FaceSearch` no create/update do evento;
  - a trilha `H1 + H2 + H3 + H4 + H5 + H6 + H7-T1 + H7-T2` continuou estavel depois da entrada da validacao operacional real de `users`, do rebuild limpo da collection tecnica e da telemetria estruturada do FaceSearch;
  - os `7 skipped` continuam sendo apenas os contratos TDD AWS em modo opt-in;
  - a regressao ampla do modulo permaneceu totalmente verde nesta rodada, incluindo user vectors, `SearchUsersByImage`, backfill legado automatico, o guard rail para `provider_key=noop`, o novo perfil default de `~30 users` no comando `face-search:validate-aws-users-high-cardinality` e os logs estruturados da trilha de busca;
  - os casos idempotentes esperados da AWS (`ResourceAlreadyExistsException`, `ConflictException` em `CreateUser` e `ResourceNotFoundException` em `DeleteCollection`) nao geram falso `aws.operation_failed`.

Bateria de contratos AWS opt-in executada:

- comando:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
- resultado:
  - `7 passed`
  - `42 assertions`
- leitura:
  - os contratos arquiteturais AWS continuam verdes quando a bateria opt-in e explicitamente ligada.

Bateria frontend executada:

- comandos:
  - `npm run type-check`
  - `npx.cmd vitest run src/modules/events/components/face-search/EventFaceSearchSettingsCard.test.tsx src/modules/events/components/face-search/EventFaceSearchSettingsForm.test.tsx`
- resultado:
  - `type-check = PASS`
  - `EventFaceSearchSettingsForm.test.tsx + EventFaceSearchSettingsCard.test.tsx = 7 passed`
- leitura:
  - o painel agora aceita o contrato AWS por evento e expoe operacao de `health/reindex/reconcile/delete collection` sem quebrar tipagem nem fluxo do form.

Bateria executada para validar a lista final de faltantes de UX e produto:

- backend:
  - `php artisan test tests/Feature/Events/CreateEventTest.php tests/Feature/FaceSearch/FaceSearchSettingsTest.php tests/Feature/FaceSearch/FaceSearchSelfieEndpointsTest.php`
  - resultado:
    - `34 passed`
    - `306 assertions`
- frontend:
  - `npx.cmd vitest run src/modules/face-search/face-search-product-ux-characterization.test.ts src/modules/events/components/face-search/EventFaceSearchSettingsCard.test.tsx src/modules/events/components/face-search/EventFaceSearchSettingsForm.test.tsx src/modules/face-search/components/FaceSearchSearchPanel.test.tsx src/modules/face-search/components/EventFaceSearchSearchCard.test.tsx src/modules/face-search/PublicFaceSearchPage.test.tsx`
  - resultado:
    - `15 passed`
- type-check:
  - `npm.cmd run type-check`
  - resultado:
    - `PASS`
- leitura:
  - a rodada confirmou em codigo e teste que:
    - o CRUD simples do evento ja persiste a camada basica de `FaceSearch`;
    - a busca interna e publica por selfie ja existem no frontend;
    - naquele recorte, a galeria e o hub publicos ainda nao chamavam diretamente a experiencia `Encontrar minhas fotos`;
    - naquele recorte, o editor simples ainda usava a linguagem `Busca por selfie`.

Bateria executada apos a validacao funcional de produto:

- backend:
  - `php artisan test tests/Feature/Events/CreateEventTest.php tests/Feature/FaceSearch tests/Unit/FaceSearch`
  - resultado:
    - `146 passed`
    - `7 skipped`
    - `1054 assertions`
- contratos opt-in:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
  - resultado:
    - `7 passed`
    - `42 assertions`
- frontend:
  - `npx.cmd vitest run src/modules/events/components/face-search/EventFaceSearchSettingsCard.test.tsx src/modules/events/components/face-search/EventFaceSearchSettingsForm.test.tsx`
  - resultado:
    - `7 passed`
- type-check:
  - `npm.cmd run type-check`
  - resultado:
    - `PASS`
- leitura:
  - a rodada final fechou o comportamento do produto em tres camadas:
    - CRUD base do evento;
    - painel dedicado de settings AWS por evento;
    - operacao de query/indexacao do modulo `FaceSearch`.

Bateria executada apos a implementacao de `H6` e do backfill automatico:

- backend:
  - `php artisan test tests/Feature/Events/CreateEventTest.php tests/Feature/FaceSearch tests/Unit/FaceSearch`
  - resultado:
    - `153 passed`
    - `7 skipped`
    - `1103 assertions`
- contratos opt-in:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
  - resultado:
    - `7 passed`
    - `42 assertions`
- frontend:
  - `npx.cmd vitest run src/modules/events/components/face-search/EventFaceSearchSettingsCard.test.tsx src/modules/events/components/face-search/EventFaceSearchSettingsForm.test.tsx`
  - resultado:
    - `7 passed`
- type-check:
  - `npm.cmd run type-check`
  - resultado:
    - `PASS`
- leitura:
  - `H6-T1`, `H6-T2` e o primeiro corte de `H6-T3` ficaram implementados sem abrir regressao no modulo;
  - a ativacao AWS por evento agora enfileira o backfill do acervo legado automaticamente, reduzindo o atrito operacional do go-live;
  - a trilha de `users` continua com fallback transparente para `faces` quando o vetor de usuario ainda nao ficou pronto.

Bateria executada apos a promocao controlada para `aws_primary_local_fallback`:

- backend dirigido:
  - `php artisan test tests/Feature/FaceSearch/PromoteAwsFallbackRolloutCommandTest.php tests/Feature/FaceSearch/RollbackAwsFallbackRolloutCommandTest.php`
  - resultado:
    - `3 passed`
    - `23 assertions`
- backend amplo:
  - `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`
  - resultado:
    - `129 passed`
    - `7 skipped`
    - `907 assertions`
- contratos opt-in:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
  - resultado:
    - `7 passed`
    - `42 assertions`
- frontend:
  - `npx.cmd vitest run src/modules/events/components/face-search/EventFaceSearchSettingsCard.test.tsx src/modules/events/components/face-search/EventFaceSearchSettingsForm.test.tsx`
  - resultado:
    - `7 passed`
- type-check:
  - `npm run type-check`
  - resultado:
    - `PASS`
- leitura:
  - a promocao controlada saiu acompanhada por cobertura real de rollout/rollback, sem abrir regressao no modulo nem no painel.

Bateria executada apos o soak curto em `aws_primary_local_fallback`:

- backend dirigido:
  - `php artisan test tests/Unit/FaceSearch/RunAwsFallbackSoakActionTest.php tests/Feature/FaceSearch/RunAwsFallbackSoakCommandTest.php`
  - resultado:
    - `3 passed`
    - `23 assertions`
- backend amplo:
  - `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`
  - resultado:
    - `132 passed`
    - `7 skipped`
    - `930 assertions`
- contratos opt-in:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
  - resultado:
    - `7 passed`
    - `42 assertions`
- frontend:
  - `npx.cmd vitest run src/modules/events/components/face-search/EventFaceSearchSettingsCard.test.tsx src/modules/events/components/face-search/EventFaceSearchSettingsForm.test.tsx`
  - resultado:
    - `7 passed`
- type-check:
  - `npm run type-check`
  - resultado:
    - `PASS`
- leitura:
  - o soak curto entrou como operacao reproduzivel com cobertura automatizada e sem abrir regressao no modulo nem no painel.

Bateria executada apos a rodada real de `H7-T2`:

- backend:
  - `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`
  - resultado:
    - `118 passed`
    - `7 skipped`
    - `841 assertions`
- contratos opt-in:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
  - resultado:
    - `7 passed`
    - `42 assertions`
- frontend:
  - `npx.cmd vitest run src/modules/events/components/face-search/EventFaceSearchSettingsCard.test.tsx src/modules/events/components/face-search/EventFaceSearchSettingsForm.test.tsx`
  - resultado:
    - `7 passed`
- type-check:
  - `npm run type-check`
  - resultado:
    - `PASS`
- leitura:
  - a rodada de validacao real do piloto nao abriu regressao automatizada no modulo nem no painel operacional.

Bateria de contratos opt-in reexecutada apos o smoke real com imagem:

- comando:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
- resultado:
  - `7 passed`
  - `42 assertions`
- leitura:
  - a fundacao contratual da integracao AWS continuou intacta depois do rerun operacional e do smoke real com imagem.

Bateria executada apos a correcao dos achados do piloto:

- bateria dirigida:
  - `php artisan test tests/Unit/FaceSearch/FaceSearchRouterTest.php tests/Unit/FaceSearch/SelfiePreflightServiceTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryTest.php tests/Unit/FaceSearch/CompreFaceDetectionProviderTest.php tests/Feature/FaceSearch/FaceIndexingPipelineTest.php tests/Feature/FaceSearch/FaceSearchSelfieEndpointsTest.php`
  - resultado:
    - `39 passed`
    - `260 assertions`
- regressao ampla:
  - `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`
  - resultado:
    - `123 passed`
    - `7 skipped`
    - `1 failed`
  - observacao:
    - a unica falha restante continua externa a esta rodada: `FaceSearchLocalDatasetManifestTest` depende do fixture local `C:\Users\Usuario\Desktop\vipsocial\55159264527_7f683b08f6_o.jpg`, ausente no contexto atual do workspace.
- contratos opt-in:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
  - resultado:
    - `7 passed`
    - `42 assertions`
- leitura:
  - as correcoes do piloto ficaram verdes em TDD;
  - a regressao ampla do modulo continuou estavel, com apenas o fixture local externo ainda falhando.

Bateria executada apos a rerodada real com probes elegiveis:

- regressao ampla:
  - `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`
  - resultado:
    - `124 passed`
    - `7 skipped`
    - `875 assertions`
- contratos opt-in:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
  - resultado:
    - `7 passed`
    - `42 assertions`
- frontend:
  - `npx.cmd vitest run src/modules/events/components/face-search/EventFaceSearchSettingsCard.test.tsx src/modules/events/components/face-search/EventFaceSearchSettingsForm.test.tsx`
  - resultado:
    - `7 passed`
- type-check:
  - `npm run type-check`
  - resultado:
    - `PASS`
- leitura:
  - a rerodada real do piloto nao abriu regressao no modulo;
  - a bateria contratual AWS continuou verde apos a calibracao de shadow baseline, preflight e envelope de rede;
  - o painel operacional de `FaceSearch` continuou verde depois da rerodada real do piloto.

Bateria executada apos o alinhamento do gate de shadow baseline:

- bateria dirigida:
  - `php artisan test tests/Feature/FaceSearch/FaceIndexingPipelineTest.php tests/Unit/FaceSearch/FaceSearchRouterTest.php`
  - resultado:
    - `16 passed`
    - `76 assertions`
- regressao ampla:
  - `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`
  - resultado:
    - `125 passed`
    - `7 skipped`
    - `882 assertions`
- contratos opt-in:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
  - resultado:
    - `7 passed`
    - `42 assertions`
- frontend:
  - `npx.cmd vitest run src/modules/events/components/face-search/EventFaceSearchSettingsCard.test.tsx src/modules/events/components/face-search/EventFaceSearchSettingsForm.test.tsx`
  - resultado:
    - `7 passed`
- type-check:
  - `npm run type-check`
  - resultado:
    - `PASS`
- leitura:
  - o gate alignment entrou sem quebrar a pipeline do modulo;
  - a bateria completa continuou verde antes da nova leitura do piloto real.

Bateria executada apos estabilizar o detector local/CompreFace:

- unit direcionada:
  - `php artisan test tests/Unit/FaceSearch/CompreFaceClientTest.php tests/Unit/FaceSearch/CompreFaceDetectionProviderTest.php`
  - resultado:
    - `12 passed`
    - `38 assertions`
- regressao ampla:
  - `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`
  - resultado:
    - `125 passed`
    - `7 skipped`
    - `882 assertions`
- contratos opt-in:
  - `$env:RUN_FACE_SEARCH_AWS_TDD='1'; php artisan test tests/Unit/FaceSearch/FaceSearchAwsConfigContractTest.php tests/Unit/FaceSearch/AwsRekognitionClientFactoryContractTest.php tests/Unit/FaceSearch/FaceSearchRouterContractTest.php tests/Unit/FaceSearch/SelfiePreflightServiceContractTest.php tests/Feature/FaceSearch/FaceSearchAwsSettingsContractTest.php tests/Unit/FaceSearch/SearchFacesBySelfieAwsArchitectureContractTest.php tests/Unit/FaceSearch/FaceSearchProviderRecordContractTest.php`
  - resultado:
    - `7 passed`
    - `42 assertions`
- frontend:
  - `npx.cmd vitest run src/modules/events/components/face-search/EventFaceSearchSettingsCard.test.tsx src/modules/events/components/face-search/EventFaceSearchSettingsForm.test.tsx`
  - resultado:
    - `7 passed`
- type-check:
  - `npm run type-check`
  - resultado:
    - `PASS`
- leitura:
  - o novo envelope do detector local ficou verde em TDD;
  - a bateria completa do modulo continuou estavel antes da rerodada real `3/3` do piloto.

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

Piloto real `H7-T2` executado em `2026-04-09`:

- script operacional criado:
  - `apps/api/scripts/face-search-aws-h7-pilot.php`
- variaveis necessarias para o modulo real:
  - `FACE_SEARCH_AWS_ACCESS_KEY_ID`
  - `FACE_SEARCH_AWS_SECRET_ACCESS_KEY`
  - `FACE_SEARCH_AWS_REGION`
- descoberta operacional:
  - o app real nao estava lendo as credenciais do smoke anterior, porque o smoke usava `FACE_SEARCH_AWS_SMOKE_*` e o modulo usa `FACE_SEARCH_AWS_*`;
  - foi necessario alinhar o `.env` local do modulo para a rodada real.
- descoberta operacional adicional:
  - para o shadow local com `CompreFace`, o piloto precisou usar variante `gallery` normalizada;
  - com originais maiores, o `CompreFace` respondeu `413 Request Entity Too Large` durante a seed local.
- relatorios bem-sucedidos:
  - `storage/app/private/face-search-aws-pilot/20260409-042658-face-search-aws-h7-pilot.json`
  - `storage/app/private/face-search-aws-pilot/20260409-043515-face-search-aws-h7-pilot.json`
  - `storage/app/private/face-search-aws-pilot/20260409-043548-face-search-aws-h7-pilot.json`
- consolidado:
  - `storage/app/private/face-search-aws-pilot/20260409-aggregate-face-search-aws-h7-pilot.json`
- lote em batch tambem executado:
  - `storage/app/private/face-search-aws-pilot/20260409-043401-face-search-aws-h7-pilot.json`
  - leitura:
    - o lote de `3` eventos consecutivos registrou `cURL error 28` em `CreateCollection` e `IndexFaces` com `connect_timeout=3s`;
    - isso reforca que o piloto pequeno deve continuar serializado e observado antes de qualquer ampliacao.

Resumo consolidado dos `3` eventos unitarios bem-sucedidos:

- `events_executed=3`
- `estimated_processing_cost_usd=0.013`
- `estimated_storage_cost_usd_monthly_if_kept=0.00006`
- `total_queries_attempted=4`
- `total_queries_blocked_by_preflight=2`
- `fallback_count=0`
- `avg_fallback_rate=0.0`
- `searchable_remote_faces=6`
- `total_unindexed_faces=0`
- `shadow_queries_completed=4`
- `avg_shadow_divergence=0.875`
- `shadow_top_match_same_rate=0.0`
- `avg_response_duration_ms=1883.75`
- `p95_response_duration_ms=1891`

Leitura do piloto:

- custo de processamento ficou baixo no recorte pequeno;
- fallback nao foi acionado nas queries que chegaram ao backend AWS;
- `UnindexedFaces` ficou zerado nesse recorte pequeno;
- a divergencia do shadow ficou alta demais para aprovar ampliacao:
  - `avg_shadow_divergence=0.875`
  - `shadow_top_match_same_rate=0.0`
- o gate de qualidade da AWS no modulo atual rejeitou varias faces que o lane local aceitou como pesquisaveis;
- a enforcement de selfie-only nao e absoluta no mundo real:
  - em `1` dos pilotos, uma imagem de duas pessoas passou pelo preflight e virou query com `0` resultado;
  - isso mostra que o bloqueio de foto de grupo hoje depende do recall do detector local.

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
- o primeiro corte de `CreateUser + AssociateFaces + SearchUsersByImage` ja entrou em `H6`, ainda dependendo apenas de validacao operacional de alta cardinalidade antes do rollout amplo.
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
- readiness gate para `users`;
- `CreateUser + AssociateFaces + SearchUsersByImage` com fallback seguro para `faces`;
- backfill automatico do acervo legado quando o lane AWS e ativado pela primeira vez.

Nao entram na fase 1:

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
- `H6` ja entregue:
  - `CreateUser`
  - `AssociateFaces`
  - `SearchUsersByImage`
- ainda pendente:
  - validacao operacional de alta cardinalidade para liberar rollout amplo de `users`

Logica central:

1. se o evento nao estiver com reconhecimento ativo, nao toca AWS;
2. se o evento usar backend AWS, provisiona ou reusa collection do evento;
3. na indexacao, gera derivado JPEG local, tenta `Bytes`, chama `IndexFaces`, persiste faces indexadas e nao indexadas;
4. na selfie, faz preflight local barato antes de gastar request AWS;
5. se a selfie passar no preflight, chama `SearchUsersByImage` quando `aws_search_mode=users` e existirem user vectors prontos; caso contrario, cai de forma segura para `SearchFacesByImage`;
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

Status atual:

- [x] readiness gate implementado em `AwsUserVectorReadinessService`.
- [x] o gate agora exige:
  - minimo de `5` faces boas por cluster;
  - variacao minima de `yaw`;
  - variacao minima de `pitch`;
  - match local por bounding box para reaproveitar embeddings ja existentes no lane local.
- [x] cobertura criada em `AwsUserVectorReadinessTest`.

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

Status atual:

- [x] `SyncAwsUserVectorJob` criado.
- [x] `CreateUser` com `ClientRequestToken` deterministico implementado no backend AWS.
- [x] `AssociateFaces` em lotes de ate `100` faces implementado.
- [x] sucesso e falha por face agora ficam persistidos em `provider_payload_json.aws_user_vector`.
- [x] cobertura criada em:
  - `SyncAwsUserVectorJobTest`
  - `AwsRekognitionFaceSearchBackendTest`

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

Status atual:

- [x] `searchBySelfie()` agora respeita `aws_search_mode=users`.
- [x] quando ainda nao existem user vectors prontos, o backend cai automaticamente para `faces`.
- [x] o audit payload da query agora registra:
  - `search_mode_requested`
  - `search_mode_resolved`
  - `search_mode_fallback_reason`
- [x] validacao operacional ganhou um comando dedicado para `users` com relatorio estruturado e thresholds objetivos.
- [x] a rodada tecnica de `users` ja passou no corte atual de `~30 users` com report versionado e collection limpa.
- [ ] ainda vale repetir essa rodada em um evento organico antes do rollout amplo.

Objetivo:

- subir precisao em pessoas recorrentes.

Subtarefas:

- respeitar `aws_search_mode=users`;
- manter fallback para `faces` se o user vector ainda nao estiver pronto;
- auditar claramente qual modo respondeu.

Testes obrigatorios:

- [x] cobertura inicial absorvida por `AwsRekognitionFaceSearchBackendTest`
- [ ] expandir `FaceSearchSelfieEndpointsTest` com asserts especificos de `users` quando a surface publica/admin precisar expor esse detalhe
- [ ] `AwsUserSearchModeTest` dedicado continua opcional para a proxima rodada, se o fluxo de `users` ganhar mais branching proprio

Definicao de pronto:

- evento pode optar por `users` sem quebrar o fluxo para quem ainda esta em `faces`.

### H6-T4. Validacao operacional de alta cardinalidade para `users`

Status atual:

- [x] comando `face-search:validate-aws-users-high-cardinality` criado.
- [x] defaults do comando agora nascem alinhados ao corte homolog/prod-like atual de `~30 users`:
  - `sample_users=20`
  - `min_ready_users=20`
  - `target_ready_users=30`
  - `max_fallback_rate=0.05`
  - `min_users_mode_resolution_rate=0.95`
  - `min_top1_match_rate=0.85`
  - `min_topk_match_rate=0.95`
  - `max_p95_latency_ms=1500`
- [x] a rodada agora mede por relatorio:
  - `ready_user_count`
  - `users_mode_resolution_rate`
  - `fallback_rate`
  - `top_1_match_rate`
  - `top_k_match_rate`
  - `avg_response_duration_ms`
  - `p95_response_duration_ms`
- [x] `AwsUserHighCardinalityProbeBuilder` agora deriva probes a partir de clusters `ready` como ground truth positivo do proprio evento.
- [x] thresholds de aprovacao ficaram explicitos no comando:
  - `min_ready_users`
  - `target_ready_users`
  - `max_fallback_rate`
  - `min_users_mode_resolution_rate`
  - `min_top1_match_rate`
  - `min_topk_match_rate`
  - `max_p95_latency_ms`
- [x] guard rail operacional novo:
  - a validacao agora aborta cedo com `skipped_reason=local_baseline_provider_noop` quando o evento usa fallback/shadow local, mas `provider_key=noop`
  - isso evita rodar `users` sobre um lane em que a baseline local esta morta no nascimento
- [x] validacao limitada usando a pasta `C:\Users\Usuario\Desktop\ddddd\FINAL`:
  - inventario da pasta:
    - `963` arquivos
    - extensao unica: `.jpg`
    - volume total aproximado: `6.19 GB`
    - tamanho medio aproximado: `6.42 MB`
    - prefixo de nome dominante: `Natali`
  - leitura:
    - o dataset serve para validacao operacional limitada de importacao/indexacao
    - ele nao serve como evidencia de `~30 users` porque e mono-identidade e ainda inclui imagens sem rosto elegivel
  - evento tecnico criado:
    - `event_id=349`
    - titulo: `FaceSearch FINAL limited validation 40`
    - collection AWS: `eventovivo-face-search-event-349`
  - report real:
    - `apps/api/storage/app/private/face-search-users-high-cardinality/20260409-195741-face-search-aws-users-high-cardinality.json`
    - `status=skipped`
    - `skipped_reason=no_ready_user_vectors`
  - estado consolidado apos a rodada:
    - `provider_records=15`
    - `aws_searchable_records=0`
    - `aws_user_ids=0`
    - `local_faces=7`
    - `local_searchable_faces=0`
- [x] evento tecnico multi-identidade `354` criado a partir de `LFW`:
  - export:
    - `30` identidades
    - `6` imagens por identidade
    - manifest: `apps/api/storage/app/face-search-datasets/lfw/20260409-205710-lfw/manifest.json`
  - primeira rodada real positiva apos recalibracao de gate:
    - report: `apps/api/storage/app/private/face-search-users-validation-seed/20260409-213046-lfw-users-validation-30-q015.json`
    - `aws_searchable=190`
    - `ready_count=25`
  - primeira validacao em collection reaproveitada:
    - report: `apps/api/storage/app/private/face-search-users-high-cardinality/20260409-213831-face-search-aws-users-high-cardinality.json`
    - leitura:
      - `users_mode_resolution_rate=1.0`
      - `fallback_rate=0`
      - `p95_response_duration_ms=423`
      - `top_1_match_rate=0.40`
      - `top_k_match_rate=0.75`
      - o problema nao era latencia; era drift de collection/reassociacao depois de varias recalibracoes no mesmo evento
  - rebuild limpo da collection e rerodada final:
    - seed report: `apps/api/storage/app/private/face-search-users-validation-seed/20260409-214357-lfw-users-validation-30-clean-q015.json`
    - report final de validacao: `apps/api/storage/app/private/face-search-users-high-cardinality/20260409-214510-face-search-aws-users-high-cardinality.json`
    - metricas finais:
      - `ready_users=25`
      - `users_mode_resolution_rate=1.0`
      - `fallback_rate=0`
      - `top_1_match_rate=0.95`
      - `top_k_match_rate=0.95`
      - `p95_response_duration_ms=482`
      - `passed=true`
- [x] aprendizado operacional novo:
  - se o evento passou por varias recalibracoes de gate ou reindexs pesados durante a validacao de `users`, a collection deve ser recriada antes da rodada final
  - na pratica:
    - `delete collection`
    - `reindex`
    - `sync user vectors`
    - so depois `validate-aws-users-high-cardinality`
- [x] observabilidade curta do fluxo ponta a ponta reforcada:
  - canal: `storage/logs/queue-telemetry-YYYY-MM-DD.log`
  - eventos novos:
    - `face_search.query.completed`
    - `face_search.query.validation_failed`
    - `face_search.query.failed`
    - `face_search.router.fallback_triggered`
    - `face_search.router.shadow_failed`
    - `face_search.aws.operation_failed`
  - campos praticos:
    - `event_id`
    - `face_search_request_id`
    - `face_search_query_id`
    - `routing_policy`
    - `primary_backend_key`
    - `response_backend_key`
    - `search_mode_requested`
    - `search_mode_resolved`
    - `response_duration_ms`
  - ruido operacional evitado:
    - `ResourceAlreadyExistsException` em `CreateCollection`
    - `ConflictException` em `CreateUser`
    - `ResourceNotFoundException` em `DeleteCollection`
    - esses casos continuam sendo tratados como idempotencia esperada, nao falha real de AWS
- [ ] o inventario organico local de `2026-04-09` ainda nao trouxe um candidato real acima de `4` registros AWS pesquisaveis, entao a rodada final ficou tecnicamente fechada em dataset multi-identidade controlado, nao em galeria organica.

Objetivo:

- fechar a liberacao de `users` com criterio operacional objetivo, nao por impressao visual.

Linha operacional homolog/prod-like atual (`~30 users`):

1. precondicoes minimas antes do comando:
   - `enabled=true`
   - `recognition_enabled=true`
   - `search_backend_key=aws_rekognition`
   - `aws_search_mode=users`
   - se `routing_policy` usar fallback/shadow local, `provider_key` precisa ser `compreface`, nunca `noop`
2. preparo de acervo:
   - deixar o backfill/reindex do evento concluir
   - rodar `reconcile` do evento antes da amostra final
   - observar que `SyncAwsUserVectorJob` ja e disparado automaticamente pelo `IndexMediaFacesJob` e pelo `ReconcileAwsCollectionJob`
3. linha exata recomendada para a rodada:
   - `php artisan face-search:validate-aws-users-high-cardinality <event_id> --sample-users=20 --min-ready-users=20 --target-ready-users=30 --max-fallback-rate=0.05 --min-users-mode-resolution-rate=0.95 --min-top1-match-rate=0.85 --min-topk-match-rate=0.95 --max-p95-latency-ms=1500`
4. observacao:
   - a chamada curta `php artisan face-search:validate-aws-users-high-cardinality <event_id>` hoje ja aplica exatamente esse mesmo perfil default
5. quando houver recalibracao forte antes da rodada final:
   - recriar a collection do evento
   - rerodar `reindex`
   - deixar o `SyncAwsUserVectorJob` convergir
   - so depois capturar o report final

Subtarefas:

- selecionar evento com massa suficiente de user vectors prontos;
- rodar o comando com thresholds explicitos;
- capturar latencia, fallback, resolucao real de `users` e match rate;
- guardar report versionado antes de declarar o lane AWS como `100%`.

Testes obrigatorios:

- `AwsUserHighCardinalityProbeBuilderTest`
- `RunAwsUsersHighCardinalityValidationActionTest`
- `RunAwsUsersHighCardinalityValidationCommandTest`

Definicao de pronto:

- existe pelo menos um report real de evento grande mostrando:
  - `users_mode_resolution_rate` dentro do threshold acordado;
  - `fallback_rate` dentro do threshold acordado;
  - `top_1_match_rate` e `top_k_match_rate` dentro do threshold acordado;
  - `p95_response_duration_ms` dentro do threshold acordado;
  - `min_ready_users` atendido no corte homolog/prod-like atual de `~30` pessoas;
  - se o produto depois quiser certificacao de escala maior, a mesma rodada deve ser repetida com override explicito para `100`/`200+` users ou acima.

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
- [x] piloto real executado em `3` eventos unitarios com `aws_primary_local_shadow`.
- [x] metricas reais de custo, latencia, fallback, `UnindexedFaces` e divergencia foram capturadas.
- [x] baseline local obrigatoria para shadow agora roda junto com a indexacao primaria.
- [x] seed local do shadow agora usa variante `gallery` no proprio pipeline.
- [x] envelope de rede AWS ficou menos agressivo para burst com timeouts por perfil.
- [x] preflight de selfie ficou mais rigido para duo/group edge cases.
- [x] rerodada real curta apos as correcoes executada com collection saudavel antes/depois.
- [x] rerodada real com probes derivados elegiveis voltou a medir query-side shadow em `3` eventos.
- [x] baseline local agora despromove `searchable=true` quando a midia nao sobrevive ao gate primario AWS.
- [x] rerodada real priorizou probes de query apenas em midias que sobreviveram ao gate remoto.
- [x] detector local/CompreFace estabilizado para a janela curta do piloto com timeout e retry de deteccao mais tolerantes.
- [x] piloto pequeno agora atende as metas recomendadas desta fase.
- [x] `H7-T2` aprovado para promocao controlada de eventos estaveis para `aws_primary_local_fallback`.
- [x] promocao controlada executada em `3` eventos estaveis com `aws_primary_local_fallback`, `sync-index`, `sync-reconcile` e rollback pronto.
- [x] soak curto executado em `346`, `347` e `348` ja promovidos, sem fallback e sem drift apos `reconcile`.
- [x] a calibracao minima do pool de selfies do piloto foi destravada com probes derivados elegiveis.

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
  - seed local do shadow com baseline local real

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

Resultado real desta rodada:

- `3` eventos unitarios serializados rodaram com cleanup remoto ao final;
- um lote separado de `3` eventos consecutivos falhou parcialmente com `cURL error 28`, indicando que o envelope atual de rede continua apertado para bursts;
- o custo do recorte pequeno ficou aceitavel;
- a latencia de query ficou aceitavel;
- o shadow ainda nao ficou aceitavel para rollout:
  - divergencia media alta
  - `top_match_same` zerado
  - parte do acervo considerado pesquisavel localmente nao sobreviveu ao gate AWS atual.

Rerodada curta apos as correcoes em `2026-04-09`:

- relatorio:
  - `apps/api/storage/app/private/face-search-aws-pilot/20260409-050119-face-search-aws-h7-pilot.json`
- o pipeline passou a registrar baseline local obrigatoria em `pipeline.shadow` para todos os itens indexados;
- o `source_ref` do shadow local ficou preso na variante `gallery`, sem voltar ao original bruto;
- a collection ficou `healthy` antes e depois da rodada;
- nesta rerodada de `1` evento nao apareceu novo `cURL error 28`;
- a foto de grupo do piloto passou a ser bloqueada no preflight;
- em compensacao, as `3` selfies candidatas do piloto tambem foram bloqueadas pelo guard rail novo;
- por isso, a rodada nao mediu divergencia de query nem `top_match_same`, apenas estabilizou a parte de indexacao/shadow baseline.

Rerodada com probes derivados elegiveis em `2026-04-09`:

- relatorio:
  - `apps/api/storage/app/private/face-search-aws-pilot/20260409-051854-face-search-aws-h7-pilot.json`
- estrategia:
  - a query valida deixou de depender do pool bruto e passou a usar um crop derivado da face dominante, validado pelo proprio `SelfiePreflightService`
  - a query de foto de grupo continuou existindo como caso de bloqueio explicito
- resumo consolidado:
  - `events_executed=3`
  - `estimated_processing_cost_usd=0.012`
  - `estimated_storage_cost_usd_monthly_if_kept=0.00011`
  - `avg_fallback_rate=0`
  - `avg_shadow_divergence=0.388889`
  - `total_unindexed_faces=0`
  - `total_queries_attempted=3`
  - `total_queries_blocked_by_preflight=3`
- resultado por evento:
  - `event_id=340`
    - `valid_selfie_status=completed`
    - `response_duration_ms=667`
    - `match_count=1`
    - `shadow_divergence_ratio=0.5`
    - `shadow_top_match_same=false`
    - `group_photo=blocked_validation`
  - `event_id=341`
    - `valid_selfie_status=completed`
    - `response_duration_ms=599`
    - `match_count=3`
    - `shadow_divergence_ratio=0`
    - `shadow_top_match_same=true`
    - `group_photo=blocked_validation`
  - `event_id=342`
    - `valid_selfie_status=completed`
    - `response_duration_ms=585`
    - `match_count=1`
    - `shadow_divergence_ratio=0.666667`
    - `shadow_top_match_same=false`
    - `group_photo=blocked_validation`
- leitura:
  - a medicao de query-side shadow voltou a existir;
  - o preflight bloqueou foto de grupo nos `3` eventos;
  - nao houve fallback em nenhuma query valida da rerodada;
  - a divergencia media caiu de `0.875` no piloto anterior para `0.388889`, mas ainda nao esta confortavel para promocao.

Rerodada com gate alinhado e probes saindo apenas de midias aprovadas pelo primario em `2026-04-09`:

- relatorio:
  - `apps/api/storage/app/private/face-search-aws-pilot/20260409-123336-face-search-aws-h7-pilot.json`
- estrategia:
  - a baseline local passou a ser despromovida para `searchable=false` quando o primario AWS fecha a midia com `faces_indexed=0`
  - a query valida do piloto passou a ser derivada apenas de midias que sobreviveram ao gate remoto
- resumo consolidado:
  - `events_executed=3`
  - `estimated_processing_cost_usd=0.008`
  - `estimated_storage_cost_usd_monthly_if_kept=0.00007`
  - `avg_fallback_rate=0`
  - `avg_shadow_divergence=0`
  - `total_unindexed_faces=0`
  - `total_queries_attempted=2`
  - `total_queries_blocked_by_preflight=2`
- resultado por evento:
  - `event_id=343`
    - indexacao completou com alignment local:
      - `55159264527_7f683b08f6_o.jpg`
        - AWS: `faces_indexed=0`, `dominant_rejection_reason=low_quality`
        - shadow local: `searchable_faces_before=1`, `searchable_faces_after=0`
      - `55160322273_dc03572778_o.jpg`
        - AWS: `faces_indexed=0`, `dominant_rejection_reason=low_quality`
        - shadow local: `searchable_faces_before=6`, `searchable_faces_after=0`
    - query nao executada por timeout real no detector local:
      - `Illuminate\Http\Client\ConnectionException`
      - `cURL error 28`
  - `event_id=344`
    - `valid_selfie_status=completed`
    - `response_duration_ms=666`
    - `match_count=3`
    - `shadow_divergence_ratio=0`
    - `shadow_top_match_same=true`
    - `fallback_triggered=false`
    - `group_photo=blocked_validation`
  - `event_id=345`
    - `valid_selfie_status=completed`
    - `response_duration_ms=1118`
    - `match_count=1`
    - `shadow_divergence_ratio=0`
    - `shadow_top_match_same=true`
    - `fallback_triggered=false`
    - `group_photo=blocked_validation`
- leitura:
  - a divergencia caiu de `0.388889` para `0` nas `2` queries validas desta rerodada;
  - o bloqueio de foto de grupo se manteve consistente;
  - o alinhamento do gate resolveu o caso em que a baseline local mantinha como pesquisavel uma midia que a AWS tinha descartado;
  - a rodada ainda nao basta para promover rollout porque `1/3` eventos perdeu a query por timeout do detector local, reduzindo a amostra efetiva.

Rerodada apos estabilizar o detector local/CompreFace em `2026-04-09`:

- relatorio:
  - `apps/api/storage/app/private/face-search-aws-pilot/20260409-125057-face-search-aws-h7-pilot.json`
- ajuste tecnico aplicado antes da rodada:
  - `detection_timeout=25`
  - `detection_retry_times=3`
  - `detection_retry_sleep_ms=250`
  - retry explicito para `ConnectionException` e timeouts transientes no `CompreFaceClient`
- resumo consolidado:
  - `events_executed=3`
  - `estimated_processing_cost_usd=0.012`
  - `estimated_storage_cost_usd_monthly_if_kept=0.00011`
  - `avg_fallback_rate=0`
  - `avg_shadow_divergence=0`
  - `total_unindexed_faces=0`
  - `total_queries_attempted=3`
  - `total_queries_blocked_by_preflight=3`
- resultado por evento:
  - `event_id=346`
    - `valid_selfie_status=completed`
    - `response_duration_ms=933`
    - `match_count=1`
    - `shadow_divergence_ratio=0`
    - `shadow_top_match_same=true`
    - `fallback_triggered=false`
    - `group_photo=blocked_validation`
  - `event_id=347`
    - `valid_selfie_status=completed`
    - `response_duration_ms=601`
    - `match_count=3`
    - `shadow_divergence_ratio=0`
    - `shadow_top_match_same=true`
    - `fallback_triggered=false`
    - `group_photo=blocked_validation`
  - `event_id=348`
    - `valid_selfie_status=completed`
    - `response_duration_ms=913`
    - `match_count=1`
    - `shadow_divergence_ratio=0`
    - `shadow_top_match_same=true`
    - `fallback_triggered=false`
    - `group_photo=blocked_validation`
- leitura:
  - a amostra curta do piloto voltou a completar `3/3` eventos sem quebra no detector local;
  - o `p95` observado ficou bem abaixo da meta de `4000 ms`;
  - fallback permaneceu em zero;
  - o bloqueio de foto de grupo se manteve consistente;
  - a divergencia do shadow ficou zerada nesta rodada curta, o que habilita promocao controlada dos eventos estaveis.

Promocao controlada para `aws_primary_local_fallback` em `2026-04-09`:

- comando executado:
  - `php artisan face-search:promote-aws-fallback 346 347 348 --sync-index --sync-reconcile`
- relatorio:
  - `apps/api/storage/app/private/face-search-rollout/20260409-142612-face-search-aws-fallback-rollout.json`
- rollback pronto:
  - `php artisan face-search:rollback-aws-fallback --report="C:\laragon\www\eventovivo\apps\api\storage\app/private/face-search-rollout/20260409-142612-face-search-aws-fallback-rollout.json"`
- resumo consolidado:
  - `promoted=3`
  - `skipped=0`
  - `rolled_back=0`
  - `failed=0`
- verificacoes por evento:
  - `event_id=346`
    - `routing_policy`: `aws_primary_local_shadow -> aws_primary_local_fallback`
    - `health_pre_promotion=healthy`
    - `health_post_promotion=healthy`
    - `faces_indexed=4`
    - `remote_face_count=4`
    - `queries_total=1`
    - `avg_shadow_divergence=0`
  - `event_id=347`
    - `routing_policy`: `aws_primary_local_shadow -> aws_primary_local_fallback`
    - `health_pre_promotion=healthy`
    - `health_post_promotion=healthy`
    - `faces_indexed=3`
    - `remote_face_count=3`
    - `queries_total=1`
    - `avg_shadow_divergence=0`
  - `event_id=348`
    - `routing_policy`: `aws_primary_local_shadow -> aws_primary_local_fallback`
    - `health_pre_promotion=healthy`
    - `health_post_promotion=healthy`
    - `faces_indexed=4`
    - `remote_face_count=4`
    - `queries_total=1`
    - `avg_shadow_divergence=0`
- leitura:
  - a promocao controlada reaproveitou exatamente os `3` eventos estaveis que tinham passado pela rerodada curta do piloto;
  - o rollout saiu com collection refeita, `sync-index`, `sync-reconcile`, health pre/post e snapshot completo de rollback no report;
  - nesta rodada nao houve fallback, erro de health nem rollback automatico.

Soak curto apos a promocao controlada em `2026-04-09`:

- comando executado:
  - `php artisan face-search:soak-aws-fallback 346 347 348 --queries-per-event=2`
- relatorio:
  - `apps/api/storage/app/private/face-search-soak/20260409-145200-face-search-aws-fallback-soak.json`
- resumo consolidado:
  - `completed=3`
  - `skipped=0`
  - `failed=0`
  - `avg_fallback_rate=0`
  - `avg_response_duration_ms=537`
  - `events_with_drift_after=0`
- resultado por evento:
  - `event_id=346`
    - `health_before=healthy`
    - `health_after=healthy`
    - `queries_completed=1`
    - `fallback_count=0`
    - `avg_response_duration_ms=850`
    - `drift_detected_after=false`
  - `event_id=347`
    - `health_before=healthy`
    - `health_after=healthy`
    - `queries_completed=2`
    - `fallback_count=0`
    - `avg_response_duration_ms=401`
    - `drift_detected_after=false`
  - `event_id=348`
    - `health_before=healthy`
    - `health_after=healthy`
    - `queries_completed=1`
    - `fallback_count=0`
    - `avg_response_duration_ms=360`
    - `drift_detected_after=false`
- leitura:
  - o soak curto confirmou o backend em `aws_primary_local_fallback` sem abrir fallback nem drift depois de `reconcile`;
  - os `4` requests reais concluidos ficaram com latencia confortavelmente abaixo da meta recomendada;
  - `346` e `348` produziram apenas `1` probe elegivel cada nesta rodada, enquanto `347` sustentou `2` probes completos;
  - o estado atual ja nao esta bloqueado por saude, drift ou fallback para seguir para o gate final antes de `H6`.

Validacao funcional de produto em `2026-04-09`:

- toggle base do CRUD do evento continua nascendo desligado no frontend:
  - `apps/web/src/modules/events/components/EventEditorPage.tsx`
  - defaults:
    - `enabled=false`
    - `allow_public_selfie_search=false`
    - `selfie_retention_hours=24`
- o CRUD simples do evento persiste apenas a camada basica de `FaceSearch`:
  - `enabled`
  - `allow_public_selfie_search`
  - `selfie_retention_hours`
  - `search_strategy`
- a configuracao operacional da AWS por evento continua no card dedicado de `FaceSearch`:
  - `recognition_enabled=true`
  - `search_backend_key=aws_rekognition`
  - `routing_policy`
  - `aws_search_mode`
  - thresholds e filtros AWS
- leitura funcional importante:
  - ativar apenas o toggle simples do CRUD base do evento continua mexendo so na camada basica de `FaceSearch`; ele nao ativa sozinho o lane AWS;
  - quando a ativacao AWS acontece no card dedicado de `FaceSearch`, o sistema agora:
    - garante a collection AWS;
    - enfileira automaticamente o backfill do acervo legado;
    - continua indexando novas imagens no pipeline normal com `face_index_status=queued`.
  - isso esta alinhado ao codigo atual:
    - `UpsertEventFaceSearchSettingsAction` agora despacha `EnsureAwsCollectionJob` e `BackfillEventFaceSearchGalleryJob` quando o lane AWS fica ativo pela primeira vez;
    - `QueueEventFaceSearchReindexAction` continua sendo a rotina que varre e enfileira todas as imagens ja existentes do evento;
    - novas imagens que entram depois da ativacao seguem o pipeline normal e ja nascem prontas para indexacao em background antes de qualquer busca futura.
- impacto pratico para o usuario final:
  - a consulta por selfie nao deveria esperar a indexacao da galeria no momento do upload da selfie;
  - o usuario consulta apenas contra o subconjunto que ja estiver indexado e `searchable` na collection;
  - portanto, com o acervo legado ja entrando no backfill automatico da ativacao AWS ou com o evento nascendo com `FaceSearch` ativo desde o inicio, a experiencia de busca fica praticamente imediata no submit da selfie.
- latencia real mais recente em producao controlada:
  - soak curto em `aws_primary_local_fallback`:
    - `avg_response_duration_ms=537`
    - faixa observada nas queries concluidas: `360 ms` a `850 ms`
    - `avg_fallback_rate=0`
    - `events_with_drift_after=0`
- limite desta validacao:
  - o recorte real mais recente ainda foi feito em `3` eventos piloto pequenos;
  - ele prova que o caminho de busca por `faces` esta rapido e estavel quando o acervo ja esta indexado;
  - para `users`, agora ja existe uma rodada tecnica positiva em dataset multi-identidade controlado:
    - evento `354`
    - `25` ready users
    - `top_1_match_rate=0.95`
    - `top_k_match_rate=0.95`
    - `p95_response_duration_ms=482`
  - a rodada tecnica do evento `349` continua servindo apenas como validacao operacional negativa, nao como prova de match rate;
  - ainda vale repetir a mesma rodada em um evento organico quando houver massa real suficiente, mas o gating tecnico do lane `users` ja foi fechado.
- leitura executiva atual:
  - para o MVP atual de `SearchFacesByImage` e para o primeiro corte de `SearchUsersByImage`, a AWS ja esta operacional;
  - para dizer que a integracao AWS esta `100%` no modulo inteiro ainda faltam:
    - repetir a rodada de `users` em um evento organico quando houver massa real suficiente;
    - janela curta de observacao operacional do modo `users` em trafego nao-tecnico;

Principais descobertas operacionais:

- a comparacao de shadow so faz sentido com baseline local pronta para a mesma galeria; isso agora ficou embutido no pipeline;
- o shadow local precisa continuar preso a variante `gallery` ou outra derivada normalizada; o contrato automatizado agora garante isso;
- o envelope de rede menos agressivo reduziu o risco imediato de burst e sustentou a promocao controlada em `3` eventos;
- o novo guard rail de preflight protegeu foto de grupo em `3/3` eventos na rerodada com probes elegiveis;
- a estrategia de probe derivado destravou a medicao de query-side shadow sem reabrir o caso duo/group;
- o alinhamento do gate entre primario AWS e baseline local eliminou o principal falso positivo de shadow visto no piloto anterior;
- nesta rodada curta a divergencia residual caiu para zero nas queries efetivamente executadas;
- o ajuste de timeout + retry no `CompreFaceClient` removeu a quebra observada na rodada anterior e devolveu amostra `3/3`;
- o soak curto confirmou que o risco imediato deixou de ser estabilidade tecnica do fallback e passou a ser apenas janela de observacao operacional antes do proximo bloco.
- a rodada tecnica de `users` mostrou um risco operacional especifico:
  - collection AWS reaproveitada depois de varias recalibracoes pode degradar `top_1_match_rate` por associacoes antigas;
  - o runbook correto para essa situacao agora esta claro: `delete collection -> reindex -> sync user vectors -> validate`.

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

Com `H1`, `H2`, `H3`, `H4`, `H5`, `H6`, `H7-T1`, `H7-T2` e a rodada tecnica positiva de validacao operacional de `users` concluida, o proximo bloco deve ser:

1. repetir a rodada em um evento organico quando houver massa suficiente:
   - idealmente com `target_ready_users` na faixa de `30`
   - com `aws_search_mode=users` ja ativo
   - e, se houver fallback/shadow local, com `provider_key=compreface`
2. garantir que o acervo legado terminou de convergir:
   - backfill/reindex concluido
   - `reconcile` do evento rodado
   - sync de user vectors deixado seguir pelo pipeline automatico de index/reconcile
   - se a collection tiver sofrido varias recalibracoes no meio da homologacao, fazer rebuild limpo antes da rodada final
3. rodar a linha operacional exata:
   - `php artisan face-search:validate-aws-users-high-cardinality <event_id> --sample-users=20 --min-ready-users=20 --target-ready-users=30 --max-fallback-rate=0.05 --min-users-mode-resolution-rate=0.95 --min-top1-match-rate=0.85 --min-topk-match-rate=0.95 --max-p95-latency-ms=1500`
4. se o report real passar, abrir uma janela curta de observacao do modo `users` antes do rollout amplo;
5. no fechamento do rollout, consolidar a rodada organica final e a janela curta de observacao, mantendo o card avancado para backend, thresholds e operacao.

---

## Lista Atualizada Das Faltantes Para 100% Funcional E Polido

### Revalidacao oficial AWS usada nesta rodada

Fontes oficiais relidas:

- `SearchUsersByImage`:
  - a API procura `UserIDs` a partir da maior face da imagem enviada;
  - retorna `UnsearchedFaces` para rostos detectados mas nao usados;
  - aceita `MaxUsers` ate `500`;
  - aceita `QualityFilter = NONE | AUTO | LOW | MEDIUM | HIGH`;
  - `UserMatchThreshold` default continua `80`.
- `CreateUser`:
  - segue com `ClientRequestToken` para idempotencia de criacao.

Leitura pratica para o produto:

- a experiencia de busca do convidado continua sendo selfie de uma pessoa por vez; foto de grupo nao cabe como UX principal desta fase;
- a robustez de retry e reconciliacao continua dependendo de idempotencia forte e de um runbook claro para collection drift;
- o modo `users` pode escalar no produto atual, mas ainda precisa da rodada organica final antes de ser tratado como rollout irrestrito.

### Fechadas nesta rodada

1. UX primaria do CRUD ja simplificada
   - o editor simples do evento agora usa linguagem de produto:
     - `Ativar reconhecimento facial`
     - `Liberar para convidados`
     - `Tempo para descartar a selfie enviada`
   - o fluxo basico ja nao depende do termo `FaceSearch` para o primeiro contato do usuario.

2. Estado operacional simples ja entrou no detalhe do evento
   - o detalhe agora resolve e mostra um status simples com leitura real do backend, e nao apenas da configuracao salva:
     - `Desligado`
     - `Ligado localmente`
     - `Preparando estrutura`
     - `Indexando fotos antigas`
     - `Pronto para validacao interna`
     - `Pronto para convidados`
   - a leitura agora considera:
     - filas `queued` e `processing` do acervo legado;
     - falhas pendentes de `reindex` ou `reconcile`;
     - registros AWS ja pesquisaveis;
     - `distinct_ready_users` quando o evento estiver em `aws_search_mode=users`;
   - a configuracao tecnica continua separada no card operacional.

3. Entrada publica da busca ja integrada no produto
   - o hub publico agora mostra CTA de `Encontrar minhas fotos` quando a busca publica estiver habilitada;
   - a galeria publica agora mostra o mesmo CTA no topo quando a busca publica estiver habilitada;
   - a rota dedicada `/e/:slug/find-me` continua existindo como superficie principal da busca.

4. Operacao do detalhe ficou sincronizada com as acoes AWS
   - salvar configuracao, reindexar evento e reconciliar collection agora invalidam o detalhe do evento;
   - isso evita que o operador veja status stale logo depois de mudar a fila do acervo legado.

5. Bateria desta rodada
   - backend:
     - `php artisan test tests/Feature/Hub/HubSettingsTest.php tests/Feature/Gallery/PublicGalleryAvailabilityTest.php tests/Feature/Events/CreateEventTest.php tests/Feature/FaceSearch/FaceSearchSettingsTest.php tests/Feature/FaceSearch/FaceSearchSelfieEndpointsTest.php`
     - `46 passed`
     - `409 assertions`
   - frontend:
     - `npx.cmd vitest run src/modules/events/face-search-status.test.ts src/modules/face-search/face-search-product-ux-characterization.test.ts src/modules/events/components/face-search/EventFaceSearchSettingsCard.test.tsx src/modules/events/components/face-search/EventFaceSearchSettingsForm.test.tsx src/modules/face-search/components/FaceSearchSearchPanel.test.tsx src/modules/face-search/components/EventFaceSearchSearchCard.test.tsx src/modules/face-search/PublicFaceSearchPage.test.tsx src/modules/gallery/PublicGalleryPage.test.tsx src/modules/hub/PublicHubPage.test.tsx`
     - `25 passed`
   - type-check:
     - `npm.cmd run type-check`
     - `PASS`

### P0. Faltantes obrigatorias para chamar de 100%

1. Rodada organica final do modo `users`
   - repetir a validacao em evento organico, nao apenas dataset tecnico controlado;
   - alvo atual continua `~30 users` prontos;
   - report precisa sair com:
     - `users_mode_resolution_rate >= 0.95`
     - `fallback_rate <= 0.05`
     - `top_1_match_rate >= 0.85`
     - `top_k_match_rate >= 0.95`
     - `p95_response_duration_ms <= 1500`

2. Janela curta de observacao real do modo `users`
   - mesmo com o report organico aprovado, ainda falta uma janela curta de observacao em trafego nao-tecnico;
   - esse bloco precisa observar:
     - fallback inesperado;
     - drift depois de reconcile;
     - latencia por query;
     - reclamacoes de match ruim ou zero resultado.

### P1. Polimento de produto e reducao de friccao

Status desta rodada:

- o fluxo basico ja foi simplificado no CRUD;
- o hub e a galeria publica ja ganharam CTA direto de `Encontrar minhas fotos`;
- o detalhe do evento agora explica com clareza quando o acervo antigo ainda esta convergindo e quando ja esta pronto para convidados;
- o card avancado do backoffice agora ficou secundario, recolhido em acordeao e com resumo visivel no topo;
- o formulario avancado e o card operacional passaram por limpeza final de vocabulario tecnico, mantendo termos mais precisos apenas onde a operacao realmente precisa deles.

Leitura atual:
- esse bloco de UX do backoffice pode ser tratado como fechado para o MVP;
- o caminho principal do operador comum ja esta centrado em:

- ligar o reconhecimento;
- liberar convidados;
- ler o status simples do evento;
- usar a busca interna ou abrir o link publico.

- as ferramentas tecnicas continuam disponiveis, mas agora ficam recolhidas e mais secundarias.


### P2. Robustez e observabilidade extra

1. Dashboard curto do lane `users`
   - consultas totais;
   - latencia p50/p95;
   - fallback rate;
   - distribuicao de `search_mode_resolved`;
   - erros AWS por tipo.

2. Sinalizacao mais clara de backfill/reindex
   - expor no backend e no frontend quando o evento ainda esta convergindo acervo legado;
   - isso reduz falsa expectativa de zero-latencia com zero-indexacao.

3. Runbook final de incidente
   - consolidar no plano:
     - quando basta `reconcile`;
     - quando precisa `delete collection -> reindex -> sync user vectors -> validate`;
     - quando descer temporariamente para lane local.

### Conclusao objetiva desta lista

Para dizer que a integracao AWS esta `100% funcional e polida`, ainda faltam:

- rodada organica final de `users`;
- pequena janela de observacao real depois dessa rodada.

Tecnicamente, o backend AWS ja esta pronto para o MVP e para o primeiro corte de `users`.
O que falta agora esta mais concentrado em:

- fechamento do rollout organico;
- ultima validacao organica de rollout.
