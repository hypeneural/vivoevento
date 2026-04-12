# WhatsApp Z-API Webhook Execution Plan

> Backlog integrado: a ordem oficial de execucao entre Billing,
> Entitlements, CRUD do evento e WhatsApp agora esta em
> [event-whatsapp-commercial-execution-plan.md](./event-whatsapp-commercial-execution-plan.md).

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
   - o vinculo pode nascer no painel ou por comando dentro do proprio grupo;
   - para o webhook existir, o telefone da instancia precisa estar dentro do grupo;
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
- mensagens de remetente bloqueado na blacklist do evento

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

## Modelo alvo de configuracao de intake no CRUD do evento

Esta secao fecha como o produto deve expor os canais de recebimento no
`events/create` e no `events/{id}/edit`, sem espalhar configuracao entre telas
de instancias, bindings manuais e docs operacionais.

### Principio de produto

O evento deve ser a fonte unica de verdade para "por onde este evento recebe
midias".

Em termos praticos:

- o usuario nao deveria pensar em "configurar webhook";
- o usuario deveria pensar em "ativar canais de recebimento do evento";
- um evento pode ter mais de um canal ativo ao mesmo tempo;
- todos os canais precisam convergir para a mesma camada canonica de intake;
- o webhook da Z-API e um detalhe de infraestrutura, nao de UX.

### Regra de UX mais pratica para `events/create`

O fluxo mais pratico para o usuario nao e tentar resolver tudo antes do evento
existir no banco.

Fluxo recomendado:

1. no `events/create`, o usuario salva primeiro o nucleo do evento;
2. logo apos criar, o fluxo redireciona para o editor completo do evento;
3. no editor, a secao `Canais de recebimento` fica desbloqueada com:
   - evento persistido
   - `upload_slug` ja gerado
   - links publicos ja disponiveis
   - codigo de intake por WhatsApp ja gerado
   - bindings de grupo e configuracoes ja podendo ser salvos sem gambiarra

Motivo:

- grupos vinculados, codigo privado e estados de canal dependem do `event_id`;
- isso evita criar regras provisórias demais no payload de create;
- a UI fica mais simples e robusta.

Decisao:

- no `events/create`, mostrar um card resumido de `Recebimento de midias`;
- apos o primeiro save, o usuario completa a configuracao detalhada no proprio
  `EventEditorPage`.

### Estrutura alvo da secao `Canais de recebimento`

No frontend, a tela do evento deve ganhar uma secao nova chamada
`Canais de recebimento`.

Ela precisa listar, no minimo, 4 cards de canal:

1. `WhatsApp grupos`
2. `WhatsApp direto`
3. `Link de upload`
4. `Telegram` ou outro canal futuro como `coming soon`

Regra de UX:

- cada card tem `enabled`, `status`, resumo operacional e CTA claro;
- o usuario enxerga no mesmo lugar todos os caminhos por onde o evento pode
  receber midia;
- o evento pode ativar grupos + WhatsApp direto + upload simultaneamente.

### Card 1 - `WhatsApp grupos`

Deve expor:

- toggle `Ativar recebimento por grupos`
- seletor de instancia WhatsApp
- lista multi-select de grupos vinculados
- resumo do numero de grupos ativos
- opcao de pausar/desativar binding sem excluir o historico
- lista de grupos vinculados com acao clara de `Desvincular`
- configuracoes operacionais por grupo, como:
  - `is_active`
  - `binding_type = event_gallery`
  - feedback automatico habilitado
  - legenda aceita
  - tipos de midia aceitos

Fonte dos grupos na UI, em ordem de praticidade:

1. `grupos observados recentemente` a partir de `whatsapp_chats` da instancia
   com `is_group=true`
2. bindings ja existentes em `whatsapp_group_bindings`
3. entrada manual de `group_external_id` + nome amigavel quando o grupo ainda
   nao apareceu no catalogo interno
4. sincronizacao ativa com provider, quando o provider suportar listagem de
   grupos de forma confiavel

Decisao pratica importante:

- como hoje o provider Z-API neste repositorio ainda nao implementa listagem de
  grupos, o plano nao deve bloquear a entrega nisso;
- a primeira versao do CRUD deve funcionar com:
  - grupos ja descobertos por webhook
  - ou cadastro manual do `group_external_id`

#### Autovinculo por comando dentro do grupo

Essa e a regra nova mais pratica para operacao real do contratante.

Fluxo alvo:

1. o contratante cria o grupo no proprio celular;
2. adiciona o telefone da instancia do Evento Vivo ao grupo;
3. no proprio grupo, envia um comando de ativacao;
4. o webhook chega com:
   - `phone` do grupo
   - `participantPhone` / `participantLid`
   - `senderName`
5. o sistema resolve o evento pelo codigo do comando;
6. cria ou reativa o binding do `group_external_id` para aquele evento;
7. confirma o vinculo no proprio grupo ou por reacao/resposta curta;
8. a partir dai, as midias do grupo passam a ser elegiveis ao evento.

Formato recomendado do comando:

```text
#ATIVAR#CODIGO_DO_GRUPO
```

Recomendacao de produto:

- separar `group_bind_code` de `media_inbox_code`;
- o codigo de grupo fica exposto dentro do chat coletivo;
- o codigo de DM abre sessao privada e nao deve ser o mesmo segredo do grupo.

Decisao recomendada:

- `group_bind_code`: usado so para vincular grupo ao evento
- `media_inbox_code`: usado so para abrir sessao privada em DM

Motivo:

- evita que qualquer membro do grupo reutilize o mesmo codigo em DM por engano
  ou abuso;
- permite rotacionar vinculo de grupo sem quebrar o intake privado;
- reduz mistura de intencoes no produto.

### Card 2 - `WhatsApp direto`

Esse canal representa intake privado por codigo.

Deve expor:

- toggle `Ativar recebimento direto no WhatsApp`
- instancia WhatsApp usada pelo evento
- codigo do evento para ativacao, por exemplo `#ANAEJOAO`
- botao para regenerar codigo
- TTL da sessao privada
- texto/roteiro pronto para o usuario copiar e divulgar
- estado operacional:
  - ativo
  - pausado
  - expiracao padrao
  - quantidade de sessoes ativas

Regra de negocio:

- o codigo identifica o evento;
- ele nao deve ser o `upload_slug` cru;
- ele nao deve reutilizar o `group_bind_code`;
- ele deve ser curto, legivel e voltado a conversa;
- depois que o usuario manda o codigo em privado, abre-se uma sessao temporaria
  de intake por `instance_id + sender_external_id`.

### Granularidade de instancia WhatsApp por evento

O produto precisa suportar 2 modos operacionais:

1. instancia compartilhada da organizacao
2. instancia dedicada para um evento especifico

Decisao recomendada:

- o evento deve ter um `default_whatsapp_instance_id` para intake via WhatsApp;
- cada canal WhatsApp do evento pode herdar essa instancia por padrao;
- se precisarmos no futuro, o canal pode ganhar `instance_id` proprio como
  override fino;
- a instancia sempre continua pertencendo a organizacao, nunca diretamente ao
  evento;
- o vinculo evento -> instancia e operacional, nao de ownership.

Motivo:

- hoje `whatsapp_instances` ja e organizacional;
- isso permite usar uma instancia compartilhada na maioria dos eventos;
- e tambem permite vender uma instancia dedicada como capacidade premium sem
  remodelar tudo depois.

Regras de negocio:

- uma instancia dedicada pode ser marcada como exclusiva para um evento;
- uma instancia compartilhada pode atender varios eventos, respeitando limites
  de pacote;
- o CRUD do evento precisa deixar claro qual instancia esta sendo usada e se ela
  e `shared` ou `dedicated`.

### Card 3 - `Link de upload`

Esse canal reaproveita a infraestrutura que ja existe no evento.

Deve expor:

- toggle `Ativar link de upload`
- URL publica de upload
- QR code
- botao de copiar link
- opcionalmente uma flag de origem visual:
  - `link`
  - `qr`

Decisao:

- o `upload_slug` atual continua sendo o identificador tecnico do canal;
- nao precisamos reinventar esse canal para a fase 1;
- so precisamos colocá-lo sob a mesma UX dos demais canais.

### Card 4 - Canais futuros

`Telegram`, `email drop`, `API inbound`, `kiosk upload` e similares devem
respeitar a mesma ideia:

- o evento ativa/desativa o canal no mesmo lugar;
- o canal ganha um `channel_type`;
- a camada canonica de intake continua unica.

Isso evita acoplamento do produto a WhatsApp apenas.

### Secao adicional - `Blacklist de remetentes`

O evento precisa ter uma blacklist propria para bloquear remetentes por
identidade WhatsApp.

Escopo minimo:

- bloquear por `phone` normalizado quando existir;
- bloquear por `@lid`/`participantLid`/`sender_external_id` quando nao houver
  telefone confiavel;
- bloquear tanto em grupo quanto em conversa privada.

Comportamento recomendado:

- a blacklist pertence ao evento, nao a instancia;
- a avaliacao acontece antes de:
  - abrir sessao privada
  - criar binding por comando no grupo
  - transformar midia em `event_media`
  - disparar feedback automatico
- grupos continuam vinculados ao evento mesmo que um participante especifico
  esteja bloqueado;
- o bloqueio recai sobre o autor da mensagem, nao sobre o grupo inteiro.

UX recomendada:

- tabela no editor do evento com:
  - identificador raw
  - tipo (`phone`, `lid`, `external_id`)
  - motivo
  - origem do cadastro
  - expiracao opcional
  - acoes de remover / reativar
- atalho para adicionar a blacklist a partir de remetentes recentes do evento.

### Contrato alvo de API para o evento

No backend, `StoreEventRequest` e `UpdateEventRequest` devem passar a aceitar um
bloco novo, por exemplo `intake_channels`.

Forma alvo:

```json
{
  "title": "Casamento Ana e Joao",
  "modules": {
    "live": true,
    "wall": true,
    "hub": true
  },
  "intake_channels": {
    "whatsapp_groups": {
      "enabled": true,
      "instance_id": 1,
      "groups": [
        {
          "group_external_id": "120363425796926861-group",
          "group_name": "Evento vivo 1",
          "is_active": true,
          "auto_feedback_enabled": true
        }
      ]
    },
    "whatsapp_direct": {
      "enabled": true,
      "instance_id": 1,
      "media_inbox_code": "ANAEJOAO",
      "session_ttl_minutes": 180
    },
    "public_upload": {
      "enabled": true
    },
    "telegram": {
      "enabled": false
    }
  },
  "intake_defaults": {
    "whatsapp_instance_id": 1,
    "whatsapp_instance_mode": "shared"
  }
}
```

Regras:

- `create` pode aceitar esse bloco, mas a UX deve tolerar salvar vazio e
  completar depois;
- `update` precisa suportar sync completo dos canais;
- a `show` do evento precisa devolver um resumo pronto para a UI editar sem
  montar estados paralelos.

### Persistencia alvo

O caminho mais pragmatico e separar registro canonico de canal de tabela
operacional especializada.

Camadas recomendadas:

1. `event_channels` como registro canonico do que o evento tem ativo
2. `whatsapp_group_bindings` como extensao operacional de grupos enquanto o
   fluxo legado ainda depender dessa tabela
3. nova tabela de sessoes para WhatsApp direto por codigo
4. novo estado de feedback por mensagem/evento
5. configuracao de instancia default do evento
6. limites e capacidades vindos de `current_entitlements_json`

Mapa recomendado:

| Conceito | Persistencia recomendada | Observacao |
| --- | --- | --- |
| Grupo vinculado | `event_channels` + `whatsapp_group_bindings` | uma linha canonica por grupo; binding operacional sincronizado |
| WhatsApp direto por codigo | `event_channels` + `event_media_intake_sessions` | sessao por remetente, com expiracao |
| Upload link | `event_channels` | `external_id = upload_slug` ou derivado dele |
| Telegram futuro | `event_channels` | provider especifico depois |
| Blacklist do evento | `event_media_sender_blacklists` | bloqueio por `phone`, `lid` ou `external_id` |
| Instancia default do evento | `events` ou config dedicada do evento | referencia operacional para os canais WhatsApp |

Decisao de modelagem:

- `event_channels` deve ser a verdade do CRUD do evento;
- `whatsapp_group_bindings` vira detalhe operacional compatibilizado por sync;
- o intake privado por codigo precisa de tabela propria de sessao, nao cabe em
  `event_channels` apenas.

### Limites por pacote e feature flags comerciais

A stack ja tem um lugar proprio para isso: `current_entitlements_json`, resolvido
por `EntitlementResolverService`.

Decisao recomendada:

- nao criar regra hardcoded de limite de canais dentro do modulo `WhatsApp`;
- os limites devem vir de features/entitlements do plano, grant ou pacote;
- o CRUD do evento e o resolvedor de intake apenas consomem esses limites.

Feature keys recomendadas:

- `channels.whatsapp_groups.enabled`
- `channels.whatsapp_groups.max`
- `channels.whatsapp_direct.enabled`
- `channels.public_upload.enabled`
- `channels.telegram.enabled`
- `channels.whatsapp.default_instance.required`
- `channels.whatsapp.dedicated_instance.enabled`
- `channels.whatsapp.dedicated_instance.max_per_event`
- `channels.whatsapp.shared_instance.enabled`
- `channels.blacklist.enabled`

Leitura de produto:

- plano X pode ter `channels.whatsapp_groups.max = 1`
- plano Y pode ter `channels.whatsapp_groups.max = 10` e
  `channels.whatsapp_direct.enabled = true`
- plano Z pode liberar grupos, DM, link, Telegram e instancia dedicada

### Caminho de super admin sem plano especifico

O sistema ja possui um motor apropriado para isso:

- `EventAccessGrant` com `source_type = bonus` ou `manual_override`
- `commercial_mode = bonus` ou `manual_override`
- snapshots persistidos no proprio grant

Melhor abordagem:

- quando o super admin quiser criar um evento free/bonus sem prender a um plano,
  usar grant `manual_override` sem pacote obrigatorio;
- o grant passa a carregar diretamente as capacidades do evento em
  `features_snapshot_json` e `limits_snapshot_json`;
- `EventCommercialStatusService` e `EntitlementResolverService` continuam sendo
  a fonte final do estado efetivo do evento.

Exemplos de override granular para esse cenario:

- grupos ilimitados ou `max = N`
- DM habilitado ou desabilitado
- link de upload habilitado
- Telegram habilitado no futuro
- instancia WhatsApp default compartilhada ou dedicada
- feedback de rejeicao habilitado com copia padrao
- blacklist habilitada

Decisao recomendada:

- pacote continua sendo otimo para presets comerciais;
- `manual_override` cobre excecao operacional e bonus customizado;
- o CRUD do evento deve exibir de onde veio a capacidade:
  - assinatura
  - pacote
  - bonus
  - manual override

Melhoria necessaria na stack:

- `EntitlementResolverService` hoje resolve `retention_days`, `max_active_events`
  e `max_photos`, mas ainda nao materializa limites/capacidades de canais;
- o plano precisa incluir a expansao desse resolvedor para um bloco
  `channels`/`channel_limits`;
- o frontend do evento precisa ler isso para travar a UX antes do save, e o
  backend precisa revalidar isso no request/action.

### Impacto concreto no front e no back

Frontend:

- `apps/web/src/modules/events/components/EventEditorPage.tsx`
- `apps/web/src/modules/events/types.ts`
- `apps/web/src/modules/events/services/events.service.ts`

Backend:

- `apps/api/app/Modules/Events/Http/Requests/StoreEventRequest.php`
- `apps/api/app/Modules/Events/Http/Requests/UpdateEventRequest.php`
- `apps/api/app/Modules/Events/Actions/CreateEventAction.php`
- `apps/api/app/Modules/Events/Actions/UpdateEventAction.php`
- `apps/api/app/Modules/Events/Http/Resources/EventDetailResource.php`
- novo action/service de sync dos canais de intake do evento

## Arquitetura de performance para processamento em segundos

Essa parte e obrigatoria porque a UX desejada depende de feedback rapido no
WhatsApp.

### Meta operacional

Objetivo de experiencia:

- webhook HTTP responde rapido e nunca espera download de midia, IA ou
  moderacao;
- a reacao de relogio deve sair em segundos, nao em minutos;
- a reacao de coracao so sai quando a publicacao realmente aconteceu.

Meta recomendada para producao:

- `ack` HTTP do webhook: sub-segundo
- classificacao + resolucao de contexto + enfileiramento de feedback inicial:
  ate poucos segundos
- feedback de publicacao: logo apos `MediaPublished`

### Fast lane e slow lane

O intake nao deve ser uma fila unica fazendo tudo.

#### Fast lane

Faz apenas:

1. validar e persistir payload bruto
2. classificar callback
3. resolver instancia e contexto do evento
4. decidir elegibilidade da midia
5. persistir ancora canonica (`provider_message_id`, autor, chat, event_id)
6. enfileirar reacao de relogio
7. enfileirar o restante do pipeline

#### Slow lane

Faz:

1. download da midia
2. criacao de `event_media`
3. variantes
4. moderacao
5. publicacao
6. reacao de coracao
7. reply por IA em milestone posterior

Decisao:

- a reacao de relogio nao deve esperar download nem moderacao;
- o webhook nao deve depender do pipeline pesado para "dar sinal de vida".

### Filas alvo

Fila recomendada por responsabilidade:

- `whatsapp-inbound` para classificacao e roteamento rapido
- `whatsapp-feedback` para reacoes e replies
- `media-download` para baixar arquivo
- `media-process` para variantes, IA e moderacao
- `media-publish` para publicacao

Se a operacao quiser menos filas, ainda assim precisa manter pelo menos a
separacao entre:

- intake/roteamento rapido
- feedback outbound
- pipeline pesado de midia

### O que precisa estar indexado ou em cache

Para resolver contexto em segundos, o lookup nao pode depender de scans caros.

Indices minimos:

- `whatsapp_group_bindings(instance_id, group_external_id, is_active)`
- `event_channels(event_id, channel_type, status)`
- sessoes DM por `(instance_id, sender_external_id, status, expires_at)`
- mensagens por `provider_message_id`

Cache recomendado em Redis:

- mapa `instance_id + group_external_id -> event_id`
- mapa `instance_id + sender_external_id -> session ativa`
- mapa `media_inbox_code -> event_id`

Regra:

- cache acelera lookup, mas o banco continua sendo a fonte de verdade.

### O que nao pode bloquear o feedback rapido

Nao pode bloquear a reacao de relogio:

- download da imagem
- processamento de legenda
- IA
- moderacao
- publicacao
- broadcast

Se algum desses passos estiver no mesmo job que decide a elegibilidade da
midia, a arquitetura esta errada para o SLA desejado.

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
4. verifica se a mensagem e um comando de ativacao de grupo;
5. se for comando `#ATIVAR#<group_bind_code>`:
   - valida que a instancia esta no grupo
   - resolve o evento pelo `group_bind_code`
   - valida que o evento esta ativo e apto a receber grupos
   - valida blacklist do autor
   - cria ou reativa binding para aquele `group_external_id`
   - registra auditoria de ativacao
   - responde confirmando ou negando o vinculo
6. se nao for comando de ativacao, procura binding ativo em
   `whatsapp_group_bindings`;
7. se nao houver binding:
   - persiste auditoria tecnica minima
   - desconsidera o webhook para o intake do evento
   - nao cria `event_media`
   - nao dispara feedback automatico
8. se houver binding e a mensagem tiver midia:
   - usa `participantPhone` e `senderName` como autor real
   - preserva `participantLid` e `senderPhoto`
   - preserva caption quando existir
   - consulta blacklist do evento pelo autor
   - se estiver bloqueado, audita e ignora o intake
   - valida que o evento continua ativo
    - encaminha para intake canonico do evento
9. se houver binding e a mensagem for apenas texto:
   - persiste como mensagem
   - nao cria `event_media`
   - deixa espaco para comandos e automacoes futuras
10. se o callback for notificacao de sistema do grupo:
    - persiste auditoria
    - nao cria `event_media`
    - nao tenta automacao de foto

Decisao recomendada:

- a ponte "grupo autorizado -> evento -> galeria" deve aceitar apenas
  `fromMe=false` por padrao;
- o autovinculo do grupo deve aceitar apenas mensagem de texto, sem depender de
  media/caption;
- o desvinculo do grupo deve acontecer no CRUD do evento, nao por comando, na
  fase 1;
- grupo nao vinculado e grupo com codigo invalido devem ser ignorados para o
  evento, mantendo apenas trilha tecnica/auditoria;
- reacoes e respostas automaticas so acontecem quando o evento estiver ativo e
  a mensagem for elegivel ao intake.

### 2. Conversa privada por codigo

Regra alvo:

1. `ReceivedCallback` chega em chat privado;
2. texto e analisado em busca de codigo valido do evento;
3. codigo resolve o evento;
4. sistema valida blacklist do autor;
5. sistema cria ou renova uma sessao de intake por telefone/identidade raw;
6. instancia responde confirmando ativacao em cima da mensagem original usando
   `messageId`;
7. midias seguintes do mesmo remetente entram no evento enquanto a sessao
   estiver ativa;
8. se o usuario digitar `sair`, a sessao e encerrada;
9. sem sessao ativa, midia privada nao vai para o evento.

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

Decisao recomendada:

- usar `media_inbox_code` proprio do evento para DM;
- aceitar sintaxe curta em privado, por exemplo `#CODIGO`;
- opcionalmente aceitar tambem `#ATIVAR#CODIGO` em DM para reduzir friccao de
  instrucoes.

Formato alvo que precisa ser suportado:

```text
*EventoVivo*
Quero enviar fotos para o telao!
> Codigo: AQUI_VAI_O_CODIGO
```

Resposta alvo inicial:

```text
✨ Vinculo realizado: {nome do evento}

📸 Agora voce pode enviar suas fotos e videos.
🚪 Para encerrar, digite Sair a qualquer momento.
```

Regra de envio:

- usar `send-text` com `messageId` da mensagem inbound para responder em thread;
- em DM, responder no proprio chat;
- em grupo, esse formato de resposta fica reservado ao comando de grupo quando
  quisermos confirmar o vinculo de forma textual.

Estado atual:

- este fluxo ainda nao existe no codigo atual;
- continua sendo requisito novo e deve ser tratado como milestone propria,
  nao como bug do webhook atual.

### 2.1. Blacklist de remetentes do evento

Regra alvo:

1. toda mensagem elegivel ao intake consulta a blacklist do evento antes de
   abrir sessao, vincular grupo ou criar `event_media`;
2. a blacklist deve aceitar:
   - `sender_phone_e164`
   - `participantPhone`
   - `sender_external_id`
   - `participantLid`
3. se houver match:
   - persistir auditoria
   - nao criar `event_media`
   - nao disparar reacao de relogio
   - opcionalmente responder com mensagem neutra em milestone posterior

Regra de match:

- primeiro por identidade raw (`sender_external_id` / `participantLid`);
- depois por telefone normalizado, quando existir;
- o match precisa funcionar tanto em grupo quanto em DM.

Comando operacional adicional:

- `sair` em conversa privada encerra a sessao ativa do remetente para aquele
  evento/instancia.

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
2. a midia foi aprovada e ficou disponivel no evento
3. a midia nao foi aprovada

No desenho pedido para esta fase, isso vira:

- reacao de relogio quando a midia elegivel entra na fila do evento
- reacao de coracao quando a midia foi moderada/aprovada e publicada
- reacao negativa quando a midia foi processada como nao aprovada
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

#### Fase B - Aprovacao/Publicacao

Momento:

- somente quando a midia realmente foi ao ar
- e somente se o evento estiver ativo

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

#### Fase C - Nao aprovado

Momento:

- quando a pipeline concluir que a midia nao foi aprovada

Gatilho:

- decisao final de moderacao em `rejected` ou equivalente

Acao:

- enviar reacao negativa na mensagem original
- enviar reply textual em cima da mensagem original com a copia padrao:
  `Sua midia nao segue as diretrizes do evento. 🛡️`

Objetivo:

- sinalizar de forma simples que a foto nao foi aprovada para o evento

Decisao:

- a fase negativa nao substitui auditoria nem motivo interno;
- a escolha exata do emoji deve ficar configuravel, mas a regra de produto fica
  travada como "feedback negativo quando nao aprovada".
- esse mesmo feedback negativo cobre:
  - bloqueio pela IA/moderacao automatica
  - bloqueio manual pelo gestor
  - remetente bloqueado na blacklist do evento quando o produto optar por
    responder em vez de ignorar silenciosamente
- o reply textual deve usar `send-text` com `messageId` da mensagem original.

#### Fase D - Reply por IA

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

Excecao de feedback negativo:

- quando a midia for rejeitada pela IA ou pelo gestor, ou quando a politica do
  evento mandar responder ao bloqueio do remetente, o sistema pode responder em
  thread com a copia de diretriz e a reacao negativa configurada.

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

Regra adicional obrigatoria:

- sempre que possivel, os testes da borda Z-API devem nascer a partir de
  payloads reais ja observados em log ou persistidos em
  `whatsapp_inbound_events`, anonimizados e transformados em fixtures de replay.

Fonte minima obrigatoria de fixtures reais nesta rodada:

- `apps/api/storage/logs/whatsapp-2026-04-05.log`
- payloads reais ja salvos em `whatsapp_inbound_events.payload_json`

Prioridade absoluta de replay:

1. imagem de grupo com `participantPhone`, `participantLid`, `senderPhoto` e
   `image.caption`
2. callback de sistema de grupo
3. callback de `reaction` que ja quebrou o normalizador
4. `MessageStatusCallback`
5. texto privado que servira de base para parser de codigo/sessao

Checklist padrao:

- [ ] existe teste para o callback especifico da Z-API
- [ ] existe teste de classificacao por `payload.type`
- [ ] existe teste para `fromMe`
- [ ] existe teste para grupo com `participantPhone`
- [ ] existe teste para midia com legenda
- [ ] existe teste para `senderPhoto`
- [ ] existe teste para `MessageStatusCallback` sem criar inbound message
- [ ] existe teste para `ConnectedCallback` e `DisconnectedCallback`
- [x] existe teste para callback de `reaction` sem exception
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

### TASK M3-T3 - Consolidar `event_channels` como registro canonico de intake

Estado atual:

- `event_channels` existe, mas ainda nao e a fonte unica de verdade para intake;
- grupos ainda vivem sobretudo em `whatsapp_group_bindings`;
- nao existe representacao canonica de `whatsapp_direct`.

Subtasks:

1. decidir os `channel_type` canonicos da fase 1:
   - `whatsapp_group`
   - `whatsapp_dm_code`
   - `public_upload_link`
2. definir o que vai em `external_id` e em `config_json` por canal;
3. decidir status operacional por canal:
   - `active`
   - `paused`
   - `disabled`
4. decidir onde vive o `default_whatsapp_instance_id` do evento;
5. sincronizar grupos com `whatsapp_group_bindings` sem quebrar fluxo legado;
6. preparar o contrato para canais futuros como `telegram_bot`.

Criterio de aceite:

- o evento passa a ter uma representacao unica e editavel de seus canais de
  recebimento.

Arquivos e areas provaveis:

- `apps/api/app/Modules/Channels/Enums/ChannelType.php`
- `apps/api/app/Modules/Channels/Models/EventChannel.php`
- `apps/api/database/migrations/*`

### TASK M3-T4 - Modelar sessao privada e estado de feedback

Estado atual:

- a sessao privada por codigo nao existe;
- o feedback `detected -> published` nao tem estado persistido.

Subtasks:

1. criar modelo/tabela para sessao de intake privado;
2. criar modelo/tabela ou estado equivalente para feedback por fase;
3. armazenar:
   - `event_id`
   - `instance_id`
   - `sender_external_id`
   - `provider_message_id`
   - `feedback_phase`
   - `status`
   - `expires_at` quando aplicavel
4. garantir idempotencia por fase;
5. permitir auditoria de erro de feedback sem duplicidade de envio.

Criterio de aceite:

- o sistema consegue saber se uma sessao privada esta ativa e se a reacao de
  relogio/coracao ja foi enviada para aquela ancora de mensagem.

### TASK M3-T5 - Modelar codigos de ativacao e blacklist do evento

Estado atual:

- nao existe diferenca formal entre codigo de grupo e codigo de DM;
- nao existe tabela de blacklist por remetente no evento.

Subtasks:

1. criar identificador proprio para `group_bind_code`;
2. criar identificador proprio para `media_inbox_code`;
3. definir politica de rotacao e unicidade dos dois codigos;
4. criar tabela de blacklist por evento contendo:
   - `event_id`
   - `identity_type`
   - `identity_value`
   - `normalized_phone`
   - `reason`
   - `expires_at`
   - `is_active`
5. indexar por `event_id + identity_type + identity_value`;
6. documentar prioridade de match entre raw id e telefone normalizado.

Criterio de aceite:

- o evento consegue diferenciar ativacao de grupo, ativacao de DM e bloqueio de
  remetente sem sobrecarregar um unico campo.

### TASK M3-T6 - Materializar limites e capacidades de canais nos entitlements

Estado atual:

- `EntitlementResolverService` so materializa alguns limites globais do evento;
- ainda nao existe bloco canonico de canais/capacidades em
  `current_entitlements_json`.

Subtasks:

1. definir o shape alvo em `resolved_entitlements`, por exemplo:
   - `channels.whatsapp_groups.enabled`
   - `channels.whatsapp_groups.max`
   - `channels.whatsapp_direct.enabled`
   - `channels.public_upload.enabled`
   - `channels.telegram.enabled`
   - `channels.whatsapp.dedicated_instance.enabled`
2. ensinar `EntitlementResolverService` a extrair esses valores de features do
   plano/pacote/grants;
3. garantir que `EventCommercialStatusService` persista esse bloco no evento;
4. suportar o caso de `manual_override` sem `package_id`, usando snapshots
   diretos do grant;
5. documentar o fallback quando a feature nao existir.

Criterio de aceite:

- o backend passa a expor, no proprio evento, as capacidades e limites de
  canais que o plano atual permite.

## M4 - Regras de roteamento para evento

Objetivo:

- ligar webhook ao contexto de evento certo e expor isso no CRUD do evento sem
  configuracao fragmentada.

### TASK M4-T1 - Fechar UX e API do CRUD do evento para canais de intake

Estado atual:

- `EventEditorPage` ainda nao tem secao de canais;
- `StoreEventRequest`, `UpdateEventRequest`, actions e resources nao conhecem
  `intake_channels`;
- a configuracao de grupos esta separada em rotas especificas do modulo
  `WhatsApp`.

Subtasks:

1. criar secao `Canais de recebimento` no editor do evento;
2. decidir comportamento do `create`:
   - salvar evento base primeiro
   - configurar canais no editor apos criacao
3. adicionar payload `intake_channels` ao contrato do evento;
4. fazer `show` do evento retornar resumo pronto para a UI;
5. adicionar card para:
   - `WhatsApp grupos`
   - `WhatsApp direto`
   - `Link de upload`
   - `Telegram` futuro
6. adicionar ao card de grupos:
   - instrucoes de autovinculo por `#ATIVAR#<group_bind_code>`
   - lista de grupos vinculados
   - acao de desvincular grupo
7. adicionar ao card de WhatsApp direto:
   - `media_inbox_code`
   - TTL da sessao
   - instrucoes de ativacao em DM
8. adicionar secao de blacklist do evento por `phone` e `@lid`
9. expor no editor a instancia WhatsApp default do evento, com modo
   `shared`/`dedicated` quando o entitlement permitir;
10. travar a UX por entitlement:
   - quantidade maxima de grupos
   - liberacao de DM
   - liberacao de upload
   - liberacao de Telegram
   - uso de instancia dedicada
11. expor no resumo comercial do evento quando a capacidade veio de:
   - assinatura
   - pacote
   - bonus
   - manual override
12. adicionar endpoint/consulta auxiliar para grupos candidatos por instancia,
   usando preferencialmente `whatsapp_chats` ja observados;
13. manter fallback de cadastro manual de `group_external_id`.

Criterio de aceite:

- um operador consegue configurar por onde o evento recebe midia sem sair da
  tela do evento;
- a UX suporta grupos, codigo WhatsApp direto e link de upload no mesmo lugar;
- a UX bloqueia escolhas que o pacote do evento nao permite antes do save.

Arquivos e areas provaveis:

- `apps/web/src/modules/events/components/EventEditorPage.tsx`
- `apps/web/src/modules/events/types.ts`
- `apps/web/src/modules/events/services/events.service.ts`
- `apps/api/app/Modules/Events/Http/Requests/StoreEventRequest.php`
- `apps/api/app/Modules/Events/Http/Requests/UpdateEventRequest.php`
- `apps/api/app/Modules/Events/Actions/CreateEventAction.php`
- `apps/api/app/Modules/Events/Actions/UpdateEventAction.php`
- `apps/api/app/Modules/Events/Http/Resources/EventDetailResource.php`

### TASK M4-T2 - Fechar fluxo de grupo vinculado

Status atualizado em `2026-04-05`:

- parser de `#ATIVAR#<group_bind_code>` implementado em
  `WhatsAppGroupActivationService`;
- o autovinculo agora valida:
  - evento ativo
  - modulo `live`
  - entitlement de grupos
  - `default_whatsapp_instance_id`
  - blacklist do remetente
- o binding e criado ou reativado por `instance_id + group_external_id`;
- codigos desconhecidos sao ignorados sem criar binding nem rotear intake;
- o payload de grupo encaminhado para `InboundMedia` agora preserva
  `_event_context` com `group_external_id`, `sender_external_id`,
  `sender_phone`, `sender_lid`, `sender_name`, `caption` e `media_url`.

Estado atual:

- ha binding, ha listener, mas a ponte ainda e parcial e depende do binding
  existir;
- nesta base local nao ha bindings ativos.

Subtasks:

1. implementar parser do comando `#ATIVAR#<group_bind_code>` em mensagens de
   grupo;
2. resolver o evento pelo `group_bind_code`;
3. criar ou reativar binding por `instance_id + group_external_id`;
4. aceitar intake apenas quando:
   - binding ativo existir
   - tipo de binding for `event_gallery`
   - callback for mensagem real
   - houver midia
   - `fromMe=false` por padrao
5. carregar autor real do grupo para o payload canonico;
6. encaminhar caption e metadata da midia para a pipeline de evento;
7. impedir ativacao se o autor estiver na blacklist do evento;
8. permitir desvinculo posterior do grupo pelo CRUD do evento.

Criterio de aceite:

- grupo autorizado com midia vira intake do evento correto;
- grupo sem binding nao vira `event_media`.

Arquivos e areas provaveis:

- `apps/api/app/Modules/WhatsApp/Services/WhatsAppInboundRouter.php`
- `apps/api/app/Modules/WhatsApp/Listeners/RouteInboundToMediaPipeline.php`
- `apps/api/app/Modules/WhatsApp/Models/WhatsAppGroupBinding.php`

### TASK M4-T3 - Fechar fluxo privado por codigo

Status atualizado em `2026-04-05`:

- implementada a primeira versao:
  - parser de `#CODIGO`, `CODIGO` e `#ATIVAR#CODIGO` em DM
  - sessao privada por `instance_id + sender_external_id`
  - reply de ativacao em thread com `messageId`
  - expiracao baseada em `session_ttl_minutes`
  - comando `sair` encerrando a sessao
  - bloqueio de abertura quando o remetente esta na blacklist do evento.

Estado atual:

- feedback por reacao continua fora desta task e fica na automacao
  operacional.

Subtasks:

1. desenhar codigo de intake de evento;
2. criar sessao privada por telefone/identidade raw;
3. criar parser de `#CODIGO`;
4. validar blacklist do remetente antes de abrir a sessao;
5. confirmar ativacao via outbound;
6. encaminhar midias privadas subsequentes para o evento enquanto a sessao
   estiver ativa;
7. definir expiracao e renovacao da sessao.

Criterio de aceite:

- conversa privada sem grupo consegue ativar intake de evento por codigo.

Arquivos e areas provaveis:

- novo modulo ou subdominio em `WhatsApp`/`InboundMedia`
- `docs/architecture/event-media-intake-architecture.md`

### TASK M4-T4 - Consolidar o canal `public_upload` dentro da mesma regra de intake

Estado atual:

- upload publico ja funciona, mas ainda aparece como fluxo paralelo ao WhatsApp;
- a UX atual mostra links publicos, mas nao como canal ativavel do evento.

Subtasks:

1. refletir `public_upload` como canal do evento no CRUD;
2. reaproveitar `upload_slug` e links existentes;
3. decidir se o canal pode ser pausado sem regenerar slug;
4. manter a origem `public_upload` rastreavel no intake canonico.

Criterio de aceite:

- o operador entende que upload web e um canal do evento no mesmo painel dos
  canais WhatsApp.

### TASK M4-T5 - Aplicar blacklist do evento na resolucao de intake

Status atualizado em `2026-04-05`:

- implementado `EventMediaSenderBlacklistService`;
- o match agora prioriza `participantLid` / identidade raw e depois telefone
  normalizado;
- a blacklist ja e aplicada antes de:
  - autovincular grupo
  - abrir sessao privada
  - transformar midia em intake de evento
  - enviar feedback automatico de bloqueio
- a gestao da blacklist ainda nao foi levada para o CRUD do evento.

Estado atual:

- o enforcement operacional da blacklist ja existia no backend;
- faltava levar essa capacidade para o CRUD do evento e para o resumo pronto
  da UI.

Status atualizado em `2026-04-06`:

- a blacklist do evento agora esta exposta no CRUD/UI do evento;
- o detalhe do evento devolve:
  - entradas persistidas da blacklist
  - remetentes agregados do evento
  - contagem de webhooks e midias por remetente
  - identidade recomendada para bloqueio
- o `EventEditorPage` agora permite:
  - bloquear/desbloquear remetentes por switch
  - definir prazo de bloqueio temporario
  - cadastrar/remover entradas manuais de blacklist
  - visualizar avatar, nome, telefone, `@lid`, ultima atividade e volume ja
    enviado pelo remetente
- a moderacao agora tambem consome esse estado no fluxo operacional:
  - `GET /media/feed` devolve contexto leve do remetente por midia
  - `GET /media/{id}` devolve contagem de midias do remetente no evento
  - `POST /media/{id}/sender-block` cria/reativa bloqueio rapido
  - `DELETE /media/{id}/sender-block` remove o bloqueio rapido
  - o feed suporta filtro `sender_blocked`
  - o review panel ganhou switch de bloqueio, prazo e badges visuais
- a listagem de remetentes do detalhe do evento ja cobre a acao rapida de
  bloqueio;
- a superficie de galeria por evento ainda nao recebeu o mesmo atalho
  operacional, entao isso segue como gap explicitamente aberto.

Subtasks:

1. consultar blacklist antes de:
   - autovincular grupo
   - abrir sessao privada
   - transformar midia em `event_media`
   - disparar feedback automatico
2. priorizar match por id raw e depois por telefone normalizado;
3. registrar auditoria de bloqueio;
4. expor gestao da blacklist no CRUD do evento.

Criterio de aceite:

- remetente bloqueado nao ativa grupo, nao abre sessao e nao injeta midia no
  evento.

### TASK M4-T6 - Aplicar limites por pacote e politica de instancia

Status atualizado em `2026-04-05`:

- o CRUD do evento ja aplica limites de grupos, DM, upload, Telegram e
  instancia dedicada;
- o intake de grupo e DM ja consome os entitlements materializados do evento;
- o webhook so roteia quando a instancia recebida bate com
  `default_whatsapp_instance_id`.

Estado atual:

- o enforcement principal ja existe no backend e no intake;
- ainda falta completar:
  - mensagens operacionais mais claras no frontend
  - exposicao da blacklist no CRUD do evento.

Status atualizado em `2026-04-06`:

- o runtime do intake agora tambem bloqueia cenarios de conflito operacional de
  instancia dedicada;
- se dois eventos dedicados apontarem para a mesma `whatsapp_instance`, grupo e
  DM deixam de ser roteados ate o conflito ser saneado.

Subtasks:

1. validar no save do evento a quantidade maxima de grupos permitida;
2. validar se DM, upload e Telegram estao liberados pelo entitlement;
3. validar se a instancia dedicada e permitida para o evento;
4. impedir que uma instancia exclusiva seja vinculada a eventos conflitantes;
5. refletir as mensagens de erro de forma clara no painel.

Criterio de aceite:

- o sistema impede configuracao fora do pacote tanto no frontend quanto no
  backend.

## M5 - Performance, seguranca, operacao e observabilidade

Objetivo:

- garantir feedback rapido em segundos e endurecer o recebimento real sem
  inventar contrato que a Z-API nao documenta.

### TASK M5-T1 - Separar fast lane e slow lane do intake

Status atualizado em `2026-04-05`:

- o relogio de deteccao ja sai na fast lane antes do dispatch para o pipeline
  canonico;
- `ProcessInboundWebhookJob` do modulo `InboundMedia` deixou de ser stub e
  agora consome `_event_context` para criar `channel_webhook_logs`,
  `inbound_messages` e `event_media`;
- o feedback operacional agora fica desacoplado por fase em
  `whatsapp_message_feedbacks`.

Estado atual:

- a separacao tecnica ja existe, mas ainda faltam:
  - metas explicitas de SLA no plano
  - backlog e telemetria operacional por fila
  - observabilidade mais clara para debug em producao.

Subtasks:

1. travar a arquitetura `fast lane` vs `slow lane`;
2. garantir que o relogio seja decidido antes de download/moderacao;
3. definir filas por responsabilidade;
4. definir alvos minimos de latencia para webhook, feedback inicial e
   publicacao;
5. separar claramente job de feedback outbound do job de processamento de
   midia.

Criterio de aceite:

- o plano deixa explicito como a reacao de reconhecimento pode sair em segundos.

### TASK M5-T2 - Endurecer autenticidade sem depender de header nao documentado

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

### TASK M5-T3 - Melhorar logs, telemetria e lookup operacional

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
4. medir backlog das filas `whatsapp-inbound`, `whatsapp-feedback` e correlatas;
5. decidir indices e caches obrigatorios para:
   - binding por grupo
   - sessao DM por codigo
   - `media_inbox_code`
   - `group_bind_code`
   - blacklist por remetente
6. documentar o que pode cair em cache e o que continua vindo do banco.

Criterio de aceite:

- o time consegue explicar por que um callback foi aceito, ignorado ou falhou
  sem precisar abrir payload manualmente toda vez.

## M6 - Matriz de testes automatizados

Objetivo:

- fechar a cobertura que hoje nao existe para os webhooks da Z-API.

Status atualizado em `2026-04-05`:

- fixtures reais anonimizadas foram versionadas em
  `apps/api/tests/Fixtures/WhatsApp/ZApi`;
- a suite agora cobre:
  - replay real de grupo com imagem
  - notificacao de grupo ignorada
  - `MessageStatusCallback`
  - autovinculo de grupo
  - blacklist por `phone` e `@lid`
  - `messageId` em feedback threaded
  - consumo real de `_event_context` no `InboundMedia`.
- a suite agora tambem cobre:
  - CRUD da blacklist do evento
  - rejeicao manual ponta a ponta via action HTTP + listener
  - rejeicao por IA ponta a ponta via `RunModerationJob` + listener

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
10. sempre que possivel, construir esses testes a partir de fixtures de replay
    baseadas em payload real da Z-API.

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

Status atualizado em `2026-04-06`:

- existe replay automatizado com fixture real/anonimizada de grupo nao
  vinculado;
- o cenario prova que o webhook continua auditado no modulo WhatsApp, mas nao
  dispara intake canonico nem feedback operacional quando nao houver binding
  ativo.

Subtasks:

1. grupo vinculado com midia;
2. grupo sem binding;
3. grupo vinculado com `fromMe=true`;
4. autovinculo do grupo via `#ATIVAR#<group_bind_code>`;
5. grupo vinculado usando `group_external_id = phone` do grupo;
6. fluxo privado por codigo quando a milestone correspondente entrar;
7. grupo nao vinculado sendo desconsiderado para intake;
8. grupo com codigo invalido sendo desconsiderado para intake;
9. evento inativo impedindo intake e feedback automatico.

### TASK M6-T5 - Cobrir CRUD de canais do evento

Subtasks:

1. teste de create/update com `intake_channels`;
2. teste de sync de `event_channels`;
3. teste de sync de `whatsapp_group_bindings` a partir do evento;
4. teste de geracao e regeneracao de `media_inbox_code`;
5. teste de geracao e regeneracao de `group_bind_code`;
6. teste de `show` do evento devolvendo resumo editavel dos canais;
7. teste de upload link refletido como canal ativo;
8. teste de vinculacao e desvinculo de grupos no editor do evento;
9. teste de CRUD da blacklist do evento;
10. teste de instancia default do evento;
11. teste de bloqueio de opcao por entitlement de pacote.

Status atualizado em `2026-04-06`:

- o `show`, `create` e `update` do evento agora incluem `intake_blacklist`;
- o resumo entregue para a UI agora inclui:
  - remetentes recentes
  - contagem de midias
  - switch de bloqueio
  - prazo de expiracao
  - entradas manuais de blacklist

### TASK M6-T6 - Cobrir performance e idempotencia do feedback

Status atualizado em `2026-04-06`:

- existe evidencia automatizada end-to-end para:
  - rejeicao manual via endpoint/action de moderacao
  - rejeicao por IA via `RunModerationJob`
- os dois cenarios validam o listener real de feedback negativo e a criacao
  de:
  - reacao negativa em thread
  - reply textual usando `messageId`
  - registros idempotentes em `whatsapp_message_feedbacks`
- a suite de moderacao agora tambem valida:
  - feed com remetente bloqueado exposto no payload
  - filtro `sender_blocked`
  - bloqueio/desbloqueio rapido do remetente a partir da propria midia
- a suite de feedback agora tambem cobre a coexistencia entre:
  - coracao de `published`
  - reacao negativa + reply de `rejected`
  sem colidir fases nem perder idempotencia por `messageId`.

Subtasks:

1. teste garantindo que o feedback de relogio nao depende de download;
2. teste de idempotencia por `provider_message_id + feedback_phase`;
3. teste garantindo que `MediaPublished` dispara coracao sem duplicidade;
4. teste de retry de fila sem reacao duplicada;
5. teste de lookup rapido usando binding/sessao resolvidos;
6. teste garantindo que rejeicao dispara feedback negativo sem conflitar com o
   coracao;
7. teste garantindo que evento inativo nao dispara feedback;
8. teste garantindo que rejeicao envia reply textual com
   `Sua midia nao segue as diretrizes do evento. 🛡️`.

### TASK M6-T7 - Cobrir blacklist por `phone` e `@lid`

Subtasks:

1. blacklist bloqueia autovinculo do grupo;
2. blacklist bloqueia abertura de sessao em DM;
3. blacklist bloqueia intake de midia em grupo ja vinculado;
4. blacklist bloqueia intake de midia em DM com sessao ativa;
5. match por `@lid` funciona mesmo sem telefone normalizado;
6. match por telefone funciona quando o raw id muda.

### TASK M6-T10 - Levar atalhos de bloqueio para galeria e detalhe do evento

Objetivo:

- alinhar as superficies operacionais do painel com a mesma acao rapida de
  bloqueio de remetente.

Status auditado em `2026-04-06`:

- a moderacao ja suporta bloqueio/desbloqueio rapido direto na midia;
- o detalhe do evento agora expoe remetentes recentes com:
  - switch de bloqueio rapido
  - prazo por preset
  - atalho para abrir moderacao filtrada por remetente
  - atalho para abrir galeria filtrada por remetente
- a galeria/listagem operacional por evento agora tambem recebeu:
  - badge de remetente bloqueado
  - acao rapida de bloqueio/desbloqueio
  - busca por `phone`, `@lid` e `external_id`
  - leitura dos filtros via query string para abrir ja filtrada.

Subtasks:

1. expor remetente e estado de bloqueio na galeria/listagem operacional do
   evento;
2. permitir bloquear/desbloquear o remetente diretamente dessa superficie;
3. criar atalho na listagem de remetentes do evento para abrir:
   - moderacao filtrada por remetente
   - galeria/listagem filtrada por remetente quando aplicavel
4. manter a mesma semantica de prazo e reativacao usada na moderacao;
5. reutilizar os endpoints `POST/DELETE /media/{id}/sender-block` sempre que o
   gatilho vier de uma midia concreta.

TTD:

- [x] teste frontend da galeria exibindo estado de bloqueio do remetente
- [x] teste frontend de bloqueio rapido a partir da galeria
- [x] teste frontend do atalho da listagem de remetentes do evento
- [x] teste de convergencia entre evento, galeria e moderacao

### TASK M6-T9 - Cobrir limites por pacote e instancia dedicada

Subtasks:

1. plano com `max_groups = 1` nao aceita segundo grupo;
2. plano sem DM bloqueia abertura de sessao e configuracao do canal;
3. plano sem Telegram bloqueia configuracao do canal;
4. plano sem instancia dedicada bloqueia selecao de instancia exclusiva;
5. plano com instancia dedicada permite vinculo exclusivo por evento;
6. `manual_override` sem pacote consegue liberar granularmente os canais;
7. backend e frontend convergem na mesma regra de entitlement.

### TASK M6-T8 - Replays obrigatorios com webhooks reais da Z-API

Subtasks:

1. extrair fixtures anonimizadas dos logs reais ja capturados;
2. criar suite de replay com foco principal em grupos;
3. validar contra payload real de:
   - imagem de grupo com legenda
   - texto em grupo
   - notificacao de grupo
   - reaction callback
   - `MessageStatusCallback`
4. reaproveitar o envelope real do provider tambem para o parser de codigo em
   DM, mesmo quando o conteudo do texto for adaptado;
5. manter os fixtures versionados no repositorio para regressao.

Criterio de aceite global de M6:

- existe cobertura automatizada para todos os callbacks de fase 1;
- existe cobertura para o CRUD dos canais do evento;
- existe cobertura para feedback rapido e idempotente;
- os bugs reais desta rodada passam a ter regressao permanente.

## Pendencias remanescentes auditadas em `2026-04-06`

- formalizar o fluxo de provisionar/cadastrar uma nova instancia dedicada para
  um evento; hoje a doc cobre selecionar uma `whatsapp_instance` existente e
  marcar exclusividade, mas nao a jornada completa de onboarding da instancia;
- concluir a homologacao real por callback e anexar evidencias finais; na
  rodada atual o hostname fixo `webhooks-local.eventovivo.com.br` respondeu
  `200`, uma chamada real de `send-text` foi aceita pela Z-API e a callback de
  delivery correspondente apareceu no banco como `delivery/ignored`; em seguida
  chegaram callbacks reais de imagem em grupo e DM, persistidos sem criar
  intake de evento porque nao havia binding/sessao; ainda falta a evidencia
  real positiva de `ReceivedCallback` com grupo vinculado ou DM com sessao
  ativa;
- depois do binding local do evento `31`, a evidencia positiva foi reproduzida:
  grupo vinculado e DM com sessao ativa criaram `event_media` com
  `source_type` `whatsapp_group` e `whatsapp_direct`; a homologacao externa
  final segue aberta apenas para registrar uma nova rodada limpa sem replay.

## M7 - Homologacao real com a Z-API

Objetivo:

- validar em ambiente real o que a suite automatizada disser que esta pronto.

### TASK M7-T1 - Fechar roteiro de homologacao por callback

Status atualizado em `2026-04-06`:

- o endpoint fixo `https://webhooks-local.eventovivo.com.br/up` respondeu
  `200` na rodada atual;
- um envio real via `send-text` foi aceito pela Z-API e retornou
  `messageId = AAB0BDE8C3527578E13E`;
- a callback correspondente de `MessageStatusCallback` foi persistida
  localmente como `delivery/ignored`, comportamento esperado porque o envio foi
  feito direto na Z-API e nao havia mensagem outbound local para atualizar;
- a rodada tambem validou e corrigiu timestamp numerico real em microssegundos
  (`momment`) para callbacks de status;
- callbacks reais de fotos em grupo e DM chegaram e confirmaram o cenario
  negativo esperado: sem binding de grupo e sem sessao DM, o sistema audita em
  `whatsapp_messages`, mas nao cria `inbound_messages` nem `event_media`;
- um callback real de `reaction` chegou com estrutura de array e passou a ser
  ignorado corretamente apos ajuste no normalizador;
- uma rodada positiva local com o evento `31` confirmou que grupo vinculado e
  DM com sessao ativa entram no intake canonico e aparecem no catalogo como
  canal `whatsapp`, inclusive quando o `source_type` bruto e
  `whatsapp_group` ou `whatsapp_direct`;
- esta task segue aberta somente para registrar uma nova rodada positiva limpa
  diretamente do provider, sem reprocessamento manual.

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

Status atualizado em `2026-04-06`:

- a ultima tentativa real confirmou disponibilidade publica do hostname fixo e
  aceite do provider para envio outbound;
- foi registrada callback real de delivery para o `messageId`
  `AAB0BDE8C3527578E13E`;
- foram registradas imagens reais de grupo e DM sem vinculo, validando que elas
  nao entram em evento automaticamente;
- foi validado localmente que `ReceivedCallback` positivo com grupo vinculado
  ou DM com sessao ativa converge para `event_media`, moderacao e galeria;
- ainda falta capturar uma nova rodada limpa diretamente da Z-API para fechar a
  homologacao ponta a ponta sem replay manual.

Subtasks:

1. salvar ids reais de callback e mensagem;
2. salvar amostras anonimizadas dos payloads;
3. validar transicoes de banco e logs;
4. atualizar esta doc e a doc de arquitetura quando houver divergencia entre
   teoria e provider real.
5. comparar o resultado dos replays automatizados com os mesmos cenarios ja
   observados em log real, principalmente grupos.

## Fase 1 x fora de escopo

### Deve entrar na fase 1

- secao `Canais de recebimento` no CRUD do evento;
- `event_channels` como registro canonico dos canais ativos do evento;
- autovinculo de grupo por `#ATIVAR#<group_bind_code>`;
- possibilidade de desvincular grupos pelo evento;
- blacklist do evento por `phone` e `@lid`;
- limites por pacote para grupos, DM e canais futuros;
- suporte a instancia WhatsApp default do evento, com caminho para instancia
  dedicada quando o pacote permitir;
- classificacao correta de callback;
- `ReceivedCallback` funcionando com grupo, privado, midia e legenda;
- `MessageStatusCallback` atualizando outbound;
- `ConnectedCallback` e `DisconnectedCallback` atualizando a instancia;
- identidade correta do autor em grupo;
- regra de `fromMe`;
- reacao de relogio em fast lane para midia elegivel;
- feedback idempotente por `provider_message_id`;
- cobertura automatizada dos webhooks da Z-API;
- cobertura automatizada do CRUD dos canais;
- documentacao operacional do painel.

### Pode ficar fora da fase 1

- endpoint dedicado de `PresenceChatCallback`;
- endpoint dedicado de `DeliveryCallback` ("Ao enviar");
- automacao rica de Nany em cima da foto;
- reply contextual automatico no grupo;
- features avancadas de atendimento baseadas em presenca.

### Nao deve ser esquecido, mas e milestone posterior

- sessao privada por codigo em producao com toda UX final;
- intake privado para evento sem grupo com replies por IA;
- consolidacao canonica completa `WhatsApp -> InboundMedia -> EventMedia`.

## Criterio de aceite global

Este plano pode ser considerado fechado quando:

- o operador conseguir ligar grupos, WhatsApp direto e upload pelo proprio CRUD
  do evento;
- o evento expuser seus canais ativos em uma representacao unica e editavel;
- o painel da Z-API estiver configurado de forma semanticamente correta;
- `ReceivedCallback`, `MessageStatusCallback`, `ConnectedCallback` e
  `DisconnectedCallback` tiverem processadores dedicados ou claramente
  separados por contrato;
- `whatsapp_messages` deixar de receber callbacks operacionais como se fossem
  mensagens inbound;
- grupo passar a persistir autor real com `participantPhone`/`senderName`;
- o `provider_message_id` virar ancora canonica para feedback e reply;
- o relogio poder ser enviado em segundos sem depender da pipeline pesada;
- o evento expor e respeitar seus limites de canais via entitlements;
- os testes de replay com payloads reais da Z-API protegerem os cenarios de
  grupo ja observados em log;
- a suite automatizada proteger os cenarios reais ja vistos em log;
- evento, galeria e moderacao convergirem no mesmo estado de bloqueio para o
  mesmo remetente;
- a doc do modulo e as docs operacionais refletirem a topologia final sem
  contradicoes.
