# Event media flow builder analysis - 2026-04-09

## Objetivo

Este documento consolida:

- a leitura real da stack atual do Evento Vivo para uma tela de edicao visual da jornada da midia;
- o que ja existe hoje no frontend e no backend e pode ser reaproveitado;
- o que ainda nao existe e precisa ser criado para um builder guiado;
- como desenhar uma pagina simples, visual e legivel para noiva, cerimonial e operador;
- por que o produto deve nascer como builder guiado e nao como canvas livre;
- qual e a melhor arquitetura tecnica para uma V1 em estilo fluxo;
- o que cabe na V1, o que deve ficar para fases seguintes e o que ainda nao existe no produto.

O foco aqui e o estado atual do codigo, nao uma arquitetura imaginaria.

---

## Veredito executivo

O Evento Vivo ja tem base suficiente para entregar uma pagina de "Jornada da midia" forte sem precisar criar uma engine nova de automacao.

Hoje o repositorio ja oferece:

- edicao de canais de entrada por evento;
- edicao de moderacao por evento;
- edicao de MediaIntelligence e respostas automaticas por evento;
- pipeline de midia bem definido do intake ate publish;
- padrao de painel lateral com `Sheet` e `Drawer`;
- componentes para layouts administrativos densos com `shadcn/ui`, `Tailwind` e `TanStack Query`;
- backend modular com ownership claro em `Events`, `WhatsApp`, `Telegram`, `InboundMedia`, `MediaProcessing`, `ContentModeration`, `MediaIntelligence`, `Gallery` e `Wall`.

O principal gap nao e "desenhar caixinhas".

O principal gap e:

1. nao existe hoje uma projecao unica da jornada do evento;
2. nao existe uma pagina unica que agregue essa configuracao;
3. nao existe um payload/view model pensado para leitura visual;
4. o produto ainda nao tem um DSL proprio de fluxo, nem deveria ter isso na V1.

Decisao recomendada:

- criar uma pagina nova de jornada visual do evento;
- usar um backbone vertical fixo com quatro faixas semanticas:
  - Entrada
  - Processamento
  - Decisao
  - Saida
- usar linguagem de operacao, nao linguagem de engine;
- usar `React Flow` apenas como renderer visual travado, nao como fonte da regra de negocio;
- manter a fonte de verdade nas configuracoes reais ja existentes por evento;
- introduzir um endpoint agregador/orquestrador no modulo `Events`, sem criar ainda um novo modulo backend so para isso.

Conclusao pratica:

- sim, da para fazer uma tela no estilo n8n/React Flow;
- nao, ela nao deve nascer como canvas livre;
- a melhor V1 e um builder guiado com trilho fixo, inspector lateral, simulacao e resumo humano automatico.

## Validacao automatica adicional em `2026-04-09`

Antes de sair da analise e ir para execucao, foi rodada uma bateria maior nos pontos reais que a jornada vai tocar:

### Backend

Comando executado:

```bash
cd apps/api
php artisan test tests/Feature/Events/EventJourneyArchitectureCharacterizationTest.php tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Events/EventIntakeChannelsTelegramPrivateTest.php tests/Feature/Events/EventIntakeBlacklistTest.php tests/Feature/Telegram/TelegramPrivateMediaIntakePipelineTest.php tests/Feature/Telegram/TelegramFeedbackAutomationTest.php tests/Feature/ContentModeration/ContentModerationSettingsTest.php tests/Feature/ContentModeration/ContentModerationPipelineTest.php tests/Feature/ContentModeration/ContentModerationObserveOnlyTest.php tests/Feature/MediaIntelligence/MediaIntelligenceSettingsTest.php tests/Feature/MediaIntelligence/MediaIntelligencePipelineTest.php tests/Unit/ContentModeration/UpsertEventContentModerationSettingsActionTest.php tests/Unit/MediaIntelligence/UpsertEventMediaIntelligenceSettingsActionTest.php tests/Unit/MediaIntelligence/PublishedMediaReplyTextResolverTest.php tests/Unit/MediaIntelligence/MediaReplyTextPromptResolverTest.php tests/Unit/MediaProcessing/MediaEffectiveStateResolverTest.php tests/Unit/MediaProcessing/FinalizeMediaDecisionActionTest.php
```

Resultado:

- `86` testes passaram;
- `1` teste falhou;
- `666` assertions passaram;
- intake, Telegram, blacklist, Safety, VLM, replies e decisao final continuam cobertos por testes reais.

Falha confirmada:

- `ContentModerationPipelineTest` falha quando o provider usa fallback `data_url` e o `MediaAssetUrlService` devolve asset ausente;
- a quebra acontece em `EventMediaResource`, que acessa `thumbnail_url` e `preview_url` assumindo shape completo do asset;
- isso e um bug real de robustez da pipeline atual, nao um problema da proposta do builder.

### Frontend

Comandos executados:

```bash
cd apps/web
npm run type-check
npm run test -- src/modules/events/intake.test.ts src/modules/events/components/content-moderation/EventContentModerationSettingsForm.test.tsx src/modules/events/components/media-intelligence/EventMediaIntelligenceSettingsForm.test.tsx src/modules/events/components/TelegramOperationalStatusCard.test.tsx src/modules/moderation/feed-utils.test.ts src/modules/moderation/moderation-architecture.test.ts src/modules/moderation/moderation-event-scope.contract.test.ts src/modules/wall/player/wall-theme-architecture-characterization.test.ts
```

Resultado:

- `type-check` passou;
- a maior parte da bateria passou em intake, forms de Safety, forms de MediaIntelligence, Telegram e caracterizacao do wall;
- durante a validacao, houve uma mudanca local no helper de duplicate cluster e a bateria frontend voltou a ficar verde.

Observacao importante:

- `feed-utils.test.ts` falhou no primeiro lote, mas passou depois que o arquivo `apps/web/src/modules/moderation/feed-utils.ts` apareceu alterado no worktree atual;
- no estado final validado desta rodada, a bateria frontend relevante fechou com `31` testes passando e `1` arquivo com cenarios skipped;
- `moderation-architecture.test.ts` tambem passou no rerun final.

Leitura pratica da bateria:

- a direcao do builder continua correta;
- a base de negocio que a jornada vai editar segue majoritariamente verde;
- antes de iniciar a implementacao do builder, vale fazer uma fase curta de estabilizacao da baseline:
  - blindar assets ausentes no backend;
  - rerodar a bateria frontend completa para garantir que o ajuste local de duplicate cluster permaneceu estavel.

## Revalidacao da baseline em `2026-04-10`

Depois do inicio da execucao, a baseline foi revalidada no estado atual do workspace.

### Backend

Correcao aplicada:

- `EventMediaResource` agora normaliza assets ausentes ou parciais antes de serializar `thumbnail_*`, `preview_*`, `moderation_thumbnail_*` e `moderation_preview_*`.

Comandos executados:

```bash
cd apps/api
php artisan test tests/Feature/ContentModeration/ContentModerationPipelineTest.php --filter="data url fallback"
php artisan test tests/Feature/Events/EventJourneyArchitectureCharacterizationTest.php tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Events/EventIntakeChannelsTelegramPrivateTest.php tests/Feature/Events/EventIntakeBlacklistTest.php tests/Feature/Telegram/TelegramPrivateMediaIntakePipelineTest.php tests/Feature/Telegram/TelegramFeedbackAutomationTest.php tests/Feature/ContentModeration/ContentModerationSettingsTest.php tests/Feature/ContentModeration/ContentModerationPipelineTest.php tests/Feature/ContentModeration/ContentModerationObserveOnlyTest.php tests/Feature/MediaIntelligence/MediaIntelligenceSettingsTest.php tests/Feature/MediaIntelligence/MediaIntelligencePipelineTest.php tests/Unit/ContentModeration/UpsertEventContentModerationSettingsActionTest.php tests/Unit/MediaIntelligence/UpsertEventMediaIntelligenceSettingsActionTest.php tests/Unit/MediaIntelligence/PublishedMediaReplyTextResolverTest.php tests/Unit/MediaIntelligence/MediaReplyTextPromptResolverTest.php tests/Unit/MediaProcessing/MediaEffectiveStateResolverTest.php tests/Unit/MediaProcessing/FinalizeMediaDecisionActionTest.php
```

Resultado:

- o cenario `data_url fallback` passou;
- a bateria backend relevante fechou com `87` testes passando;
- `670` assertions passaram.

### Frontend

Comandos executados:

```bash
cd apps/web
npm run test -- src/modules/moderation/feed-utils.test.ts src/modules/moderation/moderation-architecture.test.ts
npm run type-check
npm run test -- src/modules/events/intake.test.ts src/modules/events/components/content-moderation/EventContentModerationSettingsForm.test.tsx src/modules/events/components/media-intelligence/EventMediaIntelligenceSettingsForm.test.tsx src/modules/events/components/TelegramOperationalStatusCard.test.tsx src/modules/moderation/feed-utils.test.ts src/modules/moderation/moderation-architecture.test.ts src/modules/moderation/moderation-event-scope.contract.test.ts src/modules/wall/player/wall-theme-architecture-characterization.test.ts
```

Resultado:

- `feed-utils.test.ts` e `moderation-architecture.test.ts` passaram juntos;
- `type-check` passou;
- a bateria frontend relevante fechou com `31` testes passando e `1` arquivo com cenarios skipped.

Leitura pratica:

- a Fase 0B do plano ficou verde no estado atual do workspace;
- o proximo passo tecnico agora e abrir a Fase 1;
- o melhor ponto de entrada passa a ser `BuildEventJourneyProjectionAction` no backend.

## Implementacao inicial da projection em `2026-04-10`

A Fase 1 comecou pelo backend read-only, mantendo a decisao principal da analise: a jornada e uma projection operacional derivada do estado real do evento, nao uma DSL de automacao.

Arquivos criados:

- `apps/api/app/Modules/Events/Data/EventJourneyProjectionData.php`;
- `apps/api/app/Modules/Events/Data/EventJourneyStageData.php`;
- `apps/api/app/Modules/Events/Data/EventJourneyNodeData.php`;
- `apps/api/app/Modules/Events/Data/EventJourneyBranchData.php`;
- `apps/api/app/Modules/Events/Data/EventJourneyCapabilityData.php`;
- `apps/api/app/Modules/Events/Data/EventJourneyScenarioData.php`;
- `apps/api/app/Modules/Events/Actions/BuildEventJourneyProjectionAction.php`;
- `apps/api/tests/Unit/Events/EventJourneyProjectionDataTest.php`;
- `apps/api/tests/Unit/Events/BuildEventJourneyProjectionActionTest.php`.

Contrato implementado:

- `version`;
- `event`;
- `intake_defaults`;
- `intake_channels`;
- `settings`;
- `capabilities`;
- `stages`;
- `warnings`;
- `simulation_presets`;
- `summary`.

Nos V1 implementados na projection:

- Entrada: `entry_whatsapp_direct`, `entry_whatsapp_groups`, `entry_telegram`, `entry_public_upload`, `entry_sender_blacklist`;
- Processamento: `processing_receive_feedback`, `processing_download_media`, `processing_prepare_variants`, `processing_safety_ai`, `processing_media_intelligence`;
- Decisao: `decision_event_moderation_mode`, `decision_safety_result`, `decision_context_gate`, `decision_media_type`, `decision_caption_presence`;
- Saida: `output_reaction_final`, `output_reply_text`, `output_gallery`, `output_wall`, `output_print`, `output_silence`.

Regras confirmadas:

- os quatro blocos continuam fixos: `Entrada`, `Processamento`, `Decisao`, `Saida`;
- branches usam IDs estaveis, incluindo `default`;
- canais ativos sem entitlement aparecem como `locked` e geram `warnings`;
- `Safety` reflete `enabled`, `mode`, `fallback_mode` e heranca global;
- `MediaIntelligence` reflete `enabled`, `mode`, `fallback_mode`, `reply_text_mode` e heranca global;
- `print` fica `unavailable` na V1;
- `gallery` fica como destino obrigatorio;
- `wall` depende de modulo, setting do wall e entitlement;
- o teste de budget da action protege o cenario completo com `expectsDatabaseQueryCount(8)`.

Bateria validada:

```bash
cd apps/api
php artisan test tests/Unit/Events/EventJourneyProjectionDataTest.php tests/Unit/Events/BuildEventJourneyProjectionActionTest.php
php artisan test tests/Unit/Events/EventJourneyProjectionDataTest.php tests/Unit/Events/BuildEventJourneyProjectionActionTest.php tests/Feature/Events/EventJourneyArchitectureCharacterizationTest.php tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Events/EventIntakeChannelsTelegramPrivateTest.php tests/Feature/ContentModeration/ContentModerationSettingsTest.php tests/Feature/MediaIntelligence/MediaIntelligenceSettingsTest.php
```

Resultado:

- `14` testes novos passaram;
- `83` assertions novas passaram;
- regressao direcionada passou com `36` testes;
- regressao direcionada passou com `297` assertions.

Leitura pratica:

- a `Tarefa 1.1` esta concluida;
- a `Tarefa 1.2` esta concluida no nivel de action read-only;
- a protecao de budget deve ser repetida na `Tarefa 1.4`, quando existir endpoint `GET /events/{event}/journey-builder`;
- a proxima entrega natural era a `Tarefa 1.3`, para separar o resumo humano em action dedicada antes do controller.

## Extracao do resumo humano em `2026-04-10`

A `Tarefa 1.3` foi concluida sem mudar a direcao da arquitetura:

- o resumo agora mora em `BuildEventJourneySummaryAction`;
- `BuildEventJourneyProjectionAction` so orquestra leitura e delega o texto humano;
- o contrato externo continua simples em `summary.human_text`.

Leitura pratica da extracao:

- a projection nao mistura mais regra de montagem do grafo com copy de produto;
- o resumo ficou testavel isoladamente;
- a proxima fase pode abrir controller/rota sem carregar logica de copy dentro do endpoint.

Regras validadas pela action dedicada:

- manual: usa linguagem de operacao como `envia para revisao manual`;
- AI com Safety `enforced` + VLM `gate`: resume como analise de `risco e contexto com IA`;
- AI com Safety `observe_only` + `enrich_only`: resume como suporte de IA para entender melhor a midia e sinalizar revisao;
- sem canais ativos: entra em fallback claro, sem prometer processamento inexistente;
- `print` nunca aparece como promessa textual na V1.

Bateria validada:

```bash
cd apps/api
php artisan test tests/Unit/Events/BuildEventJourneySummaryActionTest.php tests/Unit/Events/BuildEventJourneyProjectionActionTest.php
php artisan test tests/Unit/Events/EventJourneyProjectionDataTest.php tests/Unit/Events/BuildEventJourneySummaryActionTest.php tests/Unit/Events/BuildEventJourneyProjectionActionTest.php tests/Feature/Events/EventJourneyArchitectureCharacterizationTest.php tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Events/EventIntakeChannelsTelegramPrivateTest.php tests/Feature/ContentModeration/ContentModerationSettingsTest.php tests/Feature/MediaIntelligence/MediaIntelligenceSettingsTest.php
```

Resultado:

- `18` testes passaram na bateria unitária da extração;
- `76` assertions passaram na bateria unitária da extração;
- `41` testes passaram na regressão direcionada;
- `304` assertions passaram na regressão direcionada.

Leitura pratica:

- a `Tarefa 1.3` esta concluida;
- a proxima entrega natural agora passa a ser a `Tarefa 1.4`, abrindo `EventJourneyController` e a rota read-only `GET /events/{event}/journey-builder`.

## Endpoint read-only da jornada em `2026-04-10`

A parte read-only da `Tarefa 1.4` foi concluida.

Entrega realizada:

- `EventJourneyController` criado no modulo `Events`;
- `EventJourneyResource` criado para serializar a projection;
- rota `GET /api/v1/events/{event}/journey-builder` registrada em `routes/api.php`;
- policy aplicada com `authorize('view', $event)`;
- resposta seguindo o envelope padrao com `success`, `data` e `meta.request_id`.

Bateria validada:

```bash
cd apps/api
php artisan test tests/Feature/Events/EventJourneyControllerTest.php tests/Unit/Events/EventJourneyProjectionDataTest.php tests/Unit/Events/BuildEventJourneySummaryActionTest.php tests/Unit/Events/BuildEventJourneyProjectionActionTest.php
```

Resultado:

- `22` testes passaram;
- `135` assertions passaram.

Revalidacao ampliada:

- `44` testes passaram;
- `349` assertions passaram;
- intake, projection, summary, Safety e VLM continuaram verdes juntos depois da abertura do endpoint read-only.

Regras confirmadas:

- o `GET` retorna a projection read-only completa;
- acesso cross-organization retorna `403`;
- o budget do endpoint ficou protegido com `14` queries no cenario completo;
- o `PATCH` ainda nao foi aberto e continua dependente da validacao agregadora e da action transacional das `Tarefas 1.5` e `1.6`.

## Validacao agregadora do PATCH em `2026-04-10`

A `Tarefa 1.5` foi concluida com `UpdateEventJourneyRequest`.

O que entrou no request:

- campos base de `moderation_mode` e `modules`;
- intake agregado com `intake_defaults` e `intake_channels`;
- bloco `content_moderation`;
- bloco `media_intelligence`;
- validacoes cruzadas via `after()` para devolver `422` util ao inspector.

Regras cruzadas validadas:

- `gate` exige `fallback_mode=review`;
- Telegram ativo exige `bot_username` e `media_inbox_code`;
- WhatsApp direto ativo exige `media_inbox_code`;
- TTL continua limitado entre `1` e `4320`;
- `fixed_random` exige templates;
- `modules.wall=true` falha sem entitlement do evento;
- `whatsapp_instance_id` falha fora da organizacao do evento;
- `provider_key=openrouter` reaproveita a validacao oficial/local de modelo homologado.

Leitura pratica:

- o contrato do `PATCH` ja esta fechado antes da action transacional;
- o frontend pode trabalhar com paths de erro estaveis como `media_intelligence.fallback_mode` e `intake_channels.telegram.bot_username`;
- a `Tarefa 1.6` agora pode focar em transacao, orchestration e revalidacao da projection apos save.

Bateria validada:

```bash
cd apps/api
php artisan test tests/Unit/Events/UpdateEventJourneyRequestTest.php
php artisan test tests/Unit/Events/UpdateEventJourneyRequestTest.php tests/Feature/Events/EventJourneyControllerTest.php tests/Unit/Events/EventJourneyProjectionDataTest.php tests/Unit/Events/BuildEventJourneySummaryActionTest.php tests/Unit/Events/BuildEventJourneyProjectionActionTest.php tests/Feature/Events/EventJourneyArchitectureCharacterizationTest.php tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Events/EventIntakeChannelsTelegramPrivateTest.php tests/Feature/ContentModeration/ContentModerationSettingsTest.php tests/Feature/MediaIntelligence/MediaIntelligenceSettingsTest.php
```

Resultado:

- `10` testes passaram na bateria unitária do request;
- `13` assertions passaram na bateria unitária do request;
- `54` testes passaram na regressão ampliada;
- `362` assertions passaram na regressão ampliada.

---

## Escrita transacional da jornada em `2026-04-10`

A `Tarefa 1.6` foi concluida com `UpdateEventJourneyAction` e com a abertura final do `PATCH /api/v1/events/{event}/journey-builder`.

O que entrou:

- `UpdateEventJourneyAction` no modulo `Events`;
- transacao externa para orquestrar `Event`, intake, Safety e VLM;
- reuso de `UpdateEventAction` para estado base do evento;
- reuso de `UpsertEventContentModerationSettingsAction` e `UpsertEventMediaIntelligenceSettingsAction`;
- `EventJourneyController@update` usando `UpdateEventJourneyRequest`;
- rota `PATCH /api/v1/events/{event}/journey-builder` no mesmo ownership do `GET`.

Guardas criticos adicionados no orquestrador:

- `modules.wall=true` continua protegido por entitlement;
- `media_intelligence.mode=gate` rejeita `fallback_mode=skip` mesmo dentro da action;
- `reply_text_mode=fixed_random` exige templates tambem no save agregado;
- `provider_key=openrouter` continua validado contra o catalogo homologado local.

Leitura pratica:

- a jornada agora tem leitura e escrita coesas no modulo `Events`;
- o frontend ja pode trabalhar com rascunho local e um unico CTA de salvar;
- o retorno do `PATCH` ja volta em formato de projection revalidada, sem precisar compor varias respostas;
- o rollback ficou real para os casos em que parte do estado ja teria sido escrita antes da falha.

Bateria validada:

```bash
cd apps/api
php artisan test tests/Unit/Events/UpdateEventJourneyActionTest.php tests/Feature/Events/EventJourneyControllerTest.php
php artisan test tests/Unit/Events/UpdateEventJourneyActionTest.php tests/Unit/Events/UpdateEventJourneyRequestTest.php tests/Feature/Events/EventJourneyControllerTest.php tests/Unit/Events/EventJourneyProjectionDataTest.php tests/Unit/Events/BuildEventJourneySummaryActionTest.php tests/Unit/Events/BuildEventJourneyProjectionActionTest.php tests/Feature/Events/EventJourneyArchitectureCharacterizationTest.php tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Events/EventIntakeChannelsTelegramPrivateTest.php tests/Feature/ContentModeration/ContentModerationSettingsTest.php tests/Feature/MediaIntelligence/MediaIntelligenceSettingsTest.php
```

Resultado:

- `11` testes passaram na bateria focada de action + controller;
- `125` assertions passaram na bateria focada;
- `62` testes passaram na regressao ampliada;
- `442` assertions passaram na regressao ampliada.

## Types de dominio do frontend em `2026-04-10`

A `Tarefa 2.1` tambem foi concluida para preparar o adapter do canvas sem acoplar a regra de negocio ao renderer visual.

O que entrou:

- `apps/web/src/modules/events/journey/types.ts`;
- tipos para projection, stages, nodes, branches, capabilities, scenarios e summary;
- tipo dedicado para `EventJourneyUpdatePayload`;
- imports restritos aos tipos reais de `events`, `intake` e `ApiEnvelope`.

Leitura pratica:

- o dominio da jornada ja esta tipado antes da entrada do `@xyflow/react`;
- o futuro graph adapter tera um contrato claro para transformar projection em `nodes/edges`;
- o payload de update continua espelhando o backend real, sem introduzir DSL nova.

Teste de protecao adicionado:

- a caracterizacao `event-media-flow-builder-architecture-characterization.test.ts` agora garante que `journey/types.ts` nao importa `@xyflow/react` nem tipos `Node`/`Edge`.

Bateria validada:

```bash
cd apps/web
npm run test -- src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
npm run type-check
```

Resultado:

- `4` testes passaram na caracterizacao do modulo;
- `type-check` passou sem erros.

## API client e query keys da jornada em `2026-04-10`

A `Tarefa 2.2` foi concluida para ligar o frontend ao endpoint agregador sem espalhar fetch/mutation pela pagina.

O que entrou:

- `apps/web/src/modules/events/journey/api.ts`;
- `getEventJourneyBuilder(eventId)`;
- `updateEventJourneyBuilder(eventId, payload)`;
- `eventJourneyBuilderQueryOptions(eventId)`;
- `invalidateEventJourneyBuilderQueries(queryClient, eventId)`;
- `eventJourneyBuilderMutationOptions(queryClient, eventId)`;
- `queryKeys.events.journeyBuilder(id)` em `apps/web/src/lib/query-client.ts`.

Decisoes de implementacao importantes:

- a projection da jornada usa query key dedicada sob o namespace `events`;
- o save continua agregado em um unico `PATCH`, sem chamadas paralelas para Safety, VLM e intake;
- a mutation helper nao faz optimistic patch global da projection;
- o `onSuccess` aguarda o fim das invalidacoes relevantes antes de encerrar, alinhado com a documentacao oficial do `TanStack Query`;
- a invalidacao cobre tanto as novas keys da jornada quanto as superfices legadas que ainda usam chaves literais, como `event-detail`, `event-content-moderation-settings` e `event-media-intelligence-settings`.

Leitura pratica:

- o builder ja pode nascer com `useQuery` e `useMutation` sem inventar outra camada de cache;
- o save do inspector pode depender de uma unica mutation;
- depois do `PATCH`, a UI reidrata a partir da projection revalidada e nao de um remendo manual no client;
- a convivência com telas antigas do modulo `events` continua segura durante a migracao.

Referencias oficiais consideradas:

- `TanStack Query - Query Keys`: https://tanstack.com/query/latest/docs/framework/react/guides/query-keys
- `TanStack Query - Invalidations from Mutations`: https://tanstack.com/query/latest/docs/framework/react/guides/invalidations-from-mutations
- `TanStack Query - useMutation`: https://tanstack.com/query/latest/docs/framework/react/reference/useMutation

Bateria validada:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/api.test.ts src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
npm run type-check
```

Resultado:

- `5` testes passaram na bateria de API do builder;
- `4` testes passaram na caracterizacao do modulo;
- `type-check` passou sem erros.

## Graph adapter puro em `2026-04-10`

A `Tarefa 2.3` tambem foi concluida para transformar a projection tipada em uma estrutura de grafo estavel antes da entrada do renderer visual.

O que entrou:

- `apps/web/src/modules/events/journey/buildJourneyGraph.ts`;
- tipos locais de `JourneyGraphStageBand`, `JourneyGraphNode` e `JourneyGraphEdge`;
- mapa de posicoes deterministico para os nodes reais da V1;
- fallback previsivel por stage para nodes novos ou ainda nao mapeados;
- class names semanticas por `kind`, `stage`, `status`, `active` e `editable`.

Decisoes de implementacao importantes:

- a camada continua desacoplada de `@xyflow/react`;
- cada branch vira um source handle estavel no formato `branch:<branchId>`;
- cada edge vira um ID estavel no formato `source:branch->target`;
- branches inativas continuam representadas quando ajudam o usuario a entender por que um caminho nao esta operando;
- nodes de output ou capability desligada continuam visiveis com `status-inactive` ou `status-locked`, em vez de sumirem.

Leitura pratica:

- o canvas futuro podera ser um renderer relativamente fino sobre esse adapter;
- a simulacao de cenarios podera destacar nodes e edges por ID sem depender do DOM;
- mudar o renderer visual no futuro nao exigira reescrever a logica de branching nem as regras de posicionamento.

Bateria validada:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/buildJourneyGraph.test.ts src/modules/events/journey/__tests__/api.test.ts src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
npm run test -- src/modules/events
npm run type-check
```

Resultado:

- `6` testes passaram na bateria do graph adapter;
- `15` testes passaram na bateria combinada do builder inicial;
- `47` testes passaram na regressao do modulo `events`;
- `type-check` passou sem erros.

## Resumo e simulador local em `2026-04-10`

A `Tarefa 2.4` foi concluida para fechar a camada de explicacao viva da jornada sem depender do canvas real nem de IA.

O que entrou:

- `apps/web/src/modules/events/journey/buildJourneySummary.ts`;
- `apps/web/src/modules/events/journey/buildJourneyScenarios.ts`;
- tipo local `EventJourneyBuiltScenario` em `journey/types.ts`;
- `buildJourneyScenarios(projection, graph)`;
- `simulateJourneyScenario(projection, graph, scenarioId)`;
- `buildJourneySummary(projection, simulation?)`.

Decisoes de implementacao importantes:

- o resumo base continua vindo da projection revalidada do backend;
- quando um cenario e simulado, o resumo local troca para a explicacao especifica daquele caminho;
- a simulacao usa apenas `node IDs` e `edge IDs` estaveis do graph adapter;
- a V1 fecha com nove cenarios curados:
  - foto com legenda por WhatsApp privado
  - foto sem legenda por grupo
  - video por Telegram
  - remetente bloqueado
  - Safety bloqueou
  - Safety pediu revisao
  - VLM gate pediu revisao
  - aprovado e publicado
  - rejeitado com resposta
- quando um cenario nao faz sentido na configuracao atual, ele continua visivel com `available=false` e `unavailableReason`, em vez de sumir.

Leitura pratica:

- o simulador da pagina ja pode destacar caminho sem tocar em DOM, timers do canvas ou renderer visual;
- a explicacao humana muda por cenario sem precisar chamar IA;
- a UX pode mostrar ao usuario tanto o comportamento geral do evento quanto o comportamento pontual de um caso de teste;
- a tela continua sendo projection + simulacao local, nao um motor novo de regras.

Bateria validada:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/buildJourneyScenarios.test.ts src/modules/events/journey/__tests__/buildJourneyGraph.test.ts src/modules/events/journey/__tests__/api.test.ts src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
npm run test -- src/modules/events
npm run type-check
```

Resultado:

- `6` testes passaram na bateria do simulador e resumo local;
- `21` testes passaram na bateria combinada do builder inicial;
- `53` testes passaram na regressao do modulo `events`;
- `type-check` passou sem erros.

## Mapper de update do inspector em `2026-04-10`

A `Tarefa 2.5` foi concluida para fechar a ida do draft do inspector para o payload agregado do backend sem apagar configuracao nao tocada.

O que entrou:

- `apps/web/src/modules/events/journey/toJourneyUpdatePayload.ts`;
- tipo `EventJourneyInspectorDraft`;
- tipo recursivo `EventJourneyDirtyFields<T>`;
- normalizacao local para strings, inteiros, thresholds, datas, templates e arrays do draft;
- tratamento coerente de `reply_text_mode`, `reply_prompt_override`, `reply_prompt_preset_id` e `reply_fixed_templates`.

Decisao tecnica importante:

- a implementacao ficou em mapper proprio e recursivo, em vez de depender de `getValues(undefined, { dirtyFields: true })`.

Motivo:

- a documentacao oficial do `react-hook-form` registra essa extracao por estado do form na release `v7.63.0`;
- o nosso repo segue em `react-hook-form 7.61.1`;
- portanto, usar essa API agora criaria acoplamento a uma capacidade que a nossa stack atual ainda nao garante.

Fonte oficial considerada:

- `react-hook-form v7.63.0`: https://github.com/react-hook-form/react-hook-form/releases/tag/v7.63.0

Leitura pratica:

- o inspector futuro pode usar `react-hook-form` normal, `dirtyFields` padrao e esse mapper puro para gerar patch minimo;
- o payload agora preserva campos nao tocados em vez de remandar blocos inteiros;
- TTL, thresholds, `whatsapp_instance_id`, prompt preset e templates seguem a mesma normalizacao ja validada nos forms atuais de intake, Safety e VLM;
- quando o modo de resposta muda, o payload limpa os campos que deixam de fazer sentido, evitando lixo residual no backend.

Bateria validada:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/toJourneyUpdatePayload.test.ts src/modules/events/journey/__tests__/buildJourneyScenarios.test.ts src/modules/events/journey/__tests__/buildJourneyGraph.test.ts src/modules/events/journey/__tests__/api.test.ts src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
npm run type-check
```

Resultado:

- `7` testes passaram na bateria do mapper de update;
- `28` testes passaram na bateria combinada do builder inicial;
- `type-check` passou sem erros.

Observacao desta rodada:

- a regressao ampliada `npm run test -- src/modules/events` encontrou `1` falha fora da trilha da jornada em `EventDetailPage.test.tsx`, ligada ao card de branding aplicado; como a falha nao toca `journey/*`, ela ficou registrada como regressao residual do worktree atual e nao como defeito do mapper novo.

## Leitura real da stack atual

## Frontend auditado hoje

O frontend em `apps/web` esta hoje em:

- React `18.3.1`
- TypeScript `5.8.3`
- Vite `5.4.19`
- TailwindCSS `3.4.17`
- Framer Motion `12.38.0`
- TanStack Query `5.83.0`
- React Router DOM `6.30.1`
- shadcn/ui sobre Radix
- `react-resizable-panels`
- `vaul` para drawer
- Vitest e Playwright

Leitura pratica:

- o stack atual e muito bom para tela administrativa rica;
- ja existe padrao de `Sheet`, `Drawer`, `Tabs`, `Card`, `Badge`, `Switch`, `Select`, `Form`;
- ja existe `react-resizable-panels` para layout principal + inspector;
- nao existe hoje dependencia de `React Flow`;
- nao existe hoje `Zustand`;
- nao existe hoje `Dagre` ou `ELK`.

Implicacao:

- da para construir a V1 sem mudar o modelo da aplicacao inteira;
- se o time quiser estilo n8n, faz sentido adicionar `@xyflow/react`;
- para a V1 nao ha necessidade tecnica de adicionar `Zustand` nem engine de auto-layout.
- se o time quiser seguir a documentacao oficial mais recente de `React Flow`, a melhor entrada para a nossa stack atual e o core `@xyflow/react`, nao o kit `React Flow UI`, porque a trilha de UI deles hoje ja esta orientada ao stack mais novo de `React 19 + Tailwind 4`, enquanto o nosso repo segue em `React 18 + Tailwind 3`.

## Backend auditado hoje

O backend em `apps/api` esta hoje em:

- PHP `>=8.3 <8.4`
- Laravel Framework `13.x`
- Laravel Reverb `1.9`
- Laravel Horizon `5.45`
- Laravel Sanctum `4.3`
- Spatie Data `4.20`
- Spatie Permission `7.2`

Observacao importante:

- o `AGENTS.md` fala em Laravel `12`, mas o `composer.json` atual do repositorio ja esta em Laravel `13.x`.

Leitura pratica:

- o backend ja esta modularizado e suficientemente maduro para expor um payload agregador da jornada;
- a execucao real da pipeline ja esta espalhada em modulos corretos;
- o ponto novo que falta e uma camada de orquestracao para leitura e escrita centralizada da experiencia visual.

---

## O que ja existe hoje e pode ser reaproveitado

## 1. O evento ja e o aggregate root certo para a experiencia

Arquivos centrais:

- `apps/api/app/Modules/Events/Http/Controllers/EventController.php`
- `apps/api/app/Modules/Events/Support/EventIntakeChannelsStateBuilder.php`
- `apps/api/app/Modules/Events/Actions/SyncEventIntakeChannelsAction.php`
- `apps/web/src/modules/events/components/EventEditorPage.tsx`
- `apps/web/src/modules/events/intake.ts`

Hoje o modulo `Events` ja agrega:

- `channels`
- `defaultWhatsAppInstance`
- `whatsappGroupBindings`
- `contentModerationSettings`
- `mediaIntelligenceSettings`
- `wallSettings`
- `playSettings`
- `hubSettings`

Leitura pratica:

- a nova tela deve ficar ancorada no dominio `Events`;
- nao faz sentido espalhar a pagina nova em `wall`, `moderation` ou `whatsapp`;
- o evento e o objeto que o usuario entende e o lugar onde a configuracao ja converge.

## 2. Os canais de entrada ja existem como configuracao real

Estado atual por evento:

- `whatsapp_groups`
- `whatsapp_direct`
- `public_upload`
- `telegram`

Isso ja esta consolidado em:

- `SyncEventIntakeChannelsAction`
- `EventIntakeChannelsStateBuilder`
- `apps/web/src/modules/events/intake.ts`

O que ja e editavel hoje:

- habilitar/desabilitar canal;
- escolher instancia WhatsApp;
- definir `media_inbox_code`;
- definir `session_ttl_minutes`;
- configurar grupos WhatsApp;
- ligar Telegram privado;
- ativar link de upload.

Leitura pratica:

- o bloco `Entrada` do builder ja pode nascer em cima do estado real do backend;
- nao precisa existir um formato novo de configuracao so para canais.

## 3. A pipeline da midia ja esta bem definida

Documentos e codigo centrais:

- `docs/flows/media-ingestion.md`
- `apps/api/app/Modules/MediaProcessing/README.md`
- `apps/api/app/Modules/ContentModeration/README.md`
- `apps/api/app/Modules/MediaIntelligence/README.md`
- `apps/api/app/Modules/MediaProcessing/Services/MediaEffectiveStateResolver.php`

Pipeline atual:

1. intake/webhook
2. normalizacao
3. download
4. variantes
5. safety
6. VLM
7. decisao final
8. publicacao
9. wall/gallery

Statuses ja existentes:

- `processing_status`
- `moderation_status`
- `publication_status`
- `safety_status`
- `vlm_status`
- `face_index_status`

Leitura pratica:

- o builder pode mostrar um fluxo fiel ao que o sistema realmente executa;
- o passo de "decisao" nao precisa ser inventado, ele ja existe como matriz efetiva;
- a simulacao de caminho pode usar exatamente esses estados e regras.

## 4. Safety e VLM ja sao configuraveis por evento

Arquivos centrais:

- `apps/web/src/modules/events/components/content-moderation/EventContentModerationSettingsForm.tsx`
- `apps/web/src/modules/events/components/media-intelligence/EventMediaIntelligenceSettingsForm.tsx`
- `apps/api/app/Modules/ContentModeration/Http/Resources/EventContentModerationSettingResource.php`
- `apps/api/app/Modules/MediaIntelligence/Http/Resources/EventMediaIntelligenceSettingResource.php`

Hoje ja existem configuracoes reais para:

### Safety

- `enabled`
- `provider_key`
- `mode = enforced | observe_only`
- `threshold_version`
- `fallback_mode = review | block`
- thresholds por categoria
- `analysis_scope`

### MediaIntelligence

- `enabled`
- `provider_key`
- `model_key`
- `mode = enrich_only | gate`
- `prompt_version`
- `approval_prompt`
- `caption_style_prompt`
- `fallback_mode = review | skip`
- `reply_text_mode = disabled | ai | fixed_random`
- `reply_prompt_override`
- `reply_fixed_templates`
- `reply_prompt_preset_id`

Leitura pratica:

- o bloco `Processamento` e parte do bloco `Saida` ja podem ser editados por um inspector guiado;
- o builder nao precisa inventar "resposta com IA" do zero;
- isso ja existe no backend, inclusive com dispatcher real apos publicacao.

## 5. Ja existe feedback automatico por canal

Arquivos centrais:

- `apps/api/app/Modules/WhatsApp/Services/WhatsAppFeedbackAutomationService.php`
- `apps/api/app/Modules/Telegram/Services/TelegramFeedbackAutomationService.php`
- `apps/api/app/Modules/MediaIntelligence/Services/PublishedMediaAiReplyDispatcher.php`

Hoje ja existem respostas/feedbacks reais como:

### WhatsApp

- reacao de `detected`
- reacao de `published`
- reacao de `rejected`
- reply textual em `published` quando `reply_text_mode` gerar texto
- reply textual em `rejected` via policy/entitlement

### Telegram

- `session_activated`
- `session_closed`
- `detected` com `sendChatAction` + reacao
- `published` com reacao + reply opcional
- `rejected`
- `blocked`

Leitura pratica:

- o builder consegue representar saidas humanas reais;
- mas precisa deixar claro que algumas mensagens ainda sao globais/padronizadas, nao overrides livres por evento.

## 6. Ja existe linguagem de inspector lateral no frontend

Padroes prontos:

- `Sheet` e `Drawer` em `apps/web/src/components/ui/sheet.tsx` e `drawer.tsx`
- `react-resizable-panels` em `apps/web/src/components/ui/resizable.tsx`
- inspector do wall em `apps/web/src/modules/wall/pages/EventWallManagerPage.tsx`
- details sheets do wall
- painel lateral e drawer na moderacao

Leitura pratica:

- a V1 nao precisa inventar um layout administrativo novo;
- o padrao certo e canvas central + inspector lateral no desktop + drawer no mobile.

---

## O que ainda nao existe

## 1. Nao existe uma pagina unica de jornada da midia

Hoje o usuario precisa navegar entre:

- editor do evento;
- configuracao de canais;
- card de Safety;
- card de MediaIntelligence;
- moderacao;
- wall;

Problema:

- a plataforma ja sabe executar a pipeline;
- mas o usuario final nao consegue "ler" o processo como uma jornada unica.

## 2. Nao existe uma projecao visual consolidada

Hoje o frontend recebe varias fatias:

- `intake_channels`
- `moderation_mode`
- settings de Safety
- settings de MediaIntelligence
- modulos ativos

Mas nao existe um payload tipo:

- "qual e a jornada visivel do evento?"
- "quais nos aparecem?"
- "quais condicoes estao ligadas?"
- "quais nos sao editaveis?"
- "quais nos sao so leitura?"

Esse e o principal gap estrutural.

## 3. Nao existe dependencia de canvas/flow

Hoje nao ha no `package.json`:

- `@xyflow/react`
- `zustand`
- `dagre`
- `elkjs`

Isso nao impede a feature.

Apenas significa que:

- o canvas precisara entrar conscientemente;
- nao vale adicionar metade do ecossistema logo na primeira entrega.

## 4. Nao existe um DSL de fluxo, e isso e bom para a V1

O sistema ja possui a fonte de verdade em campos reais como:

- `intake_channels.*`
- `moderation_mode`
- `content_moderation.*`
- `media_intelligence.*`
- `modules.wall`
- `modules.live`

Leitura pratica:

- criar um DSL novo de automacao agora duplicaria regra;
- a V1 deve projetar e editar o estado real existente;
- um DSL proprio so deve nascer quando o produto passar a suportar excecoes que os campos atuais nao cobrem.

## 5. Algumas saidas desejadas ainda nao existem como capability real

Exemplos que hoje nao sao first-class no produto:

- impressao/rolo;
- randomizacao de publish por fluxo;
- loop condicional;
- branch livre por caption ausente;
- branch livre por foto vs video com comportamento configuravel por evento;
- publicar no telao mas nao na galeria como politica independente por caminho;
- resposta inicial personalizada por evento com total liberdade.

Leitura pratica:

- esses itens podem aparecer como roadmap;
- nao devem contaminar a V1 com placeholders que parecem implementados.

---

## Benchmark externo e leitura critica

Foi analisado um benchmark local de mercado baseado em `React Flow` para chatbot/automacao, com canvas livre, conexoes manuais e condicoes por handles.

Leitura tecnica consolidada:

- o frontend usa `React Flow` como canvas de edicao com `handles`, `controls`, `minimap` e custom nodes;
- o backend persiste o grafo cru como `nodes + edges`;
- o runtime executa esse grafo como engine real;
- o branch de condicao usa handles dedicados por saida, mais um branch `default`;
- como o grafo e livre, o sistema precisou adicionar protecao contra loops, sessao de execucao e regras extras de validacao.

Esse benchmark confirma duas coisas importantes:

### O que vale aproveitar

- familiaridade visual de um canvas estilo flow builder;
- branch explicito por handle estavel;
- branch `default` para caminhos nao satisfeitos;
- templates prontos;
- simulacao ou sessao de execucao como ferramenta de suporte;
- validacao de caminhos invalidos antes de salvar.

### O que nao vale copiar

- usar `nodes` e `edges` como fonte canonica da regra de negocio;
- permitir canvas livre desde o primeiro dia;
- acoplar o produto a uma engine generica de automacao;
- deixar o usuario criar loops, excecoes arbitrarias e expressoes livres;
- transformar a jornada do evento em uma IDE de chatbot.

Conclusao pratica do benchmark:

- a referencia externa e util como linguagem visual e como inspiracao de branching;
- ela nao deve ditar a arquitetura do Evento Vivo;
- para o nosso caso, o canvas deve continuar sendo projecao guiada de configuracao real.

---

## Mapa real de capacidades atuais para o builder

| Bloco | Capacidade | Estado atual | Builder V1 |
| --- | --- | --- | --- |
| Entrada | WhatsApp privado por codigo | Implementado | Editavel |
| Entrada | WhatsApp grupos | Implementado | Editavel |
| Entrada | Telegram privado | Implementado parcial, mas operacional | Editavel |
| Entrada | Link/upload publico | Implementado | Editavel |
| Entrada | Blacklist por remetente | Implementado | Editavel fora do fluxo principal, em policy drawer |
| Processamento | Reacao inicial de processamento | Existe por canal | Visivel; parcialmente editavel |
| Processamento | Download da midia | Implementado | No de leitura |
| Processamento | Geracao de variantes | Implementado | No de leitura |
| Processamento | Safety por IA | Implementado | Editavel |
| Processamento | VLM / contexto / caption / tags | Implementado | Editavel |
| Processamento | Resposta automatica por IA ou templates | Implementado apos publish | Editavel |
| Decisao | Modo do evento `none/manual/ai` | Implementado | Editavel |
| Decisao | Safety `enforced/observe_only` | Implementado | Editavel |
| Decisao | VLM `enrich_only/gate` | Implementado | Editavel |
| Decisao | Revisao manual | Implementado via moderacao e fallback | Editavel |
| Decisao | Foto vs video | Detectado pelo pipeline | Visivel em simulacao; politica granular ainda nao first-class |
| Decisao | Caption existe / nao existe | Dado existe | Visivel em simulacao; branch configuravel ainda nao first-class |
| Saida | Reacao de aprovado/publicado | Implementado | Visivel; parcialmente editavel |
| Saida | Reply textual de rejeicao | Parcial, mais global/entitlement | Visivel; editavel so se policy evoluir |
| Saida | Reply por IA/fixo-random | Implementado apos publish | Editavel |
| Saida | Publicar na galeria | Implementado | No de leitura / ligado ao pipeline |
| Saida | Disponibilizar no telao | Implementado via `Wall` consumindo `published` | Visivel; dependente de modulo wall, nao branch livre |
| Saida | Impressao/rolo | Nao implementado | Fora da V1 |
| Saida | Silencio total | Parcial, via desabilitar reply | Parcial |

---

## Recomendacao de produto

## 1. O nome certo nao e "automacao"

Para o usuario final, o modelo mental deve ser:

- "Jornada da midia"
- "Como o evento trata cada foto ou video recebido"

Nao:

- "workflow engine"
- "automacao"
- "regras"

Razao:

- noiva e cerimonial querem configurar uma operacao;
- elas nao querem sentir que estao programando um sistema.

## 2. O produto deve nascer como builder guiado

O n8n funciona com liberdade porque o usuario ja espera:

- pensar em IF/SWITCH;
- lidar com branching tecnico;
- assumir o custo cognitivo de automacao.

No Evento Vivo, esse custo e um bug de produto.

Portanto a V1 deve seguir estes principios:

- backbone fixo;
- poucos pontos de decisao;
- labels em linguagem humana;
- no maximo 8 a 12 nos principais;
- branches laterais so quando a funcionalidade estiver ativa;
- nada de criar/remover no arbitrariamente;
- nada de desenhar arestas manualmente;
- nada de coordenadas salvas por usuario.

## 3. A pagina deve ter quatro faixas fixas

A melhor leitura para o caso atual e:

1. Entrada
2. Processamento
3. Decisao
4. Saida

Essas faixas devem ser desenhadas como:

- bandas horizontais empilhadas verticalmente;
- cada banda com titulo, cor e explicacao curta;
- o fluxo principal sempre descendo do topo para baixo;
- variacoes aparecendo dos lados.

Exemplo de backbone:

```text
Receber midia
  ->
Preparar e analisar
  ->
Decidir se publica ou revisa
  ->
Responder e distribuir
```

## 4. A copia dos nos precisa falar lingua operacional

Evitar labels tecnicos como:

- `EvaluateMediaPromptJob`
- `AnalyzeContentSafetyJob`
- `SyncEventIntakeChannelsAction`

Preferir:

- Receber foto no WhatsApp privado
- Receber foto no grupo do WhatsApp
- Confirmar que a midia entrou
- Baixar e preparar a imagem
- Analisar seguranca com IA
- Entender contexto e legenda
- Enviar para revisao manual
- Aprovar automaticamente
- Responder ao convidado
- Publicar na galeria
- Disponibilizar no telao

## 5. O node nao deve abrir formulario inteiro de cara

Cada no deve mostrar apenas:

- icone;
- titulo curto;
- resumo de uma linha;
- badge de estado:
  - ativo
  - desativado
  - obrigatorio
  - opcional
  - automatico
  - leitura tecnica

Ao clicar:

- abre inspector lateral com edicao;
- se a capacidade for so leitura, abre explicacao e status real;
- se a capacidade depender de outro modulo, mostrar lock ou dependencia.

## 6. O inspector lateral e obrigatorio

Recomendacao:

- desktop: painel lateral direito;
- mobile: `Drawer` inferior;
- nunca usar modal central como experiencia principal.

Razao:

- o canvas precisa continuar visivel;
- o usuario precisa manter o contexto do fluxo enquanto edita;
- o repositorio ja tem esse padrao pronto.

## 7. O topo da tela deve gerar uma explicacao humana viva

Exemplo de frase:

> Quando uma foto chega pelo WhatsApp privado, o sistema confirma o recebimento, analisa seguranca, pode enviar para revisao manual e, quando aprovada, responde ao convidado e disponibiliza a midia no evento.

Isso ajuda em:

- onboarding;
- suporte;
- confianca do cliente;
- auditoria operacional.

---

## Recomendacao de UX para a V1

## Estrutura da tela

### Cabecalho

- titulo: `Jornada da midia`
- subtitulo: `Como o evento trata cada foto ou video recebido`
- chips com canais ativos:
  - WhatsApp privado
  - WhatsApp grupos
  - Telegram
  - Link de envio
- resumo humano automatico
- seletor de template inicial

### Corpo principal

- canvas vertical com quatro faixas fixas;
- legenda fixa das quatro faixas no topo da area;
- backbone central;
- ramos laterais para condicoes ativas;
- sem `MiniMap` na V1;
- toolbar minima:
  - `Centralizar fluxo`
  - `Ver detalhes tecnicos`
- edge labels curtas:
  - Sim
  - Nao
  - Revisao
  - Publicado
  - Bloqueado
  - Grupo
  - Privado

### Lado direito

- inspector do no selecionado;
- resumo do que esta ativo;
- campos editaveis;
- preview textual do impacto;
- CTA de salvar.

### Barra secundaria

- simulador de cenarios prontos;
- botao `Ver detalhes tecnicos`;
- botao `Comparar com template`;
- indicador de inconsistencias;
- tooltips apenas para ajuda secundaria, nao para explicar a regra principal.

## Templates iniciais recomendados

- Aprovacao direta
- Revisao manual
- IA moderando
- Hibrido IA + humano
- Evento social simples
- Evento corporativo controlado

Esses templates devem aplicar patches sobre o estado atual do evento, nao coordenadas no canvas.

## Simulador de caminho

O simulador e uma das partes mais valiosas da V1.

Cenarios recomendados:

- Foto com legenda via WhatsApp privado
- Foto sem legenda via grupo
- Video via Telegram
- Midia bloqueada por safety
- Midia que vai para review manual
- Midia aprovada e publicada

Resultado esperado:

- o canvas destaca o caminho percorrido;
- o inspector explica por que cada decisao ocorreu;
- o resumo humano muda de acordo com o cenario.

---

## Recomendacao tecnica

## 0. Decisoes agora ancoradas em fontes oficiais

Para sair da zona de opiniao e ficar mais proximo do que a nossa stack realmente suporta hoje, a proposta abaixo passa a ser sustentada tambem pela documentacao oficial consultada em `2026-04-09`.

### React Flow

- a documentacao oficial confirma que custom nodes sao apenas componentes React registrados em `nodeTypes`; isso encaixa diretamente com a nossa arquitetura de componentes e com o padrao de cards administrativos do painel;
- a documentacao oficial tambem confirma o uso de `ReactFlowProvider` quando precisamos ler estado do flow fora do canvas, o que combina com inspector lateral, barra de resumo e simulador;
- o proprio `React Flow` expoe props de interacao como `nodesDraggable`, `nodesConnectable` e `elementsSelectable`, entao tecnicamente e simples travar o canvas na V1 sem hack;
- a documentacao oficial do `NodeToolbar` reforca que quick actions por no podem aparecer sem escalar com o viewport, o que e util para a nossa ideia de acoes curtas tipo `Editar`, `Simular` e `Ver regra`;
- a documentacao oficial de performance recomenda memoizar componentes e funcoes e evitar subscriptions largas em `nodes` e `edges`, o que sustenta a decisao de manter o canvas simples na V1;
- a documentacao oficial de testes do `React Flow` recomenda `Cypress` ou `Playwright` para apps reais porque a lib depende de medir DOM para renderizar edges corretamente; como o repo ja usa `Playwright`, isso reforca a nossa trilha de teste principal para o canvas.

### TanStack Query

- a documentacao oficial reforca `useMutation` para escrita e `invalidateQueries` apos sucesso, o que sustenta a ideia de um save coeso do builder seguido de revalidacao do projection payload;
- isso combina melhor com a pagina nova do que espalhar varios `PATCH` independentes e tentar sincronizar estado local na mao.

### Vitest

- a documentacao oficial confirma `jsdom` como ambiente browser-like adequado para testes de frontend em Node;
- a documentacao oficial de timers reforca `vi.useFakeTimers()` para controlar intervalos e timeouts, o que e perfeito para testar o simulador da jornada sem esperas reais;
- a documentacao oficial de Browser Mode mostra que, se precisarmos subir a fidelidade depois, da para rodar testes selecionados em browser real com provider Playwright e ainda gerar traces.

### Playwright e Testing Library

- a documentacao oficial do `Playwright` recomenda locators baseados em como o usuario percebe a pagina, especialmente `getByRole`, `getByLabel` e `getByText`, e desestimula CSS/XPath fragil;
- a documentacao oficial do `Testing Library` segue a mesma linha, priorizando `getByRole` com nome acessivel;
- a documentacao oficial de visual comparisons do `Playwright` e util para a nossa pagina porque o builder tem muita semantica visual, mas ela tambem deixa claro que as baselines precisam rodar em ambiente consistente.

### Laravel 13

- a documentacao oficial de `Validation` confirma que requests XHR recebem JSON `422` quando invalidos, o que encaixa com nossa SPA;
- a documentacao oficial de Form Requests continua sendo o caminho certo para encapsular validacao e autorizacao do update do builder;
- a documentacao oficial de `HTTP Tests` reforca assercoes prontas como `assertValid`, `assertInvalid` e `assertExactJsonStructure`, muito uteis para o endpoint agregador;
- a documentacao oficial de `Database Testing` adiciona `expectsDatabaseQueryCount`, o que vale para proteger o projection endpoint de regressao e `N+1`.

### Radix UI, shadcn/ui e Tailwind

- o repositorio ja expoe `SheetTitle` e `SheetDescription` em `apps/web/src/components/ui/sheet.tsx`, entao o inspector lateral pode nascer acessivel sem criar primitives novas;
- o repo ja usa tokens semanticos em `apps/web/src/index.css` e `apps/web/tailwind.config.ts`, o que sustenta mapear as quatro faixas da jornada para variaveis de tema em vez de espalhar cores soltas;
- a trilha oficial de `Dialog` do Radix e de tema do `shadcn/ui` reforca titulo, descricao, foco previsivel e tokens CSS como base da experiencia;
- o proprio `Tailwind` documenta `ring` e estados de foco como parte do styling, o que sustenta a regra de nunca remover foco sem substituto visivel.

## O que realmente vale endurecer agora

Depois da validacao da proposta, os pontos que mais aumentam qualidade real de produto e implementacao sao estes:

### 1. Interacao do canvas

- manter o canvas travado;
- explicitar `selectNodesOnDrag={false}` e `zoomOnDoubleClick={false}`;
- decidir `preventScrolling` conscientemente e, na V1, favorecer scroll natural da pagina;
- adicionar `ariaLabelConfig` em portugues;
- nao usar `MiniMap` e nem ruido de editor livre na V1;
- nao ligar `onlyRenderVisibleElements` cedo, porque o grafo inicial sera pequeno e o ganho nao compensa a complexidade.

### 2. Estabilidade de render

- garantir container com largura e altura definidas;
- manter `nodeTypes`, `edgeTypes` e callbacks estaveis;
- esconder handles com `visibility` e nao com `display: none`;
- usar `useUpdateNodeInternals` se um node mudar handles dinamicamente;
- aplicar `nopan` em controles interativos dentro do card.

### 3. UX e acessibilidade

- modo simples como padrao;
- legenda curta e fixa no topo: `Entrada`, `Processamento`, `Decisao`, `Saida`;
- inspector com titulo e descricao formais sempre presentes;
- botoes so com icone precisam de nome acessivel;
- tooltip serve como ajuda complementar, nunca como instrucao critica;
- foco visivel precisa fazer parte do design dos cards, chips e acoes.

### 4. Save previsivel

- manter rascunho local no inspector;
- template aplica patch local e nao salva sozinho;
- evitar optimistic update da projection inteira;
- fazer mutation unica e revalidar as queries relacionadas no sucesso.

### 5. Validacao cruzada e contrato

- usar `after()` no `FormRequest` agregador para regras compostas;
- tratar `assertExactJsonStructure` como defesa do contrato;
- subir `expectsDatabaseQueryCount` para criterio de qualidade do endpoint agregador.

### 6. Teste semantico, nao so visual

- continuar usando `Playwright` para o canvas real;
- adicionar `aria snapshots` alem de screenshot;
- cobrir labels, roles e navegacao por teclado desde a V1;
- manter `Vitest Browser Mode` como trilha futura, nao como obrigacao agora.

## 1. React Flow sim, canvas livre nao

Recomendacao tecnica principal:

- usar `React Flow` para renderizacao visual;
- travar as interacoes para a V1.

O que habilitar:

- render de nodes e edges customizados;
- zoom e pan leves;
- highlight de caminho no simulador;
- mini feedback visual de branches;
- no selection controlado pela aplicacao.

O que bloquear:

- drag livre de nodes;
- create edge manual;
- delete node;
- reconectar handles;
- persistencia de coordenadas do usuario.

Detalhe importante:

- como a documentacao oficial do `React Flow` ja expoe interaction props para isso, a V1 pode nascer com algo proximo de:
  - `nodesDraggable = false`
  - `nodesConnectable = false`
  - `elementsSelectable = true`
  - `nodesFocusable = true`
  - `edgesFocusable = false`
  - `deleteKeyCode = null`
  - `selectNodesOnDrag = false`
  - `zoomOnDoubleClick = false`
  - `preventScrolling = false`
  - `ariaLabelConfig = ptBrFlowAria`
- isso entrega leitura, selecao, acessibilidade e foco, sem abrir a porta para um editor livre.

Outras duas regras praticas importam bastante:

- o container pai do `React Flow` precisa nascer com tamanho definido, senao o canvas falha de forma silenciosa;
- na V1, nao vale ligar `onlyRenderVisibleElements`, porque o fluxo tera poucos nos e a documentacao oficial deixa claro que essa opcao tambem tem custo proprio.

Leitura pratica:

- o usuario recebe a familiaridade visual de um flow builder;
- o produto continua sendo guiado e seguro.

## 2. Nao usar auto-layout engine na V1

Como a V1 tera:

- quatro faixas fixas;
- backbone principal conhecido;
- poucas variacoes laterais;

a melhor abordagem inicial e:

- layout deterministico por mapa de posicoes;
- sem `Dagre`;
- sem `ELK`;
- sem custo extra de layout engine.

Exemplo conceitual:

- `Entrada` em `y = 0`
- `Processamento` em `y = 320`
- `Decisao` em `y = 640`
- `Saida` em `y = 960`
- backbone central em `x = 480`
- variacoes laterais em `x = 180` e `x = 780`

Quando o produto pedir:

- subflows colapsaveis;
- dezenas de branches;
- agrupamentos dinamicos;

ai sim `ELK` passa a fazer sentido.

Observacao importante vinda da doc oficial:

- `React Flow` tem exemplos oficiais tanto de subflows quanto de layout com `Dagre` e `ELK`, entao esse caminho continua valido para fases futuras;
- o ponto aqui nao e limitacao tecnica da biblioteca, e sim proteger escopo, clareza de produto e custo de manutencao da V1.

## 3. Nao introduzir Zustand na primeira entrega

Hoje o repo nao usa `Zustand`.

Para a V1, o estado pode ficar em:

- `TanStack Query` para leitura/escrita do backend;
- `react-hook-form` no inspector;
- reducer local para selecao, simulacao e estado visual.

Adicionar `Zustand` so vale a pena quando houver:

- multiplos paines sincronizados;
- undo/redo real;
- subflows complexos;
- composicao de canvas maior.

Leitura refinada pela doc oficial:

- a propria documentacao de `React Flow` mostra `Zustand` como boa opcao quando o app cresce e o estado precisa ser alterado de dentro dos proprios nodes;
- isso fortalece a decisao de adiar `Zustand` agora, porque a nossa V1 tera nodes guiados, pouca mutacao interna e inspector centralizado fora do node;
- se um dia o builder passar a suportar subflows, undo/redo e edicao rica dentro do proprio canvas, ai `Zustand` deixa de ser extra e passa a ser candidato serio.

## 4. A fonte de verdade da V1 deve continuar sendo o estado real do evento

Na V1, a fonte de verdade deve permanecer em campos reais ja existentes:

- `intake_channels`
- `intake_defaults`
- `moderation_mode`
- `content_moderation`
- `media_intelligence`
- `modules`

O canvas nao deve salvar:

- nodes;
- edges;
- posicoes;
- regras arbitarias.

O canvas deve ser apenas:

- representacao;
- resumo;
- guia de edicao;
- simulador.

## 5. Criar uma projecao agregada da jornada

Mesmo sem DSL novo, a experiencia precisa de um payload agregador.

Recomendacao:

- novo endpoint no modulo `Events`;
- retorno em formato de `JourneyProjection`;
- escrita tambem centralizada por um `JourneyUpdate` orquestrador.

### Rotas sugeridas

- `GET /api/v1/events/{event}/journey-builder`
- `PATCH /api/v1/events/{event}/journey-builder`

### Ownership recomendado

Manter em `Events`, porque:

- o usuario edita um evento;
- o `Events` ja agrega dependencias;
- a pagina nao executa pipeline por si;
- a feature ainda e uma camada de orquestracao, nao um dominio autonomo.

Se no futuro surgir:

- DSL propria;
- simulador server-side robusto;
- versoes de fluxo;
- templates persistidos;

ai faz sentido extrair um modulo `EventJourney`.

## 6. Orquestracao backend recomendada

### Leitura

O backend agregador pode reaproveitar:

- `EventIntakeChannelsStateBuilder`
- `EventIntakeBlacklistStateBuilder`
- `ContentModerationSettingsResolver`
- `EventMediaIntelligenceSettingsController` ou action equivalente
- `MediaEffectiveStateResolver` para legendas de decisao

### Escrita

O backend agregador deve distribuir para as actions corretas:

- `UpdateEventAction` para `moderation_mode`, `modules` e campos base;
- `SyncEventIntakeChannelsAction` para canais;
- `UpsertEventContentModerationSettingsAction` para Safety;
- `UpsertEventMediaIntelligenceSettingsAction` para VLM e replies.

Importante:

- o frontend nao deve chamar cinco endpoints distintos para salvar a jornada inteira;
- a tela nova precisa de uma operacao coesa de salvar;
- a projection visual nao deve receber optimistic patch completo;
- o usuario precisa trabalhar com rascunho local e confirmar no CTA de salvar antes de persistir.

## 7. Estrutura frontend recomendada

Como a feature e event-centric, o melhor encaixe e dentro de `events`.

Estrutura sugerida:

```text
apps/web/src/modules/events/
+-- pages/
|   `-- EventJourneyBuilderPage.tsx
+-- journey/
|   +-- api.ts
|   +-- types.ts
|   +-- buildJourneyGraph.ts
|   +-- summary.ts
|   +-- simulator.ts
|   +-- components/
|   |   +-- JourneyFlowCanvas.tsx
|   |   +-- JourneyNodeCard.tsx
|   |   +-- JourneyEdge.tsx
|   |   +-- JourneyInspector.tsx
|   |   +-- JourneySummaryBanner.tsx
|   |   +-- JourneyTemplateRail.tsx
|   |   `-- JourneyScenarioSimulator.tsx
|   `-- mappers/
|       +-- fromProjection.ts
|       `-- toUpdatePayload.ts
```

Rotas sugeridas:

- `apps/web/src/App.tsx`: `/events/:id/flow`
- label na UI: `Jornada da midia`

## 8. Estrutura backend recomendada

```text
apps/api/app/Modules/Events/
+-- Actions/
|   +-- BuildEventJourneyProjectionAction.php
|   `-- UpdateEventJourneyAction.php
+-- Data/
|   +-- EventJourneyProjectionData.php
|   +-- EventJourneyStageData.php
|   `-- EventJourneyNodeData.php
+-- Http/
|   +-- Controllers/
|   |   `-- EventJourneyController.php
|   +-- Requests/
|   |   `-- UpdateEventJourneyRequest.php
|   `-- Resources/
|       `-- EventJourneyResource.php
`-- routes/
    `-- api.php
```

## 9. Camada de graph adapter no frontend

Para evitar que `React Flow` "vaze" para a regra de negocio, a tela deve ter uma pequena camada adaptadora.

Estrutura sugerida:

- `buildJourneyGraph.ts`
- `buildJourneyScenarios.ts`
- `journey-node-types.ts`
- `journey-edge-types.ts`

Responsabilidades:

- transformar `JourneyProjection` em `nodes` e `edges` para o canvas;
- definir handles fixos por decisao, inclusive `default`;
- manter IDs de branch estaveis para simulacao;
- impedir que a view dependa diretamente do formato dos settings internos do evento.

Isso nos permite:

- trocar o renderer visual no futuro se necessario;
- testar regras de branching sem montar o canvas inteiro;
- manter o contrato backend/frontend mais limpo.

---

## Modelo de dados recomendado para a V1

## 1. Projection payload

Exemplo conceitual:

```json
{
  "event": {
    "id": 123,
    "title": "Casamento Ana e Pedro",
    "moderation_mode": "ai",
    "modules": {
      "live": true,
      "wall": true
    }
  },
  "capabilities": {
    "supports_react_flow_visual_mode": true,
    "supports_manual_review": true,
    "supports_ai_reply": true,
    "supports_print": false
  },
  "stages": [
    {
      "id": "entry",
      "label": "Entrada",
      "nodes": [
        {
          "id": "whatsapp_direct",
          "label": "WhatsApp privado",
          "kind": "entry",
          "active": true,
          "editable": true,
          "summary": "Recebe midias por codigo e sessao privada."
        }
      ]
    }
  ],
  "summary": {
    "human_text": "Quando uma foto chega..."
  },
  "simulation_presets": [
    "photo_whatsapp_private_with_caption",
    "photo_whatsapp_group_without_caption",
    "video_telegram",
    "blocked_sender"
  ]
}
```

Importante:

- isso e projecao de leitura;
- nao e DSL de execucao;
- nao duplica tabela de regras.

## 2. Payload de update

Para a V1, o update deve continuar espelhando campos reais.

Exemplo conceitual:

```json
{
  "moderation_mode": "ai",
  "intake_defaults": {
    "whatsapp_instance_id": 10,
    "whatsapp_instance_mode": "shared"
  },
  "intake_channels": {
    "whatsapp_direct": {
      "enabled": true,
      "media_inbox_code": "NOIVA2026",
      "session_ttl_minutes": 180
    },
    "public_upload": {
      "enabled": true
    },
    "telegram": {
      "enabled": false
    }
  },
  "content_moderation": {
    "enabled": true,
    "mode": "enforced",
    "fallback_mode": "review"
  },
  "media_intelligence": {
    "enabled": true,
    "mode": "gate",
    "reply_text_mode": "ai"
  },
  "modules": {
    "live": true,
    "wall": true
  }
}
```

Leitura pratica:

- o builder salva configuracao do produto, nao um desenho;
- o desenho e sempre regenerado a partir desse estado.

## 3. Modelo de branching recomendado

Mesmo sem DSL propria, a projecao pode explicitar branches com uma estrutura pequena e legivel.

Exemplo conceitual:

```json
{
  "id": "decision_publication_mode",
  "kind": "decision",
  "label": "Como o evento decide a publicacao",
  "branches": [
    { "id": "approved", "label": "Aprovado" },
    { "id": "review", "label": "Revisao" },
    { "id": "blocked", "label": "Bloqueado" },
    { "id": "default", "label": "Padrao" }
  ]
}
```

Leitura pratica:

- isso aproveita a melhor ideia do benchmark externo, que e branch estavel por handle;
- mas continua sendo projection de leitura, nao linguagem de execucao.

---

## Como mapear os nos da V1

## Faixa 1 - Entrada

Nos recomendados:

- WhatsApp privado
- WhatsApp grupos
- Telegram privado
- Link de envio

Politicas auxiliares:

- blacklist de remetente;
- instancia WhatsApp;
- TTL de sessao;
- codigos de inbox.

## Faixa 2 - Processamento

Nos recomendados:

- Confirmar recebimento
- Baixar midia
- Preparar variantes
- Analisar seguranca com IA
- Entender contexto e legenda

Detalhes:

- `Confirmar recebimento` pode resumir o feedback inicial;
- `Baixar midia` e `Preparar variantes` devem ser tecnicos e compactos;
- `Analisar seguranca com IA` e `Entender contexto e legenda` sao os principais nos editaveis dessa faixa.

## Faixa 3 - Decisao

Nos recomendados:

- Modo do evento
- Revisao manual
- Aprovar automaticamente
- Bloquear por risco

Branches recomendados:

- Safety bloqueou
- Safety mandou para review
- VLM bloqueou
- VLM pediu review
- Operador decidiu manualmente

## Faixa 4 - Saida

Nos recomendados:

- Reagir ao convidado
- Responder automaticamente
- Publicar na galeria
- Disponibilizar no telao

Nos futuros, fora da V1:

- Enviar para impressao
- Aplicar randomizacao
- Encadear fluxo secundario

---

## O que eu evitaria na primeira entrega

Eu evitaria:

- canvas totalmente livre;
- suporte a loops;
- persistencia de posicao de nodes;
- nos tecnicos demais visiveis por padrao;
- duplicar todas as forms atuais dentro do canvas;
- criar configuracao paralela a `content_moderation` e `media_intelligence`;
- simular IA real no client;
- prometer saidas que o backend ainda nao suporta.
- importar `React Flow UI` como kit visual pronto sem antes alinhar com o nosso stack, porque a trilha oficial deles hoje ja esta orientada ao ecossistema mais novo de `React 19 + Tailwind 4`.

Tambem evitaria substituir o editor atual de evento de uma vez.

Melhor estrategia:

- lancar a jornada como nova pagina guiada;
- manter o editor atual como fallback operacional/admin;
- migrar trafego aos poucos.

---

## Roadmap recomendado

## Fase 1 - Builder guiado e seguro

- nova pagina `Jornada da midia`;
- `@xyflow/react` como renderer travado;
- backbone vertical com quatro faixas;
- 8 a 12 nos principais;
- inspector lateral;
- resumo humano automatico;
- simulador de cenarios prontos;
- save centralizado por endpoint agregador;
- sem DSL nova;
- sem auto-layout engine.

## Fase 1.5 - Operacao mais rica

- templates oficiais;
- validacoes visuais de inconsistencia;
- modo `ver detalhes tecnicos`;
- destaque de locks por entitlement;
- resumo de impacto antes de salvar.
- `NodeToolbar` para acoes rapidas sem abrir inspector completo;
- visual snapshots fixos do canvas no CI.

## Fase 2 - Visual mode mais profundo

- subflows colapsaveis;
- detalhes por canal;
- comparacao entre templates;
- excecoes por origem ou tipo de midia;
- possivel adocao de `ELK` se o grafo crescer.
- possivel adocao de `Zustand` se o estado do canvas passar a ser editado de varios pontos ao mesmo tempo;
- possivel adocao de `Vitest Browser Mode` para testes de componentes visuais de alta fidelidade.

## Fase 3 - DSL propria, se o produto merecer

So considerar uma DSL/engine nova quando houver demanda real por:

- regras por caption;
- regras por tipo de midia;
- publish granular por destino;
- excecoes por canal;
- persistencia de templates customizados;
- simulacao server-side com versionamento.

Antes disso, a DSL seria engenharia prematura.

---

## Riscos reais

## R1. O time confundir visual com flexibilidade total

Se a tela parecer "canvas livre", o usuario vai esperar:

- criar passo arbitrario;
- mover tudo;
- fazer branch para qualquer regra.

Isso vai muito alem da capacidade atual do backend.

Mitigacao:

- backbone travado;
- copia guiada;
- mensagens claras de "automatico", "opcional" e "em breve".

## R2. Duplicar configuracao

Se o builder passar a salvar um JSON proprio desde o inicio, sem necessidade real:

- o time vai manter dois modelos;
- bugs de divergencia vao aparecer;
- suporte vai perder confianca no estado do evento.

Mitigacao:

- V1 so projeta e atualiza o estado real existente.

## R3. O canvas passar nos testes unitarios e falhar no browser real

Esse risco e especialmente relevante com `React Flow`, porque a propria documentacao oficial deixa claro que a biblioteca depende de medicao real de DOM para edges e interacoes.

Mitigacao:

- deixar regras puras em `Vitest`;
- levar leitura visual e interacao do canvas para `Playwright`;
- usar snapshots visuais apenas em ambiente padronizado;
- se necessario, adotar `Vitest Browser Mode` depois para uma faixa intermediaria de testes.

## R4. O canvas ficar bonito, mas cansativo

Esse e o maior risco de produto.

Uma tela visual so vale a pena se:

- reduzir duvida;
- reduzir navegacao entre abas;
- reduzir medo de errar;
- aumentar entendimento do evento.

Se ela virar um builder "legal", mas mais lento que o form atual, falhou.

Mitigacao:

- manter foco em entendimento e edicao guiada;
- usar frase humana, simulacao e templates;
- nao tentar vencer n8n no primeiro dia.

---

## Estrategia de testes recomendada

## 1. Backend

### Feature tests do modulo `Events`

Criar testes HTTP para:

- `GET /api/v1/events/{event}/journey-builder`
- `PATCH /api/v1/events/{event}/journey-builder`

Cobrir:

- `200` para leitura autorizada;
- `403` para acesso sem permissao;
- `422` para payload invalido;
- shape exato do payload principal;
- persistencia correta dos campos alterados;
- invalidacao ou atualizacao das fatias relacionadas apos save.

Como base oficial:

- usar `Form Request` para validacao/autorizacao;
- usar `assertValid`, `assertInvalid`, `assertForbidden` e `assertExactJsonStructure` onde fizer sentido.

### Unit tests de orquestracao

Criar unit tests para:

- `BuildEventJourneyProjectionAction`
- `UpdateEventJourneyAction`
- mapeadores de summary humano
- simulador server-side, se ele nascer no backend

Cobrir:

- projection de cada combinacao principal:
  - moderacao manual
  - moderacao IA
  - safety desligado
  - reply IA
  - canais diferentes
- branch labels corretos;
- flags `editable`, `active`, `optional`, `blocked_by_capability`.

### Protecao contra regressao de query

Para o endpoint de projection, vale adicionar casos com budget de query usando `expectsDatabaseQueryCount`.

Motivo:

- esse endpoint vai agregar muita informacao;
- e facil introduzir `N+1` sem perceber.

## 2. Frontend com Vitest

### O que deve ficar em Vitest

Testar em `Vitest` tudo que for regra pura ou composicao sem depender do canvas real:

- `buildJourneyGraph`
- `buildJourneyScenarios`
- `summary.ts`
- `toUpdatePayload.ts`
- `fromProjection.ts`
- validacoes locais do inspector
- reducers de selecao e simulacao

Casos importantes:

- graph muda quando `reply_text_mode` troca;
- branch `review` aparece quando fallback exige revisao;
- resumo humano muda por canal e modo de moderacao;
- payload de update preserva campos nao editados.

### Timers e simulacao

Usar `vi.useFakeTimers()` para:

- simulador de caminho;
- banners temporarios;
- micro interacoes internas que dependam de `setTimeout`.

Isso reduz flakes e mantem os testes rapidos.

### JSDOM

Manter `jsdom` como ambiente padrao para estes testes.

Se algum teste precisar de APIs especificas do navegador, isolar esse caso e nao contaminar toda a suite.

## 3. Frontend com Playwright

### O que deve ficar em Playwright

Levar para `Playwright` o que depende de browser real, layout e acessibilidade:

- abrir a pagina da jornada;
- selecionar um no;
- editar no inspector;
- salvar e ver estado refletido;
- trocar template;
- rodar simulador de cenario;
- validar destaque visual do caminho;
- validar responsividade desktop/mobile;
- validar navegacao por teclado nos elementos da pagina.

### Seletores

Seguir a linha oficial de `Playwright` e `Testing Library`:

- preferir `getByRole`;
- usar `getByLabel` em formularios;
- usar `getByText` para leitura semantica;
- usar `data-testid` so quando papel/nome acessivel nao forem suficientes.

Isso alinha os testes com a forma como o usuario percebe a pagina e diminui fragilidade.

### Visual regression

Adicionar snapshots visuais para alguns estados fixos:

- estado base com fluxo padrao;
- estado com review manual ativo;
- estado com multiplos canais ativos;
- mobile drawer aberto;
- branch simulado destacado.

Cuidados:

- rodar em ambiente CI padronizado;
- mascarar dados dinamicos se necessario;
- nao usar snapshot para tudo.

### Aria snapshots e acessibilidade basica

Adicionar tambem snapshots semanticos e smoke checks para:

- pagina base;
- inspector aberto;
- drawer mobile aberto.

Isso ajuda a pegar:

- botoes sem nome acessivel;
- hierarquia ruim de titulos e descricoes;
- regressao de leitura por teclado e screen reader.

## 4. React Flow especificamente

Seguir a leitura oficial da propria lib:

- `React Flow` testa melhor em browser real;
- se for necessario testar alguma unidade com `Vitest`, manter o nivel baixo:
  - node card puro
  - edge label puro
  - summary banner
- evitar depender de medicao real do canvas em `jsdom`.

## 5. Trilha opcional futura: Vitest Browser Mode

Nao e requisito para a V1, mas e uma boa carta na manga.

Cenarios onde ele faz sentido:

- interacoes de componentes visuais que ficam caras demais em `Playwright`;
- verificacao de comportamento em browser real sem subir suite E2E completa;
- coleta de traces usando provider Playwright.

Recomendacao:

- manter fora do escopo inicial;
- reconsiderar quando o canvas ganhar mais riqueza visual.

---

## Shell inicial da pagina em `2026-04-10`

A `Tarefa 3.1` saiu do papel mantendo a decisao central da analise:

- a jornada abriu como pagina propria em `apps/web/src/modules/events/pages/EventJourneyBuilderPage.tsx`;
- a rota `/events/:id/flow` entrou em `apps/web/src/App.tsx`;
- o preload dessa page entrou no matcher de `/events` em `apps/web/src/app/routing/route-preload.ts`;
- o detalhe do evento ganhou atalho visivel para `Jornada da midia`;
- o layout principal reaproveita `react-resizable-panels` via `apps/web/src/components/ui/resizable.tsx`;
- o lado esquerdo ficou como trilho visual guiado e fixo;
- o lado direito ficou como inspector reservado, ainda sem formulario contextual.

Leitura pratica:

- a page ja entrega titulo, subtitulo, resumo humano, legenda semantica fixa e leitura por etapas;
- naquele corte, o canvas ainda nao era `React Flow`, por escolha;
- a pagina ja deixa pronto o espaco de `canvas + inspector` sem vender liberdade tecnica antes da hora;
- o simulador local passou a ser consumido na propria page por botoes de cenarios, mas ainda sem highlight visual de edge/node;
- a etapa seguinte pode focar so no renderer de grafo, sem rediscutir rota, shell ou copy base.

## Validacao oficial usada nesta etapa em `2026-04-10`

### React Router

Foi revalidado na documentacao oficial que o roteamento atual em `createRoutesFromElements` continua sendo a forma certa de registrar a nova page no `Router` principal:

- https://reactrouter.com/api/components/Route

### shadcn/ui + resizable

Foi revalidado na documentacao oficial do componente `Resizable` do shadcn/ui que o split `canvas + inspector` reaproveitando `react-resizable-panels` e uma trilha compativel com a stack atual:

- https://ui.shadcn.com/docs/components/resizable

## Bateria adicional validada nesta etapa

Comandos executados:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/EventJourneyBuilderPage.test.tsx src/modules/events/EventDetailPage.test.tsx src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
npm run test -- src/modules/events
npm run type-check
```

Resultado:

- `4` testes passaram na bateria da page nova;
- `10` testes passaram na bateria focada `page + detail + characterization`;
- `69` testes passaram na regressao ampliada do modulo `events`;
- `type-check` passou sem erros.

Leitura pratica:

- o shell da nova page entrou verde;
- a regressao residual do `EventDetailPage` foi saneada;
- o proximo passo tecnico agora e isolar `JourneyFlowCanvas` e trocar o trilho visual guiado pelo renderer com `React Flow`.

## Renderer real com React Flow em `2026-04-11`

A `Tarefa 3.2` foi concluida e o canvas da jornada deixou de ser apenas shell visual.

Entrega realizada:

- `@xyflow/react` entrou no frontend como dependencia real;
- `apps/web/src/modules/events/journey/JourneyFlowCanvas.tsx` passou a encapsular o renderer;
- o canvas agora recebe `graph`, `selectedNodeId`, `highlightedNodeIds` e `highlightedEdgeIds` a partir da projection tipada;
- a toolbar externa aciona `fitView` por callback de `onReady`, sem necessidade de `ReactFlowProvider` nesta fase;
- a selecao de node passou a abrir o inspector real da page;
- o simulador local agora destaca o caminho no canvas usando `node IDs` e `edge IDs` estaveis;
- o fallback de `stages=[]` continuou tratado dentro do proprio canvas.

Decisoes que sairam da documentacao oficial e viraram codigo:

- o wrapper usa `nodesDraggable={false}` e `nodesConnectable={false}` para manter o canvas travado;
- `selectNodesOnDrag={false}` e `zoomOnDoubleClick={false}` reduzem interacao acidental;
- `preventScrolling={false}` preserva o scroll natural da pagina;
- `onlyRenderVisibleElements={false}` ficou explicito na V1;
- `ariaLabelConfig` em portugues entrou no renderer;
- handles condicionais continuam visiveis com `visibility`, sem `display: none`;
- `nodeTypes`, `edgeTypes`, callbacks e objetos de config ficaram estaveis para evitar rerender desnecessario.

Leitura pratica:

- a jornada agora ja usa `React Flow` de verdade, mas continua sem vender canvas livre;
- o design segue o compromisso da V1: renderer visual forte, regra de negocio fora do canvas;
- ainda nao houve necessidade tecnica de `ReactFlowProvider`, `MiniMap`, `NodeToolbar` ou `onlyRenderVisibleElements`;
- a proxima etapa pode focar em nodes customizados e inspector editavel sem reabrir a discussao de renderer.

## Extracao dos custom nodes em `2026-04-11`

A `Tarefa 3.3` foi concluida logo depois do renderer real.

Entrega realizada:

- `apps/web/src/modules/events/journey/JourneyNodeCard.tsx` passou a concentrar a apresentacao dos cards;
- `apps/web/src/modules/events/journey/JourneyFlowNodes.tsx` passou a concentrar os wrappers de `NodeProps` e o `nodeTypes` estavel;
- `JourneyFlowCanvas.tsx` deixou de carregar a UI inline dos nos;
- os nos de decisao ganharam uma variacao visual propria para expor caminhos de branch;
- os chips do card foram humanizados para a linguagem operacional da jornada.

Leitura pratica:

- o wrapper do canvas ficou menor, mais testavel e mais perto do papel correto de adapter;
- a apresentacao dos nos agora pode evoluir sem reabrir o wiring do `React Flow`;
- o inspector editavel fica mais barato de acoplar porque a hierarquia visual do node nao mora mais dentro do renderer.

## Validacao oficial usada nesta etapa em `2026-04-11`

### React Flow custom nodes e NodeProps

Foi revalidado na documentacao oficial que:

- `NodeTypes` deve mapear tipo para um componente React proprio;
- `NodeProps` e o contrato certo para componentes de node customizado;
- `selected`, `dragging`, `selectable` e `isConnectable` continuam vindo por props no custom node.

Fontes:

- https://reactflow.dev/learn/customization/custom-nodes
- https://reactflow.dev/api-reference/types/node-props

## Bateria adicional validada nesta etapa

Comandos executados:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/JourneyNodeCard.test.tsx src/modules/events/journey/__tests__/JourneyFlowCanvas.test.tsx src/modules/events/journey/__tests__/EventJourneyBuilderPage.test.tsx
npm run test -- src/modules/events
npm run type-check
```

Resultado:

- `2` testes passaram na bateria de `JourneyNodeCard`;
- `9` testes passaram na bateria focada `node card + canvas + page`;
- `74` testes passaram na regressao ampliada do modulo `events`;
- `type-check` passou sem erros.

## Extracao das edges e redistribuicao do grafo em `2026-04-11`

A `Tarefa 3.4` foi concluida com dois ajustes em conjunto:

- a edge saiu do wrapper do canvas;
- a geometria deterministica do grafo foi reorganizada para reduzir sobreposicao visual.

Entrega realizada:

- `apps/web/src/modules/events/journey/JourneyEdgeLabel.tsx` passou a concentrar o label visual da edge;
- `apps/web/src/modules/events/journey/JourneyFlowEdges.tsx` passou a concentrar o custom edge, `edgeTypes` e o mapper para `React Flow`;
- `JourneyFlowCanvas.tsx` ficou responsavel apenas por wiring de `nodes`, `edges`, callbacks e viewport;
- `buildJourneyGraph.ts` ganhou uma distribuicao mais aberta entre faixas e rows, com coordenadas novas e mais espaco vertical;
- `buildJourneyGraph.test.ts` passou a proteger explicitamente que nos da mesma faixa nao se sobrepoem;
- a page e o canvas passaram a abrir com mais altura util e `fitView` menos agressivo para reduzir o efeito de tudo "empilhado" na viewport inicial.

Leitura pratica:

- o canvas ficou totalmente modular: node module, edge module e wrapper separados;
- o fluxo inicial fica mais legivel sem depender de auto-layout externo;
- a V1 continua fiel ao plano: layout deterministico, sem `Dagre`, sem `ELK` e sem canvas livre.

## Validacao oficial usada nesta etapa em `2026-04-11`

### React Flow custom edges e edge labels

Foi revalidado na documentacao oficial que:

- custom edges continuam sendo o caminho certo para labels proprios e estados visuais por branch;
- `EdgeProps` e o contrato certo para os componentes de edge customizada;
- labels de edge podem ser renderizados por camada propria sem misturar semantica com o `BaseEdge`.

Fontes:

- https://reactflow.dev/learn/customization/edge-labels
- https://reactflow.dev/api-reference/types/edge-props

## Bateria adicional validada nesta etapa

Comandos executados:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/JourneyEdgeLabel.test.tsx src/modules/events/journey/__tests__/buildJourneyGraph.test.ts src/modules/events/journey/__tests__/JourneyFlowCanvas.test.tsx src/modules/events/journey/__tests__/EventJourneyBuilderPage.test.tsx
npm run test -- src/modules/events
npm run type-check
```

Resultado:

- `1` teste passou na bateria de `JourneyEdgeLabel`;
- `7` testes passaram na bateria de `buildJourneyGraph`;
- `15` testes passaram na bateria focada `edge + graph + canvas + page`;
- `76` testes passaram na regressao ampliada do modulo `events`;
- `type-check` passou sem erros.

## Inspector editavel responsivo em `2026-04-11`

A `Tarefa 3.5` entrou na primeira fatia realmente editavel do builder.

Entrega realizada:

- `apps/web/src/modules/events/journey/JourneyInspector.tsx` passou a concentrar a experiencia de inspector da jornada;
- o desktop ficou como painel lateral direito nao modal, em vez de forcar `Sheet`, para preservar o fluxo continuo de leitura e edicao ao lado do canvas;
- o mobile passou a usar `Drawer` com cabecalho formal e copy curta;
- `apps/web/src/modules/events/journey/buildJourneyInspectorDraft.ts` passou a montar o draft local minimo dos formularios leves;
- o inspector agora resolve `node.id -> section` e abre formularios proprios para:
  - `decision_event_moderation_mode`;
  - `entry_whatsapp_direct`;
  - `entry_whatsapp_groups`;
  - `entry_telegram`;
  - `entry_public_upload`;
  - `processing_safety_ai` / `decision_safety_result`;
  - `processing_media_intelligence` / `decision_context_gate` / `output_reply_text`;
- Safety reaproveitou `EventContentModerationSettingsForm`;
- MediaIntelligence reaproveitou `EventMediaIntelligenceSettingsForm`;
- o save continua centralizado em `PATCH /events/{event}/journey-builder`, sem reabrir a arquitetura em mutacoes separadas por card;
- o inspector agora assume explicitamente o modelo `rascunho local -> save -> invalidateQueries -> projection revalidada`;
- nodes ainda fora da primeira fatia ficam em modo read-only com CTA para o editor completo.

Tradeoff tecnico que precisa ficar explicito:

- a projection agregada ainda nao expoe todos os campos detalhados necessarios para hidratar os formularios completos de Safety e MediaIntelligence;
- por isso, a leitura dessas duas secoes ainda usa os endpoints detalhados existentes;
- isso nao quebrou a decisao arquitetural principal porque a persistencia continua centralizada no endpoint agregado da jornada;
- a proxima rodada pode reduzir essa ponte quando a projection carregar thresholds, prompt versions e outros campos de configuracao detalhada.

Leitura pratica:

- o builder deixou de ser apenas visual; ele ja salva configuracoes reais nos primeiros caminhos de maior valor;
- a noiva, cerimonial ou operador conseguem editar entrada, moderacao e automacao sem cair imediatamente no editor tecnico completo;
- a page ainda evita prometer liberdade total: cada node continua preso ao que o backend realmente suporta.

## Templates guiados locais em `2026-04-11`

A `Tarefa 3.6` foi fechada em cima do mesmo principio da arquitetura:

- o backend continua sendo a fonte de verdade do estado salvo;
- o frontend pode projetar um estado local temporario para explicar a mudanca antes do save;
- o template nao vira automacao escondida nem mutacao silenciosa.

Entrega realizada:

- `apps/web/src/modules/events/journey/buildJourneyTemplatePreview.ts` passou a concentrar catalogo, patch, diff humano e preview de projection;
- `apps/web/src/modules/events/journey/JourneyTemplateRail.tsx` entrou como trilho guiado acima do canvas;
- os `6` templates da V1 ja funcionam sobre o estado atual do evento:
  - `Aprovacao direta`;
  - `Revisao manual`;
  - `IA moderando`;
  - `Hibrido IA + humano`;
  - `Evento social simples`;
  - `Evento corporativo controlado`;
- a page passou a calcular `effectiveProjection`, trocando resumo, simulador e canvas para o preview local do template antes do save real;
- o save do template continua usando a mutation agregada `updateEventJourneyBuilder`, seguido de `invalidateQueries` e projection revalidada;
- o inspector agora recebe `templateDraftPreview` e trava edicao manual enquanto existe um template em rascunho;
- Safety e MediaIntelligence detalhados tambem recebem merge local do patch do template, para o preview ficar coerente com o restante da tela.

Decisao importante de UX:

- o template nao salva sozinho;
- o usuario compara o diff primeiro;
- confirma a aplicacao ao rascunho;
- ve o resultado no canvas e no resumo;
- so depois usa `Salvar template`.

Isso manteve a direcao correta do produto:

- continua parecendo um builder guiado;
- nao abre liberdade total de n8n logo de cara;
- deixa claro o que mudou;
- reduz medo de errar para noiva, cerimonialista e operador.

Regra de capability preservada:

- templates nao ativam capability indisponivel;
- quando `wall` nao esta liberado por pacote ou modulo, o diff entra como `Nao aplicado`;
- isso impede a V1 de prometer um caminho que o entitlement real nao sustenta.

Tradeoff tecnico:

- o preview do template hoje reconstroi um subconjunto relevante da projection no frontend para manter `summary`, `nodes`, `branches` e `warnings` coerentes;
- isso foi suficiente para a V1 porque os templates alteram um conjunto controlado de settings;
- se a projection ficar muito mais rica no futuro, vale considerar um endpoint server-side de `preview patch` para reduzir a logica espelhada no cliente.

## Validacao oficial usada nesta etapa em `2026-04-11`

### shadcn/ui alert dialog

Foi revalidado na documentacao oficial que `AlertDialog` continua sendo o componente certo para confirmacao explicita antes de aplicar um template ao rascunho local:

- https://ui.shadcn.com/docs/components/alert-dialog

## Bateria adicional validada nesta etapa

Comandos executados:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/buildJourneyTemplatePreview.test.ts src/modules/events/journey/__tests__/JourneyTemplateRail.test.tsx src/modules/events/journey/__tests__/EventJourneyBuilderPage.test.tsx src/modules/events/journey/__tests__/JourneyInspector.test.tsx
npm run test -- src/modules/events
npm run type-check
```

Resultado:

- `17` testes passaram na bateria focada `template preview + rail + page + inspector`;
- `89` testes passaram na regressao ampliada do modulo `events`;
- `type-check` passou sem erros.

## Simulador de cenarios fechado em `2026-04-11`

A `Tarefa 3.7` completou a parte que faltava para a V1 parecer um mapa operacional, e nao so um fluxograma bonito.

Entrega realizada:

- `apps/web/src/modules/events/journey/JourneyScenarioSimulator.tsx` passou a concentrar a UX do simulador;
- os botoes de cenario continuam usando os IDs estaveis vindos de `buildJourneyScenarios.ts`;
- o simulador agora mostra estado ativo, outcome e CTA explicito de `Limpar simulacao`;
- o resumo humano troca para `scenario.humanText` sem tocar em draft nem em save;
- o canvas continua destacando o caminho pela projection efetiva atual, incluindo templates locais ainda nao persistidos;
- o inspector passou a receber `selectedScenario` e agora explica lateralmente:
  - nome do cenario;
  - descricao;
  - outcome;
  - caminho destacado em ordem;
  - CTA de limpar;
- no mobile, o drawer do inspector passou a abrir tambem quando ha simulacao ativa, nao apenas quando existe node selecionado.

Leitura pratica:

- a pessoa nao precisa mais "adivinhar" o que o destaque no canvas quer dizer;
- o fluxo aprovado, bloqueado ou em revisao agora ganha explicacao lateral legivel;
- a simulacao continua sendo uma ferramenta de entendimento, nao um estado escondido do formulario.

Decisao importante preservada:

- simular nao altera formulario;
- simular nao dispara request;
- simular nao salva nada;
- o unico estado que muda e a projection local exibida na tela.

Isso reforca a tese central do produto:

- configuracao continua guiada;
- a explicacao vem junto com o fluxo;
- a V1 nao vira um builder livre onde o usuario precisa pensar como automacao.

## Bateria adicional validada nesta etapa

Comandos executados:

```bash
cd apps/web
npm run test -- src/modules/events/journey
npm run type-check
```

Resultado:

- `52` testes passaram na bateria completa de `journey`;
- `type-check` passou sem erros.

Observacao de regressao ampla:

- ao rodar `npm run test -- src/modules/events`, apareceram falhas externas em `src/modules/events/qr/*` por imports para arquivos ainda inexistentes no worktree atual;
- esse bloqueio nao pertence ao escopo da jornada da midia e nao foi introduzido pelos arquivos de `journey/*`.

## Validacao oficial usada nesta etapa em `2026-04-11`

### shadcn/ui drawer e sheet

Foi revalidado na documentacao oficial que:

- `Drawer` continua sendo o componente certo para o inspector mobile com `DrawerHeader`, `DrawerTitle` e `DrawerDescription`;
- `Sheet` continua sendo a referencia oficial para superficies complementares baseadas em `Dialog`, mas o desktop da V1 ficou propositalmente como painel lateral nao modal por ser mais coerente com a leitura continua do fluxo.

Fontes:

- https://ui.shadcn.com/docs/components/radix/drawer
- https://ui.shadcn.com/docs/components/radix/sheet

## Bateria adicional validada nesta etapa

Comandos executados:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/JourneyInspector.test.tsx src/modules/events/journey/__tests__/EventJourneyBuilderPage.test.tsx
npm run test -- src/modules/events
npm run type-check
```

Resultado:

- `6` testes passaram na bateria de `JourneyInspector`;
- `10` testes passaram na bateria focada `inspector + page`;
- `82` testes passaram na regressao ampliada do modulo `events`;
- `type-check` passou sem erros.

## Validacao oficial usada nesta etapa em `2026-04-11`

### React Flow API Reference

Foi revalidado na documentacao oficial que as props de interacao usadas na V1 continuam sendo o caminho correto para um canvas travado e acessivel:

- https://reactflow.dev/api-reference/react-flow

### React Flow hooks, providers e testing

Foram revalidadas as guias oficiais que sustentam as decisoes de nao usar `ReactFlowProvider` por inercia e de manter testes reais do canvas em browser:

- https://reactflow.dev/learn/advanced-use/hooks-providers
- https://reactflow.dev/learn/advanced-use/testing
- https://reactflow.dev/learn/customization/handles

## Bateria adicional validada nesta etapa

Comandos executados:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/JourneyFlowCanvas.test.tsx src/modules/events/journey/__tests__/EventJourneyBuilderPage.test.tsx src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
npm run test -- src/modules/events
npm run type-check
```

Resultado:

- `3` testes passaram na bateria do `JourneyFlowCanvas`;
- `11` testes passaram na bateria focada `canvas + page + characterization`;
- `72` testes passaram na regressao ampliada do modulo `events`;
- `type-check` passou sem erros.

---

## Fontes oficiais consultadas em 2026-04-09

## Stack real do repositorio

- frontend: [apps/web/package.json](c:\laragon\www\eventovivo\apps\web\package.json)
- backend: [apps/api/composer.json](c:\laragon\www\eventovivo\apps\api\composer.json)

## React Flow

- https://reactflow.dev/
- https://reactflow.dev/learn/customization/custom-nodes
- https://reactflow.dev/api-reference/react-flow
- https://reactflow.dev/learn/customization/handles
- https://reactflow.dev/api-reference/hooks/use-update-node-internals
- https://reactflow.dev/api-reference/hooks/use-on-selection-change
- https://reactflow.dev/api-reference/components/node-toolbar
- https://reactflow.dev/learn/advanced-use/hooks-providers
- https://reactflow.dev/learn/advanced-use/performance
- https://reactflow.dev/learn/advanced-use/state-management
- https://reactflow.dev/learn/advanced-use/testing
- https://reactflow.dev/examples/grouping/sub-flows
- https://reactflow.dev/ui
- https://reactflow.dev/whats-new/2025-10-20

## TanStack Query

- https://tanstack.com/query/latest/docs/framework/react/guides/mutations
- https://tanstack.com/query/v5/docs/framework/react/guides/invalidations-from-mutations

## Vitest

- https://vitest.dev/guide/environment.html
- https://vitest.dev/guide/mocking/timers
- https://vitest.dev/guide/browser/
- https://vitest.dev/guide/browser/trace-view

## Playwright

- https://playwright.dev/docs/locators
- https://playwright.dev/docs/aria-snapshots
- https://playwright.dev/docs/accessibility-testing
- https://playwright.dev/docs/test-snapshots

## Testing Library

- https://testing-library.com/docs/queries/about/
- https://testing-library.com/docs/queries/byrole

## Laravel 13

- https://laravel.com/docs/13.x/validation
- https://laravel.com/docs/13.x/validation#performing-additional-validation
- https://laravel.com/docs/13.x/http-tests
- https://laravel.com/docs/13.x/database-testing

## Radix UI

- https://www.radix-ui.com/primitives/docs/components/dialog

## shadcn/ui

- https://ui.shadcn.com/docs/theming
- https://ui.shadcn.com/docs/components/resizable

## React Router

- https://reactrouter.com/api/components/Route

## Tailwind CSS

- https://tailwindcss.com/docs/ring-width
- https://tailwindcss.com/docs/hover-focus-and-other-states

---

## Decisao recomendada

Se a meta e criar uma pagina visual simples, clara e forte para noiva, cerimonial e operador, eu recomendo:

1. criar a experiencia como `Jornada da midia` dentro do modulo `Events`;
2. usar `React Flow` apenas como shell visual travado;
3. organizar o canvas em quatro faixas fixas e fluxo vertical;
4. manter a fonte de verdade nos settings reais do evento;
5. criar um endpoint agregador no backend para leitura/escrita coesa;
6. reaproveitar os formularios e actions ja existentes de canais, Safety e MediaIntelligence;
7. lancar a V1 com simulador, resumo humano e templates;
8. deixar branches avancados, DSL propria e outputs novos para fases posteriores.

---

## Resumo final

O Evento Vivo ja tem o mais dificil:

- pipeline real;
- ownership modular;
- configuracoes por evento;
- UI administrativa madura.

O que falta agora nao e uma engine de automacao.

O que falta e uma camada de produto que traduza a pipeline para uma experiencia humana, visual e guiada.

Essa camada deve nascer como:

- uma projecao visual do estado real;
- um editor guiado com trilhos;
- um canvas legivel, nao programavel;
- um mapa de processo, nao um IDE.

Esse caminho entrega:

- clareza para o cliente final;
- baixo risco arquitetural;
- alto reaproveitamento da stack atual;
- espaco real para crescer depois para um modo visual mais poderoso;
- uma trilha de testes coerente com o que a stack oficial recomenda para canvas, forms e endpoints agregadores;
- uma ordem de execucao mais segura: primeiro estabilizar baseline, depois abrir a feature.
