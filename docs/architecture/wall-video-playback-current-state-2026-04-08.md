# Wall video playback - estado atual da stack e gaps

## Objetivo

Este documento registra o estado atual do telão em `2026-04-08`, com foco em:

- como a midia chega ao wall;
- como a stack atual do wall esta organizada;
- como o slideshow funciona hoje;
- como o realtime e a resiliencia de sincronizacao funcionam;
- como videos sao aceitos, preparados, cacheados e renderizados;
- quais regras de negocio ja existem;
- quais gaps tecnicos e de produto ainda precisamos fechar, principalmente para video.

O documento foi revisado com:

- leitura direta do codigo backend e frontend do wall;
- snapshot real do endpoint `GET /api/v1/public/wall/BG6JY36L/boot`;
- execucao de testes backend e frontend;
- adicao de novos testes para caracterizar o comportamento atual de video.

## Resumo executivo

Hoje o wall suporta `image` e `video` como itens normais da fila, mas o suporte a video ainda e "video como slide", nao "video como playback de primeira classe".

Na pratica:

- video comum do slideshow toca com `autoplay`, `muted`, `playsInline` e `loop`;
- o wall avanca por `interval_ms`, nao por `onEnded` do video;
- video curto repete em loop ate a troca do slide;
- video longo e interrompido quando o slide troca;
- anuncios em video ja tem regra diferente e melhor: terminam por `onEnded`;
- o player tem mecanismos de preload, metadata probe, cache local e fallback de sincronizacao;
- isso melhora robustez, mas ainda nao resolve por completo travamento de videos pesados ou longos.

O principal gap do produto hoje e que o wall ainda nao tem politica especifica para video de `EventMedia`.

## Validacao externa da plataforma web

Alguns pontos deste documento foram checados tambem contra documentacao oficial da plataforma web, porque aqui existe risco real de assumir comportamento do navegador de forma errada.

Conclusoes confirmadas:

- `preload` em video e apenas uma dica para o navegador, nao uma garantia de buffer completo;
- com `loop = true`, o navegador nao dispara `ended` no playback normal, entao um video comum em loop nunca entrega um "fim natural" ao player;
- `muted` e `playsInline` fazem sentido na estrategia atual do wall, porque autoplay inline depende desse tipo de configuracao em varios navegadores;
- `play()` retorna `Promise`, entao uma futura trilha playback-aware precisa tratar sucesso e falha de inicio do video;
- `pause()` e o mecanismo correto para congelar o video atual quando o wall entra em pausa;
- `readyState` diferencia bem `metadata ready` de `future data` e `enough data`, entao ele e mais util do que apenas `loadedmetadata` para decidir readiness real;
- `MediaCapabilities.decodingInfo()` faz sentido como guard rail futuro para decidir perfis/variantes de video com mais seguranca;
- `requestVideoFrameCallback()` faz sentido como observabilidade premium, nao como requisito da primeira entrega;
- `Cache API` ajuda em resiliencia, mas o caminho tipico para controlar fetch de playback de forma programatica passa por Service Worker;
- em MP4 progressivo, `+faststart` faz sentido para melhorar o inicio do playback.

Isso reforca a conclusao central:

- o comportamento atual do wall esta coerente com a plataforma web;
- mas essa coerencia nao e suficiente para um produto premium de wall com videos variados.

## Snapshot do wall analisado

Wall avaliado: `/wall/player/BG6JY36L`

Snapshot real observado no boot publico:

- `status = paused`
- `files = []`
- `layout = split`
- `interval_ms = 10000`
- `transition_effect = fade`
- `accepted_orientation = all`
- `ad_mode = disabled`

Implicacao:

- no momento da inspecao o wall estava pausado;
- por isso o boot nao trouxe fila de midias;
- se esse mesmo wall voltar para `live` mantendo `interval_ms = 10000`, um video comum sera tratado como slide de `10s`.

## Stack atual do wall

### Backend

Stack principal do backend do wall:

- Laravel 12
- PHP 8.3
- PostgreSQL
- Redis / filas
- Reverb para realtime compatível com protocolo Pusher

Modulos principais envolvidos:

- `Wall`
  - configuracao do telão
  - boot publico
  - eventos broadcastados
  - simulacao, diagnostico e comandos operacionais
- `MediaProcessing`
  - cria `EventMedia`
  - processa download, moderacao, variantes e publicacao
- `InboundMedia`
  - intake publico e intake privado
- `Events`
  - evento e modulos habilitados

### Frontend

Stack principal do player:

- React 18
- TypeScript
- Vite 5
- Framer Motion
- Pusher JS para conexao Reverb/Pusher

Arquitetura principal do player:

- boot e heartbeat por `fetch` publico
- realtime por WebSocket
- estado central do player em reducer
- cache/preload local no browser
- persistencia de runtime em `localStorage` + `IndexedDB`

### Contrato compartilhado

Os contratos de payload e nomes de eventos ficam em:

- `packages/shared-types/src/wall.ts`

Isso centraliza:

- tipos de layout
- tipos de transicao
- eventos do wall
- payload do boot publico
- payload dos eventos realtime
- payload de heartbeat/diagnostics

## Como a midia chega ao wall

### 1. Intake privado

O caminho privado passa pelo pipeline de inbound media, por exemplo:

- WhatsApp
- Telegram

No fluxo atual:

1. a mensagem inbound e normalizada;
2. `DownloadInboundMediaJob` baixa o binario remoto;
3. o MIME ou `message_type` define `media_type`;
4. o arquivo original e salvo em `events/{event}/originals/...`;
5. `EventMedia` e criada;
6. a midia segue para moderacao/publicacao.

Para video inbound privado:

- `video/*` vira `media_type = video`;
- o arquivo original normalmente fica em `.mp4`;
- o pipeline exclusivo de imagem nao e usado.

### 2. Upload publico

O upload publico passa por `PublicUploadController`.

Estado atual do aceite:

- upload unitario aceita `jpg`, `jpeg`, `png`, `gif`, `webp`, `heic`, `heif`, `mp4`, `mov`
- upload multiplo aceita apenas imagem

Observacao importante:

- o backend aceita video unitario;
- mas a UX publica ainda comunica "foto/imagem";
- o payload ainda expoe `accept_hint = image/*`;
- a pagina publica hoje usa `accept="image/*"`.

Ou seja:

- existe suporte tecnico parcial para video no upload publico;
- a experiencia de produto ainda nao esta alinhada com isso.

### 3. Moderacao e publicacao

Para uma midia aparecer no wall ela precisa passar pelas regras do pipeline:

- `moderation_status = approved`
- `publication_status = published`

O wall nao consome item "recebido" ou "pending" diretamente.

## Diferenca importante entre os caminhos de video

Hoje existem duas trilhas diferentes para video:

### Video inbound privado

- nao passa por `GenerateMediaVariantsJob`;
- vai direto para moderacao;
- ja nasce mais alinhado com a natureza de video.

### Video de upload publico

- aceita upload unitario de video;
- ainda despacha `GenerateMediaVariantsJob`;
- o gerador de variantes retorna cedo para midia que nao e imagem, entao o job vira um no-op funcional.

Implicacao:

- o upload publico de video funciona;
- mas o pipeline atual ainda carrega um passo desnecessario para esse tipo de midia.

Esse e um gap pequeno, mas importante para a clareza da stack.

## Como o wall carrega e sincroniza

### Boot inicial

Endpoint:

- `GET /api/v1/public/wall/{wallCode}/boot`

O boot retorna:

- `event`
- `files`
- `settings`
- `ads`

Regras atuais:

- `PublicWallController::boot()` so carrega a fila se `EventWallSetting::isPlayable()` for verdadeiro;
- se o wall esta pausado, parado, expirado ou indisponivel, o boot nao traz fila jogavel;
- `WallRuntimeMediaService` carrega `approved + published + image|video`, ordenado por `published_at desc, id desc`.

### State publico

Endpoint:

- `GET /api/v1/public/wall/{wallCode}/state`

Uso:

- checks leves do estado publico do wall;
- apoio a orquestracao externa, health checks ou telas auxiliares.

### Heartbeat

Endpoint:

- `POST /api/v1/public/wall/{wallCode}/heartbeat`

O player envia:

- status do runtime
- status da conexao
- item atual
- contadores de assets `ready/loading/error/stale`
- informacoes de cache e storage persistente
- `last_sync_at`
- motivo de fallback/erro quando existir

Isso alimenta o diagnostico do wall no backend.

## Como o realtime funciona hoje

### Transporte

O player suporta:

- Reverb self-hosted
- Pusher Cloud

Canal publico do player:

- `wall.{wallCode}`

Eventos principais:

- `wall.media.published`
- `wall.media.updated`
- `wall.media.deleted`
- `wall.settings.updated`
- `wall.status.changed`
- `wall.expired`
- `wall.player.command`
- `wall.ads.updated`

### Comportamento do hook de realtime

`useWallRealtime`:

- cria a conexao Pusher/Reverb;
- acompanha `connectionStatus`;
- roteia cada evento para callbacks do engine;
- desconecta e limpa inscricoes no unmount.

Mapeamento simplificado de estado:

- `connected`
- `connecting`
- `reconnecting`
- `disconnected`
- `error`

### Resiliencia sem depender 100% do WebSocket

Mesmo com realtime, o player nao depende apenas do socket.

`useWallPlayer` faz:

- boot inicial via HTTP;
- resync periodico:
  - `120s` quando conectado
  - `20s` quando degradado
- resync imediato quando a conexao volta;
- heartbeat a cada `20s`;
- heartbeat tambem em mudancas importantes de status/erro/visibilidade.

Isso significa:

- o wall tem modo degradado;
- se o socket oscilar, o player ainda tenta se manter consistente por polling leve.

## Como o slideshow funciona

## Estado do player

Estados principais do runtime:

- `booting`
- `idle`
- `playing`
- `paused`
- `stopped`
- `expired`
- `error`

O `reducer` centraliza:

- fila atual
- item atual
- status do player
- ads
- scheduler de ads
- metricas de play por item/remetente

## Regras de selecao da fila

O wall nao exibe simplesmente "o primeiro item do banco". Existe uma camada de fairness.

Regras principais que ja existem:

- prefere items `ready` quando o probe de asset ja terminou;
- evita repeticao excessiva do mesmo remetente;
- controla replay por item;
- aplica cooldown por remetente;
- aplica janela de volume por remetente;
- evita repetir o mesmo `duplicate_cluster_key` quando existe alternativa;
- prioriza ineditos antes de replays maduros;
- permite `featured` influenciar a ordem.

### Modos de selecao

Modos atuais:

- `balanced`
- `live`
- `inclusive`
- `editorial`
- `custom`

Em alto nivel:

- `live` e mais agressivo e rotativo;
- `inclusive` e mais conservador;
- `editorial` fica no meio;
- `custom` permite ajuste fino da policy.

### Fase do evento

O backend ainda ajusta o runtime de acordo com a fase do evento:

- `reception`
- `flow`
- `party`
- `closing`

Efeitos atuais:

- `reception`
  - slideshow mais lento
  - fairness mais restritiva
- `party`
  - slideshow mais rapido
  - mais permissivo para repeticao/rotacao
- `closing`
  - tende a desacelerar um pouco e flexibilizar replay
- `flow`
  - baseline

Importante:

- o `interval_ms` de runtime pode nao ser exatamente o valor salvo no settings bruto;
- o backend aplica `WallSelectionPreset::applyPhaseInterval()` no payload runtime.

## Layouts e transicoes

Layouts atuais:

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

Transicoes atuais:

- `fade`
- `slide`
- `zoom`
- `flip`
- `none`

### Como o layout `auto` funciona

`auto` escolhe apenas layouts de item unico e usa:

- orientacao da midia
- existencia de caption
- flag `is_featured`

Isso ja e uma customizacao importante do telão.

### Layouts multi-item

Layouts multi-slot:

- `carousel`
- `mosaic`
- `grid`

Eles nao usam o mesmo ciclo de item unico.

Hoje:

- exibem 3 slots simultaneos;
- atualizam um slot por vez via `useMultiSlot`;
- cada slot faz sua propria transicao curta;
- varios videos podem estar tocando ao mesmo tempo.

## O que ja e personalizado no telão hoje

Do ponto de vista de produto e UX, o player ja vai alem de um slideshow basico.

Elementos ja customizados:

- escolha automatica de layout por orientacao/caption/featured
- overlays de branding
- QR de upload
- neon/status "ao vivo"
- credito do remetente
- captions flutuantes em layouts especificos
- badge de item `featured`
- side thumbnails rotativas
- toast de novas fotos chegando via realtime
- modo de performance para hardware fraco ou `prefers-reduced-motion`
- suporte a ads com scheduler proprio
- heartbeat e diagnostico operacional
- comandos remotos:
  - `clear-cache`
  - `revalidate-assets`
  - `reinitialize-engine`

Do ponto de vista operacional, o wall ja esta bem mais rico do que um player estatico.

## Comportamento atual de video no wall

### Aceite

Hoje o wall aceita `EventMedia` com:

- `media_type = image`
- `media_type = video`

Desde que:

- a midia esteja aprovada
- a midia esteja publicada
- o wall esteja disponivel/jogavel

### Renderizacao do video normal

`MediaSurface` renderiza video comum do slideshow com:

- `autoPlay`
- `muted`
- `playsInline`
- `loop`

E nao usa:

- `controls`
- `onEnded`
- `poster`

### Consequencia

Video de `EventMedia` hoje se comporta assim:

- toca automaticamente
- sempre mudo
- repete em loop enquanto continuar montado
- nao controla o tempo do slide
- com `loop = true`, nao entrega `ended` como sinal natural de troca

### Regra de duracao atual

O tempo do slideshow e determinado por:

- `useWallEngine`
- `setTimeout(interval_ms)`

O player nao usa `duration_seconds` da `EventMedia` para decidir troca de slide.

### Exemplo concreto: video de 60 segundos

Se o wall estiver com `interval_ms = 10000`:

1. o video entra na tela;
2. comeca a tocar automaticamente;
3. fica mudo;
4. apos cerca de `10s` o engine dispara `advance`;
5. o elemento de video e desmontado;
6. o restante do arquivo nao continua naquele ciclo.

Do ponto de vista de playback:

- sim, existe corte.

Do ponto de vista visual:

- esse corte pode ser suavizado pela transicao do layout;
- mas ainda assim continua sendo um corte de exibicao, nao um fim natural do video.

### E se o video for curto

Exemplo: video de `5s` com `interval_ms = 10000`.

Como o video comum usa `loop`:

- ele toca;
- termina;
- recomeca;
- repete ate o slide trocar.

### Audio

Audio do video comum no wall:

- sempre mudo.

Isso nao depende de layout, porque todos os layouts reutilizam `MediaSurface`.

### Pausa operacional

Aqui existe um detalhe importante.

Se o wall ja estiver pausado no boot:

- o backend nao devolve `files`;
- o player sobe sem fila jogavel.

Se o player ja estiver rodando e recebe `paused` via realtime:

- o slideshow para de agendar `advance`;
- mas o componente de video atualmente nao recebe comando imperativo para `pause()`.

Na pratica:

- o slideshow congela;
- mas um video ja montado pode continuar tocando e loopando.

### Video de anuncio e diferente

Anuncio em video hoje e tratado de forma melhor:

- `muted`
- sem `loop`
- termina por `onEnded`
- tem timeout de seguranca

Ou seja:

- anuncio em video ja e playback-aware;
- video comum de `EventMedia` ainda nao e.

## Comportamento por template

### Layouts de item unico

`fullscreen`

- um item por vez
- fundo expandido em `cover`
- midia principal em `contain`

`cinematic`

- um item por vez
- fundo blur/echo
- midia principal em `contain`

`split`

- um item por vez
- video horizontal usa `cover`
- demais casos seguem `contain`

`polaroid`

- um item por vez
- `contain`

`gallery`

- um item por vez
- `contain`

`spotlight`

- um item por vez
- `contain`

`kenburns`

- um item por vez
- `cover`
- imagem usa duracao derivada de `interval_ms`
- video usa `kb-video-zoom` fixo de `20s`
- se o slide trocar antes, essa animacao de video tambem e interrompida

### Layouts multi-slot

`carousel`

- 3 slots simultaneos
- `cover`
- fade do slot trocado de `0.5s`

`mosaic`

- 3 slots simultaneos
- `cover`
- fade do slot trocado de `0.3s`

`grid`

- 3 slots simultaneos
- `cover`
- fade do slot trocado de `0.25s`

Nos 3 layouts multi-slot:

- podem existir varios videos ao mesmo tempo;
- todos seguem mudos;
- todos podem loopar em paralelo;
- a troca e por slot, nao pelo fim do video.

## Como o cache e o preload de video funcionam hoje

Esse ponto e importante para entender travamento e o que ainda falta.

## Camada 1 - runtime state persistido

O player persiste estado de runtime em:

- `localStorage`
- `IndexedDB`

O que e persistido:

- `currentItemId`
- `senderStats`
- `assetStatus`
- `playCount`
- `playedAt`
- metadados de dimensao/orientacao

Isso ajuda em:

- resync apos reload;
- continuidade da fila e da fairness;
- reutilizacao de metadata ja descoberta.

## Camada 2 - probe de asset

Antes de priorizar um item, o player faz `primeWallAsset()`.

Para video, hoje ele:

- cria um elemento temporario `<video preload="metadata">`
- carrega metadata do arquivo
- extrai `videoWidth` e `videoHeight`
- deduz orientacao
- marca o asset como `ready` ou `error`

Isso serve para:

- validar se o asset abre;
- descobrir orientacao real do video no browser;
- priorizar items `ready` na selecao.

## Camada 3 - Cache API

Quando o probe de asset funciona:

- o player faz `fetch(url)` e guarda uma copia no `Cache API`

Quando o probe direto falha:

- o player tenta ler o blob do `Cache API`
- cria um `objectURL`
- tenta abrir o asset local como fallback `stale`

Isso e importante, mas o alcance atual e limitado.

### O que esse cache atual faz bem

- ajuda a validar e aquecer assets;
- permite fallback de probe quando a rede falha;
- ajuda os diagnostics a identificar estado `stale`;
- reduz retrabalho de metadata probe para assets ja vistos.

### O que esse cache atual nao faz

- nao implementa Service Worker;
- nao intercepta automaticamente o `src` do `<video>` renderizado;
- nao cria um pipeline de playback a partir de blob cached;
- nao tem budget/eviction LRU proprio da aplicacao;
- nao faz transcode/adaptive bitrate;
- nao faz streaming segmentado tipo HLS/DASH;
- nao cria variante otimizada especifica para wall video.

### Consequencia pratica

Hoje o cache do player e uma camada de resiliencia e aquecimento, nao um sistema completo de video playback cacheado.

O video renderizado em `MediaSurface` continua usando a URL original:

- `src={media.url}`

Entao o ganho atual vem de:

- browser cache normal
- possivel aquecimento pelo preload
- reuse interno do navegador

Mas nao existe garantia forte de que um video grande/pesado vai tocar liso apenas porque o `Cache API` recebeu uma copia.

## Camada 4 - preload do proximo item

O player ainda tenta prever o proximo item exato da fila usando a mesma regra de fairness.

Depois disso:

- imagem: usa `img.decode()`
- video: cria um `<video preload="auto" muted>`

Isso melhora:

- micro-flicker
- primeiro buffer do proximo item

Mas ainda tem limites:

- `preload` continua sendo uma dica para o navegador, nao uma garantia de buffer pronto no momento exato da exibicao;
- e um preload oportunistico;
- nao e controle real de buffer por duracao;
- nao substitui um pipeline de video-first.

## Principais regras de negocio atuais do wall

### Regras operacionais

- wall so entra no boot jogavel se estiver `live`
- wall pode ser `paused`, `stopped`, `expired`
- ads tem scheduler proprio
- player aceita comandos remotos de manutencao

### Regras de selecao

- fairness por remetente
- limite de elegiveis por remetente
- cooldown por remetente
- janela de repeticao por remetente
- replay interval por volume da fila
- evitacao de cluster duplicado quando ha alternativa
- preferencia por itens `ready`

### Regras de layout

- `auto` resolve layouts de item unico
- multi-slot so aparece por escolha explicita
- side thumbnails so aparecem quando:
  - player esta `playing`
  - nao e layout multi-slot
  - nao esta mostrando ad

### Regras de branding/experiencia

- QR opcional
- branding opcional
- neon opcional
- credito de remetente opcional
- captions flutuantes em layouts especificos
- performance mode automatico em hardware fraco

## Gaps e problemas do estado atual

### 1. Video comum nao e playback-aware

Esse e o maior gap.

Efeito:

- videos longos sao cortados;
- videos curtos loopam;
- o wall nao sabe quando um video acabou de verdade.

### 2. Pausa do wall nao pausa o video ja montado

Efeito:

- operador enxerga "wall pausado";
- mas o video pode continuar tocando em loop.

### 3. Layouts multi-slot podem tocar varios videos ao mesmo tempo

Efeito:

- maior consumo de CPU/GPU;
- maior pressao de rede/buffer;
- experiencia visual menos previsivel;
- pior para eventos com muitos videos verticais/longos.

### 4. Upload publico de video ainda esta incoerente na UX

Efeito:

- backend aceita video unitario;
- pagina e payload ainda orientam imagem/foto.

### 5. Upload publico de video ainda despacha `GenerateMediaVariantsJob`

Efeito:

- custo conceitual desnecessario no pipeline;
- torna a stack mais confusa;
- passa a ideia de que existe estrategia real de variante para video quando hoje nao existe.

### 6. Boot inicial e realtime nao usam exatamente o mesmo gate de elegibilidade

Hoje:

- `WallRuntimeMediaService` carrega o boot inicial;
- `WallEligibilityService` controla especialmente o realtime.

Efeito:

- a logica de aceite pode nao ser 100% simetrica entre boot e broadcast.

### 7. Nao existe estrategia de wall-specific video variants

Efeito:

- o player geralmente tenta tocar o arquivo original;
- videos grandes, pesados ou com bitrate alto podem travar mais;
- o navegador carrega mais do que deveria para um telão.

### 8. O cache atual ajuda, mas nao e um cache de playback de verdade

Efeito:

- melhora robustez;
- nao garante fluidez para videos longos/pesados;
- ainda depende muito da qualidade da rede e do arquivo original.

### 9. Falta politica de produto para video

Hoje nao existe definicao clara de perguntas como:

- video deve tocar ate o fim?
- qual duracao maxima de video comum?
- video comum deve ter audio sempre mutado?
- multi-slot pode receber video?
- video longo deve ser bloqueado, truncado ou tratado como destaque?

Sem essas respostas, a stack continua ambigua.

## Melhorias recomendadas

## P0 - obrigatorias para tratar video corretamente

### 1. Separar politica de playback para imagem, video comum e video de anuncio

Precisamos de tres comportamentos explicitos:

- imagem
- video de `EventMedia`
- video de anuncio

Hoje apenas anuncio ja e tratado como tipo diferente.

### 2. Tornar video comum playback-aware no reducer

Recomendacao:

- o reducer precisa saber se o item atual e video;
- o ciclo do slide precisa aceitar `video-ended`;
- o scheduler precisa decidir por politica:
  - tocar ate o fim
  - tocar ate um cap
  - aplicar max duration
- a trilha de video precisa observar `play()`, `pause()`, `readyState`, `waiting`, `stalled` e falhas reais de playback

### 3. Definir politica de produto para duracao

Sugestao objetiva:

- `0s a 15s`
  - tocar ate o fim
- `15s a 30s`
  - tocar ate o fim ou cap configuravel por evento
- `> 30s`
  - bloquear no intake publico ou exigir politica especifica

Sem isso, o wall sempre vai parecer "quebrado" para video longo.

### 4. Pausar de verdade o elemento de video quando o wall entra em `paused`

O player precisa:

- chamar `pause()` no video atual;
- manter tempo/estado coerentes;
- decidir se `resume` continua ou reinicia.

Detalhe importante:

- quando a trilha playback-aware entrar, o `resume` precisa tratar a `Promise` retornada por `play()`.

### 5. Criar variante otimizada para wall video

Backend idealmente deve gerar, para video:

- `wall_video`
- `preview/poster`
- metadata confiavel:
  - `duration_seconds`
  - `width`
  - `height`

Objetivo:

- bitrate mais previsivel;
- resolucao adequada ao telão;
- menos travamento em notebooks/TV boxes fracos.
- startup melhor em MP4 progressivo quando fizer sentido usar `+faststart`

## P1 - importantes para fluidez e operacao

### 6. Restringir video em layouts multi-slot

Opcoes:

- bloquear video em `carousel/mosaic/grid`
- ou limitar a no maximo 1 video simultaneo
- ou converter multi-slot para imagens apenas

Hoje o comportamento atual e tecnicamente permissivo demais.

### 7. Melhorar o cache/preload de video

Evolucoes recomendadas:

- adicionar `poster`/thumbnail de video
- warming mais explicito do proximo video
- definir budget de cache e estrategia de limpeza
- estudar object URL ou resposta cacheada como fonte controlada de playback quando houver necessidade real

Observacao:

- Service Worker pode entrar aqui no futuro, mas nao deveria ser tratado como P0 do wall video.

### 8. Alinhar upload publico e UX de video

Precisamos decidir:

- video publico vai ser oficialmente suportado?
- se sim, a tela publica precisa comunicar isso;
- se nao, o backend unitario nao deveria aceitar silenciosamente.

### 9. Remover dispatch desnecessario de `GenerateMediaVariantsJob` para video publico

Isso simplifica a trilha do backend e evita confusao futuras.

## P2 - evolucoes de produto

### 10. Analytics de video

Se video virar first-class citizen, precisamos registrar:

- `started`
- `completed`
- `interrupted`
- `interrupted_by_slide`
- `interrupted_by_pause`

### 11. Regras por evento

Possiveis flags futuras:

- `wall_video_policy`
- `wall_video_max_seconds`
- `wall_video_audio_policy`
- `wall_allow_video_in_multi_layouts`

### 12. Guard rails de plataforma

Melhorias tecnicas futuras que fazem sentido, mas nao deveriam travar a primeira entrega:

- `MediaCapabilities.decodingInfo()` para decidir perfil/variante mais segura em runtime
- Service Worker para controle mais forte de fetch/cache de playback
- readiness mais rica baseada em `readyState`, `canplay`, `waiting` e `stalled`
- `requestVideoFrameCallback()` para telemetria de primeira frame, fluidez e dropped frames

### 13. Ferramentas operacionais

Adicionar no manager:

- contagem de videos na fila
- alerta de video longo
- bitrate/dimensao quando disponivel
- indicacao clara de "video suportado oficialmente" por canal de intake

## O que o time precisa validar agora

Antes de implementar, o time de produto/engenharia precisa fechar estas decisoes:

1. Video comum deve tocar ate o fim ou seguir um cap?
2. Qual duracao maxima aceitavel por canal?
3. Upload publico deve oficialmente aceitar video?
4. Video pode ou nao pode aparecer em layout multi-slot?
5. O wall continuara sempre mudo ou havera politica opcional de audio?
6. Vamos gerar variante otimizada de wall para video?

Sem esse alinhamento, qualquer ajuste no player tende a ser parcial.

## Validacoes executadas

### Snapshot real do wall

Executado:

- `GET http://localhost:8000/api/v1/public/wall/BG6JY36L/boot`

Resultado observado:

- `status = paused`
- `files_count = 0`
- `layout = split`
- `interval_ms = 10000`
- `transition_effect = fade`
- `accepted_orientation = all`
- `ad_mode = disabled`

### Testes backend executados

Executado:

- `cd apps/api && php artisan test --filter=PublicUploadTest`

Resultado:

- `PASS`, `8` testes, `71` assertions

Cobertura relevante confirmada:

- upload publico multiplo de imagem
- upload publico unitario de video
- face indexing em imagem publica
- indisponibilidade operacional do upload

### Testes frontend executados

Executado:

- `cd apps/web && npx.cmd vitest run src/modules/wall/player`

Resultado:

- `PASS`, `22` arquivos, `155` testes

### Novos testes adicionados nesta rodada

Backend:

- `apps/api/tests/Feature/InboundMedia/PublicUploadTest.php`
  - valida upload publico unitario de video

Frontend:

- `apps/web/src/modules/wall/player/components/MediaSurface.test.tsx`
  - valida que video comum do slideshow renderiza `autoplay + muted + loop + playsInline`
- `apps/web/src/modules/wall/player/engine/cache.test.ts`
  - valida probe de metadata de video com `preload="metadata"`
- `apps/web/src/modules/wall/player/engine/preload.test.ts`
  - valida preload proativo de video com `preload="auto"`

## Referencias de codigo

Arquivos centrais para entender a stack atual:

- backend
  - `apps/api/app/Modules/Wall/Http/Controllers/PublicWallController.php`
  - `apps/api/app/Modules/Wall/Services/WallRuntimeMediaService.php`
  - `apps/api/app/Modules/Wall/Services/WallBroadcasterService.php`
  - `apps/api/app/Modules/Wall/Services/WallEligibilityService.php`
  - `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`
  - `apps/api/app/Modules/Wall/Support/WallSelectionPreset.php`
  - `apps/api/app/Modules/InboundMedia/Http/Controllers/PublicUploadController.php`
  - `apps/api/app/Modules/MediaProcessing/Jobs/DownloadInboundMediaJob.php`
  - `apps/api/app/Modules/MediaProcessing/Services/MediaVariantGeneratorService.php`
  - `apps/api/app/Modules/MediaProcessing/Services/MediaAssetUrlService.php`
  - `apps/api/app/Modules/Wall/Services/WallDiagnosticsService.php`

- frontend
  - `apps/web/src/modules/wall/player/hooks/useWallPlayer.ts`
  - `apps/web/src/modules/wall/player/hooks/useWallEngine.ts`
  - `apps/web/src/modules/wall/player/hooks/useWallRealtime.ts`
  - `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`
  - `apps/web/src/modules/wall/player/components/MediaSurface.tsx`
  - `apps/web/src/modules/wall/player/components/AdOverlay.tsx`
  - `apps/web/src/modules/wall/player/engine/selectors.ts`
  - `apps/web/src/modules/wall/player/engine/preload.ts`
  - `apps/web/src/modules/wall/player/engine/cache.ts`
- `apps/web/src/modules/wall/player/engine/storage.ts`
- `apps/web/src/modules/wall/player/engine/layoutStrategy.ts`
- `apps/web/src/modules/wall/player/pusher.ts`

## Fontes externas consultadas

- MDN - `HTMLMediaElement.preload`
  - https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/preload
- MDN - `HTMLMediaElement.ended event`
  - https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/ended_event
- MDN - `HTMLMediaElement.play()`
  - https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/play
- MDN - `HTMLMediaElement.pause()`
  - https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/pause
- MDN - `HTMLMediaElement.readyState`
  - https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/readyState
- MDN - `Autoplay guide for media and Web Audio APIs`
  - https://developer.mozilla.org/en-US/docs/Web/Media/Guides/Autoplay
- MDN - `ServiceWorkerGlobalScope: fetch event`
  - https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerGlobalScope/fetch_event
- MDN - `MediaCapabilities.decodingInfo()`
  - https://developer.mozilla.org/en-US/docs/Web/API/MediaCapabilities/decodingInfo
- MDN - `HTMLVideoElement.requestVideoFrameCallback()`
  - https://developer.mozilla.org/en-US/docs/Web/API/HTMLVideoElement/requestVideoFrameCallback
- FFmpeg formats documentation
  - https://ffmpeg.org/ffmpeg-formats.html

Observacao:

- as recomendacoes de produto desta doc nao saem literalmente da documentacao do navegador;
- elas sao uma traducao de engenharia/produto em cima do comportamento real da plataforma e da stack atual do wall.

## Conclusao

O telão atual ja tem uma base forte:

- realtime funcional
- fairness de fila
- overlays e branding avancados
- ads integrados
- diagnostico operacional
- resiliencia via resync/heartbeat
- preload e cache local de apoio

Mas para video, a plataforma ainda esta em uma zona intermediaria:

- aceita video;
- exibe video;
- nao trata video como citizen de primeira classe.

Se quisermos um wall premium e previsivel para video, o caminho tecnico mais correto agora e:

1. fechar a politica de produto para video;
2. levar playback-aware video para o reducer;
3. gerar variante otimizada de video para wall;
4. melhorar a estrategia de cache/preload;
5. alinhar UX e intake com a politica final.
