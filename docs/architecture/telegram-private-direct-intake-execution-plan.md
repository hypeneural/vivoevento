# Plano de Execucao - Telegram Privado Direto

Auditado em `2026-04-06`.

Revalidado contra a documentacao oficial da Telegram Bot API `9.6` em `2026-04-06`.

## Objetivo

Executar o V1 de intake via Telegram no `eventovivo` com escopo estritamente privado:

- somente conversa privada com o bot ja criado;
- somente ativacao direta por codigo;
- somente midia e texto enviados diretamente ao bot;
- sem grupos;
- sem supergrupos;
- sem topicos;
- sem canais.

Este plano existe para transformar a analise arquitetural em backlog executavel sem perder contexto de produto, backend, frontend, filas, webhook, seguranca e testes.

## Escopo fechado do V1

O V1 deve aceitar apenas:

- `chat.type = private`;
- ativacao por deep link `/start CODIGO`;
- encerramento por `SAIR`, `/sair` ou `/stop`;
- mensagens `text`, `photo`, `video` e `document`;
- feedback minimo com `sendChatAction`, `sendMessage` e `setMessageReaction`.

O V1 deixa explicitamente fora:

- grupos;
- supergrupos;
- topicos;
- canais;
- binding por grupo;
- `message_thread_id` como identidade obrigatoria;
- `voice`;
- `audio`;
- `edited_message`;
- inbound orientado a reacao;
- album como lote logico.

## Decisoes congeladas

1. O webhook do Telegram sera refeito dentro de um modulo `Telegram`, e nao finalizado no stub atual do `InboundMedia`.
2. Idempotencia de webhook usara `provider + provider_update_id`.
3. Identidade da mensagem usara `provider + chat_external_id + provider_message_id`.
4. IDs do Telegram serao tratados como `BIGINT` ou `string`.
5. O V1 trabalhara com um unico fluxo de sessao privada por codigo.
6. O download de midia usara `getFile` e nunca URL tokenizada persistida.
7. O `InboundMedia` deve receber envelope canonico normalizado, e nao JSON cru do Telegram.

## Referencias obrigatorias

- Analise principal: [event-intake-multi-channel-analysis.md](./event-intake-multi-channel-analysis.md)
- Checklist de entrega em VPS: [production-vps-delivery-checklist.md](./production-vps-delivery-checklist.md)
- Guia de DNS e proxy: [cloudflare-vps-pointing-guide.md](./cloudflare-vps-pointing-guide.md)
- README atual do intake: [apps/api/app/Modules/InboundMedia/README.md](../../apps/api/app/Modules/InboundMedia/README.md)
- Referencias oficiais:
  - `https://core.telegram.org/bots/api`
  - `https://core.telegram.org/bots/features#deep-linking`

## Estado validado do bot atual

Validado manualmente em `2026-04-06`:

- bot responde em `getMe`;
- `username = eventovivoBot`;
- webhook registrado em `https://webhooks-local.eventovivo.com.br/api/v1/webhooks/telegram`;
- `allowed_updates = ["message", "my_chat_member"]`;
- `pending_update_count = 0`.

Smoke real local preparado em `2026-04-06`:

- evento local: `32`;
- canal Telegram local: `4`;
- codigo de ativacao: `TGTEST406`;
- deep link: `https://t.me/eventovivoBot?start=TGTEST406`;
- smoke artificial pelo endpoint publico abriu sessao com `routing_status = session_activated`.

## Status atual em `2026-04-06`

Ja concluido neste repositorio:

- Fase 1.1:
  - modulo `Telegram` criado;
  - `TelegramServiceProvider` registrado;
  - `README.md` do modulo criado.
- Fase 1.2:
  - variaveis de ambiente do bot e `secret_token` adicionadas;
  - configuracao centralizada em `config/services.php`.
- Fase 2.1:
  - client implementado para `getMe`, `getWebhookInfo`, `setWebhook`, `getFile`, `downloadFile`, `sendChatAction`, `sendMessage` e `setMessageReaction`;
  - `deleteWebhook`, `sendPhoto` e `sendVideo` ficam fora do backend V1 minimo e permanecem como extensao futura.
- Fase 2.2:
  - healthcheck inicial do bot implementado com `getMe` e `getWebhookInfo`.
- Fase 3.1:
  - webhook movido para o modulo `Telegram`;
  - validacao de `X-Telegram-Bot-Api-Secret-Token` implementada;
  - filtro estrito de `chat.type = private` implementado;
  - updates fora de escopo passam a ser auditados como `ignored`.
- Fase 3.2:
  - migration de `channel_webhook_logs.provider_update_id` adicionada;
  - chave unica `provider + provider_update_id` adicionada;
  - replay por `update_id` bloqueado antes de novo dispatch.
- Fase 4.1:
  - parser `/start CODIGO` implementado e testado;
  - resolucao do canal `telegram_bot` por `media_inbox_code` implementada.
- Fase 4.2:
  - tabela `telegram_inbox_sessions` criada;
  - modelo e factory `TelegramInboxSession` criados;
  - abertura de sessao privada por `/start CODIGO` implementada;
  - fechamento por `SAIR`, `/sair` e `/stop` implementado.
- Fase 4.3:
  - midia privada sem sessao ativa passa a ser ignorada com auditoria `no_active_session`.
- Fase 5.1:
  - normalizacao inicial de `photo`, `video` e `document` implementada a partir do `Update`;
  - `photo` seleciona o maior `PhotoSize[]`;
  - `file_id`, `file_unique_id`, `caption`, `caption_entities`, `media_group_id` e metadados principais sao preservados.
- Fase 5.2:
  - sessao ativa agora monta envelope canonico para o `InboundMedia`;
  - `media.download_strategy = telegram_file` evita persistir URL tokenizada.
- Fase 6.1:
  - downloader provider-aware inicial implementado;
  - fluxo `getFile -> file_path -> download` implementado para Telegram;
  - `file_path` seguro e persistido no payload normalizado, sem salvar a URL com token.
- Fase 7.1:
  - feedback de deteccao implementado com `sendChatAction` e `setMessageReaction`;
  - envio agendado em `telegram-send` quando existe sessao privada ativa.
- Fase 7.2:
  - feedback de publicado implementado com `setMessageReaction`;
  - feedback de rejeitado implementado com `setMessageReaction` e `sendMessage` usando `reply_parameters.message_id`;
  - tabela `telegram_message_feedbacks` criada para idempotencia por mensagem/fase/tipo.
- Fase 8.1:
  - CRUD `events/:id/edit` agora materializa `bot_username`, `media_inbox_code` e `session_ttl_minutes`;
  - o contrato do canal continua privado-only e sem grupos.
- Fase 8.2:
  - endpoint autenticado `GET /api/v1/events/{event}/telegram/operational-status` implementado;
  - o painel do evento agora mostra `getMe`, `getWebhookInfo`, `allowed_updates` esperado vs atual e sinais `my_chat_member`;
  - `my_chat_member` fica explicitamente separado da blacklist do evento.
- Parte da Fase 9.2:
  - comando `telegram:webhook:set {url} {--drop-pending}` implementado para ativacao controlada com `allowed_updates = ["message", "my_chat_member"]`, `secret_token` e URL HTTPS publica.
  - webhook publico local registrado com sucesso via Cloudflare named tunnel;
  - `getWebhookInfo` confirmou URL, `allowed_updates` e fila pendente zero.
- Parte da Fase 0.3:
  - `supervisor-telegram-send` adicionado ao Horizon;
  - variaveis `HORIZON_WAIT_TELEGRAM_SEND`, `HORIZON_TELEGRAM_SEND_MIN_PROCESSES` e `HORIZON_TELEGRAM_SEND_MAX_PROCESSES` documentadas no `.env.example`.

Ainda pendente:

- compatibilizar/expandir a identidade `inbound_messages` para todos os providers em dados historicos reais;
- completar observabilidade e decidir se o volume real exige `telegram-inbound`;
- monitorar primeiras mensagens reais recebidas pelo bot.

Baseline automatizada atual do recorte Telegram:

- suite do webhook privado + sessao:
  - `8` testes aprovados;
  - `70` assertions aprovadas.
- suite consolidada do recorte Telegram:
  - `21` testes aprovados;
  - `117` assertions aprovadas.
- regressao focada de `InboundMedia` + `WhatsAppEventIntake`:
  - `19` testes aprovados;
  - `142` assertions aprovadas.
- etapa anterior:
  - `43` testes aprovados;
  - `284` assertions aprovadas.
- recorte consolidado final desta etapa:
  - `61` testes aprovados;
  - `467` assertions aprovadas.

Rodada real privada em `2026-04-06`:

- `/start TGTEST406` chegou pelo webhook e ativou sessao;
- o parser agora tambem aceita `TGTEST406` em mensagem separada quando nao existe sessao ativa;
- duas fotos reais chegaram e foram processadas ate `inbound_messages` e `event_media`;
- o worker local sem `media-fast` deixou as midias paradas em `processing_status = downloaded`;
- apos subir worker local de `media-fast`, `media-safety`, `media-vlm`, `face-index` e `media-publish`, as duas midias ficaram `processed`, `approved` e `published`;
- filas `webhooks`, `media-download`, `media-fast`, `media-safety`, `media-vlm`, `face-index`, `media-publish` e `telegram-send` terminaram zeradas;
- o feedback `detected` com `\u{23F3}` falhou com `REACTION_INVALID`;
- o feedback `detected` foi corrigido para `\u{1F44D}` e validado com chamada real;
- a ativacao de sessao passou a enfileirar reply textual de confirmacao com o nome do evento;
- o encerramento de sessao passou a enfileirar reply textual com o nome do evento.
- a blacklist de remetente passou a valer para Telegram por `external_id`, bloqueando tanto a ativacao por codigo quanto novas midias;
- o feedback `blocked` passou a responder no proprio chat do Telegram com reacao e reply;
- o CRUD `events/:id/edit` passou a expor `bot_username`, `media_inbox_code`, `session_ttl_minutes` e preview do deep link privado.

Rodada automatizada mais recente em `2026-04-06`:

- backend Telegram + eventos relacionados:
  - `47` testes aprovados;
  - `426` assertions aprovadas.
- frontend do recorte de intake:
  - `6` testes aprovados;
  - `npm run type-check` sem erros.

## Fase 0 - Preparacao e endurecimento da base

### Tarefa 0.1 - Fechar o contrato funcional do V1

Subtarefas:

1. Confirmar que o bot sera unico no V1.
2. Confirmar que o fluxo oficial de entrada sera deep link `/start CODIGO`.
3. Confirmar que o V1 nao tera grupos nem topicos.
4. Confirmar o conjunto de tipos suportados: `text`, `photo`, `video`, `document`.
5. Confirmar o comportamento sem sessao ativa: ignorar com auditoria, sem criar `event_media`.

Saida esperada:

- escopo congelado em documentacao;
- backlog sem itens ambigudos de grupo.

### Tarefa 0.2 - Endurecer o schema canonico para Telegram

Subtarefas:

1. Planejar migration para `channel_webhook_logs` com `provider_update_id`.
2. Planejar chave unica de dedupe por `provider + provider_update_id`.
3. Planejar ajuste de `inbound_messages` para remover dependencia de `provider + message_id`.
4. Planejar chave unica por `provider + chat_external_id + message_id`.
5. Planejar colunas ou JSON necessario para `media_group_id`, `entities`, `caption_entities`, `file_id`, `file_unique_id`, `file_path`.
6. Planejar estrategia de compatibilidade para nao quebrar WhatsApp e upload direto.

Saida esperada:

- desenho de migration aprovado antes de qualquer implementacao.

### Tarefa 0.3 - Fechar a estrategia de filas e workers

Subtarefas:

1. Definir `telegram-send` para feedback outbound.
2. Atualizar desenho de Horizon com `supervisor-telegram-send`.
3. Confirmar retries, timeout e observabilidade.
4. Adiar `telegram-inbound` ate haver volume real que justifique separar a fila canonica `webhooks`.
5. Garantir que o rollout do Telegram nao concorra mal com `webhooks`, `media-download` e `media-publish`.

Saida esperada:

- estrategia de processamento assincrono pronta para rollout.

## Fase 1 - Bootstrap do modulo Telegram

### Tarefa 1.1 - Criar modulo `Telegram`

Subtarefas:

1. Criar pasta `apps/api/app/Modules/Telegram`.
2. Criar estrutura padrao do modulo.
3. Criar `TelegramServiceProvider`.
4. Registrar o modulo na configuracao da aplicacao.
5. Criar `README.md` do modulo.

Saida esperada:

- modulo isolado e pronto para receber a logica do provider.

### Tarefa 1.2 - Criar configuracao segura do bot

Subtarefas:

1. Adicionar variaveis de ambiente para token e `secret_token`.
2. Definir estrategia para identificar o bot ativo no V1.
3. Garantir que token nao apareca em log, exception ou payload auditavel.
4. Atualizar `.env.example` quando a implementacao entrar.

Saida esperada:

- configuracao segura e replicavel.

## Fase 2 - Client oficial da Bot API

### Tarefa 2.1 - Implementar client HTTP do Telegram

Subtarefas:

1. Criar client para `getMe`.
2. Criar client para `setWebhook`.
3. Criar client para `getWebhookInfo`.
4. Criar client para `sendChatAction`.
5. Criar client para `sendMessage`.
6. Criar client para `setMessageReaction`.
7. Criar client para `getFile`.
8. Manter `deleteWebhook`, `sendPhoto` e `sendVideo` como extensao posterior ao V1 privado.

Saida esperada:

- camada oficial do provider encapsulada e testavel.

### Tarefa 2.2 - Implementar healthcheck do bot

Subtarefas:

1. Criar action para validar token com `getMe`.
2. Persistir `username` e metadados minimos necessarios.
3. Criar action para consultar `getWebhookInfo`.
4. Traduzir falhas tecnicas para mensagens operacionais legiveis no painel.
5. Expor endpoint autenticado para o CRUD consumir o snapshot operacional do canal.
6. Mostrar `my_chat_member` no painel apenas como telemetria de bloqueio/desbloqueio do bot.

Saida esperada:

- bot validado antes de ligar webhook em producao;
- `events/:id/edit` mostrando estado atual do bot/webhook sem misturar isso com blacklist.

## Fase 3 - Webhook privado inbound

### Tarefa 3.1 - Substituir o stub atual por transporte real

Subtarefas:

1. Mover a responsabilidade do endpoint para o modulo `Telegram`.
2. Validar o header `X-Telegram-Bot-Api-Secret-Token`.
3. Persistir o update bruto em trilha tecnica.
4. Extrair `update_id` e registrar `provider_update_id`.
5. Ignorar com auditoria qualquer update fora de `chat.type = private`.

Saida esperada:

- endpoint seguro, auditavel e coerente com o V1.

### Tarefa 3.2 - Idempotencia do webhook

Subtarefas:

1. Bloquear replay por `provider_update_id`.
2. Garantir comportamento deterministico para retries do Telegram.
3. Registrar claramente quando um update foi descartado por duplicidade.

Saida esperada:

- webhook reexecutavel sem duplicar sessao, inbound ou media.

## Fase 4 - Sessao privada por codigo

### Tarefa 4.1 - Resolver codigo de entrada

Subtarefas:

1. Criar parser para `/start CODIGO`.
2. Definir compatibilidade com `#CODIGO` e `CODIGO` simples ou nao.
3. Resolver `EventChannel` do tipo `telegram_bot` por `media_inbox_code`.
4. Validar evento ativo, modulo `live` e entitlement.

Saida esperada:

- abertura de sessao previsivel e rastreavel.

### Tarefa 4.2 - Persistir sessao privada

Subtarefas:

1. Escolher entre `event_channel_sessions` ou `telegram_inbox_sessions`.
2. Salvar `chat_external_id`, `sender_external_id`, `event_channel_id`, `activated_by_provider_message_id`.
3. Implementar renovacao de TTL.
4. Implementar fechamento da sessao.
5. Implementar `SAIR`, `/sair` e `/stop`.

Saida esperada:

- sessao ativa reutilizavel para entrada de midia.

### Tarefa 4.3 - Regras sem sessao

Subtarefas:

1. Ignorar midia sem sessao ativa.
2. Auditar o motivo do descarte.
3. Decidir se o bot responde instruindo novo `/start`.

Saida esperada:

- comportamento consistente e sem intake acidental.

## Fase 5 - Normalizacao para envelope canonico

### Tarefa 5.1 - Normalizar o `Update`

Subtarefas:

1. Mapear `update_id`.
2. Mapear `chat.id`, `message_id`, `from.id`.
3. Mapear `text`, `entities`, `caption`, `caption_entities`.
4. Mapear `photo`, `video`, `document`.
5. Selecionar o maior item de `PhotoSize[]`.
6. Persistir `file_id` e `file_unique_id`.
7. Preservar `media_group_id` apenas como contexto opcional.

Saida esperada:

- DTO interno coerente com a Bot API.

### Tarefa 5.2 - Entregar envelope canonico ao `InboundMedia`

Subtarefas:

1. Definir schema canonico final do envelope.
2. Garantir `message_type` oficial.
3. Incluir `_event_context` ou substituir por contrato novo controlado.
4. Garantir compatibilidade de pipeline com WhatsApp existente.

Saida esperada:

- intake canonico preparado para Telegram sem acoplamento ao JSON cru.

## Fase 6 - Download e criacao de `event_media`

### Tarefa 6.1 - Downloader provider-aware

Subtarefas:

1. Criar fluxo `getFile -> file_path -> download`.
2. Garantir que a URL com token nunca seja persistida.
3. Persistir apenas metadados seguros de arquivo.
4. Adaptar a criacao de `event_media`.

Saida esperada:

- midia do Telegram entrando na pipeline real sem vazar segredo.

### Tarefa 6.2 - Compatibilidade com pipeline existente

Subtarefas:

1. Confirmar geracao de variantes.
2. Confirmar moderacao.
3. Confirmar publicacao.
4. Confirmar integracao com `source_type` e `source_subtype`.

Saida esperada:

- Telegram direto chegando ao mesmo trilho canonico de midia.

## Fase 7 - Feedback outbound

### Tarefa 7.1 - Feedback de deteccao

Status: implementado no backend.

Subtarefas:

1. Disparar `sendChatAction`.
2. Aplicar reacao de processamento.
3. Padronizar timeout e retries.

Saida esperada:

- usuario percebe que a mensagem foi recebida.

### Tarefa 7.2 - Feedback de publicado e rejeitado

Status: implementado no backend.

Subtarefas:

1. Reagir na mensagem original.
2. Enviar texto de rejeicao quando configurado.
3. Responder na mensagem correta com `reply_parameters.message_id`.

Saida esperada:

- feedback coerente com o ciclo atual de WhatsApp.

## Fase 8 - Painel administrativo e contrato do evento

### Tarefa 8.1 - Ajustar o CRUD do evento

Subtarefas:

1. Materializar `bot_username`.
2. Materializar `media_inbox_code`.
3. Materializar `session_ttl_minutes`.
4. Persistir o contrato em `EventChannel.config_json`.
5. Remover qualquer expectativa de configuracao de grupo no card do Telegram.

Saida esperada:

- configuracao do evento coerente com o V1 privado.

### Tarefa 8.2 - Exibir estado operacional

Subtarefas:

1. Exibir deep link pronto.
2. Exibir status do webhook.
3. Exibir ultimo healthcheck do bot.
4. Exibir erros operacionais relevantes.

Saida esperada:

- operador consegue ligar, validar e diagnosticar o canal.

## Fase 9 - Observabilidade e rollout

### Tarefa 9.1 - Instrumentacao operacional

Subtarefas:

1. Medir updates recebidos.
2. Medir updates duplicados.
3. Medir updates fora de escopo.
4. Medir sessoes abertas e encerradas.
5. Medir falhas de `getFile`.
6. Medir falhas de feedback outbound.

Saida esperada:

- operacao observavel em producao.

### Tarefa 9.2 - Rollout controlado

Status: comando de registro implementado e executado no webhook publico local via Cloudflare; pendente smoke test real e monitoramento das primeiras mensagens.

Subtarefas:

1. Validar `getMe`.
2. Registrar webhook com `setWebhook`.
3. Confirmar `getWebhookInfo`.
4. Validar SSL, DNS e reachability.
5. Rodar smoke test manual fim a fim.
6. Monitorar primeiras mensagens em ambiente controlado.

Saida esperada:

- entrada gradual sem perda de contexto nem regressao silenciosa.

## Bateria de testes automatizados

## 1. Unitarios obrigatorios

Arquivos recomendados:

- `tests/Unit/Telegram/TelegramBotApiClientTest.php`
- `tests/Unit/Telegram/TelegramWebhookSecretValidatorTest.php`
- `tests/Unit/Telegram/TelegramPrivateCommandParserTest.php`
- `tests/Unit/Telegram/TelegramBotHealthcheckServiceTest.php`
- `tests/Unit/InboundMedia/TelegramUpdateInspectorTest.php`
- `tests/Unit/MediaProcessing/HorizonConfigTest.php`

Casos minimos:

1. `update_id` vira chave de idempotencia.
2. `chat.id + message_id` vira identidade da mensagem.
3. IDs grandes sao preservados sem truncar.
4. `/start CODIGO` e parseado corretamente.
5. `SAIR`, `/sair` e `/stop` encerram sessao.
6. `photo` escolhe o maior `PhotoSize[]`.
7. `file_unique_id` e preservado.
8. `getFile` monta a URL de download sem persistir token.
9. feedback outbound escolhe o metodo correto por fase.
10. Horizon escuta `telegram-send` para feedback outbound.

## 2. Feature tests obrigatorios

Arquivos recomendados:

- `tests/Feature/Telegram/TelegramWebhookControllerTest.php`
- `tests/Feature/Telegram/TelegramPrivateActivationTest.php`
- `tests/Feature/Telegram/TelegramPrivateMediaIntakePipelineTest.php`
- `tests/Feature/Telegram/TelegramFeedbackAutomationTest.php`
- `tests/Feature/Telegram/TelegramWebhookRegistrationCommandTest.php`
- `tests/Feature/Events/EventIntakeChannelsTelegramPrivateTest.php`

Casos minimos:

1. webhook com `secret_token` valido aceita o update.
2. webhook com `secret_token` invalido rejeita.
3. update duplicado por `update_id` nao reprocessa.
4. update fora de `chat.type = private` e ignorado.
5. `/start CODIGO` abre sessao.
6. codigo invalido nao abre sessao.
7. midia sem sessao ativa nao cria `inbound_message`.
8. foto com sessao ativa cria `inbound_message` e `event_media`.
9. video com sessao ativa cria `event_media`.
10. document com sessao ativa cria `event_media`.
11. `SAIR` fecha sessao.
12. sessao expirada nao aceita nova midia sem nova ativacao.

## 3. Integration tests obrigatorios

Casos minimos:

1. webhook -> trilha tecnica -> normalizacao -> download -> `event_media`.
2. falha em `getFile` gera retry sem duplicar.
3. falha de download nao vaza token em log persistido.
4. feedback de publicado reage na mensagem correta.
5. feedback de rejeitado responde na mensagem correta.

## 4. Regression tests obrigatorios

Suites que precisam continuar verdes:

- `tests/Feature/WhatsApp/WhatsAppEventIntakeTest.php`
- `tests/Feature/WhatsApp/WhatsAppWebhookProcessingTest.php`
- `tests/Feature/InboundMedia/InboundMediaPipelineTest.php`
- `tests/Feature/InboundMedia/PublicUploadTest.php`
- `tests/Feature/Events/EventIntakeChannelsTest.php`

Objetivo:

- garantir que a entrada do Telegram nao quebre WhatsApp direto, WhatsApp grupos existentes nem link direto.

## 5. Smoke tests manuais obrigatorios antes do rollout

1. Abrir deep link do bot.
2. Enviar `/start CODIGO`.
3. Receber confirmacao da sessao.
4. Enviar foto.
5. Verificar criacao de `event_media`.
6. Verificar feedback operacional.
7. Enviar `SAIR`.
8. Enviar nova foto e confirmar que foi ignorada sem sessao.

## Definicao de pronto

O V1 privado do Telegram so pode ser dado como concluido quando:

1. o bot responde com sucesso em `getMe`;
2. o webhook estiver saudavel em `getWebhookInfo`;
3. a dedupe por `update_id` estiver coberta por teste;
4. a identidade por `chat.id + message_id` estiver coberta por teste;
5. o fluxo `/start CODIGO -> envio de midia -> feedback -> SAIR` estiver verde em teste automatizado;
6. a suite de regressao de WhatsApp e upload direto continuar verde;
7. nenhuma URL com token do bot estiver persistida em banco ou log.

## Ordem sugerida de entrega tecnica

1. Fase 0 completa.
2. Fase 1 completa.
3. Fase 2 completa.
4. Fase 3 completa.
5. Fase 4 completa.
6. Fase 5 completa.
7. Fase 6 completa.
8. Fase 7 completa.
9. Fase 8 completa.
10. Fase 9 completa.

## Observacao final

Qualquer pedido de "Telegram grupos" ou "Telegram topicos" deve abrir outro plano e outra analise.
Este documento existe para impedir que o V1 privado seja contaminado por escopo que nao faz parte da primeira entrega.
