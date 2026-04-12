# Event Realtime Virtual Office Analysis - 2026-04-11

## Objetivo

Avaliar, com base no codigo real do `eventovivo`, se faz sentido criar uma pagina fullscreen, sem sidebar e com o minimo de distracoes, para mostrar em tempo real o que esta acontecendo dentro de um evento especifico como se fosse um escritorio virtual com agentes/funcionarios trabalhando.

A ideia do produto analisada aqui e:

- acompanhar o fluxo completo de uma foto ou video entrando no evento;
- visualizar em tempo real onde cada item esta no pipeline;
- mostrar tambem o que acabou de acontecer;
- usar uma linguagem visual de escritorio virtual, preferencialmente em pixel art;
- preservar o ownership modular do monorepo.

Este documento tambem compara as referencias locais:

- `C:\Users\Usuario\Desktop\agentesia\pixel-agents-main`
- `C:\Users\Usuario\Desktop\agentesia\workadventure-master`

---

## Veredito Executivo

Sim, a ideia e viavel na stack atual do Evento Vivo.

Mas o caminho certo nao e portar um metaverso pronto nem tentar transformar a plataforma em um multiplayer livre.

O melhor desenho para o produto hoje e:

1. criar um modulo novo de dominio chamado `EventOperations`;
2. expor uma pagina fullscreen protegida por evento, fora do `AdminLayout`;
3. alimentar essa pagina por uma projection operacional propria;
4. usar visual inspirado em `pixel-agents`, nao em `workadventure`;
5. representar agentes como estacoes do fluxo, nao como uma sprite por foto.

Conclusao pratica:

- `pixel-agents` traz referencias fortes de linguagem visual e de engine enxuta;
- `workadventure` traz referencias de zonas, mapa e sensacao de espaco vivo, mas e pesado demais para reaproveitamento direto;
- a base atual do Evento Vivo ja possui pipeline, realtime, historico e telemetria suficientes para uma V0/V1 convincente;
- para ficar realmente bom, falta unificar os sinais em um canal e numa timeline operacional de evento.

---

## Resposta Curta

Se a pergunta for "da para fazer?", a resposta e `sim`.

Se a pergunta for "da para fazer bem sem trocar a stack?", a resposta tambem e `sim`, desde que a entrega siga esta ordem:

1. V0: tela fullscreen read-only usando endpoints que ja existem;
2. V1: projection unificada + canal realtime proprio;
3. V2: replay do que aconteceu + polimento visual pixel art;
4. V3: extras de mapa editavel, multiplas salas e cenografia mais complexa, se ainda fizer sentido.

Se a pergunta for "da para fazer com WorkAdventure dentro do produto atual?", a resposta recomendada e `nao`.

---

## Leitura Real Da Stack Atual

### Backend ja existente

O monorepo ja tem ownership claro nos modulos que sustentam esse produto:

- `InboundMedia`
- `WhatsApp`
- `Telegram`
- `ContentModeration`
- `MediaIntelligence`
- `MediaProcessing`
- `Gallery`
- `Wall`
- `Audit`
- `Events`

Isso e importante porque a tela proposta nao depende de inventar um fluxo novo. Ela depende principalmente de observar, projetar e apresentar um fluxo que ja existe.

### Frontend ja existente

A base frontend tambem ja suporta esse tipo de experiencia:

- React 18 + TypeScript + Vite 5;
- TanStack Query para boot e polling fallback;
- Reverb/Pusher protocol para realtime;
- paginas fullscreen publicas fora do `AdminLayout`, como `WallPlayerPage`;
- paginas operacionais densas, como `ModerationPage`, `EventJourneyBuilderPage` e `EventWallManagerPage`;
- componentes suficientes para overlays, sheets, drawers e cards de diagnostico.

### Realtime ja existente

O projeto ja usa canais privados/publicos que mostram que a infraestrutura para uma nova superficie realtime existe:

- `wall.{wallCode}`
- `event.{eventId}.wall`
- `event.{eventId}.gallery`
- `event.{eventId}.moderation`
- `organization.{organizationId}.moderation`
- `event.{eventId}.play`

Hoje ainda nao existe um canal proprio de "operacoes do evento", mas existe base clara para abrir `event.{eventId}.operations`.

### Historico e observabilidade ja existentes

Ja existem fontes reais para responder "o que esta acontecendo" e "o que aconteceu":

- `media_processing_runs`
- `GET /api/v1/events/{event}/media/pipeline-metrics`
- `GET /api/v1/events/{event}/timeline`
- feed realtime de moderacao
- `wall/live-snapshot`
- `wall/diagnostics`
- logs e feedbacks de WhatsApp/Telegram

Ou seja: o problema principal nao e falta de dados. O problema principal e falta de uma projection operacional unica.

---

## Fluxo Completo Atual Do Produto

O fluxo de midia ja esta bem definido no codigo.

### 1. Entrada

Os canais validados no codigo e nos testes hoje sao:

- WhatsApp privado por codigo;
- grupos de WhatsApp vinculados;
- Telegram privado por sessao;
- link publico/upload.

### 2. Recepcao e roteamento

O caminho real hoje passa por:

- webhook/provider listener;
- deduplicacao;
- sessao ou binding de evento;
- checagem de blacklist;
- persistencia de `ChannelWebhookLog` ou entidades equivalentes;
- criacao de `InboundMessage` canonica;
- dispatch para pipeline.

### 3. Download e materializacao da midia

Quando a mensagem e elegivel, o pipeline segue para:

- `DownloadInboundMediaJob`
- criacao de `EventMedia`
- registro de run em `media_processing_runs`

### 4. Preparacao tecnica

Depois entram as etapas tecnicas:

- `GenerateMediaVariantsJob`
- geracao de `fast_preview`, `thumb`, `gallery`, `wall`
- hash perceptual
- agrupamento leve de duplicatas

### 5. Analise automatica

O pipeline atual ja contempla:

- `AnalyzeContentSafetyJob`
- avaliacao contextual por `MediaIntelligence` quando habilitada
- status tecnicos por etapa

### 6. Decisao

A decisao final hoje passa por:

- `FinalizeMediaDecisionAction`
- `approved`
- `pending`
- `rejected`

Se o evento estiver em moderacao manual ou se a IA pedir revisao, o item entra em fila humana.

### 7. Saida

Depois da decisao:

- a midia pode ser publicada na galeria;
- pode entrar no wall se elegivel;
- pode gerar feedback automatico em WhatsApp/Telegram;
- pode aparecer na timeline de auditoria;
- pode ser reprocessada por etapa;
- pode ser removida e propagada para cleanup.

### 8. Wall em tempo real

O wall ja tem:

- boot HTTP;
- realtime via Reverb;
- `live snapshot`;
- `diagnostics`;
- heartbeat por player;
- simulacao da fila provavel.

### 9. Historico

O sistema tambem ja guarda trilha suficiente para uma visao historica:

- `media_processing_runs` para etapas do pipeline;
- `activitylog` para acoes e auditoria;
- feedback tables de WhatsApp/Telegram;
- estados correntes de moderacao, publicacao e wall.

---

## O Que Isso Permite Hoje Sem Criar Engine Nova

Com os endpoints e modelos atuais, ja e possivel montar uma primeira versao read-only de "sala de operacoes" combinando:

- `journey-builder` para descrever a configuracao do fluxo;
- `media/pipeline-metrics` para backlog, SLA e falhas;
- feed de moderacao para itens pendentes;
- timeline de auditoria do evento;
- `wall/live-snapshot` para o que esta no telao;
- `wall/diagnostics` para estado operacional dos players.

Isso ja permitiria uma V0 funcional.

Mas essa V0 teria quatro limites:

1. ela ainda dependeria de varias queries heterogeneas;
2. nao existiria uma timeline normalizada de estacoes/agentes;
3. o realtime continuaria espalhado entre canais diferentes;
4. o historico continuaria bom para auditoria, mas fraco para replay visual.

---

## Gaps Reais Para A Ideia Ficar Forte

Os gaps principais hoje sao estes:

### 1. Nao existe projection operacional unica por evento

Hoje o produto tem:

- configuracao unificada da jornada;
- dados tecnicos por modulo;
- historico em tabelas e activity log;

mas nao tem um payload unico de "estado vivo da operacao do evento".

### 2. Nao existe canal realtime unico para essa experiencia

Existe realtime de wall e moderacao, mas nao um stream unico de:

- entrada recebida;
- download iniciado/concluido;
- safety rodando;
- revisao manual pendente;
- aprovado/rejeitado;
- publicado na galeria;
- entrou no wall;
- feedback enviado.

### 3. Nao existe timeline normalizada para replay

`media_processing_runs` e `audit` sao excelentes para diagnostico e trilha tecnica, mas nao sao uma timeline UX-first de "escritorio virtual".

### 4. Nao existe correlacao visual pronta por estacao

Hoje a relacao existe por:

- `event_id`
- `event_media_id`
- `inbound_message_id`
- `provider_message_id`
- `trace_id` em parte do inbound

Mas isso ainda nao foi projetado como narrativa visual pronta para UI.

### 5. O wall nao e um espelho frame-perfect

O sistema atual do wall e near real-time, nao synchronized playback frame a frame.

Para a pagina proposta isso e aceitavel.

Mas a doc precisa deixar claro:

- a sala de operacoes pode mostrar "o item atual e o proximo" com alta confianca;
- ela nao deve prometer espelhamento milimetrico do frame exato da TV.

---

## Analise Das Referencias Externas

## 1. `pixel-agents-main`

### O que ele e

`pixel-agents` e uma interface visual enxuta em pixel art para observar agentes trabalhando.

Leitura real do repositorio local:

- extensao de VS Code no host;
- `webview-ui` em React;
- renderizacao principal em Canvas 2D;
- layout de escritorio em tiles;
- pathfinding leve;
- state machine simples por personagem;
- overlay com bolhas, labels e estados.

Arquivos relevantes lidos:

- `webview-ui/src/App.tsx`
- `webview-ui/src/office/engine/renderer.ts`
- `webview-ui/src/office/engine/characters.ts`
- assets em `webview-ui/public/assets/*`

### O que da para aproveitar como ideia

- linguagem visual de escritorio;
- personagens representando agentes/estacoes;
- animacoes simples por estado: andar, ler, digitar, esperar;
- speech bubbles para alerta, bloqueio ou espera humana;
- canvas leve com HUD minimo;
- tiles, furniture e monitores como linguagem de observabilidade;
- zoom inteiro e pixel-perfect rendering.

### O que nao da para reaproveitar diretamente

- acoplamento com VS Code;
- leitura de transcript JSONL de Claude;
- semantica de "um terminal = um agente";
- office editor como escopo inicial;
- toda a heuristica de detecao de status baseada em runtime de extensao.

### Veredito sobre `pixel-agents`

Essa referencia e excelente para:

- direcao visual;
- engine 2D leve;
- mapa mental da experiencia.

Ela e a referencia certa para inspirar a camada de apresentacao.

---

## 2. `workadventure-master`

### O que ele e

`WorkAdventure` e um produto completo de mundo virtual colaborativo.

Leitura real do repositorio local:

- monorepo com varios workspaces;
- frontend principal com `Svelte + Phaser`;
- backend e servicos dedicados;
- mapa via Tiled;
- streaming, proximidade, salas, chat, meeting, broadcast, editor, map storage e stack Docker inteira.

Arquivos e docs relevantes lidos:

- `README.md`
- `play/package.json`
- `docs/map-building/tiled-editor/index.md`
- `docs/map-building/inline-editor/area-editor/broadcast.md`
- `play/src/front/Phaser/Game/GameScene.ts`

### O que da para aproveitar como ideia

- nocao de zonas/areas com semantica;
- palco e audiencia como conceito;
- mapa como narrativa espacial;
- areas de broadcast;
- capacidade de transformar um espaco em experiencia operacional.

### O que nao faz sentido portar

- Phaser;
- Svelte;
- stack multiplayer inteira;
- editor via Tiled;
- WebRTC/proximity/video meetings;
- dependencia em varios servicos e em stack de mapa/storage;
- experiencia de avatar livre em tempo real.

### Veredito sobre `workadventure`

Essa referencia e boa para inspiracao conceitual de espaco e zonas.

Mas ela e pesada demais para o problema atual.

Se tentarmos trazela para dentro do Evento Vivo, o projeto vira outro produto:

- mais infra;
- mais custo;
- mais superficie de manutencao;
- mais riscos de rollout;
- pouco ganho real para a necessidade operacional da V1.

---

## Comparativo Final Das Referencias

| Repositorio | Papel recomendado | Papel nao recomendado |
|---|---|---|
| `pixel-agents-main` | inspiracao visual e tecnica para a camada 2D/canvas | copiar o runtime host ou a logica de extensao |
| `workadventure-master` | inspiracao de zonas, palco, mapa e espaco vivo | portar engine, multiplayer, mapa Tiled ou stack completa |

Conclusao:

- copiar a sensacao de `pixel-agents`;
- absorver a logica espacial de `workadventure`;
- implementar tudo em cima da stack do Evento Vivo.

---

## Arquitetura Recomendada

## 1. Ownership

### Backend

Criar um modulo novo:

- `apps/api/app/Modules/EventOperations`

Motivo:

- a feature e importante;
- cruza varios modulos;
- nao e apenas detalhe de `Events`, `Wall` ou `MediaProcessing`;
- precisa de projection propria;
- precisa de ownership claro.

### Frontend

Criar modulo correspondente:

- `apps/web/src/modules/event-operations`

Pagina principal:

- `EventOperationsRoomPage.tsx`

---

## 2. Permissao Recomendada

Recomendacao:

- nova permissao `operations.view`

Motivo:

- a pagina mistura sinais de moderacao, canais, wall e auditoria;
- forcar combinacao de varias permissoes existentes tende a complicar a UX e o controle;
- uma permissao propria deixa o produto mais claro.

Se a equipe preferir evitar nova permissao na V0, a alternativa seria:

- `events.view` como base;
- esconder blocos sensiveis se o usuario nao tiver `media.view`, `media.moderate` ou `wall.view`.

Ainda assim, a solucao mais limpa para V1 continua sendo `operations.view`.

---

## 3. Rota Frontend

Como a pagina precisa ser fullscreen e sem sidebar, ela nao deve nascer dentro do `AdminLayout`.

O padrao ja existe no projeto com `WallPlayerPage`.

Recomendacao:

- rota protegida, mas fora do shell administrativo:
  - `/events/:id/control-room`

Comportamentos reaproveitaveis do wall player:

- request de fullscreen;
- hide cursor por inatividade;
- wake lock;
- layout sem menu lateral.

---

## 4. Rota API

Sugestao de ownership no backend:

- `GET /api/v1/events/{event}/operations/room`
- `GET /api/v1/events/{event}/operations/timeline`
- `GET /api/v1/events/{event}/operations/replay`

Para V1, `room` e `timeline` ja resolvem.

---

## 5. Canal Realtime

Novo canal privado recomendado:

- `event.{eventId}.operations`

Canal opcional de awareness:

- `event.{eventId}.operations.presence`

Contrato recomendado:

- o boot HTTP entrega o snapshot operacional completo;
- o websocket entrega apenas diffs e eventos append-only;
- rebuild completo fica reservado para reconnect, resync manual ou fallback;
- alertas realmente criticos podem usar dispatch imediato;
- todo o resto deve seguir pela fila normal de broadcast.

Eventos broadcastaveis recomendados:

- `operations.station.delta`
- `operations.timeline.appended`
- `operations.alert.created`
- `operations.health.changed`
- `operations.presence.changed`

Evento especial de ressincronizacao:

- `operations.snapshot.boot`

Regra de payload:

- usar `broadcastAs()` para travar nomes curtos e estaveis;
- usar `broadcastWith()` para enviar payload pequeno e semantico;
- usar `broadcastWhen()` para suprimir ruido de baixo valor;
- nao serializar modelos Eloquent inteiros para a UI quando o cliente so precisa de `id`, `station`, `delta`, `severity` e `occurred_at`.

Regra de fila:

- `ShouldBroadcast` por padrao;
- `ShouldBroadcastNow` apenas para sinais muito criticos, como `wall player offline`, `provider travado` ou alerta operacional severo.

Cadencia recomendada de broadcast:

- `critico imediato`: incidentes que precisam aparecer quase no instante em que a persistencia confirma o fato;
- `operacional normal`: diffs de estado, counters e health que podem seguir a fila padrao;
- `timeline coalescivel`: sinais de baixo valor que podem ser agregados por janela curta antes de virar append visual.

Regra de presenca:

- se quisermos awareness de quem esta olhando a sala, o canal de presence deve ficar separado do canal operacional;
- a UI pode usar callbacks de presenca para HUD de operadores conectados sem poluir o stream de negocio.
- presence nao e fonte de verdade operacional; ele serve apenas para awareness de visualizacao da sala.

### Contrato versionado P0

Como o boot vem por HTTP e os updates vem por websocket, o contrato precisa ser versionado e monotonicamente aplicavel.

Esse contrato deve ser tratado como inviolavel, nao como detalhe de implementacao.

Campos obrigatorios em snapshot e delta:

- `schema_version`
- `snapshot_version`
- `timeline_cursor`
- `event_sequence`
- `server_time`

Regras:

- `schema_version` protege compatibilidade de payload;
- `snapshot_version` identifica a versao autoritativa do estado coarse-grained;
- `event_sequence` deve ser monotonicamente crescente por evento/sala;
- `timeline_cursor` deve apontar para o ultimo ponto consistente do rail historico;
- `server_time` permite reconciliacao de latencia, ordering defensivo e replay.

Regras de aplicacao no cliente:

- se um delta chegar com `snapshot_version` diferente do snapshot corrente, a UI deve pedir resync;
- se `event_sequence` for menor ou igual ao ultimo aplicado, o delta deve ser tratado como idempotente e descartado;
- se houver gap de sequencia, a UI deve congelar aplicacao incremental e pedir rebuild;
- reconnect deve sempre comparar `snapshot_version`, `event_sequence` e `timeline_cursor` antes de continuar o live.

Exemplo de shape minimo:

```json
{
  "schema_version": 1,
  "snapshot_version": 42,
  "timeline_cursor": "evt_000981",
  "event_sequence": 981,
  "server_time": "2026-04-11T18:42:15Z"
}
```

---

## 6. Projection Recomendada

### Fonte de verdade

As fontes de verdade continuam nos modulos atuais:

- `InboundMedia`
- `WhatsApp`
- `Telegram`
- `ContentModeration`
- `MediaIntelligence`
- `MediaProcessing`
- `Gallery`
- `Wall`
- `Audit`

### Projection nova

Criar uma projection propria em `EventOperations`.

Modelo recomendado:

### Regra central: separar estado operacional de estado de animacao

A projection e o snapshot do backend devem modelar apenas o estado operacional:

- backlog;
- fase atual;
- alertas;
- players online/offline;
- throughput;
- falhas;
- itens recentes por estacao;
- current/next do wall;
- health e degradacao por provider.

O runtime visual do frontend deve modelar apenas o estado de animacao:

- posicao do agente;
- frame atual;
- direcao;
- bounce;
- fila visual;
- luz piscando;
- balao;
- brilho de monitor;
- micro-interacoes de cenario.

Regra pratica:

- o backend pode emitir `animation_hint`, `urgency`, `queue_depth` e `render_group`;
- o backend nao deve persistir nem broadcastar coordenadas, pathfinding ou frame a frame;
- o snapshot materializado existe para verdade operacional, nao para dirigir sprite literalmente.

### `event_operation_events`

Tabela append-only, normalizada para UX.

Campos sugeridos:

- `id`
- `event_id`
- `event_media_id` nullable
- `inbound_message_id` nullable
- `station_key`
- `event_key`
- `severity`
- `urgency`
- `title`
- `summary`
- `payload_json`
- `animation_hint`
- `station_load`
- `queue_depth`
- `render_group`
- `dedupe_window_key`
- `occurred_at`
- `correlation_key`

Exemplos de `station_key`:

- `intake`
- `download`
- `variants`
- `safety`
- `intelligence`
- `human_review`
- `gallery`
- `wall`
- `feedback`
- `alerts`

Exemplos de `event_key`:

- `station.load.changed`
- `station.alert.raised`
- `media.card.arrived`
- `station.throughput.spike`
- `media.download.started`
- `media.download.completed`
- `media.variants.generated`
- `media.safety.review_requested`
- `media.safety.blocked`
- `media.moderation.pending`
- `media.moderation.approved`
- `media.moderation.rejected`
- `media.published.gallery`
- `media.published.wall`
- `feedback.sent`
- `wall.health.changed`
- `operator.presence.changed`

### `event_operation_snapshots`

Opcional na V1, mas recomendado se quisermos simplificar o boot.

Campos sugeridos:

- `event_id`
- `snapshot_json`
- `snapshot_version`
- `latest_event_sequence`
- `timeline_cursor`
- `schema_version`
- `updated_at`

Regra do snapshot:

- `snapshot_json` deve guardar apenas o estado operacional coarse-grained da sala;
- hints de animacao podem entrar como metadata de alto nivel;
- posicoes, interpolacoes, timers visuais e frames devem ficar no runtime do frontend.
- o snapshot deve expor explicitamente `snapshot_version`, `latest_event_sequence`, `timeline_cursor`, `schema_version` e `server_time`.

Se a equipe quiser economizar banco na V1:

- o snapshot pode ser montado sob demanda;
- mas o historico append-only continua valendo muito a pena.

### Retencao e prune

`event_operation_events` precisa nascer com politica de retencao desde o inicio.

Regra recomendada:

- manter granularidade completa apenas na janela quente usada por live, history recente e replay curto;
- depois dessa janela, arquivar, compactar ou resumir por estacao;
- snapshots materializados podem viver mais que os eventos brutos;
- o prune deve considerar idade do evento, encerramento do evento e custo de replay.

---

## 7. Como Alimentar A Projection

Nao e necessario reinventar o pipeline.

O caminho certo e ouvir os eventos e writes que ja existem:

- inbound recebido e normalizado;
- run iniciada/finalizada em `MediaProcessingRunService`;
- mudancas de moderacao;
- publish/reject/delete;
- feedbacks enviados;
- mudancas de wall snapshot e diagnostics;
- auditoria relevante por evento.

O ideal e criar listeners/projetores em `EventOperations`, por exemplo:

- `ProjectInboundMediaDetectedToOperationsTimeline`
- `ProjectMediaRunStartedToOperationsTimeline`
- `ProjectMediaRunFinishedToOperationsTimeline`
- `ProjectModerationDecisionToOperationsTimeline`
- `ProjectMediaPublishedToOperationsTimeline`
- `ProjectWallSnapshotToOperationsTimeline`
- `ProjectFeedbackSentToOperationsTimeline`

Esses projetores:

- escrevem em `event_operation_events`;
- atualizam o snapshot agregado;
- so depois disparam broadcast para `event.{eventId}.operations`.

Ordem recomendada:

1. listeners/projetores escrevem `event_operation_events`;
2. snapshot agregado e recalculado;
3. o delta broadcastavel e montado;
4. o broadcast sai apos commit.

Isso e importante porque a sala nao pode "mentir" por alguns instantes:

- a UI recebe "entrou em moderacao humana";
- mas a consulta de detalhe ainda nao encontra a midia;
- ou o contador da estacao ainda nao bate com a timeline.

Se o broadcast depender do estado persistido, o evento deve implementar `ShouldDispatchAfterCommit`.

Regra endurecida:

- toda emissao que dependa de dado persistido deve sair after-commit;
- se a equipe separar uma conexao de fila para listeners e broadcasts operacionais, vale considerar `after_commit=true` nessa conexao;
- se a transacao falhar, o cliente nao deve receber o evento correspondente;
- `ShouldBroadcastNow` nao substitui essa regra; ele so muda o modo de dispatch, nao a necessidade de coerencia transacional.

Validacao local da stack atual:

- o projeto ja usa `ShouldDispatchAfterCommit` nas bases de broadcast de `Wall` e `MediaProcessing`;
- a conexao `redis` em `apps/api/config/queue.php` ja nasce com `after_commit` vindo de `REDIS_QUEUE_AFTER_COMMIT`, com default `true`;
- outras conexoes continuam com `after_commit=false`, entao a regra precisa seguir explicita na arquitetura do modulo novo.

---

## Requisitos P0 E P1

### P0

- contrato versionado com `schema_version`, `snapshot_version`, `timeline_cursor`, `event_sequence` e `server_time`;
- deltas idempotentes e aplicados em ordem monotonicamente crescente;
- broadcast after-commit sempre que o payload depender de persistencia;
- hierarquia de leitura macro, meso e micro;
- `roomStore` separado de `sceneRuntime`;
- TanStack Query restrito a boot, history e fallback;
- lifecycle de `fullscreenchange`, `visibilitychange`, wake lock e reconnect;
- backpressure visual por estacao e por layer;
- modo `prefers-reduced-motion` para a sala live;
- politica inicial de retencao para `event_operation_events`.

### P1

- replay mais sofisticado e compactacao historica;
- awareness por presence channel;
- telemetria de frame budget e long tasks;
- escala horizontal de Reverb com Redis Pub/Sub;
- `OffscreenCanvas` e workers apos profiling.

---

## Como A Pagina Deveria Funcionar

## 1. Conceito De Cena

A pagina nao deve mostrar uma sprite por midia nem tentar simular o fluxo literalmente em escala 1:1.

Isso seria inviavel em eventos com volume alto.

O modelo certo e:

- uma sprite/agente por estacao;
- micro-filas por estacao;
- baloes, luzes, barras e monitores indicando atividade;
- timeline inferior para ver o que aconteceu.

Ou seja: a pagina representa departamentos do fluxo, nao itens individuais.

Frase-guia da V1:

- a sala nao deve tentar mostrar tudo o que esta acontecendo; ela deve mostrar o que merece atencao agora.

### Tres niveis de leitura

A cena precisa ser compreendida em tres distancias de leitura:

- leitura macro, em `3s`: o evento esta saudavel, em atencao ou em risco;
- leitura meso, em `15s`: onde esta o gargalo agora, como recepcao, safety, moderacao, wall ou feedback;
- leitura micro, em `2min`: qual foi o motivo, o item impactado e o historico relevante.

Regra de UX:

- saude global primeiro;
- gargalo atual depois;
- historico por ultimo;
- detalhe sob demanda;
- animacao como explicacao, nao como espetaculo.

Regra de layout obrigatoria:

- leitura macro precisa ocupar a leitura principal da tela e continuar visivel mesmo de longe;
- leitura meso precisa promover uma unica estacao dominante quando houver gargalo claro;
- leitura micro deve ficar concentrada no rail e no detalhe lateral, nunca competindo com a leitura global.

### Regra narrativa

O estado real deve alimentar uma coreografia deterministica, nao uma simulacao literal.

Exemplos:

- se entram `12` midias em `3s`, a recepcao acende, ganha fila visual e mostra `2` ou `3` cards simbolicos;
- se safety dispara review, um agente vai para a mesa de IA e volta com balao amarelo;
- se backlog humano cresce, a mesa de moderacao ganha pilha visual e lampada de warning;
- se a galeria publica midias recentes, os monitores da estacao brilham e mostram thumbs recentes;
- se o wall entra em degradacao, a estacao do telao acende alerta e muda humor do operador.

O objetivo nao e "ver cada foto andando". O objetivo e sentir que a operacao esta viva sem perder legibilidade.

### Papeis de trabalho

Para a sala realmente parecer uma equipe operando o evento, a cenografia nao deve nascer apenas de estacoes.

Ela precisa nascer de poucos papeis de trabalho, semanticamente claros:

- `coordinator`: percorre a sala, sintetiza atencao e reforca saude global ou risco;
- `dispatcher`: recebe entrada e encaminha atividade da recepcao para as proximas areas;
- `runner`: leva cards simbolicos entre areas e reforca mudanca de fase;
- `reviewer`: fica mais associado a revisao humana e backlog pendente;
- `operator`: reage ao health do wall e ao `current/next`;
- `triage`: expressa safety e IA como trabalho de avaliacao, nao apenas como maquina piscando.

Regra de produto:

- a V1 nao deve parecer "pipeline com sprites";
- ela deve parecer "equipe trabalhando" com poucos arquetipos reutilizaveis;
- esses papeis existem para orientar leitura, nao para simular recursos humanos literais.

### Camada de direcao cenica

`event-translator.ts` nao deve ser tratado apenas como tradutor tecnico.

Ele e a camada de direcao cenica da sala:

- recebe sinais operacionais brutos;
- decide o que merece virar gesto visual;
- sintetiza muitos eventos em poucos sinais legiveis;
- protege a cena contra ruido e excesso de microanimacao.

### Orquestracao de atencao

A camada de direcao cenica deve responder continuamente a uma pergunta:

- qual e a coisa mais importante para olhar agora?

Ordem recomendada de prioridade:

- falha operacional urgente;
- gargalo dominante;
- progresso visivel que ajuda a explicar fluxo;
- atividade decorativa e respiracao da cena.

Isso significa que nem todo evento merece o mesmo peso visual.

Quando a sala estiver carregada, o correto e reduzir decoracao antes de reduzir legibilidade.

### Backpressure visual

Para a cena continuar legivel sob throughput alto, a coreografia precisa ter protecao explicita.

Regras:

- nunca spawnar um card visual por evento bruto;
- agregar eventos por janela curta e por estacao;
- quando o throughput subir, degradar para heat, glow, fila sintetica e contadores;
- limitar efeitos concorrentes por estacao e por layer;
- a camada de direcao cenica deve coalescer micro-bursts antes de pedir trabalho novo para o renderer.

### Orcamento perceptivo

Nao basta ter apenas frame budget tecnico. A sala precisa nascer com budget perceptivo.

Regras:

- limitar quantos agentes podem se mover ao mesmo tempo;
- limitar quantos baloes podem aparecer de forma concorrente;
- limitar quantos alertas visuais cabem sem esconder o gargalo principal;
- limitar quantas thumbs recentes a galeria mostra antes de resumir;
- promover uma unica estacao dominante quando houver disputa de atencao.

O problema da V1 nao sera apenas CPU. Tambem sera ruido.

### Calma operacional

A sala tambem precisa ter um estado intencional de calma.

Quando o evento estiver saudavel e com pouco volume, a tela nao deve parecer quebrada nem vazia.

Regras:

- poucos movimentos;
- luzes suaves;
- timeline mais silenciosa;
- pequenos sinais de "tudo sob controle";
- nenhum pulso agressivo so para fingir atividade.

### Legivel sob estresse

O criterio da V1 nao e "ficou bonito". O criterio e "continua legivel quando o evento fica caotico".

Regras visuais P0:

- cores de severidade muito estaveis;
- uma linguagem unica para backlog, bloqueio, espera humana e degradacao;
- movimentos curtos, funcionais e subordinados ao estado operacional;
- nada de particulas ou pulse competindo com alertas reais;
- reduzir movimento nao essencial quando `prefers-reduced-motion` estiver ativo.

---

## 2. Estacoes Recomendadas

Cada estacao precisa ter um gesto visual proprio e imediatamente reconhecivel.

Mapa de gestos recomendado:

- `Recepcao`: pulsos curtos e fila simbolica;
- `Download / Arquivo`: esteira ou caixa de entrada;
- `Laboratorio / Variantes`: bancada com thumbs nascendo;
- `Safety AI`: scanner e luz amarela/vermelha;
- `IA De Contexto`: terminal ou mesa de leitura;
- `Moderacao Humana`: pilha fisica de cards;
- `Galeria`: parede viva com thumbs recentes;
- `Telao`: monitor central com `current/next`;
- `Feedback`: mensagens e reacoes saindo da estacao;
- `Alertas`: sirene discreta, nunca permanente.

Versao reduced-motion recomendada por gesto:

- `Recepcao`: menos deslocamento e mais contagem/pulso discreto;
- `Download / Arquivo`: menos esteira e mais indicador de entrada/saida;
- `Laboratorio / Variantes`: menos nascimento de thumbs e mais troca de estado;
- `Safety AI`: menos varredura e mais mudanca de cor/severidade;
- `Moderacao Humana`: menos movimento de cards e mais pilha/contador;
- `Galeria`: menos brilho e mais moldura de recentes;
- `Telao`: menos glow e mais selo claro de `current/next/health`.

### Recepcao

Representa:

- WhatsApp privado
- grupos de WhatsApp
- Telegram
- upload publico

Sinais:

- novas entradas por minuto;
- origem mais ativa;
- itens ignorados por sessao ausente;
- itens bloqueados por blacklist.

### Download / Arquivo

Representa:

- `DownloadInboundMediaJob`
- materializacao de `EventMedia`

Sinais:

- itens baixando;
- falhas de download;
- tempo medio recente.

### Laboratorio / Variantes

Representa:

- `GenerateMediaVariantsJob`

Sinais:

- itens em preparacao;
- variantes concluidas;
- gargalos.

### Safety AI

Representa:

- `AnalyzeContentSafetyJob`

Sinais:

- passando;
- review;
- blocked;
- failed.

### IA De Contexto

Representa:

- `MediaIntelligence`

Sinais:

- caption;
- enrich_only;
- gate;
- fallback.

### Moderacao Humana

Representa:

- itens pendentes;
- aprovacoes recentes;
- rejeicoes recentes;
- fila atual da operacao.

### Galeria

Representa:

- publish na galeria;
- itens ocultados;
- itens mais recentes.

### Telao

Representa:

- snapshot atual do wall;
- proximo item com confianca;
- players online/degradados/offline.

### Feedback

Representa:

- reactions e replies em WhatsApp/Telegram;
- falhas de envio;
- blocked feedback;
- published feedback.

### Alertas

Representa:

- fila travada;
- player offline;
- erro de provider;
- crescimento fora do normal de pendencias.

---

## 3. Modo Live E Modo History

### Live

Mostra:

- snapshot atual das estacoes;
- itens recentes por estacao;
- backlog;
- wall current/next;
- alertas vivos.

### History

Mostra:

- trilho temporal;
- eventos normalizados;
- clique para abrir detalhe da midia ou do incidente;
- filtros por `15m`, `1h`, `agora`, `safety`, `moderation`, `wall`.

### Replay

Fase posterior recomendada:

- selecionar janela de tempo;
- reconstruir a cena a partir de `event_operation_events`;
- reproduzir acelerado `x1`, `x4`, `x10`.

---

## 4. HUD Recomendado

Mesmo sendo fullscreen, a pagina nao deve ser muda.

HUD minimo recomendado:

- topo esquerdo: nome do evento, status global e relogio;
- topo direito: conexao, wall players e fila humana;
- rodape: rail vivo com densidade controlada;
- detalhe lateral so quando o operador seleciona uma estacao ou item.

Regra dura:

- o HUD deve mostrar apenas o que ajuda decisao imediata;
- qualquer metrica secundaria vai para detalhe lateral ou history;
- se o HUD virar dashboard, a cena perde funcao.

O estado default deve ser:

- sem sidebar fixa;
- sem menu lateral administrativo;
- sem breadcrumb pesado.

Regra de UX:

- o HUD precisa informar sem roubar a cena;
- ele nao pode virar um dashboard tradicional em cima de um canvas decorativo.

### Acessibilidade operacional

Mesmo sendo uma tela live, a control room deve nascer com duas camadas acessiveis simples:

- uma regiao `status` para atualizacoes nao criticas;
- uma regiao `alert` apenas para problemas realmente urgentes;
- uma timeline live com `role="log"`, nome acessivel claro e append apenas no fim do rail;
- labels claros para fullscreen, filtros, detalhe lateral e mudancas de modo.

---

## Proposta Tecnica De Frontend

## 1. Stack Recomendada

Continuar com a stack atual:

- React 18
- `useSyncExternalStore` para assinar store externa
- TypeScript
- TanStack Query
- Reverb/Pusher
- Tailwind

Para a cena principal:

- Canvas 2D com React como shell

Motivo:

- e suficiente para pixel art;
- conversa melhor com a referencia `pixel-agents`;
- e muito mais leve que adicionar Phaser;
- evita introduzir um engine novo no monorepo.

### Separacao de estado

O frontend deve ser dividido em dois mundos:

- `roomStore`: snapshot operacional vindo do boot HTTP e dos deltas realtime;
- `sceneRuntime`: estado efemero da animacao, mantido apenas pelo engine.

Regra de render:

- React re-renderiza HUD, filtros, sheet, rails e detalhes;
- o canvas redesenha a cena sem depender do ciclo de render do React;
- `useSyncExternalStore` e a base segura para ler a store externa com snapshots estaveis e imutaveis;
- React state comum nao deve carregar posicao, frame, pathfinding nem pulse visual dos agentes.

### O que manter em DOM normal

- HUD
- drawers/sheets
- timeline
- tooltips
- filtros

### O que vai para canvas

- sala
- tiles
- personagens
- estacoes
- animacoes de estado
- pequenos efeitos visuais

### Camadas fixas da cena

Recomendacao para V1:

- `background layer`: chao, mesas, decoracao e elementos estaticos;
- `stations layer`: monitores, indicadores, lampadas e micro-filas;
- `agents layer`: personagens andando, sentando, levando cards simbolicos;
- `effects layer`: alertas, baloes, glow, pulse e particulas leves.

HUD e timeline devem continuar em DOM normal, nao em canvas.

### Assets, loop e performance

Regras recomendadas:

- atlas e spritesheets preparados no load;
- conversao para `ImageBitmap` com `createImageBitmap()` durante o bootstrap dos assets;
- nada de `drawImage()` redimensionando sprite em loop quente;
- coordenadas inteiras para preservar pixel-perfect e evitar subpixel blur;
- `requestAnimationFrame()` como loop principal, sempre usando o `timestamp` para delta-time;
- quando a cena estiver calma, reduzir trabalho de partes nao criticas;
- em aba oculta, pausar quase tudo da cena e manter apenas HUD/realtime operacional;
- `OffscreenCanvas` entra como otimizacao de fase 2 se o profiling justificar, nao como requisito da V1;
- a migracao para `OffscreenCanvas` ou worker so deve acontecer depois de medir frame time, long tasks e saturacao da main thread.

### Orcamento de frame

Mesmo sem engine pesada, a sala precisa operar com orcamento explicito.

Regras:

- limitar trabalho por frame em cada layer;
- priorizar `agents` e `effects` quando houver disputa de CPU;
- derrubar detalhe visual antes de derrubar legibilidade operacional;
- tratar frame budget como parte do contrato de UX, nao como otimizacao tardia.

---

## 2. Estrutura Frontend Sugerida

Estrutura revisada recomendada para V1:

```text
apps/web/src/modules/event-operations/
  EventOperationsRoomPage.tsx
  api.ts
  types.ts
  hooks/
    useEventOperationsBoot.ts
    useEventOperationsRealtime.ts
  stores/
    room-store.ts
    timeline-store.ts
    hud-store.ts
  components/
    OperationsRoomCanvas.tsx
    OperationsHud.tsx
    OperationsTimelineRail.tsx
    OperationsDetailSheet.tsx
    OperationsAlertStack.tsx
  engine/
    scene-runtime.ts
    event-translator.ts
    renderer.ts
    assets.ts
    sprites.ts
  assets/
```

Ponto-chave:

- o evento do backend nao vai direto para a sprite;
- ele passa por `event-translator.ts`, que funciona como camada de direcao cenica e converte um delta operacional em comandos visuais;
- exemplo: `media.moderation.pending` vira `spawnCard(human_review)`, `setAgentMood(moderator, busy)` e `setDeskLamp(human_review, warning)`.


---

## 3. Fullscreen Sem Sidebar

Recomendacao de UX:

- rota fora do `AdminLayout`;
- entrar em fullscreen sob gesto do usuario;
- esconder cursor quando o modo `kiosk` estiver ativo;
- wake lock opcional;
- atalho para abrir detalhe da estacao selecionada.

Esses comportamentos ja foram validados como padrao aceitavel no wall player.

### Entrada em cena do fullscreen

Fullscreen nao deve ser tratado como detalhe tecnico. Ele precisa ter UX explicita.

Fluxo recomendado:

- botao visivel `Entrar em modo sala`;
- mini overlay inicial com `3` dicas curtas;
- indicacao clara de como sair com `Esc`;
- fallback elegante quando `requestFullscreen()` for negado ou encerrado pelo navegador.

O objetivo e evitar que uma falha normal do browser pareca bug do produto.

### Lifecycle operacional da tela

Essa pagina precisa tratar o lifecycle real do navegador como requisito de produto, nao como detalhe de implementacao.

Regras:

- `requestFullscreen()` so deve ser chamado sob gesto explicito do usuario;
- escutar `fullscreenchange` para saber se a sala entrou ou saiu do modo fullscreen;
- escutar `visibilitychange` para pausar quase toda a cena quando a aba ficar oculta;
- quando a aba voltar, recalcular tempo acumulado, revalidar conexao e comparar versoes do snapshot;
- se wake lock estiver habilitado, reacquirir quando a pagina voltar a ficar ativa e visivel;
- o loop da cena deve assumir que `requestAnimationFrame()` pode ser pausado ou degradado em abas ocultas;
- se `prefers-reduced-motion` estiver ativo, a sala deve reduzir movimento nao essencial sem esconder sinais operacionais.

Estados de UX obrigatorios:

- `Reconectando...`;
- `Sincronizando a sala...`;
- `Sala degradada: dados ao vivo indisponiveis`;
- `Resync concluido`.

Reconnect e resync nao sao apenas infraestrutura. Eles precisam ser experiencia legivel.

---

## Proposta Tecnica De Backend

## 1. Estrutura Sugerida

Estrutura revisada recomendada para V1:

```text
apps/api/app/Modules/EventOperations/
  Actions/
    BuildEventOperationsRoomAction.php
    AppendEventOperationEventAction.php
    BuildEventOperationsReplayAction.php
  Data/
    EventOperationsRoomData.php
    EventOperationsStationData.php
    EventOperationsDeltaData.php
    EventOperationTimelineEntryData.php
  Events/
    EventOperationsStationDeltaBroadcast.php
    EventOperationsTimelineAppendedBroadcast.php
    EventOperationsAlertCreatedBroadcast.php
    EventOperationsHealthChangedBroadcast.php
  Http/
    Controllers/
      EventOperationsController.php
    Resources/
      EventOperationsRoomResource.php
  Listeners/
    ProjectInboundToOperations.php
    ProjectMediaRunsToOperations.php
    ProjectModerationToOperations.php
    ProjectWallToOperations.php
    ProjectFeedbackToOperations.php
  Models/
    EventOperationEvent.php
    EventOperationSnapshot.php
  Providers/
    EventOperationsServiceProvider.php
  README.md
  routes/
    api.php
```


---

## 2. Entidades Tocadas

Mesmo com modulo novo, a feature toca entidades ja existentes:

- `Event`
- `EventChannel`
- `InboundMessage`
- `EventMedia`
- `MediaProcessingRun`
- `EventWallSetting`
- `WallPlayerRuntimeStatus`
- `Activity`
- feedback tables de WhatsApp e Telegram

Por isso a projection precisa ser derivada e nao dona do fluxo.

---

## 3. Query Strategy

TanStack Query nao deve dirigir a animacao da sala.

Em uma control room fullscreen, deixar a query principal com defaults de refetch agressivo tende a causar pisca, trabalho extra e sensacao de descontinuidade.

Nota de versao:

- o monorepo atual usa `@tanstack/react-query` `^5.83.0`, entao a nomenclatura correta aqui e `gcTime`;
- se esse desenho for portado para um projeto em v4, o equivalente historico de `gcTime` e `cacheTime`.

### Boot

`GET /events/{event}/operations/room` deve devolver o snapshot operacional inicial.

Uso recomendado:

- `useQuery()` apenas para boot da room;
- `staleTime` alto para a tela live;
- `refetchOnWindowFocus: false`;
- `refetchOnReconnect: false` na tela principal;
- `refetchOnMount: false` quando ja houver boot coerente em memoria;
- `select` para recortar payloads grandes;
- `notifyOnChangeProps` quando o componente consome poucas propriedades.

Payload recomendado:

- `schema_version`, `snapshot_version`, `latest_event_sequence`, `timeline_cursor` e `server_time`;
- resumo do evento;
- estacoes com estado atual;
- counters por fase;
- itens recentes por estacao;
- alertas;
- snapshot do wall;
- estado da conexao/health;
- trecho inicial da timeline.

### Timeline incremental

`GET /events/{event}/operations/timeline?cursor=...`

Deve devolver:

- blocos append-only;
- filtros por estacao, severidade e media.

Uso recomendado:

- `useInfiniteQuery()` ou cursor manual;
- pagina inferior focada em historico e replay, nao em dirigir a sala ao vivo;
- o rail pode aplicar `setQueryData()` para pequenos patches coerentes;
- qualquer patch de cache deve ser imutavel; nunca mutar `oldData` in-place;
- `invalidateQueries()` fica reservado para reconnect, version mismatch ou resync manual.

### Realtime

O websocket atualiza diretamente as stores externas:

- `roomStore`;
- `timelineStore`;
- `hudStore`.

Todo delta deve carregar pelo menos:

- `schema_version`;
- `snapshot_version`;
- `event_sequence`;
- `timeline_cursor`;
- `server_time`.

Regra central:

- Query serve para boot, history e fallback;
- o realtime atualiza a store local;
- o canvas consome o runtime efemero derivado do tradutor de eventos;
- posicao, frame, pathfinding, bounce e blink nao devem morar no cache do Query.

---

## 4. Fallback

Se o realtime cair:

- polling leve de `room` a cada 10-15s;
- timeline continua atualizando por cursor manual;
- HUD mostra modo degradado.

Comportamento visual recomendado no degradado:

- menos movimento;
- mais indicadores estaticos;
- menos efeitos;
- mais foco em saude global, alertas e wall health;
- mensagem clara de que a sala perdeu live sem parecer travada.

Esse padrao ja existe hoje em `Wall`.

---

## 5. Escala Operacional Prevista

V1 pode nascer em topologia simples, mas o desenho do canal e da projection ja deve assumir escala futura.

Trilha recomendada:

- manter o canal `event.{eventId}.operations` stateless do ponto de vista do servidor websocket;
- tratar boot HTTP e resync como mecanismos normais, nao como excecao;
- se a superficie ganhar adocao alta, o Reverb suporta escala horizontal com Redis Pub/Sub e multiplos servidores atras de load balancer;
- a projection e o contrato versionado devem continuar validos independentemente de qual node entregou o delta.

Isso evita retrabalho de topologia depois que a tela virar diferencial comercial.

---

## Validacao Contra Docs Oficiais

Esta proposta foi revisada contra a documentacao oficial das libs e APIs que sustentam a V1 recomendada.

### React

Referencia oficial:

- [React - useSyncExternalStore](https://react.dev/reference/react/useSyncExternalStore)

Implicacao para a arquitetura:

- existe um hook proprio para assinar uma store externa;
- `getSnapshot()` precisa devolver um snapshot estavel e imutavel enquanto a store nao mudar;
- isso reforca a separacao entre `roomStore` operacional e `sceneRuntime` efemero.

Observacao:

- a documentacao atual do React esta em `19.2`, mas a API `useSyncExternalStore` ja faz parte do React `18`, que e a base atual do projeto.

### TanStack Query

Referencias oficiais:

- [TanStack Query - Important Defaults](https://tanstack.com/query/latest/docs/framework/react/guides/important-defaults)
- [TanStack Query - useQuery](https://tanstack.com/query/latest/docs/framework/react/reference/useQuery)
- [TanStack Query - QueryClient](https://tanstack.com/query/latest/docs/reference/QueryClient)
- [TanStack Query - Migrating to v5](https://tanstack.com/query/latest/docs/framework/react/guides/migrating-to-v5)

Implicacao para a arquitetura:

- queries sao `stale` por default;
- `refetchOnMount` tambem e `true` por default para dado stale;
- `refetchOnWindowFocus` e `refetchOnReconnect` sao `true` por default;
- `gcTime` padrao e `5` minutos;
- `select` e `notifyOnChangeProps` ajudam a reduzir re-render desnecessario;
- `queryClient.setQueryData()` e sincronico, mas deve ser usado de forma imutavel e pontual.

Observacao de versao:

- o monorepo atual esta em TanStack Query v5, entao `gcTime` esta correto;
- a documentacao oficial de migracao registra que o nome antigo em v4 era `cacheTime`.

Conclusao pratica:

- Query entra no boot, timeline e fallback;
- Query nao deve ser a engine da sala ao vivo.

### Laravel Broadcasting

Referencia oficial:

- [Laravel 12.x - Events](https://laravel.com/docs/12.x/events)
- [Laravel 12.x - Broadcasting](https://laravel.com/docs/12.x/broadcasting)
- [Laravel 12.x - Queues](https://laravel.com/docs/12.x/queues)

Implicacao para a arquitetura:

- `broadcastAs()` permite nomes curtos e estaveis de evento;
- `broadcastWith()` permite payload enxuto em vez de serializacao ampla de modelo;
- `broadcastWhen()` evita barulho no canal;
- `ShouldBroadcast` entra em fila por padrao;
- `ShouldBroadcastNow` deve ficar reservado para casos realmente criticos;
- eventos disparados dentro de transacoes podem sair antes do commit, e a doc recomenda `ShouldDispatchAfterCommit` quando o broadcast depende do estado persistido;
- `ShouldDispatchAfterCommit` so despacha depois do commit e evita que um evento va para a UI se a transacao falhar;
- a documentacao de filas tambem cobre `after_commit=true` na conexao como regra sistemica para jobs, listeners e broadcasts dependentes de transacao;
- presence channels suportam awareness de usuarios conectados com callbacks como `here`, `joining` e `leaving`.

Conclusao pratica:

- boot completo por HTTP;
- websocket apenas com diffs;
- broadcaster pequeno, semantico e after-commit.

### Canvas e Web APIs

Referencias oficiais:

- [MDN - Optimizing canvas](https://developer.mozilla.org/en-US/docs/Web/API/Canvas_API/Tutorial/Optimizing_canvas)
- [MDN - prefers-reduced-motion](https://developer.mozilla.org/en-US/docs/Web/CSS/@media/prefers-reduced-motion)
- [MDN - createImageBitmap](https://developer.mozilla.org/en-US/docs/Web/API/Window/createImageBitmap)
- [MDN - OffscreenCanvas](https://developer.mozilla.org/en-US/docs/Web/API/OffscreenCanvas)
- [MDN - requestAnimationFrame](https://developer.mozilla.org/en-US/docs/Web/API/Window/requestAnimationFrame)
- [MDN - Page Visibility API](https://developer.mozilla.org/en-US/docs/Web/API/Page_Visibility_API)
- [MDN - Element.requestFullscreen()](https://developer.mozilla.org/en-US/docs/Web/API/Element/requestFullscreen)
- [MDN - Document fullscreenchange event](https://developer.mozilla.org/en-US/docs/Web/API/Document/fullscreenchange_event)
- [MDN - Screen Wake Lock API](https://developer.mozilla.org/en-US/docs/Web/API/Screen_Wake_Lock_API)
- [MDN - ARIA log role](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Roles/log_role)
- [MDN - ARIA status role](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Roles/status_role)
- [MDN - ARIA alert role](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Roles/alert_role)
- [Laravel Reverb - Scaling](https://laravel.com/docs/13.x/reverb#scaling)

Observacao:

- o projeto instalado usa `laravel/reverb` `^1.9`;
- a documentacao oficial publica de scaling esta atualmente sob a secao `13.x`, mas a topologia descrita continua relevante para a biblioteca usada aqui.

Implicacao para a arquitetura:

- pre-render e reuse de elementos repetidos fazem sentido para pixel art;
- `prefers-reduced-motion` reforca que movimento nao essencial deve poder ser reduzido;
- coordenadas inteiras e ausencia de scaling quente em `drawImage()` melhoram nitidez e custo;
- multiplos canvases fazem sentido para cenas complexas;
- `createImageBitmap()` e bom caminho para preparar sprites;
- `OffscreenCanvas` desacopla do DOM e pode ir para worker, mas isso deve entrar apenas se profiling pedir;
- `requestAnimationFrame()` segue sendo o loop certo e pode ser pausado em abas ocultas na maioria dos navegadores.
- `requestFullscreen()` e assincrono, exige ativacao do usuario e pode falhar, o que confirma fullscreen como acao explicita da UI com fallback honesto;
- fullscreen e wake lock precisam respeitar o lifecycle real do documento;
- `log` e o papel ARIA mais adequado para um rail live append-only em que novas entradas chegam no fim com ordem significativa;
- `status` e `alert` ajudam a tornar a camada live acessivel sem transformar toda atualizacao em ruido;
- Reverb ja documenta escala horizontal com Redis Pub/Sub, o que sustenta a trilha de crescimento proposta.

Conclusao pratica:

- V1 com canvas normal bem organizado;
- V2 com `OffscreenCanvas` e workers se o profiling realmente exigir.

---

## O Que Nao Recomendo Na V1

Nao recomendo para a V1:

- portar `WorkAdventure`;
- adotar Phaser;
- mapa livre com avatar manual;
- chat embutido na sala;
- proximity video;
- editor de escritorio/mapa;
- uma sprite por midia;
- usar React state para posicao ou frame de agente;
- deixar TanStack Query refetchar a sala inteira em foco ou reconnect;
- inferir estado operacional a partir do presence channel;
- broadcastar modelo Eloquent inteiro quando a UI so precisa de delta;
- cockpit com 20 paines tradicionais disfarcados de jogo.

Tambem nao recomendo que a tela vire brinquedo.

Ela precisa ser:

- bonita;
- memoravel;
- operacional.

Se o pixel art competir com a leitura de estado, o produto perde.

---

## Roadmap Recomendado

## Fase 0 - Spike de leitura

Objetivo:

- abrir a rota fullscreen;
- combinar endpoints existentes;
- validar linguagem visual;
- desenhar 5 a 7 estacoes fixas;
- validar tempo de compreensao da tela em poucos segundos.

Entrega:

- tela read-only;
- sem projection nova;
- sem replay real;
- com wall snapshot, moderation backlog e pipeline metrics.

Criterio de aceite:

- uma pessoa precisa entender em ate `5s` se a operacao esta saudavel, em atencao ou em risco.

Essa fase confirma UX, tempo de leitura e valor de produto com risco baixo.

---

## Fase 1 - Projection operacional

Objetivo:

- criar `EventOperations`;
- persistir `event_operation_events`;
- abrir `event.{eventId}.operations`;
- normalizar timeline e snapshot;
- separar `roomStore` de `sceneRuntime`;
- travar contrato de delta antes do polimento visual;
- consolidar gestos visuais inequivocos por estacao.

Entrega:

- live de verdade;
- timeline coerente;
- animacoes guiadas por evento real, mas coreografadas;
- historico util para "o que aconteceu".

Criterio de aceite:

- cada estacao precisa ter um gesto visual inequivoco;
- a cena precisa continuar legivel mesmo durante burst alto.

Essa e a fase que transforma a ideia em produto forte.

---

## Fase 2 - Replay e detalhes

Objetivo:

- replay de janela temporal;
- drill-down por item;
- abrir detalhes de `EventMedia`, falha, wall player ou feedback;
- introduzir `OffscreenCanvas` ou worker apenas se profiling justificar;
- priorizar explicabilidade operacional antes de cenografia extra.

Entrega:

- visao do agora;
- visao do recente;
- narrativa operacional completa.

---

## Fase 3 - Cenario e extras

Objetivo:

- polimento forte de assets;
- temas por tipo de evento;
- sala corporativa, festa, backstage, central de operacoes;
- opcionalmente layout configuravel.

Essa fase so deve entrar depois de a semantica visual e a projection estarem realmente boas.

---

## Validacao Executada Nesta Analise

Para nao deixar a recomendacao no nivel teorico, foi feita validacao direcionada no estado atual do workspace em `2026-04-11`.

### Backend

Comando executado:

```bash
cd apps/api
php artisan test tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Telegram/TelegramPrivateMediaIntakePipelineTest.php tests/Feature/ContentModeration/ContentModerationPipelineTest.php tests/Feature/MediaProcessing/MediaPipelineMetricsTest.php tests/Feature/Wall/WallDiagnosticsTest.php tests/Feature/Wall/WallLiveSnapshotTest.php tests/Feature/Events/EventJourneyControllerTest.php
```

Resultado:

- `44` testes passaram;
- `480` assertions passaram;
- intake por evento, Telegram privado, pipeline de safety, metricas de pipeline, diagnostics do wall, live snapshot do wall e projection da jornada seguiram verdes.

Leitura pratica:

- a base tecnica para uma sala de operacoes realmente existe;
- nao estamos propondo um produto em cima de modulos quebrados.
- os fluxos que alimentariam `EventOperations` seguem consistentes no backend atual.

Validacao estrutural adicional no codigo:

- `Wall` e `MediaProcessing` ja usam classes base com `ShouldDispatchAfterCommit`;
- a conexao `redis` em `apps/api/config/queue.php` esta configurada com `after_commit` vindo de env e default `true`, o que reforca a recomendacao arquitetural da doc.

### Frontend

Comando executado:

```bash
cd apps/web
npm run test -- src/lib/realtime.test.ts src/modules/events/event-media-flow-builder-architecture-characterization.test.ts src/modules/moderation/moderation-architecture.test.ts src/modules/wall/pages/EventWallManagerPage.test.tsx src/modules/wall/hooks/useWallRealtimeSync.test.tsx src/modules/wall/hooks/useWallPollingFallback.test.tsx src/modules/wall/player/runtime-profile.test.ts src/modules/wall/player/hooks/usePerformanceMode.test.ts src/modules/wall/player/components/WallPlayerRoot.test.tsx
```

Resultado:

- `9` arquivos passaram;
- `45` testes passaram;
- realtime base, fallback por polling, runtime profile, reduced-motion, wall manager e journey builder seguiram verdes.

Leitura pratica:

- o frontend atual ja comporta uma nova superficie operacional desse porte;
- a entrega nao depende de trocar framework ou de introduzir engine externa;
- a base atual ja possui referencia concreta para `visibilitychange`, wake lock, reduced motion e budget/performance mode no wall player.

Validacao estrutural adicional no codigo:

- `WallPlayerPage` ja pede fullscreen sob clique e reacquire wake lock em `visibilitychange`;
- `usePerformanceMode` ja observa `prefers-reduced-motion` e hardware mais fraco;
- `WallPlayerRoot.test.tsx` valida que o player reduz motion quando entra em modo de performance;
- `useWallRealtimeSync.test.tsx` e `useWallPollingFallback.test.tsx` sustentam a estrategia de live + fallback que inspirou esta arquitetura.

### Revalidacao adicional em `2026-04-12`

Backend:

```bash
cd apps/api
php artisan test tests/Feature/Wall/WallDiagnosticsTest.php tests/Feature/Wall/WallLiveSnapshotTest.php tests/Feature/MediaProcessing/MediaPipelineMetricsTest.php tests/Feature/ContentModeration/ContentModerationPipelineTest.php tests/Feature/Events/EventJourneyControllerTest.php
```

Resultado:

- `32` testes passaram;
- `334` assertions passaram;
- wall diagnostics, live snapshot, metricas de pipeline, safety pipeline e journey builder seguiram verdes.

Frontend:

```bash
cd apps/web
npm run test -- src/lib/realtime.test.ts src/modules/wall/hooks/useWallRealtimeSync.test.tsx src/modules/wall/hooks/useWallPollingFallback.test.tsx src/modules/wall/player/runtime-profile.test.ts src/modules/wall/player/hooks/usePerformanceMode.test.ts src/modules/wall/player/components/WallPlayerRoot.test.tsx
```

Resultado:

- `6` arquivos passaram;
- `18` testes passaram;
- realtime base, fallback, runtime profile, reduced motion e comportamento do wall player seguiram verdes.

Leitura pratica:

- a stack atual continua sustentando lifecycle, degraded mode, wake lock e reduced motion;
- a nova control room pode e deve reaproveitar esses padroes em vez de inventar um runtime paralelo.

---

## Veredito Final

### E possivel?

Sim.

### E coerente com a stack atual?

Sim.

### O produto precisa nascer como modulo proprio?

Sim. Recomendacao: `EventOperations`.

### Qual referencia seguir?

Seguir `pixel-agents` como inspiracao principal de apresentacao.

### O que fazer com `workadventure`?

Usar apenas como referencia conceitual de zonas e espaco vivo.

### O que eu faria agora?

1. travar o contrato da projection e dos deltas;
2. definir `roomStore` e `sceneRuntime` como estruturas separadas;
3. abrir a V0/V1 fullscreen fora do `AdminLayout` com canvas em camadas;
4. manter TanStack Query no boot/history e deixar o live no realtime;
5. so depois investir pesado em assets, replay e cenografia.

Essa ordem entrega valor rapido sem comprometer a arquitetura.

### Como executar sem perder controle

A execucao detalhada deve seguir o backlog por PR do plano complementar:

- `docs/architecture/event-realtime-virtual-office-execution-plan-2026-04-11.md`

Regra pratica:

- nenhum PR deve misturar contrato, projection, realtime e polimento visual;
- cada PR precisa nascer com testes red, fechar com green e declarar o slice backend/frontend;
- o `event-translator` so deve receber polimento visual depois de contrato, sequencia, resync e after-commit estarem testados.
