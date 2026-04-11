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

Plano de execucao derivado desta analise:

- `docs/architecture/wall-video-playback-execution-plan-2026-04-08.md`

## Resumo executivo

Hoje o wall suporta `image` e `video` como itens normais da fila e a trilha de video ja deixou de ser "slide puro por timer". A Fase 2 do player agora colocou video comum dentro do runtime principal do wall.

Na pratica:

- video comum agora sobe em `WallVideoSurface` controlado, com `autoPlay`, `muted`, `playsInline`, sem `loop` e com `poster` quando disponivel;
- imagem continua avancando por `interval_ms`;
- video comum agora sai por causa:
  - `ended`
  - `cap_reached`
  - `play_rejected`
  - `stalled_timeout`
  - `startup_timeout` / `poster_then_skip`
- a primeira execucao de video agora segue a trilha `poster-first`, `playback-gated`, `timeout-bounded` e `never-blocking`;
- o wall agora chama `pause()` de verdade quando entra em `paused` e retoma pela semantica baseline `resume_if_same_item_else_restart`;
- o reducer agora persiste estado `videoPlayback` com fase, `currentTime`, `durationSeconds`, `readyState`, `exitReason`, `failureReason` e `stallCount`;
- `layoutStrategy` agora impede que o video corrente fique em layout multi-slot, derrubando para layout single-item;
- anuncios em video continuam numa trilha propria e separada, com `loop = false` e saida por `onEnded`;
- o backend persiste `duration_seconds`, `width`, `height` e metadata complementar de video quando o canal fornece hints canonicos ou quando `ffprobe` esta disponivel;
- o boot do wall expoe `video_admission`, `served_variant_key` e `preview_variant_key`;
- o payload de settings do wall agora expoe `video_enabled`, rollout por canal (`public_upload_video_enabled` / `private_inbound_video_enabled`), playback mode, cap, resume mode, audio policy, multi-layout policy e variante preferida;
- o gate unico do wall usa a admissao explicita de video para bloquear video sem metadata minima, sem variante/poster exigidos ou acima da duracao permitida;
- o pipeline agora gera `wall_video_720p`, `wall_video_1080p` opcional e `wall_video_poster` para os fluxos que executam `GenerateMediaVariantsJob` em ambiente com `ffmpeg`.
- o manager agora mostra resumo da policy de video, readiness de `ffmpeg` / `ffprobe` e diagnostico operacional do playback atual;
- o heartbeat agora leva fase de video, progresso, `readyState`, stall count, startup degraded, motivo de saida, motivo de falha e hints de device/rede;
- a trilha de heartbeat agora tambem gera analytics consolidados de video por transicao relevante (`video_start`, `video_first_frame`, `video_waiting`, `video_stalled`, `video_complete`, `video_interrupted_by_cap`, `video_interrupted_by_pause`, `video_play_rejected`).

Os gaps principais agora mudaram:

- a maquina local ja foi provisionada fora do repositorio com `ffmpeg` / `ffprobe` em `%LOCALAPPDATA%\\Programs\\FFmpeg\\bin`, e agora o repositorio tambem tem scripts/runbook + `php artisan media:tooling-status`; os workers reais ainda precisam executar essa provisionacao;
- o repo agora tambem versiona o caminho operacional Ubuntu de ponta a ponta:
  - `scripts/ops/bootstrap-host.sh` instala `ffmpeg`;
  - `scripts/ops/verify-host.sh` valida `ffmpeg`, `ffprobe` e `php artisan media:tooling-status`;
  - `scripts/deploy/healthcheck.sh` valida `php artisan media:tooling-status` antes do switch da release, salvo override explicito `REQUIRE_MEDIA_TOOLING=0`;
  - `scripts/ops/homologate-wall-video.sh` gera o dossie executavel da matriz de homologacao por `device_class` / `network_class`;
- o manager ja expoe admissao e razoes do backend por item em `recentItems`, timeline e live snapshot;
- intake privado agora entra na trilha de variantes quando o tooling esta pronto, e legados ja tem comando de backfill dedicado;
- o rollout oficial agora ja e controlado por wall/canal no manager;
- o gap principal restante passou a ser homologacao remota real por classe de device/rede e execucao da provisionacao nos workers reais.

## Validacao externa da plataforma web

Alguns pontos deste documento foram checados tambem contra documentacao oficial da plataforma web, porque aqui existe risco real de assumir comportamento do navegador de forma errada.

Conclusoes confirmadas:

- `preload` em video e apenas uma dica para o navegador, nao uma garantia de buffer completo;
- com `loop = true`, o navegador nao dispara `ended` no playback normal, entao um video comum em loop nunca entrega um "fim natural" ao player;
- `muted` e `playsInline` fazem sentido na estrategia atual do wall, porque autoplay inline depende desse tipo de configuracao em varios navegadores;
- `play()` retorna `Promise`, entao a trilha playback-aware atual precisa tratar sucesso e falha de inicio do video;
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
- se esse mesmo wall voltar para `live`, um video comum agora nao segue mais o `interval_ms` puro:
  - imagem continua em `10000ms`;
  - video entra na trilha controlada do player;
  - no baseline atual do frontend, video longo tende a sair por cap em cerca de `15s`, nao mais em `10s`.

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
- `DownloadInboundMediaJob` agora tenta persistir metadata real de video via `VideoMetadataExtractorService`, combinando hints canonicos do payload com `ffprobe` quando disponivel.

### 2. Upload publico

O upload publico passa por `PublicUploadController`.

Estado atual do aceite:

- upload unitario aceita `jpg`, `jpeg`, `png`, `gif`, `webp`, `heic`, `heif`, `mp4`, `mov`
- upload multiplo aceita apenas imagem

Observacao importante:

- o backend aceita video unitario;
- o payload agora expoe:
  - `accept_hint = image/*,video/mp4,video/quicktime` quando o rollout publico daquele wall/canal e o tooling permitem video;
  - `accepts_video = true`;
  - `video_single_only = true`;
  - `video_max_duration_seconds`;
- a pagina publica agora respeita isso:
  - galeria aceita fotos ou `1` video curto;
  - camera continua foto-only;
  - envio de video usa `file`;
  - envio multiplo continua `files[]` apenas para imagens;
- `storePublicUpload()` agora tambem usa `VideoMetadataExtractorService`, entao videos novos podem nascer com `duration_seconds`, `width`, `height`, codecs e `container` quando o ambiente consegue extrair isso.
- o backend agora rejeita upload publico de video quando:
  - o rollout publico daquele wall/canal ou a policy base de video estao desligados;
  - nao foi possivel validar duracao e dimensoes;
  - a duracao excede o limite global configurado.

Ou seja:

- agora existe uma policy publica coerente para video curto;
- o rollout oficial por evento/wall/canal ja existe no manager e no backend;
- o que ainda falta e executar esse rollout em ambiente real com homologacao controlada.

### 3. Moderacao e publicacao

Para uma midia aparecer no wall ela precisa passar pelas regras do pipeline:

- `moderation_status = approved`
- `publication_status = published`

O wall nao consome item "recebido" ou "pending" diretamente.

## Diferenca importante entre os caminhos de video

Hoje existem duas trilhas diferentes para video:

### Video inbound privado

- agora pode despachar `GenerateMediaVariantsJob` quando o tooling esta pronto e o rollout privado do wall permite;
- sem tooling ou com rollout privado desligado, continua na trilha legada de moderacao;
- ja nasce mais alinhado com a natureza de video.

### Video de upload publico

- aceita upload unitario de video;
- ainda despacha `GenerateMediaVariantsJob`;
- o job agora gera variantes reais de wall quando o ambiente tem `ffmpeg`:
  - `wall_video_720p`
  - `wall_video_1080p` opcional
  - `wall_video_poster`

Implicacao:

- o upload publico de video deixou de ter um `no-op` conceitual na etapa de variantes;
- a trilha publica ja consegue preparar video para wall sem depender apenas do original;
- a assimetria agora ficou menor, mas ainda existe porque intake privado e upload publico nao entram exatamente com a mesma policy operacional em todos os ambientes.

Esse continua sendo um gap importante para a clareza e para a simetria da stack, principalmente em legados e em ambientes sem tooling completo.

## Metadata e admissao de video no backend

Esta parte mudou de forma relevante.

### Extracao de metadata

Agora existe um servico unico:

- `VideoMetadataExtractorService`

Ele opera em duas camadas:

- primeiro tenta aproveitar hints canonicos ja presentes no payload inbound, como `media.width`, `media.height`, `media.duration`, `has_audio`, codecs e `container`;
- se isso nao for suficiente e o arquivo estiver disponivel localmente, tenta enriquecer a metadata com `ffprobe`.

Hoje isso alimenta pelo menos:

- `duration_seconds`
- `width`
- `height`
- `has_audio`
- `video_codec`
- `audio_codec`
- `bitrate`
- `container`

Limite importante do estado atual:

- se o canal nao fornecer hints e o ambiente nao tiver `ffprobe`, a midia continua podendo entrar sem metadata completa;
- ou seja, a stack melhorou bastante para videos novos, mas ainda nao existe garantia absoluta de metadata rica em todos os ambientes.

### Admissao explicita de `wall-eligible video`

Agora existe uma classificacao explicita no backend:

- `WallVideoAdmissionService`

Ela avalia video por:

- metadata minima
- duracao
- `container` e `video_codec`
- existencia de variante preferida
- existencia de `poster`

E retorna:

- `eligible`
- `eligible_with_fallback`
- `blocked`

Com motivos legiveis, por exemplo:

- `missing_metadata`
- `duration_over_limit`
- `unsupported_format`
- `variant_missing`
- `poster_missing`

Hoje essa classificacao ja e exposta:

- no payload do boot do wall
- no detalhe de `EventMedia`
- no contrato compartilhado de wall types

Estado atualizado desta trilha:

- `WallEligibilityService` agora consome essa admissao explicita como gate final para video em:
  - boot;
  - realtime;
  - simulacao.
- o gate efetivo atual considera:
  - `WALL_VIDEO_ENABLED`;
  - `WALL_VIDEO_MAX_DURATION_SECONDS`;
  - exigencia de metadata minima;
  - exigencia de variante de wall;
  - exigencia de poster;
  - permissao ou nao de fallback para o original.

Limite importante que continua aberto:

- a admissao do wall ja usa settings reais por evento/wall para `video_enabled`, rollout publico/privado, cap e variante preferida;
- o manager ja expoe essa policy e o estado do pipeline;
- o que continua global de ambiente hoje e principalmente a disponibilidade real de `ffmpeg` / `ffprobe` e os defaults usados quando o wall ainda nao sobrescreve uma flag.

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
- quando a midia atual e video:
  - `current_video_phase`
  - `current_video_exit_reason`
  - `current_video_failure_reason`
  - `current_video_position_seconds`
  - `current_video_duration_seconds`
  - `current_video_ready_state`
  - `current_video_stall_count`
  - `current_video_poster_visible`
  - `current_video_first_frame_ready`
  - `current_video_playback_ready`
  - `current_video_playing_confirmed`
  - `current_video_startup_degraded`

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

`MediaSurface` agora separa tres trilhas:

- imagem
- video `poster-only`
- video controlado por `WallVideoSurface`

Para video comum do wall, o runtime atual usa:

- `autoPlay`
- `muted`
- `playsInline`
- `preload="auto"`
- sem `loop`
- com `poster` quando `preview_url` existe
- com `play()` e `pause()` imperativos

O `WallVideoSurface` agora alimenta o reducer com callbacks distintos:

- `onStarting`
- `onFirstFrame`
- `onPlaybackReady`
- `onPlaying`
- `onProgress`
- `onWaiting`
- `onStalled`
- `onEnded`
- `onFailure`

### Consequencia

Video de `EventMedia` hoje se comporta assim:

- toca automaticamente, mas de forma controlada;
- sempre mudo;
- nao usa mais `loop` no wall principal;
- entra com `poster-first` quando existe poster;
- so promove o video para live quando existe readiness minima;
- sai por causa explicita, e nao so por timeout fixo de slideshow.

### Regra de duracao atual

Hoje existem duas lanes diferentes:

- imagem:
  - continua em `useWallEngine + setTimeout(interval_ms)`
- video:
  - sai por `ended`, `cap_reached`, `play_rejected`, `stalled_timeout`, `startup_timeout` ou `poster_then_skip`

No baseline atual da Fase 2:

- videos curtos podem tocar ate o fim;
- videos longos respeitam o `video_playback_mode` e o `video_max_seconds` resolvidos no payload de settings do wall;
- o que continua em aberto nao e mais o transporte dessa policy, e sim o rollout oficial de produto sobre esses settings.

### Exemplo concreto: video de 60 segundos

Se o wall estiver com `interval_ms = 10000`:

1. o video entra na tela;
2. o player mostra `poster` imediato quando disponivel;
3. o `WallVideoSurface` tenta subir o playback em paralelo;
4. o video so e promovido quando houver readiness minima;
5. se tudo correr bem, ele comeca a tocar mudo;
6. no baseline atual, esse video tende a sair por `cap_reached` perto de `15s`, e nao mais por `10s` de `interval_ms`;
7. se o startup falhar ou engasgar cedo demais, ele sai por fallback operacional e a fila segue.

Do ponto de vista de playback:

- ainda pode existir corte para video longo, mas agora ele acontece por politica explicita de cap/fallback, nao por heranca acidental do slideshow.

Do ponto de vista visual:

- o poster-first evita tela preta na entrada;
- a saida continua curta e previsivel;
- a fila nao fica refem de um video frio ou pesado.

### E se o video for curto

Exemplo: video de `5s` com `interval_ms = 10000`.

Como o video comum nao usa mais `loop`:

- ele toca;
- termina;
- dispara `ended`;
- o reducer decide a saida natural.

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
- o `WallVideoSurface` recebe `playerStatus = paused`;
- o componente chama `pause()` no elemento atual.

Na pratica:

- o slideshow congela;
- o video corrente tambem congela de verdade;
- quando o wall volta para `playing`, o runtime tenta retomar pela semantica baseline `resume_if_same_item_else_restart`.

### Video de anuncio e diferente

Anuncio em video hoje e tratado de forma melhor:

- `muted`
- sem `loop`
- termina por `onEnded`
- tem timeout de seguranca

Ou seja:

- anuncio em video continua playback-aware;
- video comum de `EventMedia` agora tambem entrou na trilha playback-aware do player.

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

- isso continua valendo para imagens;
- para video corrente do wall, o `layoutStrategy` agora cai para `fullscreen`, impedindo que o playback-aware fique preso na lane multi-slot;
- isso funciona hoje como guard rail minimo da Fase 2;
- a policy definitiva de multi-slot para video ainda precisa virar setting real e regra de produto.

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

### 1. A trilha playback-aware entrou e a policy ja existe no wall, mas o rollout oficial ainda nao esta fechado

Estado atualizado:

- o player agora ja tem `videoPlayback`, `WallVideoSurface`, poster-first, exit reasons e cap;
- o scheduler de video ja consome `video_playback_mode`, `video_max_seconds` e `video_resume_mode` vindos de `state.settings`.

O que ainda falta:

- executar o rollout gradual em ambiente real usando os settings ja existentes;
- reduzir a dependencia de defaults globais de ambiente onde o wall ainda nao sobrescreve a flag;
- consolidar a leitura desses settings contra a matriz real de homologacao.

### 2. O diagnostico do wall ja expoe razoes operacionais ricas, mas ainda falta validar isso em homologacao real

Estado atualizado:

- o reducer ja classifica `exitReason` e `failureReason`;
- o player ja sabe diferenciar startup ruim, waiting/stalled e cap;
- manager, diagnostics, live snapshot e timeline ja expoem decisao do backend por item;
- heartbeat agora tambem carrega hints de hardware/rede e gera analytics consolidados por transicao relevante.

O que ainda falta:

- fechar a leitura operacional disso em ambiente real por device/rede;
- revisar se a agregacao atual de analytics cobre bem os casos de rollout controlado;
- decidir se entra uma visao agregada por sessao/device no manager.

### 3. O guard rail de multi-slot entrou, mas a policy definitiva ainda nao foi fechada

Estado atualizado:

- video corrente agora nao entra mais em `carousel`, `mosaic` ou `grid`;
- o player derruba para layout single-item.

O que ainda falta:

- decidir se isso continuara travado por default ou se havera override controlado;
- decidir se havera excecao futura para `1` video simultaneo;
- refletir isso no manager e na homologacao real.

### 4. Upload publico de video agora esta coerente e rolloutado por evento/wall/canal

Estado atualizado:

- payload, copy e validacao do upload publico agora falam a mesma lingua;
- video publico segue o caminho unitario;
- lote continua imagens-only;
- o manager ja controla `public_upload_video_enabled` por wall.

O que ainda falta:

- executar o rollout oficial gradualmente em walls reais;
- homologar o canal publico com tooling e variantes efetivamente presentes nos workers.

### 5. Variantes reais de video ainda dependem do caminho de pipeline e do ambiente

Efeito:

- o upload publico agora ja consegue gerar `wall_video_*` e `wall_video_poster`, mas isso ainda depende de `GenerateMediaVariantsJob` rodar com `ffmpeg` disponivel;
- o intake privado agora pode entrar nessa mesma trilha, mas ainda existe assimetria entre canais quando o rollout privado ou o tooling nao estao prontos;
- videos legados ou ambientes sem `ffmpeg` continuam caindo em `eligible_with_fallback` e podem depender do original.

### 6. Simetria base entre boot, realtime e simulacao foi corrigida

Estado atualizado:

- `WallRuntimeMediaService` agora filtra a fila com `WallEligibilityService`;
- boot, broadcast realtime e simulacao passaram a compartilhar o mesmo gate atual para:
  - `settings->isPlayable()`
  - `approved + published`
  - `media_type` permitido
  - orientacao aceita

Validacao:

- `PublicWallBootTest` agora confirma que o boot exclui midia vertical em wall `landscape`;
- `MediaPipelineEventsTest` confirma que o realtime nao broadcasta esse mesmo caso;
- `WallDiagnosticsTest` confirma que a simulacao tambem nao inclui esse item.

O que ainda falta nesta trilha:

- agregar essas razoes de inelegibilidade com mais contexto por sessao/device na homologacao real;
- na pratica, `recentItems`, timeline e live snapshot ja fazem a leitura por item; o que ainda falta e consolidar a leitura agregada.
- decidir se vale subir uma superfície agregada adicional para operação, além das views por item já existentes.

### 7. A admissao explicita de video agora ja governa o gate final com rollout por wall/canal

Estado atualizado:

- `WallVideoAdmissionService` ja classifica cada video como `eligible`, `eligible_with_fallback` ou `blocked`;
- `WallPayloadFactory` ja expoe `duration_seconds`, metadata complementar e `video_admission` no boot;
- `PublicWallBootTest` agora valida essa saida explicitamente;
- `WallEligibilityService` agora usa essa admissao como gate efetivo para boot, realtime e simulacao;
- videos `fallback-only` deixaram de entrar no wall quando a policy do wall exige variante/poster e nao permite fallback.

O que ainda falta nesta trilha:

- concluir o rollout remoto de tooling e o backfill dos legados;
- consolidar as razoes de inelegibilidade na homologacao operacional;
- alinhar os futuros settings do wall com esse gate sem duplicar regra.

## Variantes reais de video no pipeline

Esta parte mudou de forma relevante na etapa `1.4`.

### O que agora e gerado

`MediaVariantGeneratorService` agora gera para video, quando o fluxo passa por `GenerateMediaVariantsJob` e o ambiente tem `ffmpeg`:

- `wall_video_720p`
- `wall_video_1080p` quando a origem justifica
- `wall_video_poster`

Baseline aplicado hoje:

- container `MP4`
- video `H.264 / AVC`
- audio `AAC`
- `+faststart`

### Como o resolutor de URLs se comporta agora

`MediaAssetUrlService` passou a distinguir melhor as superficies:

- `wall()` para video prefere `wall_video_720p`, depois `wall_video_1080p`, e so entao cai no original;
- `thumbnail()` para video prefere `wall_video_poster`;
- `preview()` para video continua retornando variante de video, e nao poster, para nao quebrar gallery/media que hoje renderizam `<video src={preview_url}>`;
- `WallPayloadFactory` usa poster no `preview_url` do wall, entao o manager/simulacao nao ficam dependentes de tentar renderizar um mp4 em slots de imagem.

### Limite operacional importante

No ambiente local desta auditoria:

- `ffmpeg` nao estava disponivel no `PATH`;
- `ffprobe` tambem nao estava disponivel no `PATH`.

Implicacao:

- a implementacao de codigo desta etapa esta pronta;
- os binarios agora sao configuraveis por:
  - `MEDIA_FFMPEG_BIN`
  - `MEDIA_FFPROBE_BIN`
  - `apps/api/config/media_processing.php`
- a validacao automatizada foi feita com `Process::fake`;
- a geracao real em runtime ainda depende de instalar/provisionar `ffmpeg`/`ffprobe` nos workers/ambientes onde o pipeline roda.

### 8. A estrategia de wall-specific video variants existe, mas ainda nao cobre toda a stack

Estado mais preciso apos `1.4`:

- isso deixou de ser verdade para videos novos que ja passaram pela geracao de `wall_video_*` + poster;
- continua sendo verdade para legados, ambientes sem `ffmpeg` e casos em que o rollout privado ainda nao executou a trilha de variantes.

Efeito:

- o player geralmente tenta tocar o arquivo original;
- videos grandes, pesados ou com bitrate alto podem travar mais;
- o navegador carrega mais do que deveria para um telão.

### 9. O cache atual ajuda, mas nao e um cache de playback de verdade

Observacao de rollout relacionada a variantes:

- videos novos do fluxo que gera variantes ja podem sair do original e usar `wall_video_*` + poster;
- ainda faltam cobertura total para legados, workers remotos sem tooling e casos de rollout privado ainda nao homologados;
- enquanto essa cobertura nao for completa, o player ainda pode cair no original em parte da base.

Efeito:

- melhora robustez;
- nao garante fluidez para videos longos/pesados;
- ainda depende muito da qualidade da rede e do arquivo original.

### 10. Falta politica de produto para video

Hoje nao existe definicao clara de perguntas como:

- video deve tocar ate o fim?
- qual duracao maxima de video comum?
- video comum deve ter audio sempre mutado?
- multi-slot pode receber video?
- video longo deve ser bloqueado, truncado ou tratado como destaque?

Sem essas respostas, a stack continua ambigua.

## Refino: os 5 gaps centrais remanescentes da logica de video

Depois da Fase 2, os gaps centrais mudaram. A base playback-aware ja existe; o que falta agora e fechar produto, operacao e rollout.

### 1. Falta fechar o rollout oficial da policy de video que ja existe por evento/wall

Hoje o wall ja tem policy implementada para:

- cap de video longo;
- `resume_if_same_item_else_restart`;
- audio mutado;
- guard rail de multi-slot.

O que ainda falta:

- fechar o rollout oficial de produto usando esses settings;
- decidir como essa policy conversa com upload publico e intake privado;
- sair do estado de baseline global nos pontos que ainda estao em config de ambiente.

### 2. Falta expor diagnostico operacional rico de video

Hoje o reducer ja sabe diferenciar:

- `ended`
- `cap_reached`
- `play_rejected`
- `startup_timeout`
- `poster_then_skip`
- `stalled_timeout`

O que ainda falta:

- expor no manager as razoes de inelegibilidade do backend por item;
- fechar analytics/eventos de video;
- evoluir de resumo operacional para um inspector por item mais rico.

### 3. Falta fechar a cobertura de variantes em toda a stack

Hoje:

- upload publico + `GenerateMediaVariantsJob` ja conseguem gerar `wall_video_*` + poster;
- intake privado, legados e ambientes sem `ffmpeg` ainda podem cair no original.

O que ainda falta:

- provisionar `ffmpeg` / `ffprobe` nos ambientes reais;
- reduzir a assimetria residual do intake privado nos ambientes em que o rollout privado ainda nao foi homologado;
- planejar normalizacao/backfill dos legados.

### 4. Falta integrar melhor politica de video com politica de fila

Hoje o wall ja sabe sair de um video por causa, mas a fairness ainda pensa majoritariamente em contagem de exibicoes.

O que ainda falta:

- medir tempo consumido por video;
- registrar `completed`, `capped` e `interrupted`;
- evitar distorcao da fila por videos longos.

### 5. Falta completar a trilha premium de cache, warming e homologacao real

Hoje:

- existe `poster-first`;
- existe `preload="auto"` oportunistico;
- existe probe de metadata;
- existe fallback operacional sem travar a fila.

O que ainda falta:

- warming mais forte do proximo video;
- diagnostico de device/rede degradados;
- homologacao real por classe de device e rede;
- decidir se `Service Worker` e guard rails de plataforma entram depois.

## Melhorias remanescentes recomendadas

### P0 restante

- provisionar `ffmpeg` / `ffprobe` nos workers reais e ambientes remotos;
- fechar a homologacao operacional do rollout de video com matriz real de device/rede.

### P1 restante

- fairness considerando tempo consumido por video;
- warming/cache operacional mais forte.

### P2 restante

- `Service Worker` para playback cacheado de forma controlada;
- `MediaCapabilities.decodingInfo()` para guard rail de runtime;
- `requestVideoFrameCallback()` para telemetria premium;
- politicas mais ricas por fase do evento, layout e device profile.

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

- `cd apps/api && php artisan test tests/Unit/Modules/MediaProcessing/MediaToolingStatusServiceTest.php tests/Feature/MediaProcessing/BackfillWallVideoVariantsCommandTest.php tests/Feature/InboundMedia/PublicUploadTest.php tests/Feature/InboundMedia/InboundMediaPipelineTest.php tests/Feature/Wall/WallInsightsTest.php tests/Feature/Wall/WallDiagnosticsTest.php tests/Feature/Wall/WallLiveSnapshotTest.php`
- `cd apps/api && php artisan test tests/Unit/Modules/MediaProcessing/VideoMetadataExtractorServiceTest.php tests/Unit/Modules/MediaProcessing/MediaVariantGeneratorServiceTest.php tests/Unit/Modules/Wall/WallEligibilityServiceTest.php tests/Feature/Wall/PublicWallBootTest.php tests/Feature/Wall/WallDiagnosticsTest.php tests/Feature/MediaProcessing/MediaPipelineEventsTest.php tests/Feature/InboundMedia/PublicUploadTest.php`
- `cd apps/api && php artisan test tests/Feature/MediaProcessing/MediaToolingStatusCommandTest.php tests/Feature/Wall/WallAuthorizationTest.php tests/Feature/InboundMedia/PublicUploadTest.php tests/Feature/InboundMedia/InboundMediaPipelineTest.php tests/Feature/Wall/WallDiagnosticsTest.php tests/Feature/Wall/WallVideoAnalyticsTrackingTest.php`
- `cd apps/api && php artisan test tests/Feature/Wall/PublicWallBootTest.php tests/Feature/Wall/WallInsightsTest.php tests/Feature/Wall/WallLiveSnapshotTest.php tests/Unit/Modules/Wall/WallEligibilityServiceTest.php`
- `cd packages/shared-types && C:\\laragon\\www\\eventovivo\\apps\\web\\node_modules\\.bin\\tsc.cmd --noEmit src\\index.ts src\\wall.ts`
- `cd apps/web && npm run type-check`
- `Get-Command ffmpeg`
- `Get-Command ffprobe`
- `winget install --id Gyan.FFmpeg --accept-source-agreements --accept-package-agreements`
- `cd apps/api && php artisan media:tooling-status`
- `php inline bootstrap -> MediaToolingStatusService::payload()`
- `C:\\Program Files\\Git\\bin\\bash.exe -n scripts/ops/bootstrap-host.sh scripts/ops/verify-host.sh scripts/ops/homologate-wall-video.sh`
- `C:\\Program Files\\Git\\bin\\bash.exe scripts/ops/homologate-wall-video.sh --help`
- `cd apps/api && php artisan test tests/Unit/Shared/WallVideoOpsRunbookCharacterizationTest.php tests/Feature/MediaProcessing/MediaToolingStatusCommandTest.php tests/Feature/InboundMedia/PublicUploadTest.php tests/Feature/InboundMedia/InboundMediaPipelineTest.php tests/Feature/Wall/WallVideoAnalyticsTrackingTest.php`
- `C:\\Program Files\\Git\\bin\\bash.exe -n scripts/ops/bootstrap-host.sh scripts/ops/verify-host.sh scripts/ops/homologate-wall-video.sh scripts/deploy/healthcheck.sh scripts/deploy/deploy.sh scripts/deploy/smoke-test.sh`
- `cd apps/api && php artisan test tests/Unit/Shared/WallVideoOpsRunbookCharacterizationTest.php tests/Feature/MediaProcessing/MediaToolingStatusCommandTest.php tests/Feature/InboundMedia/PublicUploadTest.php tests/Feature/InboundMedia/InboundMediaPipelineTest.php tests/Feature/Wall/WallVideoAnalyticsTrackingTest.php tests/Feature/Wall/WallDiagnosticsTest.php`
- `cd apps/web && npx.cmd vitest run src/modules/wall/components/manager/inspector/WallAppearanceTab.test.tsx src/modules/wall/components/manager/diagnostics/WallPlayerDetailsSheet.test.tsx src/modules/wall/player/runtime-profile.test.ts src/modules/wall/player/hooks/useWallPlayer.test.tsx`

Resultado:

- `MediaToolingStatusServiceTest + BackfillWallVideoVariantsCommandTest + PublicUploadTest + InboundMediaPipelineTest + WallInsightsTest + WallDiagnosticsTest + WallLiveSnapshotTest` -> `PASS`, `42` testes, `405` assertions
- `VideoMetadataExtractorServiceTest + MediaVariantGeneratorServiceTest + WallEligibilityServiceTest + PublicWallBootTest + WallDiagnosticsTest + MediaPipelineEventsTest + PublicUploadTest` -> `PASS`, `51` testes, `361` assertions
- `WallEligibilityServiceTest + PublicWallBootTest + MediaVariantGeneratorServiceTest + MediaToolingStatusServiceTest + WallDiagnosticsTest + WallAuthorizationTest + PublicUploadTest` -> `PASS`, `47` testes, `323` assertions
- `MediaToolingStatusCommandTest + WallAuthorizationTest + PublicUploadTest + InboundMediaPipelineTest + WallDiagnosticsTest + WallVideoAnalyticsTrackingTest` -> `PASS`, `37` testes, `327` assertions
- `PublicWallBootTest + WallInsightsTest + WallLiveSnapshotTest + WallEligibilityServiceTest` -> `PASS`, `31` testes, `239` assertions
- `shared-types tsc` -> `PASS`
- `apps/web type-check` -> `PASS`
- `ffmpeg` / `ffprobe` no `PATH` local -> ambos ausentes (`null`)
- `winget install` de `Gyan.FFmpeg` -> `PASS`
- `php artisan media:tooling-status` com `.env` atual -> `Status: ready`
- `MediaToolingStatusService::payload()` com `.env` atual -> `ready = true`, `ffmpeg_available = true`, `ffprobe_available = true`
- `bash -n` dos scripts operacionais de video (`bootstrap-host`, `verify-host`, `homologate-wall-video`) -> `PASS`
- `homologate-wall-video.sh --help` -> `PASS`
- `WallVideoOpsRunbookCharacterizationTest + MediaToolingStatusCommandTest + PublicUploadTest + InboundMediaPipelineTest + WallVideoAnalyticsTrackingTest` -> `PASS`, `24` testes, `221` assertions
- `bash -n` dos scripts de ops/deploy relevantes (`bootstrap-host`, `verify-host`, `homologate-wall-video`, `healthcheck`, `deploy`, `smoke-test`) -> `PASS`
- `WallVideoOpsRunbookCharacterizationTest + MediaToolingStatusCommandTest + PublicUploadTest + InboundMediaPipelineTest + WallVideoAnalyticsTrackingTest + WallDiagnosticsTest` -> `PASS`, `32` testes, `310` assertions
- testes frontend focados em manager/diagnostics/heartbeat -> `PASS`, `4` arquivos, `11` testes
- validacao remota na VPS Ubuntu `24.04`:
  - `apt-get install -y ffmpeg jq` -> `PASS`
  - `php artisan media:tooling-status` em `/var/www/eventovivo/current/apps/api` -> `Status: ready`
  - `bash /var/www/eventovivo/scripts/healthcheck.sh /var/www/eventovivo/current` -> `release ok`
  - `bash /var/www/eventovivo/scripts/verify-host.sh --require-shared-env --no-sudo` -> `Host verification completed`
  - `bash /var/www/eventovivo/scripts/homologate-wall-video.sh ...` -> relatorio salvo em `/var/www/eventovivo/shared/wall-video-homologation/20260410T220918Z-wall-live-xdq0pubs-vps-ubuntu-24-04-saudavel-server-curl.md`
  - inventario real no momento da rodada:
    - `publishedApprovedVideos = 0`
    - `liveWalls = 1`
    - `wall_player_runtime_statuses = 0`

Observacao importante:

- uma rerodada mais ampla com `php artisan test --filter=Wall`, `--filter=InboundMedia` e `--filter=BackfillWallVideoVariantsCommandTest` esbarrou num problema paralelo do workspace em SQLite/migrations (`duplicate column name: billing_order_id`);
- esse erro nao veio desta trilha de video e nao foi usado como bloqueio para o fechamento da rodada.

Cobertura relevante confirmada:

- upload publico multiplo de imagem
- upload publico unitario de video
- rejeicao de video publico acima do limite configurado
- rejeicao de video publico quando o rollout publico do wall/canal ou a policy base desabilitam video
- bootstrap publico comunicando `accept_hint`, `accepts_video`, `video_single_only` e `video_max_duration_seconds`
- enriquecimento de metadata de video via `ffprobe`
- persistencia de metadata minima e complementar no intake privado de video
- face indexing em imagem publica
- indisponibilidade operacional do upload
- boot, realtime e simulacao agora compartilham o mesmo gate final de video com cap de duracao e exigencia de metadata/variante/poster
- o boot publico agora expoe `video_admission` com estado e motivos
- o boot publico agora expoe `served_variant_key` e `preview_variant_key`
- quando as variantes existem, o wall agora serve `wall_video_*` e poster no boot/pipeline
- sem variante de video, o wall bloqueia a entrada quando a policy do wall exige variante/poster
- a lane `media-variants` agora usa timeout compativel com a trilha `ffmpeg`
- `WallInsights`, `WallLiveSnapshot` e simulacao agora expoem decisao de backend por item, com `videoAdmission`, `servedVariantKey` e `previewVariantKey`
- o manager agora mostra `Video Decision Inspector` por item e a timeline operacional tambem mostra a decisao do backend
- o intake privado agora despacha `GenerateMediaVariantsJob` para video quando o tooling esta pronto
- o rollout oficial de video agora pode ser desligado por canal no proprio wall, separando upload publico de intake privado
- existe comando de backfill para videos legados sem metadata ou sem variantes de wall
- existe comando `media:tooling-status` para validar readiness real do ambiente antes de ligar rollout oficial
- o heartbeat agora persiste hints de hardware/rede (`hardware_concurrency`, `device_memory_gb`, `network_effective_type`, `network_save_data`, `network_downlink_mbps`, `network_rtt_ms`, `prefers_reduced_motion`, `document_visibility_state`)
- a trilha de analytics agora registra eventos consolidados de video por transicao, com dedupe por heartbeat (`wall.video_start`, `wall.video_first_frame`, `wall.video_waiting`, `wall.video_stalled`, `wall.video_complete`, `wall.video_interrupted_by_cap`, `wall.video_interrupted_by_pause`, `wall.video_play_rejected`)
- o manager agora mostra o perfil operacional da maquina e da rede no detalhe da tela conectada
- a maquina local agora esta provisionada fora do repositorio com binarios acessiveis pelo backend em `%LOCALAPPDATA%\\Programs\\FFmpeg\\bin`

### Testes frontend executados

Executado:

- `cd apps/web && npx.cmd vitest run src/modules/wall/player`
- `cd apps/web && npx.cmd vitest run src/modules/wall/player/engine/selectors.test.ts src/modules/wall/player/engine/cache.test.ts src/modules/wall/player/engine/preload.test.ts src/modules/wall/player/components/MediaSurface.test.tsx`
- `cd apps/web && npx.cmd vitest run src/modules/upload/PublicEventUploadPage.test.tsx src/modules/wall/player/components/MediaSurface.test.tsx`
- `cd apps/web && npx.cmd vitest run src/modules/wall/components/manager/diagnostics/WallPlayerDetailsSheet.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx src/modules/wall/player/hooks/useWallEngine.test.tsx src/modules/wall/player/hooks/useWallPlayer.test.tsx src/modules/wall/player/components/WallPlayerRoot.test.tsx src/modules/wall/player/engine/autoplay.test.ts src/modules/wall/player/engine/layoutStrategy.test.ts`
- `cd apps/web && npx.cmd vitest run src/modules/wall/components/manager/recent/WallRecentMediaDetailsSheet.test.tsx src/modules/wall/components/manager/stage/WallUpcomingTimeline.test.tsx src/modules/wall/components/manager/stage/WallHeroStage.test.tsx`

Resultado:

- `PASS`, `23` arquivos, `170` testes
- `PASS`, `4` arquivos, `24` testes
- `PASS`, `6` arquivos, `54` testes
- `PASS`, `7` arquivos, `75` testes
- `PASS`, `3` arquivos, `6` testes
- `PASS`, `npm run type-check`

### Novos testes adicionados nesta rodada

Backend:

- `apps/api/tests/Feature/InboundMedia/PublicUploadTest.php`
  - valida upload publico unitario de video, cap de duracao e rollout publico por wall/canal
- `apps/api/tests/Feature/InboundMedia/InboundMediaPipelineTest.php`
  - valida persistencia de metadata minima e complementar quando o intake privado ja traz hints canonicos
- `apps/api/tests/Feature/Wall/PublicWallBootTest.php`
  - valida que boot agora exclui midia fora da orientacao aceita
- `apps/api/tests/Feature/Wall/PublicWallBootTest.php`
  - valida `video_admission` e metadata de video no boot publico
- `apps/api/tests/Feature/MediaProcessing/MediaPipelineEventsTest.php`
  - valida que realtime nao broadcasta midia fora da orientacao aceita
- `apps/api/tests/Feature/Wall/WallDiagnosticsTest.php`
  - valida que a simulacao tambem respeita a mesma elegibilidade
- `apps/api/tests/Unit/Modules/MediaProcessing/MediaAssetUrlServiceTest.php`
  - valida fallback de video para original, preferencia por `wall_video_*` e poster dedicado
- `apps/api/tests/Unit/Modules/MediaProcessing/MediaVariantGeneratorServiceTest.php`
  - valida geracao de `wall_video_720p`, `wall_video_1080p` opcional e `wall_video_poster`
- `apps/api/tests/Unit/Modules/MediaProcessing/MediaToolingStatusServiceTest.php`
  - valida readiness de `ffmpeg` / `ffprobe` configurados por ambiente
- `apps/api/tests/Feature/MediaProcessing/BackfillWallVideoVariantsCommandTest.php`
  - valida fila de backfill para videos legados elegiveis e recusa quando o tooling nao esta pronto
- `apps/api/tests/Unit/Modules/MediaProcessing/VideoMetadataExtractorServiceTest.php`
  - valida hints canonicos, enriquecimento por `ffprobe` e fallback quando `ffprobe` falha
- `apps/api/tests/Unit/Modules/Wall/WallVideoAdmissionServiceTest.php`
  - valida `eligible`, `eligible_with_fallback` e `blocked`
- `apps/api/tests/Unit/Modules/Wall/WallEligibilityServiceTest.php`
  - valida gate final de video com metadata minima, variante, poster e fallback conforme policy do wall
- `apps/api/tests/Feature/MediaProcessing/MediaPipelineJobsTest.php`
  - valida que `GenerateMediaVariantsJob` usa a trilha `ffmpeg` para video e persiste `wall_video_720p` + `wall_video_poster`
- `apps/api/tests/Unit/MediaProcessing/HorizonConfigTest.php`
  - valida timeout alinhado da lane `media-variants`
- `apps/api/tests/Feature/Wall/WallInsightsTest.php`
  - valida admissao do backend e variante escolhida na lista recente do manager
- `apps/api/tests/Feature/Wall/WallLiveSnapshotTest.php`
  - valida `videoAdmission`, `servedVariantKey` e `previewVariantKey` no item atual e no proximo
- `apps/api/tests/Feature/Wall/WallVideoAnalyticsTrackingTest.php`
  - valida eventos consolidados de analytics de video por transicao e dedupe de `play_rejected`
- `apps/api/tests/Feature/MediaProcessing/MediaToolingStatusCommandTest.php`
  - valida o comando operacional `media:tooling-status` em ambiente pronto e nao pronto
- `apps/api/tests/Unit/Shared/WallVideoOpsRunbookCharacterizationTest.php`
  - valida que bootstrap Ubuntu instala `ffmpeg`, que `verify-host.sh` e `healthcheck.sh` checam tooling readiness, e que o repo entrega o runbook `homologate-wall-video.sh`

Frontend:

- `apps/web/src/modules/wall/player/components/MediaSurface.test.tsx`
  - valida que video comum do wall nao usa `loop` e que a lane `poster-only` renderiza o poster sem tocar outro video ao fundo
- `apps/web/src/modules/wall/player/components/WallVideoSurface.test.tsx`
  - valida `poster-first`, promocao por readiness minima, `play()` / `pause()` / `resume()`, `ended` e timeout bounded para `waiting/stalled`
- `apps/web/src/modules/wall/player/hooks/useWallEngine.test.tsx`
  - valida que imagem continua por timer, mas video agora sai por `ended` e por `cap_reached`
- `apps/web/src/modules/wall/player/engine/layoutStrategy.test.ts`
  - valida que video corrente nao fica em `carousel`, `mosaic` ou `grid`
- `apps/web/src/modules/wall/pages/EventWallManagerPage.test.tsx`
  - valida resumo da policy de video, readiness do pipeline e avisos operacionais no manager
- `apps/web/src/modules/wall/components/manager/diagnostics/WallPlayerDetailsSheet.test.tsx`
  - valida fase, progresso, motivo de saida, falha de playback e perfil de hardware/rede no detalhe operacional
- `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.test.tsx`
  - valida rollout por canal no manager (`public_upload_video_enabled` / `private_inbound_video_enabled`)
- `apps/web/src/modules/wall/components/manager/recent/WallRecentMediaDetailsSheet.test.tsx`
  - valida `Video Decision Inspector`, motivos do backend e playback source por item recente
- `apps/web/src/modules/wall/components/manager/stage/WallUpcomingTimeline.test.tsx`
  - valida admissao e variante escolhida na timeline do manager
- `apps/web/src/modules/wall/components/manager/stage/WallHeroStage.test.tsx`
  - valida detalhe de decisao do backend no item atual e na proxima exibicao
- `apps/web/src/modules/wall/player/runtime-profile.test.ts`
  - valida leitura segura de hints de device/rede para heartbeat e homologacao
- `apps/web/src/modules/wall/player/engine/selectors.test.ts`
  - valida que o engine atual prefere itens `ready`, mas ainda permite itens `idle` quando nao existe nenhum `ready`
- `apps/web/src/modules/wall/player/engine/cache.test.ts`
  - valida probe de metadata de video com `preload="metadata"`
- `apps/web/src/modules/wall/player/engine/preload.test.ts`
  - valida preload proativo de video com `preload="auto"`
- `apps/web/src/modules/upload/PublicEventUploadPage.test.tsx`
  - valida bootstrap de video curto, envio unitario via `file`, lote de imagens via `files[]` e rejeicao de batch misto

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
  - `apps/api/app/Modules/MediaProcessing/Services/VideoMetadataExtractorService.php`
  - `apps/api/app/Modules/MediaProcessing/Services/MediaVariantGeneratorService.php`
  - `apps/api/app/Modules/MediaProcessing/Services/MediaAssetUrlService.php`
  - `apps/api/app/Modules/Wall/Services/WallVideoAdmissionService.php`
  - `apps/api/app/Modules/Wall/Services/WallDiagnosticsService.php`

- frontend
  - `apps/web/src/modules/wall/player/hooks/useWallPlayer.ts`
  - `apps/web/src/modules/wall/player/hooks/useWallEngine.ts`
  - `apps/web/src/modules/wall/player/hooks/useWallRealtime.ts`
  - `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`
  - `apps/web/src/modules/wall/player/components/MediaSurface.tsx`
  - `apps/web/src/modules/wall/player/components/WallVideoSurface.tsx`
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
- MDN - `NetworkInformation effectiveType`
  - https://developer.mozilla.org/en-US/docs/Web/API/NetworkInformation/effectiveType
- MDN - `Navigator.deviceMemory`
  - https://developer.mozilla.org/en-US/docs/Web/API/Navigator/deviceMemory
- MDN - `Navigator.hardwareConcurrency`
  - https://developer.mozilla.org/en-US/docs/Web/API/Navigator/hardwareConcurrency
- Chrome DevTools - network and CPU throttling reference
  - https://developer.chrome.com/docs/devtools/performance/reference/
- Chrome DevTools - device mode
  - https://developer.chrome.com/docs/devtools/device-mode/
- Laravel Horizon - deploy e process monitor
  - https://laravel.com/docs/horizon#deploying-horizon
- Ubuntu Packages - `ffmpeg` em Ubuntu 24.04 Noble
  - https://packages.ubuntu.com/noble/ffmpeg
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

Para video, o estado atual ja saiu da zona de "slide com tag `<video>`" e entrou numa base tecnicamente bem mais madura:

- aceita video;
- extrai metadata minima e complementar;
- calcula admissao explicita;
- gera variante de wall e poster quando o ambiente suporta;
- trata video comum no reducer com playback-aware runtime;
- usa `poster-first`, cap baseline, pause/resume reais e saida por causa.

O que ainda falta para chamar a trilha de video de premium e previsivel ponta a ponta e:

1. replicar esse provisionamento de `ffmpeg` / `ffprobe` em qualquer worker/host adicional que ainda nao tenha passado por `media:tooling-status`;
2. rodar a homologacao real por classe de device e rede com a nova telemetria de heartbeat;
   - a VPS principal Ubuntu `24.04` ja foi provisionada e validada;
   - o fechamento da matriz ainda depende de wall com video publicado/aprovado e de player real conectado enviando heartbeat;
3. melhorar a estrategia de cache/preload para cenarios degradados sem depender cedo de `Service Worker`.
