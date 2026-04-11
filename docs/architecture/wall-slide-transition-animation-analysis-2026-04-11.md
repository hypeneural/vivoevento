# Wall slide transition animation analysis - 2026-04-11

## Objetivo

Este documento consolida:

- como a animacao de troca do telao funciona hoje;
- qual stack real esta sendo usada para transicoes e motion;
- onde o comportamento esta centralizado e onde ele ainda esta espalhado;
- o que a documentacao oficial valida sobre a abordagem atual;
- qual e a melhor trilha para adicionar novas animacoes;
- qual e a melhor trilha para um modo `rand` de transicao entre midias.

O foco aqui e a **troca visual entre midias do wall**, nao o board `puzzle` como tema completo.

---

## Veredito executivo

O wall ja tem uma base boa para evoluir animacoes sem trocar de stack:

- React `18.3.1`
- TypeScript `5.8.3`
- Vite `5.4.19`
- `framer-motion` `12.38.0`
- `MotionConfig` no topo do player
- `AnimatePresence mode="wait"` para layouts `single-item`
- motion tokens por layout
- reduced motion aplicado de forma global

Conclusao pratica:

1. **Nao vale introduzir outra lib de animacao agora.**
2. **O melhor caminho e continuar em Motion/Framer Motion**, formalizando um registry de transicoes do wall.
3. **Novas animacoes devem entrar primeiro nos layouts `single-item`**, onde a troca hoje ja e centralizada.
4. **Modo `rand` nao deve usar `Math.random()` dentro do render.**
5. **O melhor modo `rand` e deterministico por advance**, com pool controlado e anti-repeticao.

Em uma frase:

**a melhor abordagem e evoluir o `transition_effect` atual para um pequeno sistema de transicoes do wall, mantendo Motion como engine unica e resolvendo `rand` no runtime do player, nao no JSX.**

---

## Fontes oficiais revisadas

As referencias abaixo foram revalidadas em `2026-04-11`.

### Motion

- React animation:
  - `https://motion.dev/docs/react-animation`
- AnimatePresence:
  - `https://motion.dev/docs/react-animate-presence`
- MotionConfig:
  - `https://motion.dev/docs/react-motion-config`
- Transitions:
  - `https://motion.dev/docs/react-transitions`
- useAnimationFrame:
  - `https://motion.dev/docs/react-use-animation-frame`
- LayoutGroup:
  - `https://motion.dev/docs/react-layout-group`

### React

- Preserving and Resetting State:
  - `https://react.dev/learn/preserving-and-resetting-state`
- useTransition:
  - `https://react.dev/reference/react/useTransition`

---

## O que a documentacao oficial valida

### 1. MotionConfig e a forma certa de aplicar politica global

A documentacao oficial do Motion valida que `MotionConfig` pode definir:

- `transition` default para todos os filhos;
- `reducedMotion` como policy global.

Isso conversa diretamente com o player atual:

- ja usamos `MotionConfig` em `WallPlayerRoot`;
- ja existe uma politica de reduced motion;
- ja existe um contrato de motion por layout.

Leitura pratica:

- o wall esta no caminho certo;
- o proximo passo e subir o sistema de **transicoes** para o mesmo nivel de formalizacao que o sistema de **motion tokens**.

### 2. AnimatePresence `wait` encaixa bem no slide principal

A documentacao oficial do Motion e bem clara:

- `mode="wait"` e apropriado para animacao sequencial;
- ele espera o elemento atual sair antes do proximo entrar;
- ele suporta apenas um child por vez.

Isso casa perfeitamente com o uso atual do wall para layouts `single-item`, porque:

- um slide principal entra;
- o atual sai;
- o proximo entra;
- nao existe concorrencia de dois hero slides na mesma camada.

Leitura pratica:

- `wait` continua sendo a melhor base para `fade`, `slide`, `zoom`, `flip` e futuros efeitos single-slide;
- o wall nao precisa trocar esse modelo para ganhar mais variacao visual.

### 3. Motion ja oferece base oficial para animacoes mais ricas

A documentacao oficial valida tres pontos importantes:

- animation props aceitam **keyframes arrays**;
- `transition` aceita **value-specific transitions**;
- `visualDuration` ajuda a coordenar springs com tempo visual previsivel.

Leitura pratica:

- da para adicionar efeitos mais ricos sem trocar de tecnologia;
- da para criar transicoes em 2 ou 3 etapas sem cair para hacks de CSS;
- da para manter consistencia visual entre temas e transicoes.

### 4. useAnimationFrame e a trilha correta para loops continuos

Motion documenta que `useAnimationFrame`:

- executa callback a cada frame;
- entrega `time` e `delta`;
- serve para animacao de tempo continuo.

Isso ja conversa com o puzzle, onde drift foi movido para fora do render React.

Leitura pratica:

- transicao de slide nao precisa usar `useAnimationFrame`;
- mas efeitos continuos, parallax leve e drift devem continuar nessa trilha;
- nao devemos voltar para `setState` por frame para resolver animacao.

### 5. LayoutGroup e layoutId sao a trilha oficial para shared transitions

Motion documenta:

- `LayoutGroup` agrupa componentes que afetam o mesmo estado visual;
- `layoutId` permite shared layout animation.

Leitura pratica:

- isso e util para featured/hero ou promover uma imagem de thumbnail para destaque;
- nao e o primeiro passo para resolver slide transition;
- mas e o caminho certo se quisermos animacoes premium mais cenograficas depois.

### 6. React continua reforcando que `key` define reset de estado

A documentacao oficial do React reforca:

- estado e associado a posicao na arvore;
- mudar `key` reseta subtree;
- manter a mesma identidade preserva estado.

Leitura pratica para o wall:

- um modo `rand` nao pode escolher transicao aleatoria no render e mudar `key` por acidente;
- a escolha da transicao tem que ser resolvida por advance e ficar estavel para aquele slide;
- preview e player precisam compartilhar a mesma regra, senao a UX fica inconsistente.

---

## Bateria de validacao executada

### Frontend

Executado:

```bash
cd apps/web
npm run test -- src/modules/wall/player/engine/motion.test.ts src/modules/wall/player/wall-theme-architecture-characterization.test.ts
```

Resultado:

- `2 arquivos`
- `15 testes`
- `PASS`

### Backend

Executado:

```bash
cd apps/api
php artisan test tests/Feature/Wall/WallOptionsCharacterizationTest.php
```

Resultado:

- `1 teste`
- `13 assertions`
- `PASS`

Leitura:

- o contrato atual de transicao continua refletido em `/wall/options`;
- a arquitetura atual de Motion/registry/layout renderer segue verde;
- a analise abaixo parte de comportamento real do codigo e de testes atuais.

---

## Stack real usada hoje

## Frontend

Stack base do wall nesta parte:

- React `18.3.1`
- TypeScript `5.8.3`
- Vite `5.4.19`
- `framer-motion` `12.38.0`

Obs. importante:

- a documentacao oficial atual da Motion ja enfatiza imports via `motion/react`;
- o nosso codigo hoje ainda usa `framer-motion`;
- para este caso, o caminho de menor risco e **continuar no pacote atual** e nao misturar estilos de import no meio da feature.

## Backend

Na API, a transicao hoje e contrato simples de settings:

- enum `WallTransition`
- campo `transition_effect` em `EventWallSetting`
- validacao via `UpdateWallSettingsRequest`
- exposicao em `/wall/options`

Arquivos centrais:

- `apps/api/app/Modules/Wall/Enums/WallTransition.php`
- `apps/api/app/Modules/Wall/Http/Requests/UpdateWallSettingsRequest.php`
- `apps/api/app/Modules/Wall/Http/Controllers/EventWallController.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`

## Frontend do player

Arquivos centrais:

- `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`
- `apps/web/src/modules/wall/player/components/LayoutRenderer.tsx`
- `apps/web/src/modules/wall/player/engine/motion.ts`
- `apps/web/src/modules/wall/player/themes/motion.ts`
- `apps/web/src/modules/wall/player/themes/registry.ts`

---

## Como a animacao de troca funciona hoje

## 1. Contrato funcional atual

Hoje o wall expõe apenas estas opcoes de transicao:

- `fade`
- `slide`
- `zoom`
- `flip`
- `none`

Isso existe em:

- backend: `WallTransition`
- shared types: `WallTransition`
- manager: `options.transitions`

Ou seja:

- o contrato ainda e pequeno;
- ele funciona;
- mas esta limitado para expansao premium.

## 2. Onde a troca single-slide acontece

Nos layouts `single-item`, a troca principal acontece em:

- `LayoutRenderer.tsx`

Hoje o fluxo e:

1. o player resolve o layout real via `resolveRenderableLayout()`;
2. busca a definicao via `getWallLayoutDefinition()`;
3. resolve a transicao visual via `resolveLayoutTransition()`;
4. monta um `motion.div` keyed por `layout + media.id + media.url`;
5. aplica `AnimatePresence mode="wait"`.

Leitura pratica:

- a troca principal esta **bem centralizada**;
- isso e bom para crescer o numero de animacoes;
- novas animacoes single-slide nao precisam mexer em todos os layouts.

## 3. Onde o motion global ja existe

Em `WallPlayerRoot.tsx`, o wall ja sobe:

- `MotionConfig`
- `resolveWallMotionConfig()`
- `reducedMotion`
- tokens por layout

Isso significa que o sistema ja tem:

- policy global de tempo/easing;
- policy global de reduced motion;
- separacao entre motion do layout e motion da transicao.

## 4. Onde a transicao ainda esta espalhada

Nos layouts `board`:

- `CarouselLayout.tsx`
- `MosaicLayout.tsx`
- `GridLayout.tsx`

as animacoes acontecem por slot, cada um com seu proprio:

- `AnimatePresence`
- `initial/animate/exit`
- `duration`

Leitura pratica:

- o `transition_effect` do slide principal **nao governa realmente os board layouts**;
- board hoje tem uma semantica propria de troca;
- isso nao e errado, mas precisa ser explicitado.

## 5. O que isso significa no produto

Hoje existem dois niveis de animacao:

### Nivel A - troca principal do slide

Usado de verdade em:

- `fullscreen`
- `cinematic`
- `split`
- `polaroid`
- `kenburns`
- `spotlight`
- `gallery`

### Nivel B - troca interna de slot

Usado em:

- `carousel`
- `mosaic`
- `grid`
- `puzzle`

Conclusao:

- se a pergunta for "como adicionar novas animacoes de troca do slide?",
- a resposta principal esta no **Nivel A**.

---

## Limitacoes reais do estado atual

## L1. `transition_effect` ainda e um switch simples

Hoje `resolveLayoutTransition()` usa um `switch` direto.

Isso e suficiente para 5 opcoes, mas piora quando quisermos:

- `blur-fade`
- `lift`
- `swipe-up`
- `cross-zoom`
- `mask-reveal`
- variantes por tema

## L2. Nao existe modo `rand`

Hoje o contrato aceita apenas efeito fixo.

Nao existe:

- `transition_mode`
- `transition_pool`
- anti-repeticao
- seed deterministico

## L3. Board e single-slide ainda nao compartilham um sistema formal de transicoes

Hoje o board tem boa animacao, mas:

- usa duracoes locais;
- nao conversa diretamente com `transition_effect`;
- nao expõe um "transition style" formal por layout board.

## L4. Fazer `rand` no render seria um erro

Se tentarmos algo assim:

```ts
const effect = effects[Math.floor(Math.random() * effects.length)];
```

dentro do render ou dentro de `resolveLayoutTransition()`:

- preview e player podem divergir;
- re-render pode trocar o efeito sem trocar o slide;
- a key do slide pode ficar incoerente;
- debugging e telemetria ficam ruins.

## L5. O manager ainda pensa em uma transicao unica, nao em politica de transicao

Hoje o manager edita:

- `transition_effect`

Mas nao edita ainda:

- estrategia fixa vs aleatoria;
- pool permitida;
- efeitos por tema;
- exclusoes por capability.

---

## Melhor abordagem para adicionar novas animacoes

## Decisao recomendada

**Continuar com Motion/Framer Motion e criar um registry de transicoes do wall.**

Nao recomendo:

- introduzir GSAP so para isso;
- cair para CSS puro como sistema principal;
- duplicar engine entre single-slide e board.

## Como eu estruturaria

Criar uma camada tipo:

```ts
export interface WallTransitionDefinition {
  id: WallTransition;
  label: string;
  scope: 'single' | 'board';
  buildVariants: (context: WallTransitionContext) => {
    initial: Record<string, string | number>;
    animate: Record<string, string | number>;
    exit: Record<string, string | number>;
  };
  buildTransition: (context: WallTransitionContext) => Transition;
  reducedMotionFallback?: WallTransition;
}
```

e depois trocar o `switch` atual por:

- `wallTransitionRegistry`
- `getWallTransitionDefinition(effect)`

## Beneficios

- adicionar nova animacao deixa de ser mexer em varios `if/switch`;
- labels, capabilities e preview ficam centralizados;
- fica mais facil bloquear animacoes caras em modos fracos;
- `rand` passa a operar sobre um pool formal de definicoes.

## Quais animacoes eu priorizaria primeiro

Para v1 de expansao, eu recomendaria efeitos seguros e legiveis:

1. `fade`
2. `slide`
3. `zoom`
4. `flip`
5. `lift-fade`
6. `cross-zoom`
7. `swipe-up`
8. `blur-fade`

Motivo:

- todas cabem bem no motor atual;
- nenhuma exige canvas/WebGL;
- todas funcionam com `AnimatePresence` e variants;
- todas conseguem cair para `none` ou `fade` em reduced motion.

## O que eu evitaria cedo

- transicoes 3D muito agressivas;
- blur pesado com varios layers;
- efeitos com filter grande em TV-box fraca;
- aleatoriedade sem seed;
- misturar transicao principal com drift/panning continuo.

---

## Melhor abordagem para o modo `rand`

## Recomendacao principal

**Nao colocar `random` como efeito visual solto.**

O melhor e separar:

- `transition_effect`
- `transition_mode`

Exemplo:

```ts
type WallTransitionMode = 'fixed' | 'random';

interface WallTransitionConfig {
  transition_effect: WallTransition;
  transition_mode: WallTransitionMode;
  transition_pool?: WallTransition[];
}
```

## Por que isso e melhor do que `transition_effect = random`

Porque preserva:

- o efeito fixo atual;
- compatibilidade com o contrato existente;
- telemetria clara;
- preview e debug mais simples.

E tambem permite:

- fixed simples;
- random com pool custom;
- random com pool padrao do tema.

## Algoritmo recomendado

Eu usaria um **shuffle bag deterministico**:

1. monta um pool valido;
2. remove o ultimo efeito usado, quando houver alternativa;
3. embaralha de forma deterministica por `eventId + currentItemId + advanceIndex`;
4. escolhe o proximo;
5. fixa esse efeito no runtime state do slide atual.

Leitura pratica:

- evita repeticao chata tipo `zoom -> zoom -> zoom`;
- evita divergencia entre preview e player;
- continua permitindo "sensacao aleatoria".

## Onde resolver isso

**No runtime do player, no momento do advance.**

Nao em:

- `render()`
- `LayoutRenderer`
- `resolveLayoutTransition()` diretamente

O ideal e algo assim:

1. engine decide que o item mudou;
2. runtime resolve `activeTransitionEffect` para aquele advance;
3. `LayoutRenderer` recebe o efeito ja resolvido;
4. `resolveLayoutTransition()` apenas monta variants/transition.

## Escopo recomendado do modo `rand`

### V1 segura

Aplicar `rand` apenas a layouts `single-item`.

Motivo:

- e onde a semantica de slide e mais clara;
- o modelo atual com `AnimatePresence mode="wait"` ja encaixa;
- preview e manager ficam mais faceis de alinhar.

### Fase posterior

Criar um conceito separado para board:

- `board_transition_style`
- `slot_transition_style`
- `burst_style`

Porque board nao e a mesma coisa que "troca de slide hero".

---

## Proposta concreta de implementacao

## Backend

Arquivos-alvo:

- `packages/shared-types/src/wall.ts`
- `apps/api/app/Modules/Wall/Enums/WallTransition.php`
- `apps/api/app/Modules/Wall/Http/Requests/UpdateWallSettingsRequest.php`
- `apps/api/app/Modules/Wall/Http/Controllers/EventWallController.php`

Mudancas recomendadas:

1. manter `transition_effect` como efeito base;
2. adicionar `transition_mode: fixed | random`;
3. adicionar `transition_pool?: WallTransition[]`;
4. continuar expondo options pelo backend;
5. bloquear efeitos nao suportados por layout, se necessario depois.

## Frontend

Arquivos-alvo:

- `apps/web/src/modules/wall/player/engine/motion.ts`
- novo `apps/web/src/modules/wall/player/engine/transition-registry.ts`
- `apps/web/src/modules/wall/player/components/LayoutRenderer.tsx`
- `apps/web/src/modules/wall/player/hooks/useWallPlayer.ts`
- `apps/web/src/modules/wall/manager-config.ts`
- `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.tsx`

Mudancas recomendadas:

1. trocar o `switch` por registry;
2. resolver `activeTransitionEffect` por advance;
3. persistir o efeito do slide atual no runtime;
4. expor `fixed` vs `random` no manager;
5. expor pool custom opcional no manager;
6. manter preview usando a mesma regra.

## Telemetria minima recomendada

Vale registrar:

- `active_transition_effect`
- `transition_mode`
- `transition_pool_size`
- `transition_random_pick_count`
- `transition_fallback_count`

Isso ajuda a medir:

- repeticao;
- efeito mais usado;
- se algum efeito esta gerando degradacao;
- se o modo `rand` esta realmente variado.

---

## O que eu faria agora

## P0

1. criar `transition registry` no frontend;
2. manter `transition_effect` atual funcionando por compatibilidade;
3. adicionar `lift-fade`, `cross-zoom` e `swipe-up`;
4. adicionar `transition_mode = fixed | random`;
5. fazer `rand` apenas para layouts `single-item`;
6. usar shuffle bag deterministico.

## P1

1. permitir `transition_pool` custom;
2. criar presets por tema;
3. ligar `transition suggestions` por layout;
4. separar melhor transicao de slide e burst de board.

## P2

1. shared transitions premium com `LayoutGroup/layoutId`;
2. transicoes especiais por tema premium;
3. possivel curva de motion por evento.

---

## O que eu nao recomendaria agora

- adicionar uma segunda lib de animacao;
- usar `Math.random()` no render;
- colocar `random` dentro de `transition_effect` como unico campo;
- fazer board e single-slide compartilharem a mesma semantica a forca;
- liberar `rand` para board denso antes de medir;
- criar dez animacoes novas de uma vez.

---

## Decisao recomendada

Se a meta e evoluir a animacao de troca do wall com seguranca, a melhor decisao e:

1. manter a stack atual com `framer-motion`;
2. formalizar um registry de transicoes;
3. separar efeito fixo de modo aleatorio;
4. resolver `rand` por runtime state e nao por render;
5. comecar pelos layouts `single-item`.

Em uma frase:

**novas animacoes devem entrar como extensao da engine atual de Motion, e o modo `rand` deve ser um scheduler deterministico de efeitos por advance, nao um sorteio improvisado dentro do JSX.**

