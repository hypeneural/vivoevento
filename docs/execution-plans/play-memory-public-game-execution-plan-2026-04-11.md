# Play memory public game execution plan - 2026-04-11

## Objetivo

Este documento transforma o diagnostico do jogo publico `memory` em um plano de execucao implementavel, detalhado e orientado por TDD.

Ele cobre:

- a baseline real validada antes da execucao;
- a prioridade final que realmente importa para corrigir o bug atual;
- as decisoes tecnicas que ficam travadas para evitar drift;
- as fases de entrega em ordem de ROI;
- tarefas e subtarefas backend e frontend;
- testes obrigatorios antes de cada comportamento;
- criterios de aceite e riscos por fase;
- o que entra em P0, P1, P2 e P3.

Documento base:

- `docs/architecture/play-memory-public-game-diagnostic-2026-04-11.md`

---

## Veredito executivo

O plano deve atacar primeiro o erro estrutural, nao o sintoma visual.

Ordem final validada:

### P0 absoluto

1. contrato de prontidao entre backend e frontend;
2. compatibilidade de assets por tipo de jogo;
3. gate real entre `session`, `preload` e `ready`.

### P1 alto

1. isolamento da fronteira React <-> Phaser;
2. cleanup serio de `destroy`, listeners, timers, heartbeat e texturas;
3. preloading real com progresso, escala mobile correta e input previsivel;
4. shell publica com uma acao principal por tela;
5. cobertura de testes do frontend publico e da ponte de runtime.

### P2

1. query policy especifica da rota publica de jogo;
2. compartilhamento nativo do resultado e desafio com fallback;
3. PWA e install prompt no momento certo;
4. ranking social e historico depois da primeira partida;
5. replay em um toque.

### P3

1. admin readiness e operacao;
2. limpeza visual adicional da shell publica;
3. remocao de duplicidade residual;
4. polish mobile com `touch-action`, safe area e haptics.

---

## Baseline validada antes da execucao

## Estado do caso real

- evento `validacao-videos-whatsapp-2026-04-10`
- jogo `casamento-do-anderson`
- tipo `memory`
- `pairsCount = 10`
- assets manuais vinculados: `0`
- assets publicados aprovados do evento:
  - `2` imagens
  - `5` videos
- payload publico atual do jogo:
  - `7` assets totais
  - `2` imagens
  - `5` videos

Leitura pratica:

- o jogo esta `published`, mas nao deveria estar `launchable`;
- o jogo entra em sessao mesmo sem conjunto minimo valido de imagens;
- o runtime do `memory` recebe assets incompativeis.

## Testes executados antes do plano

### Backend

Comando:

```bash
cd apps/api
php artisan test tests/Feature/Play
```

Resultado:

- `9` testes passaram;
- `153` assertions;
- cobertura existente para:
  - catalogo
  - CRUD de jogos
  - analytics
  - fluxo publico
  - heartbeat e resume
  - validacao de boolean query string

### Frontend

Comandos:

```bash
cd apps/web
npm run test -- PlayHubPage
npm run type-check
```

Resultado:

- `1` teste do modulo `Play` passou;
- `type-check` passou.

Leitura pratica:

- o backend do `Play` tem baseline razoavel;
- o frontend publico do jogo esta subcoberto;
- a principal zona de risco hoje e a ponte React <-> Phaser.

## Validacao complementar do `puzzle` em 2026-04-11

Depois da abertura deste plano, foi feita uma rodada extra de caracterizacao automatizada focada no runtime do `puzzle`.

### Backend

Comando:

```bash
cd apps/api
php artisan test tests/Feature/Play/PublicPlayPuzzleCharacterizationTest.php
```

Resultado:

- `2` testes passaram;
- `24` assertions;
- validacao nova:
  - o `puzzle` ja recebe `runtime asset pack` minimo de `1` asset;
  - em perfil `rich`, o backend prefere `variantKey = wall` para `puzzle`;
  - o backend **ainda pode** expor `video/mp4` como asset do `puzzle` quando o evento nao tem imagem publicada valida.

### Frontend

Comandos:

```bash
cd apps/web
npm run test -- src/modules/play/hooks/usePhaserGame.test.tsx src/modules/play/phaser/core/bootGame.test.ts
npm run type-check
```

Resultado:

- `2 arquivos`
- `4 testes`
- `PASS`
- `type-check` passou

Validacao nova:

- `usePhaserGame` ainda marca `ready` logo apos `boot()`, antes de `onReady`;
- `bootGame` ja sobe o Phaser com:
  - `Scale.FIT`
  - `CENTER_BOTH`
  - `activePointers = 1`
- ou seja:
  - a base de escala e input do `puzzle` esta razoavel;
  - o gap principal continua sendo contrato de prontidao + gate real de boot.

### Leitura pratica do `puzzle`

Para o `puzzle`, o estado real agora fica mais claro:

- o backend ja se comporta como pack minimo de sessao;
- o runtime usa apenas `assets[0]`;
- mas ainda nao existe garantia de que esse `assets[0]` seja `image/*`;
- e o frontend ainda trata `boot()` como se fosse `runtime pronto`.

Conclusao:

- o `puzzle` nao invalida o plano;
- ele reforca o plano;
- a diferenca e que, para `puzzle`, a regra minima correta e:
  - `1` imagem valida;
  - `0` videos aceitos como capa jogavel.

---

## Validacao complementar contra a documentacao oficial do Phaser

As docs oficiais reforcam quatro pontos diretamente relevantes para este plano:

1. `Scenes READY`:
   - o evento `READY` do Phaser so acontece quando a Scene ja esta ativa e renderizando;
   - isso valida a regra de que `session criada` e `boot()` nao equivalem a `ready`.
2. `Loader`:
   - `preload()` inicia o Loader automaticamente;
   - `create()` so roda depois do carregamento terminar;
   - `progress` e `loaderror` existem como eventos oficiais;
   - isso valida a necessidade do estado separado `preloading`.
3. `Scale Manager`:
   - `FIT` e o modo oficial para manter proporcao dentro da area alvo;
   - `CENTER_BOTH` e o centramento oficial do canvas;
   - isso valida a base atual do `bootGame` para mobile.
4. `Input`:
   - Phaser unifica mouse e touch em eventos de ponteiro, como `pointerdown`;
   - isso valida a direcao `pointer-first` no runtime publico.

Leitura pratica:

- para o `puzzle`, o maior erro atual nao e de escala nem de input;
- o maior erro atual continua sendo lifecycle:
  - asset invalido pode entrar;
  - `ready` aparece cedo demais;
  - o jogo pode parecer iniciado antes de estar realmente jogavel.

Referencias oficiais validadas nesta rodada:

- Phaser Scenes Events: `https://docs.phaser.io/api-documentation/namespace/scenes-events`
- Phaser Loader Concepts: `https://docs.phaser.io/phaser/concepts/loader`
- Phaser Scale Manager Concepts: `https://docs.phaser.io/phaser/concepts/scale-manager`
- Phaser Input Concepts: `https://docs.phaser.io/phaser/concepts/input`
- Phaser Game API: `https://docs.phaser.io/api-documentation/class/game`

---

## Decisoes tecnicas fixadas antes da implementacao

Estas decisoes ja ficam travadas para evitar rediscussao durante a execucao:

1. `session criada` nao significa `jogo pronto`.
2. O contrato publico de prontidao entra nos endpoints existentes e continua aditivo.
3. O contrato da API publica permanece em `snake_case`, coerente com os Resources atuais.
4. O backend passa a expor, no minimo:
   - `published`
   - `launchable`
   - `bootable`
   - `required_asset_count`
   - `ready_asset_count`
   - `unsupported_asset_count`
   - `reason`
   - `cover_asset`
   - `estimated_load_weight`
5. O `memory` so aceita `image/*` como asset valido para deck.
6. O `memory` exige minimo real de imagens distintas igual a `pairsCount`.
7. O `puzzle` so aceita `image/*` como asset valido de capa.
8. O `puzzle` exige pelo menos `1` imagem valida para ficar `launchable` e `bootable`.
9. O `puzzle` continua recebendo pack minimo de `1` asset por sessao, nao um lote inteiro do evento.
10. O frontend nao tenta bootar runtime se `bootable = false`.
11. `usePhaserGame` so marca `ready` quando receber `onBootReady` real da cena.
12. Falha de preload e indisponibilidade de asset devem convergir para estado claro de `error` ou `unavailable`, nunca para canvas silenciosamente vazio.
13. A mesma regra de prontidao usada no `manifest` e no `show` deve ser revalidada no `start session`.
14. `StrictMode` entra como ferramenta opcional de dev para achar side effects, nao como solucao de producao.
15. Nao havera memoizacao em massa; o foco e estabilizar a fronteira do runtime.
16. Antes da primeira partida, a shell publica deve ter uma acao principal por tela.
17. Ranking, historico e analytics nao devem disputar atencao antes da primeira partida terminada.
18. O apelido continua opcional e pulavel.
19. O fim da partida vira ponto de compartilhamento, nao a entrada do jogo.
20. `touch-action`, safe area, haptics, CTA e limpeza visual ficam fora do P0.
21. O canvas deve ser tratado como runtime host estavel, nao como bloco reativo qualquer.
22. Input do jogo deve ser `pointer-first`, coerente entre mouse, toque e caneta.
23. O ajuste de escala do canvas deve ser tratado como parte do jogo, nao como detalhe visual.
24. O `bootGame` base permanece em `Scale.FIT` + `CENTER_BOTH` ate surgir motivo real para outra estrategia.
25. Nao criar endpoint extra so para prontidao nesta entrega; o contrato entra nos payloads publicos ja existentes.
26. Se a complexidade do pacote de assets crescer, extrair service dedicado; se nao crescer, manter dentro do modulo `Play` sem inflar a superficie.

---

## Contrato alvo da API

## Readiness do jogo

Payload recomendado no backend, em `snake_case`:

```json
{
  "published": true,
  "launchable": false,
  "bootable": false,
  "required_asset_count": 10,
  "ready_asset_count": 2,
  "unsupported_asset_count": 5,
  "reason": "memory.not_enough_images",
  "cover_asset": {
    "id": "123",
    "url": "https://..."
  },
  "estimated_load_weight": {
    "asset_count": 2,
    "total_pixels": 2488320
  }
}
```

Semantica travada:

- `published`
  jogo aparece no catalogo ou manifesto;
- `launchable`
  cumpre regra de negocio;
- `bootable`
  cumpre regra tecnica para o runtime atual.

Na V1 do `memory`, `launchable` e `bootable` tenderao a caminhar juntos, mas os dois campos permanecem separados para nao misturar negocio e runtime.

## Maquina de estados alvo no frontend

Fluxo alvo:

- `idle`
- `checking`
- `starting-session`
- `preloading`
- `ready`
- `playing`
- `paused`
- `finished`
- `error`

Bridge minimo recomendado:

- `onBootStarted`
- `onPreloadProgress`
- `onBootReady`
- `onBootFailed`
- `onGameStarted`
- `onGameEnded`

## Experiencia publica alvo

Antes da primeira partida:

- nome do jogo;
- apelido opcional;
- um unico CTA grande;
- nenhuma poluicao de ranking, analytics ou painel tecnico.

Durante o boot:

- mensagem curta;
- progresso real de preload;
- erro claro se o jogo nao puder subir.

Durante a partida:

- HUD minima;
- cartas grandes;
- entrada por `pointerdown`;
- feedback curto e previsivel.

No resultado:

- score e tempo em destaque;
- `Jogar de novo`;
- compartilhar resultado ou desafio;
- ranking e historico aparecem como consequencia, nao como barreira.

---

## Ordem de ROI consolidada

Depois da validacao final, a ordem de execucao que mais reduz risco ficou:

1. contrato de prontidao no backend;
2. compatibilidade de assets no backend;
3. gate real `start -> preload -> ready` no frontend;
4. blindagem do `memory` contra assets invalidos;
5. testes do fluxo publico ponta a ponta;
6. runtime island, cleanup e host estavel;
7. shell mobile-first com uma acao principal por tela;
8. query policy da rota publica;
9. compartilhamento, replay, PWA e ranking social.

---

## Mapa de ownership

## Backend

Ownership no modulo `Play`.

Arquivos e pastas principais:

```text
apps/api/app/Modules/Play/
  DTOs/
  Http/Controllers/
  Http/Requests/
  Http/Resources/
  Models/
  Services/
  Support/
  routes/api.php
```

## Frontend

Ownership no modulo `play`.

Arquivos e pastas principais:

```text
apps/web/src/modules/play/
  api/
  hooks/
  pages/
  components/
  phaser/core/
  phaser/memory/
  realtime/
  utils/
```

## Testes

Backend:

```text
apps/api/tests/Feature/Play/
apps/api/tests/Unit/Play/
```

Frontend:

```text
apps/web/src/modules/play/**/*.test.ts
apps/web/src/modules/play/**/*.test.tsx
apps/web/e2e/
```

---

## Fase 0 - Travar baseline, contrato e matriz de testes

## Objetivo

Congelar a baseline atual, fechar o formato do contrato e preparar a execucao em TDD sem ambiguidade.

## Tarefas

### T0.1 - Registrar baseline automatizada

Subtarefas:

- registrar no plano a baseline atual do backend `Play`;
- registrar no plano a baseline atual do frontend `Play`;
- registrar o caso real do evento `5` como fixture funcional de referencia;
- deixar claro que falhas visuais nao entram antes da correcao estrutural.

### T0.2 - Travar o contrato de prontidao

Subtarefas:

- travar nomes de campos em `snake_case`;
- travar semantica de `published`, `launchable` e `bootable`;
- travar codigos de `reason`;
- decidir comportamento do manifesto:
  - manter jogo visivel com estado indisponivel e motivo.

Justificativa:

- esconder o jogo dificulta diagnostico e operacao;
- mostrar indisponivel com motivo reduz ambiguidade e preserva contexto.

### T0.3 - Travar a matriz de testes P0

Subtarefas:

- definir testes backend obrigatorios antes de mexer nos controllers;
- definir testes frontend obrigatorios antes de mexer no runtime;
- definir arquivo e2e mobile do fluxo publico.

## TDD obrigatorio da fase

Antes de qualquer refactor:

- criar ou planejar os testes falhando para:
  - jogo `memory` com videos misturados;
  - `start session` bloqueado por falta de imagens;
  - `usePhaserGame` esperando `onBootReady`;
  - `MemoryScene` ignorando `video/*`.

## Criterios de aceite

- prioridades P0, P1, P2 e P3 ficam travadas;
- contrato publico fica definido sem drift de naming;
- comportamento do manifesto para jogo indisponivel fica decidido;
- matriz de testes minima fica definida antes da implementacao.

---

## Fase 1 - Contrato de prontidao no backend

## Objetivo

Fazer o backend dizer claramente se o jogo esta apenas publicado, realmente jogavel e tecnicamente bootavel.

## Arquivos sugeridos

```text
apps/api/app/Modules/Play/Services/GameLaunchReadinessService.php
apps/api/app/Modules/Play/DTOs/GameLaunchReadinessDTO.php
apps/api/app/Modules/Play/Http/Resources/PlayEventGameResource.php
apps/api/app/Modules/Play/Http/Controllers/PublicPlayController.php
apps/api/app/Modules/Play/Http/Controllers/PublicPlayGameController.php
apps/api/app/Modules/Play/Http/Controllers/PublicPlaySessionController.php
```

## Tarefas

### T1.1 - Criar `GameLaunchReadinessService`

Subtarefas:

- calcular `published`, `launchable` e `bootable`;
- calcular `required_asset_count`, `ready_asset_count` e `unsupported_asset_count`;
- calcular `reason`;
- calcular `cover_asset`;
- calcular `estimated_load_weight`;
- encapsular regras por tipo de jogo sem espalhar `if` em controller.

### T1.2 - Expor prontidao no manifesto publico

Subtarefas:

- acoplar `readiness` ao `PlayEventGameResource`;
- manter o jogo visivel no manifesto mesmo quando indisponivel;
- incluir motivo de indisponibilidade para o frontend.

### T1.3 - Expor prontidao no endpoint publico do jogo

Subtarefas:

- incluir `readiness` no payload do `show`;
- garantir coerencia entre `game`, `runtime.assets` e `readiness`;
- impedir que o `show` devolva assets incompativeis como se fossem deck valido.

### T1.4 - Bloquear `start session` para jogo nao launchable ou nao bootable

Subtarefas:

- revalidar prontidao no `start`;
- devolver erro de negocio com `422` e `reason` legivel;
- impedir criacao de sessao fantasma;
- impedir toasts frontend dizendo que o jogo ja esta pronto quando ainda nao esta.

## TDD obrigatorio da fase

### Backend - escrever primeiro

- `apps/api/tests/Unit/Play/GameLaunchReadinessServiceTest.php`
  - `memory` com imagens suficientes => `launchable=true`, `bootable=true`;
  - `memory` com videos apenas => `launchable=false`, `bootable=false`, `reason=memory.only_video_assets`;
  - `memory` com imagens insuficientes => `reason=memory.not_enough_images`;
  - `puzzle` com ao menos uma imagem valida => `launchable=true`, `bootable=true`;
  - `puzzle` com apenas video publicado => `launchable=false`, `bootable=false`, `reason=puzzle.no_image_available`.
- `apps/api/tests/Feature/Play/PublicPlayReadinessTest.php`
  - manifesto devolve `readiness` por jogo;
  - `show` devolve `readiness` coerente;
  - `start session` bloqueia jogo nao bootable com `422`.

## Criterios de aceite

- backend expoe prontidao de forma explicita;
- `start session` nao cria sessao quando faltam assets validos;
- o frontend consegue decidir disponibilidade sem inferir erro no meio do preload.

---

## Fase 2 - Compatibilidade de assets por tipo e `runtime_asset_pack`

## Objetivo

Garantir que o runtime receba apenas assets compativeis com o tipo de jogo e na quantidade minima correta.

## Arquivos sugeridos

```text
apps/api/app/Modules/Play/Services/GameAssetResolverService.php
apps/api/app/Modules/Play/Services/GameLaunchReadinessService.php
apps/api/tests/Unit/Play/GameAssetResolverServiceTest.php
```

## Tarefas

### T2.1 - Filtrar assets do `memory` para `image/*`

Subtarefas:

- filtrar fallback automatico por MIME valido;
- filtrar assets manuais tambem por MIME valido;
- contar assets rejeitados como `unsupported_asset_count`;
- impedir entrada de `video/*` no deck final.
- aplicar a mesma regra de MIME valido ao `puzzle`, mas mantendo pack minimo de `1` asset.

### T2.2 - Exigir minimo real de imagens por `pairsCount`

Subtarefas:

- `pairsCount = 6` exige `6` imagens validas;
- `pairsCount = 8` exige `8` imagens validas;
- `pairsCount = 10` exige `10` imagens validas;
- sem minimo real, jogo nao fica `launchable` nem `bootable`.

### T2.3 - Montar `runtime_asset_pack` final da sessao

Subtarefas:

- entregar apenas o subconjunto realmente usado no runtime;
- manter ordenacao consistente;
- eleger `cover_asset`;
- estimar peso de carga;
- evitar que o payload despeje tudo que existe no evento.

### T2.4 - Garantir coerencia entre `show`, `start` e `bootPayload`

Subtarefas:

- usar a mesma logica de asset pack nos endpoints publicos;
- garantir que o asset pack de sessao nao contradiga a prontidao exposta no `show`;
- evitar que o backend diga `bootable` e entregue runtime incompativel.

## TDD obrigatorio da fase

### Backend - escrever primeiro

- `apps/api/tests/Unit/Play/GameAssetResolverServiceTest.php`
  - `memory` ignora `video/mp4`;
  - `memory` mantem apenas imagens validas;
  - `puzzle` devolve somente o asset necessario;
  - `puzzle` ignora `video/mp4` quando a regra de prontidao entrar;
  - fallback respeita ordenacao e limite.
- `apps/api/tests/Feature/Play/PublicPlayReadinessTest.php`
  - `show` do `memory` nao expoe `video/*` em `runtime.assets`;
  - `show` do `puzzle` nao expoe `video/*` em `runtime.assets`;
  - `start session` usa asset pack coerente com o `show`.

## Criterios de aceite

- nenhum `video/*` entra no runtime do `memory`;
- `runtime.assets` passa a representar deck valido, nao lixo misto;
- `reason` de indisponibilidade bate com contagem real de assets.

---

## Fase 3 - Gate correto entre `session`, `preload` e `ready`

## Objetivo

Parar de tratar `gameDefinition.boot(...)` como `runtime pronto` e formalizar o ciclo real do jogo no frontend.

## Arquivos sugeridos

```text
apps/web/src/modules/play/phaser/core/runtimeTypes.ts
apps/web/src/modules/play/phaser/core/BaseGameBridge.ts
apps/web/src/modules/play/phaser/core/bootGame.ts
apps/web/src/modules/play/phaser/core/bootGame.test.ts
apps/web/src/modules/play/hooks/usePhaserGame.ts
apps/web/src/modules/play/pages/PublicGamePage.tsx
apps/web/src/modules/play/types/index.ts
apps/web/src/lib/api-types.ts
```

## Tarefas

### T3.1 - Expandir o contrato do bridge React <-> Phaser

Subtarefas:

- adicionar callbacks:
  - `onBootStarted`
  - `onPreloadProgress`
  - `onBootReady`
  - `onBootFailed`
  - `onGameStarted`
  - `onGameEnded`
- manter `onMove` e `onFinish` sem regressao.

### T3.2 - Criar maquina de estados explicita em `usePhaserGame`

Subtarefas:

- sair de `idle` para `preloading`, nao para `ready`;
- marcar `ready` apenas no `onBootReady`;
- capturar falhas de boot e preload em `error`;
- manter `game` e cleanup coerentes durante troca de sessao.

### T3.3 - Atualizar `PublicGamePage`

Subtarefas:

- nao permitir boot se `bootable = false`;
- mostrar estado `checking` antes do start;
- mostrar estado `starting-session` enquanto a sessao abre;
- mostrar `preloading` enquanto o runtime carrega assets;
- parar de exibir toast do tipo "ja esta pronto" no `start`;
- diferenciar erro de indisponibilidade de erro de preload.

### T3.4 - Garantir coerencia dos tipos TS

Subtarefas:

- refletir `readiness` em `ApiEnvelope` do `Play`;
- refletir novos estados de runtime;
- manter compatibilidade dos tipos usados pelo manager e pelo publico.

## TDD obrigatorio da fase

### Frontend - escrever primeiro

- `apps/web/src/modules/play/hooks/usePhaserGame.test.ts`
  - nao marca `ready` logo apos `boot`;
  - marca `ready` apenas em `onBootReady`;
  - vai para `error` em `onBootFailed`;
  - faz cleanup ao desmontar ou trocar payload.
- `apps/web/src/modules/play/phaser/core/bootGame.test.ts`
  - mantem `Scale.FIT`;
  - mantem `CENTER_BOTH`;
  - mantem `activePointers = 1`;
  - continua iniciando a Scene via `postBoot`.
- `apps/web/src/modules/play/pages/PublicGamePage.runtime.test.tsx`
  - jogo nao inicia se `bootable=false`;
  - `start session` entra em `starting-session`;
  - preload exibe progresso real;
  - indisponibilidade de assets mostra mensagem clara.

## Criterios de aceite

- `session criada` e `runtime pronto` passam a ser estados diferentes;
- preload passa a ser visivel e verificavel;
- erro de asset nao vira mais tela em branco silenciosa.

---

## Fase 4 - Blindagem do `memory` e cleanup serio do runtime

## Objetivo

Tornar o runtime `memory` defensivo contra assets invalidos e estabilizar o ciclo de montagem e desmontagem do Phaser.

## Arquivos sugeridos

```text
apps/web/src/modules/play/phaser/memory/MemoryScene.ts
apps/web/src/modules/play/phaser/memory/MemoryGame.ts
apps/web/src/modules/play/phaser/core/cleanup.ts
apps/web/src/modules/play/phaser/core/BasePlayScene.ts
apps/web/src/modules/play/hooks/usePhaserGame.ts
```

## Tarefas

### T4.1 - Filtrar `image/*` tambem no frontend

Subtarefas:

- descartar assets com `mimeType` invalido;
- descartar assets sem `url`;
- manter fallback visual claro quando o conjunto final nao fecha.

### T4.2 - Exigir minimo correto no runtime

Subtarefas:

- calcular o total de pares a partir de imagens validas;
- falhar cedo quando o total for menor que `pairsCount`;
- emitir estado de indisponibilidade antes de tentar montar o deck.

### T4.3 - Tratar explicitamente `loaderror`

Subtarefas:

- ouvir eventos de erro do Loader;
- propagar erro para `onBootFailed`;
- impedir que `bridge.ready()` aconteca em preload quebrado.

### T4.4 - Fechar cleanup de texturas e instancias

Subtarefas:

- remover texturas temporarias quando o deck mudar;
- limpar listeners do Loader e da Scene;
- limpar timers e delayed calls;
- respeitar que `game.destroy()` e assincrono por frame;
- nao deixar instancias duplicadas ao trocar sessao ou remount.

## TDD obrigatorio da fase

### Frontend - escrever primeiro

- `apps/web/src/modules/play/phaser/memory/MemoryScene.test.ts`
  - ignora `video/*`;
  - exige minimo real de imagens;
  - nao chama `ready` em preload invalido;
  - mostra mensagem clara quando faltam fotos.
- `apps/web/src/modules/play/phaser/puzzle/PuzzleScene.test.ts`
  - ignora `video/*` como capa;
  - falha cedo quando nao houver imagem valida;
  - nao chama `ready` em cover invalida;
  - mantem `ready` apenas quando o puzzle puder montar o tabuleiro.
- `apps/web/src/modules/play/phaser/core/cleanup.test.ts`
  - remove texturas temporarias;
  - nao deixa lixo ao trocar runtime.

## Criterios de aceite

- `MemoryScene` nao tenta mais carregar video como imagem;
- `bridge.ready()` so acontece em runtime realmente montado;
- remounts e reinicios deixam de acumular listeners ou texturas.

---

## Fase 5 - Cobertura de testes da ponte publica e e2e mobile

## Objetivo

Fechar a maior lacuna de regressao atual: a superficie publica do jogo e a ponte React <-> Phaser.

## Arquivos sugeridos

```text
apps/api/tests/Unit/Play/GameLaunchReadinessServiceTest.php
apps/api/tests/Unit/Play/GameAssetResolverServiceTest.php
apps/api/tests/Feature/Play/PublicPlayReadinessTest.php
apps/web/src/modules/play/hooks/usePhaserGame.test.ts
apps/web/src/modules/play/phaser/memory/MemoryScene.test.ts
apps/web/src/modules/play/pages/PublicGamePage.runtime.test.tsx
apps/web/e2e/public-play-memory-mobile.spec.ts
```

## Tarefas

### T5.1 - Consolidar bateria backend P0

Subtarefas:

- cobrir `launchable` e `bootable`;
- cobrir bloqueio de `start session`;
- cobrir `reason` e contagem de assets;
- rodar suite `Feature/Play` completa ao fechar cada marco backend.

### T5.2 - Consolidar bateria frontend P0 e P1

Subtarefas:

- cobrir `usePhaserGame`;
- cobrir `MemoryScene`;
- cobrir `PublicGamePage` nos estados principais;
- cobrir cleanup e transicao entre sessao e runtime.

### T5.3 - Criar e2e mobile do fluxo publico

Subtarefas:

- abrir rota publica do jogo;
- verificar estado indisponivel quando faltam imagens;
- verificar que o CTA nao inicia runtime em jogo nao bootable;
- preparar fixture futura para jogo bootable;
- validar fluxo `entrar -> preload -> jogo -> fim` quando existir fixture bootable controlada.

## TDD obrigatorio da fase

Regra:

- nenhum refactor estrutural do runtime fecha sem teste automatizado correspondente;
- toda correcao de bug descoberta em producao deve primeiro virar teste de caracterizacao.

## Criterios de aceite

- a regressao principal fica blindada;
- o time deixa de depender de memoria oral sobre o handoff React <-> Phaser;
- existe e2e mobile para o fluxo publico do `memory`.

---

## Fase 6 - Runtime island e jogo mobile de verdade

## Objetivo

Executar o que faz o jogo parecer jogo no celular, e nao pagina com canvas.

## Arquivos sugeridos

```text
apps/web/src/modules/play/pages/PublicGamePage.tsx
apps/web/src/modules/play/components/PublicGameMenuSheet.tsx
apps/web/src/modules/play/components/PublicGameRuntimeHost.tsx
apps/web/src/modules/play/components/PublicGameHero.tsx
apps/web/src/lib/query-client.ts
apps/web/src/modules/play/api/playApi.ts
apps/web/src/modules/play/phaser/core/bootGame.ts
apps/web/src/modules/play/phaser/memory/MemoryScene.ts
```

## Tarefas

### T6.1 - Isolar melhor o subtree do canvas

Subtarefas:

- extrair host dedicado do runtime;
- mover ranking, analytics e menu para fora do host do canvas;
- reduzir re-render no subtree que segura o Phaser.

### T6.2 - Fazer a shell publica parecer jogo

Subtarefas:

- deixar apenas uma acao principal antes da primeira partida;
- manter apelido como opcional e pulavel;
- esconder ranking, historico e analytics antes da primeira partida terminada;
- manter CTA unico por estado:
  - `Jogar agora`
  - `Continuar partida`
  - `Jogar novamente`
- reduzir texto tecnico fora do menu;
- remover duplicidade de menu.

### T6.3 - Ajustar escala e input mobile como parte do jogo

Subtarefas:

- revisar estrategia de `Scale Manager` no host atual;
- validar se `FIT` cobre bem o caso atual ou se o container precisa ajuste adicional;
- manter area util estavel no mobile;
- padronizar interacao principal em `pointerdown`;
- aumentar alvos de toque e area das cartas;
- garantir que o jogo continue previsivel em mouse, toque e caneta.

### T6.4 - Revisar Query policy da rota publica

Subtarefas:

- tornar manifesto e detalhe do jogo menos agressivos;
- revisar `refetchOnReconnect` enquanto ha partida ativa;
- manter ranking e ultimas partidas fora do caminho critico do canvas;
- evitar que refetch de shell dispute estado com gameplay.

## TDD obrigatorio da fase

### Frontend - escrever primeiro

- `apps/web/src/modules/play/pages/PublicGamePage.shell.test.tsx`
  - canvas nao remonta quando ranking muda;
  - existe uma unica acao principal antes da partida;
  - ranking e analytics ficam fora da entrada inicial;
  - menu continua funcional;
  - indisponibilidade aparece fora do canvas.
- `apps/web/src/modules/play/pages/PublicGamePage.mobile-shell.test.tsx`
  - apelido e pulavel;
  - CTA muda por estado sem duplicidade;
  - sessao retomavel mostra caminho curto de volta ao jogo.

## Criterios de aceite

- shell deixa de disputar com o runtime;
- o jogo fica legivel e acionavel no mobile com uma mao;
- antes da primeira partida existe uma unica acao principal;
- refetch deixa de ameacar fluidez da partida;
- UX melhora sem reabrir o erro estrutural ja resolvido.

---

## Fase 7 - Crescimento, replay e retorno

## Objetivo

Transformar o fim da partida em convite simples para retorno e compartilhamento, sem poluir a entrada do jogo.

## Arquivos sugeridos

```text
apps/web/src/modules/play/pages/PublicGamePage.tsx
apps/web/src/modules/play/components/PublicGameResultSheet.tsx
apps/web/src/modules/play/components/PublicGameShareActions.tsx
apps/web/src/modules/play/hooks/useInstallPwaPrompt.ts
apps/web/src/modules/play/components/PwaInstallBanner.tsx
```

## Tarefas

### T7.1 - Criar resultado curto e compartilhavel

Subtarefas:

- mostrar score, tempo e posicao de forma curta;
- expor `Jogar de novo` como CTA principal do resultado;
- mover ranking para depois da primeira partida;
- manter historico e analytics como secundarios.

### T7.2 - Implementar compartilhamento nativo com fallback

Subtarefas:

- usar `navigator.share` quando houver suporte;
- compartilhar resultado e link de desafio;
- cair para copiar link e QR quando `share` nao existir;
- garantir funcionamento apenas em contexto suportado sem quebrar a UX.

### T7.3 - Melhorar retorno ao jogo

Subtarefas:

- replay em um toque;
- retomar sessao automaticamente quando ainda estiver valida;
- avaliar melhor momento para PWA/install prompt:
  - nunca antes do primeiro valor entregue;
  - preferencialmente depois de resultado ou segunda interacao forte.

### T7.4 - Tratar ranking como consequencia social

Subtarefas:

- esconder ranking antes da primeira partida;
- mostrar ranking e desafio depois do resultado;
- evitar que leaderboard seja barreira de entrada.

## TDD obrigatorio da fase

### Frontend - escrever primeiro

- `apps/web/src/modules/play/components/PublicGameShareActions.test.tsx`
  - usa `navigator.share` quando houver suporte;
  - cai para copiar link quando nao houver;
- `apps/web/src/modules/play/pages/PublicGamePage.result.test.tsx`
  - resultado aparece ao fim da partida;
  - replay fica em um toque;
  - ranking so ganha protagonismo depois da primeira partida.

## Criterios de aceite

- o fim da partida vira ponto de retorno e convite;
- compartilhar nao bloqueia quem nao tem suporte nativo;
- replay e ranking social passam a ampliar engajamento, nao travar a entrada.

---

## Fase 8 - Admin readiness e operacao

## Objetivo

Dar visibilidade operacional para que o problema seja detectado antes de chegar ao publico.

## Arquivos sugeridos

```text
apps/api/app/Modules/Play/Http/Controllers/EventPlayController.php
apps/api/app/Modules/Play/Http/Resources/EventPlayManagerResource.php
apps/web/src/modules/play/pages/EventPlayManagerPage.tsx
apps/web/src/modules/play/components/EventPlayGameCard.tsx
```

## Tarefas

### T8.1 - Expor prontidao no manager administrativo

Subtarefas:

- mostrar `launchable` e `bootable`;
- mostrar `reason`;
- mostrar `required_asset_count`, `ready_asset_count` e `unsupported_asset_count`.

### T8.2 - Melhorar feedback operacional

Subtarefas:

- exibir imagens validas vs videos ignorados;
- exibir `faltam fotos` antes de ativar ou publicar;
- orientar o operador sobre quantidade minima por `pairsCount`.

## TDD obrigatorio da fase

### Backend e frontend - escrever primeiro

- feature test do manager com `readiness`;
- teste do card administrativo mostrando estado indisponivel.

## Criterios de aceite

- operacao consegue enxergar o problema antes do publico;
- ativacao de jogo deixa de ser "cego".

---

## Matriz de testes a adicionar

## Backend

Arquivos provaveis:

- `apps/api/tests/Unit/Play/GameLaunchReadinessServiceTest.php`
- `apps/api/tests/Unit/Play/GameAssetResolverServiceTest.php`
- `apps/api/tests/Feature/Play/PublicPlayReadinessTest.php`
- `apps/api/tests/Feature/Play/PublicPlayFlowTest.php`
- `apps/api/tests/Feature/Play/PublicPlayPuzzleCharacterizationTest.php`

## Frontend

Arquivos provaveis:

- `apps/web/src/modules/play/hooks/usePhaserGame.test.ts`
- `apps/web/src/modules/play/phaser/core/bootGame.test.ts`
- `apps/web/src/modules/play/phaser/memory/MemoryScene.test.ts`
- `apps/web/src/modules/play/phaser/puzzle/PuzzleScene.test.ts`
- `apps/web/src/modules/play/phaser/core/cleanup.test.ts`
- `apps/web/src/modules/play/pages/PublicGamePage.runtime.test.tsx`
- `apps/web/src/modules/play/pages/PublicGamePage.shell.test.tsx`
- `apps/web/src/modules/play/pages/PublicGamePage.mobile-shell.test.tsx`
- `apps/web/src/modules/play/pages/PublicGamePage.result.test.tsx`
- `apps/web/src/modules/play/components/PublicGameShareActions.test.tsx`
- `apps/web/e2e/public-play-memory-mobile.spec.ts`

---

## Comandos obrigatorios por marco

## Marco A - prontidao e assets no backend

```bash
cd apps/api
php artisan test tests/Unit/Play/GameLaunchReadinessServiceTest.php tests/Unit/Play/GameAssetResolverServiceTest.php tests/Feature/Play/PublicPlayReadinessTest.php
php artisan test tests/Feature/Play
```

## Marco B - gate `session -> preload -> ready`

```bash
cd apps/web
npm run test -- src/modules/play/hooks/usePhaserGame.test.tsx src/modules/play/phaser/core/bootGame.test.ts src/modules/play/phaser/memory/MemoryScene.test.ts src/modules/play/phaser/puzzle/PuzzleScene.test.ts src/modules/play/pages/PublicGamePage.runtime.test.tsx
npm run type-check
```

## Marco C - runtime island e shell

```bash
cd apps/web
npm run test -- src/modules/play/pages/PublicGamePage.runtime.test.tsx src/modules/play/pages/PublicGamePage.shell.test.tsx src/modules/play/hooks/usePhaserGame.test.ts
npm run type-check
```

## Marco D - integracao completa do modulo `Play`

```bash
cd apps/api
php artisan test tests/Feature/Play

cd ../web
npm run test -- src/modules/play
npm run type-check
```

## Marco E - e2e publico mobile

```bash
cd apps/web
npx playwright test e2e/public-play-memory-mobile.spec.ts
```

## Marco F - resultado, replay e share

```bash
cd apps/web
npm run test -- src/modules/play/pages/PublicGamePage.result.test.tsx src/modules/play/components/PublicGameShareActions.test.tsx src/modules/play/pages/PublicGamePage.mobile-shell.test.tsx
npm run type-check
```

---

## Definicao de pronto da entrega estrutural

Esta entrega pode ser considerada pronta quando:

- o backend expoe `published`, `launchable`, `bootable` e `reason`;
- o `memory` recebe apenas `image/*` no runtime;
- `start session` bloqueia jogo nao bootable;
- `usePhaserGame` espera `onBootReady`;
- `MemoryScene` ignora assets invalidos e falha cedo com mensagem clara;
- o estado `preloading` existe de forma real;
- o frontend publico nao inicia runtime quando o jogo esta indisponivel;
- a bateria backend e frontend P0 fica verde;
- existe e2e mobile do fluxo publico.

## Definicao de pronto da camada de produto

Esta camada pode ser considerada pronta quando:

- antes da primeira partida existe uma unica acao principal;
- apelido continua opcional e pulavel;
- ranking e historico nao poluem a entrada inicial;
- preloading aparece com progresso real;
- o resultado mostra score e tempo de forma curta;
- `Jogar de novo` funciona em um toque;
- compartilhar usa `navigator.share` quando houver suporte;
- copiar link ou QR cobre os navegadores sem suporte;
- install prompt e PWA entram depois do valor entregue, nao antes.

---

## Fora do escopo do P0

- trocar CTA como primeira acao da entrega;
- mexer em layout antes de fechar prontidao e lifecycle;
- usar `StrictMode` como "correcao" de runtime;
- memoizar em massa o modulo `play`;
- fazer otimizacao de split como se fosse causa raiz;
- touch, safe area e vibrate antes de o jogo estar realmente bootable.

---

## Proximo passo imediato

Abrir a implementacao pela `Fase 1`, em TDD, com este pacote minimo e irredutivel:

1. `GameLaunchReadinessService`;
2. filtro `memory -> image/*`;
3. bloqueio de `start session` sem minimo real;
4. `usePhaserGame` esperando `onBootReady`;
5. `MemoryScene` defensivo contra asset invalido;
6. testes backend e frontend desse fluxo antes de qualquer limpeza visual.
