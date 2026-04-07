# Billing Admin Customer Onboarding Execution Plan

## Objetivo

Este documento transforma a discovery da jornada administrativa de onboarding
comercial em ordem real de execucao, com foco em:

- criar ou reaproveitar `User` + `Organization` sem depender de `Event`;
- gerar um `access_code` proprio e rastreavel;
- criar um Pix na Pagar.me v5 para um onboarding comercial;
- saber com precisao local se esse onboarding foi pago ou nao;
- notificar o cliente via WhatsApp com codigo, status e dados do Pix;
- permitir que esse credito pago seja associado a um evento futuro;
- fechar tudo isso com TDD e homologacao controlada.

Referencias primarias:

- [docs/architecture/billing-admin-customer-onboarding-discovery.md](./billing-admin-customer-onboarding-discovery.md)
- [docs/architecture/billing-pagarme-v5-single-event-checkout.md](./billing-pagarme-v5-single-event-checkout.md)
- [docs/architecture/billing-pagarme-v5-execution-plan.md](./billing-pagarme-v5-execution-plan.md)
- `https://docs.pagar.me/reference/criar-pedido-2`
- `https://docs.pagar.me/reference/pix-2`
- `https://docs.pagar.me/reference/obter-cobran%C3%A7a`
- `https://docs.pagar.me/reference/cancelar-cobran%C3%A7a`
- `https://docs.pagar.me/reference/eventos-de-webhook-1`

Este plano existe para responder 6 perguntas:

1. como criar uma jornada administrativa sem quebrar o checkout publico;
2. em que ordem entram schema, Pix, webhook, WhatsApp e associacao futura;
3. quais testes precisam nascer antes de cada bloco;
4. como provar que o admin sabe se foi pago ou nao sem olhar a dashboard da Pagar.me;
5. quais homologacoes manuais entram so depois da suite automatizada;
6. o que fica explicitamente fora da fase 1.

## Como usar este plano

Regra simples:

- a discovery continua sendo a fonte de diagnostico e contrato;
- este arquivo vira a fonte de backlog, sequenciamento e criterios de aceite;
- se surgir duvida de payload, status, charge ou webhook, a referencia final e
  sempre a documentacao oficial v5 da Pagar.me;
- nenhuma task de codigo fecha sem TDD;
- nenhuma homologacao manual substitui a suite automatizada;
- toda mudanca de contrato deve atualizar discovery, plano e README do modulo
  na mesma rodada.

Cada task abaixo aponta para:

- objetivo;
- referencia na discovery;
- estado atual;
- subtasks;
- checklist de TDD;
- dependencias;
- criterio de aceite;
- arquivos e areas mais provaveis de impacto.

## Status inicial

Estado consolidado em `2026-04-05`:

- [x] a discovery da jornada administrativa existe
- [x] o modulo `Billing` ja tem `BillingOrder`, `Payment`, `Invoice`,
      `BillingGatewayEvent`, `BillingOrderNotification` e `PagarmeBillingGateway`
- [x] o modulo ja sabe criar ou reaproveitar `User` + `Organization` em
      `CreateAdminQuickEventAction`
- [x] a trilha de notificacao via Z-API `send-text` ja existe
- [x] a trilha de webhook idempotente da Pagar.me ja existe
- [ ] ainda nao existe entidade canonica para o onboarding comercial
- [ ] ainda nao existe `BillingOrderMode` proprio para essa jornada
- [ ] ainda nao existe endpoint admin para criar Pix avulso sem `Event`
- [ ] ainda nao existe UX administrativa para acompanhar esse onboarding
- [ ] ainda nao existe associacao posterior do credito pago a um evento futuro

## Regra de TDD obrigatoria

Neste plano, `TDD` significa:

1. escrever ou expandir o teste antes da implementacao;
2. validar que o teste falha pela razao certa;
3. implementar o menor codigo necessario para passar;
4. rodar a suite alvo;
5. rodar a regressao do modulo;
6. atualizar a discovery, este plano e o README se o contrato mudou;
7. so entao marcar a task como concluida.

Checklist padrao de TDD para qualquer task de codigo:

- [ ] existe teste novo ou expandido antes da implementacao
- [ ] o teste cobre o contrato principal da task
- [ ] o teste falhou antes da implementacao
- [ ] a implementacao passou no teste alvo
- [ ] a regressao do modulo `Billing` permaneceu verde
- [ ] o frontend, quando impactado, passou em `vitest` e `type-check`
- [ ] a discovery, este plano e o README foram atualizados se houve mudanca de
      contrato

## Premissas fechadas

Estas decisoes devem ser tratadas como travadas para a fase 1:

- a jornada e separada do checkout publico de `event_package`;
- a jornada e separada de `admin/quick-events`;
- a fase 1 sera `Pix` apenas;
- a criacao do onboarding **nao cria `Event`**;
- o sistema gera um `access_code` proprio;
- o `access_code` e codigo comercial, nao substitui autenticacao;
- o pedido externo continua sendo criado por `POST /orders`;
- a fonte de verdade do admin e o estado local do `BillingOrder` +
  `AdminCommercialIntent`;
- o admin nao consulta a Pagar.me diretamente pela UI;
- webhook continua sendo o mecanismo principal de reconciliacao;
- o frontend ou painel administrativo deve ler apenas o estado local;
- o credito pago pode existir sem evento ate ser associado;
- a associacao ao evento futuro so pode acontecer depois do pagamento aprovado;
- chargeback fica registrado, mas a politica de produto pode ficar para rodada
  seguinte;
- o WhatsApp inicial sera `send-text`, com `access_code`, status, valor e dados
  de Pix;
- cancelamento ou estorno continuam acontecendo no backoffice ou na Pagar.me e
  chegam por webhook.

## Artefatos que o repositorio precisa ganhar

Antes de chamar essa jornada de pronta, estes artefatos devem existir no repo:

| Caminho alvo | Papel |
| --- | --- |
| `docs/architecture/billing-admin-customer-onboarding-discovery.md` | Fonte de estudo e contrato |
| `docs/architecture/billing-admin-customer-onboarding-execution-plan.md` | Fonte de backlog e ordem de execucao |
| `apps/api/app/Modules/Billing/Models/AdminCommercialIntent.php` | Entidade canonica do onboarding comercial |
| migration de `admin_commercial_intents` | Persistencia do intent, codigo e vinculo futuro |
| `apps/api/app/Modules/Billing/Enums/BillingOrderMode.php` | Novo mode `commercial_intent` |
| `apps/api/app/Modules/Billing/Actions/CreateAdminCommercialIntentAction.php` | Orquestracao de criacao |
| `apps/api/app/Modules/Billing/Actions/AttachAdminCommercialIntentToEventAction.php` | Consumo posterior do credito |
| `apps/api/app/Modules/Billing/Actions/MarkAdminCommercialIntentAsPaidAction.php` | Reconciliacao de pagamento aprovado |
| `apps/api/app/Modules/Billing/Actions/FailAdminCommercialIntentAction.php` | Reconciliacao de falha |
| `apps/api/app/Modules/Billing/Actions/RefundAdminCommercialIntentAction.php` | Reconciliacao de estorno |
| `apps/api/app/Modules/Billing/Services/AdminCommercialIntentAccessCodeService.php` | Geracao e mascara do codigo |
| `apps/api/app/Modules/Billing/Services/AdminCommercialIntentWhatsAppService.php` | Composicao de mensagem e envio |
| `apps/api/app/Modules/Billing/Http/Controllers/AdminCommercialIntentController.php` | Endpoints administrativos |
| `apps/api/app/Modules/Billing/Http/Requests/*.php` | Contratos de criacao, reenvio, refresh e attach |
| `apps/api/tests/Feature/Billing/AdminCommercialIntent*` | Cobertura HTTP e fluxo |
| `apps/api/tests/Unit/Billing/AdminCommercialIntent*` | Cobertura das pecas puras |
| `apps/web/src/modules/billing-admin/` ou area equivalente | UX administrativa |

## Regra de sequenciamento

Executar na ordem abaixo:

1. fechar naming e contrato de dominio;
2. materializar schema e `BillingOrderMode`;
3. fechar criacao de identidade e intent local;
4. integrar Pix da Pagar.me no novo mode;
5. fechar webhook e maquina de estados local;
6. ligar notificacoes por WhatsApp;
7. fechar associacao futura a evento;
8. entregar UX administrativa;
9. rodar homologacao real de Pix e webhook.

Motivo:

- sem entidade propria, o fluxo vira um desvio fragil de `event_package`;
- sem estado local, o admin volta a depender da dashboard externa;
- sem TDD na reconciliacao, replay e retry ficam opacos;
- sem separar associacao futura, o pagamento volta a ficar acoplado ao `Event`.

## M0 - Fonte unica e decisoes bloqueantes

Objetivo:

- travar naming, escopo e contratos que podem desviar a implementacao.

### TASK M0-T1 - Congelar o nome da jornada e do mode

Referencia na discovery:

- `Proposta de modelagem de dominio`

Estado atual:

- a discovery trabalha com `AdminCommercialIntent` como nome recomendado;
- `BillingOrderMode` ainda nao tem um mode proprio.

Subtasks:

1. congelar o nome canonico da entidade como `AdminCommercialIntent`;
2. congelar `BillingOrderMode::CommercialIntent`;
3. documentar que `customer_onboarding` fica apenas como nome historico de
   conversa, nao como nome de codigo;
4. alinhar isso na discovery e no README do modulo.

Checklist de TDD:

- [ ] adicionar teste de enum ou cobertura indireta que falha sem o novo mode
- [ ] garantir que fluxos antigos `subscription` e `event_package` nao quebram

Dependencias:

- nenhuma.

Criterio de aceite:

- o repo passa a ter um vocabulario unico para essa jornada.

### TASK M0-T2 - Congelar as premissas de fase 1

Referencia na discovery:

- `Regras de produto recomendadas`

Estado atual:

- a discovery ja sugere fase 1 em Pix apenas.

Subtasks:

1. documentar explicitamente que fase 1 e `Pix` apenas;
2. documentar que nao existe `Event` na criacao;
3. documentar que o credito pago pode ficar em carteira;
4. documentar que chargeback fica registrado, mas com politica separada;
5. documentar que o painel admin le apenas estado local.

Checklist de TDD:

- [ ] nao se aplica em codigo nesta task

Dependencias:

- M0-T1.

Criterio de aceite:

- o time nao tenta encaixar cartao, evento e ativacao automatica na mesma
  primeira entrega.

## M1 - Dominio, schema e contratos locais

Objetivo:

- criar a base local do onboarding comercial antes de tocar no gateway.

### TASK M1-T1 - Criar `AdminCommercialIntent` e migration

Referencia na discovery:

- `Nova entidade canonica`

Estado atual:

- essa entidade ainda nao existe.

Subtasks:

1. criar model `AdminCommercialIntent`;
2. criar migration com:
   - `uuid`
   - `organization_id`
   - `responsible_user_id`
   - `billing_order_id`
   - `status`
   - `payment_status`
   - `access_code_encrypted`
   - `access_code_last4`
   - `access_code_generated_at`
   - `paid_at`
   - `failed_at`
   - `canceled_at`
   - `refunded_at`
   - `linked_event_id`
   - `linked_at`
   - `consumed_at`
   - `metadata_json`
   - `gateway_snapshot_json`
   - `whatsapp_delivery_json`
   - `created_by_user_id`;
3. criar factory;
4. criar casts e relacionamentos;
5. criar indexes e unicidade necessaria.

Checklist de TDD:

- [ ] teste unitario do model/casts
- [ ] teste feature cobrindo persistencia basica do intent
- [ ] factory valida em testes do modulo

Dependencias:

- M0-T1
- M0-T2

Criterio de aceite:

- existe um agregado local proprio para a jornada administrativa.

### TASK M1-T2 - Expandir `BillingOrderMode` e snapshots operacionais

Referencia na discovery:

- `Novo mode em BillingOrder`

Estado atual:

- `BillingOrderMode` ainda so tem `subscription` e `event_package`.

Subtasks:

1. adicionar `commercial_intent` ao enum;
2. revisar factories, seeds e validacoes que dependem do enum;
3. garantir que `BillingOrder` suporta esse novo mode sem acionar a trilha de
   ativacao de evento;
4. documentar o efeito esperado em reconciliacao e notificacao.

Checklist de TDD:

- [ ] teste feature falhando ao tentar criar order nesse mode antes da mudanca
- [ ] regressao de `event_package` e `subscription`

Dependencias:

- M1-T1.

Criterio de aceite:

- o modulo aceita um terceiro tipo de pedido sem regressao nos dois modos
  atuais.

### TASK M1-T3 - Fechar contratos HTTP e Resources

Referencia na discovery:

- `Endpoints administrativos recomendados`

Estado atual:

- os endpoints administrativos dessa jornada ainda nao existem.

Subtasks:

1. criar requests de:
   - store
   - show
   - index
   - refresh
   - resend
   - attach-event;
2. criar controller fino;
3. criar resource principal;
4. registrar rotas admin com permissao `billing.manage`;
5. definir payloads de resposta com:
   - status local
   - status do billing
   - Pix atual
   - dados mascarados do `access_code`
   - historico de notificacoes.

Checklist de TDD:

- [ ] teste feature de autorizacao
- [ ] teste feature de shape minimo do `store`
- [ ] teste feature de `show` lendo apenas estado local

Dependencias:

- M1-T1
- M1-T2

Criterio de aceite:

- existe um contrato administrativo claro, desacoplado do checkout publico.

## M2 - Criacao de identidade e intent local

Objetivo:

- reaproveitar a base de identidade do repo e iniciar o onboarding comercial
  sem criar evento.

### TASK M2-T1 - Extrair ou reaproveitar a resolucao de identidade

Referencia na discovery:

- `O que o codigo atual ja oferece e vale reaproveitar`

Estado atual:

- `CreateAdminQuickEventAction` ja resolve `User` + `Organization`, mas essa
  logica ainda esta acoplada ao fluxo que cria `Event`.

Subtasks:

1. extrair a resolucao de identidade para service/action reutilizavel;
2. manter normalizacao de telefone/email;
3. manter reaproveitamento seguro de `User` e `Organization`;
4. manter criacao de membership administrativa quando necessario;
5. evitar duplicacao entre `admin/quick-events` e a nova jornada.

Checklist de TDD:

- [ ] teste com usuario e organizacao novos
- [ ] teste com usuario existente por WhatsApp
- [ ] teste com email/telefone conflitantes
- [ ] regressao de `CreateAdminQuickEventAction`

Dependencias:

- M1-T3.

Criterio de aceite:

- existe uma peca reutilizavel de identidade sem regressao no fluxo atual.

### TASK M2-T2 - Gerar `access_code` e criar o intent local

Referencia na discovery:

- `Nova entidade canonica`

Estado atual:

- ainda nao existe geracao do codigo comercial.

Subtasks:

1. criar `AdminCommercialIntentAccessCodeService`;
2. gerar codigo aleatorio com formato humano e curto;
3. persistir codigo de forma segura;
4. guardar `last4` para suporte;
5. criar o intent local com status `pending_payment`;
6. registrar `created_by_user_id` e metadata de origem.

Checklist de TDD:

- [ ] teste unitario do gerador
- [ ] teste unitario da mascara/`last4`
- [ ] teste feature garantindo que o codigo nao aparece em logs/respostas
      erradas

Dependencias:

- M2-T1.

Criterio de aceite:

- o intent nasce com codigo proprio e trilha de auditoria minima.

### TASK M2-T3 - Criar endpoint administrativo de criacao

Referencia na discovery:

- `Payload recomendado da criacao administrativa`

Estado atual:

- ainda nao existe um endpoint para iniciar essa jornada.

Subtasks:

1. implementar `POST /api/v1/admin/customer-onboarding-intents`;
2. validar payload com `FormRequest`;
3. retornar:
   - `intent.uuid`
   - `intent.status`
   - `access_code_masked`
   - `billing_order.uuid`
   - Pix atual
   - `whatsapp_delivery.status`;
4. registrar activity log do admin;
5. manter controller fino.

Checklist de TDD:

- [ ] teste feature de criacao feliz
- [ ] teste feature de reuso de identidade
- [ ] teste feature de validacao de payload

Dependencias:

- M2-T2.

Criterio de aceite:

- o backoffice consegue iniciar a jornada com uma unica chamada local.

## M3 - Pix da Pagar.me no novo mode

Objetivo:

- gerar o Pix real na Pagar.me v5 para o onboarding comercial.

### TASK M3-T1 - Criar `BillingOrder` em mode `commercial_intent`

Referencia na discovery:

- `Novo mode em BillingOrder`

Estado atual:

- o gateway atual so e consumido por `event_package` e assinatura.

Subtasks:

1. criar action para abrir `BillingOrder` do intent;
2. montar `BillingOrderItem` apropriado para onboarding comercial;
3. persistir `idempotency_key`;
4. ligar `AdminCommercialIntent.billing_order_id`;
5. isolar esse fluxo do pipeline de ativacao de evento.

Checklist de TDD:

- [ ] teste feature garantindo o novo `BillingOrder`
- [ ] teste garantindo que pagamento aprovado ainda nao cria `EventPurchase`
      nem `EventAccessGrant`

Dependencias:

- M2-T3.

Criterio de aceite:

- existe um pedido local proprio para essa jornada, sem side effect de evento.

### TASK M3-T2 - Integrar `POST /orders` com Pix

Referencia na discovery:

- `Pontos oficiais relevantes para esta jornada`

Estado atual:

- o gateway ja sabe criar Pix, mas nao para esse novo mode.

Subtasks:

1. criar payload factory especifica ou branch controlada para
   `commercial_intent`;
2. enviar `customer` completo;
3. enviar `payment_method = pix`;
4. persistir `gateway_order_id`, `gateway_charge_id`, `gateway_status`,
   `last_transaction_json`, `qr_code`, `qr_code_url`, `expires_at`;
5. devolver snapshot local para o painel admin.

Checklist de TDD:

- [ ] teste unitario do payload
- [ ] teste feature com `Http::fake()` cobrindo resposta Pix
- [ ] teste garantindo persistencia dos campos operacionais

Dependencias:

- M3-T1.

Criterio de aceite:

- o admin cria um intent e recebe imediatamente um Pix local com snapshot
  persistido.

### TASK M3-T3 - Retry seguro com a mesma `Idempotency-Key`

Referencia na discovery:

- `Como saber se foi pago ou nao`

Estado atual:

- o retry operacional ja existe para checkout publico.

Subtasks:

1. reaproveitar a estrategia de retry do `BillingOrder`;
2. garantir lock local por intent/order;
3. reaproveitar a mesma `Idempotency-Key` quando nao existe `gateway_order_id`;
4. bloquear nova chamada quando o snapshot externo ja existe.

Checklist de TDD:

- [ ] teste feature de retry seguro
- [ ] teste feature garantindo que a mesma chave nao duplica pedido local

Dependencias:

- M3-T2.

Criterio de aceite:

- timeout ou retry do backoffice nao duplica o onboarding comercial.

## M4 - Webhook e maquina de estados local

Objetivo:

- saber com seguranca se foi pago ou nao a partir do estado local.

### TASK M4-T1 - Reconciliar `paid`, `failed`, `canceled` e `refunded`

Referencia na discovery:

- `Maquina de estados recomendada`

Estado atual:

- a reconciliacao local hoje e centrada em `event_package`.

Subtasks:

1. criar actions:
   - `MarkAdminCommercialIntentAsPaidAction`
   - `FailAdminCommercialIntentAction`
   - `CancelAdminCommercialIntentAction`
   - `RefundAdminCommercialIntentAction`;
2. ligar essas actions ao processamento do webhook do `BillingOrder`;
3. atualizar timestamps e snapshots locais;
4. impedir side effect de evento nesse mode.

Checklist de TDD:

- [ ] teste feature `order.paid`
- [ ] teste feature `order.payment_failed`
- [ ] teste feature `order.canceled`
- [ ] teste feature `charge.refunded`

Dependencias:

- M3-T3.

Criterio de aceite:

- o painel admin sabe se o intent esta `paid` ou nao sem olhar a Pagar.me.

### TASK M4-T2 - Replay, idempotencia e refresh administrativo

Referencia na discovery:

- `Como saber se foi pago ou nao`

Estado atual:

- webhook e refresh ja existem no modulo, mas ainda nao cobrem esse agregado.

Subtasks:

1. garantir replay idempotente para o mesmo `hook_id`;
2. adicionar `refresh` do intent reaproveitando `GET /orders/{id}` e
   `GET /charges/{id}`;
3. devolver status local consolidado no `show`;
4. registrar trilha de auditoria do refresh.

Checklist de TDD:

- [ ] teste feature de replay real/local do webhook
- [ ] teste feature de refresh administrativo
- [ ] teste garantindo que `show` continua lendo so estado local

Dependencias:

- M4-T1.

Criterio de aceite:

- o admin consegue revalidar o estado sem quebrar a regra de polling local.

## M5 - WhatsApp e notificacoes ao cliente

Objetivo:

- reduzir friccao operacional enviando status e Pix ao cliente a partir do
  estado local reconciliado.

### TASK M5-T1 - Enviar mensagem inicial com `access_code` e Pix

Referencia na discovery:

- `Mensagem WhatsApp recomendada`

Estado atual:

- o repo ja envia `send-text`, mas nao para essa jornada.

Subtasks:

1. criar service especifico de composicao;
2. enviar mensagem inicial ao criar o Pix quando `send_whatsapp = true`;
3. incluir:
   - `access_code`
   - valor
   - expiracao
   - `qr_code_url`
   - copia e cola
   - instrucao de uso futuro;
4. persistir contexto em `billing_order_notifications` e
   `whatsapp_messages.payload_json.context`.

Checklist de TDD:

- [ ] teste unitario do composer
- [ ] teste feature garantindo envio inicial
- [ ] teste feature garantindo deduplicacao

Dependencias:

- M3-T2

Criterio de aceite:

- o cliente recebe o Pix e o codigo comercial sem depender de operacao manual.

### TASK M5-T2 - Enviar `paid`, `failed` e `refunded`

Referencia na discovery:

- `Notificacao proativa`

Estado atual:

- o modulo ja tem base de notificacao deduplicada para outros fluxos.

Subtasks:

1. ligar notificacao do intent a partir da maquina de estados local;
2. enviar `payment_paid`;
3. enviar `payment_failed`;
4. enviar `payment_refunded`;
5. registrar `chargeback` sem enviar ate politica ser fechada.

Checklist de TDD:

- [ ] teste feature para cada transicao notificada
- [ ] teste garantindo que replay do webhook nao duplica notificacao

Dependencias:

- M4-T1
- M5-T1

Criterio de aceite:

- o cliente recebe notificacoes coerentes e deduplicadas de cada estado.

### TASK M5-T3 - Reenvio manual do WhatsApp

Referencia na discovery:

- `Endpoints administrativos recomendados`

Estado atual:

- ainda nao existe reenvio manual dessa jornada.

Subtasks:

1. criar `POST /api/v1/admin/customer-onboarding-intents/{uuid}/resend`;
2. permitir reenvio do estado atual e do `access_code`;
3. registrar auditoria do reenvio;
4. impedir flood com trava simples por cooldown.

Checklist de TDD:

- [ ] teste feature do endpoint
- [ ] teste feature do cooldown

Dependencias:

- M5-T2.

Criterio de aceite:

- o suporte consegue reenviar a mensagem sem abrir a dashboard da Z-API.

## M6 - Associacao futura a evento

Objetivo:

- consumir o credito pago em um evento futuro sem misturar criacao e ativacao.

### TASK M6-T1 - Criar o endpoint de associacao

Referencia na discovery:

- `Relacao com evento futuro`

Estado atual:

- ainda nao existe associacao posterior desse credito a um evento.

Subtasks:

1. criar `POST /api/v1/admin/customer-onboarding-intents/{uuid}/attach-event`;
2. validar `event_id`;
3. exigir permissao `billing.manage`;
4. devolver status do vinculo e evento associado.

Checklist de TDD:

- [ ] teste feature do endpoint
- [ ] teste feature de autorizacao

Dependencias:

- M4-T2.

Criterio de aceite:

- existe uma entrada clara para consumir o credito pago depois.

### TASK M6-T2 - Materializar `linked_to_event` e `consumed`

Referencia na discovery:

- `Relacao com evento futuro`

Estado atual:

- a maquina de estados do intent ainda nao existe.

Subtasks:

1. atualizar o intent para `linked_to_event`;
2. criar a operacao de consumo real no evento;
3. decidir se o consumo gera `EventAccessGrant`, `EventPurchase` ou outro
   artefato comercial;
4. marcar `consumed_at`;
5. persistir relacao com o evento.

Checklist de TDD:

- [ ] teste feature de associacao feliz
- [ ] teste feature garantindo marcacao de `consumed`
- [ ] teste garantindo que o vinculo so acontece uma vez

Dependencias:

- M6-T1.

Criterio de aceite:

- o credito pago deixa de ser abstrato e passa a produzir efeito comercial em
  um evento especifico.

### TASK M6-T3 - Bloquear consumo invalido

Referencia na discovery:

- `Relacao com evento futuro`

Estado atual:

- ainda nao existe defesa para consumo indevido.

Subtasks:

1. rejeitar attach se o intent nao estiver `paid`;
2. rejeitar attach se estiver `refunded`, `canceled` ou `failed`;
3. rejeitar attach se ja estiver `consumed`;
4. decidir postura para `chargeback` posterior ao consumo.

Checklist de TDD:

- [ ] teste feature de attach com intent nao pago
- [ ] teste feature com intent refundado
- [ ] teste feature com intent ja consumido

Dependencias:

- M6-T2.

Criterio de aceite:

- o backoffice nao consegue usar duas vezes o mesmo credito nem consumir
  credito invalido.

## M7 - UX administrativa e homologacao

Objetivo:

- dar visibilidade real ao time operacional e fechar a prova final da jornada.

### TASK M7-T1 - Entregar a pagina administrativa

Referencia na discovery:

- `Endpoints administrativos recomendados`

Estado atual:

- ainda nao existe UX administrativa propria.

Subtasks:

1. criar pagina/lista de intents;
2. criar visao de detalhe com:
   - status local
   - Pix atual
   - `access_code` mascarado
   - historico de notificacoes
   - dados do cliente
   - vinculo futuro a evento;
3. criar CTA de:
   - atualizar
   - reenviar WhatsApp
   - associar a evento;
4. manter UX rapida, com baixa friccao e foco operacional.

Checklist de TDD:

- [ ] teste de componente/pagina
- [ ] `type-check`
- [ ] teste de fluxo principal da tela

Dependencias:

- M5-T3
- M6-T3

Criterio de aceite:

- o time comercial consegue operar essa jornada sem sair do painel.

### TASK M7-T2 - Rodar homologacao real de Pix e webhook

Referencia na discovery:

- `Como saber se foi pago ou nao`

Estado atual:

- a conta ja tem homologacao real para o checkout publico de evento.

Subtasks:

1. criar Pix de onboarding comercial em homologacao;
2. validar sucesso do simulador Pix;
3. validar falha do simulador Pix;
4. validar chegada do webhook real;
5. validar replay de webhook;
6. validar retry com a mesma `Idempotency-Key`;
7. registrar evidencias JSON.

Checklist de TDD:

- [ ] suite automatizada verde antes da homologacao
- [ ] comando ou checklist operacional registrado

Dependencias:

- M7-T1.

Criterio de aceite:

- existe prova real de que o admin sabe localmente se o onboarding foi pago ou
  nao.

### TASK M7-T3 - Consolidar o release checklist da jornada

Referencia na discovery:

- `Veredito final da discovery`

Estado atual:

- ainda nao existe gate final especifico dessa jornada.

Subtasks:

1. registrar matriz final de testes;
2. registrar evidencias de homologacao;
3. atualizar README do modulo;
4. documentar pendencias de produto:
   - chargeback
   - expiracao longa de credito em carteira
   - politica de regeneracao do `access_code`.

Checklist de TDD:

- [ ] nao se aplica em codigo nesta task

Dependencias:

- M7-T2.

Criterio de aceite:

- o time consegue responder o que esta pronto, o que falta e como validar.

## Bateria de testes recomendada

### Backend feature

- [ ] cria intent com novo `User` + `Organization`
- [ ] cria intent reaproveitando `User` existente
- [ ] falha quando email e WhatsApp apontam para identidades conflitantes
- [ ] cria `BillingOrder` com `mode = commercial_intent`
- [ ] persiste `idempotency_key`, `gateway_order_id`, `gateway_charge_id`,
      `qr_code`, `qr_code_url`, `expires_at`
- [ ] `order.paid` marca `BillingOrder` e `AdminCommercialIntent` como `paid`
- [ ] `order.payment_failed` marca como `failed`
- [ ] `order.canceled` marca como `canceled`
- [ ] `charge.refunded` marca como `refunded`
- [ ] replay do mesmo webhook nao duplica estado nem notificacao
- [ ] refresh administrativo le gateway e consolida status local
- [ ] attach a evento so funciona para intent `paid`
- [ ] attach falha para `failed`, `canceled`, `refunded` e `consumed`

### Backend unit

- [ ] gerador do `access_code`
- [ ] mascara e `last4`
- [ ] payload factory do Pix de onboarding
- [ ] status mapper do intent
- [ ] composer de mensagem WhatsApp
- [ ] cooldown de resend

### Frontend/admin

- [ ] formulario de criacao valida campos obrigatorios
- [ ] lista e detalhe mostram status local
- [ ] CTA de atualizar chama somente endpoint local
- [ ] CTA de reenviar respeita cooldown
- [ ] CTA de associar a evento so aparece quando `paid`
- [ ] `type-check` verde

### Homologacao manual

- [ ] Pix sucesso com simulador
- [ ] Pix falha com simulador
- [ ] replay real do webhook
- [ ] retry real com a mesma `Idempotency-Key`
- [ ] confirmacao de que o painel admin nunca depende de polling direto na
      Pagar.me

## Gates objetivos de readiness

Antes de chamar a jornada de pronta, estes gates precisam estar verdes:

- existe entidade local `AdminCommercialIntent`
- existe `BillingOrderMode::CommercialIntent`
- o intent cria ou reaproveita `User` + `Organization`
- o intent gera `access_code`
- o Pix e criado com `POST /orders`
- o status local sabe responder se foi pago ou nao
- webhook e refresh funcionam para esse agregado
- notificacoes via WhatsApp estao deduplicadas
- associacao futura a evento funciona e e bloqueada quando invalida
- a UX administrativa esta entregue
- a suite `Billing` esta verde
- a homologacao real de Pix e webhook foi registrada

## Fora de escopo nesta fase

- cartao de credito para essa jornada administrativa
- criacao automatica de `Event` no mesmo fluxo
- uso publico do `access_code` sem mediacao do admin
- split e recorrencia
- wallet de cartao
- politica final de `chargeback`
- politica de expurgo ou expiracao longa do credito em carteira

## Primeira execucao recomendada

Se a execucao comecar agora, a ordem recomendada e esta:

1. fechar `M0-T1` e `M0-T2`
2. executar `M1-T1` ate `M1-T3`
3. executar `M2-T1` ate `M2-T3`
4. executar `M3-T1` ate `M3-T3`
5. executar `M4-T1` e `M4-T2`
6. executar `M5-T1` ate `M5-T3`
7. executar `M6-T1` ate `M6-T3`
8. executar `M7-T1` ate `M7-T3`

Se o objetivo for colocar a jornada administrativa em producao com baixo risco,
as regras praticas sao estas:

- nao misturar esse fluxo com `event_package`
- nao criar `Event` antes do pagamento
- nao usar a dashboard da Pagar.me como fonte de verdade do admin
- nao abrir cartao antes de fechar Pix, webhook, WhatsApp e attach posterior
