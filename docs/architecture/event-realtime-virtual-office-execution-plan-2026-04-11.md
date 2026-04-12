# Event realtime virtual office execution plan - 2026-04-11

## Objetivo

Transformar `docs/architecture/event-realtime-virtual-office-analysis-2026-04-11.md` em um plano de execucao tecnico, detalhado e orientado por TDD para entregar a control room fullscreen de evento em cima da stack atual do `eventovivo`, sem introduzir engine externa nem quebrar ownership modular.

Este plano responde:

1. em quantas sprints a entrega deve ser quebrada;
2. o que entra em cada sprint;
3. quais tarefas e subtarefas precisam acontecer em backend, frontend e docs;
4. qual e a ordem critica de implementacao;
5. quais testes precisam nascer antes do codigo;
6. qual e a definicao de pronto da V1.

Documento base e fonte de verdade tecnica:

- `docs/architecture/event-realtime-virtual-office-analysis-2026-04-11.md`

Referencias internas obrigatorias para a sprint:

- `apps/web/src/modules/wall/player/WallPlayerPage.tsx`
- `apps/web/src/modules/wall/player/hooks/usePerformanceMode.ts`
- `apps/web/src/modules/wall/player/runtime-capabilities.ts`
- `apps/web/src/modules/wall/player/components/WallPlayerRoot.test.tsx`
- `apps/web/src/modules/wall/hooks/useWallRealtimeSync.ts`
- `apps/web/src/modules/wall/hooks/useWallPollingFallback.ts`
- `apps/web/src/app/routing/route-preload.ts`
- `apps/web/src/App.tsx`
- `apps/web/src/lib/query-client.ts`
- `apps/web/src/shared/auth/permissions.ts`
- `apps/web/src/shared/auth/modules.ts`
- `apps/api/config/modules.php`
- `apps/api/config/queue.php`
- `apps/api/routes/channels.php`
- `apps/api/database/seeders/RolesAndPermissionsSeeder.php`
- `apps/api/app/Modules/Wall/Events/AbstractWallBroadcastEvent.php`
- `apps/api/app/Modules/Wall/Events/AbstractWallImmediateBroadcastEvent.php`
- `apps/api/app/Modules/MediaProcessing/Events/AbstractModerationBroadcastEvent.php`
- `apps/api/tests/Feature/Wall/WallLiveSnapshotTest.php`
- `apps/api/tests/Feature/Wall/WallDiagnosticsTest.php`

---

## Validacao usada nesta revisao

Este plano foi revalidado contra:

- docs oficiais de React, TanStack Query, Laravel Events/Broadcasting/Queues/Reverb e MDN para canvas, fullscreen, wake lock, `prefers-reduced-motion`, `status`, `alert` e `log`;
- a stack real do repo, incluindo `WallPlayerPage`, `usePerformanceMode`, `useWallRealtimeSync`, `useWallPollingFallback` e `apps/api/config/queue.php`;
- uma bateria de testes rerodada em `2026-04-12`.

Rodada atual de testes:

- backend: `32` testes e `334` assertions verdes em `Wall`, `MediaProcessing`, `ContentModeration` e `EventJourney`;
- frontend: `6` arquivos e `18` testes verdes cobrindo realtime base, fallback, runtime profile, reduced motion e wall player.

Implicacao pratica:

- o plano continua coerente com o comportamento real da stack;
- o que faltava endurecer era menos tecnologia e mais semantica visual, a11y e UX de degradacao.

---

## Veredito executivo

O caminho mais seguro para produzir valor rapido e chegar a uma V1 forte e este:

1. Sprint 0: contratos, permissao, rota fullscreen e shell tecnico;
2. Sprint 1: V0 read-only usando endpoints existentes;
3. Sprint 2: modulo `EventOperations` no backend com projection, snapshot e timeline;
4. Sprint 3: live realtime no frontend com store externa, canvas em camadas e timeline real;
5. Sprint 4: endurecimento operacional, fallback, retencao e rollout;
6. Sprint 5: replay e polimento visual, se a V1 ja estiver estavel.

Leitura pratica:

- `Sprint 0` e `Sprint 1` destravam demo interna rapido;
- `Sprint 2` e `Sprint 3` transformam a ideia em produto de verdade;
- `Sprint 4` fecha risco operacional;
- `Sprint 5` e opcional para V1 comercial, mas recomendado para V2 forte.

Se a equipe trabalhar em sprints de duas semanas, a recomendacao e:

- juntar `Sprint 0 + Sprint 1`;
- juntar `Sprint 2 + Sprint 3`;
- manter `Sprint 4` e `Sprint 5` separadas.

---

## Decisoes tecnicas fixadas antes da implementacao

Estas decisoes ficam congeladas para evitar drift:

1. O backend nasce em modulo novo: `apps/api/app/Modules/EventOperations`.
2. O frontend nasce em modulo novo: `apps/web/src/modules/event-operations`.
3. A rota protegida principal fica fora do `AdminLayout`: `/events/:id/control-room`.
4. O live usa canal privado proprio: `event.{eventId}.operations`.
5. O boot HTTP e autoritativo; o websocket entrega apenas deltas e append-only timeline.
6. O backend modela estado operacional; o frontend modela animacao.
7. O frontend usa `Canvas 2D` para cena e DOM normal para HUD, timeline, drawers e filtros.
8. `TanStack Query` fica restrito a boot, history e fallback.
9. `useSyncExternalStore` vira a base da leitura do `roomStore`.
10. `ShouldDispatchAfterCommit` e regra para todo delta que depende de persistencia.
11. O backlog de projection e replay usa fila existente, preferencialmente `analytics`, sem abrir fila nova na V1.
12. `operations.view` e permissao propria da feature.
13. A nova permissao nao deve reutilizar `observability.operations_dashboard_permission`, que hoje aponta para `audit.view`.
14. Nao entra em V1: Phaser, WorkAdventure, proximity chat, avatar livre, editor de mapa, sprite por midia.

---

## Decisoes de produto e UX fixadas antes da implementacao

Estas decisoes sao igualmente rigidas e nao devem ser reabertas no meio da sprint sem motivo objetivo:

1. A sala e uma superficie operacional legivel, nao um metaverso nem um brinquedo.
2. A frase-guia da V1 e: mostrar o que merece atencao agora, nao tudo o que aconteceu.
3. A UX precisa funcionar em tres niveis de leitura:
   - macro em `3s`
   - meso em `15s`
   - micro em `2min`
4. Saude global vem antes de gargalo; gargalo vem antes de historico; historico vem antes de detalhe.
5. Macro, meso e micro viram regra de layout obrigatoria, nao apenas principio abstrato.
6. Cada estacao precisa ter um gesto visual proprio e reconhecivel.
7. A sensacao de "equipe trabalhando" nasce de papeis visuais de trabalho, nao apenas de estacoes animadas.
8. `event-translator.ts` e a camada de direcao cenica e orquestracao de atencao da sala, nao apenas um mapper tecnico.
9. Animacao sempre fica subordinada ao estado operacional.
10. A cena precisa continuar legivel sob burst alto, estado calmo e com `prefers-reduced-motion`.
11. HUD informa sem roubar a cena; ele nao pode virar dashboard tradicional sobre um canvas decorativo.
12. Timeline live e um `log` acessivel, enquanto `status` e `alert` ficam reservados para mensagens de sistema e urgencias.
13. Fullscreen, reconnect e degradacao sao parte da UX do produto, nao apenas infraestrutura.
14. Presence channel, quando existir, serve para awareness e nunca para inferir estado operacional.

---

## Contrato inviolavel do live

O live desta feature so pode ser implementado se estas invariantes forem respeitadas em backend e frontend.

### Campos obrigatorios

- `schema_version`
- `snapshot_version`
- `timeline_cursor`
- `event_sequence`
- `server_time`

### Regras obrigatorias

1. Boot HTTP e sempre autoritativo.
2. Websocket entrega apenas diff, append de timeline e alertas pequenos.
3. `event_sequence` e monotonicamente crescente por evento.
4. Delta com `event_sequence` repetido ou menor e descartado como idempotente.
5. Delta com `snapshot_version` divergente exige resync.
6. Gap de `event_sequence` congela aplicacao incremental e ativa rebuild.
7. Todo delta dependente de dado persistido sai after-commit.
8. Se a transacao falhar, o cliente nao pode receber o evento correspondente.

### Implicacao direta no plano

- nenhuma sprint implementa UX fina antes de estes contratos estarem cobertos por teste;
- nenhuma animacao nova entra se a semantica de ordenacao/resync ainda estiver frouxa.

---

## Escopo real da V1

## Entra na V1

- rota fullscreen protegida por evento;
- V0 read-only combinando sinais existentes;
- modulo `EventOperations` no backend;
- `event_operation_events` append-only;
- `event_operation_snapshots` para boot rapido;
- endpoints `room` e `timeline`;
- canal privado `event.{eventId}.operations`;
- `roomStore`, `timelineStore` e `sceneRuntime` separados;
- canvas em camadas com estacoes fixas;
- HUD, timeline e detalhe lateral em DOM;
- fallback degradado por polling;
- retencao e prune iniciais;
- runbook minimo e docs de ownership.

## Fica fora da V1

- replay acelerado completo;
- presence channel como regra de produto;
- editor de layout da sala;
- multiplas salas por evento;
- atlas premium por tipo de evento;
- `OffscreenCanvas` e worker antes de profiling;
- espelhamento frame-perfect do wall;
- sprite por item individual da fila.

---

## Metricas de sucesso da V1

Estas metricas precisam orientar homologacao interna e feedback da primeira rodada com operadores.

### Produto e UX

- um operador entende em ate `5s` se a operacao esta saudavel, em atencao ou em risco;
- um operador identifica o gargalo dominante em ate `15s`;
- um operador chega ao detalhe relevante sem depender de menu administrativo ou treinamento tecnico profundo;
- um operador entende quando a sala entrou em reconnect, resync ou degradado sem achar que a tela travou.

### Tecnica

- reconnect nao duplica evento nem reordena timeline;
- websocket pode cair sem tornar a tela inutil;
- `prefers-reduced-motion` reduz movimento nao essencial sem esconder sinais operacionais;
- burst alto nao degrada a cena ao ponto de esconder backlog, alerta ou wall health;
- o budget perceptivo impede que agentes, baloes ou thumbs concorram com o gargalo dominante.

### Operacional

- um incidente real de moderacao, provider ou wall aparece como sinal acionavel;
- o time consegue fazer resync manual;
- existe runbook minimo para perda de websocket, gap de sequence e wall degradado.

---

## Estrutura alvo da implementacao

## Backend

```text
apps/api/app/Modules/EventOperations/
  Actions/
    BuildEventOperationsRoomAction.php
    BuildEventOperationsTimelineAction.php
    AppendEventOperationEventAction.php
    RebuildEventOperationsSnapshotAction.php
    BuildEventOperationsReplayAction.php
  Data/
    EventOperationsRoomData.php
    EventOperationsStationData.php
    EventOperationsDeltaData.php
    EventOperationTimelineEntryData.php
  Events/
    EventOperationsStationDeltaBroadcast.php
    EventOperationsTimelineAppendedBroadcast.php
    EventOperationsAlertCreatedBroadcast.php
    EventOperationsHealthChangedBroadcast.php
  Http/
    Controllers/
      EventOperationsController.php
    Requests/
      ListEventOperationsTimelineRequest.php
      ShowEventOperationsRoomRequest.php
      ShowEventOperationsReplayRequest.php
    Resources/
      EventOperationsRoomResource.php
      EventOperationsTimelineResource.php
  Jobs/
    PruneEventOperationEventsJob.php
    RebuildEventOperationsSnapshotJob.php
  Listeners/
    ProjectInboundToOperations.php
    ProjectMediaRunsToOperations.php
    ProjectModerationToOperations.php
    ProjectGalleryToOperations.php
    ProjectWallToOperations.php
    ProjectFeedbackToOperations.php
  Models/
    EventOperationEvent.php
    EventOperationSnapshot.php
  Providers/
    EventOperationsServiceProvider.php
  Support/
    EventOperationsEventMapper.php
    EventOperationsSequenceService.php
    EventOperationsSnapshotBuilder.php
  README.md
  routes/
    api.php
```

## Frontend

```text
apps/web/src/modules/event-operations/
  EventOperationsRoomPage.tsx
  api.ts
  types.ts
  hooks/
    useEventOperationsBoot.ts
    useEventOperationsRealtime.ts
    useControlRoomLifecycle.ts
    useEventOperationsFallback.ts
  stores/
    room-store.ts
    timeline-store.ts
    hud-store.ts
  components/
    OperationsFullscreenEntryOverlay.tsx
    OperationsRoomCanvas.tsx
    OperationsHud.tsx
    OperationsTimelineRail.tsx
    OperationsDetailSheet.tsx
    OperationsAlertStack.tsx
    OperationsStatusPill.tsx
    OperationsLiveAnnouncer.tsx
  engine/
    scene-runtime.ts
    event-translator.ts
    renderer.ts
    assets.ts
    sprites.ts
```

Arquivos transversais que a trilha precisa tocar:

- `apps/web/src/App.tsx`
- `apps/web/src/app/routing/route-preload.ts`
- `apps/web/src/lib/query-client.ts`
- `apps/web/src/shared/auth/permissions.ts`
- `apps/api/config/modules.php`
- `apps/api/routes/channels.php`
- `apps/api/database/seeders/RolesAndPermissionsSeeder.php`
- `docs/modules/module-map.md`

---

## Dependencias e caminho critico

Sequencia critica obrigatoria:

1. congelar contrato de snapshot/delta;
2. abrir rota fullscreen e lifecycle da tela;
3. entregar V0 read-only com sinais atuais;
4. criar projection e snapshot no backend;
5. conectar live realtime no frontend;
6. endurecer fallback, prune e rollout.

Paralelizacao segura:

- backend pode abrir `EventOperations` enquanto frontend fecha a shell fullscreen;
- frontend pode desenvolver `sceneRuntime` com fixtures de snapshot antes do canal real;
- docs e TDD podem andar em paralelo, mas nenhum slice de codigo fecha sem testes red -> green.

Bloqueadores reais:

- contrato de `schema_version`, `snapshot_version`, `event_sequence`, `timeline_cursor` e `server_time`;
- permissao `operations.view`;
- definicao da projection append-only;
- store externa separada do runtime de animacao.

Ponto de atencao:

- `event-translator.ts` fica no caminho critico da UX, porque ele decide se a sala sera legivel ou ruidosa.

---

## Regra de TDD obrigatoria

Neste plano, `TDD` significa:

1. escrever o teste da fatia antes da implementacao;
2. ver o teste falhar pelo motivo certo;
3. implementar o minimo para ficar verde;
4. refatorar sem quebrar o contrato;
5. rodar a regressao do modulo adjacente.

Checklist padrao para qualquer task de codigo:

- teste de contrato ou feature primeiro;
- implementacao minima;
- regressao do modulo afetado;
- type-check frontend quando houver mudanca web;
- atualizar docs/README quando o ownership mudar.

---

## Backlog implementavel por PR

Este backlog transforma as sprints em PRs pequenos o suficiente para review, mas grandes o suficiente para entregar fatias testaveis.

Regra de execucao:

- cada PR comeca com testes red escritos no mesmo PR;
- a implementacao so entra depois de o red falhar pelo motivo esperado;
- cada PR fecha com comando green documentado na descricao do PR;
- PRs de frontend podem usar fixtures quando o backend correspondente ainda nao existir;
- PRs de backend nao devem bloquear o frontend se o contrato compartilhado ja estiver congelado.

### Ordem critica dos PRs

Sequencia recomendada:

```text
PR-00 -> PR-01 -> PR-02 -> PR-03 -> PR-04
                        -> PR-05 -> PR-06 -> PR-07 -> PR-08
PR-09 -> PR-10 -> PR-11 -> PR-12 -> PR-13 -> PR-14
PR-15 -> PR-16
```

Paralelizacao segura:

- `PR-03` e `PR-05` podem andar em paralelo depois de `PR-01`;
- `PR-04` pode andar com fixtures enquanto `PR-05` e `PR-06` fecham backend;
- `PR-09` so deve abrir depois de `PR-06`;
- `PR-11` so deve abrir depois de `PR-09`;
- `PR-16` e opcional para V2, nao para V1.

### `PR-00` Congelar backlog, ADR e fixtures de contrato

Objetivo:

- transformar esta documentacao em fonte de execucao versionada;
- criar fixtures estaveis para desenvolvimento paralelo.

Status em `2026-04-12`:

- concluido;
- red confirmado com falha de import para `@eventovivo/shared-types/event-operations` e fixtures ausentes;
- green confirmado com `2` arquivos e `6` testes passando;
- `npm run type-check` passou.

Slice:

- docs;
- contratos;
- fixtures sem comportamento runtime.

Arquivos em ordem:

```text
docs/architecture/event-realtime-virtual-office-analysis-2026-04-11.md
docs/architecture/event-realtime-virtual-office-execution-plan-2026-04-11.md
packages/shared-types/src/event-operations.ts
packages/shared-types/src/index.ts
apps/web/src/modules/event-operations/__fixtures__/operations-room.fixture.ts
apps/web/src/modules/event-operations/__fixtures__/operations-deltas.fixture.ts
```

Testes red:

```text
apps/web/src/modules/event-operations/event-operations-contract.test.ts
apps/web/src/modules/event-operations/event-operations-fixtures.test.ts
```

Green esperado:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-operations/event-operations-contract.test.ts src/modules/event-operations/event-operations-fixtures.test.ts
```

Aceite:

- tipos expoem `schema_version`, `snapshot_version`, `event_sequence`, `timeline_cursor` e `server_time`;
- fixtures cobrem snapshot saudavel, gargalo, alerta critico, gap de sequencia e modo degradado;
- nenhum codigo de UI ou backend real entra neste PR.

### `PR-01` Permissao, modulo frontend vazio e rota fullscreen

Objetivo:

- abrir a superficie protegida sem dados reais;
- provar que a rota fica fora do `AdminLayout`.

Status em `2026-04-12`:

- concluido;
- red confirmado com `operations.view` ausente, route import ausente e shell/overlay ausentes;
- green backend confirmado com `1` teste e `7` assertions passando;
- green frontend confirmado com `3` arquivos e `4` testes passando;
- regressao frontend de `src/modules/event-operations` confirmou `5` arquivos e `10` testes passando;
- `npm run type-check` passou.

Slice:

- backend minimo de permissao;
- frontend shell;
- rota.

Arquivos em ordem:

```text
apps/api/database/seeders/RolesAndPermissionsSeeder.php
apps/web/src/shared/auth/permissions.ts
apps/web/src/app/routing/route-preload.ts
apps/web/src/App.tsx
apps/web/src/modules/event-operations/EventOperationsRoomPage.tsx
apps/web/src/modules/event-operations/components/OperationsFullscreenEntryOverlay.tsx
```

Testes red:

```text
apps/api/tests/Feature/Auth/OperationsViewPermissionTest.php
apps/web/src/modules/event-operations/EventOperationsRoomPage.test.tsx
apps/web/src/modules/event-operations/event-operations-route.test.ts
apps/web/src/modules/event-operations/components/OperationsFullscreenEntryOverlay.test.tsx
```

Green esperado:

```bash
cd apps/api
php artisan test tests/Feature/Auth/OperationsViewPermissionTest.php

cd apps/web
npx.cmd vitest run src/modules/event-operations/EventOperationsRoomPage.test.tsx src/modules/event-operations/event-operations-route.test.tsx src/modules/event-operations/components/OperationsFullscreenEntryOverlay.test.tsx
```

Aceite:

- `operations.view` existe e nao reutiliza `audit.view`;
- rota `/events/:id/control-room` exige usuario autenticado;
- pagina renderiza sem sidebar;
- overlay exibe CTA `Entrar em modo sala`, tres dicas e fallback quando fullscreen falha.

### `PR-02` Lifecycle fullscreen, wake lock, visibility e reduced motion

Objetivo:

- extrair o comportamento tecnico que ja existe no wall player para a control room;
- transformar fullscreen e degradacao do browser em UX explicita.

Status em `2026-04-12`:

- concluido;
- red confirmado com hooks e componente ausentes;
- green do slice confirmado com `3` arquivos e `6` testes passando;
- regressao do wall player confirmada com `2` arquivos e `5` testes passando;
- suite completa de `src/modules/event-operations` confirmou `8` arquivos e `16` testes passando;
- `npm run type-check` passou.

Slice:

- frontend lifecycle;
- acessibilidade basica;
- sem dados reais.

Arquivos em ordem:

```text
apps/web/src/modules/event-operations/hooks/useControlRoomLifecycle.ts
apps/web/src/modules/event-operations/hooks/useReducedControlRoomMotion.ts
apps/web/src/modules/event-operations/components/OperationsStatusPill.tsx
apps/web/src/modules/event-operations/EventOperationsRoomPage.tsx
```

Testes red:

```text
apps/web/src/modules/event-operations/hooks/useControlRoomLifecycle.test.ts
apps/web/src/modules/event-operations/hooks/useReducedControlRoomMotion.test.ts
apps/web/src/modules/event-operations/components/OperationsStatusPill.test.tsx
```

Green esperado:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-operations/hooks/useControlRoomLifecycle.test.ts src/modules/event-operations/hooks/useReducedControlRoomMotion.test.ts src/modules/event-operations/components/OperationsStatusPill.test.tsx
```

Regressao:

```bash
cd apps/web
npx.cmd vitest run src/modules/wall/player/hooks/usePerformanceMode.test.ts src/modules/wall/player/components/WallPlayerRoot.test.tsx
```

Aceite:

- `requestFullscreen()` so roda sob gesto do usuario;
- `fullscreenchange` e `visibilitychange` atualizam estado da pagina;
- wake lock e reacquire ficam protegidos por feature detection;
- `prefers-reduced-motion` reduz movimento nao essencial sem esconder estado operacional.

### `PR-03` V0 read-only com endpoints existentes

Objetivo:

- entregar primeira tela util sem projection nova;
- combinar dados existentes em um view model unico.

Status em `2026-04-12`:

- concluido;
- red confirmado com hook de boot, HUD, rail e alert stack ausentes, seguido de ajuste do teste legado da pagina para o novo contrato com Query;
- green do slice confirmado com `4` arquivos e `6` testes passando;
- suite completa de `src/modules/event-operations` confirmou `12` arquivos e `22` testes passando;
- regressao frontend adjacente confirmou `2` arquivos e `19` testes passando;
- validacao backend das fontes existentes confirmou `35` testes e `392` assertions passando em `EventJourney`, `MediaPipelineMetrics`, `WallDiagnostics`, `WallLiveSnapshot` e `Audit`;
- `npm run type-check` passou.

Slice:

- frontend boot;
- TanStack Query para boot/polling;
- sem websocket novo.

Arquivos em ordem:

```text
apps/web/src/lib/query-client.ts
apps/web/src/modules/event-operations/api.ts
apps/web/src/modules/event-operations/types.ts
apps/web/src/modules/event-operations/hooks/useEventOperationsBoot.ts
apps/web/src/modules/event-operations/stores/hud-store.ts
apps/web/src/modules/event-operations/components/OperationsHud.tsx
apps/web/src/modules/event-operations/components/OperationsTimelineRail.tsx
apps/web/src/modules/event-operations/components/OperationsAlertStack.tsx
apps/web/src/modules/event-operations/EventOperationsRoomPage.tsx
```

Testes red:

```text
apps/web/src/modules/event-operations/hooks/useEventOperationsBoot.test.tsx
apps/web/src/modules/event-operations/components/OperationsHud.test.tsx
apps/web/src/modules/event-operations/components/OperationsTimelineRail.test.tsx
apps/web/src/modules/event-operations/components/OperationsAlertStack.test.tsx
```

Green esperado:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-operations/hooks/useEventOperationsBoot.test.tsx src/modules/event-operations/components/OperationsHud.test.tsx src/modules/event-operations/components/OperationsTimelineRail.test.tsx src/modules/event-operations/components/OperationsAlertStack.test.tsx
```

Regressao:

```bash
cd apps/web
npx.cmd vitest run src/modules/wall/pages/EventWallManagerPage.test.tsx src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
```

Aceite:

- HUD mostra apenas nome do evento, saude global, conectividade, wall health e fila humana;
- timeline nasce com `role="log"`;
- `status` e `alert` ficam separados;
- Query usa `staleTime` alto e nao vira engine do live.

### `PR-04` Canvas V0, style guide operacional e papeis visuais

Objetivo:

- fazer a V0 parecer equipe trabalhando, nao pipeline com sprites;
- validar linguagem visual antes do backend novo.

Status em `2026-04-12`:

- concluido;
- red confirmado com `scene-runtime`, `visual-roles`, `OperationsRoomCanvas` e sinais de leitura macro/meso ainda ausentes;
- green do slice confirmado com `4` arquivos e `9` testes passando;
- suite completa de `src/modules/event-operations` confirmou `16` arquivos e `31` testes passando;
- regressao frontend adjacente confirmou `2` arquivos e `19` testes passando;
- validacao backend das fontes existentes confirmou `35` testes e `392` assertions passando em `EventJourney`, `MediaPipelineMetrics`, `WallDiagnostics`, `WallLiveSnapshot` e `Audit`;
- `npm run type-check` passou.

Slice:

- frontend canvas;
- fixtures;
- sem websocket novo.

Arquivos em ordem:

```text
apps/web/src/modules/event-operations/engine/scene-runtime.ts
apps/web/src/modules/event-operations/engine/renderer.ts
apps/web/src/modules/event-operations/engine/sprites.ts
apps/web/src/modules/event-operations/engine/assets.ts
apps/web/src/modules/event-operations/engine/visual-roles.ts
apps/web/src/modules/event-operations/components/OperationsRoomCanvas.tsx
apps/web/src/modules/event-operations/EventOperationsRoomPage.tsx
docs/architecture/event-realtime-virtual-office-execution-plan-2026-04-11.md
```

Testes red:

```text
apps/web/src/modules/event-operations/engine/scene-runtime.test.ts
apps/web/src/modules/event-operations/engine/visual-roles.test.ts
apps/web/src/modules/event-operations/components/OperationsRoomCanvas.test.tsx
apps/web/src/modules/event-operations/event-operations-visual-reading.test.tsx
```

Green esperado:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-operations/engine/scene-runtime.test.ts src/modules/event-operations/engine/visual-roles.test.ts src/modules/event-operations/components/OperationsRoomCanvas.test.tsx src/modules/event-operations/event-operations-visual-reading.test.tsx
```

Aceite:

- canvas tem camadas `background`, `stations`, `agents` e `effects`;
- papeis `coordinator`, `dispatcher`, `runner`, `reviewer`, `operator` existem como arquetipos visuais;
- estado calmo existe;
- reduced-motion tem gesto alternativo por estacao;
- teste sintetico valida macro em `5s` e gargalo em `15s` por heuristica de DOM/labels.

### `PR-05` Scaffolding backend EventOperations e migrations

Objetivo:

- criar ownership backend formal da feature;
- persistir eventos append-only e snapshots.

Status em `2026-04-12`:

- concluido;
- red confirmado com provider, models, migrations, registro do modulo e mapa de modulos ainda ausentes;
- green do slice confirmado com `6` testes e `33` assertions passando em `EventOperations` e `operations.view`;
- regressao backend das fontes existentes confirmou `35` testes e `392` assertions passando em `EventJourney`, `MediaPipelineMetrics`, `WallDiagnostics`, `WallLiveSnapshot` e `Audit`;
- suite completa de `src/modules/event-operations` confirmou `16` arquivos e `31` testes passando;
- regressao frontend adjacente confirmou `2` arquivos e `19` testes passando;
- `npm run type-check` passou.

Slice:

- backend estrutural;
- banco;
- docs de modulo.

Arquivos em ordem:

```text
apps/api/app/Modules/EventOperations/Providers/EventOperationsServiceProvider.php
apps/api/app/Modules/EventOperations/README.md
apps/api/app/Modules/EventOperations/routes/api.php
apps/api/config/modules.php
apps/api/database/migrations/*_create_event_operation_events_table.php
apps/api/database/migrations/*_create_event_operation_snapshots_table.php
apps/api/app/Modules/EventOperations/Models/EventOperationEvent.php
apps/api/app/Modules/EventOperations/Models/EventOperationSnapshot.php
docs/modules/module-map.md
```

Testes red:

```text
apps/api/tests/Feature/EventOperations/EventOperationsModuleRegistrationTest.php
apps/api/tests/Unit/EventOperations/EventOperationEventModelTest.php
apps/api/tests/Unit/EventOperations/EventOperationSnapshotModelTest.php
```

Green esperado:

```bash
cd apps/api
php artisan test tests/Feature/EventOperations/EventOperationsModuleRegistrationTest.php tests/Unit/EventOperations/EventOperationEventModelTest.php tests/Unit/EventOperations/EventOperationSnapshotModelTest.php
```

Aceite:

- modulo registrado em `config/modules.php`;
- migrations incluem indices `(event_id, event_sequence)`, `(event_id, occurred_at)`, `(event_id, station_key, occurred_at)` e `(event_id, correlation_key)`;
- models tem casts para JSON e datas;
- nenhum projector entra ainda.

### `PR-06` Sequencia, append action e snapshot builder

Objetivo:

- fechar o contrato monotonico do backend antes de abrir API ou broadcast.

Status em `2026-04-12`:

- concluido;
- red confirmado com servico monotonicamente crescente, append action, snapshot builder e versionamento ainda ausentes;
- green do slice confirmado com `7` testes e `47` assertions passando;
- suite completa de `EventOperations` confirmou `13` testes e `80` assertions passando, incluindo scaffold, models, sequence, append e snapshot versioning;
- regressao backend das fontes existentes confirmou `35` testes e `392` assertions passando em `EventJourney`, `MediaPipelineMetrics`, `WallDiagnostics`, `WallLiveSnapshot` e `Audit`;
- suite completa de `src/modules/event-operations` confirmou `16` arquivos e `31` testes passando;
- regressao frontend adjacente confirmou `2` arquivos e `19` testes passando;
- `npm run type-check` passou.

Slice:

- backend dominio;
- sem HTTP;
- sem websocket.

Arquivos em ordem:

```text
apps/api/app/Modules/EventOperations/Support/EventOperationsSequenceService.php
apps/api/app/Modules/EventOperations/Support/EventOperationsSnapshotBuilder.php
apps/api/app/Modules/EventOperations/Actions/AppendEventOperationEventAction.php
apps/api/app/Modules/EventOperations/Actions/RebuildEventOperationsSnapshotAction.php
apps/api/app/Modules/EventOperations/Data/EventOperationsDeltaData.php
apps/api/app/Modules/EventOperations/Data/EventOperationsStationData.php
apps/api/app/Modules/EventOperations/Data/EventOperationsRoomData.php
```

Testes red:

```text
apps/api/tests/Unit/EventOperations/EventOperationsSequenceServiceTest.php
apps/api/tests/Unit/EventOperations/AppendEventOperationEventActionTest.php
apps/api/tests/Unit/EventOperations/EventOperationsSnapshotBuilderTest.php
apps/api/tests/Feature/EventOperations/EventOperationsSnapshotVersioningTest.php
```

Green esperado:

```bash
cd apps/api
php artisan test tests/Unit/EventOperations/EventOperationsSequenceServiceTest.php tests/Unit/EventOperations/AppendEventOperationEventActionTest.php tests/Unit/EventOperations/EventOperationsSnapshotBuilderTest.php tests/Feature/EventOperations/EventOperationsSnapshotVersioningTest.php
```

Aceite:

- `event_sequence` cresce por evento;
- delta repetido pode ser identificado como idempotente;
- snapshot expoe `schema_version`, `snapshot_version`, `latest_event_sequence`, `timeline_cursor` e `server_time`;
- snapshot nao contem coordenadas, frame ou estado efemero de animacao.

### `PR-07` API room/timeline e autorizacao

Objetivo:

- expor boot e history com contrato versionado;
- garantir autorizacao por `operations.view`.

Status em `2026-04-12`:

- concluido;
- red confirmado com `room` e `timeline` ainda sem rotas/controlador e, por isso, falhando pelo motivo certo antes do slice HTTP;
- green do slice confirmado com `7` testes e `103` assertions passando para `room`, `timeline` e autorizacao;
- suite completa de `EventOperations` confirmou `20` testes e `183` assertions passando, incluindo scaffold, models, sequence, append, snapshot e API;
- regressao backend das fontes existentes confirmou `35` testes e `392` assertions passando em `EventJourney`, `MediaPipelineMetrics`, `WallDiagnostics`, `WallLiveSnapshot` e `Audit`;
- suite completa de `src/modules/event-operations` confirmou `16` arquivos e `31` testes passando;
- regressao frontend adjacente confirmou `2` arquivos e `19` testes passando;
- `npm run type-check` passou.

Slice:

- backend HTTP;
- resources;
- requests;
- policy/canal sem broadcast ainda.

Arquivos em ordem:

```text
apps/api/app/Modules/EventOperations/Http/Requests/ShowEventOperationsRoomRequest.php
apps/api/app/Modules/EventOperations/Http/Requests/ListEventOperationsTimelineRequest.php
apps/api/app/Modules/EventOperations/Actions/BuildEventOperationsRoomAction.php
apps/api/app/Modules/EventOperations/Actions/BuildEventOperationsTimelineAction.php
apps/api/app/Modules/EventOperations/Http/Resources/EventOperationsRoomResource.php
apps/api/app/Modules/EventOperations/Http/Resources/EventOperationsTimelineResource.php
apps/api/app/Modules/EventOperations/Http/Controllers/EventOperationsController.php
apps/api/app/Modules/EventOperations/routes/api.php
```

Testes red:

```text
apps/api/tests/Feature/EventOperations/EventOperationsRoomTest.php
apps/api/tests/Feature/EventOperations/EventOperationsTimelineTest.php
apps/api/tests/Feature/EventOperations/EventOperationsAuthorizationTest.php
```

Green esperado:

```bash
cd apps/api
php artisan test tests/Feature/EventOperations/EventOperationsRoomTest.php tests/Feature/EventOperations/EventOperationsTimelineTest.php tests/Feature/EventOperations/EventOperationsAuthorizationTest.php
```

Regressao:

```bash
cd apps/api
php artisan test tests/Feature/Events/EventJourneyControllerTest.php tests/Feature/Wall/WallLiveSnapshotTest.php
```

Aceite:

- `GET /api/v1/events/{event}/operations/room` retorna snapshot completo;
- `GET /api/v1/events/{event}/operations/timeline` retorna append-only por cursor;
- payload e aditivo e pequeno;
- usuario sem `operations.view` nao acessa.

### `PR-08` Event mapper, projectors e coerencia perceptiva minima

Objetivo:

- comecar a alimentar `EventOperations` sem broadcast;
- garantir que sinais de baixo valor nao nascem como ruido visual.

Slice:

- backend projectors;
- mapper;
- sem websocket.

Arquivos em ordem:

```text
apps/api/app/Modules/EventOperations/Support/EventOperationsEventMapper.php
apps/api/app/Modules/EventOperations/Support/EventOperationsAttentionPriority.php
apps/api/app/Modules/EventOperations/Listeners/ProjectInboundToOperations.php
apps/api/app/Modules/EventOperations/Listeners/ProjectMediaRunsToOperations.php
apps/api/app/Modules/EventOperations/Listeners/ProjectModerationToOperations.php
apps/api/app/Modules/EventOperations/Listeners/ProjectGalleryToOperations.php
apps/api/app/Modules/EventOperations/Listeners/ProjectWallToOperations.php
apps/api/app/Modules/EventOperations/Listeners/ProjectFeedbackToOperations.php
apps/api/app/Modules/EventOperations/Providers/EventOperationsServiceProvider.php
```

Testes red:

```text
apps/api/tests/Unit/EventOperations/EventOperationsEventMapperTest.php
apps/api/tests/Unit/EventOperations/EventOperationsAttentionPriorityTest.php
apps/api/tests/Feature/EventOperations/EventOperationsProjectionFlowTest.php
```

Green esperado:

```bash
cd apps/api
php artisan test tests/Unit/EventOperations/EventOperationsEventMapperTest.php tests/Unit/EventOperations/EventOperationsAttentionPriorityTest.php tests/Feature/EventOperations/EventOperationsProjectionFlowTest.php
```

Regressao:

```bash
cd apps/api
php artisan test tests/Feature/MediaProcessing/MediaPipelineMetricsTest.php tests/Feature/ContentModeration/ContentModerationPipelineTest.php tests/Feature/Wall/WallDiagnosticsTest.php
```

Aceite:

- mapper define `station_key`, `event_key`, `severity`, `urgency`, `render_group`, `animation_hint` e prioridade;
- eventos de baixo valor ficam `timeline coalescivel` ou prioridade baixa;
- projectors escrevem append-only e atualizam snapshot;
- rollback ainda nao pode vazar broadcast porque broadcast nao existe neste PR.

### `PR-09` Canal realtime, broadcasts after-commit e cadencia

Objetivo:

- abrir `event.{eventId}.operations` com diffs pequenos;
- validar after-commit e rollback.

Slice:

- backend realtime;
- canais;
- broadcasts.

Arquivos em ordem:

```text
apps/api/routes/channels.php
apps/api/app/Modules/EventOperations/Events/EventOperationsStationDeltaBroadcast.php
apps/api/app/Modules/EventOperations/Events/EventOperationsTimelineAppendedBroadcast.php
apps/api/app/Modules/EventOperations/Events/EventOperationsAlertCreatedBroadcast.php
apps/api/app/Modules/EventOperations/Events/EventOperationsHealthChangedBroadcast.php
apps/api/app/Modules/EventOperations/Support/EventOperationsBroadcastPriority.php
apps/api/app/Modules/EventOperations/Actions/AppendEventOperationEventAction.php
```

Testes red:

```text
apps/api/tests/Feature/EventOperations/EventOperationsChannelAuthorizationTest.php
apps/api/tests/Feature/EventOperations/EventOperationsBroadcastAfterCommitTest.php
apps/api/tests/Feature/EventOperations/EventOperationsBroadcastPayloadTest.php
```

Green esperado:

```bash
cd apps/api
php artisan test tests/Feature/EventOperations/EventOperationsChannelAuthorizationTest.php tests/Feature/EventOperations/EventOperationsBroadcastAfterCommitTest.php tests/Feature/EventOperations/EventOperationsBroadcastPayloadTest.php
```

Aceite:

- canal privado usa `operations.view`;
- broadcasts usam `broadcastAs()` e `broadcastWith()`;
- delta carrega `schema_version`, `snapshot_version`, `event_sequence`, `timeline_cursor` e `server_time`;
- evento em transacao com rollback nao chega ao broadcast fake;
- `ShouldBroadcastNow` fica restrito a prioridade critica e depois de commit quando depender de persistencia.

### `PR-10` Frontend boot dedicado, stores externas e Query contido

Objetivo:

- substituir V0 heterogenea por boot `room` dedicado;
- manter Query fora do hot path do live.

Slice:

- frontend API;
- stores;
- TanStack Query.

Arquivos em ordem:

```text
apps/web/src/lib/query-client.ts
apps/web/src/modules/event-operations/api.ts
apps/web/src/modules/event-operations/types.ts
apps/web/src/modules/event-operations/hooks/useEventOperationsBoot.ts
apps/web/src/modules/event-operations/stores/room-store.ts
apps/web/src/modules/event-operations/stores/timeline-store.ts
apps/web/src/modules/event-operations/stores/hud-store.ts
apps/web/src/modules/event-operations/EventOperationsRoomPage.tsx
```

Testes red:

```text
apps/web/src/modules/event-operations/hooks/useEventOperationsBoot.test.tsx
apps/web/src/modules/event-operations/stores/room-store.test.ts
apps/web/src/modules/event-operations/stores/timeline-store.test.ts
apps/web/src/modules/event-operations/stores/hud-store.test.ts
```

Green esperado:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-operations/hooks/useEventOperationsBoot.test.tsx src/modules/event-operations/stores/room-store.test.ts src/modules/event-operations/stores/timeline-store.test.ts src/modules/event-operations/stores/hud-store.test.ts
```

Aceite:

- stores expoem snapshots estaveis para `useSyncExternalStore`;
- `setSnapshot` troca objeto inteiro e nao muta estado in-place;
- Query usa `staleTime` alto, `refetchOnWindowFocus: false` e `refetchOnReconnect: false` na tela live;
- `setQueryData` so aparece em patches imutaveis do rail/history.

### `PR-11` Frontend realtime, ordem, idempotencia e resync

Objetivo:

- conectar websocket da sala;
- aplicar contrato inviolavel no cliente.

Slice:

- frontend realtime;
- stores;
- UX de reconnect/resync.

Arquivos em ordem:

```text
apps/web/src/modules/event-operations/hooks/useEventOperationsRealtime.ts
apps/web/src/modules/event-operations/hooks/useEventOperationsFallback.ts
apps/web/src/modules/event-operations/stores/room-store.ts
apps/web/src/modules/event-operations/stores/timeline-store.ts
apps/web/src/modules/event-operations/components/OperationsStatusPill.tsx
apps/web/src/modules/event-operations/EventOperationsRoomPage.tsx
```

Testes red:

```text
apps/web/src/modules/event-operations/hooks/useEventOperationsRealtime.test.tsx
apps/web/src/modules/event-operations/hooks/useEventOperationsFallback.test.tsx
apps/web/src/modules/event-operations/stores/room-store.sequence.test.ts
apps/web/src/modules/event-operations/stores/timeline-store.sequence.test.ts
```

Green esperado:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-operations/hooks/useEventOperationsRealtime.test.tsx src/modules/event-operations/hooks/useEventOperationsFallback.test.tsx src/modules/event-operations/stores/room-store.sequence.test.ts src/modules/event-operations/stores/timeline-store.sequence.test.ts
```

Regressao:

```bash
cd apps/web
npx.cmd vitest run src/lib/realtime.test.ts src/modules/wall/hooks/useWallRealtimeSync.test.tsx src/modules/wall/hooks/useWallPollingFallback.test.tsx
```

Aceite:

- delta repetido ou antigo e descartado;
- gap de `event_sequence` congela incremental;
- `snapshot_version` divergente ativa resync;
- UX mostra `Reconectando...`, `Sincronizando a sala...`, `Sala degradada...` e `Resync concluido`.

### `PR-12` Event-translator como diretor de atencao

Objetivo:

- fazer a cena priorizar atencao, nao apenas mapear eventos para sprites.

Slice:

- frontend engine;
- direction layer;
- backpressure inicial.

Arquivos em ordem:

```text
apps/web/src/modules/event-operations/engine/event-translator.ts
apps/web/src/modules/event-operations/engine/attention-priority.ts
apps/web/src/modules/event-operations/engine/scene-runtime.ts
apps/web/src/modules/event-operations/engine/visual-roles.ts
apps/web/src/modules/event-operations/engine/sprites.ts
apps/web/src/modules/event-operations/engine/renderer.ts
```

Testes red:

```text
apps/web/src/modules/event-operations/engine/event-translator.test.ts
apps/web/src/modules/event-operations/engine/event-translator.attention-priority.test.ts
apps/web/src/modules/event-operations/engine/event-translator.backpressure.test.ts
apps/web/src/modules/event-operations/engine/scene-runtime.test.ts
```

Green esperado:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-operations/engine/event-translator.test.ts src/modules/event-operations/engine/event-translator.attention-priority.test.ts src/modules/event-operations/engine/event-translator.backpressure.test.ts src/modules/event-operations/engine/scene-runtime.test.ts
```

Aceite:

- prioridade minima segue falha urgente, gargalo dominante, progresso visivel, respiracao decorativa;
- micro-bursts viram contadores, heat ou glow;
- nenhum evento bruto gera card visual obrigatorio 1:1;
- estado calmo e reduced-motion sao semanticos, nao apenas "desligar animacao".

### `PR-13` HUD, log acessivel, detalhe lateral e canvas live

Objetivo:

- integrar stores reais, canvas e DOM sem re-render pesado;
- fechar legibilidade da V1.

Slice:

- frontend UI;
- acessibilidade;
- integracao live.

Arquivos em ordem:

```text
apps/web/src/modules/event-operations/components/OperationsHud.tsx
apps/web/src/modules/event-operations/components/OperationsTimelineRail.tsx
apps/web/src/modules/event-operations/components/OperationsDetailSheet.tsx
apps/web/src/modules/event-operations/components/OperationsAlertStack.tsx
apps/web/src/modules/event-operations/components/OperationsLiveAnnouncer.tsx
apps/web/src/modules/event-operations/components/OperationsRoomCanvas.tsx
apps/web/src/modules/event-operations/EventOperationsRoomPage.tsx
```

Testes red:

```text
apps/web/src/modules/event-operations/components/OperationsHud.test.tsx
apps/web/src/modules/event-operations/components/OperationsTimelineRail.test.tsx
apps/web/src/modules/event-operations/components/OperationsDetailSheet.test.tsx
apps/web/src/modules/event-operations/components/OperationsAlertStack.test.tsx
apps/web/src/modules/event-operations/components/OperationsLiveAnnouncer.test.tsx
apps/web/src/modules/event-operations/EventOperationsRoomPage.test.tsx
```

Green esperado:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-operations/components/OperationsHud.test.tsx src/modules/event-operations/components/OperationsTimelineRail.test.tsx src/modules/event-operations/components/OperationsDetailSheet.test.tsx src/modules/event-operations/components/OperationsAlertStack.test.tsx src/modules/event-operations/components/OperationsLiveAnnouncer.test.tsx src/modules/event-operations/EventOperationsRoomPage.test.tsx
```

Aceite:

- rail usa `role="log"` com nome acessivel;
- `status` anuncia updates nao criticos;
- `alert` so aparece para urgencia real;
- HUD nao mostra metricas secundarias;
- detalhe lateral e sob demanda.

### `PR-14` Hardening visual, degradacao bonita e budget perceptivo

Objetivo:

- garantir que a sala continua util sob burst, websocket ruim e reduced motion.

Slice:

- frontend engine;
- fallback;
- UX operacional.

Arquivos em ordem:

```text
apps/web/src/modules/event-operations/engine/perceptual-budget.ts
apps/web/src/modules/event-operations/engine/event-translator.ts
apps/web/src/modules/event-operations/engine/scene-runtime.ts
apps/web/src/modules/event-operations/hooks/useEventOperationsFallback.ts
apps/web/src/modules/event-operations/components/OperationsStatusPill.tsx
apps/web/src/modules/event-operations/components/OperationsRoomCanvas.tsx
```

Testes red:

```text
apps/web/src/modules/event-operations/engine/perceptual-budget.test.ts
apps/web/src/modules/event-operations/engine/event-translator.backpressure.test.ts
apps/web/src/modules/event-operations/event-operations-degraded-mode.test.tsx
apps/web/src/modules/event-operations/event-operations-reduced-motion.test.tsx
```

Green esperado:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-operations/engine/perceptual-budget.test.ts src/modules/event-operations/engine/event-translator.backpressure.test.ts src/modules/event-operations/event-operations-degraded-mode.test.tsx src/modules/event-operations/event-operations-reduced-motion.test.tsx
```

Aceite:

- websocket cai e a sala simplifica sem parecer travada;
- agentes, baloes, alertas e thumbs tem teto perceptivo;
- alertas reais ganham prioridade sobre decoracao;
- degraded mode mostra menos movimento e mais indicadores estaticos.

### `PR-15` Retencao, prune, runbook e rollout interno

Objetivo:

- fechar operabilidade da V1;
- evitar crescimento infinito de `event_operation_events`.

Slice:

- backend jobs;
- docs;
- rollout.

Arquivos em ordem:

```text
apps/api/app/Modules/EventOperations/Jobs/PruneEventOperationEventsJob.php
apps/api/app/Modules/EventOperations/Console/PruneEventOperationEventsCommand.php
apps/api/app/Modules/EventOperations/Providers/EventOperationsServiceProvider.php
apps/api/app/Modules/EventOperations/README.md
docs/modules/module-map.md
docs/flows/event-operations-control-room.md
docs/architecture/event-realtime-virtual-office-execution-plan-2026-04-11.md
```

Testes red:

```text
apps/api/tests/Feature/EventOperations/EventOperationsPruneTest.php
apps/api/tests/Feature/EventOperations/EventOperationsRetentionPolicyTest.php
apps/api/tests/Feature/EventOperations/EventOperationsRunbookContractTest.php
```

Green esperado:

```bash
cd apps/api
php artisan test tests/Feature/EventOperations/EventOperationsPruneTest.php tests/Feature/EventOperations/EventOperationsRetentionPolicyTest.php tests/Feature/EventOperations/EventOperationsRunbookContractTest.php
```

Regressao:

```bash
cd apps/api
php artisan test tests/Feature/EventOperations tests/Unit/EventOperations
```

Aceite:

- prune preserva janela quente para live/history recente;
- snapshots continuam autoritativos;
- runbook cobre perda de websocket, gap de sequence, wall offline, resync manual e prune;
- CTA para control room pode ser habilitado apenas por permissao.

### `PR-16` Replay curto e history forte

Objetivo:

- entregar V2 opcional de replay sem reabrir arquitetura.

Slice:

- backend replay;
- frontend replay/history;
- opcional apos V1.

Arquivos em ordem:

```text
apps/api/app/Modules/EventOperations/Actions/BuildEventOperationsReplayAction.php
apps/api/app/Modules/EventOperations/Http/Requests/ShowEventOperationsReplayRequest.php
apps/api/app/Modules/EventOperations/Http/Resources/EventOperationsReplayResource.php
apps/api/app/Modules/EventOperations/Http/Controllers/EventOperationsController.php
apps/web/src/modules/event-operations/hooks/useEventOperationsReplay.ts
apps/web/src/modules/event-operations/engine/scene-runtime.ts
apps/web/src/modules/event-operations/components/OperationsTimelineRail.tsx
apps/web/src/modules/event-operations/EventOperationsRoomPage.tsx
```

Testes red:

```text
apps/api/tests/Feature/EventOperations/EventOperationsReplayTest.php
apps/api/tests/Feature/EventOperations/EventOperationsTimelineFiltersTest.php
apps/web/src/modules/event-operations/engine/scene-runtime.replay.test.ts
apps/web/src/modules/event-operations/hooks/useEventOperationsReplay.test.tsx
apps/web/src/modules/event-operations/EventOperationsRoomPage.replay.test.tsx
```

Green esperado:

```bash
cd apps/api
php artisan test tests/Feature/EventOperations/EventOperationsReplayTest.php tests/Feature/EventOperations/EventOperationsTimelineFiltersTest.php

cd apps/web
npx.cmd vitest run src/modules/event-operations/engine/scene-runtime.replay.test.ts src/modules/event-operations/hooks/useEventOperationsReplay.test.tsx src/modules/event-operations/EventOperationsRoomPage.replay.test.tsx
```

Aceite:

- replay reconstroi janela curta a partir de `event_operation_events`;
- live e replay nao compartilham estado efemero indevidamente;
- filtros de timeline funcionam por estacao, severidade e midia;
- OffscreenCanvas continua fora, salvo profiling real.

### Regra de descricao de PR

Cada PR deve incluir na descricao:

```text
Objetivo:
Slice:
Arquivos principais:
Testes red criados:
Comandos green:
Regressao rodada:
Riscos e rollback:
Screenshots ou payloads, quando aplicavel:
```

---

## Sprint 0 - Contratos, permissao e shell tecnico

### Objetivo da sprint

Abrir a espinha dorsal da feature sem depender ainda de projection nova:

- contrato congelado;
- permissao criada;
- rota fullscreen registrada;
- shell tecnico e lifecycle da tela prontos;
- style guide operacional da sala fechado antes da V0.

### Tarefas da sprint

### `S0-T1` Congelar contrato shared

Subtarefas:

- criar `packages/shared-types/src/event-operations.ts`;
- definir `station_key`, `event_key`, `severity`, `urgency`, `animation_hint`, `render_group`;
- definir shape minimo de snapshot e delta:
  - `schema_version`
  - `snapshot_version`
  - `timeline_cursor`
  - `event_sequence`
  - `server_time`
- definir nomes broadcastaveis:
  - `operations.station.delta`
  - `operations.timeline.appended`
  - `operations.alert.created`
  - `operations.health.changed`
  - `operations.snapshot.boot`
- definir contrato de aplicacao no cliente:
  - descarte idempotente por `event_sequence`
  - freeze em gap de sequence
  - resync em `snapshot_version` divergente
  - `timeline_cursor` como ancora do rail

### `S0-T2` Criar permissao e trilha de acesso

Subtarefas:

- adicionar `operations.view` em `apps/api/database/seeders/RolesAndPermissionsSeeder.php`;
- decidir papeis iniciais:
  - `super-admin`
  - `platform-admin`
  - `partner-owner`
  - `partner-manager`
  - `event-operator`
- adicionar constante em `apps/web/src/shared/auth/permissions.ts`;
- atualizar mocks/fallbacks de permissao no frontend;
- nao promover `operations` a modulo comercial do sidebar na V1;
- manter a feature gated por permissao, nao por org module.

### `S0-T3` Registrar rota fullscreen no frontend

Subtarefas:

- adicionar `routeImports.eventOperationsRoom` em `apps/web/src/app/routing/route-preload.ts`;
- registrar `/events/:id/control-room` em `apps/web/src/App.tsx` dentro de `ProtectedRoute`, mas fora do `AdminLayout`;
- criar `apps/web/src/modules/event-operations/EventOperationsRoomPage.tsx`;
- deixar a pagina com fundo fullscreen e fallback visual simples.

### `S0-T4` Extrair lifecycle tecnico da tela

Subtarefas:

- criar `useControlRoomLifecycle.ts`;
- copiar e endurecer o padrao do `WallPlayerPage` para:
  - `requestFullscreen()` sob gesto do usuario;
  - hide cursor por inatividade;
  - wake lock;
  - `visibilitychange`;
  - `fullscreenchange`;
- tratar `requestFullscreen()` como promessa que pode falhar;
- expor fallback elegante quando fullscreen for negado, encerrado por `Esc` ou perdido por troca de app;
- expor estado de modo `kiosk` e fullscreen ativo.

### `S0-T5` Congelar regras de UX, acessibilidade e direcao cenica

Subtarefas:

- documentar a hierarquia de leitura macro, meso e micro dentro do modulo;
- congelar a frase-guia da V1 nos arquivos de doc/README da feature;
- transformar macro, meso e micro em layout obrigatorio:
  - macro = saude global dominante
  - meso = estacao dominante quando houver gargalo
  - micro = rail e detalhe sob demanda
- criar style guide operacional da sala com:
  - escala de severidade
  - escala de movimento
  - mapa de gestos por estacao
  - versao reduced-motion de cada gesto
  - orcamento perceptivo por layer
- definir mapa inicial de gestos por estacao;
- definir papeis visuais de trabalho:
  - coordinator
  - dispatcher
  - runner
  - reviewer
  - operator
- definir regra de HUD:
  - topo esquerdo = evento + status global + relogio
  - topo direito = conectividade + wall health + fila humana
  - rodape = rail vivo com densidade controlada
  - lateral = detalhe sob demanda
- definir camada acessivel minima:
  - timeline live com `role="log"`
  - regiao `status` para atualizacoes nao criticas
  - regiao `alert` para urgencias reais
- definir experiencia de entrada em fullscreen:
  - CTA `Entrar em modo sala`
  - overlay curto com `3` dicas
  - pista visivel de saida com `Esc`
  - fallback quando fullscreen falhar
- definir modo `prefers-reduced-motion` como requisito P0.

### TDD obrigatorio da sprint

Backend:

- ajustar/estender testes de auth e permissao para `operations.view`;
- garantir que a permissao chega no estado de acesso do usuario sem colidir com `audit.view`.

Frontend:

- `apps/web/src/modules/event-operations/EventOperationsRoomPage.test.tsx`
- `apps/web/src/modules/event-operations/hooks/useControlRoomLifecycle.test.ts`
- `apps/web/src/modules/event-operations/components/OperationsFullscreenEntryOverlay.test.tsx`
- teste de rota para garantir que a pagina esta fora do `AdminLayout`
- teste de a11y minima para `log`, `status` e `alert`
- teste de reduced-motion para o lifecycle/shell da pagina
- teste de fallback quando fullscreen for negado

### Definicao de pronto da sprint

- contrato shared versionado;
- permissao criada e sem conflito semantico;
- rota protegida existente;
- shell fullscreen renderiza;
- lifecycle tecnico coberto por teste;
- style guide operacional aprovado;
- principios de UX e acessibilidade congelados antes do codigo visual mais caro.

---

## Sprint 1 - V0 read-only com dados existentes

### Objetivo da sprint

Entregar a primeira demo interna util da sala de operacoes usando apenas endpoints ja existentes, sem projection propria ainda.

### Tarefas da sprint

### `S1-T1` Compor boot read-only no frontend

Subtarefas:

- criar `api.ts` e `types.ts` do modulo;
- criar `queryKeys.eventOperations` em `apps/web/src/lib/query-client.ts`;
- montar `useEventOperationsBoot.ts` combinando:
  - detalhe do evento
  - `media/pipeline-metrics`
  - timeline do evento
  - `wall/live-snapshot`
  - `wall/diagnostics`
  - feed operacional minimo de moderacao, se necessario
- normalizar payload em view model unico para a cena.

### `S1-T2` Entregar HUD e layout base

Subtarefas:

- criar `OperationsHud.tsx`;
- criar `OperationsTimelineRail.tsx`;
- criar `OperationsDetailSheet.tsx`;
- criar `OperationsAlertStack.tsx`;
- exibir:
  - nome do evento
  - saude global
  - estado de conexao
  - wall health
  - fila humana
  - alertas resumidos
- garantir hierarquia de leitura:
  - macro = status global
  - meso = gargalo por estacao com uma dominante clara
  - micro = detalhe e timeline
- controlar densidade do rail inferior para nao competir com a cena;
- adicionar `status` e `alert` region no shell HUD;
- garantir que o rail live nasce como `role="log"` e nao apenas como faixa decorativa.

### `S1-T3` Entregar canvas V0 com estacoes fixas

Subtarefas:

- criar `OperationsRoomCanvas.tsx`;
- desenhar 5 a 7 estacoes fixas:
  - intake
  - download
  - variants
  - safety
  - human_review
  - gallery
  - wall
- usar counters e estados agregados, sem animacao complexa ainda;
- manter HUD e timeline fora do canvas;
- dar a cada estacao um gesto visual inicial, mesmo simples;
- introduzir papeis visuais minimos de trabalho, mesmo com assets simples;
- validar que a V0 continua legivel mesmo com burst sintetico de dados;
- validar tambem que a cena continua boa em estado de calma operacional.

### `S1-T4` Criar polling leve da V0

Subtarefas:

- polling de boot read-only entre `10s` e `15s`;
- status degradado no HUD quando houver erro;
- nenhum websocket dedicado nesta sprint.

### TDD obrigatorio da sprint

Frontend:

- `useEventOperationsBoot.test.tsx`
- `OperationsHud.test.tsx`
- `OperationsTimelineRail.test.tsx`
- `OperationsRoomCanvas.test.tsx`
- `EventOperationsRoomPage.test.tsx` cobrindo loading, erro e render read-only
- teste de hierarquia visual minima: status global visivel antes do detalhe
- teste de reduced-motion para a V0
- teste humano assistido: leitura da saude global em ate `5s`
- teste sintetico de burst para verificar se a tela continua compreensivel

Regressao recomendada:

- `src/modules/wall/pages/EventWallManagerPage.test.tsx`
- `src/modules/events/event-media-flow-builder-architecture-characterization.test.ts`

### Definicao de pronto da sprint

- a rota `/events/:id/control-room` entrega uma V0 legivel;
- a tela funciona sem projection nova;
- fullscreen e lifecycle continuam estaveis;
- a demo interna ja explica o fluxo do evento de ponta a ponta;
- a V0 ja parece uma equipe trabalhando, e nao apenas um pipeline com sprites;
- a equipe consegue responder em segundos se a operacao esta saudavel, em atencao ou em risco.

---

## Sprint 2 - Backend EventOperations, projection e timeline

### Objetivo da sprint

Criar o backend de verdade da feature:

- modulo `EventOperations`;
- tabela append-only;
- snapshot por evento;
- endpoints `room` e `timeline`;
- canal privado;
- projectors e broadcasts coerentes.

### Tarefas da sprint

### `S2-T1` Scaffolding do modulo backend

Subtarefas:

- criar pasta `apps/api/app/Modules/EventOperations`;
- registrar provider em `apps/api/config/modules.php`;
- criar `README.md` do modulo;
- registrar no `docs/modules/module-map.md`;
- abrir `routes/api.php` do modulo.

### `S2-T2` Criar persistencia

Subtarefas:

- migration `event_operation_events`;
- migration `event_operation_snapshots`;
- campos minimos de `event_operation_events`:
  - `event_id`
  - `event_media_id`
  - `inbound_message_id`
  - `station_key`
  - `event_key`
  - `severity`
  - `urgency`
  - `title`
  - `summary`
  - `payload_json`
  - `animation_hint`
  - `station_load`
  - `queue_depth`
  - `render_group`
  - `dedupe_window_key`
  - `correlation_key`
  - `event_sequence`
  - `occurred_at`
- campos minimos de `event_operation_snapshots`:
  - `event_id`
  - `schema_version`
  - `snapshot_version`
  - `latest_event_sequence`
  - `timeline_cursor`
  - `snapshot_json`
  - `updated_at`
- criar indices:
  - `(event_id, event_sequence)` unico
  - `(event_id, occurred_at desc)`
  - `(event_id, station_key, occurred_at desc)`
  - `(event_id, correlation_key)`
- garantir servico monotonicamente crescente para `event_sequence`, sem depender de heuristica de timestamp;
- documentar explicitamente a relacao entre `latest_event_sequence` do snapshot e os deltas de live.

### `S2-T3` Construir actions, resources e endpoints

Subtarefas:

- implementar `BuildEventOperationsRoomAction`;
- implementar `BuildEventOperationsTimelineAction`;
- implementar `AppendEventOperationEventAction`;
- implementar `RebuildEventOperationsSnapshotAction`;
- expor:
  - `GET /api/v1/events/{event}/operations/room`
  - `GET /api/v1/events/{event}/operations/timeline`
- deixar `replay` fora desta sprint, mas reservar a action.

### `S2-T4` Projetar sinais dos modulos existentes

Subtarefas:

- criar `EventOperationsEventMapper`;
- mapear eventos e writes relevantes de:
  - `InboundMedia`
  - `MediaProcessing`
  - `ContentModeration`
  - `Gallery`
  - `Wall`
  - feedbacks WhatsApp/Telegram
- criar listeners:
  - `ProjectInboundToOperations`
  - `ProjectMediaRunsToOperations`
  - `ProjectModerationToOperations`
  - `ProjectGalleryToOperations`
  - `ProjectWallToOperations`
  - `ProjectFeedbackToOperations`
- listeners escrevem append-only;
- snapshot e recalculado;
- delta broadcastavel e montado depois;
- o mapper ja precisa classificar prioridade de atencao e prioridade de broadcast para evitar ruido visual por default.

### `S2-T5` Abrir canal privado e broadcasts

Subtarefas:

- registrar `event.{eventId}.operations` em `apps/api/routes/channels.php`;
- autorizar com `operations.view`;
- criar eventos broadcastaveis do modulo;
- usar `broadcastAs()`, `broadcastWith()` e payload pequeno;
- usar `ShouldDispatchAfterCommit` quando o delta depender do estado persistido;
- validar se a conexao operacional usara a conexao `redis` atual com `after_commit` habilitado;
- garantir que a trilha nao emite live se a transacao que gerou o evento falhar;
- separar tres classes de cadencia:
  - critico imediato
  - operacional normal
  - timeline coalescivel

### TDD obrigatorio da sprint

Backend:

- `apps/api/tests/Feature/EventOperations/EventOperationsRoomTest.php`
- `apps/api/tests/Feature/EventOperations/EventOperationsTimelineTest.php`
- `apps/api/tests/Feature/EventOperations/EventOperationsChannelAuthorizationTest.php`
- `apps/api/tests/Feature/EventOperations/EventOperationsProjectionFlowTest.php`
- `apps/api/tests/Feature/EventOperations/EventOperationsBroadcastAfterCommitTest.php`
- `apps/api/tests/Unit/EventOperations/AppendEventOperationEventActionTest.php`
- `apps/api/tests/Unit/EventOperations/EventOperationsSnapshotBuilderTest.php`
- `apps/api/tests/Unit/EventOperations/EventOperationsEventMapperTest.php`
- `apps/api/tests/Unit/EventOperations/EventOperationsSequenceServiceTest.php`
- teste de coerencia perceptiva minima: eventos de baixo valor nao devem nascer com prioridade visual alta

Regressao recomendada:

- `tests/Feature/Wall/WallLiveSnapshotTest.php`
- `tests/Feature/Wall/WallDiagnosticsTest.php`
- `tests/Feature/MediaProcessing/MediaPipelineMetricsTest.php`

### Definicao de pronto da sprint

- modulo backend registrado;
- projection append-only persistida;
- endpoint `room` devolve snapshot versionado;
- endpoint `timeline` devolve rail append-only;
- canal privado autoriza corretamente;
- projectors escrevem e broadcastam sem mentir para a UI;
- a camada de mapper ja diferencia o que e urgente, normal e coalescivel;
- rollback transacional nao vaza evento para o cliente.

---

## Sprint 3 - Frontend live, store externa e canvas em camadas

### Objetivo da sprint

Trocar a V0 agregada pelo fluxo V1 real:

- boot dedicado por `room`;
- timeline dedicada;
- websocket da sala;
- stores externas;
- runtime visual separado;
- cena pixel-art operando com estado real.

### Tarefas da sprint

### `S3-T1` Fechar contrato de consumo no frontend

Subtarefas:

- importar tipos de `packages/shared-types`;
- adicionar `queryKeys.eventOperations.room` e `queryKeys.eventOperations.timeline`;
- implementar `useEventOperationsBoot.ts`;
- implementar `useEventOperationsRealtime.ts`;
- garantir que Query so faz boot e fallback.

### `S3-T2` Criar stores externas

Subtarefas:

- implementar `room-store.ts`;
- implementar `timeline-store.ts`;
- implementar `hud-store.ts`;
- consumir tudo via `useSyncExternalStore`;
- suportar:
  - snapshot bootstrap
  - append de timeline
  - descarte idempotente
  - resync em `snapshot_version` diferente
  - freeze em gap de `event_sequence`
  - troca explicita entre `connected`, `resyncing` e `degraded`

### `S3-T3` Criar runtime de cena e camada de direcao cenica

Subtarefas:

- implementar `scene-runtime.ts`;
- implementar `event-translator.ts`;
- implementar `renderer.ts`;
- criar camadas:
  - background
  - stations
  - agents
  - effects
- traduzir eventos operacionais em hints visuais:
  - fila
  - lampada
  - balao
  - pulse
  - glow
- tratar `event-translator.ts` como camada de direcao cenica:
  - decide o que merece gesto visual
  - responde continuamente qual e a coisa mais importante para olhar agora
  - sintetiza muitos eventos em poucos sinais
  - protege a cena de ruido literal 1:1
- implementar orquestracao de atencao com ordem minima:
  - falha urgente
  - gargalo dominante
  - progresso visivel
  - respiracao decorativa
- introduzir papeis visuais de trabalho na cenografia:
  - coordinator
  - dispatcher
  - runner
  - reviewer
  - operator
- implementar mapa inicial de gestos por estacao:
  - recepcao
  - download
  - variantes
  - safety
  - moderacao humana
  - galeria
  - wall
  - feedback
- definir versao reduced-motion por gesto e por papel;
- definir estado de calma operacional com poucos movimentos e sinais de "tudo sob controle".

### `S3-T4` Integrar HUD, timeline e detalhe lateral ao estado real

Subtarefas:

- ligar `OperationsHud` ao `roomStore`;
- ligar `OperationsTimelineRail` ao `timelineStore`;
- ligar `OperationsDetailSheet` a selecao da estacao;
- exibir estado degradado quando realtime cair;
- manter DOM e canvas desacoplados;
- promover uma unica estacao dominante quando houver gargalo principal;
- usar o rail live como `role="log"` com append ordenado no fim;
- garantir regiao `status` para updates nao criticos;
- garantir `alert` apenas para urgencias reais;
- respeitar `prefers-reduced-motion` sem esconder backlog ou alerta;
- comunicar reconnect, resync e degradado com texto claro na UI.

### TDD obrigatorio da sprint

Frontend:

- `apps/web/src/modules/event-operations/hooks/useEventOperationsRealtime.test.tsx`
- `apps/web/src/modules/event-operations/stores/room-store.test.ts`
- `apps/web/src/modules/event-operations/stores/timeline-store.test.ts`
- `apps/web/src/modules/event-operations/engine/event-translator.test.ts`
- `apps/web/src/modules/event-operations/engine/event-translator.attention-priority.test.ts`
- `apps/web/src/modules/event-operations/engine/scene-runtime.test.ts`
- `apps/web/src/modules/event-operations/components/OperationsHud.test.tsx`
- `apps/web/src/modules/event-operations/components/OperationsDetailSheet.test.tsx`
- `apps/web/src/modules/event-operations/EventOperationsRoomPage.test.tsx`
- `apps/web/src/modules/event-operations/components/OperationsAlertStack.test.tsx`
- teste de reduced-motion para o runtime/cena
- teste de `role=\"log\"` e append ordenado no rail live
- teste de resync por `snapshot_version` divergente

Regressao recomendada:

- `src/modules/wall/hooks/useWallRealtimeSync.test.tsx`
- `src/modules/wall/player/runtime-profile.test.ts`

### Definicao de pronto da sprint

- a sala abre com `room` dedicado;
- o live chega por canal proprio;
- stores e runtime estao separados;
- a timeline e append-only e coerente;
- o canvas responde a estado real sem depender de React re-render completo;
- cada estacao ja possui gesto visual minimo e reconhecivel;
- os papeis de trabalho ja fazem a sala parecer equipe, e nao parque de maquinas;
- `event-translator.ts` ja esta operando como diretor de atencao da cena.

---

## Sprint 4 - Hardening operacional, fallback e rollout

### Objetivo da sprint

Transformar a V1 tecnica em V1 operavel:

- fallback confiavel;
- retencao e prune;
- backpressure visual;
- rollout seguro;
- docs e runbook.

### Tarefas da sprint

### `S4-T1` Implementar fallback e reconnect

Subtarefas:

- criar `useEventOperationsFallback.ts`;
- polling leve de `room` em modo degradado;
- resync manual e automatico;
- HUD explicito para:
  - `connecting`
  - `connected`
  - `disconnected`
  - `offline`
- congelar aplicacao incremental quando houver gap de sequencia;
- simplificar a cena no degradado:
  - menos movimento
  - mais indicadores estaticos
  - mais foco em saude global, alertas e wall health
  - mensagem clara de que o live caiu sem parecer travamento

### `S4-T2` Endurecer backpressure visual

Subtarefas:

- coalescer micro-bursts no `event-translator`;
- limitar efeitos concorrentes por estacao;
- degradar de card individual para contadores, glow e heat;
- proteger frame budget da cena;
- definir tetos por layer para `agents`, `stations` e `effects`;
- definir budget perceptivo:
  - quantos agentes podem se mover ao mesmo tempo
  - quantos baloes podem aparecer
  - quantos alertas visuais cabem
  - quantas thumbs recentes a galeria mostra antes de resumir
- garantir que alertas reais sempre tenham prioridade sobre decoracao.

### `S4-T3` Implementar retencao e prune

Subtarefas:

- criar job/comando de prune para `event_operation_events`;
- manter janela quente completa para live e replay curto;
- resumir ou remover eventos antigos por politica;
- manter snapshot materializado autoritativo;
- documentar a politica no `README` do modulo.

### `S4-T4` Fechar rollout e documentacao

Subtarefas:

- adicionar CTA/control room no detalhe do evento;
- atualizar `docs/flows/` se a operacao virar fluxo oficial;
- atualizar `docs/modules/module-map.md`;
- escrever runbook:
  - perda de websocket
  - backlog travado
  - wall offline
  - resync manual
- registrar como medir:
  - compreensao em `5s`
  - gargalo em `15s`
  - reduced-motion
  - burst visual
- preparar feature flag ou rollout controlado por permissao para piloto interno.

### TDD obrigatorio da sprint

Backend:

- `apps/api/tests/Feature/EventOperations/EventOperationsPruneTest.php`
- `apps/api/tests/Feature/EventOperations/EventOperationsRetentionPolicyTest.php`

Frontend:

- `apps/web/src/modules/event-operations/hooks/useEventOperationsFallback.test.tsx`
- `apps/web/src/modules/event-operations/engine/event-translator.backpressure.test.ts`
- `apps/web/src/modules/event-operations/components/OperationsStatusPill.test.tsx`
- `apps/web/src/modules/event-operations/EventOperationsRoomPage.test.tsx` cobrindo modo degradado
- teste de prioridade visual para alerta sobre efeito decorativo
- teste de "degradacao bonita": websocket cai, a sala simplifica e continua util

### Definicao de pronto da sprint

- live continua util com websocket ruim;
- a cena nao explode sob throughput alto;
- prune e retention existem;
- runbook minimo foi escrito;
- a feature pode ser habilitada para time interno sem depender do autor da sprint;
- existe criterio objetivo para validar leitura e legibilidade sob estresse;
- o modo degradado comunica perda de live sem parecer bug.

---

## Sprint 5 - Replay, history forte e polimento visual

### Objetivo da sprint

Fechar a trilha de replay e elevar a experiencia de produto sem reabrir arquitetura.

### Tarefas da sprint

### `S5-T1` Implementar replay

Subtarefas:

- expor `GET /api/v1/events/{event}/operations/replay`;
- aceitar janela temporal e velocidade;
- reconstruir a cena a partir de `event_operation_events`;
- manter `roomStore` e `sceneRuntime` separados tambem em replay.

### `S5-T2` Fortalecer history e filtros

Subtarefas:

- filtros por estacao, severidade e midia;
- cursor temporal consistente;
- clique no rail abre detalhe do incidente ou da midia;
- priorizar explicabilidade operacional antes de cenografia adicional.

### `S5-T3` Polir assets e temas

Subtarefas:

- atlas inicial de pixel art;
- temas por tipo de evento;
- micro-interacoes melhores por estacao;
- so avancar para tema premium se a semantica visual ja estiver inequivoca;
- `OffscreenCanvas` apenas se profiling justificar.

### TDD obrigatorio da sprint

Backend:

- `apps/api/tests/Feature/EventOperations/EventOperationsReplayTest.php`
- `apps/api/tests/Feature/EventOperations/EventOperationsTimelineFiltersTest.php`

Frontend:

- `apps/web/src/modules/event-operations/engine/scene-runtime.replay.test.ts`
- `apps/web/src/modules/event-operations/components/OperationsTimelineRail.test.tsx` cobrindo filtros
- `apps/web/src/modules/event-operations/EventOperationsRoomPage.test.tsx` cobrindo troca `live/replay`

### Definicao de pronto da sprint

- replay reproduz janelas curtas com contrato consistente;
- history deixa de ser apenas rail e vira instrumento de diagnostico;
- polimento visual nao compromete legibilidade nem frame budget;
- a semantica visual continua mais forte que o tema decorativo;
- se o tema competir com orientacao operacional, o tema volta para backlog.

---

## Bateria TDD consolidada

## Backend

Suite nova esperada:

```text
apps/api/tests/Feature/EventOperations/
  EventOperationsRoomTest.php
  EventOperationsTimelineTest.php
  EventOperationsReplayTest.php
  EventOperationsChannelAuthorizationTest.php
  EventOperationsProjectionFlowTest.php
  EventOperationsBroadcastAfterCommitTest.php
  EventOperationsPruneTest.php
  EventOperationsRetentionPolicyTest.php

apps/api/tests/Unit/EventOperations/
  AppendEventOperationEventActionTest.php
  EventOperationsAttentionPriorityTest.php
  EventOperationsSnapshotBuilderTest.php
  EventOperationsEventMapperTest.php
  EventOperationsSequenceServiceTest.php
```

## Frontend

Suite nova esperada:

```text
apps/web/src/modules/event-operations/
  EventOperationsRoomPage.test.tsx
  hooks/
    useControlRoomLifecycle.test.ts
    useEventOperationsBoot.test.tsx
    useEventOperationsRealtime.test.tsx
    useEventOperationsFallback.test.tsx
  stores/
    room-store.test.ts
    timeline-store.test.ts
  components/
    OperationsFullscreenEntryOverlay.test.tsx
    OperationsHud.test.tsx
    OperationsTimelineRail.test.tsx
    OperationsDetailSheet.test.tsx
    OperationsAlertStack.test.tsx
    OperationsStatusPill.test.tsx
  engine/
    event-translator.test.ts
    event-translator.attention-priority.test.ts
    event-translator.backpressure.test.ts
    scene-runtime.test.ts
    scene-runtime.replay.test.ts
```

## Comandos de regressao que devem acompanhar a trilha

Backend:

```bash
cd apps/api
php artisan test tests/Feature/EventOperations tests/Unit/EventOperations
php artisan test tests/Feature/Wall/WallLiveSnapshotTest.php tests/Feature/Wall/WallDiagnosticsTest.php tests/Feature/MediaProcessing/MediaPipelineMetricsTest.php
```

Frontend:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-operations
npx.cmd vitest run src/modules/wall/hooks/useWallRealtimeSync.test.tsx src/modules/wall/player/runtime-profile.test.ts src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
npx.cmd tsc --noEmit
```

---

## Ordem recomendada de execucao dentro da sprint

Para qualquer sprint desta trilha:

1. escrever testes de contrato da fatia;
2. criar tipos/shared e fixtures primeiro;
3. implementar backend ou store minima;
4. implementar UI/renderer por cima do contrato ja verde;
5. rodar regressao dos modulos adjacentes;
6. atualizar docs e README.

Regra pratica:

- nunca implementar sprite, efeito ou animacao antes de o contrato operacional estar travado;
- nunca usar o canvas para mascarar incoerencia de snapshot/delta;
- nunca usar Query como fonte de verdade do live;
- nunca deixar direcao cenica competir com legibilidade;
- nunca tratar alertas urgentes como apenas mais um efeito da camada visual.

---

## Checklist de homologacao da V1

- [ ] rota `/events/:id/control-room` protegida e fora do `AdminLayout`
- [ ] permissao `operations.view` funcionando
- [ ] boot `room` versionado
- [ ] `timeline` append-only coerente
- [ ] canal `event.{eventId}.operations` autenticado
- [ ] `roomStore` separado de `sceneRuntime`
- [ ] direcao cenica implementada no `event-translator` sem microanimacao literal por evento bruto
- [ ] papeis de trabalho visuais implementados de forma legivel
- [ ] fullscreen, wake lock e lifecycle do browser tratados
- [ ] entrada em fullscreen tem CTA, overlay curto e fallback honesto
- [ ] `prefers-reduced-motion` respeitado
- [ ] fallback degradado sem quebrar a leitura
- [ ] prune inicial configurado
- [ ] HUD mostra estado de conexao e degradacao
- [ ] HUD respeita hierarquia macro, meso e micro
- [ ] rail live usa `role="log"` e `status`/`alert` estao acessiveis
- [ ] backpressure visual protege a cena em bursts
- [ ] budget perceptivo evita ruido visual concorrente
- [ ] docs do modulo e runbook atualizados
- [ ] operador entende a saude global em ate `5s`
- [ ] operador identifica o gargalo dominante em ate `15s`
- [ ] bateria TDD verde

---

## Definicao de pronto da V1

A V1 pode ser considerada pronta quando:

1. um operador consegue abrir a sala de um evento ativo e entender o fluxo sem treinamento tecnico profundo;
2. em ate `5s`, esse operador entende se a operacao esta saudavel, em atencao ou em risco;
3. em ate `15s`, ele identifica a estacao que merece atencao;
4. o estado live continua coerente apos reconnect, tab oculta e resync;
5. a sala respeita `prefers-reduced-motion` e continua legivel;
6. a camada de direcao cenica nao compete com o estado operacional;
7. reconnect, resync e degradado aparecem como estados compreensiveis para o operador;
8. a sala nao depende de polling agressivo para parecer viva;
9. um backlog real de moderacao ou falha do wall aparece como sinal legivel e acionavel;
10. a equipe consegue manter a feature sem depender de conhecimento implicito fora do repo.

Em uma frase:

**a V1 fecha quando a control room deixa de ser uma demo bonita e passa a ser uma superficie operacional confiavel, versionada e testada.**
