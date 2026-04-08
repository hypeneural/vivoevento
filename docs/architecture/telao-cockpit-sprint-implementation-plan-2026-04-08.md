# Plano De Implementacao Da Sprint: Cockpit Do Telao Ao Vivo

Data do plano: 2026-04-08

## Objetivo

Entregar a primeira versao realmente operacional do novo cockpit do telao em `/events/:id/wall`, mantendo a stack atual e priorizando:

- topo mais vivo e util;
- ligacao clara entre insights do evento, palco, timeline recente e detalhe lateral;
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

- `totals.displayed` continua `null` nesta fase:
  - o backend atual nao possui telemetria acumulada de exibidas;
  - so existe dado operacional de runtime atual por player;
  - portanto o contrato precisa manter esse campo como `nullable` ate entrar snapshot ou historico autoritativo.
- o trilho recente ficou melhor com `ScrollArea` e clique explicito:
  - sem autoplay;
  - sem deslocamento automatico brigando com leitura;
  - com ligacao direta entre item do topo e palco atual.
- a primeira ligacao entre topo e palco ja ficou validada:
  - clicar numa midia recente destaca o item no trilho;
  - o palco passa a refletir a midia escolhida;
  - o detalhe lateral dedicado continua como proxima fatia, nao como pre-requisito para este bloco.

## Resultado esperado ao fim da sprint

Ao final desta sprint, a tela `/events/:id/wall` deve permitir:

- visualizar no topo um trilho vivo com:
  - `Quem mais enviou`
  - `Total de midias`
  - `Ultimas chegadas`
- selecionar uma midia recente e refletir isso no palco e no detalhe lateral;
- carregar os insights com um unico endpoint agregado;
- operar com realtime hibrido:
  - WebSocket como principal;
  - polling leve apenas em degradacao;
  - invalidacao geral ao reconectar;
- manter a interface estavel durante refetch com `placeholderData`;
- usar `Skeleton` apenas no primeiro paint;
- manter copy menos tecnica e mais clara para operador leigo;
- preservar responsividade sem criar uma segunda pagina especifica.

## Escopo desta sprint

### Entra agora

- `GET /events/{event}/wall/insights`
- `WallTopInsightsRail`
- `WallTopContributorCard`
- `WallTotalMediaCard`
- `WallLiveMediaTimelineStrip`
- `WallRecentMediaDetailsSheet`
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

- [useWallPollingFallback.test.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallPollingFallback.test.ts)
- [useWallRealtimeSync.test.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallRealtimeSync.test.ts)

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
- [WallDiagnosticsTest.php](C:/laragon/www/eventovivo/apps/api/tests/Feature/Wall/WallDiagnosticsTest.php)
- [WallInsightsServiceTest.php](C:/laragon/www/eventovivo/apps/api/tests/Unit/Wall/WallInsightsServiceTest.php)

### Frontend

Criar ou expandir:

- [EventWallManagerPage.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/pages/EventWallManagerPage.test.tsx)
- [useWallTopInsights.test.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallTopInsights.test.ts)
- [useWallPollingFallback.test.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallPollingFallback.test.ts)
- [useWallRealtimeSync.test.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallRealtimeSync.test.ts)
- [useWallSelectedMedia.test.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallSelectedMedia.test.ts)
- [useWallRecentMediaTimeline.test.ts](C:/laragon/www/eventovivo/apps/web/src/modules/wall/hooks/useWallRecentMediaTimeline.test.ts)
- [WallTopInsightsRail.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/components/manager/top/WallTopInsightsRail.test.tsx)
- [WallLiveMediaTimelineStrip.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/components/manager/top/WallLiveMediaTimelineStrip.test.tsx)
- [WallRecentMediaDetailsSheet.test.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/wall/components/manager/recent/WallRecentMediaDetailsSheet.test.tsx)

## Comandos de validacao por bloco

### Backend

```powershell
cd apps/api
php artisan test --filter=WallInsightsTest
php artisan test --filter=WallDiagnosticsTest
php artisan test --filter=WallInsightsServiceTest
```

### Frontend

```powershell
cd apps/web
npm run test -- src/modules/wall/pages/EventWallManagerPage.test.tsx
npm run test -- src/modules/wall/hooks/useWallTopInsights.test.ts
npm run test -- src/modules/wall/hooks/useWallPollingFallback.test.ts
npm run test -- src/modules/wall/hooks/useWallRealtimeSync.test.ts
npm run test -- src/modules/wall/hooks/useWallSelectedMedia.test.ts
npm run test -- src/modules/wall/hooks/useWallRecentMediaTimeline.test.ts
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

- [ ] rota `GET /events/{event}/wall/insights`
- [ ] controller fino
- [ ] query de agregacao
- [ ] service de montagem
- [ ] resource de resposta
- [ ] `previewUrl` em thumb
- [ ] `source` normalizado
- [ ] testes de contrato e policy

### Frontend

- [ ] query keys novas
- [ ] `getEventWallInsights`
- [ ] `useWallTopInsights`
- [ ] `WallTopInsightsRail`
- [ ] `WallTopContributorCard`
- [ ] `WallTotalMediaCard`
- [ ] `WallLiveMediaTimelineStrip`
- [ ] `WallRecentMediaDetailsSheet`
- [ ] `useWallSelectedMedia`
- [ ] `useWallRecentMediaTimeline`
- [ ] `useWallRealtimeSync`
- [ ] `useWallPollingFallback`

### UX/UI

- [ ] copy simplificada
- [ ] `Skeleton` so no primeiro paint
- [ ] `placeholderData` durante refetch
- [ ] selecao persistida
- [ ] hover e focus states
- [ ] detalhe responsivo
- [ ] toolbar com navegacao por setas
- [ ] tabs com estrategia correta de ativacao
- [ ] trilho sem autoplay implicito
- [ ] nenhum dado essencial dependente apenas de hover

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
