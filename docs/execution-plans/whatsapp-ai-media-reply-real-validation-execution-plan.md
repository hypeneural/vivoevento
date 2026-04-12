# WhatsApp AI Media Reply Real Validation Execution Plan

## Objetivo

Fechar a validacao real de um evento com:

- intake por WhatsApp grupo e DM;
- moderacao por IA ativa no pipeline;
- reply da midia aprovada ativo;
- observabilidade suficiente para diagnosticar qualquer falha de webhook, provider externo, fila ou regra de negocio.

Este plano tambem evolui a feature de `reply_text` para suportar:

- sem resposta;
- resposta por IA baseada na imagem;
- resposta fixa com 1 ou mais textos cadastrados e selecao deterministica.

Para a evolucao da area dedicada de IA, presets, teste do prompt com ate `3` imagens, historico persistido e tarefas especificas de front/backend/banco, seguir tambem:

- `docs/execution-plans/ia-respostas-automaticas-de-midia-execution-plan.md`

---

## Escopo

Inclui:

- `ContentModeration` com OpenAI real;
- `MediaIntelligence` para `reply_text`;
- `WhatsApp` via Z-API com reply em thread usando `messageId`;
- painel admin para configurar prompt global, override por evento e respostas fixas;
- trilha de debug operacional para homologacao real.

Nao inclui nesta rodada:

- ampliacao do dataset do `FaceSearch`;
- recalibracao do `FaceSearch`;
- nova engine de WhatsApp;
- automacao de provisionamento da Z-API fora do que ja existe.

---

## Estado Atual Validado

### Ja validado no ambiente local

- [x] existe um evento local ativo para homologacao:
  - `event_id = 31`
  - titulo: `Homologacao WhatsApp Z-API Local`
- [x] a instancia WhatsApp default do evento existe e esta conectada:
  - `whatsapp_instance_id = 1`
  - provider: `zapi`
  - status: `connected`
- [x] o evento ja possui canais ativos:
  - grupo: `GRUPO-EVIVO-TESTE`
  - DM: `FOTOS-EVIVO-TESTE`
- [x] o autovinculo de grupo por comando ja existe no codigo:
  - comando esperado: `#ATIVAR#GRUPO-EVIVO-TESTE`
- [x] o reply em thread via Z-API ja existe no codigo usando `send-text` com `messageId`
- [x] o smoke real de `ContentModeration` na OpenAI esta funcionando hoje no local
- [x] o `reply_text` por IA ja existe no backend e na UI, mas apenas em modo binario `enabled/disabled`

### Fatos operacionais importantes

- o evento `31` ainda esta com `moderation_mode = none`;
- nao existe linha de settings de `ContentModeration` para o evento `31`;
- nao existe linha de settings de `MediaIntelligence` para o evento `31`;
- o canal de grupo existe e o grupo remoto ja esta mapeado como `120363425796926861-group`;
- a tentativa de obter `invitationLink` do grupo via provider retornou `success=true`, mas `invitationLink=null`;
- portanto, hoje eu consigo te entregar os codigos operacionais, mas nao um link de convite obtido programaticamente.

### Relatorios reais ja gerados

- OpenAI moderation:
  - `apps/api/storage/app/content-moderation-smoke/20260407-235851-openai-real-run.json`
  - `apps/api/storage/app/content-moderation-smoke/20260408-000825-openai-real-run.json`
- OpenRouter VLM:
  - ultimo sucesso validado:
    - `apps/api/storage/app/media-intelligence-smoke/20260407-215035-openrouter-real-run.json`
- ponto de atencao:
  - uma reexecucao local posterior do smoke do OpenRouter retornou `HTTP 400`;
  - isso nao invalida o sucesso anterior, mas vira blocker operacional para usar `OpenRouter` na homologacao do evento sem nova revalidacao.

### Revalidacao automatizada mais recente

- backend:
  - `RunOpenAiContentModerationSmokeCommandTest`
  - `MediaIntelligenceGlobalSettingsTest`
  - `MediaIntelligenceSettingsTest`
  - `WhatsAppEventAutomationTest`
  - `TelegramFeedbackAutomationTest`
- frontend:
  - `EventMediaIntelligenceSettingsForm.test.tsx`
  - `SettingsPage.test.tsx`
  - `npm run type-check`

Resultado:

- backend: `31 passed`, `263 assertions`
- frontend: `16 passed`
- type-check: `passed`

---

## Contratos Externos Congelados

### OpenAI moderation

- endpoint oficial: `POST /v1/moderations`
- contrato do app:
  - caminho preferencial: `image_url`
  - fallback operacional: `data_url`

### Z-API reply em thread

- endpoint oficial: `POST /send-text`
- contrato do app:
  - enviar `messageId` da midia recebida;
  - em grupo, usar `privateAnswer=true` quando a resposta tiver que sair no privado;
  - manter `reaction` e `reply_text` como operacoes separadas.

### Z-API webhook inbound

- webhook de recebimento: `ReceivedCallback`
- webhook de status de mensagem: `MessageStatusCallback`
- campos operacionais que o app precisa preservar:
  - `messageId`
  - `phone`
  - `isGroup`
  - `participantPhone`
  - `participantLid`
  - `momment`
  - `type`

---

## Decisoes De Implementacao

### 1. Reply mode deixa de ser booleano

O modelo atual `reply_text_enabled` nao cobre o produto.

Precisamos de um modo explicito:

- `disabled`
- `ai`
- `fixed_random`

### 2. Prompt global continua existindo

O prompt global continua sendo a base do modo `ai`.

O evento pode sobrescrever o prompt global via `reply_prompt_override`.

### 3. Resposta fixa deve ser deterministica por midia

Nao vale sortear de forma diferente a cada retry.

A regra recomendada e:

- escolher o texto fixo com hash deterministico de `event_id + event_media_id + lista_de_templates`;
- persistir o texto efetivamente enviado nas tabelas de feedback;
- manter o mesmo texto em retries e entre canais.

### 4. Maximo log nao significa duplicar tudo em todas as tabelas

Ja existe bastante dado bruto no banco:

- `whatsapp_inbound_events.payload_json`
- `whatsapp_messages.payload_json`
- `whatsapp_dispatch_logs.request_json`
- `whatsapp_dispatch_logs.response_json`
- `event_media_safety_evaluations.raw_response_json`
- `event_media_vlm_evaluations.raw_response_json`
- `media_processing_runs`

O que falta e:

- request payload das chamadas de IA;
- correlacao ponta a ponta;
- endpoints/resources para leitura operacional.

### 5. Validacao real deve usar o evento local ja existente

Evento recomendado para homologacao:

- `event_id = 31`
- titulo: `Homologacao WhatsApp Z-API Local`

Codigos operacionais atuais:

- grupo: `GRUPO-EVIVO-TESTE`
- DM: `FOTOS-EVIVO-TESTE`

---

## Gaps Validados No Codigo Atual

### Gap A - `reply_text` ainda e so binario

Hoje existe apenas:

- `reply_text_enabled`
- `reply_prompt_override`

Ainda nao existe:

- `reply_text_mode`
- templates fixos globais
- templates fixos por evento
- selecao deterministica de resposta fixa

### Gap B - o painel nao expoe o codigo do grupo de forma operacional

O codigo de grupo existe no banco, mas nao aparece de forma clara no detalhe do evento.

Tambem existe copy desatualizada no frontend dizendo que o autovinculo entraria "na proxima etapa", apesar de ele ja existir.

### Gap C - logs brutos existem, mas a API nao expoe detalhe suficiente

Hoje:

- `WhatsAppDispatchLogResource` nao devolve `request_json` nem `response_json`;
- `WhatsAppInboundEventResource` nao devolve `payload_json` nem `normalized_json`;
- `EventMediaDetailResource` expoe versoes resumidas das avaliacoes de safety e VLM, sem request payload;
- nao existe endpoint agregador de debug por midia.

### Gap D - nao existe trace operacional ponta a ponta no pipeline async

O app ja gera `request_id` e `trace_id` no HTTP middleware.

Mas esse contexto nao esta sendo propagado de forma util para:

- webhook bruto
- `InboundMessage`
- `media_processing_runs`
- feedback outbound
- logs de provider

### Gap E - OpenRouter ainda precisa de revalidacao operacional

O ultimo smoke real bem-sucedido existe.

Mas a reexecucao mais recente retornou `400`.

Enquanto isso nao for explicado ou estabilizado, a homologacao do evento deve considerar:

- `OpenRouter` como candidato preferencial para `reply_text` por IA;
- mas com fallback operacional para `fixed_random` se o provider estiver instavel.

---

## Impacto No Banco

## Recomendacao

Incrementar o banco apenas onde o contrato novo realmente exige persistencia ou auditoria.

### Tabelas que precisam mudar

#### `media_intelligence_global_settings`

Adicionar:

- `reply_text_fixed_templates_json`

Motivo:

- guardar lista global default de textos fixos.

#### `event_media_intelligence_settings`

Adicionar:

- `reply_text_mode`
- `reply_fixed_templates_json`

Motivo:

- o evento precisa escolher `disabled|ai|fixed_random`;
- e, em `fixed_random`, poder sobrescrever a lista global.

#### `event_media_safety_evaluations`

Adicionar:

- `request_payload_json`

Motivo:

- hoje temos a resposta bruta do provider, mas nao o request efetivamente enviado.

#### `event_media_vlm_evaluations`

Adicionar:

- `request_payload_json`

Motivo:

- diagnosticar prompt, schema, payload multimodal e saida do provider real.

#### `whatsapp_message_feedbacks`

Adicionar:

- `resolution_json`

Conteudo esperado:

- `reply_mode`
- `reply_source`
- `prompt_source`
- `template_count`
- `template_index`
- `trace_id`

Motivo:

- saber por que uma resposta foi enviada, de onde veio e qual estrategia foi aplicada.

#### `telegram_message_feedbacks`

Adicionar:

- `resolution_json`

Motivo:

- manter paridade com WhatsApp.

### O que nao precisa de nova tabela nesta rodada

- nao criar tabela separada para templates fixos;
- nao criar tabela separada para trace/correlacao;
- nao criar tabela separada para log de IA.

### Motivo da recomendacao

- o produto ainda esta na fase de homologacao operacional;
- JSON em settings e suficiente para uma primeira rodada;
- tabelas dedicadas para templates so valem se surgir requisito de analytics, ordering complexo, versionamento ou ownership por template.

---

## Impacto No Backend

### MediaIntelligence

Arquivos centrais a tocar:

- `apps/api/app/Modules/MediaIntelligence/Models/EventMediaIntelligenceSetting.php`
- `apps/api/app/Modules/MediaIntelligence/Models/MediaIntelligenceGlobalSetting.php`
- `apps/api/app/Modules/MediaIntelligence/Actions/UpsertEventMediaIntelligenceSettingsAction.php`
- `apps/api/app/Modules/MediaIntelligence/Actions/UpsertMediaIntelligenceGlobalSettingsAction.php`
- `apps/api/app/Modules/MediaIntelligence/Http/Requests/UpsertEventMediaIntelligenceSettingsRequest.php`
- `apps/api/app/Modules/MediaIntelligence/Http/Requests/UpsertMediaIntelligenceGlobalSettingsRequest.php`
- `apps/api/app/Modules/MediaIntelligence/Http/Resources/EventMediaIntelligenceSettingResource.php`
- `apps/api/app/Modules/MediaIntelligence/Http/Resources/MediaIntelligenceGlobalSettingResource.php`
- `apps/api/app/Modules/MediaIntelligence/Services/MediaReplyTextPromptResolver.php`
- `apps/api/app/Modules/MediaIntelligence/Services/PublishedMediaReplyTextResolver.php`
- `apps/api/app/Modules/MediaIntelligence/Services/PublishedMediaAiReplyDispatcher.php`
- `apps/api/app/Modules/MediaIntelligence/Jobs/EvaluateMediaPromptJob.php`

Classes novas recomendadas:

- `ReplyTextMode` enum
- `ReplyTextConfigResolver`
- `FixedReplyTextSelector`
- `MediaReplyTextTraceBuilder`

### ContentModeration

Arquivos centrais a tocar:

- `apps/api/app/Modules/ContentModeration/Services/OpenAiContentModerationProvider.php`
- `apps/api/app/Modules/ContentModeration/DTOs/ContentSafetyEvaluationResult.php`

Objetivo:

- logar request payload de moderation;
- propagar `trace_id`;
- manter o smoke real como comando operacional.

### WhatsApp

Arquivos centrais a tocar:

- `apps/api/app/Modules/WhatsApp/Services/WhatsAppFeedbackAutomationService.php`
- `apps/api/app/Modules/WhatsApp/Jobs/SendWhatsAppMessageJob.php`
- `apps/api/app/Modules/WhatsApp/Http/Resources/WhatsAppDispatchLogResource.php`
- `apps/api/app/Modules/WhatsApp/Http/Resources/WhatsAppInboundEventResource.php`
- `apps/api/app/Modules/WhatsApp/Http/Controllers/WhatsAppLogController.php`
- `apps/api/app/Modules/WhatsApp/Services/WhatsAppGroupActivationService.php`

Objetivo:

- manter `messageId` como ancora do reply;
- expor mais dado bruto para debug;
- enriquecer feedback com `resolution_json`.

### InboundMedia / MediaProcessing

Arquivos centrais a tocar:

- `apps/api/app/Modules/InboundMedia/Jobs/ProcessInboundWebhookJob.php`
- `apps/api/app/Modules/InboundMedia/Jobs/NormalizeInboundMessageJob.php`
- `apps/api/app/Modules/MediaProcessing/Http/Resources/EventMediaDetailResource.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaProcessingRunService.php`

Objetivo:

- propagar `trace_id` do webhook ate a decisao final;
- expor um debug timeline por midia.

### Debug operacional

Novo endpoint recomendado:

- `GET /api/v1/events/{event}/media/{media}/ai-debug`

Resposta minima:

- `trace_id`
- inbound webhook bruto
- inbound normalizado
- runs do pipeline
- safety request/response
- VLM request/response
- feedback outbound
- dispatch logs WhatsApp/Telegram

Permissao recomendada:

- `audit.view` ou `channels.manage`

---

## Impacto No Frontend

### Form do evento

Arquivo principal:

- `apps/web/src/modules/events/components/media-intelligence/EventMediaIntelligenceSettingsForm.tsx`

Mudancas:

- trocar switch de `reply_text_enabled` por select:
  - `Sem resposta`
  - `Resposta por IA`
  - `Resposta fixa aleatoria`
- exibir textarea de prompt somente em modo `ai`
- exibir lista de templates fixos somente em modo `fixed_random`
- permitir add/remove de multiplos textos

### Config global

Arquivos principais:

- `apps/web/src/modules/settings/SettingsPage.tsx`
- `apps/web/src/modules/settings/api.ts`
- `apps/web/src/modules/settings/types.ts`

Mudancas:

- manter prompt global de IA
- adicionar lista global de templates fixos
- copy operacional explicando precedencia:
  - evento sobrescreve global
  - se lista do evento estiver vazia, usa global

### Evento / operacao

Arquivos principais:

- `apps/web/src/modules/events/EventDetailPage.tsx`
- `apps/web/src/modules/events/components/EventEditorPage.tsx`
- `apps/web/src/modules/events/api.ts`
- `apps/web/src/lib/api-types.ts`

Mudancas:

- mostrar `group_bind_code` e `media_inbox_code` no detalhe do evento
- adicionar botao copiar para:
  - `#ATIVAR#GRUPO-EVIVO-TESTE`
  - `FOTOS-EVIVO-TESTE`
- corrigir copy desatualizada do editor que ainda fala "proxima etapa"
- adicionar card de validacao operacional ou link para debug timeline da midia

### Logs operacionais

Opcional forte nesta rodada:

- ampliar o modulo de WhatsApp para mostrar `request_json` e `response_json`
- ou criar uma aba no detalhe do evento para debug da homologacao

---

## Estrategia De `reply_text`

## Modo `disabled`

- nenhuma resposta textual e enviada;
- reacao pode continuar ativa se o evento quiser.

## Modo `ai`

- o provider VLM gera `reply_text` baseado na imagem;
- prompt efetivo:
  - override do evento, se existir;
  - senao prompt global;
- o resultado fica persistido em `event_media_vlm_evaluations.reply_text`;
- o dispatch usa esse valor.

## Modo `fixed_random`

- nao depende do provider VLM para `reply_text`;
- seleciona um texto da lista do evento ou da lista global;
- a selecao deve ser deterministica por midia;
- o texto efetivo enviado fica persistido no feedback do canal.

## Precedencia recomendada

1. `reply_text_mode` do evento
2. prompt override do evento
3. templates fixos do evento
4. prompt global
5. templates fixos globais

---

## Observabilidade Recomendada

## Ja existe e deve ser reaproveitado

- `whatsapp_inbound_events`
- `whatsapp_messages`
- `whatsapp_dispatch_logs`
- `channel_webhook_logs`
- `event_media_safety_evaluations`
- `event_media_vlm_evaluations`
- `media_processing_runs`
- `whatsapp_message_feedbacks`
- `telegram_message_feedbacks`

## O que precisa ser adicionado

### 1. request payload das chamadas de IA

Sem isso, fica impossivel fechar diagnostico de:

- prompt mal composto;
- schema invalido;
- payload multimodal incompleto;
- escolha errada de `path_used`.

### 2. `trace_id` no pipeline async

Objetivo:

- correlacionar um unico envio do usuario com:
  - webhook bruto
  - inbound message
  - download
  - moderation
  - VLM
  - feedback outbound

### 3. canal de log dedicado para IA

Adicionar em `config/logging.php`:

- `ai-pipeline`

Eventos minimos:

- request para moderation
- response de moderation
- request para VLM
- response de VLM
- falha de parse
- fallback `image_url -> data_url`
- erro de provider externo

### 4. endpoints debug mais completos

O painel nao precisa mostrar tudo por padrao.

Mas o operador precisa ter como abrir:

- payload bruto do webhook;
- payload normalizado;
- request/response do outbound;
- request/response de AI;
- contexto de feedback aplicado.

---

## Plano De Implementacao

### Fase R1 - Estrategia de reply_text

- [ ] `R1-T1` criar enum `ReplyTextMode`
- [ ] `R1-T2` migrar settings para `reply_text_mode`
- [ ] `R1-T3` adicionar `reply_text_fixed_templates_json` global
- [ ] `R1-T4` adicionar `reply_fixed_templates_json` por evento
- [ ] `R1-T5` criar `ReplyTextConfigResolver`
- [ ] `R1-T6` criar `FixedReplyTextSelector` com selecao deterministica
- [ ] `R1-T7` adaptar `PublishedMediaReplyTextResolver`
- [ ] `R1-T8` manter compatibilidade de leitura com `reply_text_enabled` durante a transicao

### Fase R2 - Observabilidade e logs

- [ ] `R2-T1` adicionar `request_payload_json` em safety
- [ ] `R2-T2` adicionar `request_payload_json` em VLM
- [ ] `R2-T3` adicionar `resolution_json` em feedbacks WhatsApp e Telegram
- [ ] `R2-T4` propagar `trace_id` do webhook para o pipeline async
- [ ] `R2-T5` criar canal `ai-pipeline`
- [ ] `R2-T6` enriquecer logs de provider com `trace_id`, `event_id`, `media_id`, `path_used`, `latency_ms`
- [ ] `R2-T7` expor `request_json/response_json` nas resources de log protegidas por permissao

### Fase R3 - Painel admin

- [ ] `R3-T1` trocar switch por `reply_text_mode` no form do evento
- [ ] `R3-T2` adicionar editor de templates fixos no evento
- [ ] `R3-T3` adicionar editor de templates fixos globais
- [ ] `R3-T4` mostrar `group_bind_code` e `media_inbox_code` no detalhe do evento
- [ ] `R3-T5` corrigir copy antiga do `EventEditorPage`
- [ ] `R3-T6` adicionar acesso a debug timeline da midia

### Fase R4 - Homologacao real do evento 31

- [ ] `R4-T1` ativar `moderation_mode = ai` no evento `31`
- [ ] `R4-T2` criar settings de `ContentModeration` para o evento `31`
- [ ] `R4-T3` criar settings de `MediaIntelligence` para o evento `31`
- [ ] `R4-T4` rodar com `reply_text_mode = ai`
- [ ] `R4-T5` rodar com `reply_text_mode = fixed_random`
- [ ] `R4-T6` capturar artefatos operacionais dos 2 modos

### Fase R5 - Runbook de validacao

- [ ] `R5-T1` validar webhook inbound real
- [ ] `R5-T2` validar autovinculo do grupo com `#ATIVAR#GRUPO-EVIVO-TESTE`
- [ ] `R5-T3` validar intake de imagem no grupo
- [ ] `R5-T4` validar moderation pass
- [ ] `R5-T5` validar reply em thread no WhatsApp
- [ ] `R5-T6` validar intake por DM com `FOTOS-EVIVO-TESTE`
- [ ] `R5-T7` comparar IA vs texto fixo

---

## Bateria De Testes Recomendada

### Backend unit

- [ ] resolver de modo efetivo
- [ ] seletor deterministico de templates fixos
- [ ] precedencia global vs evento
- [ ] comportamento `disabled`
- [ ] comportamento `ai`
- [ ] comportamento `fixed_random`

### Backend feature

- [ ] update das settings globais
- [ ] update das settings por evento
- [ ] dispatch de WhatsApp com `messageId`
- [ ] `privateAnswer=true` em grupo quando aplicavel
- [ ] persistencia de `resolution_json`
- [ ] debug endpoint agregando trilha da midia
- [ ] smoke real OpenAI segue verde

### Frontend

- [ ] form do evento com os 3 modos
- [ ] add/remove de templates fixos
- [ ] copy buttons dos codigos operacionais
- [ ] settings globais com prompt + templates

### Manual

- [ ] enviar `#ATIVAR#GRUPO-EVIVO-TESTE` no grupo
- [ ] enviar imagem no grupo
- [ ] validar publicacao da midia
- [ ] validar reacao e reply em thread
- [ ] repetir em DM com `FOTOS-EVIVO-TESTE`
- [ ] repetir com `reply_text_mode = fixed_random`

---

## Runbook Da Homologacao Real

## Evento alvo

- evento: `31`
- titulo: `Homologacao WhatsApp Z-API Local`

## Codigos operacionais atuais

- grupo:
  - `GRUPO-EVIVO-TESTE`
- DM:
  - `FOTOS-EVIVO-TESTE`

## Comando do grupo

Enviar no grupo:

```text
#ATIVAR#GRUPO-EVIVO-TESTE
```

## Pre-requisitos locais

- `apps/api/.env` com:
  - `OPENAI_API_KEY`
  - `MEDIA_INTELLIGENCE_OPENROUTER_API_KEY`
  - credenciais reais da Z-API
- workers ativos para:
  - `whatsapp-inbound`
  - `webhooks`
  - `media-fast`
  - `media-process`
  - `media-safety`
  - `media-vlm`
  - `media-publish`
  - `whatsapp-send`
- webhook publico apontando para:
  - `/api/v1/webhooks/whatsapp/zapi/3BDB98A79042D03232CC1ABE514C6FD4/inbound`
  - `/api/v1/webhooks/whatsapp/zapi/3BDB98A79042D03232CC1ABE514C6FD4/status`
  - `/api/v1/webhooks/whatsapp/zapi/3BDB98A79042D03232CC1ABE514C6FD4/delivery`

## Passo a passo do teste em grupo

1. ligar `moderation_mode = ai` no evento `31`
2. salvar settings de safety com provider `openai`
3. salvar settings de `MediaIntelligence` com:
   - provider `openrouter`
   - `reply_text_mode = ai`
4. enviar `#ATIVAR#GRUPO-EVIVO-TESTE` no grupo
5. confirmar binding criado/reativado
6. enviar uma imagem no grupo
7. esperar:
   - webhook inbound
   - normalizacao
   - download
   - moderation
   - VLM
   - publicacao
   - reaction
   - reply textual em thread

## Passo a passo do teste em DM

1. abrir conversa privada com a instancia
2. enviar:

```text
FOTOS-EVIVO-TESTE
```

3. enviar a imagem
4. validar o mesmo ciclo operacional

## Artefatos obrigatorios ao final de cada rodada

- `trace_id`
- `provider_message_id` inbound
- `whatsapp_inbound_events.id`
- `channel_webhook_logs.id`
- `inbound_messages.id`
- `event_media.id`
- `event_media_safety_evaluations.id`
- `event_media_vlm_evaluations.id`
- `whatsapp_message_feedbacks.id`
- `whatsapp_dispatch_logs.id`
- `request_outcome` dos providers
- `latency_ms` por etapa

---

## O Que Precisa Ser Feito Antes De Te Entregar A Rodada Final De Teste

### Ja posso te entregar agora

- codigo do grupo:
  - `GRUPO-EVIVO-TESTE`
- codigo de DM:
  - `FOTOS-EVIVO-TESTE`

### O que ainda nao consigo te entregar programaticamente

- link de convite do grupo remoto

Motivo:

- a chamada ao provider retornou `invitationLink = null`

Conclusao pratica:

- para entrar no grupo, ainda pode ser necessario convite manual pelo proprio WhatsApp da instancia ou pelo admin da Z-API.

---

## Veredito Executivo

O nucleo do fluxo ja existe.

O que falta agora nao e reinventar pipeline.

O que falta e:

1. transformar `reply_text` em estrategia de produto, e nao em booleano;
2. expor melhor os codigos e a trilha operacional no painel;
3. adicionar request logging de IA e correlacao ponta a ponta;
4. rodar a homologacao real no evento `31`.

O melhor caminho e implementar essa rodada em cima do evento local ja existente, sem abrir uma nova frente de arquitetura.

---

## Fontes Oficiais Validadas

- Z-API Docs - Answer Messages:
  - `https://developer.z-api.io/en/message/reply-message`
- Z-API Docs - Upon receiving:
  - `https://developer.z-api.io/en/webhooks/on-message-received`
- Z-API Docs - Message status:
  - `https://developer.z-api.io/en/webhooks/on-whatsapp-message-status-changes`
- OpenAI Docs - Moderations:
  - `https://platform.openai.com/docs/api-reference/moderations/create`
