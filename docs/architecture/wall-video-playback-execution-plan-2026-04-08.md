# Wall Video Playback Execution Plan - 2026-04-08

## Objetivo

Transformar o diagnostico de `wall-video-playback-current-state-2026-04-08.md` em um plano de execucao implementavel para tornar video um fluxo de primeira classe dentro do telão.

Este plano responde 8 perguntas:

1. o que esta validado hoje por codigo, testes e comportamento real;
2. o que e limitacao real do navegador e do Chrome, e nao bug nosso;
3. o que entra no `P0` para o wall parar de tratar video como slide;
4. o que entra no `P1` para robustez de playback e operacao;
5. o que deve ficar claramente para `P2`;
6. quais arquivos e modulos devem ser alterados;
7. quais testes automatizados precisam existir em cada etapa;
8. qual e a definicao de pronto antes de ligar video em producao.

## Referencias primarias

- `docs/architecture/wall-video-playback-current-state-2026-04-08.md`
- `docs/architecture/telao-ao-vivo-execution-plan.md`
- `apps/web/src/modules/wall/player/components/MediaSurface.test.tsx`
- `apps/web/src/modules/wall/player/engine/autoplay.test.ts`
- `apps/web/src/modules/wall/player/engine/cache.test.ts`
- `apps/web/src/modules/wall/player/engine/preload.test.ts`
- `apps/web/src/modules/wall/player/components/AdOverlay.test.tsx`
- `apps/web/src/modules/wall/player/hooks/useWallEngine.test.tsx`
- `apps/api/tests/Feature/InboundMedia/PublicUploadTest.php`
- `apps/api/tests/Feature/InboundMedia/InboundMediaPipelineTest.php`
- `apps/api/tests/Feature/MediaProcessing/EventMediaListTest.php`

## Validacao executada nesta rodada

Frontend:

- `cd apps/web && npx.cmd vitest run src/modules/wall/player`
  - `22 arquivos`
  - `155 testes`
  - `PASS`
- `cd apps/web && npx.cmd vitest run src/modules/wall/player/engine/selectors.test.ts src/modules/wall/player/engine/cache.test.ts src/modules/wall/player/engine/preload.test.ts src/modules/wall/player/components/MediaSurface.test.tsx`
  - `4 arquivos`
  - `24 testes`
  - `PASS`
- `cd apps/web && npx.cmd vitest run src/modules/wall/player/components/MediaSurface.test.tsx src/modules/wall/player/engine/autoplay.test.ts src/modules/wall/player/engine/cache.test.ts src/modules/wall/player/engine/preload.test.ts src/modules/wall/player/components/AdOverlay.test.tsx src/modules/wall/player/hooks/useWallEngine.test.tsx src/modules/wall/player/hooks/useWallPlayer.test.tsx src/modules/wall/player/components/WallPlayerRoot.test.tsx`
- resultado:
  - `8 arquivos`
  - `39 testes`
  - `PASS`

Backend:

- `cd apps/api && php artisan test --filter=Wall`
  - `66 testes`
  - `301 assertions`
  - `PASS`
- `cd apps/api && php artisan test tests/Feature/Wall/PublicWallBootTest.php tests/Feature/Wall/WallDiagnosticsTest.php tests/Feature/MediaProcessing/MediaPipelineEventsTest.php tests/Unit/Modules/Wall/WallEligibilityServiceTest.php tests/Unit/Modules/MediaProcessing/MediaAssetUrlServiceTest.php tests/Unit/Modules/MediaProcessing/MediaVariantGeneratorServiceTest.php`
  - `26 testes`
  - `150 assertions`
  - `PASS`
- `cd apps/api && php artisan test tests/Feature/Wall/PublicWallBootTest.php`
  - `2 testes`
  - `38 assertions`
  - `PASS`
- `cd apps/api && php artisan test tests/Unit/Modules/Wall/WallEligibilityServiceTest.php`
  - `7 testes`
  - `11 assertions`
  - `PASS`
- `cd apps/api && php artisan test tests/Unit/Modules/MediaProcessing/MediaAssetUrlServiceTest.php tests/Unit/Modules/MediaProcessing/MediaVariantGeneratorServiceTest.php`
  - `3 testes`
  - `3 assertions`
  - `PASS`
- `cd apps/api && php artisan test --filter=PublicUploadTest`
  - `8 testes`
  - `71 assertions`
  - `PASS`
- `cd apps/api && php artisan test --filter=InboundMediaPipelineTest`
  - `4 testes`
  - `51 assertions`
  - `PASS`
- `cd apps/api && php artisan test --filter=EventMediaListTest`
  - `7 testes`
  - `104 assertions`
  - `PASS`

Leituras confirmadas pelos testes e codigo atual:

- `MediaSurface` ainda renderiza video comum com `autoPlay + muted + playsInline + loop`, sem `controls`, sem `poster`;
- `useWallEngine` continua avancando a fila principal por `setTimeout(interval_ms)` para o slideshow;
- `AdOverlay` ja trata video de anuncio de forma diferente, com `loop = false` e saida por `onEnded`;
- `cache.ts` faz probe de metadata com `<video preload="metadata">`, mas nao e um player cacheado de verdade;
- `preload.ts` faz aquecimento oportunistico com `<video preload="auto" muted>`;
- `selectors.test.ts` confirma que o engine atual prefere itens `ready`, mas ainda aceita itens `idle` quando nao existe nenhum `ready`;
- upload publico aceita video unitario no backend;
- intake privado de video nao passa por estagios image-only;
- `MediaAssetUrlService::wall()` e `::preview()` ainda acabam caindo no original para video, porque as variantes atuais sao essencialmente de imagem.
- `MediaAssetUrlServiceTest` confirma que, sem variante de video, o wall hoje cai no original; e, se a variante existir, a URL de wall e preferida.
- `MediaVariantGeneratorServiceTest` confirma que o gerador atual ainda retorna cedo para video e nao produz `wall_video` nem `poster`.
- `WallRuntimeMediaService` agora filtra boot e simulacao com o mesmo `WallEligibilityService` usado pelo realtime;
- `PublicWallBootTest`, `MediaPipelineEventsTest` e `WallDiagnosticsTest` agora validam a mesma regra de orientacao entre boot, broadcast e simulacao.

## Restricoes oficiais do navegador validadas

Conclusoes que devem orientar a implementacao:

- `preload` e apenas hint do autor, nao garantia de buffer completo;
- `ended` nao dispara quando `loop = true` e a taxa de playback e nao-negativa;
- `play()` retorna `Promise` e pode rejeitar com `NotAllowedError` ou `NotSupportedError`;
- `Chrome` permite autoplay mudo, mas autoplay com som depende de interacao do usuario ou de condicoes adicionais;
- `readyState`, `loadeddata`, `canplay`, `waiting` e `stalled` sao sinais mais fortes de readiness de playback do que apenas `loadedmetadata`;
- `loadeddata` e util para primeira frame, mas nao deve ser gate unico porque o proprio MDN documenta que esse evento pode nao disparar em mobile/tablet com `data-saver` ativo;
- `canplaythrough` deve ser tratado como sinal de confianca alta, nao como gate duro de entrada;
- `poster` e nativo para o estado em que ainda nao ha video suficiente para exibir;
- `Service Worker fetch` e o caminho oficial para interceptar e servir respostas de cache de forma controlada;
- `MediaCapabilities.decodingInfo()` faz sentido como guard rail futuro para decidir se uma variante tende a tocar com suavidade e eficiencia;
- `requestVideoFrameCallback()` faz sentido para observabilidade premium, nao para a primeira entrega;
- `MP4 + AVC (H.264)` e a combinacao de video mais compativel no navegador, idealmente com `AAC` para audio;
- `Range requests` e resposta `206 Partial Content` ajudam playback e seek de midia grande.
- quando houver `Service Worker`/Workbox para midia, `Range requests` precisam ser tratados explicitamente e o cache precisa ser populado antecipadamente; confiar em cache runtime do streaming parcial nao resolve playback de video grande.

Leitura de produto derivada dessas limitacoes:

- o wall deve continuar `sempre mudo` no primeiro rollout serio de video;
- o problema principal nao e "autoplay", e sim a falta de uma maquina de estados propria para video;
- `Service Worker` nao e `P0`; primeiro vem variante certa + reducer playback-aware + readiness real.

## Principios de execucao

- nao tentar "melhorar um pouco o slideshow"; criar um subsistema de playback de video dentro do wall;
- manter `image`, `event video` e `ad video` como trilhas distintas;
- deixar o `reducer` como fonte unica de verdade do runtime;
- tratar `playback exit reason` como dado de dominio;
- preferir variante otimizada antes de apostar em cache agressivo;
- bloquear video em layout multi-slot por padrao na primeira entrega;
- manter rollout incremental, com guardrails e feature flags por evento/wall;
- nao ligar oficialmente upload publico de video enquanto a politica de duracao e UX nao estiver fechada.

## Escopo da primeira entrega

O que entra em `P0`:

- politica de produto para video comum;
- politica unica de elegibilidade entre boot, realtime, resync e itens consumidos pelo player;
- settings do wall para video;
- metadata confiavel + admissao explicita de `wall-eligible video`;
- variante de video especifica para wall + poster;
- primeira execucao controlada de video grande com `poster-first`, `startup deadline` e comportamento `never-blocking`;
- reducer playback-aware para video comum;
- taxonomia de falhas de playback e motivos de saida;
- pausa e retomada reais do video;
- cap de duracao e regras de saida;
- bloqueio de video em `carousel`, `mosaic` e `grid`;
- cobertura automatizada do runtime e dos fluxos de intake;
- homologacao real por classe de device e rede em pelo menos um wall controlado.

O que entra em `P1`:

- analytics e diagnosticos especificos de video;
- manager com simulador, avisos e decisao por item;
- warming mais forte do proximo video;
- filtros operacionais por video longo/sem variante;
- suporte opcional a `wall_video_1080p`.

O que fica para `P2`:

- `Service Worker` para cache controlado de playback;
- `MediaCapabilities.decodingInfo()` para escolha adaptativa de variante;
- `requestVideoFrameCallback()` para metricas frame-level;
- politicas mais ricas por fase do evento e por layout;
- qualquer trilha tipo `HLS`, `DASH` ou `MSE`.

## Fase 0 - Politica de produto e contrato

Objetivo:

- fechar as regras antes de espalhar campos e estados pelo backend e pelo player.

### 0.1 Fechar a politica de video do wall

Subtarefas:

- [ ] decidir oficialmente se upload publico unitario de video sera:
  - suportado;
  - suportado com flag;
  - bloqueado ate a trilha premium ficar pronta.
- [ ] fechar duracao padrao por classe:
  - `<= 15s`: tocar ate o fim;
  - `15s a 30s`: tocar ate o fim somente se a policy permitir, senao aplicar cap;
  - `> 30s`: bloquear, mandar para revisao ou exigir destaque editorial.
- [ ] fechar politica de pausa:
  - `resume_if_same_item`;
  - `restart_from_zero`;
  - ou `resume_if_same_item_else_restart`.
- [ ] fechar politica de audio como `sempre mudo`;
- [ ] fechar politica multi-slot inicial como `disallow`;
- [ ] fechar politica de fallback quando o playback falhar:
  - `retry_once`;
  - `poster_then_skip`;
  - `skip_and_penalize`.

Criterio de aceite:

- existe uma tabela de regras unica para `image`, `event video` e `ad video`.

### 0.2 Fechar o contrato de settings

Arquivos centrais:

- `packages/shared-types/src/wall.ts`
- `apps/api/app/Modules/Wall/Models/EventWallSetting.php`
- `apps/api/app/Modules/Wall/Http/Requests/UpdateWallSettingsRequest.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`
- `apps/web/src/modules/wall/wall-settings.ts`
- `apps/web/src/modules/wall/manager-config.ts`

Subtarefas:

- [ ] adicionar `video_enabled`;
- [ ] adicionar `video_playback_mode`:
  - `fixed_interval`
  - `play_to_end`
  - `play_to_end_if_short_else_cap`
- [ ] adicionar `video_max_seconds`;
- [ ] adicionar `video_resume_mode`;
- [ ] adicionar `video_audio_policy` com default `muted`;
- [ ] adicionar `video_multi_layout_policy`:
  - `disallow`
  - `one`
  - `all`
- [ ] adicionar `video_preferred_variant`:
  - `wall_video_720p`
  - `wall_video_1080p`
  - `original`

Criterio de aceite:

- boot, settings update, manager e shared-types conhecem as mesmas chaves.

## Fase 1 - Backend de ingestao, metadata e variantes

Objetivo:

- impedir que o player continue refem do arquivo original.

### 1.1 Unificar elegibilidade entre boot, realtime e resync

Arquivos centrais:

- `apps/api/app/Modules/Wall/Http/Controllers/PublicWallController.php`
- `apps/api/app/Modules/Wall/Services/WallRuntimeMediaService.php`
- `apps/api/app/Modules/Wall/Services/WallEligibilityService.php`
- `apps/api/app/Modules/Wall/Services/WallBroadcasterService.php`
- `apps/api/app/Modules/Wall/Services/WallSimulationService.php`
- `apps/web/src/modules/wall/player/hooks/useWallPlayer.ts`

Subtarefas:

- [x] criar uma unica politica de elegibilidade usada por:
  - boot inicial;
  - broadcasts realtime;
  - resync periodico;
  - simulacao e diagnostico do manager.
- [x] consolidar no mesmo gate atual:
  - `settings->isPlayable()`;
  - `approved + published`;
  - `media_type` permitido;
  - orientacao aceita.
- [x] impedir que o player receba no boot um item que o realtime nao pode republicar, ou o inverso, para as regras atuais do wall;
- [ ] estender o mesmo gate para criterios especificos de video:
  - `video_enabled`;
  - politica de duracao/admissao de video;
  - existencia da variante/poster exigidos pelo rollout.
- [ ] expor razoes de inelegibilidade que possam alimentar diagnostico e manager:
  - `orientation_blocked`
  - `video_disabled`
  - `duration_blocked`
  - `variant_missing`
  - `poster_missing`
  - `unsupported_format`

Criterio de aceite:

- o mesmo item e aceito ou rejeitado da mesma forma em boot, realtime, resync e simulacao.

### 1.2 Persistir metadata real de video

Arquivos centrais:

- `apps/api/app/Modules/MediaProcessing/Models/EventMedia.php`
- `apps/api/app/Modules/InboundMedia/Http/Controllers/PublicUploadController.php`
- `apps/api/app/Modules/MediaProcessing/Jobs/DownloadInboundMediaJob.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaVariantGeneratorService.php`

Subtarefas:

- [ ] garantir preenchimento consistente de `duration_seconds`, `width` e `height` para video;
- [ ] adicionar metadata complementar para operacao:
  - `has_audio`
  - `video_codec`
  - `audio_codec`
  - `bitrate`
  - `container`
- [ ] definir um servico unico para extrair metadata de video;
- [ ] tornar a extracao idempotente para upload publico e intake privado.

Criterio de aceite:

- todo `EventMedia` de video candidato ao wall tem metadata minima confiavel.

### 1.3 Criar admissao explicita de `wall-eligible video`

Arquivos centrais:

- `apps/api/app/Modules/MediaProcessing/Models/EventMedia.php`
- `apps/api/app/Modules/Wall/Services/WallEligibilityService.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaAssetUrlService.php`

Subtarefas:

- [ ] introduzir uma etapa explicita de admissao antes de um video entrar no wall;
- [ ] decidir a elegibilidade com base em:
  - duracao;
  - metadata minima;
  - container/codec baseline;
  - poster disponivel;
  - variante preferida disponivel, quando obrigatoria;
  - politica de rollout ativa naquele evento/wall.
- [ ] persistir ou calcular em tempo de consulta um resultado legivel:
  - `eligible`
  - `eligible_with_fallback`
  - `blocked`
- [ ] registrar motivo de bloqueio/fallback para operacao:
  - `missing_metadata`
  - `duration_over_limit`
  - `unsupported_format`
  - `variant_missing`
  - `poster_missing`

Criterio de aceite:

- o backend sabe explicar porque um video entra, entra com fallback ou nao entra no wall.

### 1.4 Gerar variantes de video para wall

Arquivos centrais:

- `apps/api/app/Modules/MediaProcessing/Services/MediaVariantGeneratorService.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaAssetUrlService.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`

Subtarefas:

- [ ] criar `wall_video_720p`;
- [ ] criar `wall_video_1080p` opcional;
- [ ] criar `wall_video_poster`;
- [ ] padronizar baseline para web:
  - `MP4`
  - `AVC (H.264)`
  - `AAC`
  - `+faststart`
- [ ] atualizar `MediaAssetUrlService::wall()` para preferir variante de wall;
- [ ] atualizar `MediaAssetUrlService::preview()` e `::thumbnail()` para preferirem poster/preview de video;
- [ ] manter fallback para original apenas quando nao houver variante;
- [ ] registrar no payload do wall qual variante esta sendo servida.

Criterio de aceite:

- o player recebe por padrao uma URL otimizada para wall e um poster para video.

### 1.5 Limpar o intake publico de video

Arquivos centrais:

- `apps/api/app/Modules/InboundMedia/Http/Controllers/PublicUploadController.php`
- `apps/web/src/modules/upload/PublicEventUploadPage.tsx`

Subtarefas:

- [ ] alinhar `accept_hint` do backend com a politica final;
- [ ] alinhar a UI publica para comunicar corretamente imagem x video;
- [ ] parar de despachar `GenerateMediaVariantsJob` como no-op para video, ou adaptar o job para a nova trilha de variantes de video;
- [ ] manter upload multiplo como imagens apenas na primeira entrega.

Criterio de aceite:

- o produto nao comunica uma coisa e executa outra.

## Fase 2 - Reducer e subsistema de playback de video

Objetivo:

- tirar video comum da lane de slideshow puro por tempo fixo.

### 2.1 Modelar a maquina de estados de video

Arquivos centrais:

- `apps/web/src/modules/wall/player/types.ts`
- `apps/web/src/modules/wall/player/engine/reducer.ts`
- `apps/web/src/modules/wall/player/hooks/useWallEngine.ts`

Estados alvo:

- `idle`
- `probing`
- `primed`
- `starting`
- `playing`
- `waiting`
- `stalled`
- `paused_by_wall`
- `completed`
- `capped`
- `interrupted`
- `failed_to_start`

Falhas classificadas alvo:

- `network_error`
- `unsupported_format`
- `autoplay_blocked`
- `decode_degraded`
- `src_missing`
- `variant_missing`

Saidas alvo:

- `ended`
- `cap_reached`
- `paused_by_operator`
- `play_rejected`
- `stalled_timeout`
- `replaced_by_command`
- `media_deleted`
- `visibility_degraded`

Subtarefas:

- [ ] adicionar bloco `videoPlayback` no estado do player;
- [ ] adicionar actions do reducer para eventos de video;
- [ ] persistir no runtime pelo menos:
  - `currentTime`
  - `durationSeconds`
  - `readyState`
  - `exitReason`
  - `failureReason`
  - `stallCount`
- [ ] fazer o reducer decidir quando um item de video entra, pausa, retoma e sai.

Criterio de aceite:

- o estado do player sabe por que o video entrou, por que saiu e qual foi a classe da falha quando existir.

### 2.2 Trocar o scheduler por causa de saida

Arquivos centrais:

- `apps/web/src/modules/wall/player/hooks/useWallEngine.ts`
- `apps/web/src/modules/wall/player/engine/reducer.ts`

Subtarefas:

- [ ] manter `interval_ms` apenas para imagem;
- [ ] parar de usar `loop` em video comum;
- [ ] quando `currentItem.type === video`, trocar a saida de `setTimeout(interval_ms)` por:
  - `ended`
  - `cap_reached`
  - `play_rejected`
  - `stalled_timeout`
- [ ] manter ad video separado, mas alinhado com a mesma taxonomia de eventos;
- [ ] mapear `play_rejected` e `error` do elemento para falhas classificadas:
  - `autoplay_blocked`
  - `unsupported_format`
  - `src_missing`
  - `network_error`
- [ ] evitar corrida entre scheduler de ads e scheduler de video comum.

Criterio de aceite:

- video comum deixa de ser interrompido apenas porque o timeout fixo venceu.

### 2.3 Controlar `play()` e `pause()` de forma imperativa

Arquivos centrais:

- `apps/web/src/modules/wall/player/components/MediaSurface.tsx`
- `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`
- `apps/web/src/modules/wall/player/engine/autoplay.ts`

Subtarefas:

- [ ] separar `MediaSurface` de imagem e `WallVideoSurface` controlado;
- [ ] aplicar `muted + playsInline + autoPlay`, mas sem `loop` para video comum;
- [ ] chamar `play()` explicitamente e tratar `Promise`;
- [ ] chamar `pause()` quando o wall entra em `paused`;
- [ ] implementar a semantica de `resume` definida na fase 0;
- [ ] aplicar `poster` quando disponivel.

Criterio de aceite:

- `wall paused` pausa de verdade o elemento de video atual.

### 2.4 Diferenciar `metadata_ready` de `playback_ready`

Arquivos centrais:

- `apps/web/src/modules/wall/player/engine/cache.ts`
- `apps/web/src/modules/wall/player/engine/preload.ts`
- `apps/web/src/modules/wall/player/hooks/useWallEngine.ts`

Subtarefas:

- [ ] manter probe de metadata para dimensao/orientacao;
- [ ] adicionar camada de readiness operacional baseada em:
  - `loadeddata`
  - `canplay`
  - `readyState`
  - `waiting`
  - `stalled`
- [ ] usar `HAVE_FUTURE_DATA` como threshold minimo razoavel de entrada;
- [ ] usar `loadeddata` como sinal util para primeira frame visivel;
- [ ] usar `HAVE_ENOUGH_DATA` como sinal excelente, nao obrigatorio;
- [ ] tratar `canplaythrough` apenas como sinal auxiliar de confianca alta;
- [ ] registrar a degradacao quando o item entra em `waiting` ou `stalled`.

Criterio de aceite:

- `asset ready` nao significa apenas "li os metadados".

### 2.5 Controlar a primeira execucao de video grande

Objetivo:

- impedir que um unico video frio, pesado ou longo comprometa a superficie principal do wall.

Arquivos centrais:

- `apps/web/src/modules/wall/player/components/MediaSurface.tsx`
- `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`
- `apps/web/src/modules/wall/player/hooks/useWallEngine.ts`
- `apps/web/src/modules/wall/player/engine/reducer.ts`
- `apps/web/src/modules/wall/player/engine/cache.ts`
- `apps/web/src/modules/wall/player/engine/preload.ts`

Diretriz de runtime:

- a primeira execucao de video grande deve ser `poster-first`, `playback-gated`, `timeout-bounded` e `never-blocking`.

Estados operacionais adicionais recomendados:

- `poster_visible`
- `first_frame_ready`
- `playback_ready`
- `playing_confirmed`
- `startup_degraded`

Saidas operacionais adicionais recomendadas:

- `startup_timeout`
- `poster_then_skip`
- `startup_waiting_timeout`
- `startup_play_rejected`

Subtarefas:

- [ ] introduzir uma trilha `poster-first` para video grande, frio ou sem warming confiavel;
- [ ] renderizar `poster` imediatamente na superficie principal enquanto o video tenta subir em paralelo;
- [ ] promover o video para `video-live` apenas quando houver sinais minimos de readiness:
  - `loadeddata` como sinal util de primeira frame;
  - `readyState >= HAVE_FUTURE_DATA` como gate minimo recomendado;
  - `playing` como confirmacao operacional de playback real.
- [ ] manter fallback quando `loadeddata` nao vier, usando `readyState` e `playing` como criterios principais de promocao;
- [ ] nao depender de `metadata_ready` para promover o video a tela principal;
- [ ] definir `startup deadline` curto e calibravel:
  - janela alvo inicial de `800ms a 1500ms` para primeira frame;
  - tolerancia maior apenas por perfil de device/wall, nao como regra universal.
- [ ] se a janela curta expirar sem readiness suficiente:
  - manter `poster` por pouco tempo;
  - registrar `startup_degraded`;
  - sair por `poster_then_skip` ou `startup_timeout`, conforme policy.
- [ ] garantir que o scheduler principal nao fique refem do sucesso do startup do video;
- [ ] separar claramente:
  - falha antes da primeira frame;
  - falha depois do playback ja confirmado.

Criterio de aceite:

- um video grande em primeira execucao nunca toma a tela principal sem readiness minima e nunca congela a fila do wall.

### 2.6 Fechar a spec de `WallVideoSurface`

Objetivo:

- transformar a proposta de playback em contrato concreto de componente/runtime.

Arquivos centrais:

- `apps/web/src/modules/wall/player/components/MediaSurface.tsx`
- `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`
- `apps/web/src/modules/wall/player/engine/autoplay.ts`
- `apps/web/src/modules/wall/player/hooks/useWallEngine.ts`

Contrato recomendado:

- `WallVideoSurface` recebe:
  - `src`
  - `poster`
  - `startupDeadlineMs`
  - `stallBudgetMs`
  - `resumeMode`
  - `onFirstFrame`
  - `onPlaybackReady`
  - `onPlaying`
  - `onWaiting`
  - `onStalled`
  - `onEnded`
  - `onFailure`

Subtarefas:

- [ ] separar a renderizacao de imagem da renderizacao de video controlado;
- [ ] encapsular `play()`, `pause()`, listeners e cleanup dentro do `WallVideoSurface`;
- [ ] classificar falhas antes de propagar para o reducer:
  - `autoplay_blocked`
  - `unsupported_format`
  - `network_error`
  - `src_missing`
  - `decode_degraded`
  - `variant_missing`
- [ ] expor callbacks distintos para:
  - primeira frame;
  - playback pronto;
  - playback confirmado;
  - degradacao;
  - fim natural;
  - falha terminal.

Criterio de aceite:

- a logica de video deixa de ficar espalhada entre DOM, hook e reducer sem contrato explicito.

## Fase 3 - Layouts, UX e politica visual

Objetivo:

- evitar que video degrade a experiencia visual ou a maquina.

### 3.1 Restringir video nos layouts multi-slot

Arquivos centrais:

- `apps/web/src/modules/wall/player/engine/layoutStrategy.ts`
- `apps/web/src/modules/wall/player/layouts/CarouselLayout.tsx`
- `apps/web/src/modules/wall/player/layouts/MosaicLayout.tsx`
- `apps/web/src/modules/wall/player/layouts/GridLayout.tsx`

Subtarefas:

- [ ] bloquear video em `carousel`, `mosaic` e `grid` no primeiro rollout;
- [ ] se o produto quiser excecao futura, limitar a `1` video simultaneo no maximo;
- [ ] fazer `auto` preferir layouts single-item para video;
- [ ] esconder ou reduzir side thumbnails enquanto um video estiver em playback;
- [ ] reduzir toast de nova midia e overlays decorativos enquanto houver video ativo.

Criterio de aceite:

- o primeiro rollout nao dispara decode paralelo de varios videos.

### 3.2 Tratar layouts single-item de forma adequada

Arquivos centrais:

- `apps/web/src/modules/wall/player/layouts/SplitLayout.tsx`
- `apps/web/src/modules/wall/player/layouts/CinematicLayout.tsx`
- `apps/web/src/modules/wall/player/layouts/FullscreenLayout.tsx`
- `apps/web/src/modules/wall/player/layouts/KenBurnsLayout.tsx`

Subtarefas:

- [ ] validar `contain` x `cover` para video em cada layout;
- [ ] remover a animacao fixa de `20s` para video em `KenBurnsLayout`;
- [ ] impedir que `KenBurnsLayout` herde logica temporal de imagem para video;
- [ ] garantir transicao de saida curta e previsivel, sem mascarar corte logico com animacao longa;
- [ ] usar poster para reduzir tela preta na troca.

Criterio de aceite:

- video nao fica preso a uma animacao desenhada para imagem.

### 3.3 Tratar waiting/stalled como evento operacional de UX

Arquivos centrais:

- `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`
- `apps/web/src/modules/wall/player/hooks/useWallEngine.ts`
- `apps/web/src/modules/wall/player/components/MediaSurface.tsx`

Subtarefas:

- [ ] definir janela curta de tolerancia para `waiting`;
- [ ] diferenciar:
  - `waiting` antes da primeira frame;
  - `waiting` depois do playback confirmado.
- [ ] quando evoluir para `stalled`, cair para `poster` ou fallback definido;
- [ ] tolerar no maximo uma degradacao curta antes de penalizar o item em rollout inicial;
- [ ] seguir a fila sem travar o wall quando o timeout operacional estourar;
- [ ] registrar claramente se a saida foi `stalled_timeout` ou `poster_then_skip`.

Criterio de aceite:

- `waiting` e `stalled` deixam de ser apenas telemetria e passam a influenciar a operacao do wall.

## Fase 4 - Cache, preload e rede

Objetivo:

- melhorar fluidez sem superestimar o que o browser garante sozinho.

### 4.1 Melhorar warming e cache local

Arquivos centrais:

- `apps/web/src/modules/wall/player/engine/cache.ts`
- `apps/web/src/modules/wall/player/engine/preload.ts`
- `apps/web/src/modules/wall/player/runtime-capabilities.ts`

Subtarefas:

- [ ] pre-aquecer poster e metadata do proximo video;
- [ ] elevar para `preload="auto"` apenas quando o item for forte candidato a ser o proximo;
- [ ] deixar explicito que `preload="auto"` continua sendo hint e nao substitui a politica de `poster-first`;
- [ ] deixar explicito que, mesmo no futuro com `Service Worker`, midia grande exigira tratamento de `Range` e pre-cache dedicado para ser servida do cache de forma confiavel;
- [ ] registrar budget e limpeza do cache local;
- [ ] separar estado:
  - `metadata_ready`
  - `first_frame_ready`
  - `playback_ready`
  - `buffering`
  - `stalled`
  - `error`
- [ ] manter `Service Worker` explicitamente fora de `P0`.

Criterio de aceite:

- warming existe, mas sem prometer cache de playback que nao existe ainda.

### 4.2 Garantir delivery de rede favoravel a video

Arquivos centrais:

- `apps/api/app/Modules/MediaProcessing/Services/MediaAssetUrlService.php`
- infra/CDN/origin do storage publico

Subtarefas:

- [ ] validar `Accept-Ranges` e `206 Partial Content` no storage/CDN;
- [ ] evitar compressao HTTP desnecessaria em MP4;
- [ ] validar tempo de primeiro byte e seek em um sample real de wall video;
- [ ] definir naming e cache headers das variantes de video e poster.
- [ ] assumir explicitamente no rollout inicial:
  - sem `wall_video` e `poster`, video grande nao entra como playback live padrao;
  - sem delivery favoravel, o fallback deve privilegiar poster + skip e nao insistir no original pesado.

Criterio de aceite:

- o delivery nao sabota seek, buffer e first frame.

## Fase 5 - Manager, simulacao e operacao

Objetivo:

- deixar a politica de video operavel e compreensivel para operador leigo.

Arquivos centrais:

- `apps/web/src/modules/wall/pages/EventWallManagerPage.tsx`
- `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.tsx`
- `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.tsx`
- `apps/web/src/modules/wall/components/manager/stage/WallUpcomingTimeline.tsx`

Subtarefas:

- [ ] organizar a experiencia em 5 blocos visuais:
  - `Politica de video`
  - `Compatibilidade do telao`
  - `Fila de videos`
  - `Comportamento efetivo`
  - `Alertas`
- [ ] adicionar `Policy Summary` fixo no topo do inspector, traduzindo a policy em linguagem humana;
- [ ] adicionar `Video Decision Inspector` por item, explicando:
  - duracao;
  - variante escolhida;
  - poster disponivel;
  - layout efetivo;
  - motivo de bloqueio, fallback ou cap.
- [ ] expor settings de video no bloco `Politica de video`;
- [ ] mostrar no bloco `Compatibilidade do telao` se o wall atual esta apto:
  - variante disponivel;
  - poster disponivel;
  - layout atual compativel;
  - wall degradado ou nao.
- [ ] mostrar no bloco `Fila de videos` os videos com duracao, status, variante, poster e motivo de bloqueio;
- [ ] permitir no bloco `Comportamento efetivo` uma simulacao do tipo "o que acontecera com este video?";
- [ ] traduzir no `Comportamento efetivo` a regra de primeira execucao:
  - entra com poster;
  - sobe para video live se houver readiness;
  - cai para timeout/fallback se nao estabilizar.
- [ ] concentrar em `Alertas` casos como:
  - video longo sera limitado por cap;
  - sem variante otimizada;
  - layout atual bloqueia video;
  - upload publico nao aceita video naquele evento.

Criterio de aceite:

- o operador entende antes de ligar se o wall vai tocar, cortar ou bloquear video.

## Fase 6 - Analytics e diagnostico

Objetivo:

- tornar playback de video auditavel.

Arquivos centrais:

- `apps/web/src/modules/wall/player/hooks/useWallPlayer.ts`
- `packages/shared-types/src/wall.ts`
- backend de heartbeat/diagnostics do modulo `Wall`

Subtarefas:

- [ ] adicionar eventos:
  - `video_start`
  - `video_first_frame`
  - `video_complete`
  - `video_interrupted_by_cap`
  - `video_interrupted_by_pause`
  - `video_waiting`
  - `video_stalled`
  - `video_play_rejected`
- [ ] classificar falhas no heartbeat e nos eventos com taxonomia legivel:
  - `network_error`
  - `unsupported_format`
  - `autoplay_blocked`
  - `decode_degraded`
  - `src_missing`
  - `variant_missing`
- [ ] enriquecer heartbeat com:
  - `video_state`
  - `video_current_time`
  - `video_duration_seconds`
  - `video_ready_state`
  - `video_exit_reason`
  - `video_failure_reason`
  - `video_variant_key`
  - `video_stall_count`
- [ ] mostrar alertas de wall degradado por video no manager.

Criterio de aceite:

- o time sabe se o problema foi arquivo ruim, rede, autoplay bloqueado, cap ou stall.

## Fase 7 - Rollout e homologacao real

Objetivo:

- evitar ligar video premium em producao sem amostragem real.

Subtarefas:

- [ ] liberar por evento/wall com flag;
- [ ] homologar primeiro apenas layouts single-item;
- [ ] rodar matriz real por classe de device:
  - desktop forte
  - notebook fraco
  - mini-PC ou TV box
  - Android usado como wall controller, quando aplicavel
- [ ] rodar matriz real por classe de rede:
  - saudavel
  - moderada
  - degradada
- [ ] usar Chrome desktop como baseline e Edge/Chrome Android como checks complementares;
- [ ] testar classes de duracao:
  - `5s`
  - `12s`
  - `20s`
  - `60s`
- [ ] testar:
  - pausa e resume
  - reconnect do wall
  - troca de settings em tempo real
  - item deletado durante playback
  - fila com imagem + video + anuncio

Criterio de aceite:

- video premium so sobe quando o comportamento esta previsivel em runtime, nao apenas bonito em laboratorio.

## Mapa de arquivos a tocar

Backend:

- `apps/api/app/Modules/InboundMedia/Http/Controllers/PublicUploadController.php`
- `apps/api/app/Modules/MediaProcessing/Models/EventMedia.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaVariantGeneratorService.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaAssetUrlService.php`
- `apps/api/app/Modules/Wall/Models/EventWallSetting.php`
- `apps/api/app/Modules/Wall/Http/Requests/UpdateWallSettingsRequest.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`

Frontend:

- `packages/shared-types/src/wall.ts`
- `apps/web/src/modules/wall/player/types.ts`
- `apps/web/src/modules/wall/player/engine/reducer.ts`
- `apps/web/src/modules/wall/player/hooks/useWallEngine.ts`
- `apps/web/src/modules/wall/player/hooks/useWallPlayer.ts`
- `apps/web/src/modules/wall/player/components/MediaSurface.tsx`
- `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`
- `apps/web/src/modules/wall/player/engine/autoplay.ts`
- `apps/web/src/modules/wall/player/engine/cache.ts`
- `apps/web/src/modules/wall/player/engine/preload.ts`
- `apps/web/src/modules/wall/player/engine/layoutStrategy.ts`
- `apps/web/src/modules/wall/pages/EventWallManagerPage.tsx`
- `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.tsx`
- `apps/web/src/modules/upload/PublicEventUploadPage.tsx`

## Bateria automatizada alvo

Backend:

- [ ] `PublicUploadTest` com policy final de video publico
- [ ] `InboundMediaPipelineTest` cobrindo metadata de video e skip de estagios image-only
- [ ] `PublicWallBootTest` e suite de broadcast/resync provando simetria de elegibilidade
- [ ] `MediaAssetUrlServiceTest` cobrindo fallback para original e preferencia por variante quando existir
- [ ] `MediaVariantGeneratorServiceTest` cobrindo no-op atual e futura geracao de variantes reais para video
- [ ] novo teste para geracao de `wall_video_*` e `poster`
- [ ] novo teste para `MediaAssetUrlService` preferindo variantes de video
- [ ] novo teste de admissao de `wall-eligible video` com motivos de bloqueio/fallback
- [ ] novo teste para payload do wall com settings de video

Frontend:

- [ ] `MediaSurface.test.tsx` evoluido para video comum sem `loop` e com `poster`
- [ ] novo teste de `WallVideoSurface` cobrindo `play()`, `pause()`, `resume()` e `ended`
- [ ] novo teste de `WallVideoSurface` cobrindo `poster-first` antes da primeira frame
- [ ] novo teste de `WallVideoSurface` cobrindo promocao de `poster` para `video-live` apos `loadeddata` + readiness minima
- [ ] novo teste de reducer para estados e motivos de saida
- [ ] novo teste de reducer para falhas classificadas de playback
- [ ] `useWallEngine.test.tsx` cobrindo:
  - imagem por timer
  - video por `ended`
  - video por cap
  - video por stall timeout
- [ ] teste de readiness usando `readyState >= HAVE_FUTURE_DATA` como gate minimo
- [ ] teste de startup com `startup_timeout` sem bloquear a fila
- [ ] teste de `waiting/stalled` com `poster_then_skip`
- [ ] teste para bloqueio de video em multi-slot
- [ ] testes do manager para `Policy Summary` e `Video Decision Inspector`
- [ ] testes do manager para settings de video e avisos operacionais

## Smokes reais obrigatorios

- [ ] video curto com variante `wall_video_720p` toca ate o fim e avanca naturalmente;
- [ ] video grande na primeira execucao entra com poster imediato e so promove para live quando houver readiness minima;
- [ ] video grande sem variante de wall nao compromete a superficie principal e sai por fallback previsivel;
- [ ] video longo respeita cap configurado e registra `cap_reached`;
- [ ] `wall paused` congela video e `resume` respeita a politica definida;
- [ ] sem variante de video, o player cai para poster + skip ou fallback definido, sem travar a fila;
- [ ] fila com imagem, video comum e anuncio em video nao entra em corrida de scheduler;
- [ ] o mesmo video e aceito ou rejeitado da mesma forma em boot, realtime e resync;
- [ ] `waiting` evolui para fallback operacional sem tela preta prolongada;
- [ ] upload publico e intake privado seguem a politica oficial sem divergencia de UX.

## Definicao de pronto da primeira entrega

- existe politica fechada para duracao, pausa, multi-slot e upload publico;
- existe politica unica de elegibilidade entre boot, realtime, resync e simulacao;
- o backend sabe classificar `wall-eligible video` e explicar bloqueios/fallbacks;
- o player usa uma maquina de estados propria para video comum;
- a primeira execucao de video grande segue a regra `poster-first`, `timeout-bounded` e `never-blocking`;
- video comum nao usa `loop`;
- video comum nao depende de `interval_ms` para encerrar;
- o wall pausa de verdade o elemento de video quando entra em `paused`;
- o backend entrega variante de wall e poster para video;
- a classificacao de falhas diferencia pelo menos rede, formato, autoplay e variante ausente;
- layouts multi-slot estao bloqueados por padrao para video;
- suite backend e frontend especifica da trilha de video esta verde;
- a matriz real de homologacao passou em pelo menos um ambiente de wall controlado;
- o manager deixa claro quando a configuracao atual corta, bloqueia ou toca ate o fim.

## Referencias oficiais validadas

- Chrome autoplay policy:
  - `https://developer.chrome.com/blog/autoplay/`
- MDN `play()`:
  - `https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/play`
- MDN `ended`:
  - `https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/ended_event`
- MDN `preload`:
  - `https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/preload`
- MDN `canplay`:
  - `https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/canplay_event`
- MDN `canplaythrough`:
  - `https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/canplaythrough_event`
- MDN `loadeddata`:
  - `https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/loadeddata_event`
- MDN `playing`:
  - `https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/playing_event`
- MDN `waiting`:
  - `https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/waiting_event`
- MDN `stalled`:
  - `https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/stalled_event`
- MDN `poster`:
  - `https://developer.mozilla.org/en-US/docs/Web/API/HTMLVideoElement/poster`
- MDN `MediaCapabilities.decodingInfo()`:
  - `https://developer.mozilla.org/en-US/docs/Web/API/MediaCapabilities/decodingInfo`
- MDN `requestVideoFrameCallback()`:
  - `https://developer.mozilla.org/en-US/docs/Web/API/HTMLVideoElement/requestVideoFrameCallback`
- MDN `ServiceWorkerGlobalScope.fetch`:
  - `https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerGlobalScope/fetch_event`
- MDN video codec guide:
  - `https://developer.mozilla.org/en-US/docs/Web/Media/Guides/Formats/Video_codecs`
- MDN audio codec guide:
  - `https://developer.mozilla.org/en-US/docs/Web/Media/Guides/Formats/Audio_codecs`
- MDN HTTP range requests:
  - `https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/Range_requests`
- Chrome Workbox cached audio/video:
  - `https://developer.chrome.com/docs/workbox/serving-cached-audio-and-video`
