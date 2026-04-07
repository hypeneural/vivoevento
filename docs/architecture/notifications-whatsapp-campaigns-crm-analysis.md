# CRM Campaigns, Z-API and Notification Orchestration Analysis

## Objetivo

Este documento consolida:

- a analise detalhada do CRM em `C:\laragon\www\evydencia\crm`;
- a comparacao com a base atual do `eventovivo`;
- o entendimento da gestao de mensagens, campanhas, atividades e reguas de cobranca;
- a proposta arquitetural recomendada para criar no `eventovivo` uma camada de notificacoes gerenciavel pelo front, escalavel e reutilizavel em todo o ecossistema.

O foco aqui nao e copiar o CRM literalmente. O foco e identificar:

- o que ele resolve bem;
- onde ele esta acoplado demais ao dominio legado de pedidos;
- e como aproveitar o que faz sentido sobre a base atual do `eventovivo`, especialmente os modulos `WhatsApp`, `Billing` e o placeholder `Notifications`.

## Fontes analisadas

### CRM

Arquivos mais relevantes inspecionados:

- `composer.json`
- `package.json`
- `routes/manager.php`
- `app/Http/Controllers/Manager/MessageController.php`
- `app/Http/Controllers/Manager/CampaignController.php`
- `app/Http/Controllers/Manager/CampaignScheduledController.php`
- `app/Http/Controllers/Manager/CampaignScheduledContactController.php`
- `app/Http/Controllers/Manager/StageMessageController.php`
- `app/Http/Controllers/Manager/ProductNotificationController.php`
- `app/Services/MessageService.php`
- `app/Services/CampaignService.php`
- `app/Services/CampaignStageService.php`
- `app/Services/CampaignScheduledService.php`
- `app/Services/CampaignScheduledContactService.php`
- `app/Services/ProductNotificationService.php`
- `app/Services/CollectionRulerService.php`
- `app/Services/Messages/MessageService.php`
- `app/Services/Messages/CampaignMessageService.php`
- `app/Services/Messages/OrderMessageService.php`
- `app/Services/MessageVariableService.php`
- `app/Services/Zapi/Zapi.php`
- `app/Services/Zapi/WhatsappPayload.php`
- `app/Observers/OrderStatusObserver.php`
- `app/Jobs/PixCollectionRulerJob.php`
- `app/Console/Kernel.php`
- migrations de `messages`, `campaigns`, `campaign_stages`, `stage_messages`,
  `campaign_scheduleds`, `campaign_scheduled_contacts`, `scheduled_contact_stage`,
  `message_instances`, `products_notifications` e `orders_installments`
- views de `manager/message`, `manager/campaign`, `manager/campaign/scheduled`,
  `manager/campaign/stage`, `manager/product/notification`,
  `order/notifications/*`, `manager/order/pix/*` e `order/payment/pix*`

### Eventovivo

Arquivos mais relevantes inspecionados:

- `apps/api/app/Modules/WhatsApp/README.md`
- `apps/api/app/Modules/WhatsApp/Services/WhatsAppMessagingService.php`
- `apps/api/app/Modules/WhatsApp/Clients/Providers/ZApi/ZApiApiClient.php`
- `apps/api/app/Modules/Billing/README.md`
- `apps/api/app/Modules/Billing/Services/BillingPaymentStatusNotificationService.php`
- `apps/api/app/Modules/Notifications/README.md`

### Documentacao oficial atual da Z-API

Foi usada apenas como referencia de capacidade atual da plataforma, nao como base principal da analise de codigo:

- `https://developer.z-api.io/`

Ponto confirmado na documentacao atual:

- o fluxo oficial de envio da Z-API considera fila interna do provider e webhooks
  de `delivery` e `message-status`, o que reforca a recomendacao de separar
  claramente `orquestracao`, `dispatch` e `confirmacao de entrega`.

## 1. Stack do CRM

## 1.1 Backend

O CRM e uma aplicacao Laravel classica, monolitica, fortemente baseada em Blade.

Stack observada:

- Laravel 10
- PHP `^8.1`, operando localmente com 8.3
- MySQL 8
- Redis para fila/cache/sessao
- Sanctum
- BenSampo Enum
- Guzzle
- Flysystem S3
- Livewire pontual
- `laravel/ui`
- `laravelcollective/html`

## 1.2 Frontend

O front do CRM e hibrido e legado.

Stack observada:

- Bootstrap 4
- jQuery
- Select2
- SweetAlert2
- jQuery Validation
- Laravel Mix 5
- React 16 parcial

Isso importa porque a logica de negocio esta muito no backend, e a UX do manager
e majoritariamente server-rendered com pequenos incrementos em JS.

## 1.3 Estilo arquitetural

O CRM nao esta organizado como modulo de dominio no estilo do `eventovivo`.

O desenho real e este:

- controllers Blade/manager finos a medianos;
- services concentrando bastante regra;
- observers acionando automacoes por mudanca de status;
- jobs periodicos para remarketing, reguas e processamento de campanha;
- notificacoes WhatsApp via `Notification` do Laravel + `WhatsappChannel`;
- integracao Z-API tratada como transporte direto, nao como dominio separado.

Isso funciona, mas gera acoplamento alto entre:

- pedido;
- parcela;
- campanha;
- mensagem;
- produto;
- lead;
- transporte WhatsApp.

## 2. Modelagem de dados do CRM

## 2.1 Catalogo de mensagens

Tabela central: `messages`

Evolucao observada nas migrations:

- nasceu com tipos basicos: `text`, `audio`, `location`, `button`, `video`,
  `attachment`, `image`;
- depois ganhou `contact`, `sticker`, `link`, `call`, `action`, `pix`,
  `carousel`;
- tambem ganhou campos auxiliares como `link`, `description`, `buttons`,
  `carousel`.

Na pratica, `Message` funciona como um template de payload multimodal da Z-API.

Campos conceitualmente relevantes:

- identificacao: `name`
- tipo: `type`
- corpo textual: `message`
- titulo: `title`
- arquivo ou recurso hospedado: `url`
- link externo: `link`
- descricao auxiliar: `description`
- botoes de acao: `buttons`
- carrossel em JSON: `carousel`
- metadados especificos por tipo, como localizacao

Observacao importante:

- o schema e simples, mas o significado dos campos muda conforme o tipo;
- isso deixa o catalogo flexivel para operacao;
- mas deixa o dominio frouxo e mais dificil de validar/manter.

## 2.2 Instancias de envio

Tabela central: `message_instances`

Papel:

- guardar a configuracao de envio Z-API;
- definir uma instancia favorita;
- permitir selecionar a instancia por campanha ou por execucao.

Campos principais:

- `name`
- `zapi_url`
- `zapi_token`
- `zapi_instance`
- `zapi_security_token`
- `favorite`

Diagnostico:

- isso resolve rapidamente o multi-sender;
- mas mistura credencial de provider e conceito de sender num modelo legado;
- no `eventovivo`, a base atual de `whatsapp_instances` esta mais madura e
  provider-agnostic.

## 2.3 Campanhas

Tabela central: `campaigns`

Campos relevantes:

- `name`
- `message_instance_id`
- `lead_integration`
- `product_reference`
- `product_slug`

Relacoes:

- campanha tem varias etapas (`campaign_stages`);
- campanha pode virar varias execucoes concretas (`campaign_scheduleds`);
- campanha pode ser usada como gatilho de produto em `products_notifications`.

## 2.4 Etapas da campanha

Tabelas:

- `campaign_stages`
- `stage_messages`

`campaign_stages` define a cadencia relativa:

- `name`
- `time_value`
- `time_type` (`minutes`, `hours`, `days`)
- `campaign_id`

`stage_messages` vincula as mensagens daquela etapa:

- `stage_id`
- `message_id`
- `sort`

Diagnostico:

- a ideia da etapa relativa e boa e reaproveitavel;
- o uso de pivot simples tambem e bom;
- mas falta separar melhor `blocos de conteudo`, `anexos` e `acao`;
- o `sort` existe, mas a UX parece pouco robusta para ordenacao real.

## 2.5 Execucoes de campanha

Tabelas:

- `campaign_scheduleds`
- `campaign_scheduled_contacts`
- `scheduled_contact_stage`

Esse trio e o motor operacional real.

### `campaign_scheduleds`

Representa uma execucao concreta de campanha.

Campos relevantes:

- `campaign_id`
- `start_at`
- `end_at`
- `status`
- `customer_id`
- `order_id`
- `order_cake_id`
- `message_instance_id`
- `kind`
- `contacts_header`
- `use_leads_system`
- `payload_version`

`kind` e importante porque o CRM ja usa a mesma tabela para:

- campanha normal;
- regua de agendamento;
- regua operacional de bolo.

### `campaign_scheduled_contacts`

Representa cada destinatario da execucao.

Campos relevantes:

- `name`
- `whatsapp`
- `content`
- `type`
- `status`
- `lead_id`
- `campaign_scheduled_id`

`content` carrega a linha completa do CSV do contato.

### `scheduled_contact_stage`

Representa a trilha de conclusao por etapa e por contato.

Isso permite saber:

- que contatos ainda tem etapa pendente;
- quantas etapas ja foram concluídas;
- quando uma execucao terminou de fato.

Diagnostico:

- essa e a melhor parte conceitual do CRM;
- ela separa bem `execucao`, `destinatario` e `progresso`;
- isso vale reaproveitar no `eventovivo`;
- mas a implementacao atual depende demais de SQL manual e regras acopladas ao
  pedido.

## 2.6 Ponte de negocio: notificacoes por produto

Tabela central: `products_notifications`

Campos:

- `product_id`
- `campaign_id`
- `dispatcher`
- `status`

Aqui o CRM conecta evento de negocio a campanha.

`dispatcher` cobre estados como:

- `order_created`
- `waiting_payment`
- `payment_confirmed`
- `payment_refunded`
- `payment_chargeback`
- `collection_ruler_pix`
- `collection_ruler_boleto`
- `payment_error`
- `schedule_ruler`

Diagnostico:

- para o CRM, isso e muito eficiente;
- para o `eventovivo`, isso seria estreito demais;
- o equivalente correto para nos nao e `products_notifications`;
- o equivalente correto e um modulo de `Notifications` com triggers por evento
  de dominio, nao por produto do checkout legado.

## 2.7 Dados de Pix no CRM

Tabela observada: `orders_installments`

Campos adicionados:

- `pix_expiration_date`
- `pix_qrcode`

Na pratica, o CRM guarda o copia-e-cola na parcela e o reutiliza em:

- tela do checkout;
- tela do manager;
- notificacao WhatsApp transacional;
- reguas de cobranca.

Esse ponto e conceitualmente correto:

- o valor de Pix precisa estar no estado local do sistema;
- ele nao deve depender de consulta ad hoc ao gateway toda vez.

## 3. Fluxo de criacao de mensagem no CRM

Rota principal:

- `GET /gestao/mensagens/novo`
- `POST /gestao/mensagens/novo`

Arquivos centrais:

- `app/Http/Controllers/Manager/MessageController.php`
- `app/Services/MessageService.php`
- `app/Http/Requests/MessageRequest.php`
- `resources/views/manager/message/_form.blade.php`

## 3.1 Como funciona

O fluxo e:

1. o manager abre o form Blade;
2. escolhe o tipo da mensagem;
3. o backend carrega o mesmo form com paines condicionais por tipo;
4. `MessageRequest` valida o payload conforme o tipo;
5. `MessageService` mapeia os campos especificos para o modelo `Message`;
6. se houver arquivo, ele sobe em storage/S3 e grava a `url`;
7. o registro passa a poder ser anexado em template ou etapa de campanha.

## 3.2 Tipos suportados

O CRM ja suporta no catalogo:

- texto
- audio
- localizacao
- botoes simples
- video
- anexo/documento
- imagem
- contato
- sticker
- link rico
- call
- action buttons
- pix button
- carousel

## 3.3 Pontos fortes

- catalogo unico de mensagens;
- suporte multimodal;
- validacao condicional no request;
- reutilizacao da mesma mensagem em varias campanhas/etapas;
- suporte nativo a Pix button e carousel.

## 3.4 Limitacoes

- um unico registro `messages` concentra tipos muito diferentes;
- o form e muito grande e dificil de evoluir;
- `pix` e tratado como um tipo fixo que espera um valor literal;
- `carousel` e um JSON cru salvo em texto, sem schema forte;
- anexos agrupados como conjunto ainda nao sao uma abstracao de dominio,
  apenas um payload especial por mensagem.

## 4. Fluxo de criacao de campanha no CRM

Rotas principais:

- `GET /gestao/campanhas`
- `GET /gestao/campanhas/novo`
- `POST /gestao/campanhas/novo`
- `GET /gestao/campanhas/{id}/etapas`
- `GET /gestao/campanhas/{id}/executar`
- `POST /gestao/campanhas/{id}/executar`
- `GET /gestao/campanhas/atividades`

## 4.1 Estrutura funcional

Uma campanha no CRM tem:

- uma instancia de mensagem padrao;
- opcao de integracao com leads;
- referencia/slug de produto;
- varias etapas;
- varias mensagens por etapa;
- varias execucoes concretas;
- varios contatos por execucao.

## 4.2 Etapas e periodicidade

Cada etapa guarda:

- um nome;
- um atraso relativo em minutos/horas/dias;
- um conjunto de mensagens.

A regra e:

- a primeira etapa conta a partir de `start_at`;
- as seguintes contam a partir da conclusao da etapa anterior;
- para alguns `kind`, a referencia temporal muda para o evento operacional,
  como sessao agendada ou entrega do bolo.

Esse desenho resolve bem periodicidade e regua.

## 4.3 Templates

O CRM ainda permite anexar templates completos a uma etapa.

Isso significa:

- mensagem pode ser usada individualmente;
- ou o manager pode anexar um conjunto pronto de mensagens via template.

Conceitualmente, isso e util:

- `Template` funciona como kit de mensagens;
- `Campaign Stage` funciona como orquestrador temporal;
- `Message` funciona como bloco reutilizavel.

## 5. Como `gestao/campanhas/atividades` funciona

Arquivos centrais:

- `CampaignScheduledController`
- `CampaignScheduledContactController`
- `CampaignScheduledService`
- `CampaignScheduledContactService`
- `resources/views/manager/campaign/scheduled/index.blade.php`
- `resources/views/manager/campaign/scheduled/contact/index.blade.php`

## 5.1 O que a tela representa

`Campanhas > Atividades` nao mostra definicao de campanha. Ela mostra execucao.

Cada linha representa um `campaign_scheduled` com:

- emissao;
- campanha;
- instancia;
- inicio;
- fim;
- situacao.

Ao abrir detalhes, o CRM mostra:

- contatos;
- nome;
- se entrou como lead;
- quantas etapas concluiu;
- se o contato esta ativo, inativo ou concluido.

## 5.2 O que isso resolve bem

- operacao consegue enxergar progresso real;
- da para inativar contato sem matar a campanha inteira;
- existe nocao de execucao concreta, nao so de template abstrato.

Essa e uma referencia muito boa para o `eventovivo`.

## 5.3 O que esta faltando no CRM

Mesmo com esse desenho, ainda faltam coisas que o `eventovivo` pode fazer
melhor:

- historico detalhado de cada dispatch e de cada resposta do provider;
- diferenca explicita entre `queued`, `provider_accepted`, `sent`, `received`,
  `read`, `failed`;
- retry por etapa/destinatario com idempotencia forte;
- preview dos dados resolvidos antes do envio;
- auditoria por template revisado;
- melhor modelagem de anexos agrupados e componentes ricos.

## 6. Como o CRM executa uma regua de cobranca Pix

## 6.1 Gatilho transacional direto

No `OrderStatusObserver`, quando o pedido entra em `waiting_payment`:

- o CRM chama `OrderMessageService->PayWithPix()`;
- envia um `send-text` com instrucoes;
- depois envia `sendPixMessage()` com o codigo Pix da parcela.

Ou seja:

- existe uma mensagem transacional imediata;
- fora da campanha;
- amarrada diretamente ao status do pedido.

## 6.2 Regua automatica para inadimplencia ou atraso

Em paralelo, o CRM roda:

- `PixCollectionRulerJob` de hora em hora;
- `CollectionRulerService` encontra pedidos Pix pendentes dentro da janela;
- para cada pedido elegivel, `CampaignScheduledService->createCollectionRulerPix($order)`.

Ou seja, ha duas camadas:

1. mensagem imediata de pedido aguardando pagamento;
2. campanha automatica de regua de cobranca para pedidos ainda nao pagos.

Esse desenho e bom e deve inspirar o `eventovivo`.

## 6.3 Como ele sabe qual campanha usar

Via `ProductNotification`.

O produto do pedido pode ter uma notificacao com `dispatcher`:

- `collection_ruler_pix`
- `collection_ruler_boleto`
- `payment_confirmed`
- etc.

A campanha certa e encontrada por:

- produto do pedido;
- status/dispatcher configurado.

## 7. Como o CRM trata pedido pago, emitido, confirmado e cancelado

O `OrderStatusObserver` faz esse papel.

Fluxo resumido:

- aborta campanhas antigas do cliente quando necessario;
- tenta achar uma `ProductNotification` compativel com o novo status;
- se achar, agenda a campanha correspondente;
- se nao achar e o pedido nao for interno, cai para notificacoes transacionais
  diretas em `OrderMessageService`.

Mensagens transacionais observadas:

- `orderCreated()`
- `PayWithPix()`
- `paymentConfirmed()`
- `paymentRefused()`
- `sessionScheduleConfirmation()`
- `selectionScheduleConfirmation()`
- `productAvailableToRetrieve()`
- `orderAborted()`
- `orderCompleted()`

Ou seja, o CRM mistura dois modelos:

- campanhas configuraveis por produto;
- mensagens fixas codificadas no service.

Essa mistura resolve o negocio legado, mas nao e o melhor desenho para o
`eventovivo`.

## 8. Integracao Z-API no CRM

## 8.1 Camada de transporte

Arquivos centrais:

- `app/Services/Zapi/Zapi.php`
- `app/Services/Zapi/WhatsappPayload.php`
- `app/Notifications/WhatsappNotification.php`
- `app/Notifications/Whatsapp/WhatsappChannel.php`
- `app/Services/Messages/MessageService.php`

## 8.2 Como funciona

Fluxo:

1. services de negocio chamam metodos como `sendText`, `sendPixMessage`,
   `sendCarousel`, `sendAttachment`;
2. `MessageService` cria `WhatsappPayload`;
3. `WhatsappNotification` usa `WhatsappChannel`;
4. `WhatsappChannel` instancia `Zapi` e envia;
5. `Zapi` resolve o endpoint conforme o tipo e faz `POST`.

Tipos mapeados para endpoints:

- `send-text`
- `send-audio`
- `send-location`
- `send-button-list`
- `send-video`
- `send-document/{ext}`
- `send-image`
- `send-contact`
- `send-link`
- `send-reaction`
- `send-sticker`
- `send-call`
- `send-button-actions`
- `send-button-otp`
- `send-button-pix`
- `send-carousel`

## 8.3 O que o CRM acerta

- encapsula os payloads da Z-API;
- centraliza a decisao do endpoint por tipo;
- permite reuso do transporte em varias partes do sistema;
- ja suporta recursos ricos, inclusive Pix button e carousel.

## 8.4 O que o CRM nao tem de forma madura

- modulo provider-agnostic;
- entidade forte de outbound log com status unificado;
- relacao clara entre dispatch, retry e comprovacao de entrega;
- camada orquestradora acima do transporte;
- forte separacao entre template, renderizacao e envio.

## 9. Diagnostico comparativo com o eventovivo

## 9.1 O que o `eventovivo` ja tem melhor

O `eventovivo` ja nasce com vantagens importantes:

- modulo `WhatsApp` provider-agnostic;
- adapters separados para Z-API e Evolution;
- `whatsapp_messages`, `whatsapp_dispatch_logs` e `whatsapp_inbound_events`;
- DTOs fortes para envio;
- jobs explicitos para dispatch;
- webhook normalizado;
- `Billing` com maquina de estados local;
- deduplicacao de notificacoes em `billing_order_notifications`.

Em outras palavras:

- o `eventovivo` ja esta melhor na camada de transporte e auditoria;
- o CRM esta melhor na camada de orquestracao de campanhas e jornadas.

## 9.2 O que o `eventovivo` ainda nao tem

Hoje o `eventovivo` ainda nao tem um dominio completo de notificacoes
orquestradas.

Faltam principalmente:

- catalogo geral de templates gerenciavel;
- jornadas/automacoes configuraveis pelo front;
- execucoes/atividades com progresso por destinatario;
- triggers de negocio configuraveis alem do billing;
- scheduler genericamente orientado a notificacao;
- UI administrativa unica para mensagens, jornadas e atividades.

## 9.3 O que nao vale copiar do CRM

Nao vale copiar:

- a dependencia direta de `ProductNotification` para tudo;
- o acoplamento forte com `OrderStatusObserver`;
- o uso de SQL muito especifico de MySQL como coracao da engine;
- a modelagem frouxa de `Message` para todo tipo de bloco;
- o manager Blade legado;
- o transporte Z-API acoplado diretamente ao dominio de mensagens.

## 10. Recomendacao arquitetural para o eventovivo

## 10.1 Principio central

No `eventovivo`, a orquestracao de notificacoes deve nascer no modulo
`Notifications`, e nao dentro de `Billing` nem dentro de `WhatsApp`.

Separacao recomendada:

- `WhatsApp`: transporte, provider, inbound, outbound, delivery, dispatch logs;
- `Billing`: estados financeiros e eventos de negocio;
- `Notifications`: templates, jornadas, regras de disparo, execucoes,
  destinatarios, renderizacao, retries e atividades.

## 10.2 Bounded contexts recomendados

### `WhatsApp`

Continua responsavel por:

- instancias;
- adaptadores Z-API/Evolution;
- envio tecnico;
- mensagens outbound/inbound;
- logs de dispatch;
- webhooks do provider.

### `Notifications`

Novo dominio principal de orquestracao.

Deve concentrar:

- templates;
- jornadas;
- etapas;
- gatilhos;
- execucoes;
- destinatarios;
- deliveries;
- preview/renderizacao;
- supressoes e preferencia de canal no futuro.

### Modulos produtores de evento

Continuam donos do negocio e apenas publicam eventos internos:

- `Billing`
- `Events`
- `MediaProcessing`
- `WhatsApp` inbound comercial
- futuras features de onboarding, catalogo, trial, play, etc.

## 10.3 Entidades recomendadas

### `NotificationTemplate`

Responsavel por um bloco reutilizavel de conteudo.

Campos sugeridos:

- `id`
- `organization_id` nullable para templates globais
- `name`
- `channel` (`whatsapp`, depois `email`, `in_app`, etc.)
- `kind` (`text`, `image`, `document`, `pix_button`, `carousel`, `action_buttons`)
- `subject` nullable
- `body_text`
- `body_json`
- `status`
- `version`
- `created_by`
- `updated_by`

### `NotificationTemplateAsset`

Para anexos estruturados e reuso de assets.

Campos sugeridos:

- `notification_template_id`
- `asset_type`
- `media_library_id` ou equivalente
- `sort`
- `label`
- `config_json`

### `NotificationJourney`

Representa uma automacao configuravel.

Campos sugeridos:

- `name`
- `slug`
- `scope` (`billing`, `event_onboarding`, `media_feedback`, `crm`, `global`)
- `trigger_key`
- `channel`
- `status`
- `organization_id` nullable
- `entry_conditions_json`
- `exit_conditions_json`

### `NotificationJourneyStep`

Representa uma etapa temporal da jornada.

Campos sugeridos:

- `notification_journey_id`
- `name`
- `delay_unit`
- `delay_value`
- `order`
- `template_id`
- `send_mode` (`single`, `template_bundle`, `dynamic_pix`)
- `conditions_json`
- `provider_overrides_json`

### `NotificationRun`

Representa uma execucao concreta.

Campos sugeridos:

- `notification_journey_id`
- `trigger_type`
- `trigger_id`
- `trigger_key`
- `status`
- `scheduled_at`
- `started_at`
- `completed_at`
- `context_json`

### `NotificationRunRecipient`

Representa cada destinatario de uma execucao.

Campos sugeridos:

- `notification_run_id`
- `user_id` nullable
- `customer_id` nullable
- `phone`
- `name`
- `status`
- `variables_json`
- `suppressed_reason`

### `NotificationDelivery`

Representa cada envio efetivo.

Campos sugeridos:

- `notification_run_recipient_id`
- `notification_journey_step_id`
- `channel`
- `provider_key`
- `whatsapp_instance_id`
- `status`
- `queued_at`
- `provider_accepted_at`
- `sent_at`
- `delivered_at`
- `read_at`
- `failed_at`
- `template_snapshot_json`
- `payload_json`
- `provider_response_json`
- `error_message`
- `whatsapp_message_id` nullable

### `NotificationTriggerRule`

Opcional, mas muito util para desacoplar gatilho e jornada.

Campos sugeridos:

- `trigger_key`
- `scope`
- `notification_journey_id`
- `status`
- `conditions_json`

## 10.4 Relacao com o billing atual

A base atual do `Billing` nao deve ser descartada.

Recomendacao:

- manter `BillingPaymentStatusNotificationService` como ponte inicial;
- no medio prazo, migrar a orquestracao hardcoded para o modulo
  `Notifications`;
- `Billing` deve publicar eventos internos como:
  - `billing.order.pix_generated`
  - `billing.order.paid`
  - `billing.order.failed`
  - `billing.order.refunded`
  - `billing.order.chargedback`
- `Notifications` escuta esses eventos e abre `NotificationRun`.

Assim:

- `Billing` continua dono do estado financeiro;
- `Notifications` passa a ser dono da comunicacao.

## 11. Como eu faria a UX/UI no eventovivo

O `eventovivo` nao deve reproduzir as telas Blade do CRM.

Ele deve usar SPA React moderna e separar a experiencia em quatro areas.

## 11.1 Templates

Tela: `/notifications/templates`

Capacidades:

- listar templates;
- filtrar por canal, tipo, status e escopo;
- editar em builder estruturado;
- preview com variaveis resolvidas;
- anexos agrupados;
- suporte a `pix_button`, `carousel`, `image`, `document`, `text`.

## 11.2 Jornadas

Tela: `/notifications/journeys`

Capacidades:

- criar jornada;
- escolher trigger;
- adicionar etapas;
- configurar atrasos;
- escolher template por etapa;
- definir condicoes por etapa;
- ativar/desativar.

Essa e a evolucao correta de `campanhas + etapas`.

## 11.3 Atividades

Tela: `/notifications/runs`

Capacidades:

- listar execucoes;
- detalhar destinatarios;
- detalhar etapas por destinatario;
- ver `queued`, `sent`, `delivered`, `read`, `failed`;
- abortar execucao;
- retry seletivo por destinatario/etapa.

Essa e a evolucao correta de `campanhas/atividades`.

## 11.4 Configuracoes e senders

Tela: `/notifications/settings`

Capacidades:

- escolher instancia WhatsApp padrao;
- override por journey;
- templates globais vs organizacao;
- politicas de quiet hours;
- limites de retry;
- webhooks operacionais.

## 11.5 Integracao com telas de negocio

Tambem vale ter paines contextuais dentro de:

- detalhe do `BillingOrder`
- detalhe do `Event`
- perfil do `User`

Esses paines devem mostrar:

- ultima notificacao enviada;
- estado de entrega;
- timeline;
- botao de reenvio controlado.

## 12. Como eu adaptaria a ideia de regua do CRM para o eventovivo

## 12.1 Pagamentos e pedidos

Para `Billing`, eu separaria dois grupos:

### Transacionais

Disparadas imediatamente por evento:

- Pix gerado
- pagamento confirmado
- pagamento reprovado
- reembolso
- chargeback

### Jornadas

Disparadas por schedule/condicao:

- Pix ainda nao pago D+1
- Pix ainda nao pago D+2
- pedido pago mas onboarding nao concluido
- evento criado, mas configuracao inicial nao feita
- lembrete para concluir dados obrigatorios

## 12.2 Ecossistema futuro

A mesma engine deve servir para:

- onboarding comercial
- onboarding de evento
- trial
- feedback de moderacao
- liberacao de galeria
- lembrete de parede/telao
- recuperacao de lead
- notificacoes operacionais internas no futuro

Esse e o ponto principal:

- o CRM usa campanha como ferramenta de CRM e cobranca;
- o `eventovivo` deve usar `Notifications` como infraestrutura transversal de
  comunicacao do produto.

## 13. Melhorias reais em relacao ao CRM

## 13.1 O que eu reaproveitaria conceitualmente

- jornada com etapas relativas;
- execucao concreta separada da definicao;
- trilha por destinatario;
- integracao opcional com segmento/leads;
- uso de template reutilizavel;
- suporte a Pix button e mensagens ricas.

## 13.2 O que eu mudaria de forma objetiva

### 1. Tirar o acoplamento de produto/pedido

No `eventovivo`, gatilho deve ser evento de dominio, nao `product_id`.

### 2. Tirar o SQL manual do coracao da engine

As consultas de elegibilidade precisam ser:

- legiveis;
- testadas;
- preferencialmente orientadas a estados persistidos ou query objects.

### 3. Criar delivery como entidade de primeira classe

Sem isso nao ha boa auditoria nem UX operacional.

### 4. Usar versionamento de template

Se a campanha rodou com um template antigo, isso precisa ficar no snapshot.

### 5. Modelar assets e blocos ricos de forma estruturada

Nao guardar tudo em um `text` com JSON cru sempre que der para evitar.

### 6. Reusar o modulo `WhatsApp` atual como transporte

O CRM acopla tudo a Z-API. O `eventovivo` ja nao precisa fazer isso.

## 14. Plano de evolucao recomendado

## Fase 0 - Consolidacao de fronteiras

Objetivo:

- travar a fronteira entre `Billing`, `WhatsApp` e `Notifications`.

Entregas:

- README do modulo `Notifications` expandido;
- enums de `trigger_key`, `channel`, `delivery_status`;
- eventos de dominio publicados por `Billing`.

## Fase 1 - Catalogo de templates

Objetivo:

- criar CRUD real de templates reutilizaveis.

Entregas:

- tabelas de template e assets;
- API REST;
- tela SPA;
- preview com variaveis.

## Fase 2 - Jornadas e etapas

Objetivo:

- criar o equivalente moderno de `campanhas + etapas`.

Entregas:

- journeys;
- steps;
- bind de template;
- conditions JSON;
- delays relativos.

## Fase 3 - Runs, recipients e deliveries

Objetivo:

- criar o equivalente moderno de `atividades`.

Entregas:

- runs;
- recipients;
- deliveries;
- timeline operacional;
- retry/abort.

## Fase 4 - Integracao com billing

Objetivo:

- migrar notificacoes hardcoded do checkout para a engine nova.

Entregas:

- journeys para `pix_generated`, `paid`, `failed`, `refunded`, `chargedback`;
- painel do pedido com trilha de notificacao;
- uso de `send-text` + `send-button-pix` via modulo `WhatsApp`.

## Fase 5 - Expansao para outros dominios

Objetivo:

- usar a mesma engine para onboarding, evento, media e futuras features.

## 15. Bateria TDD recomendada

## 15.1 Backend unitario

- `NotificationTemplateRendererTest`
- `NotificationJourneyDelayResolverTest`
- `NotificationTriggerRuleMatcherTest`
- `NotificationRecipientResolverTest`
- `NotificationDeliveryPayloadBuilderTest`
- `NotificationWhatsAppDispatchServiceTest`

## 15.2 Backend feature

- CRUD de templates
- CRUD de jornadas
- listagem de runs
- detalhamento de run
- retry de delivery
- abort de run

## 15.3 Backend integracao

- trigger `billing.order.pix_generated` abre run
- trigger `billing.order.paid` abre run
- webhook de delivery atualiza `NotificationDelivery`
- deduplicacao por `trigger + target + journey`

## 15.4 Frontend

- listagem de templates
- builder de template
- criacao de jornada com etapas
- listagem de atividades
- detalhamento por destinatario
- reenvio de entrega

## 15.5 Testes de caracterizacao antes de migrar

Antes de trocar a orquestracao atual do billing:

- congelar em testes o comportamento atual de `BillingPaymentStatusNotificationService`;
- garantir equivalencia para:
  - Pix gerado
  - pagamento pago
  - pagamento falho
  - reembolso
  - Pix button pela Z-API

## 16. Conclusao

O CRM tem um motor de campanhas funcional e pragmatico.

O que ele faz bem:

- templates multimodais;
- jornadas em etapas;
- atividades concretas;
- reguas de cobranca;
- uso operacional do WhatsApp com Z-API.

O que ele nao faz tao bem:

- separar dominio, orquestracao e transporte;
- escalar para varios contextos do produto sem acoplamento;
- oferecer um modelo forte de auditoria de dispatch;
- manter a manutencao simples no longo prazo.

Para o `eventovivo`, a direcao correta nao e recriar o CRM. A direcao correta e:

- manter o `WhatsApp` como camada de transporte;
- manter `Billing` como dono do estado financeiro;
- transformar `Notifications` em dominio de orquestracao transversal;
- construir uma UX moderna de templates, jornadas e atividades;
- e usar TDD para congelar o comportamento transacional antes de migrar.

Se esse desenho for seguido, o `eventovivo` ganha:

- notificacoes de pagamento melhores;
- campanhas e automacoes gerenciaveis no front;
- reaproveitamento para onboarding, evento, media e CRM futuro;
- e uma base mais limpa e mais escalavel do que a encontrada hoje no CRM legado.
