# Notifications Operational Inbox Architecture

## Objetivo

Este documento consolida a analise da stack atual do `eventovivo` e transforma a ideia do sino mockado do painel administrativo em uma feature coerente com a arquitetura real do produto.

O objetivo nao e criar "mais um sino de alertas". O objetivo e definir um `Notification Center` operacional por usuario, persistido, com dedupe, recorrencia, resolucao e evolucao clara para realtime, e-mail e push.

---

## Escopo desta analise

Foram inspecionados principalmente:

- `apps/web/src/app/layouts/AppHeader.tsx`
- `apps/web/src/lib/query-client.ts`
- `apps/web/src/modules/dashboard/hooks/useDashboardStats.ts`
- `apps/web/src/modules/settings/SettingsPage.tsx`
- `apps/web/src/modules/moderation/hooks/useModerationRealtime.ts`
- `apps/web/src/modules/wall/hooks/useWallManagerRealtime.ts`
- `apps/web/src/sw.ts`
- `apps/api/app/Modules/Dashboard/*`
- `apps/api/app/Modules/Billing/*`
- `apps/api/app/Modules/WhatsApp/*`
- `apps/api/app/Modules/Notifications/*`
- `apps/api/routes/channels.php`
- `apps/api/database/seeders/RolesAndPermissionsSeeder.php`

Tambem foram adicionados testes automatizados de caracterizacao para responder duvidas centrais desta fase.

---

## Leitura da stack atual

## 1. Frontend

O frontend ja tem as pecas basicas certas para um inbox operacional:

- o sino mockado existe em `AppHeader.tsx`;
- o `TanStack Query` ja tem `queryKeys.notifications`;
- existe uma tela administrativa com rotas e guards prontos para receber uma pagina `/notifications`;
- o painel ja trabalha com dropdown, badge de contador, toasts e invalidation;
- a stack de realtime ja esta consolidada em `wall` e `moderation`.

O que ainda nao existe:

- modulo frontend `notifications`;
- service real para buscar inbox e unread count;
- pagina `/notifications`;
- estado otimista para `read`, `dismiss` e `read-all`;
- escuta realtime por usuario;
- push do navegador.

Importante: `sw.ts` hoje cuida de cache PWA e nao implementa `Push API`. Isso significa que `push` deve entrar como fase posterior, nao como peca inicial.

## 2. Backend

O backend tambem ja tem varios insumos reais:

- existe um modulo `Notifications`, mas ainda placeholder;
- o `Dashboard` ja calcula `pending_moderation`, `processing_errors` e `alerts`;
- o `Billing` ja expoe estado suficiente para notificacoes de ciclo de assinatura e pagamento;
- o modulo `WhatsApp` ja tem estados operacionais e erros persistidos;
- o projeto ja usa broadcasting com canais privados por permissao e escopo.

O que ainda nao existe:

- persistencia de inbox por usuario;
- orquestrador de notificacoes transversal;
- endpoints reais de inbox;
- canal privado por usuario para notificacoes;
- politicas de dedupe e agregacao;
- preferencia persistida por canal de entrega.

## 3. Sinais de negocio ja disponiveis

A stack atual ja oferece fontes fortes para a fase 1:

- `Dashboard Alerts`
  - evento perto do limite de fotos;
  - erro de processamento agregado;
  - evento que comeca hoje sem midia.
- `Dashboard KPIs`
  - backlog de moderacao;
  - quantidade de erros operacionais.
- `Billing`
  - `status`;
  - `trial_ends_at`;
  - `renews_at`;
  - `cancellation_effective_at`;
  - notificacoes transacionais ja deduplicadas em `billing_order_notifications`.
- `WhatsApp`
  - `status`;
  - `last_error`;
  - `qr_error`;
  - `device_error`;
  - `connected_at` e `disconnected_at`.
- `Wall` e `Moderation`
  - padrao de canal privado e invalidation via realtime.

Conclusao: a plataforma ja tem sinais de dominio suficientes para iniciar a feature sem inventar fontes artificiais.

---

## Modelo arquitetural recomendado

O modelo correto para este repositorio e de 3 camadas.

## Camada 1. Dominio emite sinais

Cada modulo de negocio continua dono do proprio estado e passa a emitir sinais sem saber como o inbox funciona.

Exemplos:

- `media.processing.failed`
- `media.moderation.backlog`
- `event.photo_limit.near`
- `event.starts_today_without_media`
- `billing.payment_failed`
- `billing.payment_paid`
- `billing.subscription_trial_ending`
- `billing.subscription_renewal_due`
- `whatsapp.instance.disconnected`
- `whatsapp.instance.invalid_credentials`
- `wall.expired`

Regra arquitetural:

- o dominio publica o fato;
- ele nao decide destinatario final;
- ele nao cria linha de inbox diretamente;
- ele nao dispara e-mail/push diretamente.

### Regra critica: somente depois do commit

Toda geracao de notificacao deve acontecer apenas depois do commit da transacao.

No contexto Laravel, isso significa:

- eventos de dominio que impactam notificacoes devem usar `ShouldDispatchAfterCommit` quando aplicavel;
- listeners e jobs derivados devem rodar `afterCommit`;
- broadcast e fan-out acontecem depois da persistencia do inbox.

Isso evita:

- inbox criada para estado que sofreu rollback;
- broadcast de item inexistente;
- divergencia entre UI e estado do banco.

## Camada 2. Notification Orchestrator

Esta e a peca central do desenho.

Responsabilidades:

- receber sinais de dominio;
- resolver destinatarios por permissao e escopo;
- definir severidade;
- aplicar dedupe;
- aplicar agregacao;
- reabrir incidente quando ele reaparece;
- persistir o inbox;
- publicar eventos pequenos para realtime.

O orquestrador deve receber contexto estruturado:

- `organization_id`
- `event_id` opcional
- `entity_type`
- `entity_id`
- `code`
- `severity`
- `status`
- `payload`
- `action_url`

Ele nao deve depender de "quem clicou em qual pagina". Ele deve operar por regra de negocio e autorizacao.

## Camada 3. Delivery Channels

Os canais de entrega sao adaptadores finais:

- `web inbox`
- `realtime`
- `email`
- `push`

Fase inicial:

- `web inbox` obrigatorio;
- `realtime` recomendado;
- `email` opcional;
- `push` fora do MVP inicial.

---

## Modelo de dados recomendado

Nao vale usar a tabela nativa de notifications do Laravel como schema final do produto.

Ela e boa como referencia de mecanismo, mas o produto precisa de estado operacional mais rico.

Tabela recomendada:

`user_notifications`

Campos:

- `id`
- `user_id`
- `organization_id`
- `code`
- `category`
- `severity`
- `status`
- `title`
- `message`
- `action_url`
- `event_id`
- `entity_type`
- `entity_id`
- `payload_json`
- `dedupe_key`
- `occurrence_count`
- `first_occurred_at`
- `last_occurred_at`
- `read_at`
- `dismissed_at`
- `resolved_at`
- `created_at`
- `updated_at`

### Por que essa tabela faz sentido aqui

Porque o produto precisa diferenciar:

- o usuario viu;
- o usuario removeu da frente;
- o problema acabou;
- o problema aconteceu de novo.

### Status recomendado

`status` deve ser explicito:

- `active`
- `resolved`
- `dismissed`

`read_at` continua existindo, mas nao substitui `status`.

### Semantica dos campos de ciclo de vida

- `read_at`: o usuario viu.
- `dismissed_at`: o usuario escondeu da triagem.
- `resolved_at`: o sistema detectou que o problema acabou.
- `occurrence_count`: o problema reapareceu ou foi reafirmado.
- `first_occurred_at`: primeira vez em que o incidente apareceu.
- `last_occurred_at`: ultima recorrencia conhecida.

### Recorrencia e interatividade

Quando um incidente recebe uma nova ocorrencia, ele deve voltar a ser operacionalmente visivel:

- `status` volta para `active`;
- `occurrence_count` incrementa;
- `last_occurred_at` atualiza;
- `resolved_at` e `dismissed_at` sao limpos;
- `read_at` tambem deve ser limpo.

A limpeza de `read_at` e intencional. Se o usuario leu uma falha de WhatsApp as 09:00 e a mesma falha voltou as 10:00, o contador de nao lidas precisa refletir que existe uma recorrencia nova. Caso contrario, o sino deixaria de comunicar um problema ativo que reapareceu depois da triagem.

### Indices recomendados

- indice por `user_id + status + last_occurred_at desc`
- indice por `user_id + read_at`
- indice unico por `user_id + dedupe_key`
- indice por `organization_id + category + status`
- indice por `event_id` quando houver

---

## Dedupe e agregacao

Dedupe e obrigatorio.

O sistema nao deve criar uma linha por foto, uma linha por erro tecnico identico ou uma linha por heartbeat repetido.

Exemplos de `dedupe_key`:

- `media.processing.failed:event:123`
- `media.moderation.backlog:org:8`
- `whatsapp.instance.disconnected:instance:44`
- `billing.payment_failed:subscription:19`

### Regra de upsert

Quando o mesmo problema reaparece:

1. localizar por `user_id + dedupe_key`;
2. se existir e estiver `active`, incrementar `occurrence_count` e atualizar `last_occurred_at`;
3. se existir e estiver `resolved` ou `dismissed`, reabrir como `active`, limpar `resolved_at` ou `dismissed_at`, incrementar `occurrence_count` e atualizar `last_occurred_at`;
4. se nao existir, criar.

### Janela horaria

Janela pode ser usada como refinamento operacional, mas nao como dedupe principal.

O dedupe principal deve ser semantico e orientado a incidente/contexto.

---

## Mapeamento de destinatarios

O orquestrador deve resolver destinatarios por permissao e escopo.

Mapa inicial recomendado:

- `media.*` operacional:
  - usuarios com `media.moderate`
- `billing.*`:
  - usuarios com `billing.manage` ou `billing.manage_subscription`
- `whatsapp.*`:
  - usuarios com `channels.manage`
- `wall.*`:
  - usuarios com `wall.manage`
- `play.*`:
  - usuarios com `play.manage`

### Gap atual importante

A stack atual ja tem as permissoes `notifications.view` e `notifications.manage`, mas a role `financeiro` ainda nao recebe `notifications.view`.

Isso significa:

- a plataforma ja suporta a ideia de permissao dedicada para inbox;
- mas a matriz atual ainda nao esta pronta para fazer do Notification Center o inbox oficial do financeiro.

Esse gap deve ser resolvido na implementacao da feature, e nao mascarado no frontend.

---

## Realtime recomendado

O realtime deve seguir o padrao ja adotado em `wall` e `moderation`.

Canal recomendado:

- `private-user.{id}.notifications`

Eventos pequenos:

- `notification.created`
- `notification.updated`
- `notification.resolved`
- `notification.unread_count_changed`

O payload do realtime nao deve carregar a lista inteira. Ele deve carregar:

- `notification_id`
- `change_type`
- `unread_count` quando aplicavel

No frontend, o efeito principal deve ser:

- invalidar `notificationsUnreadCount`;
- invalidar a primeira pagina do inbox;
- opcionalmente aplicar merge otimista local.

---

## API recomendada

Endpoints iniciais:

- `GET /notifications`
- `GET /notifications/unread-count`
- `POST /notifications/{id}/read`
- `POST /notifications/{id}/dismiss`
- `POST /notifications/read-all`
- `GET /notifications/preferences`
- `PUT /notifications/preferences`

### Filtros recomendados para `GET /notifications`

- `status=active|resolved|dismissed`
- `unread=true|false`
- `category=...`
- `severity=info|warning|error|success`
- `event_id=...`
- `cursor=...`

### Paginacao

Usar `cursor pagination`.

Razoes:

- combina melhor com `useInfiniteQuery`;
- evita custo crescente de `offset`;
- encaixa melhor com inbox ordenado por `last_occurred_at`.

Politica inicial:

- `per_page` padrao: `20`;
- `per_page` maximo: `50`;
- dropdown do sino: ate `8` itens.

O dropdown nao deve usar a mesma carga da pagina completa. Ele deve buscar uma lista curta, priorizada por `unread + active`, e delegar a triagem completa para `/notifications`.

---

## Frontend recomendado

Criar modulo:

`apps/web/src/modules/notifications/`

Estrutura sugerida:

- `api.ts`
- `types.ts`
- `hooks/useNotificationsInbox.ts`
- `hooks/useNotificationsUnreadCount.ts`
- `hooks/useNotificationsRealtime.ts`
- `components/NotificationsDropdown.tsx`
- `components/NotificationListItem.tsx`
- `NotificationsPage.tsx`

### Queries separadas

- `notificationsUnreadCount`
- `notificationsInbox`

### Comportamento do sino

O sino deve mostrar apenas:

- contador de nao lidas;
- ultimos itens relevantes;
- CTA `Ver todas`.

### Pagina `/notifications`

Deve ser o centro operacional completo, com filtros:

- `active`
- `unread`
- `resolved`
- `dismissed`
- `category`
- `severity`
- `event`

### Mutations

- `markAsRead`
- `dismiss`
- `readAll`

Comportamento recomendado:

- UI otimista local;
- `invalidateQueries` no sucesso;
- rollback em caso de erro.

---

## O que faz sentido entrar no sino agora

Entram:

- backlog de moderacao relevante;
- erro de processamento agregado;
- evento perto do limite de fotos;
- evento ativo que comeca hoje sem midia;
- instancia WhatsApp desconectada ou com credencial invalida;
- falha de pagamento;
- pagamento confirmado quando isso muda fluxo operacional;
- assinatura perto do fim de trial ou renovacao;
- wall encerrado quando exige acao.

Nao entram:

- cada upload individual;
- cada aprovacao/rejeicao individual;
- page views;
- analytics de baixo sinal;
- auditoria generica sem acao operacional.

Auditoria continua tendo tela propria.

---

## Rollout recomendado

### Fase 1

Snapshot por usuario baseado em sinais ja existentes:

- dashboard alerts;
- KPIs agregados;
- estados de billing;
- estados de WhatsApp.

### Fase 2

Persistencia real com `user_notifications`, dedupe e status.

### Fase 3

Realtime por `private-user.{id}.notifications`.

### Fase 4

Tela `/notifications` com filtros e triagem completa.

### Fase 5

Preferencias por canal e entrega externa.

### Fase 6

E-mail e push.

---

## Testes automatizados adicionados nesta etapa

Foram adicionados testes de caracterizacao para ancorar a documentacao no codigo atual:

- validacao de que os `alerts` atuais do dashboard ja cobrem a fase 1 de snapshot;
- validacao de canal privado por usuario para notificacoes em `broadcasting/auth`;
- validacao do gap atual do papel `financeiro` em relacao a `notifications.view`.
- validacao de `dedupe_key` semantico, sem janela temporal como chave principal;
- validacao de recorrencia de incidente voltando para `active` e `unread`;
- validacao de limite de pagina para proteger performance do inbox e do dropdown.

Esses testes nao implementam a feature inteira. Eles congelam o que a stack atual ja entrega e deixam explicitos os gaps que a implementacao real precisara fechar.

---

## Decisoes fechadas

1. O produto deve nascer como inbox operacional persistido por usuario.
2. O schema final deve ser proprio, nao a tabela padrao do Laravel.
3. `status`, `occurrence_count`, `first_occurred_at` e `last_occurred_at` sao obrigatorios.
4. `read_at`, `dismissed_at` e `resolved_at` sao conceitos separados.
5. Dedupe e agregacao sao obrigatorios.
6. O dominio publica sinais; o orquestrador decide destinatarios e persistencia.
7. A geracao e a entrega devem acontecer somente depois do commit.
8. O canal realtime deve ser privado por usuario.
9. O frontend deve usar `TanStack Query` com unread count separado do inbox.
10. Push do navegador nao entra como requisito inicial.
11. Nova ocorrencia de incidente deve limpar `read_at` para refletir recorrencia no contador.
12. `GET /notifications` deve usar cursor pagination com limite maximo de pagina.

---

## Proxima execucao recomendada

1. criar a migration de `user_notifications`;
2. criar `ListNotificationsQuery`, `MarkNotificationReadAction`, `DismissNotificationAction` e `MarkAllNotificationsReadAction`;
3. criar o `NotificationOrchestrator`;
4. plugar os sinais de snapshot existentes na fase 1;
5. substituir o mock do header por `notificationsUnreadCount`;
6. criar a pagina `/notifications`;
7. ligar realtime por usuario;
8. revisar a matriz de permissoes, especialmente `financeiro`.
