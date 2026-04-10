# Wall puzzle video and theme extensibility analysis - 2026-04-09

## Objetivo

Este documento valida duas perguntas especificas:

- o que acontece se a fila do telao for composta quase so por videos;
- quao viavel e ter um tema `Quebra Cabeca` com varios videos simultaneos sem sobrecarregar navegador, device ou rede.

Tambem fecha uma segunda frente:

- quao facil ou dificil fica criar outros templates depois;
- como isso poderia evoluir para personalizacao por evento sem transformar o wall num conjunto de casos especiais.

Esta rodada foi feita com:

- leitura direta do codigo atual do wall;
- execucao de testes backend e frontend;
- consulta a documentacao oficial de Chrome for Developers e MDN.

Documentos base desta leitura:

- `docs/architecture/wall-puzzle-theme-analysis-2026-04-09.md`
- `docs/architecture/wall-video-playback-current-state-2026-04-08.md`
- `docs/architecture/wall-puzzle-theme-execution-plan-2026-04-09.md`

---

## Veredito executivo

### 1. Fila so de video no wall atual

Se a fila for composta so por videos, o wall atual aguenta o fluxo desde que continue em layout `single-item` ou em `auto` resolvendo para layout de item unico.

Nesse cenario:

- o backend so entrega videos elegiveis;
- o player toca um video por vez;
- o video atual passa pela trilha controlada de `WallVideoSurface`;
- existe `poster-first`, `startup deadline`, `stall budget` e cap por duracao quando configurado;
- o wall nao depende do `interval_ms` puro para encerrar video.

Conclusao pratica:

- `sequencia so de video` e viavel no wall atual;
- `mural puzzle com varios videos vivos ao mesmo tempo` nao e uma extensao natural desse fluxo.

### 2. Puzzle com varios videos simultaneos

Para o tema `Quebra Cabeca`, a resposta correta hoje e:

- `nao` para v1;
- `nao` como comportamento default;
- `talvez` so numa fase posterior, com guard rails bem mais fortes.

Motivo:

- o browser nao publica um limite fixo de "quantos videos simultaneos";
- a capacidade real depende de hardware decode, codec, resolucao, bitrate, GPU, memoria, rede e thermal throttling;
- a propria plataforma web oferece APIs para estimar suavidade e medir perda de frames, em vez de prometer um numero maximo universal.

Conclusao pratica:

- o `puzzle` deve nascer `image-first`;
- video em `puzzle` deve entrar apenas como fallback controlado ou modo premium com limite severo;
- `6`, `9` ou `12` videos simultaneos no board nao sao uma aposta profissional para v1.

### 3. Facilidade de criar outros templates

O wall atual ja tem base razoavel para novos temas:

- renderer central;
- preview do manager reutilizando o mesmo renderer;
- enums de layout compartilhados;
- settings do wall por evento;
- politicas de video e fairness ja separadas do layout visual.

Mas hoje ainda falta um sistema formal de temas.

Para escalar de verdade:

- layout precisa virar `definition + capabilities + theme_config`;
- board layouts precisam virar subsistema compartilhado;
- customizacao por evento precisa sair de campos soltos e entrar em contrato de tema.

---

## Validacao executada nesta rodada

### Testes frontend executados

Executado:

- `cd apps/web && npm run test -- src/modules/wall/player/components/LayoutRenderer.video-multi-layout.test.tsx src/modules/wall/player/engine/layoutStrategy.test.ts src/modules/wall/player/components/MediaSurface.test.tsx src/modules/wall/player/components/WallVideoSurface.test.tsx src/modules/wall/player/hooks/useWallEngine.test.tsx`

Resultado:

- `5` arquivos
- `46` testes
- `PASS`

Cobertura relevante:

- video em layout multi-slot hoje monta varios `<video>` se a policy permitir;
- `video_multi_layout_policy = disallow` continua derrubando multi-layout para single-item;
- `MediaSurface` em video comum usa `autoplay`, `muted`, `playsInline` e `preload="auto"`;
- `WallVideoSurface` continua sendo a trilha controlada de startup/stall/end;
- `useWallEngine` continua tratando video como playback dirigido por causa de saida, nao por timer fixo.

### Testes backend executados

Executado:

- `cd apps/api && php artisan test tests/Unit/Modules/Wall/WallVideoAdmissionServiceTest.php tests/Unit/Modules/Wall/WallEligibilityServiceTest.php tests/Feature/Wall/PublicWallBootTest.php`

Resultado:

- `20` testes
- `121` assertions
- `PASS`

Cobertura relevante:

- o backend bloqueia video sem metadata/variant/poster conforme policy;
- o boot publico so entrega video elegivel;
- o wall prefere variante `wall_video_*` e poster quando disponiveis;
- `original` so entra quando a policy explicitamente permite fallback.

### Teste novo adicionado nesta rodada

- `apps/web/src/modules/wall/player/components/LayoutRenderer.video-multi-layout.test.tsx`
  - valida que, se `video_multi_layout_policy = all`, o `LayoutRenderer` atual monta `3` videos simultaneos em `grid`;
  - valida tambem que essa trilha nao usa poster-first da `WallVideoSurface`.

---

## Fontes oficiais usadas nesta validacao

### Chrome for Developers

- `https://developer.chrome.com/blog/autoplay/`
- `https://developer.chrome.com/docs/workbox/serving-cached-audio-and-video`

### MDN

- `https://developer.mozilla.org/en-US/docs/Web/Media/Guides/Autoplay`
- `https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/preload`
- `https://developer.mozilla.org/en-US/docs/Web/API/MediaCapabilities/decodingInfo`
- `https://developer.mozilla.org/en-US/docs/Web/API/HTMLVideoElement/getVideoPlaybackQuality`
- `https://developer.mozilla.org/en-US/docs/Web/API/HTMLVideoElement/requestVideoFrameCallback`
- `https://developer.mozilla.org/en-US/docs/Web/API/ResizeObserver`
- `https://developer.mozilla.org/en-US/docs/Web/SVG/Reference/Attribute/clipPathUnits`

### O que essas fontes confirmam

- autoplay mudo e o caminho seguro para video sem gesto do usuario;
- `play()` ainda pode falhar e precisa ser tratado;
- `preload` e apenas `hint`, nao garantia;
- a plataforma expoe `decodingInfo()` para perguntar se um perfil tende a ser `supported`, `smooth` e `powerEfficient`;
- a plataforma expoe `getVideoPlaybackQuality()` para medir frames perdidos;
- `requestVideoFrameCallback()` existe para observabilidade frame-level;
- se houver `Service Worker` para video, `Range requests` e pre-cache explicito passam a ser obrigatorios;
- nao existe uma tabela oficial com "maximo de 4" ou "maximo de 8" videos simultaneos garantidos para Chrome.

Inferencia importante, derivada das fontes oficiais:

- o limite de videos simultaneos nao deve ser tratado como constante global do produto;
- ele deve ser tratado como `runtime budget` dependente de device e asset.

---

## Como video funciona hoje no wall

## 1. Gate de backend

Hoje o backend ja faz um trabalho importante antes de o player existir:

- so entrega `approved + published`;
- so entrega `image | video`;
- aplica gate de orientacao;
- aplica `WallVideoAdmissionService`;
- exige metadata minima;
- exige variante e poster quando a policy pede;
- usa `wall_video_720p` como preferencia operacional default;
- corta original-only em modo estrito.

Na pratica:

- o wall atual nao deveria receber qualquer video bruto do nada;
- ele recebe video que ja passou por um gate tecnico razoavel.

## 2. Trilha controlada de playback

Quando o item atual e video em layout `single-item`, o fluxo atual e:

1. `WallPlayerRoot` cria `videoControl`;
2. `LayoutRenderer` renderiza layout single-item;
3. `MediaSurface` usa `WallVideoSurface`;
4. `WallVideoSurface` faz `poster-first`;
5. o player promove o video so quando ha readiness minima;
6. o engine avanca por `ended`, `cap_reached` ou falha classificada.

Essa trilha atual e a correta para "fila so de video" em telao classico.

## 3. O que acontece em multi-layout hoje

Aqui esta o ponto mais importante desta validacao.

Hoje:

- `LayoutRenderer` usa `resolveRenderableLayout()`;
- se a midia atual for video e `video_multi_layout_policy = disallow`, o renderer cai para `fullscreen`;
- esse e o default operacional do produto.

Mas, se alguem abrir a policy para `one` ou `all`, os layouts multi-slot atuais fazem isto:

- `CarouselLayout`, `MosaicLayout` e `GridLayout` usam `MediaSurface` diretamente;
- nesses slots, `MediaSurface` renderiza `<video autoPlay muted playsInline preload="auto">`;
- essa trilha nao usa `WallVideoSurface`;
- essa trilha nao tem `poster-first` controlado;
- essa trilha nao tem `startupDeadlineMs` por slot;
- essa trilha nao tem `stallBudgetMs` por slot;
- essa trilha nao tem cap operacional por slot;
- essa trilha nao tem uma observabilidade equivalente por slot.

Conclusao objetiva:

- abrir multi-video no board atual nao significa "reaproveitar o que ja esta pronto";
- significa cair numa trilha mais crua e menos controlada.

---

## O que acontece se a fila for so de video

## Cenario A - layout single-item ou auto

Esse e o cenario seguro e suportado hoje.

Se a fila vier assim:

- video A
- video B
- video C
- video D

o wall atual tende a fazer:

1. tocar `video A`;
2. sair por `ended` ou `cap_reached`;
3. entrar em `video B`;
4. repetir o ciclo.

Beneficios:

- um decode principal por vez;
- uma surface principal por vez;
- poster-first controlado;
- menor disputa de CPU/GPU/rede;
- heartbeat e diagnostico mais claros.

Risco residual:

- se todos os videos forem pesados, ainda pode haver startup degradado;
- mas o design atual ja tem mais mecanismos para sair dessa situacao sem travar o wall.

## Cenario B - layout multi-slot liberado para video

Se alguem configurar `grid`, `mosaic` ou `carousel` com `video_multi_layout_policy = all`, o estado atual passa a ser outro:

- varios `<video>` montados ao mesmo tempo;
- varios `preload="auto"` competindo;
- varios decodes concorrentes;
- varios posters e starts disputando rede e GPU;
- menos previsibilidade de startup e stall por slot.

Com fila so de video, isso degrada mais rapido porque:

- toda nova reposicao no board pode criar mais um decode quente;
- a tela fica dependente de varias pipelines de video em paralelo;
- qualquer device mais fraco ou rede mais instavel sofre bem antes.

Conclusao pratica:

- `fila so de video` em single-item e viavel;
- `fila so de video` em puzzle multi-video nao e uma boa aposta para default.

---

## O que a documentacao oficial do navegador realmente permite concluir

## 1. Autoplay

Chrome e MDN confirmam que autoplay mudo e o caminho seguro.

Isso valida a estrategia atual do wall:

- `muted`
- `autoPlay`
- `playsInline`

Mas isso nao resolve o problema principal do puzzle.

Autoplay resolve:

- permissao basica para iniciar.

Autoplay nao resolve:

- quantos videos o hardware aguenta decodificar ao mesmo tempo;
- quantos vao tocar `smooth`;
- quantos vao manter boa eficiencia energetica;
- quantos vao competir com animacoes, overlays e realtime.

## 2. Preload

MDN e clara ao dizer que `preload` e um `hint`.

Isso significa:

- `preload="auto"` ajuda;
- `preload="auto"` nao garante buffer completo;
- `preload="auto"` em varios videos ao mesmo tempo pode aumentar consumo sem entregar previsibilidade proporcional.

Leitura pratica para puzzle:

- nao da para usar `preload="auto"` em muitos slots e considerar isso uma estrategia de produto;
- no maximo ele entra como aquecimento seletivo de poucos candidatos.

## 3. MediaCapabilities

MDN documenta `MediaCapabilities.decodingInfo()` justamente para responder:

- `supported`
- `smooth`
- `powerEfficient`

para uma configuracao concreta de video.

Esse ponto e decisivo:

- a plataforma oficial nao diz "o browser suporta 8 videos";
- ela diz "pergunte por uma configuracao concreta".

Leitura pratica:

- a capacidade real de multi-video e por device e por perfil de asset;
- o produto precisa trabalhar com budget e downgrade, nao com numero magico universal.

## 4. Runtime quality

MDN expoe duas ferramentas importantes:

- `getVideoPlaybackQuality()`
- `requestVideoFrameCallback()`

Essas APIs existem justamente porque:

- smoothness de video precisa ser medida em runtime;
- perda de frames e atraso de frame sao sinais reais de degradacao.

Leitura pratica:

- se um dia o `puzzle` aceitar video, isso deve ser acompanhado de observabilidade frame-level ou pelo menos `dropped frames`;
- sem isso, o time vai decidir pelo "parece que travou" em vez de diagnostico tecnico.

## 5. Cache de video

Chrome for Developers e muito claro no guia de Workbox:

- media em `<video>` costuma usar `Range requests`;
- se quiser servir video do cache, e preciso respeitar `Range`;
- cachear enquanto o video e streamado em runtime nao resolve por si so, porque so conteudo parcial e buscado durante o playback;
- o caminho correto passa por pre-cache explicito ou warming controlado.

Leitura pratica:

- "cache para baixar conexoes" continua valido;
- mas cache de video nao substitui arquitetura boa de slots;
- e perigoso prometer puzzle multi-video so com `Cache API` e `preload`.

---

## Existe um limite oficial de quantos videos simultaneos o Chrome aguenta

Resposta curta:

- eu nao encontrei uma documentacao oficial de Chrome ou MDN que publique um numero fixo garantido de videos simultaneos por pagina ou por device.

Resposta mais precisa:

- a plataforma oficial oferece meios de verificar suporte, smoothness, power efficiency e dropped frames;
- isso indica que o limite e variavel e dependente do ambiente;
- portanto, qualquer numero fixo que o produto adotar sera uma decisao operacional nossa, nao uma garantia do navegador.

Conclusao recomendada:

- tratar `1`, `2` ou `0` videos ativos como policy de produto;
- nao tratar `6`, `9` ou `12` como capacidade presumida do browser.

---

## Recomendacao pratica para o tema Quebra Cabeca

## V1 recomendada

- `puzzle` image-only;
- se o item atual for video, o wall faz fallback para `fullscreen` ou `cinematic`;
- opcionalmente o board pode continuar mostrando posters estaticos de videos, mas sem playback simultaneo.

Essa e a decisao com melhor equilibrio entre:

- impacto visual;
- realtime;
- cache util;
- previsibilidade operacional.

## V2 segura

Se for realmente necessario colocar video no puzzle, a melhor progressao e:

### Modo 1 - hero video + posters

- `1` video ativo;
- outras pecas de video mostram poster;
- realtime continua entrando no board sem sobrecarregar decode.

### Modo 2 - board misto

- `1` video ativo;
- `1` segundo video ativo apenas em tier premium e hardware validado;
- resto continua poster-only.

### Modo 3 - experimental

- `2` videos ativos no maximo;
- so com:
  - `MediaCapabilities` favoravel;
  - runtime sem dropped frames relevantes;
  - device/hardware homologado;
  - preset menor.

O que eu nao recomendo:

- `6` videos ativos em board de `6` pecas;
- `9` videos ativos em board de `9` pecas;
- `12` videos ativos em preset premium;
- liberar `all` por default no manager.

## Politica recomendada para fila so de video com puzzle ligado

Se o evento estiver no tema `puzzle` e a fila entrar em burst quase so de video, a politica mais previsivel e:

1. o manager identifica que o tema e `image-first`;
2. o primeiro item video da fila marca o board como `video-incompatible for live board`;
3. o player troca temporariamente para:
   - `fullscreen`, ou
   - `cinematic`;
4. quando a fila voltar a ter densidade de imagem suficiente, o board pode retomar.

Isso preserva:

- realtime verdadeiro;
- cache util;
- animacao premium;
- sem inventar um multi-video fraco.

---

## Orcamento recomendado de runtime para video no puzzle

Isso aqui nao e limite oficial do Chrome.
Isso e recomendacao operacional derivada da plataforma e da nossa stack.

### Tier seguro para v1

- `0` videos ativos no board
- `1` video ativo so fora do board, em fallback single-item
- `1` video futuro com `preload="auto"` no maximo
- posters para o restante

### Tier seguro para v2

- `1` video ativo no board
- `1` slot vizinho no maximo com poster de video
- `0` a `1` video futuro em warming forte
- no maximo `2` surfaces com pressao real de video ao mesmo tempo

### Tier premium experimental

- `2` videos ativos no maximo
- somente em preset menor
- somente com runtime bom
- somente com dropped frames e stall sob observacao

Regra de produto recomendada:

- se houver qualquer sintoma de degradacao, o player baixa para `1` video ativo ou fallback single-item.

Sinais de downgrade automatico recomendados:

- startup acima do budget;
- waiting/stalled recorrente;
- dropped frames acima do toleravel;
- `decodingInfo()` deixando de ser `smooth` ou `powerEfficient` para o perfil alvo;
- reconnects com atrasos visiveis.

---

## O que precisaria melhorar para video premium em templates futuros

Se a meta for ter `puzzle`, `mosaic premium`, `social board` e outros temas com video real, o wall precisaria evoluir nestes pontos:

## 1. Capabilities por tema

Exemplo:

- `supportsVideo`
- `maxSimultaneousVideos`
- `supportsPosterOnlyVideoTiles`
- `supportsHeroVideo`
- `supportsAudio`
- `supportsSideThumbnails`

Isso precisa virar regra funcional do manager, nao apenas texto.

## 2. Theme registry

Cada tema deveria declarar:

- renderer;
- capabilities;
- fallback layout;
- limites operacionais;
- configuracao default;
- compatibilidade com reduced motion.

## 3. Theme config por evento

Para personalizacao por evento, o contrato correto e um `theme_config`.

Exemplos uteis:

- preset `compact | standard | immersive`
- intensidade de motion
- peca ancora com logo do evento
- cor e glow da borda
- permitir ou nao poster de video no board
- hero slot on/off
- label do evento
- QR anchor
- branding mode

## 4. Board subsystem

`puzzle`, `mosaic premium` e futuros temas de board precisam compartilhar:

- scheduler local;
- fairness por slot;
- replacement policy;
- budget de asset;
- degrade policy;
- runtime metrics.

## 5. Observabilidade de video por slot

Se houver multi-video real no futuro, o wall precisa observar por slot:

- first frame
- playback ready
- dropped frames
- waiting/stalled
- replacement reason

Sem isso, varios videos simultaneos viram uma caixa-preta operacional.

---

## Facilidade de criar outros templates e personalizar por evento

## O que ja esta bom hoje

O estado atual do wall ajuda bastante:

- `LayoutRenderer` centraliza o renderer;
- o preview do manager reaproveita o player;
- settings do wall ja sao por evento;
- o manager ja expone varios knobs visuais e operacionais;
- backend e frontend ja compartilham enums e payloads;
- overlays como branding, QR e sender credit ja nao precisam ser reinventados por tema.

Isso significa que criar um novo template hoje e viavel.

## O que ainda trava a escalabilidade

Hoje, criar um tema novo ainda exige tocar em varios pontos acoplados:

- enum compartilhado;
- enum backend;
- config do manager;
- `LayoutRenderer`;
- regras implicitas espalhadas.

Para um ou dois temas isso funciona.
Para uma familia de temas personalizados por evento, isso fica caro.

## Como eu estruturaria para personalizacao por evento

### Nivel 1 - template base

Exemplos:

- `fullscreen`
- `cinematic`
- `puzzle`
- `social-board`

### Nivel 2 - preset do template

Exemplos:

- `wedding-clean`
- `festival-neon`
- `corporate-minimal`

### Nivel 3 - `theme_config` do evento

Exemplos:

- cores
- logo
- motion intensity
- comportamento da ancora
- badge do featured
- politica de video
- densidade do board

Com isso, o time nao precisa criar "um layout novo" para cada evento.
Cria:

- um tema bom;
- alguns presets;
- personalizacao leve por evento.

## Recomendacao final sobre extensibilidade

Se a meta e facilitar outros templates e personalizacao por evento:

1. criar `layout registry`;
2. introduzir `theme_config`;
3. formalizar capabilities por tema;
4. separar `board themes` como subsistema;
5. usar fallback de video por tema em vez de tentar tornar todos os temas multi-video.

---

## Decisao recomendada

Se a pergunta for "podemos ter puzzle com varios videos rodando ao mesmo tempo?", minha resposta e:

- tecnicamente possivel em alguns devices;
- arquiteturalmente inadequado como default hoje;
- arriscado para evento real;
- nao validado pela stack atual como caminho profissional de v1.

Se a pergunta for "como fica uma fila so de video?", a resposta e:

- com o wall atual, ela funciona melhor em layout single-item;
- com `puzzle`, o certo e fallback claro para layout de video ou board poster-only.

Se a pergunta for "fica facil criar outros templates e personalizar por evento?", a resposta e:

- sim, se o wall evoluir de `lista de layouts` para `sistema de temas`;
- nao, se cada novo tema continuar espalhando regra entre enum, switch e manager manual.

---

## Resumo final

O ponto central desta validacao e simples:

- `video no wall` e uma trilha diferente de `imagem no wall`;
- `muitos videos simultaneos` e um problema de runtime budget, nao de CSS bonito;
- o navegador nao promete um numero fixo seguro de videos ativos;
- o produto precisa decidir um limite conservador e tratar o resto como downgrade/fallback.

Por isso, para o `Quebra Cabeca`:

- v1 deve ser `image-first`;
- fila so de video deve cair para layout single-item;
- multi-video no board so deveria existir depois de:
  - capabilities formais;
  - runtime metrics;
  - fallback por device;
  - homologacao real em hardware de evento.
