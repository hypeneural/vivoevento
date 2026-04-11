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

- Frontend: `apps/web/src/modules/partners/PartnersPage.tsx` agora consome a API real de parceiros; o mock ficou apenas como dado legado em `shared/mock/data.ts`.
- Backend: o modulo `Partners` agora entrega API real para CRUD administrativo e subrecursos de parceiros.
- Existe CRUD generico em `/api/v1/organizations`, mas ele nao e suficiente para a tela de parceiros porque:
  - nao retorna agregados comerciais da tela;
  - nao separa o contrato administrativo de parceiros do contrato de `organizations/current`;
  - o frontend espera campos como plano, receita, eventos ativos e equipe.
- A migracao final para permissoes com escopo foi aplicada:
  - visibilidade global usa `partners.view.any`;
  - gestao global usa `partners.manage.any`.
- Compatibilidade operacional: `super-admin` e `platform-admin` continuam autorizados pela role global mesmo quando o banco local ainda nao foi resemeado com as novas permissoes `.any`. Isso corrige o caso real de `GET /api/v1/partners` retornando `403` para super admin em ambientes ja existentes.
- Robustez operacional adicional: `GET /api/v1/partners` e `GET /api/v1/partners/{partner}` agora toleram ambientes locais onde `partner_stats` e `partner_profiles` ainda nao foram migradas, evitando `500` durante bootstrap ou bases desatualizadas.

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
- `/partners` cobre listagem, filtros administrativos, detalhe, create/edit, suspensao, remocao de parceiro vazio, staff, grants e activity via API real.
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
- `/settings` agora esta funcional no fluxo principal:
  - usa `meOrganization` para hidratar os formularios;
  - salva organizacao via `PATCH /organizations/current`;
  - salva branding via `PATCH /organizations/current/branding`;
  - lista, convida e remove equipe via `/organizations/current/team`;
  - exibe `Permissoes` e `Integracoes` apenas para `super-admin`.
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
- A UI administrativa completa da V1 foi criada no web:
  - detalhe em painel lateral;
  - create/edit em dialog;
  - suspend em dialog proprio;
  - delete apenas como "remover vazio";
  - staff e grants como dialogs de operacao administrativa;
  - eventos, clientes, staff, grants e activity no detalhe.
- `/settings` ganhou persistencia real para organizacao e branding.
- `/settings` ganhou upload real de logo/ativos de branding via `POST /organizations/current/branding/logo`.
- `/settings` ganhou fluxo real de equipe com convites pendentes, aceite posterior e remocao segura via `organizations/current/team` e `organizations/current/team/invitations`.
- `/settings` ganhou persistencia real da aba `Preferencias` via `PATCH /auth/me`.
- As abas de `/settings` voltaram a alternar visualmente o conteudo ativo no frontend.
- `Permissoes` e `Integracoes` em `/settings` ficaram restritas a `super-admin`.
- O escopo de integracoes/instancias foi revalidado por testes de WhatsApp para garantir que usuarios de organizacao nao enxergam dados globais ou a instancia padrao do sistema.
- O `403` real de `GET /organizations/current/team` para super admin legado foi fechado com fallback de acesso por role global quando a organizacao atual existe.
- Os filtros de `/partners` agora ficam recolhidos por padrao e so aparecem sob demanda em `Filtros e ordenacao`.
- A terminologia visivel do CRUD administrativo de parceiros foi alinhada para portugues nas acoes de equipe, concessoes e estados comerciais mais sensiveis.

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
- [x] Criar UI administrativa completa de create/edit/suspend/remove-empty/staff/grants/detail/activity em `apps/web`.
- [x] Corrigir compatibilidade de autorizacao para super admin legado que ainda nao recebeu `partners.view.any`/`partners.manage.any` por reseed.
- [x] Implementar rebuild opcional assincrono de `partner_stats` com job unico e hooks dos agregados.
- [x] Evoluir o detalhe web de parceiros com filtros e paginacao por aba para eventos, clientes e grants.
- [x] Trocar a equipe mockada de `settings` por leitura real via `/organizations/current/team`.
- [x] Implementar persistencia real dos saves de organizacao e branding em `settings`.
- [x] Implementar upload real de logo/ativos de branding em `settings`.
- [x] Implementar fluxo real de equipe em `settings`, incluindo convite pendente, reenvio/revogacao, aceite posterior e remocao segura.
- [x] Implementar persistencia real de `Preferencias` em `settings`.
- [x] Garantir fallback de `/partners` quando `partner_stats` e `partner_profiles` ainda nao existirem no banco local.
- [x] Corrigir a alternancia visual das abas em `/settings`.
- [x] Restringir `Permissoes` e `Integracoes` em `settings` para `super-admin`.
- [x] Validar o escopo de integracoes/instancias para impedir vazamento da instancia padrao do sistema para usuarios de organizacao.
- [x] Recolher filtros e ordenacao de `/partners` atras de CTA explicita.
- [x] Traduzir labels criticos do CRUD administrativo de parceiros para portugues.

## Status atual do backend

Implementado nesta execucao em `apps/api/app/Modules/Partners`:

- migrations `partner_profiles` e `partner_stats`;
- `PartnerPolicy` com escopo administrativo global;
- `RebuildPartnerStatsAction` e `EnsurePartnerStatsProjectionAction`;
- `RebuildPartnerStatsJob` com dispatch opcional por config, fila `analytics` e deduplicacao por parceiro;
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

- `PartnerAdminCrudContractTest`: 26 testes verdes cobrindo listagem, fallback sem tabelas de projecao, filtros, `partner_profiles`, `partner_stats`, CRUD, policy, compatibilidade de super admin legado, grants, staff e activity.
- `PartnerStatsProjectionQueueTest`: 3 testes verdes cobrindo dispatch assincrono, no-op com feature flag desligada e rebuild efetivo da projecao.
- regressao de escopo/admin: `OrganizationAdminScopeCharacterizationTest`, `AccessMatrixTest`, `ClientCrudTest`, `ListEventsTest`, `AuditTest`.
- regressao comercial: `EventCommercialStatusTest` e `EntitlementResolverServiceTest`.

Status do frontend nesta fase:

- UI de listagem/filtros/KPIs usando API real;
- painel lateral de detalhe com resumo, eventos, clientes, staff, grants e activity;
- detalhe com filtros e paginacao por aba para eventos, clientes, grants, staff e logs;
- filtros da listagem recolhidos por padrao, com abertura por CTA dedicada;
- dialogs para criar, editar, suspender, convidar staff e criar grant;
- acao de remover parceiro vazio protegida por confirmacao;
- `/settings` lendo equipe real via `/organizations/current/team`, sem `mockUsers`;
- `/settings` com persistencia real de organizacao e branding via API;
- `/settings` com upload real de logo/ativos de branding via API;
- `/settings` com fluxo real de equipe, incluindo convite pendente, link manual, reenvio, revogacao e remocao de membro nao-owner;
- `/settings` com troca real de abas visiveis, usando apenas o conteudo ativo;
- `/settings` com persistencia real de `Preferencias` via `/auth/me`;
- `/settings` mostrando `Permissoes` e `Integracoes` apenas para `super-admin`;
- `/settings` aceitando super admin legado em equipe/branding/settings mesmo antes de reseed granular, desde que exista organizacao atual;
- labels visiveis do fluxo administrativo de parceiros revisadas para portugues;
- testes de tela cobrindo listagem real, filtros, detalhe, create, edit, suspend, staff, grants, paginacao/filtros por aba, delete de parceiro vazio e CRUD funcional de `settings`.

Pendencias explicitas apos esta fase:

- habilitar `PARTNER_STATS_ASYNC_UPDATES=true` apenas nos ambientes com worker/monitoramento da fila `analytics`;
- revisar lazy loading dos subrecursos do detalhe se o custo de consultas paralelas por parceiro crescer;
- evoluir branding para galeria de ativos adicionais se o produto passar a exigir favicon, watermark ou variantes por canal.

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

Implementado na V1 administrativa:

- `apps/web/src/modules/partners/api.ts`
- `apps/web/src/modules/partners/types.ts`
- `apps/web/src/modules/partners/PartnersPage.tsx`
- `apps/web/src/modules/partners/PartnersPage.test.tsx`
- `apps/web/src/modules/partners/components/PartnerDetailSheet.tsx`
- `apps/web/src/modules/partners/components/PartnerFormDialog.tsx`
- `apps/web/src/modules/partners/components/PartnerSuspendDialog.tsx`
- `apps/web/src/modules/partners/components/PartnerStaffDialog.tsx`
- `apps/web/src/modules/partners/components/PartnerGrantDialog.tsx`

Usar:

- `useQuery` com `queryKeys.partners.list(filters)`;
- filtros por busca, status, plano, eventos ativos, clientes e ordenacao;
- estados de loading, empty, erro e forbidden;
- permissao `partners.view.any` ou `partners.manage.any` para visualizar.
- `useMutation` para criar, editar, suspender, remover parceiro vazio, convidar staff e criar grants;
- acoes de escrita condicionadas por `partners.manage.any`;
- detalhe administrativo em painel lateral com resumo, eventos, clientes, staff, grants e logs.

Refinamentos futuros de UI:

- revisar lazy loading por aba se o custo de carregar todos os subrecursos em paralelo crescer;
- ampliar filtros internos do detalhe se surgirem novos recortes operacionais alem dos atuais;
- evoluir o branding de `/settings` para ativos adicionais alem do logo, caso o produto exija.
- expandir `Preferencias` se surgirem novas configuracoes persistidas por usuario ou por organizacao.

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
13. Criar UI de detalhe e forms administrativos para CRUD completo via painel.
14. Fechar `/settings` com leitura e escrita real de `organizations/current`, CRUD de equipe e visibilidade estrita de abas administrativas para `super-admin`.
15. Atualizar `apps/web/README.md` quando a tela deixar de ser mockada. Concluido para CRUD administrativo de `/partners`.

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
- `php artisan test tests/Feature/Partners/PartnerAdminCrudContractTest.php --filter="legacy global admin|allows a super admin to list"`
- `npx vitest run src/modules/partners/PartnersPage.test.tsx --testNamePattern="updates and suspends|adds staff"`
- `npx vitest run src/modules/partners/PartnersPage.test.tsx --testNamePattern="creates a grant|confirms removing"`
- `npx vitest run src/modules/partners/PartnersPage.test.tsx src/app/layouts/AppSidebar.test.tsx src/app/guards/ModuleGuard.test.tsx`
- `npm run type-check`
- `php artisan test tests/Feature/Partners/PartnerAdminCrudContractTest.php tests/Feature/Auth/AccessMatrixTest.php`

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
- regressao final de auth + partners: 29 testes passaram.
- regressao final de events + admin quick event: 10 testes passaram.
- regressao final web partners/sidebar/guard: 8 testes passaram.
- regressao focada do 403 de super admin legado: 2 testes passaram.
- regressao focada de edit/suspend/staff no web: 2 testes passaram.
- regressao focada de grant/delete no web: 2 testes passaram.
- regressao final web partners/sidebar/guard apos UI CRUD: 14 testes passaram.
- regressao final auth + partners apos compatibilidade de super admin legado: 29 testes passaram.
- type-check web: ok.

Ultima validacao completa desta fase em `2026-04-07 15:11:03 -03:00`:

- `php artisan test tests/Feature/Organizations/OrganizationTest.php tests/Feature/Organizations/OrganizationAdminScopeCharacterizationTest.php tests/Feature/Auth/MeTest.php tests/Feature/Clients/ClientCrudTest.php tests/Feature/Audit/AuditTest.php`
  - 44 testes passaram, 302 assertions.
- `php artisan test tests/Feature/Partners/PartnerStatsProjectionQueueTest.php tests/Feature/Partners/PartnerAdminCrudContractTest.php tests/Feature/Auth/AccessMatrixTest.php tests/Feature/Events/ListEventsTest.php tests/Feature/Billing/AdminQuickEventTest.php`
  - 42 testes passaram, 436 assertions.
- `npx vitest run src/modules/partners/PartnersPage.test.tsx src/modules/settings/SettingsPage.test.tsx src/app/layouts/AppSidebar.test.tsx src/app/guards/ModuleGuard.test.tsx`
  - 19 testes passaram.
- `npm run type-check`
  - ok.

Validacao complementar de acabamento em `2026-04-07 16:02:42 -03:00`:

- `php artisan test tests/Feature/Partners/PartnerStatsProjectionQueueTest.php tests/Feature/Partners/PartnerAdminCrudContractTest.php tests/Feature/Auth/AccessMatrixTest.php tests/Feature/Organizations/OrganizationAdminScopeCharacterizationTest.php`
  - 39 testes passaram, 332 assertions.
- `php artisan test tests/Feature/Clients/ClientCrudTest.php tests/Feature/Audit/AuditTest.php tests/Feature/Events/ListEventsTest.php tests/Feature/Billing/AdminQuickEventTest.php`
  - 31 testes passaram, 299 assertions.
- `npx vitest run src/modules/partners/PartnersPage.test.tsx src/modules/settings/SettingsPage.test.tsx src/app/layouts/AppSidebar.test.tsx src/app/guards/ModuleGuard.test.tsx`
  - 24 testes passaram.
- `npm run type-check`
  - ok.

O que essa ultima bateria confirmou:

- `/partners` nao quebra com `500` em bases sem `partner_stats` ou `partner_profiles`;
- `/settings` alterna corretamente entre `Perfil`, `Organizacao`, `Branding`, `Equipe`, `Permissoes`, `Integracoes` e `Preferencias`;
- `/settings` persiste organizacao e branding por API real;
- `/settings` emite convites pendentes, reenvia, revoga e remove equipe por API real, preservando o owner e sem provisionar acesso imediato;
- `Permissoes` e `Integracoes` em `/settings` aparecem apenas para `super-admin`;
- instancias de WhatsApp continuam escopadas para a organizacao atual e nao aceitam vazamento por `organization_id`;
- o painel de parceiros manteve guardas de permissao e escopo administrativos;
- os filtros da listagem de parceiros ficaram recolhidos por padrao;
- labels como equipe, membro, concessao e periodo de teste ficaram padronizadas em portugues.

Validacao final desta etapa em `2026-04-07 17:01:11 -03:00`:

- `php artisan test tests/Feature/Auth/MeTest.php tests/Feature/Auth/AccessMatrixTest.php tests/Feature/Organizations/OrganizationTest.php tests/Feature/Organizations/OrganizationAdminScopeCharacterizationTest.php tests/Feature/WhatsApp/WhatsAppInstanceManagementTest.php tests/Feature/Partners/PartnerAdminCrudContractTest.php tests/Feature/Partners/PartnerStatsProjectionQueueTest.php`
  - 68 testes passaram, 563 assertions.
- `php artisan test tests/Feature/Clients/ClientCrudTest.php tests/Feature/Audit/AuditTest.php tests/Feature/Events/ListEventsTest.php tests/Feature/Billing/AdminQuickEventTest.php`
  - 31 testes passaram, 299 assertions.
- `npx vitest run src/modules/settings/SettingsPage.test.tsx src/modules/partners/PartnersPage.test.tsx src/app/layouts/AppSidebar.test.tsx src/app/guards/ModuleGuard.test.tsx`
  - 28 testes passaram.
- `npm run type-check`
  - ok.

Validacao pontual apos endurecimento final de RBAC e `/settings` em `2026-04-07 17:03:45 -03:00`:

- `php artisan test tests/Feature/Auth/MeTest.php tests/Feature/Organizations/OrganizationTest.php tests/Feature/WhatsApp/WhatsAppInstanceManagementTest.php`
  - 30 testes passaram, 245 assertions.
- `npx vitest run src/modules/settings/SettingsPage.test.tsx`
  - 9 testes passaram.
- `npm run type-check`
  - ok.

Validacao complementar de persistencia real em `/settings` em `2026-04-07 18:01:31 -03:00`:

- `php artisan test tests/Feature/Auth/MeTest.php tests/Feature/Organizations/OrganizationTest.php tests/Feature/WhatsApp/WhatsAppInstanceManagementTest.php tests/Feature/Partners/PartnerAdminCrudContractTest.php tests/Feature/Partners/PartnerStatsProjectionQueueTest.php`
  - 61 testes passaram, 542 assertions.
- `npx vitest run src/modules/settings/SettingsPage.test.tsx src/modules/partners/PartnersPage.test.tsx src/app/layouts/AppSidebar.test.tsx src/app/guards/ModuleGuard.test.tsx`
  - 30 testes passaram.
- `npm run type-check`
  - ok.

O que essa bateria adicional confirmou:

- `/settings` faz upload real do logo da organizacao por API e atualiza a sessao com o ativo salvo;
- `/settings` persiste `Preferencias` do usuario autenticado em `/auth/me` sem sobrescrever outras preferencias existentes;
- `GET /organizations/current/team` nao retorna mais `403` para super admin legado com organizacao atual;
- o endurecimento de escopo de integracoes/instancias de WhatsApp segue preservado;
- o CRUD administrativo de parceiros permaneceu verde apos os ajustes em `settings`.

Validacao ampliada de regressao em `2026-04-07 18:04:53 -03:00`:

- `php artisan test tests/Feature/Clients/ClientCrudTest.php tests/Feature/Audit/AuditTest.php tests/Feature/Events/ListEventsTest.php tests/Feature/Billing/AdminQuickEventTest.php tests/Feature/Auth/AccessMatrixTest.php tests/Feature/Organizations/OrganizationAdminScopeCharacterizationTest.php`
  - 41 testes passaram, 361 assertions.
- `php artisan route:list --path=organizations/current`
  - 7 rotas validadas para `current`, `branding`, `branding/logo` e `team`.

O que essa regressao ampliada confirmou:

- clientes, auditoria, listagem de eventos, billing administrativo rapido e matriz de acesso permaneceram estaveis;
- o endurecimento do CRUD global de `organizations` continuou preservado;
- a superficie real de `/organizations/current` esta completa para leitura, escrita, branding/logo e equipe.

Validacao complementar da migracao de `/settings > Equipe` em `2026-04-10 22:40:00 -03:00`:

- `php artisan test tests/Feature/Organizations/OrganizationTeamInvitationContractTest.php tests/Feature/Organizations/OrganizationTeamInvitationCharacterizationTest.php tests/Feature/Organizations/OrganizationTest.php tests/Feature/EventTeam/EventTeamInvitationContractTest.php`
  - 33 testes passaram, 303 assertions.
- `npx vitest run src/modules/settings/SettingsPage.test.tsx src/modules/settings/SettingsTeamInvitationFlow.contract.test.tsx src/modules/team-invitations/PublicOrganizationInvitationPage.test.tsx src/modules/event-team/EventAccessPage.test.tsx src/modules/event-invitations/PublicEventInvitationPage.test.tsx src/modules/events/EventDetailPage.test.tsx src/app/layouts/AppSidebar.test.tsx`
  - 29 testes passaram.
- `npm run type-check`
  - ok.

O que esta bateria adicional confirmou:

- `/settings > Equipe` nao cria mais membership ativo no momento do convite;
- a organizacao agora acompanha membros ativos e convites pendentes em secoes separadas;
- o fluxo generico nao expõe mais `owner` no formulario de convite;
- o link manual, o reenvio por WhatsApp e a revogacao ficaram cobertos por teste no frontend;
- o aceite publico de convite organizacional ficou disponivel em `/convites/equipe/:token` sem criar organizacao nova para o usuario convidado;
- a semantica organization-scoped ficou alinhada ao slice event-scoped ja entregue em `/events/:eventId/access`.

Atualizacao de dependencia compartilhada em `2026-04-10 14:32:00 -03:00`:

- [x] a base de sessao multi-workspace para acessos event-scoped ja existe em `/auth/me`, com `active_context` e `workspaces.event_accesses`;
- [x] o frontend ja possui entrada segura `/my-events` para usuarios convidados por evento;
- [x] o modulo `EventTeam` agora bloqueia mutacao fora do escopo do evento e aceita presets de acesso;
- [x] convites pendentes por evento ja sao persistidos com reutilizacao de `existing_user_id` quando o usuario da plataforma ja existir;
- [x] o aceite publico do convite por evento ja funciona para usuario novo e para usuario existente autenticado;
- [x] a rota publica do web `/convites/eventos/:token` ja existe para a jornada do convidado;
- [x] reenvio/revogacao do convite por evento ja existem com rotacao de token, bloqueio imediato do link revogado e activity log;
- [x] o envio do convite por WhatsApp ja usa a instancia do evento quando elegivel, com fallback seguro para a organizacao e sem usar instancia global do sistema;
- [x] a UI administrativa `/events/:eventId/access` ja existe para equipe ativa, convites pendentes, reenvio, revogacao e remocao de acesso, entao a delegacao partner -> DJ/noivos deixou de ser parcial no frontend administrativo.

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

## Atualizacao complementar de `/settings`, ownership e branding em `2026-04-10 23:07:23 -03:00`

Esta rodada fechou o backlog que havia ficado depois da migracao de `/settings > Equipe` para convites pendentes.

Entregue:

- [x] corrigido o `500` local em `GET /api/v1/organizations/current/team/invitations` aplicando a migration pendente `organization_member_invitations`.
- [x] criado fluxo dedicado de transferencia de ownership por `POST /api/v1/organizations/current/team/ownership-transfer`.
- [x] `partner-owner` continua fora do convite comum de equipe.
- [x] `/settings > Equipe` mostra `Tornar titular` com modal de confirmacao para membros ativos nao-owner.
- [x] branding expandido ganhou upload real de `cover`, `logo_dark`, `favicon` e `watermark` por `POST /api/v1/organizations/current/branding/assets`.
- [x] dominio proprio e ativos premium passaram a respeitar entitlements vindos do plano ativo.
- [x] `/auth/me` passou a carregar os novos campos de branding da organizacao e os novos entitlements.
- [x] eventos passaram a expor `inherit_branding` e `effective_branding`, com fallback da organizacao quando o evento nao tem branding proprio.
- [x] recursos publicos principais passaram a usar branding efetivo quando aplicavel.

Validacao executada:

- `php artisan test tests/Feature/Organizations/OrganizationTest.php tests/Feature/Organizations/OrganizationOwnershipTransferTest.php tests/Feature/Organizations/OrganizationBrandingEntitlementTest.php tests/Feature/Events/EventBrandingInheritanceTest.php tests/Feature/Events/CreateEventTest.php tests/Feature/Events/EventBrandingUploadTest.php tests/Feature/Auth/MeTest.php tests/Feature/Auth/AccessMatrixTest.php`
  - `59 passed`, `469 assertions`
- `npx vitest run src/modules/settings/SettingsPage.test.tsx src/modules/settings/SettingsTeamInvitationFlow.contract.test.tsx src/modules/team-invitations/PublicOrganizationInvitationPage.test.tsx`
  - `23 passed`
- `npm run type-check`
  - `ok`

Pendencias fora desta V1:

- UI explicita no editor de evento para ligar/desligar `inherit_branding`.
- preview visual do `effective_branding` no detalhe/editor do evento.
- fluxo operacional completo de DNS/SSL para `custom_domain`.
- CTA comercial de upgrade para planos sem branding premium.

## Atualizacao visual do branding em eventos em `2026-04-10 23:55:00 -03:00`

Fechamento desta dependência de UX:

- [x] o editor de evento agora expõe `Herdar branding da organizacao` de forma explicita e legível para operador nao tecnico.
- [x] o preview do editor passou a refletir o branding efetivo, nao apenas os campos crus do evento.
- [x] o detalhe do evento passou a mostrar `Branding aplicado`, com capa/logo/cores efetivas e explicacao de origem.
- [x] a semantica de heranca ficou coerente com os entitlements organizacionais ja entregues em `/settings`.

Validacao desta rodada:

- `php artisan test tests/Feature/Events/EventBrandingInheritanceTest.php tests/Feature/Events/CreateEventTest.php`
  - `18 passed`, `160 assertions`
- `npx vitest run src/modules/events/branding.test.ts src/modules/events/EventDetailPage.test.tsx`
  - `6 passed`
- `npm run type-check`
  - `ok`

Pendencias restantes fora desta rodada:

- CTA comercial de upgrade para entitlements de branding premium.
- operacao completa de `custom_domain` com DNS/SSL.
