# Play Games Discovery

## Objetivo

Este documento consolida:

- o estado real do modulo `Play` hoje;
- as decisoes ja fechadas para iniciar os 2 primeiros jogos;
- os gaps tecnicos antes de chamar essa experiencia de produto;
- a arquitetura recomendada para um launch publico mobile-first.

Contexto de produto assumido:

- 98% dos acessos aos jogos acontecerao no celular do convidado;
- o launch inicial do `Play` deve focar em `memory` e `puzzle`;
- a experiencia publica precisa ser otimizada para web mobile e evoluir para PWA.

## Veredito Executivo

A direcao atual do modulo esta correta:

- Laravel continua como cerebro do `Play`;
- React continua como shell publico e administrativo;
- Phaser continua como runtime do gameplay;
- TanStack Query continua como camada de server state;
- realtime continua valendo para leaderboard e feedback do produto.

O que falta para o modulo virar produto forte nao e "mais Phaser". O que falta e:

- mobile-first real no runtime;
- ciclo robusto de sessao;
- score autoritativo no backend;
- curadoria melhor dos assets;
- PWA publica com cache do shell e cache de runtime para fotos do jogo.

## Decisoes Fechadas para o Launch

Estas decisoes devem ser tratadas como baseline da implementacao:

1. O publico do `Play` permanece dentro de `apps/web` neste momento.
2. O launch e `portrait-first`.
3. O ranking mostra apelido livre, com fallback anonimizado.
4. O score oficial passa a ser calculado no backend.
5. O fallback automatico do publico usa apenas fotos `published`.
6. O `puzzle` vai ate `3x3` no launch.
7. `memory` vai primeiro para producao.
8. `puzzle` entra logo depois, ja redesenhado para celular.
9. O frontend publico do `Play` deve virar PWA antes do launch real.

## Backlog Executavel

Este backlog deve ser tratado como a trilha oficial de implementacao do launch.

### Fase 0 - Fundacao de produto

Objetivo:

- transformar a arquitetura atual em base pronta para launch publico mobile-first;
- endurecer os contratos sem reescrever o modulo inteiro;
- levar `memory` para a primeira versao produtiva sem criar retrabalho para `puzzle`.

#### 0.1 Documentacao viva

Tarefas:

- manter este documento como referencia principal de decisao;
- atualizar o `README` do modulo `Play` no backend conforme a implementacao avancar;
- registrar contratos publicos e administrativos quando o payload real mudar.

Subtarefas:

- refletir diferencas entre contrato alvo e contrato em producao;
- anotar decisoes fechadas de score, sessao, assets e PWA;
- marcar o que ja foi entregue e o que ainda esta pendente.

#### 0.2 Backend - endurecimento inicial

Tarefas:

- validar `settings` por tipo de jogo;
- normalizar configuracao de `memory` e `puzzle`;
- calcular score autoritativo no backend;
- devolver resultado autoritativo no `finish`;
- manter compatibilidade com o fluxo atual sempre que possivel.

Subtarefas:

- criar validadores por tipo de jogo;
- restringir `memory` ao launch: `pairsCount` em `6`, `8` e `10`;
- restringir `puzzle` ao launch: `gridSize` em `2x2` e `3x3`;
- aceitar legado minimo onde necessario e normalizar para o baseline do launch;
- armazenar score do cliente apenas como diagnostico, nunca como verdade;
- garantir que ranking use apenas score autoritativo.

#### 0.3 Frontend - shell publico

Tarefas:

- transformar o shell publico do `Play` em PWA;
- adicionar install prompt;
- preparar persistencia minima do shell;
- exibir estado de conectividade e fallback de runtime.

Subtarefas:

- configurar `vite-plugin-pwa` com `injectManifest`;
- registrar service worker no bootstrap do app;
- criar `manifest.webmanifest`;
- adicionar cache do shell;
- adicionar runtime caching para imagens do jogo;
- criar banner de instalacao do PWA.

#### 0.4 Frontend - runtime mobile-first

Tarefas:

- trocar a base do runtime para viewport logica portrait;
- criar relayout responsivo por viewport;
- redesenhar `memory` com cartas e grid para toque;
- redesenhar `puzzle` com bandeja inferior e board central.

Subtarefas:

- refatorar `bootGame`;
- introduzir helper de viewport e resize;
- limitar `puzzle` a `2x2` e `3x3` no runtime;
- remover dependencias de layout desktop;
- melhorar feedback visual de progresso e loading.

#### 0.5 Fluxo de sessao prioritario

Tarefas:

- manter `start`, `moves` e `finish` funcionando com contratos endurecidos;
- preparar a base para `heartbeat`, `resume` e `abandoned`.

Subtarefas:

- devolver dados suficientes para UI distinguir score autoritativo;
- expor no frontend o resultado validado pelo servidor;
- deixar o payload pronto para evolucao do ciclo de sessao.

#### 0.6 Frontend - performance de entrega

Tarefas:

- separar o runtime Phaser por jogo;
- reduzir o peso inicial da rota publica do jogo;
- aplicar prefetch progressivo orientado por rota e intencao;
- manter a estrutura pronta para adicionar novos jogos sem engrossar o bundle inicial.

Subtarefas:

- transformar o `GameRegistry` em catalogo com loaders assincronos;
- carregar `memory` e `puzzle` apenas quando o `gameKey` for realmente usado;
- criar chunks proprios para `play-memory-runtime` e `play-puzzle-runtime`;
- isolar `phaser` em chunk de vendor dedicado;
- aquecer a rota `PublicGamePage` a partir do hub quando a conexao permitir;
- aquecer dados publicos do jogo com `prefetchQuery`;
- aquecer assets da rodada com orcamento pequeno e heuristica de rede;
- respeitar `saveData` e conexoes `2g/3g` para nao desperdicar banda;
- transformar esse fluxo em baseline para os proximos jogos plugaveis.

### Fase 1 - Memory produtivo

Objetivo:

- subir `memory` como primeiro jogo oficial do `Play`.

Tarefas:

- finalizar UX mobile do board;
- garantir carregamento de assets e fallback publico;
- consolidar ranking e analytics de `memory`.

Subtarefas:

- validar preview inicial por dificuldade;
- calibrar tamanho de carta e espacamento para telas pequenas;
- revisar score e desempate;
- validar fluxo em navegadores mobile reais.

### Fase 2 - Puzzle mobile

Objetivo:

- publicar `puzzle` sem herdar o layout desktop atual.

Tarefas:

- refatorar layout para tabuleiro central e bandeja inferior;
- limitar launch a `2x2` e `3x3`;
- calibrar drag e snap para touch.

Subtarefas:

- revisar tamanho das pecas por viewport;
- calibrar `dragTolerance`;
- revisar score, tempo e drops errados;
- validar em dispositivos de menor desempenho.

## Status da Execucao Atual

Entregue neste ciclo:

- backlog executavel com fases, tarefas e subtarefas;
- validacao de `settings` por tipo de jogo no backend;
- score autoritativo inicial no backend com `finish` retornando `authoritative_result`;
- compatibilidade inicial dos endpoints publicos com payload camelCase;
- shell publico com PWA via `vite-plugin-pwa` e `injectManifest`;
- service worker com cache do shell e runtime caching para imagens e manifestos publicos;
- banner de instalacao do PWA no hub e na pagina publica do jogo;
- baseline mobile-first no runtime Phaser;
- `memory` ajustado para grid portrait;
- `puzzle` ajustado para board central + bandeja inferior;
- `heartbeat`, `resume` e `abandoned` no backend;
- `resumeToken`, `expiresAt` e `sessionSeed` no payload publico;
- persistencia minima da sessao no frontend;
- banner de retomada no shell publico;
- reidratacao minima do runtime com restore de moves;
- `README` do modulo `Play` atualizado no backend.
- `GameRegistry` assincrono para runtime plugavel;
- chunks proprios para `play-memory-runtime` e `play-puzzle-runtime`;
- `phaser` isolado em `vendor-phaser`;
- `PublicGamePage` reduzido para chunk leve;
- prefetch progressivo da rota publica do jogo a partir do hub;
- aquecimento do runtime por `game_type_key`;
- aquecimento controlado de assets com heuristica de rede.

Ainda pendente para os proximos ciclos:

- refinamento visual final de `memory`;
- calibracao final de `puzzle` em devices reais;
- analytics adicionais de PWA e conectividade;
- reduzir tambem o chunk principal global do app, que ainda permanece alto fora do escopo especifico do `Play`;
- medir impacto real em devices modestos e calibrar o orcamento de prefetch por tipo de conexao.

## Stack Atual Confirmada

### Backend

- Laravel 12
- PHP 8.3
- PostgreSQL
- Redis
- broadcasting compativel com Reverb/Pusher

### Frontend

- React 18
- TypeScript
- Vite 5
- TanStack Query
- Phaser 3.90.0
- Pusher JS

### Conclusao de stack

A stack base esta correta para o produto.

Mantemos:

- Laravel para config, sessao, assets, score, ranking, analytics e regras;
- React para shell, overlays, formularios, install prompt, PWA, ranking e estado externo;
- Phaser para tabuleiro, input, preload, animacao e loop do jogo.

## Estado Atual do Modulo Play

## Backend

O backend do `Play` ja possui uma base estrutural boa e reaproveitavel:

- catalogo de tipos de jogo;
- jogo configurado por evento;
- assets vinculados por jogo;
- sessao de jogo;
- tracking de movimentos;
- ranking por jogo;
- analytics por sessao e por jogo;
- leaderboard em tempo real.

### Tabelas existentes

- `play_game_types`
- `play_event_games`
- `play_game_assets`
- `play_game_sessions`
- `play_game_moves`
- `play_game_rankings`

### Endpoints atuais

Administrativo:

- `GET /api/v1/play/catalog`
- `GET /api/v1/events/{event}/play`
- `GET /api/v1/events/{event}/play/analytics`
- `GET /api/v1/events/{event}/play/settings`
- `PATCH /api/v1/events/{event}/play/settings`
- `POST /api/v1/events/{event}/play/games`
- `PATCH /api/v1/events/{event}/play/games/{playGame}`
- `DELETE /api/v1/events/{event}/play/games/{playGame}`
- `GET /api/v1/events/{event}/play/games/{playGame}/assets`
- `POST /api/v1/events/{event}/play/games/{playGame}/assets`

Publico:

- `GET /api/v1/public/events/{event:slug}/play`
- `GET /api/v1/public/events/{event:slug}/play/{gameSlug}`
- `GET /api/v1/public/events/{event:slug}/play/{gameSlug}/ranking`
- `GET /api/v1/public/events/{event:slug}/play/{gameSlug}/last-plays`
- `POST /api/v1/public/events/{event:slug}/play/{gameSlug}/sessions`
- `POST /api/v1/public/play/sessions/{sessionUuid}/moves`
- `GET /api/v1/public/play/sessions/{sessionUuid}/analytics`
- `POST /api/v1/public/play/sessions/{sessionUuid}/finish`

### O que o backend ja faz hoje

- cadastra `memory` e `puzzle` no catalogo;
- cria jogos por evento;
- aceita assets manuais por jogo;
- usa fallback automatico de midia quando nao ha assets manuais;
- inicia sessoes por `player_identifier`;
- recebe `moves`;
- recebe resultado final;
- atualiza ranking;
- publica leaderboard em realtime;
- agrega analytics por sessao e por jogo.

### O que ainda precisa endurecer

- score ainda chega confiado do cliente;
- `settings` ainda entram como `array` generico;
- ainda nao existe ciclo completo de abandono, heartbeat e retomada;
- a selecao automatica de assets ainda esta simples demais para launch publico.

## Frontend

O frontend ja esta dividido em:

- manager administrativo por evento;
- hub publico do `Play`;
- pagina publica do jogo;
- runtime React + Phaser com registry proprio.

### Camadas ja existentes

- `GameRegistry`
- `BaseGameBridge`
- `BasePlayScene`
- `bootGame`
- `usePhaserGame`
- utilitarios de `runtime-prefetch`

Isso confirma que a linha "engine do Evento Vivo + jogos plugaveis" deve ser mantida.

### Fluxo publico atual

- React busca manifest publico;
- React busca definicao do jogo;
- React cria a sessao;
- React passa `sessionUuid`, `settings` e `assets` para o runtime;
- Phaser emite `move`, `progress` e `finish`;
- React envia `moves` em lote para a API;
- React envia `finish`;
- ranking e analytics atualizam em tempo real.

## O Que Ja Esta Certo

- arquitetura por dominio;
- separacao backend/admin/public runtime;
- base de engine plugavel;
- registry preguicoso por jogo;
- code-splitting por runtime de jogo;
- prefetch progressivo por rota e intencao;
- modelo de sessao, ranking e analytics;
- realtime por jogo;
- jogos `memory` e `puzzle` ja iniciados;
- testes de feature backend para fluxo publico e analytics.

## Gaps Criticos Antes de Produzir

## 1. O runtime ainda nao e mobile-first

Problemas concretos ja observados:

- `bootGame` ainda sobe canvas com viewport logica fixa;
- `MemoryScene` ainda precisa layout por viewport real;
- `PuzzleScene` ainda esta desenhado com mentalidade desktop;
- safe area, thumb zone e densidade de toque ainda nao sao base do layout.

Impacto:

- `memory` precisa refino;
- `puzzle` precisa refactor real de layout antes do launch.

## 2. O publico ainda nao e PWA

Hoje ainda faltam:

- `manifest.webmanifest`;
- service worker;
- install prompt;
- cache do shell;
- runtime caching dos assets do jogo;
- estrategia de reconnect.

Conclusao:

- o shell publico do `Play` precisa virar PWA antes do launch.

## 3. O score ainda nao e autoritativo

O backend hoje ainda recebe `score`, `time_ms`, `moves`, `mistakes` e `accuracy` e normaliza quase sem revalidar.

Isso precisa mudar para:

- tempo controlado pelo servidor;
- score oficial calculado no backend;
- payload do cliente tratado como diagnostico;
- ranking usando apenas score autoritativo.

## 4. Ainda falta validacao por tipo de jogo

O catalogo ja carrega `config_schema_json`, mas os requests ainda aceitam `settings` como `array` solto.

Para producao, isso precisa virar:

- validacao por tipo de jogo no backend;
- schema tipado no frontend;
- normalizacao unica por tipo de jogo;
- erro claro quando configuracao invalida entrar.

## 5. Ainda falta ciclo real de sessao

O launch precisa fechar:

- `start`
- `moves`
- `heartbeat`
- `resume`
- `finish`
- `abandoned`

Decisao recomendada:

- retomar sessao por ate 3 minutos;
- marcar `abandoned` apos 120 segundos sem atividade util.

## 6. A curadoria de assets ainda esta fraca

Para launch publico, o fallback automatico precisa considerar:

- apenas fotos `published`;
- prioridade para fotos verticais;
- resolucao minima;
- evitar duplicadas;
- evitar fotos fracas;
- ordem de prioridade entre selecao manual, preset e fallback.

## 7. O modulo Play ainda nao tem documentacao atualizada no proprio modulo

O `README` do modulo backend esta atrasado em relacao ao estado real do sistema.

## Arquitetura Recomendada para o Publico

## Regra principal

React controla aplicacao e produto.
Phaser controla gameplay.

### React deve ser dono de

- rotas publicas;
- shell PWA;
- fetch do manifest;
- criacao, retomada e finalizacao de sessao;
- ranking;
- analytics;
- drawers, overlays e banners;
- estado de conectividade;
- retry de requests;
- install prompt;
- identidade do jogador.

### Phaser deve ser dono de

- preload da rodada;
- tabuleiro;
- input e touch;
- drag, drop, flip e snap;
- animacao e feedback instantaneo;
- progresso local da partida;
- emissao de `move`, `progress` e `finish`.

## Estrutura recomendada no frontend

```text
apps/web/src/modules/play/
  api/
    play.public.api.ts
    play.admin.api.ts
    play.query-options.ts
  dtos/
  schemas/
  hooks/
  store/
  bridge/
    play-event-bus.ts
    play-bridge.types.ts
    usePlayBridge.ts
  game/
    bootGame.ts
    destroyGame.ts
    game.config.ts
    registry/
    core/
      BaseGameBridge.ts
      BasePlayScene.ts
      SceneResizeController.ts
      AssetPreloadService.ts
      SessionTelemetry.ts
      TextureDisposer.ts
    scenes/
      BootScene.ts
      PreloadScene.ts
      OverlayScene.ts
      ResultScene.ts
    games/
      memory/
      puzzle/
  components/
    PublicPlayShell.tsx
    RankingDrawer.tsx
    SessionResumeBanner.tsx
    PwaInstallBanner.tsx
  pages/
    PublicPlayHubPage.tsx
    PublicGamePage.tsx
    EventPlayManagerPage.tsx
  utils/
```

## Estrutura recomendada de cenas

1. `BootScene`
2. `PreloadScene`
3. `GameScene`
4. `OverlayScene`
5. `ResultScene`

### Responsabilidades

`BootScene`:

- recebe config minima;
- prepara viewport;
- conecta bridge.

`PreloadScene`:

- baixa imagens da rodada;
- emite progresso;
- captura erro de asset;
- prepara texturas da sessao.

`GameScene`:

- roda `memory` ou `puzzle`;
- centraliza input e regras visuais.

`OverlayScene`:

- countdown;
- toast visual;
- feedback curto.

`ResultScene`:

- score;
- posicao no ranking;
- CTA para jogar de novo.

## Mobile-First de Verdade

## Escala

Recomendacao de launch:

- `Phaser.Scale.FIT`
- `autoCenter = CENTER_BOTH`
- viewport logica portrait
- relayout em todo `resize`
- orientacao tratada como portrait-first

Evitar:

- layout fixo desktop;
- dependencia de coluna lateral;
- assumir que `Scale Manager` resolve layout sozinho.

## Viewport logico recomendado

```ts
type SceneViewport = {
  width: number
  height: number
  isPortrait: boolean
  safeTop: number
  safeBottom: number
  boardRect: { x: number; y: number; w: number; h: number }
  hudRect: { x: number; y: number; w: number; h: number }
}
```

## Memory no launch

### Meta de UX

- experiencia rapida;
- uso com uma mao;
- partida de 35 a 70 segundos;
- board legivel em portrait.

### Config oficial de launch

```json
{
  "pairsCount": 6,
  "difficulty": "easy",
  "showPreviewSeconds": 3,
  "allowDuplicateSource": false,
  "flipBackDelayMs": 800,
  "scoringVersion": "memory_v1"
}
```

### Faixas do launch

- `easy`: 6 pares, preview 3s
- `normal`: 8 pares, preview 2s
- `hard`: 10 pares, preview 0 a 1s

### Regras fechadas

- fotos nao repetem no launch;
- ranking prioriza maior score;
- desempate por menor tempo, menos erros e menos movimentos.

### Formula recomendada de score autoritativo

```text
score = max(0, 1200 - (elapsed_seconds * 6) - (moves * 4) - (mistakes * 15))
```

## Puzzle no launch

### Meta de UX

- experiencia curta;
- tabuleiro central;
- bandeja de pecas no rodape;
- nada de layout lateral desktop.

### Config oficial de launch

```json
{
  "gridSize": 2,
  "showReferenceImage": true,
  "snapEnabled": true,
  "dragTolerance": 18,
  "scoringVersion": "puzzle_v1"
}
```

### Escopo fechado do launch

- `2x2`
- `3x3`

Fora do launch:

- `4x4`
- `5x5`

### Regras fechadas

- 1 foto por partida;
- a foto vem de um pool curado;
- ranking prioriza menor tempo;
- desempate por menos encaixes errados e menos movimentos.

### Formula recomendada de score autoritativo

```text
score = max(0, 1200 - (elapsed_seconds * 5) - (moves * 2) - (wrongDrops * 8))
```

## Assets e Curadoria

## Ordem de prioridade

1. assets manuais do jogo;
2. preset curado do evento;
3. fallback automatico.

## Regras do launch

- publico usa apenas fotos `published`;
- priorizar fotos verticais;
- lado maior minimo da origem: 1200px;
- derivativo de gameplay: 1080px no lado maior;
- evitar duplicadas;
- evitar fotos borradas, muito escuras ou ruins para recorte.

## Derivativos recomendados

- `play_cover`
- `play_memory_card`
- `play_puzzle_full`

## Cleanup de texturas

Ao encerrar uma sessao:

1. destruir objetos da cena;
2. limpar listeners;
3. remover texturas da sessao.

Nunca remover textura antes de destruir os objetos que ainda a usam.

## Sessao, Resume e Anti-Fraude

## Identidade do jogador

No launch:

- `player_identifier`: token estavel do navegador
- `display_name`: apelido opcional

Preparar schema para futuro:

- `contact_identifier`
- telefone
- integracao com WhatsApp

## Ciclo recomendado

1. `start`
2. `append moves`
3. `heartbeat`
4. `backgrounded`
5. `resumed`
6. `finish`
7. `abandoned`

## Regras de sessao recomendadas

- retomar sessao por ate 3 minutos;
- marcar `abandoned` apos 120 segundos sem atividade;
- ao voltar de background, tentar `resume` antes de reiniciar.

## Regras de score autoritativo

1. `server_started_at` define o tempo real.
2. score do cliente e apenas informativo.
3. `finish` sem coerencia minima pode ser rejeitado ou marcado suspeito.
4. tempo impossivel para a configuracao deve ser recusado.
5. leaderboard usa apenas `authoritative_score`.

## Contratos Publicos Recomendados

## Manifest publico

`GET /api/v1/public/events/{event:slug}/play`

Resposta alvo:

```json
{
  "event": {
    "id": 22,
    "slug": "casamento-ana-e-lucas",
    "title": "Ana & Lucas"
  },
  "games": [
    {
      "uuid": "game-uuid",
      "slug": "memory-noivos",
      "type": "memory",
      "title": "Jogo da Memoria",
      "description": "Encontre os pares das fotos do evento",
      "thumbUrl": "https://cdn.exemplo/thumb.jpg",
      "isActive": true,
      "settingsVersion": "memory_v1",
      "assetsCount": 12
    }
  ],
  "rankingEnabled": true,
  "pwa": {
    "installable": true,
    "minVersion": null
  }
}
```

## Iniciar sessao

`POST /api/v1/public/events/{event:slug}/play/{gameSlug}/sessions`

Request alvo:

```json
{
  "playerIdentifier": "browser-device-token",
  "displayName": null,
  "device": {
    "platform": "ios",
    "viewportWidth": 390,
    "viewportHeight": 844,
    "pixelRatio": 3
  }
}
```

Response alvo:

```json
{
  "session": {
    "uuid": "session-uuid",
    "playerIdentifier": "browser-device-token",
    "resumeToken": "opaque-token",
    "status": "started",
    "startedAt": "2026-04-01T22:00:00-03:00",
    "expiresAt": "2026-04-01T22:03:00-03:00",
    "authoritativeScoring": true
  },
  "game": {
    "uuid": "game-uuid",
    "slug": "memory-noivos",
    "type": "memory",
    "title": "Jogo da Memoria"
  },
  "settings": {
    "type": "memory",
    "pairsCount": 8,
    "difficulty": "normal",
    "showPreviewSeconds": 2,
    "allowDuplicateSource": false,
    "flipBackDelayMs": 700,
    "scoringVersion": "memory_v1"
  },
  "assets": [
    {
      "id": 101,
      "kind": "photo",
      "key": "photo_101",
      "url": "https://cdn.exemplo/play/101.jpg",
      "width": 1080,
      "height": 1350,
      "orientation": "portrait"
    }
  ]
}
```

## Append de moves

`POST /api/v1/public/play/sessions/{sessionUuid}/moves`

Request alvo:

```json
{
  "batchNumber": 1,
  "moves": [
    {
      "moveNumber": 1,
      "clientTs": 1743550000123,
      "type": "flip",
      "payload": {
        "cardIndex": 3,
        "assetId": 101
      }
    }
  ]
}
```

## Heartbeat

`POST /api/v1/public/play/sessions/{sessionUuid}/heartbeat`

Request alvo:

```json
{
  "state": "visible",
  "elapsedMs": 14220,
  "reason": "focus"
}
```

## Resume

`POST /api/v1/public/play/sessions/{sessionUuid}/resume`

Request alvo:

```json
{
  "resumeToken": "opaque-token"
}
```

## Finish

`POST /api/v1/public/play/sessions/{sessionUuid}/finish`

Request alvo:

```json
{
  "clientResult": {
    "timeMs": 43120,
    "moves": 18,
    "mistakes": 3,
    "accuracy": 0.83,
    "score": 910
  }
}
```

Response alvo:

```json
{
  "status": "finished",
  "authoritativeResult": {
    "timeMs": 43208,
    "moves": 18,
    "mistakes": 3,
    "score": 876,
    "rankPosition": 4
  }
}
```

## Ranking publico

`GET /api/v1/public/events/{event:slug}/play/{gameSlug}/ranking`

Resposta alvo:

```json
{
  "items": [
    {
      "position": 1,
      "displayName": "Lulu",
      "score": 980,
      "timeMs": 31200,
      "playedAt": "2026-04-01T21:54:00-03:00"
    }
  ]
}
```

## Contratos Administrativos Recomendados

## Criar jogo

`POST /api/v1/events/{event}/play/games`

```json
{
  "catalogType": "memory",
  "title": "Jogo da Memoria",
  "slug": "memory-noivos",
  "isActive": true,
  "settings": {
    "pairsCount": 8,
    "difficulty": "normal",
    "showPreviewSeconds": 2,
    "allowDuplicateSource": false,
    "flipBackDelayMs": 700
  }
}
```

## Assets do jogo

`POST /api/v1/events/{event}/play/games/{playGame}/assets`

```json
{
  "assetIds": [101, 102, 103, 104],
  "selectionMode": "manual"
}
```

## Preview tecnico do jogo

Recomendacao de novo endpoint:

- `GET /api/v1/events/{event}/play/games/{playGame}/preview-config`

Objetivo:

- devolver `settings` normalizados;
- devolver os assets efetivos da rodada;
- permitir preview consistente no admin.

## DTOs e Schemas Recomendados

## Frontend

DTOs recomendados:

- `PublicPlayManifestDTO`
- `PublicGameCardDTO`
- `PublicGameBootPayloadDTO`
- `PlaySessionDTO`
- `PlayAssetDTO`
- `MemorySettingsDTO`
- `PuzzleSettingsDTO`
- `PlayMoveDTO`
- `ClientFinishPayloadDTO`

Schemas recomendados:

- `MemorySettingsSchema`
- `PuzzleSettingsSchema`
- schemas de manifest publico;
- schemas de payload de sessao;
- schemas de ranking.

## Backend

Requests recomendados:

- `StoreEventGameRequest`
- `UpdateEventGameRequest`
- `StorePlayGameAssetsRequest`
- `StartPublicPlaySessionRequest`
- `AppendPlayMovesRequest`
- `HeartbeatPlaySessionRequest`
- `ResumePlaySessionRequest`
- `FinishPlaySessionRequest`

DTOs recomendados:

- `PublicPlayManifestData`
- `PublicPlayGameData`
- `PlaySessionData`
- `PlayAssetData`
- `MemorySettingsData`
- `PuzzleSettingsData`
- `PlayMoveData`
- `AuthoritativePlayResultData`
- `PlayRankingEntryData`

Services recomendados:

- `PlaySessionService`
- `PlayScoreService`
- `PlayAssetSelectionService`
- `PlayRealtimeService`
- `PlayResumeService`
- `PlayAnalyticsService`

## Validacao por tipo de jogo

Interfaces recomendadas:

- `GameSettingsValidatorInterface`
- `GameScoreCalculatorInterface`

Implementacoes iniciais:

- `MemorySettingsValidator`
- `PuzzleSettingsValidator`
- `MemoryScoreCalculator`
- `PuzzleScoreCalculator`

## Libs recomendadas

Essenciais:

- `phaser`
- `@tanstack/react-query`
- `zod`
- `vite-plugin-pwa`

Muito uteis:

- `zustand` para UI state leve do shell publico
- `react-hook-form` para o manager admin

Opcional e pontual:

- `phaser3-rex-plugins`, apenas se algum componente especifico realmente agregar

## PWA Minimo Obrigatorio

Antes do launch publico, implementar:

1. `vite-plugin-pwa`
2. `manifest.webmanifest`
3. auto registro do service worker
4. cache do shell
5. runtime caching das imagens do jogo
6. retry visual de rede
7. persistencia minima da sessao em curso
8. medicao de install

### Estrategia recomendada

- `injectManifest`

Motivo:

- o `Play` precisa de service worker customizado;
- o shell e os assets dinamicos do jogo tem necessidades diferentes;
- fotos do evento nao devem entrar em precache massivo.

## Analytics do Launch

Eventos recomendados:

- `session_started`
- `assets_loading_started`
- `asset_load_error`
- `game_ready`
- `first_interaction`
- `backgrounded`
- `resumed`
- `abandoned`
- `finished`
- `ranking_updated`
- `pwa_installed`

## Painel Admin no Launch

No launch, o admin nao precisa de replay completo de jogadas.

Basta:

- metricas agregadas;
- drilldown por sessao;
- taxa de abandono;
- top erros;
- ranking;
- analytics por jogo.

Replay completo pode entrar depois.

## Ordem Recomendada de Implementacao

1. consolidar a estrutura `bridge / game / api / dtos / schemas`;
2. fechar validacao de `MemorySettings` e `PuzzleSettings` no front e no back;
3. refatorar o runtime para mobile-first real;
4. implementar `BootScene`, `PreloadScene` e bridge de progresso;
5. fechar `start`, `moves`, `heartbeat`, `resume`, `finish` e `abandoned`;
6. tornar score autoritativo no backend;
7. adicionar PWA ao shell publico;
8. promover `memory` como primeiro jogo oficial;
9. redesenhar `puzzle` para mobile e promover depois;
10. atualizar `README` do modulo `Play` com o estado final.

## O Que Eu Faria Agora

Com este direcionamento fechado, a proxima etapa tecnica recomendada e:

1. especificar o `public play shell` mobile/PWA;
2. refatorar o runtime Phaser para viewport responsiva;
3. endurecer contratos de sessao e score;
4. levar `memory` para a primeira versao produtiva;
5. entrar em `puzzle` logo em seguida, ja limitado a `2x2` e `3x3`.

## Status Recente de Execucao

Concluido nesta frente:

- runtime do `Play` separado por chunks proprios de `memory`, `puzzle` e `vendor-phaser`;
- preload mais agressivo por rota e por intencao de clique;
- shell publico com PWA, persistencia minima e retomada de sessao;
- `heartbeat`, `resume` e `abandoned` conectados no fluxo publico;
- score autoritativo e validacao de `settings` por tipo de jogo;
- manager `/events/:id/play` reorganizado para mostrar links publicos primeiro;
- cards dos jogos separados em blocos recolhiveis de publicacao, regras e fotos;
- pagina publica do jogo reduzida para foco em gameplay, com menu mobile-first para ranking, analytics, historico e app.
- modulo `puzzle` refatorado em `domain`, `factories`, `systems`, `ui` e `types`;
- `puzzle` com `dropZone` real por slot, feedback de drag, particulas, audio e victory flow;
- assets de teste do `puzzle` preparados em `public/assets/play/puzzle`;
- documentacao tecnica dedicada do `puzzle` criada para orientar o time.
- shell publico agora le progresso tipado do `puzzle`, incluindo combo, score preview e completion ratio;
- HUD mobile-first do jogo passou a mostrar progresso compacto sem poluir a tela principal.
- shell publico agora le progresso tipado tambem para `memory`, com score preview, acuracia e pares encontrados;
- `start` e `resume` passaram a popular estado inicial/restaurado do HUD antes de o Phaser emitir o primeiro `progress`;
- `PublicGameMenuSheet` saiu do chunk principal e virou lazy chunk proprio, reduzindo a rota publica do jogo para mobile fraco.
- assets do `Play` agora sao entregues por perfil adaptativo de device/conexao, com selecao de variante no backend;
- sessoes publicas persistem o perfil do device para manter a mesma qualidade de asset em retomadas;
- o web passou a enviar perfil de conexao no `start`, alinhar `queryKey` por perfil e prefetchar com o mesmo bucket de entrega.

Subtarefas concluidas de UX:

- link do hub publico e link direto de cada jogo agora ficam visiveis e copiaveis;
- a tela publica deixou de abrir com ranking, analytics e historico todos expostos;
- os detalhes do jogo migraram para um menu em sheet, mais adequado para celular;
- a tela principal passou a manter apenas entrada, status curto e viewport do jogo.

Proxima pilha recomendada:

1. reduzir ainda mais o peso de `vendor-ui` e do chunk global do app;
2. fechar layout mobile-first definitivo do `puzzle`;
3. adicionar onboarding visual curto no `memory` sem poluir a entrada;
4. preparar o contrato do terceiro jogo sem acoplar UI ou runtime;
5. evoluir a pipeline de media para derivativos dedicados `play_memory_card` e `play_puzzle_full`.
