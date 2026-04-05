# WhatsApp Z-API Webhook Execution Plan

## Objetivo

Este documento transforma a leitura da documentacao oficial da Z-API, dos logs
reais ja capturados no `eventovivo` e do estado atual do modulo `WhatsApp` em
um plano de execucao para organizar corretamente os webhooks da Z-API.

O foco aqui nao e apenas "receber webhook", e sim fechar a logica de negocio do
Evento Vivo para:

- receber texto, imagem, audio e midia com legenda;
- distinguir grupo autorizado de conversa privada;
- identificar corretamente quem enviou a mensagem;
- separar entrada de mensagem, status de mensagem, status da instancia e
  presenca de chat;
- preparar o cenario de intake por grupo vinculado e por codigo em conversa
  privada;
- impedir que callbacks operacionais poluam `whatsapp_messages` como se fossem
  mensagens reais.

## Referencias primarias

Internas:

- [docs/architecture/event-media-intake-architecture.md](./event-media-intake-architecture.md)
- [docs/architecture/whatsapp-zapi-photo-webhook-analysis.md](./whatsapp-zapi-photo-webhook-analysis.md)
- [docs/flows/whatsapp-inbound.md](../flows/whatsapp-inbound.md)
- [apps/api/app/Modules/WhatsApp/README.md](../../apps/api/app/Modules/WhatsApp/README.md)

Oficiais da Z-API:

- `https://developer.z-api.io/webhooks/on-message-received`
- `https://developer.z-api.io/webhooks/on-whatsapp-message-status-changes`
- `https://developer.z-api.io/webhooks/on-webhook-connected`
- `https://developer.z-api.io/webhooks/on-whatsapp-disconnected`
- `https://developer.z-api.io/webhooks/on-chat-presence`
- `https://developer.z-api.io/webhooks/on-message-send`

## Este plano responde 7 perguntas

1. quais webhooks da Z-API realmente importam para o Evento Vivo;
2. qual deve ser a topologia alvo dos endpoints publicos;
3. que dados de identidade precisam ser persistidos por callback;
4. como distinguir grupo vinculado de conversa privada por codigo;
5. o que fazer com `fromMe`, `participantPhone`, `participantLid`,
   `connectedPhone`, `status`, `momment` e `caption`;
6. que partes entram na fase 1 e que partes ficam fora;
7. quais testes automatizados precisam nascer antes de qualquer alteracao de
   codigo.

## Estado atual validado em 2026-04-05

Leitura reconciliada com:

- codigo atual do modulo `WhatsApp`;
- logs em `apps/api/storage/logs/whatsapp-2026-04-05.log`;
- payloads reais persistidos em `whatsapp_inbound_events` e
  `whatsapp_messages`;
- `php artisan test tests/Feature/WhatsApp`.

Constatacoes objetivas:

- o `ReceivedCallback` da Z-API esta chegando e persistindo mensagens reais,
  inclusive texto, imagem, audio e imagem com legenda;
- em grupos, a Z-API envia campos importantes que hoje o sistema nao trata
  corretamente:
  - `phone`: id do grupo/chat
  - `participantPhone`: telefone do participante que enviou
  - `participantLid`: id raw do participante no WhatsApp
  - `senderName`: nome do participante
  - `fromMe`: se a mensagem partiu do numero conectado
  - `connectedPhone`: telefone da instancia conectada
- o `MessageStatusCallback` ja chegou de verdade no endpoint `/delivery`, mas o
  backend atual o roteou como mensagem inbound `system`, poluindo
  `whatsapp_messages`;
- ainda nao ha amostra real local de `ConnectedCallback` ou
  `DisconnectedCallback` chegando em `/status`;
- ha eventos `fromMe=true` no `ReceivedCallback`, o que prova que a configuracao
  "notificar as enviadas por mim" ja esta impactando o inbound;
- esta instancia local nao possui `whatsapp_group_bindings` ativos, entao o
  caminho "grupo autorizado -> evento -> galeria" ainda nao foi validado ponta
  a ponta nesta base;
- o fluxo "conversa privada por codigo" continua nao implementado no codigo
  atual;
- a suite `tests/Feature/WhatsApp` esta verde, mas nao cobre controller/job de
  webhook da Z-API, nem `status`, nem `delivery`, nem identidade de grupo.

Evidencias operacionais relevantes desta rodada:

- houve `ReceivedCallback` real de imagem em grupo com:
  - `isGroup=true`
  - `phone=...-group`
  - `participantPhone=554896553954`
  - `senderName=Anderson Marques`
  - `fromMe=true`
  - `image.imageUrl` preenchido
- houve `ReceivedCallback` real de grupo com `fromMe=false` e
  `participantPhone=554792884228`;
- houve `MessageStatusCallback` real com `status=READ_BY_ME`, `ids=[...]` e
  `type=MessageStatusCallback`;
- houve `ReceivedCallback` real de imagem em grupo em `2026-04-05 17:21:55 BRT`
  com:
  - `chatName=Evento vivo 1`
  - `phone=120363425796926861-group`
  - `participantPhone=554896553954`
  - `participantLid=18924129272011@lid`
  - `senderName=Anderson Marques`
  - `senderPhoto=https://pps.whatsapp.net/...`
  - `connectedPhone=554896553954`
  - `fromMe=true`
  - `image.caption=Teste de grupo`
  - `image.imageUrl` preenchido
- houve `ReceivedCallback` real de sistema em grupo em `2026-04-05 17:22:35 BRT`
  com:
  - `notification=GROUP_PARTICIPANT_LEAVE`
  - `chatName=Automacao & I.A. na Pratica | Hype Neural`
  - `participantPhone=270497728188446`
  - `senderName=Alvarenga`
- houve `ReceivedCallback` real com `reaction` estruturada em
  `2026-04-05 16:33 BRT`, e o backend atual falhou porque o normalizador
  tratou `reaction` como se fosse texto simples;
- existem callbacks reais com ids nao-E.164 em `phone`, incluindo:
  - `...-group`
  - `...@lid`
  - `...@newsletter`
  - `status@broadcast`

## Premissas travadas

Estas decisoes devem ser tratadas como fechadas neste plano:

- a documentacao oficial da Z-API e a fonte final do contrato de callback;
- `ReceivedCallback` e webhook de mensagem, nao webhook de lifecycle de
  entrega;
- `MessageStatusCallback` e webhook de status de mensagem, nao mensagem inbound;
- `ConnectedCallback` e `DisconnectedCallback` sao lifecycle da instancia;
- `PresenceChatCallback` e sinal operacional/UX, nao intake de midia;
- `DeliveryCallback` do webhook "Ao enviar" e separado de
  `MessageStatusCallback`;
- nunca mais devemos classificar callback apenas pela presenca da chave
  `status`, porque `ReceivedCallback` tambem usa esse campo;
- o sistema deve preservar o payload bruto para auditoria, mesmo quando o
  callback nao gera `whatsapp_messages`;
- o sistema deve persistir identidade raw do provider e, separadamente, a
  versao normalizada quando existir telefone E.164 valido;
- por regra de produto, midia de evento deve depender de contexto explicito:
  - grupo vinculado ao evento
  - ou sessao privada ativa por codigo

## Regra de produto consolidada para intake do Evento Vivo

Esta secao trava a leitura final de negocio a partir dos logs reais, da doc
oficial da Z-API e dos fluxos ja existentes no repositorio.

### Canais oficiais de entrada de midia do evento

O Evento Vivo deve tratar intake de midia por 3 estrategias canonicas:

1. `whatsapp_group`
   - um ou mais grupos vinculados explicitamente ao evento;
   - relacionamento por `group_external_id` (`phone` do grupo na Z-API);
   - toda midia valida do grupo vai para o evento quando houver binding ativo;
2. `whatsapp_dm_code`
   - conversa privada com a instancia;
   - usuario envia um codigo do evento;
   - isso abre uma sessao temporaria de envio para aquele remetente;
3. `public_upload`
   - upload publico pelo link/QR ja existente do evento.

### Regra alvo de roteamento por origem

| Origem | Como resolve o evento | Quem e o autor | O que vira `event_media` |
| --- | --- | --- | --- |
| Grupo vinculado | `instance_id + group_external_id` em `whatsapp_group_bindings` | `participantPhone` / `participantLid` / `senderName` / `senderPhoto` | imagem, video, audio, documento elegiveis |
| Privado por codigo | sessao ativa por `instance_id + sender_external_id` | `phone` ou `chatLid`, `senderName`, `senderPhoto` | midia enviada durante a sessao |
| Upload web | `upload_slug` do evento | nome informado no form | arquivos aceitos no endpoint publico |

### O que nao deve virar intake de evento

- `ReceivedCallback` com `notification=*`
- `ReceivedCallback` de `reaction`
- `MessageStatusCallback`
- `ConnectedCallback`
- `DisconnectedCallback`
- `PresenceChatCallback`
- mensagens `fromMe=true`, salvo regra operacional futura explicitamente
  habilitada

### Identidade minima que o intake precisa preservar

Para qualquer origem via WhatsApp, a camada canonica de intake precisa sair com:

- `group_external_id` quando for grupo
- `chat_external_id`
- `sender_external_id`
- `sender_phone_e164` quando houver telefone humano real
- `sender_lid`
- `sender_name`
- `sender_avatar_url`
- `connected_phone`
- `from_me`
- `provider_message_id`
- `caption`
- `media_url`
- `provider_occurred_at`

Sem isso, o sistema nao consegue:

- saber quem enviou a foto no grupo;
- associar sessao privada por codigo ao remetente certo;
- mostrar credito/autoria;
- reagir ou responder corretamente;
- auditar conflitos entre numero conectado e participante.

## Topologia alvo dos webhooks da Z-API

### Conjunto minimo que precisa entrar na fase 1

| Painel Z-API | Callback oficial | Endpoint alvo | Papel no Evento Vivo | Status neste plano |
| --- | --- | --- | --- | --- |
| Ao receber | `ReceivedCallback` | `/api/v1/webhooks/whatsapp/{provider}/{instanceKey}/inbound` | intake de mensagem, texto, midia e legenda | obrigatorio |
| Ao conectar | `ConnectedCallback` | `/api/v1/webhooks/whatsapp/{provider}/{instanceKey}/status` | lifecycle da instancia | obrigatorio |
| Ao desconectar | `DisconnectedCallback` | `/api/v1/webhooks/whatsapp/{provider}/{instanceKey}/status` | lifecycle da instancia | obrigatorio |
| Receber status da mensagem | `MessageStatusCallback` | `/api/v1/webhooks/whatsapp/{provider}/{instanceKey}/delivery` | status de outbound | obrigatorio |

### Conjunto recomendado para fase posterior

| Painel Z-API | Callback oficial | Endpoint alvo sugerido | Papel no Evento Vivo | Status neste plano |
| --- | --- | --- | --- | --- |
| Presenca do chat | `PresenceChatCallback` | `/api/v1/webhooks/whatsapp/{provider}/{instanceKey}/presence` | UX/operacao de atendimento | opcional |
| Ao enviar | `DeliveryCallback` | `/api/v1/webhooks/whatsapp/{provider}/{instanceKey}/sent` | reconciliacao de envio via callback dedicado | opcional |

Decisao pratica:

- na fase 1, manter `Presenca do chat` e `Ao enviar` vazios no painel e
  documentados como fora do fluxo critico;
- se o produto realmente precisar deles, adicionar endpoints dedicados em vez
  de reciclar `/status` ou `/delivery`.

## Modelo alvo de identidade e contexto

O modelo atual usa `sender_phone` de forma simplista demais. Isso nao fecha a
realidade da Z-API.

Precisamos separar 5 conceitos:

1. `callback_type`
   - valor raw de `payload.type`
   - exemplos: `ReceivedCallback`, `MessageStatusCallback`,
     `ConnectedCallback`, `DisconnectedCallback`, `PresenceChatCallback`,
     `DeliveryCallback`
2. `chat_external_id`
   - id da conversa no provider
   - exemplos: `5548999999999`, `120363...-group`, `...@lid`, `...@newsletter`
3. `sender_external_id`
   - ator real da mensagem
   - em grupo deve preferir `participantPhone`, depois `participantLid`, depois
     fallback seguro
4. `sender_phone_e164`
   - telefone normalizado quando o valor for realmente um telefone
   - pode ser `null` para `@lid`, `@newsletter`, `status@broadcast` ou ids raw
5. `sender_name`
   - nome visivel do autor
   - deve vir de `senderName`

### Regra alvo por tipo de conversa

| Cenario | `chat_external_id` | `sender_external_id` | `sender_phone_e164` | `sender_name` | Observacao |
| --- | --- | --- | --- | --- | --- |
| Chat privado com telefone | `phone` | `phone` ou `senderLid` | telefone normalizado | `senderName` | fluxo comum |
| Chat privado com `@lid` | `phone` ou `senderLid` | `senderLid` | `null` | `senderName` | manter raw id |
| Newsletter/canal | `phone` com `@newsletter` | `phone` | `null` | `senderName` ou `chatName` | nao tratar como participante de evento |
| Grupo | `phone` do grupo | `participantPhone` ou `participantLid` | telefone do participante quando existir | `senderName` | `phone` nao e o autor |
| Callback de delivery/status | nao cria remetente de mensagem | ids de mensagem outbound | nao aplicavel | nao aplicavel | nao deve gerar `whatsapp_messages` |

### Regra alvo para `fromMe`

- `fromMe=true` significa que a mensagem partiu do numero conectado na
  instancia, nao de um convidado externo;
- por padrao de produto, `fromMe=true` nao deve alimentar intake automatico de
  galeria;
- o payload bruto continua sendo auditado;
- uma excecao operacional futura pode ser modelada por configuracao explicita
  do binding, nunca como comportamento padrao.

## Regras de negocio alvo do Evento Vivo

### 1. Grupo autorizado vinculado a evento

Regra alvo:

1. webhook `ReceivedCallback` chega;
2. sistema identifica `isGroup=true`;
3. resolve `group_external_id` pelo `chat_external_id`;
4. procura binding ativo em `whatsapp_group_bindings`;
5. se nao houver binding:
   - persiste inbound e chat
   - nao cria `event_media`
   - nao tenta intake do evento
6. se houver binding e a mensagem tiver midia:
   - usa `participantPhone` e `senderName` como autor real
   - preserva `participantLid` e `senderPhoto`
   - preserva caption quando existir
   - encaminha para intake canonico do evento
7. se houver binding e a mensagem for apenas texto:
   - persiste como mensagem
   - nao cria `event_media`
   - deixa espaco para comandos e automacoes futuras
8. se o callback for notificacao de sistema do grupo:
   - persiste auditoria
   - nao cria `event_media`
   - nao tenta automacao de foto

Decisao recomendada:

- a ponte "grupo autorizado -> evento -> galeria" deve aceitar apenas
  `fromMe=false` por padrao;
- reacoes e respostas automaticas ficam fora da primeira rodada deste plano,
  exceto se forem estritamente necessarias para confirmar intake.

### 2. Conversa privada por codigo

Regra alvo:

1. `ReceivedCallback` chega em chat privado;
2. texto e analisado em busca de `#CODIGO`;
3. codigo resolve o evento;
4. sistema cria ou renova uma sessao de intake por telefone/identidade raw;
5. instancia responde confirmando ativacao;
6. midias seguintes do mesmo remetente entram no evento enquanto a sessao
   estiver ativa;
7. sem sessao ativa, midia privada nao vai para o evento.

Identidade recomendada da sessao:

- chave principal: `instance_id + sender_external_id`
- complementar:
  - `sender_phone_e164`
  - `sender_lid`
  - `sender_name`
  - `sender_avatar_url`

Motivo:

- em alguns payloads o provedor manda telefone;
- em outros, `chatLid`/`participantLid`;
- precisamos sobreviver a mudancas de representacao sem perder a sessao.

Estado atual:

- este fluxo ainda nao existe no codigo atual;
- continua sendo requisito novo e deve ser tratado como milestone propria,
  nao como bug do webhook atual.

### 3. Midia com legenda

Regra alvo:

- `image.caption`, `video.caption` e campos equivalentes devem ser preservados;
- caption precisa acompanhar o intake canonico para o evento;
- caption nao pode depender de `payload['caption']`, porque a doc oficial da
  Z-API explicita `image.caption` e `video.caption`.

### 4. Status da mensagem outbound

Regra alvo:

- `MessageStatusCallback` deve localizar a mensagem outbound pelo
  `provider_message_id` contido em `ids[]`;
- esse callback nao deve criar `WhatsAppMessage` inbound;
- o sistema deve refletir pelo menos:
  - `SENT`
  - `RECEIVED`
  - `READ`
  - `READ_BY_ME`
  - `PLAYED`

Decisao recomendada:

- manter `status` canonico local em `queued`, `sending`, `sent`, `delivered`,
  `read` e `failed`;
- preservar o ultimo status raw do provider em campo proprio ou metadata;
- armazenar timestamps dedicados para `delivered_at`, `read_at` e `played_at`
  quando fizer sentido;
- `READ_BY_ME` deve ser tratado como evento operacional do numero conectado,
  nao como leitura do destinatario final.

### 5. Status da instancia

Regra alvo:

- `ConnectedCallback` atualiza a instancia para `connected`;
- `DisconnectedCallback` atualiza a instancia para `disconnected` e armazena
  contexto do erro quando existir;
- o webhook de status nao deve criar `whatsapp_messages`;
- a mudanca de estado deve refletir imediatamente em operacao, sem depender
  apenas do polling de `SyncInstanceStatusJob`.

### 6. Presenca do chat

Regra alvo:

- `PresenceChatCallback` nao entra no fluxo critico de intake;
- se for implementado depois, deve ser tratado como sinal operacional curto,
  possivelmente com TTL/cache ou trilha leve de auditoria;
- nunca deve criar `whatsapp_messages`.

### 7. Reacoes, notificacoes e callbacks de sistema

Regra alvo:

- `reaction` em `ReceivedCallback` e um evento de interacao, nao uma mensagem de
  texto;
- notificacoes como `GROUP_PARTICIPANT_LEAVE` ou equivalentes sao eventos de
  sistema do grupo;
- ambos devem ficar auditados, mas nao devem entrar na pipeline de `event_media`.

Leitura operacional validada:

- ja houve falha real no backend por `reaction` vir como objeto/array;
- ja houve callback real de notificacao de saida de participante no grupo.

Decisao:

- criar classificacao propria de inbound nao-midiatico:
  - `message`
  - `reaction`
  - `group_notification`
  - `system`
- apenas `message` com midia elegivel segue para o intake do evento.

### 8. Webhook "Ao enviar"

Regra alvo:

- `DeliveryCallback` nao substitui `MessageStatusCallback`;
- se for implementado, deve alimentar reconciliacao de envio em endpoint
  proprio (`/sent`), sem conflitar com `/delivery`;
- como o envio atual ja recebe ids na resposta de `send-text` e similares, esse
  callback fica fora do MVP.

## Lifecycle alvo de feedback no WhatsApp

Esta secao consolida a automacao desejada para as midias recebidas pela Z-API,
tanto em grupo vinculado quanto em conversa privada com sessao ativa.

### Objetivo do feedback

O usuario precisa perceber 3 estados distintos no proprio WhatsApp:

1. a mensagem foi reconhecida pelo sistema
2. a midia entrou no processamento do evento
3. a midia foi publicada com sucesso

No desenho pedido para esta fase, isso vira:

- reacao de relogio quando a midia elegivel e identificada
- reacao de coracao quando a midia foi publicada no evento
- futuramente, resposta textual por IA em cima da mesma mensagem

### Papel central do `messageId`

O `messageId` do callback inbound e um identificador operacional critico.

Ele precisa ser preservado porque:

1. a Z-API exige `messageId` para `send-reaction`
2. a resposta contextual de texto via `reply-message` tambem depende do
   `messageId` original
3. ele e a ancora de idempotencia da automacao por fase
4. ele conecta o `WhatsAppMessage` tecnico ao `InboundMessage` canonico e ao
   `EventMedia` publicado

Decisao de arquitetura:

- `provider_message_id` do inbound nao pode ser tratado apenas como dedupe;
- ele deve ser promovido a chave de correlacao da automacao.

### Contrato oficial relevante da Z-API

Conforme a documentacao oficial:

- `send-reaction` exige:
  - `phone`
  - `reaction`
  - `messageId`
- `reply-message` usa `send-text` com `messageId` para relacionar a resposta a
  uma mensagem original do chat

Leitura de produto:

- reacao e reply contextual usam a mesma ancora: o `messageId` da mensagem
  recebida;
- por isso o webhook inbound precisa persistir esse id antes de qualquer outra
  etapa assicrona.

### Resolucao correta do alvo da reacao e do reply

Regra alvo:

- para grupo:
  - `phone` do outbound deve ser o `chat_external_id` / `group_external_id`
  - nunca `participantPhone`
- para conversa privada:
  - `phone` do outbound deve ser o `chat_external_id` do chat privado
- para reply:
  - `messageId` deve ser o `provider_message_id` da mensagem inbound original

Observacao importante:

- a doc da Z-API diz que o campo `phone` aceita telefone ou ID do grupo;
- nos callbacks reais, o grupo chega como `120363...-group`;
- portanto devemos reutilizar o identificador raw do chat/grupo que veio do
  provider, sem tentar "converter" isso para E.164.

### Fases da automacao

#### Fase A - Reconhecimento

Momento:

- logo depois que o webhook foi validado, a instancia foi resolvida e a
  mensagem foi considerada elegivel para intake do evento

Gatilho:

- grupo vinculado ativo + midia valida + `fromMe=false`
- ou DM com sessao ativa + midia valida + `fromMe=false`

Acao:

- enfileirar `SendReaction` com emoji de relogio

Objetivo:

- sinalizar rapidamente para o usuario que a midia foi identificada

#### Fase B - Publicacao

Momento:

- somente quando a midia realmente foi ao ar

Gatilho:

- evento de dominio `MediaPublished`

Acao:

- enviar reacao de coracao na mesma mensagem original

Objetivo:

- sinalizar conclusao do ciclo de publish, nao apenas recebimento tecnico

Decisao:

- o coracao deve disparar em `MediaPublished`, nao apenas em
  `moderation_status=approved`
- se houver moderacao manual, o relogio pode durar ate a publicacao real

#### Fase C - Reply por IA

Momento:

- milestone posterior

Gatilho recomendado:

- imagem lida pela camada de IA
- midia segura/moderada para receber resposta
- politicas do evento permitirem reply automatico

Acao:

- enviar texto usando `reply-message`/`send-text` com `messageId` da mensagem
  inbound original

Objetivo:

- responder em cima da propria mensagem com um texto curto derivado do prompt,
  da descricao da imagem e do contexto do evento

### Regras de elegibilidade do feedback

Deve reagir:

- midia inbound da Z-API vinculada a evento ativo
- grupo pre-cadastrado ao evento
- conversa privada com sessao ativa por codigo

Nao deve reagir por padrao:

- `fromMe=true`
- callbacks de `reaction`
- callbacks com `notification=*`
- texto puro sem sessao/sem binding
- delivery/status/presence

### Regras de idempotencia

Sem idempotencia forte, retries de fila vao duplicar reacoes.

Chave recomendada por fase:

- `instance_id + inbound_provider_message_id + feedback_kind + feedback_phase`

Exemplos:

- `zapi-reaction-detected`
- `zapi-reaction-published`
- `zapi-reply-ai-description`

### Estado minimo que precisa ser persistido

No modelo canonico, cada midia inbound que pode receber automacao deve guardar:

- `inbound_provider_message_id`
- `chat_external_id`
- `group_external_id` quando existir
- `sender_external_id`
- `sender_phone_e164`
- `sender_name`
- `sender_avatar_url`
- `from_me`
- `event_id`
- `ingestion_context`
- `processing_feedback_state`
- `publish_feedback_state`
- `last_feedback_error`

Motivo:

- permite retentativa controlada
- permite saber se o relogio ja foi enviado
- permite saber se o coracao ja foi enviado
- permite reply posterior pela IA

### Gap do codigo atual para este lifecycle

Hoje o repositorio ja tem parte da infraestrutura, mas ainda nao fecha o ciclo:

1. existe suporte tecnico a `sendReaction` no provider, client, service e job
2. `WhatsAppMessage` outbound ja guarda `reply_to_provider_message_id`
3. existe `SendAutoReactionJob`

Mas ainda faltam pontos criticos:

1. `SendAutoReactionJob` nao esta ligado por listener no `WhatsAppServiceProvider`
2. o job atual usa `sender_phone` como destino prioritario, o que e errado para
   grupo; o alvo deve ser o `chat_external_id`
3. a automacao atual depende apenas do metadata do binding, nao do estado real
   do intake/publicacao
4. nao existe automacao para DM com sessao ativa
5. nao existe fase dupla `detected -> published`
6. nao existe idempotencia por fase
7. o modulo ainda nao suporta reply textual contextual como parte da automacao

### Sequencia recomendada de implementacao

1. preservar `messageId` inbound como ancora canonica do intake
2. corrigir o alvo do outbound de reacao para usar `chat_external_id`
3. criar job de reacao de reconhecimento apos resolucao do contexto do evento
4. criar job de reacao de publicacao consumindo `MediaPublished`
5. guardar estado de feedback para evitar duplicidade
6. depois adicionar reply textual por IA usando `messageId`

## Gaps validados no codigo atual

### Gap 1 - Classificacao de evento errada

Hoje o backend classifica callbacks olhando `isset($payload['status'])`.

Impacto:

- `ReceivedCallback` cai como `event_type=status`;
- `MessageStatusCallback` cai no mesmo fluxo que mensagem recebida;
- a auditoria perde precisao.

### Gap 2 - `/delivery` esta gerando mensagem inbound `system`

Ja houve callback real de `MessageStatusCallback` gravado como:

- `direction=inbound`
- `type=system`
- `sender_phone=status@broadcast`

Impacto:

- polui `whatsapp_messages`;
- atrapalha analytics, automacao e leitura operacional.

### Gap 3 - Remetente de grupo esta sendo resolvido errado

Hoje o normalizador prefere `phone`.

Impacto:

- em grupo, `sender_phone` fica com o id do grupo;
- o autor real do conteudo some;
- nao fechamos o requisito de "quem enviou a foto".

### Gap 4 - `fromMe`, `participantPhone`, `participantLid` e `connectedPhone`
nao entram no DTO interno

Impacto:

- nao ha distincao segura entre participante, grupo e numero conectado;
- intake pode capturar midia errada;
- a sessao privada por codigo nao tem uma identidade robusta para se apoiar.

### Gap 4.1 - `senderPhoto` nao entra no modelo interno

Impacto:

- o sistema nao consegue guardar avatar do autor;
- perde contexto util para CRM, auditoria e experiencias futuras de UI.

### Gap 5 - Caption e timestamp da Z-API nao estao normalizados corretamente

Impacto:

- legenda de foto/video pode se perder no fluxo;
- `momment` do provider nao esta sendo respeitado de forma correta;
- ordenacao e auditoria podem usar `now()` em vez do tempo real do callback.

### Gap 5.1 - `reaction` e notificacoes de sistema nao tem parser proprio

Impacto:

- callback valido pode quebrar o job;
- payload operacional entra como erro em vez de auditoria controlada;
- o intake fica fragil a outros formatos reais da Z-API.

### Gap 6 - Ausencia de cobertura automatizada para webhooks da Z-API

Impacto:

- a suite atual do modulo `WhatsApp` passa, mas nao protege os cenarios que
  estao falhando no webhook real;
- qualquer refactor arrisca reintroduzir o bug de classificacao.

## Regra de TTD obrigatoria

Neste plano, nenhuma task de codigo fecha sem:

1. teste novo ou expandido antes da implementacao;
2. falha inicial pela razao certa;
3. implementacao minima para passar;
4. suite alvo verde;
5. regressao do modulo `WhatsApp` verde;
6. doc atualizada se o contrato mudar.

Checklist padrao:

- [ ] existe teste para o callback especifico da Z-API
- [ ] existe teste de classificacao por `payload.type`
- [ ] existe teste para `fromMe`
- [ ] existe teste para grupo com `participantPhone`
- [ ] existe teste para midia com legenda
- [ ] existe teste para `senderPhoto`
- [ ] existe teste para `MessageStatusCallback` sem criar inbound message
- [ ] existe teste para `ConnectedCallback` e `DisconnectedCallback`
- [ ] existe teste para callback de `reaction` sem exception
- [ ] existe teste para notificacao de grupo sem criacao de `event_media`
- [ ] existe teste de regressao do roteamento para binding de grupo
- [ ] existe teste do fluxo privado por codigo quando essa milestone entrar

## M0 - Fonte unica e topologia alvo

Objetivo:

- travar sem ambiguidade o mapa entre painel Z-API, callback oficial e papel no
  Evento Vivo.

### TASK M0-T1 - Congelar a topologia alvo dos endpoints

Estado atual:

- o modulo expoe hoje apenas `inbound`, `status` e `delivery`;
- `Presenca do chat` e `Ao enviar` ainda nao tem endpoints dedicados.

Subtasks:

1. documentar o mapa oficial painel -> callback -> endpoint;
2. confirmar que:
   - `Ao receber` usa `/inbound`
   - `Ao conectar` e `Ao desconectar` usam `/status`
   - `Receber status da mensagem` usa `/delivery`
3. deixar `Presenca do chat` e `Ao enviar` fora do painel na fase 1;
4. registrar endpoints futuros opcionais:
   - `/presence`
   - `/sent`

Criterio de aceite:

- qualquer pessoa do time consegue configurar o painel da Z-API sem misturar
  webhook de mensagem com webhook de lifecycle.

Arquivos e areas provaveis:

- `docs/api/local-webhooks-windows.md`
- `apps/api/app/Modules/WhatsApp/routes/api.php`
- `apps/api/app/Modules/WhatsApp/README.md`

### TASK M0-T2 - Congelar a regra de identidade do remetente

Estado atual:

- o sistema nao diferencia com seguranca autor do grupo vs chat do grupo.

Subtasks:

1. travar o conceito de `chat_external_id`;
2. travar o conceito de `sender_external_id`;
3. travar o conceito de `sender_phone_e164`;
4. decidir a regra de fallback para `@lid`, `@newsletter`, `status@broadcast`;
5. travar a regra de `fromMe=true` como nao elegivel ao intake automatico por
   padrao.

Criterio de aceite:

- o plano deixa explicito quem e o autor real em grupo, privado e callback
  operacional.

Arquivos e areas provaveis:

- `docs/architecture/event-media-intake-architecture.md`
- `apps/api/app/Modules/WhatsApp/Clients/DTOs/NormalizedInboundMessageData.php`
- `apps/api/app/Modules/WhatsApp/Clients/Providers/ZApi/ZApiWebhookNormalizer.php`

## M1 - Normalizacao correta dos callbacks da Z-API

Objetivo:

- sair da heuristica atual e passar a normalizar por contrato oficial.

### TASK M1-T1 - Criar classificador canonico de callback

Estado atual:

- a decisao de tipo usa `status` e `ack`, o que esta errado para a Z-API.

Subtasks:

1. classificar primeiro por `payload.type`;
2. usar `_webhook_type` apenas como pista complementar, nao como verdade final;
3. mapear explicitamente:
   - `ReceivedCallback`
   - `MessageStatusCallback`
   - `ConnectedCallback`
   - `DisconnectedCallback`
   - `PresenceChatCallback`
   - `DeliveryCallback`
4. criar tratamento de `unknown_callback_type` com auditoria e `ignored`.

Criterio de aceite:

- nenhum `ReceivedCallback` e salvo como `event_type=status`;
- nenhum `MessageStatusCallback` e roteado como mensagem inbound.

Arquivos e areas provaveis:

- `apps/api/app/Modules/WhatsApp/Jobs/ProcessInboundWebhookJob.php`
- `apps/api/app/Modules/WhatsApp/Clients/Providers/ZApi/ZApiWebhookNormalizer.php`

### TASK M1-T2 - Expandir o DTO interno de inbound

Estado atual:

- o DTO atual nao comporta os campos necessarios para grupo e lifecycle.

Subtasks:

1. adicionar ao DTO:
   - `callbackType`
   - `fromMe`
   - `senderExternalId`
   - `senderPhoneE164`
   - `participantPhone`
   - `participantLid`
   - `connectedPhone`
   - `providerStatus`
   - `providerOccurredAt`
2. decidir se `lastProviderStatus` vive no DTO ou em metadata separada;
3. manter `rawPayload` integral.

Criterio de aceite:

- o DTO suporta corretamente grupo, privado, delivery e lifecycle sem
  ambiguidade.

Arquivos e areas provaveis:

- `apps/api/app/Modules/WhatsApp/Clients/DTOs/NormalizedInboundMessageData.php`
- `apps/api/app/Modules/WhatsApp/Clients/Providers/ZApi/ZApiWebhookNormalizer.php`

### TASK M1-T3 - Corrigir extracao de campos da Z-API

Estado atual:

- ha leitura errada ou incompleta de caption, timestamp e identidade.

Subtasks:

1. corrigir `senderPhone` para usar:
   - privado: `phone` ou `senderLid`
   - grupo: `participantPhone` ou `participantLid`
2. corrigir `senderName` para priorizar `senderName`;
3. corrigir `caption` para ler:
   - `image.caption`
   - `video.caption`
   - equivalentes por tipo
4. corrigir `mediaUrl` por tipo oficial;
5. corrigir `occurredAt` para ler `momment`;
6. preservar `connectedPhone` e `fromMe`.

Criterio de aceite:

- imagem com legenda real da Z-API sai normalizada com legenda;
- grupo sai com participante correto;
- `momment` do provider e respeitado.

Arquivos e areas provaveis:

- `apps/api/app/Modules/WhatsApp/Clients/Providers/ZApi/ZApiWebhookNormalizer.php`

## M2 - Separacao do processamento por classe de webhook

Objetivo:

- parar de jogar todos os callbacks no mesmo fluxo de mensagem inbound.

### TASK M2-T1 - Separar `ReceivedCallback` do resto

Estado atual:

- `ProcessInboundWebhookJob` tenta dar conta de tudo.

Subtasks:

1. manter `inbound` dedicado apenas a mensagem recebida;
2. permitir persistencia de `whatsapp_messages` apenas para callbacks de
   mensagem;
3. marcar callbacks nao-mensagem como `ignored` no roteador de inbound;
4. preservar trilha bruta em `whatsapp_inbound_events`.

Criterio de aceite:

- somente callbacks de mensagem criam `whatsapp_messages`.

### TASK M2-T2 - Criar processador dedicado de `MessageStatusCallback`

Estado atual:

- `/delivery` gera mensagem inbound `system`.

Subtasks:

1. criar action/job dedicado para status de mensagem;
2. resolver outbound por `provider_message_id` em `ids[]`;
3. mapear:
   - `SENT`
   - `RECEIVED`
   - `READ`
   - `READ_BY_ME`
   - `PLAYED`
4. registrar callbacks sem match como auditoria, nao como inbound;
5. decidir armazenamento do ultimo status raw.

Criterio de aceite:

- `/delivery` nao cria `whatsapp_messages` inbound;
- mensagens outbound passam a refletir lifecycle real do provider.

Arquivos e areas provaveis:

- `apps/api/app/Modules/WhatsApp/Jobs/ProcessInboundWebhookJob.php`
- `apps/api/app/Modules/WhatsApp/Models/WhatsAppMessage.php`
- `apps/api/app/Modules/WhatsApp/Enums/MessageStatus.php`

### TASK M2-T3 - Criar processador dedicado de lifecycle da instancia

Estado atual:

- `/status` ainda nao tem consumidor especializado.

Subtasks:

1. criar action/job para `ConnectedCallback` e `DisconnectedCallback`;
2. atualizar `whatsapp_instances.status`;
3. atualizar `connected_at`, `disconnected_at`, `last_health_status` e erro;
4. emitir evento interno quando a instancia mudar de estado;
5. manter polling de `SyncInstanceStatusJob` apenas como reconciliacao, nao
   como unica fonte de verdade.

Criterio de aceite:

- callback de conectar/desconectar atualiza a instancia imediatamente.

Arquivos e areas provaveis:

- `apps/api/app/Modules/WhatsApp/Models/WhatsAppInstance.php`
- `apps/api/app/Modules/WhatsApp/Jobs/SyncInstanceStatusJob.php`
- `apps/api/app/Modules/WhatsApp/Events/WhatsAppInstanceStatusChanged.php`

## M3 - Persistencia e schema alinhados com a logica de negocio

Objetivo:

- guardar o que o produto precisa sem esmagar identidade, status e autoria.

### TASK M3-T1 - Expandir schema de mensagens e auditoria

Estado atual:

- `whatsapp_messages` so tem `sender_phone`;
- `whatsapp_inbound_events` nao distingue callback type com clareza suficiente.

Subtasks:

1. avaliar adicionar em `whatsapp_messages`:
   - `sender_external_id`
   - `sender_phone_e164`
   - `sender_name`
   - `from_me`
   - `participant_phone`
   - `participant_lid`
   - `delivered_at`
   - `read_at`
   - `played_at`
   - `last_provider_status`
2. avaliar adicionar em `whatsapp_inbound_events`:
   - `callback_type`
   - `chat_external_id`
   - `sender_external_id`
3. usar `metadata_json` de `whatsapp_chats` para armazenar sinais complementares
   quando nao justificarem coluna propria.

Criterio de aceite:

- o banco passa a representar de forma auditavel quem falou, em que contexto,
  e qual callback foi recebido.

Arquivos e areas provaveis:

- `apps/api/database/migrations/*`
- `apps/api/app/Modules/WhatsApp/Models/WhatsAppMessage.php`
- `apps/api/app/Modules/WhatsApp/Models/WhatsAppInboundEvent.php`
- `apps/api/app/Modules/WhatsApp/Models/WhatsAppChat.php`

### TASK M3-T2 - Preservar identidade raw e identidade normalizada

Estado atual:

- o sistema mistura ids de provider e telefone humano no mesmo campo.

Subtasks:

1. manter a identidade raw que veio do provider;
2. normalizar para E.164 apenas quando o valor for realmente numero;
3. nunca converter `@lid`, `@newsletter`, `status@broadcast` ou `-group` para
   pseudo-telefone;
4. garantir que analytics e intake usem a identidade certa para cada caso.

Criterio de aceite:

- nenhuma automacao depende de assumir que `phone` sempre e um telefone humano.

## M4 - Regras de roteamento para evento

Objetivo:

- ligar webhook ao contexto de evento certo, sem intake indevido.

### TASK M4-T1 - Fechar fluxo de grupo vinculado

Estado atual:

- ha binding, ha listener, mas a ponte ainda e parcial e depende do binding
  existir;
- nesta base local nao ha bindings ativos.

Subtasks:

1. resolver binding por `instance_id + group_external_id`;
2. aceitar intake apenas quando:
   - binding ativo existir
   - tipo de binding for `event_gallery`
   - callback for mensagem real
   - houver midia
   - `fromMe=false` por padrao
3. carregar autor real do grupo para o payload canonico;
4. encaminhar caption e metadata da midia para a pipeline de evento.

Criterio de aceite:

- grupo autorizado com midia vira intake do evento correto;
- grupo sem binding nao vira `event_media`.

Arquivos e areas provaveis:

- `apps/api/app/Modules/WhatsApp/Services/WhatsAppInboundRouter.php`
- `apps/api/app/Modules/WhatsApp/Listeners/RouteInboundToMediaPipeline.php`
- `apps/api/app/Modules/WhatsApp/Models/WhatsAppGroupBinding.php`

### TASK M4-T2 - Fechar fluxo privado por codigo

Estado atual:

- documentado como necessario, mas ainda inexistente em codigo.

Subtasks:

1. desenhar codigo de intake de evento;
2. criar sessao privada por telefone/identidade raw;
3. criar parser de `#CODIGO`;
4. confirmar ativacao via outbound;
5. encaminhar midias privadas subsequentes para o evento enquanto a sessao
   estiver ativa;
6. definir expiracao e renovacao da sessao.

Criterio de aceite:

- conversa privada sem grupo consegue ativar intake de evento por codigo.

Arquivos e areas provaveis:

- novo modulo ou subdominio em `WhatsApp`/`InboundMedia`
- `docs/architecture/event-media-intake-architecture.md`

## M5 - Seguranca, operacao e observabilidade

Objetivo:

- endurecer o recebimento real sem inventar contrato que a Z-API nao documenta.

### TASK M5-T1 - Endurecer autenticidade sem depender de header nao documentado

Estado atual:

- o controller comenta sobre `webhook_secret`, mas isso nao esta implementado;
- a doc oficial usada nesta rodada nao documenta header de autenticacao nos
  callbacks.

Subtasks:

1. remover ambiguidade da doc interna sobre autenticacao de callback;
2. nao rejeitar callback com base em header nao documentado pela Z-API;
3. usar HTTPS e hostname fixo como baseline obrigatoria;
4. capturar headers reais quando surgirem evidencias concretas de mecanismo
   autenticado suportado;
5. avaliar estrategia adicional de protecao sem quebrar compatibilidade.

Criterio de aceite:

- a seguranca fica explicita e baseada em contrato real, nao em suposicao.

### TASK M5-T2 - Melhorar logs e telemetria

Subtasks:

1. logar callback type, route, instance, group/private, fromMe e match de
   binding;
2. separar erro de classificacao de erro de roteamento;
3. criar visao operacional para:
   - callbacks recebidos
   - callbacks ignorados
   - callbacks falhos
   - callbacks sem match de instancia
   - callbacks de delivery sem match de mensagem
4. medir backlog das filas `whatsapp-inbound` e correlatas.

Criterio de aceite:

- o time consegue explicar por que um callback foi aceito, ignorado ou falhou
  sem precisar abrir payload manualmente toda vez.

## M6 - Matriz de testes automatizados

Objetivo:

- fechar a cobertura que hoje nao existe para os webhooks da Z-API.

### TASK M6-T1 - Cobrir `ReceivedCallback`

Subtasks:

1. teste de texto privado;
2. teste de imagem privada com legenda;
3. teste de grupo com `participantPhone` e `senderName`;
4. teste de `fromMe=true`;
5. teste de newsletter/lid sem normalizacao indevida;
6. teste de deduplicacao por `provider_message_id`.
7. teste de grupo com `senderPhoto`;
8. teste de callback de reacao sem exception;
9. teste de notificacao de grupo sem criacao de `event_media`.

### TASK M6-T2 - Cobrir `MessageStatusCallback`

Subtasks:

1. teste de `SENT`;
2. teste de `RECEIVED`;
3. teste de `READ`;
4. teste de `READ_BY_ME`;
5. teste de `PLAYED`;
6. teste garantindo que nao cria `whatsapp_messages` inbound.

### TASK M6-T3 - Cobrir lifecycle da instancia

Subtasks:

1. teste de `ConnectedCallback`;
2. teste de `DisconnectedCallback`;
3. teste de transicao de estado e timestamps;
4. teste de callback desconhecido sendo auditado e ignorado.

### TASK M6-T4 - Cobrir roteamento de evento

Subtasks:

1. grupo vinculado com midia;
2. grupo sem binding;
3. grupo vinculado com `fromMe=true`;
4. fluxo privado por codigo quando a milestone correspondente entrar.
5. grupo vinculado usando `group_external_id = phone` do grupo.

Criterio de aceite global de M6:

- existe cobertura automatizada para todos os callbacks de fase 1;
- os bugs reais desta rodada passam a ter regressao permanente.

## M7 - Homologacao real com a Z-API

Objetivo:

- validar em ambiente real o que a suite automatizada disser que esta pronto.

### TASK M7-T1 - Fechar roteiro de homologacao por callback

Subtasks:

1. `ReceivedCallback`:
   - texto privado
   - imagem privada com legenda
   - grupo com participante externo
   - grupo com mensagem propria (`fromMe=true`)
2. `MessageStatusCallback`:
   - `SENT`
   - `RECEIVED`
   - `READ`
   - `READ_BY_ME`
   - `PLAYED`
3. `ConnectedCallback` e `DisconnectedCallback`
4. opcional:
   - `PresenceChatCallback`
   - `DeliveryCallback`

### TASK M7-T2 - Consolidar evidencias operacionais

Subtasks:

1. salvar ids reais de callback e mensagem;
2. salvar amostras anonimizadas dos payloads;
3. validar transicoes de banco e logs;
4. atualizar esta doc e a doc de arquitetura quando houver divergencia entre
   teoria e provider real.

## Fase 1 x fora de escopo

### Deve entrar na fase 1

- classificacao correta de callback;
- `ReceivedCallback` funcionando com grupo, privado, midia e legenda;
- `MessageStatusCallback` atualizando outbound;
- `ConnectedCallback` e `DisconnectedCallback` atualizando a instancia;
- identidade correta do autor em grupo;
- regra de `fromMe`;
- cobertura automatizada dos webhooks da Z-API;
- documentacao operacional do painel.

### Pode ficar fora da fase 1

- endpoint dedicado de `PresenceChatCallback`;
- endpoint dedicado de `DeliveryCallback` ("Ao enviar");
- automacao rica de Nany em cima da foto;
- reply contextual automatico no grupo;
- features avancadas de atendimento baseadas em presenca.

### Nao deve ser esquecido, mas e milestone posterior

- sessao privada por codigo;
- intake privado para evento sem grupo;
- consolidacao canonica completa `WhatsApp -> InboundMedia -> EventMedia`.

## Criterio de aceite global

Este plano pode ser considerado fechado quando:

- o painel da Z-API estiver configurado de forma semanticamente correta;
- `ReceivedCallback`, `MessageStatusCallback`, `ConnectedCallback` e
  `DisconnectedCallback` tiverem processadores dedicados ou claramente
  separados por contrato;
- `whatsapp_messages` deixar de receber callbacks operacionais como se fossem
  mensagens inbound;
- grupo passar a persistir autor real com `participantPhone`/`senderName`;
- a suite automatizada proteger os cenarios reais ja vistos em log;
- a doc do modulo e as docs operacionais refletirem a topologia final sem
  contradicoes.
