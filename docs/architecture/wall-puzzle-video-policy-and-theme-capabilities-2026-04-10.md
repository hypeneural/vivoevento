# Wall puzzle video policy and theme capabilities - 2026-04-10

## Objetivo

Este documento transforma a analise complementar do `Quebra Cabeca` em uma policy objetiva de produto e arquitetura para:

- decidir como video deve se comportar no tema `puzzle`;
- definir limites operacionais para playback simultaneo;
- reduzir risco de sobrecarga de navegador, device e rede;
- fixar como o manager deve bloquear combinacoes invalidas;
- preparar a base para novos templates e personalizacao por evento.

Documentos base:

- `docs/architecture/wall-puzzle-theme-analysis-2026-04-09.md`
- `docs/architecture/wall-puzzle-video-and-theme-extensibility-analysis-2026-04-09.md`
- `docs/execution-plans/wall-puzzle-theme-execution-plan-2026-04-09.md`

---

## Veredito executivo

Decisao de produto recomendada:

1. `puzzle` v1 nasce `image-first`.
2. `puzzle` v1 nao reproduz varios videos simultaneos.
3. O maximo operacional suportado como comportamento default continua sendo `1` video ativo por vez.
4. Quando o layout selecionado for `puzzle` e o item atual for video, o player deve cair para um layout `single-item` com trilha controlada de `WallVideoSurface`.
5. `poster-only` em board entra como modo futuro, nao como comportamento padrao da v1.
6. O manager precisa sair do modelo atual de opcoes soltas e passar a aplicar `capabilities` por layout.

Conclusao pratica:

- `video no puzzle = fallback single-item`;
- `multi-video no puzzle = fora da v1`;
- `maxSimultaneousVideos default = 1`;
- `theme system = layout registry + capabilities + theme_config`.

---

## Validacao executada nesta rodada

## Fontes oficiais revalidadas em `2026-04-10`

Chrome for Developers:

- `https://developer.chrome.com/blog/autoplay/`
- `https://developer.chrome.com/docs/workbox/serving-cached-audio-and-video`

MDN:

- `https://developer.mozilla.org/en-US/docs/Web/Media/Guides/Autoplay`
- `https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/preload`
- `https://developer.mozilla.org/en-US/docs/Web/API/MediaCapabilities/decodingInfo`
- `https://developer.mozilla.org/en-US/docs/Web/API/HTMLVideoElement/getVideoPlaybackQuality`
- `https://developer.mozilla.org/en-US/docs/Web/API/HTMLVideoElement/requestVideoFrameCallback`

O que essas fontes permitem afirmar com seguranca:

- autoplay mudo continua sendo a trilha segura para video sem gesto do usuario;
- autoplay com audio pode ser bloqueado e `play()` precisa continuar tratado como falha recuperavel;
- `preload` e apenas `hint`, nao garantia de download, decode ou prontidao de playback;
- `MediaCapabilities.decodingInfo()` existe para consultar se um perfil tende a ser `supported`, `smooth` e `powerEfficient`;
- `getVideoPlaybackQuality()` e `requestVideoFrameCallback()` existem para medir frames perdidos, frames apresentados e degradacao de playback;
- cache de audio/video com `Service Worker` exige atencao explicita a `Range Requests`;
- nao existe numero oficial fixo de "maximo de N videos simultaneos" garantido pelo Chrome ou MDN.

Inferencia obrigatoria:

- o limite de videos simultaneos do wall nao deve ser tratado como constante universal;
- ele deve ser tratado como `runtime budget` dependente de device, codec, bitrate, resolucao e condicoes reais de rede.

## Testes executados nesta rodada

## Frontend

Executado:

```bash
cd apps/web
npm run test -- src/modules/wall/player/wall-theme-architecture-characterization.test.ts src/modules/wall/player/components/LayoutRenderer.video-multi-layout.test.tsx src/modules/wall/player/components/WallVideoSurface.test.tsx src/modules/wall/player/components/MediaSurface.test.tsx src/modules/wall/player/engine/layoutStrategy.test.ts src/modules/wall/player/engine/preload.test.ts src/modules/wall/components/manager/stage/WallPreviewCanvas.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx
```

Resultado:

- `8` arquivos;
- `63` testes;
- `PASS`.

Cobertura relevante:

- `LayoutRenderer` continua montando `3` videos simultaneos em `grid` quando `video_multi_layout_policy = all`;
- `WallVideoSurface` continua sendo a trilha controlada de poster-first, startup timeout e stall budget;
- o wall ainda nao expoe `puzzle`, `theme_config` nem capability matrix formal;
- o manager ainda trata `video_multi_layout_policy` como opcao solta, nao como regra derivada do layout.

## Backend

Executado:

```bash
cd apps/api
php artisan test tests/Feature/Wall/WallOptionsCharacterizationTest.php tests/Feature/Wall/PublicWallBootTest.php tests/Unit/Modules/Wall/WallVideoAdmissionServiceTest.php
```

Resultado:

- `9` testes;
- `118` assertions;
- `PASS`.

Cobertura relevante:

- `/wall/options` ainda nao devolve capability metadata por layout;
- `PublicWallBoot` continua entregando video admission metadata e gate de orientacao;
- `WallVideoAdmissionService` continua distinguindo `eligible`, `eligible_with_fallback` e `blocked`.

---

## Estado real confirmado no codigo atual

## 1. Playback de video controlado existe, mas so em `single-item`

Hoje a trilha robusta de video passa por:

- `WallPlayerRoot`
- `LayoutRenderer`
- `MediaSurface`
- `WallVideoSurface`

Nessa trilha existem:

- poster-first;
- readiness minima antes de promover playback;
- startup deadline;
- stall budget;
- saida por `ended`, `cap_reached` ou falha classificada.

Isso valida a policy de manter video principal em `single-item`.

## 2. Multi-layout com video ainda cai em `<video>` cru

Hoje, se `video_multi_layout_policy = all`:

- `grid`, `mosaic` e `carousel` montam varios `<video autoPlay muted playsInline preload="auto">`;
- esses slots nao passam pela trilha de `WallVideoSurface`;
- esses slots nao tem poster-first controlado por slot;
- esses slots nao tem startup/stall budget por slot;
- esses slots nao tem observabilidade equivalente por slot.

Isso invalida a ideia de liberar `puzzle` multi-video como default.

## 3. O manager ainda nao bloqueia por capability

Hoje:

- `manager-config.ts` lista layouts e opcoes separadas;
- `WallAppearanceTab.tsx` expoe `video_multi_layout_policy` como select independente;
- `/wall/options` devolve apenas `value` e `label` para layout;
- nao existe `capabilities`, `maxSimultaneousVideos`, `posterOnlyMode` ou `theme_config`.

Isso confirma que a extensibilidade ainda esta num modelo de configuracao espalhada.

---

## Policy de produto

## 1. Policy global de video do wall

Enquanto o tema system formal nao existir, o produto deve operar com estas regras:

- `autoplay` sempre mudo;
- `playsInline` sempre ligado;
- falha de `play()` continua tratada como erro recuperavel;
- `video_enabled` segue sendo gate global;
- gate de backend continua exigindo metadata minima e variants/poster conforme policy;
- nenhum layout novo nasce prometendo multi-video sem telemetria e runtime budget proprios.

## 2. Policy do `puzzle` v1

### Capability oficial do tema

`puzzle` v1 deve ser declarado como:

- `supportsVideoPlayback = false`
- `supportsVideoPosterOnly = false`
- `supportsMultiVideo = false`
- `maxSimultaneousVideos = 0`
- `fallbackVideoLayout = cinematic`

Leitura pratica:

- o board do `puzzle` trabalha apenas com imagem;
- se o item selecionado for video, o board nao tenta tocar video dentro de slot;
- o runtime troca temporariamente para um layout de fallback `single-item`.

### Comportamento de runtime

Se `layout = puzzle`:

1. item atual e imagem:
   - renderizar `puzzle`.
2. item atual e video elegivel:
   - renderizar fallback `single-item`;
   - usar `WallVideoSurface`;
   - voltar ao `puzzle` quando o video terminar ou sair por cap/falha.
3. item atual e video bloqueado pela policy de admission:
   - nao tentar board playback;
   - seguir regra operacional existente de pular ou substituir por item elegivel.

### O que nao entra na v1

- video dentro de peca do puzzle;
- `6`, `9` ou `12` videos simultaneos;
- mistura de videos tocando em paralelo com drift, mask e glow do board;
- deteccao de face client-side em video;
- poster animado por slot como comportamento default.

## 3. Maximo de videos simultaneos

Policy default recomendada do produto:

- wall inteiro: `1` video em playback ativo por vez;
- `fullscreen`, `cinematic`, `split`, `spotlight`, `gallery`, `kenburns`, `polaroid`: continuam podendo tocar `1` video;
- `carousel`, `mosaic`, `grid`: produto continua com `video_multi_layout_policy = disallow` como default operacional;
- `puzzle` v1: `0` videos no board e `1` apenas via fallback `single-item`.

Justificativa:

- isso preserva cache e rede sem matar realtime;
- isso preserva a trilha robusta de video que ja existe;
- isso evita que animacao premium dispute decode paralelo com varios videos.

## 4. Poster-only modes

Poster-only deve existir como policy futura formal, nao como gambiarra local por tema.

Contrato recomendado para fase futura:

- `videoBehavior = fallback_single_item`
- `videoBehavior = poster_only`
- `videoBehavior = skip_video`
- `videoBehavior = experimental_multi_playback`

Policy de liberacao:

- v1 publica apenas `fallback_single_item`;
- `poster_only` fica para fase posterior e pode ser util para board layouts premium;
- `experimental_multi_playback` nao entra sem telemetria, capability gate e rollout controlado.

## 5. Rules do manager por capability

Quando o layout selecionado for `puzzle`, o manager deve:

- esconder ou desabilitar `video_multi_layout_policy`;
- mostrar explicacao curta:
  - `Puzzle exibe imagens. Videos entram em layout individual de fallback.`
- nao oferecer modo `all` ou `one` para puzzle;
- nao oferecer side thumbnails se a capability do tema nao suportar;
- nao oferecer opcoes visuais que o tema nao consome;
- salvar `theme_config` apenas dentro do namespace do tema.

Enquanto a capability matrix nao existir, esta policy deve ser aplicada na camada de UI e validacao de request.

## 6. Liberacao oficial do puzzle

Quando o `puzzle` sair do rollout controlado e virar tema oficial:

- `/wall/options` deve continuar sendo a fonte de verdade do manager;
- `puzzle` deve aparecer sempre no backend, sem depender de `.env`;
- a seguranca operacional deixa de ser `feature flag` e passa a ser:
  - capability restritiva;
  - fallback single-item para video;
  - budget de runtime;
  - diagnostics e downgrade observavel.

Regras objetivas dessa liberacao:

- `WALL_PUZZLE_ENABLED` e `WALL_PUZZLE_PREVIEW_ENABLED` deixam de ser prerequisito operacional;
- `fallbackOptions` do frontend nao vira fonte de promocao do tema;
- `maxSimultaneousVideos default = 1` continua como policy global do produto;
- `puzzle` continua com `0` videos no board e `1` apenas via fallback controlado.

---

## Policy de extensibilidade para novos templates

## 1. Nenhum tema novo deve nascer so com enum + switch

Novo tema deve entrar com:

- `layout definition`
- `capabilities`
- `defaults`
- `theme_config`
- `preview strategy`

Contrato minimo recomendado:

```ts
type WallLayoutCapability = {
  supportsVideoPlayback: boolean;
  supportsVideoPosterOnly: boolean;
  supportsMultiVideo: boolean;
  maxSimultaneousVideos: number;
  supportsSideThumbnails: boolean;
  supportsFloatingCaption: boolean;
  supportsThemeConfig: boolean;
};
```

## 2. `wall/options` deve evoluir

O endpoint de options deve passar a devolver, por layout:

- `value`
- `label`
- `capabilities`
- `defaults`
- `preview_hint`

Isso permite:

- manager bloquear combinacoes invalidas sem heuristica solta;
- preview refletir o comportamento real;
- futuros templates premium nascerem com menos codigo especial.

## 3. `theme_config` por evento

Personalizacao por evento deve entrar em contrato unico, nao em proliferacao de colunas.

Exemplos de configuracao que cabem em `theme_config`:

- preset do board;
- ancora central;
- intensidade de animacao;
- fallback de video;
- hero slot;
- burst intensity;
- palette e style variants do tema.

---

## Regras objetivas para execucao do plano

Antes de implementar `puzzle`, a equipe deve assumir estas regras como fixas:

1. `puzzle` nao e multi-video na v1.
2. O board nao monta `<video>` em paralelo como estrategia default.
3. O maximo operacional default continua `1` playback de video por vez.
4. O fallback oficial de video para `puzzle` e `single-item`.
5. O manager passa a aplicar capability rules, nao apenas textos de ajuda.
6. `theme_config` e `layout registry` deixam de ser opcional para escalar templates.

---

## Criterios de aceite desta policy

Aceitamos a policy como pronta quando:

- o documento base do `puzzle` referencia esta policy;
- os testes de caracterizacao travam o estado atual que ela pretende corrigir;
- o execution plan do `puzzle` puder assumir `video no puzzle = fallback`;
- o time tiver uma regra unica para `maxSimultaneousVideos`;
- o manager tiver diretriz clara para bloquear combinacoes invalidas na implementacao;
- a liberacao oficial do `puzzle` nao depender mais de `env gate`.

## Proximo passo recomendado

Transformar esta policy em trabalho implementavel em duas frentes:

1. `P0 foundation`
   - layout registry
   - capabilities
   - theme_config
   - bloqueio de manager por capability
2. `P0 puzzle runtime`
   - `puzzle` image-first
   - fallback `single-item` para video
   - nenhum board multi-video na v1
