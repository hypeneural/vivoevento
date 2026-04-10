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

## O que ficou validado no codigo atual

### 1. `puzzle` ainda nao existe no contrato

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

## Principios de execucao

- nao misturar a entrega do `puzzle` com migracao de pacote `framer-motion -> motion/react`;
- fazer `puzzle` v1 como `image-only`;
- manter `metadata ao vivo + asset sob demanda`, nao congelar a fila por causa de cache;
- fazer drift, parallax e burst fora do render React;
- manter player e preview no mesmo renderer;
- bloquear capabilities incompativeis no manager, nao so avisar;
- entrar em producao por feature flag e rollout progressivo.

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
- `apps/api/database/migrations/<timestamp>_add_theme_config_to_event_wall_settings_table.php`
- opcional: `apps/api/config/wall.php`

Subtarefas:

- [ ] adicionar `puzzle` a `WallLayout` no shared type e no enum backend;
- [ ] adicionar coluna JSON `theme_config` em `event_wall_settings`;
- [ ] adicionar cast e `fillable` em `EventWallSetting`;
- [ ] validar `theme_config` no request de update;
- [ ] expor `theme_config` em `WallPayloadFactory::settings()`;
- [ ] expor `puzzle` nas `options()` do backend;
- [ ] introduzir gate de rollout:
  - `puzzle_enabled` global;
  - `puzzle_preview_enabled` se quiser preview antes do player vivo.

Contrato recomendado de `theme_config` na v1:

```ts
type WallThemeConfig = {
  preset?: 'compact' | 'standard';
  anchor_mode?: 'event_brand' | 'qr_prompt' | 'none';
  burst_intensity?: 'gentle' | 'normal';
  hero_enabled?: boolean;
};
```

Regras:

- `theme_config` nasce layout-agnostico;
- a subtree `puzzle` so entra se o contrato ficar muito grande depois;
- nao adicionar meia duzia de colunas novas para cada tema.

### Bateria TDD da fase 1

Backend:

- [ ] criar `apps/api/tests/Feature/Wall/WallOptionsPuzzleLayoutTest.php`
- [ ] criar `apps/api/tests/Feature/Wall/WallSettingsThemeConfigTest.php`
- [ ] criar `apps/api/tests/Feature/Wall/WallSettingsFeatureFlagTest.php`

Frontend:

- [ ] ampliar `apps/web/src/modules/wall/player/wall-theme-architecture-characterization.test.ts`
- [ ] criar `apps/web/src/modules/wall/wall-settings.test.ts`

Cenarios obrigatorios:

- [ ] `options()` retorna `puzzle` apenas quando o gate permitir;
- [ ] `theme_config` vazio nao quebra contrato antigo;
- [ ] `theme_config` invalido falha com erro claro;
- [ ] manager serializa e compara `theme_config` sem falso positivo de dirty state.

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

- [ ] criar `WallLayoutDefinition`;
- [ ] registrar layouts existentes no registry antes de adicionar `puzzle`;
- [ ] mover `switch` de `LayoutRenderer` para resolucao por registry;
- [ ] criar `WallMotionTokens` por tema;
- [ ] subir `MotionConfig` para `WallPlayerRoot`;
- [ ] formalizar politica de `reducedMotion` por tema;
- [ ] manter `framer-motion` na primeira entrega para nao misturar risco de API e de tema.

Contrato recomendado:

```ts
interface WallLayoutDefinition {
  id: WallLayout;
  label: string;
  kind: 'single' | 'board';
  renderer: React.ComponentType<WallLayoutProps>;
  supportsVideo: boolean;
  supportsSideThumbnails: boolean;
  supportsFloatingCaption: boolean;
  supportsRealtimeBurst: boolean;
  motion: WallMotionTokens;
  version: string;
}
```

### Bateria TDD da fase 2

- [ ] criar `apps/web/src/modules/wall/player/themes/registry.test.ts`
- [ ] criar `apps/web/src/modules/wall/player/themes/motion.test.ts`
- [ ] ampliar `apps/web/src/modules/wall/player/components/WallPlayerRoot.test.tsx`
- [ ] ampliar `apps/web/src/modules/wall/player/engine/motion.test.ts`

Cenarios obrigatorios:

- [ ] `MotionConfig` envolve o player inteiro;
- [ ] `reducedMotion` desliga transform/layout animation agressiva;
- [ ] cada layout resolve motion tokens do registry, nao via condicao espalhada;
- [ ] `puzzle` e tratado como `board`.

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

- [ ] definir modelo de slot, ocupacao e idade do slot;
- [ ] definir scheduler de burst com:
  - preenchimento de slot vazio;
  - preservacao de ancora;
  - preservacao maior de featured;
  - substituicao do slot mais antigo elegivel;
  - limite de trocas por burst;
  - bloqueio de trocas adjacentes no mesmo burst.
- [ ] definir selecao sem duplicata visivel de sender quando houver alternativa;
- [ ] formalizar `boardInstanceKey` com:
  - `eventId`
  - `layout`
  - `preset`
  - `themeVersion`
  - `performanceTier`
  - `reducedMotion`
- [ ] preservar board em updates comuns de fila;
- [ ] resetar board apenas quando a identidade acima mudar.

### Bateria TDD da fase 3

- [ ] criar `apps/web/src/modules/wall/player/themes/board/useWallBoard.test.ts`
- [ ] criar `apps/web/src/modules/wall/player/themes/board/BoardBurstScheduler.test.ts`
- [ ] criar `apps/web/src/modules/wall/player/themes/board/BoardSelectionPolicy.test.ts`

Cenarios obrigatorios:

- [ ] item novo preenche vazio antes de substituir slot ocupado;
- [ ] featured permanece mais tempo que item comum;
- [ ] board nao reseta em update incremental de fila;
- [ ] board reseta quando muda `preset` ou `performanceTier`;
- [ ] scheduler evita trocar dois vizinhos no mesmo burst quando houver alternativa.

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

- [ ] separar `network_ready` de `decode_ready`;
- [ ] trocar a trilha generica de probe de imagem para `decode()` quando disponivel;
- [ ] criar janela quente:
  - pecas visiveis;
  - ancora;
  - candidatos do proximo burst.
- [ ] usar `fetchpriority="high"` so para ancora, hero e proximo burst;
- [ ] limitar concurrent decode por tier;
- [ ] limitar slots entrando em animacao forte por tier;
- [ ] registrar budget de runtime para `6` e `9` pecas;
- [ ] integrar budget ao `usePerformanceMode`;
- [ ] nao exigir refetch completo da fila para reagir a item novo.

Budget inicial da v1:

- tier fraco:
  - `6` pecas
  - `1` decode simultaneo
  - `1` troca por burst
- FHD padrao:
  - `9` pecas
  - `2` decodes simultaneos
  - `2` trocas por burst

### Bateria TDD da fase 4

- [ ] ampliar `apps/web/src/modules/wall/player/engine/preload.test.ts`
- [ ] ampliar `apps/web/src/modules/wall/player/engine/cache.test.ts`
- [ ] criar `apps/web/src/modules/wall/player/engine/readiness.test.ts`
- [ ] criar `apps/web/src/modules/wall/player/hooks/usePerformanceMode.test.ts`

Cenarios obrigatorios:

- [ ] imagem so entra como `ready` depois de `decode`;
- [ ] asset de futuro nao rouba prioridade da peca que entra agora;
- [ ] janela quente nao tenta aquecer fila inteira;
- [ ] board continua reagindo a evento realtime sem refetch completo.

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

- [ ] criar preset `compact` com `6` pecas;
- [ ] criar preset `standard` com `9` pecas;
- [ ] criar peca ancora central opcional;
- [ ] criar `clipPath` com `clipPathUnits="objectBoundingBox"`;
- [ ] deduplicar `defs` por variante de shape;
- [ ] limitar catalogo inicial a poucas variantes de shape;
- [ ] implementar drift e micro-burst via `MotionValue + useSpring + useAnimationFrame/useAnimate`;
- [ ] bloquear video dentro do tema e cair para `fullscreen` ou `cinematic`;
- [ ] bloquear side thumbnails enquanto `layout=puzzle`;
- [ ] bloquear floating caption por peca;
- [ ] manter sender/caption apenas em ancora ou barra externa, se necessario.

Regras da v1:

- somente imagem;
- sem face detection client-side;
- sem blur pesado por slot;
- sem `12` pecas;
- sem shared transition premium ainda.

### Bateria TDD da fase 5

- [ ] criar `apps/web/src/modules/wall/player/themes/puzzle/PuzzleLayout.test.tsx`
- [ ] criar `apps/web/src/modules/wall/player/themes/puzzle/PuzzlePiece.test.tsx`
- [ ] criar `apps/web/src/modules/wall/player/themes/puzzle/usePuzzleBoard.test.ts`
- [ ] criar `apps/web/src/modules/wall/player/themes/shared/ThemeMediaSurface.test.tsx`
- [ ] ampliar `apps/web/src/modules/wall/player/engine/layoutStrategy.test.ts`

Cenarios obrigatorios:

- [ ] `layout=puzzle` cai para fallback single-item quando a midia atual for video;
- [ ] preset `standard` usa `9` slots e `compact` usa `6`;
- [ ] troca incremental nao remonta o board inteiro;
- [ ] drift nao depende de `setState` por frame;
- [ ] shape e referenciado por `defs` deduplicados, nao por SVG unico por slot.

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

- [ ] expor `puzzle` no editor quando o gate permitir;
- [ ] expor `theme_config` minimo:
  - `preset`
  - `anchor_mode`
  - `hero_enabled`
  - `burst_intensity`
- [ ] bloquear no manager:
  - video em `puzzle`
  - side thumbnails em `puzzle`
  - floating caption por peca
  - blur pesado por slot
  - face overlay client-side
- [ ] reaproveitar o mesmo registry/layout renderer no preview;
- [ ] manter preview previsivel mesmo com configuracao incompleta ou invalida.

### Bateria TDD da fase 6

- [ ] ampliar `apps/web/src/modules/wall/pages/EventWallManagerPage.test.tsx`
- [ ] ampliar `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.test.tsx`
- [ ] ampliar `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.test.tsx`

Cenarios obrigatorios:

- [ ] manager mostra controles minimos do `puzzle`;
- [ ] controles incompativeis ficam bloqueados ou desligados automaticamente;
- [ ] preview do manager bate com o renderer do player;
- [ ] salvar `theme_config` nao gera dirty state infinito.

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
- fallback para video existe;
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

- [ ] contrato compartilhado, enum backend e manager conhecem `puzzle`;
- [ ] `theme_config` existe e e salvo com seguranca;
- [ ] player tem `MotionConfig` e contrato global de motion;
- [ ] `puzzle` usa fundacao de board, nao `useMultiSlot` remendado;
- [ ] readiness de imagem depende de `decode()`, nao so de `load`;
- [ ] cache aquece apenas a janela quente do board;
- [ ] realtime continua atualizando a fila sem refetch completo;
- [ ] preview e player batem no mesmo preset;
- [ ] manager bloqueia video e outras capabilities incompativeis;
- [ ] board so reseta quando a identidade oficial muda;
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
