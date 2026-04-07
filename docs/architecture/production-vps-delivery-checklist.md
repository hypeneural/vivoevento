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
- atualizada em `2026-04-06`

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
- [x] `M3` originalmente fora do go-live base
- [x] recorte operacional de `M3` retomado em producao para Z-API/Pagar.me
- [x] `M4` concluido na VPS real
- [x] `M5` concluido
- [ ] `M6` pendente

## Execucao real da VPS

Status: go-live base funcional em `2026-04-06` no host `vmi3206619`.

- [x] acessar a VPS Ubuntu 24.04 por SSH
- [x] validar versoes e candidatos de pacotes base no host
- [x] confirmar acesso de rede do host para GitHub
- [x] detectar que o clone remoto ainda nao continha os artefatos mais recentes da VPS
- [x] enviar a arvore local validada para `/tmp/eventovivo-bootstrap`
- [x] executar `bootstrap-host.sh --enable-ufw`
- [x] confirmar `nginx`, `php8.3-fpm`, `redis-server`, `postgresql` e `fail2ban` ativos apos bootstrap
- [x] confirmar `deploy` e estrutura `/var/www/eventovivo`
- [x] executar `install-configs.sh --repo-root /tmp/eventovivo-bootstrap`
- [x] confirmar instalacao de:
  - `nginx.conf`, `sites-enabled` e `cloudflare-real-ip.conf`
  - `eventovivo.conf` do PHP-FPM
  - drop-ins de Redis e PostgreSQL
  - units `systemd`
  - `sudoers` do `deploy`
  - scripts em `/var/www/eventovivo/scripts`
- [x] reiniciar Redis e PostgreSQL para aplicar os baselines
- [x] validar:
  - `php-fpm8.3 -tt`
  - `systemd-analyze verify`
  - `visudo -cf /etc/sudoers.d/eventovivo-deploy-systemctl`
  - `redis-cli ping`
  - `pg_isready`
- [x] reaplicar `install-configs.sh` com a versao que desabilita o pool `www` padrao do PHP-FPM
- [x] instalar certificado de origem em `/etc/ssl/certs/eventovivo-origin.crt`
- [x] instalar chave de origem em `/etc/ssl/private/eventovivo-origin.key`
- [x] validar `nginx -t`
- [x] criar banco, usuario e extensoes da app
- [x] criar `/var/www/eventovivo/shared/.env`
- [x] rodar `verify-host.sh --require-shared-env`
- [x] executar primeiro deploy
- [x] habilitar `eventovivo-horizon`, `eventovivo-reverb` e `eventovivo-scheduler.timer`
- [x] rodar smoke test
- [x] validar `Horizon`, `Reverb` e `eventovivo-scheduler.timer` ativos apos o deploy
- [x] validar zero failed jobs no baseline operacional
- [x] validar backlog zerado nas filas base:
  - `webhooks`
  - `media-fast`
  - `media-process`
  - `media-publish`
  - `broadcasts`
  - `default`
  - `notifications`
  - `analytics`
  - `billing`
- [x] validar handshake websocket `101` com `REVERB_APP_KEY` real da VPS
- [x] publicar hotfix do admin para `/login` na release `20260406_130951`
- [x] corrigir o unit real do `Reverb` para remover `ExecStop` incorreto
- [x] criar `super-admin` de producao com organizacao ativa
- [x] validar login real via `POST /api/v1/auth/login`
- [x] validar sessao real via `GET /api/v1/auth/me`
- [x] validar rollback com segunda release valida (`20260406_130951` <-> `20260406_133407`)
- [x] alinhar `deploy.sh` e `rollback.sh` para reciclagem via `systemctl reload`
- [x] publicar hotfix do admin para payloads reais de `dashboard`, `play` e
  `plans` na release `20260406_182927`
- [x] validar APIs reais de `dashboard`, `play`, `plans` e `audit` com token do
  `super-admin`
- [x] confirmar que o audit mostra eventos `auth.login` na primeira pagina
- [x] ajustar cache do admin no Nginx para nao cachear `/`, `/index.html`,
  `/sw.js` e `/manifest.webmanifest`
- [x] ajustar service worker do admin para nao manter runtime cache de scripts
  e styles do painel
- [x] ajustar `deploy.sh` para podar assets Vite do admin nao referenciados pelo
  build atual
- [x] validar que a origin ja retorna `404` para chunk antigo removido
- [x] corrigir `sites-enabled/eventovivo-admin.conf` para symlink real apontando
  para `sites-available`
- [x] remover backup antigo de `sites-enabled` para eliminar conflito de
  `server_name`
- [x] validar que asset removido na origin retorna `404` com `no-store`
- [x] validar que asset atual na origin retorna `public, immutable`
- [x] validar que `/sw.js` na origin retorna `no-store`
- [x] endurecer `install-configs.sh` para substituir arquivo regular em
  `sites-enabled` por symlink real
- [x] confirmar release ativa `/var/www/eventovivo/releases/20260406_182927`
- [x] confirmar `nginx`, `php8.3-fpm`, `eventovivo-horizon`,
  `eventovivo-reverb` e `eventovivo-scheduler.timer` ativos apos o hotfix
- [x] confirmar zero failed jobs apos o hotfix
- [x] confirmar backlog `0` em `webhooks`, `media-fast`, `media-process`,
  `media-publish`, `broadcasts`, `default`, `notifications`, `analytics` e
  `billing`
- [ ] purgar cache da Cloudflare para `/sw.js` e chunks antigos ainda servidos
  como `CF-Cache-Status: HIT`
- [ ] validar em navegador limpo, apos purge, que `/`, `/play`, `/plans` e
  `/audit` nao disparam erro de console

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
- [x] executar validacoes de host na Ubuntu real

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
- [x] proteger `/`, `/index.html`, `/sw.js` e `/manifest.webmanifest` do admin
  contra cache imutavel
- [x] criar [deploy/nginx/sites/eventovivo-api.conf](./../../deploy/nginx/sites/eventovivo-api.conf)
- [x] criar [deploy/nginx/sites/eventovivo-ws.conf](./../../deploy/nginx/sites/eventovivo-ws.conf)
- [x] incluir rate limit basico da API no template
- [x] incluir headers de websocket no reverse proxy do Reverb
- [x] incluir restore do IP real do Cloudflare
- [x] alinhar a estrategia de proxies com o bootstrap do Laravel
- [x] manter os 4 hosts publicos do desenho aprovado
- [x] validar `nginx -t` em um host Ubuntu 24.04 real

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
- [x] validar `php-fpm8.3 -tt` em um host Ubuntu 24.04 real
- [x] validar `systemd-analyze verify` em um host Ubuntu 24.04 real

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
- [x] alinhar `deploy.sh` para remover assets Vite do admin nao referenciados
  pelo build atual
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

Status: o go-live base subiu sem depender de `M3`, mas em `2026-04-06` o recorte
operacional minimo de Z-API foi retomado para o evento bonificado da Umalu.

- [ ] fechar autenticidade por provider
- [ ] persistir payload bruto cedo com quarentena/retencao
- [ ] fechar `InboundMedia\\Jobs\\ProcessInboundWebhookJob`
- [ ] fechar `NormalizeInboundMessageJob`
- [ ] fechar `DownloadInboundMediaJob`
- [x] auditar a fiacao real de `whatsapp-*`
- [x] adicionar supervisores `whatsapp-*` no Horizon:
  - `supervisor-whatsapp-inbound`
  - `supervisor-whatsapp-send`
  - `supervisor-whatsapp-sync`
- [x] configurar `.env` de producao com filas dedicadas:
  - `WHATSAPP_QUEUE_INBOUND=whatsapp-inbound`
  - `WHATSAPP_QUEUE_SEND=whatsapp-send`
  - `WHATSAPP_QUEUE_SYNC=whatsapp-sync`
- [x] cadastrar provider `zapi` em producao
- [x] cadastrar instancia Z-API `3BDB98A79042D03232CC1ABE514C6FD4` como ativa, default e conectada
- [x] validar status remoto da Z-API com HTTP `200`
- [x] validar POST publico em `/status` retornando `200 {"status":"received"}`
- [x] validar consumo do job em `whatsapp-inbound` com `processing_status=processed`
- [x] validar `queue:failed` sem falhas apos o webhook de status
- [x] validar backlog `0` em:
  - `queues:whatsapp-inbound`
  - `queues:whatsapp-send`
  - `queues:whatsapp-sync`

## M4 - Bootstrap da VPS

Status: concluido na VPS real.

Artefatos de repo prontos para esta fase:

- [x] versionar baseline de Redis em [deploy/redis/eventovivo.conf](./../../deploy/redis/eventovivo.conf)
- [x] versionar baseline de PostgreSQL em [deploy/postgresql/eventovivo.conf](./../../deploy/postgresql/eventovivo.conf)
- [x] criar verificador de host em [verify-host.sh](./../../scripts/ops/verify-host.sh)
- [x] ajustar `bootstrap-host.sh` para `unattended-upgrades` por APT/timers
- [x] ajustar `bootstrap-host.sh` para `php8.3-opcache`
- [x] tratar `postgresql-16-pgvector` como requisito do bootstrap padrao
- [x] simplificar include do Redis para arquivo explicito em `install-configs.sh`

- [x] endurecer Ubuntu 24.04
- [x] instalar runtime base
- [x] instalar PostgreSQL 16 e Redis 7
- [x] preparar `/var/www/eventovivo`
- [x] instalar configs versionadas do repo
- [x] instalar certificado de origem antes do `nginx -t`
- [x] reiniciar Redis e PostgreSQL para aplicar os baselines locais
- [x] manter site default do Nginx desabilitado
- [x] instalar units para habilitacao no primeiro deploy
- [x] fechar o apontamento DNS proxied para `@`, `www`, `admin`, `api` e `ws`
- [x] criar banco, usuario e extensoes da app
- [x] criar `/var/www/eventovivo/shared/.env`
- [ ] criar `/var/www/eventovivo/shared/apps-web.env.production`
- [ ] criar `/var/www/eventovivo/shared/apps-landing.env.production`
- [x] alinhar `FILESYSTEM_DISK=public` na VPS para o go-live base enquanto o object storage externo nao estiver pronto
- [x] rodar `verify-host.sh --require-shared-env`

## M5 - Primeiro deploy funcional

Status: concluido, com rollback validado entre duas releases boas.

- [x] criar primeira release valida
- [x] linkar `.env` e `shared/storage`
- [x] linkar `shared/apps-web.env.production` no build do admin
- [x] linkar `shared/apps-landing.env.production` no build da landing
- [x] instalar dependencias
- [x] buildar frontends
- [x] validar no healthcheck o `VITE_API_BASE_URL` compilado no admin
- [x] podar assets Vite do admin nao referenciados pelo build atual
- [x] aquecer caches
- [x] rodar migrations
- [x] executar healthcheck
- [x] trocar symlink `current`
- [x] reciclar processos
- [x] rodar smoke test minimo completo
- [x] criar segunda release valida para servir de alvo do rollback
- [x] validar login real com `super-admin`
- [x] validar `/api/v1/dashboard/stats` com token real
- [x] validar `/api/v1/events?module=play&per_page=24` com token real
- [x] validar `/api/v1/plans` com token real
- [x] validar `/api/v1/audit` exibindo `auth.login`
- [x] testar rollback real
- [x] restaurar a release final apos o rollback validado

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
- [x] iniciar `M4` com bootstrap real da VPS
- [x] concluir os bloqueios restantes de `M4`
- [x] executar `M5-T4` para validar rollback real
- [x] configurar credenciais Pagar.me de producao no `.env` da VPS
- [x] rebuildar admin com `VITE_PAGARME_PUBLIC_KEY` de producao
- [x] validar Basic Auth do webhook Pagar.me via action local da app
- [x] validar POST sem Basic Auth no Pagar.me retornando `401`
- [x] criar organizacao parceira `Umalu Eventos`
- [x] criar evento bonificado `Ana Clara e Joao Paulo - 11.04.2026`
- [x] ativar modulos `live`, `wall`, `play` e `hub`
- [x] ativar canais `public_upload_link` e `whatsapp_direct`
- [x] criar usuario de acesso da Umalu como `partner-owner`
- [x] validar login real da Umalu em `POST /api/v1/auth/login`
- [x] criar plano interno `bonus-full-umalu` e assinatura `bonus` ativa para a organizacao
- [x] validar que o acesso da Umalu libera `wall`, `play` e `whatsapp`
- [x] ativar canal `whatsapp_group` com codigo de vinculacao por `#ATIVAR#`
- [x] validar APIs publicas do evento:
  - upload API `200`
  - hub API `200`
  - pagina publica de upload `200`
- [ ] validar upload publico real em producao
- [ ] validar publish -> wall ponta a ponta em producao

## Sequencia pronta para execucao na VPS

### Etapa 1 - Bootstrap do host

- [x] clonar ou staging da arvore de bootstrap na VPS
- [x] detectar que o clone remoto estava desatualizado e subir a arvore local validada para `/tmp/eventovivo-bootstrap`
- [x] executar `sudo bash scripts/ops/bootstrap-host.sh --enable-ufw`
- [x] confirmar instalacao de:
  - `nginx`
  - `php8.3-fpm`
  - `php8.3-opcache`
  - `postgresql-16`
  - `postgresql-client-16`
  - `redis-server`
  - `node`
  - `npm`
  - `composer`
- [x] confirmar usuario `deploy`
- [x] confirmar estrutura `/var/www/eventovivo`

### Etapa 2 - Instalar templates versionados

- [x] executar `sudo bash scripts/ops/install-configs.sh --repo-root "<repo>"`
- [x] confirmar instalacao de:
  - `nginx.conf`
  - `cloudflare-real-ip.conf`
  - vhosts `landing`, `admin`, `api`, `ws`
  - `opcache-production.ini`
  - pool `eventovivo.conf`
  - baselines de Redis e PostgreSQL
  - sudoers minimo do usuario `deploy`
  - units `systemd`
  - `logrotate`
- [x] confirmar remocao de `/etc/nginx/sites-enabled/default`
- [x] confirmar `systemctl daemon-reload`
- [x] confirmar remocao do pool padrao `www.conf` do PHP-FPM

### Etapa 3 - Instalar segredos e validar host

- [x] instalar `eventovivo-origin.crt` e `eventovivo-origin.key`
- [x] reiniciar `redis-server`
- [x] reiniciar `postgresql`
- [x] criar `/var/www/eventovivo/shared/.env`
- [x] preencher segredos internos base com geracao local no host a partir de `deploy/examples/apps-api.env.production.example`
- [x] executar `sudo bash /var/www/eventovivo/scripts/verify-host.sh --require-shared-env`
- [x] validar:
  - [x] `nginx -t`
  - [x] `php-fpm8.3 -tt`
  - [x] `systemd-analyze verify`
  - [x] `redis-cli ping`
  - [x] `pg_isready`
  - [x] `composer --version`
  - [x] `node --version`
  - [x] `npm --version`
  - [x] `psql --version`

### Etapa 4 - Fechar Cloudflare e subir primeiro deploy

- [x] criar registros `A` proxied para `@`, `www`, `admin`, `api` e `ws`
- [ ] ativar `Full (strict)`
- [x] executar `bash scripts/deploy/deploy.sh`
- [x] habilitar `eventovivo-horizon.service`, `eventovivo-reverb.service` e `eventovivo-scheduler.timer`
- [x] confirmar que o usuario `deploy` consegue reciclar apenas os `systemctl` previstos
- [x] executar `bash /var/www/eventovivo/scripts/smoke-test.sh`
- [x] validar websocket
- [ ] validar upload publico e wall com fluxo funcional completo
