# Production VPS Delivery Checklist

## Objetivo

Esta checklist existe para mostrar, de forma operacional, o que ja foi feito e
o que ainda falta fazer para subir a VPS de producao do Evento Vivo.

Ela complementa:

- [docs/architecture/production-vps-runbook.md](./production-vps-runbook.md)
- [docs/architecture/production-vps-execution-plan.md](./production-vps-execution-plan.md)

## Como ler

- `[x]` concluido
- `[ ]` pendente
- atualizada em `2026-04-05`

## Snapshot da rodada

- [x] `M0-T1` consolidado em documentacao
- [x] `M1-T1` executado
- [x] `M1-T2` executado
- [x] `M1-T3` executado
- [x] `M1-T4` executado
- [x] `M1-T5` executado
- [x] `M2-T1` executado
- [x] `M2-T2` executado
- [x] `M2-T3` executado
- [x] `M2-T4` executado
- [x] `M2-T5` executado
- [x] `M2-T6` executado
- [x] `M3` explicitamente fora do go-live atual
- [ ] `M4` pendente
- [ ] `M5` pendente
- [ ] `M6` pendente

## Ultima revisao antes da VPS

Status: concluida em `2026-04-05`.

- [x] revisar scripts operacionais contra a documentacao oficial atual
- [x] alinhar Ubuntu 24.04 com `php8.3-fpm`, `php8.3-opcache`, `postgresql-16` e `postgresql-client-16`
- [x] revisar `pgvector` como requisito do schema atual
- [x] alinhar baseline de Node para `Node.js 24 LTS`
- [x] revisar o bootstrap para `unattended-upgrades` por timers
- [x] revisar `install-configs.sh` com include explicito do Redis
- [x] revisar `verify-host.sh` cobrindo `nginx`, `php-fpm`, `systemd`, Redis, PostgreSQL e OPcache
- [x] revisar `verify-host.sh` cobrindo tambem `composer`, `node`, `npm` e `psql`
- [x] desabilitar o site default do Nginx na instalacao dos templates
- [x] revisar execution plan e runbook com a sequencia `M4 -> M5`
- [ ] executar validacoes de host na Ubuntu real

## Preflight local antes da VPS

Status: concluido em `2026-04-05`.

- [x] validar sintaxe dos scripts com `bash -n`
- [x] validar backend operacional com:
  - `php artisan test tests/Feature/Shared/HealthEndpointsTest.php`
  - `php artisan test tests/Unit/MediaProcessing/QueueConfigTest.php`
  - `php artisan test tests/Unit/MediaProcessing/HorizonConfigTest.php`
- [x] validar `apps/web` com `npm run type-check`
- [x] validar `apps/landing` com `npm run type-check`
- [x] validar `apps/web` com `npm run build`
- [x] validar `apps/landing` com `npm run build`
- [x] validar `php artisan migrate:status` local
- [x] aplicar a migration local pendente de idempotencia do WhatsApp
- [x] confirmar existencia local de `apps/landing/.env.example`
- [x] registrar avisos nao bloqueantes:
  - chunk grande em `apps/web` no bundle `vendor-phaser`
  - warnings Tailwind na landing para `duration-[20s]` e `duration-[3s]`

## M0 - Fonte unica e escopo do go-live

### M0-T1 - Congelar o escopo da fase 1

Status: concluido.

- [x] manter o runbook como fonte de topologia
- [x] manter o execution plan como fonte de backlog
- [x] alinhar README com as docs principais da VPS
- [x] deixar explicito que a fase 1 nao depende de WhatsApp inbound full
- [x] deixar explicito que a fase 1 nao inclui IA pesada local
- [x] manter deploy por releases, Reverb atras do Nginx e storage fora da
  release como decisoes fechadas

## M1 - Repositorio operacional versionado

### M1-T1 - Criar a arvore `deploy/` e `scripts/` de operacao

Status: concluido nesta rodada.

- [x] criar `deploy/`
- [x] criar `deploy/nginx/`
- [x] criar `deploy/nginx/sites/`
- [x] criar `deploy/nginx/conf.d/`
- [x] criar `deploy/php/`
- [x] criar `deploy/php-fpm/`
- [x] criar `deploy/redis/`
- [x] criar `deploy/postgresql/`
- [x] criar `deploy/systemd/`
- [x] criar `deploy/sudoers/`
- [x] criar `deploy/logrotate/`
- [x] criar `deploy/examples/`
- [x] criar `scripts/deploy/`
- [x] criar `scripts/ops/`
- [x] criar [deploy/README.md](./../../deploy/README.md)
- [x] criar placeholders rastreaveis para `deploy/examples/`, `scripts/deploy/`
  e `scripts/ops/`
- [x] registrar a ordem de instalacao dos templates no host

### M1-T2 - Versionar templates de Nginx

Status: concluido nesta rodada.

- [x] criar [deploy/nginx/nginx.conf](./../../deploy/nginx/nginx.conf)
- [x] criar [deploy/nginx/conf.d/cloudflare-real-ip.conf](./../../deploy/nginx/conf.d/cloudflare-real-ip.conf)
- [x] versionar os ranges atuais do Cloudflare com observacao de resync
- [x] criar [deploy/nginx/sites/eventovivo-landing.conf](./../../deploy/nginx/sites/eventovivo-landing.conf)
- [x] criar [deploy/nginx/sites/eventovivo-admin.conf](./../../deploy/nginx/sites/eventovivo-admin.conf)
- [x] criar [deploy/nginx/sites/eventovivo-api.conf](./../../deploy/nginx/sites/eventovivo-api.conf)
- [x] criar [deploy/nginx/sites/eventovivo-ws.conf](./../../deploy/nginx/sites/eventovivo-ws.conf)
- [x] incluir rate limit basico da API no template
- [x] incluir headers de websocket no reverse proxy do Reverb
- [x] incluir restore do IP real do Cloudflare
- [x] alinhar a estrategia de proxies com o bootstrap do Laravel
- [x] manter os 4 hosts publicos do desenho aprovado
- [ ] validar `nginx -t` em um host Ubuntu 24.04 real

### M1-T3 - Versionar templates de PHP-FPM, systemd e logrotate

Status: concluido nesta rodada.

- [x] criar [deploy/php/opcache-production.ini](./../../deploy/php/opcache-production.ini)
- [x] criar [deploy/php-fpm/eventovivo.conf](./../../deploy/php-fpm/eventovivo.conf)
- [x] criar [deploy/redis/eventovivo.conf](./../../deploy/redis/eventovivo.conf)
- [x] criar [deploy/postgresql/eventovivo.conf](./../../deploy/postgresql/eventovivo.conf)
- [x] registrar `pm.max_children=28` como baseline e nao como numero final
- [x] criar [deploy/systemd/eventovivo-horizon.service](./../../deploy/systemd/eventovivo-horizon.service)
- [x] criar [deploy/systemd/eventovivo-reverb.service](./../../deploy/systemd/eventovivo-reverb.service)
- [x] criar [deploy/systemd/eventovivo-scheduler.service](./../../deploy/systemd/eventovivo-scheduler.service)
- [x] criar [deploy/systemd/eventovivo-scheduler.timer](./../../deploy/systemd/eventovivo-scheduler.timer)
- [x] criar [deploy/sudoers/eventovivo-deploy-systemctl](./../../deploy/sudoers/eventovivo-deploy-systemctl)
- [x] incluir `LimitNOFILE` para Horizon e Reverb
- [x] incluir `UMask=0002` nos services long-lived
- [x] incluir `ExecReload` nos services em que faz sentido
- [x] criar [deploy/logrotate/eventovivo](./../../deploy/logrotate/eventovivo)
- [ ] validar `php-fpm8.3 -tt` em um host Ubuntu 24.04 real
- [ ] validar `systemd-analyze verify` em um host Ubuntu 24.04 real

### M1-T4 - Fechar o contrato de ambientes

Status: concluido nesta rodada.

- [x] confirmar existencia de `apps/api/.env.example`
- [x] confirmar existencia de `apps/web/.env.example`
- [x] confirmar existencia de `apps/landing/.env.example`
- [x] criar [deploy/examples/README.md](./../../deploy/examples/README.md) para
  rastrear a proxima entrega
- [x] revisar todas as variaveis obrigatorias da API para producao
- [x] revisar todas as variaveis obrigatorias do admin para producao
- [x] revisar todas as variaveis obrigatorias da landing para producao
- [x] corrigir ou documentar o drift de `REDIS_CLIENT`
- [x] criar [apps-api.env.production.example](./../../deploy/examples/apps-api.env.production.example)
- [x] criar [apps-web.env.production.example](./../../deploy/examples/apps-web.env.production.example)
- [x] criar [apps-landing.env.production.example](./../../deploy/examples/apps-landing.env.production.example)
- [x] classificar variaveis em obrigatorias, opcionais e apenas de dev
- [x] fechar o contrato final de segredos em `/var/www/eventovivo/shared/.env`
- [x] alinhar [apps/api/.env.example](./../../apps/api/.env.example) com Redis,
  cache e sessao

### M1-T5 - Criar scripts versionados de deploy e operacao

Status: concluido nesta rodada.

- [x] criar [scripts/deploy/README.md](./../../scripts/deploy/README.md)
- [x] criar [scripts/ops/README.md](./../../scripts/ops/README.md)
- [x] criar [deploy.sh](./../../scripts/deploy/deploy.sh)
- [x] criar [rollback.sh](./../../scripts/deploy/rollback.sh)
- [x] criar [healthcheck.sh](./../../scripts/deploy/healthcheck.sh)
- [x] criar [smoke-test.sh](./../../scripts/deploy/smoke-test.sh)
- [x] criar [bootstrap-host.sh](./../../scripts/ops/bootstrap-host.sh)
- [x] criar [install-configs.sh](./../../scripts/ops/install-configs.sh)
- [x] criar [verify-host.sh](./../../scripts/ops/verify-host.sh)
- [x] validar shell syntax com `bash -n`
- [x] alinhar `deploy.sh` com release imutavel, symlink `current`,
  `storage:link`, warmup e recycle de servicos
- [x] alinhar `install-configs.sh` com backup de arquivos e instalacao dos
  templates em `/etc`

## M2 - Endurecimento da aplicacao antes da VPS

### M2-T1 - Health endpoints

Status: concluido nesta rodada.

- [x] definir ownership operacional do endpoint em `Shared`
- [x] criar [HealthCheckService.php](./../../apps/api/app/Shared/Support/HealthCheckService.php)
- [x] criar [HealthController.php](./../../apps/api/app/Shared/Http/Controllers/HealthController.php)
- [x] criar `/health/live`
- [x] criar `/health/ready`
- [x] validar DB no readiness
- [x] validar Redis no readiness
- [x] validar storage no readiness
- [x] validar configuracao minima de queue no readiness
- [x] adicionar rotas publicas operacionais em [web.php](./../../apps/api/routes/web.php)
- [x] escrever testes de feature em [HealthEndpointsTest.php](./../../apps/api/tests/Feature/Shared/HealthEndpointsTest.php)
- [x] validar `php artisan test tests/Feature/Shared/HealthEndpointsTest.php`

### M2-T2 - Endurecer `config/queue.php`

Status: concluido nesta rodada.

- [x] adotar `block_for=5`
- [x] adotar `after_commit=true`
- [x] revisar `retry_after` da conexao Redis para `240`
- [x] alinhar `timeout < retry_after` nas lanes atuais do Horizon
- [x] documentar no codigo que `retry_after` e por conexao
- [x] alinhar [apps/api/.env.example](./../../apps/api/.env.example) com `REDIS_QUEUE_*`
- [x] documentar em [docs/api/queues.md](./../api/queues.md) que DB logica separada nao isola eviction
- [x] adicionar teste unitario em [QueueConfigTest.php](./../../apps/api/tests/Unit/MediaProcessing/QueueConfigTest.php)
- [x] validar `php artisan test tests/Unit/MediaProcessing/QueueConfigTest.php`

### M2-T3 - Endurecer `config/horizon.php`

Status: concluido nesta rodada.

- [x] revisar `maxJobs`
- [x] revisar `maxTime`
- [x] revisar `timeout`
- [x] revisar `tries`
- [x] revisar `memory`
- [x] revisar `balanceCooldown`
- [x] revisar `balanceMaxShift`
- [x] ativar `fast_termination`
- [x] elevar `memory_limit` do master do Horizon
- [x] manter lanes sagradas protegidas com supervisors dedicados
- [x] expor tuning adicional por env em [apps/api/.env.example](./../../apps/api/.env.example)
- [x] atualizar teste de contrato em [HorizonConfigTest.php](./../../apps/api/tests/Unit/MediaProcessing/HorizonConfigTest.php)
- [x] validar `php artisan test tests/Unit/MediaProcessing/HorizonConfigTest.php`

### M2-T4 - Idempotencia, locks e resiliencia

Status: concluido nesta rodada.

- [x] mapear jobs para `ShouldBeUnique`
- [x] mapear jobs para `WithoutOverlapping`
- [x] adicionar unique indexes onde fizer sentido
- [x] aplicar throttle em jobs de provider com Redis
- [x] revisar `tries` de jobs com lock/throttle
- [x] adicionar constraint forte em [2026_04_04_120000_add_unique_constraint_to_whatsapp_messages.php](./../../apps/api/database/migrations/2026_04_04_120000_add_unique_constraint_to_whatsapp_messages.php)
- [x] endurecer [WhatsAppInboundRouter.php](./../../apps/api/app/Modules/WhatsApp/Services/WhatsAppInboundRouter.php) para corrida de insert com refetch seguro
- [x] adicionar cobertura de inbound em [WhatsAppInboundRouterTest.php](./../../apps/api/tests/Feature/WhatsApp/WhatsAppInboundRouterTest.php)
- [x] adicionar testes de contrato em [MediaPipelineResilienceConfigTest.php](./../../apps/api/tests/Unit/MediaProcessing/MediaPipelineResilienceConfigTest.php)
- [x] validar `php artisan test tests/Feature/WhatsApp/WhatsAppInboundRouterTest.php`
- [x] validar `php artisan test tests/Unit/MediaProcessing/MediaPipelineResilienceConfigTest.php`
- [x] validar `php artisan test tests/Feature/MediaProcessing/MediaPipelineJobsTest.php`
- [x] endurecer:
  - [GenerateMediaVariantsJob.php](./../../apps/api/app/Modules/MediaProcessing/Jobs/GenerateMediaVariantsJob.php)
  - [RunModerationJob.php](./../../apps/api/app/Modules/MediaProcessing/Jobs/RunModerationJob.php)
  - [PublishMediaJob.php](./../../apps/api/app/Modules/MediaProcessing/Jobs/PublishMediaJob.php)
  - [AnalyzeContentSafetyJob.php](./../../apps/api/app/Modules/ContentModeration/Jobs/AnalyzeContentSafetyJob.php)
  - [EvaluateMediaPromptJob.php](./../../apps/api/app/Modules/MediaIntelligence/Jobs/EvaluateMediaPromptJob.php)
  - [IndexMediaFacesJob.php](./../../apps/api/app/Modules/FaceSearch/Jobs/IndexMediaFacesJob.php)

### M2-T5 - Broadcast de alto volume

Status: concluido nesta rodada.

- [x] manter `ShouldBroadcastNow` apenas para sinais operacionais raros
- [x] migrar foto/moderacao de alto volume para fila `broadcasts`
- [x] confirmar `ShouldDispatchAfterCommit`
- [x] endurecer [AbstractModerationBroadcastEvent.php](./../../apps/api/app/Modules/MediaProcessing/Events/AbstractModerationBroadcastEvent.php)
- [x] validar contrato com [MediaPipelineResilienceConfigTest.php](./../../apps/api/tests/Unit/MediaProcessing/MediaPipelineResilienceConfigTest.php)
- [x] corrigir serializacao de eventos queued removendo `readonly` dos payloads de broadcast

### M2-T6 - Observabilidade minima

Status: concluido nesta rodada.

- [x] propagar `request_id` ou `trace_id` para jobs com middleware + `Context`
- [x] ligar `queue:monitor`
- [x] definir thresholds de backlog
- [x] registrar latencia `inbound -> publish`
- [x] explicitar politica de degradacao operacional
- [x] proteger Horizon, Telescope e Pulse
- [x] adicionar [AssignRequestContext.php](./../../apps/api/app/Shared/Http/Middleware/AssignRequestContext.php)
- [x] alinhar [BaseController.php](./../../apps/api/app/Shared/Http/BaseController.php) com `request_id` contextual
- [x] alinhar [observability.php](./../../apps/api/config/observability.php)
- [x] alinhar [logging.php](./../../apps/api/config/logging.php)
- [x] alinhar [AppServiceProvider.php](./../../apps/api/app/Providers/AppServiceProvider.php) com `QueueBusy`, `LongWaitDetected` e contexto de job
- [x] alinhar [console.php](./../../apps/api/routes/console.php) com `queue:monitor`
- [x] adicionar [MediaPipelineTelemetryService.php](./../../apps/api/app/Modules/MediaProcessing/Services/MediaPipelineTelemetryService.php)
- [x] adicionar [MediaPipelineDegradationPolicy.php](./../../apps/api/app/Modules/MediaProcessing/Services/MediaPipelineDegradationPolicy.php)
- [x] alinhar gates `viewHorizon`, `viewTelescope` e `viewPulse` em [AppServiceProvider.php](./../../apps/api/app/Providers/AppServiceProvider.php)
- [x] endurecer degradacao em:
  - [AnalyzeContentSafetyJob.php](./../../apps/api/app/Modules/ContentModeration/Jobs/AnalyzeContentSafetyJob.php)
  - [EvaluateMediaPromptJob.php](./../../apps/api/app/Modules/MediaIntelligence/Jobs/EvaluateMediaPromptJob.php)
  - [IndexMediaFacesJob.php](./../../apps/api/app/Modules/FaceSearch/Jobs/IndexMediaFacesJob.php)
  - [RunModerationJob.php](./../../apps/api/app/Modules/MediaProcessing/Jobs/RunModerationJob.php)
  - [ReprocessEventMediaStageAction.php](./../../apps/api/app/Modules/MediaProcessing/Actions/ReprocessEventMediaStageAction.php)
- [x] expor `inbound_to_publish_seconds` em [MediaPipelineMetricsService.php](./../../apps/api/app/Modules/MediaProcessing/Services/MediaPipelineMetricsService.php)
- [x] validar contexto em [HealthEndpointsTest.php](./../../apps/api/tests/Feature/Shared/HealthEndpointsTest.php)
- [x] validar scheduler em [QueueMonitorScheduleTest.php](./../../apps/api/tests/Unit/MediaProcessing/QueueMonitorScheduleTest.php)
- [x] validar telemetria em [MediaPipelineTelemetryServiceTest.php](./../../apps/api/tests/Unit/MediaProcessing/MediaPipelineTelemetryServiceTest.php)
- [x] validar publish telemetry em [PublishLatencyTelemetryTest.php](./../../apps/api/tests/Feature/MediaProcessing/PublishLatencyTelemetryTest.php)
- [x] validar degradacao em [OperationalDegradationPolicyTest.php](./../../apps/api/tests/Feature/MediaProcessing/OperationalDegradationPolicyTest.php)
- [x] validar gates em [OperationalDashboardAccessTest.php](./../../apps/api/tests/Unit/Shared/OperationalDashboardAccessTest.php)

## M3 - WhatsApp para producao

Status: fora do go-live atual e nao bloqueante para `M4` e `M5`.

- [ ] fechar autenticidade por provider
- [ ] persistir payload bruto cedo com quarentena/retencao
- [ ] fechar `InboundMedia\\Jobs\\ProcessInboundWebhookJob`
- [ ] fechar `NormalizeInboundMessageJob`
- [ ] fechar `DownloadInboundMediaJob`
- [ ] auditar a fiacao real de `whatsapp-*`
- [ ] adicionar supervisores `whatsapp-*` quando a ponta a ponta estiver pronta

## M4 - Bootstrap da VPS

Status: pendente.

Artefatos de repo prontos para esta fase:

- [x] versionar baseline de Redis em [deploy/redis/eventovivo.conf](./../../deploy/redis/eventovivo.conf)
- [x] versionar baseline de PostgreSQL em [deploy/postgresql/eventovivo.conf](./../../deploy/postgresql/eventovivo.conf)
- [x] criar verificador de host em [verify-host.sh](./../../scripts/ops/verify-host.sh)
- [x] ajustar `bootstrap-host.sh` para `unattended-upgrades` por APT/timers
- [x] ajustar `bootstrap-host.sh` para `php8.3-opcache`
- [x] tratar `postgresql-16-pgvector` como requisito do bootstrap padrao
- [x] simplificar include do Redis para arquivo explicito em `install-configs.sh`

- [ ] endurecer Ubuntu 24.04
- [ ] instalar runtime base
- [ ] instalar PostgreSQL 16 e Redis 7
- [ ] preparar `/var/www/eventovivo`
- [ ] instalar configs versionadas do repo
- [ ] instalar certificado de origem antes do `nginx -t`
- [ ] reiniciar Redis e PostgreSQL para aplicar os baselines locais
- [ ] manter site default do Nginx desabilitado
- [ ] instalar units para habilitacao no primeiro deploy
- [ ] fechar Cloudflare e DNS

## M5 - Primeiro deploy funcional

Status: pendente.

- [ ] criar primeira release
- [ ] linkar `.env` e `shared/storage`
- [ ] instalar dependencias
- [ ] buildar frontends
- [ ] aquecer caches
- [ ] rodar migrations
- [ ] executar healthcheck
- [ ] trocar symlink `current`
- [ ] reciclar processos
- [ ] rodar smoke test minimo completo
- [ ] testar rollback real

## M6 - Throughput e capacidade

Status: pendente.

- [ ] rodar carga sintetica para 5 eventos simultaneos
- [ ] rodar carga sintetica para 10 eventos simultaneos
- [ ] rodar carga sintetica para 20 eventos simultaneos
- [ ] revisar `pm.max_children`
- [ ] revisar `maxProcesses` por lane
- [ ] revisar Redis em carga
- [ ] revisar PostgreSQL em carga
- [ ] definir gatilhos de escala horizontal

## Proxima execucao recomendada

Ordem sugerida da proxima rodada:

- [x] executar `M2-T1`
- [x] executar `M2-T2`
- [x] executar `M2-T3`
- [x] executar `M2-T4`
- [x] executar `M2-T5`
- [x] concluir `M2-T6`
- [x] decidir que `M3` fica fora do go-live atual
- [ ] iniciar `M4` com bootstrap real da VPS

## Sequencia pronta para execucao na VPS

### Etapa 1 - Bootstrap do host

- [ ] clonar o repo temporariamente na VPS
- [ ] executar `sudo bash scripts/ops/bootstrap-host.sh --enable-ufw`
- [ ] confirmar instalacao de:
  - `nginx`
  - `php8.3-fpm`
  - `php8.3-opcache`
  - `postgresql-16`
  - `postgresql-client-16`
  - `redis-server`
  - `node`
  - `npm`
  - `composer`
- [ ] confirmar usuario `deploy`
- [ ] confirmar estrutura `/var/www/eventovivo`

### Etapa 2 - Instalar templates versionados

- [ ] executar `sudo bash scripts/ops/install-configs.sh --repo-root "<repo>"`
- [ ] confirmar instalacao de:
  - `nginx.conf`
  - `cloudflare-real-ip.conf`
  - vhosts `landing`, `admin`, `api`, `ws`
  - `opcache-production.ini`
  - pool `eventovivo.conf`
  - baselines de Redis e PostgreSQL
  - sudoers minimo do usuario `deploy`
  - units `systemd`
  - `logrotate`
- [ ] confirmar remocao de `/etc/nginx/sites-enabled/default`
- [ ] confirmar `systemctl daemon-reload`

### Etapa 3 - Instalar segredos e validar host

- [ ] instalar `eventovivo-origin.crt` e `eventovivo-origin.key`
- [ ] reiniciar `redis-server`
- [ ] reiniciar `postgresql`
- [ ] criar `/var/www/eventovivo/shared/.env`
- [ ] preencher segredos com base em `deploy/examples/apps-api.env.production.example`
- [ ] executar `sudo bash /var/www/eventovivo/scripts/verify-host.sh --require-shared-env`
- [ ] validar:
  - `nginx -t`
  - `php-fpm8.3 -tt`
  - `systemd-analyze verify`
  - `redis-cli ping`
  - `pg_isready`
  - `composer --version`
  - `node --version`
  - `npm --version`
  - `psql --version`

### Etapa 4 - Fechar Cloudflare e subir primeiro deploy

- [ ] criar registros `A` proxied para `@`, `www`, `admin`, `api` e `ws`
- [ ] ativar `Full (strict)`
- [ ] executar `bash scripts/deploy/deploy.sh`
- [ ] habilitar `eventovivo-horizon.service`, `eventovivo-reverb.service` e `eventovivo-scheduler.timer`
- [ ] confirmar que o usuario `deploy` consegue reciclar apenas os `systemctl` previstos
- [ ] executar `bash /var/www/eventovivo/scripts/smoke-test.sh`
- [ ] validar wall, upload e websocket
