# Billing And Subscriptions Execution Plan

## Objetivo

Este documento transforma a discovery em ordem real de execucao.

Referencia primaria:

- `docs/architecture/billing-subscriptions-discovery.md`

Este plano existe para responder 4 perguntas de execucao:

1. o que ja temos hoje e pode ser reaproveitado;
2. o que precisa mudar primeiro sem quebrar o que ja existe;
3. em que ordem as tarefas devem entrar;
4. qual e a primeira entrega recomendada para gerar melhoria real sem abrir um refactor infinito.

## Como usar este plano

Regra simples:

- a discovery continua sendo a fonte de diagnostico e direcao;
- este arquivo vira a fonte de backlog, sequenciamento e controle de contexto.

Cada task abaixo aponta para:

- objetivo;
- referencia na discovery;
- estado atual do codigo;
- subtasks;
- dependencias;
- criterios de aceite;
- arquivos e modulos mais provaveis de impacto.

## Estado Atual Reaproveitavel

Antes de executar, estes pontos devem ser tratados como fundacao pronta:

- `organizations`, `organization_members`, `clients` e `events` ja formam o backbone de conta e operacao;
- `subscriptions` ja modela assinatura recorrente na organizacao;
- `event_purchases` ja existe e ja impacta KPIs e historico;
- `purchased_plan_snapshot_json` ja existe em `events`;
- `commercial_mode` e `current_entitlements_json` ja existem em `events`;
- onboarding OTP por WhatsApp ja existe em `Auth`;
- `event_access_grants` ja existe como motor de ativacao do evento;
- `EntitlementResolverService` e `OrganizationEntitlementResolverService` ja existem;
- `MeResource` ja injeta `access.entitlements` no frontend;
- `event_packages` ja existe como catalogo avulso separado;
- `/plans` ja existe como area administrativa e agora consome contratos reais de catalogo, assinatura atual, invoices, checkout e cancelamento da assinatura.

Arquivos-base que precisam ser sempre lembrados:

- `apps/api/app/Modules/Auth/Actions/RegisterWithWhatsAppOtpAction.php`
- `apps/api/app/Modules/Auth/Http/Resources/MeResource.php`
- `apps/api/app/Modules/Billing/Http/Controllers/SubscriptionController.php`
- `apps/api/app/Modules/Billing/Models/EventAccessGrant.php`
- `apps/api/app/Modules/Billing/Models/EventPackage.php`
- `apps/api/app/Modules/Billing/Models/EventPurchase.php`
- `apps/api/app/Modules/Billing/Models/Subscription.php`
- `apps/api/app/Modules/Billing/Services/EntitlementResolverService.php`
- `apps/api/app/Modules/Events/Models/Event.php`
- `apps/api/app/Modules/Organizations/Enums/OrganizationType.php`
- `apps/web/src/modules/auth/services/auth.service.ts`
- `apps/web/src/modules/auth/LoginPage.tsx`
- `apps/web/src/modules/plans/PlansPage.tsx`

## Regra De Sequenciamento

Executar na ordem abaixo:

1. ajustar fundacao sem ruptura;
2. criar motor de ativacao comercial do evento;
3. separar catalogo avulso do catalogo recorrente;
4. abrir jornadas reais no produto;
5. endurecer financeiro e gateway;
6. limpar acoplamentos antigos.

Motivo:

- se tentar fazer gateway antes de grants e entitlements, a cobranca nasce sem dominio claro;
- se tentar fazer UX final antes de `commercial_mode` e `event_access_grants`, o frontend vai depender de mocks ou heuristicas;
- se tentar matar `event_purchases` cedo demais, quebra dashboard e historico.

## Milestones De Execucao

## M0 - Decisao de nomenclatura e contrato de negocio

Objetivo:

- travar nomes, tipos e regras antes de abrir migrations.

Relaciona-se com discovery:

- `Perguntas Em Aberto`
- `Direcao Recomendada`
- `Plano De Acao Recomendado > Fase 0`

### TASK M0-T1 - Fechar vocabulario oficial do dominio

Subtasks:

1. confirmar se `direct_customer` entra no enum de organizacao;
2. confirmar se `host`, `agency` e `brand` ficam como tipos comerciais ou apenas taxonomia interna;
3. confirmar nomenclatura oficial de:
   - `trial`
   - `bonus`
   - `manual_override`
   - `subscription_covered`
   - `single_purchase`
4. confirmar se `event_purchases` sera mantido como tabela legada de compra avulsa ou renomeado no medio prazo.

Saida obrigatoria:

- glosario curto aprovado no proprio doc ou em adendo de produto.

Criterio de aceite:

- equipe consegue responder sem ambiguidade o que e assinatura, pacote de evento e grant.

Dependencias:

- nenhuma.

## M1 - Fundacao sem ruptura

Objetivo:

- suportar parceiro e cliente direto no mesmo backbone;
- parar de forcar toda jornada a terminar em `/plans`;
- preparar o evento para carregar estado comercial proprio.

Relaciona-se com discovery:

- `O Que O Codigo Ja Tem E Vale Reaproveitar`
- `Gaps Criticos > 6, 7, 8`
- `Plano De Acao Recomendado > Fase 1`

### TASK M1-T1 - Expandir o tipo de organizacao

Estado atual:

- `OrganizationType` hoje contem `partner`, `host`, `agency`, `brand`, `internal`.

Subtasks:

1. adicionar `direct_customer` em `apps/api/app/Modules/Organizations/Enums/OrganizationType.php`;
2. revisar requests e controllers que validam `OrganizationType`;
3. revisar factories e testes de organizacao;
4. decidir se `host` continua ativo ou vira alias de transicao.

Arquivos provaveis:

- `apps/api/app/Modules/Organizations/Enums/OrganizationType.php`
- `apps/api/app/Modules/Organizations/Http/Requests/StoreOrganizationRequest.php`
- `apps/api/app/Modules/Organizations/Http/Controllers/OrganizationController.php`
- `apps/api/database/factories/OrganizationFactory.php`
- testes de enums e organizacao

Criterio de aceite:

- API aceita e persiste `direct_customer` sem quebrar tipos existentes.

Dependencias:

- M0-T1.

### TASK M1-T2 - Tirar o onboarding de um caminho obrigatorio `partner -> /plans`

Estado atual:

- `RegisterWithWhatsAppOtpAction` cria sempre organizacao `partner`;
- onboarding final aponta para `/plans`.

Subtasks:

1. introduzir um contexto de jornada no fluxo OTP, por exemplo:
   - `partner_signup`
   - `trial_event`
   - `single_event_checkout`
   - `admin_assisted`
2. ajustar `RequestRegisterOtpRequest` e `VerifyRegisterOtpRequest` para aceitar esse contexto;
3. ajustar `RegisterWithWhatsAppOtpAction` para criar o tipo de organizacao adequado;
4. ajustar payload de onboarding para devolver `next_path` coerente por jornada;
5. manter compatibilidade com o fluxo atual quando contexto nao for informado.

Arquivos provaveis:

- `apps/api/app/Modules/Auth/Http/Requests/RequestRegisterOtpRequest.php`
- `apps/api/app/Modules/Auth/Http/Requests/VerifyRegisterOtpRequest.php`
- `apps/api/app/Modules/Auth/Actions/RegisterWithWhatsAppOtpAction.php`
- `apps/api/app/Modules/Auth/Http/Controllers/LoginController.php`
- `apps/web/src/modules/auth/services/auth.service.ts`
- `apps/web/src/modules/auth/LoginPage.tsx`

Criterio de aceite:

- cadastro OTP deixa de depender de um unico desfecho;
- o mesmo backend suporta `partner` e `direct_customer`.

Dependencias:

- M1-T1.

### TASK M1-T3 - Preparar o evento para carregar situacao comercial

Estado atual:

- `events` tem `purchased_plan_snapshot_json`;
- nao tem `commercial_mode`;
- nao tem `current_entitlements_json`.

Subtasks:

1. criar migration para adicionar:
   - `commercial_mode`
   - `current_entitlements_json`
2. atualizar model `Event`;
3. decidir valores oficiais de `commercial_mode`;
4. atualizar resources e responses de evento para devolver esses campos;
5. manter `purchased_plan_snapshot_json` como snapshot legado/comercial inicial.

Arquivos provaveis:

- nova migration em `apps/api/database/migrations`
- `apps/api/app/Modules/Events/Models/Event.php`
- `apps/api/app/Modules/Events/Http/Resources/EventResource.php`
- `apps/api/app/Modules/Events/Http/Resources/EventDetailResource.php`
- `apps/web/src/modules/events/types.ts`

Criterio de aceite:

- qualquer evento passa a expor sua situacao comercial de forma legivel.

Dependencias:

- M0-T1.

### TASK M1-T4 - Criar leitura inicial de status comercial do evento

Estado atual:

- o painel nao tem um contrato proprio para saber se o evento e demo, pago, coberto por assinatura ou bonus.

Subtasks:

1. criar endpoint inicial `GET /api/v1/events/{id}/commercial-status`;
2. nesta primeira versao, a resposta pode ser derivada de:
   - subscription da organizacao;
   - `event_purchases`;
   - `purchased_plan_snapshot_json`;
   - `commercial_mode`
3. criar resource dedicado;
4. expor blocos minimos:
   - `commercial_mode`
   - `subscription_summary`
   - `purchase_summary`
   - `entitlements_summary` mesmo que parcial

Arquivos provaveis:

- novo controller ou action em `Billing` ou `Events`
- novas rotas em `apps/api/app/Modules/Billing/routes/api.php` ou `Events/routes/api.php`
- frontend de eventos para futura leitura

Criterio de aceite:

- suporte e frontend ja conseguem ler a situacao comercial do evento sem heuristica espalhada.

Dependencias:

- M1-T3.

## M2 - Motor de ativacao comercial do evento

Objetivo:

- separar acesso efetivo de pagamento;
- suportar trial, bonus, override e compra avulsa no mesmo motor.

### Status atual de M2

Status:

- `M2-T1` implementada na base;
- `M2-T2` implementada na base;
- `M2-T3` implementada na base;
- `M2-T4` implementada na base.

Entregas ja realizadas nesta rodada:

1. tabela `event_access_grants` criada com indices e snapshots;
2. enums de `source_type`, `status` e `merge_strategy` adicionados;
3. model `EventAccessGrant` e relacoes com `Event` e `Organization`;
4. `EntitlementResolverService` criado como fonte unica de combinacao entre:
   - baseline de subscription
   - compra avulsa legada por evento
   - grants ativos do evento
5. `EventCommercialStatusService` passou a delegar para o resolver;
6. `GET /api/v1/events/{id}/commercial-status` agora expõe `grants_summary`;
7. recalculo automatico foi conectado a:
   - `Subscription::saved/deleted`
   - `EventPurchase::saved/deleted`
   - `EventAccessGrant::saved/deleted`
8. testes de feature e unit cobrem expand, restrict, persistencia e recalculo automatico do snapshot resolvido.
9. `MeResource` e `AccessMatrixController` passaram a usar um builder unico sobre entitlements da organizacao;
10. o frontend autenticado passou a receber `access.entitlements` e expor esse contrato nos hooks de sessao;
11. a UI de eventos passou a exibir `commercial_mode` e resumo comercial basico no detalhe/listagem.

Arquivos-chave desta rodada:

- `apps/api/app/Modules/Billing/Models/EventAccessGrant.php`
- `apps/api/app/Modules/Billing/Services/EntitlementResolverService.php`
- `apps/api/app/Modules/Billing/Actions/SyncEventEntitlementsAction.php`
- `apps/api/app/Modules/Billing/Actions/SyncOrganizationEventEntitlementsAction.php`
- `apps/api/app/Modules/Billing/Enums/EventAccessGrantSourceType.php`
- `apps/api/app/Modules/Billing/Enums/EventAccessGrantStatus.php`
- `apps/api/app/Modules/Billing/Enums/EntitlementMergeStrategy.php`
- `apps/api/app/Modules/Billing/Services/OrganizationEntitlementResolverService.php`
- `apps/api/app/Modules/Auth/Services/AccessStateBuilderService.php`
- `apps/api/database/migrations/2026_04_01_140000_create_event_access_grants_table.php`
- `apps/api/app/Modules/Billing/Providers/BillingServiceProvider.php`
- `apps/api/app/Modules/Auth/Http/Resources/MeResource.php`
- `apps/api/app/Modules/Auth/Http/Controllers/AccessMatrixController.php`
- `apps/api/app/Modules/Events/Support/EventCommercialStatusService.php`
- `apps/web/src/app/providers/AuthProvider.tsx`
- `apps/web/src/shared/hooks/useModuleAccess.ts`
- `apps/web/src/modules/events/EventDetailPage.tsx`
- `apps/web/src/modules/events/EventsListPage.tsx`
- `apps/api/tests/Feature/Events/EventCommercialStatusTest.php`
- `apps/api/tests/Feature/Billing/BillingTest.php`
- `apps/api/tests/Feature/Auth/MeTest.php`
- `apps/api/tests/Feature/Auth/AccessMatrixTest.php`
- `apps/api/tests/Unit/Billing/EntitlementResolverServiceTest.php`

Proximo passo direto:

- `M3-T1` abrir o catalogo separado de `event_packages`.

Relaciona-se com discovery:

- `Gaps Criticos > 2, 3, 6, 7`
- `Direcao Recomendada > Camada de grants de evento`
- `Plano De Acao Recomendado > Fase 2`

### TASK M2-T1 - Criar schema de `event_access_grants`

Subtasks:

1. criar migration de `event_access_grants`;
2. definir enums ou constantes para:
   - `source_type`
   - `status`
   - `merge_strategy`
3. criar model e relacoes em `Event`, `Organization` e eventualmente `User`;
4. criar factory e seed minima para testes;
5. revisar indices por `event_id`, `status`, `starts_at`, `ends_at`, `priority`.

Arquivos provaveis:

- nova migration
- novo model em `apps/api/app/Modules/Billing/Models` ou modulo comercial dedicado
- `Event.php`
- `Organization.php`

Criterio de aceite:

- o sistema consegue persistir grant de evento sem depender de assinatura fake.

Dependencias:

- M1-T3.

### TASK M2-T2 - Criar `EntitlementResolverService`

Subtasks:

1. definir contrato de entrada:
   - organizacao
   - evento
   - subscription ativa
   - grants ativos
2. definir contrato de saida:
   - modulos
   - limites
   - branding
   - `source_summary`
3. implementar regra de precedencia inicial:
   - permissao do usuario continua separada
   - subscription define baseline
   - grant do evento pode expandir, substituir ou restringir
4. salvar resultado em `events.current_entitlements_json`;
5. cobrir com testes unitarios de combinacao.

Arquivos provaveis:

- novo service em `apps/api/app/Modules/Billing/Services` ou `Shared/Support`
- actions ou listeners de recalculo
- `MeResource.php`

Criterio de aceite:

- existe uma unica fonte de verdade para o acesso comercial do evento.

Dependencias:

- M2-T1.

### TASK M2-T3 - Recalculo automatico de entitlements

Subtasks:

1. recalcular quando subscription muda;
2. recalcular quando grant e criado;
3. recalcular quando grant expira ou e revogado;
4. recalcular quando evento muda de modo comercial;
5. registrar auditoria de recalculo e mudanca de origem.

Arquivos provaveis:

- actions de billing
- jobs ou listeners
- audit trail

Criterio de aceite:

- `current_entitlements_json` nao fica defasado em mudancas criticas.

Dependencias:

- M2-T2.

### TASK M2-T4 - Fazer `MeResource` e telas consumirem entitlements

Subtasks:

1. reduzir logica manual de features em `MeResource`;
2. introduzir leitura do entitlement resolvido;
3. manter compatibilidade com plano ate concluir migracao;
4. ajustar frontend para confiar mais em `resolved_entitlements`.

Arquivos provaveis:

- `apps/api/app/Modules/Auth/Http/Resources/MeResource.php`
- `apps/web/src/app/providers/AuthProvider.tsx`
- modulos dependentes de feature flags

Criterio de aceite:

- acesso de modulo deixa de depender exclusivamente do plano bruto da organizacao.

Dependencias:

- M2-T2.

## M3 - Catalogo avulso do evento

Objetivo:

- separar produto recorrente de produto event-first.

Status de alinhamento documental:

- revisado em `2026-04-02` contra o estado real do codigo;
- o catalogo avulso ja cumpriu a base necessaria para destravar as jornadas publicas e o financeiro minimo.

### Status atual de M3

Status:

- `M3-T1` implementada na base;
- `M3-T2` implementada na base;
- demais tasks de M3 ainda pendentes.

Entregas ja realizadas nesta rodada:

1. schema separado para:
   - `event_packages`
   - `event_package_prices`
   - `event_package_features`
2. models, enums, factories e seeder inicial do catalogo avulso;
3. relacao opcional de `event_access_grants.package_id` com `event_packages`;
4. leitura inicial por API em:
   - `GET /api/v1/event-packages`
   - `GET /api/v1/event-packages/{id}`
   - `GET /api/v1/public/event-packages`
5. filtro por `target_audience` na rota publica;
6. tipos compartilhados do frontend alinhados para o novo catalogo;
7. testes de feature cobrindo catalogo publico, filtro por audiencia e detalhe autenticado.
8. `event_purchases` agora aceita `package_id` sem perder compatibilidade com `plan_id` legado;
9. `EntitlementResolverService` passou a resolver compra avulsa por `event_package` quando o snapshot vier do novo catalogo;
10. `billing/invoices` passou a carregar metadata de `package` no historico de invoices;
11. testes de feature e unit cobrem compra avulsa nova via `event_package` e leitura legada por `plan`.

Arquivos-chave desta rodada:

- `apps/api/app/Modules/Billing/Models/EventPackage.php`
- `apps/api/app/Modules/Billing/Models/EventPackagePrice.php`
- `apps/api/app/Modules/Billing/Models/EventPackageFeature.php`
- `apps/api/app/Modules/Billing/Enums/EventPackageAudience.php`
- `apps/api/app/Modules/Billing/Enums/EventPackageBillingMode.php`
- `apps/api/app/Modules/Billing/Queries/ListEventPackagesQuery.php`
- `apps/api/app/Modules/Billing/Http/Controllers/EventPackageController.php`
- `apps/api/app/Modules/Billing/Http/Resources/EventPackageResource.php`
- `apps/api/database/migrations/2026_04_01_150000_create_event_package_tables.php`
- `apps/api/database/migrations/2026_04_01_150100_add_package_fk_to_event_access_grants_table.php`
- `apps/api/database/migrations/2026_04_01_191000_add_package_id_to_event_purchases_table.php`
- `apps/api/database/seeders/EventPackagesSeeder.php`
- `apps/api/app/Modules/Billing/Models/EventPurchase.php`
- `apps/api/app/Modules/Billing/Services/EntitlementResolverService.php`
- `apps/api/app/Modules/Billing/Http/Controllers/SubscriptionController.php`
- `apps/api/tests/Feature/Billing/EventPackageCatalogTest.php`
- `apps/api/tests/Feature/Billing/BillingTest.php`
- `apps/api/tests/Feature/Events/EventCommercialStatusTest.php`
- `apps/api/tests/Unit/Billing/EntitlementResolverServiceTest.php`
- `apps/web/src/lib/api-types.ts`

Proximo passo direto:

- `M4-T1` abrir a jornada publica de `trial-events`, porque o backbone comercial do evento ja tem grant, entitlement e catalogo separado.

Relaciona-se com discovery:

- `Gaps Criticos > 1`
- `Direcao Recomendada > Camada de catalogo`
- `Plano De Acao Recomendado > Fase 3`

### TASK M3-T1 - Criar `event_packages`

Subtasks:

1. criar tabelas:
   - `event_packages`
   - `event_package_prices`
   - `event_package_features`
2. criar models, relations e seed inicial;
3. definir target audience:
   - `direct_customer`
   - `partner`
   - `both`
4. definir serializacao de features e limites.

Criterio de aceite:

- compra avulsa deixa de depender conceitualmente do catalogo de `plans`.

Dependencias:

- M0-T1.

### TASK M3-T2 - Adaptar compra avulsa para apontar a pacote de evento

Subtasks:

1. decidir estrategia de transicao de `event_purchases.plan_id`;
2. criar novo campo `package_id` ou tabela de ligacao;
3. preservar compatibilidade de dashboards existentes;
4. atualizar KPIs e historicos para entender compra avulsa nova e legada.

Criterio de aceite:

- novo fluxo de compra avulsa referencia `event_package`;
- leituras antigas continuam funcionando.

Dependencias:

- M3-T1.

## M4 - Jornadas reais do produto

Objetivo:

- transformar a arquitetura em funil de produto usavel.

### Status atual de M4

Status:

- `M4-T1` implementada na base;
- `M4-T2` implementada na base;
- `M4-T3` implementada na base.

Entregas ja realizadas nesta rodada:

1. endpoint `POST /api/v1/public/trial-events` aberto na API publica;
2. estrategia escolhida para esta primeira versao: `sessao leve`, sem depender de OTP estrito;
3. criacao automatica de:
   - usuario
   - organizacao
   - membership `partner-owner`
   - evento
   - grant `trial`
4. emissao imediata de token autenticado e onboarding apontando para o evento criado;
5. sincronizacao imediata de `commercial_mode=trial` e `current_entitlements_json`;
6. testes de feature cobrindo criacao do trial e bloqueio de WhatsApp ja cadastrado.

Entregas adicionais desta rodada:

1. endpoint `POST /api/v1/public/event-checkouts` aberto na API publica;
2. endpoint `POST /api/v1/public/event-checkouts/{billingOrder:uuid}/confirm` aberto para confirmacao simplificada do checkout;
3. criacao automatica de:
   - usuario leve
   - organizacao `direct_customer`
   - membership `partner-owner`
   - evento com modulos derivados do `event_package`
   - `billing_order` preliminar em `pending_payment`
   - `billing_order_item` com snapshot do pacote
4. confirmacao do checkout convertendo o pedido em:
   - `event_purchase`
   - grant `event_purchase`
   - sincronizacao de `commercial_mode=single_purchase`
   - atualizacao de `purchased_plan_snapshot_json`
5. introducao inicial de `billing_orders` e `billing_order_items` como fundacao financeira minima;
6. testes de feature cobrindo:
   - criacao do checkout
   - confirmacao idempotente
   - bloqueio de pacote nao elegivel
   - bloqueio de WhatsApp ja cadastrado.

Entregas adicionais da jornada admin:

1. endpoint autenticado `POST /api/v1/admin/quick-events` aberto para super-admin e platform-admin;
2. criacao ou reuso de:
   - usuario responsavel
   - organizacao
   - membership ativa
   - evento operacional
3. criacao de grant `bonus` ou `manual_override` com:
   - `package_id`
   - motivo
   - origem
   - observacao
   - janela `starts_at` / `ends_at`
4. auditoria explicita do ator que concedeu o grant e do contexto da jornada;
5. retorno estruturado com:
   - `responsible_user`
   - `organization`
   - `event`
   - `commercial_status`
   - `grant`
   - `access_delivery`
6. testes de feature cobrindo:
   - criacao com nova organizacao
   - reuso de organizacao e usuario existente
   - proibicao para `partner-owner`.

Arquivos-chave desta rodada:

- `apps/api/app/Modules/Billing/Http/Requests/StorePublicTrialEventRequest.php`
- `apps/api/app/Modules/Billing/Actions/CreatePublicTrialEventAction.php`
- `apps/api/app/Modules/Billing/Http/Controllers/PublicTrialEventController.php`
- `apps/api/app/Modules/Billing/Http/Resources/PublicTrialEventResource.php`
- `apps/api/app/Modules/Billing/Http/Requests/StorePublicEventCheckoutRequest.php`
- `apps/api/app/Modules/Billing/Http/Requests/ConfirmPublicEventCheckoutRequest.php`
- `apps/api/app/Modules/Billing/Actions/CreatePublicEventCheckoutAction.php`
- `apps/api/app/Modules/Billing/Actions/ConfirmPublicEventCheckoutAction.php`
- `apps/api/app/Modules/Billing/Http/Controllers/PublicEventCheckoutController.php`
- `apps/api/app/Modules/Billing/Http/Controllers/AdminQuickEventController.php`
- `apps/api/app/Modules/Billing/Http/Resources/PublicEventCheckoutResource.php`
- `apps/api/app/Modules/Billing/Http/Resources/AdminQuickEventResource.php`
- `apps/api/app/Modules/Billing/Services/EventPackageSnapshotService.php`
- `apps/api/app/Modules/Billing/Services/PublicEventCheckoutPayloadBuilder.php`
- `apps/api/app/Modules/Billing/Services/PublicJourneyIdentityService.php`
- `apps/api/app/Modules/Billing/Actions/CreateAdminQuickEventAction.php`
- `apps/api/app/Modules/Billing/Http/Requests/StoreAdminQuickEventRequest.php`
- `apps/api/app/Modules/Billing/Models/BillingOrder.php`
- `apps/api/app/Modules/Billing/Models/BillingOrderItem.php`
- `apps/api/app/Modules/Billing/routes/api.php`
- `apps/api/database/migrations/2026_04_01_194000_create_billing_order_tables.php`
- `apps/api/database/migrations/2026_04_01_194100_add_billing_order_id_to_event_purchases_table.php`
- `apps/api/tests/Feature/Billing/PublicTrialEventTest.php`
- `apps/api/tests/Feature/Billing/PublicEventCheckoutTest.php`
- `apps/api/tests/Feature/Billing/AdminQuickEventTest.php`
- `apps/web/src/lib/api-types.ts`

Proximo passo direto:

- `M6-T1` revisar leituras e telas ainda acopladas ao plano bruto da organizacao;
- depois disso, conectar o frontend de billing aos contratos reais da camada comercial.

Relaciona-se com discovery:

- `Jornadas Prioritarias Que O Modelo Precisa Suportar`
- `Plano De Acao Recomendado > Fase 4`

### TASK M4-T1 - Jornada publica de trial

Subtasks:

1. criar endpoint `POST /api/v1/public/trial-events`;
2. integrar com OTP ou sessao leve;
3. criar organizacao se necessario;
4. criar evento;
5. criar grant `trial`;
6. devolver onboarding para painel do evento.

Criterio de aceite:

- usuario cria um evento demo sem precisar escolher plano.

Dependencias:

- M1-T2
- M2-T1
- M2-T2

### TASK M4-T2 - Jornada publica de compra direta de evento

Subtasks:

1. criar endpoint `GET /api/v1/public/event-packages`;
2. criar endpoint `POST /api/v1/public/event-checkouts`;
3. criar order preliminar;
4. apos pagamento confirmado, criar `event_purchase` e grant `event_purchase`;
5. atualizar `commercial_mode` do evento.

Criterio de aceite:

- noiva ou cliente final consegue contratar um evento sem assinatura recorrente.

Dependencias:

- M3-T1
- M2-T1
- M5-T1 no minino em versao simplificada

Status atualizado em 2026-04-02:

- concluida em versao simplificada;
- `GET /api/v1/public/event-packages` ja existia e permaneceu como catalogo publico;
- `POST /api/v1/public/event-checkouts` agora cria usuario leve, organizacao `direct_customer`, evento e order preliminar;
- `POST /api/v1/public/event-checkouts/{billingOrder:uuid}/confirm` agora converte o checkout em `event_purchase` e grant `event_purchase`;
- `billing_orders` e `billing_order_items` entraram em versao minima para suportar essa jornada;
- a confirmacao ainda e manual/simulada, sem gateway real ou webhook, embora `payments` e `invoices` minimos ja sejam gerados na confirmacao.

### TASK M4-T3 - Jornada admin de criacao rapida e bonificacao

Subtasks:

1. criar endpoint `POST /api/v1/admin/quick-events`;
2. permitir criar ou reutilizar organizacao;
3. permitir criar evento com grant `bonus` ou `manual_override`;
4. auditar:
   - quem concedeu
   - motivo
   - prazo
   - observacao
5. opcionalmente enviar acesso por WhatsApp.

Criterio de aceite:

- super admin cria evento operacional sem precisar inventar assinatura.

Dependencias:

- M2-T1
- M2-T2

Status atualizado em 2026-04-02:

- concluida em versao operacional inicial;
- `POST /api/v1/admin/quick-events` agora cria evento operacional sem assinatura, com grant `bonus` ou `manual_override`;
- a jornada permite criar nova organizacao ou reutilizar organizacao existente;
- a identidade do responsavel pode ser criada ou reaproveitada a partir de WhatsApp/e-mail;
- motivo, origem, observacao, prazo e ator do grant ficam persistidos no metadata e no audit log;
- envio real de acesso por WhatsApp continua pendente e hoje retorna apenas status `pending_not_implemented` quando solicitado.

Status atualizado em 2026-04-03:

- envio real de acesso por WhatsApp entrou em versao operacional inicial;
- `CreateAdminQuickEventAction` agora usa `AdminQuickEventAccessDeliveryService` fora da transacao principal;
- quando existe instancia conectada configurada em `billing.access_delivery.whatsapp_instance_id` ou fallback operacional valido, o backend cria `whatsapp_messages` e enfileira `SendWhatsAppMessageJob`;
- quando nao existe sender disponivel, a jornada continua sem falhar e devolve `access_delivery.status=unavailable`;
- o resultado do delivery passa a ser persistido em `event_access_grants.metadata_json.access_delivery`;
- testes de feature cobrem os dois cenarios:
  - `queued` com instancia configurada
  - `unavailable` sem sender operacional.

## M5 - Financeiro real e gateway

Objetivo:

- deixar cobranca e conciliacao em estado produtivo.

Relaciona-se com discovery:

- `Gaps Criticos > 5`
- `Plano De Acao Recomendado > Fase 5`

### Status atual de M5

Status:

- `M5-T1` implementada na base;
- `M5-T2` implementada na base.
- `M5-T3` implementada na base.

Entregas ja realizadas nesta rodada:

1. migrations de `payments` e `invoices` adicionadas ao modulo de billing;
2. models `Payment` e `Invoice` criados com enums de status financeiro;
3. `BillingOrder` passou a expor relacoes com pagamentos e invoices;
4. `MarkBillingOrderAsPaidAction` consolidou a liquidacao minima de orders em:
   - atualizacao do `billing_order`
   - criacao ou reaproveitamento de `payment`
   - criacao ou reaproveitamento de `invoice`
5. `POST /api/v1/billing/checkout` passou a:
   - usar `plan_prices`
   - criar `billing_order`
   - criar `billing_order_item`
   - gerar `payment` e `invoice`
6. `POST /api/v1/public/event-checkouts/{billingOrder:uuid}/confirm` passou a gerar `payment` e `invoice` ao liquidar a compra avulsa;
7. `GET /api/v1/billing/invoices` deixou de depender de `event_purchases` e agora pagina invoices reais com snapshot do pedido;
8. testes de feature cobrem checkout de assinatura, confirmacao de checkout publico e leitura de invoices reais.
9. `BillingGatewayInterface`, `BillingGatewayManager` e `ManualBillingGateway` entraram como camada isolada de provider;
10. `billing_gateway_events` passou a persistir webhook, idempotencia e resultado processado;
11. `POST /api/v1/webhooks/billing/{provider}` entrou como endpoint publico de webhook do billing;
12. `POST /api/v1/billing/checkout` e `POST /api/v1/public/event-checkouts` passaram a iniciar o checkout via gateway manager;
13. `RegisterBillingGatewayPaymentAction` e `CancelBillingOrderAction` consolidaram pagamento e cancelamento desacoplados do provider;
14. testes de feature cobrem webhook de pagamento de assinatura, webhook de pagamento de pacote e webhook de cancelamento idempotente.
15. `POST /api/v1/billing/subscription/cancel` agora existe em versao operacional inicial;
16. o cancelamento da assinatura pode ocorrer:
   - imediatamente
   - ao fim do ciclo atual
17. entitlements da conta e `commercial-status` do evento passaram a respeitar `ends_at` para assinaturas canceladas no fim do ciclo;
18. a pagina `/plans` agora aciona cancelamento real e comunica claramente quando a cobertura da conta segue valida ate o fim do periodo.
19. permissoes de billing foram endurecidas para separar:
   - `billing.purchase` para contratar plano
   - `billing.manage_subscription` para encerrar renovacao automatica
   - `billing.view` para leitura de assinatura e invoices

Arquivos-chave desta rodada:

- `apps/api/app/Modules/Billing/Actions/MarkBillingOrderAsPaidAction.php`
- `apps/api/app/Modules/Billing/Actions/CreateSubscriptionCheckoutAction.php`
- `apps/api/app/Modules/Billing/Actions/CreateEventPackageGatewayCheckoutAction.php`
- `apps/api/app/Modules/Billing/Actions/RegisterBillingGatewayPaymentAction.php`
- `apps/api/app/Modules/Billing/Actions/CancelBillingOrderAction.php`
- `apps/api/app/Modules/Billing/Actions/ProcessBillingWebhookAction.php`
- `apps/api/app/Modules/Billing/Actions/ActivatePaidEventPackageOrderAction.php`
- `apps/api/app/Modules/Billing/Actions/CancelCurrentSubscriptionAction.php`
- `apps/api/app/Modules/Billing/Http/Controllers/SubscriptionController.php`
- `apps/api/app/Modules/Billing/Http/Requests/CancelCurrentSubscriptionRequest.php`
- `apps/api/app/Modules/Billing/Http/Controllers/BillingWebhookController.php`
- `apps/api/app/Modules/Billing/Http/Resources/BillingInvoiceResource.php`
- `apps/api/app/Modules/Billing/Services/BillingGatewayInterface.php`
- `apps/api/app/Modules/Billing/Services/BillingGatewayManager.php`
- `apps/api/app/Modules/Billing/Services/ManualBillingGateway.php`
- `apps/api/app/Modules/Billing/Models/BillingOrder.php`
- `apps/api/app/Modules/Billing/Models/BillingGatewayEvent.php`
- `apps/api/app/Modules/Billing/Models/Payment.php`
- `apps/api/app/Modules/Billing/Models/Invoice.php`
- `apps/api/app/Modules/Billing/Models/Subscription.php`
- `apps/api/app/Modules/Billing/Services/OrganizationEntitlementResolverService.php`
- `apps/api/app/Modules/Billing/Services/EntitlementResolverService.php`
- `apps/api/app/Modules/Billing/Services/PlanSnapshotService.php`
- `apps/api/app/Modules/Billing/Actions/ConfirmPublicEventCheckoutAction.php`
- `apps/api/database/migrations/2026_04_02_120000_create_billing_gateway_events_table.php`
- `apps/api/database/migrations/2026_04_02_100000_create_billing_payments_and_invoices_tables.php`
- `apps/api/tests/Feature/Billing/BillingTest.php`
- `apps/api/tests/Feature/Billing/PublicEventCheckoutTest.php`
- `apps/api/tests/Feature/Billing/BillingWebhookTest.php`
- `apps/api/tests/Feature/Auth/MeTest.php`
- `apps/api/tests/Unit/Billing/EntitlementResolverServiceTest.php`
- `apps/web/src/modules/plans/PlansPage.tsx`
- `apps/web/src/modules/plans/api.ts`

Validacoes executadas nesta rodada:

- `cd apps/api && php artisan test --filter=BillingTest`
- `cd apps/api && php artisan test --filter=PublicEventCheckoutTest`
- `cd apps/api && php artisan test --filter=EventCommercialStatusTest`
- `cd apps/api && php artisan test --filter=BillingWebhookTest`
- `cd apps/api && php artisan test --filter=EntitlementResolverServiceTest`
- `cd apps/api && php artisan test --filter=MeTest`
- `cd apps/web && npm run type-check`

Proximo passo direto:

- integrar adapter externo real de gateway;
- endurecer retries, conciliacao e renovacao automatica;
- depois disso, voltar para UX comercial publica menos administrativa.

### TASK M5-T1 - Criar trilha financeira minima

Subtasks:

1. criar:
   - `billing_orders`
   - `billing_order_items`
   - `payments`
   - `invoices`
2. separar invoice de purchase;
3. modelar status financeiros;
4. ligar orders de assinatura e de pacote avulso.

Criterio de aceite:

- `billing/invoices` deixa de depender de `event_purchases`.

Dependencias:

- M0-T1.

Status atualizado em 2026-04-02:

- concluida em versao minima operacional;
- `billing_orders`, `billing_order_items`, `payments` e `invoices` ja existem no modulo;
- `billing/checkout` de assinatura e a confirmacao do checkout publico de evento ja geram trilha financeira minima;
- `billing/invoices` ja devolve invoices reais, desacopladas de `event_purchases`;
- ainda faltam provider externo real, retries, conciliacao automatica e demais estados financeiros avancados.

### TASK M5-T2 - Isolar integracao de gateway

Subtasks:

1. criar `BillingGatewayInterface`;
2. implementar adapter inicial;
3. criar actions:
   - criar checkout de assinatura
   - criar checkout de pacote
   - registrar pagamento
   - cancelar
4. criar endpoints de webhook;
5. idempotencia e audit trail.

Criterio de aceite:

- dominio comercial nao fica acoplado ao provider.

Dependencias:

- M5-T1.

Status atualizado em 2026-04-02:

- concluida em versao inicial com provider `manual`;
- `BillingGatewayInterface` e `BillingGatewayManager` agora separam controller e provider;
- o provider inicial `ManualBillingGateway` sustenta:
  - checkout de assinatura com auto-captura
  - checkout de pacote com confirmacao manual existente
  - parse de webhook manual
  - cancelamento manual
- `billing_gateway_events` persiste webhook, idempotencia e resultado processado;
- `POST /api/v1/webhooks/billing/{provider}` agora processa:
  - `payment.paid`
  - `checkout.canceled`
- `RegisterBillingGatewayPaymentAction` desacopla a confirmacao de pagamento do provider para:
  - assinatura recorrente
  - pacote avulso por evento
- ainda falta adapter real de gateway, retries assincronos e regras avancadas de conciliacao.

### TASK M5-T3 - Cancelamento de assinatura e encerramento do ciclo

Subtasks:

1. expor endpoint autenticado para cancelar a assinatura atual da organizacao;
2. suportar:
   - cancelamento imediato
   - cancelamento ao fim do ciclo
3. recalcular automaticamente entitlements da conta e dos eventos cobertos;
4. ajustar `/plans` para sair do estado puramente informativo e acionar cancelamento real;
5. comunicar na UI quando a cobertura da conta continua ativa ate `ends_at`.

Criterio de aceite:

- a conta consegue cancelar a assinatura sem mock;
- cancelamento ao fim do ciclo nao derruba entitlements antes da hora;
- cancelamento imediato remove a cobertura comercial baseada na conta.

Dependencias:

- M5-T1
- M2-T3
- M2-T4

Status atualizado em 2026-04-03:

- concluida em versao operacional inicial;
- `POST /api/v1/billing/subscription/cancel` agora existe para a conta autenticada;
- `CancelCurrentSubscriptionAction` passou a suportar:
  - `period_end`
  - `immediately`
- `OrganizationEntitlementResolverService` e `EntitlementResolverService` agora tratam assinatura `canceled` com `ends_at` futuro como baseline ainda ativo;
- `/plans` agora executa cancelamento real e comunica que a renovacao automatica foi encerrada, mantendo cobertura da conta ate o fim do ciclo quando aplicavel.

## M6 - Limpeza de acoplamentos antigos

Objetivo:

- evitar que o sistema continue aparentando `billing por conta` em tudo.

Relaciona-se com discovery:

- `Gaps Criticos > 7`
- `Plano De Acao Recomendado > Fase 6`

### TASK M6-T1 - Revisar telas e resources que expoem plano da organizacao por padrao

Subtasks:

1. revisar `ClientResource`;
2. revisar `ListClientsQuery`;
3. revisar dashboard e KPIs;
4. revisar telas de evento para priorizar `commercial_mode` e `commercial-status`.

Criterio de aceite:

- UI administrativa deixa claro quando a origem comercial e do evento e nao da conta.

Dependencias:

- M2-T4
- M4-T1 ou M4-T2

Status atualizado em 2026-04-02:

- em andamento com a primeira rodada implementada;
- `ClientResource` agora expone `organization_billing` explicitando que `plan_name` e `subscription_status` pertencem a assinatura da organizacao;
- `ListClientsQuery` passou a carregar tambem campos operacionais da subscription da conta;
- a tela `clients` deixou de rotular esse bloco apenas como `Plano` e passou a usar `Plano da organizacao` e `Plano da conta`;
- a pagina `/plans` saiu do mock e agora consome:
  - `GET /api/v1/plans`
  - `GET /api/v1/billing/subscription`
  - `GET /api/v1/billing/invoices`
  - `POST /api/v1/billing/checkout`

Status atualizado em 2026-04-03:

- concluida na segunda rodada;
- dashboard e KPIs agora diferenciam:
  - receita liquidada total
  - receita de assinatura da conta
  - receita de eventos/pacotes avulsos
  - mix comercial dos eventos ativos
- o ranking de parceiros no dashboard passou a mostrar split entre receita recorrente da conta e receita avulsa por evento;
- as telas de evento passaram a usar melhor `commercial_mode` e `commercial-status`, deixando mais claro:
  - quando o evento depende da assinatura da conta
  - quando ele possui ativacao propria por pacote, trial, bonus ou override
  - qual e a origem comercial principal ativa.
- a pagina `/plans` passou a permitir cancelar a assinatura recorrente da conta via:
  - `POST /api/v1/billing/subscription/cancel`
  - recalculo automatico de `current_entitlements_json` para os eventos cobertos pela conta
  - feedback explicito de que a assinatura da conta e diferente da ativacao comercial propria do evento
- a pagina `/plans` e os requests do modulo passaram a respeitar melhor o split de permissao entre:
  - visualizacao de billing
  - compra/checkout da conta
  - cancelamento da renovacao automatica

## Primeira Execucao Recomendada

## Objetivo da execucao 01

Fazer a menor entrega que:

- reduz atrito real de onboarding;
- prepara o evento para comercializacao propria;
- nao depende ainda de gateway, order ou invoices reais.

## Status atual da execucao 01

Status:

- concluida em base tecnica;
- pronta para abrir a proxima rodada sobre `event_access_grants`.

Itens ja implementados nesta rodada:

1. `direct_customer` entrou em `OrganizationType`;
2. o onboarding OTP passou a aceitar contexto de jornada e devolver `next_path` dinamico;
3. `events` agora possui `commercial_mode` e `current_entitlements_json`;
4. a API passou a expor `GET /api/v1/events/{id}/commercial-status`;
5. o frontend de autenticacao deixou de assumir `/plans` como desfecho unico.

Arquivos-chave desta execucao:

- `apps/api/app/Modules/Organizations/Enums/OrganizationType.php`
- `apps/api/app/Modules/Auth/Enums/RegisterJourneyType.php`
- `apps/api/app/Modules/Auth/Actions/RegisterWithWhatsAppOtpAction.php`
- `apps/api/app/Modules/Events/Enums/EventCommercialMode.php`
- `apps/api/app/Modules/Events/Support/EventCommercialStatusService.php`
- `apps/api/app/Modules/Events/Http/Controllers/EventController.php`
- `apps/api/database/migrations/2026_04_01_130000_add_commercial_fields_to_events_table.php`
- `apps/web/src/modules/auth/services/auth.service.ts`
- `apps/web/src/modules/auth/LoginPage.tsx`

Validacoes executadas nesta rodada:

- `cd apps/api && php artisan test --filter=RegisterOtpTest`
- `cd apps/api && php artisan test --filter=CreateEventTest`
- `cd apps/api && php artisan test --filter=EventCommercialStatusTest`
- `cd apps/web && npm run type-check`

Escopo da execucao 01:

1. adicionar `direct_customer` em `OrganizationType`;
2. evoluir OTP para aceitar contexto de jornada;
3. adicionar `commercial_mode` e `current_entitlements_json` em `events`;
4. criar endpoint inicial `GET /events/{id}/commercial-status`;
5. ajustar frontend de auth para nao terminar sempre em `/plans`.

Nao entra nesta execucao:

- `event_access_grants`;
- `event_packages`;
- gateway;
- `billing_orders`;
- `payments`;
- `invoices`.

## Valor entregue pela execucao 01

Ao final desta entrega, o produto ja melhora em 3 frentes:

1. onboarding deixa de ser obrigatoriamente parceiro-assinatura;
2. evento passa a ter estado comercial explicito;
3. suporte e frontend ganham uma leitura inicial de status comercial do evento.

## Ordem interna da execucao 01

### E1-T1 - Schema e enums

Subtasks:

1. adicionar `direct_customer` ao enum;
2. criar migration para novos campos em `events`;
3. atualizar factories e casts.

Entrega:

- base persistivel pronta para novos fluxos.

### E1-T2 - Onboarding OTP orientado por jornada

Subtasks:

1. ajustar requests;
2. ajustar action de registro;
3. ajustar payload de onboarding;
4. manter compatibilidade retroativa.

Entrega:

- API consegue distinguir `partner_signup` de `single_event` ou `trial`.

### E1-T3 - Resource e endpoint de status comercial

Subtasks:

1. criar action/query de leitura;
2. criar resource;
3. expor rota;
4. derivar estado inicial de subscription + purchase snapshot.

Entrega:

- frontend e suporte leem um contrato unico de status.

### E1-T4 - Frontend auth e roteamento

Subtasks:

1. ajustar `auth.service.ts` para respeitar `next_path` dinamico;
2. ajustar `LoginPage.tsx` para nao presumir `/plans`;
3. exibir texto de onboarding coerente com a jornada recebida.

Entrega:

- o primeiro passo apos cadastro fica desacoplado do funil plan-first.

### E1-T5 - Testes minimos obrigatorios

Subtasks:

1. teste de enum/validacao para `direct_customer`;
2. teste de OTP criando organizacao conforme jornada;
3. teste de evento persistindo `commercial_mode`;
4. teste do endpoint `commercial-status`.

Entrega:

- a primeira melhoria entra protegida.

## Criterio de aceite da execucao 01

Tudo abaixo deve estar verdadeiro:

1. API aceita `direct_customer`;
2. cadastro OTP pode devolver onboarding para jornada diferente de `/plans`;
3. evento passa a salvar `commercial_mode`;
4. evento passa a devolver `current_entitlements_json`, mesmo que inicial/parcial;
5. `GET /events/{id}/commercial-status` responde com status coerente;
6. frontend respeita `next_path`;
7. nenhum fluxo atual de parceiro e assinatura quebra.

## Riscos Da Execucao 01

Risco 1:

- espalhar `journey_type` sem contrato claro.

Mitigacao:

- centralizar valores permitidos em enum ou value object.

Risco 2:

- `commercial-status` nascer acoplado demais ao modelo atual e precisar reescrita total depois.

Mitigacao:

- tratar esta primeira resposta como read model transitoria;
- manter nomes que ja antecipem grants e entitlements futuros.

Risco 3:

- ajustar onboarding e quebrar o cadastro atual.

Mitigacao:

- manter default `partner_signup` quando nada vier informado.

## Definicao De Pronto Por Milestone

Um milestone so deve ser considerado concluido quando:

1. schema, model e resource estiverem alinhados;
2. houver teste cobrindo o contrato principal;
3. existir ao menos uma rota ou tela usando a capacidade nova;
4. a doc de discovery ainda continuar valida ou for atualizada.

## O Que Vem Imediatamente Depois Da Execucao 01

Assim que a execucao 01 entrar, a proxima entrega recomendada e:

- M2-T1 `event_access_grants`
- M2-T2 `EntitlementResolverService`

Motivo:

- sem essas duas pecas, o sistema continua sem motor oficial para trial, bonus e compra direta;
- com elas, o frontend passa a ler direito de uso do evento em vez de inferir demais a partir da conta.

## Veredito De Execucao

Se o objetivo for destravar o produto sem perder contexto, a ordem correta e:

1. fundacao de jornada e estado comercial do evento;
2. grants e entitlements;
3. catalogo avulso;
4. jornadas publicas e admin;
5. financeiro real;
6. limpeza dos acoplamentos antigos.

Essa ordem preserva o que ja existe no repo e ataca primeiro o maior problema atual: o produto ainda pensa demais em assinatura da conta e de menos na ativacao comercial do evento.
