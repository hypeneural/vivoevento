# Arquitetura de Recebimento de Midias por Evento

## Objetivo

Este documento descreve:

- como a estrutura atual do repositorio recebe midias de um evento;
- o que ja existe para WhatsApp via Z-API, grupo, link publico e pipeline de midia;
- o que ainda falta para suportar bem os cenarios desejados;
- qual deve ser a arquitetura alvo para receber fotos com consistencia operacional.

O foco e o problema de ingestao de midia do evento, nao apenas o endpoint do webhook.

## Cenarios de negocio desejados

O produto precisa suportar pelo menos estes 3 modos de entrada:

### 1. Grupo de WhatsApp vinculado ao evento

Cenario:

- a cerimonialista ou anfitriao cria um grupo no proprio telefone;
- adiciona os convidados;
- adiciona tambem o numero da instancia do WhatsApp;
- a assistente "Nany" fica dentro do grupo;
- as fotos enviadas naquele grupo vao para o evento correto;
- a Nany pode reagir e responder em cima da foto enviada.

Regra esperada:

- o grupo precisa estar vinculado a um evento;
- o vinculo deve usar o `phone`/`group_external_id` que a Z-API manda no
  callback;
- toda midia valida que chegar nesse grupo entra no intake do evento;
- a reacao e a resposta precisam acontecer no contexto do grupo e da mensagem original.

### 2. Conversa privada por codigo

Cenario:

- o convidado manda uma mensagem em privado para a instancia;
- exemplo:

```text
Quero enviar fotos para o telao!
Codigo: #CODIGOAQUI
```

- o sistema identifica o evento ativo;
- abre uma janela temporaria para aquele telefone;
- a partir dai as fotos enviadas por esse telefone vao para o evento.

Regra esperada:

- o codigo precisa identificar o evento;
- o remetente precisa ganhar uma sessao temporaria de envio;
- essa sessao precisa expirar;
- a Nany deve confirmar a ativacao.

### 3. Upload por pagina/link

Cenario:

- o convidado entra pelo link publico de upload;
- envia a foto pelo navegador;
- a foto entra no pipeline.

Regra esperada:

- a origem precisa ficar rastreada;
- idealmente devemos distinguir se a entrada veio por link direto ou por QR.

## Resumo executivo

Hoje o repositorio esta assim:

- o modulo `WhatsApp` recebe o webhook da Z-API, normaliza parte do payload e persiste o inbound em tabelas `whatsapp_*`;
- o modulo `InboundMedia` deveria ser a camada canonica de intake de midia, mas a maior parte do fluxo de webhook ainda esta em scaffold;
- o upload publico por link ja cria `event_media` direto e entra no pipeline real;
- a pipeline de `MediaProcessing` esta boa quando a midia ja existe em `event_media`;
- o fluxo "grupo WhatsApp -> evento -> galeria" ainda nao esta fechado;
- o fluxo "conversa privada por codigo" ainda nao existe;
- a automacao da Nany existe so parcialmente: ha job de reacao automatica, mas ele nao esta ligado ao evento inbound e ainda usa alvo errado para mensagens de grupo.

Conclusao curta:

- a borda de provider WhatsApp existe;
- a pipeline de midia existe;
- o que falta e a camada de resolucao de contexto do evento e a ponte canonica entre `WhatsApp` e `InboundMedia`.

## Estrutura atual por modulo

## 1. Modulo `WhatsApp`

Responsabilidade atual:

- gerenciar instancias e conexao;
- receber webhooks inbound;
- normalizar payload da Z-API;
- persistir `whatsapp_inbound_events`, `whatsapp_chats` e `whatsapp_messages`;
- enviar mensagens, imagens, audio e reacoes;
- manter bindings de grupos com eventos.

Arquivos centrais:

- `apps/api/app/Modules/WhatsApp/Http/Controllers/WhatsAppWebhookController.php`
- `apps/api/app/Modules/WhatsApp/Jobs/ProcessInboundWebhookJob.php`
- `apps/api/app/Modules/WhatsApp/Services/WhatsAppInboundRouter.php`
- `apps/api/app/Modules/WhatsApp/Clients/Providers/ZApi/ZApiWebhookNormalizer.php`
- `apps/api/app/Modules/WhatsApp/Models/WhatsAppGroupBinding.php`

O que esta bom:

- rotas publicas de webhook estao corretas;
- instancia e provider ja sao resolvidos;
- deduplicacao basica por `provider_message_id` existe;
- ha estrutura boa para outbound;
- ha tabela especifica para binding de grupo.

O que esta fraco:

- o modulo ainda termina cedo demais em `whatsapp_*`;
- ele ainda nao resolve direito o contexto do evento;
- ele envia o raw payload para a pipeline legada em vez de uma estrutura canonica;
- a automacao da Nany ainda nao esta amarrada corretamente.

## 2. Modulo `InboundMedia`

Responsabilidade pretendida:

- ser a camada canonica de intake, independente da origem;
- persistir a mensagem inbound normalizada;
- rotear para o evento;
- disparar o download da midia.

Arquivos centrais:

- `apps/api/app/Modules/InboundMedia/Jobs/ProcessInboundWebhookJob.php`
- `apps/api/app/Modules/InboundMedia/Jobs/NormalizeInboundMessageJob.php`
- `apps/api/app/Modules/InboundMedia/Models/InboundMessage.php`
- `apps/api/app/Modules/InboundMedia/Models/ChannelWebhookLog.php`

Problema principal:

- os jobs ainda estao em scaffold;
- a ponte para o fluxo real de galeria ainda nao foi implementada.

## 3. Modulo `MediaProcessing`

Responsabilidade atual:

- gerar variantes;
- rodar safety;
- tomar decisao de moderacao;
- publicar a midia.

Arquivos centrais:

- `apps/api/app/Modules/MediaProcessing/Jobs/GenerateMediaVariantsJob.php`
- `apps/api/app/Modules/ContentModeration/Jobs/AnalyzeContentSafetyJob.php`
- `apps/api/app/Modules/MediaProcessing/Jobs/RunModerationJob.php`
- `apps/api/app/Modules/MediaProcessing/Jobs/PublishMediaJob.php`

O que esta bom:

- pipeline posterior esta funcional;
- variantes, fingerprint, moderation e publish ja existem;
- wall e moderacao ja consomem esses eventos.

Problema principal:

- `DownloadInboundMediaJob` ainda esta em scaffold;
- o intake externo ainda nao chega automaticamente em `event_media`.

## 4. Modulo `Events` e links publicos

O evento ja possui:

- `upload_slug`
- `slug`
- `wall_code`

Links publicos existentes:

- upload: `/upload/{upload_slug}`
- galeria: `/e/{slug}/gallery`
- wall: `/wall/player/{wall_code}`

Problema:

- nao existe hoje um codigo dedicado para ativacao de envio por WhatsApp em conversa privada;
- `upload_slug` e longo e orientado a URL;
- `wall_code` pertence ao dominio do wall, nao do intake de midia;
- portanto nenhum identificador existente e um encaixe limpo para o fluxo "mande #CODIGO".

## 5. Modulo `Channels`

Estrutura atual:

- existe a tabela `event_channels`;
- existe enum generico com `whatsapp_group`, `public_upload_link`, `telegram_bot`, `internal_upload`.

Problema:

- o WhatsApp em producao pratica usa `whatsapp_group_bindings`, nao `event_channels`;
- hoje ha duplicidade de conceito entre "channel do evento" e "binding do grupo".

## Fluxos atuais que realmente funcionam

## 1. Upload publico por link

Fluxo real:

1. `POST /api/v1/public/events/{uploadSlug}/upload`
2. `PublicUploadController`
3. cria `event_media` diretamente
4. dispara `GenerateMediaVariantsJob`

Persistencia atual:

- `source_type = public_upload`
- `source_label = sender_name`
- `original_disk`
- `original_path`
- `client_filename`

Observacao importante:

- esse fluxo funciona;
- mas o backend ainda nao distingue `public_upload_link` de `public_upload_qr`;
- a UI fala em link e QR, mas a persistencia real cai no mesmo `source_type`.

## 2. WhatsApp inbound ate `whatsapp_messages`

Fluxo real:

1. webhook entra em `/api/v1/webhooks/whatsapp/zapi/{instanceKey}/inbound`
2. `ProcessInboundWebhookJob`
3. `ZApiWebhookNormalizer`
4. `WhatsAppInboundRouter`
5. cria `whatsapp_chats` e `whatsapp_messages`
6. dispara `WhatsAppMessageReceived`

Observacao importante:

- esse fluxo funciona ate a persistencia do inbound no dominio `whatsapp_*`;
- ele ainda nao chega automaticamente em `event_media`.

## 3. Binding de grupo

Fluxo real:

- existe CRUD de binding em `WhatsAppGroupBindingController`;
- o binding suporta `binding_type = event_gallery`;
- o listener `RouteInboundToMediaPipeline` tenta enviar a mensagem com midia para a pipeline de intake.

Problema:

- como `InboundMedia` ainda esta parcial, o binding ainda nao fecha o loop.

## Fluxos desejados vs estado atual

| Fluxo | Estado atual | Gap |
| --- | --- | --- |
| Grupo de WhatsApp vinculado ao evento | Parcial | persiste inbound e binding, mas nao cria `event_media` automaticamente |
| Nany reagindo automaticamente no grupo | Parcial | ha job de reacao, mas nao esta ligado ao evento inbound e o alvo da reacao em grupo esta incorreto |
| Nany respondendo em cima da foto | Nao implementado | nao existe fluxo de reply contextual automatizado |
| Conversa privada por codigo | Nao implementado | nao existe parser de codigo, nem sessao de envio por telefone |
| Upload por link | Implementado | falta distinguir melhor origem por link vs QR |
| Rajada de 40 fotos | Parcial | intake pode receber, mas falta controle de lote, resposta resumida e backlog canonico |

## Analise do payload real da Z-API vs parser atual

Os exemplos que voce mandou sao muito importantes porque mostram o formato real que precisamos suportar.

## Exemplo de foto

Campos relevantes do exemplo:

- `isGroup`
- `instanceId`
- `messageId`
- `phone`
- `fromMe`
- `momment`
- `status`
- `chatName`
- `senderName`
- `senderPhoto`
- `participantPhone`
- `participantLid`
- `type = ReceivedCallback`
- `image.mimeType`
- `image.imageUrl`
- `image.caption`
- `image.width`
- `image.height`
- `image.viewOnce`

## Exemplo de texto

Campos relevantes do exemplo:

- `text.message`
- `status = RECEIVED`
- `momment`
- `fromMe`
- `type = ReceivedCallback`

## Exemplo real validado em 2026-04-05

### Grupo `Evento vivo 1` com imagem e legenda

Campos validados no ambiente local:

- `chatName = Evento vivo 1`
- `phone = 120363425796926861-group`
- `participantPhone = 554896553954`
- `participantLid = 18924129272011@lid`
- `senderName = Anderson Marques`
- `senderPhoto = https://pps.whatsapp.net/...`
- `connectedPhone = 554896553954`
- `fromMe = true`
- `image.caption = Teste de grupo`
- `image.imageUrl` preenchido

Leitura de produto:

- o grupo e identificado por `phone`;
- o autor real e identificado por `participantPhone` / `participantLid`;
- avatar e nome do autor ja chegam no callback;
- `fromMe=true` exige regra explicita para nao virar intake automatico por
  engano.

### Grupo com callback de sistema

Tambem houve callback real com:

- `notification = GROUP_PARTICIPANT_LEAVE`
- `chatName = Automacao & I.A. na Pratica | Hype Neural`
- `participantPhone = 270497728188446`
- `senderName = Alvarenga`

Leitura de produto:

- grupos nao geram apenas mensagens de midia/texto;
- existem callbacks de sistema que devem ficar auditados, mas nao devem gerar
  `event_media`.

### Callback de reacao

Tambem houve callback real com:

- `reaction.value = heart`
- `reaction.referencedMessage.messageId = ...`
- `type = ReceivedCallback`

Leitura de produto:

- reacao e um tipo de interacao separado;
- nao deve quebrar o normalizador;
- nao deve virar `event_media`.

## O que o parser atual faz bem

| Campo do payload | Estado atual |
| --- | --- |
| `text.message` | suportado |
| `image.imageUrl` | suportado |
| `isGroup` | suportado como fallback de deteccao de grupo |
| `phone` | suportado como fallback de chat/sender |
| `senderName` / `chatName` | parcialmente suportado |

## O que o parser atual perde ou interpreta errado

| Campo do payload | Estado atual | Impacto |
| --- | --- | --- |
| `status = RECEIVED` | hoje pode classificar a mensagem como `status` em vez de `message` | semantica errada do inbound |
| `momment` | ignorado | timestamp errado, cai em `now()` |
| `participantPhone` | ignorado | em grupo, o sistema pode nao saber quem enviou a foto |
| `participantLid` | ignorado | perde identidade complementar do participante |
| `fromMe` | ignorado | risco de capturar mensagem da propria Nany como inbound do evento |
| `senderPhoto` | ignorado | perde avatar/autoria do remetente |
| `instanceId` | nao validado contra a rota | pouca confianca operacional |
| `image.mimeType` | ignorado | `mime_type` fica incompleto |
| `image.caption` | ignorado | legenda da foto se perde |
| `image.width` / `image.height` | ignorados no intake | metadado se perde antes do processamento |
| `image.thumbnailUrl` | ignorado | perde asset auxiliar |
| `image.viewOnce` | ignorado | nao ha regra de negocio para esse caso |
| `reaction` | pode quebrar o parser | callback real falha em vez de ser auditado |
| `notification` | hoje tende a cair como `system` generico | mistura evento operacional com mensagem |

## Conclusao sobre o parser

O normalizador da Z-API precisa ser refeito para o formato real do provider.

Hoje ele esta mais proximo de um "best effort parser" do que de um adaptador confiavel para producao.

## Gaps funcionais mais importantes

## 1. Falta uma camada canonica de resolucao do evento

Hoje o sistema sabe que chegou uma mensagem do WhatsApp, mas nao sabe resolver de forma canonica:

- por qual estrategia essa mensagem pertence a um evento;
- se veio por grupo vinculado;
- se veio por telefone ativado por codigo;
- se veio por link publico;
- se veio por QR.

Precisamos de uma camada unica de resolucao de contexto.

Essa camada precisa responder por ordem:

1. a origem foi `whatsapp_group`, `whatsapp_dm_code` ou `public_upload`?
2. qual evento foi resolvido?
3. quem e o autor real da midia?
4. essa mensagem e elegivel para virar `event_media`?
5. se nao for elegivel, ela ainda precisa ser auditada?

## 2. Falta uma estrategia clara para grupo vs conversa privada

Hoje existe:

- suporte parcial a grupo vinculado;
- nenhum suporte a sessao privada por codigo.

Precisamos tratar como dois modos diferentes:

- `whatsapp_group`
- `whatsapp_dm_code`

E os dois precisam convergir para o mesmo intake canonico do evento.

## 3. A origem ainda esta mal modelada

Hoje `source_type` mistura niveis diferentes:

- `whatsapp`
- `public_upload`
- `public_link`
- `qrcode`

Isso mistura:

- canal principal
- subtipo de entrada

Melhor modelo:

- `source_type`: `whatsapp`, `public_upload`, `telegram`, `internal`
- `source_subtype`: `group`, `dm_code`, `link`, `qr`, `panel`

Para WhatsApp, a autoria precisa viajar junto:

- `sender_external_id`
- `sender_phone_e164`
- `sender_lid`
- `sender_name`
- `sender_avatar_url`

## 4. A automacao da Nany ainda nao esta pronta

Problemas atuais:

- ha `SendAutoReactionJob`, mas o listener nao esta registrado no provider do modulo;
- em grupo, a reacao usa `sender_phone` como alvo, quando o correto tende a ser o chat/grupo;
- nao existe resposta automatica em cima da foto;
- nao existe controle de "reagir 1 vez por lote" quando o convidado manda 40 fotos.

## 5. Falta tratamento de lote e rajada

Hoje o produto ainda nao modela bem:

- 40 fotos de uma vez do mesmo remetente;
- um album/logica de burst;
- resposta resumida em vez de spam de 40 respostas;
- backlog por remetente no intake antes de chegar ao wall.

## 6. O modulo `Channels` esta subutilizado

Hoje temos:

- `event_channels` como abstracao generica;
- `whatsapp_group_bindings` como configuracao real do grupo.

Isso gera duplicidade de conceito.

## 7. Faltam filas Horizon para WhatsApp

O codigo usa:

- `whatsapp-inbound`
- `whatsapp-send`
- `whatsapp-sync`

Mas o Horizon atual nao supervisiona essas filas.

## 8. Faltam testes da borda mais critica

Nao existe hoje cobertura relevante para:

- webhook inbound da Z-API;
- parsing de payload real de imagem e texto;
- grupo com `participantPhone`;
- fluxo `fromMe`;
- fluxo de binding de grupo para evento;
- sessao por codigo;
- reacao/resposta automatica da Nany;
- deduplicacao e lotes.

## Estrutura alvo recomendada

## Principio central

Nao devemos colocar a regra de negocio do intake dentro do modulo `WhatsApp`.

O desenho recomendado e:

- `WhatsApp` continua sendo a borda de transporte/provider;
- `InboundMedia` vira a camada canonica de intake;
- `MediaProcessing` continua sendo a camada posterior;
- `Events` e `Channels` sustentam a configuracao dos canais do evento.

## Responsabilidade por modulo

### `WhatsApp`

Deve cuidar de:

- receber webhook;
- validar instancia;
- normalizar payload do provider;
- persistir trilha tecnica em `whatsapp_*`;
- disparar um evento interno com DTO canonico de transporte;
- enviar reacoes, replies e mensagens da Nany.

Nao deve cuidar de:

- decidir sozinho qual evento recebe a midia.

### `InboundMedia`

Deve cuidar de:

- resolver o contexto do evento;
- decidir a estrategia de roteamento;
- criar `InboundMessage`;
- registrar origem canonica;
- decidir se a midia vira `event_media`;
- acionar download.

Essa e a camada certa para centralizar:

- grupo vinculado;
- codigo por telefone;
- upload publico;
- futuras origens.

### `Events` / `Channels`

Devem cuidar de:

- configuracao dos canais publicos e privados do evento;
- codigo publico de intake do evento;
- estado "evento pode receber midia agora?".

### `MediaProcessing`

Continua cuidando de:

- download;
- variantes;
- moderation;
- publish.

## Fluxo alvo recomendado

```text
Z-API Webhook
  -> WhatsAppWebhookController
  -> ProcessInboundWebhookJob
  -> ZApiWebhookNormalizerV2
  -> WhatsAppInboundRouter
  -> WhatsAppMessageReceived
  -> ResolveInboundMediaContextAction
  -> CreateInboundMessageAction
  -> DownloadInboundMediaJob
  -> EventMedia
  -> GenerateMediaVariantsJob
  -> AnalyzeContentSafetyJob
  -> RunModerationJob
  -> PublishMediaJob
```

Diferenca central para o estado atual:

- nao mandar raw payload para `InboundMedia`;
- mandar uma estrutura canonica ja normalizada.

## Melhorias de modelagem recomendadas

## 1. Criar uma sessao de intake por telefone

Para o fluxo de codigo via conversa privada, recomendamos uma nova tabela:

### `event_media_ingestion_sessions`

Campos sugeridos:

- `id`
- `organization_id`
- `event_id`
- `instance_id`
- `provider_key`
- `channel_type`
- `channel_subtype`
- `sender_phone`
- `sender_key`
- `activation_code`
- `status`
- `activated_at`
- `expires_at`
- `last_message_at`
- `messages_count`
- `media_count`
- `metadata_json`
- `created_at`
- `updated_at`

Valores sugeridos:

- `channel_type = whatsapp`
- `channel_subtype = dm_code`

Uso:

- quando o usuario manda `#CODIGO`, cria ou reabre uma sessao;
- midias futuras daquele telefone entram no evento enquanto a sessao estiver ativa.

## 2. Criar um codigo proprio de intake do evento

Nao recomendamos reutilizar `wall_code` ou `upload_slug`.

Recomendacao:

- criar um codigo proprio do intake de midia do evento.

Duas alternativas:

### Alternativa robusta

Nova tabela:

### `event_media_ingestion_codes`

Campos sugeridos:

- `id`
- `event_id`
- `code`
- `is_active`
- `expires_at`
- `metadata_json`
- `created_at`
- `updated_at`

### Alternativa simples

Adicionar em `events`:

- `media_inbox_code`

Recomendacao deste documento:

- se a feature for produto central, usar tabela propria;
- se for MVP rapido, um campo no `events` resolve.

## 3. Enriquecer `inbound_messages`

Tabela atual:

- `inbound_messages`

Campos novos recomendados:

- `source_type`
- `source_subtype`
- `provider_key`
- `sender_key`
- `source_chat_id`
- `source_group_id`
- `source_batch_key`
- `from_me`
- `ingestion_session_id`
- `media_meta_json`
- `provider_context_json`

Isso permite rastrear:

- grupo vinculado;
- DM por codigo;
- link publico;
- origem do lote;
- contexto do provider.

## 4. Enriquecer `event_media`

Campos novos recomendados:

- `source_subtype`
- `sender_key`
- `source_batch_key`
- `source_context_json`

Motivo:

- hoje `event_media` so diferencia bem a origem em nivel superficial;
- para analitica, debugging, wall fairness e automacao isso ainda e pouco.

## 5. Unificar melhor `event_channels` com WhatsApp

Recomendacao:

- `event_channels` deve ser o registro canonico do canal do evento;
- `whatsapp_group_bindings` e futuras configuracoes especificas devem apontar para esse canal ou ser absorvidas por ele.

Modelo sugerido:

- `event_channels` guarda:
  - `event_id`
  - `channel_type`
  - `provider`
  - `external_id`
  - `label`
  - `status`
  - `config_json`
- `whatsapp_group_bindings` vira extensao operacional ou e gradualmente migrado.

Tipos recomendados:

- `whatsapp_group`
- `whatsapp_dm_code`
- `public_upload_link`
- `public_upload_qr`

Decisao recomendada:

- no curto prazo, `whatsapp_group_bindings` continua operacional para nao
  travar entrega;
- no medio prazo, `event_channels` deve virar o registro canonico e os bindings
  de grupo devem apontar para ele ou ser absorvidos por ele.

## Regras por cenario

## Cenario A: grupo de WhatsApp vinculado

### Regra alvo

1. webhook chega;
2. se `isGroup = true`, resolver `group_external_id`;
3. localizar binding ativo do grupo;
4. validar que o binding pertence a um evento apto a receber midia;
5. criar `InboundMessage`;
6. se houver midia, criar `event_media` via pipeline.

### Regras tecnicas importantes

- para grupo, `sender_phone` deve vir de `participantPhone`, nao de `phone`;
- `phone` ou `chatId` devem ser tratados como identificador do grupo;
- `sender_key` deve ser o participante normalizado, nao o grupo;
- `sender_avatar_url` deve vir de `senderPhoto` quando existir;
- a Nany deve reagir no contexto do grupo/chat, nao no telefone do participante.

### Metadados uteis

- `group_external_id`
- `group_name`
- `participant_phone`
- `participant_lid`
- `sender_avatar_url`
- `connected_phone`
- `instance_external_id`

## Cenario B: codigo por conversa privada

### Regra alvo

1. webhook de texto chega em chat privado;
2. parser identifica `#CODIGO`;
3. resolve evento pelo codigo;
4. valida que o evento esta apto a receber midia;
5. cria ou atualiza `event_media_ingestion_session`;
6. envia resposta da Nany confirmando a ativacao;
7. midias seguintes do mesmo telefone vao para o evento enquanto a sessao estiver ativa.

Melhor chave de sessao:

- `instance_id + sender_external_id`

Campos complementares da sessao:

- `sender_phone_e164`
- `sender_lid`
- `sender_name`
- `sender_avatar_url`
- `connected_phone`

### Regras de negocio sugeridas

- sessao expira em X horas;
- nova mensagem com codigo renova a sessao;
- uma mesma conversa pode trocar de evento so com novo codigo;
- se o evento estiver encerrado, a Nany responde com erro amigavel;
- se o modulo `live` estiver desligado, nao ativa a sessao.

### Regex inicial sugerida

Padrao simples:

```text
#([A-Z0-9]{4,12})
```

Normalizacao:

- uppercase;
- trim;
- sem depender de frase fixa.

## Cenario C: upload por link e QR

### Estado atual

- o link publico ja funciona;
- QR e link acabam indo para o mesmo fluxo e o mesmo `source_type`.

### Melhoria recomendada

No minimo, identificar:

- `source_type = public_upload`
- `source_subtype = link` ou `qr`

Como inferir:

- parametro na URL;
- querystring de campanha;
- ou `entrypoint` assinado no frontend publico.

Conclusao de produto:

- WhatsApp e upload web devem entrar na mesma camada canonica de intake;
- a diferenca nao deve estar no pipeline posterior, e sim no resolvedor de
  contexto e no `source_subtype`.

## Nany: automacao recomendada

## Papel da Nany

A Nany deve ser tratada como camada de automacao do modulo `WhatsApp`, nao como parte do parser.

## Acoes desejadas

### 1. Reacao automatica

Casos:

- grupo vinculado ao evento;
- DM com sessao ativa;
- imagem aceita para intake.

Regra sugerida:

- reagir com relogio quando a midia elegivel for reconhecida pelo intake;
- reagir com coracao quando a midia for realmente publicada;
- reagir so uma vez por fase da mesma mensagem;
- em rajada, permitir reagir apenas na primeira do lote ou usar debounce configuravel.

Requisito tecnico critico:

- o `messageId` inbound precisa ser preservado como ancora da automacao;
- o destino da reacao deve usar o `chat_external_id`:
  - grupo -> `group_external_id`
  - privado -> `chat_external_id` do chat

Leitura de produto:

- o relogio comunica "ja identifiquei seu envio";
- o coracao comunica "sua foto realmente foi ao ar";
- as duas reacoes so fazem sentido para midia vinculada a evento ativo.

### 2. Resposta em cima da foto

Estado atual:

- nao existe reply contextual automatizado;
- `sendText` atual nao suporta `messageId` de reply.

Melhoria recomendada:

- suportar reply por `messageId` no outbound;
- permitir templates curtos:
  - "Recebi sua foto para o evento X"
  - "Foto recebida para o telao"
  - "Seu envio foi associado ao evento X"

Recomendacao de faseamento:

- fase 1: apenas reacao de relogio e coracao
- fase 2: reply textual por IA usando o `messageId` da mensagem inbound
- fase 3: prompt contextual com leitura da imagem e descricao curta

Regra recomendada:

- o reply textual deve acontecer so quando a midia for segura o suficiente para
  resposta automatica;
- em grupo, reply textual precisa ser configuravel para evitar poluicao do chat;
- em DM, reply textual e mais seguro como experiencia padrao.

### 3. Confirmacao da sessao por codigo

Exemplos:

- "Codigo aceito. Agora pode me mandar suas fotos para o evento Casamento Ana e Pedro."
- "Sessao ativa por 12h. Pode mandar suas fotos aqui."

### 4. Resumo de lote

Para rajada de fotos:

- evitar responder 40 vezes;
- mandar um resumo:
  - "Recebi 40 fotos. Vou processar e enviar para a galeria do evento."

## Controle de rajada e lote

Esse ponto e critico para o seu uso real.

Quando o convidado manda 40 fotos:

- o intake deve aceitar as 40;
- o bot nao deve gerar 40 respostas;
- o wall nao deve tentar exibir as 40 logo em seguida.

## Regras recomendadas

### Intake

- cada foto continua sendo uma unidade de `InboundMessage` e `event_media`;
- todas entram na pipeline;
- criar um `source_batch_key` por rajada.

### Automacao

- uma unica reacao ou resposta por lote, com debounce curto;
- registrar contador de lote.

### Wall

- deixar o wall resolver backlog por remetente;
- usar `sender_key` e `source_batch_key` para fairness e anti-monopolio.

## Regras operacionais recomendadas

## 1. Identificacao de evento apto a receber midia

Hoje `isActive()` do evento olha principalmente status.

Para intake por WhatsApp, sugerimos regra mais rica:

- `status = active`
- modulo `live` habilitado
- opcionalmente dentro de uma janela operacional baseada em `starts_at` / `ends_at`
- sem bloqueio comercial/operacional

## 2. Ignorar `fromMe = true`

Para evitar loops:

- mensagens enviadas pela propria instancia nao devem entrar como midia inbound do evento.

## 3. Validar `instanceId`

Se o payload trouxer `instanceId`, validar contra a instancia da rota.

Isso melhora:

- seguranca;
- observabilidade;
- debug.

## 4. Persistir tudo antes de falhar

Hoje, se a instancia nao for encontrada, o payload pode se perder.

Recomendacao:

- persistir o raw payload mesmo quando nao houver instancia valida;
- marcar status de erro com motivo tecnico.

## Melhorias prioritarias

## P0 - obrigatorio para o fluxo funcionar de verdade

1. Refazer o normalizador da Z-API para o payload real.
2. Implementar a ponte canonica `WhatsApp -> InboundMedia`.
3. Implementar `DownloadInboundMediaJob`.
4. Criar `event_media` automatico para midia do WhatsApp.
5. Adicionar workers Horizon para filas `whatsapp-*`.
6. Ensinar o parser a tratar `reaction` e notificacoes de grupo sem exception.

## P1 - obrigatorio para o produto atender os cenarios desejados

1. Implementar sessao por codigo em conversa privada.
2. Criar codigo dedicado de intake do evento.
3. Corrigir reacao da Nany para contexto de grupo.
4. Implementar reply contextual em cima da foto.
5. Modelar lote/rajada com `source_batch_key`.
6. Persistir `senderPhoto`/avatar do autor quando o provider enviar.

## P2 - melhora estrutural importante

1. Unificar `event_channels` com bindings de WhatsApp.
2. Distinguir `source_type` de `source_subtype`.
3. Enriquecer `inbound_messages` e `event_media` com contexto canonico.
4. Passar a distinguir link vs QR no upload publico.

## P3 - resiliencia e qualidade

1. Testes de inbound com payload real de texto e imagem.
2. Testes de grupo com `participantPhone`.
3. Testes de codigo por DM.
4. Testes de reacao/resposta automatica.
5. Testes de rajada com 40 fotos.

## Estrutura recomendada de implementacao

## Dentro do modulo `WhatsApp`

Criar ou evoluir:

- `Actions/NormalizeZApiInboundMessageAction`
- `Actions/DispatchWhatsAppAutomationAction`
- `Support/ZApiPayloadInspector`

Evoluir DTO:

- `NormalizedInboundMessageData`

Campos novos sugeridos:

- `providerEventType`
- `fromMe`
- `connectedPhone`
- `participantPhone`
- `participantLid`
- `mediaMeta`

## Dentro do modulo `InboundMedia`

Criar:

- `Actions/ResolveInboundMediaContextAction`
- `Actions/CreateInboundMessageAction`
- `Actions/ResolveEventFromWhatsAppGroupAction`
- `Actions/ResolveEventFromWhatsAppSessionAction`
- `Actions/ResolveEventFromPublicUploadAction`

Criar modelos:

- `EventMediaIngestionSession`
- opcionalmente `EventMediaIngestionCode`

## Dentro do modulo `MediaProcessing`

Implementar:

- `DownloadInboundMediaJob`

Evoluir:

- servico de download para aceitar URL externa da Z-API;
- idempotencia por `provider_message_id` + `source_batch_key`.

## Dentro do modulo `Events` / `Channels`

Adicionar:

- codigo de intake do evento;
- canal `whatsapp_dm_code`;
- evolucao do registry de canais.

## Conclusao final

Hoje a estrutura atual esta boa para separar dominios, mas ainda esta incompleta para o problema real de recebimento de midias por evento.

O repositorio ja tem:

- borda de provider WhatsApp;
- upload publico funcional;
- pipeline posterior de midia boa;
- base para bindings de grupo.

O que falta e a camada central de intake que responda:

- "esta mensagem pertence a qual evento?"
- "por que estrategia ela foi vinculada?"
- "como essa origem deve ser rastreada?"
- "como a Nany deve agir nesse contexto?"

Recomendacao principal deste documento:

- manter `WhatsApp` como modulo de transporte;
- transformar `InboundMedia` na camada canonica de intake de midia;
- implementar 3 estrategias formais de resolucao:
  - grupo vinculado;
  - sessao por codigo em DM;
  - upload publico;
- e parar de depender do bridge atual que apenas passa raw payload para jobs legacy incompletos.

Se fizermos isso, a plataforma passa a suportar bem os tres cenarios que voce quer, sem espalhar regra entre controllers, listeners e payloads soltos.
