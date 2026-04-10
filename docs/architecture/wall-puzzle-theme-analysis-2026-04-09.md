# Wall puzzle theme analysis - 2026-04-09

## Objetivo

Este documento consolida:

- como os templates do telao funcionam hoje;
- como o player esta estruturado;
- quais animacoes e limites reais ja existem;
- como um novo tema `Quebra Cabeca` pode entrar na stack atual;
- como deixar esse tema muito dinamico, com varias imagens surgindo sem degradar o player;
- qual e o limite pratico de quantidade de imagens simultaneas na arquitetura atual.

Este material foi produzido por leitura direta do codigo frontend e backend do modulo `Wall`.

Plano derivado desta analise:

- `docs/architecture/wall-puzzle-theme-execution-plan-2026-04-09.md`

Analise complementar desta rodada:

- `docs/architecture/wall-puzzle-video-and-theme-extensibility-analysis-2026-04-09.md`

Policy derivada desta analise:

- `docs/architecture/wall-puzzle-video-policy-and-theme-capabilities-2026-04-10.md`

---

## Veredito executivo

O wall atual ja tem uma base boa para receber um tema novo:

- runtime central com reducer;
- layouts isolados por componente;
- contratos compartilhados entre backend e frontend;
- preload, cache local e deteccao de asset pronto;
- fallback de performance para devices mais fracos;
- preview do manager reutilizando o mesmo renderer do player.

Mas o tema `Quebra Cabeca` nao cabe como variacao cosmetica de `grid` ou `mosaic`.

Ele pede um passo a mais de arquitetura porque hoje:

1. os layouts multi-item exibem so `3` slots simultaneos;
2. a troca de slots esta acoplada ao avancar do slide principal;
3. o preload foi pensado para "item atual + proximo item", nao para um mural com `8`, `9` ou `12` pecas vivas;
4. nenhum template atual faz trocas assicronas pequenas dentro da mesma cena;
5. nenhum template atual usa mascaramento por forma irregular.

Conclusao pratica:

- sim, da para fazer o tema `Quebra Cabeca` dentro da stack atual;
- a melhor trilha para v1 nao e canvas/WebGL;
- a melhor trilha para v1 e `React + SVG clipPath + layout deterministico + engine local de slots do tema`;
- o tema deve entrar primeiro como layout `image-first`, com videos bloqueados no proprio tema;
- o numero de pecas visiveis precisa ser controlado por budget de performance, nao por entusiasmo visual.

---

## Onde o wall esta estruturado hoje

## Stack atual do wall

## Frontend

O player do wall hoje roda sobre a stack atual de `apps/web`:

- React `18.3.1`
- TypeScript `5.8.3`
- Vite `5.4.19`
- TailwindCSS `3.4.17`
- Framer Motion `12.38.0`
- Pusher JS `8.4.3` para conexao com Laravel Reverb
- React Router DOM `6.30.1`
- TanStack Query `5.83.0`
- Radix UI + shadcn/ui como base de componentes do painel
- Lucide React `0.462.0`
- Vitest `3.2.4` e Playwright `1.59.1` na trilha de testes

Para o wall especificamente, o stack efetivamente usado no player hoje e:

- React + reducer local para runtime
- Framer Motion para transicoes de layout e overlays
- HTML media elements nativos (`img` e `video`)
- Cache API + memoria local para resiliencia de assets
- Pusher protocol / Reverb para realtime
- preview do manager reusando o mesmo renderer do player

## Backend

O backend atual de `apps/api` esta em:

- PHP `>=8.3 <8.4`
- Laravel Framework `13.x`
- Laravel Reverb `1.9`
- Laravel Horizon `5.45`
- Laravel Sanctum `4.3`
- Laravel Pulse `1.7`
- Laravel Telescope `5.19`
- Predis para trilha Redis
- Spatie Laravel Data `4.20`
- Spatie Activitylog `4.12`
- Spatie Medialibrary `11.21`
- Spatie Permission `7.2`

No contexto do wall, a stack de backend efetivamente usada hoje e:

- Laravel + Eloquent
- payloads montados por Resources e factories de payload
- filas e broadcasting para sincronizacao em tempo real
- Redis como camada operacional de realtime/queues quando habilitado
- regras de elegibilidade no modulo `Wall`
- resolucao de variants e URLs via `MediaAssetUrlService`

## Leitura pratica dessa stack para o tema `Quebra Cabeca`

Essa stack favorece bastante uma implementacao de tema baseada em DOM/SVG porque:

- o renderer atual ja e componentizado;
- Framer Motion ja esta no projeto e resolve bem entrada/transicao;
- o preview do manager reaproveita o player;
- o backend ja entrega payload tipado com variantes e metadados;
- o wall ja tem modo performance e trilha de cache local.

Ao mesmo tempo, essa stack ainda nao oferece um "theme system" formal.

Hoje o que existe e:

- uma lista de enums de layout;
- um switch no `LayoutRenderer`;
- layouts soltos por arquivo;
- alguns utilitarios compartilhados.

Para `Quebra Cabeca` isso funciona.
Para uma familia maior de temas, isso ainda e pouco estruturado.

Nuance importante desta validacao:

- a documentacao oficial atual da Motion ja concentra as APIs React sob `motion/react`;
- o repo hoje ainda importa `framer-motion` nos arquivos do wall;
- para esta analise, considerei a semantica oficial atual da lib e o estado real do codigo existente.

## Frontend do player

Arquivos centrais:

- `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`
- `apps/web/src/modules/wall/player/components/LayoutRenderer.tsx`
- `apps/web/src/modules/wall/player/hooks/useWallEngine.ts`
- `apps/web/src/modules/wall/player/engine/reducer.ts`
- `apps/web/src/modules/wall/player/engine/selectors.ts`
- `apps/web/src/modules/wall/player/components/MediaSurface.tsx`

Arquivos de layouts existentes:

- `apps/web/src/modules/wall/player/layouts/FullscreenLayout.tsx`
- `apps/web/src/modules/wall/player/layouts/CinematicLayout.tsx`
- `apps/web/src/modules/wall/player/layouts/SplitLayout.tsx`
- `apps/web/src/modules/wall/player/layouts/PolaroidLayout.tsx`
- `apps/web/src/modules/wall/player/layouts/KenBurnsLayout.tsx`
- `apps/web/src/modules/wall/player/layouts/SpotlightLayout.tsx`
- `apps/web/src/modules/wall/player/layouts/GalleryLayout.tsx`
- `apps/web/src/modules/wall/player/layouts/CarouselLayout.tsx`
- `apps/web/src/modules/wall/player/layouts/MosaicLayout.tsx`
- `apps/web/src/modules/wall/player/layouts/GridLayout.tsx`

O preview do manager reaproveita o mesmo renderer:

- `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.tsx`

## Backend do wall

Arquivos relevantes:

- `apps/api/app/Modules/Wall/Enums/WallLayout.php`
- `apps/api/app/Modules/Wall/Http/Controllers/EventWallController.php`
- `apps/api/app/Modules/Wall/Services/WallRuntimeMediaService.php`
- `apps/api/app/Modules/Wall/Services/WallEligibilityService.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`
- `packages/shared-types/src/wall.ts`

---

## Como os templates do telao funcionam hoje

## 1. Shell base do player

`PlayerShell` cria:

- viewport full-screen;
- background custom ou gradiente padrao;
- overlay escuro global;
- camada para layouts e overlays.

Por cima do layout entram overlays independentes:

- branding;
- QR;
- credito do remetente;
- badge de destaque;
- faixa de conexao/sincronizacao;
- miniaturas laterais;
- caption flutuante em alguns layouts.

Isso e importante para o `Quebra Cabeca` porque o tema novo nao precisa reinventar QR, branding ou sender credit. Ele precisa so ocupar a area principal do layout.

## 2. Estrategia atual de layouts

O renderer hoje trabalha com dois grupos:

### Layouts single-item

- `fullscreen`
- `cinematic`
- `split`
- `polaroid`
- `kenburns`
- `spotlight`
- `gallery`

Esses layouts trocam a midia inteira com `AnimatePresence` no `LayoutRenderer`.

Animacoes de troca suportadas:

- `fade`
- `slide`
- `zoom`
- `flip`
- `none`

### Layouts multi-item

- `carousel`
- `mosaic`
- `grid`

Esses layouts nao usam a mesma animacao global do slide.
Eles fazem transicao slot-a-slot dentro do proprio layout.

Limite atual confirmado:

- `MULTI_ITEM_SLOT_COUNT = 3`

Ou seja:

- hoje nenhum layout principal do wall exibe `4+` midias simultaneas no palco central;
- o maximo simultaneo no layout central e `3`.

## 3. Resumo dos temas atuais

### `fullscreen`

- uma midia central;
- blur de fundo reaproveitando a mesma imagem;
- foco em contemplacao;
- sem movimento proprio alem da transicao entre slides.

### `cinematic`

- uma midia;
- fundo blur forte;
- moldura dupla glass/frosted;
- visual premium mas estatico.

### `split`

- uma midia + bloco editorial lateral;
- bom para caption;
- nao e um layout de alta densidade visual.

### `polaroid`

- uma midia em papel/foto impressa;
- rotacao leve fixa;
- badge de destaque;
- tema mais editorial que dinamico.

### `kenburns`

- uma midia;
- pan/zoom continuo por CSS;
- e o layout atual mais "vivo" para imagem unica.

Animacoes atuais do Ken Burns:

- `kb-zoom-in`
- `kb-zoom-out`
- `kb-pan-left`
- `kb-pan-right`

### `spotlight`

- uma midia com glow radial;
- caption glass no rodape;
- forte impacto visual, baixa densidade.

### `gallery`

- uma midia em moldura de galeria;
- usa linguagem de museu;
- baixa dinamica por design.

### `carousel`

- `3` slots;
- centro grande, laterais menores;
- usa perspectiva 3D e dimming nas laterais;
- e o tema multi-item mais cenografico.

### `mosaic`

- `3` slots;
- 1 slot grande + 2 menores;
- boa variacao de escala;
- transicao simples por fade em cada slot.

### `grid`

- `3` slots iguais;
- cards glassmorphism;
- visual limpo, mas sem narrativa.

## 4. O que ainda nao existe em nenhum tema atual

Hoje nao existe no wall:

- mascaramento irregular de imagem;
- pecas encaixadas entre si;
- mural com `6`, `9` ou `12` slots centrais;
- entradas em micro-bursts;
- substituicao assicrona de varias pecas dentro da mesma cena;
- engine local de board persistente por layout;
- face brackets vindos de metadata por slot.

---

## Como a fila e a quantidade de imagens funcionam hoje

## 1. Limite total da fila no runtime

No contrato atual:

- `queue_limit` vai de `5` ate `500`
- default backend: `100`

Fluxo real:

1. o backend carrega midias aprovadas/publicadas;
2. ordena por `published_at desc`, depois `id desc`;
3. aplica elegibilidade;
4. corta pela `queue_limit`;
5. o player recebe essa lista no boot;
6. em tempo real, novos itens tambem sao truncados localmente se ultrapassarem o limite.

Conclusao:

- o sistema ja suporta fila operacional grande;
- isso nao significa que ele suporte muitas imagens simultaneas na tela.

## 2. Quantidade visivel hoje

### Palco principal

- single-item layouts: `1`
- multi-item layouts: `3`

### Miniaturas laterais

Quando habilitadas:

- `useSideThumbnails` seleciona ate `8` itens prontos
- distribui em `4` de cada lado

Na pratica o wall pode ter:

- `1` midia principal + `8` thumbs laterais
- ou `3` midias principais + sem thumbs laterais para multi-layout

Mas essas miniaturas laterais:

- nao fazem parte do layout principal;
- nao sao pecas hero;
- servem como contexto e expectativa da fila.

## 3. Limites de preload e cache hoje

O engine hoje faz:

- `primeWallAsset` para item atual + top `4` itens idle;
- `preloadNextItem()` para o proximo item previsto;
- cache local em memoria + Cache API;
- deteccao de `assetStatus = ready/loading/stale/error`.

Isso foi desenhado para:

- um slide principal;
- no maximo `3` slots principais;
- uma troca por vez.

Nao foi desenhado para:

- `9` pecas novas entrando quase ao mesmo tempo;
- `12` imagens com panning constante;
- board com reshuffle frequente.

## 4. Politica de video hoje

O wall aceita video na fila, mas:

- layouts multi-slot caem para single-item quando `video_multi_layout_policy = disallow`;
- esse e o default operacional atual.

Para `Quebra Cabeca`, isso e um sinal claro:

- v1 deve ser image-first;
- video deve ficar bloqueado no tema, ou cair para outro layout.

---

## Limitacoes reais da arquitetura atual para um tema puzzle

### L1. O enum de layout ainda nao conhece `puzzle`

Hoje os contratos aceitam:

- `auto`
- `polaroid`
- `fullscreen`
- `split`
- `cinematic`
- `kenburns`
- `spotlight`
- `gallery`
- `carousel`
- `mosaic`
- `grid`

Para o tema novo entrar direito, precisamos adicionar `puzzle` em:

- `packages/shared-types/src/wall.ts`
- `apps/api/app/Modules/Wall/Enums/WallLayout.php`
- `apps/web/src/modules/wall/manager-config.ts`
- `apps/web/src/modules/wall/player/components/LayoutRenderer.tsx`

## L2. O multi-slot atual esta preso em `3`

`LayoutRenderer` usa:

- `MULTI_ITEM_SLOT_COUNT = 3`

Isso serve para `carousel`, `mosaic` e `grid`, mas e pequeno demais para puzzle.

## L3. O multi-slot atual troca uma peca por advance

`useMultiSlot()` faz round-robin simples:

- uma troca por trigger;
- trigger acoplado ao advance do item principal.

Para puzzle, isso e pouco dinamico.

Se quisermos "varias imagens surgindo", o tema precisa:

- ter seu proprio clock local;
- aceitar bursts pequenos;
- preencher slots vazios sem esperar o proximo slide inteiro.

## L4. O preload atual nao cobre um board denso

Hoje a stack sabe muito bem:

- renderizar o item atual;
- preparar o proximo;
- manter poucos assets quentes.

Puzzle pede:

- prefetch em janela;
- budget por slot visivel;
- politicas de substituicao mais conservadoras.

## L5. O player tem modo performance

`usePerformanceMode()` reduz efeitos quando o device e fraco ou pede reduced motion.

Isso impacta diretamente o puzzle:

- o tema precisa ter `tiers` de complexidade;
- nao pode assumir que toda TV-box aguenta `12` pecas animadas com blur, glow e brackets ao mesmo tempo.

---

## Avaliacao tecnica da ideia de mascaramento

## Melhor abordagem para v1

Recomendacao:

- usar `SVG clipPath` inline e responsivo;
- nao usar `canvas/WebGL` na primeira entrega;
- nao depender de `mask-image: url(...)` como mecanismo principal.

## Por que `SVG clipPath` encaixa melhor na stack atual

Porque o wall ja e:

- React;
- DOM-driven;
- composto por componentes;
- animado com Framer Motion;
- testado em volta de `MediaSurface`.

Com `clipPath`, conseguimos:

- manter a arquitetura do player;
- encapsular cada peca como componente React;
- continuar usando `img` e `video` normais;
- animar entrada, opacidade, escala e panning sem trocar de tecnologia.

## Por que eu evitaria `mask-image` como base principal

`mask-image` e usavel, mas para esse caso traz mais friccao:

- dependencia maior de asset externo;
- mais sensibilidade a CORS/origem;
- comportamento menos previsivel entre browsers e TVs;
- menos controle sobre reuso de formas parametrizadas.

## Por que eu evitaria `canvas/WebGL` em v1

WebGL faria sentido so se a meta fosse:

- centenas de sprites;
- fisica;
- shaders;
- composicao muito livre em tempo real.

Nao e o caso hoje.

O custo de v1 em WebGL seria:

- engine paralela;
- debug pior;
- preview do manager mais caro;
- menos reaproveitamento de `MediaSurface`, preload, cache e contratos ja prontos.

---

## Conceito recomendado para o tema `Quebra Cabeca`

## 1. Nao usar auto-fill generico

A ideia de `grid-template-columns: repeat(auto-fill, minmax(...))` e boa para paginas responsivas comuns, mas para o player do telao eu nao recomendo isso como base principal.

Motivo:

- o wall precisa ser deterministico;
- o preview do manager precisa bater com o player;
- as pecas precisam encaixar entre vizinhos;
- a distribuicao visual deve parecer curada, nao casual.

Recomendacao:

- usar `board presets` fixos e responsivos.

Exemplo de presets:

- `compact`: 6 pecas
- `standard`: 9 pecas
- `immersive`: 12 pecas

Cada preset define:

- linhas e colunas;
- slot hero;
- bordas internas/externas;
- combinacao de tabs e insets entre vizinhos;
- ordem de preenchimento;
- slots elegiveis para destaque ou logo.

## 2. Estrutura da board

O tema deve ser organizado em tres camadas:

### `PuzzleLayout`

Responsavel por:

- escolher preset;
- ler modo performance;
- montar board;
- disparar cadencia local de entrada/substituicao;
- controlar fila local de pecas visiveis.

### `PuzzlePiece`

Responsavel por:

- aplicar `clipPath`;
- renderizar imagem dentro da peca;
- fazer `object-fit: cover`;
- animar entrada;
- fazer drift/panning suave;
- mostrar glow/borda/linha de encaixe;
- opcionalmente desenhar brackets de IA.

### `usePuzzleBoard`

Responsavel por:

- receber o pool de `allItems`;
- decidir quais itens entram primeiro;
- controlar `incomingQueue`;
- preencher slots vazios;
- trocar slots antigos em micro-bursts;
- evitar duplicata visivel;
- preservar uma ou duas pecas estaveis para nao parecer reset.

---

## Como o tema pode ficar muito dinamico sem parecer caos

## 1. Dinamica recomendada

O segredo nao e trocar tudo o tempo inteiro.
O segredo e combinar:

- persistencia de board;
- entrada cadenciada;
- pequenas substituicoes locais;
- drift lento dentro de cada peca.

Fluxo visual recomendado:

1. o board nasce com pecas vazias e uma peca ancora;
2. novas imagens entram em `micro-bursts` de `1` a `2` pecas por vez;
3. cada peca faz um "snap in" curto, como se encaixasse;
4. pecas antigas continuam visiveis por um tempo, criando memoria visual;
5. a imagem dentro da peca faz drift lento, especialmente em retrato;
6. periodicamente o board troca poucas pecas, nao todas.

Isso gera:

- sensacao de fluxo continuo;
- varias imagens surgindo;
- menos cansaco visual;
- menos custo de decode do que um reshuffle completo.

## 2. Cadencia sugerida

Para um tema puzzle, eu recomendo separar duas cadencias:

### Cadencia macro

- sincronizada com `interval_ms` do wall
- serve para "ciclo principal" do tema

### Cadencia micro

- interna do tema
- serve para encaixe de pecas novas

Exemplo seguro para v1:

- `interval_ms` do wall: `8000ms` a `12000ms`
- micro-burst interno: a cada `900ms` a `1400ms`
- maximo de `2` trocas por burst
- no maximo `3` slots entrando em animacao ao mesmo tempo

## 3. Politica de substituicao de slots

A board deve seguir esta ordem:

1. preencher slots vazios;
2. preservar peca hero/ancora;
3. preservar featured por mais tempo;
4. substituir a peca mais antiga nao fixada;
5. evitar trocar duas pecas vizinhas no mesmo burst;
6. evitar repetir remetente visivel quando existir alternativa.

Isso conversa bem com a fairness que o wall ja usa no selector principal.

## 4. Tratamento de retrato x paisagem

A sua intuicao esta correta:

- usar `object-fit: cover`
- e somar `panning` lento dentro da mascara

Essa combinacao resolve:

- foto de WhatsApp vertical em peca horizontal;
- corte mais cinematografico;
- maior sensacao de vida mesmo em imagem estatica.

Recomendacao:

- retrato em slot horizontal: drift vertical leve
- paisagem em slot vertical: drift horizontal leve
- quadradas: drift minimo ou nenhum

O importante e:

- drift muito sutil;
- duracao longa;
- nunca parecer "scanner nervoso".

## 5. Peca ancora central

Vale muito a pena.

Opcoes para a peca ancora:

- logo do evento;
- QR em modo reduzido;
- nome do evento;
- slogan;
- "envie sua foto" quando a fila estiver baixa.

Essa ancora ajuda o layout a:

- ter centro visual;
- nao parecer ruido puro;
- aceitar muitos encaixes sem perder identidade.

---

## Contrato global de motion do wall

O `Quebra Cabeca` nao deveria nascer com um sistema de motion isolado.
O wall inteiro precisa de um contrato global de animacao.

Recomendacao:

- subir `MotionConfig` para o shell do player ou para `WallPlayerRoot`;
- cada tema declarar tokens proprios de `enter`, `exit`, `burst`, `drift`, `shared`, `reducedMotion` e `visualDuration`;
- manter uma assinatura visual por tema, mas dentro de uma politica comum de tempo e degradacao;
- aplicar o mesmo contrato no player vivo e no preview do manager.

Contrato sugerido:

```ts
export interface WallMotionTokens {
  enter: Transition;
  exit: Transition;
  burst: Transition;
  drift: {
    axis: 'x' | 'y' | 'xy';
    distance: number;
    durationMs: number;
  };
  shared?: Transition;
  reducedMotion: 'always' | 'user' | 'never';
  visualDurationMs: number;
}
```

Beneficio real:

- `fullscreen`, `cinematic`, `grid`, `mosaic`, `carousel` e `puzzle` deixam de inventar fisicas incompativeis;
- reduced motion passa a ser policy, nao excecao espalhada;
- fica mais barato introduzir temas premium sem quebrar a assinatura do wall.

Regra importante:

- o contrato de motion deve ser do wall inteiro;
- `Quebra Cabeca` so seria o primeiro tema a exigir isso com mais intensidade.

## Como implementar motion sem degradar o render React

Drift, parallax, snap-in e micro-bursts nao deveriam depender de `setState` frequente por slot.

Implementacao recomendada:

- usar `MotionValue` para deslocamentos e opacidade continua;
- usar `useSpring` para amortecer drift e snap-in;
- usar `useAnimationFrame` ou `useAnimate` para loops locais e bursts curtos;
- manter React responsavel pela identidade do slot, nao pelo tick da animacao.

Regra pratica:

- troca de item, mudanca de preset e entrada/saida de slot continuam eventos React;
- drift continuo, parallax e burst local devem rodar fora do ciclo normal de render.

Isso e importante porque:

- reduz churn em boards com `6` a `12` pecas;
- preserva responsividade do realtime;
- mantem as animacoes fortes sem pagar com re-render desnecessario.

## Politica oficial de preservacao e reset do board

O tema precisa de uma regra formal de identidade.
Sem isso, o board tende a acumular reset acidental e estado fantasma.

O board deve preservar estado quando mudarem:

- entrada incremental de itens por realtime;
- remocao incremental de itens da fila;
- featured ou metadata editorial que nao mudem a identidade do tema;
- warmup de cache, readiness de asset ou retry local;
- reorder interno do proprio scheduler do board.

O board deve resetar quando mudarem:

- `eventId`;
- `layout`;
- `preset`;
- `themeVersion`;
- `performanceTier`;
- politica de `reducedMotion`.

Implementacao recomendada:

- definir um `boardInstanceKey` explicito, por exemplo `eventId:layout:preset:themeVersion:performanceTier:reducedMotion`;
- aplicar essa mesma regra no player e no preview do manager.

---

## Proposta concreta de arquitetura para o tema

## V1 recomendada

### Escopo

- imagens apenas
- sem video dentro do puzzle
- sem deteccao de rosto no client
- `9` pecas no preset principal
- `6` pecas no modo performance
- `12` pecas apenas em modo premium

### Comportamento

- board persistente;
- pecas entram por burst;
- drift leve nas imagens;
- clipPath irregular por peca;
- glow/linha sutil nos encaixes;
- peca central opcional;
- featured pode ocupar slot hero por mais tempo.

### Estrutura sugerida

- `apps/web/src/modules/wall/player/layouts/PuzzleLayout.tsx`
- `apps/web/src/modules/wall/player/layouts/PuzzlePiece.tsx`
- `apps/web/src/modules/wall/player/layouts/puzzle.ts`
- `apps/web/src/modules/wall/player/layouts/puzzle.css`
- `apps/web/src/modules/wall/player/hooks/usePuzzleBoard.ts`

### Contrato minimo

Adicionar `puzzle` em:

- `packages/shared-types/src/wall.ts`
- `apps/api/app/Modules/Wall/Enums/WallLayout.php`

### Manager

Atualizar:

- `apps/web/src/modules/wall/manager-config.ts`

Como o manager ja consome os layouts do backend e o preview usa `LayoutRenderer`, o novo tema entra de forma natural depois disso.

## V2 recomendada

Depois da v1 validada:

- `layout_config` ou `theme_config` em JSON no backend;
- presets `compact`, `standard`, `immersive`;
- configuracao de ancora;
- controle de intensidade de animacao;
- modo "burst high";
- face brackets por metadata do servidor.

---

## Estrutura recomendada das pecas

## 1. Shape system

Cada peca deve ter:

- um `pieceId`;
- uma posicao `row/col`;
- um `shapeVariant`;
- metadados de bordas:
  - `top`
  - `right`
  - `bottom`
  - `left`

Cada borda pode ser:

- `flat`
- `tab`
- `inset`

Regra:

- a borda direita de uma peca deve casar com a esquerda da peca vizinha;
- a borda inferior deve casar com a superior da peca de baixo.

Isso pode ser gerado de forma deterministica por preset.

Para nao criar custo oculto de manutencao, eu recomendo:

- manter um catalogo pequeno de variantes reaproveitaveis, por exemplo `6` a `10` shapes base;
- normalizar os paths para uso relativo;
- reutilizar `defs` por variante e orientacao, em vez de gerar um SVG unico por slot;
- tratar `clipPathUnits="objectBoundingBox"` como padrao do sistema.

## 2. Mascaramento

Cada variante pode virar um `clipPath` SVG normalizado.

Exemplo de conceito:

```tsx
<svg width="0" height="0" aria-hidden="true">
  <defs>
    <clipPath id={clipId} clipPathUnits="objectBoundingBox">
      <path d={normalizedPiecePath} />
    </clipPath>
  </defs>
</svg>

<div className="piece-frame">
  <div className="piece-media" style={{ clipPath: `url(#${clipId})` }}>
    <img src={item.url} className="piece-image" alt="" />
  </div>
</div>
```

Regra de implementacao:

- o `clipPath` deve ser deduplicado por `shapeVariant`;
- o slot referencia a definicao existente;
- o tema nao deve inflar o DOM com dezenas de `defs` equivalentes.

## 3. Camadas visuais por peca

Cada peca deve ter:

- borda base;
- sombra interna leve;
- brilho de encaixe;
- midia mascarada;
- overlay de caption curto opcional;
- overlay de brackets opcional.

Nao recomendo caption completo em todas as pecas.

Melhor:

- mostrar sender/caption so em slot hero;
- ou em hover/estado editorial no futuro;
- ou em uma barra externa da board.

---

## Quantidade de imagens: limites reais e recomendacao

## Limite funcional do sistema hoje

O sistema atual aguenta fila de ate `500` itens, mas isso nao e o mesmo que renderizar `500` itens.

O limite relevante para o tema puzzle e:

- quantidade de imagens simultaneas montadas;
- quantidade de animacoes concorrentes;
- quantidade de imagens precisando decode ao mesmo tempo;
- qualidade do asset servido para o wall.

## Recomendacao operacional por tier

### Tier 1 - performance / TV-box fraca

- `6` pecas visiveis
- sem brackets
- sem blur pesado
- sem troca simultanea acima de `1` peca

### Tier 2 - FHD padrao

- `8` ou `9` pecas visiveis
- drift leve
- bursts de `1` a `2` pecas
- no maximo `2` animacoes de entrada fortes ao mesmo tempo

### Tier 3 - premium / hardware forte / 4K

- `10` a `12` pecas visiveis
- glow melhor
- burst de `2` pecas
- overlays extras opcionais

## Limite que eu nao recomendaria para v1

Eu nao recomendo com a stack atual:

- `15+` pecas hero simultaneas;
- video misturado nas pecas;
- client-side face detection rodando em todas as pecas;
- reshuffle total da board a cada slide;
- varios blur layers por peca.

Isso aumentaria demais:

- decode paralelo;
- churn de render;
- custo de entrada;
- risco de jitter em hardware real de evento.

## Orcamento de runtime recomendado para a v1

Os tiers acima ainda estao conceituais.
Para o tema ficar operavel em evento real, eu recomendo transformar isso em budget tecnico explicito.

Importante:

- estes numeros sao metas iniciais de engenharia;
- eles ainda precisam ser validados com profiling real em hardware de evento.

### Tier 1 - TV-box fraca

- no maximo `1` imagem em decode ao mesmo tempo;
- no maximo `1` peca entrando com animacao forte;
- no maximo `1` troca por micro-burst;
- timeout de readiness por peca: `1200ms`;
- downgrade automatico se `2` bursts consecutivos estourarem esse budget.

### Tier 2 - FHD padrao

- no maximo `2` imagens em decode ao mesmo tempo;
- no maximo `2` pecas entrando com animacao forte;
- no maximo `2` trocas por micro-burst;
- timeout de readiness por peca: `900ms`;
- downgrade automatico se `2` bursts consecutivos estourarem esse budget ou se `3` slots ficarem em espera ao mesmo tempo.

### Tier 3 - hardware forte / 4K

- no maximo `3` imagens em decode ao mesmo tempo;
- no maximo `3` pecas entrando com animacao forte;
- no maximo `2` trocas por micro-burst;
- timeout de readiness por peca: `700ms`;
- downgrade automatico se o palco cair abaixo da area util prevista ou se o budget de decode for estourado de forma recorrente.

## Politica de cache sem matar o realtime

Cache no wall deve reduzir conexoes e redownload de asset.
Ele nao deve atrasar a percepcao de realtime.

Recomendacao:

- cachear a janela quente do tema, nao a fila inteira;
- considerar como janela quente: pecas visiveis + proximas `1` ou `2` pecas elegiveis para burst;
- ao receber evento realtime, atualizar primeiro o pool de itens e o scheduler local;
- disparar preload dirigido so para o item realmente elegivel para entrar;
- nunca exigir refetch completo da fila para o board reagir a um item novo.

Leitura pratica:

- realtime continua definindo o que entrou na fila agora;
- cache reduz o custo de asset para o que ja foi escolhido pelo scheduler;
- o compromisso correto e `metadata ao vivo + asset sob demanda`, nao `fila congelada para proteger o cache`.

## Readiness de asset: regra recomendada

Para um tema denso, eu recomendaria esta regra:

- item novo so e considerado `ready` quando terminar `fetch + decode`, nao apenas `fetch`;
- se o asset ainda nao estiver pronto, a peca atual permanece estavel;
- se o timeout de readiness estourar, o slot e pulado temporariamente e volta para retry curto;
- `fetchpriority` deve ser usado so para ancora, hero e pecas do proximo burst.

---

## IA, brackets e rastreamento de rosto

## Recomendacao

Para telao ao vivo, eu nao usaria `face-api.js` no navegador como base.

Motivo:

- custo imprevisivel;
- piora quando varias imagens entram;
- aumenta disputa de CPU com animacao e decode.

Melhor caminho:

- detectar rostos no servidor;
- enviar bounding boxes junto do payload;
- desenhar os brackets no front com SVG overlay.

Isso encaixa melhor no wall porque:

- preserva performance;
- deixa o efeito previsivel;
- permite cachear metadata junto da midia.

Mas isso e fase 2.

A v1 do `Quebra Cabeca` nao depende disso para funcionar bem.

---

## Como o tema entra no codigo sem quebrar o resto

## Passos minimos

### Backend e contratos

1. adicionar `puzzle` ao enum compartilhado em `packages/shared-types/src/wall.ts`;
2. adicionar `Puzzle` ao enum backend em `apps/api/app/Modules/Wall/Enums/WallLayout.php`;
3. expor o novo label no endpoint de options do wall;
4. garantir que o manager consiga salvar `layout = puzzle`.

### Frontend do player

1. criar `PuzzleLayout.tsx`;
2. criar `PuzzlePiece.tsx`;
3. criar `usePuzzleBoard.ts`;
4. ligar o novo case no `LayoutRenderer`;
5. bloquear video no proprio tema:
   - se `media.type === video`, cair para `fullscreen` ou `cinematic`.

### Frontend do manager

1. adicionar label em `manager-config.ts`;
2. validar o preview no `WallPreviewCanvas`.

Como o preview usa o mesmo renderer do player, isso e uma vantagem importante.

---

## Capabilities incompativeis do `Quebra Cabeca` v1

O manager nao deveria tratar capability como texto informativo.
Ele deveria bloquear combinacoes que a v1 do tema nao suporta bem.

Para a primeira entrega, eu recomendaria bloquear:

- video dentro do `puzzle`;
- side thumbnails ao mesmo tempo que o board puzzle;
- floating caption por peca;
- blur pesado por slot;
- face overlay client-side;
- autoplay de audio ligado a pecas do board;
- qualquer configuracao que tente misturar `puzzle` com politica de multi-video.

Fallback recomendado:

- se o item elegivel for video, cair para `fullscreen` ou `cinematic`;
- se reduced motion estiver ativo, cortar drift continuo e layout animation mais agressiva;
- se o palco perder area util, cair de `9` para `6` pecas antes de forcar mais degrade visual.

---

## Melhorias recomendadas na arquitetura de templates

Se a meta for criar o `Quebra Cabeca` e deixar o wall pronto para varios temas novos depois, eu recomendo aproveitar essa entrega para subir um nivel de arquitetura.

Hoje o sistema tem layouts.
O proximo passo e ter um pequeno sistema de temas do wall.

## 1. Criar um registro formal de layouts

Hoje o cadastro do layout esta espalhado em:

- enum compartilhado;
- enum backend;
- config do manager;
- switch do `LayoutRenderer`.

Melhoria recomendada:

- criar um `layout registry` no frontend com metadados por tema;
- opcionalmente espelhar uma estrutura simples no backend para labels/capabilities.

Exemplo de metadados por layout:

- `value`
- `label`
- `kind: single | board | cinematic`
- `supportsVideo`
- `supportsSideThumbnails`
- `supportsFeaturedHero`
- `recommendedPerformanceTier`
- `defaultTransition`
- `previewStrategy`

Isso facilita:

- renderizacao condicional;
- validacao no manager;
- preview mais inteligente;
- feature flags por tema.

## 2. Separar layout metadata de implementacao visual

Hoje o layout e basicamente "nome + componente".

Melhoria recomendada:

- cada tema ter um manifesto tecnico leve.

Exemplo:

```ts
export interface WallLayoutDefinition {
  id: WallLayout;
  label: string;
  renderer: React.ComponentType<WallLayoutProps>;
  capabilities: {
    supportsVideo: boolean;
    supportsMultiItem: boolean;
    supportsDynamicBoard: boolean;
  };
  defaults: {
    slotCount?: number;
    transitionEffect?: WallTransition;
  };
}
```

Isso reduz o acoplamento ao `switch/case` manual e deixa novos temas mais baratos de adicionar.

## 3. Criar um subsistema compartilhado para layouts de board

`carousel`, `mosaic`, `grid` e o futuro `puzzle` compartilham uma natureza parecida:

- varias midias vivas ao mesmo tempo;
- slots com reposicao local;
- politica de preenchimento;
- transicao por celula;
- necessidade de evitar duplicatas visiveis.

Melhoria recomendada:

- extrair um subsistema pequeno, mas formal, para board layouts.

Exemplos de componentes/hooks compartilhados:

- `useWallBoard()`
- `BoardSlot`
- `BoardBurstScheduler`
- `BoardSelectionPolicy`
- `BoardTransitionLayer`
- `BoardEmptyCell`

O `Quebra Cabeca` deveria ser o primeiro consumidor dessa base, em vez de nascer como codigo totalmente isolado.

## 4. Criar um `ThemeSurface` compartilhado

Hoje `MediaSurface` resolve bem imagem e video, mas o tema puzzle vai precisar de mais uma camada:

- `clipPath`
- drift/panning
- reveal controlado
- loading/error visual por peca
- possivel overlay de brackets

Melhoria recomendada:

- criar um wrapper compartilhado para surfaces tematicas, por exemplo `ThemeMediaSurface`.

Ele pode unificar:

- `onLoad` / `onError`
- fade-in
- drift presets
- cover policy por orientacao
- poster fallback
- medicao de readiness por slot

Isso facilitaria nao so o `Quebra Cabeca`, mas qualquer tema com midia mais tratada.

## 5. Introduzir `theme_config` no contrato do wall

Hoje o contrato de settings do wall conhece:

- `layout`
- `transition_effect`
- toggles gerais
- politicas de video

Mas nao existe espaco formal para configuracao do tema.

Melhoria recomendada:

- adicionar um campo futuro como `theme_config` ou `layout_config`.

Exemplos de configuracao que caberiam bem:

- preset do board
- slot hero on/off
- intensidade de animacao
- modo performance forcado
- ancora central
- quantidade maxima de trocas por burst
- overlays especificos do tema

Isso evita proliferar dezenas de colunas novas no settings quando surgirem mais temas.

## 6. Criar regras formais de capability por tema

Hoje algumas regras estao implicitas no codigo.

Exemplo:

- multi-layout com video depende de `video_multi_layout_policy`;
- side thumbnails sao desligadas em multi-layout;
- reduzimos efeitos em devices fracos.

Melhoria recomendada:

- formalizar capabilities por tema.

Exemplos:

- `supportsVideo`
- `supportsAudio`
- `supportsFaceOverlay`
- `supportsSideThumbnails`
- `supportsRealtimeBurst`
- `supportsBackgroundBlur`
- `supportsFloatingCaption`

Isso ajudaria o manager a:

- esconder opcoes irrelevantes;
- evitar combinacoes ruins;
- mostrar previews mais honestos.

## 7. Criar um pipeline de preview e teste para temas

Hoje o preview do manager e uma vantagem importante, mas para escalar temas eu recomendo formalizar testes de layout.
O manager faz parte do problema de performance e previsibilidade, nao so o player vivo.

Melhorias recomendadas:

- fixtures de midia por orientacao
- fixture com pouca fila
- fixture com fila cheia
- fixture com `featured`
- fixture com reconnect/realtime
- screenshot tests por tema

O alvo aqui e validar:

- encaixe visual;
- safe areas;
- sobreposicao com QR/branding;
- comportamento em reduced motion;
- degradacao em modo performance.

## 8. Criar um checklist oficial para novos templates

Vale muito a pena deixar a propria doc com um checklist de produto/engenharia.

Cada novo tema deveria responder:

1. suporta video?
2. suporta featured hero?
3. quantos slots simultaneos suporta por tier?
4. depende de `theme_config`?
5. precisa de preload em janela?
6. funciona no preview do manager?
7. tem fallback em reduced motion?
8. conflita com QR, branding, sender credit ou side thumbs?

Isso reduz o custo cognitivo de cada novo layout.

## 9. Padronizar uma pasta de temas

Hoje todos os layouts ficam em `player/layouts`.

Melhoria recomendada quando o numero de temas crescer:

- separar layouts simples de temas mais estruturados.

Exemplo:

- `player/layouts/single/*`
- `player/layouts/board/*`
- `player/layouts/themes/puzzle/*`

Ou:

- `player/themes/<theme-name>/`

com:

- `index.tsx`
- `definition.ts`
- `config.ts`
- `hooks.ts`
- `theme.css`
- `README.md`

Isso deixa o `Quebra Cabeca` mais organizado e abre caminho para outros temas premium depois.

## 10. Melhoria recomendada especifica para o `Quebra Cabeca`

Se eu fosse desenhar a entrega pensando no futuro, eu faria assim:

### Entrega do tema

- `PuzzleLayout`
- `PuzzlePiece`
- `usePuzzleBoard`
- `puzzle-shapes.ts`
- `puzzle.css`

### Entrega de fundacao reaproveitavel

- `wall-layout-registry.ts`
- `ThemeMediaSurface.tsx`
- `board-layout.ts`
- `theme_config` no contrato, nem que inicialmente vazio

Assim o custo extra da entrega gera infraestrutura real para os proximos templates.

---

## Validacao cruzada com documentacao oficial

Cruzei as sugestoes de melhoria com a documentacao oficial das libs e APIs que ja estao na stack. O resultado mais importante nao e "aceitar tudo", e sim entender o que casa com o wall atual e o que ainda seria sofisticacao prematura.

## Motion / Framer Motion

Validacoes relevantes:

- a documentacao oficial atual concentra as APIs React sob `motion/react`, mas o repo ainda usa `framer-motion`;
- isso nao invalida a analise: os conceitos de `MotionConfig`, `useSpring`, `useAnimate`, `useAnimationFrame` e `AnimatePresence` continuam aplicaveis na stack atual;
- `MotionConfig` e o ponto certo para politicas globais de transicao e reduced motion por arvore de componentes;
- `useReducedMotion` existe como trilha oficial da lib e faz sentido virar capability de tema;
- springs com `visualDuration` ajudam a alinhar animacoes com tempo visual previsivel;
- `useSpring`, `useAnimate` e `useAnimationFrame` sao a trilha certa para movimento continuo e atualizacao fora do ciclo normal de render do React;
- `AnimatePresence` com `mode="popLayout"` e valido para board layouts, mas componentes customizados precisam expor `forwardRef`, e o pai precisa estar corretamente posicionado;
- `layoutId` / `LayoutGroup` fazem sentido para shared transitions premium, mas isso e refinamento de segunda fase, nao fundacao obrigatoria da v1.

## React

Validacoes relevantes:

- `key` continua sendo o mecanismo correto para preservar ou resetar subarvores quando a identidade visual do board muda;
- `useSyncExternalStore` existe exatamente para integrar fontes externas com snapshots consistentes;
- `startTransition` / `useTransition` sao a trilha oficial para updates nao bloqueantes;
- `memo`, `useMemo` e menos `Effect` desnecessario continuam sendo a recomendacao base para reduzir re-render e fragilidade;
- React continua desaconselhando ajustar estado derivado via effects quando isso puder ser resolvido por derivacao mais direta.

Nuance importante:

- `useSyncExternalStore` e uma boa trilha para realtime se o player comecar a mostrar tearing, churn ou reconciliacoes mais pesadas;
- sem esse sintoma, eu manteria isso como melhoria condicional e nao como P0.

## TanStack Query

Validacoes relevantes:

- os defaults sao agressivos e queries nascem stale por padrao;
- `placeholderData` e util para suavizar UI enquanto dados novos chegam;
- `initialData` serve para bootstrap confiavel, nao para mascarar loading de qualquer fluxo;
- `prefetchQuery` e apropriado para preparar preview/configuracao antes da interacao do usuario.

Leitura pratica para o wall:

- o manager precisa entrar nessa conversa, porque preview piscando tambem degrada a confianca no sistema de temas;
- cache de query deve aquecer configuracao e preview sem competir com o playback vivo.

## Vite

Validacao relevante:

- `import.meta.glob` continua sendo o caminho oficial para modulos lazy e chunks separados por tema.

## Plataforma web / APIs do navegador

Validacoes relevantes:

- `HTMLImageElement.decode()` e um passo real para considerar imagem pronta de forma mais limpa;
- `fetchpriority` e so um hint e precisa ser usado de forma seletiva;
- `content-visibility`, `contain` e `contain-intrinsic-size` fazem sentido especialmente no manager e em paines fora do palco ativo;
- `ResizeObserver` continua sendo a API certa para reagir ao tamanho real do container;
- `clipPathUnits="objectBoundingBox"` e a melhor base para shapes responsivos e reaproveitaveis no puzzle.

## Fontes oficiais usadas nesta validacao

- Motion docs: `https://motion.dev/docs/react-motion-config`
- Motion docs: `https://motion.dev/motion/transition`
- Motion docs: `https://motion.dev/docs/react-use-animate`
- Motion docs: `https://motion.dev/docs/react-use-spring`
- Motion docs: `https://motion.dev/docs/react-use-animation-frame`
- Motion docs: `https://motion.dev/motion/use-reduced-motion/`
- Motion docs: `https://motion.dev/motion/animate-presence/`
- React docs: `https://react.dev/learn/preserving-and-resetting-state`
- React docs: `https://react.dev/learn/you-might-not-need-an-effect`
- React docs: `https://react.dev/reference/react/useSyncExternalStore`
- React docs: `https://react.dev/reference/react/startTransition`
- React docs: `https://react.dev/reference/react/useTransition`
- React docs: `https://react.dev/reference/react/memo`
- React docs: `https://react.dev/reference/react/useMemo`
- TanStack Query docs: `https://tanstack.com/query/latest/docs/react/guides/important-defaults`
- TanStack Query docs: `https://tanstack.com/query/latest/docs/react/guides/placeholder-query-data`
- TanStack Query docs: `https://tanstack.com/query/latest/docs/react/guides/initial-query-data`
- TanStack Query docs: `https://tanstack.com/query/latest/docs/react/guides/prefetching`
- Vite docs: `https://vite.dev/guide/features.html`
- MDN: `https://developer.mozilla.org/en-US/docs/Web/API/HTMLImageElement/decode`
- MDN: `https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Attributes/fetchpriority`
- MDN: `https://developer.mozilla.org/en-US/docs/Web/API/ResizeObserver`
- MDN: `https://developer.mozilla.org/en-US/docs/Web/CSS/content-visibility`
- MDN: `https://developer.mozilla.org/en-US/docs/Web/CSS/contain-intrinsic-size`
- MDN: `https://developer.mozilla.org/en-US/docs/Web/SVG/Reference/Attribute/clipPathUnits`

---

## O que realmente e interessante para esta stack

Nem toda melhoria sugerida tem o mesmo peso neste repositorio.

## P0 - fundacao obrigatoria antes de chamar a v1 de pronta

### 1. Contrato global de motion do wall

Isso e P0 de verdade.
Sem isso, cada layout continua com uma fisica diferente e o `puzzle` vira excecao cara.

Fundacao minima:

- `MotionConfig` no topo do player;
- tokens por tema para `enter`, `exit`, `burst`, `drift`, `reducedMotion` e `visualDuration`;
- policy comum para reduced motion no player e no preview.

### 2. `layout registry` + `theme_config` + capabilities formais

Isso tambem e P0.
Sem isso, o manager continua espalhando regra de tema entre enum, config e switch manual.

Fundacao minima:

- `wall-layout-registry.ts`;
- `theme_config` no contrato;
- capabilities formais por tema;
- bloqueio funcional de combinacoes incompativeis no editor.

### 3. Subsistema de board layouts + `usePuzzleBoard`

Se `puzzle` nascer fora de uma base compartilhada, ele vira o primeiro layout "especial demais".

Fundacao minima:

- `useWallBoard()` ou estrutura equivalente;
- `BoardSlot`, `BoardSelectionPolicy` e `BoardBurstScheduler`;
- `usePuzzleBoard` como primeiro consumidor dessa fundacao.

### 4. Pipeline de readiness com `decode()` e prioridade por slot

Isso e P0 porque muda diretamente a percepcao de fluidez e o equilibrio entre cache e realtime.

Fundacao minima:

- readiness baseada em `fetch + decode`;
- prioridade maior so para ancora, hero e proximo burst;
- janela quente limitada ao board visivel e ao proximo burst;
- sem refetch completo da fila a cada evento realtime.

### 5. Drift, micro-bursts e parallax fora do render React

Isso precisa entrar como fundacao, nao como refinamento.

Implementacao minima:

- `MotionValue`;
- `useSpring`;
- `useAnimate` ou `useAnimationFrame`;
- React cuidando da identidade do slot, nao do tick animado.

### 6. Shape system normalizado e deduplicado

Sem isso, o tema fica bonito na demo e caro de sustentar depois.

Fundacao minima:

- `clipPathUnits="objectBoundingBox"` como padrao;
- catalogo pequeno de variantes;
- `defs` deduplicados;
- shapes deterministicas por preset.

### 7. Politica oficial de preservacao e reset por `key`

Isso precisa ser contrato, nao comportamento acidental.

Sem isso, o risco e:

- board fantasma no preview;
- pecas antigas sobrando apos troca de preset;
- reset desnecessario em updates comuns de fila.

## P1 - melhoria importante depois da fundacao estar firme

### 8. `AnimatePresence` com `mode="popLayout"` nas pecas

Isso melhora bastante a percepcao premium do board.
Mas vem depois de readiness, budget e scheduler estarem fechados.

### 9. `ResizeObserver` no palco vivo e downgrade por area util

O preview do manager ja usa `ResizeObserver`.
O proximo passo e aplicar isso no palco vivo para decidir `6`, `9` ou `12` pecas pela geometria real, nao so por heuristica de device.

### 10. Melhor politica de preview e tuning de query no manager

O manager faz parte do problema.

P1 real:

- `placeholderData` onde fizer sentido;
- `prefetchQuery` no fluxo de troca de tema e preset;
- `staleTime` e refetch policies menos agressivas para o editor;
- preview sem flicker enquanto configuracao muda.

### 11. Disciplina de implementacao: `memo`, `useMemo` e menos `Effect`

Isso nao e headline de produto, mas precisa virar criterio tecnico da entrega.

### 12. Reduced motion formal como capability de tema

Isso deve acompanhar o contrato de motion.
No puzzle, reduced motion precisa desligar drift continuo e layout animation mais agressiva.

## P2 - refinamento premium ou melhoria condicional

### 13. `layoutId` / `LayoutGroup` para shared transitions premium

Faz sentido para featured hero, promocao de peca e transicoes editoriais melhores.
Nao e fundacao da v1.

### 14. Clusters de remetente, face brackets e overlays premium

Isso pode enriquecer muito o tema, mas e segunda fase.
O board precisa ficar estavel antes de ganhar mais semantica visual.

### 15. Lazy chunks de temas premium com `import.meta.glob` + `LazyMotion`

Boa evolucao quando houver varios temas premium relevantes.
Nao precisa entrar antes de existir custo real de boot.

### 16. `useSyncExternalStore` para realtime, se aparecer sintoma real

Eu manteria isso como melhoria condicional.
So sobe de prioridade se aparecerem:

- tearing;
- churn perceptivel em bursts;
- dificuldade real de sustentar o hook/reducer atual.

### 17. `startTransition` / `useTransition` no editor e reconfiguracao pesada

Boa melhoria quando o manager ficar mais complexo.
Nao muda a fundacao do player.

---

## Sugestoes que ja existem parcialmente no repo

Algumas ideias da rodada nao comecam do zero:

- o wall ainda importa `framer-motion` diretamente, mesmo com a documentacao oficial atual da Motion priorizando `motion/react`;
- `placeholderData` ja existe em `apps/web/src/modules/wall/wall-query-options.ts` para `insights` e `liveSnapshot`;
- `ResizeObserver` ja existe no preview do manager em `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.tsx`;
- `img.decode()` ja existe em `apps/web/src/modules/wall/player/engine/preload.ts`, mas ainda nao e o criterio geral de readiness do asset;
- o manager ainda nao usa `prefetchQuery` no fluxo principal de troca de tema/preview;
- o wall ja tem um inicio de degradacao por performance em `usePerformanceMode`, mas ainda nao tem policy formal por tema.

Isso reforca que a melhor estrategia agora e:

- consolidar e formalizar;
- nao reescrever do zero o que ja comecou certo.

---

## Testes adicionados nesta rodada

Para sustentar a documentacao com comportamento real do codigo atual, esta rodada adicionou:

- `apps/web/src/modules/wall/player/wall-theme-architecture-characterization.test.ts`
  - valida que o wall ainda hardcode `MULTI_ITEM_SLOT_COUNT = 3`;
  - valida que o wall ainda importa `framer-motion` e ainda nao usa `MotionConfig`, `LayoutGroup` ou `useReducedMotion`;
  - valida que o realtime ainda nao usa `useSyncExternalStore`;
  - valida que o preview do manager ja usa `ResizeObserver`;
  - valida que o wall ja usa `placeholderData` em parte das queries do modulo, mas ainda nao usa `prefetchQuery` no fluxo principal do manager;
  - valida que `img.decode()` existe so na trilha de preload do proximo item, nao na pipeline generica de probe.

Continuam relevantes como base anterior:

- `apps/web/src/modules/wall/player/engine/preload.test.ts`
  - valida a trilha de preload do proximo item e o uso de video preload `auto`;
- `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.test.tsx`
  - valida que o preview reaproveita o renderer visual do player com overlays reais;
- `apps/web/src/modules/wall/player/components/WallPlayerRoot.test.tsx`
  - valida o fluxo principal entre layout e overlay de anuncio.

---

## Roadmap recomendado

## Fase 1 - fundacao do sistema de temas

- criar `layout registry`;
- introduzir `theme_config`;
- fechar contrato global de motion do wall;
- formalizar capabilities por tema;
- criar base compartilhada para board layouts;
- fechar politica oficial de reset/preservacao por `key`.

## Fase 2 - `Quebra Cabeca` v1 segura

- adicionar `puzzle` ao contrato;
- implementar board com `6/9` pecas;
- usar imagens apenas;
- usar SVG clipPath com shapes deduplicados;
- usar readiness com `decode()` e prioridade por slot;
- usar drift e micro-bursts fora do render React;
- entregar peca ancora e fallback claro para video.

## Fase 3 - operacao e previsibilidade

- aplicar `ResizeObserver` no palco vivo;
- fechar budget de runtime por tier;
- melhorar preview do manager com tuning de query e prefetch;
- validar degradacao automatica;
- validar comportamento em reduced motion.

## Fase 4 - refinamento premium

- `popLayout` nas pecas;
- preset `12` pecas;
- featured em slot hero;
- shared transitions premium;
- lazy chunks para temas premium quando fizer sentido.

## Fase 5 - metadata visual avancada

- brackets por face;
- clusters de remetente;
- regras de permanencia por tipo de item;
- overlays premium por metadata.

## Plano de rollout recomendado

Roadmap tecnico e rollout de produto nao sao a mesma coisa.
Para wall em evento real, eu recomendo introducao progressiva.

### Fase A - behind feature flag interna

- layout disponivel apenas para time interno;
- sem exposicao no manager publico;
- logs e metricas ligados desde o inicio.

### Fase B - preview-only no manager

- tema aparece no manager;
- preview funcional;
- player vivo ainda bloqueado;
- validacao de compatibilidade com branding, QR e overlays.

### Fase C - ativacao em um evento controlado

- habilitar o tema em `1` evento de baixo risco;
- limitar a `6` ou `9` pecas conforme hardware;
- acompanhar decode, reconnect, atraso de entrada e resets inesperados.

### Fase D - coleta de metricas e ajuste

- revisar budget de runtime;
- revisar hit rate do cache quente;
- revisar se o realtime continua responsivo sob bursts;
- ajustar presets e degradacao automatica.

### Fase E - liberacao para tenants selecionados

- liberar para grupo pequeno de clientes;
- manter feature flag por tenant;
- promover para opcao geral so depois de estabilidade operacional.

## Criterios de aceite da v1

Eu consideraria a v1 aceita se estes pontos forem verdade ao mesmo tempo:

- em FHD padrao com `9` pecas, o board mantem fluidez perceptiva e nao parece reiniciar a cada update comum;
- novas midias entram por realtime sem exigir refetch completo da fila;
- cache reduz redownload de assets sem atrasar a entrada do item elegivel;
- peca nova nao reaparece em loading visivel depois de `ready`, exceto erro real de asset;
- o preview do manager bate visualmente com o player para o mesmo preset e mesma configuracao;
- o board reseta so quando mudarem `eventId`, `layout`, `preset`, `themeVersion`, `performanceTier` ou `reducedMotion`;
- o manager bloqueia capabilities incompativeis da v1;
- video cai para fallback claro e previsivel;
- reduced motion corta drift continuo e animacao agressiva sem quebrar o layout.

---

## Decisao recomendada

Se a meta e criar um tema `Quebra Cabeca` bonito, dinamico e viavel no produto atual, eu recomendo esta decisao:

1. implementar `puzzle` como layout nativo do wall;
2. usar `SVG clipPath` inline, nao WebGL;
3. fazer v1 image-only;
4. fechar antes um contrato global de motion do wall;
5. trabalhar com `9` pecas como preset principal e `6` como fallback seguro;
6. usar micro-bursts locais dirigidos fora do render React;
7. preservar o board com politica oficial de reset por `key`;
8. equilibrar cache quente e preload dirigido sem atrasar o realtime;
9. deixar face brackets para fase 2 com metadata do servidor.

Esse caminho entrega:

- impacto visual alto;
- baixo risco arquitetural;
- boa compatibilidade com a stack atual;
- reuso do preview do manager;
- controle real de performance.

## Resumo final

Hoje o wall esta preparado para layouts novos, mas ainda num modelo pensado para `1` ou `3` midias principais.

O tema `Quebra Cabeca` e viavel, desde que seja tratado como:

- um layout novo com engine propria de board;
- um tema orientado a imagens;
- um mural de `6` a `12` pecas, nao um feed infinito em miniatura;
- um sistema de entradas pequenas e constantes, nao de reshuffle bruto.

Se tentarmos comecar com `9` pecas, `SVG clipPath`, drift leve e bursts pequenos, a chance de termos um tema forte e estavel e alta.
Mas a entrega so fica profissional de verdade se vier junto com:

- contrato global de motion;
- budget de runtime;
- readiness real por `decode()`;
- politica formal de reset;
- cache de asset que respeita o realtime.
