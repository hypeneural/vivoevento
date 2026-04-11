# Plano De Implementacao Da Sprint: Cockpit Do Telao Ao Vivo

Data do plano: 2026-04-08

## Objetivo

Entregar a primeira versao realmente operacional do novo cockpit do telao em `/events/:id/wall`, mantendo a stack atual e priorizando:

- topo mais vivo e util;
- ligacao clara entre insights do evento, palco, timeline recente e detalhe lateral;
- snapshot real do item atual do wall no palco principal;
- menos waterfall no carregamento;
- menos flicker e menos sensacao de painel "piscando";
- base pronta para evoluir preview real, monitor live e telemetria sem trocar a arquitetura.

## Documentacao base obrigatoria

Antes de implementar, toda pessoa da sprint deve alinhar a leitura com estes documentos:

- [telao-manager-stack-ux-analysis-2026-04-08.md](C:/laragon/www/eventovivo/docs/architecture/telao-manager-stack-ux-analysis-2026-04-08.md)
- [wall-websocket-live-sync-analysis.md](C:/laragon/www/eventovivo/docs/architecture/wall-websocket-live-sync-analysis.md)
- [wall-remote-control-pwa-analysis.md](C:/laragon/www/eventovivo/docs/architecture/wall-remote-control-pwa-analysis.md)
- [telao-ao-vivo-melhorias.md](C:/laragon/www/eventovivo/docs/architecture/telao-ao-vivo-melhorias.md)
- [telao-ao-vivo-implementation.md](C:/laragon/www/eventovivo/docs/architecture/telao-ao-vivo-implementation.md)
- [wall-improvements-validation-2026-04-08.md](C:/laragon/www/eventovivo/docs/architecture/wall-improvements-validation-2026-04-08.md)

## Revalidacao apos ajustes externos em 2026-04-10

Leitura objetiva:

- o checklist desta sprint do cockpit continua fechado;
- os ajustes externos mais relevantes no modulo `Wall` foram:
  - `puzzle` promovido a layout oficial com `theme_config` e capabilities proprias;
  - diagnostico enriquecido com runtime de video e contadores de `board`;
  - prefetch da simulacao por `theme fingerprint` para reduzir troca visivel da previsao.

Testes direcionados executados nesta revalidacao:

- backend:
  - `cd apps/api && php artisan test --filter=WallDiagnosticsTest`
  - `9` testes passando
  - `cd apps/api && php artisan test --filter=WallInsightsTest`
  - `7` testes passando
  - `cd apps/api && php artisan test --filter=WallLiveSnapshotTest`
  - `7` testes passando
  - `cd apps/api && php artisan test --filter=WallOptionsPuzzleLayoutTest`
  - `2` testes passando
  - `cd apps/api && php artisan test --filter=WallSettingsThemeConfigTest`
  - `3` testes passando
- frontend:
  - `cd apps/web && npm run test -- src/modules/wall/pages/EventWallManagerPage.test.tsx src/modules/wall/components/manager/diagnostics/WallPlayerRuntimeCard.test.tsx src/modules/wall/wall-query-options.test.ts src/modules/wall/player/hooks/useStageGeometry.test.ts src/modules/wall/player/themes/puzzle/PuzzleLayout.test.tsx src/modules/wall/player/themes/puzzle/premium-motion.test.tsx`
  - `29` testes passando
  - `cd apps/web && npm run test -- src/modules/wall`
  - `299` testes passando
  - `cd apps/web && npm run type-check`
  - sem erros

Observacao importante:

- `cd apps/api && php artisan test --filter=Wall` nao esta 100% verde nesta revalidacao;
- hoje existem `2` falhas fora do cockpit UI e ligadas ao pipeline de variantes de video:
  - `Tests\\Unit\\Modules\\MediaProcessing\\MediaVariantGeneratorServiceTest`
  - `Tests\\Feature\\MediaProcessing\\MediaPipelineJobsTest`
- portanto, o cockpit do manager continua estavel, mas o baseline backend wall-adjacent nao deve mais ser descrito como totalmente verde sem essa correcao.

## Baseline revalidado nesta rodada

Antes de atualizar este plano, o baseline atual do modulo wall foi revalidado com testes automatizados existentes:

- frontend:
  - `cd apps/web && npm run test -- src/modules/wall/pages/EventWallManagerPage.test.tsx`
- backend:
  - `cd apps/api && php artisan test --filter=WallDiagnosticsTest`

Resultado:

- `EventWallManagerPage.test.tsx`
  - `6` testes passando
- `WallDiagnosticsTest`
  - `4` testes passando

Leitura:

- o manager atual continua funcional no fluxo principal;
- diagnostico, heartbeat e simulacao continuam consistentes;
- a sprint pode partir de uma base estavel em vez de corrigir uma regressao previa.

## Implementado e validado nesta rodada

Primeira fatia executada de acordo com este plano:

- backend:
  - `GET /events/{event}/wall/insights`
  - `WallInsightsService`
  - `WallInsightsResource`
  - testes de feature do contrato
- frontend:
  - tipos `ApiWallInsightsResponse`
  - `queryKeys.wall.insights`
  - `wall-query-options.ts`
  - `useWallTopInsights`
  - `useWallSelectedMedia`
  - `WallTopInsightsRail`
  - `WallTopContributorCard`
  - `WallTotalMediaCard`
  - `WallLiveMediaTimelineStrip`
  - `WallRecentMediaChip`
  - integracao inicial com `EventWallManagerPage`

Testes automatizados executados nesta rodada:

- backend:
  - `cd apps/api && php artisan test --filter=WallInsightsTest`
  - `3` testes passando
  - `36` assertions
- backend regressao do modulo:
  - `cd apps/api && php artisan test --filter=Wall`
  - `57` testes passando
- frontend:
  - `cd apps/web && npm run test -- src/modules/wall/hooks/useWallTopInsights.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx`
  - `10` testes passando
- frontend:
  - `cd apps/web && npm run type-check`
  - sem erros

Achados reais da implementacao:

- `totals.displayed` saiu de `null` na `Etapa 3`:
  - o backend agora persiste historico agregado em `wall_display_counters`;
  - a contagem nasce do `heartbeat` com `current_item_id` + `current_item_started_at`;
  - a trilha tem dedupe para nao contar a mesma exibicao duas vezes em reconexao ou repeticao do mesmo sinal.
- o trilho recente ficou melhor com `ScrollArea` e clique explicito:
  - sem autoplay;
  - sem deslocamento automatico brigando com leitura;
  - com ligacao direta entre item do topo e palco atual.
- a primeira ligacao entre topo e palco ja ficou validada:
  - clicar numa midia recente destaca o item no trilho;
  - o palco passa a refletir a midia escolhida;
  - o detalhe lateral dedicado continua como proxima fatia, nao como pre-requisito para este bloco.

Segunda fatia executada de acordo com este plano:

- frontend:
  - `useWallRealtimeSync`
  - `useWallPollingFallback`
  - `WallRecentMediaDetailsSheet`
  - integracao do polling leve nas queries de evento, settings, diagnostics e insights
  - detalhe responsivo por `Sheet` no desktop e `Drawer` no mobile
  - polimento visual leve no trilho e no palco

Testes automatizados executados nesta segunda fatia:

- frontend:
  - `cd apps/web && npm run test -- src/modules/wall/hooks/useWallPollingFallback.test.tsx src/modules/wall/hooks/useWallRealtimeSync.test.tsx src/modules/wall/components/manager/recent/WallRecentMediaDetailsSheet.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx`
  - `17` testes passando
- frontend:
  - `cd apps/web && npm run type-check`
  - sem erros

Achados reais desta segunda fatia:

- o manager agora opera em modo hibrido de fato:
  - `connected` e `connecting` desligam polling;
  - `disconnected` e `offline` ligam polling leve;
  - reconexao invalida `settings`, `diagnostics`, `insights` e `event.detail`.
- o detalhe da midia recente entrou sem criar uma segunda navegacao:
  - `Sheet` no desktop;
  - `Drawer` no mobile;
  - selecao do trilho continua estavel durante refetch.
- o polimento visual ficou dentro do orcamento de animacao definido no plano:
  - transicao curta no palco com `AnimatePresence`;
  - badge `Agora` nos itens muito recentes;
  - sem autoplay no trilho;
  - sem animar listas inteiras ou KPIs.

Terceira fatia executada de acordo com este plano:

- frontend:
  - `WallCommandToolbar`
  - `useWallRecentMediaTimeline`
  - navegacao por setas na toolbar principal
  - tabs do palco com ativacao automatica em conteudo pre-carregado
  - tabs do inspector com ativacao manual
  - pausa do trilho recente em hover e foco
  - navegacao por teclado no trilho recente
  - manutencao do item selecionado visivel no trilho

Testes automatizados executados nesta terceira fatia:

- frontend:
  - `cd apps/web && npm run test -- src/modules/wall/components/manager/layout/WallCommandToolbar.test.tsx src/modules/wall/hooks/useWallRecentMediaTimeline.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx`
  - `16` testes passando
- frontend regressao do modulo:
  - `cd apps/web && npm run test -- src/modules/wall`
  - `183` testes passando
- frontend:
  - `cd apps/web && npm run type-check`
  - sem erros
- backend regressao do modulo:
  - `cd apps/api && php artisan test --filter=Wall`
  - `57` testes passando

Achados reais desta terceira fatia:

- a toolbar principal do manager agora segue o contrato de interacao previsto:
  - `Tab` entra no grupo;
  - `ArrowLeft`, `ArrowRight`, `Home` e `End` navegam entre os comandos;
  - a acao principal continua no mesmo trilho visual do header.
- a estrategia final das tabs ficou fechada assim:
  - palco com ativacao automatica porque o conteudo relevante ja esta pre-carregado;
  - inspector com ativacao manual para evitar troca acidental de contexto em navegacao por teclado.
- o trilho recente deixou de ser apenas clicavel:
  - aceita teclado;
  - pausa em hover e foco;
  - mantem o item selecionado visivel sem recentrar de forma agressiva.
- a coluna da direita saiu do modelo de inspector infinito e passou a operar por abas:
  - `Fila`
  - `Aparencia`
  - `Anuncios`

Quarta fatia executada de acordo com este plano:

- backend:
  - enriquecimento real de `sequence_preview` com:
    - `preview_url`
    - `source_type`
- frontend:
  - `WallHeroStage`
  - `WallDraftPreviewCard`
  - `WallUpcomingTimeline`
  - `WallQueueTab`
  - integracao do preview inicial do rascunho sem `iframe`
  - integracao de thumbnails e origem em `Proximas fotos`
  - reducao do peso direto de `EventWallManagerPage.tsx`

Testes automatizados executados nesta quarta fatia:

- frontend:
  - `cd apps/web && npm run test -- src/modules/wall/pages/EventWallManagerPage.test.tsx`
  - `11` testes passando
- frontend regressao do modulo:
  - `cd apps/web && npm run test -- src/modules/wall`
  - `185` testes passando
- frontend:
  - `cd apps/web && npm run type-check`
  - sem erros
- backend regressao do modulo:
  - `cd apps/api && php artisan test --filter=Wall`
  - `57` testes passando

Achados reais desta quarta fatia:

- o palco agora tem uma previa inicial de rascunho sem `iframe`:
  - ela abriu o caminho para reaproveitar o renderer real do player;
  - mas ainda nao fazia isso por completo nesta fase.
- `Proximas fotos` deixou de ser apenas texto:
  - cada item agora pode mostrar thumb;
  - origem normalizada;
  - badges de reprise e destaque.
- a quebra da pagina principal entrou em fase real:
  - o palco ja foi extraido para `WallHeroStage`;
  - a fila do inspector saiu para `WallQueueTab`;
  - `EventWallManagerPage.tsx` caiu de `1864` para `1593` linhas nesta rodada.
- a extracao do inspector ainda nao tinha terminado por completo nesta fatia:
  - `Aparencia` e `Anuncios` ficaram como proxima rodada;
  - a reducao de tamanho ja foi material sem reabrir risco desnecessario no formulario.

Quinta fatia executada de acordo com este plano:

- frontend:
  - `WallAppearanceTab`
  - `WallAdsTab`
  - integracao dos dois tabs na pagina principal
  - remocao dos blocos inline restantes do inspector
  - nova reducao do peso direto de `EventWallManagerPage.tsx`

Testes automatizados executados nesta quinta fatia:

- frontend:
  - `cd apps/web && npm run test -- src/modules/wall/components/manager/inspector/WallAppearanceTab.test.tsx src/modules/wall/components/manager/inspector/WallAdsTab.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx`
  - `13` testes passando
- frontend regressao do modulo:
  - `cd apps/web && npm run test -- src/modules/wall`
  - `187` testes passando
- frontend:
  - `cd apps/web && npm run type-check`
  - sem erros
- backend regressao do modulo:
  - `cd apps/api && php artisan test --filter=Wall`
  - `57` testes passando

Achados reais desta quinta fatia:

- a extracao do inspector fechou o escopo previsto para esta sprint:
  - `WallAppearanceTab` entrou na pagina principal;
  - `WallAdsTab` entrou na pagina principal;
  - o fluxo de salvar, subir anuncio, reordenar e remover criativo continuou verde nos testes da pagina.
- o arquivo principal deixou de carregar os dois maiores blocos inline restantes:
  - `EventWallManagerPage.tsx` caiu de `1593` para `1215` linhas;
  - a pagina agora ficou mais proxima de orquestracao do que de formulario bruto.
- os testes de caracterizacao dos tabs extraidos agora seguram o contrato dos componentes fora da pagina:
  - `WallAppearanceTab.test.tsx`
  - `WallAdsTab.test.tsx`

Sexta fatia executada de acordo com este plano:

- frontend:
  - `WallPreviewCanvas`
  - `WallPlayerRuntimeCard`
  - integracao do preview com primitives reais do player:
    - `LayoutRenderer`
    - `BrandingOverlay`
    - `FeaturedBadge`
    - `SideThumbnails`
  - `Proximas fotos` convertida em timeline horizontal com `ScrollArea`
  - cards de player com cor por saude operacional
  - nova reducao do peso direto de `EventWallManagerPage.tsx`

Testes automatizados executados nesta sexta fatia:

- frontend focado:
  - `cd apps/web && npm run test -- src/modules/wall/components/manager/stage/WallPreviewCanvas.test.tsx src/modules/wall/components/manager/diagnostics/WallPlayerRuntimeCard.test.tsx src/modules/wall/components/manager/stage/WallUpcomingTimeline.test.tsx src/modules/wall/components/manager/stage/WallDraftPreviewCard.test.tsx`
  - `5` testes passando
- frontend pagina:
  - `cd apps/web && npm run test -- src/modules/wall/pages/EventWallManagerPage.test.tsx`
  - `11` testes passando
- frontend regressao do modulo:
  - `cd apps/web && npm run test -- src/modules/wall`
  - `190` testes passando
- frontend:
  - `cd apps/web && npm run type-check`
  - sem erros
- backend regressao do modulo:
  - `cd apps/api && php artisan test --filter=Wall`
  - `57` testes passando

Achados reais desta sexta fatia:

- a `Previa do rascunho` deixou de ser uma composicao paralela do manager:
  - agora reaproveita renderer e overlays reais do player;
  - continua sem `iframe`;
  - continua desacoplada de clock e runtime ao vivo.
- `Proximas fotos` mudou de lista vertical para timeline horizontal:
  - caixas compactas por item;
  - thumb;
  - origem;
  - badges de reprise e destaque;
  - scroll lateral mais condizente com leitura sequencial da fila.
- o bloco de player ficou mais legivel operacionalmente:
  - a cor do card agora acompanha a saude do player;
  - isso reduz o custo de leitura de `Ultimo sinal` e `Sem conexao` em diagnostico denso.
- a pagina principal voltou a encolher sem reabrir risco no fluxo:
  - `EventWallManagerPage.tsx` caiu de `1215` para `936` linhas.

Setima fatia executada de acordo com este plano:

- backend:
  - `GET /events/{event}/wall/live-snapshot`
  - `WallLiveSnapshotService`
  - `WallLiveSnapshotResource`
  - resolvedor unico de `layout_hint` para manager e simulacao
  - enriquecimento real de `sequence_preview` com:
    - `caption`
    - `layout_hint`
- frontend:
  - `getEventWallLiveSnapshot`
  - `useWallLiveSnapshot`
  - integracao do snapshot real do wall em `WallHeroStage`
  - copy operacional no `WallPlayerRuntimeCard`
  - badges de layout e caption na timeline de `Proximas fotos`

Testes automatizados executados nesta setima fatia:

- backend focado:
  - `cd apps/api && php artisan test --filter=WallLiveSnapshotTest`
  - `2` testes passando
- backend focado:
  - `cd apps/api && php artisan test --filter=WallDiagnosticsTest`
  - `4` testes passando
- frontend focado:
  - `cd apps/web && npm run test -- src/modules/wall/hooks/useWallLiveSnapshot.test.tsx src/modules/wall/hooks/useWallPollingFallback.test.tsx src/modules/wall/hooks/useWallRealtimeSync.test.tsx src/modules/wall/components/manager/diagnostics/WallPlayerRuntimeCard.test.tsx src/modules/wall/components/manager/stage/WallUpcomingTimeline.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx`
  - `24` testes passando
- frontend regressao do modulo:
  - `cd apps/web && npm run test -- src/modules/wall`
  - `193` testes passando
- frontend:
  - `cd apps/web && npm run type-check`
  - sem erros
- backend regressao do modulo:
  - `cd apps/api && php artisan test --filter=Wall`
  - `59` testes passando

Achados reais desta setima fatia:

- o palco agora ja recebe um `live snapshot` real do wall:
  - item atual;
  - player mais recente ainda online;
  - origem;
  - caption;
  - `layoutHint`.
- a simulacao deixou de ficar muda demais para operacao:
  - `caption` entrou no `sequence_preview`;
  - `layout_hint` entrou com o mesmo resolvedor de layout usado nesta fase para o monitor do manager.
- o card de player ficou menos tecnico no ponto de entrada:
  - `Ultimo envio na tela` virou `Remetente atual`;
  - `Fotos` virou `Midias carregadas`;
  - `Uso do cache` virou `Aproveitamento do cache`;
  - `Espaco local` virou `Espaco no navegador`.
- a timeline de `Proximas fotos` ficou mais util para leitura rapida:
  - agora exibe `caption` quando existir;
  - mostra o `layout` previsto em badge operacional;
  - continua horizontal e com scroll leve.

Oitava fatia executada de acordo com este plano:

- backend:
  - evento privado `wall.runtime.snapshot.updated`
  - persistencia de `current_item_started_at` por player
  - `advancedAt` no payload de `liveSnapshot`
- frontend:
  - atualizacao direta do cache de `liveSnapshot` via evento dedicado
  - `WallAdvanceClock`
  - `WallPlayerDetailsSheet`
  - CTA de detalhe expandido no `WallPlayerRuntimeCard`
  - integracao do detalhe expandido e do clock no `EventWallManagerPage`

Testes automatizados executados nesta oitava fatia:

- backend focado:
  - `cd apps/api && php artisan test --filter=WallLiveSnapshotTest`
  - `3` testes passando
- backend focado:
  - `cd apps/api && php artisan test --filter=WallDiagnosticsTest`
  - `5` testes passando
- frontend focado:
  - `cd apps/web && npm run test -- src/modules/wall/components/manager/stage/WallAdvanceClock.test.tsx src/modules/wall/components/manager/diagnostics/WallPlayerDetailsSheet.test.tsx src/modules/wall/hooks/useWallRealtimeSync.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx`
  - `20` testes passando
- frontend regressao do modulo:
  - `cd apps/web && npm run test -- src/modules/wall`
  - `199` testes passando
- frontend:
  - `cd apps/web && npm run type-check`
  - sem erros
- backend regressao do modulo:
  - `cd apps/api && php artisan test --filter=Wall`
  - `62` testes passando

Achados reais desta oitava fatia:

- o manager agora recebe um evento dedicado para snapshot ao vivo:
  - `wall.runtime.snapshot.updated`;
  - ele atualiza o cache de `liveSnapshot` diretamente;
  - deixa de depender apenas de `invalidateQueries` para esse caso.
- o palco agora ganhou um relogio operacional de troca:
  - ele usa `advancedAt` do snapshot;
  - estima o tempo restante com base em `interval_ms`;
  - congela a leitura quando o wall entra em pausa.
- o diagnostico por player ganhou um segundo nivel menos tecnico:
  - o card continua resumido para varredura rapida;
  - o detalhe expandido abre em `Sheet` no desktop;
  - o detalhe expandido abre em `Drawer` no mobile;
  - a copy passou a orientar intervencao em vez de despejar termos tecnicos.
- a pagina principal continuou abaixo do teto da sprint:
  - `EventWallManagerPage.tsx` esta em `958` linhas nesta fase;
  - o aumento foi pequeno e veio de monitor ao vivo e detalhe expandido, nao de regressao estrutural.

## Resultado esperado ao fim da sprint

Ao final desta sprint, a tela `/events/:id/wall` deve permitir:

- visualizar no topo um trilho vivo com:
  - `Quem mais enviou`
  - `Total de midias`
  - `Ultimas chegadas`
- selecionar uma midia recente e refletir isso no palco e no detalhe lateral;
- acompanhar no palco o item real que o wall esta exibindo neste momento;
- acompanhar no palco a proxima troca prevista com base no snapshot ao vivo;
- carregar os insights com um unico endpoint agregado;
- operar com realtime hibrido:
  - WebSocket como principal;
  - polling leve apenas em degradacao;
  - invalidacao geral ao reconectar;
- reagir ao evento dedicado de snapshot sem depender apenas de refetch;
- manter a interface estavel durante refetch com `placeholderData`;
- usar `Skeleton` apenas no primeiro paint;
- manter copy menos tecnica e mais clara para operador leigo;
- preservar responsividade sem criar uma segunda pagina especifica.

## Escopo desta sprint

### Entra agora

- `GET /events/{event}/wall/insights`
- `GET /events/{event}/wall/live-snapshot`
- `wall.runtime.snapshot.updated`
- `WallTopInsightsRail`
- `WallTopContributorCard`
- `WallTotalMediaCard`
- `WallLiveMediaTimelineStrip`
- `WallRecentMediaDetailsSheet`
- `WallPlayerDetailsSheet`
- `WallAdvanceClock`
- `useWallLiveSnapshot`
- politica de cache por volatilidade
- `useWallPollingFallback`
- `useWallRealtimeSync`
- ligacao entre timeline recente, palco e detalhe lateral
- copy menos tecnica no topo e na area de recentes

### Nao entra agora

- iframe como preview principal
- refatoracao total de todas as abas do inspector
- monitor live autoritativo completo por player
- charts avancados de operacao
- presence channel para operador
- redesenho completo do player publico

## Principios de implementacao

### 1. Testes antes do codigo

Esta sprint deve seguir `TTD` na pratica:

1. escrever o teste automatizado do comportamento esperado;
2. rodar o teste e confirmar falha;
3. implementar o minimo para fazer passar;
4. refatorar sem quebrar o contrato;
5. rodar a suite do bloco;
6. rodar a regressao do modulo wall antes de fechar o bloco.

### 2. Linguagem operacional

Na interface principal, evitar termos como:

- sender key
- runtime
- stale
- payload
- fairness
- disabled

Preferir:

- remetente
- exibicao
- cache local
- dados do painel
- equilibrio da fila
- parado

### 3. Performance por padrao

Toda tarefa deve respeitar:

- `placeholderData` para refetch visualmente estavel;
- `Skeleton` so no primeiro carregamento;
- thumbs leves no topo;
- polling desligado quando o canal estiver saudavel;
- evitar rerender grande da pagina inteira por selecao local;
- preferir componentes pequenos e especializados.

### 4. Responsivo sem duplicar interface

Desktop:

- detalhe lateral em `Sheet`
- workspace em duas colunas

Mobile:

- detalhe em `Drawer`
- timeline e topo continuam prioritarios
- manter o palco como primeiro bloco visivel

## Contrato de interacao e acessibilidade

O plano ja esta bom em arquitetura e dados, mas esta sprint precisa travar tambem um contrato formal de interacao.

Sem isso, a equipe corre o risco de implementar um cockpit tecnicamente correto e operacionalmente inconsistente.

### 1. Barra de comando tratada como toolbar

A barra de comando deve ser implementada como grupo de controles com comportamento de toolbar:

- foco por `Tab` entra no grupo;
- navegacao interna por setas esquerda/direita;
- estados e acoes principais continuam clicaveis normalmente;
- nenhum controle critico pode ficar escondido sem label clara.

Regra pratica para esta sprint:

- manter a semantica de toolbar no markup e no comportamento;
- evitar criar uma barra visual sem contrato de teclado.

### 2. Regras das tabs

#### `WallStageTabs`

Usar ativacao automatica apenas quando:

- o painel ja estiver pre-carregado;
- a troca nao causar latencia perceptivel.

Se a aba depender de fetch ou render mais pesado:

- usar ativacao manual por `Enter` ou `Space`.

#### `WallInspectorTabs`

Default recomendado:

- ativacao manual nas abas que possam disparar trabalho mais caro;
- ativacao automatica apenas em conteudo instantaneo.

### 3. Regra do trilho recente

O trilho recente nao deve ter autoplay implicito.

Pode ter:

- insercao animada de item novo;
- destaque visual de item novo;
- auto-scroll assistido apenas quando nao houver hover, foco ou item selecionado.

Nao pode ter:

- rotacao automatica continua;
- deslocamento que brigue com leitura ou teclado;
- mudanca de item sem controle do operador.

Se no futuro o trilho virar carousel com rotacao:

- precisara de controle explicito para parar/iniciar;
- pausa obrigatoria em hover e foco.

### 4. Tooltip, hover e detalhe

Nenhuma informacao critica pode depender apenas de hover.

Regra:

- `Tooltip`
  - legenda curta
- `HoverCard`
  - detalhe leve, nao essencial
- `Sheet`
  - detalhe lateral no desktop
- `Drawer`
  - detalhe no mobile

### 5. Navegacao por teclado

O contrato minimo desta sprint e:

- toolbar com setas;
- tabs com comportamento previsivel;
- trilho recente com setas esquerda/direita;
- destaque de foco visivel;
- `Escape` fecha detalhe lateral quando aplicavel.

## Orcamento de animacao

O cockpit precisa parecer vivo, mas nao pode virar painel pesado.

### Motion permitido nesta sprint

- badge `Ao vivo`
- troca da midia principal do palco
- entrada de item novo no trilho
- transicao visual entre item do trilho e destaque do palco/detalhe

### Motion proibido nesta sprint

- animar todos os KPIs a cada refetch
- animar listas inteiras em refetch de insights
- animar layout completo da pagina
- autoplay visual no trilho recente

### Regra tecnica

- `AnimatePresence` apenas em trocas pontuais;
- `layout` ou `layoutId` apenas em elementos pequenos e claramente conectados;
- evitar motion em massa em listas densas.

## Modulos de suporte recomendados

Para evitar logica espalhada em componentes, esta sprint deve prever mais cinco arquivos de suporte:

- `apps/web/src/modules/wall/wall-query-options.ts`
- `apps/web/src/modules/wall/wall-copy.ts`
- `apps/web/src/modules/wall/wall-source-meta.ts`
- `apps/web/src/modules/wall/wall-interaction-contract.ts`
- `apps/web/src/modules/wall/wall-view-models.ts`

### Papel de cada arquivo

- `wall-query-options.ts`
  - concentra `staleTime`, `gcTime`, `placeholderData`, `refetchOnWindowFocus`, `refetchOnReconnect`
- `wall-copy.ts`
  - centraliza labels amigaveis
- `wall-source-meta.ts`
  - mapeia origem para label, cor e icone
- `wall-interaction-contract.ts`
  - documenta e exporta as regras de teclado, clique, hover e autoativacao
- `wall-view-models.ts`
  - transforma payload da API em shape pronto para UI sem poluir os componentes

## Definicao global de pronto

Uma entrega so conta como pronta quando:

- o teste do comportamento novo existe e passa;
- o teste de regressao do modulo wall passa;
- o controlador backend continua fino;
- listagens complexas ficam em `Queries` ou `Services` bem delimitados;
- a interface usa copy amigavel;
- nao existe flicker bruto em refetch de insights;
- a selecao de midia se mantem durante refetch;
- a tela continua usavel em desktop e mobile.

## Arquitetura alvo da sprint

```txt
EventWallManagerPage
- WallHeaderBar
- WallCommandToolbar
- WallWorkspace
  - WallTopInsightsRail
    - WallTopContributorCard
    - WallTotalMediaCard
    - WallLiveMediaTimelineStrip
  - WallHeroStage
  - WallStageTabs
  - WallInspectorTabs
- WallDiagnosticsAccordion
- WallDangerZone
```

## Ordem de entrega recomendada

1. contratos, endpoint agregado e view models
2. selecao de midia e detalhe lateral
3. trilho vivo do topo
4. realtime hibrido
5. polimento visual, motion e responsivo

## Bloco 0. Preparacao e contratos

### Objetivo

Preparar os contratos, query keys e pontos de extensao antes de mexer em layout.

### Backend

#### Tarefas

- revisar quais dados ja existem em `EventMedia`, `InboundMessage` e payloads do wall;
- decidir a origem de cada campo do `WallInsightsResponse`;
- definir limite inicial de `recentItems`:
  - recomendado: `10` no backend
  - opcional no frontend: recortar para `6-10` no topo

#### Subtarefas

- mapear em qual tabela vivem:
  - remetente
  - origem da midia
  - published_at
  - thumb/url
  - status operacional
- definir fallback de `displayName`;
- definir normalizacao de `source`.

### Frontend

#### Tarefas

- expandir `queryKeys.wall` em [query-client.ts](C:/laragon/www/eventovivo/apps/web/src/lib/query-client.ts);
- criar [wall-query-options.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/wall-query-options.ts);
- criar [wall-copy.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/wall-copy.ts);
- criar [wall-source-meta.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/wall-source-meta.ts);
- criar [wall-interaction-contract.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/wall-interaction-contract.ts);
- criar [wall-view-models.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/wall-view-models.ts);
- definir contrato TS do novo payload;
- decidir onde guardar os tipos:
  - preferencialmente em `api-types` se forem trafegados pela API.

#### Subtarefas

- adicionar chaves:
  - `options`
  - `insights`
  - `liveSnapshot`
- definir `query options` por volatilidade;
- definir interfaces:
  - `TopContributor`
  - `WallMediaTotals`
  - `RecentMediaItem`
  - `ApiWallInsightsResponse`

### Testes TTD

#### Backend

- criar teste de contrato falhando primeiro:
  - [WallInsightsTest.php](C:/laragon/www/eventovivo/apps/api/tests/Feature/Wall/WallInsightsTest.php)

Casos minimos:

- usuario autorizado recebe `200`;
- usuario sem permissao recebe `403`;
- payload retorna estrutura vazia coerente quando nao ha midia;
- payload retorna `topContributor`, `totals`, `recentItems`, `sourceMix`.

#### Frontend

- criar teste falhando primeiro:
  - [EventWallManagerPage.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/pages/EventWallManagerPage.test.tsx)
  - ou novo teste focado em hook:
    - [useWallTopInsights.test.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallTopInsights.test.ts)

Casos minimos:

- a query de insights usa a key correta;
- o hook aceita `placeholderData`;
- o retorno e mapeado para UI sem transformar o dado no componente.
- `wall-query-options` nao usa defaults agressivos para refetch do cockpit.

### Criterio de aceite

- contratos definidos;
- testes iniciais escritos e falhando pelo motivo certo;
- sem iniciar layout antes do contrato da API estar congelado.

## Bloco 1. Endpoint agregado `wall/insights`

### Objetivo

Reduzir waterfall e entregar o topo vivo com um unico fetch.

### Arquivos alvo

- `apps/api/app/Modules/Wall/routes/api.php`
- `apps/api/app/Modules/Wall/Http/Controllers/EventWallInsightsController.php`
- `apps/api/app/Modules/Wall/Http/Resources/WallInsightsResource.php`
- `apps/api/app/Modules/Wall/Services/WallInsightsService.php`
- `apps/api/app/Modules/Wall/Queries/BuildWallInsightsQuery.php`

### Decisao de arquitetura

Para respeitar o padrao do repositorio:

- controller fino;
- regra de montagem do payload em `Service`;
- consulta complexa em `Queries`.

### Tarefas detalhadas

#### 1. Criar rota

- adicionar `GET /events/{event}/wall/insights`
- manter middleware `auth:sanctum`
- manter policy `viewWall`

#### 2. Criar controller fino

- autorizar evento;
- delegar para service;
- retornar resource.

#### 3. Criar query de agregacao

Responsabilidades:

- buscar midias relevantes do evento;
- agregar totais;
- localizar remetente lider;
- montar ultimas midias;
- montar distribuicao por origem.

#### 4. Criar service de orquestracao

Responsabilidades:

- chamar query;
- aplicar normalizacao de copy pronta para UI;
- garantir limite de `recentItems`;
- delegar serializacao final para resource.

#### 5. Criar resource

Responsabilidades:

- padronizar resposta;
- garantir nomes estaveis dos campos;
- evitar logica de negocio no controller.

### Regras de dados

- `displayName` pronto para UI;
- `source` normalizado em:
  - `whatsapp`
  - `telegram`
  - `upload`
  - `manual`
  - `gallery`
- `previewUrl` deve ser thumb;
- `recentItems` limitado;
- `lastCaptureAt` deve refletir a ultima midia recebida no recorte adotado.

### Testes TTD

#### Feature tests

Criar em [WallInsightsTest.php](C:/laragon/www/eventovivo/apps/api/tests/Feature/Wall/WallInsightsTest.php):

- retorna `403` sem permissao;
- retorna `200` para usuario com `viewWall`;
- retorna payload vazio sem erro quando nao ha midia;
- calcula corretamente o remetente lider;
- calcula corretamente os totais;
- limita `recentItems` ao maximo esperado;
- normaliza `source`;
- retorna `previewUrl` preenchida quando existir midia com thumb.

#### Unit tests

Criar em:

- [WallInsightsServiceTest.php](C:/laragon/www/eventovivo/apps/api/tests/Unit/Wall/WallInsightsServiceTest.php)
- opcionalmente [BuildWallInsightsQueryTest.php](C:/laragon/www/eventovivo/apps/api/tests/Unit/Wall/BuildWallInsightsQueryTest.php)

Casos:

- fallback de nome amigavel;
- ordenacao de recentes;
- normalizacao por origem;
- agregacao sem duplicar regra de status.

### Criterio de aceite

- endpoint estavel;
- payload coerente;
- testes de contrato e policy passando;
- controller continua fino.

## Bloco 2. API do frontend e cache de insights

### Objetivo

Conectar o frontend ao agregado novo sem instabilidade visual.

### Arquivos alvo

- `apps/web/src/modules/wall/api.ts`
- `apps/web/src/lib/api-types.ts`
- `apps/web/src/lib/query-client.ts`
- `apps/web/src/modules/wall/hooks/useWallTopInsights.ts`

### Tarefas detalhadas

#### 1. API client

- adicionar `getEventWallInsights(eventId)`
- tipar retorno com `ApiWallInsightsResponse`

#### 2. Query keys

- adicionar `options`
- adicionar `insights`
- adicionar `liveSnapshot`

#### 3. Hook `useWallTopInsights`

Responsabilidades:

- chamar a API nova;
- aplicar `placeholderData`;
- expor `isInitialLoading` separado de `isFetching`;
- manter nome de retorno simples para os componentes.

#### 4. Politica de cache

- `wall.options`
  - `staleTime` alto
- `wall.insights`
  - `placeholderData: previous => previous`
  - `staleTime` curto
- evitar spinner bruto quando estiver apenas atualizando em background
- preferir `Infinity` para dados praticamente estaticos e reservar `static` apenas para o que realmente nao deve reagir nem a invalidacao manual.

#### 5. Render optimization

- usar `select` nos hooks sempre que isso reduzir payload entregue a arvores grandes;
- nao passar o objeto completo da query para componentes densos;
- nao usar `notifyOnChangeProps: "all"` neste cockpit;
- evitar logica de formatacao repetida dentro dos componentes do topo.

### Testes TTD

#### Hook tests

Criar em:

- [useWallTopInsights.test.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallTopInsights.test.ts)

Casos:

- usa query key correta;
- chama endpoint correto;
- usa `placeholderData`;
- nao perde dados anteriores durante refetch;
- retorna estados corretos de carregamento.
- respeita `select` ou view model sem alterar o cache bruto.

#### Regressao de pagina

Expandir [EventWallManagerPage.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/pages/EventWallManagerPage.test.tsx):

- pagina renderiza insights do topo quando endpoint responde;
- nao quebra os blocos ja existentes.

### Criterio de aceite

- frontend consome o novo endpoint;
- sem flicker visivel no topo durante refetch;
- query keys novas prontas para os proximos blocos.

## Bloco 3. Topo vivo do workspace

### Objetivo

Transformar o topo em uma faixa que conte a historia do evento:

- quem lidera;
- quanto entrou;
- o que acabou de chegar.

### Arquivos alvo

- `apps/web/src/modules/wall/components/manager/top/WallTopInsightsRail.tsx`
- `apps/web/src/modules/wall/components/manager/top/WallTopContributorCard.tsx`
- `apps/web/src/modules/wall/components/manager/top/WallTotalMediaCard.tsx`
- `apps/web/src/modules/wall/components/manager/top/WallLiveMediaTimelineStrip.tsx`
- `apps/web/src/modules/wall/components/manager/top/WallRecentMediaChip.tsx`

### Tarefas detalhadas

#### 1. `WallTopInsightsRail`

- receber `topContributor`, `totals`, `recentItems`;
- renderizar layout responsivo:
- desktop: 3 blocos
- tablet: 2 linhas
- mobile: empilhado com prioridade no trilho recente
- respeitar o contrato de interacao definido em `wall-interaction-contract.ts`

#### 2. `WallTopContributorCard`

Mostrar:

- `Quem mais enviou`
- nome amigavel
- origem
- total de midias
- ultima atividade relativa

Evitar:

- sender key cru
- telefone completo
- card seco sem contexto

#### 3. `WallTotalMediaCard`

Mostrar:

- recebidas
- aprovadas
- em fila
- exibidas

#### 4. `WallLiveMediaTimelineStrip`

Comecar com `ScrollArea`, nao com `Carousel`.

Motivo:

- o trilho e denso;
- o uso e operacional;
- o scroll horizontal nativo combina melhor com feed vivo.

Regras obrigatorias:

- sem autoplay;
- sem deslocamento automatico quando houver hover;
- sem deslocamento automatico quando houver item selecionado;
- foco visivel por teclado;
- detalhe essencial nunca apenas em hover.

#### 5. `WallRecentMediaChip`

Mostrar:

- thumb
- nome curto
- origem
- horario relativo
- badge `Agora` quando aplicavel

### Regras de UX

- `Skeleton` so no primeiro paint;
- refetch em background nao deve trocar tudo por loading;
- hover deve ser leve;
- foco por teclado precisa ser visivel;
- a copy deve evitar ingles desnecessario.

### Testes TTD

#### Componentes

Criar:

- [WallTopInsightsRail.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/components/manager/top/WallTopInsightsRail.test.tsx)
- [WallTopContributorCard.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/components/manager/top/WallTopContributorCard.test.tsx)
- [WallLiveMediaTimelineStrip.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/components/manager/top/WallLiveMediaTimelineStrip.test.tsx)

Casos:

- renderiza copy amigavel;
- renderiza thumbs e origem;
- aplica `Skeleton` apenas no carregamento inicial;
- preserva estrutura no refetch com dados anteriores;
- suporta foco por teclado nos itens do trilho.
- nao executa autoplay implicito do trilho.

### Criterio de aceite

- topo vivo renderizado;
- copy simplificada aplicada;
- visualmente estavel;
- responsivo.

## Bloco 4. Selecao de midia recente e detalhe lateral

### Objetivo

Ligar o trilho recente ao palco e ao detalhe sem rerender desnecessario.

### Arquivos alvo

- `apps/web/src/modules/wall/hooks/useWallSelectedMedia.ts`
- `apps/web/src/modules/wall/hooks/useWallRecentMediaTimeline.ts`
- `apps/web/src/modules/wall/components/manager/recent/WallRecentMediaDetailsSheet.tsx`
- `apps/web/src/modules/wall/components/manager/stage/WallHeroStage.tsx`

### Tarefas detalhadas

#### 1. `useWallSelectedMedia`

Responsabilidades:

- guardar `selectedMediaId`;
- expor `selectMedia`, `clearSelection`, `openDetails`, `closeDetails`;
- manter selecao durante refetch de insights.
- expor informacao suficiente para o detalhe lateral abrir com titulo e contexto acessivel.

#### 2. `useWallRecentMediaTimeline`

Responsabilidades:

- detectar item novo;
- pausar auto-scroll no hover;
- manter item selecionado visivel;
- preparar navegacao por teclado.
- respeitar a regra de nao deslocar o trilho com foco ativo.

#### 3. `WallRecentMediaDetailsSheet`

Desktop:

- `Sheet` lateral

Mobile:

- `Drawer`

Conteudo minimo:

- thumb maior
- nome do remetente
- origem
- horario
- status da midia

#### 4. Integracao com `WallHeroStage`

- destaque visual da midia selecionada;
- se a midia selecionada nao for a atual do palco, mostrar estado de "selecionada no painel";
- nao confundir item selecionado com item efetivamente ao vivo.
- usar motion apenas no destaque pontual, nao na lista inteira.

### Testes TTD

#### Hook tests

- [useWallSelectedMedia.test.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallSelectedMedia.test.ts)
- [useWallRecentMediaTimeline.test.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallRecentMediaTimeline.test.ts)

Casos:

- selecao persiste durante refetch;
- hover pausa auto-scroll;
- teclado move foco entre itens;
- abrir detalhe lateral usa item selecionado certo.
- item selecionado nao some visualmente durante refetch de insights.

#### Component tests

- [WallRecentMediaDetailsSheet.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/components/manager/recent/WallRecentMediaDetailsSheet.test.tsx)

Casos:

- abre no clique/double click;
- mostra dados da midia selecionada;
- fecha corretamente;
- respeita empty state.
- possui titulo e contexto acessivel no container de detalhe.

#### Regressao de pagina

Expandir [EventWallManagerPage.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/pages/EventWallManagerPage.test.tsx):

- clicar numa midia recente destaca no palco;
- abrir detalhe lateral funciona;
- selecao continua apos novo fetch.

### Criterio de aceite

- trilho recente conversa com o palco;
- detalhe lateral funciona em desktop e mobile;
- sem perda de selecao em refetch.

## Bloco 5. Realtime hibrido

### Objetivo

Manter o painel vivo quando o canal estiver saudavel e confiavel quando cair.

### Arquivos alvo

- `apps/web/src/modules/wall/hooks/useWallRealtimeSync.ts`
- `apps/web/src/modules/wall/hooks/useWallPollingFallback.ts`
- `apps/web/src/modules/wall/hooks/useWallManagerRealtime.ts`
- `apps/web/src/modules/wall/pages/EventWallManagerPage.tsx`

### Tarefas detalhadas

#### 1. Separar a sincronizacao do realtime

Criar `useWallRealtimeSync(eventId)` com responsabilidade unica:

- ouvir canal privado;
- invalidar:
  - `settings`
  - `insights`
  - `liveSnapshot`
  - `diagnostics`
  - opcionalmente `event`

#### 2. Criar `useWallPollingFallback`

Responsabilidades:

- receber estado do realtime;
- devolver intervalos por query;
- desligar polling quando `connected`;
- religar apenas em `disconnected` ou `offline`.
- devolver configuracao pronta para `wall-query-options.ts`.

#### 3. Aplicar a politica por prioridade

- `liveSnapshot`
  - `5s` a `8s`
- `diagnostics`
  - `10s`
- `insights`
  - `15s`
- `settings` e `event`
  - `20s` a `30s`

#### 4. Reconexao

- ao reconectar:
  - invalidar tudo uma vez
  - desligar polling

#### 5. Query options centralizadas

- mover as politicas de query do cockpit para `wall-query-options.ts`;
- separar claramente:
  - quase estatico
  - persistente
  - operacional
  - tempo real
  - simulacao

### Testes TTD

#### Hook tests

- [useWallPollingFallback.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallPollingFallback.test.tsx)
- [useWallRealtimeSync.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallRealtimeSync.test.tsx)

Casos:

- `connected` nao ativa polling;
- `offline` ativa polling;
- `disconnected` ativa polling;
- reconexao corta polling e invalida queries;
- invalidate usa keys corretas.
- `query options` do cockpit permanecem consistentes fora da pagina principal.

#### Regressao de pagina

Expandir [EventWallManagerPage.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/pages/EventWallManagerPage.test.tsx):

- estado conectado nao faz polling;
- estado offline faz polling de fallback;
- ao simular reconexao, a pagina invalida e volta ao modo normal.

### Criterio de aceite

- sem polling duplicado quando o canal estiver saudavel;
- sem painel morto quando o canal cair;
- reconexao previsivel.

## Bloco 6. Polimento visual e responsivo

### Objetivo

Fechar a sprint com cara de cockpit vivo, sem custo alto de bundle.

### Tarefas detalhadas

#### 1. Micro animacoes

- usar `AnimatePresence` apenas em:
  - selo `Ao vivo`
  - item atual do palco
  - selecao visual do trilho
- entrada de item novo no trilho
- evitar animacao em massa em listas grandes

#### 2. Estados vazios

- sem midia recente
- sem remetente lider
- sem dados de totais

#### 3. Responsivo

- validar `Sheet` no desktop e `Drawer` no mobile;
- garantir que o trilho continue navegavel no toque;
- manter o palco acima do detalhe no mobile.

#### 4. Copy final

Aplicar:

- `Top convidado` -> `Quem mais enviou`
- `Total capturado` -> `Total de midias`
- `Fotos Recentes` -> `Ultimas chegadas`

#### 5. Contrato perceptivo de UX

- nenhum KPI principal desaparece durante refetch;
- nenhum bloco do topo desloca layout em background fetch;
- tabs do palco so usam ativacao automatica quando o painel estiver pre-carregado;
- o trilho recente nunca autoanda com hover, foco ou item selecionado;
- toolbar permite navegacao por setas;
- nenhum detalhe essencial depende exclusivamente de hover.

### Testes TTD

#### Component tests

- validar empty states;
- validar foco e hover states;
- validar renderizacao mobile-friendly com layout reduzido.
- validar que o trilho nao autoanda durante hover ou foco.

### Criterio de aceite

- sensacao de cockpit vivo;
- menos "painel que pisca";
- leitura clara para usuario leigo.

## Matriz de testes automatizados

### Backend

Criar ou expandir:

- [WallInsightsTest.php](C:/laragon/www/eventovivo/apps/api/tests/Feature/Wall/WallInsightsTest.php)
- [WallLiveSnapshotTest.php](C:/laragon/www/eventovivo/apps/api/tests/Feature/Wall/WallLiveSnapshotTest.php)
- [WallDiagnosticsTest.php](C:/laragon/www/eventovivo/apps/api/tests/Feature/Wall/WallDiagnosticsTest.php)
- [WallInsightsServiceTest.php](C:/laragon/www/eventovivo/apps/api/tests/Unit/Wall/WallInsightsServiceTest.php)

### Frontend

Criar ou expandir:

- [EventWallManagerPage.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/pages/EventWallManagerPage.test.tsx)
- [useWallTopInsights.test.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallTopInsights.test.ts)
- [useWallPollingFallback.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallPollingFallback.test.tsx)
- [useWallRealtimeSync.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallRealtimeSync.test.tsx)
- [useWallSelectedMedia.test.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallSelectedMedia.test.ts)
- [useWallRecentMediaTimeline.test.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallRecentMediaTimeline.test.ts)
- [WallTopInsightsRail.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/components/manager/top/WallTopInsightsRail.test.tsx)
- [WallLiveMediaTimelineStrip.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/components/manager/top/WallLiveMediaTimelineStrip.test.tsx)
- [WallRecentMediaDetailsSheet.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/components/manager/recent/WallRecentMediaDetailsSheet.test.tsx)
- [WallDraftPreviewCard.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/components/manager/stage/WallDraftPreviewCard.test.tsx)
- [WallUpcomingTimeline.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/components/manager/stage/WallUpcomingTimeline.test.tsx)
- [WallAdvanceClock.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/components/manager/stage/WallAdvanceClock.test.tsx)
- [WallPlayerDetailsSheet.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/components/manager/diagnostics/WallPlayerDetailsSheet.test.tsx)

## Comandos de validacao por bloco

### Backend

```powershell
cd apps/api
php artisan test --filter=WallInsightsTest
php artisan test --filter=WallDiagnosticsTest
php artisan test --filter=WallLiveSnapshotTest
php artisan test --filter=WallInsightsServiceTest
```

### Frontend

```powershell
cd apps/web
npm run test -- src/modules/wall/pages/EventWallManagerPage.test.tsx
npm run test -- src/modules/wall/hooks/useWallTopInsights.test.ts
npm run test -- src/modules/wall/hooks/useWallLiveSnapshot.test.tsx
npm run test -- src/modules/wall/hooks/useWallPollingFallback.test.tsx
npm run test -- src/modules/wall/hooks/useWallRealtimeSync.test.tsx
npm run test -- src/modules/wall/hooks/useWallSelectedMedia.test.ts
npm run test -- src/modules/wall/hooks/useWallRecentMediaTimeline.test.ts
npm run test -- src/modules/wall/components/manager/stage/WallAdvanceClock.test.tsx
npm run test -- src/modules/wall/components/manager/diagnostics/WallPlayerDetailsSheet.test.tsx
```

### Regressao final da sprint

```powershell
cd apps/api
php artisan test --filter=Wall

cd ..\\web
npm run test -- src/modules/wall
npm run type-check
```

## Checklist objetivo da sprint

### Backend

- [x] rota `GET /events/{event}/wall/insights`
- [x] rota `GET /events/{event}/wall/live-snapshot`
- [x] controller fino
- [x] query de agregacao
- [x] service de montagem
- [x] resource de resposta
- [x] `previewUrl` em thumb
- [x] `source` normalizado
- [x] testes de contrato e policy
- [x] evento privado `wall.runtime.snapshot.updated`
- [x] `current_item_started_at` persistido por player
- [x] `liveSnapshot.advancedAt`
- [x] `sequence_preview.preview_url`
- [x] `sequence_preview.source_type`
- [x] `sequence_preview.caption`
- [x] `sequence_preview.layout_hint`

### Frontend

- [x] query keys novas
- [x] `getEventWallInsights`
- [x] `getEventWallLiveSnapshot`
- [x] `useWallTopInsights`
- [x] `useWallLiveSnapshot`
- [x] `WallTopInsightsRail`
- [x] `WallTopContributorCard`
- [x] `WallTotalMediaCard`
- [x] `WallLiveMediaTimelineStrip`
- [x] `WallRecentMediaDetailsSheet`
- [x] `useWallSelectedMedia`
- [x] `useWallRecentMediaTimeline`
- [x] `useWallRealtimeSync`
- [x] `useWallPollingFallback`
- [x] atualizacao dedicada de `liveSnapshot` via evento realtime
- [x] `WallHeroStage`
- [x] `WallDraftPreviewCard`
- [x] `WallUpcomingTimeline`
- [x] `WallAdvanceClock`
- [x] `WallPlayerDetailsSheet`
- [x] `WallQueueTab`
- [x] `WallAppearanceTab` integrado na pagina principal
- [x] `WallAdsTab` integrado na pagina principal
- [x] `EventWallManagerPage.tsx` abaixo de `1600` linhas
- [x] `EventWallManagerPage.tsx` abaixo de `1300` linhas
- [x] `EventWallManagerPage.tsx` abaixo de `1000` linhas

### UX/UI

- [x] copy simplificada
- [x] `Skeleton` so no primeiro paint
- [x] `placeholderData` durante refetch
- [x] selecao persistida
- [x] hover e focus states
- [x] detalhe responsivo
- [x] toolbar com navegacao por setas
- [x] tabs com estrategia correta de ativacao
- [x] trilho sem autoplay implicito
- [x] nenhum dado essencial dependente apenas de hover
- [x] previa inicial do rascunho sem `iframe`
- [x] `Proximas fotos` com thumb e origem
- [x] preview com renderer compartilhado do player
- [x] `Proximas fotos` como timeline horizontal com scroll
- [x] card de player com cor por saude operacional
- [x] palco com `live snapshot` real do wall
- [x] palco com clock de advance
- [x] `Proximas fotos` com `caption` e `layout_hint`
- [x] card de player com copy operacional
- [x] detalhe expandido do player com linguagem operacional

## Riscos e cuidados

### 1. Inconsistencia semantica

Se `Quem mais enviou` e `Total de midias` usarem recortes diferentes, o operador vai perceber como bug.

Acao:

- travar a regra de recorte no backend e documentar dentro do service.

### 2. Thumb pesada

Se `previewUrl` vier em imagem grande, o topo perde performance.

Acao:

- retornar thumb real no backend e testar payload com fixture de imagem.

### 3. Auto-scroll agressivo

Se o trilho recentrar sozinho o tempo inteiro, ele briga com hover, clique e teclado.

Acao:

- pausar no hover e nao recentrar se houver item selecionado.

### 4. Polling duplicado

Se polling continuar ativo enquanto o socket estiver saudavel, o painel vai gastar mais e piscar mais.

Acao:

- teste automatizado cobrindo `connected`, `offline` e reconexao.

## Recomendacao final da sprint

Se for preciso travar a sprint com foco absoluto, a ordem deve ser:

1. endpoint agregado `wall/insights`
2. `WallTopInsightsRail`
3. timeline recente clicavel
4. selecao e detalhe lateral
5. fallback hibrido de realtime
6. polimento visual

Porque essa ordem entrega mais rapido o que muda a percepcao do produto:

- mais vida no topo;
- ligacao clara entre dado e palco;
- menos waterfall;
- menos reload visual.

## Bloco novo apos a sprint atual

Este bloco nao reabre o checklist concluido acima.

Ele organiza a proxima rodada de evolucao em cima do que ja ficou verde nesta sprint.

### Objetivo do bloco novo

Subir o cockpit de um monitor operacional forte para um monitor mais autoritativo, enquanto prepara a semantica visual final de origem, video e exibidas.

### Eixo 1. Monitor live mais autoritativo

Backend:

- emitir snapshot a partir do `advance` real do engine quando essa fonte estiver disponivel, e nao apenas do `heartbeat`
- decidir fonte autoritativa do `advancedAt`
- carregar tambem `nextItem` ou `upcomingItem` quando o runtime tiver essa informacao
- avaliar se `current_transition` precisa entrar no payload do snapshot

Frontend:

- promover o palco de `clock estimado` para `clock autoritativo`
- mostrar `Agora` e `Proxima` com semantica mais clara no monitor live
- reduzir divergencia visual entre snapshot e simulacao quando ambos estiverem presentes

### Eixo 2. Historico confiavel de exibidas

Backend:

- criar trilha confiavel para contar quantas midias ja foram exibidas
- decidir se a contagem nasce de evento de `advance`, tabela agregada ou resumo incremental
- destravar `totals.displayed` para deixar de ser `null`

Frontend:

- trocar fallback vazio por valor real em `Total de midias`
- mostrar o numero de exibidas sem inventar regra no cliente

### Eixo 3. Semantica visual final de origem e video

Backend:

- avaliar incluir icone ou `source_label` normalizado no payload quando isso realmente reduzir regra no cliente
- estudar campos para video em previsao e monitor:
  - `is_video`
  - `duration_seconds`
  - `video_policy_label`

Frontend:

- mapear origem com linguagem e badge finais
- destacar video na timeline e no palco sem poluir a leitura
- preparar alerta simples para video longo ou politicas especiais de exibicao

### Testes obrigatorios deste bloco novo

Backend:

- teste de feature para o snapshot autoritativo quando o `advance` mudar
- teste de feature para contagem acumulada de exibidas
- teste de regressao para garantir que `heartbeat` continua como fallback seguro

Frontend:

- teste do palco quando `liveSnapshot` trouxer `nextItem`
- teste do relogio quando a origem passar a ser autoritativa
- teste da timeline com badge final de video e origem

### Revalidacao complementar executada antes de abrir este bloco

Wall cockpit:

- `cd apps/api && php artisan test --filter=Wall`
  - `62` testes passando
- `cd apps/web && npm run test -- src/modules/wall`
  - `199` testes passando
- `cd apps/web && npm run type-check`
  - sem erros

Video baseline:

- `cd apps/api && php artisan test --filter=PublicUploadTest`
  - `8` testes passando
- `cd apps/web && npm run test -- src/modules/wall/player/components/MediaSurface.test.tsx src/modules/wall/player/engine/cache.test.ts src/modules/wall/player/engine/preload.test.ts`
  - `12` testes passando

Leitura:

- o cockpit atual esta estavel o suficiente para abrir a proxima fase;
- a trilha atual de video continua validada como baseline;
- isso permite evoluir monitor autoritativo e semantica de video sem perder a referencia do comportamento atual.

## Plano executavel do bloco novo

Este plano segue a mesma logica da sprint atual:

1. teste antes do codigo;
2. fatias pequenas e integraveis;
3. regressao do modulo ao fim de cada etapa;
4. backend e frontend caminhando pelo mesmo contrato.

## Escopo deste bloco novo

### Entra agora

- fonte mais autoritativa para `advancedAt`
- evento realtime de snapshot mantendo o manager sincronizado sem flicker
- `nextItem` ou `upcomingItem` no `liveSnapshot`, se a fonte existir de forma confiavel
- contagem acumulada e autoritativa de `displayed`
- semantica final de origem no monitor e na timeline
- semantica minima de video no monitor e na previsao

### Nao entra agora

- refatoracao total do player publico
- pipeline completo de video first-class
- HLS/DASH, transcode adaptativo ou Service Worker de playback
- charts avancados do cockpit
- analytics profundo de video por frame ou por play completo

## Ordem de entrega recomendada

1. trilha autoritativa de `advance`
2. `liveSnapshot` com `nextItem`
3. contagem acumulada de exibidas
4. semantica final de origem e video
5. polimento visual e regressao completa

Motivo:

- sem fonte autoritativa, o resto continua aproximacao;
- sem `displayed` confiavel, o topo segue parcialmente estimado;
- sem semantica final de origem e video, o cockpit continua forte tecnicamente, mas incompleto como produto operacional.

## Etapa 1. Fonte autoritativa de advance

### Objetivo

Trocar o `advancedAt` derivado do heartbeat por uma fonte mais proxima do `advance` real do engine.

### Backend

- definir onde o `advance` real do wall pode ser emitido de forma confiavel
- escolher estrategia:
  - evento backend a partir de runtime monitorado
  - persistencia incremental por player autoritativo
  - snapshot central produzido por uma fonte eleita
- registrar `advancedAt` com semantica clara:
  - quando a midia entrou
  - quando o slide avancou
- manter `heartbeat` como fallback seguro quando a fonte autoritativa nao estiver disponivel

### Frontend

- adaptar `useWallRealtimeSync` para preferir payload autoritativo quando existir
- ajustar `WallAdvanceClock` para usar origem autoritativa sem mudar a API do componente
- evitar salto visual quando `advancedAt` for corrigido por um evento mais preciso

### Arquivos provaveis

- `apps/api/app/Modules/Wall/Http/Controllers/PublicWallController.php`
- `apps/api/app/Modules/Wall/Services/WallDiagnosticsService.php`
- `apps/api/app/Modules/Wall/Services/WallLiveSnapshotService.php`
- `apps/api/app/Modules/Wall/Events/*`
- `packages/shared-types/src/wall.ts`
- `apps/web/src/modules/wall/hooks/useWallRealtimeSync.ts`
- `apps/web/src/modules/wall/components/manager/stage/WallAdvanceClock.tsx`

### TDD obrigatorio

Backend:

- criar teste que prova que o `advancedAt` muda quando ha `advance` real
- criar teste que prova que o fallback por `heartbeat` continua valido quando nao houver evento autoritativo
- criar teste que prova que o snapshot nao regride para um tempo mais antigo ao receber eventos fora de ordem

Frontend:

- criar teste do `WallAdvanceClock` com atualizacao de `advancedAt` no meio da exibicao
- criar teste do `useWallRealtimeSync` garantindo prioridade da origem autoritativa
- criar teste da pagina validando que o palco nao pisca ao receber correcao de tempo

### Criterio de pronto

- `advancedAt` deixa de depender apenas do `heartbeat`
- o palco continua estavel durante a troca da origem temporal
- fallback por `heartbeat` continua funcionando

### Implementado nesta primeira fatia da Etapa 1

- o player passou a enviar `current_item_started_at` no `heartbeat`
- o `current_item_started_at` agora nasce no engine do player quando o item atual muda
- o backend passou a preferir esse timestamp quando ele vier do player
- o backend agora ignora regressao temporal quando chegar um timestamp mais antigo para a mesma midia
- o fallback anterior continua funcionando quando o player nao enviar `current_item_started_at`

### Testes executados nesta primeira fatia da Etapa 1

- backend:
  - `cd apps/api && php artisan test --filter=WallLiveSnapshotTest`
  - `4` testes passando
- backend:
  - `cd apps/api && php artisan test --filter=WallDiagnosticsTest`
  - `6` testes passando
- backend:
  - `cd apps/api && php artisan test --filter=Wall`
  - `71` testes passando
- frontend:
  - `cd apps/web && npm run test -- src/modules/wall/player/hooks/useWallPlayer.test.tsx`
  - `5` testes passando
- frontend:
  - `cd apps/web && npm run test -- src/modules/wall/hooks/useWallLiveSnapshot.test.tsx src/modules/wall/hooks/useWallRealtimeSync.test.tsx src/modules/wall/components/manager/stage/WallAdvanceClock.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx`
  - `20` testes passando
- frontend:
  - `cd apps/web && npm run test -- src/modules/wall`
  - `202` testes passando
- frontend:
  - `cd apps/web && npm run type-check`
  - sem erros

### Check de conclusao desta fatia

- [x] `current_item_started_at` saiu do engine do player
- [x] `heartbeat` passou a carregar `current_item_started_at`
- [x] backend passou a preferir timestamp autoritativo vindo do player
- [x] backend impede regressao temporal para a mesma midia
- [x] clock do manager continua operando com fallback seguro
- [x] regressao do modulo `Wall` ficou verde apos a mudanca

## Etapa 2. Snapshot com proxima midia

### Objetivo

Permitir que o palco mostre nao so o que esta em tela agora, mas tambem a proxima midia prevista quando essa informacao for confiavel.

### Backend

- adicionar `nextItem` ou `upcomingItem` no payload de `liveSnapshot`
- definir contrato minimo:
  - `id`
  - `previewUrl`
  - `senderName`
  - `source`
  - `layoutHint`
  - `isVideo`
  - `durationSeconds`
- garantir que o snapshot nao inventa proxima midia se a fonte nao for confiavel

### Frontend

- evoluir `WallHeroStage` para mostrar bloco `Agora` e bloco `Proxima`
- manter prioridade da selecao manual do operador sobre o snapshot
- evitar que `nextItem` concorra visualmente com a timeline de `Proximas fotos`

### Arquivos provaveis

- `apps/api/app/Modules/Wall/Http/Resources/WallLiveSnapshotResource.php`
- `apps/api/app/Modules/Wall/Services/WallLiveSnapshotService.php`
- `apps/web/src/lib/api-types.ts`
- `apps/web/src/modules/wall/hooks/useWallLiveSnapshot.ts`
- `apps/web/src/modules/wall/components/manager/stage/WallHeroStage.tsx`

### TDD obrigatorio

Backend:

- criar teste de feature cobrindo `liveSnapshot.nextItem`
- criar teste garantindo que `nextItem` fica `null` quando o backend nao puder afirmar a proxima midia

Frontend:

- criar teste do palco quando houver `currentItem` e `nextItem`
- criar teste garantindo que selecao manual continua com prioridade
- criar teste de layout responsivo no mobile para `Agora` e `Proxima`

### Criterio de pronto

- o palco mostra `Agora` e `Proxima` quando a fonte estiver confiavel
- a timeline continua complementar, nao redundante
- selecao manual do operador continua mandando no palco

### Implementado nesta primeira fatia da Etapa 2

- o backend passou a devolver `nextItem` no `liveSnapshot` quando a previsao da fila confirma a mesma midia atual do player como primeira posicao prevista
- quando essa confirmacao nao existe, o backend mantem `nextItem = null` para nao inventar a proxima exibicao
- o contrato do snapshot passou a carregar no item atual e no proximo:
  - `isVideo`
  - `durationSeconds`
- o palco ganhou bloco `Agora no telao`
- o palco ganhou bloco `Proxima no telao`
- a selecao manual do operador continua com prioridade apenas na midia principal, sem esconder o monitor de `Agora` e `Proxima`

### Testes executados nesta primeira fatia da Etapa 2

- backend:
  - `cd apps/api && php artisan test --filter=WallLiveSnapshotTest`
  - `6` testes passando
- backend:
  - `cd apps/api && php artisan test --filter=WallDiagnosticsTest`
  - `6` testes passando
- backend:
  - `cd apps/api && php artisan test --filter=Wall`
  - `77` testes passando
- frontend:
  - `cd apps/web && npm run test -- src/modules/wall/hooks/useWallLiveSnapshot.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx src/modules/wall/hooks/useWallRealtimeSync.test.tsx src/modules/wall/components/manager/stage/WallAdvanceClock.test.tsx`
  - `21` testes passando
- frontend:
  - `cd apps/web && npm run test -- src/modules/wall`
  - `203` testes passando
- frontend:
  - `cd apps/web && npm run type-check`
  - sem erros

## Etapa 3. Historico confiavel de exibidas

### Objetivo

Destravar `totals.displayed` com um numero autoritativo e acumulado.

### Implementado nesta etapa

Backend:

- criada a tabela agregada `wall_display_counters`;
- criada a trilha `WallDisplayCounterService`;
- a contagem passa a ser escrita no `heartbeat`, usando:
  - `current_item_id`
  - `current_item_started_at`
  - tolerancia derivada de `interval_ms` para dedupe;
- o contador sobe em avancos reais e em replay legitimo da mesma midia mais tarde;
- o contador ignora repeticao do mesmo display e regressao temporal do sinal;
- `wall/insights` agora devolve `totals.displayed` preenchido com numero.

Frontend:

- `ApiWallInsightsTotals.displayed` deixou de ser `nullable`;
- `WallTotalMediaCard` parou de usar fallback local `'-'`;
- `useWallRealtimeSync` invalida `wall.insights(eventId)` quando o snapshot muda de item ou de `advancedAt`;
- `useWallTopInsights` continua com `placeholderData` para evitar sumico visual durante refetch.

### Arquivos provaveis

- `apps/api/app/Modules/Wall/Services/WallDisplayCounterService.php`
- `apps/api/app/Modules/Wall/Services/WallDiagnosticsService.php`
- `apps/api/app/Modules/Wall/Queries/BuildWallInsightsQuery.php`
- `apps/api/app/Modules/Wall/Http/Resources/WallInsightsResource.php`
- `apps/api/database/migrations/2026_04_09_040000_create_wall_display_counters_table.php`
- `apps/web/src/modules/wall/components/manager/top/WallTotalMediaCard.tsx`
- `apps/web/src/modules/wall/hooks/useWallRealtimeSync.ts`
- `apps/web/src/modules/wall/hooks/useWallTopInsights.ts`

### TDD executado

Backend:

- `WallInsightsTest`
  - garante que `displayed` sobe quando a exibicao avanca;
  - garante idempotencia da contagem;
  - garante isolamento por evento.

Frontend:

- `WallTotalMediaCard.test.tsx`
  - garante renderizacao de `displayed` preenchido;
- `useWallTopInsights.test.tsx`
  - garante que refetch nao apaga o numero anterior;
- `useWallRealtimeSync.test.tsx`
  - garante invalidaçao de `insights` quando o snapshot realmente muda.

### Validacao executada

Backend:

- `cd apps/api && php artisan test --filter=WallInsightsTest`
- `6` testes passando
- `cd apps/api && php artisan test --filter=WallLiveSnapshotTest`
- `6` testes passando
- `cd apps/api && php artisan test --filter=WallDiagnosticsTest`
- `6` testes passando
- `cd apps/api && php artisan test --filter=Wall`
- `80` testes passando

Frontend:

- `cd apps/web && npm run test -- src/modules/wall/hooks/useWallTopInsights.test.tsx src/modules/wall/components/manager/top/WallTotalMediaCard.test.tsx src/modules/wall/hooks/useWallRealtimeSync.test.tsx`
- `8` testes passando
- `cd apps/web && npm run test -- src/modules/wall`
- `204` testes passando
- `cd apps/web && npm run type-check`
- sem erros

### Criterio de pronto

- `totals.displayed` deixa de ser `null`
- a contagem nao duplica em reconexao ou repeticao do mesmo display
- o card superior passa a usar numero autoritativo

## Etapa 4. Semantica final de origem e video

### Objetivo

Fechar a leitura operacional de origem e preparar o cockpit para a proxima fase de video.

### Implementado nesta etapa

Backend:

- `liveSnapshot.currentItem` e `liveSnapshot.nextItem` agora carregam:
  - `isVideo`
  - `durationSeconds`
  - `videoPolicyLabel`
- `sequence_preview` agora carrega:
  - `is_video`
  - `duration_seconds`
  - `video_policy_label`
- a normalizacao de origem continua unica no backend via `WallSourceNormalizer`
- `wall/insights` recente tambem passou a expor:
  - `isVideo`
  - `durationSeconds`
  - `videoPolicyLabel`

Frontend:

- `wall-source-meta.ts` virou o mapper unico de semantica visual:
  - origem
  - tipo de midia
  - label de duracao
  - copy operacional de video
- o palco passou a mostrar badge final de video e leitura operacional em `Agora` e `Proxima`
- a timeline horizontal passou a destacar video com selo e copy curta
- o detalhe lateral da midia recente agora mostra:
  - origem
  - tipo de midia
  - copy operacional de playback no telao

### Arquivos provaveis

- `apps/api/app/Modules/Wall/Support/WallVideoPolicyLabelResolver.php`
- `apps/web/src/modules/wall/wall-source-meta.ts`
- `apps/web/src/modules/wall/components/manager/stage/WallHeroStage.tsx`
- `apps/web/src/modules/wall/components/manager/stage/WallUpcomingTimeline.tsx`
- `apps/web/src/modules/wall/components/manager/recent/WallRecentMediaDetailsSheet.tsx`
- `apps/web/src/lib/api-types.ts`
- `packages/shared-types/src/wall.ts`
- `apps/api/app/Modules/Wall/Services/WallInsightsService.php`
- `apps/api/app/Modules/Wall/Services/WallSimulationService.php`
- `apps/api/app/Modules/Wall/Services/WallLiveSnapshotService.php`

### TDD executado

Backend:

- `WallLiveSnapshotTest`
  - garante normalizacao de origem no item atual;
  - garante `isVideo`, `durationSeconds` e `videoPolicyLabel` no snapshot;
  - garante `nextItem` com semantica de video quando a previsao for confiavel.
- `WallDiagnosticsTest`
  - garante `source_type` normalizado na simulacao;
  - garante `is_video`, `duration_seconds` e `video_policy_label` no `sequence_preview`.

Frontend:

- `WallUpcomingTimeline.test.tsx`
  - garante badge final de origem e selo de video na timeline;
- `WallHeroStage.test.tsx`
  - garante selo de video e duracao no palco atual e no proximo;
- `WallRecentMediaDetailsSheet.test.tsx`
  - garante copy operacional de video no detalhe lateral.

### Validacao executada

Backend:

- `cd apps/api && php artisan test --filter=WallLiveSnapshotTest`
- `7` testes passando
- `cd apps/api && php artisan test --filter=WallDiagnosticsTest`
- `6` testes passando
- `cd apps/api && php artisan test --filter=Wall`
- `82` testes passando

Frontend:

- `cd apps/web && npm run test -- src/modules/wall/components/manager/stage/WallHeroStage.test.tsx src/modules/wall/components/manager/stage/WallUpcomingTimeline.test.tsx src/modules/wall/components/manager/recent/WallRecentMediaDetailsSheet.test.tsx`
- `4` testes passando
- `cd apps/web && npm run test -- src/modules/wall`
- `206` testes passando
- `cd apps/web && npm run type-check`
- sem erros

### Criterio de pronto

- origem deixa de depender de regra espalhada pela UI
- video fica visivel como tipo de conteudo no cockpit
- a base fica pronta para ligar esse bloco ao plano dedicado de playback

## Etapa 5. Polimento final e regressao

### Objetivo

Fechar a rodada sem regressao e com semantica consistente entre palco, timeline, topo e diagnostico.

### Implementado nesta etapa

- o palco agora mantem contexto operacional quando o player ainda nao confirmou `currentItem` ou `nextItem`
- `Agora` e `Proxima` ganharam hierarquia visual propria, reduzindo leitura plana no bloco lateral
- a timeline horizontal agora usa estado de loading estavel com placeholders, evitando salto visual enquanto a previsao recalcula
- os cards da timeline ficaram menores no mobile para manter scroll horizontal mais previsivel
- a copy de estados vazios e loading foi revisada para linguagem mais operacional

### Suite final obrigatoria

Backend:

```powershell
cd apps/api
php artisan test --filter=WallLiveSnapshotTest
php artisan test --filter=WallInsightsTest
php artisan test --filter=WallDiagnosticsTest
php artisan test --filter=Wall
```

Frontend:

```powershell
cd apps/web
npm run test -- src/modules/wall/hooks/useWallLiveSnapshot.test.tsx
npm run test -- src/modules/wall/hooks/useWallRealtimeSync.test.tsx
npm run test -- src/modules/wall/components/manager/stage/WallAdvanceClock.test.tsx
npm run test -- src/modules/wall/components/manager/stage/WallHeroStage.test.tsx
npm run test -- src/modules/wall/components/manager/stage/WallUpcomingTimeline.test.tsx
npm run test -- src/modules/wall/components/manager/top/WallTotalMediaCard.test.tsx
npm run test -- src/modules/wall/pages/EventWallManagerPage.test.tsx
npm run test -- src/modules/wall
npm run type-check
```

### TDD executado

Frontend:

- `WallHeroStage.test.tsx`
  - garante contexto operacional quando o player ainda nao confirmou item atual e proximo;
- `WallUpcomingTimeline.test.tsx`
  - garante loading estavel com placeholders durante a recalculacao da previsao;
- `EventWallManagerPage.test.tsx`
  - continua validando integracao do palco com `Agora`, `Proxima`, toolbar, inspector e detalhe do player.

### Validacao executada

Backend:

- `cd apps/api && php artisan test --filter=WallLiveSnapshotTest`
- `7` testes passando
- `cd apps/api && php artisan test --filter=WallInsightsTest`
- `6` testes passando
- `cd apps/api && php artisan test --filter=WallDiagnosticsTest`
- `7` testes passando
- `cd apps/api && php artisan test --filter=Wall`
- `92` testes passando
- `1` `todo` ja existente fora deste escopo em `PagarmeClientTest`

Frontend:

- `cd apps/web && npm run test -- src/modules/wall/hooks/useWallLiveSnapshot.test.tsx src/modules/wall/hooks/useWallRealtimeSync.test.tsx src/modules/wall/components/manager/stage/WallAdvanceClock.test.tsx src/modules/wall/components/manager/stage/WallHeroStage.test.tsx src/modules/wall/components/manager/stage/WallUpcomingTimeline.test.tsx src/modules/wall/components/manager/top/WallTotalMediaCard.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx`
- `26` testes passando
- `cd apps/web && npm run test -- src/modules/wall`
- `208` testes passando
- `cd apps/web && npm run type-check`
- sem erros

### Criterio de aceite do bloco novo

- `advancedAt` vem de fonte mais autoritativa que o heartbeat puro
- `nextItem` aparece apenas quando a informacao for confiavel
- `displayed` deixa de ser `null`
- origem e video aparecem com semantica consistente
- o palco continua estavel e sem flicker
- o modulo `Wall` fecha verde em backend, frontend e type-check

## Checklist executavel do bloco novo

### Backend

  - [x] fonte autoritativa para `advancedAt`
  - [x] fallback por `heartbeat` mantido
  - [x] `liveSnapshot.nextItem` ou `upcomingItem`
  - [x] `totals.displayed` autoritativo
  - [x] idempotencia da contagem de exibidas
  - [x] `isVideo` no snapshot e na simulacao
  - [x] `durationSeconds` no snapshot e na simulacao
  - [x] normalizacao final de origem

### Frontend

  - [x] `WallAdvanceClock` com origem temporal autoritativa
  - [x] palco com bloco `Agora`
  - [x] palco com bloco `Proxima`
  - [x] `WallTotalMediaCard` com exibidas reais
  - [x] mapper unico de origem
  - [x] badge final de video e duracao
  - [x] detalhe lateral com copy operacional para video
  - [x] responsividade validada do palco com `Agora` e `Proxima`

### Testes

  - [x] testes de feature do snapshot autoritativo
  - [x] testes de feature da contagem de exibidas
  - [x] testes de unit ou feature da normalizacao de origem
  - [x] testes do palco com `nextItem`
  - [x] testes do relogio com correcao de `advancedAt`
  - [x] testes da timeline com origem e video
  - [x] regressao `Wall` no backend
  - [x] regressao `Wall` no frontend
  - [x] `type-check` verde

## Fontes oficiais complementares para esta rodada

- shadcn/ui Hover Card:
  - https://ui.shadcn.com/docs/components/hover-card
- shadcn/ui Tooltip:
  - https://ui.shadcn.com/docs/components/tooltip
- shadcn/ui Carousel:
  - https://ui.shadcn.com/docs/components/carousel
- TanStack Query important defaults:
  - https://tanstack.com/query/latest/docs/framework/react/guides/important-defaults
- TanStack Query render optimizations:
  - https://tanstack.com/query/latest/docs/framework/react/guides/render-optimizations
- W3C APG Tabs Pattern:
  - https://www.w3.org/WAI/ARIA/apg/patterns/tabs/
- W3C APG Toolbar Pattern:
  - https://www.w3.org/WAI/ARIA/apg/patterns/toolbar/
- W3C APG Carousel Pattern:
  - https://www.w3.org/WAI/ARIA/apg/patterns/carousel/
- Motion AnimatePresence:
  - https://motion.dev/docs/react-animate-presence
- Motion layout animations:
  - https://motion.dev/docs/react-layout-animations
