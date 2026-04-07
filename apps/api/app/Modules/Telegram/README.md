# Telegram Module

Modulo de transporte do Telegram para intake privado direto no bot.

Escopo atual do V1:

- conversa privada apenas;
- ativacao por `/start CODIGO`;
- ativacao alternativa por codigo puro quando nao existe sessao ativa;
- encerramento por `SAIR`, `/sair` e `/stop`;
- idempotencia por `provider + provider_update_id`;
- sessao persistida em `telegram_inbox_sessions`;
- envelope canonico para `photo`, `video` e `document`;
- download por `getFile`, sem persistir URL tokenizada;
- feedback outbound por fase:
  - `session_activated`: `sendMessage` em reply com o nome do evento;
  - `session_closed`: `sendMessage` em reply com o nome do evento;
  - `detected`: `sendChatAction` + `setMessageReaction`;
  - `published`: `setMessageReaction`;
  - `rejected`: `setMessageReaction` + `sendMessage` em reply;
- healthcheck com `getMe` e `getWebhookInfo`;
- webhook publico local registrado em `https://webhooks-local.eventovivo.com.br/api/v1/webhooks/telegram`;
- integracao gradual com o pipeline canonico de `InboundMedia`.

Pendente para fechar rollout:

- observabilidade operacional.
- decisao futura sobre `telegram-inbound`, se o volume real justificar.
- smoke test real fim a fim com o bot publico.
