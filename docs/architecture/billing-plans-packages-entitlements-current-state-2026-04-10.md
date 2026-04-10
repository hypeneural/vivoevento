# Planos, pacotes e entitlements do Eventovivo - analise atual

## Objetivo

Este documento consolida, em `2026-04-10`, a estrutura real de planos e pacotes do Eventovivo, com foco em:

- como os planos recorrentes de parceiro funcionam hoje;
- como os pacotes de compra unica por evento funcionam hoje;
- quais tabelas, APIs, services, actions e telas ja existem;
- como trial, bonus e manual override estao modelados;
- quais limites ja sao projetados no evento ou na organizacao;
- quais limites ainda nao sao realmente aplicados;
- como evoluir para planos comercialmente funcionais, com nome, descricao, valor, meios de pagamento, parcelas, limites, recursos e uso mensal;
- como tratar planos sem limite e ajustes comerciais por parceiro sem alterar o catalogo global.

Documentos relacionados:

- `docs/architecture/billing-pagarme-v5-recurring-subscriptions-2026-04-09.md`
- `docs/architecture/billing-pagarme-v5-recurring-subscriptions-execution-plan-2026-04-09.md`
- `docs/architecture/billing-pagarme-v5-single-event-checkout.md`
- `docs/architecture/public-event-checkout-v2-implementation-plan-2026-04-08.md`
- `docs/architecture/billing-subscriptions-discovery.md`

## Veredito executivo

O Eventovivo ja tem uma base correta para dois modelos comerciais diferentes:

1. `plans` para recorrencia da organizacao parceira.
2. `event_packages` para compra avulsa de um evento.

Essa separacao esta certa e deve ser mantida.

O que ainda falta para os planos virarem produto funcional de verdade nao e a existencia do catalogo. O catalogo existe. O gap esta em quatro camadas:

1. falta um contrato tipado de features e limites;
2. falta ledger/contador de uso para limites mensais e por evento;
3. falta enforcement sistematico nos modulos operacionais;
4. falta CRUD/admin comercial para configurar planos, pacotes, parcelas, trial, bonus e overrides por parceiro sem alterar seeders/codigo.

Hoje a recorrencia Pagar.me ja caminha na direcao correta para cobrar o parceiro mensalmente. Mas os limites do plano recorrente ainda funcionam mais como snapshot/feature flag do que como controle operacional completo.

Para o caso recorrente de cerimonialistas/parceiros, a melhor abordagem e:

- o plano recorrente gera um `pool mensal da organizacao`;
- eventos do parceiro consomem esse pool;
- limites como `1000 midias/mes` e `4 eventos ativos` precisam ser medidos por ciclo de assinatura;
- recursos como wall, play, galeria, IA, face search e canais devem ser capabilities do plano;
- excecoes por parceiro devem ser `overrides auditaveis`, nao edicao direta do plano global.

Para a compra unica:

- o pacote avulso deve continuar vinculado ao evento;
- a quota deve ser de escopo do evento, nao mensal;
- parcelas no cartao podem existir, mas hoje o frontend publico envia `installments = 1`;
- o backend ja aceita `installments` de `1` a `24` no checkout publico, mas falta expor isso como regra comercial do pacote.

## Codigo revisado

### Catalogo recorrente

- `apps/api/database/migrations/2024_01_01_000005_create_plans_tables.php`
- `apps/api/database/migrations/2024_01_01_000006_create_subscriptions_table.php`
- `apps/api/database/migrations/2026_04_09_180000_expand_plan_prices_for_recurring_gateway_plans.php`
- `apps/api/database/migrations/2026_04_09_181000_expand_subscriptions_for_recurring_gateway_state.php`
- `apps/api/database/seeders/PlansSeeder.php`
- `apps/api/app/Modules/Plans/Models/Plan.php`
- `apps/api/app/Modules/Plans/Models/PlanPrice.php`
- `apps/api/app/Modules/Plans/Models/PlanFeature.php`
- `apps/api/app/Modules/Plans/Http/Controllers/PlanController.php`
- `apps/api/app/Modules/Plans/routes/api.php`
- `apps/api/app/Modules/Billing/Actions/CreateSubscriptionCheckoutAction.php`
- `apps/api/app/Modules/Billing/Http/Requests/StoreSubscriptionCheckoutRequest.php`
- `apps/api/app/Modules/Billing/Services/PlanSnapshotService.php`
- `apps/api/app/Modules/Billing/Services/OrganizationEntitlementResolverService.php`

### Compra unica por evento

- `apps/api/database/migrations/2026_04_01_150000_create_event_package_tables.php`
- `apps/api/database/migrations/2026_04_01_140000_create_event_access_grants_table.php`
- `apps/api/database/migrations/2026_04_01_191000_add_package_id_to_event_purchases_table.php`
- `apps/api/database/migrations/2026_04_01_194000_create_billing_order_tables.php`
- `apps/api/database/migrations/2026_04_01_194100_add_billing_order_id_to_event_purchases_table.php`
- `apps/api/database/seeders/EventPackagesSeeder.php`
- `apps/api/app/Modules/Billing/Models/EventPackage.php`
- `apps/api/app/Modules/Billing/Models/EventPackagePrice.php`
- `apps/api/app/Modules/Billing/Models/EventPackageFeature.php`
- `apps/api/app/Modules/Billing/Models/EventPurchase.php`
- `apps/api/app/Modules/Billing/Models/EventAccessGrant.php`
- `apps/api/app/Modules/Billing/Actions/CreatePublicEventCheckoutAction.php`
- `apps/api/app/Modules/Billing/Actions/ConfirmPublicEventCheckoutAction.php`
- `apps/api/app/Modules/Billing/Actions/ActivatePaidEventPackageOrderAction.php`
- `apps/api/app/Modules/Billing/Actions/CreateEventPackageGatewayCheckoutAction.php`
- `apps/api/app/Modules/Billing/Http/Requests/StorePublicEventCheckoutRequest.php`
- `apps/api/app/Modules/Billing/Http/Resources/EventPackageResource.php`
- `apps/api/app/Modules/Billing/Queries/ListEventPackagesQuery.php`
- `apps/api/app/Modules/Billing/Services/EventPackageSnapshotService.php`
- `apps/api/app/Modules/Billing/Services/EventPackageCheckoutMarketingService.php`
- `apps/api/app/Modules/Billing/Services/Pagarme/PagarmeOrderPayloadFactory.php`

### Entitlements, trial e frontend

- `apps/api/app/Modules/Billing/Services/EntitlementResolverService.php`
- `apps/api/app/Modules/Billing/Actions/SyncEventEntitlementsAction.php`
- `apps/api/app/Modules/Billing/Actions/SyncOrganizationEventEntitlementsAction.php`
- `apps/api/app/Modules/Events/Support/EventCommercialStatusService.php`
- `apps/api/app/Modules/Events/Actions/CreateEventAction.php`
- `apps/api/app/Modules/Events/Actions/UpdateEventAction.php`
- `apps/api/app/Modules/Events/Actions/SyncEventIntakeChannelsAction.php`
- `apps/api/app/Modules/Billing/Actions/CreatePublicTrialEventAction.php`
- `apps/api/app/Modules/Billing/Actions/CreateAdminQuickEventAction.php`
- `apps/api/app/Modules/Billing/Http/Requests/StoreAdminQuickEventRequest.php`
- `apps/web/src/modules/plans/PlansPage.tsx`
- `apps/web/src/modules/plans/components/RecurringPlanCheckoutDialog.tsx`
- `apps/web/src/modules/billing/public-checkout/PublicCheckoutPageV2.tsx`
- `apps/web/src/modules/billing/public-checkout/support/checkoutFormUtils.ts`

## Testes relevantes ja existentes

- `apps/api/tests/Unit/Billing/EntitlementResolverServiceTest.php`
- `apps/api/tests/Unit/Billing/PlanSnapshotServiceTest.php`
- `apps/api/tests/Feature/Billing/EventPackageCatalogTest.php`
- `apps/api/tests/Feature/Billing/PublicEventCheckoutTest.php`
- `apps/api/tests/Feature/Billing/PublicTrialEventTest.php`
- `apps/api/tests/Feature/Billing/AdminQuickEventTest.php`
- `apps/api/tests/Feature/Billing/BillingTest.php`
- `apps/api/tests/Feature/Billing/RecurringSubscriptionCheckoutTest.php`
- `apps/api/tests/Feature/Events/EventCommercialStatusTest.php`
- `apps/api/tests/Feature/Events/EventCommercialEnvelopeCharacterizationTest.php`
- `apps/api/tests/Feature/InboundMedia/PublicUploadCommercialEnvelopeCharacterizationTest.php`
- `apps/api/tests/Feature/Events/EventIntakeChannelsTest.php`

## Os dois modelos comerciais

## 1. Compra unica por evento

Publico esperado:

- noiva;
- debutante;
- aniversariante;
- cerimonial que nao quer assinatura mensal;
- cliente final que quer comprar um evento especifico.

Unidade comercial correta:

- `event`;
- um pacote comprado libera aquele evento;
- o pagamento gera `billing_order`, `payment`, `invoice`, `event_purchase` e `event_access_grant`.

Catalogo atual:

- `event_packages`
- `event_package_prices`
- `event_package_features`

Seeder atual:

- `essential-event`
- `interactive-event`
- `premium-event`

Recursos atuais dos pacotes seeded:

| Pacote | Valor | Retencao | Midias | Hub/Galeria | Wall | Play | White label |
| --- | ---: | ---: | ---: | --- | --- | --- | --- |
| `essential-event` | R$ 99,00 | 30 dias | 150 | sim | nao | nao | nao |
| `interactive-event` | R$ 199,00 | 90 dias | 400 | sim | sim | nao | nao |
| `premium-event` | R$ 299,00 | 180 dias | 800 | sim | sim | sim | sim |

Observacao: a tabela acima representa o estado do `EventPackagesSeeder`, nao uma tabela comercial oficial final.

Fluxo atual:

1. `GET /api/v1/public/event-packages` lista pacotes ativos para compra publica.
2. `POST /api/v1/public/event-checkouts` cria usuario/organizacao quando necessario, cria evento e cria `BillingOrder`.
3. O gateway configurado para `event_package` cria o pagamento.
4. `POST /api/v1/public/event-checkouts/{billingOrder:uuid}/confirm` ou webhook confirma.
5. `ActivatePaidEventPackageOrderAction` cria `EventPurchase`.
6. A mesma action cria `EventAccessGrant` de `source_type=event_purchase`.
7. `EventCommercialStatusService` recalcula `commercial_mode=single_purchase` e `current_entitlements_json`.

Estado do pagamento:

- Pix ja e primeira classe no checkout publico.
- Cartao ja existe no request e no payload Pagar.me.
- Backend aceita `payment.credit_card.installments` entre `1` e `24`.
- Frontend V2 hoje envia `installments: 1` fixo.
- Nao existe ainda configuracao por pacote para maximo de parcelas, juros, valor minimo de parcela ou meios permitidos.

Conclusao para compra unica:

- o modelo correto ja existe;
- o pacote avulso ja tem nome, descricao, valor e features;
- o checkout publico ja tokeniza cartao no submit;
- ainda falta transformar parcelas e meios de pagamento em configuracao comercial do pacote, nao constante do frontend.

## 2. Recorrencia mensal para parceiros

Publico esperado:

- cerimonialista;
- fotografo;
- assessoria;
- parceiro que opera varios eventos por mes;
- equipe que paga mensalmente para ter pacotes/limites recorrentes.

Unidade comercial correta:

- `organization`;
- a assinatura pertence a organizacao parceira;
- usuarios acessam por membership/permissao;
- eventos da organizacao sao cobertos pela assinatura enquanto ela esta ativa.

Catalogo atual:

- `plans`
- `plan_prices`
- `plan_features`
- `subscriptions`
- `subscription_cycles`

Seeder atual:

- `starter`
- `professional`
- `business`

Recursos atuais dos planos seeded:

| Plano | Mensal | Anual | Eventos ativos | Retencao | Wall | Play | WhatsApp | White label |
| --- | ---: | ---: | ---: | ---: | --- | --- | --- | --- |
| `starter` | R$ 49,00 | nao seedado | 3 | 30 dias | sim | nao | sim | nao |
| `professional` | R$ 99,00 | R$ 999,00 | 10 | 90 dias | sim | sim | sim | nao |
| `business` | R$ 199,00 | R$ 1.999,00 | 50 | 180 dias | sim | sim | sim | sim |

Fluxo Pagar.me atual:

1. `/plans` lista catalogo via `GET /api/v1/plans`.
2. Usuario escolhe plano/ciclo.
3. Frontend coleta pagador, endereco e cartao.
4. Cartao e tokenizado apenas no submit final.
5. Backend recebe `card_token`.
6. `CreateSubscriptionCheckoutAction` resolve `PlanPrice`.
7. `PagarmeSubscriptionGatewayService::ensurePlan()` cria ou reutiliza `gateway_plan_id`.
8. `PagarmeSubscriptionGatewayService::createSubscription()` cria assinatura Pagar.me com `POST /subscriptions`.
9. `subscriptions` recebe ids externos, status de contrato, billing e acesso.
10. Eventos da organizacao sao recalculados por `SyncOrganizationEventEntitlementsAction`.

Regra atual de troca de plano:

- se o usuario escolhe o mesmo plano e mesmo ciclo, o checkout e bloqueado;
- se escolhe outro plano ou outro ciclo, o sistema cria uma nova assinatura no provider e tenta cancelar a assinatura anterior no Pagar.me;
- a assinatura local e atualizada por `organization_id`.

Ponto de atencao:

- ainda nao existe proration comercial;
- ainda nao existe uma tabela de historico de mudanca de plano;
- o modelo atual e pragmatico para P0, mas precisa evoluir antes de vender upgrades/downgrades complexos.

Conclusao para recorrencia:

- a cobranca mensal Pagar.me ja esta no caminho certo;
- a assinatura esta corretamente vinculada ao parceiro/organizacao;
- ainda falta transformar os limites do plano em uso real por ciclo mensal.

## Banco de dados atual

## Catalogo recorrente

### `plans`

Campos principais:

- `code`
- `name`
- `audience`
- `status`
- `description`

Leitura:

- representa o produto recorrente da organizacao;
- hoje nao possui colunas de categoria, destaque, CTA, ordem comercial, se e publico, se e customizavel ou se e plano sob consulta.

### `plan_prices`

Campos principais:

- `plan_id`
- `billing_cycle`
- `currency`
- `amount_cents`
- `gateway_provider`
- `gateway_price_id`
- `gateway_plan_id`
- `gateway_plan_payload_json`
- `billing_type`
- `billing_day`
- `trial_period_days`
- `payment_methods_json`
- `is_default`

Leitura:

- ja comporta mensal/anual;
- ja comporta `gateway_plan_id` da Pagar.me;
- ja tem `trial_period_days`, mas o trial recorrente ainda nao aparece como experiencia final de produto;
- nao tem `max_installments` porque recorrencia deve usar `installments = 1`.

### `plan_features`

Campos principais:

- `plan_id`
- `feature_key`
- `feature_value`

Leitura:

- e a fonte atual de capacidades e limites do plano;
- usa strings livres;
- nao tem schema tipado;
- nao diferencia limite, feature, marketing copy, quota mensal, quota por evento, boolean, enum, dinheiro ou unidade.

## Assinatura recorrente

### `subscriptions`

Campos principais:

- `organization_id`
- `plan_id`
- `plan_price_id`
- `status`
- `billing_cycle`
- `payment_method`
- `starts_at`
- `trial_ends_at`
- `current_period_started_at`
- `current_period_ends_at`
- `renews_at`
- `next_billing_at`
- `ends_at`
- `cancel_at_period_end`
- `gateway_provider`
- `gateway_customer_id`
- `gateway_plan_id`
- `gateway_card_id`
- `gateway_subscription_id`
- `contract_status`
- `billing_status`
- `access_status`
- `metadata_json`

Leitura:

- representa a assinatura da organizacao;
- ja separa contrato, billing e acesso;
- ainda nao representa historico de trocas;
- ainda nao representa overrides comerciais por parceiro como primeira classe;
- `trial_ends_at` existe, mas o fluxo de trial recorrente ainda nao esta productizado.

### `subscription_cycles`

Campos principais:

- `subscription_id`
- `gateway_cycle_id`
- `status`
- `billing_at`
- `period_start_at`
- `period_end_at`
- `closed_at`
- `raw_gateway_json`

Leitura:

- e a entidade correta para competencia mensal;
- ainda precisa virar a base tambem dos contadores de uso mensal;
- hoje cobre reconciliacao financeira, nao consumo de recursos.

## Compra unica por evento

### `event_packages`

Campos principais:

- `code`
- `name`
- `description`
- `target_audience`
- `is_active`
- `sort_order`

Leitura:

- representa o produto avulso de evento;
- ja separa publico-alvo: `direct_customer`, `partner`, `both`;
- nao tem CRUD administrativo completo;
- catalogo seeded ainda e a fonte principal.

### `event_package_prices`

Campos principais:

- `event_package_id`
- `billing_mode`
- `currency`
- `amount_cents`
- `is_active`
- `is_default`

Leitura:

- hoje modela compra unica simples;
- falta `payment_methods_json`;
- falta `max_installments`;
- falta `min_installment_cents`;
- falta politica de juros/desconto por Pix;
- falta id externo opcional de produto/preco se a equipe quiser rastreabilidade no gateway.

### `event_package_features`

Campos principais:

- `event_package_id`
- `feature_key`
- `feature_value`

Leitura:

- guarda capacidades, limites e tambem copy de checkout (`checkout.*`);
- funciona para o MVP;
- mistura regras tecnicas com marketing;
- precisa evoluir para schema tipado ou para separar `package_features` de `package_marketing`.

### `event_purchases`

Campos principais:

- `organization_id`
- `client_id`
- `event_id`
- `billing_order_id`
- `plan_id`
- `package_id`
- `price_snapshot_cents`
- `currency`
- `features_snapshot_json`
- `status`
- `purchased_by_user_id`
- `purchased_at`

Leitura:

- registra a compra avulsa efetivada;
- ainda convive com `plan_id` legado;
- para novas compras, `package_id` e `billing_order_id` sao o caminho correto.

### `event_access_grants`

Campos principais:

- `organization_id`
- `event_id`
- `source_type`
- `source_id`
- `package_id`
- `status`
- `priority`
- `merge_strategy`
- `starts_at`
- `ends_at`
- `features_snapshot_json`
- `limits_snapshot_json`
- `granted_by_user_id`
- `notes`
- `metadata_json`

Leitura:

- e a melhor entidade do modelo atual;
- separa pagamento de direito de acesso;
- representa compra avulsa, trial, bonus e manual override;
- permite expirar acesso;
- permite sobrepor, expandir ou restringir capacidades.

## APIs atuais

## Planos recorrentes

- `GET /api/v1/plans`
- `GET /api/v1/plans/{id}`
- `GET /api/v1/plans/current`
- `GET /api/v1/billing/subscription`
- `GET /api/v1/billing/subscription/cards`
- `PATCH /api/v1/billing/subscription/card`
- `POST /api/v1/billing/subscription/reconcile`
- `POST /api/v1/billing/subscription/cancel`
- `GET /api/v1/billing/invoices`
- `POST /api/v1/billing/checkout`
- `GET /api/v1/subscriptions`
- `GET /api/v1/subscriptions/{id}`

Ponto de atencao:

- `Route::apiResource('plans', PlanController::class)` registra mais rotas do que o controller implementa;
- na pratica, o controller atual so implementa `index` e `show`;
- nao existe CRUD administrativo completo de plano/preco/feature.

## Pacotes avulsos

- `GET /api/v1/public/event-packages`
- `POST /api/v1/public/event-checkouts`
- `GET /api/v1/public/event-checkouts/{billingOrder:uuid}`
- `POST /api/v1/public/event-checkouts/{billingOrder:uuid}/confirm`
- `POST /api/v1/webhooks/billing/{provider}`
- `GET /api/v1/event-packages`
- `GET /api/v1/event-packages/{eventPackage}`

Ponto de atencao:

- o catalogo publico lista pacotes;
- o catalogo autenticado lista e mostra pacotes;
- nao existe CRUD administrativo completo de pacote/preco/feature.

## Trial e bonificacao

- `POST /api/v1/public/trial-events`
- `POST /api/v1/admin/quick-events`

Ponto de atencao:

- trial publico e hardcoded em action;
- bonus admin exige `package_id`;
- manual override pode ser package-less se informar features ou limits;
- nao ha tela administrativa dedicada para configurar templates de trial/bonus.

## Entitlements de evento e acesso

- `GET /api/v1/events/{id}/commercial-status`
- `GET /api/v1/auth/me`
- `GET /api/v1/access/matrix`

Leitura:

- o estado resolvido ja chega ao frontend;
- parte do painel ja usa isso para modulos e contexto comercial;
- enforcement ainda e parcial.

## Como os entitlements funcionam hoje

## Resolver por evento

`EntitlementResolverService` monta o estado efetivo de um evento a partir de:

1. baseline do evento;
2. assinatura ativa da organizacao;
3. compra avulsa paga;
4. grants ativos.

O resultado e salvo em:

- `events.commercial_mode`
- `events.current_entitlements_json`

Modos comerciais atuais:

- `none`
- `subscription_covered`
- `trial`
- `single_purchase`
- `bonus`
- `manual_override`

Estrutura resolvida atual:

- `modules.live`
- `modules.wall`
- `modules.play`
- `modules.hub`
- `limits.retention_days`
- `limits.max_active_events`
- `limits.max_photos`
- `branding.watermark`
- `branding.white_label`
- `channels.whatsapp_groups.enabled`
- `channels.whatsapp_groups.max`
- `channels.whatsapp_direct.enabled`
- `channels.public_upload.enabled`
- `channels.telegram.enabled`
- `channels.blacklist.enabled`
- `channels.whatsapp.default_instance.required`
- `channels.whatsapp.shared_instance.enabled`
- `channels.whatsapp.dedicated_instance.enabled`
- `channels.whatsapp.dedicated_instance.max_per_event`
- `channels.whatsapp.feedback.reject_reply.enabled`
- `channels.whatsapp.feedback.reject_reply.message`

## Resolver por organizacao

`OrganizationEntitlementResolverService` monta entitlements da conta a partir da assinatura ativa.

Estrutura atual:

- `modules.live_gallery`
- `modules.wall`
- `modules.play`
- `modules.hub`
- `modules.whatsapp_ingestion`
- `modules.analytics_advanced`
- `limits.max_active_events`
- `limits.retention_days`
- `branding.white_label`
- `branding.custom_domain`

Esse payload alimenta:

- `/auth/me`
- `/access/matrix`
- visibilidade de modulos no frontend.

## Precedencia atual

Na pratica:

1. assinatura ativa aplica baseline com `replace`;
2. compra avulsa paga pode aplicar pacote do evento;
3. grants ativos aplicam `expand`, `replace` ou `restrict`;
4. o `source_type` de maior prioridade define `commercial_mode`.

Prioridades atuais:

- `manual_override`: 1000
- `bonus`: 900
- `event_purchase`: 800
- `subscription`: 500
- `trial`: 100

Leitura correta:

- pagamento e acesso ja estao desacoplados;
- grants sao a ferramenta correta para liberar evento;
- ainda falta ledger de uso e enforcement.

## Trial gratis hoje

O trial publico existe por `CreatePublicTrialEventAction`.

Ele cria:

- usuario;
- organizacao;
- membership `partner-owner`;
- evento;
- grant `trial`;
- token de acesso;
- onboarding para o evento.

Configuracao hardcoded atual:

- retencao: `7` dias;
- eventos ativos: `1`;
- midias: `20`;
- modules: `live=true`, `hub=true`, `wall=false`, `play=false`;
- watermark: `true`;
- grant expira em `now() + 7 dias`.

Pontos fortes:

- trial nao cria assinatura fake;
- trial usa `EventAccessGrant`;
- trial fica auditavel;
- trial nao depende de pagamento.

Gaps:

- nao e configuravel por admin;
- nao nasce de um catalogo de trials;
- nao tem controle claro de conversao para pacote pago;
- nao tem politica comercial editavel para duracao, limite, watermark e modulos;
- nao ha uso/anti-abuse alem das validacoes de identidade existentes.

## Bonus e manual override hoje

O admin quick event existe por `CreateAdminQuickEventAction`.

Ele cria:

- usuario responsavel ou reutiliza usuario;
- organizacao ou reutiliza organizacao;
- membership;
- evento;
- grant comercial;
- opcionalmente entrega acesso por WhatsApp.

Tipos aceitos:

- `bonus`
- `manual_override`

Regras atuais:

- `bonus` exige `package_id`;
- `manual_override` pode usar pacote ou pode ser package-less;
- manual override sem pacote precisa informar `features` ou `limits`;
- `starts_at`, `ends_at`, `reason`, `origin` e `notes` existem no request.

Pontos fortes:

- bonificacao nao cria assinatura falsa;
- fica auditavel via grant e activity log;
- pode ter prazo;
- pode expandir, substituir ou restringir features.

Gaps:

- nao ha tela comercial final para editar grants existentes;
- nao ha templates administraveis de cortesia;
- nao ha bonus no nivel da organizacao/parceiro recorrente;
- nao ha ledger de consumo para saber quanto da cortesia foi usado.

## Estado atual dos limites por recurso

## O que ja existe como feature/limite

O modelo atual ja consegue representar:

- numero maximo de eventos ativos (`events.max_active`);
- retencao de midias (`media.retention_days`);
- numero maximo de fotos/midias por evento (`media.max_photos`);
- wall/telao (`wall.enabled`);
- play/jogos (`play.enabled`);
- hub/galeria base (`hub.enabled` e `live.enabled`);
- white label (`white_label.enabled`);
- watermark (`gallery.watermark`);
- WhatsApp generico (`channels.whatsapp`);
- WhatsApp grupos (`channels.whatsapp_groups.enabled`, `channels.whatsapp_groups.max`);
- WhatsApp direto (`channels.whatsapp_direct.enabled`);
- link de upload publico (`channels.public_upload.enabled`);
- Telegram (`channels.telegram.enabled`);
- blacklist de remetente (`channels.blacklist.enabled`);
- instancia compartilhada/dedicada (`channels.whatsapp.shared_instance.enabled`, `channels.whatsapp.dedicated_instance.enabled`, `channels.whatsapp.dedicated_instance.max_per_event`);
- feedback automatico de rejeicao (`channels.whatsapp.feedback.reject_reply.*`).

## O que existe no produto, mas ainda nao esta bem amarrado ao plano

Existem modulos reais para:

- reconhecimento facial / busca por rosto;
- moderacao por IA;
- VLM/media intelligence;
- wall;
- play;
- gallery/hub;
- intake por WhatsApp, link e Telegram.

Mas nem todos estao em um contrato comercial forte.

Gaps claros:

- nao ha feature key oficial para `face_search.enabled` no resolver;
- nao ha enforcement comercial impedindo ativar face search sem entitlement;
- nao ha feature key oficial para `content_moderation.ai.enabled`;
- teste de caracterizacao mostra que ainda e possivel trocar evento para `moderation_mode=ai` sem entitlement comercial;
- ainda e possivel habilitar `wall` e `play` no evento mesmo quando os entitlements resolvidos dizem `false`;
- upload publico ainda aceita midia mesmo quando o canal esta desabilitado ou quando `media.max_photos` foi atingido;
- `retention_days` bruto do evento ainda pode divergir acima do limite comercial resolvido.

Esses testes sao importantes porque documentam o gap real:

- `EventCommercialEnvelopeCharacterizationTest`
- `PublicUploadCommercialEnvelopeCharacterizationTest`

## Enforcement atual por area

| Area | Estado atual | Comentario |
| --- | --- | --- |
| Visibilidade de modulos no menu | Parcial | `/auth/me` usa entitlements da organizacao. |
| Configuracao de canais do evento | Bom para configuracao | `SyncEventIntakeChannelsAction` bloqueia canais nao permitidos ao configurar. |
| Upload publico | Fraco | Testes mostram que upload ainda passa sem canal ativo e acima do limite. |
| Quantidade de grupos WhatsApp | Parcial | Enforced na configuracao dos grupos. |
| Instancia dedicada | Parcial | Enforced na configuracao e evita conflito de instancia dedicada. |
| Wall/Play por evento | Fraco | Pode habilitar modulo mesmo se entitlement resolvido estiver falso. |
| Moderacao IA | Fraco | Pode mudar `moderation_mode` para `ai` sem entitlement. |
| Face search | Fraco | Config existe, mas billing gate nao esta formalizado. |
| Retencao | Fraco | Campo bruto pode exceder limite resolvido. |
| Limite de midias | Fraco | Nao ha contador/bloqueio consistente no upload/ingestao. |
| Eventos ativos do plano | Fraco | Exposto em entitlement, mas sem bloqueio central na criacao/ativacao. |
| Pool mensal da recorrencia | Inexistente | Nao ha ledger de uso por ciclo. |

## Problema central da recorrencia

O caso de parceiro recorrente nao deve ser tratado como "cada evento recebe o mesmo pacote completo sem medir consumo".

Exemplo do produto:

- plano mensal tem `1000 midias`;
- retencao de `6 meses`;
- ate `4 eventos`;
- IA em ate `4 eventos`;
- wall e galeria em todos;
- midias podem ser distribuidas entre os eventos.

Hoje o sistema consegue dizer:

- o parceiro tem plano X;
- eventos estao cobertos por assinatura;
- cada evento tem um snapshot de entitlements.

Mas ainda nao consegue garantir:

- quantas midias ja foram consumidas no ciclo atual;
- quanto ainda resta do pool mensal;
- quais eventos estao alocados no plano;
- se o quinto evento deve ser bloqueado, cobrado avulso ou exigir upgrade;
- se a IA foi consumida em 4 eventos e deve bloquear o quinto;
- se um evento avulso tem quota propria separada do pool mensal;
- se um parceiro recebeu override customizado sem alterar o catalogo global.

## Abordagem recomendada para recorrencia

## 1. Separar escopos de entitlement

Cada feature/limite precisa declarar escopo:

- `organization`: vale para a conta inteira;
- `subscription_cycle`: reseta a cada ciclo mensal/anual;
- `event`: vale para um evento especifico;
- `event_lifetime`: vale durante a vida do evento;
- `channel`: vale para configuracao de intake;
- `module`: vale para habilitacao de modulo.

Exemplos:

| Feature key alvo | Escopo | Reset | Observacao |
| --- | --- | --- | --- |
| `events.active.max` | organization | continuo | maximo de eventos ativos cobertos pelo plano. |
| `media.monthly.max` | subscription_cycle | mensal | pool mensal compartilhado entre eventos. |
| `media.event.max` | event_lifetime | nao | quota especifica de pacote avulso. |
| `media.retention_days` | event | nao | politica aplicada ao evento. |
| `content_moderation.ai.events.max` | subscription_cycle | mensal | quantidade de eventos com IA no ciclo. |
| `face_search.enabled` | event/module | nao | habilita busca por rosto no evento. |
| `channels.whatsapp_groups.max` | event | nao | maximo de grupos por evento. |
| `channels.whatsapp.dedicated_instance.max_per_event` | event | nao | controla instancia dedicada. |

## 2. Criar ledger de uso

Para planos recorrentes, precisa existir uma trilha de consumo.

Sugestao pragmatica:

- `entitlement_usage_periods`
- `entitlement_usage_counters`
- opcionalmente `entitlement_usage_events` para auditoria fina.

### `entitlement_usage_periods`

Campos recomendados:

- `organization_id`
- `subscription_id`
- `subscription_cycle_id`
- `period_start_at`
- `period_end_at`
- `status`
- `source_type`
- `source_id`
- `metadata_json`

### `entitlement_usage_counters`

Campos recomendados:

- `usage_period_id`
- `event_id` nullable
- `feature_key`
- `scope`
- `limit_value`
- `used_value`
- `reserved_value`
- `unlimited`
- `unit`
- `last_calculated_at`

Exemplos:

- `media.monthly.max`: usado 742 de 1000;
- `events.active.max`: usado 3 de 4;
- `content_moderation.ai.events.max`: usado 2 de 4;
- `face_search.indexed_media.max`: usado 500 de 1000;
- `storage.bytes.max`: usado X de Y, se entrar no produto.

## 3. Criar alocacao de eventos cobertos pelo plano

Para plano recorrente, nao basta dizer que todo evento da organizacao esta coberto.

Sugestao:

- `subscription_event_allocations`

Campos recomendados:

- `organization_id`
- `subscription_id`
- `subscription_cycle_id`
- `event_id`
- `status`
- `allocated_at`
- `released_at`
- `allocation_source`
- `metadata_json`

Uso:

- ao criar/ativar evento, aloca o evento no plano;
- se passou de `events.active.max`, bloqueia ou exige upgrade/compra avulsa;
- evento avulso pago pode ficar fora do pool recorrente;
- evento bonificado pode ficar fora ou dentro do pool, conforme regra do grant.

## 4. Manter `event_access_grants` como projecao do evento

Mesmo com pool mensal, `event_access_grants` continua util.

Papel recomendado:

- representa o direito efetivo daquele evento;
- pode ser originado por compra avulsa, trial, bonus, manual override ou alocacao de assinatura;
- congela capacidades que o evento recebeu;
- facilita suporte e auditoria.

Ponto de decisao:

- para recorrencia, o grant pode ser sintetico por evento alocado;
- ou o evento pode resolver diretamente a assinatura sem criar grant.

Recomendacao:

- manter resolucao direta da assinatura no curto prazo;
- introduzir `subscription_event_allocations` antes de criar grants sinteticos;
- criar grants sinteticos so se o suporte precisar enxergar cobertura evento a evento com historico.

## 5. Representar ilimitado explicitamente

Hoje `null` pode significar:

- sem limite;
- limite nao configurado;
- erro de seed;
- recurso nao aplicavel.

Isso e perigoso.

Recomendacao:

- cada limite deve ter `unlimited=true/false`;
- `limit_value=null` so quando `unlimited=true` ou `not_applicable`;
- no contrato comercial, usar tipo estruturado, nao apenas string.

Exemplo:

```json
{
  "feature_key": "media.monthly.max",
  "type": "integer_limit",
  "scope": "subscription_cycle",
  "unit": "media",
  "limit_value": null,
  "unlimited": true
}
```

## Ajustes por parceiro

O produto precisa permitir:

- planos sem limitacao;
- parceiros com condicoes customizadas;
- ajustes comerciais pontuais;
- upgrades negociados.

Nao recomendo editar o `plan` global para isso.

Modelo recomendado:

1. `plans` permanece catalogo padrao.
2. `subscriptions` aponta para o plano contratado.
3. Overrides por parceiro ficam em tabela propria.

Sugestao:

- `organization_entitlement_overrides`

Campos recomendados:

- `organization_id`
- `subscription_id` nullable
- `feature_key`
- `value`
- `unlimited`
- `merge_strategy`
- `starts_at`
- `ends_at`
- `reason`
- `granted_by_user_id`
- `metadata_json`

Exemplos:

- Business padrao tem `events.active.max=50`, mas parceiro X tem ilimitado;
- Professional padrao tem `media.monthly.max=1000`, mas parceiro Y tem 2500 por contrato;
- parceiro Z tem `white_label.enabled=true` por acordo comercial;
- parceiro em trial comercial tem wall liberado por 15 dias.

Beneficio:

- catalogo nao perde integridade;
- suporte sabe o que foi negociado;
- auditoria mostra quem liberou;
- renovacao Pagar.me continua simples.

## Contrato tipado de features

Hoje feature keys vivem em strings espalhadas entre seeders, resources e resolvers.

Isso precisa virar contrato de plataforma.

Sugestao:

- criar `BillingFeatureDefinition` em codigo;
- ou criar tabela `billing_feature_definitions`;
- seedar a lista oficial;
- validar `plan_features`, `event_package_features`, grants e overrides contra essa lista.

Campos recomendados:

- `key`
- `label`
- `description`
- `value_type`: `boolean`, `integer`, `string`, `enum`, `json`
- `scope`: `organization`, `subscription_cycle`, `event`, `event_lifetime`, `channel`, `module`
- `unit`: `media`, `days`, `events`, `groups`, `instances`
- `default_value`
- `supports_unlimited`
- `reset_policy`: `none`, `billing_cycle`, `event_lifetime`
- `enforcement_point`
- `public_copy`

Primeira lista recomendada:

| Key | Tipo | Escopo | Enforcement |
| --- | --- | --- | --- |
| `events.active.max` | integer_limit | organization | criar/ativar evento |
| `media.monthly.max` | integer_limit | subscription_cycle | ingestao de midia |
| `media.event.max` | integer_limit | event_lifetime | ingestao de midia |
| `media.retention_days` | integer_limit | event | retencao/job/storage |
| `hub.enabled` | boolean | event/module | event settings/public route |
| `gallery.enabled` | boolean | event/module | gallery routes |
| `wall.enabled` | boolean | event/module | wall settings/player |
| `play.enabled` | boolean | event/module | play routes |
| `content_moderation.ai.enabled` | boolean | event/module | update event/moderation job |
| `content_moderation.mode` | enum | event/module | update event/settings |
| `face_search.enabled` | boolean | event/module | face settings/search/index |
| `channels.public_upload.enabled` | boolean | event/channel | upload public route |
| `channels.whatsapp_groups.enabled` | boolean | event/channel | intake config/webhook |
| `channels.whatsapp_groups.max` | integer_limit | event/channel | intake config |
| `channels.whatsapp_direct.enabled` | boolean | event/channel | session service |
| `channels.telegram.enabled` | boolean | event/channel | telegram session/webhook |
| `channels.whatsapp.shared_instance.enabled` | boolean | event/channel | intake defaults |
| `channels.whatsapp.dedicated_instance.enabled` | boolean | event/channel | intake defaults |
| `channels.whatsapp.dedicated_instance.max_per_event` | integer_limit | event/channel | intake defaults |
| `branding.white_label.enabled` | boolean | organization/event | branding settings |
| `gallery.watermark.enabled` | boolean | event | render/export |

Observacao:

- o sistema atual usa chaves como `wall.enabled`, `media.max_photos` e `white_label.enabled`;
- nao precisa quebrar isso imediatamente;
- criar aliases e migracao progressiva e mais seguro.

## Como transformar os planos em funcionais

## Plano recorrente funcional

Um plano recorrente precisa ter:

- nome comercial;
- descricao curta;
- descricao detalhada;
- publico-alvo;
- status comercial;
- ordem no catalogo;
- ciclo mensal/anual;
- valor por ciclo;
- meios de pagamento permitidos;
- trial period opcional;
- billing type Pagar.me;
- gateway plan id;
- features tipadas;
- limites tipados;
- regra de uso mensal;
- politica de upgrade/downgrade;
- politica de inadimplencia;
- politica de cancelamento;
- overrides por parceiro.

Campos que faltam ou estao fracos:

- `plans.sort_order`
- `plans.is_public`
- `plans.is_recommended`
- `plans.checkout_badge`
- `plans.marketing_json`
- `plan_prices.payment_methods_json` ja existe, mas precisa ser usado;
- `plan_prices.trial_period_days` ja existe, mas precisa virar UX/regra;
- tabela de feature definitions;
- tabela de overrides;
- tabela de usage.

## Pacote avulso funcional

Um pacote avulso precisa ter:

- nome comercial;
- descricao curta;
- beneficios;
- publico alvo;
- status publico;
- ordem;
- valor;
- meios de pagamento permitidos;
- parcelas maximas no cartao;
- desconto Pix opcional;
- features tipadas;
- limites por evento;
- duracao/retencao;
- politica de expiracao do checkout;
- politica de cancelamento/reembolso;
- regra clara de ativacao do evento.

Campos que faltam ou estao fracos:

- `event_package_prices.payment_methods_json`
- `event_package_prices.max_installments`
- `event_package_prices.min_installment_cents`
- `event_package_prices.pix_discount_cents` ou `discount_policy_json`
- `event_package_prices.gateway_product_id/gateway_price_id` opcional;
- separacao entre `event_package_features` tecnicas e marketing copy;
- feature definitions;
- usage/enforcement.

## Enforcements obrigatorios

Para os planos serem reais, cada limite precisa ter ponto de bloqueio.

## Criacao/ativacao de evento

Aplicar:

- `events.active.max`;
- elegibilidade da assinatura;
- alocacao no ciclo;
- fallback para compra avulsa ou upgrade.

Pontos de codigo:

- `CreateEventAction`
- `UpdateEventAction`
- future `ActivateEventAction`, se o produto separar draft/active.

## Modulos por evento

Aplicar:

- `wall.enabled`;
- `play.enabled`;
- `hub.enabled`;
- `gallery.enabled`;
- `face_search.enabled`;
- `content_moderation.ai.enabled`.

Pontos de codigo:

- `CreateEventAction`
- `UpdateEventAction`
- settings controllers de Wall, Play, Hub, FaceSearch, ContentModeration.

## Ingestao de midia

Aplicar:

- `media.event.max`;
- `media.monthly.max`;
- canal habilitado;
- blacklist se aplicavel;
- source type permitido.

Pontos de codigo:

- public upload controller;
- WhatsApp webhook/intake;
- Telegram webhook/intake;
- inbound media pipeline.

## Retencao

Aplicar:

- `media.retention_days`;
- job de limpeza;
- UI nao deve permitir subir acima do limite sem override.

Pontos de codigo:

- `CreateEventAction`
- `UpdateEventAction`
- retention jobs/storage cleanup.

## Canais

Aplicar:

- `channels.public_upload.enabled`;
- `channels.whatsapp_groups.enabled`;
- `channels.whatsapp_groups.max`;
- `channels.whatsapp_direct.enabled`;
- `channels.telegram.enabled`;
- instancia compartilhada/dedicada.

Pontos de codigo:

- `SyncEventIntakeChannelsAction` ja cobre parte;
- public upload ainda precisa bloquear;
- webhooks de WhatsApp/Telegram precisam respeitar o estado resolvido.

## Moderacao IA

Aplicar:

- `content_moderation.ai.enabled`;
- `content_moderation.mode`;
- quantidade de eventos com IA no ciclo, se esse for produto;
- custo/uso por midia se entrar no preco.

Pontos de codigo:

- `CreateEventAction`
- `UpdateEventAction`
- `ContentModeration` settings;
- jobs de analise.

## Face search

Aplicar:

- `face_search.enabled`;
- `face_search.public_selfie_search.enabled`;
- limites de indexacao;
- limites de buscas publicas, se necessario.

Pontos de codigo:

- `FaceSearch` settings;
- index pipeline;
- public find-me routes.

## Recomendacao de backlog

## Fase 0 - documentar contrato comercial

1. Definir lista oficial de feature keys.
2. Definir aliases de compatibilidade para chaves atuais.
3. Definir escopo de cada feature: organizacao, ciclo, evento ou canal.
4. Definir quais limites podem ser ilimitados.
5. Definir como compra avulsa interage com assinatura.

Aceite:

- doc de feature definitions aprovada;
- seeders atuais mapeados para keys oficiais;
- nenhuma nova feature comercial entra sem key oficial.

## Fase 1 - schema de features e overrides

1. Criar `billing_feature_definitions`.
2. Criar `organization_entitlement_overrides`.
3. Validar `plan_features` e `event_package_features` contra definitions.
4. Adicionar suporte a `unlimited`.
5. Atualizar resolvers para ler overrides.

Testes:

- unit de normalizacao de feature definitions;
- unit de alias legacy;
- unit de override por parceiro;
- feature de `/auth/me` refletindo override;
- feature de evento refletindo override.

## Fase 2 - uso mensal e alocacao de eventos

1. Criar `entitlement_usage_periods`.
2. Criar `entitlement_usage_counters`.
3. Criar `subscription_event_allocations`.
4. Criar action `EnsureSubscriptionUsagePeriodAction`.
5. Criar action `AllocateEventToSubscriptionAction`.
6. Criar action `ConsumeEntitlementUsageAction`.
7. Amarrar periodo ao `subscription_cycle`.

Testes:

- cria periodo de uso ao receber ciclo;
- aloca evento ate o limite;
- bloqueia evento acima de `events.active.max`;
- consome midia no pool mensal;
- consome midia no limite do pacote avulso;
- evento avulso pago nao consome pool mensal quando essa for a regra configurada.

## Fase 3 - enforcement operacional

1. Bloquear public upload se canal desabilitado.
2. Bloquear public upload se `media.event.max` ou `media.monthly.max` estourar.
3. Bloquear WhatsApp/Telegram se canal nao permitido.
4. Bloquear habilitacao de Wall/Play/Hub acima do entitlement.
5. Bloquear `moderation_mode=ai` sem entitlement.
6. Bloquear FaceSearch sem entitlement.
7. Aplicar retencao maxima no update do evento.

Testes:

- converter os characterization tests atuais de gap para testes de bloqueio;
- adicionar feature tests para cada canal;
- adicionar unit tests para o guard central.

## Fase 4 - catalogo administravel

1. CRUD de planos recorrentes.
2. CRUD de precos recorrentes.
3. CRUD de features do plano com definitions.
4. CRUD de pacotes avulsos.
5. CRUD de precos de pacote.
6. CRUD de features/marketing do pacote.
7. Tela admin para overrides por parceiro.

Testes:

- feature de permissao admin;
- validacao de feature key invalida;
- validacao de parcela maxima;
- validacao de plano sem limite;
- audit log de alteracao comercial.

## Fase 5 - UX comercial

1. `/plans` deve mostrar limites reais da conta e uso atual.
2. `/plans` deve mostrar se o plano e mensal/anual, trial e proxima cobranca.
3. Checkout recorrente deve continuar cartao e `installments=1`.
4. Compra avulsa deve permitir parcelas conforme regra do pacote.
5. Compra avulsa deve explicar recursos em linguagem de evento, nao SaaS.
6. Painel do evento deve mostrar cobertura: assinatura, compra avulsa, trial, bonus ou override.

Testes:

- Vitest de catalogo com plano ilimitado;
- Vitest de checkout avulso com parcelas;
- Vitest de trial/bonus copy;
- Vitest de uso atual no plano recorrente.

## Fase 6 - financeiro e auditoria

1. Manter recorrencia Pagar.me como motor financeiro.
2. Para compra avulsa, usar `orders` Pagar.me.
3. Registrar logs de decisao de entitlement.
4. Criar timeline comercial do evento.
5. Criar timeline comercial da organizacao.
6. Reconcile deve comparar financeiro e acesso efetivo.

Testes:

- webhook pago ativa grant;
- reembolso revoga ou marca grant conforme politica;
- cancelamento recorrente preserva acesso ate fim do ciclo quando aplicavel;
- chargeback cancela acesso conforme politica;
- logs aparecem com reason e actor.

## Decisoes de produto pendentes

1. `media.max_photos` deve contar fotos apenas ou fotos + videos + arquivos?
2. O limite recorrente e mensal por ciclo ou total enquanto assinatura estiver ativa?
3. O pacote avulso tem quota vitalicia do evento ou expira apos a retencao?
4. Evento avulso comprado por parceiro consome ou nao consome o pool mensal da assinatura?
5. O limite de IA e por evento, por midia analisada ou ambos?
6. Face search entra em todos os planos premium ou vira add-on?
7. Instancia dedicada pode ser recurso por evento ou pool da organizacao?
8. Planos sem limite devem aparecer como `Ilimitado` ou `Sob contrato`?
9. Troca de plano recorrente deve cancelar e recriar assinatura ou usar migracao/proration no provider?
10. Bonus deve sempre nascer de pacote base ou pode ter templates livres?

## Recomendacao final

O caminho correto e manter os dois catalogos:

1. `plans` para recorrencia do parceiro;
2. `event_packages` para compra unica do evento.

Mas o proximo salto nao deve ser criar mais planos no seeder. O proximo salto deve ser transformar os recursos comerciais em contrato operacional:

- feature definitions;
- limites com escopo;
- unlimited explicito;
- overrides por parceiro;
- usage por ciclo;
- allocation de eventos;
- enforcement nos modulos.

Para a recorrencia Pagar.me, o foco atual continua correto: cobrar mensalmente o parceiro com `plan + subscription`. Em paralelo, o produto precisa passar a medir e aplicar os recursos do plano no Eventovivo. Sem essa camada, a cobranca recorrente funciona financeiramente, mas o plano ainda nao controla de verdade o que o parceiro pode usar.

Para compra unica, a estrutura tambem esta no caminho certo. O pacote avulso ja vincula pagamento, evento, purchase e grant. O ajuste importante e tornar parcelas, meios de pagamento, limites e recursos editaveis por catalogo, e nao fixos no frontend ou em seeders.

