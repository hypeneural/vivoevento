# Wall Puzzle Theme Execution Plan - 2026-04-09

## Objetivo

Transformar o diagnostico de `wall-puzzle-theme-analysis-2026-04-09.md` em um plano de execucao implementavel para o tema `Quebra Cabeca`, com foco em:

- performance real de player;
- cache para reduzir conexoes sem matar o realtime;
- animacao forte sem custo excessivo de re-render;
- preview e manager previsiveis;
- base reaproveitavel para futuros temas do wall.

Este plano responde 10 perguntas:

1. o que foi validado nesta rodada por codigo, testes e documentacao oficial;
2. o que ja existe no wall e pode ser reaproveitado;
3. o que entra de verdade em `P0`;
4. o que entra em `P1` sem confundir fundacao com refinamento;
5. quais arquivos devem ser alterados em cada fase;
6. como organizar o codigo para nao transformar `puzzle` num caso especial;
7. quais testes precisam existir antes, durante e depois da implementacao;
8. qual e a ordem de entrega por PR e por sprint;
9. como introduzir o tema em producao sem susto;
10. qual e a definicao de pronto antes de liberar o layout.

## Referencias primarias

- `docs/architecture/wall-puzzle-theme-analysis-2026-04-09.md`
- `docs/architecture/wall-puzzle-video-and-theme-extensibility-analysis-2026-04-09.md`
- `docs/architecture/wall-puzzle-video-policy-and-theme-capabilities-2026-04-10.md`
- `docs/architecture/wall-video-playback-execution-plan-2026-04-08.md`
- `apps/web/src/modules/wall/player/wall-theme-architecture-characterization.test.ts`
- `apps/web/src/modules/wall/player/components/LayoutRenderer.tsx`
- `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`
- `apps/web/src/modules/wall/player/hooks/useWallPlayer.ts`
- `apps/web/src/modules/wall/player/hooks/useWallRealtime.ts`
- `apps/web/src/modules/wall/player/engine/preload.ts`
- `apps/web/src/modules/wall/player/engine/cache.ts`
- `apps/web/src/modules/wall/player/engine/layoutStrategy.ts`
- `apps/web/src/modules/wall/player/hooks/useMultiSlot.ts`
- `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.tsx`
- `apps/web/src/modules/wall/pages/EventWallManagerPage.tsx`
- `apps/web/src/modules/wall/wall-query-options.ts`
- `apps/web/src/modules/wall/manager-config.ts`
- `apps/web/src/modules/wall/wall-settings.ts`
- `packages/shared-types/src/wall.ts`
- `apps/api/app/Modules/Wall/Enums/WallLayout.php`
- `apps/api/app/Modules/Wall/Http/Controllers/EventWallController.php`
- `apps/api/app/Modules/Wall/Http/Requests/UpdateWallSettingsRequest.php`
- `apps/api/app/Modules/Wall/Models/EventWallSetting.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`
- `apps/api/tests/Feature/Wall/PublicWallBootTest.php`
- `apps/api/tests/Feature/Wall/WallLiveSnapshotTest.php`
- `apps/api/tests/Feature/Wall/WallInsightsTest.php`
- `apps/api/tests/Feature/Wall/WallDiagnosticsTest.php`
- `apps/api/tests/Feature/Wall/WallOptionsCharacterizationTest.php`
- `apps/api/tests/Unit/Modules/Wall/WallEligibilityServiceTest.php`

## Validacao executada nesta rodada

### Frontend

- `cd apps/web && npm run test -- src/modules/wall/player/wall-theme-architecture-characterization.test.ts src/modules/wall/player/engine/layoutStrategy.test.ts src/modules/wall/player/engine/motion.test.ts src/modules/wall/player/engine/preload.test.ts src/modules/wall/player/engine/cache.test.ts src/modules/wall/player/hooks/useMultiSlot.test.ts src/modules/wall/components/manager/stage/WallPreviewCanvas.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx src/modules/wall/player/components/WallPlayerRoot.test.tsx`
  - `9 arquivos`
  - `67 testes`
  - `PASS`
- `cd apps/web && npm run test -- src/modules/wall/player/wall-theme-architecture-characterization.test.ts`
  - `1 arquivo`
  - `5 testes`
  - `PASS`

### Backend

- `cd apps/api && php artisan test tests/Feature/Wall/PublicWallBootTest.php tests/Feature/Wall/WallLiveSnapshotTest.php tests/Feature/Wall/WallInsightsTest.php tests/Feature/Wall/WallDiagnosticsTest.php tests/Unit/Modules/Wall/WallEligibilityServiceTest.php tests/Unit/Modules/Wall/WallAcceptedOrientationTest.php`
  - `45 testes`
  - `331 assertions`
  - `PASS`

### Validacao final antes da execucao do PR 1 - 2026-04-10

Frontend:

- `cd apps/web && npm run test -- src/modules/wall/player/wall-theme-architecture-characterization.test.ts src/modules/wall/player/components/LayoutRenderer.video-multi-layout.test.tsx src/modules/wall/player/components/WallVideoSurface.test.tsx src/modules/wall/player/components/MediaSurface.test.tsx src/modules/wall/player/engine/layoutStrategy.test.ts src/modules/wall/player/engine/preload.test.ts src/modules/wall/components/manager/stage/WallPreviewCanvas.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx`
  - `8 arquivos`
  - `63 testes`
  - `PASS`
- `cd apps/web && npm run test -- src/modules/wall/player/components/LayoutRenderer.video-multi-layout.test.tsx src/modules/wall/player/wall-theme-architecture-characterization.test.ts`
  - `2 arquivos`
  - `9 testes`
  - `PASS`

Backend:

- `cd apps/api && php artisan test tests/Feature/Wall/WallOptionsCharacterizationTest.php tests/Feature/Wall/PublicWallBootTest.php tests/Unit/Modules/Wall/WallVideoAdmissionServiceTest.php tests/Feature/Wall/WallLiveSnapshotTest.php tests/Feature/Wall/WallDiagnosticsTest.php`
  - `24 testes`
  - `274 assertions`
  - `PASS`

O que esta bateria pre-PR1 travou:

- `video_multi_layout_policy = all` ainda monta `3` `<video>` simultaneos crus em `grid`;
- `video_multi_layout_policy = disallow` cai para uma unica surface controlada por `WallVideoSurface`;
- `/wall/options` ainda nao expoe `puzzle` nem capability metadata por layout;
- `PublicWallBoot` continua entregando admission metadata para video;
- diagnostics e live snapshot continuam verdes;
- o execution plan agora referencia a policy de video e assume `video no puzzle = fallback single-item`.

### Validacao do PR 1 - contrato e gate - 2026-04-10

Backend:

- `cd apps/api && php artisan test tests/Feature/Wall/WallOptionsPuzzleLayoutTest.php tests/Feature/Wall/WallSettingsThemeConfigTest.php tests/Feature/Wall/WallSettingsFeatureFlagTest.php tests/Feature/Wall/WallOptionsCharacterizationTest.php`
  - `7 testes`
  - `72 assertions`
  - `PASS`
- `cd apps/api && php artisan test tests/Feature/Wall tests/Unit/Modules/Wall`
  - `87 testes`
  - `561 assertions`
  - `PASS`

Frontend:

- `cd apps/web && npm run test -- src/modules/wall/wall-settings.test.ts src/modules/wall/player/wall-theme-architecture-characterization.test.ts`
  - `2 arquivos`
  - `10 testes`
  - `PASS`
- `cd apps/web && npm run test -- src/modules/wall`
  - `45 arquivos`
  - `242 testes`
  - `PASS`
- `cd apps/web && npm run type-check`
  - `PASS`

O que esta bateria do PR 1 travou:

- `/wall/options` devolve `capabilities` e `defaults` para todos os layouts;
- `puzzle` fica escondido quando `wall.layouts.puzzle.enabled=false`;
- `puzzle` aparece quando o gate permite, com `max_simultaneous_videos=0` e `fallback_video_layout=cinematic`;
- `layout=puzzle` e rejeitado no update quando o gate esta fechado;
- `theme_config` e salvo, normalizado e devolvido no payload de settings;
- `theme_config` invalido falha com erro de validacao;
- o frontend preserva `theme_config` em clone/payload e evita dirty state por ordem diferente de chaves;
- o fallback estatico do manager fica capability-aware sem expor `puzzle` fora do gate;
- o teste legado `WallAdsTest` foi atualizado para reconhecer `12` layouts no enum, incluindo `puzzle`.

### Validacao do PR 2 - registry e motion foundation - 2026-04-10

Frontend:

- `cd apps/web && npm run test -- src/modules/wall/player/themes/registry.test.ts src/modules/wall/player/themes/motion.test.ts src/modules/wall/player/components/WallPlayerRoot.test.tsx src/modules/wall/player/engine/motion.test.ts src/modules/wall/player/engine/layoutStrategy.test.ts src/modules/wall/player/wall-theme-architecture-characterization.test.ts`
  - `6 arquivos`
  - `49 testes`
  - `PASS`
- `cd apps/web && npm run test -- src/modules/wall`
  - `47 arquivos`
  - `253 testes`
  - `PASS`
- `cd apps/web && npm run type-check`
  - `PASS`

O que esta bateria do PR 2 travou:

- o player agora usa `MotionConfig` no topo com policy global de `reducedMotion`;
- os layouts passam a ser resolvidos por `registry`, nao por `switch` espalhado;
- `puzzle` entra no registry como layout `board`, com capability e fallback coerentes com a policy de video;
- `resolveLayoutTransition()` passa a consumir motion tokens por tema, em vez de depender de transicao hardcoded por renderer;
- `WallPlayerRoot` deixa de hardcode de side thumbnails por nome de layout e passa a respeitar `kind`/capability do registry;
- `layoutStrategy` passa a tratar `puzzle` como multi-layout com fallback de video para `cinematic`.

### Validacao da Fase 3 - board subsystem foundation - 2026-04-10

Frontend:

- `cd apps/web && npm run test -- src/modules/wall/player/themes/board/BoardSelectionPolicy.test.ts src/modules/wall/player/themes/board/BoardBurstScheduler.test.ts src/modules/wall/player/themes/board/useWallBoard.test.ts src/modules/wall/player/wall-theme-architecture-characterization.test.ts src/modules/wall/player/components/LayoutRenderer.video-multi-layout.test.tsx src/modules/wall/player/components/WallPlayerRoot.test.tsx`
  - `6 arquivos`
  - `19 testes`
  - `PASS`
- `cd apps/web && npm run test -- src/modules/wall`
  - `50 arquivos`
  - `261 testes`
  - `PASS`
- `cd apps/web && npm run type-check`
  - `PASS`

O que esta bateria da fase 3 travou:

- `LayoutRenderer` passa a usar `useWallBoard` no caminho principal dos layouts `board`, em vez de depender de `useMultiSlot`;
- o board ganha identidade formal via `boardInstanceKey` com `eventId`, `layout`, `preset`, `themeVersion`, `performanceTier` e `reducedMotion`;
- updates incrementais de fila deixam de resetar o board quando a identidade nao muda;
- mudancas de `preset` ou `performanceTier` resetam o board de forma explicita;
- o scheduler passa a preencher vazio antes de substituir slot ocupado, preservar ancora, segurar featured por mais tempo e evitar troca de slots adjacentes quando houver alternativa;
- a politica de selecao passa a evitar duplicata visivel de sender quando existe outra opcao pronta na pool.

### Validacao da Fase 4 - readiness, hot window e runtime budget - 2026-04-10

Frontend:

- `cd apps/web && npm run test -- src/modules/wall/player/engine/readiness.test.ts src/modules/wall/player/engine/preload.test.ts src/modules/wall/player/engine/cache.test.ts src/modules/wall/player/hooks/usePerformanceMode.test.ts src/modules/wall/player/hooks/useWallPlayer.test.tsx src/modules/wall/player/hooks/useWallEngine.test.tsx src/modules/wall/player/components/WallPlayerRoot.test.tsx`
  - `7 arquivos`
  - `39 testes`
  - `PASS`
- `cd apps/web && npm run test -- src/modules/wall`
  - `52 arquivos`
  - `268 testes`
  - `PASS`
- `cd apps/web && npm run type-check`
  - `PASS`

O que esta bateria da fase 4 travou:

- a trilha generica de probe de imagem deixa de confiar so em `onload` e passa a usar `decode()` como readiness real;
- `cache.ts` passa a usar a mesma semantica de readiness do preload, em vez de manter duas definicoes diferentes de "asset pronto";
- o player ganha `readiness.ts` com janela quente limitada por `layout` e `runtime budget`, em vez de aquecer a fila inteira;
- `fetchPriority` alto fica reservado para `current`, `anchor` e `next-burst`, preservando prioridade da peca que entra agora;
- o budget operacional fecha `6` pecas / `1` decode para tier `performance` e `9` pecas / `2` decodes para tier `premium`;
- `usePerformanceMode` passa a devolver `performanceTier` e `runtimeBudget`, em vez de so um booleano de reduced effects;
- layouts `board` passam a respeitar `maxStrongAnimations` por tier no runtime visual, em vez de animar forte todo slot ativo;
- updates de midia vindos por realtime continuam entrando sem refetch completo de boot.

### Validacao da Fase 5 - `PuzzleLayout` image-only e gates de video - 2026-04-10

Frontend:

- `cd apps/web && npm run test -- src/modules/wall/player/themes/board/board-utils.test.ts src/modules/wall/player/layouts/GridLayout.test.tsx src/modules/wall/player/engine/layoutStrategy.test.ts src/modules/wall/player/components/LayoutRenderer.video-multi-layout.test.tsx src/modules/wall/player/themes/shared/ThemeMediaSurface.test.tsx src/modules/wall/player/themes/puzzle/PuzzlePiece.test.tsx src/modules/wall/player/themes/puzzle/usePuzzleBoard.test.ts src/modules/wall/player/themes/puzzle/PuzzleLayout.test.tsx`
  - `8 arquivos`
  - `42 testes`
  - `PASS`
- `cd apps/web && npm run test -- src/modules/wall`
  - `58 arquivos`
  - `282 testes`
  - `PASS`
- `cd apps/web && npm run type-check`
  - `PASS`

O que esta bateria da fase 5 travou:

- `PuzzleLayout` entra como renderer real do registry, em vez de fallback cosmetico para layout existente;
- o `puzzle` usa `usePuzzleBoard` sobre a fundacao de `useWallBoard`, preservando o board em update incremental de fila;
- o preset `compact` fecha `6` pecas e o `standard` fecha `9`, com ancora opcional sem criar um segundo renderer;
- os shapes usam `clipPathUnits="objectBoundingBox"` e `defs` deduplicados por variante, em vez de um SVG completo por slot;
- drift roda fora do render React via `useAnimationFrame + useMotionValue + useSpring`, sem loop de `setState` por frame;
- `ThemeMediaSurface` e poster-only para video e o board `puzzle` nunca monta `<video>`;
- `layoutStrategy` agora derruba qualquer video em `puzzle` para fallback single-item `cinematic`, mesmo se `video_multi_layout_policy=all`;
- o fallback de video no caminho `puzzle` continua usando a trilha controlada de `WallVideoSurface`, com somente `1` `<video>` montado;
- o runtime visual passa a limitar slots com animacao forte por tier tambem nos layouts de board ja existentes.

### Validacao da Fase 6 - manager, preview parity e bloqueio de capabilities - 2026-04-10

Frontend:

- `cd apps/web && npm run test -- src/modules/wall/player/wall-theme-architecture-characterization.test.ts src/modules/wall/wall-settings.test.ts src/modules/wall/components/manager/inspector/WallAppearanceTab.test.tsx src/modules/wall/components/manager/stage/WallPreviewCanvas.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx`
  - `5 arquivos`
  - `30 testes`
  - `PASS`
- `cd apps/web && npm run test -- src/modules/wall`
  - `58 arquivos`
  - `288 testes`
  - `PASS`
- `cd apps/web && npm run type-check`
  - `PASS`

O que esta bateria da fase 6 travou:

- o manager continua rollout-safe quando `fallbackOptions.layouts` ainda nao lista `puzzle`, mas drafts persistidos de `puzzle` seguem editaveis por uma `PUZZLE_LAYOUT_FALLBACK_OPTION` sintetica;
- o editor passa a normalizar `draft` e payload salvo com `resolveManagedWallSettings()`, aplicando defaults de `theme_config` e locks de capability antes do save;
- `WallAppearanceTab` agora mostra controles minimos de `theme_config` para `preset`, `anchor_mode`, `hero_enabled` e `burst_intensity`;
- `layout=puzzle` desliga miniaturas laterais, trava `video_multi_layout_policy` em `disallow` e comunica o fallback operacional de video;
- o preview do manager reaproveita o mesmo `LayoutRenderer` e a mesma normalizacao de settings do player, sem depender de estado incompleto do form;
- salvar `theme_config` em `puzzle` nao volta dirty state infinito nem reabre combinacoes incompativeis apos reconcile local.

### Leituras oficiais validadas

Motion:

- `https://motion.dev/docs/react-motion-config`
  - `MotionConfig` e o ponto oficial para politica global de transicao e reduced motion.
- `https://motion.dev/docs/react-transitions`
  - `visualDuration` ajuda a alinhar springs com tempo visual previsivel.
- `https://motion.dev/docs/react-use-animation-frame`
  - `useAnimationFrame` existe exatamente para loops por frame fora do render React.
- `https://motion.dev/docs/react-animate-presence`
  - `mode="popLayout"` exige `forwardRef` em child customizado e pai corretamente posicionado.

React:

- `https://react.dev/learn/preserving-and-resetting-state`
  - `key` continua sendo o mecanismo certo para resetar subarvore quando a identidade muda.
- `https://react.dev/learn/you-might-not-need-an-effect`
  - estado derivado e animacao nao devem virar `Effect + setState` sem necessidade.
- `https://react.dev/reference/react/useSyncExternalStore`
  - `useSyncExternalStore` e a ponte oficial para store externa, mas nao precisa entrar sem sintoma real.

TanStack Query:

- `https://tanstack.com/query/latest/docs/react/guides/important-defaults`
  - queries nascem stale por padrao.
- `https://tanstack.com/query/latest/docs/react/guides/placeholder-query-data`
  - `placeholderData` ajuda a segurar UI sem flicker.
- `https://tanstack.com/query/latest/docs/react/guides/prefetching`
  - `prefetchQuery` e a trilha oficial para aquecer preview/configuracao.

MDN:

- `https://developer.mozilla.org/en-US/docs/Web/API/HTMLImageElement/decode`
  - `decode()` so resolve quando a imagem ja esta decodificada.
- `https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Attributes/fetchpriority`
  - `fetchpriority` e so hint e precisa ser usado com parcimonia.
- `https://developer.mozilla.org/en-US/docs/Web/API/ResizeObserver`
  - `ResizeObserver` e a API certa para reagir ao tamanho real do palco.
- `https://developer.mozilla.org/en-US/docs/Web/SVG/Reference/Attribute/clipPathUnits`
  - `objectBoundingBox` e a base certa para shape responsivo e reutilizavel.

Chrome e MDN para video:

- `https://developer.chrome.com/blog/autoplay/`
  - autoplay mudo continua sendo a rota segura para playback sem gesto do usuario.
- `https://developer.chrome.com/docs/workbox/serving-cached-audio-and-video`
  - cache de audio/video com Service Worker exige atencao explicita a Range Requests.
- `https://developer.mozilla.org/en-US/docs/Web/Media/Guides/Autoplay`
  - `play()` pode falhar e precisa ser tratado como recuperavel.
- `https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/preload`
  - `preload` e hint, nao garantia de download, decode ou readiness.
- `https://developer.mozilla.org/en-US/docs/Web/API/MediaCapabilities/decodingInfo`
  - smoothness e eficiencia de playback dependem do perfil de midia e do device.
- `https://developer.mozilla.org/en-US/docs/Web/API/HTMLVideoElement/getVideoPlaybackQuality`
  - dropped frames precisam ser medidos se algum dia houver video premium por slot.
- `https://developer.mozilla.org/en-US/docs/Web/API/HTMLVideoElement/requestVideoFrameCallback`
  - existe trilha oficial para observabilidade frame-level.

## Estado inicial validado antes do PR 1

### 1. Antes do PR 1, `puzzle` ainda nao existia no contrato

O teste de caracterizacao agora trava explicitamente:

- `packages/shared-types/src/wall.ts` ainda nao conhece `puzzle`;
- `apps/api/app/Modules/Wall/Enums/WallLayout.php` ainda nao conhece `Puzzle`;
- `apps/web/src/modules/wall/manager-config.ts` ainda nao lista `puzzle`;
- `UpdateWallSettingsRequest.php` ainda nao conhece `theme_config`.

Conclusao:

- contrato e manager precisam mudar antes de qualquer layout novo entrar.

### 2. O player ainda nao tem contrato global de motion

Hoje:

- `LayoutRenderer.tsx` ainda usa `framer-motion`;
- ainda nao existe `MotionConfig`;
- ainda nao existe `LayoutGroup`;
- ainda nao existe politica formal de `reducedMotion` por tema.

Conclusao:

- `puzzle` nao deve inaugurar um segundo sistema de motion paralelo.

### 3. O multi-slot atual continua preso em `3`

`LayoutRenderer.tsx` ainda hardcode:

- `MULTI_ITEM_SLOT_COUNT = 3`

Conclusao:

- `carousel`, `mosaic` e `grid` nao podem ser a base do puzzle.

### 4. O preview do manager ja reaproveita o renderer do player

Hoje:

- `WallPreviewCanvas.tsx` usa `LayoutRenderer`;
- `WallPreviewCanvas.tsx` ja usa `ResizeObserver`.

Conclusao:

- o preview e uma alavanca forte da entrega;
- precisamos manter o mesmo caminho de render para player e manager.

### 5. O manager ja tem algum amortecimento de query, mas ainda nao prefetcha

Hoje:

- `wall-query-options.ts` ja usa `placeholderData` para `insights` e `liveSnapshot`;
- `EventWallManagerPage.tsx` ainda nao usa `prefetchQuery`.

Conclusao:

- ha base para melhorar o editor sem reescrever o modulo.

### 6. O cache de asset ainda nao esta pronto para um board denso

Hoje:

- `preload.ts` usa `img.decode()` apenas no preload oportunistico do proximo item;
- `cache.ts` ainda usa `image.onload` na trilha generica de probe;
- o cache atual foi pensado para item atual + proximo, nao para board vivo.

Conclusao:

- readiness real e budget de decode precisam entrar no foundation.

### 7. O realtime do wall ja e real e nao deve ser destruido para caber o puzzle

Hoje:

- `useWallRealtime.ts` ja conecta com Reverb/Pusher;
- `useWallPlayer.ts` ja trata resync, reconnect e heartbeat;
- `useSyncExternalStore` ainda nao e necessario sem sintoma real de tearing.

Conclusao:

- o plano precisa preservar o realtime atual;
- o foco deve ser melhorar scheduler, cache e render, nao refatorar tudo cedo demais.

### 8. Video robusto existe apenas na trilha single-item

Hoje:

- `WallVideoSurface` faz poster-first, startup deadline e stall budget;
- essa trilha entra quando o layout resolvido e single-item;
- layouts multi-slot ainda usam `MediaSurface` sem `videoControl`, caindo em `<video autoPlay muted playsInline preload="auto">`.

Conclusao:

- o `puzzle` v1 nao pode usar multi-video como comportamento default;
- o fallback de video precisa cair para layout single-item e reaproveitar `WallVideoSurface`.

### 9. Antes do PR 1, capabilities ainda nao existiam como contrato formal

Hoje:

- `/wall/options` devolve `value` e `label` por layout;
- `manager-config.ts` lista opcoes separadas;
- `WallAppearanceTab.tsx` expoe `video_multi_layout_policy` como select independente;
- nao existe `capabilities`, `maxSimultaneousVideos`, `posterOnlyMode`, `fallbackVideoLayout` ou `theme_config`.

Conclusao:

- `layout registry + capabilities + theme_config` deixam de ser melhoria opcional;
- eles entram como fundacao obrigatoria antes de liberar o `puzzle` para evento real.

## Principios de execucao

- nao misturar a entrega do `puzzle` com migracao de pacote `framer-motion -> motion/react`;
- fazer `puzzle` v1 como `image-only`;
- manter `metadata ao vivo + asset sob demanda`, nao congelar a fila por causa de cache;
- fazer drift, parallax e burst fora do render React;
- manter player e preview no mesmo renderer;
- bloquear capabilities incompativeis no manager, nao so avisar;
- entrar em producao por feature flag e rollout progressivo.

## Policy unificada que este plano passa a assumir

Esta secao consolida as quatro docs do projeto em regras executaveis:

1. `puzzle` v1 e `image-first`.
2. `puzzle` v1 nao monta `<video>` dentro de peca.
3. `multi-video no puzzle` fica fora da v1.
4. `maxSimultaneousVideos` default do produto continua `1`.
5. Capability oficial do `puzzle` v1:
   - `supportsVideoPlayback = false`
   - `supportsVideoPosterOnly = false`
   - `supportsMultiVideo = false`
   - `maxSimultaneousVideos = 0`
   - `fallbackVideoLayout = cinematic`
6. Se `layout=puzzle` e o item atual for video elegivel:
   - o board nao tenta tocar o video;
   - o player cai para fallback single-item;
   - a trilha usada precisa ser `WallVideoSurface`;
   - ao terminar, falhar ou bater cap, o runtime volta ao board.
7. `poster-only` e modo futuro formal, nao gambiarra local da v1.
8. O manager deve bloquear combinacoes invalidas por capability, nao apenas exibir ajuda textual.

## Organizacao recomendada do codigo

Estrutura alvo do frontend:

```text
apps/web/src/modules/wall/player/
  themes/
    registry.ts
    motion.ts
    shared/
      ThemeMediaSurface.tsx
    board/
      types.ts
      useWallBoard.ts
      BoardSlot.tsx
      BoardBurstScheduler.ts
      BoardSelectionPolicy.ts
    puzzle/
      PuzzleLayout.tsx
      PuzzlePiece.tsx
      usePuzzleBoard.ts
      puzzle-shapes.ts
      puzzle-motion.ts
      puzzle.css
```

Regra:

- `themes/board/*` vira fundacao;
- `themes/puzzle/*` vira o primeiro consumidor;
- `carousel`, `mosaic` e `grid` podem migrar depois, sem bloquear a v1.

## P0 - fundacao obrigatoria e `puzzle` v1

## Fase 0 - Guardrails e caracterizacao

Objetivo:

- travar o estado atual antes da refatoracao.

Arquivos-alvo:

- `apps/web/src/modules/wall/player/wall-theme-architecture-characterization.test.ts`
- `apps/web/src/modules/wall/player/engine/layoutStrategy.test.ts`
- `apps/web/src/modules/wall/player/engine/motion.test.ts`
- `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.test.tsx`
- `apps/web/src/modules/wall/pages/EventWallManagerPage.test.tsx`

Subtarefas:

- [x] travar que `puzzle` ainda nao existe em shared types, backend enum e manager options;
- [x] travar que `theme_config` ainda nao existe no request/backend contract;
- [x] travar que o wall ainda importa `framer-motion` e nao usa `MotionConfig`;
- [x] travar que o manager ainda nao usa `prefetchQuery`;
- [x] travar que `decode()` ainda nao e o criterio geral de readiness.

Resultado esperado:

- as mudancas posteriores deixam de se apoiar em memoria e ficam ancoradas no estado atual real.

## Fase 1 - Contrato compartilhado, backend e rollout gate

Objetivo:

- adicionar `puzzle` e `theme_config` do jeito certo, sem espalhar regra de tema em varios lugares.

Arquivos-alvo:

- `packages/shared-types/src/wall.ts`
- `apps/web/src/lib/api-types.ts`
- `apps/api/app/Modules/Wall/Enums/WallLayout.php`
- `apps/api/app/Modules/Wall/Http/Requests/UpdateWallSettingsRequest.php`
- `apps/api/app/Modules/Wall/Models/EventWallSetting.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`
- `apps/api/app/Modules/Wall/Http/Controllers/EventWallController.php`
- `apps/web/src/modules/wall/manager-config.ts`
- `apps/web/src/modules/wall/wall-settings.ts`
- `apps/web/src/modules/wall/api.ts`
- `apps/api/database/migrations/2026_04_10_020000_add_theme_config_to_event_wall_settings.php`
- `apps/api/config/wall.php`

Subtarefas:

- [x] adicionar `puzzle` a `WallLayout` no shared type e no enum backend;
- [x] adicionar coluna JSON `theme_config` em `event_wall_settings`;
- [x] adicionar cast e `fillable` em `EventWallSetting`;
- [x] validar `theme_config` no request de update;
- [x] expor `theme_config` em `WallPayloadFactory::settings()`;
- [x] expor `puzzle` nas `options()` do backend quando o gate permitir;
- [x] evoluir `options()` para devolver capability metadata por layout;
- [x] definir capability backend do `puzzle`:
  - `supports_video_playback=false`;
  - `supports_video_poster_only=false`;
  - `supports_multi_video=false`;
  - `max_simultaneous_videos=0`;
  - `fallback_video_layout=cinematic`.
- [x] introduzir gate de rollout:
  - `WALL_PUZZLE_ENABLED`;
  - `WALL_PUZZLE_PREVIEW_ENABLED`.

Contrato recomendado de `theme_config` na v1:

```ts
type WallThemeConfig = {
  preset?: 'compact' | 'standard';
  anchor_mode?: 'event_brand' | 'qr_prompt' | 'none';
  burst_intensity?: 'gentle' | 'normal';
  hero_enabled?: boolean;
  video_behavior?: 'fallback_single_item';
};
```

Regras:

- `theme_config` nasce layout-agnostico;
- a subtree `puzzle` so entra se o contrato ficar muito grande depois;
- nao adicionar meia duzia de colunas novas para cada tema.

### Bateria TDD da fase 1

Backend:

- [x] criar `apps/api/tests/Feature/Wall/WallOptionsPuzzleLayoutTest.php`
- [x] criar `apps/api/tests/Feature/Wall/WallSettingsThemeConfigTest.php`
- [x] criar `apps/api/tests/Feature/Wall/WallSettingsFeatureFlagTest.php`

Frontend:

- [x] ampliar `apps/web/src/modules/wall/player/wall-theme-architecture-characterization.test.ts`
- [x] criar `apps/web/src/modules/wall/wall-settings.test.ts`

Cenarios obrigatorios:

- [x] `options()` retorna `puzzle` apenas quando o gate permitir;
- [x] `options()` retorna `capabilities` para todos os layouts;
- [x] `puzzle.capabilities.max_simultaneous_videos` e `0`;
- [x] `puzzle.capabilities.fallback_video_layout` e `cinematic`;
- [x] `theme_config` vazio nao quebra contrato antigo;
- [x] `theme_config` invalido falha com erro claro;
- [x] manager serializa e compara `theme_config` sem falso positivo de dirty state.

## Fase 2 - Layout registry e contrato global de motion

Objetivo:

- criar a base de temas do wall antes de adicionar o primeiro tema denso.

Arquivos-alvo:

- novo `apps/web/src/modules/wall/player/themes/registry.ts`
- novo `apps/web/src/modules/wall/player/themes/motion.ts`
- `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`
- `apps/web/src/modules/wall/player/components/LayoutRenderer.tsx`
- `apps/web/src/modules/wall/player/types.ts`
- `apps/web/src/modules/wall/player/engine/motion.ts`
- `apps/web/src/modules/wall/player/design/tokens.ts`

Subtarefas:

- [x] criar `WallLayoutDefinition`;
- [x] registrar layouts existentes no registry antes de adicionar `puzzle`;
- [x] mover `switch` de `LayoutRenderer` para resolucao por registry;
- [x] criar `WallMotionTokens` por tema;
- [x] subir `MotionConfig` para `WallPlayerRoot`;
- [x] formalizar politica de `reducedMotion` por tema;
- [x] manter `framer-motion` na primeira entrega para nao misturar risco de API e de tema.

Contrato recomendado:

```ts
interface WallLayoutDefinition {
  id: WallLayout;
  label: string;
  kind: 'single' | 'board';
  renderer: React.ComponentType<WallLayoutProps>;
  capabilities: {
    supportsVideoPlayback: boolean;
    supportsVideoPosterOnly: boolean;
    supportsMultiVideo: boolean;
    maxSimultaneousVideos: number;
    fallbackVideoLayout?: Exclude<WallLayout, 'auto'>;
    supportsSideThumbnails: boolean;
    supportsFloatingCaption: boolean;
    supportsRealtimeBurst: boolean;
    supportsThemeConfig: boolean;
  };
  motion: WallMotionTokens;
  version: string;
}
```

### Bateria TDD da fase 2

- [x] criar `apps/web/src/modules/wall/player/themes/registry.test.ts`
- [x] criar `apps/web/src/modules/wall/player/themes/motion.test.ts`
- [x] ampliar `apps/web/src/modules/wall/player/components/WallPlayerRoot.test.tsx`
- [x] ampliar `apps/web/src/modules/wall/player/engine/motion.test.ts`

Cenarios obrigatorios:

- [x] `MotionConfig` envolve o player inteiro;
- [x] `reducedMotion` desliga transform/layout animation agressiva;
- [x] cada layout resolve motion tokens do registry, nao via condicao espalhada;
- [x] `puzzle` e tratado como `board`.
- [x] `puzzle` declara `maxSimultaneousVideos=0` e fallback para `cinematic`.

## Fase 3 - Subsistema de board layouts

Objetivo:

- impedir que `puzzle` nasca como um caso isolado e dificil de manter.

Arquivos-alvo:

- novo `apps/web/src/modules/wall/player/themes/board/types.ts`
- novo `apps/web/src/modules/wall/player/themes/board/useWallBoard.ts`
- novo `apps/web/src/modules/wall/player/themes/board/BoardSlot.tsx`
- novo `apps/web/src/modules/wall/player/themes/board/BoardBurstScheduler.ts`
- novo `apps/web/src/modules/wall/player/themes/board/BoardSelectionPolicy.ts`
- opcional novo `apps/web/src/modules/wall/player/themes/board/board-utils.ts`

Subtarefas:

- [x] definir modelo de slot, ocupacao e idade do slot;
- [x] definir scheduler de burst com:
  - preenchimento de slot vazio;
  - preservacao de ancora;
  - preservacao maior de featured;
  - substituicao do slot mais antigo elegivel;
  - limite de trocas por burst;
  - bloqueio de trocas adjacentes no mesmo burst.
- [x] definir selecao sem duplicata visivel de sender quando houver alternativa;
- [x] formalizar `boardInstanceKey` com:
  - `eventId`
  - `layout`
  - `preset`
  - `themeVersion`
  - `performanceTier`
  - `reducedMotion`
- [x] preservar board em updates comuns de fila;
- [x] resetar board apenas quando a identidade acima mudar.

### Bateria TDD da fase 3

- [x] criar `apps/web/src/modules/wall/player/themes/board/useWallBoard.test.ts`
- [x] criar `apps/web/src/modules/wall/player/themes/board/BoardBurstScheduler.test.ts`
- [x] criar `apps/web/src/modules/wall/player/themes/board/BoardSelectionPolicy.test.ts`

Cenarios obrigatorios:

- [x] item novo preenche vazio antes de substituir slot ocupado;
- [x] featured permanece mais tempo que item comum;
- [x] board nao reseta em update incremental de fila;
- [x] board reseta quando muda `preset` ou `performanceTier`;
- [x] scheduler evita trocar dois vizinhos no mesmo burst quando houver alternativa.

## Fase 4 - Readiness de asset, cache quente e budget de runtime

Objetivo:

- preparar a pipeline de midia para um mural de pecas sem degradar o player.

Arquivos-alvo:

- `apps/web/src/modules/wall/player/engine/preload.ts`
- `apps/web/src/modules/wall/player/engine/cache.ts`
- novo `apps/web/src/modules/wall/player/engine/readiness.ts`
- `apps/web/src/modules/wall/player/runtime-capabilities.ts`
- `apps/web/src/modules/wall/player/hooks/usePerformanceMode.ts`
- `apps/web/src/modules/wall/player/hooks/useWallPlayer.ts`

Subtarefas:

- [x] separar `network_ready` de `decode_ready`;
- [x] trocar a trilha generica de probe de imagem para `decode()` quando disponivel;
- [x] criar janela quente:
  - pecas visiveis;
  - ancora;
  - candidatos do proximo burst.
- [x] usar `fetchpriority="high"` so para ancora, hero e proximo burst;
- [x] limitar concurrent decode por tier;
- [x] limitar slots entrando em animacao forte por tier;
- [x] registrar budget de runtime para `6` e `9` pecas;
- [x] integrar budget ao `usePerformanceMode`;
- [x] nao exigir refetch completo da fila para reagir a item novo.

Budget inicial da v1:

- tier fraco:
  - `6` pecas
  - `1` decode simultaneo
  - `1` troca por burst
  - `1` slot com animacao forte
- FHD padrao:
  - `9` pecas
  - `2` decodes simultaneos
  - `2` trocas por burst
  - `2` slots com animacao forte

### Bateria TDD da fase 4

- [x] ampliar `apps/web/src/modules/wall/player/engine/preload.test.ts`
- [x] ampliar `apps/web/src/modules/wall/player/engine/cache.test.ts`
- [x] criar `apps/web/src/modules/wall/player/engine/readiness.test.ts`
- [x] criar `apps/web/src/modules/wall/player/hooks/usePerformanceMode.test.ts`
- [x] criar `apps/web/src/modules/wall/player/themes/board/board-utils.test.ts`
- [x] criar `apps/web/src/modules/wall/player/layouts/GridLayout.test.tsx`

Cenarios obrigatorios:

- [x] imagem so entra como `ready` depois de `decode`;
- [x] asset de futuro nao rouba prioridade da peca que entra agora;
- [x] janela quente nao tenta aquecer fila inteira;
- [x] layouts de board respeitam budget de animacao forte por tier;
- [x] board continua reagindo a evento realtime sem refetch completo.

## Fase 5 - Implementar `Quebra Cabeca` v1

Objetivo:

- entregar um layout novo, bonito e seguro, sem video e sem overload de UX.

Arquivos-alvo:

- novo `apps/web/src/modules/wall/player/themes/puzzle/PuzzleLayout.tsx`
- novo `apps/web/src/modules/wall/player/themes/puzzle/PuzzlePiece.tsx`
- novo `apps/web/src/modules/wall/player/themes/puzzle/usePuzzleBoard.ts`
- novo `apps/web/src/modules/wall/player/themes/puzzle/puzzle-shapes.ts`
- novo `apps/web/src/modules/wall/player/themes/puzzle/puzzle-motion.ts`
- novo `apps/web/src/modules/wall/player/themes/puzzle/puzzle.css`
- novo `apps/web/src/modules/wall/player/themes/shared/ThemeMediaSurface.tsx`
- `apps/web/src/modules/wall/player/components/LayoutRenderer.tsx`
- `apps/web/src/modules/wall/player/engine/layoutStrategy.ts`

Subtarefas:

- [x] criar preset `compact` com `6` pecas;
- [x] criar preset `standard` com `9` pecas;
- [x] criar peca ancora central opcional;
- [x] criar `clipPath` com `clipPathUnits="objectBoundingBox"`;
- [x] deduplicar `defs` por variante de shape;
- [x] limitar catalogo inicial a poucas variantes de shape;
- [x] implementar drift fora do render React via `MotionValue + useSpring + useAnimationFrame` e manter burst declarativo por motion;
- [x] bloquear video dentro do tema e cair para `cinematic` via capability `fallbackVideoLayout`;
- [x] garantir que fallback de video use `WallVideoSurface`, nao `<video>` cru;
- [x] garantir `maxSimultaneousVideos=0` dentro do board;
- [x] bloquear side thumbnails em runtime enquanto `layout=puzzle`;
- [x] bloquear floating caption por peca;
- [x] manter sender/caption apenas em ancora ou barra externa, se necessario.

Regras da v1:

- somente imagem;
- sem face detection client-side;
- sem blur pesado por slot;
- sem `12` pecas;
- sem shared transition premium ainda.

### Bateria TDD da fase 5

- [x] criar `apps/web/src/modules/wall/player/themes/puzzle/PuzzleLayout.test.tsx`
- [x] criar `apps/web/src/modules/wall/player/themes/puzzle/PuzzlePiece.test.tsx`
- [x] criar `apps/web/src/modules/wall/player/themes/puzzle/usePuzzleBoard.test.ts`
- [x] criar `apps/web/src/modules/wall/player/themes/shared/ThemeMediaSurface.test.tsx`
- [x] ampliar `apps/web/src/modules/wall/player/engine/layoutStrategy.test.ts`
- [x] ampliar `apps/web/src/modules/wall/player/components/LayoutRenderer.video-multi-layout.test.tsx`

Cenarios obrigatorios:

- [x] `layout=puzzle` cai para fallback single-item quando a midia atual for video;
- [x] fallback de video no `puzzle` monta somente `1` `<video>`;
- [x] fallback de video no `puzzle` usa poster-first/control path da `WallVideoSurface`;
- [x] board `puzzle` nunca monta `<video>` em slot;
- [x] preset `standard` usa `9` slots e `compact` usa `6`;
- [x] troca incremental nao remonta o board inteiro;
- [x] drift nao depende de `setState` por frame;
- [x] shape e referenciado por `defs` deduplicados, nao por SVG unico por slot.

## Fase 6 - Manager, preview e bloqueio de capabilities

Objetivo:

- fazer o editor ficar coerente com o tema antes de pensar em polimento premium.

Arquivos-alvo:

- `apps/web/src/modules/wall/pages/EventWallManagerPage.tsx`
- `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.tsx`
- `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.tsx`
- `apps/web/src/modules/wall/wall-settings.ts`
- `apps/web/src/modules/wall/manager-config.ts`

Subtarefas:

- [x] expor `puzzle` no editor quando o gate permitir;
- [x] expor `theme_config` minimo:
  - `preset`
  - `anchor_mode`
  - `hero_enabled`
  - `burst_intensity`
- [x] bloquear no manager:
  - video em `puzzle`
  - side thumbnails em `puzzle`
  - floating caption por peca
  - blur pesado por slot
  - face overlay client-side
- [x] esconder/desabilitar `video_multi_layout_policy` quando `layout=puzzle`;
- [x] mostrar copy operacional:
  - `Puzzle exibe imagens. Videos entram em layout individual de fallback.`
- [x] reaproveitar o mesmo registry/layout renderer no preview;
- [x] manter preview previsivel mesmo com configuracao incompleta ou invalida.
- [x] normalizar `draft` e payload salvo com defaults/capabilities sinteticos para evitar combinacoes invalidas no manager.

### Bateria TDD da fase 6

- [x] ampliar `apps/web/src/modules/wall/pages/EventWallManagerPage.test.tsx`
- [x] ampliar `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.test.tsx`
- [x] ampliar `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.test.tsx`
- [x] ampliar `apps/web/src/modules/wall/wall-settings.test.ts`
- [x] alinhar `apps/web/src/modules/wall/player/wall-theme-architecture-characterization.test.ts` ao manager capability-aware e ao fallback estatico rollout-safe.

Cenarios obrigatorios:

- [x] manager mostra controles minimos do `puzzle`;
- [x] controles incompativeis ficam bloqueados ou desligados automaticamente;
- [x] manager nao permite `one` nem `all` para video multi-layout quando `layout=puzzle`;
- [x] preview do manager bate com o renderer do player;
- [x] salvar `theme_config` nao gera dirty state infinito.

## P1 - melhoria importante depois da v1 estar firme

## Fase 7 - Geometria real do palco e downgrade adaptativo

Objetivo:

- reagir ao tamanho real do palco e a area consumida por overlays.

Arquivos-alvo:

- novo `apps/web/src/modules/wall/player/hooks/useStageGeometry.ts`
- `apps/web/src/modules/wall/player/themes/puzzle/PuzzleLayout.tsx`
- `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.tsx`
- `apps/web/src/modules/wall/player/components/PlayerShell.tsx`

Subtarefas:

- [ ] adicionar `ResizeObserver` tambem ao palco vivo;
- [ ] considerar safe areas de QR, branding e caption;
- [ ] descer de `9` para `6` pecas quando a area util nao comportar;
- [ ] manter preview e player aplicando a mesma regra.

### Bateria TDD da fase 7

- [ ] criar `apps/web/src/modules/wall/player/hooks/useStageGeometry.test.ts`
- [ ] ampliar `apps/web/src/modules/wall/player/themes/puzzle/PuzzleLayout.test.tsx`

## Fase 8 - Tuning do manager e prefetch de preview

Objetivo:

- reduzir flicker no editor sem competir com o player.

Arquivos-alvo:

- `apps/web/src/modules/wall/wall-query-options.ts`
- `apps/web/src/modules/wall/pages/EventWallManagerPage.tsx`
- `apps/web/src/modules/wall/api.ts`

Subtarefas:

- [ ] adicionar `prefetchQuery` ao trocar tema/preset;
- [ ] revisar `staleTime` do editor;
- [ ] manter `placeholderData` onde o ganho visual for real;
- [ ] evitar refetch agressivo em `focus/reconnect` para o editor.

### Bateria TDD da fase 8

- [ ] ampliar `apps/web/src/modules/wall/pages/EventWallManagerPage.test.tsx`
- [ ] criar `apps/web/src/modules/wall/wall-query-options.test.ts`

## Fase 9 - Polimento premium do board

Objetivo:

- melhorar a sensacao premium do layout sem reabrir o foundation.

Arquivos-alvo:

- `apps/web/src/modules/wall/player/themes/puzzle/PuzzleLayout.tsx`
- `apps/web/src/modules/wall/player/themes/puzzle/PuzzlePiece.tsx`
- `apps/web/src/modules/wall/player/themes/board/BoardSlot.tsx`

Subtarefas:

- [ ] usar `AnimatePresence mode="popLayout"` nas pecas;
- [ ] aplicar `forwardRef` onde preciso;
- [ ] garantir pai com `position != static`;
- [ ] estudar `LayoutGroup` e `layoutId` para featured hero;
- [ ] liberar preset `12` somente apos medicao real.

### Bateria TDD da fase 9

- [ ] ampliar `apps/web/src/modules/wall/player/themes/puzzle/PuzzleLayout.test.tsx`
- [ ] criar `apps/web/src/modules/wall/player/themes/puzzle/premium-motion.test.tsx`

## Fase 10 - Observabilidade e downgrade operacional

Objetivo:

- tornar o puzzle observavel em evento real.

Arquivos-alvo:

- `packages/shared-types/src/wall.ts`
- `apps/web/src/modules/wall/player/hooks/useWallPlayer.ts`
- `apps/api/app/Modules/Wall/Http/Resources/WallDiagnosticsResource.php`
- `apps/api/tests/Feature/Wall/WallDiagnosticsTest.php`
- `apps/web/src/modules/wall/components/manager/diagnostics/WallPlayerRuntimeCard.tsx`

Subtarefas:

- [ ] adicionar counters especificos:
  - `board_piece_count`
  - `board_burst_count`
  - `board_budget_downgrade_count`
  - `decode_backlog_count`
  - `board_reset_count`
- [ ] expor downgrade reason no diagnostics;
- [ ] mostrar no manager quando o wall caiu de `9` para `6` pecas.

### Bateria TDD da fase 10

- [ ] ampliar `apps/api/tests/Feature/Wall/WallDiagnosticsTest.php`
- [ ] ampliar `apps/web/src/modules/wall/components/manager/diagnostics/WallPlayerRuntimeCard.test.tsx`

## O que fica explicitamente fora do P0/P1

- video dentro do `puzzle`;
- `face-api.js` no client;
- face brackets em tempo real;
- lazy chunks de tema como prerequisito da v1;
- `useSyncExternalStore` sem sintoma real de tearing;
- migracao de pacote para `motion/react`;
- WebGL/canvas para a primeira entrega.

## Bateria TDD consolidada

### Backend - manter

- `apps/api/tests/Feature/Wall/WallOptionsCharacterizationTest.php`
- `apps/api/tests/Feature/Wall/PublicWallBootTest.php`
- `apps/api/tests/Feature/Wall/WallLiveSnapshotTest.php`
- `apps/api/tests/Feature/Wall/WallInsightsTest.php`
- `apps/api/tests/Feature/Wall/WallDiagnosticsTest.php`
- `apps/api/tests/Unit/Modules/Wall/WallEligibilityServiceTest.php`
- `apps/api/tests/Unit/Modules/Wall/WallAcceptedOrientationTest.php`

### Backend - criar

- `apps/api/tests/Feature/Wall/WallOptionsPuzzleLayoutTest.php`
- `apps/api/tests/Feature/Wall/WallSettingsThemeConfigTest.php`
- `apps/api/tests/Feature/Wall/WallSettingsFeatureFlagTest.php`

### Frontend - manter

- `apps/web/src/modules/wall/player/wall-theme-architecture-characterization.test.ts`
- `apps/web/src/modules/wall/player/components/LayoutRenderer.video-multi-layout.test.tsx`
- `apps/web/src/modules/wall/player/components/WallVideoSurface.test.tsx`
- `apps/web/src/modules/wall/player/components/MediaSurface.test.tsx`
- `apps/web/src/modules/wall/player/engine/layoutStrategy.test.ts`
- `apps/web/src/modules/wall/player/engine/motion.test.ts`
- `apps/web/src/modules/wall/player/engine/preload.test.ts`
- `apps/web/src/modules/wall/player/engine/cache.test.ts`
- `apps/web/src/modules/wall/player/hooks/useMultiSlot.test.ts`
- `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.test.tsx`
- `apps/web/src/modules/wall/pages/EventWallManagerPage.test.tsx`
- `apps/web/src/modules/wall/player/components/WallPlayerRoot.test.tsx`

### Frontend - criar

- `apps/web/src/modules/wall/wall-settings.test.ts`
- `apps/web/src/modules/wall/player/themes/registry.test.ts`
- `apps/web/src/modules/wall/player/themes/motion.test.ts`
- `apps/web/src/modules/wall/player/themes/board/useWallBoard.test.ts`
- `apps/web/src/modules/wall/player/themes/board/BoardBurstScheduler.test.ts`
- `apps/web/src/modules/wall/player/themes/board/BoardSelectionPolicy.test.ts`
- `apps/web/src/modules/wall/player/engine/readiness.test.ts`
- `apps/web/src/modules/wall/player/hooks/usePerformanceMode.test.ts`
- `apps/web/src/modules/wall/player/themes/puzzle/PuzzleLayout.test.tsx`
- `apps/web/src/modules/wall/player/themes/puzzle/PuzzlePiece.test.tsx`
- `apps/web/src/modules/wall/player/themes/puzzle/usePuzzleBoard.test.ts`
- `apps/web/src/modules/wall/player/themes/shared/ThemeMediaSurface.test.tsx`
- `apps/web/src/modules/wall/player/hooks/useStageGeometry.test.ts`
- `apps/web/src/modules/wall/wall-query-options.test.ts`

## Ordem recomendada de entrega

## PR 1 - Contrato e gate

- Fase 0 completa
- Fase 1 completa

Saida esperada:

- `puzzle` existe no contrato;
- `theme_config` existe no backend e frontend;
- `options()` ja devolve capabilities por layout;
- `puzzle` declara `maxSimultaneousVideos=0` e fallback `cinematic`;
- manager consegue receber `puzzle`, mas ainda nao renderiza o tema;
- rollout gate impede exposicao prematura.

## PR 2 - Registry e motion foundation

- Fase 2 completa

Saida esperada:

- wall passa a ter registry de layouts;
- player passa a ter contrato global de motion;
- reduced motion vira policy real.

## PR 3 - Board subsystem e readiness

- Fase 3 completa
- Fase 4 completa

Saida esperada:

- foundation de board pronta;
- readiness por `decode()` pronta;
- budget de runtime minimo fechado para `6` e `9` pecas.

## PR 4 - `Puzzle` v1 no player

- Fase 5 completa

Saida esperada:

- player ja exibe `puzzle` image-only;
- fallback para video existe e usa `WallVideoSurface`;
- board `puzzle` nunca monta `<video>` em slot;
- board preserva estado e reage a realtime sem reset bruto.

## PR 5 - Manager e preview parity

- Fase 6 completa

Saida esperada:

- manager salva `theme_config`;
- preview bate com player;
- capabilities incompativeis ficam bloqueadas.

## PR 6 - P1 operacional

- Fases 7 e 8

Saida esperada:

- palco vivo responde a geometria real;
- editor ganha prefetch e menos flicker.

## PR 7 - P1 premium

- Fases 9 e 10

Saida esperada:

- board fica mais premium;
- degradacao e budget ficam observaveis no manager.

## Sequencia recomendada por sprint

### Sprint 1

- PR 1
- PR 2

### Sprint 2

- PR 3
- PR 4

### Sprint 3

- PR 5
- rollout interno

### Sprint 4

- PR 6
- PR 7 se a telemetria autorizar

## Plano de rollout

### Etapa A - feature flag interna

- `puzzle` invisivel para clientes;
- preview e player liberados so para time interno;
- metricas e logs ligados desde o inicio.

### Etapa B - preview-only no manager

- layout aparece no manager;
- preview funciona;
- player vivo continua bloqueado.

### Etapa C - um evento controlado

- ativar em `1` evento;
- usar `6` ou `9` pecas conforme hardware;
- acompanhar resets, decode budget e reconnect.

### Etapa D - tenants selecionados

- liberar para grupo pequeno;
- manter feature flag por tenant;
- so promover depois de estabilidade real.

## Definicao de pronto da v1

O `Quebra Cabeca` so deve ser considerado pronto quando:

- [x] contrato compartilhado, enum backend e manager conhecem `puzzle`;
- [x] `theme_config` existe e e salvo com seguranca;
- [x] `options()` expoe capabilities por layout;
- [x] `puzzle.capabilities.maxSimultaneousVideos = 0`;
- [x] `puzzle.capabilities.fallbackVideoLayout = cinematic`;
- [x] player tem `MotionConfig` e contrato global de motion;
- [x] `puzzle` usa fundacao de board, nao `useMultiSlot` remendado;
- [x] readiness de imagem depende de `decode()`, nao so de `load`;
- [x] cache aquece apenas a janela quente do board;
- [x] realtime continua atualizando a fila sem refetch completo;
- [x] preview e player batem no mesmo preset;
- [x] manager bloqueia video e outras capabilities incompativeis;
- [x] `puzzle` nao monta `<video>` dentro do board;
- [x] video elegivel em `puzzle` cai para fallback single-item com `WallVideoSurface`;
- [ ] `maxSimultaneousVideos` default do produto continua `1`;
- [x] board so reseta quando a identidade oficial muda;
- [ ] em FHD padrao com `9` pecas o layout se mantem fluido e sem loading visivel reentrante;
- [ ] rollout interno passou por pelo menos um evento controlado.

## Recomendacao final

O erro mais caro aqui seria tentar "desenhar o puzzle" antes de criar a fundacao certa.

O `P0` real e:

1. contrato compartilhado + `theme_config` + gate de rollout;
2. registry de layout + contrato global de motion;
3. subsistema de board;
4. readiness/cache/budget;
5. `PuzzleLayout` image-only;
6. manager/preview bloqueando combinacoes ruins.

Se essa ordem for respeitada, o `Quebra Cabeca` entra como tema forte e abre caminho real para novos templates.
Se essa ordem for pulada, o risco e entregar uma demo bonita e um player caro de sustentar em evento real.
