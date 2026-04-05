# Analise do Fluxo Atual de Webhook de Fotos via WhatsApp / Z-API

## Objetivo

Este documento descreve o estado real do codigo para recepcao de fotos vindas de webhooks do WhatsApp, com foco em Z-API.

Ele responde:

- qual endpoint deve ser configurado no painel da Z-API;
- o que o backend identifica hoje sobre provider, instancia, remetente e tipo da mensagem;
- o que ja existe na pipeline de persistencia e processamento;
- quais tabelas e filas participam do fluxo;
- quais gaps ainda impedem o fluxo completo ponta a ponta.

Fonte desta analise: codigo atual do repositorio em `apps/api`, nao a documentacao antiga.

## Resposta curta

### Endpoint correto para fotos inbound da Z-API

Para mensagens recebidas, incluindo fotos:

```text
POST {APP_URL}/api/v1/webhooks/whatsapp/zapi/{EXTERNAL_INSTANCE_ID}/inbound
```

Exemplo:

```text
https://api.seudominio.com/api/v1/webhooks/whatsapp/zapi/3F2A9B7C8D6E5F4A/inbound
```

### O que entra no lugar de `{EXTERNAL_INSTANCE_ID}`

Para Z-API, o valor precisa ser o mesmo que esta salvo em:

- `whatsapp_instances.external_instance_id`
- que, no cadastro da instancia, vem de `provider_config.instance_id`

Importante:

- `instance_name` nao e usado na rota do webhook da Z-API;
- a URL precisa ser publica e acessivel pela internet;
- `localhost`, `127.0.0.1` ou URL interna do Laragon nao servem para a Z-API chamar.

### Endpoints adicionais ja existentes

Se quiser separar tambem webhooks de status e delivery:

```text
POST {APP_URL}/api/v1/webhooks/whatsapp/zapi/{EXTERNAL_INSTANCE_ID}/status
POST {APP_URL}/api/v1/webhooks/whatsapp/zapi/{EXTERNAL_INSTANCE_ID}/delivery
```

## Stack atual relevante para este fluxo

### Backend

- PHP 8.3
- Laravel 13
- PostgreSQL
- Redis + Horizon
- Reverb para realtime
- Intervention Image para variantes

### Modulos envolvidos

| Modulo | Papel real no fluxo |
| --- | --- |
| `WhatsApp` | Recebe webhook, identifica instancia, normaliza payload Z-API, persiste evento e mensagem inbound |
| `InboundMedia` | Pipeline legado de webhook para midia; existe estrutura, mas os jobs principais ainda estao em scaffold |
| `MediaProcessing` | Gera variantes, roda decisao de moderacao e publica midia quando ja existe `event_media` |
| `ContentModeration` | Escuta `MediaVariantsGenerated`, roda safety em `media-safety` e reencaminha para moderacao final |
| `Wall` | Escuta eventos de midia publicada/atualizada e faz broadcast realtime |

## Rotas reais no codigo

As rotas ativas do modulo `WhatsApp` sao carregadas com prefixo `api/v1`.

### Webhooks publicos

| Metodo | Rota | Uso |
| --- | --- | --- |
| `POST` | `/api/v1/webhooks/whatsapp/{provider}/{instanceKey}/inbound` | mensagens recebidas, incluindo foto |
| `POST` | `/api/v1/webhooks/whatsapp/{provider}/{instanceKey}/status` | atualizacao de status da instancia/provider |
| `POST` | `/api/v1/webhooks/whatsapp/{provider}/{instanceKey}/delivery` | delivery/read receipts |

### Rota antiga

A documentacao antiga ainda cita:

```text
/api/v1/webhooks/zapi
```

Mas essa rota foi migrada para o modulo `WhatsApp` e nao esta mais registrada em `apps/api/app/Modules/InboundMedia/routes/api.php`.

Conclusao:

- para Z-API, a rota correta hoje e a do modulo `WhatsApp`;
- a referencia a `/api/v1/webhooks/zapi` esta desatualizada.

## Fluxo real atual no codigo

## 1. Recepcao HTTP do webhook

Arquivo principal:

- `apps/api/app/Modules/WhatsApp/Http/Controllers/WhatsAppWebhookController.php`

Comportamento:

1. recebe `provider` e `instanceKey` pela URL;
2. responde `200` imediatamente com `{"status":"received"}`;
3. dispara `ProcessInboundWebhookJob` na fila `whatsapp-inbound`.

Observacao:

- nao existe autenticacao HTTP;
- o comentario diz que haveria validacao por `webhook_secret`, mas isso nao esta implementado no codigo atual.

## 2. Processamento assincrono do webhook

Arquivo principal:

- `apps/api/app/Modules/WhatsApp/Jobs/ProcessInboundWebhookJob.php`

O job faz:

1. busca a instancia em `whatsapp_instances` por:
   - `provider_key`
   - `external_instance_id`
2. cria um registro em `whatsapp_inbound_events`;
3. resolve o normalizador do provider;
4. normaliza o payload;
5. envia o resultado para `WhatsAppInboundRouter`;
6. marca o inbound event como `processed` ou `failed`.

Campos persistidos em `whatsapp_inbound_events`:

- `instance_id`
- `provider_key`
- `provider_message_id`
- `event_type`
- `payload_json`
- `processing_status`
- `received_at`

Gap importante:

- se a instancia nao for encontrada, o job apenas loga warning e retorna;
- nesse caso o payload nao fica salvo em tabela nenhuma.

## 3. Normalizacao especifica da Z-API

Arquivo principal:

- `apps/api/app/Modules/WhatsApp/Clients/Providers/ZApi/ZApiWebhookNormalizer.php`

O normalizador tenta montar um `NormalizedInboundMessageData` com:

- `providerKey`
- `instanceExternalId`
- `eventType`
- `messageId`
- `chatId`
- `chatType`
- `groupId`
- `senderPhone`
- `senderName`
- `messageType`
- `text`
- `mediaUrl`
- `mimeType`
- `caption`
- `occurredAt`
- `rawPayload`

### Como ele detecta o tipo da mensagem

Ele classifica por chaves do payload:

| Chave encontrada | `messageType` gerado |
| --- | --- |
| `image` | `image` |
| `video` | `video` |
| `audio` | `audio` |
| `document` | `document` |
| `sticker` | `sticker` |
| `reaction` | `reaction` |
| `contact` | `contact` |
| `text.message` | `text` |
| `body` | `text` |
| nenhuma das anteriores | `system` |

### Como ele tenta extrair remetente e origem

| Dado | Extracao atual |
| --- | --- |
| `chatId` | `chatId`, senao `phone`, senao `from` |
| `senderPhone` | `phone`, senao `senderPhone`, senao `from` |
| `senderName` | `senderName`, senao `chatName`, senao `notifyName` |
| `mediaUrl` | procura URLs em `image`, `video`, `audio`, `document`, `sticker`, `imageUrl`, `mediaUrl` |
| `mimeType` | `mimetype` ou `mimeType` |

### O que isso significa na pratica

Hoje o sistema ja tenta identificar:

- que o webhook veio do provider `zapi`;
- de qual instancia ele veio;
- se a mensagem e de grupo ou privada;
- qual e o `messageType`;
- qual e o telefone do remetente;
- qual e o nome do remetente;
- se ha uma URL de midia.

### Limitacoes do formato esperado hoje

O codigo atual espera, para fotos, um payload que exponha a imagem por URL.

Ele nao tem suporte explicito para:

- payload com binario inline;
- payload apenas em base64;
- formatos alternativos nao mapeados nas chaves acima.

Gap tecnico observado:

- o metodo de timestamp tem um typo e checa `mompiledentTimestamp`, mas tenta usar `momentTimestamp`;
- na pratica, a maior chance e cair em `timestamp` ou `now()`.

Outro gap:

- os controllers `status` e `delivery` adicionam `_webhook_type` no payload;
- o job nao usa esse campo;
- a classificacao final depende apenas das chaves do payload (`status` ou `ack`).

## 4. Roteamento interno apos normalizacao

Arquivo principal:

- `apps/api/app/Modules/WhatsApp/Services/WhatsAppInboundRouter.php`

O router faz:

1. `findOrCreate` de `whatsapp_chats`;
2. deduplicacao por `provider_message_id` + `direction=inbound`;
3. cria `whatsapp_messages`;
4. atualiza `last_message_at` do chat;
5. dispara `WhatsAppMessageReceived`.

### O que fica salvo em `whatsapp_messages`

- `instance_id`
- `chat_id`
- `direction=inbound`
- `provider_message_id`
- `type`
- `text_body`
- `media_url`
- `mime_type`
- `status=received`
- `sender_phone`
- `normalized_payload_json`
- `received_at`

### O que fica salvo em `whatsapp_chats`

- `external_chat_id`
- `type`
- `phone`
- `group_id`
- `display_name`
- `is_group`

### Resposta objetiva para "ele sabe quem enviou?"

Sim, parcialmente.

Hoje ele sabe identificar e persistir:

- telefone do remetente em `whatsapp_messages.sender_phone`;
- nome do remetente em `whatsapp_chats.display_name`;
- chat/grupo em `whatsapp_chats.external_chat_id` e `group_id`.

Mas ele nao faz:

- vinculo desse remetente com um `User` interno da plataforma;
- normalizacao forte de telefone;
- persistencia de `sender_name` diretamente em `whatsapp_messages`.

Ou seja:

- ele sabe "qual telefone enviou";
- nao sabe "qual usuario autenticado do Evento Vivo enviou", porque esse conceito nao existe nesse fluxo;
- o nome fica no chat, nao na mensagem.

## 5. Como o sistema tenta ligar isso a um evento

Arquivo principal:

- `apps/api/app/Modules/WhatsApp/Listeners/RouteInboundToMediaPipeline.php`

O listener roda quando `WhatsAppMessageReceived` e disparado.

Ele so tenta encaminhar a mensagem para a pipeline de midia se:

1. a mensagem tiver midia;
2. vier de um grupo;
3. esse grupo estiver vinculado em `whatsapp_group_bindings`;
4. o `binding_type` for `event_gallery`.

Portanto, para a foto do WhatsApp ser candidata a entrar no fluxo do evento, nao basta receber a foto:

- o grupo precisa estar previamente vinculado a um evento;
- e o binding precisa ser do tipo `event_gallery`.

Tabela envolvida:

- `whatsapp_group_bindings`

Campos chave:

- `organization_id`
- `event_id`
- `instance_id`
- `group_external_id`
- `binding_type`
- `is_active`
- `metadata_json`

## 6. Onde o fluxo quebra hoje

Aqui esta o ponto central da analise.

Quando o listener decide encaminhar a foto para a pipeline antiga, ele faz:

```text
WhatsAppMessageReceived
-> RouteInboundToMediaPipeline
-> InboundMedia\ProcessInboundWebhookJob::dispatch(provider, rawPayload)
```

O problema:

- `InboundMedia\ProcessInboundWebhookJob` ainda esta em scaffold;
- `InboundMedia\NormalizeInboundMessageJob` ainda esta em scaffold;
- `MediaProcessing\DownloadInboundMediaJob` ainda esta em scaffold.

Esses jobs possuem apenas comentarios descrevendo o que deveriam fazer.

Consequencia pratica:

- o webhook entra;
- o WhatsApp e identificado;
- a mensagem inbound e salva em tabelas `whatsapp_*`;
- mas a foto nao vira automaticamente um registro em `inbound_messages`;
- e tambem nao vira automaticamente um `event_media`.

Em outras palavras:

- o modulo `WhatsApp` esta funcional para capturar e persistir o inbound;
- a ponte do WhatsApp para a pipeline de galeria ainda nao esta fechada.

## 7. Comparacao com o upload publico por link

Fluxo funcional hoje:

- `POST /api/v1/public/events/{uploadSlug}/upload`
- controller: `PublicUploadController`

Esse fluxo ja cria `event_media` diretamente com:

- `source_type = public_upload`
- `source_label = sender_name`
- `caption`
- `original_disk`
- `original_path`
- `client_filename`

Depois ele despacha:

- `GenerateMediaVariantsJob`

Ou seja, o upload publico ja entra no pipeline real de midia.

### Resposta objetiva para a comparacao com "veio pelo link"

Hoje existe rastreabilidade de origem para upload publico:

- a origem fica em `event_media.source_type = public_upload`

Para WhatsApp, isso ainda nao acontece no fluxo automatico real, porque o `event_media` nao esta sendo criado a partir do webhook inbound.

Existe suporte no frontend para exibir canal `whatsapp`, e o `EventMediaResource` sabe mapear:

- `source_type = whatsapp` -> `channel = whatsapp`

Mas, no codigo atual, nao existe criacao real de `event_media` com `source_type = whatsapp` a partir do webhook.

Conclusao:

- a semantica existe;
- a persistencia automatica ponta a ponta ainda nao.

## 8. Pipeline de processamento de imagem

## O que ja esta pronto

Quando um `event_media` ja existe, a pipeline real esta bem mais avancada.

### Etapas existentes

1. `GenerateMediaVariantsJob`
2. `AnalyzeContentSafetyJob`
3. `RunModerationJob`
4. `PublishMediaJob`

### Filas reais usadas por essa pipeline

| Etapa | Fila real |
| --- | --- |
| variantes | `media-fast` |
| safety | `media-safety` |
| publicacao | `media-publish` |

O `DownloadInboundMediaJob` ainda aponta para:

- `media-download`

Mas esta em scaffold.

### O que essas etapas fazem hoje

`GenerateMediaVariantsJob`

- gera `fast_preview`, `thumb`, `gallery`, `wall`;
- preenche largura/altura;
- calcula `perceptual_hash`;
- agrupa duplicatas por `duplicate_group_key`;
- cria registros em `event_media_variants`;
- cria run em `media_processing_runs`;
- emite `MediaVariantsGenerated`.

`AnalyzeContentSafetyJob`

- roda em `media-safety`;
- atualiza `safety_status`;
- grava historico em `event_media_safety_evaluations`;
- dispara `RunModerationJob`.

`RunModerationJob`

- aplica `FinalizeMediaDecisionAction`;
- decide `moderation_status`;
- define `decision_source`;
- se aprovado, dispara `PublishMediaJob`.

`PublishMediaJob`

- marca `publication_status = published`;
- preenche `published_at`;
- emite `MediaPublished`;
- abastece realtime da moderacao e do wall.

## Tabelas da pipeline de midia

### `event_media`

Tabela central da foto/video do evento.

Campos importantes:

- `event_id`
- `inbound_message_id`
- `uploaded_by_user_id`
- `media_type`
- `source_type`
- `source_label`
- `caption`
- `original_disk`
- `original_path`
- `client_filename`
- `mime_type`
- `size_bytes`
- `width`
- `height`
- `perceptual_hash`
- `duplicate_group_key`
- `processing_status`
- `moderation_status`
- `publication_status`
- `safety_status`
- `face_index_status`
- `vlm_status`
- `decision_source`
- `published_at`

### `event_media_variants`

Artefatos derivados da midia.

Campos importantes:

- `event_media_id`
- `variant_key`
- `disk`
- `path`
- `width`
- `height`
- `size_bytes`
- `mime_type`

### `media_processing_runs`

Historico operacional das etapas da pipeline.

Campos importantes:

- `event_media_id`
- `stage_key`
- `provider_key`
- `provider_version`
- `model_key`
- `model_snapshot`
- `input_ref`
- `decision_key`
- `queue_name`
- `worker_ref`
- `result_json`
- `metrics_json`
- `cost_units`
- `idempotency_key`
- `status`
- `attempts`
- `error_message`
- `failure_class`
- `started_at`
- `finished_at`

### `event_content_moderation_settings`

Configuracao de safety por evento.

### `event_media_safety_evaluations`

Historico de avaliacao de safety por foto.

## 9. Filas: o que temos e o que falta

## Filas efetivamente referenciadas no codigo

| Fila | Uso |
| --- | --- |
| `whatsapp-inbound` | processamento de webhook inbound do WhatsApp |
| `whatsapp-send` | envio de mensagens e reacoes |
| `whatsapp-sync` | sync/status/QR |
| `webhooks` | pipeline legado `InboundMedia` |
| `media-download` | download de midia externa |
| `media-fast` | variantes e moderacao base |
| `media-safety` | safety moderation |
| `media-publish` | publicacao |
| `broadcasts` | realtime |

## O que o Horizon realmente supervisiona hoje

Pelo `config/horizon.php`, existem supervisores para:

- `webhooks`
- `media-download`
- `media-fast`
- `media-process`
- `media-safety`
- `media-publish`
- `broadcasts`
- `notifications`
- `default`

Gap operacional importante:

- nao existem supervisores Horizon configurados para `whatsapp-inbound`, `whatsapp-send` e `whatsapp-sync`.

Consequencia:

- o modulo `WhatsApp` pode despachar jobs para essas filas;
- mas, sem worker dedicado ou mapeamento adicional, essas filas nao serao processadas automaticamente em producao/local via Horizon.

## 10. O sistema ja sabe que o webhook veio do WhatsApp?

Sim.

Hoje isso fica claro em varios niveis:

1. a rota de entrada ja esta dentro do modulo `WhatsApp`;
2. a URL carrega `provider = zapi`;
3. o job grava `provider_key` em `whatsapp_inbound_events`;
4. o normalizador Z-API gera `providerKey = zapi`;
5. o router persiste a mensagem em `whatsapp_messages`.

Mas existe um detalhe importante:

- essa identificacao fica muito bem resolvida no contexto `whatsapp_*`;
- ela ainda nao chega ate `event_media.source_type = whatsapp`, porque a ponte de ingestao de midia ainda nao conclui.

## 11. O sistema ja sabe quem enviou a imagem?

### O que ele sabe hoje

- telefone: sim, em `whatsapp_messages.sender_phone`
- nome exibido: sim, em `whatsapp_chats.display_name`
- grupo/chat de origem: sim
- evento vinculado: apenas se houver `whatsapp_group_bindings` ativo do tipo `event_gallery`

### O que ele nao sabe hoje

- qual `User` interno da plataforma enviou;
- uma identidade unificada e confiavel do remetente para o dominio de galeria;
- persistir esse remetente em `inbound_messages` e `event_media` de forma automatica, porque essa ponte esta incompleta.

## 12. Gaps atuais, resumidos

### Criticos

1. O endpoint legado `/api/v1/webhooks/zapi` esta desatualizado na documentacao.
2. O webhook de WhatsApp chega, mas a foto nao entra automaticamente em `event_media`.
3. `InboundMedia\ProcessInboundWebhookJob`, `NormalizeInboundMessageJob` e `DownloadInboundMediaJob` ainda estao em scaffold.
4. `webhook_secret` nao esta sendo validado, apesar do comentario indicar isso.
5. O Horizon nao esta configurado para processar as filas `whatsapp-*`.

### Altos

1. `normalized_json` de `whatsapp_inbound_events` recebe novamente o payload bruto, nao a estrutura normalizada.
2. `normalized_payload_json` de `whatsapp_messages` tambem guarda o payload bruto.
3. `status` e `delivery` passam pelo mesmo router de inbound e nao possuem pipeline especializada.
4. se a instancia nao for encontrada, o payload do webhook nao e persistido para auditoria.

### Medios

1. extracao de timestamp tem typo e pode cair em `now()`.
2. extracao de telefone/nome depende de combinacoes de chaves e pode variar conforme payload real da Z-API.
3. o sistema hoje espera URL de midia, nao base64/binario.
4. existe job de auto reaction, mas nao ha binding de listener registrando esse disparo no `WhatsAppServiceProvider`.

## 13. Configuracao recomendada no painel da Z-API

Para receber fotos de mensagens WhatsApp no backend atual:

```text
https://SEU_BACKEND_PUBLICO/api/v1/webhooks/whatsapp/zapi/SEU_EXTERNAL_INSTANCE_ID/inbound
```

Onde:

- `SEU_BACKEND_PUBLICO` = dominio publico HTTPS do backend Laravel
- `SEU_EXTERNAL_INSTANCE_ID` = `whatsapp_instances.external_instance_id`

Se quiser tambem separar eventos de status e entrega:

```text
https://SEU_BACKEND_PUBLICO/api/v1/webhooks/whatsapp/zapi/SEU_EXTERNAL_INSTANCE_ID/status
https://SEU_BACKEND_PUBLICO/api/v1/webhooks/whatsapp/zapi/SEU_EXTERNAL_INSTANCE_ID/delivery
```

Checklist pratico:

1. a instancia WhatsApp precisa estar cadastrada em `whatsapp_instances`;
2. o `provider_key` precisa ser `zapi`;
3. o `external_instance_id` precisa bater com o valor usado na URL;
4. a URL precisa ser publica e com HTTPS;
5. se a intencao for jogar a foto na galeria do evento, o grupo precisa estar vinculado em `whatsapp_group_bindings` como `event_gallery`;
6. hoje, mesmo com isso, ainda existe gap na ponte para `event_media`.

## 14. Conclusao final

### O que temos hoje

- recepcao HTTP do webhook via rota correta do modulo `WhatsApp`;
- identificacao do provider e da instancia;
- normalizacao basica do payload Z-API;
- persistencia do inbound em `whatsapp_inbound_events`, `whatsapp_chats` e `whatsapp_messages`;
- identificacao de telefone, nome e grupo de origem;
- listener que tenta encaminhar fotos para a pipeline de galeria;
- pipeline de `MediaProcessing` relativamente madura quando um `event_media` ja existe.

### O que falta para fechar ponta a ponta

- implementar de fato a ponte `WhatsApp inbound -> InboundMessage -> DownloadInboundMediaJob -> EventMedia`;
- criar `event_media` com `source_type = whatsapp`;
- preencher `inbound_messages` com remetente, tipo e URL normalizada;
- validar `webhook_secret`;
- adicionar supervisores/workers para filas `whatsapp-*`.

### Resposta final para a pergunta principal

O endpoint que voce deve colocar no painel da Z-API para receber fotos de mensagens do WhatsApp, pelo codigo atual, e:

```text
POST {APP_URL}/api/v1/webhooks/whatsapp/zapi/{EXTERNAL_INSTANCE_ID}/inbound
```

Mas o sistema ainda nao fecha totalmente a ingestao automatica dessa foto ate a galeria do evento, porque a ponte com `InboundMedia` e o download externo ainda estao incompletos.
