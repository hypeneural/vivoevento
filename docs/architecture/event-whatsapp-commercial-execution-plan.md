# Event WhatsApp Commercial Execution Plan

## Objetivo

Este documento unifica a ordem de execucao entre:

- ativacao comercial do evento via `EventAccessGrant`;
- entitlements materializados em `events.current_entitlements_json`;
- CRUD do evento para canais de recebimento;
- intake de midia via WhatsApp Z-API;
- automacao operacional de feedback no WhatsApp;
- limites por pacote, bonus e `manual_override`.

Este passa a ser o backlog unico para a entrega de:

1. `manual_override` sem `package_id` no fluxo administrativo;
2. limites/capacidades de canais materializados pelo `EntitlementResolverService`;
3. configuracao de grupos, DM, link e instancia WhatsApp no proprio evento;
4. intake via Z-API respeitando entitlements e regras operacionais do evento.

## Fontes de verdade

Este plano consolida duas trilhas que continuam existindo como referencia:

- [whatsapp-zapi-webhook-execution-plan.md](./whatsapp-zapi-webhook-execution-plan.md)
- [billing-subscriptions-execution-plan.md](./billing-subscriptions-execution-plan.md)

Leituras complementares obrigatorias:

- [billing-subscriptions-discovery.md](./billing-subscriptions-discovery.md)
- [event-media-intake-architecture.md](./event-media-intake-architecture.md)
- [media-ingestion.md](../flows/media-ingestion.md)

Regra:

- os documentos acima continuam como fonte de contrato, contexto e diagnostico;
- este arquivo vira a fonte unica de sequenciamento, milestone e criterio de
  aceite da entrega integrada.

## Veredito executivo

As decisoes tecnicas que ficam travadas neste plano sao:

1. `EventAccessGrant` continua sendo o motor oficial de ativacao do evento;
2. `manual_override` precisa poder existir sem `package_id`;
3. grants administrativos devem aceitar snapshots diretos de features e limites;
4. `EntitlementResolverService` precisa materializar capacidades de canais em
   `current_entitlements_json`;
5. o modulo `WhatsApp` nao decide comercialmente nada sozinho:
   - ele so consome entitlements do evento;
6. `event_channels` deve ser o registro canonico dos canais do evento;
7. `whatsapp_group_bindings` continua operacional no curto prazo, mas como
   detalhe de implementacao;
8. a instancia WhatsApp continua pertencendo a organizacao;
9. o evento so referencia uma instancia default ou dedicada de forma
   operacional;
10. validacoes de webhook e parser precisam usar replays de payloads reais da
    Z-API, especialmente de grupos.

## Perguntas que este plano responde

1. como liberar evento free/bonus sem amarrar a um pacote fixo;
2. como transformar capacidades comerciais em limites reais de canais;
3. como o CRUD do evento deve expor grupos, DM, link, blacklist e instancia;
4. em que ordem entram billing, entitlements, CRUD e webhook;
5. quais testes precisam nascer antes de qualquer implementacao relevante;
6. quais gates devem estar verdes para considerar a integracao pronta.

## Estado atual reaproveitavel

Fundacao ja existente e que deve ser reaproveitada:

- `EventAccessGrant` ja existe e suporta `bonus` e `manual_override`;
- `EventCommercialStatusService` ja persiste `commercial_mode` e
  `current_entitlements_json`;
- `EntitlementResolverService` ja resolve:
  - modulos
  - branding
  - limites como `retention_days`, `max_active_events`, `max_photos`
- `admin/quick-events` ja cria evento + grant operacional;
- `event_channels` ja existe como tabela;
- `whatsapp_group_bindings` ja existe;
- `whatsapp_instances` ja e modelado por organizacao;
- `whatsapp-zapi-webhook-execution-plan.md` ja consolidou:
  - grupos por `group_external_id`
  - sessao privada por codigo
  - blacklist por `phone` e `@lid`
  - feedback com `messageId`
  - replay com logs reais.

Gaps atuais mais relevantes:

- `StoreAdminQuickEventRequest` ainda exige `grant.package_id`;
- `CreateAdminQuickEventAction` ainda monta grant sempre a partir de pacote;
- `EntitlementResolverService` ainda nao materializa limites/capacidades de
  canais;
- o CRUD do evento ainda nao conhece `intake_channels`;
- o intake WhatsApp ainda nao consome entitlements comerciais do evento.

## Regra de sequenciamento

Executar na ordem abaixo:

1. liberar o grant administrativo granular;
2. materializar entitlements de canais no evento;
3. expor e validar canais no CRUD do evento;
4. adaptar intake e webhook para consumir esses entitlements;
5. ligar automacao de feedback e bloqueio;
6. homologar com replays reais e validacao operacional.

Motivo:

- sem `manual_override` granular, o super admin continua preso a pacote;
- sem `current_entitlements_json` completo, o frontend e o backend divergem;
- sem CRUD do evento pronto, o intake fica sem fonte unica de configuracao;
- sem replays reais, o parser de grupo continua fragil.

## Regra de TTD obrigatoria

Nenhuma task de codigo fecha sem:

1. teste novo ou expandido escrito antes;
2. falha inicial pela razao certa;
3. implementacao minima para passar;
4. suite alvo verde;
5. regressao do modulo impactado verde;
6. doc atualizada se o contrato mudou.

Checklist obrigatorio por camada:

- Billing/Entitlements:
  - [ ] teste unitario do resolver
  - [ ] teste feature do endpoint/request/action
- Events CRUD:
  - [ ] teste feature HTTP
  - [ ] teste de resource/payload
  - [ ] teste frontend ou type-check quando impactado
- WhatsApp:
  - [ ] replay de payload real da Z-API
  - [ ] teste de roteamento
  - [ ] teste de idempotencia por `provider_message_id`
- Integracao final:
  - [ ] teste de entitlement refletindo no CRUD
  - [ ] teste de entitlement refletindo no intake

Regra adicional:

- fixtures reais da Z-API devem ser versionadas no repositorio e usadas como
  base para os testes mais criticos de grupo.

## Fontes obrigatorias de fixture e replay

As fontes minimas obrigatorias para os testes de webhook e intake sao:

- `apps/api/storage/logs/whatsapp-2026-04-05.log`
- `whatsapp_inbound_events.payload_json`
- payloads reais ja anonimizados de:
  - imagem em grupo com legenda
  - imagem em grupo sem legenda
  - texto em grupo
  - notificacao de grupo
  - reaction callback
  - `MessageStatusCallback`
  - DM com codigo
  - DM com `sair`

Regras para fixtures:

1. anonimizar telefone, nome e avatar antes de versionar;
2. preservar `phone`, `participantPhone`, `participantLid`, `senderName`,
   `messageId`, `instanceId`, `type`, `text`, `caption`, `reaction`,
   `status` e `momment`;
3. nunca simplificar a estrutura do payload para "JSON inventado" quando ja
   existir amostra real equivalente;
4. manter um `README` curto na pasta de fixtures dizendo de que webhook oficial
   da Z-API veio cada payload;
5. reaproveitar o mesmo fixture em mais de um teste quando o objetivo for
   validar parser, roteamento e idempotencia sobre a mesma evidencia real.

## Matriz de TDD/TTD por camada

### Billing e grants

- `admin/quick-events` com `manual_override` sem `package_id`
- `admin/quick-events` com `bonus` via pacote
- validacao de combinacoes invalidas
- persistencia de `features_snapshot_json`
- persistencia de `limits_snapshot_json`
- auditoria de `source_type`, `notes`, `expires_at`, `granted_by`

### Entitlements

- merge entre assinatura + bonus
- merge entre assinatura + `manual_override`
- override granular de canais sem quebrar limites antigos
- materializacao de:
  - `channels.whatsapp_groups.enabled`
  - `channels.whatsapp_groups.max`
  - `channels.whatsapp_direct.enabled`
  - `channels.public_upload.enabled`
  - `channels.telegram.enabled`
  - `channels.blacklist.enabled`
  - `channels.whatsapp.dedicated_instance.enabled`
  - `channels.whatsapp.feedback.reject_reply.enabled`
  - `channels.whatsapp.feedback.reject_reply.message`

### CRUD do evento

- create/update com `intake_channels`
- create/update com instancia `shared`
- create/update com instancia `dedicated`
- bloqueio por limite maximo de grupos
- bloqueio quando DM nao estiver liberado
- bloqueio quando upload nao estiver liberado
- bloqueio quando Telegram nao estiver liberado
- bloqueio de instancia dedicada exclusiva em evento diferente
- `show` retornando estado editavel e resumo comercial coerente

### WhatsApp webhook e intake

- replay real de grupo com imagem e legenda
- replay real de grupo com imagem sem legenda
- replay real de grupo com texto de ativacao
- replay real de grupo com notificacao de sistema ignorada
- replay real de `MessageStatusCallback` sem criar inbound
- replay real de `reaction` sem quebrar parser
- replay real de DM abrindo sessao
- replay real de DM encerrando sessao com `sair`
- replay com blacklist por `phone`
- replay com blacklist por `@lid`

### Feedback operacional

- relogio em fast lane ao aceitar midia elegivel
- coracao somente apos `MediaPublished`
- feedback negativo apos rejeicao manual
- feedback negativo apos bloqueio por IA
- reply textual com `messageId`
- idempotencia por `provider_message_id + feedback_phase`

### Integracao ponta a ponta

- `manual_override` cria entitlement de canais
- CRUD respeita o entitlement
- binding de grupo respeita o entitlement
- intake de grupo respeita binding + blacklist + instancia
- intake de DM respeita codigo + sessao + blacklist
- upload por link respeita entitlement do evento

## Estrutura sugerida da suite

Sugestao de organizacao para manter a suite legivel:

- `apps/api/tests/Feature/Billing/AdminQuickEventManualOverrideTest.php`
- `apps/api/tests/Unit/Billing/EntitlementResolverServiceTest.php`
- `apps/api/tests/Feature/Events/EventIntakeChannelsTest.php`
- `apps/api/tests/Feature/WhatsApp/WhatsAppWebhookReplayTest.php`
- `apps/api/tests/Feature/WhatsApp/WhatsAppInboundContextResolverTest.php`
- `apps/api/tests/Feature/WhatsApp/WhatsAppFeedbackAutomationTest.php`
- `apps/api/tests/Fixtures/WhatsApp/ZApi/*`

## Feature keys alvo para canais

O estado final de entitlements deve comportar pelo menos:

- `channels.whatsapp_groups.enabled`
- `channels.whatsapp_groups.max`
- `channels.whatsapp_direct.enabled`
- `channels.public_upload.enabled`
- `channels.telegram.enabled`
- `channels.blacklist.enabled`
- `channels.whatsapp.dedicated_instance.enabled`
- `channels.whatsapp.dedicated_instance.max_per_event`
- `channels.whatsapp.shared_instance.enabled`
- `channels.whatsapp.feedback.reject_reply.enabled`
- `channels.whatsapp.feedback.reject_reply.message`

## M0 - Fonte unica e contrato integrado

Objetivo:

- congelar o shape final entre grant comercial, entitlements, CRUD do evento e
  intake WhatsApp.

### TASK M0-T1 - Congelar o contrato integrado

Subtasks:

1. registrar este plano como backlog unico;
2. manter os dois planos anteriores como referencia;
3. travar a decisao:
   - `manual_override` sem pacote entra no escopo
   - `EntitlementResolverService` vira a fonte final de canais
   - `WhatsApp` apenas consome entitlements
4. travar os nomes das feature keys de canais.

TTD:

- nao se aplica para esta task documental.

Criterio de aceite:

- o time consegue apontar uma ordem oficial de entrega sem depender de
  interpretacao entre billing e WhatsApp.

## M1 - Grant administrativo granular sem pacote

Objetivo:

- permitir que super admin crie evento free/bonus com granularidade propria,
  sem depender de `event_package`.

### TASK M1-T1 - Tornar `manual_override` package-less

Estado atual:

- `grant.package_id` e obrigatorio no request;
- `CreateAdminQuickEventAction` usa `EventPackage::findOrFail(...)`.

Subtasks:

1. permitir `grant.package_id` opcional para `source_type=manual_override`;
2. adicionar no request um bloco de override explicito, por exemplo:
   - `grant.features`
   - `grant.limits`
3. manter `package_id` obrigatorio para `bonus` quando a regra de produto pedir
   preset comercial;
4. validar combinacoes invalidas:
   - `manual_override` sem pacote e sem snapshots
   - `bonus` sem pacote quando nao permitido
5. manter auditoria de:
   - ator
   - motivo
   - origem
   - notas
   - prazo.

TTD:

- [ ] teste feature de `admin/quick-events` com `manual_override` sem pacote
- [ ] teste de validacao para combinacoes invalidas
- [ ] teste garantindo que `bonus` continua funcionando com pacote
- [ ] teste garantindo persistencia de metadata do grant

Criterio de aceite:

- super admin consegue criar evento bonus/manual com capacidades explicitas sem
  selecionar pacote.

Arquivos provaveis:

- `apps/api/app/Modules/Billing/Http/Requests/StoreAdminQuickEventRequest.php`
- `apps/api/app/Modules/Billing/Actions/CreateAdminQuickEventAction.php`
- `apps/api/tests/Feature/Billing/*`

### TASK M1-T2 - Persistir snapshots manuais do grant

Subtasks:

1. persistir `features_snapshot_json` e `limits_snapshot_json` diretamente do
   request quando `manual_override` for package-less;
2. manter compatibilidade com snapshots vindos de pacote;
3. registrar na resposta do endpoint o resumo efetivo do grant.

TTD:

- [ ] teste unitario do action com snapshots diretos
- [ ] teste feature da resposta do endpoint

Criterio de aceite:

- o grant administrativo passa a carregar exatamente as capacidades concedidas
  pelo super admin.

## M2 - Entitlements de canais materializados no evento

Objetivo:

- expandir `current_entitlements_json` para suportar canais e instancia.

### TASK M2-T1 - Expandir `EntitlementResolverService`

Subtasks:

1. adicionar bloco `channels` no estado resolvido;
2. extrair os limites/capacidades de planos, pacotes e grants;
3. suportar merge coerente entre:
   - subscription
   - event purchase
   - bonus
   - manual override
4. definir defaults quando a feature nao existir;
5. manter compatibilidade com os limites atuais do evento.

TTD:

- [ ] teste unitario cobrindo plano com limites de canais
- [ ] teste unitario cobrindo `manual_override` package-less
- [ ] teste unitario cobrindo precedence entre subscription e grant
- [ ] teste unitario cobrindo mensagem de rejeicao configuravel

Criterio de aceite:

- `resolved_entitlements` passa a incluir capacidades de grupos, DM, link,
  Telegram, blacklist e instancia dedicada.

Arquivos provaveis:

- `apps/api/app/Modules/Billing/Services/EntitlementResolverService.php`
- `apps/api/tests/Unit/Billing/EntitlementResolverServiceTest.php`

### TASK M2-T2 - Persistir e expor o novo shape no evento

Subtasks:

1. garantir que `EventCommercialStatusService` persiste o novo bloco;
2. expor o resumo comercial em `EventDetailResource` e `commercial-status`;
3. indicar de onde veio a capacidade:
   - assinatura
   - pacote
   - bonus
   - manual override.

TTD:

- [ ] teste feature do endpoint de commercial status
- [ ] teste feature do detalhe do evento

Criterio de aceite:

- frontend consegue saber o que o evento pode ou nao pode usar antes de salvar.

## M3 - CRUD do evento para canais e instancia

Objetivo:

- fazer o evento virar a fonte unica de configuracao do intake.

Status de execucao em 2026-04-05:

- backend de `create`, `update` e `show` ja aceita e devolve
  `intake_channels` e `intake_defaults`
- `event_channels` ja esta sendo sincronizado como representacao canonica
- `whatsapp_group_bindings` ja esta sendo sincronizado a partir do evento
- backend ja bloqueia grupos, DM, upload, Telegram e instancia dedicada por
  entitlement
- `EventEditorPage` ja expoe os canais no `events/{id}/edit`
- o `events/create` ficou intencionalmente enxuto e agora redireciona para o
  editor completo apos o primeiro save
- a UX ja bloqueia grupos, DM, upload, Telegram e instancia dedicada com a
  mesma leitura de entitlement usada no backend
- `show`, `create` e `update` do evento agora tambem devolvem
  `intake_blacklist` com:
  - entradas persistidas
  - remetentes agregados do evento
  - contagem de webhooks e midias por remetente
  - identidade recomendada para bloqueio
- o `EventEditorPage` agora expoe a blacklist do evento com:
  - tabela de remetentes relacionados
  - avatar, nome, `phone`/`@lid`, ultima atividade e quantidade de midias
  - switch de bloqueio por remetente
  - prazo de bloqueio por `datetime-local`
  - CRUD manual completo das entradas de blacklist
- suite validada nesta etapa:
  - `tests/Feature/Events/EventIntakeChannelsTest.php`
  - `tests/Feature/Events/EventIntakeBlacklistTest.php`
  - regressao de `tests/Feature/Events`
  - regressao de `tests/Feature/Billing`
  - regressao de `tests/Unit/Billing`
  - `apps/web/src/modules/events/intake.test.ts`
  - `npm run type-check`
  - regressao de `npm run test -- src/modules/events`

### TASK M3-T1 - Adicionar `intake_channels` e `intake_defaults` ao contrato do evento

Subtasks:

1. aceitar `intake_channels` em create/update;
2. aceitar `intake_defaults.whatsapp_instance_id`;
3. aceitar `intake_defaults.whatsapp_instance_mode`:
   - `shared`
   - `dedicated`
4. devolver esse estado na `show` do evento.

TTD:

- [x] teste feature de create/update com payload de canais
- [x] teste feature do `show` retornando estado editavel

Criterio de aceite:

- a API do evento suporta grupos, DM, upload, blacklist e instancia default.

### TASK M3-T2 - Sincronizar `event_channels`

Subtasks:

1. usar `event_channels` como registro canonico;
2. mapear `channel_type` ao contrato do CRUD;
3. sincronizar grupos com `whatsapp_group_bindings`;
4. representar o canal de upload no mesmo modelo;
5. preparar canais futuros como `telegram_bot`.

TTD:

- [x] teste de sync de `event_channels`
- [x] teste de sync de `whatsapp_group_bindings`
- [x] teste de upload como canal ativo

Criterio de aceite:

- toda configuracao de intake do evento fica auditavel em uma representacao
  canonica.

### TASK M3-T3 - Aplicar limites comerciais no CRUD

Subtasks:

1. travar quantidade maxima de grupos;
2. travar liberacao de DM;
3. travar liberacao de upload;
4. travar liberacao de Telegram;
5. travar uso de instancia dedicada quando nao permitido;
6. impedir conflito de instancia exclusiva em eventos diferentes.

TTD:

- [x] teste feature backend de bloqueio por entitlement
- [x] teste frontend/type-check do estado bloqueado
- [x] teste de instancia dedicada exclusiva

Criterio de aceite:

- a UX e o backend convergem na mesma regra comercial.

## M4 - Webhook Z-API e roteamento comercialmente consciente

Objetivo:

- adaptar o intake para respeitar entitlements e configuracao do evento.

### TASK M4-T1 - Consumir entitlements no resolvedor de contexto

Status atualizado em `2026-04-05`:

- implementada para `whatsapp_group` e `whatsapp_direct`;
- o intake agora rejeita roteamento quando:
  - o evento nao esta `active`
  - o modulo `live` esta desligado
  - o entitlement do canal esta desabilitado
  - a instancia do webhook nao corresponde a `default_whatsapp_instance_id`.

Subtasks:

1. antes de aceitar grupo, validar `channels.whatsapp_groups.enabled`;
2. antes de abrir sessao DM, validar `channels.whatsapp_direct.enabled`;
3. antes de usar blacklist, validar `channels.blacklist.enabled`;
4. antes de aceitar canal futuro, validar entitlement correspondente;
5. ignorar intake quando o evento nao estiver ativo ou quando o modulo `live`
   nao estiver habilitado.

TTD:

- [x] replay de grupo com entitlement habilitado
- [x] replay de grupo com entitlement bloqueado
- [x] replay de DM com entitlement habilitado via sessao ativa

Criterio de aceite:

- o intake nao aceita nada que o evento comercialmente nao pode usar.

### TASK M4-T2 - Suportar instância default e dedicada no intake

Subtasks:

1. usar `default_whatsapp_instance_id` do evento como referencia de intake;
2. permitir override fino por canal quando isso entrar no contrato;
3. garantir que autovinculo de grupo respeita a instancia correta;
4. garantir que sessao DM abre na instancia correta.

Status atualizado em `2026-04-05`:

- implementada a validacao de intake contra `default_whatsapp_instance_id`;
- o webhook nao roteia grupo nem DM se a mensagem chegar por uma instancia
  diferente da configurada no evento.

Status atualizado em `2026-04-06`:

- o intake agora tambem se protege quando existe conflito operacional de
  instancia dedicada no banco;
- se dois eventos dedicados apontarem para a mesma `whatsapp_instance`, o
  roteamento de grupo e DM passa a ser ignorado ate o conflito ser resolvido.

TTD:

- [x] teste feature de grupo em instancia compartilhada
- [x] teste feature de DM na instancia default do evento
- [x] teste de conflito de instancia exclusiva

Criterio de aceite:

- o intake usa a instancia esperada pelo evento, sem ambiguidades.

## M5 - Automacao de feedback e politicas de bloqueio

Objetivo:

- padronizar o comportamento de reconhecimento, aprovacao e rejeicao.

Status atualizado em `2026-04-05`:

- implementada a automacao operacional em `WhatsAppFeedbackAutomationService`;
- o sistema agora persiste idempotencia por fase em
  `whatsapp_message_feedbacks`;
- o relogio (`detected`) sai na fast lane antes do dispatch para o pipeline
  canonico;
- o coracao (`published`) sai ao consumir `MediaPublished`;
- a rejeicao (`blocked` / `rejected`) envia reacao negativa e reply textual em
  thread usando `messageId`;
- para grupos, o reply negativo sai com `privateAnswer=true`;
- o pipeline canonico de `InboundMedia` agora consome `_event_context` e cria
  `inbound_messages` e `event_media` reais a partir dele.
- a moderacao agora recebe contexto operacional do remetente por midia com:
  - `phone`, `@lid`, `external_id`, avatar e identidade recomendada
  - estado atual de bloqueio no evento
  - quantidade de midias desse remetente no evento no payload detalhado
- a UI de moderacao agora expoe:
  - filtro de `remetente bloqueado`
  - badge visual de bloqueio no feed
  - bloqueio/desbloqueio rapido do remetente a partir do review da midia
- o detalhe do evento ja expoe remetentes recentes dentro da blacklist com:
  - switch de bloqueio
  - prazo de expiracao
  - contagem de midias por remetente
- a superficie de galeria por evento ainda nao recebeu o mesmo atalho rapido de
  bloqueio/desbloqueio, entao isso segue como pendencia explicita do plano;
- validacao end-to-end agora existe para:
  - rejeicao manual do gestor via action HTTP + listener
  - rejeicao por IA via `RunModerationJob` + listener

### TASK M5-T1 - Reacao por fase com `messageId`

Subtasks:

1. relogio quando a midia elegivel entra na fila;
2. coracao quando `MediaPublished` acontecer;
3. feedback negativo quando a midia for rejeitada;
4. idempotencia por `provider_message_id + feedback_phase`;
5. nunca reagir em eventos inativos ou sem entitlement.

TTD:

- [x] teste de relogio em fast lane
- [x] teste de coracao em `MediaPublished`
- [x] teste de rejeicao sem colidir com coracao
- [x] teste de retry sem duplicidade

Criterio de aceite:

- o feedback no WhatsApp reflete o lifecycle real da midia.

### TASK M5-T2 - Reply textual negativo padrao

Subtasks:

1. usar `send-text` com `messageId` da mensagem original;
2. responder com a copia padrao:
   - `Sua midia nao segue as diretrizes do evento. 🛡️`
3. permitir que a copia venha de entitlement/grant no futuro;
4. cobrir:
   - bloqueio pela IA
   - rejeicao manual do gestor
   - politica de bloqueio do evento quando configurada para responder.

TTD:

- [x] teste de moderacao AI rejeitando
- [x] teste de rejeicao manual
- [x] teste de payload de outbound com `messageId`
- [x] teste de bloqueio/desbloqueio rapido de remetente via moderacao
- [x] teste de feed filtrando remetentes bloqueados

Criterio de aceite:

- a rejeicao passa a ter comportamento previsivel e alinhado ao produto.

### TASK M5-T3 - Levar atalhos de bloqueio para galeria e detalhe do evento

Objetivo:

- garantir que o operador consiga agir sobre o remetente sem depender apenas do
  review da moderacao.

Status auditado em `2026-04-06`:

- a listagem de remetentes no detalhe do evento agora esta exposta tambem no
  `EventDetailPage`, com:
  - switch de bloqueio/desbloqueio rapido
  - prazo rapido por preset
  - atalho para abrir moderacao filtrada por remetente
  - atalho para abrir galeria filtrada por remetente
- a moderacao continua permitindo bloqueio/desbloqueio rapido a partir da
  propria midia;
- a galeria/listagem operacional por evento agora tambem recebeu:
  - badge de remetente bloqueado
  - acao rapida de bloqueio/desbloqueio
  - busca por `phone`, `@lid` e `external_id`
  - prefill via query string a partir do detalhe do evento.

Subtasks:

1. expor remetente e estado de bloqueio na superficie de galeria por evento;
2. permitir bloquear/desbloquear o remetente a partir do card/row da galeria;
3. permitir atalho a partir da listagem de remetentes do detalhe do evento para:
   - abrir a moderacao filtrada por remetente
   - abrir a galeria/listagem filtrada por remetente quando essa superficie
     existir
4. reutilizar os endpoints operacionais ja existentes de `sender-block`;
5. manter convergencia visual e funcional entre evento, galeria e moderacao.

TTD:

- [x] teste frontend da galeria mostrando remetente bloqueado
- [x] teste frontend de bloqueio rapido a partir da galeria
- [x] teste frontend de atalho da listagem de remetentes do evento para abrir
  lista filtrada
- [x] teste de convergencia entre galeria, moderacao e blacklist do evento

Criterio de aceite:

- o operador consegue bloquear um remetente a partir das superficies
  operacionais principais, sem perder contexto.

## M6 - Sessao DM, grupos, blacklist e comandos

Objetivo:

- fechar a operacao real de grupo e conversa privada.

### TASK M6-T1 - Autovinculo de grupo

Status atualizado em `2026-04-05`:

- implementado parser de `#ATIVAR#<group_bind_code>` dentro do proprio grupo;
- o fluxo valida evento ativo, modulo `live`, entitlement de grupos,
  `default_whatsapp_instance_id` e blacklist do remetente;
- o binding e criado ou reativado por `instance_id + group_external_id`;
- codigos desconhecidos sao ignorados sem criar binding nem rotear intake.

Subtasks:

1. parser de `#ATIVAR#<group_bind_code>`;
2. validar grupo, evento, instancia e blacklist;
3. criar ou reativar binding;
4. desconsiderar grupo nao vinculado ou codigo invalido para intake do evento.

TTD:

- [x] replay real de grupo com codigo valido
- [x] replay real de grupo com codigo invalido
- [x] replay real de grupo nao vinculado

Criterio de aceite:

- o contratante consegue vincular grupo de forma pratica e segura.

### TASK M6-T2 - Sessao privada por codigo e comando `sair`

Status atualizado em `2026-04-05`:

- implementada em primeira versao operacional;
- o webhook privado agora:
  - abre sessao por `media_inbox_code`
  - responde em thread usando `send-text` com `messageId`
  - encaminha midia privada quando a sessao estiver ativa
  - encerra a sessao quando o remetente envia `sair`
  - bloqueia abertura quando o remetente esta na blacklist do evento,
    respondendo com feedback negativo.

Subtasks:

1. abrir sessao por `media_inbox_code`;
2. responder em thread com `messageId`;
3. encerrar sessao com `sair`;
4. bloquear abertura se o remetente estiver na blacklist.

TTD:

- [x] replay adaptado de DM com codigo valido
- [x] teste de comando `sair`
- [x] teste de blacklist bloqueando sessao

Criterio de aceite:

- DM passa a funcionar como intake controlado por codigo e sessao.

## M7 - Suite unica de validacao

Objetivo:

- garantir regressao integrada entre billing, CRUD e WhatsApp.

### TASK M7-T1 - Consolidar fixtures reais da Z-API

Subtasks:

1. extrair e anonimizar payloads reais dos logs;
2. versionar fixtures de:
   - imagem de grupo com legenda
   - texto em grupo
   - notificacao de grupo
   - reaction callback
   - `MessageStatusCallback`
3. reaproveitar o envelope real para DM.

### TASK M7-T2 - Criar matriz de testes integrada

Subtasks:

1. testes unitarios de entitlements;
2. testes feature de `admin/quick-events`;
3. testes feature do CRUD do evento;
4. testes feature do webhook;
5. testes de replay com payload real;
6. testes de feedback e rejeicao.

Criterio de aceite:

- a entrega fica protegida ponta a ponta pela mesma suite.

## M8 - Homologacao e rollout

Objetivo:

- validar o comportamento integrado em ambiente real.

### TASK M8-T1 - Roteiro de homologacao

Subtasks:

1. criar evento bonus/manual sem pacote;
2. configurar grupos/DM/upload no CRUD;
3. validar limite de grupos por entitlement;
4. validar instancia compartilhada e dedicada;
5. validar grupo nao vinculado sendo ignorado;
6. validar relogio, coracao e feedback negativo;
7. validar `sair` em DM.

### TASK M8-T2 - Evidencias operacionais

Status atualizado em `2026-04-06`:

- o named tunnel fixo voltou a responder `200` em
  `https://webhooks-local.eventovivo.com.br/up`;
- uma chamada real de `send-text` para a Z-API foi aceita e retornou
  `messageId = AAB0BDE8C3527578E13E`;
- ao drenar a fila `whatsapp-inbound`, apareceu callback real de
  `MessageStatusCallback` para esse `messageId`, persistida como evento
  `delivery/ignored`; isso e esperado porque o envio foi feito direto contra a
  Z-API e nao existia mensagem outbound local correspondente;
- a mesma rodada expôs e corrigiu um bug de timestamp real da Z-API: alguns
  callbacks chegam com `momment` em microssegundos, nao em segundos;
- em uma segunda rodada real no mesmo dia, fotos enviadas em grupo e DM
  chegaram como `ReceivedCallback`, foram persistidas em `whatsapp_messages` e
  corretamente nao geraram `inbound_messages` nem `event_media` porque o grupo
  nao estava vinculado e a DM nao tinha sessao aberta;
- a rodada tambem expôs e corrigiu callback real de `reaction` estruturado
  como array;
- a rodada positiva local com o evento `31` confirmou grupo vinculado e DM com
  sessao ativa gerando `event_media` para `whatsapp_group` e
  `whatsapp_direct`;
- a validacao local tambem expos dois ajustes operacionais do painel: o editor
  nao pode enviar `0` quando uma retencao customizada ou instancia atual nao
  aparece no select, e a galeria precisa tratar `whatsapp_group` e
  `whatsapp_direct` como canal `whatsapp`;
- no Windows local, previews de midia dependem do junction `public/storage`
  apontando para `storage/app/public`; sem isso os arquivos existem, mas a URL
  publica pode falhar;
- a homologacao real ainda continua aberta para o cenario positivo completo:
  grupo vinculado ou DM com sessao ativa gerando intake canonico e feedback.

Subtasks:

1. guardar ids reais de mensagem;
2. guardar payloads anonimizados usados como evidencias;
3. comparar comportamento real com replay automatizado;
4. atualizar docs quando houver divergencia.

## Pendencias remanescentes auditadas em `2026-04-06`

Estas pendencias ja nao sao inferencia; elas continuam abertas no plano depois
da rodada atual de implementacao:

- formalizar o provisioning/onboarding de uma nova instancia WhatsApp dedicada
  por evento; hoje o plano cobre selecao e vinculo de instancia existente, mas
  nao a jornada completa de criar/conectar uma nova instancia a partir do
  contexto do evento;
- fechar a homologacao real por callback e registrar as evidencias finais; na
  rodada atual o endpoint publico respondeu `200`, o envio real foi aceito
  pela Z-API, a callback de delivery chegou, e callbacks reais de imagem em
  grupo/DM sem vinculo foram persistidos sem criar intake de evento; ainda
  falta concluir a evidencia positiva de `ReceivedCallback` com grupo vinculado
  ou DM com sessao ativa.

## Fora de escopo imediato

- Telegram em producao;
- IA reply rico em grupo;
- listagem live de grupos diretamente da Z-API;
- politicas avancadas de burst/lote alem do essencial para feedback;
- reforma completa do modulo `InboundMedia`.

## Criterio de aceite global

Este plano pode ser considerado fechado quando:

- super admin consegue criar evento `bonus` ou `manual_override` sem pacote;
- o evento passa a expor entitlements comerciais de canais no estado resolvido;
- o CRUD do evento vira a fonte unica de configuracao dos canais de intake;
- o intake WhatsApp respeita limites, blacklist e instancia configurada;
- grupo, DM e feedback de rejeicao funcionam com `messageId`;
- replays reais da Z-API protegem os cenarios de grupo ja observados em log;
- existe evidencia automatizada de convergencia entre evento, galeria e
  moderacao para o mesmo remetente bloqueado;
- a suite integrada cobre billing, CRUD e webhook sem depender de heuristica
  manual.
