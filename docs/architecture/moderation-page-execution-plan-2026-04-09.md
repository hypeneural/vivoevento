# Moderation Page Execution Plan - 2026-04-09

## Objetivo

Transformar o diagnostico de `moderation-page-current-state-analysis-2026-04-09.md` em um plano de execucao implementavel para a rota `/moderation`, sem perder contexto tecnico, sem quebrar o realtime e com TDD suficiente para travar comportamento antes de refatorar.

## Status da primeira entrega

Itens concluidos nesta rodada:

- [x] alinhar o backend do feed ao mesmo conceito de `effective state` usado pelo frontend;
- [x] formalizar a projecao cursor-safe do feed com `coalesce(sort_order, 0)`, prioridade operacional e ordenacao deterministica;
- [x] separar `stats` do payload da primeira pagina com endpoint dedicado em `/media/feed/stats`;
- [x] propagar `AbortSignal` em `moderationService.list`, `moderationService.show` e query dedicada de `stats`;
- [x] proteger o gatilho de `fetchNextPage` para nao concorrer com outro fetch em andamento;
- [x] enviar `X-Socket-ID` no client HTTP quando houver socket ativo;
- [x] trocar a trilha de broadcast da moderacao para `broadcast(...)->toOthers()`;
- [x] criar/atualizar testes de contrato no backend e no frontend para esse corte.

## Status da segunda entrega

Itens concluidos nesta rodada:

- [x] expor `thumbnail_source` e `preview_source` no payload de `EventMediaResource` e `EventMediaDetailResource`;
- [x] expor `updated_at` no payload operacional para suportar frescor no merge do frontend;
- [x] criar a surface compartilhada de thumbnail com `loading/error/fallback` para card e painel;
- [x] corrigir o painel lateral para usar `preview_url` real em video e `thumbnail_url` apenas como `poster`;
- [x] adicionar dedupe por `updated_at` no merge do feed, da fila `incomingItems` e do patch otimista;
- [x] ampliar a cobertura com `ModerationMediaSurface.test.tsx`, `feed-utils.test.ts`, `MediaAssetUrlServiceTest.php`, `ModerationMediaTest.php` e `EventMediaListTest.php`.

## Status da terceira entrega

Itens concluidos nesta rodada:

- [x] criar `moderation_thumb` e `moderation_preview` no pipeline de variants da midia;
- [x] expor `moderation_thumbnail_url`, `moderation_thumbnail_source`, `moderation_preview_url` e `moderation_preview_source` no payload operacional;
- [x] impedir fallback para original no feed da moderacao, mantendo fallback visual controlado no frontend;
- [x] ligar `ModerationMediaSurface` ao perfil dedicado da moderacao e ao preview ampliado;
- [x] migrar o shell para data router com `createBrowserRouter`, `RouterProvider` e `ScrollRestoration`;
- [x] adicionar chave estavel de scroll restoration para `/moderation`;
- [x] corrigir o bloqueio de indice duplicado na migration `2026_04_09_192000_add_recurring_fields_to_payments_table.php` para destravar a suite.

## Status da quarta entrega

Itens concluidos nesta rodada:

- [x] criar migration dedicada com indices compostos do feed de moderacao para `event_media`;
- [x] adicionar indices GIN com `pg_trgm` para a busca multi-campo de `event_media` e `inbound_messages` no PostgreSQL;
- [x] remover o `orWhereHas('inboundMessage')` do hot path da busca do `/media/feed`, trocando por `left join` direto;
- [x] adicionar `prefetch` do proximo detalhe carregado a partir da midia focada;
- [x] ampliar a cobertura com busca por `event title` e identidade do remetente no backend;
- [x] ampliar a cobertura com teste de arquitetura para o prefetch do proximo detalhe no frontend.

## Status da quinta entrega

Itens concluidos nesta rodada:

- [x] aplicar a migration de indices da moderacao no PostgreSQL local real;
- [x] validar o caminho quente do feed com `EXPLAIN ANALYZE` a partir do proprio `ListModerationMediaQuery`;
- [x] validar a busca por `event title` e `sender name` com `EXPLAIN ANALYZE` no PostgreSQL local;
- [x] corrigir o nome explicito do indice de `publication_status + processing_status` para respeitar o limite real de 63 caracteres do PostgreSQL;
- [x] validar, em sonda adicional, que a forma atual da busca ainda nao fecha naturalmente no GIN de `pg_trgm`;
- [x] decidir, com base na primeira medicao real local, que `partial index` adicional ficava fora desta rodada e que `search document` so deveria subir se a busca estourasse o budget em volume maior.

## Status da sexta entrega

Itens concluidos nesta rodada:

- [x] criar `ModerationFeedExplainAnalyzeService` para repetir o benchmark da moderacao em PostgreSQL real com `EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)`;
- [x] criar o comando `php artisan media:moderation-feed-explain` com budgets explicitos de `feed = 700ms` e `search = 500ms`;
- [x] adicionar `--fail-on-budget` e `--output` para transformar a validacao de homolog em runbook repetivel;
- [x] rerodar o benchmark local com a ferramenta nova e confirmar que a busca continua dentro do budget operacional;
- [x] ampliar a cobertura com testes dedicados do service e do command.

## Status da setima entrega

Itens concluidos nesta rodada:

- [x] confirmar que nao ha credencial/configuracao de homolog disponivel no workspace atual;
- [x] confirmar que o `.env` local desta maquina aponta para `APP_ENV=local` com PostgreSQL em `127.0.0.1:5433`, nao para uma base homologada separada;
- [x] adicionar modo transacional `--synthetic-media` ao comando para simular volume maior em PostgreSQL real sem persistir dados de benchmark;
- [x] rodar sonda sintetica com `5.000` midias e confirmar que `search_sender_name_hot` passava de `500ms` na forma anterior;
- [x] promover `search document` dedicado em `event_media.moderation_search_document`, com backfill e indice GIN `event_media_moderation_search_document_trgm_idx`;
- [x] remover a busca por `OR` espalhado entre `event_media`, `events` e `inbound_messages` do hot path do `/media/feed`;
- [x] adicionar `--disable-jit` ao benchmark, porque a sonda mostrou que JIT do PostgreSQL pode dominar a latencia de busca OLTP;
- [x] rerodar a sonda de `5.000` midias com `search document` + `--disable-jit` e manter todos os cenarios dentro do budget.
- [x] repetir a sonda local com `20.000` midias sinteticas e reabrir a decisao quando `search_event_title_hot` saiu de `500ms`;
- [x] adicionar fast path para busca por titulo exato de evento, aplicando `event_id` antes do documento textual amplo;
- [x] rerodar a sonda de `20.000` midias com o fast path e manter `feed` e `search` dentro dos budgets (`search_event_title_hot ~= 254ms`, `search_sender_name_hot ~= 30ms`).

## Status da oitava entrega

Itens concluidos nesta rodada:

- [x] remover `ModerationPagination.tsx` e travar sua ausencia em teste de arquitetura;
- [x] propagar `reason` no frontend para `reject` e `bulkReject`, mantendo o contrato pronto tambem para `approve` e `bulkApprove`;
- [x] extrair helper `resolveNextPendingModerationItem()` para auto-advance deterministicamente alinhado a ordem visivel da fila;
- [x] adicionar toggle de `auto-advance` no painel lateral;
- [x] adicionar `Aprovar e proxima` no painel e `Shift + A` no teclado;
- [x] adicionar dialog de reprovacao com motivos rapidos, motivo customizado e suporte a lote;
- [x] ampliar a cobertura com testes de service, helper, painel e arquitetura do modulo.

## Status da nona entrega

Itens concluidos nesta rodada:

- [x] criar endpoint dedicado `GET /media/{eventMedia}/duplicates` para revisar o cluster de duplicata no proprio fluxo da moderacao;
- [x] criar `ListDuplicateClusterMediaQuery` com escopo por `event_id + duplicate_group_key`;
- [x] criar endpoint dedicado `POST /media/{eventMedia}/undo-decision` com `UndoEventMediaDecisionAction`;
- [x] devolver decisoes manuais desfeitas para `pending + draft`, limpando `decision_source` e metadados de override;
- [x] adicionar a secao de cluster de duplicata no `ModerationReviewPanel` com abertura de item do grupo e acao `Rejeitar demais como duplicada`;
- [x] adicionar toast `Desfazer` para acoes unitarias de `approve`, `reject`, `favorite` e `pin`;
- [x] ampliar a cobertura com testes de feature, service, helper, painel e arquitetura para cluster + undo.

Itens ainda pendentes deste plano:

- [ ] repetir `EXPLAIN ANALYZE` em ambiente homologado real quando houver credencial/configuracao disponivel;
- [ ] validar a politica de JIT do PostgreSQL em homolog/producao para queries OLTP do painel.

Comando recomendado para homolog:

- `cd apps/api && php artisan media:moderation-feed-explain --disable-jit --fail-on-budget --output=storage/app/reports/moderation-feed-explain-homolog.json`

Comando recomendado para sonda local de volume:

- `cd apps/api && php artisan media:moderation-feed-explain --synthetic-media=20000 --disable-jit --fail-on-budget --output=storage/app/reports/moderation-feed-explain-synthetic-20000.json`

Este plano responde 9 perguntas:

1. o que foi validado nesta rodada por codigo, testes e documentacao oficial;
2. quais duvidas ja estao fechadas e quais continuam em aberto;
3. qual e o problema funcional real do feed hoje;
4. o que entra no `P0` para corrigir contrato, estabilidade visual e fluxo operacional;
5. o que entra no `P1` para robustez de cache, scroll e produtividade;
6. o que deve ficar claramente para `P2`;
7. quais arquivos e modulos devem ser alterados em cada fase;
8. quais testes automatizados precisam existir antes, durante e depois da implementacao;
9. qual e a definicao de pronto antes de considerar a pagina estabilizada.

## Referencias primarias

- `docs/architecture/moderation-page-current-state-analysis-2026-04-09.md`
- `apps/api/app/Modules/MediaProcessing/Queries/ListModerationMediaQuery.php`
- `apps/api/app/Modules/MediaProcessing/Queries/ListDuplicateClusterMediaQuery.php`
- `apps/api/app/Modules/MediaProcessing/Http/Resources/EventMediaResource.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaEffectiveStateResolver.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaAssetUrlService.php`
- `apps/api/app/Modules/MediaProcessing/Services/ModerationFeedExplainAnalyzeService.php`
- `apps/api/app/Modules/MediaProcessing/Services/ModerationSearchDocumentBuilder.php`
- `apps/api/app/Modules/MediaProcessing/Console/RunModerationFeedExplainCommand.php`
- `apps/api/app/Modules/MediaProcessing/Actions/UndoEventMediaDecisionAction.php`
- `apps/web/src/modules/moderation/ModerationPage.tsx`
- `apps/web/src/modules/moderation/feed-utils.ts`
- `apps/web/src/modules/moderation/components/ModerationVirtualGrid.tsx`
- `apps/web/src/modules/moderation/components/ModerationMediaCard.tsx`
- `apps/web/src/modules/moderation/components/ModerationMediaSurface.tsx`
- `apps/web/src/modules/moderation/services/moderation.service.ts`
- `apps/web/src/lib/api.ts`
- `apps/web/src/App.tsx`
- `apps/web/src/app/routing/scroll-restoration.ts`
- `apps/web/src/modules/moderation/moderation-architecture.test.ts`
- `apps/api/tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php`
- `apps/api/tests/Feature/MediaProcessing/RunModerationFeedExplainCommandTest.php`
- `apps/api/tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php`
- `apps/api/tests/Unit/Modules/MediaProcessing/ModerationFeedExplainAnalyzeServiceTest.php`
- `apps/api/tests/Unit/Modules/MediaProcessing/ModerationSearchDocumentBuilderTest.php`
- `apps/api/tests/Unit/Modules/MediaProcessing/MediaAssetUrlServiceTest.php`
- `apps/api/database/migrations/2026_04_09_230000_add_moderation_feed_indexes.php`
- `apps/api/database/migrations/2026_04_09_232000_add_moderation_search_document_to_event_media.php`
- `apps/web/src/lib/api.realtime.test.ts`
- `apps/web/src/app/routing/router-architecture.test.ts`
- `apps/web/src/app/routing/scroll-restoration.test.ts`
- `apps/web/src/modules/moderation/services/moderation.service.test.ts`
- `apps/web/src/modules/moderation/feed-utils.test.ts`
- `apps/web/src/modules/moderation/components/ModerationReviewPanel.test.tsx`
- `apps/web/src/modules/moderation/components/ModerationMediaSurface.test.tsx`

## Referencias oficiais usadas nesta rodada

- TanStack Query docs sobre `useMutation`, `onMutate` e rollback de optimistic updates:
  - https://tanstack.com/query/latest/docs/framework/react/guides/optimistic-updates

## Validacao executada nesta rodada

### Testes criados ou ampliados

- `apps/api/tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php`
- `apps/api/tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php`
- `apps/api/tests/Feature/MediaProcessing/RunModerationFeedExplainCommandTest.php`
- `apps/api/tests/Unit/Modules/MediaProcessing/ModerationFeedExplainAnalyzeServiceTest.php`
- `apps/api/tests/Unit/Modules/MediaProcessing/ModerationSearchDocumentBuilderTest.php`
- `apps/api/tests/Unit/Modules/MediaProcessing/MediaAssetUrlServiceTest.php`
- `apps/web/src/lib/api.realtime.test.ts`
- `apps/web/src/app/routing/router-architecture.test.ts`
- `apps/web/src/modules/moderation/services/moderation.service.test.ts`
- `apps/web/src/modules/moderation/feed-utils.test.ts`
- `apps/web/src/modules/moderation/moderation-architecture.test.ts`

### Comandos executados

Backend:

- `cd apps/api && php artisan test tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php tests/Unit/Modules/MediaProcessing/MediaAssetUrlServiceTest.php`
  - `5 testes`
  - `25 assertions`
  - `PASS`
- `cd apps/api && php artisan test tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php tests/Unit/Modules/MediaProcessing/MediaAssetUrlServiceTest.php`
  - `7 testes`
  - `33 assertions`
  - `PASS`

Frontend:

- `cd apps/web && npx vitest run src/modules/moderation/feed-utils.test.ts src/modules/moderation/components/ModerationReviewPanel.test.tsx`
  - `2 arquivos`
  - `6 testes`
  - `PASS`
- `cd apps/web && npx vitest run src/lib/api.realtime.test.ts src/app/routing/router-architecture.test.ts src/modules/moderation/feed-utils.test.ts src/modules/moderation/components/ModerationReviewPanel.test.tsx`
  - `4 arquivos`
  - `9 testes`
  - `PASS`
- `cd apps/web && npm.cmd run test -- src/lib/api.realtime.test.ts src/modules/moderation/services/moderation.service.test.ts src/modules/moderation/feed-utils.test.ts src/modules/moderation/components/ModerationReviewPanel.test.tsx src/modules/moderation/components/ModerationMediaSurface.test.tsx src/app/routing/router-architecture.test.ts`
  - `6 arquivos`
  - `16 testes`
  - `PASS`
- `cd apps/web && npm.cmd run type-check`
  - `PASS`
- `cd apps/web && npm.cmd run test -- src/modules/moderation/services/moderation.service.test.ts src/modules/moderation/feed-utils.test.ts src/modules/moderation/components/ModerationReviewPanel.test.tsx src/modules/moderation/moderation-architecture.test.ts`
  - `4 arquivos`
  - `19 testes`
  - `PASS`
- `cd apps/web && npm.cmd run type-check`
  - `PASS`
- `cd apps/api && php artisan test tests/Feature/MediaProcessing/ModerationMediaTest.php tests/Feature/MediaProcessing/EventMediaListTest.php tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php`
  - `21 testes`
  - `336 assertions`
  - `PASS`

Frontend:

- `cd apps/web && npm.cmd run test -- src/modules/moderation/services/moderation.service.test.ts src/modules/moderation/feed-utils.test.ts src/modules/moderation/components/ModerationReviewPanel.test.tsx src/modules/moderation/moderation-architecture.test.ts`
  - `4 arquivos`
  - `24 testes`
  - `PASS`
- `cd apps/web && npm.cmd run type-check`
  - `PASS`

Backend:

- `cd apps/api && php artisan test tests/Feature/MediaProcessing/ModerationMediaTest.php tests/Feature/MediaProcessing/EventMediaListTest.php tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php`
  - `24 testes`
  - `365 assertions`
  - `PASS`

Backend:

- `cd apps/api && php artisan test tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php tests/Feature/MediaProcessing/ModerationMediaTest.php tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php tests/Unit/Modules/MediaProcessing/MediaAssetUrlServiceTest.php tests/Feature/MediaProcessing/EventMediaListTest.php`
  - `23 testes`
  - `335 assertions`
  - `PASS`
- `cd apps/api && php artisan test tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php tests/Feature/MediaProcessing/ModerationMediaTest.php tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php tests/Unit/Modules/MediaProcessing/MediaAssetUrlServiceTest.php tests/Feature/MediaProcessing/EventMediaListTest.php tests/Feature/MediaProcessing/MediaPipelineJobsTest.php`
  - `35 testes`
  - `426 assertions`
  - `PASS`

Frontend:

- `cd apps/web && npm.cmd run test -- src/lib/api.realtime.test.ts src/modules/moderation/services/moderation.service.test.ts src/modules/moderation/feed-utils.test.ts src/modules/moderation/components/ModerationReviewPanel.test.tsx src/modules/moderation/components/ModerationMediaSurface.test.tsx src/app/routing/router-architecture.test.ts src/app/routing/scroll-restoration.test.ts`
  - `7 arquivos`
  - `19 testes`
  - `PASS`
- `cd apps/web && npm.cmd run test -- src/lib/api.realtime.test.ts src/modules/moderation/services/moderation.service.test.ts src/modules/moderation/feed-utils.test.ts src/modules/moderation/components/ModerationReviewPanel.test.tsx src/modules/moderation/components/ModerationMediaSurface.test.tsx src/modules/moderation/moderation-architecture.test.ts src/app/routing/router-architecture.test.ts src/app/routing/scroll-restoration.test.ts`
  - `8 arquivos`
  - `21 testes`
  - `PASS`
- `cd apps/web && npm.cmd run type-check`
  - `PASS`

Backend:

- `cd apps/api && php artisan test tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php tests/Feature/MediaProcessing/ModerationMediaTest.php tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php tests/Unit/Modules/MediaProcessing/MediaAssetUrlServiceTest.php tests/Feature/MediaProcessing/EventMediaListTest.php tests/Feature/MediaProcessing/MediaPipelineJobsTest.php`
  - `37 testes`
  - `416 assertions`
  - `2 falhas laterais em MediaPipelineJobsTest.php`
- `cd apps/api && php artisan media:moderation-feed-explain`
  - `PASS`
  - busca continua dentro do budget local de `500ms`
  - `search_document.promote = no`
- `cd apps/api && php artisan test tests/Feature/MediaProcessing/RunModerationFeedExplainCommandTest.php tests/Unit/Modules/MediaProcessing/ModerationFeedExplainAnalyzeServiceTest.php tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php tests/Feature/MediaProcessing/ModerationMediaTest.php tests/Unit/Modules/MediaProcessing/MediaAssetUrlServiceTest.php tests/Feature/MediaProcessing/EventMediaListTest.php`
  - `33 testes`
  - `388 assertions`
  - `PASS`
- `cd apps/api && php artisan media:moderation-feed-explain --synthetic-media=5000 --disable-jit --fail-on-budget --output=storage/app/reports/moderation-feed-explain-synthetic-5000-search-document-disable-jit.json`
  - `PASS`
  - `search_sender_name_hot ~= 56ms`
  - `search_document.promote = no` apos a promocao do documento dedicado
- `cd apps/api && php artisan media:moderation-feed-explain --synthetic-media=20000 --disable-jit --fail-on-budget --output=storage/app/reports/moderation-feed-explain-synthetic-20000-search-document-disable-jit.json`
  - `FAIL` antes do fast path de titulo exato
  - `search_event_title_hot` saiu do budget de `500ms`
  - decisao correta apos ajuste do comando: `search_document.present = yes`, `search_document.promote = no`, `search_document.requires_follow_up = yes`
- `cd apps/api && php artisan media:moderation-feed-explain --synthetic-media=20000 --disable-jit --fail-on-budget --output=storage/app/reports/moderation-feed-explain-synthetic-20000-search-document-event-title-fast-path.json`
  - `PASS`
  - `search_event_title_hot ~= 254ms`
  - `search_sender_name_hot ~= 30ms`
  - `search_document.requires_follow_up = no`
- `cd apps/api && php artisan media:moderation-feed-explain --disable-jit --fail-on-budget --output=storage/app/reports/moderation-feed-explain-local-event-title-fast-path.json`
  - `PASS`
  - dataset local real segue dentro do budget depois do fast path
- `cd apps/api && php artisan media:moderation-feed-explain --disable-jit --fail-on-budget --output=storage/app/reports/moderation-feed-explain-current-env.json`
  - `PASS`
  - ambiente efetivo desta maquina continua `APP_ENV=local`, `DB_HOST=127.0.0.1`, `DB_PORT=5433`, `DB_DATABASE=eventovivo`
- `cd apps/api && php artisan test tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php tests/Unit/Modules/MediaProcessing/ModerationFeedExplainAnalyzeServiceTest.php tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php`
  - `11 testes`
  - `68 assertions`
  - `PASS`
- `cd apps/api && php artisan test tests/Feature/MediaProcessing/RunModerationFeedExplainCommandTest.php tests/Unit/Modules/MediaProcessing/ModerationFeedExplainAnalyzeServiceTest.php tests/Unit/Modules/MediaProcessing/ModerationSearchDocumentBuilderTest.php tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php tests/Feature/MediaProcessing/ModerationMediaTest.php tests/Unit/Modules/MediaProcessing/MediaAssetUrlServiceTest.php tests/Feature/MediaProcessing/EventMediaListTest.php`
  - `35 testes`
  - `398 assertions`
  - `PASS`

### Leituras oficiais validadas

React:

- `https://react.dev/reference/react/useDeferredValue`
  - `useDeferredValue` melhora responsividade de render.
  - `useDeferredValue` nao reduz requests por si so.
  - para render lento, a propria doc reforca que o ganho depende de isolar a parte pesada e permitir que ela pule renders desnecessarios.

React Router:

- `https://reactrouter.com/v6/components/scroll-restoration`
  - `ScrollRestoration` e a peca oficial para restaurar scroll.
  - ela faz mais sentido quando o app esta em data router.
- `https://reactrouter.com/v6/routers/picking-a-router`
  - data routers sao a base para recursos de dados e utilidades como `ScrollRestoration`.

TanStack Query:

- `https://tanstack.com/query/latest/docs/framework/react/guides/query-cancellation`
  - queries nao sao canceladas de verdade a menos que o `AbortSignal` seja consumido pela chamada HTTP.
- `https://tanstack.com/query/v5/docs/framework/react/guides/infinite-queries`
  - uma `InfiniteQuery` compartilha uma unica entrada de cache entre paginas.
  - `fetchNextPage` concorrendo com outro fetch pode sobrescrever refreshes.
  - o proprio guia recomenda evitar chamar `fetchNextPage` enquanto `isFetching` estiver verdadeiro.
  - `maxPages` existe para limitar memoria e o custo de refetch sequencial.
- `https://tanstack.com/query/latest/docs/framework/react/guides/scroll-restoration`
  - o cache sincronico ajuda scroll restoration quando o layout fica estavel e os dados continuam disponiveis.
- `https://tanstack.com/query/latest/docs/framework/react/guides/request-waterfalls`
  - waterfalls devem ser achatados por contrato de leitura melhor ou por `prefetch`.
- `https://tanstack.com/query/latest/docs/framework/react/guides/prefetching`
  - `prefetchQuery` respeita `staleTime` e serve para aquecer detalhes antes da navegacao real.

MDN:

- `https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API`
  - `IntersectionObserver` foi feito para observacao assincrona de visibilidade e infinite scroll.
  - `rootMargin` positivo antecipa a interseccao e pode iniciar carregamento antes do item ficar visivel.
  - callbacks continuam rodando na main thread e devem ser leves.
- `https://developer.mozilla.org/en-US/docs/Web/API/HTMLImageElement/loading`
  - `loading="lazy"` e apenas uma dica ao navegador para adiar download fora do viewport.
  - isso ajuda custo inicial, mas nao resolve estado visual de loading ou erro do componente.

Laravel:

- `https://laravel.com/docs/12.x/eloquent-relationships#eager-loading-specific-columns`
  - ao limitar colunas de eager loading, e obrigatorio manter `id` e foreign keys relevantes.
- `https://laravel.com/docs/12.x/pagination#cursor-pagination`
  - cursor pagination e a estrategia correta para listas com carga incremental.
  - a ordenacao precisa ser deterministica e refletida fielmente no cursor.
  - a documentacao tambem reforca restricoes importantes para ordenacao unica, sem `null` problematico e com expressoes tratadas de forma segura.
- `https://laravel.com/docs/12.x/broadcasting#only-to-others`
  - `toOthers()` existe para evitar eco de broadcast para o mesmo cliente.
  - isso depende do `X-Socket-ID` ir junto na request.

PostgreSQL:

- `https://www.postgresql.org/docs/current/indexes-multicolumn.html`
  - indices multicoluna sao mais efetivos quando o filtro quente usa bem as colunas mais a esquerda.
- `https://www.postgresql.org/docs/current/indexes-partial.html`
  - partial index so compensa quando o predicado casa de forma reconhecivel com o `WHERE` real.
  - clausulas parametrizadas nao costumam casar bem com a implicacao de predicado.
- `https://www.postgresql.org/docs/current/pgtrgm.html`
  - `pg_trgm` suporta aceleracao de `LIKE`/`ILIKE` e comparacoes por similaridade com GIN/GiST.
- `https://www.postgresql.org/docs/current/using-explain.html`
  - `EXPLAIN ANALYZE` executa a query de verdade e e a base para validar plano e custo real.
- `https://www.postgresql.org/docs/current/jit-decision.html`
  - JIT tende a beneficiar queries longas/analiticas, mas pode custar mais do que economiza em queries curtas.
- `https://www.postgresql.org/docs/current/jit-configuration.html`
  - `jit`, `jit_above_cost`, `jit_inline_above_cost` e `jit_optimize_above_cost` controlam quando o PostgreSQL compila a query.
- `https://www.postgresql.org/docs/current/auto-explain.html`
  - `auto_explain` permite registrar planos de queries lentas em producao com rollout controlado.

## O que ficou validado

### 1. O problema principal nao e so visual; o contrato do feed esta errado

O teste de caracterizacao confirmou um bug funcional real:

- `MediaEffectiveStateResolver` sabe classificar uma midia como `pending_moderation` por contexto de IA;
- o feed de moderacao nao carrega contexto suficiente do evento para sustentar isso;
- o mesmo item pode aparecer como `approved` no feed e `pending_moderation` quando avaliado com o resolver completo;
- `?status=pending_moderation` hoje nao traz toda a fila operacional que o moderador espera ver.

Conclusao:

- isso e `P0 funcional`;
- nao e ajuste cosmetico de badge;
- nao e problema exclusivo do frontend.

### 2. O frontend local ja trabalha com estado efetivo, nao com estado bruto

`feed-utils.ts` confirmou:

- ordenacao local usa `status === 'pending_moderation'`;
- filtros locais tambem usam `status`;
- logo, a semantica visivel do frontend ja e `effective state`.

Conclusao:

- o backend precisa alinhar o contrato da rota `/media/feed` com a mesma semantica;
- manter badge, ordenacao e filtro com fontes diferentes continua gerando pulo de card e incoerencia operacional.

### 3. O client HTTP ja suporta `AbortSignal`; o problema e a propagacao

`apps/web/src/lib/api.ts` ja aceita `RequestInit` e faz `...options` no `fetch`.

Hoje o que falta e:

- passar `signal` do `queryFn` para `moderationService.list`;
- passar `signal` do `queryFn` para `moderationService.show`;
- padronizar isso nas queries da rota.

Conclusao:

- nao precisamos reescrever a camada HTTP;
- precisamos propagar corretamente o `signal`.

### 4. O mesmo transporte HTTP ainda nao injeta `X-Socket-ID`

Os testes de caracterizacao confirmaram:

- `api.ts` encaminha `AbortSignal`;
- `api.ts` ainda nao injeta `X-Socket-ID`.

Conclusao:

- a base para cancellation ja existe;
- a base para `toOthers()` ainda nao esta pronta no frontend.

### 5. O infinite scroll atual pode gerar churn desnecessario

`ModerationPage.tsx` hoje:

- usa `IntersectionObserver` com `rootMargin: '1200px 0px'`;
- chama `feedQuery.fetchNextPage()` quando o sentinela entra;
- nao protege explicitamente contra `isFetching` dentro do callback;
- trabalha sobre uma `InfiniteQuery` que sofre cirurgia local frequente via `setQueryData`.

Conclusao:

- o desenho atual aumenta a chance de refetch e merge agressivos enquanto a lista esta mudando;
- isso esta alinhado com o guia oficial da TanStack sobre risco de sobrescrever refreshes em `InfiniteQuery`.

### 6. `stats` continuam acopladas a primeira pagina cursorizada

O controller e o teste de caracterizacao confirmaram:

- `meta.stats` so existe quando `cursor` esta vazio;
- paginas seguintes devolvem `stats = null`;
- a tela continua lendo `pages[0].meta.stats` como fonte do topo.

Conclusao:

- isso e fragil para uma `InfiniteQuery` altamente mutavel;
- `stats` devem virar query separada e barata.

### 7. O app agora usa data router com `ScrollRestoration`

O teste de caracterizacao atualizado confirmou:

- o shell passou para `createBrowserRouter`;
- o app agora usa `RouterProvider`;
- `ScrollRestoration` foi ligado no shell;
- `/moderation` ganhou `getKey` estavel por pathname + filtros criticos conhecidos da rota.

Conclusao:

- scroll restoration deixou de ser uma aposta implicita no cache da query;
- a fundacao oficial do router ja esta pronta para os proximos refinamentos.

### 8. O broadcaster da moderacao ainda nao usa `toOthers()`

O teste de caracterizacao confirmou:

- os broadcasts da moderacao sao emitidos por `event(new ...)`;
- a trilha ainda nao usa `broadcast(...)->toOthers()`.

Conclusao:

- hoje ainda existe espaco para eco entre mutation e broadcast;
- o frontend esta compensando mais do que deveria.

### 9. O fallback para asset original e real e afeta a percepcao de loading

O teste ampliado de `MediaAssetUrlServiceTest` confirmou:

- sem variantes otimizadas, imagem pode cair no original para `thumbnail_url` e `preview_url`.

No frontend:

- `ModerationMediaCard` usa `loading="lazy"` e `decoding="async"`;
- mas nao tem estado proprio de `loading`, `loaded` ou `error`;
- nao tem `onError`;
- nao tem placeholder persistente nem fade-in controlado.

Conclusao:

- existe um custo visual estrutural quando a grade remonta e o card depende de imagem grande ou lenta;
- lazy loading sozinho nao resolve esse problema.

### 10. O scroll restoration tem potencial, mas o layout ainda nao esta estavel o suficiente

TanStack Query ajuda quando:

- os dados continuam em cache;
- o layout volta rapido;
- o componente nao zera a arvore visual por refetch ou por reordenacao agressiva.

Hoje isso ainda nao acontece com seguranca porque:

- a lista sofre `prepend`, `upsert`, `remove` e `rebuildPages`;
- o foco pode mudar quando o item atual sai do feed;
- a grade virtualizada remonta cards em um fluxo de dados bastante movel.

### 11. A virtualizacao custom continua valida, mas precisa ficar mais previsivel

O grid atual resolve um problema real de quantidade de cards, mas hoje tambem:

- observa scroll global;
- recalcula viewport frequentemente;
- depende de altura fixa de card;
- sente qualquer reorder local da lista.

Conclusao:

- nao existe indicio forte de que a virtualizacao em si precise ser removida;
- mas ela precisa receber um feed mais estavel e um pipeline de imagem com mais continuidade visual.

## Decisoes arquiteturais recomendadas

### 1. `effective state` vira a fonte de verdade operacional da rota `/moderation`

Para a pagina de moderacao:

- badge do card;
- quick filters;
- filtro principal de status;
- prioridade da fila;
- stats principais da pagina;

devem todos falar a mesma lingua: `effective_media_state`.

Estados brutos continuam importantes, mas como diagnostico:

- `moderation_status`
- `publication_status`
- `processing_status`
- `safety_status`
- `vlm_status`

### 2. O feed deve virar um contrato explicito de leitura operacional

O feed de moderacao nao pode ser apenas "lista de `event_media` com resource".

Ele precisa ser um contrato explicito para a superficie `/moderation`:

- primeira pagina operacional;
- ordenacao consistente;
- filtros coerentes com o que o operador enxerga;
- payload suficiente para o card;
- detalhe pesado carregado separadamente.

### 3. A ordenacao operacional precisa virar um contrato cursor-safe

Mesmo com implementacao cursorizada custom hoje, a regra tecnica precisa ficar explicita:

- a prioridade operacional deve existir como projecao SQL unica e reutilizavel;
- essa projecao deve ser deterministicamente espelhada no cursor;
- o conjunto final de ordenacao precisa continuar univoco;
- as partes do cursor nao podem depender de ambiguidade ou `null` instavel.

Decisao recomendada:

- tratar a prioridade operacional como alias/projecao formal da query;
- nao espalhar `CASE WHEN` solto entre `order by`, `where` do cursor e `stats`.

### 4. `stats` devem sair do payload da primeira pagina

Principio:

- o topo da pagina nao deve depender de `pages[0].meta.stats`;
- `stats` precisam de invalidacao propria;
- `stats` devem ser query barata e separada, ou endpoint dedicado.

### 5. Realtime novo entra como overlay, nao como reordenador autoritario da pagina

Principio:

- se o operador estiver no topo e sem selecao, o item pode entrar direto;
- fora disso, entra em fila de `incoming`;
- atualizacoes de itens visiveis devem preferir patch local, nao resort global imediato;
- exclusoes continuam removendo do feed e da fila.

### 6. Realtime de mutacao precisa de disciplina de transporte

Regras obrigatorias:

- request do moderador envia `X-Socket-ID`;
- broadcast relevante usa `toOthers()`;
- frontend compara `updated_at`, `version` ou heuristica equivalente antes de aplicar payload antigo.

### 7. Imagem precisa de um componente de superficie proprio

Nao basta renderizar `<img src=...>`.

A grade precisa de um componente de midia com:

- placeholder estavel;
- transicao so apos `load`;
- fallback controlado de erro;
- possibilidade de tratar diferentemente asset otimizado e asset original.

### 8. Scroll restoration deve ser tratado no router

Principio:

- TanStack Query ajuda a manter dados;
- React Router precisa assumir a restauracao de scroll;
- a chave de restauracao deve refletir pathname e filtros criticos;
- mudancas internas de query param devem avaliar `preventScrollReset`.

### 9. Observabilidade comeca na Sprint 1

Metas sem medicao nao fecham regressao.

Principio:

- medicao de feed, filtro, `fetchNextPage`, erro de thumbnail e origem de asset entra antes do rollout funcional;
- banco deve ter validacao por `EXPLAIN ANALYZE` desde o P0;
- `auto_explain` entra em rollout controlado quando o ambiente permitir.

### 10. A stack atual e suficiente; o gargalo e de contrato e estado

Nao ha motivo para trocar React, TanStack Query, Laravel ou a estrategia de cursor nesta rodada.

O que precisa mudar primeiro:

- contrato do feed;
- consistencia de status;
- uso correto de `AbortSignal`;
- politica de merge do feed;
- pipeline visual de thumbnail.

## Prioridade revisada

### `P0` obrigatorio

- alinhar feed, filtros, ordenacao e stats ao `effective state`;
- formalizar projecao cursor-safe, aliasavel e deterministicamente unica para a ordenacao operacional;
- propagar `AbortSignal` nas queries da rota;
- proteger `fetchNextPage` para nao concorrer com outro fetch;
- separar `stats` do payload da primeira pagina;
- adicionar `X-Socket-ID` no transporte, `toOthers()` no broadcast e versao/`updated_at` no dedupe;
- reduzir churn do realtime sobre a lista carregada;
- criar superficie de thumbnail com estado de loading e error;
- expor proveniencia do asset quando o card estiver usando fallback.
- comecar medicao operacional ja na primeira sprint.

### `P1` estrutural

- implementar scroll restoration de verdade no router;
- melhorar continuidade de foco;
- prefetch do detalhe ao abrir ou navegar;
- validar indices compostos por `EXPLAIN ANALYZE`;
- considerar partial index apenas se o predicado real fechar;
- memoizar cards e reduzir churn de props depois da estabilizacao de contrato;
- limitar custo de memoria da `InfiniteQuery` se a medicao mostrar necessidade.

### `P2` opcional

- refinamento fino de virtualizacao;
- heuristicas mais sofisticadas de warming e cache visual;
- experiments de `maxPages` ou politica avancada de descarte;
- qualquer troca maior de biblioteca de virtualizacao so depois de medir gargalo real.

## Fase 0 - Congelar comportamento e fechar contrato

Objetivo:

- travar a semantica esperada antes de mexer na pagina.

### 0.1 Manter os testes de caracterizacao criados nesta rodada

Subtarefas:

- [ ] manter `ModerationFeedCharacterizationTest` como retrato do bug atual enquanto a correcao nao entra;
- [ ] manter `feed-utils.test.ts` para documentar a semantica atual do frontend;
- [ ] manter `MediaAssetUrlServiceTest` como prova do fallback para original.

Observacao:

- quando a fase 1 entrar, o teste de caracterizacao deve ser invertido para virar teste de contrato final, nao removido.

### 0.2 Fechar o contrato funcional da rota `/media/feed`

Decisoes que precisam estar escritas e aceitas:

- [ ] `status` do feed passa a significar `effective_media_state`;
- [ ] `status` no filtro da rota `/media/feed` passa a operar sobre `effective state`;
- [ ] a ordenacao operacional passa a existir como projecao cursor-safe formal;
- [ ] `meta.stats` da pagina passa a refletir `effective state`, mas deixa de viajar acoplado a primeira pagina;
- [ ] campos brutos continuam no payload apenas para diagnostico e painel detalhado;
- [ ] detalhe e feed precisam concordar no mesmo `status` para o mesmo item.

### 0.3 Fechar a regra de transporte do realtime

Decisoes que precisam estar escritas e aceitas:

- [ ] requests autenticadas do admin enviam `X-Socket-ID`;
- [ ] broadcasts de mutation relevante usam `toOthers()`;
- [ ] frontend compara payload mais novo antes de aplicar patch.

### 0.4 Definir SLOs minimos e medicao inicial da pagina

Metas iniciais para esta rota:

- [ ] primeira pagina de feed em `p95 <= 700ms` no ambiente homologado;
- [ ] troca de filtro com tela estabilizada em `p95 <= 500ms` sem concorrencia de requests antigos;
- [ ] sem duplicidade de `fetchNextPage` para o mesmo cursor;
- [ ] sem discrepancia funcional entre feed e detalhe para `status`.
- [ ] comecar a registrar latencia de feed, filtro, erro de thumbnail e origem de asset desde a primeira sprint.

### Bateria TDD da fase 0

Backend:

- [ ] manter `apps/api/tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php`
- [ ] manter `apps/api/tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php`
- [ ] criar `apps/api/tests/Feature/MediaProcessing/ModerationFeedContractTest.php`

Frontend:

- [ ] manter `apps/web/src/modules/moderation/feed-utils.test.ts`
- [ ] manter `apps/web/src/lib/api.realtime.test.ts`
- [ ] manter `apps/web/src/app/routing/router-architecture.test.ts`
- [ ] criar `apps/web/src/modules/moderation/services/moderation.service.test.ts`

## Fase 1 - Corrigir o contrato backend do feed

Objetivo:

- fazer `/media/feed` e `/media/{id}` falarem a mesma lingua de status, filtro, prioridade e stats.

### 1.1 Carregar o contexto correto do evento no feed

Arquivos-alvo:

- `apps/api/app/Modules/MediaProcessing/Queries/ListModerationMediaQuery.php`
- `apps/api/app/Modules/MediaProcessing/Http/Resources/EventMediaResource.php`

Subtarefas:

- [ ] ampliar o eager loading de `event` para incluir o que o `MediaEffectiveStateResolver` realmente precisa;
- [ ] carregar tambem `event.contentModerationSettings` e `event.mediaIntelligenceSettings`;
- [ ] revisar colunas selecionadas para nao quebrar a regra de `id + foreign keys`.

Resultado esperado:

- o resource do feed deixa de perder contexto de IA.

### 1.2 Criar uma projecao unica de `effective state` para a query

Arquivos recomendados:

- `apps/api/app/Modules/MediaProcessing/Queries/ListModerationMediaQuery.php`
- `apps/api/app/Modules/MediaProcessing/Support/ModerationFeedStateProjection.php` ou equivalente

Subtarefas:

- [ ] centralizar a logica de `effective state` usada por filtro, ordenacao e stats;
- [ ] evitar duplicar `CASE WHEN` em varios pontos soltos;
- [ ] decidir se a projecao sera feita por:
  - helper SQL centralizado no proprio modulo;
  - ou view/read model se a expressao ficar complexa demais.

Decisao recomendada:

- comecar com helper SQL centralizado;
- migrar para read model dedicado apenas se o custo de query em producao justificar.

### 1.3 Alinhar ordenacao e cursor com a mesma projecao

Subtarefas:

- [ ] trocar a prioridade SQL atual baseada so em `moderation_status = pending`;
- [ ] usar a mesma prioridade que o frontend ja entende como `pending_moderation`;
- [ ] formalizar a projecao operacional com alias/representacao unica e cursor-safe;
- [ ] garantir que a combinacao final continue deterministicamente unica;
- [ ] garantir que o cursor nao dependa de trecho ambiguo ou valor `null` instavel;
- [ ] refletir a nova prioridade no cursor encode/decode;
- [ ] garantir que a ordenacao final continue estavel com:
  - `sort_order desc`
  - prioridade operacional
  - `created_at desc`
  - `id desc`

### 1.4 Alinhar filtro `status` e `meta.stats`

Subtarefas:

- [ ] fazer `status=pending_moderation` incluir pendencias de IA e workflow bruto;
- [ ] fazer `approved`, `rejected`, `published`, `processing`, `received` e `error` seguirem a semantica operacional decidida;
- [ ] recalcular `meta.stats` com a mesma semantica do filtro rapido.

Decisao importante:

- se a equipe ainda quiser contagens brutas de workflow, elas devem entrar em outro bloco de `meta`, nao no `stats` operacional do moderador.

### 1.5 Desacoplar `stats` da primeira pagina do feed

Arquivos-alvo:

- `apps/api/app/Modules/MediaProcessing/Http/Controllers/EventMediaController.php`
- rota dedicada do modulo `MediaProcessing`

Subtarefas:

- [ ] criar endpoint leve de `stats` para a moderacao;
- [ ] manter a mesma semantica de filtro operacional usada pelo feed;
- [ ] invalidar `stats` de forma independente das paginas do feed;
- [ ] decidir se contagens brutas tambem entram, mas em bloco separado.

### 1.6 Preservar diagnostico bruto sem poluir o contrato da superficie

Subtarefas:

- [ ] manter `moderation_status`, `publication_status`, `processing_status`, `safety_status` e `vlm_status` no payload;
- [ ] documentar que esses campos sao diagnosticos, nao a chave primaria de UX da rota;
- [ ] usar esses campos principalmente no painel lateral e em troubleshooting.

### Bateria TDD da fase 1

Antes de implementar:

- [ ] inverter `ModerationFeedCharacterizationTest` para o comportamento correto assim que a mudanca entrar;
- [ ] criar `apps/api/tests/Feature/MediaProcessing/ModerationFeedEffectiveStatusTest.php`
- [ ] criar `apps/api/tests/Feature/MediaProcessing/ModerationFeedStatsTest.php`
- [ ] criar `apps/api/tests/Feature/MediaProcessing/ModerationFeedCursorStabilityTest.php`
- [ ] criar `apps/api/tests/Feature/MediaProcessing/ModerationFeedStatsEndpointTest.php`

Cenarios obrigatorios:

- [ ] midia `approved` com `safety_status=review` em evento `ai + enforced` aparece como `pending_moderation`;
- [ ] `status=pending_moderation` traz workflow pendente e pendencia por IA;
- [ ] item com `vlm_status=rejected` em modo `gate` entra como `rejected`;
- [ ] cursor nao duplica nem pula item ao virar pagina com mistura de estados;
- [ ] `meta.stats.pending` bate com a mesma semantica do filtro "Nao moderadas".

## Fase 2 - Estabilizar query pipeline e infinite scroll no frontend

Objetivo:

- reduzir concorrencia inutil, cancelar requests obsoletos e diminuir churn da `InfiniteQuery`.

### 2.1 Propagar `AbortSignal` nas queries da rota

Arquivos-alvo:

- `apps/web/src/modules/moderation/services/moderation.service.ts`
- `apps/web/src/modules/moderation/ModerationPage.tsx`

Subtarefas:

- [ ] aceitar `signal` em `moderationService.list`;
- [ ] aceitar `signal` em `moderationService.show`;
- [ ] passar `signal` vindo do `queryFn` para o service;
- [ ] validar cancelamento nas trocas rapidas de busca, evento e filtros.

Resultado esperado:

- requests antigas deixam de completar e sobrescrever estado visual quando o usuario ja mudou o filtro.

### 2.2 Endurecer o gatilho de `fetchNextPage`

Subtarefas:

- [ ] proteger o callback do observer com `hasNextPage && !isFetching && !isFetchingNextPage`;
- [ ] revisar `rootMargin: '1200px 0px'` com base em medicao;
- [ ] garantir que o sentinela nao dispare cascata de pagina enquanto o viewport ainda esta montando;
- [ ] medir se a tela ganha mais com `600px` ou `800px` do que com `1200px`.

### 2.3 Desacoplar `stats` no frontend

Arquivos-alvo:

- `apps/web/src/modules/moderation/ModerationPage.tsx`
- `apps/web/src/modules/moderation/services/moderation.service.ts`

Subtarefas:

- [ ] criar query separada para `stats`;
- [ ] invalidar `stats` em mutation e em eventos relevantes;
- [ ] parar de ler `pages[0].meta.stats` como fonte do topo.

### 2.4 Reduzir cirurgia agressiva em `pages`

Arquivos-alvo:

- `apps/web/src/modules/moderation/feed-utils.ts`
- `apps/web/src/modules/moderation/ModerationPage.tsx`

Subtarefas:

- [ ] revisar `rebuildPages()` para nao reembaralhar mais do que o necessario;
- [ ] evitar resort global em atualizacao que nao muda chaves de ordenacao;
- [ ] manter `pageParams` intactos em toda cirurgia local;
- [ ] decidir se parte do estado de realtime deve sair da cache e virar overlay derivado.

Decisao recomendada:

- `query cache` continua sendo a fonte dos itens carregados;
- `incomingItems` vira overlay explicito para novidades ainda nao aplicadas.

### 2.5 Preservar foco e navegacao

Subtarefas:

- [ ] impedir troca de foco para o primeiro item em momentos desnecessarios;
- [ ] so mover foco automaticamente quando o item atual realmente sair do conjunto visivel;
- [ ] manter navegacao por teclado coerente apos approve/reject.

### Bateria TDD da fase 2

Arquivos recomendados:

- `apps/web/src/modules/moderation/services/moderation.service.test.ts`
- `apps/web/src/modules/moderation/ModerationPage.feed.test.tsx`
- ampliar `apps/web/src/modules/moderation/feed-utils.test.ts`

Cenarios obrigatorios:

- [ ] troca rapida de busca cancela request anterior;
- [ ] troca de filtro nao deixa resposta antiga reaparecer na tela;
- [ ] `fetchNextPage` nao roda quando `isFetching` ja estiver ativo;
- [ ] `stats` da pagina nao dependem mais de `pages[0].meta.stats`;
- [ ] merge local preserva `pageParams`;
- [ ] foco nao salta indevidamente durante update simples de item.

## Fase 3 - Corrigir continuidade visual da midia

Objetivo:

- parar de depender do navegador puro para mediar loading, erro e transicao de thumbnail.

### 3.1 Expor proveniencia do asset no backend

Arquivos-alvo:

- `apps/api/app/Modules/MediaProcessing/Services/MediaAssetUrlService.php`
- `apps/api/app/Modules/MediaProcessing/Http/Resources/EventMediaResource.php`
- `apps/api/app/Modules/MediaProcessing/Http/Resources/EventMediaDetailResource.php`

Campos recomendados:

- `thumbnail_source`
- `preview_source`

Valores esperados:

- `thumb`
- `gallery`
- `wall`
- `fast_preview`
- `original`
- `none`

Motivo:

- o frontend precisa saber quando esta mostrando um asset otimizado e quando caiu no original.

### 3.2 Criar um componente de superficie de thumbnail

Arquivos recomendados:

- `apps/web/src/modules/moderation/components/ModerationMediaThumbnail.tsx`
- `apps/web/src/modules/moderation/components/ModerationMediaCard.tsx`

Responsabilidades:

- [ ] estado `loading`;
- [ ] estado `loaded`;
- [ ] estado `error`;
- [ ] placeholder estavel;
- [ ] fade-in controlado apos `load`;
- [ ] fallback visual claro em erro sem quebrar o card.

### 3.3 Tratar asset original como classe diferente de risco visual

Subtarefas:

- [ ] se `thumbnail_source === original`, usar placeholder mais conservador;
- [ ] evitar trocar card para branco ou transparente enquanto a imagem grande nao chega;
- [ ] opcionalmente sinalizar visualmente que a variante otimizada ainda nao existe.

### 3.4 Revisar video em cards

Subtarefas:

- [ ] manter `preload="metadata"` em preview de video no grid;
- [ ] garantir que video sem poster nao degrade a experiencia da grade;
- [ ] validar se video precisa de placeholder proprio no grid.

### Bateria TDD da fase 3

Backend:

- [ ] criar `apps/api/tests/Unit/Modules/MediaProcessing/MediaAssetSourceTest.php`

Frontend:

- [ ] criar `apps/web/src/modules/moderation/components/ModerationMediaThumbnail.test.tsx`
- [ ] ampliar `apps/web/src/modules/moderation/components/ModerationReviewPanel.test.tsx`

Cenarios obrigatorios:

- [ ] thumbnail mostra placeholder antes do load;
- [ ] thumbnail faz fade-in apenas depois de `load`;
- [ ] erro de imagem cai em fallback estavel;
- [ ] payload exposto marca corretamente quando a origem e `original`.

## Fase 4 - Domar realtime e updates otimistas

Objetivo:

- impedir que a fila viva roube foco do operador ou reorganize tudo a cada evento.

### 4.1 Separar claramente `loaded feed` de `incoming queue`

Subtarefas:

- [ ] manter entrada imediata so quando o operador estiver no topo e sem selecao;
- [ ] se houver selecao ativa ou scroll distante do topo, enfileirar em `incoming`;
- [ ] exibir CTA claro para aplicar novos itens;
- [ ] impedir que item novo derrube o contexto de revisao atual.

### 4.2 Corrigir transporte e supressao de eco

Subtarefas:

- [ ] enviar `X-Socket-ID` em todas as mutations autenticadas do admin;
- [ ] usar `toOthers()` nos broadcasts da trilha de moderacao;
- [ ] comparar `updated_at`, `version` ou heuristica equivalente antes de aplicar payload remoto;
- [ ] validar que mutation local + resposta HTTP + broadcast nao fazem triplo churn do mesmo item.

### 4.3 Patch local por tipo de evento

Regras recomendadas:

- `created`
  - entra no feed somente quando seguro;
  - caso contrario vai para `incoming`;
- `updated`
  - faz patch local do item visivel;
  - so reordena se chave de ordenacao mudar de fato;
- `deleted`
  - remove do feed, fila e selecao.

### 4.4 Mutations do operador precisam ser previsiveis

Subtarefas:

- [ ] approve/reject devem mover foco para o proximo item pendente de forma deterministica;
- [ ] favorite/pin devem evitar resort global desnecessario;
- [ ] bulk actions devem aplicar patch em lote e refetch controlado em seguida.

### 4.5 Reconciliacao apos reconnect

Subtarefas:

- [ ] quando o canal websocket reconectar, disparar resync controlado;
- [ ] evitar duplicar item ja patchado localmente;
- [ ] garantir que a fila `incoming` continue consistente apos reconnect.

### Bateria TDD da fase 4

Arquivos recomendados:

- `apps/web/src/modules/moderation/hooks/useModerationRealtime.test.ts`
- `apps/web/src/modules/moderation/ModerationPage.realtime.test.tsx`
- ampliar `apps/web/src/modules/moderation/feed-utils.test.ts`

Cenarios obrigatorios:

- [ ] item novo nao entra no topo enquanto ha selecao ativa;
- [ ] mutation do operador nao reaplica eco do proprio socket;
- [ ] item atualizado visivel sofre patch sem recriar toda a lista;
- [ ] delete remove item de feed, `incoming` e selecao;
- [ ] reconnect dispara resync sem duplicidade.

## Fase 5 - Melhorar o painel lateral e cortar waterfalls evitaveis

Objetivo:

- manter abertura do painel rapida sem depender de waterfall desnecessario.

### 5.1 Separar `feed payload` de `detail payload`

Regra recomendada:

- o feed entrega tudo o que o card precisa e um subconjunto leve do review;
- o detalhe busca somente campos pesados:
  - bloco completo de IA;
  - diagnostico de deduplicacao;
  - auditoria de override;
  - dados mais ricos do remetente.

### 5.2 Semear o painel com dados do feed

Subtarefas:

- [ ] abrir o painel com `focusedMedia` imediatamente;
- [ ] hidratar os campos faltantes com `show()` sem resetar o layout;
- [ ] evitar spinner de tela inteira no painel quando ja existe bootstrap local.

### 5.3 Prefetch onde a navegacao e previsivel

Subtarefas:

- [ ] prefetch do detalhe ao focar item via teclado;
- [ ] opcionalmente prefetch do proximo item pendente;
- [ ] medir se isso reduz sensacao de atraso sem gerar waterfall desnecessario.

### Bateria TDD da fase 5

Arquivos recomendados:

- ampliar `apps/web/src/modules/moderation/components/ModerationReviewPanel.test.tsx`
- criar `apps/web/src/modules/moderation/ModerationPage.detail.test.tsx`

Cenarios obrigatorios:

- [ ] painel abre com dados iniciais do feed;
- [ ] detalhe completo chega sem desmontar estrutura do painel;
- [ ] navegacao por teclado preenche o proximo item com menor latencia percebida.

## Fase 6 - Observabilidade, rollout e guardrails permanentes

Objetivo:

- impedir que a pagina volte a degradar silenciosamente.

### 6.1 Medir o que importa

Metricas minimas:

- [ ] tempo da primeira pagina do feed;
- [ ] tempo de troca de filtro ate tela estabilizada;
- [ ] quantidade de `fetchNextPage` por sessao;
- [ ] taxa de `thumbnail_source=original`;
- [ ] taxa de erro de imagem no card;
- [ ] tamanho medio da fila `incoming`;
- [ ] tempo de abertura do painel lateral.

Observacao:

- essa fase organiza o guardrail permanente;
- a instrumentacao minima precisa comecar ainda na Sprint 1.

### 6.2 Rollout por ordem de risco

Sequencia recomendada:

1. alinhar contrato backend;
2. separar `stats` do feed;
3. ligar `AbortSignal` e endurecer infinite scroll;
4. trocar superficie de thumbnail;
5. estabilizar overlay realtime;
6. otimizar painel lateral.

### 6.3 Guardrails de regressao

Subtarefas:

- [ ] adicionar suite da moderacao ao CI;
- [ ] bloquear PR que quebre contrato de feed;
- [ ] bloquear PR que remova cobertura de `feed-utils`;
- [ ] bloquear PR que quebre `thumbnail_source` ou fallback visual.

## Bateria TDD consolidada

### Backend

Manter:

- `apps/api/tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php`
- `apps/api/tests/Unit/Modules/MediaProcessing/MediaAssetUrlServiceTest.php`

Criar:

- `apps/api/tests/Feature/MediaProcessing/ModerationFeedContractTest.php`
- `apps/api/tests/Feature/MediaProcessing/ModerationFeedEffectiveStatusTest.php`
- `apps/api/tests/Feature/MediaProcessing/ModerationFeedStatsTest.php`
- `apps/api/tests/Feature/MediaProcessing/ModerationFeedCursorStabilityTest.php`
- `apps/api/tests/Unit/Modules/MediaProcessing/MediaAssetSourceTest.php`

### Frontend

Manter:

- `apps/web/src/modules/moderation/feed-utils.test.ts`
- `apps/web/src/modules/moderation/components/ModerationReviewPanel.test.tsx`

Criar:

- `apps/web/src/modules/moderation/services/moderation.service.test.ts`
- `apps/web/src/modules/moderation/ModerationPage.feed.test.tsx`
- `apps/web/src/modules/moderation/ModerationPage.realtime.test.tsx`
- `apps/web/src/modules/moderation/ModerationPage.detail.test.tsx`
- `apps/web/src/modules/moderation/components/ModerationMediaThumbnail.test.tsx`
- `apps/web/src/modules/moderation/hooks/useModerationRealtime.test.ts`

## Sequencia recomendada de execucao

### Sprint 1

- Fase 0 completa
- Fase 1 completa
- instrumentacao minima da Fase 6.1 ligada
- iniciar Fase 2.1, 2.2 e 2.3

Saida esperada:

- rota `/media/feed` coerente com `effective state`
- projecao cursor-safe formalizada
- `stats` corrigidos e desacoplados do feed
- requests obsoletos cancelados

### Sprint 2

- concluir Fase 2
- concluir Fase 3

Saida esperada:

- infinite scroll mais previsivel
- card sem "pisca" branco
- tratamento claro para fallback do original

### Sprint 3

- concluir Fase 4
- concluir Fase 5
- consolidar Fase 6

Saida esperada:

- realtime mais amigavel para operacao
- painel lateral mais responsivo
- guardrails de regressao ligados

## Definicao de pronto

A pagina `/moderation` so deve ser considerada estabilizada quando:

- [ ] o mesmo item tem o mesmo `status` no feed e no detalhe;
- [ ] `status=pending_moderation` traz toda a fila operacional esperada;
- [ ] `stats` da pagina vem de query dedicada e batem com os quick filters visiveis;
- [ ] a ordenacao operacional continua cursor-safe, sem duplicar ou pular item;
- [ ] requests antigas nao sobrescrevem filtros novos;
- [ ] `fetchNextPage` nao concorre com outro fetch da mesma `InfiniteQuery`;
- [ ] mutations do moderador enviam `X-Socket-ID` e broadcasts relevantes usam `toOthers()`;
- [ ] cards de imagem tem placeholder, transicao de load e fallback de erro;
- [ ] a taxa de uso de asset original e observavel;
- [ ] scroll restoration esta explicitamente tratado no router;
- [ ] realtime nao rouba foco do operador em uso normal;
- [ ] testes backend e frontend da moderacao estao verdes no CI.

## Duvidas ainda em aberto

Estas duvidas nao bloqueiam o plano, mas precisam ser fechadas antes das fases finais:

1. `stats` dedicadas devem expor apenas visao operacional ou tambem um bloco bruto de diagnostico?
2. qual `rootMargin` final entrega melhor equilibrio entre continuidade e churn no ambiente real?
3. vale limitar `maxPages` na rota de moderacao ou isso piora scroll restoration para o time operacional?
4. qual `getKey` do router representa melhor o retorno para a fila: pathname puro ou pathname + filtros criticos?
5. o painel lateral deve prefetch do proximo item por teclado ja no `P1` ou isso fica para medicao posterior?

## Recomendacao final

O `P0` real da moderacao nao e trocar biblioteca nem reescrever a tela inteira.

O `P0` real e:

1. corrigir o contrato backend do feed para `effective state`;
2. formalizar uma projecao cursor-safe para ordenacao operacional;
3. separar `stats` do payload da primeira pagina;
4. propagar `AbortSignal` e endurecer a `InfiniteQuery`;
5. adicionar `X-Socket-ID` + `toOthers()` + dedupe por versao/tempo;
6. parar de tratar thumbnail como `<img>` sem estado.

Se essa ordem for seguida, a pagina sai do estado "funciona, mas pisca e confunde" para um estado operacional previsivel, com contrato consistente e base boa para refinamentos posteriores.
