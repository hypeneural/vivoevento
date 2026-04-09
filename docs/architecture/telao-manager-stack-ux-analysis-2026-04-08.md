# Gerenciamento do Telao Ao Vivo: Stack Atual, Realtime e Proposta de UX

Data da analise: 2026-04-08

## Objetivo

Documentar, com base no codigo real do `eventovivo`, como funciona hoje a pagina administrativa do telao em:

- `apps/web/src/modules/wall/pages/EventWallManagerPage.tsx`
- rota SPA: `/events/:id/wall`
- exemplo operacional citado pelo usuario: `/events/31/wall`

O foco aqui e responder:

1. qual e a stack atual do manager do telao;
2. como os dados da pagina chegam do backend;
3. como funciona o realtime, incluindo conexoes de players;
4. como sao calculados os KPIs e a previsao das proximas exibicoes;
5. o que significam os termos hoje opacos para o operador;
6. quais sao os principais problemas atuais de UX/UI;
7. qual e a melhor estrategia de evolucao usando a stack atual, sem trocar a base tecnologica.

## Escopo real analisado

### Frontend

- `apps/web/src/modules/wall/WallPage.tsx`
- `apps/web/src/modules/wall/pages/EventWallManagerPage.tsx`
- `apps/web/src/modules/wall/hooks/useWallManagerRealtime.ts`
- `apps/web/src/modules/wall/hooks/useWallRealtimeSync.ts`
- `apps/web/src/modules/wall/hooks/useWallPollingFallback.ts`
- `apps/web/src/modules/wall/api.ts`
- `apps/web/src/modules/wall/manager-config.ts`
- `apps/web/src/modules/wall/wall-settings.ts`
- `apps/web/src/modules/wall/player/*`

### Backend

- `apps/api/app/Modules/Wall/routes/api.php`
- `apps/api/app/Modules/Wall/Http/Controllers/EventWallController.php`
- `apps/api/app/Modules/Wall/Http/Controllers/PublicWallController.php`
- `apps/api/app/Modules/Wall/Http/Controllers/EventWallAdController.php`
- `apps/api/app/Modules/Wall/Services/WallDiagnosticsService.php`
- `apps/api/app/Modules/Wall/Services/WallSimulationService.php`
- `apps/api/app/Modules/Wall/Services/WallBroadcasterService.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`
- `apps/api/app/Modules/Wall/Services/WallRuntimeMediaService.php`
- `apps/api/app/Modules/Wall/Actions/*`
- `apps/api/routes/channels.php`

### Documentos relacionados

- `docs/architecture/wall-websocket-live-sync-analysis.md`
- `docs/architecture/wall-remote-control-pwa-analysis.md`
- `docs/architecture/telao-ao-vivo-melhorias.md`
- `docs/architecture/telao-ao-vivo-implementation.md`
- `docs/architecture/telao-cockpit-sprint-implementation-plan-2026-04-08.md`
- `docs/architecture/wall-improvements-validation-2026-04-08.md`

## Resumo executivo

Leitura curta:

- a pagina atual do manager do telao ja saiu do estado inicial de "inspector infinito" e entrou na primeira fase real de cockpit;
- a implementacao agora separa melhor operacao critica, diagnostico tecnico, previsao de fila e configuracao por contexto;
- o manager agora usa WebSocket para invalidar queries e fallback de polling leve quando o canal privado cai;
- o player publico usa um modelo hibrido: boot HTTP + WebSocket + heartbeat HTTP + resync HTTP periodico;
- o palco agora ja tem uma previa do rascunho sem `iframe`, reaproveitando renderer e overlays reais do player;
- o palco agora tambem ja consome um `live snapshot` real do wall para mostrar o item atual quando nao ha selecao manual no manager;
- o manager agora tambem recebe um evento dedicado `wall.runtime.snapshot.updated`, reduzindo a dependencia de refetch para atualizar o monitor live;
- a lista "Ordem mais provavel das proximas 12 exibicoes" e uma simulacao com a fila real + draft atual, nao um espelho autoritativo do que esta passando na TV;
- "Tela d7e1d73a...cfd4" nao e o codigo do telao; e o `player_instance_id` do navegador/player;
- `R2 | L0 | E0 | S0` significa `ready`, `loading`, `error`, `stale`;
- o topo do workspace ja ganhou um trilho vivo com selecao persistida, detalhe responsivo e sem autoplay;
- a barra principal ja funciona como toolbar com navegacao por setas;
- o palco agora usa ativacao automatica apenas nas tabs pre-carregadas;
- o inspector agora usa ativacao manual para nao trocar de contexto por acidente;
- `Aparencia` e `Anuncios` ja sairam de blocos inline e passaram a componentes proprios do inspector;
- `Proximas fotos` agora funciona como timeline horizontal com scroll, thumb e origem;
- a simulacao agora tambem ja entrega `caption` e `layout_hint`, deixando a leitura da fila mais proxima do palco real;
- o `liveSnapshot` agora tambem pode trazer `nextItem` quando a previsao da fila confirma a proxima midia com confianca;
- o palco agora mostra blocos dedicados de `Agora no telao` e `Proxima no telao`;
- o snapshot, a simulacao e os itens recentes agora expoem semantica final de foto/video com `isVideo`, duracao e `videoPolicyLabel`;
- origem e video agora usam um mapper unico no frontend, evitando regra espalhada entre palco, timeline e detalhe;
- o palco agora mantem placeholders operacionais quando o player ainda nao confirmou a midia atual ou a proxima;
- a timeline prevista agora entra com loading estavel, sem colapsar a area enquanto a previsao recalcula;
- os cards de player no diagnostico agora usam cor por saude operacional, facilitando leitura rapida de online, instabilidade e offline;
- o palco agora exibe um relogio operacional de troca usando `advancedAt` do snapshot e `interval_ms` do wall;
- o diagnostico por player agora abre um detalhe expandido em `Sheet` no desktop e `Drawer` no mobile, com copy mais orientada a operacao;
- para subir muito a usabilidade, o caminho certo nao e colocar mais cards na lateral; e separar a experiencia em:
  - barra operacional fixa;
  - workspace principal com preview/monitor;
  - inspector por abas;
  - diagnostico por painel proprio;
  - acoes perigosas agrupadas e sincronizadas por um unico estado.

## 1. Como a pagina atual esta montada

### Rota e composicao

- `apps/web/src/modules/wall/WallPage.tsx` decide entre `WallHubPage` e `EventWallManagerPage`.
- Quando existe `:id`, a rota cai em `EventWallManagerPage`.
- A pagina ainda concentra muita orquestracao em um unico arquivo React, mas a extracao ja ficou material:
  - `WallHeroStage` saiu para componente proprio;
  - `WallQueueTab` saiu para componente proprio;
  - `WallAppearanceTab` saiu para componente proprio;
  - `WallAdsTab` saiu para componente proprio;
  - `WallPreviewCanvas` saiu para componente proprio;
  - `WallPlayerRuntimeCard` saiu para componente proprio;
  - `WallPlayerDetailsSheet` saiu para componente proprio;
  - `WallAdvanceClock` saiu para componente proprio;
  - `EventWallManagerPage.tsx` esta em `958` linhas nesta rodada.

### Queries da pagina

O manager abre estas queries:

- `getEventDetail(eventId)`
- `getEventWallSettings(eventId)`
- `getWallOptions()`
- `getEventWallInsights(eventId)`
- `getEventWallLiveSnapshot(eventId)`
- `getEventWallDiagnostics(eventId)`
- `getEventWallAds(eventId)`
- `simulateEventWall(eventId, simulationDraft)`

### Mutations da pagina

- salvar configuracoes do wall;
- executar acoes de ciclo de vida;
- enviar comando operacional para players;
- subir anuncio;
- remover anuncio;
- reordenar anuncios.

### Layout atual

O layout principal e:

- coluna esquerda com:
  - pulso do evento;
  - transmissao em tabs:
    - `Ao vivo`
    - `Proximas fotos`
  - diagnostico operacional;
  - acoes avancadas.
- coluna direita fixa de `420px` com:
  - cabecalho de configuracao do telao;
  - abas manuais:
    - `Fila`
    - `Aparencia`
    - `Anuncios`

### Consequencia de UX

Essa organizacao melhorou quatro pontos relevantes, mas ainda deixa gaps importantes:

1. o operador agora entende melhor o fluxo `ver -> entender -> agir`;
2. a coluna lateral deixou de ser um inspector infinito e passou a ter contexto por aba;
3. a preview visual agora esta bem mais fiel porque reaproveita primitives reais do player;
4. a pagina ainda precisa amadurecer `monitor live autoritativo` como proxima fatia.

## 2. Stack atual do manager do telao

### Frontend

- React 18
- TypeScript
- React Router
- TanStack Query v5
- shadcn/ui + Radix UI
- framer-motion
- lucide-react
- `pusher-js`

### Backend

- Laravel 12
- Policies por evento
- endpoints modulares em `Modules/Wall`
- broadcasting via Laravel Broadcasting
- canal publico do player e canal privado do manager
- implementacao compativel com Reverb/Pusher protocol

### Dados compartilhados

- contrato do wall em `packages/shared-types/src/wall.ts`
- `WallSettings`
- `WallDiagnosticsResponse`
- `WallSimulationResponse`
- `WallHeartbeatPayload`
- `WallPlayerCommandPayload`

## 3. O fluxo atual de dados do manager

### Boot da pagina admin

Ao abrir `/events/:id/wall`, a pagina:

1. busca o evento;
2. busca `wall/settings`;
3. busca `wall/options`;
4. busca `wall/insights`;
5. busca `wall/live-snapshot`;
6. busca `wall/diagnostics`;
7. busca `wall/ads`;
8. monta um `draft` local a partir de `settings`;
9. com debounce de `650ms`, envia esse draft para `POST /events/{event}/wall/simulate`;
10. usa a resposta para preencher a previsao da fila e a previa inicial do rascunho.

### Caracteristica importante

O draft fica local ate o operador salvar.

Isso reduz chamadas enquanto o usuario mexe em sliders e toggles, mas gera uma tensao de UX:

- o operador esta vendo "uma previsao do que ficaria";
- e agora ja ganhou uma previa visual bem mais fiel do draft;
- mas ainda nao esta vendo um runtime autoritativo completo do wall aplicando esse draft.

## 4. Realtime atual: WebSocket, heartbeat e polling

## Resposta direta

Hoje o sistema nao e "so WebSocket" nem "so polling".

Ele e hibrido.

### Manager admin

O manager usa:

- canal privado `event.{eventId}.wall`;
- `pusher-js` em `apps/web/src/modules/wall/realtime/pusher.ts`;
- hooks `useWallRealtimeSync` e `useWallPollingFallback`.

O manager escuta estes eventos:

- `wall.settings.updated`
- `wall.status.changed`
- `wall.expired`
- `wall.diagnostics.updated`
- `wall.runtime.snapshot.updated`

Quando chegam `settings`, `status`, `expired` e `diagnostics`, o manager invalida queries do TanStack Query:

- `wall.settings(eventId)`
- `wall.diagnostics(eventId)`
- `wall.insights(eventId)`
- `wall.liveSnapshot(eventId)`
- `events.detail(eventId)`

Quando chega `wall.runtime.snapshot.updated`:

- o manager aplica o payload direto em `wall.liveSnapshot(eventId)`;
- evita esperar refetch para atualizar o palco;
- mantem a invalidacao como fallback nos outros eventos de mudanca estrutural.

### Ponto importante

O manager agora faz polling de fallback apenas quando o canal privado esta degradado.

Regra atual:

- `connected`:
  - sem polling;
- `connecting`:
  - sem polling;
- `disconnected`:
  - polling leve de fallback;
- `offline`:
  - polling leve de fallback.

Intervalos aplicados nesta fase:

- `event.detail`:
  - `30000ms`
- `wall.settings`:
  - `20000ms`
- `wall.insights`:
  - `15000ms`
- `wall.liveSnapshot`:
  - `5000ms`
- `wall.diagnostics`:
  - `10000ms`

Ao reconectar:

- o manager invalida `settings`, `diagnostics`, `insights` e `event.detail`;
- corta o polling de fallback;
- volta ao modo realtime puro.

### Player publico do telao

O player publico usa:

- `GET /public/wall/{wallCode}/boot`
- canal publico `wall.{wallCode}`
- `POST /public/wall/{wallCode}/heartbeat`
- resync periodico chamando `boot` de novo

Nesta fase, o heartbeat tambem passou a alimentar um snapshot dedicado para o manager:

- o backend atualiza `current_item_started_at` por player;
- monta o `liveSnapshot` mais recente;
- e emite `wall.runtime.snapshot.updated` no canal privado do evento.

Nesta primeira fatia do bloco novo:

- o `current_item_started_at` passou a nascer no engine do player quando a midia atual muda;
- o `heartbeat` passou a enviar esse timestamp explicitamente;
- o backend passou a preferir esse valor quando ele vier do player;
- quando o mesmo item continua em tela, o backend nao deixa `advancedAt` regredir para um horario mais antigo.

#### Timings atuais do player

- heartbeat: a cada `20s`
- resync quando conectado: a cada `120s`
- resync quando degradado/desconectado: a cada `20s`

### Conclusao pratica

- atualizacao de conteudo e comandos: WebSocket
- telemetria de cada tela: heartbeat HTTP
- recuperacao de consistencia do player: resync HTTP periodico
- fallback do manager: polling leve condicionado ao estado do canal privado

## 5. Como funcionam as conexoes mostradas no diagnostico

## "Tela d7e1d73a...cfd4"

Esse texto vem de `player_instance_id`.

Ele:

- e gerado no browser com `crypto.randomUUID()` em `apps/web/src/modules/wall/player/heartbeat-storage.ts`;
- e salvo em `localStorage` por `wall_code`;
- identifica uma instancia especifica de player, nao o wall em si.

Em outras palavras:

- `wall_code` identifica o telao logico do evento;
- `player_instance_id` identifica cada navegador/dispositivo conectado naquele wall.

### Como a tela mostra isso

O manager encurta o valor com:

- `primeiros 8 caracteres + ... + ultimos 4`

Por isso o operador enxerga algo como:

- `Tela d7e1d73a...cfd4`

## "Ultimo sinal 00:55:04"

Esse valor vem de `last_heartbeat_at`.

Fluxo:

1. o player manda heartbeat publico;
2. o backend grava ou atualiza `wall_player_runtime_statuses`;
3. `WallDiagnosticsService` agrega isso em `wall_diagnostic_summaries`;
4. o manager mostra `last_seen_at` no resumo e por player.

### Offline atual

Um player e considerado offline se ficar mais de `60s` sem heartbeat.

Esse threshold esta em:

- `WallDiagnosticsService::OFFLINE_AFTER_SECONDS = 60`

## "Sem conexao"

No card por player, esse label nao quer dizer apenas "socket desconectado".

Hoje ele significa `health_status = offline`, que e derivado de:

- heartbeat ausente por mais de 60s

Ja o campo `Conexao` mostra outro dado:

- `connected`
- `reconnecting`
- `disconnected`
- `error`

Ou seja:

- `health_status` e um resumo operacional;
- `connection_status` e o estado do socket reportado pelo player.

### Leitura visual atual do card

Nesta rodada, o card por player ganhou tom visual por saude operacional:

- `healthy`
  - verde
- `degraded`
  - laranja
- `offline`
  - vermelho

Leitura:

- isso nao muda a regra de negocio do diagnostico;
- mas reduz muito o tempo de leitura quando existe mais de uma tela conectada.

## 6. O que significam os KPIs e termos atuais

## 6.1 KPIs do resumo

### Situacao do telao

Vem de `WallDiagnosticsService::classifySummaryHealth`.

Valores:

- `idle`: nenhuma tela conectada
- `healthy`: todas as telas online e sem degradacao
- `degraded`: existe tela offline ou degradada
- `offline`: existem players conhecidos, mas nenhum online

### Telas conectadas

Formato:

- `online_players / total_players`

Detalhe:

- `offline_players`
- `degraded_players`

### Fotos prontas

Nao e contagem de fotos do evento.

E a soma, em todos os players conectados, do status local dos assets do runtime:

- `ready_count`
- `loading_count`
- `error_count`
- `stale_count`

Logo, isso representa "saude da fila no navegador das telas", nao "quantidade de midias publicadas no evento".

### Ultimo sinal

Maior `last_heartbeat_at` dentre os players monitorados.

### Cache local

Resumo da estrategia de cache do browser:

- `cache_hit_rate_avg`
- `cache_usage_bytes_max`
- `cache_quota_bytes_max`
- `cache_stale_fallback_count`

## 6.2 Card por player

### Exibicao

Vem de `runtime_status` enviado pelo player.

Valores possiveis:

- `booting`
- `idle`
- `playing`
- `paused`
- `stopped`
- `expired`
- `error`

### Conexao

Vem do estado do hook `useWallRealtime` no player.

Valores:

- `idle`
- `connecting`
- `connected`
- `reconnecting`
- `disconnected`
- `error`

### Remetente atual

Nesta rodada, o card principal deixou de mostrar o `current_sender_key` cru.

Agora a copy operacional prioriza leitura humana, por exemplo:

- `Convidado via WhatsApp`
- `Convidado via Telegram`
- `Equipe do evento`

Leitura:

- o valor tecnico ainda existe no diagnostico expandido;
- mas o resumo do card agora comunica melhor o que esta em foco.

### Midias carregadas

No card, o resumo operacional agora ficou assim:

- `32 prontas`
- `0 carregando`
- `0 com erro`
- `0 em cache`

Por baixo disso, a semantica continua a mesma:

- `ready` = assets prontos para renderizar
- `loading` = assets em carregamento
- `error` = assets que falharam
- `stale` = asset servido a partir de cache local stale fallback

`stale` continua significando:

- a rede falhou no carregamento direto;
- o player conseguiu exibir uma copia local do cache;
- a tela continuou funcionando, mas usando um fallback menos fresco.

### Aproveitamento do cache

Percentual calculado a partir de:

- `cache_hit_count`
- `cache_miss_count`

### Espaco no navegador

Estimativa do navegador via `navigator.storage.estimate()`:

- uso atual
- quota estimada

### Cache ativo / armazenamento / acertos / falhas / desatualizados

Complementos do card:

- se cache esta habilitado;
- qual storage foi detectado (`indexeddb`, `localstorage`, `cache_api`, etc);
- quantos hits, misses e stale fallbacks ja ocorreram;
- `last_sync_at`;
- `last_fallback_reason`.

### Detalhe expandido do player

Nesta rodada, o diagnostico ganhou um segundo nivel menos tecnico:

- clique explicito no card abre detalhe expandido;
- `Sheet` no desktop;
- `Drawer` no mobile.

O detalhe expandido passou a responder em linguagem operacional:

- `Situacao atual`
- `Conexao agora`
- `Quem esta na tela`
- `Fila pronta`
- `Aproveitamento do cache`
- `Espaco usado no navegador`

Leitura:

- o resumo continua bom para varredura rapida;
- o detalhe expandido agora ajuda a decidir quando intervir;
- os valores tecnicos ainda existem, mas ficaram no contexto certo em vez de disputar atencao no card principal.

## 7. Como funciona "Ordem mais provavel das proximas 12 exibicoes"

## Resposta curta

Esse bloco e simulacao, nao espelho do runtime real.

### Endpoint

- `POST /events/{event}/wall/simulate`

### O que a simulacao usa

- fila real atual do evento;
- `queue_limit`;
- `interval_ms`;
- `selection_mode`;
- `event_phase`;
- `selection_policy`;
- draft local ainda nao salvo.

### O que ela faz

O `WallSimulationService`:

1. carrega as midias publicadas e aprovadas do evento;
2. transforma em itens simulaveis;
3. aplica politica de fairness por remetente;
4. respeita replay budget por item;
5. respeita cooldown por remetente;
6. respeita janela por remetente;
7. tenta evitar remetente repetido quando ha alternativa;
8. tenta evitar duplicate cluster repetido quando ha alternativa;
9. calcula uma sequencia prevista de ate `12` posicoes.

### O que sai no payload atual

Cada item da sequencia hoje traz:

- `position`
- `eta_seconds`
- `item_id`
- `preview_url`
- `sender_name`
- `source_type`
- `caption`
- `layout_hint`
- `sender_key`
- `duplicate_cluster_key`
- `is_featured`
- `is_replay`
- `created_at`

### O que nao sai hoje

Ainda nao vem:

- icone de origem

Leitura atualizada:

- o bloco deixou de ser apenas lista textual;
- a operacao ja consegue ler thumb e origem na previsao;
- a previsao agora tambem consegue mostrar legenda curta e o layout previsto da exibicao;
- a composicao atual do manager usa caixas horizontais com scroll lateral, o que combina melhor com leitura de sequencia;
- ainda falta enriquecer icone de origem no proprio payload e semantica visual mais completa para video e duracao.

## 8. Como funcionam os estados do telao

## Ciclo de vida salvo no backend

O enum `WallStatus` tem:

- `draft`
- `live`
- `paused`
- `stopped`
- `expired`

### `draft`

- telao configurado, mas nao em exibicao publica;
- nao deveria tocar no player;
- usado como estado inicial e pos-reset.

### `live`

- `StartWallAction`;
- `is_enabled = true`;
- player entra em `playing` se houver item renderizavel;
- se nao houver midia pronta, o player fica em `idle`.

### `paused`

- `StopWallAction` com alvo `Paused`;
- `is_enabled` continua `true`;
- o player congela o estado atual;
- se existir `currentItem`, ele mantem a midia em tela com badge de pausa;
- se nao existir item atual, mostra tela de pausa/aguardo.

### `stopped`

- `StopWallAction` com alvo `Stopped`;
- `is_enabled = false`;
- publicamente o payload de status acaba sendo tratado como `disabled`;
- o player sai da exibicao e mostra tela de encerrado/parado.

Importante:

- no backend, `stopped` ainda pode voltar para `live`;
- na UI atual do manager, porem, estado terminal leva o usuario para `Resetar`, nao para `Iniciar`.

Essa diferenca merece revisao.

### `expired`

- `ExpireWallAction`;
- `is_enabled = false`;
- `expires_at` e preenchido;
- o backend emite `wall.expired`;
- os endpoints publicos passam a responder indisponibilidade/`410`.

Na pratica:

- mata o wall atual;
- invalida a exibicao publica atual;
- exige reset para gerar novo `wall_code`.

### `reset`

- gera novo `wall_code`;
- volta para `draft`;
- limpa varios defaults visuais;
- transmite `expired` no codigo antigo para derrubar players anteriores.

## Diferenca pratica entre as acoes

### Pausar

- objetivo: congelar sem desmontar a sessao;
- preview ideal: manter a foto atual congelada com badge de pausa;
- conexoes continuam vivas;
- novas fotos continuam podendo entrar na fila local.

### Resumir

- objetivo: voltar do congelamento;
- preview ideal: continuar a partir da fila atual.

### Parar completamente

- objetivo: encerrar a exibicao agora, sem invalidar necessariamente o wall_code;
- efeito visual atual: tela encerrada/parada;
- nao e um "pause forte"; e uma saida da exibicao.

### Encerrar telao

- objetivo: finalizar o wall atual como recurso publico;
- efeito visual atual: player recebe expiracao e sai definitivamente;
- o link/codigo atual deixa de ser reutilizavel.

## 9. O que esta ruim hoje do ponto de vista de UX/UI

## 9.1 Tudo importante esta espalhado

Hoje o operador precisa percorrer:

- header para `Pausar` ou `Resumir`;
- meio da pagina para acoes avancadas;
- coluna lateral para configuracao;
- rodape sticky para salvar.

Isso divide o foco operacional.

## 9.2 A previa atual ficou melhor, mas ainda nao fecha o problema inteiro

O manager ja saiu do placeholder puro e agora tem uma `Previa do rascunho`.

Mesmo assim, ela ainda tem limites importantes:

- ja reutiliza renderer e overlays reais do player;
- ja convive melhor com o monitor ao vivo porque o palco passou a usar snapshot real e clock estimado de advance;
- ainda nao espelha um clock central autoritativo da exibicao;
- ainda simplifica alguns comportamentos visuais e de temporizacao.

Leitura:

- a direcao tecnica esta correta porque abandonou `iframe`;
- o salto desta rodada foi importante porque o preview deixou de parecer mock solto;
- o proximo passo continua sendo subir de clock estimado para monitor live mais autoritativo e mais centralizado.

## 9.3 A coluna lateral ficou longa demais

O inspector atual acumula:

- politica
- fairness
- slideshow
- layout
- anuncios
- mensagem idle

Tudo em uma coluna estreita.

Isso explica bem a sensacao de pagina "toda concentrada na lateral".

## 9.4 Acoes de alto risco nao estao didaticamente separadas

O operador precisa entender bem a diferenca entre:

- `Pausar`
- `Parar completamente`
- `Encerrar telao`
- `Resetar`

Hoje isso existe no codigo, mas a explicacao visual ainda e fraca.

## 9.5 Os diagnosticos sao tecnicos demais

Exemplos:

- `R/L/E/S`
- `current_sender_key`
- `persistent_storage`
- `cache stale fallback`

Esses termos fazem sentido para engenharia, nao para operacao de evento.

## 9.6 Nao existe monitor real da exibicao atual

Hoje o manager tem:

- simulacao futura;
- diagnostico por player;
- status do wall.

Mas nao tem:

- "o que esta efetivamente na tela agora" com qualidade visual boa;
- "como meu draft vai ficar" com renderer fiel.

## 9.7 O manager esta forte tecnicamente, mas fraco como produto de operacao

A pagina atual foi desenhada muito mais como:

- console tecnico de configuracao

do que como:

- cockpit de operacao de telao ao vivo.

## 10. O melhor desenho de produto para essa pagina

## Recomendacao principal

Separar a pagina em duas camadas claras dentro da mesma rota:

1. `workspace operacional`
2. `inspector de configuracao`

## Principio de hierarquia

O criterio que mais faz sentido para esta pagina, olhando o codigo atual e a dor de UX descrita, e:

- `ver -> entender -> agir`

Traduzindo para a interface:

1. primeiro o operador precisa ver o telao e o que esta acontecendo agora;
2. depois precisa entender rapidamente o estado do wall;
3. so entao deve entrar em ajuste de fila, aparencia, anuncios e diagnostico profundo.

Esse principio importa porque o problema principal hoje nao e falta de recurso.

O problema principal e:

- hierarquia visual fraca;
- semantica muito tecnica;
- competicao entre preview, comando, diagnostico e formulario;
- excesso de informacao no mesmo nivel visual.

### Workspace operacional

Deve concentrar:

- barra fixa de estado e comandos;
- preview/monitor grande;
- proximas exibicoes em formato visual;
- diagnostico resumido;
- conexoes por player.

### Inspector de configuracao

Deve concentrar:

- presets e estrategia da fila;
- visual da exibicao;
- overlays e branding;
- anuncios;
- mensagem idle;
- configuracoes avancadas.

## Quatro zonas fixas

Uma organizacao mais profissional para a pagina, sem trocar a stack, e dividir a experiencia em quatro zonas fixas:

1. `Comando`
   - barra sticky com status, realtime, alteracoes pendentes e acoes principais
2. `Palco`
   - ao vivo, previa do draft e proximas exibicoes
3. `Organizacao`
   - inspector por assunto, nao por ordem historica do formulario
4. `Diagnostico`
   - informacao tecnica separada do fluxo principal

## Layout recomendado

### Desktop

- topo: `WallCommandBar` sticky
- corpo: `ResizablePanelGroup`
  - painel esquerdo maior:
    - preview/monitor
    - timeline das proximas exibicoes
    - KPIs operacionais
  - painel direito:
    - inspector com abas
    - scroll independente

### Mobile

- pagina principal enxuta com:
  - status
  - comandos
  - preview
  - KPIs essenciais
- configuracoes e diagnostico profundo em:
  - `Sheet`
  - `Drawer`

## Estrutura operacional recomendada

### Topo fixo: barra operacional unica

Essa barra deve ser o centro do comando. Hoje isso ainda nao acontece.

Ela deve mostrar sempre:

- status grande do wall:
  - `Ao vivo`
  - `Pausado`
  - `Parado`
  - `Offline`
- badge de realtime:
  - `Conectado`
  - `Reconectando`
  - `Sem sinal`
- resumo curto:
  - telas conectadas
  - ultima atividade
  - fotos carregadas
- acao principal unica:
  - `Iniciar`
  - `Pausar`
  - `Retomar`
- menu secundario:
  - `Parar`
  - `Encerrar`
  - `Resetar`
- chip de alteracoes pendentes:
  - `5 alteracoes nao salvas`

### Centro da pagina: palco principal

O centro precisa ser a regiao dominante.

Ele deve parar de parecer um placeholder de estado e passar a ser:

- area principal do preview/monitor;
- area principal das proximas exibicoes;
- area principal da leitura operacional.

### Rodape tecnico separado

Diagnostico profundo nao deve competir com configuracao.

O ideal aqui e:

- `Diagnostico tecnico` em `Accordion` recolhido por padrao;
- players, cache, storage, heartbeat e manutencao dentro dele;
- detalhe de player abrindo em `Sheet` no desktop e `Drawer` no mobile.

## Wireframe textual recomendado

```txt
HEADER
[Voltar] Evento / Status / Realtime / Codigo do telao

BARRA OPERACIONAL STICKY
[Status] [Socket] [Alteracoes pendentes]
[Iniciar|Pausar|Retomar] [Abrir telao] [Salvar]
[Mais acoes v -> Parar | Encerrar | Resetar | Limpar cache]

WORKSPACE
+-------------------------------+-------------------------------+
| PALCO                         | INSPECTOR                     |
| [Ao vivo] [Previa] [Proximas] | [Visao geral] [Aparencia]    |
|                               | [Ritmo] [Fila] [Anuncios]    |
| Canvas grande do telao        | [Diagnostico]                |
| Foto atual / remetente        |                               |
| Origem / tempo restante       | Scroll independente          |
| Proxima foto                  | Conteudo da aba ativa        |
|                               |                               |
| Strip de KPIs                 | Rodape do inspector          |
| Timeline com thumbnails       | [Descartar] [Salvar]         |
+-------------------------------+-------------------------------+

DIAGNOSTICO TECNICO
[Saude do telao v]
Players conectados / cache / trends / detalhes por player

ZONA DE RISCO
[Parar agora] [Encerrar telao] [Gerar novo codigo]
```

## 11. Preview real e monitor real: a abordagem certa

## Nao usar iframe como solucao principal

Um iframe com `/wall/player/:code` seria:

- outro player;
- com runtime proprio;
- com cache proprio;
- com clock proprio.

Entao:

- nao e espelho perfeito;
- nao e o melhor preview de configuracao;
- nao e o melhor monitor do que a TV remota esta exibindo.

## Melhor abordagem

Criar dois modos no mesmo painel:

### Modo 1. Preview do draft

Objetivo:

- mostrar para o operador como o template vai ficar com as mudancas ainda nao salvas.

Implementacao recomendada:

- extrair um `WallPreviewCanvas` reutilizando renderer do player:
  - `LayoutRenderer`
  - `BrandingOverlay`
  - `FeaturedBadge`
  - `SideThumbnails`
  - `AdOverlay` quando aplicavel
- alimentar esse preview com:
  - settings do draft
  - primeira foto simulada
  - segunda e terceira fotos para transicao curta
- rodar um loop curto de `3s` enquanto o preview estiver visivel.

Status atual desta trilha:

- a extracao inicial do `WallPreviewCanvas` ja entrou no manager;
- o preview agora reaproveita `LayoutRenderer`, `BrandingOverlay`, `FeaturedBadge` e `SideThumbnails`;
- a pendencia principal daqui para frente deixa de ser composicao visual e passa a ser fidelidade temporal e monitor live autoritativo.

Vantagem:

- zero iframe;
- renderer fiel;
- custo controlado;
- sem dependencia de WebSocket para o preview.

### Modo 2. Monitor ao vivo

Objetivo:

- acompanhar o que o wall realmente esta exibindo agora.

Hoje ja existe uma primeira trilha operacional de monitor ao vivo, mas ela ainda nao e autoritativa no nivel de clock central do engine.

O caminho curto ja entrou nesta fase:

- `GET /events/{event}/wall/live-snapshot`
- evento privado `wall.runtime.snapshot.updated`
- `advancedAt` derivado de `current_item_started_at` do player mais recente
- relogio visual no palco calculado por `interval_ms`

Leitura:

- isso ja entrega item atual, origem, remetente e tempo restante estimado;
- melhora muito a leitura operacional no manager;
- nesta primeira fatia, isso ficou melhor porque o `advancedAt` pode nascer do proprio engine do player, e nao apenas do instante em que o heartbeat chegou;
- mas ainda depende do ritmo do heartbeat e do ultimo player reportado.

Para fechar isso de forma mais forte, ainda existem dois caminhos:

#### Caminho curto ja implementado

Payload atual do snapshot:

- `current_item_id`
- `current_item_preview_url`
- `current_sender_name`
- `current_source_type`
- `current_layout`
- `advanced_at`
- `next_item`, apenas quando a simulacao do backend confirma a mesma midia atual como primeira posicao prevista da fila

Hoje esse snapshot e atualizado a partir do player monitorado mais recente.

#### Caminho robusto

Mover a timeline do wall para um relogio mais autoritativo e publicar snapshots centrais.

Isso e um projeto maior.

## Recomendacao objetiva

Para esta fase:

- fazer preview do draft agora;
- tratar monitor ao vivo como segunda trilha;
- nao vender iframe como preview definitivo.

## O que mostrar no monitor ao vivo

Nesta fase, o palco ja mostra a maior parte desta composicao quando existe `liveSnapshot` e nao ha selecao manual.

Composicao minima recomendada:

- imagem atual
- badge de origem:
  - `WhatsApp`
  - `Upload`
  - `Manual`
  - `Galeria`
- nome do remetente
- tempo restante
- proxima foto
- ultima sincronizacao
- estado resumido das telas conectadas

Debaixo disso, faz sentido exibir:

- timeline horizontal com as proximas `5-8` exibicoes;
- thumbnail + ETA + remetente + origem + replay/destaque.

## O que mostrar na previa do draft

Cada alteracao importante precisa devolver feedback imediato:

- mudou `layout` -> a previa muda em menos de `250ms`;
- mudou `transition` -> a previa reapresenta uma troca curta;
- ativou `QR` -> o QR entra na composicao;
- ativou `miniaturas` -> o rail lateral aparece;
- mudou `interval_ms` -> o contador da previa reflete isso.

Objetivo:

- aumentar confianca do operador;
- reduzir sensacao de "salvei e preciso abrir outra tela para ver".

## 12. Como melhorar a lista das proximas 12 exibicoes

Hoje o bloco mostra:

- segundos
- nome do remetente
- reprise

Isso e pouco para operacao.

## O que eu exibiria

Cada item da previsao deveria mostrar:

- thumbnail da foto
- `eta_seconds`
- nome do remetente
- origem da midia
- badge de `reprise`
- badge de `destaque`, se houver

## Backend necessario

Enriquecer `WallSimulationPreviewItem` com:

- `preview_url`
- `source_type`
- `caption`
- `layout_hint` opcional

Observacao:

`WallPayloadFactory::media()` ja conhece `url` e `source_type`.
Nesta fase, `WallSimulationService` ja repassa `preview_url`, `source_type`, `caption` e `layout_hint` no `sequence_preview`.
As proximas pendencias aqui continuam sendo:

- icones de origem mais ricos no proprio payload ou no mapper do frontend;
- semantica visual mais forte para video, duracao e estado de replay;
- ligacao ainda mais direta entre previsao, snapshot ao vivo e detalhe operacional.

## Icones de origem

Com base na stack atual, faz sentido mapear:

- `whatsapp`
- `telegram`
- `upload`
- `manual`
- `gallery`

para icones e badges no frontend.

Isso melhora muito a leitura operacional.

## 13. Componentes recomendados com a stack atual

## O que usar agora, sem trocar stack

### `ResizablePanelGroup`

Uso recomendado:

- workspace com preview a esquerda e inspector a direita;
- ajuste manual de largura sem rebuild de layout.

Motivo:

- encaixa perfeitamente no problema principal da pagina, que hoje comprime tudo numa lateral fixa.

### `Tabs`

Uso recomendado:

- separar o inspector em:
  - `Visao geral`
  - `Aparencia`
  - `Ritmo`
  - `Fila`
  - `Anuncios`
  - `Diagnostico`

Motivo:

- elimina o scroll longo;
- reduz carga cognitiva;
- mantem o usuario em um contexto por vez.

### `Accordion` e `Collapsible`

Uso recomendado:

- esconder detalhes avancados dentro de cada aba;
- deixar presets e configuracoes basicas sempre visiveis;
- mover explicacoes mais longas para secoes colapsaveis.

Motivo:

- o manager atual mostra tudo expandido ao mesmo tempo.

### `ScrollArea`

Uso recomendado:

- scroll independente no inspector;
- scroll independente na lista de players conectados;
- scroll independente em timeline e historico.

Motivo:

- evita que o usuario perca o preview ao navegar no formulario;
- reduz a sensacao de "cliquei e fui parar no fim da pagina".

### `Sheet` e `Drawer`

Uso recomendado:

- abrir configuracao e diagnostico detalhado no mobile;
- exibir acao perigosa com contexto sem sair do fluxo.

Motivo:

- a pagina atual e desktop-first;
- `Drawer` combina muito bem com uso operacional em celular.

### `Chart`

Uso recomendado:

- sparkline de heartbeat;
- barra de composicao `ready/loading/error/stale`;
- evolucao de hit-rate e stale fallback;
- previsao de atraso medio da fila.

Motivo:

- KPIs do wall sao temporais e comparativos;
- apenas cards estaticos escondem tendencia.

### `HoverCard` e `Tooltip`

Uso recomendado:

- explicar termos tecnicos;
- mostrar legenda de `R/L/E/S`;
- detalhar diferenca entre `paused`, `stopped` e `expired`.

### `Skeleton`

Uso recomendado:

- carregar preview, simulacao e diagnostico sem saltos visuais.

### `Button` + `ToggleGroup` + `DropdownMenu`

Uso recomendado:

- montar a toolbar operacional com componentes que ja existem no repo;
- evitar introduzir um `ButtonGroup` externo so para agrupar acoes.

Motivo:

- o projeto ja tem `button.tsx`, `toggle-group.tsx` e `dropdown-menu.tsx`;
- isso resolve bem acao principal, estados simples e menu de risco sem nova dependencia.

## Contrato de interacao recomendado

Para esta tela virar cockpit de verdade, nao basta o contrato de API.

Tambem precisamos de contrato de interacao.

Estado desta rodada:

- toolbar principal implementada com navegacao por setas;
- tabs do palco implementadas com ativacao automatica;
- tabs do inspector implementadas com ativacao manual;
- trilho recente implementado com pausa em hover e foco, navegacao por teclado e manutencao do item selecionado visivel.

### Toolbar de comando

A barra de comando deve ser tratada como toolbar:

- `Tab` entra no grupo;
- setas navegam entre controles;
- acoes criticas continuam com label clara;
- o operador nao precisa atravessar dezenas de tab stops para operar.

### Tabs do palco e do inspector

Regra recomendada:

- ativacao automatica apenas quando o painel estiver pre-carregado e sem latencia perceptivel;
- ativacao manual quando a aba depender de fetch ou render mais pesado.

### Trilha recente

Regra recomendada:

- sem autoplay implicito;
- insercao animada de novos itens sim;
- auto-scroll apenas quando nao houver hover, foco ou item selecionado.

### Hover nao pode ser dependencia critica

Leitura operacional:

- `Tooltip` para legenda curta;
- `HoverCard` para contexto leve;
- `Sheet` ou `Drawer` para detalhe importante.

Nenhuma informacao essencial deve depender apenas de hover.

## Orcamento de animacao recomendado

`AnimatePresence` e `layout` fazem sentido aqui, mas com disciplina.

Usos bons:

- badge `Ao vivo`;
- troca da midia atual;
- destaque do item selecionado;
- entrada de novo item no trilho.

Usos ruins:

- animar todos os KPIs a cada refetch;
- animar listas inteiras;
- animar layout global da pagina.

## Modulos de suporte que passam a fazer sentido

Para reduzir logica espalhada no manager, vale prever:

- `wall-query-options.ts`
- `wall-copy.ts`
- `wall-source-meta.ts`
- `wall-interaction-contract.ts`
- `wall-view-models.ts`

Leitura:

- query keys sozinhas nao bastam;
- a estrategia de cache e refetch merece um modulo proprio;
- copy, origem e contrato de interacao nao devem ficar dissolvidos dentro de `EventWallManagerPage`.

## O que a documentacao oficial reforca

### shadcn/ui

- `Resizable` e apropriado para um editor com workspace e inspector porque a biblioteca ja entrega o padrao de paines redimensionaveis dentro da propria stack usada no projeto.
- `Tabs` e o melhor antidoto para o inspector infinito porque permite trocar de contexto sem scroll longo e sem abrir outra rota.
- `Accordion` e `Collapsible` encaixam bem para separar "operacao basica" de "ajustes avancados" sem esconder o fluxo principal.
- `Scroll Area` e o componente certo para manter preview e inspector independentes, evitando que a pagina inteira salte para o fim em interacoes longas.
- `Sheet` e `Drawer` ja estao na stack e sao mais adequados do que replicar a pagina completa no mobile.
- `Chart` da propria base shadcn usa Recharts por composicao, o que e um bom encaixe para evoluir KPIs sem criar uma abstracao nova so para o wall.
- `Hover Card` e util para contexto leve, mas nao deve virar dependencia de leitura critica.
- `Tooltip` funciona bem para legenda curta, nao para detalhe essencial.
- `Carousel` existe e e forte para snapping e swipe, mas para o trilho operacional desta sprint o `ScrollArea` continua sendo a escolha mais simples e segura.

### TanStack Query

- a documentacao de `query invalidation` reforca exatamente o padrao que o manager ja usa: realtime invalida a query e o Query Client refaz a leitura.
- o gap que existia nesta area nao estava no modelo da lib, e sim na falta de fallback de refetch quando o canal privado caia.
- nesta fase, esse ponto ja foi fechado com `useWallPollingFallback` e com atualizacao direta do `liveSnapshot` via evento dedicado.
- a documentacao de `important defaults` reforca que queries stale podem refetch em mount, foco e reconexao se nao controlarmos isso explicitamente.
- a documentacao de `render optimizations` reforca que `select` pode reduzir o payload entregue aos componentes sem alterar o cache, o que combina muito bem com o topo do cockpit.

### Laravel Reverb e Broadcasting

- a documentacao oficial do Laravel confirma que Reverb e a trilha first-party para WebSocket e que o ecossistema continua compativel com o protocolo estilo Pusher.
- isso valida a decisao atual de manter:
  - canal publico para o player;
  - canal privado para o manager;
  - `pusher-js` no frontend.

### W3C APG: Toolbar e Tabs

- o `Toolbar Pattern` do W3C reforca que agrupar controles reduz tab stops e melhora navegacao por teclado quando existe uma faixa de comando com varias acoes.
- o `Tabs Pattern` reforca o modelo certo para mostrar um painel por vez.
- a mesma referencia recomenda ativacao automatica de tabs apenas quando o painel aparece sem latencia perceptivel.

Leitura para o wall:

- toolbar sticky faz sentido como padrao acessivel, nao apenas visual;
- tabs no palco e no inspector fazem sentido desde que a aba ativa responda rapido;
- se alguma aba depender de fetch pesado, vale manter ativacao manual.

### W3C APG: Carousel

- se um dia o trilho recente evoluir para carousel com rotacao, a referencia do W3C reforca que precisa existir controle claro de iniciar/parar rotacao.
- para esta sprint, isso reforca a decisao de nao usar autoplay no trilho recente.

### Motion

- `AnimatePresence` e apropriado para o palco porque a documentacao oficial descreve exatamente o caso de troca por `key` unica, que encaixa muito bem em slideshow e troca de item atual.
- a documentacao de layout animations reforca que `layout` e `layoutId` servem bem para shared element transitions pequenas, o que combina com destaque entre trilho, palco e detalhe.

### Lucide React

- a documentacao oficial do `lucide-react` reforca tres vantagens alinhadas ao manager:
  - componente SVG standalone;
  - bundle tree-shakable;
  - personalizacao por props.

Isso justifica manter Lucide como biblioteca principal de icones do cockpit.

## Componentes internos que vale reaproveitar

- `StatsCard` em `apps/web/src/shared/components/StatsCard.tsx`
- padrao `SectionCard` do `HubPage`
- `WallManagerSection`
- `HelpTooltip`

## 14. Reorganizacao recomendada da pagina

## Estrutura de componentes sugerida

```txt
apps/web/src/modules/wall/
  pages/
    EventWallManagerPage.tsx
  components/manager/
    layout/
      WallManagerShell.tsx
      WallHeaderBar.tsx
      WallCommandToolbar.tsx
      WallWorkspace.tsx
      WallDiagnosticsAccordion.tsx
      WallDangerZone.tsx
    stage/
      WallHeroStage.tsx
      WallStageTabs.tsx
      WallLiveMonitorCard.tsx
      WallDraftPreviewCard.tsx
      WallUpcomingTimeline.tsx
      WallSummaryKpiStrip.tsx
      WallLiveBadge.tsx
    top/
      WallTopInsightsRail.tsx
      WallTopContributorCard.tsx
      WallTotalMediaCard.tsx
      WallLiveMediaTimelineStrip.tsx
      WallRecentMediaChip.tsx
    inspector/
      WallInspectorTabs.tsx
      WallOverviewTab.tsx
      WallAppearanceTab.tsx
      WallPlaybackTab.tsx
      WallQueueRulesTab.tsx
      WallAdsTab.tsx
      WallIdleStateTab.tsx
      WallDiagnosticsTab.tsx
    diagnostics/
      WallPlayersList.tsx
      WallPlayerRowCard.tsx
      WallPlayerDetailsSheet.tsx
      WallHeartbeatMiniChart.tsx
      WallCacheHealthMiniChart.tsx
    recent/
      WallRecentMediaSection.tsx
      WallRecentMediaTimeline.tsx
      WallRecentMediaDetailsSheet.tsx
    hooks/
      useWallDraftState.ts
      useWallLiveSnapshot.ts
      useWallPollingFallback.ts
      useWallTopInsights.ts
      useWallRecentMediaTimeline.ts
      useWallRealtimeSync.ts
      useWallSelectedMedia.ts
```

## Nova hierarquia visual

### Linha 1. Barra operacional fixa

Deve mostrar:

- status do wall
- estado do realtime
- botao principal `Iniciar`, `Pausar` ou `Resumir`
- acoes destrutivas em menu ou grupo destacado
- aviso de alteracoes pendentes

### Linha 2. Workspace

Deve mostrar:

- trilho vivo de insights no topo do workspace;
- preview draft e monitor live em tabs
- timeline das proximas exibicoes
- KPIs resumidos

### Linha 3. Diagnostico tecnico

Deve mostrar:

- players conectados
- manutencao
- cache
- storage

Mas fora do fluxo principal de edicao.

### Linha 4. Inspector

Deve mostrar:

- configuracoes por dominio, nao por ordem historica de implementacao.

## Faixa viva acima do palco

Sem mudar a direcao geral da pagina, existe um incremento que vale muito agora:

- transformar o topo do workspace em um `TopInsightsRail`;
- deixar a barra sticky como comando;
- e usar essa nova faixa como leitura viva do evento.

### Estrutura recomendada

```txt
TopInsightsRail
- WallTopContributorCard
- WallTotalMediaCard
- WallLiveMediaTimelineStrip
```

### Ordem recomendada

1. `Quem mais enviou`
2. `Total de midias`
3. `Ultimas chegadas`

Essa ordem conta melhor a historia do evento:

- quem lidera;
- quanto entrou;
- o que acabou de chegar agora.

### Bloco `Quem mais enviou`

Hoje o KPI equivalente existe, mas ainda esta magro para operacao.

Vale mostrar:

- nome amigavel ou fallback curto;
- total de midias;
- icone do canal;
- `ultima ha 2 min`;
- medalha apenas se realmente for lider da janela atual.

### Bloco `Total de midias`

Esse card deveria deixar de ser um total seco.

Formato recomendado:

- `124 recebidas`
- `97 aprovadas`
- `14 em fila`
- `53 exibidas`

Mesmo compactado em duas linhas, isso entrega muito mais confianca do que um unico numero.

### Bloco `Ultimas chegadas`

Esse bloco e o que mais ajuda o topo a respirar como painel vivo.

Em vez de depender apenas de uma lista longa mais abaixo, a faixa superior deve mostrar:

- `6-10` miniaturas compactas;
- nome curto;
- icone do canal;
- horario relativo;
- badge `Agora` quando acabou de entrar.

Clique e hover devem conectar essa faixa ao palco e ao detalhe lateral.

## Abas finais recomendadas

### Aba `Visao geral`

Deve concentrar:

- preview grande
- status do telao
- o que esta passando agora
- proxima foto
- quantidade de telas conectadas
- botao principal de acao

### Aba `Aparencia`

Deve concentrar:

- estilo da exibicao
- animacao de troca
- orientacao aceita
- miniaturas laterais
- QR e branding
- destaque

### Aba `Ritmo`

Deve concentrar:

- tempo por foto
- maximo de midia na fila
- cooldown
- limite por remetente
- evitar repeticao

### Aba `Fila`

Deve concentrar:

- como a fila escolhe
- repeticao curta / media / longa
- prioridade do recente
- equilibrio por pessoa
- evitar repetir o mesmo remetente

### Aba `Anuncios`

Deve concentrar:

- modo dos anuncios
- frequencia
- upload do criativo
- criativos ativos

### Aba `Diagnostico`

Deve concentrar:

- players conectados
- cache
- realtime
- manutencao
- historico curto de falhas

## Timeline horizontal de midias recentes

Se a tela ja vai assumir postura de cockpit, a timeline horizontal precisa virar um componente proprio, nao um detalhe perdido no layout.

### Estrutura visual recomendada

Cada item deve mostrar:

- thumbnail `56x56` ou `64x64`;
- nome curto em uma linha;
- icone do canal sobreposto;
- horario relativo;
- estado visual:
  - normal
  - hover
  - selecionado
  - nova midia
  - erro
  - exibida

### Interacoes recomendadas

- `hover`
  - tooltip com nome completo, origem, horario e status
- `click`
  - seleciona a midia e destaca no palco ou no inspector
- `double click`
  - abre detalhe rapido em `Sheet`
- `keyboard`
  - setas navegam entre itens

### Componente recomendado

- se a intencao for lista densa e continua:
  - `ScrollArea`
- se a intencao for snapping, swipe e teclado com cara mais forte de carrossel:
  - `Carousel`

Leitura pragmatica:

- `ScrollArea` e melhor para feed vivo compacto;
- `Carousel` e melhor para uma timeline mais cenografica, com foco em selecao.

## 15. Melhorias especificas de copy e entendimento

## Trocar rotulos tecnicos por linguagem operacional

### Blocos da pagina

| Atual | Melhor |
|------|--------|
| Estado do telao | Transmissao |
| Diagnostico operacional | Saude do telao |
| Previsao da fila | Proximas fotos |
| Modo do telao | Como a fila escolhe |
| Fila e justica | Regras da fila |
| Ajustes da exibicao | Ritmo da tela |
| Visual e troca de fotos | Aparencia |
| Patrocinadores no telao | Anuncios |
| Mensagem quando nao ha fotos | Tela sem fotos |
| Acoes avancadas | Acoes de risco |
| Top convidado | Quem mais enviou |
| Total capturado | Total de midias |
| Fotos Recentes | Ultimas chegadas |

### KPIs e labels tecnicos

| Atual | Melhor |
|------|--------|
| Tela d7e1d73a...cfd4 | Player 1 |
| Ultimo envio na tela | Remetente atual |
| Fotos R/L/E/S | Prontas / Carregando / Erro / Cache |
| Sem conexao | Offline |
| Com instabilidade | Player degradado |
| Ultimo sinal | Ultima atividade |
| Fotos prontas | Fotos carregadas |

Observacao:

- `ID tecnico`, `player_instance_id`, `persistent_storage` e `current_sender_key` devem continuar existindo, mas em tooltip, sheet de detalhe ou diagnostico expandido.

## Explicar a diferenca entre as acoes

Sugestao de microcopy:

- `Pausar`
  - "Congela a exibicao atual sem encerrar o wall."
- `Resumir`
  - "Retoma a troca de fotos na tela."
- `Parar completamente`
  - "Interrompe a exibicao publica agora. O player sai do modo de apresentacao."
- `Encerrar telao`
  - "Finaliza este wall e invalida o codigo atual."
- `Resetar`
  - "Gera um novo codigo e volta o wall para rascunho."

## 16. Melhorias tecnicas recomendadas no backend

## 16.1 Fallback de polling no manager

Quando `useWallManagerRealtime` estiver:

- `disconnected`
- `offline`

habilitar refetch interval para:

- `wall.byEvent(eventId)`
- `wall.diagnostics(eventId)`
- `wall.insights(eventId)`
- `wall.liveSnapshot(eventId)`

Sugestao:

- `wall.liveSnapshot`: `5s` a `8s`
- `wall.diagnostics`: `10s`
- `wall.insights`: `15s`
- `wall.byEvent`: `20s` ou `30s`

Regra importante:

- quando o socket estiver `connected`, esses polls devem ficar desligados;
- quando reconectar, o manager invalida tudo uma vez e volta ao modo realtime puro.

## 16.2 Payload de monitor ao vivo

Nesta fase, o manager ja ganhou um endpoint dedicado de snapshot ao vivo:

- `GET /events/{event}/wall/live-snapshot`

Payload atual:

- item atual
- remetente atual
- origem
- preview atual
- `layoutHint`
- `advancedAt`
- player mais recente ainda online
- status atual do wall

Evento realtime atual:

- `wall.runtime.snapshot.updated`

Leitura:

- isso ja permite trazer o item real do wall para o palco do manager;
- isso ja permite mostrar um clock operacional de troca no palco;
- ainda nao substitui um monitor autoritativo com clock central de advance;
- o proximo salto aqui deixa de ser "ter snapshot" e passa a ser "ter snapshot mais autoritativo e com semantica temporal mais forte".

## 16.3 Endpoint agregado de insights

Hoje o manager ja conta com um agregado proprio para o topo vivo.

Endpoint atual:

- `GET /events/{event}/wall/insights`

Resposta recomendada:

```ts
interface WallInsightsResponse {
  topContributor: {
    senderKey: string
    displayName: string | null
    maskedContact?: string | null
    source: 'whatsapp' | 'telegram' | 'upload' | 'manual' | 'gallery'
    mediaCount: number
    lastSentAt?: string | null
    avatarUrl?: string | null
  } | null
  totals: {
    received: number
    approved: number
    queued: number
    displayed: number
  }
  recentItems: Array<{
    id: string
    previewUrl: string | null
    senderName: string | null
    senderKey: string
    source: 'whatsapp' | 'telegram' | 'upload' | 'manual' | 'gallery'
    createdAt: string | null
    approvedAt?: string | null
    displayedAt?: string | null
    status: 'received' | 'approved' | 'queued' | 'displayed' | 'error'
    isFeatured?: boolean
    isReplay?: boolean
  }>
  sourceMix: Array<{
    source: 'whatsapp' | 'telegram' | 'upload' | 'manual' | 'gallery'
    count: number
  }>
  lastCaptureAt?: string | null
}
```

### Motivo arquitetural

Esse agregado resolve tres problemas de uma vez:

1. reduz waterfall no primeiro paint;
2. reduz regra inventada no frontend;
3. permite cachear o topo como uma unidade coerente.

### Regras recomendadas para esse payload

- `displayName` deve vir pronto para UI;
- `source` deve vir normalizado;
- `previewUrl` deve vir em versao thumb, nao em imagem cheia;
- `recentItems` deve vir limitado para `10-20` itens, nao para feed infinito;
- o endpoint deve continuar sob `viewWall`.

### Achado validado na implementacao da Etapa 3

Essa duvida ja ficou resolvida no codigo real:

- `totals.displayed` deixou de ser `nullable` no payload usado pelo cockpit.

Motivo:

- o backend agora persiste historico acumulado em `wall_display_counters`;
- a escrita acontece na trilha do `heartbeat`, usando `current_item_id` + `current_item_started_at`;
- a contagem aplica dedupe para reconexao, repeticao do mesmo display e regressao temporal do sinal.

Leitura:

- o agregado `wall/insights` agora entrega um KPI autoritativo de exibidas para o topo;
- a autoridade atual e operacional, derivada do runtime real dos players;
- o proximo salto deixa de ser destravar esse numero e passa a ser enriquecer a semantica final de origem, video e historico de ciclo.

## 16.4 Enriquecer `sequence_preview`

Nesta fase, quatro enriquecimentos ja entraram no contrato real:

- `preview_url`
- `source_type`
- `caption`
- `layout_hint`

Pendencias que continuam valendo para a proxima rodada:

- `sender_display_name`
- `is_video`

## 16.5 Estrategia de cache e first paint

O manager precisa carregar rapido sem virar painel morto.

O caminho certo aqui nao e cachear tudo igual.

O caminho certo e separar os dados por volatilidade.

### Cache por categoria

#### Quase estatico

- `wall/options`
- enums e configuracoes de apoio

Politica recomendada:

- `staleTime` muito alto ou `static`
- `gcTime` alto
- sem polling

#### Estado salvo do wall

- `wall.byEvent(eventId)`
- `event.detail(eventId)`

Politica recomendada:

- `staleTime` moderado
- invalida via realtime quando possivel
- fallback leve quando offline

#### Insights operacionais

- `wall.insights(eventId)`

Politica recomendada:

- `placeholderData: (previousData) => previousData`
- `staleTime` curto
- sem limpar a UI entre refetches
- fallback de polling apenas quando o canal cair

#### Runtime mais vivo

- `wall.liveSnapshot(eventId)`
- `wall.diagnostics(eventId)`

Politica recomendada:

- `staleTime: 0`
- websocket como fonte principal
- polling somente em degradacao

#### Simulacao de draft

- `wall.simulation(eventId, draftHash)`

Politica recomendada:

- continuar com debounce
- manter `placeholderData` do draft anterior para evitar flicker
- `gcTime` menor que o das queries persistentes

### Regras de UX/performance

- evitar spinner no topo quando so uma refetch leve estiver acontecendo;
- manter cards e timeline com `Skeleton` apenas no primeiro paint;
- usar dados anteriores como placeholder durante transicoes de query;
- evitar refetch de background em abas do navegador quando isso nao trouxer ganho operacional real;
- separar bem componentes de server state e de UI state para reduzir rerender desnecessario.

### UI state local recomendado

```ts
interface WallCockpitUiState {
  selectedMediaId: string | null
  hoveredMediaId: string | null
  isRecentStripPaused: boolean
  isRecentDetailsOpen: boolean
  activeStageTab: 'live' | 'preview' | 'upcoming'
  activeInspectorTab: 'overview' | 'appearance' | 'rhythm' | 'queue' | 'ads' | 'diagnostics'
}
```

Regra pratica:

- server state no TanStack Query;
- selecao e interacao no estado local;
- draft separado do estado salvo.

## 16.6 Realtime hibrido sem perder performance

O painel deve operar em modo hibrido:

- realtime quando o canal privado estiver saudavel;
- polling leve somente quando o canal cair;
- invalidacao unica quando reconectar.

### Query keys recomendadas

```ts
export const wallQueryKeys = {
  event: (eventId: number | string) => ['wall', 'event', eventId] as const,
  settings: (eventId: number | string) => ['wall', 'settings', eventId] as const,
  liveSnapshot: (eventId: number | string) => ['wall', 'liveSnapshot', eventId] as const,
  metrics: (eventId: number | string) => ['wall', 'metrics', eventId] as const,
  recentMedia: (eventId: number | string, limit = 20) =>
    ['wall', 'recentMedia', eventId, { limit }] as const,
  topContributor: (eventId: number | string) =>
    ['wall', 'topContributor', eventId] as const,
  insights: (eventId: number | string) =>
    ['wall', 'insights', eventId] as const,
  simulation: (eventId: number | string, draftHash: string) =>
    ['wall', 'simulation', eventId, draftHash] as const,
  diagnostics: (eventId: number | string) =>
    ['wall', 'diagnostics', eventId] as const,
}
```

### Hooks sugeridos

- `useWallTopInsights`
- `useWallRecentMediaTimeline`
- `useWallLiveSnapshot`
- `useWallRealtimeSync`
- `useWallPollingFallback`
- `useWallSelectedMedia`

### Comportamento recomendado

- `useWallRealtimeSync` invalida `insights`, `diagnostics` e `settings`;
- `useWallRealtimeSync` aplica `liveSnapshot` direto no cache quando chega `wall.runtime.snapshot.updated`;
- `useWallPollingFallback` ativa `refetchInterval` apenas em `disconnected` ou `offline`;
- ao reconectar, o manager invalida tudo e corta o polling;
- `useWallRecentMediaTimeline` pausa auto-scroll em hover para nao brigar com o usuario;
- `useWallSelectedMedia` sincroniza a selecao do trilho com palco e detalhe.

## 16.7 Normalizar semantica publica de `stopped`

Hoje:

- admin enxerga `stopped`
- payload publico tende a sair como `disabled`

Isso funciona tecnicamente, mas dificulta diagnostico semantico.

Vale decidir entre:

- manter distincao formal no payload publico;
- ou renomear a acao/public copy para ficar coerente.

## 17. Roteiro recomendado de evolucao

## Fase 1. Organizacao e clareza

- quebrar `EventWallManagerPage` em componentes menores;
- criar barra operacional unica;
- criar `WallTopInsightsRail`;
- mover configuracao para abas;
- adicionar `ScrollArea`;
- melhorar textos dos KPIs e estados;
- mover diagnostico profundo para `Accordion` recolhido.

## Fase 2. Preview de verdade

- extrair renderer compartilhado do player;
- criar `WallDraftPreviewCard`;
- criar `WallHeroStage`;
- aplicar loop visual curto de `3s`;
- manter montagem lazy quando aba de preview estiver aberta;
- adicionar tabs do palco:
  - `Ao vivo`
  - `Previa`
  - `Proximas`

## Fase 3. Monitor live

- adicionar snapshot de runtime;
- mostrar item atual com imagem e origem;
- ligar a timeline recente ao palco e ao `Sheet` de detalhe;
- sincronizar com diagnostico por player;
- adicionar timeline horizontal com thumbnails.

## Fase 4. Telemetria visual

- charts de heartbeat/cache/fila;
- grafico leve de composicao por origem no topo ou no detalhe;
- timeline de degradacao por player;
- indicadores de freshness e fairness com visual mais legivel;
- detalhe de player em `Sheet` / `Drawer`.

## Decisao recomendada

Se eu tivesse que decidir a proxima sprint hoje:

- nao colocaria mais configuracoes na lateral;
- nao usaria iframe como preview principal;
- nao misturaria simulacao com monitor real como se fossem a mesma coisa;
- priorizaria uma barra operacional fixa;
- priorizaria tambem um trilho vivo de insights no topo do workspace;
- criaria um preview de draft com o renderer real do player;
- adicionaria polling fallback no manager;
- criaria um endpoint agregado `wall/insights` para topo + recentes;
- enriqueceria a previsao das proximas 12 exibicoes com thumbnail e origem;
- simplificaria a linguagem dos KPIs antes de adicionar novos blocos.

## Proximo bloco recomendado apos esta sprint

Com o cockpit atual estabilizado, o proximo bloco mais coerente deixa de ser reorganizacao de layout e passa a ser consolidacao de fonte autoritativa.

Plano executavel detalhado desta continuidade:

- `docs/architecture/telao-cockpit-sprint-implementation-plan-2026-04-08.md`
  - secao `Plano executavel do bloco novo`

### 1. Monitor live mais autoritativo

Hoje o manager ja tem:

- snapshot dedicado
- evento privado de snapshot
- clock estimado de troca
- `advancedAt` mais forte porque o player agora envia `current_item_started_at` autoritativo no `heartbeat`
- `nextItem` quando a previsao da fila bate com a midia atual do snapshot
- palco com blocos `Agora no telao` e `Proxima no telao`

O que ainda falta:

- completar a migracao de `advancedAt` para uma fonte ainda mais proxima do `advance` real, reduzindo a dependencia da cadencia do `heartbeat`
- reduzir a diferenca entre `monitor live` e `simulacao`

### 2. Historico confiavel de exibidas

Estado atual:

- `totals.displayed` ja e numero autoritativo para o cockpit atual;
- o backend agora guarda historico agregado em `wall_display_counters`;
- a trilha sobe o contador quando a exibicao realmente avanca e evita duplicidade no mesmo display.

Leitura:

- isso destrava o KPI superior e remove a dependencia de regra local no frontend;
- a proxima etapa deixa de ser persistencia e passa a ser semantica final de origem e video.

### 3. Semantica visual final de origem e video

Estado atual:

- `liveSnapshot`, `sequence_preview` e `wall/insights` recente agora carregam:
  - normalizacao final de origem
  - `isVideo`
  - duracao
  - `videoPolicyLabel`
- palco, timeline horizontal e detalhe lateral agora consomem um mapper unico de semantica visual;
- video curto, video com duracao diferenciada e video longo com politica especial agora aparecem com leitura consistente no cockpit;
- o playback de video do wall continua com gaps especificos de produto e engine, mas a camada de leitura operacional do manager ficou fechada.

Proximo passo:

- ligar essa evolucao ao plano proprio de video em:
  - `docs/architecture/wall-video-playback-current-state-2026-04-08.md`
  - `docs/architecture/wall-video-playback-execution-plan-2026-04-08.md`
- seguir com o bloco seguinte do plano de continuidade, caso o time queira levar o monitor para uma fase ainda mais autoritativa de playback e video.

## Revalidacao complementar executada em 2026-04-09

Cockpit do wall:

- `cd apps/api && php artisan test --filter=WallLiveSnapshotTest`
  - `7` testes passando
- `cd apps/api && php artisan test --filter=WallDiagnosticsTest`
  - `7` testes passando
- `cd apps/api && php artisan test --filter=WallInsightsTest`
  - `6` testes passando
- `cd apps/api && php artisan test --filter=Wall`
  - `92` testes passando
- `cd apps/web && npm run test -- src/modules/wall/components/manager/stage/WallHeroStage.test.tsx src/modules/wall/components/manager/stage/WallUpcomingTimeline.test.tsx src/modules/wall/components/manager/recent/WallRecentMediaDetailsSheet.test.tsx`
  - `4` testes passando
- `cd apps/web && npm run test -- src/modules/wall/hooks/useWallLiveSnapshot.test.tsx src/modules/wall/hooks/useWallRealtimeSync.test.tsx src/modules/wall/components/manager/stage/WallAdvanceClock.test.tsx src/modules/wall/components/manager/stage/WallHeroStage.test.tsx src/modules/wall/components/manager/stage/WallUpcomingTimeline.test.tsx src/modules/wall/components/manager/top/WallTotalMediaCard.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx`
  - `26` testes passando
- `cd apps/web && npm run test -- src/modules/wall`
  - `208` testes passando
- `cd apps/web && npm run type-check`
  - sem erros
- `cd apps/web && npm run test -- src/modules/wall/hooks/useWallTopInsights.test.tsx src/modules/wall/components/manager/top/WallTotalMediaCard.test.tsx src/modules/wall/hooks/useWallRealtimeSync.test.tsx`
  - `8` testes passando

Detalhe importante desta revalidacao:

- o snapshot ao vivo agora preserva `advancedAt` autoritativo vindo do player quando a mesma midia continua em tela;
- timestamps mais antigos para a mesma midia sao ignorados no backend para evitar regressao visual do clock;
- o snapshot agora tambem devolve `nextItem` quando a previsao do backend confirma a proxima midia com confianca;
- `totals.displayed` agora sai de historico agregado por wall e nao depende mais de fallback `null` na UI;
- snapshot, simulacao e itens recentes agora usam a mesma semantica de origem e video no contrato;
- o detalhe lateral agora traduz playback de video para copy operacional, sem vazar regra tecnica crua;
- o palco agora segura o contexto de `Agora` e `Proxima` com placeholders operacionais, mesmo antes da primeira confirmacao do player;
- a timeline prevista agora preserva altura e contexto durante o loading, reduzindo a sensacao de piscada quando a previsao recarrega;
- o palco agora continua obedecendo a selecao manual do operador na midia principal, sem perder os blocos `Agora` e `Proxima`;
- a suite `Wall` do backend continua verde, com apenas um `TODO` ja existente fora deste escopo em `PagarmeClientTest`;
- o warning restante de `React Router` nos testes do manager continua sendo apenas aviso de future flags, sem falha funcional.

Baseline de video e intake:

- `cd apps/api && php artisan test --filter=PublicUploadTest`
  - `8` testes passando
- `cd apps/web && npm run test -- src/modules/wall/player/components/MediaSurface.test.tsx src/modules/wall/player/engine/cache.test.ts src/modules/wall/player/engine/preload.test.ts`
  - `12` testes passando

Leitura:

- a base atual do cockpit esta verde para seguir evoluindo;
- a trilha de video atual continua caracterizada por testes reais;
- isso reduz risco de misturar a proxima fase de monitor autoritativo com regressao de playback atual.

### Ordem recomendada desta continuidade

1. fonte autoritativa de `advancedAt`
2. `nextItem` no `liveSnapshot`
3. historico confiavel de exibidas
4. semantica final de origem e video
5. polimento e regressao completa

## Fontes oficiais consultadas para a proposta

### Stack de UI

- shadcn/ui Resizable:
  - https://ui.shadcn.com/docs/components/resizable
- shadcn/ui Tabs:
  - https://ui.shadcn.com/docs/components/tabs
- shadcn/ui Accordion:
  - https://ui.shadcn.com/docs/components/accordion
- shadcn/ui Scroll Area:
  - https://ui.shadcn.com/docs/components/scroll-area
- shadcn/ui Hover Card:
  - https://ui.shadcn.com/docs/components/hover-card
- shadcn/ui Tooltip:
  - https://ui.shadcn.com/docs/components/tooltip
- shadcn/ui Carousel:
  - https://ui.shadcn.com/docs/components/carousel
- shadcn/ui Sheet:
  - https://ui.shadcn.com/docs/components/sheet
- shadcn/ui Drawer:
  - https://ui.shadcn.com/docs/components/drawer
- shadcn/ui Chart:
  - https://ui.shadcn.com/docs/components/chart
- W3C APG Toolbar Pattern:
  - https://www.w3.org/WAI/ARIA/apg/patterns/toolbar/
- W3C APG Tabs Pattern:
  - https://www.w3.org/WAI/ARIA/apg/patterns/tabs/
- W3C APG Carousel Pattern:
  - https://www.w3.org/WAI/ARIA/apg/patterns/carousel/

### Data e realtime

- TanStack Query invalidation:
  - https://tanstack.com/query/v5/docs/react/guides/query-invalidation
- TanStack Query important defaults:
  - https://tanstack.com/query/latest/docs/framework/react/guides/important-defaults
- TanStack Query useQuery:
  - https://tanstack.com/query/v5/docs/framework/react/reference/useQuery
- TanStack Query placeholder data:
  - https://tanstack.com/query/v5/docs/framework/react/guides/placeholder-query-data
- TanStack Query render optimizations:
  - https://tanstack.com/query/latest/docs/framework/react/guides/render-optimizations
- Laravel Reverb:
  - https://laravel.com/docs/12.x/reverb
- Laravel Broadcasting:
  - https://laravel.com/docs/12.x/broadcasting
- Motion AnimatePresence:
  - https://motion.dev/docs/react-animate-presence
- Motion layout animations:
  - https://motion.dev/docs/react-layout-animations
- Lucide React:
  - https://lucide.dev/guide/react

## Conclusao final

A tela `/events/:id/wall` ja tem base tecnica suficiente para virar um cockpit forte de operacao do telao.

O problema principal hoje nao e falta de backend, nem falta de realtime.

O problema principal e de produto e composicao:

- muitas responsabilidades juntas;
- preview fraco;
- semantica tecnica demais;
- ausencia de separacao clara entre `editar`, `operar`, `diagnosticar` e `monitorar`.

Com a stack atual, a melhor evolucao e perfeitamente possivel sem trocar framework nem introduzir um frontend paralelo.
