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
  - `23 arquivos`
  - `170 testes`
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
- `cd apps/web && npx.cmd vitest run src/modules/wall/player/components/WallVideoSurface.test.tsx src/modules/wall/player/components/MediaSurface.test.tsx src/modules/wall/player/hooks/useWallEngine.test.tsx src/modules/wall/player/components/WallPlayerRoot.test.tsx src/modules/wall/player/engine/layoutStrategy.test.ts src/modules/wall/player/engine/autoplay.test.ts`
- resultado:
  - `6 arquivos`
  - `54 testes`
  - `PASS`

Backend:

- `cd apps/api && php artisan test tests/Unit/Modules/MediaProcessing/VideoMetadataExtractorServiceTest.php tests/Unit/Modules/MediaProcessing/MediaVariantGeneratorServiceTest.php tests/Unit/Modules/Wall/WallEligibilityServiceTest.php tests/Feature/Wall/PublicWallBootTest.php tests/Feature/Wall/WallDiagnosticsTest.php tests/Feature/MediaProcessing/MediaPipelineEventsTest.php tests/Feature/InboundMedia/PublicUploadTest.php`
  - `51 testes`
  - `361 assertions`
  - `PASS`
- `cd apps/api && php artisan test tests/Unit/Modules/MediaProcessing/MediaToolingStatusServiceTest.php tests/Feature/MediaProcessing/BackfillWallVideoVariantsCommandTest.php tests/Feature/InboundMedia/PublicUploadTest.php tests/Feature/InboundMedia/InboundMediaPipelineTest.php tests/Feature/Wall/WallInsightsTest.php tests/Feature/Wall/WallDiagnosticsTest.php tests/Feature/Wall/WallLiveSnapshotTest.php`
  - `42 testes`
  - `405 assertions`
  - `PASS`
- `cd apps/api && php artisan test tests/Unit/Modules/MediaProcessing/MediaVariantGeneratorServiceTest.php tests/Unit/Modules/MediaProcessing/MediaAssetUrlServiceTest.php tests/Feature/Wall/PublicWallBootTest.php tests/Feature/MediaProcessing/MediaPipelineJobsTest.php tests/Unit/MediaProcessing/HorizonConfigTest.php`
  - `21 testes`
  - `196 assertions`
  - `PASS`
- `cd apps/api && php artisan test --filter=MediaProcessing`
  - `88 testes`
  - `721 assertions`
  - `PASS`
- `cd apps/api && php artisan test --filter=Wall`
  - `92 testes`
  - `497 assertions`
  - `PASS`
- `cd apps/api && php artisan test --filter=InboundMedia`
  - `21 testes`
  - `196 assertions`
  - `PASS`
- `cd apps/api && php artisan test tests/Unit/Modules/MediaProcessing/VideoMetadataExtractorServiceTest.php tests/Unit/Modules/Wall/WallVideoAdmissionServiceTest.php`
  - `6 testes`
  - `77 assertions`
  - `PASS`
- `cd apps/api && php artisan test tests/Unit/Modules/Wall/WallEligibilityServiceTest.php tests/Feature/Wall/PublicWallBootTest.php tests/Unit/Modules/MediaProcessing/MediaVariantGeneratorServiceTest.php tests/Unit/Modules/MediaProcessing/MediaToolingStatusServiceTest.php tests/Feature/Wall/WallDiagnosticsTest.php tests/Feature/Wall/WallAuthorizationTest.php tests/Feature/InboundMedia/PublicUploadTest.php`
  - `47 testes`
  - `323 assertions`
  - `PASS`
- `cd apps/api && php artisan test --filter=Wall`
  - `94 testes`
  - `543 assertions`
  - `PASS`
- `cd apps/web && npx.cmd vitest run src/modules/wall/components/manager/diagnostics/WallPlayerDetailsSheet.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx src/modules/wall/player/hooks/useWallEngine.test.tsx src/modules/wall/player/hooks/useWallPlayer.test.tsx src/modules/wall/player/components/WallPlayerRoot.test.tsx src/modules/wall/player/engine/autoplay.test.ts src/modules/wall/player/engine/layoutStrategy.test.ts`
  - `7 arquivos`
  - `75 testes`
  - `PASS`
- `cd apps/web && npx.cmd vitest run src/modules/wall/components/manager/recent/WallRecentMediaDetailsSheet.test.tsx src/modules/wall/components/manager/stage/WallUpcomingTimeline.test.tsx src/modules/wall/components/manager/stage/WallHeroStage.test.tsx`
  - `3 arquivos`
  - `6 testes`
  - `PASS`
- `cd apps/web && npm.cmd run type-check`
  - `PASS`
- `winget install --id Gyan.FFmpeg --accept-source-agreements --accept-package-agreements`
  - `PASS`
- `cd apps/api && php artisan media:tooling-status`
  - `Status: ready`
- `php inline bootstrap -> MediaToolingStatusService::payload()`
  - `ready = true`
  - `ffmpeg_available = true`
  - `ffprobe_available = true`
- `C:\\Program Files\\Git\\bin\\bash.exe -n scripts/ops/bootstrap-host.sh scripts/ops/verify-host.sh scripts/ops/homologate-wall-video.sh`
  - `PASS`
- `C:\\Program Files\\Git\\bin\\bash.exe scripts/ops/homologate-wall-video.sh --help`
  - `PASS`
- `C:\\Program Files\\Git\\bin\\bash.exe -n scripts/ops/bootstrap-host.sh scripts/ops/verify-host.sh scripts/ops/homologate-wall-video.sh scripts/deploy/healthcheck.sh scripts/deploy/deploy.sh scripts/deploy/smoke-test.sh`
  - `PASS`
- `cd apps/api && php artisan test tests/Feature/MediaProcessing/MediaToolingStatusCommandTest.php tests/Feature/Wall/WallAuthorizationTest.php tests/Feature/InboundMedia/PublicUploadTest.php tests/Feature/InboundMedia/InboundMediaPipelineTest.php tests/Feature/Wall/WallDiagnosticsTest.php tests/Feature/Wall/WallVideoAnalyticsTrackingTest.php`
  - `37 testes`
  - `327 assertions`
  - `PASS`
- `cd apps/api && php artisan test tests/Feature/Wall/PublicWallBootTest.php tests/Feature/Wall/WallInsightsTest.php tests/Feature/Wall/WallLiveSnapshotTest.php tests/Unit/Modules/Wall/WallEligibilityServiceTest.php`
  - `31 testes`
  - `239 assertions`
  - `PASS`
- `cd apps/api && php artisan test tests/Unit/Shared/WallVideoOpsRunbookCharacterizationTest.php tests/Feature/MediaProcessing/MediaToolingStatusCommandTest.php tests/Feature/InboundMedia/PublicUploadTest.php tests/Feature/InboundMedia/InboundMediaPipelineTest.php tests/Feature/Wall/WallVideoAnalyticsTrackingTest.php`
  - `24 testes`
  - `221 assertions`
  - `PASS`
- `cd apps/api && php artisan test tests/Unit/Shared/WallVideoOpsRunbookCharacterizationTest.php tests/Feature/MediaProcessing/MediaToolingStatusCommandTest.php tests/Feature/InboundMedia/PublicUploadTest.php tests/Feature/InboundMedia/InboundMediaPipelineTest.php tests/Feature/Wall/WallVideoAnalyticsTrackingTest.php tests/Feature/Wall/WallDiagnosticsTest.php`
  - `32 testes`
  - `310 assertions`
  - `PASS`
- `cd apps/web && npx.cmd vitest run src/modules/wall/components/manager/inspector/WallAppearanceTab.test.tsx src/modules/wall/components/manager/diagnostics/WallPlayerDetailsSheet.test.tsx src/modules/wall/player/runtime-profile.test.ts src/modules/wall/player/hooks/useWallPlayer.test.tsx`
  - `4 arquivos`
  - `11 testes`
  - `PASS`
- `cd apps/api && php artisan test tests/Feature/Wall/PublicWallBootTest.php`
  - `4 testes`
  - `61 assertions`
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
- `cd packages/shared-types && C:\\laragon\\www\\eventovivo\\apps\\web\\node_modules\\.bin\\tsc.cmd --noEmit src\\index.ts src\\wall.ts`
  - `PASS`
- `cd apps/web && npm run type-check`
  - `PASS`
- `Get-Command ffmpeg`
  - `null`
- `Get-Command ffprobe`
  - `null`

Leituras confirmadas pelos testes e codigo atual:

- `MediaSurface` agora separa imagem, `poster-only` e `WallVideoSurface` controlado;
- `WallVideoSurface` agora aplica `autoPlay + muted + playsInline`, sem `loop`, com `poster-first`, `startup deadline`, `waiting/stalled budget` e `play()/pause()` imperativos;
- `useWallEngine` agora usa `setTimeout(interval_ms)` apenas para imagem; video comum sai por `ended`, `cap_reached` ou falha classificada;
- `AdOverlay` continua tratando video de anuncio de forma separada, com `loop = false` e saida por `onEnded`;
- `cache.ts` faz probe de metadata com `<video preload="metadata">`, mas nao e um player cacheado de verdade;
- `preload.ts` faz aquecimento oportunistico com `<video preload="auto" muted>`;
- `selectors.test.ts` confirma que o engine atual prefere itens `ready`, mas ainda aceita itens `idle` quando nao existe nenhum `ready`;
- `layoutStrategy` agora derruba video corrente para layout single-item quando o wall estiver configurado em `carousel`, `mosaic` ou `grid`;
- upload publico aceita video unitario no backend;
- intake privado de video nao passa por estagios image-only;
- `MediaAssetUrlService::wall()` agora prefere `wall_video_720p` e `wall_video_1080p`, enquanto o preview do wall usa poster quando ele existe;
- `MediaAssetUrlServiceTest` confirma preferencia por variante de wall, poster dedicado e fallback para original quando necessario;
- `MediaVariantGeneratorServiceTest` confirma geracao real de `wall_video_720p`, `wall_video_1080p` opcional e `wall_video_poster`;
- `MediaPipelineJobsTest` confirma que `GenerateMediaVariantsJob` usa a trilha `ffmpeg` para video e persiste `wall_video_*` + `wall_video_poster`;
- `PublicUploadTest` agora confirma policy publica coerente para video curto e rejeicao quando o rollout publico do wall/canal ou a policy base desabilitam video, ou quando a duracao excede o limite;
- `PublicEventUploadPage.test.tsx` confirma que a UI publica envia video como `file`, preserva lote de imagens em `files[]` e rejeita mistura de imagem + video;
- `WallEligibilityServiceTest`, `PublicWallBootTest`, `WallDiagnosticsTest` e `MediaPipelineEventsTest` confirmam que o gate final de video agora ja vale para boot, realtime e simulacao;
- `EventWallManagerPage.test.tsx` confirma que o manager agora mostra resumo da policy ativa, readiness de `ffmpeg` / `ffprobe` e avisos operacionais do runtime de video;
- `WallPlayerDetailsSheet.test.tsx` confirma que o detalhe operacional do player renderiza fase, progresso, motivo de saida, falha e perfil de hardware/rede;
- `WallAppearanceTab.test.tsx` confirma rollout por canal no proprio wall (`public_upload_video_enabled` / `private_inbound_video_enabled`);
- `WallVideoAnalyticsTrackingTest` confirma eventos consolidados de analytics de video e dedupe de `play_rejected`;
- `MediaToolingStatusCommandTest` confirma readiness operacional de `ffmpeg` / `ffprobe` por comando;
- `runtime-profile.test.ts` confirma leitura de hints de hardware/rede no player sem quebrar o heartbeat;
- a maquina local agora tem `ffmpeg` / `ffprobe` provisionados fora do repositorio em `%LOCALAPPDATA%\\Programs\\FFmpeg\\bin`, com `.env` apontando para esses binarios;
- o repo agora tem scripts operacionais para provisionamento remoto em `scripts/ops/install-ffmpeg-windows.ps1` e `scripts/ops/install-ffmpeg-ubuntu.sh`;
- o repo agora tambem integrou a trilha oficial de video aos scripts base de host:
  - `bootstrap-host.sh` instala `ffmpeg` no Ubuntu;
  - `verify-host.sh` valida `ffmpeg` / `ffprobe` e executa `php artisan media:tooling-status` quando a release atual existe;
  - `healthcheck.sh` valida `php artisan media:tooling-status` antes do switch da release por default;
  - `homologate-wall-video.sh` gera o relatorio versionado da matriz de homologacao por `device_class` / `network_class`;
- `WallRuntimeMediaService` agora filtra boot e simulacao com o mesmo `WallEligibilityService` usado pelo realtime;
- `PublicWallBootTest`, `MediaPipelineEventsTest` e `WallDiagnosticsTest` agora validam a mesma regra de orientacao entre boot, broadcast e simulacao.

Observacao importante:

- uma rerodada mais ampla com `php artisan test --filter=Wall`, `--filter=InboundMedia` e `--filter=BackfillWallVideoVariantsCommandTest` esbarrou num problema paralelo do workspace em SQLite/migrations (`duplicate column name: billing_order_id`);
- esse erro nao veio desta trilha de video e nao foi usado como bloqueio para o fechamento desta rodada.

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
- nao ligar oficialmente upload publico de video fora dos ambientes em que o rollout por wall/canal, a policy base de duracao e a UX ja estejam validados.

## Status de execucao atual

- [x] `0.2` contrato de settings de video conhecido por `shared-types`, update de settings, boot do wall e manager
- [x] `1.1` simetria base entre boot, realtime, resync e simulacao para as regras atuais do wall
- [x] `1.2` metadata minima e metadata complementar de video persistidas via servico unico quando hints canonicos ou `ffprobe` estao disponiveis
- [x] `1.3` admissao explicita de `wall-eligible video` calculada no backend e exposta no payload/detail resource
- [x] `1.4` variantes reais `wall_video_*` e `wall_video_poster` para fluxos que executam `GenerateMediaVariantsJob` em ambiente com `ffmpeg`
- [x] `1.5` limpeza do intake publico e alinhamento de UX/policy
- [x] `2.1` estado `videoPlayback` no reducer com fases, motivos de saida e falhas classificadas basicas
- [x] `2.2` scheduler principal trocado para imagem por timer e video por causa de saida
- [x] `2.3` `WallVideoSurface` controlado com `play()` / `pause()` imperativos, sem `loop` e com `poster`
- [x] `2.4` readiness operacional minima baseada em `loadeddata`, `canplay`, `readyState`, `waiting` e `stalled`
- [x] `2.5` primeira execucao controlada com `poster-first`, `startup deadline` e `never-blocking`
- [x] `2.6` contrato inicial de `WallVideoSurface` fechado no player
- [x] `3.1` guard rail minimo puxado para frente: video corrente agora nao entra em layout multi-slot e cai para single-item
- [x] `6.2` heartbeat enriquecido com estado atual de video, progresso, `readyState`, `exit_reason`, `failure_reason` e sinais de degradacao
- [x] `6.3` manager/diagnostics mostrando `Policy Summary`, status de pipeline de variantes e alertas operacionais de video
- [x] `6.4` manager expondo admissao do backend por item em `recentItems`, timeline e live snapshot
- [x] `6.5` analytics consolidados de video por transicao e telemetria de hardware/rede no heartbeat
- [x] provisionamento operacional local de `ffmpeg` / `ffprobe` fora do repositorio na maquina de desenvolvimento
- [x] alinhamento inicial de intake privado, legados e rollout oficial do upload publico com policy + tooling readiness
- [x] rollout oficial por evento/wall/canal no manager e no backend (`public_upload_video_enabled` / `private_inbound_video_enabled`)
- [x] scripts e comando operacional para replicar provisionamento de `ffmpeg` / `ffprobe` fora do repositorio
- [x] automacao de host Ubuntu e runbook versionado para homologacao real do wall video
- [ ] provisionar `ffmpeg` / `ffprobe` nos workers reais e ambientes remotos
- [ ] executar a matriz real de homologacao por classe de device e rede em ambiente controlado

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
- politica adaptativa entre `wall_video_720p` e `wall_video_1080p`.

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

- [x] adicionar `video_enabled`;
- [x] adicionar `video_playback_mode`:
  - `fixed_interval`
  - `play_to_end`
  - `play_to_end_if_short_else_cap`
- [x] adicionar `video_max_seconds`;
- [x] adicionar `video_resume_mode`;
- [x] adicionar `video_audio_policy` com default `muted`;
- [x] adicionar `video_multi_layout_policy`:
  - `disallow`
  - `one`
  - `all`
- [x] adicionar `video_preferred_variant`:
  - `wall_video_720p`
  - `wall_video_1080p`
  - `original`

Criterio de aceite:

- criterio atingido:
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
- [x] estender o mesmo gate para criterios especificos de video com rollout por wall/canal:
  - `video_enabled`;
  - politica de duracao/admissao de video;
  - existencia da variante/poster exigidos pelo rollout.
- [x] expor razoes de inelegibilidade que alimentam diagnostico e manager:
  - `orientation_blocked`
  - `video_disabled`
  - `duration_blocked`
  - `variant_missing`
  - `poster_missing`
  - `unsupported_format`

Criterio de aceite:

- criterio atingido nesta fase:
  - o mesmo item agora e aceito ou rejeitado da mesma forma em boot, realtime e simulacao, inclusive para a policy configurada no wall;
  - as razoes de admissao/inelegibilidade de video ja alimentam o manager e as superficies operacionais por item.

### 1.2 Persistir metadata real de video

Arquivos centrais:

- `apps/api/app/Modules/MediaProcessing/Models/EventMedia.php`
- `apps/api/app/Modules/InboundMedia/Http/Controllers/PublicUploadController.php`
- `apps/api/app/Modules/MediaProcessing/Jobs/DownloadInboundMediaJob.php`
- `apps/api/app/Modules/MediaProcessing/Services/VideoMetadataExtractorService.php`

Subtarefas:

- [x] garantir preenchimento consistente de `duration_seconds`, `width` e `height` para video no upload publico e no intake privado quando hints canonicos ou `ffprobe` estao disponiveis;
- [x] adicionar metadata complementar para operacao:
  - `has_audio`
  - `video_codec`
  - `audio_codec`
  - `bitrate`
  - `container`
- [x] definir um servico unico para extrair metadata de video;
- [x] tornar a extracao idempotente para upload publico e intake privado.

Criterio de aceite:

- criterio parcialmente atingido:
  - videos novos ja persistem metadata minima confiavel quando o canal fornece hints canonicos ou quando `ffprobe` esta disponivel no ambiente;
  - ainda falta uma estrategia de backfill/normalizacao para legados e para ambientes sem hints e sem `ffprobe`.

### 1.3 Criar admissao explicita de `wall-eligible video`

Arquivos centrais:

- `apps/api/app/Modules/MediaProcessing/Models/EventMedia.php`
- `apps/api/app/Modules/Wall/Services/WallVideoAdmissionService.php`
- `apps/api/app/Modules/Wall/Services/WallEligibilityService.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaAssetUrlService.php`
- `apps/api/app/Modules/MediaProcessing/Http/Resources/EventMediaDetailResource.php`
- `packages/shared-types/src/wall.ts`

Subtarefas:

- [x] introduzir uma etapa explicita de admissao antes de um video entrar no wall;
- [x] decidir a elegibilidade com base em:
  - duracao;
  - metadata minima;
  - container/codec baseline;
  - poster disponivel;
  - variante preferida disponivel, quando obrigatoria;
  - politica de rollout ativa naquele evento/wall.
- [x] persistir ou calcular em tempo de consulta um resultado legivel:
  - `eligible`
  - `eligible_with_fallback`
  - `blocked`
- [x] registrar motivo de bloqueio/fallback para operacao:
  - `missing_metadata`
  - `duration_over_limit`
  - `unsupported_format`
  - `variant_missing`
  - `poster_missing`

Criterio de aceite:

- criterio atingido para a elegibilidade e rollout oficial do dominio:
  - o backend agora explica no payload e no detalhe da midia porque um video entra, entra com fallback ou ficaria bloqueado;
  - essa classificacao agora governa o gate efetivo usando settings reais do wall para `video_enabled`, cap, variante preferida e rollout por canal.

### 1.4 Gerar variantes de video para wall

Arquivos centrais:

- `apps/api/app/Modules/MediaProcessing/Services/MediaVariantGeneratorService.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaAssetUrlService.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`

Subtarefas:

- [x] criar `wall_video_720p`;
- [x] criar `wall_video_1080p` opcional;
- [x] criar `wall_video_poster`;
- [x] padronizar baseline para web:
  - `MP4`
  - `AVC (H.264)`
  - `AAC`
  - `+faststart`
- [x] atualizar `MediaAssetUrlService::wall()` para preferir variante de wall;
- [x] atualizar `MediaAssetUrlService::preview()` e `::thumbnail()` para preferirem poster/preview de video com compatibilidade para gallery/media;
- [x] manter fallback para original apenas quando nao houver variante;
- [x] registrar no payload do wall qual variante esta sendo servida;
- [x] tornar `ffmpeg` e `ffprobe` configuraveis por ambiente:
  - `MEDIA_FFMPEG_BIN`
  - `MEDIA_FFPROBE_BIN`
  - `apps/api/config/media_processing.php`
- [x] materializar verificacao e runbook operacional:
  - `php artisan media:tooling-status`
  - `scripts/ops/install-ffmpeg-windows.ps1`
  - `scripts/ops/install-ffmpeg-ubuntu.sh`

Criterio de aceite:

- criterio parcialmente atingido:
  - o wall agora entrega `url` otimizada e `preview_url` com poster quando as variantes existem;
  - o pipeline de variantes agora usa a trilha `ffmpeg` para video e a lane `media-variants` foi alinhada para timeout compativel;
  - o payload agora informa `served_variant_key` e `preview_variant_key`;
  - a maquina de desenvolvimento ja foi provisionada fora do repositorio e o backend local resolve `ffmpeg`/`ffprobe` por `.env`;
  - o repo agora ja oferece comando de readiness e scripts de provisionamento para Windows/Ubuntu;
  - ainda falta executar isso nos workers reais e ambientes remotos.

### 1.5 Limpar o intake publico de video

Arquivos centrais:

- `apps/api/app/Modules/InboundMedia/Http/Controllers/PublicUploadController.php`
- `apps/web/src/modules/upload/PublicEventUploadPage.tsx`

Subtarefas:

- [x] alinhar `accept_hint` do backend com a politica final;
- [x] alinhar a UI publica para comunicar corretamente imagem x video;
- [x] adaptar `GenerateMediaVariantsJob` para a nova trilha de variantes de video;
- [x] manter upload multiplo como imagens apenas na primeira entrega;
- [x] rejeitar upload publico de video quando a policy do wall/canal estiver desabilitada ou quando a duracao exceder o cap configurado.
- [x] esconder oficialmente suporte publico a video quando o tooling nao estiver pronto.
- [x] fazer o intake privado de video entrar na trilha de variantes quando o tooling estiver pronto.
- [x] criar comando de backfill para videos legados sem metadata ou sem variantes de wall.

Criterio de aceite:

- criterio atingido para o rollout oficial atual:
  - o bootstrap, a UI e o backend agora comunicam a mesma regra;
  - imagem continua em lote;
  - video fica restrito ao caminho unitario e ao cap configurado;
  - upload publico so liga oficialmente quando `public_upload_video_enabled` + tooling readiness permitem;
  - intake privado so entra na trilha oficial quando `private_inbound_video_enabled` + tooling readiness permitem;
  - legados ja tem comando de backfill para convergir com a policy final.

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

- [x] adicionar bloco `videoPlayback` no estado do player;
- [x] adicionar actions do reducer para eventos de video;
- [x] persistir no runtime pelo menos:
  - `currentTime`
  - `durationSeconds`
  - `readyState`
  - `exitReason`
  - `failureReason`
  - `stallCount`
- [x] fazer o reducer decidir quando um item de video entra, pausa, retoma e sai.

Criterio de aceite:

- o estado do player sabe por que o video entrou, por que saiu e qual foi a classe da falha quando existir.

### 2.2 Trocar o scheduler por causa de saida

Arquivos centrais:

- `apps/web/src/modules/wall/player/hooks/useWallEngine.ts`
- `apps/web/src/modules/wall/player/engine/reducer.ts`

Subtarefas:

- [x] manter `interval_ms` apenas para imagem;
- [x] parar de usar `loop` em video comum;
- [x] quando `currentItem.type === video`, trocar a saida de `setTimeout(interval_ms)` por:
  - `ended`
  - `cap_reached`
  - `play_rejected`
  - `stalled_timeout`
- [x] manter ad video separado, mas alinhado com a mesma taxonomia de eventos;
- [x] mapear `play_rejected` e `error` do elemento para falhas classificadas:
  - `autoplay_blocked`
  - `unsupported_format`
  - `src_missing`
  - `network_error`
- [x] evitar corrida entre scheduler de ads e scheduler de video comum.

Criterio de aceite:

- video comum deixa de ser interrompido apenas porque o timeout fixo venceu.

### 2.3 Controlar `play()` e `pause()` de forma imperativa

Arquivos centrais:

- `apps/web/src/modules/wall/player/components/MediaSurface.tsx`
- `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`
- `apps/web/src/modules/wall/player/engine/autoplay.ts`

Subtarefas:

- [x] separar `MediaSurface` de imagem e `WallVideoSurface` controlado;
- [x] aplicar `muted + playsInline + autoPlay`, mas sem `loop` para video comum;
- [x] chamar `play()` explicitamente e tratar `Promise`;
- [x] chamar `pause()` quando o wall entra em `paused`;
- [x] implementar a semantica de `resume` definida na fase 0 com baseline `resume_if_same_item_else_restart`;
- [x] aplicar `poster` quando disponivel.

Criterio de aceite:

- `wall paused` pausa de verdade o elemento de video atual.

### 2.4 Diferenciar `metadata_ready` de `playback_ready`

Arquivos centrais:

- `apps/web/src/modules/wall/player/engine/cache.ts`
- `apps/web/src/modules/wall/player/engine/preload.ts`
- `apps/web/src/modules/wall/player/hooks/useWallEngine.ts`

Subtarefas:

- [x] manter probe de metadata para dimensao/orientacao;
- [x] adicionar camada de readiness operacional baseada em:
  - `loadeddata`
  - `canplay`
  - `readyState`
  - `waiting`
  - `stalled`
- [x] usar `HAVE_FUTURE_DATA` como threshold minimo razoavel de entrada;
- [x] usar `loadeddata` como sinal util para primeira frame visivel;
- [ ] usar `HAVE_ENOUGH_DATA` como sinal excelente, nao obrigatorio;
- [ ] tratar `canplaythrough` apenas como sinal auxiliar de confianca alta;
- [x] registrar a degradacao quando o item entra em `waiting` ou `stalled`.

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

- [x] introduzir uma trilha `poster-first` para video grande, frio ou sem warming confiavel;
- [x] renderizar `poster` imediatamente na superficie principal enquanto o video tenta subir em paralelo;
- [x] promover o video para `video-live` apenas quando houver sinais minimos de readiness:
  - `loadeddata` como sinal util de primeira frame;
  - `readyState >= HAVE_FUTURE_DATA` como gate minimo recomendado;
  - `playing` como confirmacao operacional de playback real.
- [x] manter fallback quando `loadeddata` nao vier, usando `readyState` e `playing` como criterios principais de promocao;
- [x] nao depender de `metadata_ready` para promover o video a tela principal;
- [x] definir `startup deadline` curto e calibravel:
  - janela alvo inicial de `800ms a 1500ms` para primeira frame;
  - tolerancia maior apenas por perfil de device/wall, nao como regra universal.
- [x] se a janela curta expirar sem readiness suficiente:
  - manter `poster` por pouco tempo;
  - registrar `startup_degraded`;
  - sair por `poster_then_skip` ou `startup_timeout`, conforme policy.
- [x] garantir que o scheduler principal nao fique refem do sucesso do startup do video;
- [x] separar claramente:
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

- [x] separar a renderizacao de imagem da renderizacao de video controlado;
- [x] encapsular `play()`, `pause()`, listeners e cleanup dentro do `WallVideoSurface`;
- [x] classificar falhas antes de propagar para o reducer:
  - `autoplay_blocked`
  - `unsupported_format`
  - `network_error`
  - `src_missing`
  - `decode_degraded`
  - `variant_missing`
- [x] expor callbacks distintos para:
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

- [x] adicionar eventos:
  - `video_start`
  - `video_first_frame`
  - `video_complete`
  - `video_interrupted_by_cap`
  - `video_interrupted_by_pause`
  - `video_waiting`
  - `video_stalled`
  - `video_play_rejected`
- [x] classificar falhas no heartbeat com taxonomia legivel:
  - `network_error`
  - `unsupported_format`
  - `autoplay_blocked`
  - `decode_degraded`
  - `src_missing`
  - `variant_missing`
- [x] enriquecer heartbeat com:
  - `current_media_type`
  - `current_video_phase`
  - `current_video_position_seconds`
  - `current_video_duration_seconds`
  - `current_video_ready_state`
  - `current_video_exit_reason`
  - `current_video_failure_reason`
  - `current_video_stall_count`
  - `current_video_poster_visible`
  - `current_video_first_frame_ready`
  - `current_video_playback_ready`
  - `current_video_playing_confirmed`
  - `current_video_startup_degraded`
- [x] enriquecer heartbeat com hints de homologacao por device/rede:
  - `hardware_concurrency`
  - `device_memory_gb`
  - `network_effective_type`
  - `network_save_data`
  - `network_downlink_mbps`
  - `network_rtt_ms`
  - `prefers_reduced_motion`
  - `document_visibility_state`
- [x] mostrar alertas de wall degradado por video no manager.
- [x] adicionar a mesma taxonomia aos eventos/analytics de video de forma consolidada.

Criterio de aceite:

- criterio atingido para a trilha de observabilidade:
  - o manager e os diagnostics ja mostram fase, progresso, motivo de saida, falha de playback, guidance operacional e perfil de hardware/rede;
  - o backend agora registra analytics consolidados de video por transicao relevante, com dedupe por heartbeat.

## Fase 7 - Rollout e homologacao real

Objetivo:

- evitar ligar video premium em producao sem amostragem real.

Subtarefas:

- [x] liberar por evento/wall com flag e rollout por canal no manager;
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

Passos operacionais restantes:

- Windows worker ou host de homologacao:
  - `powershell -ExecutionPolicy Bypass -File scripts/ops/install-ffmpeg-windows.ps1`
  - configurar `MEDIA_FFMPEG_BIN` e `MEDIA_FFPROBE_BIN` quando o script nao puder escrever no perfil padrao
  - validar com `cd apps/api && php artisan media:tooling-status`
- Ubuntu worker ou host de homologacao:
  - `bash scripts/ops/install-ffmpeg-ubuntu.sh`
  - validar com `cd apps/api && php artisan media:tooling-status`
- em todos os ambientes reais:
  - confirmar que `GenerateMediaVariantsJob` esta rodando na lane correta;
  - gerar pelo menos um video novo e validar `wall_video_720p`, `wall_video_poster`, `served_variant_key` e `preview_variant_key`;
  - validar no manager que `ffmpeg_ready = true`, `ffprobe_ready = true` e que a policy do wall/canal esta refletida na timeline e no live snapshot.
- no deploy:
  - manter `REQUIRE_MEDIA_TOOLING=1` para hosts que participam do rollout de video;
  - usar `REQUIRE_MEDIA_TOOLING=0` apenas em host que ainda nao deve processar video de wall;
  - confirmar que `eventovivo-horizon.service` recicla Horizon por `ExecReload=php artisan horizon:terminate`.

Criterio de aceite:

- video premium so sobe quando o comportamento esta previsivel em runtime, nao apenas bonito em laboratorio;
- pelo menos um ambiente remoto Windows ou Ubuntu precisa fechar `media:tooling-status` como `ready`;
- a matriz de homologacao precisa produzir heartbeat com perfil de hardware/rede e confirmar pelo menos um caso de `video_start`, `video_first_frame` e `video_complete` ou `video_interrupted_by_cap` sem ambiguidade operacional.

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
- [x] `MediaAssetUrlServiceTest` cobrindo fallback para original e preferencia por variante quando existir
- [x] `MediaVariantGeneratorServiceTest` cobrindo geracao real de variantes de video
- [x] novo teste para geracao de `wall_video_*` e `poster`
- [x] novo teste para `MediaAssetUrlService` preferindo variantes de video
- [x] novo teste de admissao de `wall-eligible video` com motivos de bloqueio/fallback
- [ ] novo teste para payload do wall com settings de video

Frontend:

- [x] `MediaSurface.test.tsx` evoluido para video comum sem `loop` e com `poster-only`
- [x] novo teste de `WallVideoSurface` cobrindo `play()`, `pause()`, `resume()` e `ended`
- [x] novo teste de `WallVideoSurface` cobrindo `poster-first` antes da primeira frame
- [x] novo teste de `WallVideoSurface` cobrindo promocao de `poster` para `video-live` apos `loadeddata` + readiness minima
- [ ] novo teste de reducer para estados e motivos de saida
- [ ] novo teste de reducer para falhas classificadas de playback
- [x] `useWallEngine.test.tsx` cobrindo:
  - imagem por timer
  - video por `ended`
  - video por cap
  - video por stall timeout
- [ ] teste de readiness usando `readyState >= HAVE_FUTURE_DATA` como gate minimo
- [x] teste de startup com `startup_timeout` sem bloquear a fila
- [x] teste de `waiting/stalled` com `poster_then_skip`
- [x] teste para bloqueio de video em multi-slot
- [x] testes do manager para `Video Decision Inspector`
- [x] testes do manager para settings de video e avisos operacionais

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
- [x] upload publico e intake privado seguem a politica oficial inicial sem divergencia de UX quando o tooling esta pronto.

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
