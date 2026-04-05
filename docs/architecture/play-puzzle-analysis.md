# Play Puzzle Architecture

## Objetivo

Este documento descreve a arquitetura tecnica recomendada e agora parcialmente implementada para o jogo `puzzle` do modulo `Play`.

O foco desta fase e:

- `React + TypeScript` como shell do produto;
- `Phaser` como runtime do gameplay;
- puzzle grid com foto dinamica do evento;
- mobile-first;
- codigo modular e preparado para escala;
- base pronta para ranking, analytics, dificuldade dinamica e novos minigames.

## Estado Atual do Produto

Antes desta refatoracao, o `puzzle` concentrava quase toda a logica em uma unica `PuzzleScene`.

Problemas principais do desenho antigo:

- `PuzzleScene` cuidava de layout, recorte, drag, validacao, feedback e finalizacao;
- nao existiam `dropZone` reais por slot;
- nao havia separacao clara entre `domain`, `factory` e `system`;
- o feedback visual era funcional, mas ainda pouco tatil;
- a estrutura nao ajudava a evoluir para HUD externa, combos, audio e vitoria cinematica.

## Decisao Arquitetural

O `puzzle` passa a seguir esta regra:

- `PuzzleScene` orquestra;
- `domain` guarda regras de board, combo e score;
- `factories` montam slots e pecas;
- `systems` controlam drag, placement, feedback, audio e victory;
- `ui` fala com o bridge do React;
- `types` tipam tudo o que circula entre cena, systems e bridge.

Essa separacao reduz acoplamento e evita voltar para uma `Scene` monolitica.

## Estrutura de Pastas

```text
apps/web/src/modules/play/phaser/puzzle/
  PuzzleGame.ts
  PuzzleScene.ts

  config/
    puzzleAssets.ts
    puzzleConfig.ts

  domain/
    PuzzleBoard.ts
    PuzzleCombo.ts
    PuzzleScore.ts

  factories/
    PuzzlePieceFactory.ts
    PuzzleSlotFactory.ts

  systems/
    PuzzleAudioSystem.ts
    PuzzleDragSystem.ts
    PuzzleFeedbackSystem.ts
    PuzzlePlacementSystem.ts
    PuzzleVictorySystem.ts

  types/
    puzzle.types.ts

  ui/
    PuzzleHudBridge.ts
```

## Responsabilidade por Arquivo

### `PuzzleGame.ts`

Ponto de entrada do runtime do `puzzle`.

Responsabilidades:

- receber payload do `Play`;
- normalizar settings;
- criar `bridge`;
- chamar `bootGame`.

### `PuzzleScene.ts`

Cena orquestradora.

Responsabilidades:

- preload da foto do evento e assets de suporte;
- instanciar grid, layout, factories e systems;
- conectar `PuzzleHudBridge` ao runtime;
- disparar fluxo de vitoria e encerramento;
- limpar texturas temporarias da sessao.

### `config/puzzleAssets.ts`

Mapa de assets do jogo.

Responsabilidades:

- definir chaves de audio;
- definir caminhos de prompts, particulas e icones;
- concentrar nomes de textura usados pela cena.

### `config/puzzleConfig.ts`

Normalizacao de configuracao.

Responsabilidades:

- normalizar `gridSize`, `snapEnabled`, `dragTolerance`;
- traduzir `settings` externos para um shape estavel;
- resolver o grid logico do puzzle.

### `domain/PuzzleBoard.ts`

Regra de board e layout.

Responsabilidades:

- construir layout mobile-first do tabuleiro e da bandeja;
- criar o estado do board;
- marcar peca encaixada;
- checar conclusao do puzzle;
- ler restore de pecas completas.

### `domain/PuzzleCombo.ts`

Regra de combo.

Responsabilidades:

- controlar streak de acertos;
- resetar combo em erro;
- expor snapshot atual e maximo.

### `domain/PuzzleScore.ts`

Regra de score e progresso.

Responsabilidades:

- contar `moves`, `wrongDrops` e `correctDrops`;
- hidratar estado a partir de `restore.moves`;
- gerar `progress snapshot`;
- gerar `NormalizedGameResult`.

### `factories/PuzzleSlotFactory.ts`

Montagem dos slots.

Responsabilidades:

- criar cada slot visual;
- criar `dropZone` real;
- criar frame e highlight por slot.

### `factories/PuzzlePieceFactory.ts`

Montagem de texturas e pecas.

Responsabilidades:

- recortar a foto em grid;
- criar sprites das pecas;
- posicionar pecas na bandeja;
- respeitar `restore` e seed da sessao.

### `systems/PuzzleAudioSystem.ts`

Audio do gameplay.

Responsabilidades:

- preload de SFX curtos;
- tocar pickup, hover, snap, error e victory;
- manter a cena limpa de detalhes de cache de audio.

### `systems/PuzzleFeedbackSystem.ts`

Game feel.

Responsabilidades:

- `bringToTop` e scale no drag;
- highlight de slot valido ou invalido;
- tween de snap;
- wobble e retorno ao tray;
- particulas leves.

### `systems/PuzzlePlacementSystem.ts`

Regra de encaixe.

Responsabilidades:

- decidir se o drop e valido;
- aplicar score e progresso;
- travar slot e peca;
- disparar feedback e audio corretos;
- informar se o board foi resolvido.

### `systems/PuzzleDragSystem.ts`

Controlador de input.

Responsabilidades:

- ouvir `dragstart`, `drag`, `dragenter`, `dragleave`, `drop`, `dragend`;
- encaminhar para feedback e placement;
- emitir eventos de move para o bridge;
- iniciar vitoria quando o board fecha.

### `systems/PuzzleVictorySystem.ts`

Fechamento da experiencia.

Responsabilidades:

- revelar a imagem inteira;
- tocar som final;
- aplicar efeito de camera;
- disparar burst final de particulas;
- chamar `finish` depois da sequencia.

### `ui/PuzzleHudBridge.ts`

Camada de traducao para o React.

Responsabilidades:

- emitir `ready`;
- emitir `progress` tipado;
- emitir eventos de move;
- finalizar a partida com payload consistente.

## Fluxo de Eventos React <-> Phaser

### React para Phaser

React continua dono de:

- criar sessao;
- retomar sessao;
- finalizar sessao na API;
- persistir estado de sessao;
- mostrar menu, ranking e analytics fora do canvas.

O payload entregue ao Phaser continua vindo por `GameBootPayload`.

### Phaser para React

O `PuzzleHudBridge` continua usando o bridge generico do `Play`, mas com eventos mais organizados.

Eventos principais emitidos:

- `drag_start`
- `drop`
- `complete_piece`
- `combo`
- `victory`

Progressos emitidos:

- `moves`
- `wrongDrops`
- `placed`
- `total`
- `combo`
- `maxCombo`
- `scorePreview`
- `completionRatio`

## Interfaces TypeScript Recomendadas

### Estado da peca

```ts
export type PuzzlePieceModel = {
  id: string
  slotId: string
  textureKey: string
  sprite: Phaser.GameObjects.Image
  homeX: number
  homeY: number
  homeScaleX: number
  homeScaleY: number
  targetX: number
  targetY: number
  boardScaleX: number
  boardScaleY: number
  isLocked: boolean
  currentSlotId: string | null
  activeSlotId: string | null
  dropSlotId: string | null
}
```

### Estado do slot

```ts
export type PuzzleSlotModel = {
  id: string
  row: number
  col: number
  textureKey: string
  x: number
  y: number
  width: number
  height: number
  zone: Phaser.GameObjects.Zone
  frame: Phaser.GameObjects.Rectangle
  highlight: Phaser.GameObjects.Rectangle
  state: 'idle' | 'available' | 'invalid' | 'locked'
  placedPieceId: string | null
}
```

### Progresso de gameplay

```ts
export type PuzzleProgressSnapshot = {
  moves: number
  wrongDrops: number
  placed: number
  total: number
  combo: number
  maxCombo: number
  scorePreview: number
  completionRatio: number
}
```

## Gameplay Implementado Nesta Fase

A base do jogo foi elevada para um fluxo mais tatil:

1. Ao iniciar drag:
   - a peca sobe no topo;
   - ganha leve scale;
   - toca som curto de pickup.

2. Cada slot do tabuleiro e um `dropZone` real.

3. Ao entrar em slot:
   - o slot destaca visualmente;
   - se a peca for correta, o destaque e verde;
   - se a peca estiver em slot errado, o destaque pode ficar em vermelho sutil.

4. Ao encaixar corretamente:
   - o system calcula se o drop esta dentro da tolerancia;
   - a peca faz `snap` com tween curto;
   - o slot entra em estado `locked`;
   - toca som de sucesso;
   - gera particulas leves;
   - emite `drop` e `complete_piece`.

5. Ao errar:
   - a peca faz wobble;
   - retorna para o tray automaticamente;
   - o combo reseta;
   - toca som de erro.

6. Ao vencer:
   - a cena faz `camera shake` e `flash`;
   - a imagem completa aparece com reveal curto;
   - toca som final;
   - o bridge finaliza a partida.

## Exemplo Simplificado da Scene

```ts
export class PuzzleScene extends BasePlayScene<PuzzleGameSettings> {
  create() {
    const hud = new PuzzleHudBridge(this.bridge)
    const grid = resolvePuzzleGrid(this.payload.settings.gridSize)
    const layout = buildPuzzleSceneLayout(this, this.payload.settings)
    const slots = PuzzleSlotFactory.create({ scene: this, grid, layout, pieceKeys })
    const pieces = PuzzlePieceFactory.create({ scene: this, grid, layout, slots, sessionSeed, restoredPieceKeys })
    const board = createPuzzleBoardState(grid, layout, slots, pieces)

    const audio = new PuzzleAudioSystem(this)
    const feedback = new PuzzleFeedbackSystem(this)
    const score = new PuzzleScore()
    const placement = new PuzzlePlacementSystem(board, score, feedback, audio, this.payload.settings)
    const victory = new PuzzleVictorySystem(this, board, feedback, audio)

    const drag = new PuzzleDragSystem({
      scene: this,
      board,
      placement,
      feedback,
      audio,
      victory,
      hud,
      getElapsedMs: () => this.elapsedMs(),
      onSolved: () => hud.finish(score.buildResult(this.elapsedMs(), this.payload.settings)),
      emitProgress: (snapshot, phase) => hud.progress(snapshot, phase),
    })

    drag.bind()
    hud.ready(score.buildProgress(board.totalPieces, board.placedCount, this.elapsedMs()))
  }
}
```

## Exemplo de Drag and Drop

```ts
this.input.on('dragstart', (_pointer, gameObject) => {
  const piece = getPieceBySprite(gameObject)
  audio.playPickup()
  feedback.onDragStart(piece)
  hud.move('drag_start', { pieceKey: piece.textureKey })
})

this.input.on('dragenter', (_pointer, gameObject, dropZone) => {
  const piece = getPieceBySprite(gameObject)
  const slot = getSlotByZone(dropZone)
  feedback.highlightSlot(slot, piece.slotId === slot.id ? 'available' : 'invalid')
})

this.input.on('dragend', (_pointer, gameObject, dropped) => {
  const piece = getPieceBySprite(gameObject)
  const slot = dropped ? getDropSlot(piece) : null
  const outcome = placement.resolveDrop(piece, slot, elapsedMs())
  hud.progress(outcome.progress)
})
```

## Exemplo de Feedback Visual e Sonoro

```ts
feedback.animateCorrectPlacement(piece, slot)
audio.playSnap()
hud.move('complete_piece', {
  pieceKey: piece.textureKey,
  slotId: slot.id,
  placed: board.placedCount,
  total: board.totalPieces,
})
```

## Exemplo de Score e Combo

```ts
const score = new PuzzleScore()

score.registerCorrect()
score.registerWrong()

const progress = score.buildProgress(totalPieces, placedPieces, elapsedMs)
const result = score.buildResult(elapsedMs, settings)
```

Observacao:

- o combo ja existe no runtime para UX e telemetria;
- o score final do cliente continua simples e estavel;
- o backend continua soberano na pontuacao oficial.

## Assets de Teste

O projeto agora fica preparado para usar assets em:

```text
apps/web/public/assets/play/puzzle/
  audio/
  icons/
  particles/
  prompts/
```

Arquivos base criados:

- `audio/piece-pickup.wav`
- `audio/slot-hover.wav`
- `audio/slot-snap.wav`
- `audio/placement-error.wav`
- `audio/puzzle-victory.wav`
- `icons/moves.svg`
- `icons/combo.svg`
- `icons/timer.svg`
- `particles/spark-dot.svg`
- `prompts/touch-drag.svg`

Esses assets sao placeholders leves de desenvolvimento. O contrato de caminho ja fica pronto para arte e audio finais.

## Nomenclatura Recomendada

Padrão adotado:

- classes: `PuzzleDragSystem`, `PuzzlePlacementSystem`
- domain: `PuzzleBoard`, `PuzzleScore`, `PuzzleCombo`
- factories: `PuzzlePieceFactory`, `PuzzleSlotFactory`
- ui bridge: `PuzzleHudBridge`
- tipos: `PuzzlePieceModel`, `PuzzleSlotModel`, `PuzzleProgressSnapshot`

Regra pratica:

- nomear por responsabilidade;
- evitar nomes vagos como `helpers`, `manager` ou `utils` para logica principal;
- usar `System` para comportamento baseado em cena;
- usar `Factory` para montagem de objetos de jogo;
- usar `domain` para regra pura e estado.

## Pontos de Extensao Futura

Esta base foi pensada para abrir estes proximos passos sem refatoracao violenta:

- suporte futuro a `4x4` apenas ampliando schema e grid resolver;
- HUD React com `moves`, `errors`, `combo` e progresso em tempo real;
- analytics de `drag_start`, `drop`, `complete_piece`, `combo`, `victory`;
- dificuldade dinamica por tempo medio ou taxa de erro;
- variantes de puzzle com bandeja diferente, referencia opcional e desafios por tempo;
- reutilizacao do padrao `factory + system + bridge` em outros minigames.

## Proximos Passos Recomendados

1. usar o `PuzzleProgressSnapshot` no shell React para exibir `combo` e `completionRatio`;
2. alinhar backend e frontend para permitir `4x4` quando o produto liberar;
3. refinar o layout visual do tray para `3x3` em telas muito baixas;
4. adicionar tutorial curto in-canvas usando prompt de toque;
5. criar testes visuais manuais para drag, snap, erro, restore e victory;
6. reaproveitar o mesmo modelo de `systems` ao nascer o terceiro jogo do `Play`.

## Conclusao Objetiva

O `puzzle` saiu de uma cena grande com regras misturadas para uma base modular, com:

- `Scene` curta;
- `dropZone` real por slot;
- `drag/drop` organizado;
- feedback visual mais rico;
- audio e particulas preparados;
- score e combo desacoplados;
- documentacao e assets de teste no proprio projeto.

Essa e a base correta para continuar evoluindo o puzzle grid do Evento Vivo sem cair em improviso e sem bloquear ranking, analytics ou novos minigames depois.
