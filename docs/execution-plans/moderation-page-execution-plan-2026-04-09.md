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

Itens que fecharam o corte de rollout deste plano:

- [x] publicar em homolog uma release que contenha o comando `media:moderation-feed-explain`, o `search document` e as migrations da trilha atual da moderacao;
- [x] rodar o benchmark em homolog com a release atualizada e validar a politica de JIT do PostgreSQL para queries OLTP do painel.

Comando recomendado para homolog:

- `cd apps/api && php artisan media:moderation-feed-explain --disable-jit --fail-on-budget --output=storage/app/reports/moderation-feed-explain-homolog.json`
- quando o ambiente nao tiver `event_media` real, usar a sonda transacional:
  - `cd apps/api && php artisan media:moderation-feed-explain --organization-id=1 --synthetic-media=5000 --disable-jit --fail-on-budget --output=storage/app/reports/moderation-feed-explain-homolog-disable-jit.json`

Comando recomendado para sonda local de volume:

- `cd apps/api && php artisan media:moderation-feed-explain --synthetic-media=20000 --disable-jit --fail-on-budget --output=storage/app/reports/moderation-feed-explain-synthetic-20000.json`

## Status da decima entrega

Itens concluidos nesta rodada:

- [x] ampliar o contrato do `/media/feed` e `/media/feed/stats` com `media_type`, `duplicates` e `ai_review`;
- [x] endurecer a projecao operacional para expor `error` quando `processing_status = failed`, antes da queda para `pending_moderation`;
- [x] criar projecao SQL dedicada de `ai review` para reaproveitar a mesma semantica de safety/VLM bloqueante;
- [x] expor quick filters operacionais para `Com erro`, `Imagens`, `Videos`, `IA em review` e `Duplicatas`;
- [x] adicionar controles avancados de `media type`, `IA em review` e `duplicatas` na UI da moderacao;
- [x] ampliar a cobertura com TDD em `ModerationMediaTest.php`, `feed-utils.test.ts`, `moderation.service.test.ts` e `moderation-architecture.test.ts`.

## Status da decima primeira entrega

Itens concluidos nesta rodada:

- [x] criar `resolveModerationQueueProgress()` para calcular posicao carregada, posicao pendente e pendentes restantes a partir da ordem real do feed;
- [x] usar `stats.pending` da query dedicada como total autoritativo da fila pendente no recorte ativo;
- [x] expor `Pendentes restantes` e `Posicao atual` no topo da fila de moderacao;
- [x] expor posicao, pendentes depois da midia atual e amostra carregada no `ModerationReviewPanel`;
- [x] validar no backend que `stats.pending` continua alinhado aos filtros ativos de moderacao;
- [x] ampliar a cobertura TDD em `feed-utils.test.ts`, `ModerationReviewPanel.test.tsx`, `moderation-architecture.test.ts` e `ModerationMediaTest.php`.

## Status da decima segunda entrega

Itens concluidos nesta rodada:

- [x] validar acesso SSH ao ambiente de homolog informado pelo time;
- [x] localizar a release ativa em `/var/www/eventovivo/current -> /var/www/eventovivo/releases/20260406_194835`;
- [x] confirmar que a release ativa de homolog ainda nao contem o comando `media:moderation-feed-explain`, os services `ModerationFeedExplainAnalyzeService` e `ModerationSearchDocumentBuilder`, nem as migrations `2026_04_09_230000_add_moderation_feed_indexes.php` e `2026_04_09_232000_add_moderation_search_document_to_event_media`;
- [x] registrar que a pendencia de benchmark em homolog deixou de ser falta de acesso e passou a ser falta de rollout da release atual da moderacao;
- [x] manter a validacao local verde com benchmark da moderacao em PostgreSQL real via `php artisan media:moderation-feed-explain --disable-jit`.

## Status da decima terceira entrega

Itens concluidos nesta rodada:

- [x] publicar em homolog a release atual da moderacao em `/var/www/eventovivo/releases/20260410_181500_moderation`;
- [x] confirmar que a release ativa de homolog agora contem o comando `media:moderation-feed-explain`;
- [x] executar o benchmark oficial em homolog com sonda sintetica transacional de `5.000` midias porque o ambiente ainda nao tem `event_media` real;
- [x] validar que, com JIT habilitado, `search_sender_name_hot` saiu do budget (`~1378ms`) e que o proprio plano do PostgreSQL registrou `~1339ms` de compilacao JIT;
- [x] validar que, com `--disable-jit`, todos os cenarios ficaram dentro do budget em homolog (`feed <= 700ms`, `search <= 500ms`);
- [x] fechar a politica de JIT para esta rota: manter benchmark e leitura OLTP da moderacao validados com `SET LOCAL jit = off` no comando oficial;
- [x] ligar observabilidade minima da rota com logs estruturados de `feed`, `stats`, `detail` e endpoint dedicado de telemetria do frontend;
- [x] instrumentar o frontend para `feed_first_page_loaded`, `filters_stabilized`, `feed_next_page_loaded`, `detail_loaded`, `incoming_queue_changed`, `media_surface_error`, `media_surface_original_fallback` e `media_surface_unavailable`;
- [x] definir `Nao moderadas` como recorte default do produto e fazer `Limpar filtros` voltar para esse estado operacional;
- [x] levar a bateria atual da moderacao para CI com workflow dedicado em `.github/workflows/moderation.yml`;
- [x] rerodar a bateria local completa da moderacao com os testes novos de observabilidade e telemetria.

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
- `apps/api/tests/Feature/MediaProcessing/ModerationMediaTest.php`
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
- `apps/web/src/modules/moderation/components/ModerationReviewPanel.test.tsx`
- `apps/web/src/modules/moderation/moderation-architecture.test.ts`
- `apps/api/tests/Feature/MediaProcessing/ModerationMediaTest.php`

### Comandos executados

Entrega atual:

- `cd apps/web && npm.cmd run test -- src/modules/moderation/feed-utils.test.ts src/modules/moderation/services/moderation.service.test.ts src/modules/moderation/components/ModerationReviewPanel.test.tsx src/modules/moderation/components/ModerationMediaSurface.test.tsx src/modules/moderation/moderation-architecture.test.ts`
  - `5 arquivos`
  - `35 testes`
  - `PASS`
- `cd apps/web && npm.cmd run type-check`
  - `PASS`
- `cd apps/api && php artisan test tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php tests/Feature/MediaProcessing/ModerationMediaTest.php tests/Feature/MediaProcessing/EventMediaListTest.php tests/Unit/MediaProcessing/MediaEffectiveStateResolverTest.php tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php`
  - `32 testes`
  - `439 assertions`
  - `PASS`

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

Entrega final desta rodada:

- `cd apps/web && npm.cmd run test -- src/lib/api.realtime.test.ts src/app/routing/router-architecture.test.ts src/app/routing/scroll-restoration.test.ts src/modules/moderation/feed-utils.test.ts src/modules/moderation/services/moderation.service.test.ts src/modules/moderation/components/ModerationReviewPanel.test.tsx src/modules/moderation/components/ModerationMediaSurface.test.tsx src/modules/moderation/moderation-architecture.test.ts`
  - `8 arquivos`
  - `44 testes`
  - `PASS`
- `cd apps/web && npm.cmd run type-check`
  - `PASS`
- `cd apps/api && php artisan test tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php tests/Feature/MediaProcessing/ModerationMediaTest.php tests/Feature/MediaProcessing/EventMediaListTest.php tests/Feature/MediaProcessing/RunModerationFeedExplainCommandTest.php tests/Feature/MediaProcessing/ModerationTelemetryEndpointTest.php tests/Unit/MediaProcessing/MediaEffectiveStateResolverTest.php tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php tests/Unit/Modules/MediaProcessing/ModerationFeedExplainAnalyzeServiceTest.php tests/Unit/Modules/MediaProcessing/ModerationSearchDocumentBuilderTest.php tests/Unit/Modules/MediaProcessing/MediaAssetUrlServiceTest.php tests/Unit/Modules/MediaProcessing/ModerationObservabilityServiceTest.php`
  - `49 testes`
  - `546 assertions`
  - `PASS`
- homolog:
  - release ativa em `/var/www/eventovivo/current -> /var/www/eventovivo/releases/20260410_181500_moderation`
  - `php artisan media:moderation-feed-explain --organization-id=1 --synthetic-media=5000 --output=storage/app/reports/moderation-feed-explain-homolog-jit-on.json`
    - `FAIL` de budget em `search_sender_name_hot ~= 1377ms`
    - `JIT Timing Total ~= 1339ms`
  - `php artisan media:moderation-feed-explain --organization-id=1 --synthetic-media=5000 --disable-jit --fail-on-budget --output=storage/app/reports/moderation-feed-explain-homolog-disable-jit.json`
    - `PASS`
    - `feed_org_hot ~= 61ms`
    - `feed_event_hot ~= 46ms`
    - `feed_pending_hot ~= 37ms`
    - `search_event_title_hot ~= 64ms`
    - `search_sender_name_hot ~= 53ms`
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
- `cd apps/api && php artisan test tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php tests/Feature/MediaProcessing/ModerationMediaTest.php tests/Feature/MediaProcessing/EventMediaListTest.php tests/Unit/MediaProcessing/MediaEffectiveStateResolverTest.php tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php`
  - `31 testes`
  - `425 assertions`
  - `PASS`

Frontend:

- `cd apps/web && npm.cmd run test -- src/modules/moderation/feed-utils.test.ts src/modules/moderation/services/moderation.service.test.ts src/modules/moderation/moderation-architecture.test.ts`
  - `3 arquivos`
  - `21 testes`
  - `PASS`
- `cd apps/web && npm.cmd run test -- src/modules/moderation/feed-utils.test.ts src/modules/moderation/services/moderation.service.test.ts src/modules/moderation/components/ModerationReviewPanel.test.tsx src/modules/moderation/components/ModerationMediaSurface.test.tsx src/modules/moderation/moderation-architecture.test.ts`
  - `5 arquivos`
  - `31 testes`
  - `PASS`
- `cd apps/web && npm.cmd run type-check`
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

## Backlog remanescente normalizado

O topo deste documento ja registra 13 entregas concluidas. O backlog real da moderacao agora ficou reduzido a observacao operacional e refino guiado por medicao.

### 1. Follow-up de observabilidade

Estado atual entregue:

- logs estruturados de `moderation.feed.response`, `moderation.feed.stats`, `moderation.feed.detail` e `moderation.feed.client_telemetry`;
- telemetria do frontend para:
  - `feed_first_page_loaded`
  - `filters_stabilized`
  - `feed_next_page_loaded`
  - `detail_loaded`
  - `incoming_queue_changed`
  - `media_surface_error`
  - `media_surface_original_fallback`
  - `media_surface_unavailable`

Proxima ordem:

1. observar os logs da rota em homolog apos uso real da equipe;
2. confirmar thresholds práticos de alerta para:
   - primeira pagina;
   - estabilizacao de filtros;
   - taxa de erro de surface;
   - taxa de fallback para asset original;
   - crescimento da fila `incoming`;
3. decidir se essa trilha permanece em logs estruturados ou sobe para dashboard dedicado.

### 2. Validacao do workflow de CI em execucao remota

Estado atual entregue:

- workflow `.github/workflows/moderation.yml` criado com jobs separados de frontend e backend;
- type-check do frontend entrou como gate;
- a bateria atual da moderacao foi espelhada no workflow.

Follow-up real:

1. validar a primeira execucao do workflow no GitHub;
2. ajustar apenas se o runner remoto revelar diferenca de ambiente nao coberta localmente.

### 3. Decisoes restantes de produto e UX operacional

Decisoes ja fechadas:

- `Nao moderadas` virou o recorte default do produto;
- `Limpar filtros` volta para esse recorte operacional.

Ainda em aberto:

- [ ] decidir se a visao de `stats` segue apenas operacional ou se ganha tambem um bloco bruto de diagnostico;
- [ ] decidir se o `prefetch` deve subir do item focado para o proximo item pendente quando a navegacao por teclado for dominante.

### 4. Refino opcional guiado por medicao

Os checks ainda abertos abaixo sao intencionais:

- nao representam falha de implementacao do plano base;
- representam apenas calibracao fina apos uso real e observabilidade ligada.

Esses itens nao devem voltar ao `P0`.

- [ ] revisar `rootMargin` com medicao real de churn/continuidade;
- [ ] avaliar `maxPages` apenas se memoria ou refetch sequencial virarem problema real;
- [ ] revisar memoizacao/virtualizacao so se surgir gargalo mensuravel apos observabilidade;
- [ ] considerar mais testes de realtime detalhado apenas se aparecer regressao nova nessa trilha.

## Bateria de regressao que precisa permanecer verde

### Backend

Manter:

- `apps/api/tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php`
- `apps/api/tests/Feature/MediaProcessing/ModerationMediaTest.php`
- `apps/api/tests/Feature/MediaProcessing/EventMediaListTest.php`
- `apps/api/tests/Feature/MediaProcessing/RunModerationFeedExplainCommandTest.php`
- `apps/api/tests/Feature/MediaProcessing/ModerationTelemetryEndpointTest.php`
- `apps/api/tests/Unit/MediaProcessing/MediaEffectiveStateResolverTest.php`
- `apps/api/tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php`
- `apps/api/tests/Unit/Modules/MediaProcessing/ModerationFeedExplainAnalyzeServiceTest.php`
- `apps/api/tests/Unit/Modules/MediaProcessing/ModerationSearchDocumentBuilderTest.php`
- `apps/api/tests/Unit/Modules/MediaProcessing/MediaAssetUrlServiceTest.php`
- `apps/api/tests/Unit/Modules/MediaProcessing/ModerationObservabilityServiceTest.php`

### Frontend

Manter:

- `apps/web/src/lib/api.realtime.test.ts`
- `apps/web/src/app/routing/router-architecture.test.ts`
- `apps/web/src/app/routing/scroll-restoration.test.ts`
- `apps/web/src/modules/moderation/feed-utils.test.ts`
- `apps/web/src/modules/moderation/services/moderation.service.test.ts`
- `apps/web/src/modules/moderation/components/ModerationReviewPanel.test.tsx`
- `apps/web/src/modules/moderation/components/ModerationMediaSurface.test.tsx`
- `apps/web/src/modules/moderation/moderation-architecture.test.ts`

Extensoes futuras so se surgirem gaps reais:

- `apps/web/src/modules/moderation/ModerationPage.realtime.test.tsx`
- `apps/web/src/modules/moderation/ModerationPage.detail.test.tsx`
- `apps/web/src/modules/moderation/hooks/useModerationRealtime.test.ts`

## Definicao de pronto normalizada

A rota `/moderation` so deve ser considerada estabilizada de ponta a ponta quando:

- [x] feed e detalhe usam o mesmo `status` operacional;
- [x] `status=pending_moderation` traz a fila operacional esperada;
- [x] `stats` da pagina vem de query dedicada e batem com os quick filters visiveis;
- [x] a ordenacao operacional continua cursor-safe, sem duplicar ou pular item;
- [x] requests antigas deixam de sobrescrever filtros novos;
- [x] `fetchNextPage` nao concorre com outro fetch da mesma `InfiniteQuery`;
- [x] mutations do moderador enviam `X-Socket-ID` e broadcasts relevantes usam `toOthers()`;
- [x] cards de imagem/video usam placeholder, transicao de load e fallback de erro;
- [x] a rota nao cai no asset original no feed da moderacao;
- [x] scroll restoration esta tratado no router;
- [x] realtime nao reaplica eco basico do proprio operador;
- [x] a fila mostra contador de pendentes restantes e posicao atual;
- [x] benchmark em homolog foi executado com a release correta;
- [x] politica de JIT foi validada em homolog/producao;
- [x] observabilidade minima da rota esta ligada;
- [x] testes backend e frontend da moderacao rodam no CI.

## Duvidas ainda em aberto

Estas duvidas nao bloqueiam o estado local atual, mas precisam de fechamento antes do encerramento definitivo:

1. qual `rootMargin` final entrega melhor equilibrio entre continuidade e churn no ambiente real?
2. vale limitar `maxPages` na rota de moderacao ou isso piora scroll restoration?
3. `stats` devem expor somente visao operacional ou tambem contagens brutas em bloco separado?
4. o painel lateral deve prefetch do proximo item pendente por teclado ou manter apenas o aquecimento do proximo item carregado?

## Recomendacao final

O proximo passo correto nao e abrir mais fundacao de moderacao.

A ordem correta agora e:

1. observar o comportamento real da rota em homolog com a instrumentacao ligada;
2. validar a primeira execucao do workflow de CI no GitHub;
3. fechar apenas as duas decisoes de produto remanescentes (`stats` bruto e estrategia final de prefetch);
4. so depois abrir refinos opcionais de UX/performance guiados por medicao.
