# Super Admin global support gap analysis - 2026-04-10

## Objetivo

Documentar os gaps encontrados no suporte global do `super-admin`, a validacao do erro em producao e a abordagem recomendada para manter:

- visibilidade global real para `super-admin` e `platform-admin`;
- escopo restrito para os demais papeis conforme a matriz de permissoes;
- queries compativeis com PostgreSQL nas telas administrativas;
- UX de suporte com filtro global e drill-down por organizacao.

## Cenario validado

Contexto reportado:

- usuario `superadmin@eventovivo.com.br`;
- parceiro `Umalu Eventos` (`organization_id = 3`);
- cliente `Umalu Eventos - Lucineia` com `events_count = 1`;
- tentativa de abrir `/partners/3` e `/partners/3/activity`;
- resposta `500 Internal Server Error`.

Validacao objetiva em producao, no arquivo:

- `/var/www/eventovivo/shared/storage/logs/laravel-2026-04-10.log`

Erro encontrado em `2026-04-10 22:10:28` e repetido em `2026-04-10 22:11:15`:

- `SQLSTATE[42883]: Undefined function: 7 ERROR: operator does not exist: text = bigint`
- request paths:
  - `api/v1/partners/3`
  - `api/v1/partners/3/activity`

## Diagnostico consolidado

### 1. `/events` nao estava realmente global para super-admin

Arquivo:

- `apps/api/app/Modules/Events/Http/Controllers/EventController.php`

Problema encontrado:

- a listagem usava `organization_id` do request ou `currentOrganization()` por padrao;
- isso fazia o `super-admin` cair no escopo da organizacao ativa;
- na pratica, se o `super-admin` estivesse com a organizacao da plataforma ativa, ele nao veria os eventos dos parceiros por padrao.

Risco adicional encontrado:

- um usuario nao global podia enviar `organization_id` manualmente e forcar a listagem de outra organizacao;
- isso contrariava a matriz de permissoes.

### 2. O detalhe e a activity do parceiro quebravam em PostgreSQL

Arquivo:

- `apps/api/app/Modules/Partners/Queries/ListPartnerActivitiesQuery.php`

Causa raiz:

- a query comparava propriedades JSON do `activity_log` com inteiros;
- no PostgreSQL, `properties->>'partner_id'` retorna `text`;
- a comparacao final ficava equivalente a `text = bigint`.

Impacto:

- `GET /api/v1/partners/{id}`
- `GET /api/v1/partners/{id}/activity`

Ambos quebravam porque o detalhe do parceiro tambem puxa atividade recente.

### 3. O contexto explicito de organizacao para super-admin era aceito, mas nao persistia corretamente

Arquivos:

- `apps/api/app/Modules/Users/Models/User.php`
- `apps/api/app/Modules/Auth/Services/WorkspaceStateBuilderService.php`

Problema encontrado:

- `POST /api/v1/auth/context/organization` ja permitia que `super-admin` escolhesse qualquer organizacao;
- mas `currentOrganization()` so resolvia memberships;
- e o `WorkspaceStateBuilderService` descartava organizacoes fora da lista de memberships;
- resultado: o backend aceitava o contexto, mas `/auth/me` voltava para a organizacao anterior.

Impacto:

- telas dependentes de `currentOrganization()` podiam se comportar como se o contexto nao tivesse sido trocado;
- suporte operacional ficava inconsistente.

### 4. A tela web de eventos nao tinha filtro global por organizacao

Arquivo:

- `apps/web/src/modules/events/EventsListPage.tsx`

Problema encontrado:

- diferente de `ClientsPage` e `AnalyticsPage`, a pagina de eventos nao oferecia filtro global por organizacao para `super-admin`;
- mesmo com o backend corrigido, a UX de suporte continuaria inferior.

## Correcoes aplicadas nesta rodada

### Backend

1. `EventController@index`

- `super-admin` e `platform-admin` agora listam todos os eventos por padrao;
- filtro por `organization_id` continua disponivel para usuarios globais;
- usuarios nao globais nao podem mais forcar `organization_id` de outra organizacao.

2. `activity_log` queries

- foi criado o helper compartilhado `InteractsWithActivityLogProperties`;
- comparacoes por `partner_id`, `organization_id` e `event_id` passaram a usar valores text-compatible para o JSON path;
- isso remove a incompatibilidade `text = bigint` no PostgreSQL.

Arquivos ajustados:

- `apps/api/app/Shared/Concerns/InteractsWithActivityLogProperties.php`
- `apps/api/app/Modules/Partners/Queries/ListPartnerActivitiesQuery.php`
- `apps/api/app/Modules/Audit/Queries/ListAuditActivitiesQuery.php`
- `apps/api/app/Modules/Audit/Http/Controllers/EventTimelineController.php`

3. Resolucao de contexto global

- `User::currentOrganization()` agora respeita o `active_context.organization_id` para usuarios globais, mesmo sem membership naquela organizacao;
- `WorkspaceStateBuilderService` agora preserva a organizacao explicitamente escolhida pelo `super-admin`.

### Frontend

1. `EventsListPage`

- filtro por organizacao adicionado para usuarios globais;
- listagem segue global por padrao;
- o filtro envia `organization_id` apenas quando selecionado.

2. `eventsService`

- adicionado carregamento das organizacoes para alimentar o filtro da pagina de eventos.

## Testes executados

### Backend

Comando:

- `php artisan test tests/Feature/Events/ListEventsTest.php tests/Feature/Auth/MeTest.php tests/Unit/Shared/InteractsWithActivityLogPropertiesTest.php`

Resultado:

- `23 passed`

Comando:

- `php artisan test tests/Feature/Partners/PartnerAdminCrudContractTest.php`

Resultado:

- `26 passed`

### Frontend

Comando:

- `npm run type-check`

Resultado:

- `tsc --noEmit` concluido com sucesso

## Melhor abordagem daqui para frente

### Regra de produto

Para usuarios globais:

- listas administrativas devem abrir em escopo global por padrao;
- filtros por organizacao e evento devem ser explicitos;
- contexto de organizacao deve servir para operacao assistida, nao para esconder o resto da plataforma.

Para usuarios nao globais:

- o escopo deve nascer do contexto autorizado;
- parametros de filtro nao podem ampliar esse escopo.

### Regra tecnica

Quando consultar `activity_log.properties` com IDs:

- tratar o valor extraido do JSON como texto;
- alinhar o tipo da comparacao com o subquery;
- nao depender de comportamento implicito de SQLite para validar query que vai rodar em PostgreSQL.

### Proximas melhorias recomendadas

1. Criar smoke tests E2E de `super-admin` para:

- `/events`
- `/clients`
- `/partners`
- `/analytics`
- troca de contexto de organizacao

2. Fazer um sweep de todos os pontos que usam `currentOrganization()` e separar claramente:

- telas globais;
- telas operacionais por organizacao;
- telas operacionais por evento.

3. Revisar se o workspace selector deve expor busca de qualquer organizacao para usuarios globais, em vez de depender apenas de memberships.

4. Adicionar uma policy utilitaria para escopo administrativo global, reduzindo divergencia entre controllers.

5. Rodar ao menos uma suite backend de regressao contra PostgreSQL no CI para capturar erros que SQLite nao pega.

## Conclusao

Os dois problemas principais estavam confirmados:

- o `super-admin` nao tinha visao global real de eventos por padrao;
- o detalhe/activity de parceiro quebrava em PostgreSQL por comparacao incorreta entre `text` e `bigint`.

Com os ajustes desta rodada, o comportamento esperado de suporte global fica alinhado com a matriz de permissoes:

- global para `super-admin` e `platform-admin`;
- restrito para os demais;
- filtros explicitos na UX;
- queries administrativas compativeis com PostgreSQL.
