# Diagnostico do Jogo Publico Memory - 2026-04-11

## Objetivo

Documentar o estado atual do modulo `Play`, com foco no jogo publico:

- evento: `validacao-videos-whatsapp-2026-04-10`
- jogo: `casamento-do-anderson`
- url: `https://admin.eventovivo.com.br/e/validacao-videos-whatsapp-2026-04-10/play/casamento-do-anderson`

O objetivo desta analise e consolidar:

- stack real do modulo no repositorio em `2026-04-11`;
- fluxo tecnico de frontend e backend;
- estado real do jogo em producao;
- causa provavel da tela em branco ou partida sem renderizacao;
- validacao do diagnostico contra documentacao oficial das libs;
- bateria de testes executada e gaps de cobertura;
- backlog recomendado para o proximo ciclo.

---

## Stack Real do Modulo Play

### Backend

- Laravel Framework `^13.0` no `apps/api/composer.json`
- PHP 8.3
- PostgreSQL
- Redis
- broadcasting com Reverb/Pusher compativel

### Frontend

- React `18.3.1`
- TypeScript
- Vite `5.4.19`
- TailwindCSS 3
- shadcn/ui
- Phaser `3.90.0`
- TanStack Query `5.83.0`
- React Hook Form + Zod

### Estado atual da stack no codigo

- `apps/web/src/main.tsx`
  o app nao esta sob `StrictMode` hoje.
- `apps/web/src/lib/query-client.ts`
  ja existe `staleTime = 5 minutos` e `refetchOnWindowFocus = false`.
- `apps/web/vite.config.ts`
  ja existe separacao de chunks para `vendor-phaser`, `play-memory-runtime` e `play-puzzle-runtime`.
- `apps/web/src/modules/play/phaser/registerDefaultGames.ts`
  os runtimes `memory` e `puzzle` ja sao lazy-loaded via `import()`.

---

## O Que Esta Doc Precisava Refinar

Depois da validacao contra o codigo real e contra as docs oficiais, o diagnostico original continua correto na causa raiz, mas precisava de seis ajustes de foco:

1. a maior lacuna nao e visual; e a ausencia de um contrato explicito de runtime entre backend e frontend;
2. a stack real precisava ser corrigida na doc, porque o repositorio hoje esta em Laravel `^13.0`, nao Laravel 12;
3. Vite code split e lazy loading do runtime ja existem parcialmente, entao o problema nao e "falta total de split", e sim governanca de quando bootar, o que bootar e como isolar o canvas;
4. TanStack Query ja esta menos agressivo do que o default puro, entao a melhoria correta e ajustar a rota de jogo, nao reescrever a estrategia inteira de cache;
5. React `memo`, `useMemo` e `useCallback` nao devem entrar na doc como solucao generica; a recomendacao certa e estabilizar a fronteira React <-> Phaser e garantir cleanup;
6. a bateria de testes precisava entrar como parte central do diagnostico, porque o maior gap atual esta justamente no frontend publico do jogo.

---

## Mapa Tecnico Atual

### Backend relevante

- `apps/api/app/Modules/Play/Http/Controllers/PublicPlayController.php`
  entrega o manifesto publico do `Play`.
- `apps/api/app/Modules/Play/Http/Controllers/PublicPlayGameController.php`
  entrega o payload de um jogo publico especifico.
- `apps/api/app/Modules/Play/Http/Controllers/PublicPlaySessionController.php`
  inicia sessao, heartbeat, resume, finish e analytics.
- `apps/api/app/Modules/Play/Services/GameAssetResolverService.php`
  resolve os assets do jogo, incluindo fallback automatico.
- `apps/api/app/Modules/Play/Services/GameSessionService.php`
  monta o `bootPayload` entregue ao frontend.

### Frontend relevante

- `apps/web/src/modules/play/pages/PublicGamePage.tsx`
  monta a tela publica do jogo e chama o runtime Phaser.
- `apps/web/src/modules/play/hooks/usePhaserGame.ts`
  carrega o runtime e cria o `Phaser.Game`.
- `apps/web/src/modules/play/phaser/memory/MemoryScene.ts`
  implementa a cena do jogo da memoria.
- `apps/web/src/modules/play/components/PublicGameMenuSheet.tsx`
  concentra ranking, historico, detalhes e PWA no menu lateral ou inferior.

---

## Logica Atual do Fluxo Publico

### Backend

1. O manifesto publico lista todos os jogos `is_active = true` do evento.
2. O endpoint publico do jogo retorna:
   - configuracao do jogo;
   - assets resolvidos para o runtime;
   - ranking;
   - ultimas partidas;
   - analytics agregados.
3. O endpoint de `start session` cria a sessao e devolve o `bootPayload`.
4. O backend hoje valida:
   - modulo `play` habilitado no evento;
   - `EventPlaySetting.is_enabled = true`;
   - jogo `is_active = true`.
5. O backend hoje nao valida:
   - se o jogo esta realmente pronto para jogar;
   - se existem imagens suficientes para `memory`;
   - se os assets devolvidos sao compativeis com o tipo do jogo.

### Frontend

1. A pagina publica carrega o manifesto e o payload do jogo.
2. O usuario informa apelido opcional.
3. Ao clicar no CTA, o frontend cria uma sessao publica.
4. O payload da sessao e enviado ao Phaser.
5. O runtime do `memory` faz preload de todos os assets recebidos e tenta renderizar o tabuleiro.

---

## Estado Real em Producao em 2026-04-11

### Evento `5`

- evento ativo: `validacao-videos-whatsapp-2026-04-10`
- modulo `play` ativo
- `event_play_settings.is_enabled = true`
- `memory_enabled = true`
- `puzzle_enabled = true`
- `ranking_enabled = true`

### Jogos ativos do evento

- `casamento-do-anderson`
  - tipo: `memory`
  - ativo: `true`
  - `pairsCount = 10`
- `quebra-cabeca-do-anderson`
  - tipo: `puzzle`
  - ativo: `true`

### Assets do evento publicados

Validacao direta no banco e na API publica em `2026-04-11`:

- imagens publicadas aprovadas: `2`
- videos publicados aprovados: `5`
- assets manuais vinculados ao jogo: `0`

Ou seja, o jogo `casamento-do-anderson` esta usando fallback automatico, sem selecao manual de fotos.

### Payload publico atual do jogo `casamento-do-anderson`

O endpoint publico do jogo retorna:

- `pairsCount = 10`
- `7` assets totais no runtime
- composicao desses `7` assets:
  - `2` imagens `image/webp`
  - `5` videos `video/mp4`

Isso significa que o jogo da memoria esta sendo bootado com um conjunto de assets incompativel com a logica do runtime.

---

## Sintomas Observados

### Na interface

- a tela inicial expoe informacoes demais fora do menu;
- o CTA principal e `Iniciar partida`, nao `Jogar agora`;
- depois de uma tentativa de inicio, o botao vira `Nova partida`;
- ha badges, chips, contadores e status expostos antes do jogo funcionar;
- a area principal do jogo pode ficar vazia, sem feedback claro do erro real;
- existe duplicidade de acesso ao menu:
  - botao `Menu` no topo;
  - botao `Abrir menu do jogo` no rodape.

### No comportamento

- a sessao publica e aberta com sucesso;
- o backend registra `status = started`;
- o canvas do Phaser pode ficar sem jogo renderizado;
- o usuario percebe que "o jogo nao inicia", embora a sessao tenha sido criada.

---

## Diagnostico Tecnico

### 1. O backend entrega videos para o runtime do `memory`

O fallback atual em `GameAssetResolverService` busca qualquer midia `approved + published` e nao filtra por compatibilidade com o tipo do jogo.

Trechos relevantes:

- `GameAssetResolverService.php:61-70`
  usa fallback automatico com todas as midias publicadas e aprovadas.
- `GameAssetResolverService.php:110-123`
  devolve `mimeType` no payload, mas nao usa isso para bloquear assets invalidos.
- `GameAssetResolverService.php:192-194`
  o `memory` usa `pairsCount` como limite alvo, mas nao garante quantidade minima real de imagens.

Consequencia:

- o jogo `memory` recebe videos no payload;
- o frontend tenta tratar esses assets como cartas do `memory`.

### 2. O runtime do `memory` tenta carregar todo asset como imagem

Em `MemoryScene.ts`, o preload atual faz:

- `MemoryScene.ts:36-40`
  `this.load.image(...)` para todo asset com `url`.

Esse fluxo nao diferencia:

- `image/webp`
- `video/mp4`

Consequencia:

- assets de video entram no loader de imagem;
- o preload pode falhar parcial ou totalmente;
- o usuario ve area vazia ou jogo sem renderizacao confiavel.

### 3. A regra minima de prontidao do jogo nao existe no backend

O `start` publico da sessao hoje apenas resolve o jogo ativo e cria a sessao:

- `PublicPlaySessionController.php:27-43`

Hoje nao existe validacao do tipo:

- `memory` precisa de pelo menos `pairsCount` imagens validas;
- `puzzle` precisa de pelo menos `1` imagem valida;
- jogo sem prontidao nao deve aparecer como jogavel;
- jogo sem prontidao nao deve abrir sessao.

Consequencia:

- o backend aprova um jogo que ainda nao esta pronto para ser jogado.

### 4. O manifesto publico lista jogos ativos, nao jogos prontos

Em `PublicPlayController.php:29-32`, a listagem publica filtra apenas `is_active`.

Hoje nao existe diferenca entre:

- jogo ativo administrativamente;
- jogo publicavel;
- jogo efetivamente launchable.

Consequencia:

- o frontend mostra jogo disponivel mesmo quando ele nao atende o minimo operacional.

### 5. O frontend marca o runtime como pronto cedo demais

Em `usePhaserGame.ts`:

- `usePhaserGame.ts:63-85`
  o estado muda para `ready` logo apos `gameDefinition.boot(...)`.

Isso acontece antes de garantir que:

- preload concluiu;
- a cena chamou `bridge.ready()`;
- os assets realmente carregaram;
- houve renderizacao util.

Consequencia:

- o overlay `Preparando o jogo...` pode sumir cedo demais;
- a tela fica em branco sem erro claro;
- a UX parece quebrada, mesmo com sessao iniciada.

### 6. O frontend expoe dados demais fora do menu

Em `PublicGamePage.tsx`, a tela principal hoje mantem fora do menu:

- cabecalho tecnico de sessao e realtime;
- apelido;
- CTA;
- chips de fotos, jogadas e retomada;
- painel de progresso completo.

Trechos relevantes:

- `PublicGamePage.tsx:699-775`
- `PublicGamePage.tsx:778-825`

Isso contradiz parcialmente a propria intencao do modulo:

- "Ranking, analytics e historico ficam no menu."

Na pratica, muita informacao continua fora do menu.

---

## Causa Raiz Mais Provavel do Caso Atual

Para o jogo `casamento-do-anderson`, a causa raiz mais provavel e a combinacao de quatro fatores:

1. o evento tem apenas `2` imagens publicadas e `5` videos publicados;
2. o jogo `memory` esta configurado para `10` pares;
3. o fallback publico do backend mistura imagens e videos;
4. o runtime do `memory` tenta carregar tudo como imagem.

Em termos de produto, o jogo nao estava pronto para ser jogado, mas o sistema o apresentou como pronto.

---

## Validacao Contra Documentacao Oficial

### 1. Phaser confirma que o problema central e de ciclo de vida

As docs oficiais de Phaser reforcam que `Scenes` sao a unidade natural para separar responsabilidades como loading screen, main menu e gameplay. As docs do Loader tambem expoem eventos claros de ciclo de carga, como `start`, `progress`, `filecomplete`, `loaderror`, `postprocess` e `complete`.

Validacao para esta doc:

- a recomendacao de separar `LoadingScene`, `MenuScene` e `GameScene` faz sentido arquiteturalmente;
- mesmo sem introduzir tres cenas de imediato, o frontend precisa respeitar esse ciclo de vida;
- `session criada` nao pode ser tratada como `runtime pronto`;
- o bridge React/Phaser precisa expor pelo menos:
  - `onBootStarted`
  - `onPreloadProgress`
  - `onBootReady`
  - `onBootFailed`
  - `onGameStarted`
  - `onGameEnded`

Nuance importante:

- a doc anterior acertou ao falar de `loading`, mas ainda estava orientada demais ao sintoma visual;
- a melhoria principal validada pela doc oficial e formalizar um contrato de runtime antes do `boot()`.

### 2. Phaser tambem valida cleanup mais rigoroso

As docs oficiais indicam que `Phaser.Game.destroy()` agenda a destruicao para o proximo frame, nao como uma limpeza instantanea. A API de `TextureManager` tambem confirma que as texturas vivem em um manager global e podem ser removidas dinamicamente.

Validacao para esta doc:

- `usePhaserGame` precisa tratar destroy e cleanup com mais rigor na troca de sessao, unmount e reinicio;
- o runtime deve reutilizar texturas quando o deck for o mesmo;
- se o conjunto de assets mudar, texturas temporarias devem ser removidas explicitamente.

### 3. React confirma a direcao, mas com escopo menor do que parecia

As docs oficiais de React deixam claro que:

- `StrictMode` em desenvolvimento reexecuta renders e `Effects` para detectar side effects e falta de cleanup;
- `memo`, `useMemo` e `useCallback` sao otimizacoes, nao garantias semanticas.

Validacao para esta doc:

- faz sentido usar `StrictMode` como detector de leaks no runtime publico;
- hoje isso ainda nao acontece, porque `apps/web/src/main.tsx` nao usa `StrictMode`;
- a doc precisa evitar prescrever memoizacao em massa;
- a recomendacao correta e memoizar apenas a fronteira do runtime:
  - `sessionId`
  - `gameId`
  - `runtimeConfig`
  - callbacks do bridge

### 4. TanStack Query confirma que a rota de jogo precisa perfil proprio

As docs oficiais do TanStack Query lembram que queries sao consideradas stale por padrao e podem refetchar em mount, reconnect e outros gatilhos. As docs tambem validam `select` e `notifyOnChangeProps` como ferramentas para reduzir re-render.

Validacao para esta doc:

- a recomendacao de reduzir agressividade durante gameplay esta correta;
- mas a doc precisava registrar que o projeto ja mitiga parte disso com:
  - `staleTime = 5 minutos`
  - `refetchOnWindowFocus = false`
- o ponto que ainda falta mesmo no codigo atual e:
  - revisar `refetchOnReconnect = true` para a rota publica de jogo;
  - isolar ranking e analytics fora do subtree do canvas;
  - reduzir dependencia de query reativa durante partida.

### 5. Vite confirma que code split ja e viavel e parcialmente existente

As docs oficiais do Vite confirmam que `dynamic import()` gera chunks separados e que `build.cssCodeSplit` vem habilitado por padrao. No codigo atual, isso ja aparece tanto na rota publica quanto no registro lazy dos runtimes e nos `manualChunks` dedicados.

Validacao para esta doc:

- a recomendacao de split por tipo de jogo continua correta;
- mas a doc precisava reconhecer que o repositorio ja faz parte desse trabalho;
- o gap real agora e:
  - atrasar o boot do runtime ate a intencao clara de jogar;
  - evitar que shell, menu e ranking disputem renderizacao com o canvas;
  - manter asset pack minimo por sessao.

### 6. Web mobile oficial valida `touch-action`, safe area e haptics, mas como camada secundaria

As docs oficiais da plataforma web validam:

- `touch-action: manipulation` para reduzir atrasos e evitar double-tap zoom;
- `env(safe-area-inset-*)` para proteger UI em aparelhos com notch ou areas nao retangulares;
- `navigator.vibrate()` como API opcional, com comportamento de no-op em aparelhos sem suporte.

Validacao para esta doc:

- essas recomendacoes fazem sentido para dar cara de jogo mobile;
- mas elas entram depois de resolver prontidao, boot e estado do runtime;
- hoje sao melhorias de qualidade percebida, nao a causa primaria da falha atual.

### Referencias oficiais usadas nesta validacao

- Phaser Scenes: `https://docs.phaser.io/phaser/concepts/scenes`
- Phaser Loader Concepts: `https://docs.phaser.io/phaser/concepts/loader`
- Phaser Loader Events: `https://docs.phaser.io/api-documentation/3.88.2/namespace/loader-events`
- Phaser Game API (`destroy`): `https://docs.phaser.io/api-documentation/class/game`
- Phaser TextureManager API: `https://docs.phaser.io/api-documentation/class/textures-texturemanager`
- React `StrictMode`: `https://react.dev/reference/react/StrictMode`
- React `memo`: `https://react.dev/reference/react/memo`
- TanStack Query Important Defaults: `https://tanstack.com/query/latest/docs/framework/react/guides/important-defaults`
- TanStack Query Render Optimizations: `https://tanstack.com/query/latest/docs/framework/react/guides/render-optimizations`
- Vite Features (`dynamic import`): `https://vite.dev/guide/features/`
- Vite Build Options (`cssCodeSplit`): `https://vite.dev/config/build-options.html#build-csscodesplit`
- MDN `touch-action`: `https://developer.mozilla.org/en-US/docs/Web/CSS/touch-action`
- MDN `env()` / safe area: `https://developer.mozilla.org/en-US/docs/Web/CSS/env`
- MDN `Navigator.vibrate()`: `https://developer.mozilla.org/en-US/docs/Web/API/Navigator/vibrate`

---

## O Que Ja Existe Hoje e Precisa Ser Reconhecido na Doc

Para a doc nao parecer que "nada foi feito", estes pontos precisam ficar explicitados:

- o runtime publico ja usa lazy loading de modulo por tipo de jogo;
- o build ja separa chunks de Phaser e runtimes de `memory` e `puzzle`;
- a pagina publica ja usa `useMemo` e `useCallback` em alguns pontos criticos;
- o Query Client global ja reduz parte dos refetches padrao;
- o modulo `Play` ja possui feature tests backend consistentes para fluxo publico, CRUD, catalogo e analytics.

O problema atual nao e ausencia completa de arquitetura.
O problema atual e falta de um contrato mais forte entre:

- disponibilidade de negocio;
- boot tecnico;
- estado visual da pagina.

---

## Bateria de Testes Executada em 2026-04-11

### Backend

Comando executado:

- `cd apps/api && php artisan test tests/Feature/Play`

Resultado:

- `9` testes passaram;
- `153` assertions;
- cobertura existente para:
  - catalogo
  - CRUD de jogos
  - analytics
  - fluxo publico
  - heartbeat e resume
  - validacao de boolean query string no endpoint publico

### Frontend

Comandos executados:

- `cd apps/web && npm run test -- PlayHubPage`
- `cd apps/web && npm run type-check`

Resultado:

- `1` teste frontend do modulo `Play` passou;
- `type-check` passou sem erros.

### Gap real de cobertura

O maior problema da bateria atual e distribuicao ruim da cobertura:

- backend do `Play` esta razoavelmente coberto;
- frontend publico do jogo esta quase sem testes;
- a fronteira React <-> Phaser esta sem caracterizacao automatizada;
- nao ha teste para assets invalidos no `memory`;
- nao ha teste para `runtimeStatus` esperar `onBootReady`;
- nao ha teste de indisponibilidade real quando faltam imagens;
- nao ha e2e publico mobile-first para o fluxo `Jogar agora` -> preload -> partida -> resultado.

### Bateria de testes que falta entrar na doc

1. feature test backend para `launchable` e `bootable`;
2. feature test backend para bloquear `start session` quando faltarem imagens;
3. feature test backend para manifesto publico marcar jogo indisponivel com motivo;
4. vitest para `PublicGamePage` cobrindo estados `idle`, `checking`, `starting-session`, `preloading`, `ready` e `error`;
5. vitest para `usePhaserGame` validando cleanup, destroy e gate por `onBootReady`;
6. vitest para `MemoryScene` ignorando assets `video/*` e exigindo minimo real de pares;
7. Playwright mobile para entrada, preload, partida, pause ou menu e replay.

---

## O Que Precisamos Melhorar

## Veredito de prioridade final validado

Depois da revalidacao contra o codigo real, o caso de producao e as docs oficiais, a ordem que realmente importa ficou esta:

### P0 absoluto

1. contrato de prontidao entre backend e frontend;
2. compatibilidade de assets por tipo de jogo;
3. gate real entre `session`, `preload` e `ready`.

### P1 alto

1. isolamento melhor da fronteira React <-> Phaser;
2. cleanup serio de `destroy`, listeners, timers, heartbeat e texturas;
3. cobertura de testes do frontend publico e da ponte de runtime.

### P2

1. query policy especifica da rota publica de jogo;
2. isolamento de ranking, analytics e menu fora do subtree do canvas;
3. refinamento adicional de prefetch, split e runtime island.

### P3

1. limpeza visual da shell publica;
2. troca de CTA;
3. remocao de duplicidade de menu;
4. `touch-action`, safe area e haptics.

Leitura pratica:

- se o sistema ainda liberar jogo nao `bootable`, mudar layout nao resolve o bug estrutural;
- se o backend ainda misturar `video/*` no `memory`, o erro continua sendo de contrato antes de ser de UX;
- se o frontend continuar tratando `gameDefinition.boot(...)` como `ready`, o preload continuara opaco para o usuario.

## O que deve ser rebaixado agora

Alguns itens continuam corretos como backlog, mas nao devem disputar prioridade com prontidao e lifecycle:

- `StrictMode` nao e solucao de producao; e ferramenta de dev para encontrar side effects e cleanup ruim;
- memoizacao em massa nao e estrategia; o foco certo e estabilizar a fronteira do runtime;
- `touch-action`, `env(safe-area-inset-*)` e `navigator.vibrate()` ajudam na percepcao mobile, mas nao corrigem o bug atual;
- trocar `Iniciar partida` por `Jogar agora` melhora clareza, mas nao corrige um jogo publicado sem assets validos;
- code split adicional so entra depois de o contrato de boot estar correto.

## Prioridade 1 - Contrato de runtime

O principal ajuste desta doc e tornar explicito que a maior melhoria nao e visual. E criar um contrato entre backend e frontend.

Payload minimo recomendado:

- `published`
- `launchable`
- `bootable`
- `requiredAssetCount`
- `readyAssetCount`
- `unsupportedAssetCount`
- `reason`
- `recommendedPairsCount`
- `coverAsset`
- `estimatedLoadWeight`

Leitura recomendada:

- `published`
  jogo pode aparecer no catalogo;
- `launchable`
  cumpre regra de negocio;
- `bootable`
  cumpre regra tecnica para o runtime atual.

Para `memory`, `launchable` e `bootable` devem exigir:

- somente imagens validas;
- quantidade minima real compativel com `pairsCount`.

## Prioridade 2 - Regra de negocio e disponibilidade

### 1. Bloquear sessao para jogo nao launchable ou nao bootable

`PublicPlaySessionController::start` deve retornar erro de negocio claro quando:

- o jogo estiver ativo, mas nao launchable;
- o jogo estiver launchable, mas nao bootable;
- faltarem fotos suficientes;
- os assets forem incompativeis com o tipo.

### 2. Manifesto publico deve esconder ou marcar jogo indisponivel

O manifesto publico deve refletir prontidao real:

- opcao A: esconder jogos nao launchable;
- opcao B: mostrar, mas com status indisponivel e motivo.

Para o caso atual, a regra desejada parece ser:

- sem fotos suficientes, nao existe jogo jogavel.

### 3. Criar conceito de prontidao no backend

O backend precisa calcular algo como:

- `launchable`
- `bootable`
- `requiredAssetCount`
- `readyAssetCount`
- `unsupportedAssetCount`
- `reason`

Exemplos de `reason`:

- `memory.not_enough_images`
- `memory.only_video_assets`
- `puzzle.no_image_available`

## Prioridade 3 - Assets por tipo de jogo e asset pack por sessao

### 4. Filtrar assets do `memory` para imagem

No backend:

- `memory` deve usar apenas `media_type = image` ou `mimeType image/*`;
- videos nao devem entrar no deck.

### 5. Exigir quantidade minima de imagens reais

Para `memory`:

- `pairsCount = 6` exige `6` imagens distintas validas;
- `pairsCount = 8` exige `8` imagens distintas validas;
- `pairsCount = 10` exige `10` imagens distintas validas.

Se o produto quiser fallback elastico, isso deve ser explicito e visivel no admin.
No momento, a direcao mais segura e:

- sem imagens suficientes, nao liberar o jogo.

### 6. Entregar `runtimeAssetPack` pronto para a sessao

No backend, o payload publico nao deve despejar tudo que existe no evento.
O ideal e montar um pacote final compativel com o runtime:

- somente o subconjunto necessario;
- ordenado e validado;
- com peso estimado e `coverAsset`;
- pronto para preload.

### 7. Melhorar o manager do admin

No admin do evento, o card do jogo deve mostrar:

- fotos validas para `memory`;
- videos ignorados para `memory`;
- total necessario vs total disponivel;
- status `Pronto para publicar` ou `Faltam fotos`.

## Prioridade 4 - Ciclo de vida do runtime e bridge React/Phaser

### 8. Criar maquina de estados explicita no frontend

Fluxo recomendado:

- `idle`
- `checking`
- `starting-session`
- `preloading`
- `ready`
- `playing`
- `paused`
- `finished`
- `error`

Hoje o salto entre `start session` e `ready` esta curto demais.

### 9. So marcar runtime como pronto quando a cena estiver pronta

`usePhaserGame` deve manter `loading` ate o `onBootReady` real da cena.

Se houver falha de preload, o usuario deve ver:

- erro claro;
- CTA de tentar novamente;
- mensagem de ativo indisponivel, se for problema de assets.

### 10. Filtrar no frontend por `mimeType`

Mesmo com a correcao no backend, o frontend deve ser defensivo:

- `MemoryScene` deve ignorar assets que nao sejam `image/*`.

### 11. Exigir minimo correto no runtime

Hoje o `memory` so falha se houver menos de `2` assets:

- `MemoryScene.ts:48-54`

Isso esta fraco demais.

O correto e:

- se `pairsCount = 10`, exigir `10` imagens validas antes de montar o deck.

### 12. Isolar melhor o runtime island

`PublicGamePage` nao deve misturar o subtree do canvas com chips, rankings, analytics e overlays reativos.
O ideal e:

- nickname, menu e overlays fora do componente que instancia o jogo;
- props minimas e estaveis para o runtime;
- cleanup rigoroso de listeners, heartbeat, timers e instancias.

## Prioridade 5 - Query policy, UX publica e mobile

### 13. Aplicar perfil proprio de Query na rota publica de jogo

Na rota de gameplay:

- manifesto e detalhes do jogo podem ter `staleTime` maior;
- ranking e ultimas partidas devem refetchar fora do canvas;
- gameplay nao deve depender de refetch automatico;
- `refetchOnReconnect` deve ser revisto para esta rota.

### 14. Reduzir a quantidade de informacao fora do menu

Na tela principal, o ideal para o `memory` e:

- titulo do jogo;
- subtitulo do evento;
- CTA principal `Jogar agora`;
- opcionalmente campo de apelido;
- estado simples do jogo:
  - pronto para jogar;
  - preparando;
  - faltam fotos;
  - erro ao carregar.

Fora isso, ranking, historico, analytics e detalhes de sessao devem ficar no menu.

### 15. Trocar o CTA principal

O CTA deve ser mais direto:

- `Jogar agora`

Quando a sessao ja existir:

- `Continuar partida`
ou
- `Jogar novamente`

`Nova partida` funciona, mas nao comunica tao bem o fluxo inicial.

### 16. Mostrar estado vazio util quando o jogo nao estiver pronto

Exemplo de mensagem:

> Este jogo ainda nao esta pronto para jogar.
> Faltam fotos publicadas suficientes para montar o tabuleiro.

### 17. Evitar duplicidade de menu

Hoje ha dois acessos ao mesmo menu na mesma tela.
Deve permanecer apenas um padrao principal.

### 18. Fechar a camada mobile

Depois de resolver prontidao e boot:

- aplicar `touch-action: manipulation` no container principal;
- usar `env(safe-area-inset-*)` para voltar, menu e CTA;
- adicionar feedback visual curto para flip, match e mismatch;
- usar `navigator.vibrate()` apenas como enhancement opcional.

---

## Validacao do Caso `casamento-do-anderson`

### Como esta hoje

- tecnicamente publicado e ativo;
- operacionalmente nao pronto para `memory`;
- backend abre sessao mesmo assim;
- frontend tenta bootar runtime com assets incompativeis;
- usuario recebe uma tela confusa, com muita informacao e pouca clareza de erro.

### Diagnostico final

O problema principal nao e falta de botao.
O problema principal e:

- o sistema considera o jogo launchable quando ele nao deveria ser.

O problema de UX existe, mas ele agrava um erro de prontidao e validacao que nasce no backend e explode no frontend.

---

## Backlog Recomendado

## Sprint 1 - contrato e prontidao

1. criar `GameLaunchReadinessService`;
2. expor `published`, `launchable`, `bootable` e `reason`;
3. filtrar assets do `memory` para imagem no backend;
4. validar `requiredAssetCount` antes de expor o jogo publicamente;
5. bloquear `start session` quando faltarem imagens suficientes.

## Sprint 2 - ciclo de vida do runtime

1. criar bridge com `onBootStarted`, `onPreloadProgress`, `onBootReady`, `onBootFailed`, `onGameStarted` e `onGameEnded`;
2. corrigir `usePhaserGame` para respeitar `onBootReady` real;
3. filtrar assets invalidos tambem no frontend;
4. exigir minimo real de imagens no `MemoryScene`;
5. revisar cleanup de `game.destroy()`, listeners, timers e texturas.

## Sprint 3 - shell, Query e runtime island

1. simplificar o subtree do canvas;
2. mover ranking, analytics e historico para fora do host do runtime;
3. aplicar perfil de Query proprio na rota publica;
4. revisar `refetchOnReconnect` durante gameplay;
5. manter `runtimeAssetPack` minimo por sessao.

## Sprint 4 - UX publica e mobile

1. simplificar a tela principal;
2. trocar CTA para `Jogar agora`;
3. mover chips e metricas nao essenciais para o menu;
4. criar estado vazio claro para jogo indisponivel;
5. remover duplicidade do menu;
6. aplicar safe area, `touch-action` e feedback ou haptic.

## Sprint 5 - testes

1. adicionar feature tests backend para `launchable` e `bootable`;
2. adicionar vitest para `PublicGamePage` e `usePhaserGame`;
3. adicionar vitest para `MemoryScene` com assets invalidos;
4. adicionar e2e mobile do fluxo publico de jogo.

## Sprint 6 - UX de operacao e admin

1. mostrar prontidao do jogo no manager;
2. destacar imagens validas vs videos ignorados;
3. permitir selecao manual obrigatoria de fotos para `memory`, se desejado;
4. exibir motivo de bloqueio antes de ativar ou publicar.

---

## Arquivos Mais Provaveis de Mudanca

### Backend

- `apps/api/app/Modules/Play/Services/GameAssetResolverService.php`
- `apps/api/app/Modules/Play/Http/Controllers/PublicPlayController.php`
- `apps/api/app/Modules/Play/Http/Controllers/PublicPlayGameController.php`
- `apps/api/app/Modules/Play/Http/Controllers/PublicPlaySessionController.php`
- possivelmente um novo service `GameLaunchReadinessService`

### Frontend

- `apps/web/src/modules/play/pages/PublicGamePage.tsx`
- `apps/web/src/modules/play/hooks/usePhaserGame.ts`
- `apps/web/src/modules/play/phaser/memory/MemoryScene.ts`
- `apps/web/src/modules/play/components/PublicGameMenuSheet.tsx`
- possivelmente um novo componente `GameUnavailableState`
- possivelmente uma `LoadingScene` ou um bridge mais formal de boot

---

## Conclusao

O modulo `Play` ja tem uma base boa:

- sessao publica;
- ranking;
- analytics;
- resume;
- realtime;
- runtime Phaser separado por tipo de jogo;
- code split parcial ja implantado;
- cobertura backend funcional ja razoavel.

Mas o caso real do jogo `casamento-do-anderson` mostra que ainda falta uma camada essencial:

- validacao de prontidao real do jogo antes da exposicao publica;
- contrato de runtime explicito entre backend e frontend;
- gate correto entre sessao criada, preload concluido e jogo realmente pronto.

Sem essa camada, o sistema ativa jogos que ainda nao possuem insumos validos para o tipo escolhido, e a UX publica acaba escondendo um problema de negocio com sintomas de frontend.
