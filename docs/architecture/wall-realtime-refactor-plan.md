# Plano de Refactor - Wall Realtime

## Objetivo

Reestruturar o realtime do telao para que o fluxo `MediaProcessing -> Wall` seja tipado, testavel, seguro e operacionalmente previsivel em producao com Reverb + Horizon.

## Estado Consolidado

- [x] Infra base de broadcasting foi versionada no backend.
- [x] `routes/channels.php` esta registrado no bootstrap e a rota `/broadcasting/auth` existe.
- [x] A fila `broadcasts` foi isolada no Horizon.
- [x] `MediaProcessing` ja expoe eventos de dominio tipados para o pipeline.
- [x] O `Wall` ja consome eventos tipados em vez de strings `media.*`.
- [x] Eventos operacionais do Wall seguem imediatos e eventos de midia seguem enfileirados.
- [x] O acesso HTTP e os canais privados do Wall agora validam permissao + organizacao do evento.
- [x] O `WallBroadcasterService` foi reduzido a um orquestrador fino.
- [x] O contrato compartilhado backend/frontend do telao foi formalizado em `packages/shared-types`.
- [x] O player do telao ja entende o status publico `disabled`.
- [x] Backend e frontend ja possuem cobertura minima para `boot`, `state` e transicoes sensiveis do player.

## Task 1 - Infraestrutura de Broadcasting

### 1.1 Configuracao versionada do backend
- [x] Versionar `config/broadcasting.php`.
- [x] Versionar `config/reverb.php`.
- [x] Tornar `allowed_origins` configuravel por ambiente.
- [x] Alinhar `apps/api/.env.example` com Reverb + Redis.
- [x] Alinhar `apps/web/.env.example` com `VITE_REVERB_*`.

### 1.2 Bootstrap e autenticacao de canais
- [x] Registrar `routes/channels.php` em `bootstrap/app.php`.
- [x] Confirmar a rota `/broadcasting/auth`.
- [x] Manter `wall.{wallCode}` como canal publico do telao.
- [x] Fechar os callbacks reais dos canais privados por evento.

### 1.3 Operacao de filas
- [x] Criar supervisor dedicado `broadcasts`.
- [x] Definir threshold de espera para `redis:broadcasts`.
- [x] Tornar thresholds de espera do Horizon configuraveis por ambiente para `broadcasts`, `media-process` e `media-publish`.
- [ ] Revisar sizing real de `broadcasts`, `media-process` e `media-publish` em producao.

## Task 2 - Contratos Tipados do Pipeline de Midia

### 2.1 Eventos de dominio
- [x] Criar `AbstractMediaPipelineEvent`.
- [x] Criar `MediaPublished`.
- [x] Criar `MediaVariantsGenerated`.
- [x] Criar `MediaRejected`.
- [x] Criar `MediaDeleted`.

### 2.2 Emissao nas bordas sincronas
- [x] `approve` emite `MediaPublished` quando a midia ja esta publicada.
- [x] `reject` emite `MediaRejected`.
- [x] `destroy` emite `MediaDeleted`.

### 2.3 Emissao no pipeline assincrono
- [x] `GenerateMediaVariantsJob` emite `MediaVariantsGenerated`.
- [x] `RunModerationJob` converge para aprovacao/publicacao quando aplicavel.
- [x] `PublishMediaJob` emite `MediaPublished`.
- [ ] Consolidar os jobs como unica fonte final de emissao, reduzindo logica residual nos controllers.

## Task 3 - Integracao Wall <- MediaProcessing

### 3.1 Consumo tipado no modulo Wall
- [x] `WallServiceProvider` deixou de ouvir nomes de evento em string.
- [x] Listeners do Wall consomem `MediaPublished`, `MediaVariantsGenerated`, `MediaDeleted` e `MediaRejected`.
- [x] Resolucao de `EventMedia` foi centralizada no evento base do pipeline.

### 3.2 Estrategia de broadcast
- [x] `WallMediaPublished`, `WallMediaUpdated` e `WallMediaDeleted` usam `ShouldBroadcast` + fila `broadcasts`.
- [x] `WallStatusChanged`, `WallSettingsUpdated` e `WallExpired` usam broadcast imediato.
- [x] Os eventos broadcastaveis usam `ShouldDispatchAfterCommit`.

### 3.3 Validacao do fluxo
- [x] Cobrir pipeline com testes feature.
- [x] Cobrir jobs do pipeline com testes dedicados.
- [x] Cobrir o boot publico do telao com payload inicial de settings + midia.
- [x] Cobrir o `state` publico com retorno `disabled`.
- [ ] Medir tempo real entre publicacao e renderizacao no telao em ambiente produtivo.

## Task 4 - Seguranca e Autorizacao do Wall

### 4.1 Regra de acesso por evento
- [x] Criar `EventAccessService` compartilhado.
- [x] Validar permissao especifica por modulo/canal.
- [x] Validar vinculacao ativa do usuario a organizacao do evento.
- [x] Permitir bypass somente para roles globais (`super-admin`, `platform-admin`).

### 4.2 Policy e abilities do modulo Wall
- [x] Criar `WallPolicy`.
- [x] Registrar abilities `viewWall` e `manageWall` no provider do modulo.
- [x] Aplicar `authorize()` nos endpoints administrativos do Wall.
- [x] Restringir `/wall/options` por permissao `wall.view`.

### 4.3 Canais privados por evento
- [x] `event.{eventId}.wall` exige `wall.view`.
- [x] `event.{eventId}.gallery` exige `gallery.view`.
- [x] `event.{eventId}.moderation` exige `media.moderate`.
- [x] `event.{eventId}.play` exige `play.view`.

### 4.4 Testes de autorizacao
- [x] Cobrir acesso HTTP permitido ao Wall.
- [x] Cobrir acesso HTTP negado para evento de outra organizacao.
- [x] Cobrir auth bem-sucedido do canal privado do Wall.
- [x] Cobrir auth negado por organizacao errada.
- [x] Cobrir auth negado por falta de permissao no canal de moderacao.

## Task 5 - Refactor Estrutural do WallBroadcasterService

### 5.1 Separacao de responsabilidades
- [x] Extrair `WallEligibilityService`.
- [x] Extrair `WallPayloadFactory`.
- [x] Extrair resolucao de asset/imagem para `MediaProcessing` via `MediaAssetUrlService`.

### 5.2 Consistencia entre HTTP e realtime
- [x] Remover uso do broadcaster como serializer HTTP dentro de resources.
- [x] Consolidar o shape base de payload entre boot inicial e eventos realtime.
- [x] Eliminar o N+1 imediato de `inboundMessage` e `variants` no boot publico.
- [ ] Revisar se ainda existe drift entre payloads administrativos, boot publico e eventos operacionais.

## Task 6 - Contratos Compartilhados e Fechamento

### 6.1 Contrato backend/frontend do telao
- [x] Formalizar payloads do Wall em `packages/shared-types`.
- [x] Publicar status suportados, incluindo `disabled`.
- [x] Alinhar tipos do player com o contrato versionado.

### 6.2 Validacao final do contrato
- [x] Adicionar testes do player para mapear `live` e `disabled`.
- [x] Adicionar testes do client HTTP do player para `boot` e `state`.
- [x] Adicionar teste backend para `state` publico com retorno `disabled`.
- [x] Atualizar a documentacao do modulo Wall e do pacote de tipos compartilhados.

### 6.3 Observabilidade e proximas garantias

#### 6.3.1 Logs estruturados do fluxo
- [x] Logar falhas de `GenerateMediaVariantsJob`.
- [x] Logar falhas de `RunModerationJob`.
- [x] Logar falhas de `PublishMediaJob`.
- [x] Logar falhas dos broadcasts do Wall com `wall_code` e `media_id`.

#### 6.3.2 Metricas operacionais
- [x] Agendar `horizon:snapshot` para persistir snapshots de metricas.
- [x] Adicionar `tags()` nos jobs do pipeline para leitura no Horizon.
- [x] Expor tuning inicial de waits e max processes via `.env`.

#### 6.3.3 Politica de retry e timeout
- [x] Explicitar `tries`, `timeout` e `backoff` nos jobs do pipeline.
- [x] Explicitar `tries`, `timeout` e `backoff` nos eventos broadcastaveis do Wall.
- [ ] Validar retry/timeout reais com carga e falha induzida em ambiente produtivo.

## Riscos Operacionais em Aberto

1. O fluxo ja esta consistente no codigo, mas ainda nao mede latencia real de ponta a ponta entre aprovacao/publicacao e renderizacao no telao.
2. A fila `broadcasts` esta separada, mas ainda falta observar backlog, retry e timeout em carga real.
3. O contrato compartilhado do telao esta em TypeScript; ainda nao existe uma representacao formal em schema/OpenAPI para codegen ou validacao automatica multi-stack.

## Proxima Execucao Recomendada

1. Instrumentar logs e metricas do fluxo `MediaProcessing -> Wall`.
2. Revisar sizing operacional de `broadcasts`, `media-process` e `media-publish` em producao.
3. Evoluir o contrato compartilhado do telao para schema formal em `packages/contracts`.
