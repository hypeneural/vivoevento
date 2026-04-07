# Analise do Intake Multicanal de Midia por Evento

Auditado em `2026-04-06`.

Revalidado contra a documentacao oficial da Telegram Bot API `9.6` em `2026-04-06`.

## Objetivo

Este documento consolida:

- como o intake de midia funciona hoje no `eventovivo`;
- como o vinculo com evento acontece no fluxo atual de WhatsApp via Z-API;
- o estado real de cada canal hoje:
  - Telegram
  - WhatsApp direto
  - WhatsApp grupos
  - Link direto
- a melhor abordagem para adicionar Telegram sem duplicar regra de negocio;
- os gaps que precisam ser corrigidos antes de escalar o intake multicanal.

O foco aqui e o estado atual do codigo, nao uma arquitetura imaginaria.

## Resumo executivo

Hoje o produto esta assim:

- `WhatsApp direto` esta funcional no backend:
  - abre sessao por codigo;
  - fecha sessao com `SAIR`;
  - resolve o evento;
  - encaminha a midia para `InboundMedia`;
  - cria `event_media`;
  - dispara feedback operacional.
- `WhatsApp grupos` esta funcional quando o grupo ja esta vinculado ao evento:
  - binding manual funciona;
  - autovinculo por `#ATIVAR#codigo` existe no backend, mas ainda nao esta provisionado pelo CRUD do evento.
- `Link direto` esta funcional:
  - upload publico cria `event_media` direto e entra na pipeline real.
- `Telegram` privado hoje esta implementado ate a etapa operacional de backend:
  - existe toggle no evento;
  - existe `channel_type = telegram_bot`;
  - existe modulo `Telegram` com client, healthcheck, sessao privada, normalizacao, download e feedback;
  - existe endpoint `POST /api/v1/webhooks/telegram` endurecido com `secret_token` e filtro estrito de `chat.type = private`;
  - o webhook publico local via Cloudflare ja foi registrado de forma controlada;
  - existe endpoint autenticado `GET /api/v1/events/{event}/telegram/operational-status` para o painel;
  - o CRUD `events/:id/edit` ja mostra snapshot de `getMe`, `getWebhookInfo` e sinais `my_chat_member`;
  - ainda falta consolidar smoke test manual recorrente e observabilidade dedicada.

Conclusao pratica:

- o trilho canonico hoje e `WhatsApp -> InboundMedia -> EventMedia -> pipeline`;
- Telegram ainda nao pode ser plugado apenas ligando o toggle existente;
- a melhor abordagem e criar um modulo de transporte `Telegram` e fazer ele entregar um payload canonico ao `InboundMedia`, em vez de empurrar o JSON cru do Telegram para jobs hoje acoplados ao formato da Z-API.

## Matriz de estado atual

| Canal | Estado real hoje | Observacao |
| --- | --- | --- |
| WhatsApp direto | Implementado | Sessao por codigo + `SAIR` + feedback |
| WhatsApp grupos | Implementado parcialmente | Binding manual pronto; autovinculo ainda nao esta exposto no CRUD |
| Link direto | Implementado | Upload publico cria `event_media` direto |
| Telegram | Implementado parcialmente | V1 privado backend + painel operacional no CRUD; sem grupos e sem observabilidade dedicada |

## Arquitetura real auditada hoje

## 1. Fluxo de WhatsApp inbound

O fluxo real atual do WhatsApp e este:

```text
POST /api/v1/webhooks/whatsapp/{provider}/{instanceKey}/inbound
  -> WhatsAppWebhookController
  -> ProcessInboundWebhookJob                queue: whatsapp-inbound
  -> whatsapp_inbound_events
  -> ZApiWebhookNormalizer
  -> WhatsAppInboundRouter
  -> whatsapp_chats + whatsapp_messages
  -> WhatsAppMessageReceived
  -> RouteInboundToMediaPipeline
     -> resolve codigo / binding / blacklist / contexto do evento
     -> feedback detectado
     -> InboundMedia\ProcessInboundWebhookJob queue: webhooks
  -> ChannelWebhookLog
  -> NormalizeInboundMessageJob              queue: webhooks
  -> inbound_messages
  -> DownloadInboundMediaJob                 queue: media-download
  -> event_media
  -> GenerateMediaVariantsJob                queue: media-fast
  -> AnalyzeContentSafetyJob                 queue: media-safety
  -> RunModerationJob                        queue: media-fast
  -> PublishMediaJob                         queue: media-publish
  -> MediaPublished / MediaRejected
  -> feedback final no provider
```

### O que ja fica persistido antes de chegar no `InboundMedia`

No dominio `WhatsApp`, antes do intake canonico, o sistema ja persiste:

- `whatsapp_inbound_events`
- `whatsapp_chats`
- `whatsapp_messages`
- `whatsapp_group_bindings`
- `whatsapp_inbox_sessions`
- `whatsapp_message_feedbacks`

Isso significa que o transporte e a auditoria operacional do WhatsApp ja existem e estao bem mais maduros do que o stub do Telegram.

## 2. Como o evento e resolvido hoje no WhatsApp

O vinculo com evento hoje acontece em duas estrategias.

### 2.1. WhatsApp grupos

O grupo entra no evento quando existe contexto valido em uma destas formas:

1. binding manual em `whatsapp_group_bindings`;
2. autovinculo por texto `#ATIVAR#<codigo>`.

Regras reais do backend:

- a mensagem precisa vir de grupo;
- o evento precisa estar `active`;
- o modulo `live` precisa estar habilitado;
- a instancia recebida no webhook precisa ser a `default_whatsapp_instance_id` do evento;
- o entitlement `channels.whatsapp_groups.enabled` precisa estar ativo;
- a blacklist do evento precisa permitir o remetente.

Identidade usada hoje em grupos:

- `chat_external_id` = grupo;
- `group_external_id` = grupo;
- `sender_external_id` = `participantLid` ou telefone do participante;
- `sender_phone` = `participantPhone`;
- `sender_lid` = `participantLid`;
- `sender_name` = `senderName`;
- `sender_avatar_url` = `senderPhoto`.

Isso esta correto conceitualmente e precisa ser espelhado no Telegram.

### 2.2. WhatsApp direto

No WhatsApp direto o evento nao e descoberto por binding permanente, mas por sessao ativa.

Fluxo real hoje:

1. o usuario manda um texto com codigo em chat privado;
2. o sistema extrai o codigo;
3. procura um `EventChannel` do tipo `whatsapp_direct` com `external_id = media_inbox_code`;
4. valida evento, modulo `live`, instancia e entitlement;
5. abre ou renova uma linha em `whatsapp_inbox_sessions`;
6. responde no mesmo thread;
7. as proximas midias daquele remetente entram no evento enquanto a sessao estiver ativa;
8. `SAIR` encerra a sessao.

Regex aceitas hoje para ativacao:

- `#ATIVAR#CODIGO`
- `#CODIGO`
- `CODIGO`

Encerramento hoje:

- `SAIR`

Chave real da sessao hoje:

- `instance_id + sender_external_id`

## 3. Como a fila canonica de intake funciona hoje

Depois que o listener do WhatsApp resolve o contexto do evento, ele entrega um payload ao `InboundMedia` com `_event_context`.

Campos esperados hoje dentro de `_event_context`:

- `event_id`
- `event_channel_id`
- `intake_source`
- `group_binding_id` ou `inbox_session_id`
- `provider_message_id`
- `chat_external_id`
- `group_external_id`
- `sender_external_id`
- `sender_phone`
- `sender_lid`
- `sender_name`
- `sender_avatar_url`
- `caption`
- `media_url`

No `InboundMedia`, a sequencia real e:

1. `ProcessInboundWebhookJob`
   - cria `channel_webhook_logs`;
   - detecta tipo;
   - salva payload bruto.
2. `NormalizeInboundMessageJob`
   - exige `_event_context`;
   - cria `inbound_messages`;
   - deduplica por `provider + message_id`;
   - dispara `DownloadInboundMediaJob`.
3. `DownloadInboundMediaJob`
   - baixa o arquivo por `media_url`;
   - cria `event_media`;
   - marca `source_type` com `intake_source`;
   - dispara `GenerateMediaVariantsJob`.

Hoje isso funciona bem para WhatsApp porque o `media_url` ja vem pronto da Z-API.

## 4. Link direto hoje

O canal `public_upload` usa um fluxo proprio:

```text
GET/POST /api/v1/public/events/{uploadSlug}/upload
  -> PublicUploadController
  -> EventMedia::create()
  -> GenerateMediaVariantsJob
```

Pontos importantes:

- nao usa `InboundMessage`;
- nao depende de sessao;
- nao depende de provider;
- `source_type` hoje fica `public_upload`.

Gap atual:

- o sistema ainda nao separa claramente `link` de `qr`;
- os dois entrypoints convergem no mesmo `source_type`.

## 5. Telegram hoje

O estado atual do Telegram no codigo ja saiu do placeholder minimo e fecha o trilho backend do V1 privado:

- `EventChannel` ja aceita `telegram_bot`;
- o CRUD do evento ja aceita `intake_channels.telegram.enabled`;
- o `EventEditorPage` mostra o card `Telegram`;
- existe rota publica `POST /api/v1/webhooks/telegram`;
- existe modulo `Telegram` com:
  - `TelegramServiceProvider`;
  - `TelegramBotApiClient`;
  - `TelegramBotHealthcheckService`;
  - `TelegramWebhookSecretValidator`;
  - `TelegramPrivateCommandParser`;
  - `HandleTelegramPrivateWebhookAction`;
  - `TelegramPrivateSessionService`;
  - `TelegramFeedbackAutomationService`;
  - `TelegramFeedbackContextResolver`;
  - `SendTelegramFeedbackJob`;
  - `TelegramInboxSession`;
  - `TelegramMessageFeedback`;
  - `RegisterTelegramWebhookCommand`;
  - `TelegramWebhookController`.
- o controller atual:
  - valida `X-Telegram-Bot-Api-Secret-Token`;
  - ignora updates fora de `chat.type = private` com trilha tecnica;
  - deduplica `provider + provider_update_id` antes de novo dispatch;
  - abre sessao privada com `/start CODIGO`;
  - fecha sessao privada com `SAIR`, `/sair` ou `/stop`;
  - monta envelope canonico para `photo`, `video` e `document` quando existe sessao ativa;
  - ignora midia sem sessao ativa com auditoria `no_active_session`;
  - dispara feedback de deteccao em `telegram-send`;
  - despacha apenas updates privados normalizaveis para o `InboundMedia`.

Isso fecha a borda backend do V1 privado. A parte ainda pendente e observar a operacao real, executar smoke test fim a fim recorrente e aprofundar a observabilidade.

### Validacao automatizada do estado atual

Nesta analise foram adicionados testes para congelar o comportamento real do codigo em `2026-04-06`:

- `tests/Feature/Telegram/TelegramWebhookControllerTest.php`
  - valida que a rota do webhook existe;
  - valida que ela exige `secret_token`;
  - valida que ela so despacha updates privados para o pipeline generico;
  - valida replay por `update_id`;
  - valida que updates fora de escopo ficam auditados;
  - valida que o `NormalizeInboundMessageJob` ignora o update cru com `missing_event_context`.
- `tests/Feature/Telegram/TelegramPrivateActivationTest.php`
  - valida abertura de sessao por `/start CODIGO`;
  - valida descarte de codigo invalido;
  - valida fechamento de sessao por `SAIR`.
- `tests/Feature/Telegram/TelegramPrivateMediaIntakePipelineTest.php`
  - valida envelope canonico para `photo`, `video` e `document`;
  - valida que midia sem sessao ativa e ignorada;
  - valida download via `getFile` sem persistir URL com token;
  - valida que feedback `detected` e agendado apenas quando existe sessao ativa.
- `tests/Feature/Telegram/TelegramFeedbackAutomationTest.php`
  - valida `sendChatAction` + reacao na fase `detected`;
  - valida reacao de publicado por evento `MediaPublished`;
  - valida reacao + reply com `reply_parameters.message_id` por evento `MediaRejected`;
  - valida idempotencia dos feedbacks por mensagem/fase/tipo.
- `tests/Feature/Telegram/TelegramWebhookRegistrationCommandTest.php`
  - valida `setWebhook` com `allowed_updates = ["message", "my_chat_member"]`, `secret_token` e `drop_pending_updates`;
  - valida rejeicao de URL nao HTTPS/local.
- `tests/Unit/MediaProcessing/HorizonConfigTest.php`
  - valida `supervisor-telegram-send`;
  - valida wait threshold e limites locais/producao do feedback Telegram.
- `tests/Unit/InboundMedia/TelegramUpdateInspectorTest.php`
  - valida dedupe por `update_id` e identidade por `chat.id + message_id`;
  - valida tipagem em string para ids do Telegram;
  - valida `message_type` oficial;
  - valida selecao do maior item de `PhotoSize[]`.

Resultado do recorte executado:

- baseline consolidada atual do recorte Telegram:
  - `21` testes aprovados;
  - `117` assertions aprovadas.
- regressao focada de `InboundMedia` + `WhatsAppEventIntake`:
  - `19` testes aprovados;
  - `142` assertions aprovadas.
- recorte consolidado final desta etapa:
  - `61` testes aprovados;
  - `467` assertions aprovadas.

### Validacao real do bot existente

Validacao manual realizada em `2026-04-06` na Bot API oficial com o bot atual:

- `getMe.ok = true`
- `id = 8547738765`
- `username = eventovivoBot`
- `first_name = Eventovivo`
- `can_join_groups = true`
- `can_read_all_group_messages = false`
- `getWebhookInfo.url = "https://webhooks-local.eventovivo.com.br/api/v1/webhooks/telegram"`
- `getWebhookInfo.pending_update_count = 0`
- `getWebhookInfo.allowed_updates = ["message", "my_chat_member"]`

Leitura pratica para o V1 privado:

- o token atual responde e o bot existe;
- o webhook publico local esta registrado via Cloudflare;
- nao existe fila pendente acumulada;
- o estado atual combina com um rollout privado ativado de forma controlada e ainda pendente de smoke test manual.

### Pendencias antes do rollout real

Mesmo com webhook privado, idempotencia, sessao, envelope canonico, download por `getFile` e feedback outbound, o intake ainda tem pontos de rollout pendentes:

1. o envelope canonico Telegram ja existe para `photo`, `video` e `document`, mas ainda deve ser endurecido com payloads reais capturados no smoke test;
2. a identidade `inbound_messages` foi ajustada para `provider + chat_external_id + message_id`, mas dados historicos precisam ser considerados antes de migrar producao;
3. filas e observabilidade dedicadas ainda precisam ser fechadas antes do rollout de maior escala;
4. o webhook publico ja foi registrado, mas ainda precisa ser monitorado com mensagens reais do bot.

Leitura pratica:

- o toggle existe;
- a rota existe;
- a borda privada do provider ja esta endurecida;
- a sessao privada ja resolve o contexto do evento;
- `photo`, `video` e `document` ja entram no envelope canonico;
- o download via `getFile` ja existe sem persistir URL tokenizada;
- feedback `detected`, `published` e `rejected` ja existe;
- o painel do evento ja expoe status operacional do bot e do webhook;
- ainda faltam observabilidade dedicada e smoke test real recorrente fim a fim.

### Diagnostico da primeira rodada real privada

Rodada real validada em `2026-04-06` com o codigo `TGTEST406`:

- o webhook recebeu `/start TGTEST406` e abriu sessao com `routing_status = session_activated`;
- o parser do V1 agora tambem aceita `TGTEST406` como codigo puro quando nao existe sessao ativa;
- mensagens `TGTEST406` enviadas separadamente antes dessa correcao ficaram como `missing_event_context`;
- duas fotos chegaram via webhook e foram processadas ate `inbound_messages` e `event_media`;
- inicialmente as midias ficaram em `processing_status = downloaded` porque o worker local nao escutava `media-fast`;
- apos subir worker local para `media-fast`, `media-safety`, `media-vlm`, `face-index` e `media-publish`, as duas midias ficaram `processed`, `approved` e `published`;
- filas `webhooks`, `media-download`, `media-fast`, `media-safety`, `media-vlm`, `face-index`, `media-publish` e `telegram-send` ficaram zeradas apos processamento;
- nao houve failed job de Telegram;
- o feedback `detected` original tentou usar a reacao `\u{23F3}` e a Bot API respondeu `REACTION_INVALID`;
- a reacao de deteccao foi trocada para `\u{1F44D}` e validada com chamada real no Telegram;
- a ativacao por `/start CODIGO` ou codigo puro agora enfileira reply textual com o nome do evento;
- o encerramento por `Sair` fecha a sessao em banco e agora tambem responde no chat com o nome do evento.

## Estado por canal, em detalhe

## WhatsApp grupos

### O que esta pronto

- inbound via webhook;
- normalizacao da Z-API;
- deduplicacao;
- binding manual do grupo ao evento;
- blacklist por remetente;
- feedback de deteccao, publicacao e rejeicao;
- intake canonico ate `event_media`.

### O que esta incompleto

- o autovinculo por `#ATIVAR#codigo` existe no backend, mas ainda nao esta provisionado no CRUD do evento;
- o `SyncEventIntakeChannelsAction` hoje cria o canal de grupos com `external_id = null`;
- o `WhatsAppGroupActivationService` procura o codigo em `EventChannel.external_id`;
- logo, o autovinculo nao esta pronto ponta a ponta a partir da configuracao atual do evento.

Leitura pratica:

- binding manual funciona;
- autovinculo ainda depende de etapa adicional de produto/configuracao.

## WhatsApp direto

### O que esta pronto

- canal no evento;
- `media_inbox_code`;
- sessao em `whatsapp_inbox_sessions`;
- `SAIR`;
- reply em thread;
- blacklist;
- entrega ao `InboundMedia`.

### O que ainda falta melhorar

- o modelo esta muito especifico de WhatsApp;
- se Telegram entrar copiando esse padrao 1:1, vamos duplicar sessao, feedback e resolucao em dois lugares.

## Link direto

### O que esta pronto

- ativacao do canal no evento;
- upload publico;
- criacao direta de `event_media`;
- pipeline posterior funcionando.

### O que ainda falta melhorar

- diferenciar `link` de `qr`;
- convergir metrica e taxonomia de origem com os canais de chat.

## Telegram

### O que ja existe

- entitlement;
- toggle no evento;
- `channel_type = telegram_bot`;
- rota publica do webhook;
- validacao de `secret_token`;
- filtro estrito de `chat.type = private`;
- client oficial inicial para `getMe`, `getWebhookInfo` e `setWebhook`;
- healthcheck inicial do bot.

### O que ainda nao existe

- auditoria propria (`telegram_inbound_events`, `telegram_messages`, etc.);
- sessao por codigo;
- roteamento privado por sessao;
- normalizador do `Update`;
- downloader para `getFile`;
- feedback com reply/reaction;
- workers dedicados.

## 6. Validacoes oficiais da Bot API que realmente importam

Esta secao resume so o que muda desenho, schema e rollout.

### 6.1. Identidade e deduplicacao

O ponto mais importante para Telegram e este:

- `update_id` e a chave correta para idempotencia do webhook;
- `message_id` nao e global, ele e unico apenas dentro do chat;
- a identidade da mensagem precisa ser `chat.id + message_id`;
- `media_group_id` pode ser persistido quando existir, mas nao precisa virar unidade de lote no V1 privado.

Impacto direto no `eventovivo`:

- a deduplicacao atual em `inbound_messages` por `provider + message_id` nao serve para Telegram;
- para webhook, a chave recomendada e `provider + provider_update_id`;
- para mensagem canonica, a chave recomendada e `provider + chat_external_id + provider_message_id`.

### 6.2. Tipagem de identificadores

A Bot API deixa claro que ids como `chat.id` e `user.id` podem passar de `32 bits`.

Regra de stack recomendada:

- banco: `BIGINT` ou `string`, nunca `INT`;
- payload canonico: ids serializados como string;
- PHP: converter ids para string na borda;
- JS/TS: nao tratar esses ids como `number` solto sem cuidado.

### 6.3. Webhook, rollout e observabilidade

Para Telegram, a operacao do webhook precisa ser mais prescritiva do que a doc anterior descrevia.

Regras operacionais:

- usar `setWebhook` com `allowed_updates` explicito;
- usar `secret_token` e validar `X-Telegram-Bot-Api-Secret-Token`;
- usar `drop_pending_updates` em rollout quando fizer sentido;
- monitorar `getWebhookInfo`;
- lembrar que `setWebhook` e `getUpdates` sao mutuamente exclusivos;
- considerar que updates ficam armazenados pelo Telegram por ate `24 horas`, o que pode gerar cauda apos trocar modo ou filtros.

Regras de infraestrutura:

- webhook oficial exige HTTPS;
- no Bot API padrao, as portas aceitas sao `443`, `80`, `88` ou `8443`.

### 6.4. Healthcheck de onboarding do bot

Para o recorte privado-only, o onboarding pode ser mais simples.

Checklist recomendado:

- `getMe`
  - validar token;
  - validar `username`;
  - validar se o bot esta ativo e responde com `is_bot = true`.
- `getWebhookInfo`
  - validar se o webhook esperado esta registrado;
  - validar se nao existe fila acumulada de updates;
  - validar `last_error_message` antes de qualquer rollout.

### 6.5. Escopo operacional do V1

Telegram no `eventovivo` vai iniciar apenas em conversa privada com o bot.

Regras de escopo:

- somente `chat.type = private`;
- grupos, supergrupos, canais e topicos ficam fora do V1;
- qualquer update fora de conversa privada deve ser ignorado e auditado como `out_of_scope`.

### 6.6. Payload e tipos oficiais de mensagem

O envelope canonico deve respeitar a semantica real da Bot API.

Tipos oficiais importantes:

- `text`
- `photo`
- `video`
- `document`
- `voice`
- `audio`

Regras de normalizacao:

- texto vem em `message.text` com `entities`;
- legenda vem em `caption` com `caption_entities`;
- foto chega em `message.photo` como array de `PhotoSize[]`;
- o processamento deve escolher o maior item disponivel;
- `file_unique_id` deve ser persistido para rastreabilidade e possivel dedupe de midia;
- `file_unique_id` nao serve para download;
- albums chegam como mensagens separadas com o mesmo `media_group_id`.

### 6.7. Download e limites do Bot API

Fluxo oficial:

1. receber `file_id`;
2. chamar `getFile`;
3. usar o `file_path` retornado;
4. baixar em `/file/bot<TOKEN>/<file_path>`.

Regras que importam para arquitetura:

- a URL com `file_path` e garantida por pelo menos `1 hora`;
- no Bot API padrao, download de arquivo de bot vai ate `20 MB`;
- se o produto for trabalhar com video pesado com frequencia, a saida oficial e `Local Bot API Server`;
- no `Local Bot API Server`, o Telegram documenta download sem limite e upload de ate `2000 MB`.

Regra de seguranca:

- nao persistir URL tokenizada em banco, log ou payload auditavel.

### 6.8. Feedback, reacoes e eventos operacionais

No Telegram, a mensagem e a ancora da interacao.

Regras praticas:

- `sendChatAction` cobre a espera perceptivel;
- `sendChatAction`, `sendMessage` e `setMessageReaction` cobrem o feedback minimo do V1 privado;
- `sendPhoto`, `sendVideo` e `sendMediaGroup` ficam como extensao de outbound posterior;
- `setMessageReaction` hoje permite ao bot definir ate uma reacao por mensagem;
- quando a mensagem pertence a um `media_group_id`, a reacao e aplicada na primeira mensagem nao deletada do conjunto;
- se o bot escutar `message_reaction`, ele nao recebe update das proprias reacoes;
- no V1 privado, `message_reaction` pode ficar fora do rollout inicial.

Eventos que precisam ficar explicitamente fora do V1 ou tratados em trilha propria:

- `edited_message`
- `my_chat_member`

## 7. Gaps tecnicos que precisam mudar antes de plugar Telegram

## 7.1. A deduplicacao canonica atual esta errada para Telegram

Hoje o `NormalizeInboundMessageJob` faz `firstOrCreate` por:

- `provider`
- `message_id`

E a migration de `inbound_messages` reforca isso com `unique(provider, message_id)`.

Para Telegram, isso precisa ser mudado.

Recomendacao:

- `channel_webhook_logs`
  - adicionar `provider_update_id`;
  - deduplicar por `provider + provider_update_id`.
- `inbound_messages`
  - parar de deduplicar por `provider + message_id`;
  - usar `provider + chat_external_id + message_id`;
  - persistir `media_group_id` quando existir como contexto complementar, sem transformar isso em identidade obrigatoria do V1.

## 7.2. O contrato do `InboundMedia` ainda nao e provider-agnostic

Mesmo sendo o intake canonico, ele ainda depende demais de chaves derivadas da Z-API.

Sintomas:

- detecta tipo olhando `image`, `video`, `audio`, `document`;
- extrai texto olhando `text.message`;
- baixa sempre por `media_url`.

Decisao recomendada:

- evoluir o `InboundMedia` para receber um envelope canonico normalizado;
- parar de empurrar payload cru de provider para dentro dele;
- padronizar `message_type` em termos oficiais, como `photo`, `video`, `document`, `voice`, `audio`, `text`.

## 7.3. O download atual assume URL direta

Hoje `DownloadInboundMediaJob` faz:

- `Http::get($inboundMessage->media_url)`

Para Telegram isso nao e suficiente porque:

1. o download depende de `getFile`;
2. a URL final inclui o token do bot.

Decisao recomendada:

- nao persistir URL tokenizada do Telegram em banco ou log;
- persistir `file_id`, `file_unique_id`, `file_path` e contexto do bot;
- baixar via um `RemoteInboundMediaDownloader` provider-aware.

## 7.4. A taxonomia de origem ainda esta inconsistente

Hoje o `DownloadInboundMediaJob` grava:

- `source_type = whatsapp_group`
- `source_type = whatsapp_direct`

Mas filtros e resources do catalogo ainda esperam:

- `whatsapp`
- `telegram`
- `public_link`
- `qrcode`

Impacto:

- listagens e badges podem classificar errado a origem;
- Telegram entraria em cima de uma taxonomia ja inconsistente.

Decisao recomendada:

- definir um padrao unico antes de expandir:
  - opcao A:
    - `source_type = whatsapp | telegram | public_upload`
    - `source_subtype = direct | group | topic | album | link | qr`
  - opcao B:
    - manter `source_type` granular e corrigir toda a camada de leitura.

Recomendacao deste documento:

- usar `source_type` macro + `source_subtype` granular.

## 7.5. Horizon e filas de transporte

O backend ja possui supervisores dedicados para WhatsApp:

- `whatsapp-inbound`
- `whatsapp-send`
- `whatsapp-sync`

Para Telegram V1 privado, o ponto critico de rollout e o feedback outbound. Ele agora usa:

- `telegram-send`

E `config/horizon.php` ja cria `supervisor-telegram-send` para essa fila.

Impacto:

- o feedback `detected`, `published` e `rejected` nao depende da fila `default`;
- o webhook privado ainda reaproveita a fila canonica `webhooks` para normalizacao;
- uma fila `telegram-inbound` so deve entrar se o volume real justificar separar o recebimento do Telegram da fila canonica.

Decisao recomendada:

- manter `telegram-send` supervisionada desde o V1;
- observar latencia de `webhooks` antes de introduzir `telegram-inbound`.

## 7.6. O CRUD do evento agora materializa o contrato Telegram V1 privado e ja expoe o estado operacional

O editor `events/:id/edit` em `app/web` agora ja cobre o minimo operacional do Telegram privado:

- toggle dedicado do canal;
- `bot_username`;
- `media_inbox_code`;
- `session_ttl_minutes`;
- copy explicita de escopo privado;
- preview do deep link `https://t.me/<bot_username>?start=<media_inbox_code>`;
- copy de blacklist deixando claro que `ID externo` tambem entra no bloqueio;
- card operacional consultando `GET /api/v1/events/{event}/telegram/operational-status`;
- snapshot de `getMe` e `getWebhookInfo`;
- comparacao entre `allowed_updates` esperado e atual;
- historico recente de sinais `my_chat_member` com aviso explicito de que isso nao altera a blacklist do evento.

Isso fecha a configuracao funcional do canal no CRUD.

Recomendacao:

- manter `bot_username`, `media_inbox_code` e `session_ttl_minutes` como contrato obrigatorio de `EventChannel.config_json`;
- manter o painel lendo `my_chat_member` apenas como telemetria operacional do bot;
- manter qualquer configuracao de grupo fora do contrato do Telegram V1.

## 8. Melhor abordagem para inserir Telegram

## 8.1. Principio central

Nao colocar a regra de Telegram dentro do `InboundMedia`.

A abordagem mais solida e:

- `WhatsApp` continua sendo modulo de transporte/provider;
- `Telegram` nasce como novo modulo de transporte/provider;
- `InboundMedia` continua sendo a camada canonica de intake;
- `Events` e `Channels` continuam sendo a fonte de configuracao do evento;
- `MediaProcessing` continua totalmente agnostico de provider.

## 8.2. Modulo novo recomendado: `apps/api/app/Modules/Telegram`

Esse modulo deve nascer com responsabilidades equivalentes ao `WhatsApp`, mas limitadas ao escopo privado do Telegram:

- validar bot/token;
- registrar webhook, atualizar webhook e auditar `getWebhookInfo`;
- fazer onboarding com `getMe`;
- normalizar `Update` para um DTO interno;
- persistir trilha tecnica;
- enviar reply, reacao e midia de feedback;
- resolver apenas chat privado e sessao ativa;
- entregar um payload canonico para `InboundMedia`.

### Estrutura recomendada

```text
Telegram/
|-- Actions/
|-- Clients/
|   |-- BotApi/
|-- DTOs/
|-- Enums/
|-- Events/
|-- Http/
|   |-- Controllers/
|-- Jobs/
|-- Listeners/
|-- Models/
|-- Providers/
|-- Services/
|-- Support/
|-- routes/
|   |-- api.php
`-- README.md
```

## 8.3. Contrato canonico recomendado entre transporte e `InboundMedia`

Em vez de mandar payload cru, o transporte deve entregar um envelope como este:

```json
{
  "provider": "telegram",
  "provider_update_id": "123456789",
  "provider_message_id": "81",
  "message_type": "photo",
  "occurred_at": "2026-04-06T14:32:00Z",
  "chat_external_id": "9007199254740991",
  "chat_type": "private",
  "media_group_id": null,
  "sender_external_id": "9007199254740991",
  "sender_phone": null,
  "sender_lid": null,
  "sender_name": "Ana",
  "sender_avatar_url": null,
  "body_text": null,
  "caption": "Evento ao vivo",
  "entities": [],
  "caption_entities": [],
  "from_me": false,
  "media": {
    "download_strategy": "telegram_file",
    "file_id": "AAA_big",
    "file_unique_id": "U_big",
    "file_path": null,
    "mime_type": "image/jpeg",
    "file_name": null,
    "width": 1080,
    "height": 1350,
    "duration": null,
    "file_size": 245000
  },
  "event_context": {
    "event_id": 987,
    "event_channel_id": 123,
    "intake_source": "telegram",
    "source_subtype": "direct",
    "session_id": 456
  },
  "provider_context_json": {
    "update_id": 123456789,
    "message_id": 81,
    "chat_type": "private"
  }
}
```

Com isso:

- `InboundMedia` para de depender da Z-API;
- `Telegram` e `WhatsApp` falam a mesma lingua;
- o download deixa de depender de URL publica;
- `message_type` deixa de perder semantica da origem;
- o sistema ganha chave clara para webhook e para mensagem.

## 8.4. Como Telegram deve vincular o evento

A melhor estrategia e manter a mesma semantica de produto ja usada no WhatsApp:

- codigo para ativar conversa privada;
- comando para encerrar sessao privada;
- link direto como canal paralelo e independente.

## 8.5. Telegram direto

Fluxo recomendado:

1. usuario abre o bot via deep link:
   - `https://t.me/<bot_username>?start=<media_inbox_code>`
2. o bot recebe `/start CODIGO`;
3. o backend resolve o `EventChannel` Telegram do evento;
4. valida:
   - evento ativo;
   - modulo `live`;
   - entitlement `telegram.enabled`;
   - blacklist por `sender_external_id` / `chat.id`;
5. cria ou renova a sessao;
6. responde confirmando;
7. qualquer `photo`, `video` ou `document` recebido depois entra no evento;
8. `SAIR`, `/sair` ou `/stop` encerra a sessao.

### Bloqueio de remetente no Telegram

Para Telegram privado, o bloqueio do Eventovivo nao pode depender de um "ban" nativo do provider.

Desenho correto:

- o bloqueio e operacional, no dominio do evento;
- a identidade principal e `external_id`, usando `sender_external_id = from.id` e `chat_external_id = chat.id`;
- o bloqueio deve impedir ativacao de sessao por codigo;
- o bloqueio deve impedir nova midia de entrar na pipeline mesmo com sessao ativa;
- o bot deve responder com feedback `blocked` no proprio chat;
- `my_chat_member` deve ser tratado apenas como sinal operacional de que o usuario bloqueou ou desbloqueou o bot, nao como mecanismo de bloqueio do evento.

Em chat privado do Telegram, a Bot API informa `my_chat_member` quando o bot e bloqueado ou desbloqueado pelo usuario, mas nao oferece um mecanismo equivalente para o bot "banir" o usuario naquela conversa privada. Por isso a blacklist do Eventovivo precisa viver no intake e na moderacao.

### Chave da sessao recomendada

- `bot_id + chat.id`

Ou, se a sessao for provider-agnostic:

- `event_channel_id + chat_external_id`

### Melhor escolha de UX

- Telegram deve ter deep link pronto no painel;
- isso reduz erro de digitacao;
- isso combina com QR e CTA de onboarding.

## 8.6. Feedback no Telegram

O Telegram encaixa bem no mesmo ciclo de feedback do WhatsApp:

- fase `blocked`
  - `setMessageReaction`
  - `sendMessage`
- fase `detected`
  - `sendChatAction`
  - `setMessageReaction`
- fase `published`
  - `setMessageReaction`
- fase `rejected`
  - reacao negativa
  - `sendMessage` com `reply_parameters.message_id`

### Regras recomendadas

- em conversa privada, reply textual e seguro como comportamento padrao;
- albums ficam fora do V1 como unidade logica completa, embora `media_group_id` seja preservado como contexto;
- `sendMediaGroup` fica mais importante para outbound futuro do que para intake.

## 8.7. Download de midia no Telegram

Fluxo recomendado:

1. webhook chega com `file_id`;
2. o modulo `Telegram` chama `getFile`;
3. recebe `file_path`;
4. o downloader busca `https://api.telegram.org/file/bot<TOKEN>/<file_path>`;
5. grava o original em `storage`;
6. cria `event_media`.

### Regra de seguranca

- nao persistir a URL final com token;
- persistir `file_id`, `file_unique_id`, `file_path` quando necessario e referencia do bot.

## 9. Modelo de dados recomendado para Telegram

## 9.1. Transporte e auditoria

Criar tabelas proprias do modulo:

- `telegram_bots`
- `telegram_inbound_events`
- `telegram_chats`
- `telegram_messages`

Campos que devem entrar desde o inicio:

- `provider_update_id`
- `chat_id`
- `user_id`
- `message_id`
- `media_group_id`
- `file_id`
- `file_unique_id`
- `file_path`

Regra de tipagem:

- ids do Telegram como `BIGINT` ou `string`, nunca `INT`.

## 9.2. Sessao de conversa

Se o trabalho for iniciado agora, vale a pena generalizar a sessao de intake em vez de copiar `whatsapp_inbox_sessions`.

Tabela recomendada:

- `event_channel_sessions`

Campos sugeridos:

- `id`
- `organization_id`
- `event_id`
- `event_channel_id`
- `provider`
- `session_kind`
- `chat_external_id`
- `sender_external_id`
- `status`
- `activated_by_provider_message_id`
- `last_inbound_provider_message_id`
- `activated_at`
- `last_interaction_at`
- `expires_at`
- `closed_at`
- `metadata_json`

`metadata_json` pode guardar:

- `sender_phone`
- `sender_lid`
- `sender_name`
- `sender_avatar_url`
- `telegram_user_id`
- `telegram_chat_id`

Opcao pragmatica, se nao quiser generalizar agora:

- criar `telegram_inbox_sessions`;
- migrar depois para a tabela generica.

## 9.3. Configuracao recomendada no `EventChannel`

Para o canal `telegram_bot`, o `config_json` deve passar a guardar:

```json
{
  "bot_username": "eventovivoBot",
  "media_inbox_code": "ANAEJOAO",
  "session_ttl_minutes": 180,
  "allow_private": true,
  "v1_allowed_updates": ["message"]
}
```

Para o canal `whatsapp_group`, vale a mesma ideia:

- `group_bind_code` precisa existir de forma oficial no CRUD do evento;
- hoje isso ainda nao esta materializado pela action de sync.

## 10. Contrato minimo de rollout V1

Esta e a recomendacao de V1 mais segura para reduzir superficie:

- `allowed_updates = ["message", "my_chat_member"]`
- ignorar `edited_message` no V1;
- tratar apenas `text`, `photo`, `video` e `document`;
- persistir `media_group_id` apenas como contexto opcional quando ele existir;
- responder com:
  - `sendChatAction`
  - `sendMessage`
  - `setMessageReaction`
- nao depender de `message_reaction` no V1;
- usar deep link `/start CODIGO` para privado;
- usar `SAIR` para encerrar sessao.

O V1 pode deixar explicitamente fora:

- albums como unidade logica completa;
- `voice`;
- `audio`;
- tratamento de `edited_message`;
- ingestao orientada a reacao de usuario.

## 11. Canais que o administrador deve conseguir ativar

O painel do evento deve convergir para esta grade:

### WhatsApp grupos

- `enabled`
- instancia padrao
- grupos observados ou `group_external_id` manual
- `group_bind_code`
- feedback automatico

### WhatsApp direto

- `enabled`
- instancia padrao
- `media_inbox_code`
- TTL da sessao
- texto para divulgar

### Telegram

- `enabled`
- `bot_username`
- `media_inbox_code`
- TTL da sessao
- deep link pronto
- status do webhook
- status do healthcheck do bot

### Link direto

- `enabled`
- URL publica
- QR code
- origem `link` vs `qr`

## 12. Ordem recomendada de implementacao

## Fase 0 - endurecer a base atual

1. Corrigir a deduplicacao canonica de Telegram.
2. Corrigir o contrato canonico do `InboundMedia`.
3. Criar `source_subtype` ou corrigir a taxonomia de origem.
4. Ajustar filtros/resources para `whatsapp_group` e `whatsapp_direct`.
5. Adicionar supervisores Horizon para `whatsapp-*`.
6. Materializar o contrato privado do Telegram no CRUD do evento.

## Fase 1 - Telegram direto

1. Criar modulo `Telegram`.
2. Implementar onboarding com `getMe`.
3. Registrar webhook com `secret_token`.
4. Criar normalizador do `Update`.
5. Implementar deep link `/start CODIGO`.
6. Implementar sessao e `SAIR`.
7. Entregar payload canonico para `InboundMedia`.
8. Implementar downloader `getFile`.
9. Implementar feedback detectado/publicado/rejeitado.

## Fase 2 - estabilizacao e rollout privado

1. Expor status de webhook e healthcheck no painel.
2. Endurecer observabilidade e retries.
3. Unificar sessoes de chat.
4. Unificar feedback provider-agnostic por fase.
5. Distinguir `link` e `qr`.
6. Consolidar dashboards e filtros por `source_type` e `source_subtype`.

## 13. Recomendacao final

Se a pergunta e "qual e a melhor abordagem para inserir Telegram tambem", a resposta fica mais precisa depois da validacao oficial e dos testes:

- manter o endpoint atual `POST /api/v1/webhooks/telegram`, mas com ownership no modulo `Telegram`;
- nao empurrar JSON cru do Telegram para dentro do `InboundMedia` como contrato final;
- consolidar o modulo `Telegram` de transporte;
- manter `InboundMedia` como intake canonico;
- corrigir a dedupe para `update_id` no webhook e `chat.id + message_id` na mensagem;
- serializar ids do Telegram como `BIGINT` ou string;
- generalizar a ideia de sessao por codigo;
- manter o V1 estritamente em conversa privada com o bot;
- manter a UX alinhada entre canais:
  - codigo para entrar
  - `SAIR` para sair
  - feedback por fase
  - link direto como canal paralelo

Essa abordagem preserva a regra principal do repositorio:

- feature importante nasce em modulo de dominio;
- transporte fica no modulo do provider;
- regra de intake fica centralizada;
- pipeline de midia continua unica.

## Referencias oficiais do Telegram usadas para a recomendacao

- Bot API: `https://core.telegram.org/bots/api`
- Deep linking: `https://core.telegram.org/bots/features#deep-linking`

## Documento complementar de execucao

- plano detalhado: `docs/architecture/telegram-private-direct-intake-execution-plan.md`

## Nota operacional importante

O token do bot do Telegram deve viver apenas em variavel segura de ambiente.
Se um token real tiver sido exposto fora de um cofre seguro, ele deve ser rotacionado antes de qualquer rollout.
