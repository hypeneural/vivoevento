# Performance do Painel Administrativo: Frontend, Requisicoes e API

Data da analise: 2026-04-09

## Objetivo

Documentar, com base no codigo atual do monorepo `eventovivo`, o estado de performance do painel administrativo e responder:

1. onde estao hoje os principais gargalos de carregamento;
2. o que ja esta bom na stack atual;
3. o que precisamos melhorar no frontend, nas requisicoes e na API;
4. qual backlog tecnico faz mais sentido para acelerar dashboard, filtros e subpaginas;
5. quais melhorias de banco, cache e arquitetura vao sustentar crescimento sem degradar a UX.

Esta analise nao e um benchmark sintetico de tempo real em producao. Ela foi feita a partir de:

- leitura do codigo real do frontend e backend;
- validacao da estrategia atual de cache e carregamento;
- inspecao de payloads e pontos de acoplamento;
- revisao de queries e migrations relevantes;
- build de producao do painel para verificar bundle e chunking.

## Stack real observada em 2026-04-09

### Frontend SPA

- React `18.3.1`
- TypeScript `5.8.3`
- Vite `5.4.19`
- `@vitejs/plugin-react-swc` `3.11.0`
- TanStack Query `5.83.0`
- React Router DOM `6.30.1`
- React Hook Form `7.61.1` + Zod `3.25.76`
- TailwindCSS `3.4.17`
- Radix UI primitives + componentes locais em padrao shadcn/ui
- Framer Motion `12.38.0`
- Recharts `2.15.4`
- `pusher-js` `8.4.3` para conexoes Reverb no frontend
- `vite-plugin-pwa` `1.2.0` + Workbox

### Backend e servicos de aplicacao

- PHP `>=8.3 <8.4`
- Laravel Framework `^13.0`
- Laravel Reverb `^1.9`
- Laravel Horizon `^5.45`
- Laravel Pulse `^1.7`
- Laravel Sanctum `^4.3`
- Laravel Fortify `^1.36`
- Laravel Pennant `^1.22`
- Laravel Telescope `^5.19`
- Spatie Data `^4.20`
- Spatie Activitylog `^4.12`
- Spatie Medialibrary `^11.21`
- Spatie Permission `^7.2`
- Predis habilitado no projeto

### Banco, cache e realtime

- PostgreSQL como banco principal, conforme convencoes do repositorio
- Redis como base de cache/filas/realtime, conforme stack declarada do projeto
- Realtime administrativo e publico apoiado em Laravel Reverb
- Filas operacionais com Horizon disponivel no backend
- Observabilidade aplicacional ja preparada com Pulse

### Nota importante de consistencia

O `AGENTS.md` e algumas convencoes do repositorio ainda citam Laravel 12, mas o estado real do codigo em `2026-04-09` mostra `laravel/framework:^13.0` no `composer.json`.

Para evitar decisao tecnica baseada em versao errada, a documentacao de stack do projeto deveria ser alinhada para Laravel 13.

## Escopo revisado

### Frontend

- `apps/web/package.json`
- `apps/web/src/lib/query-client.ts`
- `apps/web/src/lib/api.ts`
- `apps/web/src/app/providers/AuthProvider.tsx`
- `apps/web/src/app/routing/AdminWarmup.tsx`
- `apps/web/src/app/routing/route-preload.ts`
- `apps/web/src/modules/dashboard/DashboardPage.tsx`
- `apps/web/src/modules/dashboard/hooks/useDashboardStats.ts`
- `apps/web/src/modules/events/EventsListPage.tsx`
- `apps/web/src/modules/events/EventDetailPage.tsx`
- `apps/web/src/modules/events/components/EventEditorPage.tsx`
- `apps/web/src/modules/events/services/events.service.ts`
- `apps/web/src/modules/analytics/AnalyticsPage.tsx`
- `apps/web/src/modules/partners/components/PartnerDetailSheet.tsx`
- `apps/web/src/modules/clients/ClientsPage.tsx`
- `apps/web/src/modules/media/MediaPage.tsx`
- `apps/web/src/modules/moderation/ModerationPage.tsx`
- `apps/web/src/modules/wall/pages/EventWallManagerPage.tsx`
- `apps/web/src/shared/components/GlobalSearch.tsx`
- `apps/web/src/shared/hooks/useGlobalSearch.ts`
- `apps/web/vite.config.ts`
- `apps/web/src/sw.ts`

### Backend

- `apps/api/composer.json`
- `apps/api/app/Modules/Dashboard/Http/Controllers/DashboardController.php`
- `apps/api/app/Modules/Dashboard/Actions/BuildDashboardStatsAction.php`
- `apps/api/app/Modules/Analytics/Http/Controllers/AnalyticsController.php`
- `apps/api/app/Modules/Analytics/Services/AnalyticsMetricsService.php`
- `apps/api/app/Modules/Events/Http/Controllers/EventController.php`
- `apps/api/app/Modules/Events/Queries/ListEventsQuery.php`
- `apps/api/database/migrations/2024_01_01_000011_create_media_tables.php`
- `apps/api/database/migrations/2024_01_01_000013_create_analytics_events_table.php`
- `apps/api/database/migrations/2024_01_01_000015_create_play_runtime_tables.php`
- `apps/api/database/migrations/2026_04_01_000019_add_perceptual_hash_to_event_media.php`

## Resumo executivo

Leitura curta:

- a stack atual do painel nao esta ruim; ela ja usa lazy routes, React Query com defaults razoaveis e alguns pontos de `useDeferredValue`;
- o problema principal nao e um unico endpoint lento, e sim a soma de quatro fatores:
  - muitas queries em paralelo no carregamento inicial de paginas complexas;
  - endpoints pesados sendo reutilizados para casos simples de filtro e autocomplete;
  - payloads de detalhe grandes demais para o primeiro paint;
  - indices de banco ainda insuficientes para os filtros e agregacoes mais frequentes;
- a dashboard ja tem cache de 60 segundos no backend, mas ainda entrega um payload grande e montado por varias consultas orquestradas na mesma requisicao;
- filtros do admin ja evitam parte do flicker visual, mas hoje ainda desperdicam rede e CPU porque a camada `api.ts` nao foi estruturada para cancelamento consistente de requests concorrentes;
- algumas subpaginas do admin abrem queries demais de uma vez, mesmo quando parte da informacao esta atras da dobra, em aba secundaria ou dentro de um detalhe lateral;
- o bundle do painel esta razoavelmente code-splitado, mas ainda carrega chunks compartilhados grandes no admin, principalmente `vendor-ui`, `vendor-charts` e `vendor-motion`;
- o maior retorno de curto prazo viria de:
  - abortar requests antigos em digitacao e troca rapida de filtros;
  - criar endpoints leves de options para selects;
  - carregar detalhes e abas sob demanda;
  - dividir payloads "criticos para first paint" de payloads "secundarios";
  - adicionar indices faltantes em `event_media`, `analytics_events` e `play_game_sessions`.

## Pesquisa externa aplicada a nossa stack

Esta secao cruza o que foi observado no codigo com documentacao oficial atual de React, TanStack Query, Vite, Laravel, PostgreSQL e Redis.

### React 18 e TanStack Query

Os docs oficiais de React deixam claro que `useDeferredValue` melhora responsividade de render, mas nao reduz requests por si so. Em outras palavras:

- continuar usando `useDeferredValue` faz sentido para input + listas pesadas;
- mas isso nao substitui debounce, cancelamento e desenho correto de queries;
- para busca global e filtros, nosso gargalo continua sendo rede/API se os requests antigos nao forem abortados.

Os docs oficiais do TanStack Query reforcam exatamente o que esta faltando hoje na nossa camada:

- cada query function recebe um `AbortSignal`;
- se esse `signal` for realmente consumido no `fetch`, queries obsoletas podem ser canceladas de verdade;
- `prefetchQuery` serve para evitar waterfalls e preparar navegacoes provaveis;
- `invalidateQueries` continua sendo o mecanismo padrao para sincronizar listagens e detalhes apos mutacoes;
- `staleTime: Infinity` e melhor do que `static` para dados estaveis que ainda precisam reagir a invalidacao manual.

O que isso significa para o Evento Vivo:

- `useDeferredValue` deve continuar;
- a camada `api.ts` precisa ser adaptada para `signal`;
- filtros, autocomplete e busca global devem usar cancelamento de request;
- selects e dados de apoio devem usar `staleTime` mais alto;
- rotas provaveis devem combinar preload de chunk com `prefetchQuery`.

### Vite 5

Os docs oficiais do Vite recomendam:

- fazer dynamic import de dependencias grandes usadas apenas em alguns fluxos;
- evitar barrel files quando eles puxam transformacoes e imports demais;
- perfilar transformacoes e plugins quando o projeto cresce;
- investigar waterfalls internos de transform e aquecer apenas arquivos muito usados em dev.

O que isso significa para o Evento Vivo:

- o `manualChunks` atual esta no caminho certo;
- ainda precisamos revisar o custo real de `vendor-ui`, `vendor-charts`, `vendor-motion` e `vendor-phaser`;
- charts e motion devem continuar fora do caminho critico das rotas administrativas simples;
- service worker deve continuar focado em assets e superficies publicas, nao em API autenticada do admin.

Nota de validacao:

- confirmei na fonte oficial do Vite a existencia do anuncio de `Vite 8 Beta` com Rolldown;
- nao tratei isso como P0 nem como ganho imediato de UX do admin;
- no contexto atual do Evento Vivo, isso continua sendo aposta de modernizacao e produtividade, nao destravador principal de runtime.

### Laravel 13: cache, broadcasting, Reverb e observabilidade

Os docs oficiais do Laravel trazem algumas alavancas muito alinhadas com os nossos problemas atuais:

- `Cache::flexible` implementa stale-while-revalidate, ideal para dashboard, analytics e options que podem tolerar dado levemente desatualizado por alguns segundos;
- `Cache::tags` permite invalidacao mais cirurgica por organizacao, evento e modulo;
- `Cache::lock` ajuda a evitar stampede quando varias requisicoes tentam recalcular o mesmo cache expirado;
- `Cache::memo` reduz hits repetidos dentro da mesma request/job quando o mesmo valor e lido varias vezes;
- `broadcast(...)->toOthers()` + `X-Socket-ID` evitam processar no cliente a mesma alteracao duas vezes;
- `ShouldRescue` e util quando broadcast e complementar, nao critico, e nao deve derrubar a UX da acao principal;
- Pulse ja oferece visibilidade de slow requests, slow queries, filas e cache hit/miss;
- os docs do Reverb mostram monitoracao via Pulse e escala horizontal via Redis;
- os docs do Reverb tambem deixam claro que o loop padrao `stream_select` costuma limitar o processo a cerca de `1.024` open files, entao volume alto de conexoes exige tuning ou `ext-uv`.

O que isso significa para o Evento Vivo:

- dashboard e analytics deveriam migrar de `remember(60)` puro para uma estrategia mais explicita de fresh/stale por tipo de dado;
- invalidacao de cache precisa ser pensada por tenant e por entidade, nao so por TTL;
- hoje nao encontrei uso consistente de `toOthers` / `X-Socket-ID` no projeto, entao existe espaco para reduzir duplicidade entre resposta HTTP, optimistic update e evento realtime;
- Pulse e Horizon devem sair do papel como instrumentos de decisao, nao apenas como dependencias instaladas.

### PostgreSQL

Os docs oficiais do PostgreSQL reforcam dois pontos importantes:

- indices multicoluna funcionam melhor quando as colunas de filtro mais seletivas e mais frequentes aparecem na esquerda do indice;
- partial indexes sao uteis quando queremos acelerar subconjuntos quentes e evitar indexar valores comuns demais;
- `EXPLAIN ANALYZE` deve ser executado nas queries reais, porque custo estimado e custo real divergem facilmente em tabelas grandes.

O que isso significa para o Evento Vivo:

- nossos novos indices em `event_media`, `analytics_events` e `play_game_sessions` precisam ser desenhados a partir dos filtros reais, nao por intuicao;
- vale considerar partial indexes para filas e estados quentes, por exemplo dados `pending`, `published` ou `processing` quando isso reduzir tamanho e custo de escrita;
- antes de criar muitos indices, precisamos validar `EXPLAIN ANALYZE` dos endpoints mais quentes.

### Redis

Os docs oficiais do Redis reforcam tres pontos praticos:

- a politica de eviction precisa combinar com o padrao de acesso;
- `allkeys-lru` e um bom default quando existe um subconjunto quente acessado com muito mais frequencia;
- o proprio Redis recomenda considerar duas instancias separadas quando ha mistura de cache e chaves persistentes;
- keyspace notifications e Pub/Sub sao fire-and-forget, entao nao servem como base autoritativa de eventos de negocio.

O que isso significa para o Evento Vivo:

- se Redis estiver compartilhando cache, queue e coordenacao de realtime, a memoria precisa ser tratada como recurso critico;
- como recomendacao arquitetural, faz sentido separar pelo menos conexoes e politicas por papel, e idealmente separar instancias quando o volume crescer;
- cache invalidation importante nao deve depender de notificacoes efemeras de Redis Pub/Sub; o certo e usar eventos de dominio, Reverb e invalidacao explicita de query/cache.

## Validacoes objetivas desta revisao

Esta rodada nao ficou so em opiniao arquitetural. Os pontos abaixo foram validados no codigo e na configuracao atual do projeto.

### Fatos locais confirmados

- o fallback real de `config/cache.php` continua `database`, embora os testes usem override para `array`;
- o fallback real de `config/queue.php` continua `database`, embora os testes usem override para `sync`;
- existe store Redis configurado para cache e conexao Redis separada em DB logico diferente:
  - `database.redis.default.database = 0`
  - `database.redis.cache.database = 1`
- Horizon esta configurado para operar sobre Redis;
- o projeto agenda `horizon:snapshot` a cada 5 minutos;
- o projeto agenda `queue:monitor` por fila observada;
- o pacote Pulse esta instalado, mas nao existe `config/pulse.php` versionado no repo hoje;
- o projeto ja tem gates para acesso a Horizon, Telescope e Pulse;
- o projeto ja possui testes de configuracao operacional para Horizon;
- esta revisao adicionou testes de caracterizacao para cache, Redis e agendamentos operacionais.

### Testes adicionados nesta revisao

- `apps/api/tests/Unit/Shared/PerformanceArchitectureCharacterizationTest.php`

### Testes executados

- `php artisan test tests/Unit/Shared/PerformanceArchitectureCharacterizationTest.php`
- `php artisan test tests/Unit/Shared/OperationalDashboardAccessTest.php`
- `php artisan test tests/Unit/MediaProcessing/HorizonConfigTest.php`

Resultado:

- 8 testes passando
- 61 assertions

## O que realmente importa agora

Nem tudo que melhora performance tem o mesmo impacto. Pela combinacao de codigo real, configuracao atual e docs oficiais, a prioridade mais importante para o Evento Vivo hoje e esta:

### P0 realmente critico

- definir SLO, SLI e budget por tela e por endpoint;
- padronizar contrato de leitura por superficie:
  - bootstrap de rota;
  - section/tab lazy;
  - options/autocomplete;
- implementar cancelamento real com `AbortSignal` nas queries criticas;
- remover o uso de endpoints pesados para selects e filtros;
- separar first paint de conteudo secundario nas telas grandes;
- reforcar indices e validar `EXPLAIN ANALYZE` nas consultas mais quentes;
- tornar a estrategia de cache compativel com a invalidacao planejada;
- instrumentar Pulse, Horizon e Reverb de forma operacional e visivel.

### P1 importante, mas depois da base

- read models para dashboard e analytics;
- modularizacao forte de detalhes pesados;
- padrao unico de mutacao + invalidacao + broadcast;
- separacao mais forte de Redis por papel operacional;
- budgets e gates automatizados no CI para evitar regressao.

### P2 valido, mas nao destrava a UX agora

- upgrade para React 19;
- benchmark de Vite 8 com Rolldown;
- adocao de React Compiler;
- tuning mais agressivo de processo longo e concorrencia.

Esses itens de P2 sao reais e oficiais, mas nao corrigem o problema atual de overfetching, payload, SQL e topologia de cache.

## Contratos enterprise de performance

Se a arquitetura quiser subir de “analise boa” para “padrao enterprise”, ela precisa de contratos objetivos. Abaixo esta a proposta inicial.

### SLOs e budgets sugeridos

| Superficie | SLO primario | Budget inicial sugerido |
|---|---|---|
| Dashboard bootstrap | p95 de first useful content | ate `1.5s` com cache quente, ate `3.0s` sem cache |
| Filtro de listas | p95 de feedback util apos alteracao | ate `700ms` |
| Options/autocomplete | p95 de resposta | ate `250ms` a `400ms` |
| Detail bootstrap | p95 de abertura de rota | ate `1.2s` com dados essenciais |
| Section lazy | p95 de abertura de aba | ate `800ms` |
| Wall live endpoints | staleness maxima aceitavel | `0s` a `5s` conforme superficie |
| Dashboard/analytics quase-live | atraso maximo tolerado | `15s` a `60s` por bloco |
| Payload JSON bootstrap | tamanho maximo por rota | preferencialmente `< 100KB`, ideal `< 60KB` |
| Query count backend | SQL por request bootstrap | budget por endpoint, evitando fan-out opaco |
| Queue wait | wait time por fila critica | dentro dos thresholds declarados no `horizon.php` |

Esses numeros sao metas iniciais propostas, nao medicao atual consolidada.

### SLIs minimos que precisam existir

- p50, p95 e p99 por rota do admin;
- p50, p95 e p99 por endpoint quente;
- tamanho de payload JSON por endpoint de leitura;
- numero de queries SQL por request;
- tempo de serializacao de resource;
- hit ratio por classe de cache;
- queue wait time por fila critica;
- numero de requests por navegacao;
- numero de chunks e tamanho de chunk por rota.

## Contratos de leitura por superficie

Hoje parte da aplicacao ainda pensa em endpoint como “CRUD generico”. Para arquitetura enterprise do admin, isso precisa virar contrato de leitura por superficie.

### 1. Bootstrap de rota

Objetivo:

- carregar apenas o necessario para first paint e tomada de contexto.

Regras:

- payload pequeno;
- latencia orcada;
- sem relacoes profundas opcionais;
- sem listas auxiliares completas;
- sem charts pesados se nao forem criticos.

Exemplos desejados:

- `/dashboard/bootstrap`
- `/events/{id}/bootstrap`
- `/events/{id}/wall/bootstrap`

### 2. Section ou tab lazy

Objetivo:

- carregar por contexto quando o usuario abre uma aba, card, sheet ou painel.

Regras:

- cada secao deve ter ownership de dados claro;
- relacoes profundas ficam aqui;
- a tela principal nao depende disso para nascer.

Exemplos desejados:

- `/events/{id}/team`
- `/events/{id}/media-intelligence`
- `/events/{id}/content-moderation`
- `/partners/{id}/activity`

### 3. Options/autocomplete

Objetivo:

- responder rapido, com payload minimo, cacheavel e filtravel.

Regras:

- devolver apenas `id`, `label` e metadados minimos;
- nunca reutilizar endpoint de listagem administrativa rica;
- aceitar busca incremental e pagina pequena quando necessario.

Exemplos desejados:

- `/events/options`
- `/clients/options`
- `/organizations/options`
- `/whatsapp/instances/options`

## Observabilidade fim a fim

### Aplicacao

O projeto ja tem componentes para isso, mas precisa transformar dependencias instaladas em operacao real:

- Pulse para:
  - slow requests;
  - slow jobs;
  - slow queries;
  - slow outgoing requests;
  - cache hit/miss;
  - throughput de filas;
- Horizon para:
  - wait time;
  - throughput;
  - snapshots de metricas;
  - backlog por fila;
- Reverb + Pulse para:
  - conexoes;
  - mensagens;
  - atividade por servidor;
  - degradacao de superficie live.

### Banco

Nao basta rodar `EXPLAIN ANALYZE` manualmente de vez em quando.

Camada enterprise de validacao:

- `EXPLAIN ANALYZE` nas queries mais quentes;
- `ANALYZE`/autovacuum garantindo estatisticas atualizadas;
- `auto_explain` com rollout controlado para capturar planos de queries lentas;
- revisao recorrente de planos e scans anormais em producao.

### Frontend

Precisamos medir por navegacao:

- tempo ate shell pronto;
- tempo ate first useful content;
- tempo de filtros;
- numero de requests disparados;
- requests cancelados;
- rotas com waterfall de chunk ou de API.

## Topologia e prerequisitos operacionais

### Cache tags: prerequisito explicito

O Laravel documenta que cache tags nao funcionam com `file`, `dynamodb` ou `database`.

Como o fallback atual do projeto para cache ainda e `database`, a doc precisa ser explicita:

- se a estrategia oficial depender de cache tags, entao Redis compativel com tags precisa virar requisito de implantacao;
- isso pode ser feito tornando Redis o store default ou usando store Redis explicitamente nos fluxos que dependem de tags;
- sem essa decisao, “invalidacao por tags” continua correta conceitualmente, mas incompleta operacionalmente.

### Redis e papeis operacionais

O estado atual ja separa DB logico default e cache dentro do Redis configurado, o que e melhor do que nada. Mesmo assim, do ponto de vista enterprise, isso ainda nao substitui uma topologia explicita por papel.

Arquitetura recomendada:

- cache;
- queue/Horizon;
- Reverb/scaling.

No minimo:

- conexoes separadas;
- prefixes separados;
- budgets de memoria separados;
- politicas de eviction conhecidas.

No ideal, quando o volume justificar:

- instancias separadas.

### Reverb

Do ponto de vista de escala, os docs oficiais tornam estes pontos obrigatorios:

- monitorar conexoes e mensagens via Pulse;
- rodar `pulse:check` no servidor Reverb quando a integracao estiver ativa;
- ajustar limite de open files;
- considerar `ext-uv` acima de ~1000 conexoes concorrentes;
- tratar Nginx/reverse proxy como parte da arquitetura, nao como detalhe de deploy.

## Governanca de regressao

Um admin enterprise nao pode depender apenas de “lembrar de otimizar”.

Precisamos de gates automatizados para rotas e endpoints criticos:

### Gates de backend

- budget de payload JSON por endpoint bootstrap;
- budget de queries SQL por endpoint;
- deteccao de N+1;
- budget de tempo em teste de smoke quando viavel;
- contract tests de recursos bootstrap/section/options.

### Gates de frontend

- budget de requests por navegacao em rotas criticas;
- budget de tamanho de chunk por rota;
- budget de dependencias do shell do admin;
- cobertura para cancelamento de request em filtros criticos;
- cobertura para lazy load por section/tab.

### Gates de arquitetura

- nenhuma rota grande nasce sem definir:
  - bootstrap;
  - sections lazy;
  - options;
  - freshness class;
  - regra de invalidacao;
  - SLO primario.

## O que ja esta bom na stack atual

### 1. Base de cache do frontend

O `QueryClient` ja define um baseline coerente para painel administrativo:

- `staleTime` de 5 minutos;
- `gcTime` de 10 minutos;
- `refetchOnWindowFocus: false`;
- retry limitado e sem insistir em `401` e `403`.

Isso e melhor do que o padrao agressivo de refetch e reduz ruído de rede para navegacao administrativa.

### 2. Code splitting por rota

O `App.tsx` e o `route-preload.ts` ja usam import dinamico. A navegacao principal do admin nao esta presa a um bundle unico monolitico.

Existe tambem um `AdminWarmup.tsx` que faz preload de rotas comuns em condicoes favoraveis de rede e memoria. Isso e uma boa direcao.

### 3. Uso parcial de React 18 para responsividade

Listas e filtros do admin ja usam `useDeferredValue` em varios pontos, especialmente em:

- eventos;
- analytics;
- media;
- moderacao;
- clientes.

Isso melhora a sensacao de responsividade ao digitar, mesmo antes de qualquer otimizacao mais profunda de rede.

### 4. Cache backend em dashboard e analytics

Os endpoints de dashboard e analytics ja usam `Cache::remember(...)` com TTL de 60 segundos. Para dados de cockpit administrativo, isso ja ajuda a reduzir custo repetido.

## Gargalos observados no frontend

### 1. Requests concorrentes demais para carregamento inicial

Algumas paginas do admin abrem muitas queries ao mesmo tempo. O problema nao e apenas "quantidade", e sim abrir tudo como se tudo fosse critico para o primeiro paint.

### Analytics

`apps/web/src/modules/analytics/AnalyticsPage.tsx` monta, dependendo do estado:

- busca de organizacoes;
- busca de clientes;
- busca de eventos;
- carregamento do item selecionado da organizacao;
- carregamento do item selecionado do cliente;
- carregamento do item selecionado do evento;
- analytics global;
- analytics por evento.

Mesmo com `placeholderData: keepPreviousData`, a pagina ainda pode gerar waterfall e pressao desnecessaria quando o operador troca filtros em sequencia.

### Wall manager

`apps/web/src/modules/wall/pages/EventWallManagerPage.tsx` abre ao entrar:

- detalhe do evento;
- settings do wall;
- options globais;
- diagnostics;
- ads;
- top insights;
- live snapshot;
- simulacao, quando o draft muda;
- alem de polling fallback em partes da tela.

Para uma tela operacional complexa isso e compreensivel, mas hoje tudo nasce muito junto. Isso aumenta TTFB percebido, custo de CPU no cliente e custo de backend.

### Event detail

`apps/web/src/modules/events/EventDetailPage.tsx` carrega logo de inicio:

- detalhe completo do evento;
- status comercial;
- midias recentes do evento.

O detalhe do evento ja vem muito carregado do backend. Ao somar `commercialStatus` e `media`, a abertura da pagina tende a ficar mais pesada do que o necessario para mostrar a visao geral.

### Event editor

`apps/web/src/modules/events/components/EventEditorPage.tsx` abre pelo menos:

- detalhe do evento;
- lista de clientes;
- lista de instancias WhatsApp com `per_page=100`;
- status operacional de Telegram.

Para criacao/edicao, parte disso poderia ser adiada para quando o bloco correspondente for realmente usado.

### Partner detail sheet

`apps/web/src/modules/partners/components/PartnerDetailSheet.tsx` abre todas as queries do painel lateral ao mesmo tempo:

- detalhe;
- eventos;
- clientes;
- equipe;
- grants;
- activity.

Esse e um caso claro de melhoria. O correto aqui e carregar:

- detalhe imediatamente;
- a aba ativa sob demanda;
- as demais abas somente quando o usuario navegar para elas.

### 2. Filtros ja estao menos ruins, mas ainda desperdicam requests

O admin usa debounce e `useDeferredValue`, o que e positivo. O problema e que a camada `apps/web/src/lib/api.ts` nao esta organizada para propagacao padronizada de `AbortSignal` nas query functions do React Query.

Consequencia pratica:

- o usuario digita rapido;
- a UI usa o valor deferido;
- requests antigos continuam vivos;
- o navegador, a API e o banco ainda processam chamadas que ja perderam valor;
- isso piora cenarios de autocomplete, busca global e filtros de listas grandes.

O efeito e mais sensivel em:

- `GlobalSearch`;
- `AnalyticsPage`;
- `EventsListPage`;
- `MediaPage`;
- `ModerationPage`;
- `ClientsPage`;
- `PartnersPage`.

### 3. Endpoints pesados usados para popular filtros e selects

Hoje varias telas usam recursos "de listagem completa" para preencher dropdowns simples.

Exemplos encontrados:

- `MediaPage` busca `/events` com `per_page: 100`;
- `ModerationPage` busca `/events` com `per_page: 100`;
- `events.service.ts` usa `/clients` com `per_page: 100` para opcoes do editor;
- `EventEditorPage` tambem usa lista ampla de instancias WhatsApp.

Isso e um anti-pattern de performance porque:

- um select nao precisa do mesmo payload de uma listagem administrativa;
- esses endpoints trazem relacionamentos, contagens e campos desnecessarios;
- o custo de serializacao e parse cresce sem melhorar UX.

O painel precisa de endpoints dedicados de `options`, com resposta pequena, cacheavel e pensada para autocomplete.

### 4. Sessao e shell do admin fora da estrategia principal de cache

`AuthProvider.tsx` ainda hidrata a sessao fora do React Query, usando estado local e persistencia propria. Isso nao e um bug, mas limita:

- invalidacao centralizada;
- prefetch da sessao;
- reaproveitamento de cache entre header, sidebar e paginas;
- padronizacao de loading state.

Em um admin grande, vale mover `me` para uma query canonical, com hydration e invalidador unico.

### 5. Bundle do admin ainda tem chunks compartilhados grandes

Build validado em `2026-04-09`:

- `vendor-phaser`: 1,478.40 kB minificado;
- `vendor-charts`: 422.01 kB;
- `vendor-ui`: 319.46 kB;
- `vendor-motion`: 127.88 kB;
- `index`: 206.65 kB;
- `WallPage`: 132.73 kB;
- `EventDetailPage`: 86.54 kB;
- `EventEditorPage`: 70.06 kB;
- `ModerationPage`: 59.91 kB;
- precache PWA: 148 entradas, 4191.64 KiB.

O ponto importante aqui:

- o painel nao esta totalmente monolitico;
- mas ainda existe custo relevante de bibliotecas compartilhadas no admin;
- `recharts`, `radix` e `framer-motion` aparecem como vetores concretos;
- o warning de chunk acima de 500 kB continua ativo.

## Gargalos observados na API e no backend

### 1. Dashboard cacheada, mas ainda pesada por composicao

O endpoint de dashboard ja usa cache no controller, o que e bom. Mesmo assim, `BuildDashboardStatsAction.php` monta um payload relativamente grande com:

- KPIs;
- changes;
- uploads por hora;
- eventos por tipo;
- engagement por modulo;
- eventos recentes;
- fila de moderacao;
- top partners;
- alerts.

Do ponto de vista de UX, isso significa que a pagina depende de um unico payload "tudo ou nada". Se qualquer parte for cara, o primeiro paint do dashboard inteiro atrasa.

O problema aqui nao e so tempo de banco. E granularidade.

### 2. Analytics com consultas e agregacoes custosas

`AnalyticsController.php` tambem cacheia por 60 segundos, mas `AnalyticsMetricsService.php` concentra varias agregacoes por periodo, incluindo leituras em:

- `analytics_events`;
- `play_game_sessions`;
- relacoes de jogos do evento;
- agrupamentos por data e por tipo de evento.

Hoje a cache ajuda. Mas, conforme o volume crescer, esse modelo tende a ficar caro porque:

- as consultas somam filtros por periodo;
- parte delas usa `whereBetween` em colunas sem o melhor indice composto;
- a camada ainda recalcula conjuntos semelhantes dentro da mesma requisicao.

### 3. Payloads de listagem e detalhe muito genericos

### Listagem de eventos

`ListEventsQuery.php` ja faz eager loading de:

- organizacao;
- cliente;
- modules;
- wallSettings;
- `withCount('media')`.

Para a tela administrativa de eventos isso faz sentido. Para alimentar options de filtros, nao.

### Detalhe de evento

`EventController.php@show` carrega de uma vez:

- organization;
- modules;
- channels;
- defaultWhatsAppInstance;
- whatsappGroupBindings;
- mediaSenderBlacklists;
- banners;
- client;
- teamMembers.user;
- wallSettings;
- playSettings;
- hubSettings;
- contentModerationSettings;
- faceSearchSettings;
- mediaIntelligenceSettings;
- mais contagens de midia por status.

Isso e util para uma tela rica, mas pesado demais como payload inicial obrigatorio. O admin precisa separar:

- overview do evento;
- configuracoes operacionais;
- IA/moderacao;
- wall/play/hub;
- membros e canais;
- indicadores de midia.

### 4. Indices de banco ainda aquem do padrao necessario

### `event_media`

A migration base de `event_media` praticamente nao adiciona indices alem dos implicitos de FKs. Depois, apenas alguns indices especificos foram adicionados para perceptual hash.

Para o uso atual do admin e dos fluxos criticos, faltam indices fortes para filtros comuns como:

- `event_id, created_at`;
- `event_id, moderation_status`;
- `event_id, publication_status`;
- `event_id, processing_status`;
- `event_id, sort_order`;
- em alguns cenarios, `event_id, source_type`.

Isso impacta:

- galerias internas;
- moderacao;
- dashboards com contagens;
- ordenacao de listas por evento;
- consultas derivadas de status de processamento/publicacao.

### `analytics_events`

Hoje existem indices em:

- `event_id, event_name`;
- `organization_id, event_name`;
- `occurred_at`.

Mas o padrao real de consulta do service costuma misturar:

- organizacao ou evento;
- intervalo de datas;
- tipo de evento.

Faz mais sentido adicionar tambem indices compostos orientados a tempo, como:

- `organization_id, occurred_at, event_name`;
- `event_id, occurred_at, event_name`.

### `play_game_sessions`

O schema atual indexa:

- `event_game_id, created_at`;
- `event_game_id, player_identifier`.

Mas as queries de analytics usam `started_at` com frequencia. Falta pelo menos:

- `event_game_id, started_at`.

Sem isso, parte do analytics de jogos tende a envelhecer mal com o crescimento do historico.

## Modelo recomendado de cache sem prejudicar realtime

O ponto central para o Evento Vivo nao e "colocar cache em tudo". E classificar os dados por criticidade e por tolerancia a staleness.

| Classe de dado | Exemplos | Cache frontend | Cache backend | Realtime / invalidacao | Service worker |
|---|---|---|---|---|---|
| Live operacional | wall runtime, player diagnostics, live snapshot, comandos, estados de execucao | `staleTime` muito baixo ou `0`, sem persistencia longa | sem cache agressivo, no maximo snapshot curtissimo para fallback | Reverb como via principal, `setQueryData` ou invalidacao pontual, polling fallback | nao cachear |
| Quase-live administrativo | dashboard KPI, contadores de moderacao, resumos operacionais | `15s` a `60s` conforme tela | `Cache::flexible` com janela curta, chave por org/evento | invalidar em mutacoes relevantes e aceitar pequeno atraso controlado | nao cachear API autenticada |
| Dados estaveis de apoio | options, listas auxiliares, dicionarios, configuracoes raramente alteradas | `5min` a `30min`, ou `Infinity` com invalidacao manual | `remember` ou `flexible`, preferencialmente com tags | invalidar em CRUD da entidade base | em geral, nao necessario |
| Sessao e autorizacao | `me`, permissoes, feature flags, branding da organizacao atual | `Infinity` com invalidacao manual, nao `static` se pode mudar em sessao longa | opcional e curto, sempre por usuario/org | invalidar em login/logout, troca de org, role ou permissao | nao cachear |
| Assets estaticos | JS/CSS hashados, imagens publicas, logos estaveis | browser cache padrao | CDN / headers longos | invalidacao por nome hashado | sim, quando publico e imutavel |

### Regras praticas para nossa stack

- nao usar service worker para cachear endpoints autenticados do admin nem superfices live;
- usar React Query como cache principal do admin em memoria, com invalidacao explicita;
- usar cache backend curto e etiquetado para dashboard, analytics e options;
- usar Reverb para notificar mudanca de estado, nao para despejar payload gigante em toda tela;
- quando uma mutacao for local e a UI ja souber o novo estado, aplicar `setQueryData` ou invalidate localmente e emitir realtime apenas para outras conexoes;
- separar "dados que precisam chegar agora" de "dados que podem chegar logo depois".

## Sugestoes adicionais de robustez

### 1. Introduzir taxonomia oficial de freshness por rota

Hoje o projeto mistura no mesmo tipo de carregamento:

- dados live;
- dados quase-live;
- dados de apoio;
- dados estaticos.

Vale formalizar uma matriz por modulo dizendo:

- frescor esperado;
- tipo de cache permitido;
- trigger de invalidacao;
- fallback quando o canal live cair.

### 2. Tratar Redis por papel operacional

Pelo que a stack indica, Redis tende a acumular varias responsabilidades:

- cache;
- filas;
- suporte a realtime/escalabilidade.

Recomendacao:

- no minimo separar conexoes e prefixos por papel;
- idealmente separar instancias quando a pressao de memoria e throughput crescer;
- nunca deixar politica de eviction de cache impactar fluxo de queue ou coordenacao live.

Isto e uma inferencia arquitetural a partir das recomendacoes oficiais de eviction do Redis e da topologia de escalabilidade do Reverb.

### 3. Tornar invalidacao de cache orientada a dominio

Em vez de depender apenas de TTL:

- evento criado/atualizado deve invalidar caches de options e listas relacionadas;
- mudanca de configuracao de wall deve invalidar snapshots e settings relevantes;
- mudanca de role/permissao deve invalidar `me`, menu e acessos;
- mudanca de moderacao/publicacao deve invalidar cards, filas e contadores afetados.

### 4. Garantir que realtime nao duplica trabalho local

Quando uma mutacao retorna sucesso:

- o cliente que fez a acao pode atualizar cache local imediatamente;
- o evento realtime deve servir principalmente para outras sessoes/conexoes;
- isso pede revisao de `toOthers`, identificacao de socket e padrao unico de invalidacao.

### 5. Usar observabilidade instalada antes de comprar complexidade nova

Como o projeto ja tem `Pulse`, `Horizon`, `Telescope` e `Reverb`, o proximo passo deveria ser medir:

- latencia p50/p95 por endpoint do admin;
- query count por request;
- cache hit/miss por classe de dado;
- throughput e runtime das filas `analytics`, `media-process`, `media-publish`;
- conexoes e mensagens do Reverb nas superficies live.

Sem isso, qualquer decisao como Octane, mais Redis, ou mais polling corre risco de ser prematura.

## Duvidas abertas para fechar a estrategia

- Quais telas do admin realmente precisam frescor sub-5s, e quais aceitam `15s`, `30s` ou `60s`?
- Dashboard e analytics podem operar oficialmente em stale-while-revalidate curto, ou existe algum bloco que precisa ser live?
- Moderacao precisa ser realtime integral ou bastam invalidacoes pontuais + fallback curto?
- Vamos manter um Redis compartilhado para tudo ou separar cache de queue/reverb?
- Vamos padronizar invalidacao por tags de `organization`, `event`, `module` e `user`, ou continuar apenas com TTL?
- O endpoint `GET /events/{id}` deve continuar agregando tantos relacionamentos ou vamos modularizar por contexto?
- Queremos que cada aba/section do admin tenha seu proprio endpoint de bootstrap leve?
- Existe meta de UX definida para o painel, por exemplo p95 de abertura de rota e p95 de filtro?

## O que precisamos fazer para melhorar

### Prioridade P0

Janela sugerida: base obrigatoria das proximas 1 ou 2 sprints.

### 1. Definir SLO, SLI e budget por tela e endpoint

Sem isso, nao existe criterio objetivo de pronto.

Escopo minimo:

- dashboard bootstrap;
- filtros das listas principais;
- detail bootstrap;
- options/autocomplete;
- wall/live surfaces.

### 2. Oficializar classes de freshness, query keys e invalidacao

Precisamos sair de uma soma de escolhas locais e entrar em um padrao oficial:

- query keys estaveis;
- freshness por classe de dado;
- invalidacao por `organization`, `event`, `module`, `user`;
- separacao entre stale aceitavel e live obrigatorio.

### 3. Tornar o contrato de leitura por superficie um padrao oficial

Toda rota quente do admin deve declarar:

- bootstrap;
- sections lazy;
- options/autocomplete.

Isso e uma regra de arquitetura, nao uma sugestao de melhoria.

### 4. Adicionar cancelamento real de requests no frontend

Implementar suporte padronizado a `AbortSignal` em `apps/web/src/lib/api.ts` e propagar o `signal` recebido pelo React Query para as query functions.

Impacto:

- reduz requests zumbis em digitacao;
- reduz carga na API;
- reduz parse de respostas que nao serao mais usadas;
- melhora filtros, autocomplete e busca global.

Aplicar primeiro em:

- `GlobalSearch`;
- `AnalyticsPage`;
- `EventsListPage`;
- `MediaPage`;
- `ModerationPage`;
- `ClientsPage`;
- `PartnersPage`.

### 5. Criar endpoints leves de options

Adicionar endpoints e resources especificos para selects/autocomplete, por modulo, por exemplo:

- `GET /api/v1/events/options`;
- `GET /api/v1/clients/options`;
- `GET /api/v1/organizations/options`;
- `GET /api/v1/whatsapp/instances/options`.

Cada endpoint deve devolver apenas:

- `id`;
- `label`;
- poucos metadados auxiliares, se necessario.

Esses endpoints devem nascer em seus modulos de dominio, com:

- controller fino;
- query dedicada;
- resource leve;
- cache curto quando fizer sentido.

### 6. Carregamento sob demanda por aba, card ou sheet

Trocar a estrategia de "abre tudo junto" por "carrega o critico primeiro".

Casos imediatos:

- `PartnerDetailSheet`: carregar somente detalhe e aba ativa;
- `EventDetailPage`: carregar overview primeiro e modulos secundarios quando a aba abrir;
- `EventEditorPage`: adiar listas auxiliares ate o campo/bloco ser realmente usado;
- `EventWallManagerPage`: separar bootstrap critico de dados secundarios.

### 7. Tornar a estrategia de cache compativel com a arquitetura

Isto inclui:

- explicitar se cache tags sao requisito;
- alinhar store e driver com essa decisao;
- usar cache curto por classe de dado;
- evitar cache no service worker para admin autenticado e superficies live.

### 8. Instrumentar observabilidade de verdade

Ativar e usar de forma operacional:

- Pulse;
- Horizon;
- Reverb + Pulse;
- `auto_explain` com rollout controlado;
- medicao de requests e payloads no frontend.

### 9. Separar payload critico e payload secundario do dashboard

Em vez de um unico payload para toda a dashboard, dividir em pelo menos:

- bootstrap acima da dobra:
  - KPIs;
  - alertas criticos;
  - talvez 1 resumo pequeno;
- blocos secundarios:
  - charts;
  - listas recentes;
- fila de moderacao;
- rankings.

Isso melhora a percepcao de velocidade mesmo antes de qualquer mega refactor.

### 10. Adicionar indices faltantes nas tabelas mais quentes

Entrar com migrations novas para:

- `event_media`;
- `analytics_events`;
- `play_game_sessions`.

Essa entrega provavelmente da ganho simultaneo em dashboard, moderacao, media, analytics e wall.

### Prioridade P1

Janela sugerida: desempenho estrutural depois da base obrigatoria.

### 1. Modularizar endpoints de detalhe pesado

O detalhe de evento precisa sair do modelo "tudo em um resource". O caminho mais limpo para a stack atual e:

- endpoint de overview;
- endpoints por dominio de configuracao;
- ou um mecanismo controlado de `include=`.

Exemplos:

- `/events/{id}` para overview;
- `/events/{id}/commercial-status`;
- `/events/{id}/wall-settings`;
- `/events/{id}/play-settings`;
- `/events/{id}/content-moderation`;
- `/events/{id}/media-intelligence`;
- `/events/{id}/team`.

Isso respeita a organizacao modular do backend e melhora payload, cache e ownership.

### 2. Transformar dashboard e analytics em read models graduais

Cache curto ajuda, mas nao substitui modelo de leitura.

Direcao esperada:

- snapshots;
- tabelas agregadas por periodo;
- jobs de consolidacao;
- eventualmente materialized views onde fizer sentido.

### 3. Reaproveitar cache de opcoes entre paginas

Depois de existirem endpoints leves de options, o frontend deve tratar isso como dados compartilhados do shell administrativo.

Exemplo:

- lista de eventos recentes para filtro;
- lista de clientes por organizacao;
- lista de instancias WhatsApp.

Nao faz sentido cada pagina redescobrir do zero as mesmas opcoes.

### 4. Prefetch de dados, nao so de rota

Hoje o projeto ja faz preload de chunks de rota. O proximo passo e combinar isso com `queryClient.prefetchQuery(...)` para fluxos provaveis, como:

- dashboard -> eventos;
- lista de eventos -> criar evento;
- lista de eventos -> detalhe do evento em hover/intencao;
- parceiros -> detalhe do parceiro ao abrir sheet.

### 5. Rever chunking do admin

O `vite.config.ts` ja tem `manualChunks`, mas ainda vale melhorar:

- garantir que charts so entrem onde sao realmente usados;
- evitar que `framer-motion` participe de paginas administrativas simples;
- revisar uso de componentes `radix` pesados no shell do admin;
- impedir que dependencias de `play/phaser` vazem para rotas administrativas que nao usam jogos.

### 6. Reduzir trabalho global do header e shell

O header ainda usa `mockNotifications` e mantem responsabilidades que nao sao essenciais para first paint.

Mesmo nao sendo o maior gargalo, vale:

- manter shell leve;
- mover partes nao criticas para carregamento tardio;
- evitar que a chrome do app dispute CPU com a pagina principal.

### 7. Consolidar padrao de mutacao + realtime

Padronizar nas superfices live e administrativas:

- mutacao atualiza cache local ou invalida query relevante;
- broadcast vai para outras conexoes quando fizer sentido;
- resposta HTTP nao precisa competir com um broadcast que volta para o mesmo cliente.

Aqui vale revisar uso de `toOthers`, identificacao de socket e `ShouldRescue` nos eventos suplementares.

### 8. Separar Redis por papel operacional

Planejar separacao ao menos logica, e preferencialmente fisica quando necessario, entre:

- cache;
- queue;
- suporte a escalabilidade/live.

Isso reduz risco de politica de eviction ou burst de workload de um papel degradar os outros.

### Prioridade P2

Janela sugerida: escala e modernizacao depois de limpar contratos de dados.

### 1. Contratos de performance por modulo

Cada modulo administrativo relevante deveria ter:

- endpoint de listagem;
- endpoint de detail;
- endpoint de options;
- endpoint de stats, se aplicavel.

Hoje parte do problema vem da reutilizacao de um endpoint "forte" para um caso "leve".

### 2. Observabilidade real de performance

Sem telemetria, o time corre risco de otimizar o lugar errado.

Precisamos instrumentar:

- frontend:
  - tempo ate first useful content por rota;
  - contagem de requests por navegacao;
  - tempo de filtros;
  - falhas e cancelamentos;
- backend:
  - latency por endpoint;
  - query count por request;
  - cache hit/miss;
  - tempo de serializacao de resources;
- banco:
  - queries mais lentas;
  - scans em tabelas grandes;
  - indice nao usado ou faltante.

### 3. Avaliar throughput de processo longo so depois da limpeza de payload/query

Se, depois das correcoes de payload, cache e banco, ainda houver gargalo de throughput PHP, ai sim pode fazer sentido avaliar app server de processo longo como proximo passo.

Mas essa decisao deve entrar apenas depois de:

- endpoints mais pesados estarem modularizados;
- indices principais estarem corretos;
- Pulse/Telescope apontarem gargalo real de aplicacao e nao de consulta;
- o time aceitar o custo operacional adicional de processo longo.

### 4. Benchmark de modernizacao de stack

Somente depois de limpar contratos de dados, vale benchmarkar:

- React 19;
- React Compiler;
- Vite 8 / Rolldown.

Esses upgrades podem trazer ganho relevante de dev/build e eventualmente runtime de componente, mas nao substituem a correcoes de payload, cache, invalidador e banco.

## Melhorias especificas por area do admin

### Dashboard

### Problema atual

- dashboard nasce como uma pagina grande, com charts e listas dependentes do mesmo payload;
- `DashboardPage.tsx` tambem puxa `recharts` e `framer-motion`, aumentando custo de render;
- qualquer atraso no payload principal atrasa a tela inteira.

### Melhor caminho

- responder rapido com bloco above-the-fold;
- carregar charts e listas logo depois do paint;
- considerar `lazy` para componentes de grafico;
- manter cache curto no backend;
- estudar pre-aggregacao se o volume crescer.

### Filtros de listas

### Problema atual

- filtros ja usam `useDeferredValue`, mas requests antigos nao sao abortados de forma consistente;
- listas usam endpoints ricos demais para popular filtros relacionados.

### Melhor caminho

- abort em todas as buscas concorrentes;
- endpoints de options;
- normalizar query keys e filtros;
- garantir que pagina e filtros nao refacam o mesmo request duas vezes ao voltar de uma navegacao.

### Subpaginas de detalhe

### Problema atual

- detalhes administrativos concentram muitas responsabilidades no primeiro carregamento;
- tabs, sheets e paineis secundario ainda puxam dados cedo demais.

### Melhor caminho

- adotar `progressive disclosure` tecnico;
- cada aba carrega seus dados ao ser aberta;
- cards abaixo da dobra podem ser lazy;
- payload overview deve ser pequeno e estavel.

### Busca global

### Problema atual

- UX de debounce esta correta;
- faltam cancelamento real e possivel endpoint ainda mais enxuto para autocomplete.

### Melhor caminho

- ligar `signal` do React Query;
- garantir limite pequeno de resultados;
- retornar payload minimo.

## Melhorias da stack atual

### Frontend

- manter React Query como eixo central de cache, inclusive para sessao `me`;
- propagar `AbortSignal` na camada de API;
- usar mais `prefetchQuery` para navegacoes provaveis;
- reduzir mount simultaneo de queries;
- tratar tabs, drawers e sheets como fronteiras de carregamento;
- lazy load de charts e blocos secundarios;
- revisar uso de `framer-motion` em paginas administrativas onde ele agrega pouco;
- manter `useDeferredValue`, mas sem confundir isso com otimizacao de request;
- separar claramente cache em memoria do admin de qualquer cache de service worker;
- evitar que o service worker do app confunda necessidades do painel com as rotas publicas.

### Backend Laravel

- criar endpoints mais especializados por modulo;
- evitar resources gigantes para qualquer caso de detalhe;
- reforcar indices de leitura quente;
- introduzir agregados prontos para dashboard/analytics quando o custo de consulta crescer;
- usar cache curto de forma mais granular, nao apenas em payloads enormes;
- considerar `Cache::flexible`, tags e locks onde houver recalculo concorrido;
- usar broadcasting como invalidacao e sincronizacao, nao como substituto bruto de API.

### Banco e infraestrutura

- adicionar indices compostos alinhados aos filtros reais;
- considerar partial indexes onde o subconjunto quente justificar;
- revisar EXPLAIN das queries mais frequentes de analytics, media e dashboard;
- habilitar monitoracao de slow queries;
- separar Redis por papel quando a pressao operacional pedir;
- monitorar hit ratio, evictions e backlog de filas;
- garantir compressao HTTP e headers de cache corretos para assets do admin.

## Ordem recomendada de execucao

### Fase 1

- definir SLO, SLI e budgets iniciais por rota e endpoint;
- oficializar bootstrap, sections lazy e options como contrato de leitura;
- abort de requests no frontend;
- endpoints leves de options;
- lazy queries por aba em `PartnerDetailSheet`, `EventDetailPage` e `EventEditorPage`;
- indices de `event_media`, `analytics_events` e `play_game_sessions`;
- matriz oficial de freshness por classe de dado;
- manter admin API fora do service worker;
- primeiras chaves de invalidacao por org e evento;
- decidir explicitamente se cache tags entram ou nao como prerequisito.

### Fase 2

- dashboard em camadas: bootstrap + secundarios;
- `EventWallManagerPage` com bootstrap mais enxuto;
- sessao `me` migrada para query canonica;
- prefetch de dados principais;
- padrao unico de mutacao + invalidacao + realtime;
- Pulse/Horizon/Reverb operando como dashboards de performance;
- `auto_explain` e rotina de revisao de planos em rollout controlado;
- primeiros budgets automatizados no CI.

### Fase 3

- modularizacao dos detalhes pesados no backend;
- agregados/materializacao para analytics e dashboard;
- revisao de chunking do admin;
- observabilidade dedicada de performance;
- separacao mais forte de Redis por papel, se confirmada necessidade;
- tuning de Reverb para alta concorrencia, se os numeros pedirem;
- benchmark de React 19, React Compiler e Vite 8.

## Checklist de implementacao

- [ ] adicionar suporte consistente a `signal` em `apps/web/src/lib/api.ts`
- [ ] adaptar query functions criticas para cancelamento
- [ ] definir SLO, SLI e budgets por rota quente
- [ ] definir contratos bootstrap / section / options para telas criticas
- [ ] criar endpoints de options por modulo
- [ ] trocar `per_page=100` em selects por endpoints dedicados
- [ ] carregar tabs e sheets sob demanda
- [ ] quebrar dashboard em bootstrap e blocos secundarios
- [ ] definir matriz de freshness por classe de dado
- [ ] definir regra oficial para cache x realtime por rota
- [ ] decidir se cache tags serao requisito oficial e alinhar driver/store a isso
- [ ] revisar payload de `EventController@show`
- [ ] adicionar migrations de indices faltantes
- [ ] validar `EXPLAIN ANALYZE` das queries mais quentes antes de multiplicar indices
- [ ] preparar rollout controlado de `auto_explain`
- [ ] padronizar invalidacao por `organization`, `event`, `module`, `user`
- [ ] revisar `toOthers` / socket identification nas superficies live
- [ ] ligar dashboards de Pulse, Horizon e Reverb para acompanhamento continuo
- [ ] criar gates de CI para payload, query count, requests por navegacao e chunk size
- [ ] medir p50/p95 de endpoints administrativos
- [ ] medir contagem de requests por rota do admin

## Referencias oficiais consultadas nesta revisao

### React e TanStack Query

- React versions: `https://react.dev/versions`
- React `useDeferredValue`: `https://react.dev/reference/react/useDeferredValue`
- React 19 release: `https://react.dev/blog/2024/12/05/react-19`
- React Compiler v1.0: `https://react.dev/blog/2025/10/07/react-compiler-1`
- TanStack Query `Query Cancellation`: `https://tanstack.com/query/latest/docs/framework/react/guides/query-cancellation`
- TanStack Query `Important Defaults`: `https://tanstack.com/query/latest/docs/framework/react/guides/important-defaults`
- TanStack Query `Invalidations from Mutations`: `https://tanstack.com/query/latest/docs/framework/react/guides/invalidations-from-mutations`
- TanStack Query `Prefetching`: `https://tanstack.com/query/latest/docs/framework/react/guides/prefetching`

### Vite

- Vite `Performance`: `https://vite.dev/guide/performance.html`
- Vite `Build Guide`: `https://vite.dev/guide/build`
- Vite 8 Beta announcement: `https://vite.dev/blog/announcing-vite8-beta`

### Laravel

- Laravel Cache: `https://laravel.com/docs/13.x/cache`
- Laravel Broadcasting: `https://laravel.com/docs/13.x/broadcasting`
- Laravel Reverb: `https://laravel.com/docs/11.x/reverb`
- Laravel Pulse: `https://laravel.com/docs/13.x/pulse`
- Laravel Horizon: `https://laravel.com/docs/13.x/horizon`

### PostgreSQL e Redis

- PostgreSQL multicolumn indexes: `https://www.postgresql.org/docs/current/indexes-multicolumn.html`
- PostgreSQL partial indexes: `https://www.postgresql.org/docs/14/indexes-partial.html`
- PostgreSQL `EXPLAIN ANALYZE`: `https://www.postgresql.org/docs/current/using-explain.html`
- PostgreSQL `ANALYZE`: `https://www.postgresql.org/docs/current/sql-analyze.html`
- PostgreSQL `auto_explain`: `https://www.postgresql.org/docs/current/auto-explain.html`
- PostgreSQL materialized views: `https://www.postgresql.org/docs/current/rules-materializedviews.html`
- Redis eviction policies: `https://redis.io/docs/latest/develop/reference/eviction/`
- Redis Pub/Sub: `https://redis.io/docs/latest/develop/pubsub/`
- Redis keyspace notifications: `https://redis.io/docs/latest/develop/pubsub/keyspace-notifications/`

## Conclusao

O painel administrativo ja tem uma base moderna e suficientemente boa para evoluir sem troca de stack. O principal agora nao e reinventar frontend ou backend. E refinar a granularidade.

Hoje o ganho mais forte viria de quatro ajustes coordenados:

- menos requests simultaneos e mais cancelamento;
- menos payload pesado em casos simples;
- mais carregamento progressivo nas telas complexas;
- mais eficiencia de banco nas tabelas e agregacoes quentes.

Se esse backlog for atacado nessa ordem, a tendencia e melhorar ao mesmo tempo:

- velocidade percebida da dashboard;
- tempo de resposta de filtros;
- abertura de subpaginas;
- custo de CPU no browser;
- pressao na API e no banco.
