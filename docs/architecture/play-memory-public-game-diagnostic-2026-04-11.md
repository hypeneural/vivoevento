# Diagnostico do Jogo Publico Memory - 2026-04-11

## Objetivo

Documentar o estado atual do modulo `Play`, com foco no jogo publico:

- evento: `validacao-videos-whatsapp-2026-04-10`
- jogo: `casamento-do-anderson`
- url: `https://admin.eventovivo.com.br/e/validacao-videos-whatsapp-2026-04-10/play/casamento-do-anderson`

O objetivo desta analise e consolidar:

- stack atual do modulo;
- fluxo tecnico de frontend e backend;
- estado real do jogo em producao em `2026-04-11`;
- causa provavel da tela em branco/partida sem renderizacao;
- gaps de produto, UX e regra de negocio;
- backlog recomendado para o proximo ciclo.

---

## Stack Atual do Modulo Play

### Backend

- Laravel 12
- PHP 8.3
- PostgreSQL
- Redis
- broadcasting com Reverb/Pusher compativel

### Frontend

- React 18
- TypeScript
- Vite 5
- TailwindCSS 3
- shadcn/ui
- Phaser para o runtime dos jogos
- TanStack Query para consumo da API publica
- React Hook Form + Zod para entrada do apelido/sessao

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
  concentra ranking, historico, detalhes e PWA no menu lateral/inferior.

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
5. O runtime do memory faz preload de todos os assets recebidos e tenta renderizar o tabuleiro.

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

Isso significa que o jogo da memoria esta sendo bootado com um conjunto de assets incompatível com a logica do runtime.

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

## 1. O backend entrega videos para o runtime do memory

O fallback atual em `GameAssetResolverService` busca qualquer midia `approved + published` e nao filtra por compatibilidade com o tipo do jogo.

Trechos relevantes:

- `GameAssetResolverService.php:61-70`
  usa fallback automatico com todas as midias publicadas/aprovadas.
- `GameAssetResolverService.php:110-123`
  devolve `mimeType` no payload, mas nao usa isso para bloquear assets invalidos.
- `GameAssetResolverService.php:192-194`
  o `memory` usa `pairsCount` como limite alvo, mas nao garante quantidade minima real de imagens.

Consequencia:

- o jogo `memory` recebe videos no payload;
- o frontend tenta tratar esses assets como cartas do memory.

## 2. O runtime do memory tenta carregar todo asset como imagem

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

## 3. A regra minima de prontidao do jogo nao existe no backend

O `start` publico da sessao hoje apenas resolve o jogo ativo e cria a sessao:

- `PublicPlaySessionController.php:27-43`

Hoje nao existe validacao do tipo:

- `memory` precisa de pelo menos `pairsCount` imagens validas;
- `puzzle` precisa de pelo menos `1` imagem valida;
- jogo sem prontidao nao deve aparecer como jogavel;
- jogo sem prontidao nao deve abrir sessao.

Consequencia:

- o backend aprova um jogo que ainda nao esta pronto para ser jogado.

## 4. O manifesto publico lista jogos ativos, nao jogos prontos

Em `PublicPlayController.php:29-32`, a listagem publica filtra apenas `is_active`.

Hoje nao existe diferenca entre:

- jogo ativo administrativamente;
- jogo publicavel;
- jogo efetivamente launchable.

Consequencia:

- o frontend mostra jogo disponivel mesmo quando ele nao atende o minimo operacional.

## 5. O frontend marca o runtime como pronto cedo demais

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
- a UX parece "quebrada", mesmo com sessao iniciada.

## 6. O frontend expoe dados demais fora do menu

Em `PublicGamePage.tsx`, a tela principal hoje mantem fora do menu:

- cabeçalho tecnico de sessao/realtime;
- apelido;
- CTA;
- chips de fotos/jogadas/retomada;
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
4. o runtime do memory tenta carregar tudo como imagem.

Em termos de produto, o jogo nao estava pronto para ser jogado, mas o sistema o apresentou como pronto.

---

## O Que Precisamos Melhorar

## Prioridade 1 - Regra de negocio

### 1. Criar conceito de prontidao do jogo

O backend precisa calcular algo como:

- `launchable: boolean`
- `required_asset_count`
- `ready_asset_count`
- `unsupported_asset_count`
- `unavailable_reason`

Exemplos:

- `memory.not_enough_images`
- `memory.only_video_assets`
- `puzzle.no_image_available`

### 2. Bloquear sessao para jogo nao pronto

`PublicPlaySessionController::start` deve retornar erro de negocio claro quando:

- o jogo estiver ativo, mas nao launchable;
- faltarem fotos suficientes;
- os assets forem incompatíveis com o tipo.

### 3. Manifesto publico deve esconder ou marcar jogo indisponivel

O manifesto publico deve refletir prontidao real:

- opcao A: esconder jogos nao launchable;
- opcao B: mostrar, mas com status indisponivel e motivo.

Para o caso atual, a regra desejada parece ser:

- sem fotos suficientes, nao existe jogo jogavel.

## Prioridade 2 - Assets por tipo de jogo

### 4. Filtrar assets do `memory` para imagem

No backend:

- `memory` deve usar apenas `media_type = image` ou `mimeType image/*`
- videos nao devem entrar no deck.

### 5. Exigir quantidade minima de imagens reais

Para `memory`:

- `pairsCount = 6` exige `6` imagens distintas validas;
- `pairsCount = 8` exige `8` imagens distintas validas;
- `pairsCount = 10` exige `10` imagens distintas validas.

Se o produto quiser fallback elastico, isso deve ser explicito e visivel no admin.
No momento, a direcao mais segura e:

- sem imagens suficientes, nao liberar o jogo.

### 6. Melhorar o manager do admin

No admin do evento, o card do jogo deve mostrar:

- fotos validas para `memory`;
- videos ignorados para `memory`;
- total necessario vs total disponivel;
- status `Pronto para publicar` ou `Faltam fotos`.

## Prioridade 3 - Robustez do runtime

### 7. Filtrar no frontend por `mimeType`

Mesmo com a correcao no backend, o frontend deve ser defensivo:

- `MemoryScene` deve ignorar assets que nao sejam `image/*`.

### 8. Exigir minimo correto no runtime

Hoje o memory so falha se houver menos de `2` assets:

- `MemoryScene.ts:48-54`

Isso esta fraco demais.

O correto e:

- se `pairsCount = 10`, exigir `10` imagens validas antes de montar o deck.

### 9. So marcar runtime como pronto quando a cena estiver pronta

`usePhaserGame` deve manter `loading` ate o `onReady` real da cena.

Se houver falha de preload, o usuario deve ver:

- erro claro;
- CTA de tentar novamente;
- mensagem de ativo indisponivel, se for problema de assets.

## Prioridade 4 - UX publica

### 10. Reduzir a quantidade de informacao fora do menu

Na tela principal, o ideal para o memory e:

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

### 11. Trocar o CTA principal

O CTA deve ser mais direto:

- `Jogar agora`

Quando a sessao ja existir:

- `Continuar partida`
ou
- `Jogar novamente`

`Nova partida` funciona, mas nao comunica tao bem o fluxo inicial.

### 12. Mostrar estado vazio util quando o jogo nao estiver pronto

Exemplo de mensagem:

> Este jogo ainda nao esta pronto para jogar.
> Faltam fotos publicadas suficientes para montar o tabuleiro.

### 13. Evitar duplicidade de menu

Hoje ha dois acessos ao mesmo menu na mesma tela.
Deve permanecer apenas um padrao principal.

---

## Validacao do Caso `casamento-do-anderson`

### Como esta hoje

- tecnicamente publicado e ativo;
- operacionalmente nao pronto para `memory`;
- backend abre sessao mesmo assim;
- frontend tenta bootar runtime com assets incompativeis;
- usuario recebe uma tela confusa, com muita informacao e pouca clareza de erro.

### Diagnostico final

O problema principal nao e "falta de botao".
O problema principal e:

- o sistema considera o jogo launchable quando ele nao deveria ser.

O problema de UX existe, mas ele agrava um erro de prontidao/validacao que nasce no backend e explode no frontend.

---

## Backlog Recomendado

## Sprint 1 - estabilizacao funcional

1. filtrar assets do `memory` para imagem no backend;
2. validar `required_asset_count` antes de expor o jogo publicamente;
3. bloquear `start session` quando faltarem imagens suficientes;
4. filtrar assets invalidos tambem no frontend;
5. corrigir `usePhaserGame` para respeitar `onReady` real.

## Sprint 2 - UX publica

1. simplificar a tela principal;
2. trocar CTA para `Jogar agora`;
3. mover chips e metricas nao essenciais para o menu;
4. criar estado vazio claro para jogo indisponivel;
5. remover duplicidade do menu.

## Sprint 3 - UX de operacao/admin

1. mostrar prontidao do jogo no manager;
2. destacar imagens validas vs videos ignorados;
3. permitir selecao manual obrigatoria de fotos para `memory`, se desejado;
4. exibir motivo de bloqueio antes de ativar/publicar.

---

## Arquivos Mais Provaveis de Mudanca

### Backend

- `apps/api/app/Modules/Play/Services/GameAssetResolverService.php`
- `apps/api/app/Modules/Play/Http/Controllers/PublicPlayController.php`
- `apps/api/app/Modules/Play/Http/Controllers/PublicPlayGameController.php`
- `apps/api/app/Modules/Play/Http/Controllers/PublicPlaySessionController.php`
- possivelmente um novo service de `GameLaunchReadinessService`

### Frontend

- `apps/web/src/modules/play/pages/PublicGamePage.tsx`
- `apps/web/src/modules/play/hooks/usePhaserGame.ts`
- `apps/web/src/modules/play/phaser/memory/MemoryScene.ts`
- `apps/web/src/modules/play/components/PublicGameMenuSheet.tsx`
- possivelmente um novo componente de `GameUnavailableState`

---

## Conclusao

O modulo `Play` ja tem uma base boa:

- sessao publica;
- ranking;
- analytics;
- resume;
- realtime;
- runtime Phaser separado por tipo de jogo.

Mas o caso real do jogo `casamento-do-anderson` mostra que ainda falta uma camada essencial:

- validacao de prontidao real do jogo antes da exposicao publica.

Sem essa camada, o sistema ativa jogos que ainda nao possuem insumos validos para o tipo escolhido, e a UX publica acaba escondendo um problema de negocio com sintomas de frontend.
