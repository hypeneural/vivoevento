# Partners Admin CRUD and Access Plan

## Contexto

A pagina `/partners` do painel administrativo nasceu como tela mockada e agora usa API real para listagem. Ela representa parceiros B2B, como cerimonialistas, fotografos, agencias e fornecedores profissionais, mas o dominio real ja usa `organizations` como conta comercial principal.

Definicao recomendada:

- `partner` e uma `Organization` com `organizations.type = 'partner'`.
- A tela `/partners` e uma visao administrativa global sobre essas organizacoes parceiras.
- O usuario da propria organizacao parceira nao deve conseguir listar nem visualizar outros parceiros.
- O super administrador pode ver todos os parceiros, seus eventos, clientes, staff, plano, grants/bonus, faturamento e logs.

Observacao de produto:

- Se `platform-admin` deve ter o mesmo poder que `super-admin`, isso precisa ser uma decisao explicita. O requisito levantado aqui fala em super administrador; os testes de contrato focam em `super-admin`.

## Gap atual

- Frontend: `apps/web/src/modules/partners/PartnersPage.tsx` agora consome `GET /api/v1/partners`; o mock ficou apenas como dado legado em `shared/mock/data.ts`.
- Backend: o modulo `Partners` agora entrega API real para CRUD administrativo e subrecursos de parceiros.
- Existe CRUD generico em `/api/v1/organizations`, mas ele nao e suficiente para a tela de parceiros porque:
  - nao retorna agregados comerciais da tela;
  - o frontend `/partners` ainda nao tem telas dedicadas para detalhe/subrecursos do parceiro;
  - nao separa o contrato administrativo de parceiros do contrato de `organizations/current`;
  - o frontend espera campos como plano, receita, eventos ativos e equipe.
- A migracao final para permissoes com escopo foi aplicada:
  - visibilidade global usa `partners.view.any`;
  - gestao global usa `partners.manage.any`.

## Validacao adicional da stack atual

### Backend validado por testes ja existentes

- `MeTest` e `AccessMatrixTest` confirmam que:
  - o super admin mantem papel global mesmo com membership em organizacao;
  - o painel recebe `organization` atual, `accessible_modules`, feature flags e entitlements reais.
- `ClientCrudTest` confirma que:
  - partner-owner enxerga apenas clientes da propria organizacao;
  - super admin consegue listar clientes globalmente e filtrar por plano.
- `AnalyticsApiTest` confirma que:
  - partner users ficam automaticamente escopados para a organizacao atual;
  - super admin pode filtrar analytics por `organization_id`.
- `AuditTest` confirma que:
  - auditoria ja e real, paginada, filtravel, com redacao de campos sensiveis;
  - usuarios nao globais nao conseguem escopar logs para outra organizacao.
- `ListEventsTest` e `EventDetailAndLinksTest` confirmam que:
  - eventos continuam vinculados a `organization_id`;
  - detalhe de evento fora da organizacao do usuario e negado.
- `EventCommercialStatusTest`, `EntitlementResolverServiceTest` e `DashboardStatsTest` confirmam que:
  - o contexto comercial atual do evento ja existe e e recalculado;
  - o dashboard ja calcula ranking de parceiros e split de receita no backend.

### Painel atual (`app/web`)

- `/partners` consulta API real via `partnersService.list` e `queryKeys.partners.list(filters)`.
- `/partners` esta protegido por guard real de modulo/permissao.
- `/partners` cobre listagem e filtros administrativos na V1; telas dedicadas para detalhe, create/edit/suspend/staff/grants no web ficam como proxima camada de UI.
- `/clients` ja e real:
  - usa `/clients`;
  - so mostra filtro de organizacao para `isPlatformAdmin`;
  - usa `/organizations` para preencher opcoes de organizacao.
- `/analytics` ja e real:
  - so mostra seletor de organizacao para `isPlatformAdmin`;
  - usa `/organizations?type=partner` para buscar parceiros no filtro global;
  - parceiro comum segue vendo dados do proprio escopo porque o backend resolve `organization_id` automaticamente.
- `/audit` ja e real:
  - exibe o escopo retornado pela API (`global` ou nome da organizacao);
  - ainda nao oferece seletor visual de organizacao, embora o backend suporte `organization_id`.
- `/settings` ainda mistura dados reais e mock:
  - usa `meOrganization` para dados basicos;
  - equipe vem de `mockUsers`;
  - salvar configuracoes ainda mostra feedback `(mock)`.
- As rotas sensiveis de gestao agora ficam atras de `ModuleGuard` no `App.tsx`.

## Gaps confirmados e bloqueantes antes do CRUD de partners

- O leak do CRUD generico de `/api/v1/organizations` foi confirmado e corrigido nesta execucao:
  - foi adicionada `OrganizationPolicy`;
  - `OrganizationController` agora chama `authorize(...)` no CRUD administrativo;
  - `StoreOrganizationRequest` e `UpdateOrganizationRequest` deixaram de aceitar tudo;
  - o comportamento agora esta coberto por `tests/Feature/Organizations/OrganizationAdminScopeCharacterizationTest.php`.
- O painel tambem foi endurecido nesta execucao:
  - `App.tsx` agora protege as rotas sensiveis com `ModuleGuard`;
  - `ModuleGuard` passou a validar permissao e modulo;
  - o comportamento agora esta coberto por `apps/web/src/app/guards/ModuleGuard.test.tsx`.
- O gap de compatibilidade com permissoes antigas sem escopo foi fechado migrando backend e frontend para:
  - `partners.view.any`;
  - `partners.manage.any`.

## Decisoes fechadas nesta execucao

- `platform-admin` recebeu o mesmo alcance de `super-admin` na `PartnerPolicy`, mantendo o mesmo criterio ja usado em outros modulos administrativos.
- O feed `/partners/{partner}/activity` foi implementado sobre `activity_log`, escopando logs do proprio parceiro, de seus eventos, clientes e usuarios vinculados.
- Staff e grants foram implementados como actions proprias do modulo `Partners`, sem reutilizar diretamente `/organizations/current/team`.
- Os filtros adicionais entraram na V1: `subscription_status`, `has_clients` e `commercial_mode` em `/partners/{partner}/events`.
- O frontend `/partners` passou a usar API real para listagem, filtros, KPIs da pagina atual, estados de loading/erro/empty e permissao `partners.view.any`/`partners.manage.any`.
- A troca da equipe mockada em `/settings` continua fora desta fase; o backlog ficou concentrado no CRUD administrativo global de parceiros.

## Objetivo

Criar um CRUD administrativo de parceiros dentro do modulo `Partners`, sem duplicar o backbone de organizacoes.

O CRUD deve:

- usar `Organization` como entidade persistida;
- filtrar sempre `organizations.type = 'partner'`;
- expor uma visao rica para super admin;
- impedir que um parceiro veja outros parceiros;
- manter controllers finos;
- usar Actions para escrita;
- usar Queries para listagens complexas;
- usar Resources para resposta;
- usar Requests para validacao;
- registrar logs de auditoria relevantes;
- manter compatibilidade com eventos, clientes, equipe e billing existentes.

## Checklist de execucao

- [x] Endurecer o CRUD global de `organizations` com policy/autorizacao explicita para fechar o leak de escopo.
- [x] Proteger as rotas sensiveis do `app/web` com guard real de modulo/permissao em vez de depender so do menu lateral.
- [x] Alinhar permissao inicial de partners entre backend, access matrix, sidebar e module registry.
- [x] Migrar o modulo para a convencao final `partners.view.any` / `partners.manage.any`.
- [x] Criar `partner_profiles` para separar segmento comercial do `organizations.type`.
- [x] Criar `partner_stats` para listagem e filtros administrativos.
- [x] Implementar `GET /api/v1/partners` com query, resource e filtros reais.
- [x] Implementar `POST /api/v1/partners` com criacao de owner e log.
- [x] Implementar `GET /api/v1/partners/{partner}` com resumo comercial, eventos, clientes, staff e activity.
- [x] Implementar `PATCH /api/v1/partners/{partner}`.
- [x] Implementar `POST /api/v1/partners/{partner}/suspend`.
- [x] Implementar `DELETE /api/v1/partners/{partner}` apenas para parceiro vazio.
- [x] Implementar subrecursos `events`, `clients`, `staff`, `grants` e `activity`.
- [x] Integrar `apps/web/src/modules/partners` com API real e remover `mockPartners` da pagina.
- [x] Implementar filtros adicionais da V1: `subscription_status`, `has_clients` e `commercial_mode` em eventos do parceiro.
- [x] Registrar que a troca da equipe mockada em `settings` fica fora do escopo desta fase.

## Status atual do backend

Implementado nesta execucao em `apps/api/app/Modules/Partners`:

- migrations `partner_profiles` e `partner_stats`;
- `PartnerPolicy` com escopo administrativo global;
- `RebuildPartnerStatsAction` e `EnsurePartnerStatsProjectionAction`;
- `PartnerController` com:
  - `GET /api/v1/partners`
  - `POST /api/v1/partners`
  - `GET /api/v1/partners/{partner}`
  - `PATCH /api/v1/partners/{partner}`
  - `DELETE /api/v1/partners/{partner}`
  - `POST /api/v1/partners/{partner}/suspend`
  - `GET /api/v1/partners/{partner}/events`
  - `GET /api/v1/partners/{partner}/clients`
  - `GET /api/v1/partners/{partner}/staff`
  - `POST /api/v1/partners/{partner}/staff`
  - `GET /api/v1/partners/{partner}/grants`
  - `POST /api/v1/partners/{partner}/grants`
  - `GET /api/v1/partners/{partner}/activity`

Contratos automatizados validados nesta fase:

- `PartnerAdminCrudContractTest`: 23 testes verdes cobrindo listagem, filtros, `partner_profiles`, `partner_stats`, CRUD, policy, grants, staff e activity.
- regressao de escopo/admin: `OrganizationAdminScopeCharacterizationTest`, `AccessMatrixTest`, `ClientCrudTest`, `ListEventsTest`, `AuditTest`.
- regressao comercial: `EventCommercialStatusTest` e `EntitlementResolverServiceTest`.

Pendencias explicitas apos esta fase:

- criar telas/forms no web para create/edit/suspend/staff/grants, se a operacao administrativa precisar sair da API e entrar na UI agora;
- trocar a equipe mockada de `/settings`, caso essa tela passe a entrar no mesmo escopo administrativo.

## Decisoes apos revisar a stack atual

- Manter `Partner = Organization(type = partner)`.
- Implementar `/partners` como fachada administrativa global, nao como substituto de `/organizations/current`.
- Criar `partner_profiles` para segmento comercial e informacoes administrativas opcionais.
- Usar `POST /api/v1/partners/{partner}/suspend` para suspensao operacional. Nao usar `DELETE` para "talvez suspender".
- Reservar `DELETE /api/v1/partners/{partner}` para remocao real de parceiro sem historico operacional relevante.
- Usar uma projecao administrativa (`partner_stats`) para a listagem e filtros pesados.
- Manter o snapshot comercial atual do evento (`events.commercial_mode` e `events.current_entitlements_json`) como V1 do contexto comercial.
- Avaliar `event_commercial_context` como evolucao futura se os snapshots dentro de `events` ficarem insuficientes para auditoria, historico ou consultas cruzadas.
- Tratar escrita de staff e grants no modulo `Partners` como fachada administrativa que delega para actions dos modulos donos.

## Modelo de dados

### Tabelas existentes usadas

- `organizations`
  - fonte de verdade para o parceiro;
  - campos: `type`, `trade_name`, `legal_name`, `document_number`, `slug`, `email`, `billing_email`, `phone`, `logo_path`, cores, dominios, `status`.
- `organization_members`
  - staff e donos do parceiro;
  - campos: `organization_id`, `user_id`, `role_key`, `is_owner`, `status`.
- `clients`
  - clientes vinculados ao parceiro via `organization_id`.
- `events`
  - eventos vinculados ao parceiro via `organization_id`.
- `subscriptions`, `plans`
  - plano recorrente da organizacao.
- `billing_orders`, `invoices`, `payments`
  - receita e historico financeiro.
- `event_access_grants`
  - bonus, trials, manual overrides, compras e grants ligados aos eventos/organizacao.
- `events.commercial_mode` e `events.current_entitlements_json`
  - snapshot comercial atual do evento;
  - ja e recalculado por hooks de billing/grants na stack atual;
  - suficiente para a primeira versao do detalhe de parceiros e dos resumos por modo comercial.
- `activity_log`
  - auditoria operacional via Spatie Activitylog.

### Campos faltantes provaveis

O mock usa `type` para "Fotografo", "Agencia", "Cerimonialista". No banco, `organizations.type` e categoria estrutural (`partner`, `direct_customer`, `agency`, etc.). Nao se deve reaproveitar `organizations.type` para segmento comercial.

Opcao recomendada:

```text
partner_profiles
- id
- organization_id unique
- segment string nullable
- business_stage string nullable
- account_owner_user_id nullable
- notes text nullable
- tags_json jsonb nullable
- onboarded_at timestamp nullable
- created_at / updated_at
```

Uso:

- `organizations.type = partner` continua definindo que a conta e um parceiro.
- `partner_profiles.segment = ceremonialista | fotografo | agencia | buffet | espaco | outro` descreve o tipo comercial do parceiro.

Se a feature inicial precisar ser menor, `segment` pode ficar fora da primeira entrega e a tela usa apenas nome/status/plano/eventos/equipe/receita.

### Projecao administrativa

A listagem `/partners` nao deve recalcular todos os agregados em tempo real.

Criar:

```text
partner_stats
- organization_id primary/unique
- clients_count integer default 0
- events_count integer default 0
- active_events_count integer default 0
- team_size integer default 0
- active_bonus_grants_count integer default 0
- subscription_plan_code string nullable
- subscription_plan_name string nullable
- subscription_status string nullable
- subscription_billing_cycle string nullable
- subscription_revenue_cents integer default 0
- event_package_revenue_cents integer default 0
- total_revenue_cents integer default 0
- last_paid_invoice_at timestamp nullable
- refreshed_at timestamp nullable
- created_at / updated_at
```

Atualizacao da projecao:

- V1 aceitavel: `RebuildPartnerStatsAction` sincrono chamado nas actions administrativas e em pontos criticos.
- V2 recomendada: jobs em Redis disparados por eventos internos / observers de `Client`, `Event`, `OrganizationMember`, `Subscription`, `Invoice` e `EventAccessGrant`.
- A API deve expor `stats_refreshed_at` para facilitar diagnostico de staleness no admin.

### Contexto comercial do evento

A sugestao de `event_commercial_context` faz sentido como modelo futuro, mas a stack atual ja tem uma camada materializada:

- `events.commercial_mode`;
- `events.current_entitlements_json`;
- `EventCommercialStatusService`;
- `SyncEventEntitlementsAction`;
- hooks em `BillingServiceProvider` para `Subscription`, `EventPurchase` e `EventAccessGrant`.

Portanto:

- V1 de Partners deve ler o snapshot atual do evento;
- nao criar `event_commercial_context` na primeira entrega apenas para a pagina `/partners`;
- criar `event_commercial_context` depois se for necessario historico de fontes, consultas por cobertura comercial ou auditoria mais granular.

## Contrato de API

Todas as rotas abaixo ficam em `apps/api/app/Modules/Partners/routes/api.php`.

### Listar parceiros

```http
GET /api/v1/partners
```

Query params:

- `search`: busca por `trade_name`, `legal_name`, `slug`, `email`, `phone`, `document_number` e, se existir, `partner_profiles.segment`.
- `status`: `active`, `inactive`, `suspended`.
- `segment`: filtro por segmento comercial, se `partner_profiles` existir.
- `plan_code`: filtro pelo plano atual da assinatura da organizacao.
- `subscription_status`: `trialing`, `active`, `canceled`, etc.
- `has_active_events`: booleano.
- `has_clients`: booleano.
- `has_active_bonus_grants`: booleano para `event_access_grants.source_type in ('bonus', 'manual_override')` ativos no momento.
- `sort_by`: `created_at`, `name`, `active_events_count`, `clients_count`, `team_size`, `revenue_cents`.
- `sort_direction`: `asc` ou `desc`.
- `page`, `per_page`.

Resposta minima por item:

```json
{
  "id": 1,
  "uuid": "...",
  "type": "partner",
  "name": "Cerimonial Viva",
  "trade_name": "Cerimonial Viva",
  "legal_name": "Cerimonial Viva LTDA",
  "segment": "cerimonialista",
  "email": "contato@example.com",
  "phone": "11999990000",
  "status": "active",
  "logo_url": null,
  "clients_count": 12,
  "events_count": 31,
  "active_events_count": 4,
  "team_size": 3,
  "active_bonus_grants_count": 2,
  "stats_refreshed_at": "2026-04-06T00:00:00.000000Z",
  "current_subscription": {
    "plan_key": "pro-parceiro",
    "plan_name": "Pro Parceiro",
    "status": "active",
    "billing_cycle": "monthly"
  },
  "revenue": {
    "currency": "BRL",
    "subscription_cents": 9900,
    "event_package_cents": 19900,
    "total_cents": 29800
  },
  "created_at": "2026-04-06T00:00:00.000000Z",
  "updated_at": "2026-04-06T00:00:00.000000Z"
}
```

### Criar parceiro

```http
POST /api/v1/partners
```

Payload minimo:

```json
{
  "name": "Cerimonial Viva",
  "legal_name": "Cerimonial Viva LTDA",
  "document_number": "00.000.000/0001-00",
  "email": "contato@example.com",
  "billing_email": "financeiro@example.com",
  "phone": "11999990000",
  "timezone": "America/Sao_Paulo",
  "segment": "cerimonialista",
  "status": "active",
  "owner": {
    "name": "Maria Cerimonial",
    "email": "maria@example.com",
    "phone": "11999990001",
    "send_invite": true
  }
}
```

Comportamento esperado:

- cria `organizations.type = partner`;
- gera slug unico;
- cria `partner_profiles`, se esse modelo existir;
- cria ou vincula owner em `organization_members` com `role_key = partner-owner`;
- registra activity log com ator super admin;
- retorna `201`.

### Detalhar parceiro

```http
GET /api/v1/partners/{partner}
```

Deve retornar:

- dados da organizacao;
- perfil comercial;
- plano atual;
- staff resumido;
- contadores de clientes;
- contadores de eventos por status e modo comercial;
- grants ativos e proximos a expirar;
- receita agregada;
- ultimos logs relevantes.

### Atualizar parceiro

```http
PATCH /api/v1/partners/{partner}
```

Campos permitidos:

- `name`, `legal_name`, `document_number`, `email`, `billing_email`, `phone`, `timezone`;
- branding administrativo: `logo_path`, `primary_color`, `secondary_color`, `subdomain`, `custom_domain`;
- `segment`, `notes`, `tags`, se `partner_profiles` existir;
- `status`.

Comportamento:

- atualiza apenas organizacoes do tipo `partner`;
- registra diff no activity log;
- nao altera assinatura, grants ou eventos implicitamente.

### Suspender parceiro

```http
POST /api/v1/partners/{partner}/suspend
```

Payload opcional:

```json
{
  "reason": "Inadimplencia ou revisao manual",
  "notes": "Suspensao administrativa solicitada pelo super admin."
}
```

Comportamento:

- altera `organizations.status = suspended`;
- nao apaga clientes, eventos, grants, invoices ou members;
- registra `partner.suspended`;
- dispara recomputacao de `partner_stats`;
- retorna o parceiro atualizado.

### Remover parceiro vazio

```http
DELETE /api/v1/partners/{partner}
```

Uso permitido:

- somente quando o parceiro nao tem eventos, clientes, invoices, billing orders, grants ou membros operacionais alem do owner inicial;
- se houver historico operacional, retornar `409 Conflict` com orientacao para usar `/suspend`;
- se permitido, fazer soft delete e registrar `partner.deleted`.

### Subrecursos

```http
GET /api/v1/partners/{partner}/events
GET /api/v1/partners/{partner}/clients
GET /api/v1/partners/{partner}/staff
POST /api/v1/partners/{partner}/staff
GET /api/v1/partners/{partner}/grants
POST /api/v1/partners/{partner}/grants
GET /api/v1/partners/{partner}/activity
```

Regras:

- todos os subrecursos devem ser escopados por `organization_id = partner.id`;
- `events` deve aceitar os mesmos filtros basicos de eventos: `search`, `status`, `event_type`, `commercial_mode`, periodo e ordenacao;
- `clients` deve aceitar `search`, `type`, `has_events`, `sort_by`;
- `staff` deve retornar membros com usuario, `role_key`, `is_owner`, `status`, `joined_at`;
- `POST /staff` deve criar ou convidar staff via action propria, sem duplicar regras de `Organizations`;
- `grants` deve retornar bonus/manual overrides/event purchases/trials, com filtro por `source_type`, `status`, `event_id`;
- `POST /grants` deve criar bonus ou manual override via action propria, validando evento do parceiro;
- `activity` deve retornar logs do parceiro, eventos, clientes e grants relacionados, sem vazar logs de outras organizacoes.

## Autorizacao

### Permissoes recomendadas

Modelo alvo por capability + escopo:

- `partners.view.any`: listar e detalhar parceiros globais.
- `partners.manage.any`: criar, editar, suspender/remover e gerenciar staff do parceiro.
- `partners.billing.view.any`: ver valores financeiros detalhados.
- `partners.grants.manage.any`: criar/alterar bonus e manual overrides.
- `organization.view.self`: visualizar a propria organizacao via `/organizations/current`.
- `organization.manage.self`: editar a propria organizacao.
- `organization.team.manage.self`: gerenciar equipe da propria organizacao.

Decisao aplicada nesta fase:

- nao manter alias ativo no contrato novo de Partners;
- usar `partners.view.any` para leitura global;
- usar `partners.manage.any` para escrita global.

### Policy implementada

`App\Modules\Partners\Policies\PartnerPolicy`.

Regras base:

- `viewAny`: apenas `super-admin` e `platform-admin`; exige `partners.view.any` ou `partners.manage.any`.
- `view`: mesmas regras de `viewAny`, e o registro precisa ter `type = partner`.
- `create`: apenas admin global com `partners.manage.any`.
- `update`: apenas admin global com `partners.manage.any`.
- `suspend`: apenas admin global com `partners.manage.any`.
- `delete`: apenas admin global com `partners.manage.any`, e apenas quando nao houver historico operacional.
- `viewFinancials`: admin global e permissao financeira adequada.
- `viewActivity`: admin global com `audit.view` ou permissao dedicada.

O parceiro comum deve continuar usando:

- `GET /api/v1/organizations/current`;
- `PATCH /api/v1/organizations/current`;
- `GET /api/v1/organizations/current/team`.

Ele nao deve usar:

- `GET /api/v1/partners`;
- `GET /api/v1/partners/{id}` de qualquer parceiro;
- `GET /api/v1/organizations` global.

## Query e performance

`App\Modules\Partners\Queries\ListPartnersQuery` foi implementada.

Ela deve:

- comecar em `organizations`, joinando `partner_stats` e opcionalmente `partner_profiles`;
- filtrar sempre `organizations.type = 'partner'`;
- usar colunas da projecao para `clients_count`, `events_count`, `active_events_count`, `team_size`, grants, plano e receita;
- evitar subqueries pesadas por item na listagem;
- manter paginação por `per_page` maximo 100;
- usar operador portavel de like (`ilike` em PostgreSQL, `like` em outros drivers).

O detalhe `GET /partners/{partner}` pode montar uma visao rica com queries especificas e paginacao/limites internos para ultimos logs, staff e grants recentes.

## Logs e auditoria

Registrar activity log para:

- parceiro criado;
- perfil de parceiro atualizado;
- status alterado;
- parceiro suspenso ou removido;
- owner/staff convidado, ativado ou removido;
- bonus/grant criado, revogado ou expirado manualmente;
- alteracao relevante de plano/assinatura se feita pela area de parceiros.

Campos recomendados em `properties`:

- `organization_id`;
- `partner_id`;
- `changed_fields`;
- `before`;
- `after`;
- `actor_role`;
- `related_event_id`, quando aplicavel;
- `related_client_id`, quando aplicavel;
- `grant_id`, quando aplicavel.

## Frontend

Implementado na V1 da listagem:

- `apps/web/src/modules/partners/api.ts`
- `apps/web/src/modules/partners/types.ts`
- `apps/web/src/modules/partners/PartnersPage.tsx`
- `apps/web/src/modules/partners/PartnersPage.test.tsx`

Usar:

- `useQuery` com `queryKeys.partners.list(filters)`;
- filtros por busca, status, plano, eventos ativos, clientes e ordenacao;
- estados de loading, empty, erro e forbidden;
- permissao `partners.view.any` ou `partners.manage.any` para visualizar.

Pendente para UI administrativa completa:

- `PartnerFormDialog.tsx`;
- mutations para criar/editar/suspender/remover;
- telas ou paineis dedicados para staff, grants, clientes, eventos e activity do detalhe;
- acao de escrita condicionada por `partners.manage.any`.

## Plano de implementacao

1. Ajustar RBAC:
   - adicionar `partners.view.any` e `partners.manage.any` no seeder;
   - revisar se `platform-admin` participa ou nao do acesso global;
   - alinhar `AccessStateBuilderService` para usar `partners.view.any` para visibilidade e `partners.manage.any` para acoes.
2. Criar policy do dominio Partners e registrar no service provider.
3. Criar `ListPartnersRequest`, `StorePartnerRequest`, `UpdatePartnerRequest`.
4. Criar migration/model/action para `partner_profiles`.
5. Criar migration/model/action/job para `partner_stats`.
6. Criar `RebuildPartnerStatsAction` e, se possivel, job `RebuildPartnerStatsJob`.
7. Criar `ListPartnersQuery` baseada em `partner_stats`.
8. Criar `PartnerResource` e resources auxiliares para subscription/revenue/staff/grants.
9. Criar `CreatePartnerAction`, `UpdatePartnerAction`, `ChangePartnerStatusAction`, `SuspendPartnerAction`.
10. Criar facade actions `InvitePartnerStaffAction` e `CreatePartnerGrantAction`, delegando para os modulos donos.
11. Criar `PartnerController` e rotas REST/subrecursos.
12. Integrar frontend `/partners` com API real.
13. Criar UI de detalhe e forms administrativos, se a proxima fase exigir CRUD completo via painel.
14. Decidir e executar o ajuste da tela de `settings` para remover mocks de equipe, ou documentar explicitamente que isso fica fora desta entrega.
15. Atualizar `apps/web/README.md` quando a tela deixar de ser mockada. Concluido para a listagem de `/partners`.

## Fora da V1

- Criar `event_commercial_context` como tabela separada.
- Criar metricas profundas de midia/engajamento por parceiro.
- Criar projection de activity cache, exceto se a primeira versao do detalhe ficar lenta.

## Testes automatizados planejados

Arquivo criado:

- `apps/api/tests/Feature/Partners/PartnerAdminCrudContractTest.php`
- `apps/api/tests/Feature/Organizations/OrganizationAdminScopeCharacterizationTest.php`

Status:

- testes de contrato de `/api/v1/partners` estao ativos e verdes;
- `OrganizationAdminScopeCharacterizationTest` passou a funcionar como suite de endurecimento do CRUD global de `organizations`.

## Bateria executada nesta revisao

Comandos rodados:

- `php artisan test tests/Feature/Auth/MeTest.php tests/Feature/Auth/AccessMatrixTest.php`
- `php artisan test tests/Feature/Clients/ClientCrudTest.php tests/Feature/Analytics/AnalyticsApiTest.php`
- `php artisan test tests/Feature/Audit/AuditTest.php tests/Feature/Organizations/OrganizationTest.php`
- `php artisan test tests/Feature/Events/EventCommercialStatusTest.php tests/Unit/Billing/EntitlementResolverServiceTest.php tests/Feature/Dashboard/DashboardStatsTest.php`
- `php artisan test tests/Feature/Events/ListEventsTest.php tests/Feature/Events/EventDetailAndLinksTest.php`
- `php artisan test tests/Feature/Organizations/OrganizationAdminScopeCharacterizationTest.php`
- `php artisan test tests/Feature/Auth/MeTest.php tests/Feature/Auth/AccessMatrixTest.php tests/Feature/Organizations/OrganizationTest.php tests/Feature/Organizations/OrganizationAdminScopeCharacterizationTest.php`
- `php artisan test tests/Feature/Clients/ClientCrudTest.php tests/Feature/Audit/AuditTest.php`
- `npx vitest run src/app/guards/ModuleGuard.test.tsx`
- `php artisan test tests/Feature/Auth/AccessMatrixTest.php --filter=partners`
- `npx vitest run src/app/layouts/AppSidebar.test.tsx`
- `npm run type-check`
- `php artisan test tests/Feature/Auth/MeTest.php tests/Feature/Auth/AccessMatrixTest.php`
- `npx vitest run src/app/guards/ModuleGuard.test.tsx src/app/layouts/AppSidebar.test.tsx`
- `php artisan test tests/Feature/Auth/AccessMatrixTest.php tests/Feature/Partners/PartnerAdminCrudContractTest.php`
- `php artisan test tests/Feature/Events/ListEventsTest.php tests/Feature/Billing/AdminQuickEventTest.php`
- `npx vitest run src/modules/partners/PartnersPage.test.tsx src/app/layouts/AppSidebar.test.tsx src/app/guards/ModuleGuard.test.tsx`
- `npm run type-check`

Resumo:

- auth/access matrix: 17 testes passaram;
- clients/analytics: 13 testes passaram;
- audit/organizations(current): 16 testes passaram;
- commercial status/billing resolver/dashboard: 17 testes passaram;
- events list/detail: 7 testes passaram;
- hardening de `organizations`: 5 testes passaram;
- regressao curta de auth/organizations: 27 testes passaram;
- regressao curta de clients/audit: 21 testes passaram;
- web route guard: 3 testes passaram + type-check ok.
- access matrix `partners.view.any`: 1 teste passou.
- sidebar `partners.view.any`: 1 teste passou.
- regressao final de auth/access matrix: 18 testes passaram.
- regressao final dos guards do web: 4 testes passaram.
- regressao final de auth + partners: 28 testes passaram.
- regressao final de events + admin quick event: 10 testes passaram.
- regressao final web partners/sidebar/guard: 8 testes passaram.
- type-check web: ok.

Cobertura esperada:

- super admin lista parceiros e nao ve `direct_customer`;
- partner-owner nao lista nem detalha outros parceiros;
- filtros por busca, status, plano, assinatura, clientes, bonus e eventos ativos;
- criacao de parceiro com owner;
- atualizacao de dados e perfil;
- suspensao via `POST /partners/{partner}/suspend`;
- delete apenas para parceiro vazio, com `409 Conflict` quando houver historico;
- detalhe com eventos, clientes, staff, grants, plano e receita;
- subrecursos de eventos, clientes e staff escopados por organizacao;
- filtro `commercial_mode` em eventos do parceiro;
- activity log de create/update/suspend;
- validacao de payload;
- proibicao de mutation por usuario sem permissao global.

Testes ja existentes que validam o contexto comercial atual:

- `tests/Feature/Events/EventCommercialStatusTest.php`;
- `tests/Unit/Billing/EntitlementResolverServiceTest.php`.
