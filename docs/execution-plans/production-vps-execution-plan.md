# Production VPS Execution Plan

## Objetivo

Este documento transforma o runbook de producao da VPS em ordem real de
execucao, com foco primeiro no repositorio e depois na maquina.

Referencias primarias:

- [docs/architecture/production-vps-runbook.md](./production-vps-runbook.md)
- [docs/architecture/landing-page-deployment.md](./landing-page-deployment.md)
- [docs/architecture/event-media-intake-architecture.md](./event-media-intake-architecture.md)
- [docs/api/queues.md](../api/queues.md)
- [docs/flows/media-ingestion.md](../flows/media-ingestion.md)
- [docs/flows/whatsapp-inbound.md](../flows/whatsapp-inbound.md)
- [README.md](../../README.md)

Checklist operacional com status de execucao:

- [docs/architecture/production-vps-delivery-checklist.md](./production-vps-delivery-checklist.md)
- [docs/architecture/production-vps-command-sequence.md](./production-vps-command-sequence.md)

Snapshot da rodada atual:

- `M1-T1` ate `M1-T5`: concluidos
- `M2-T1` ate `M2-T3`: concluidos
- `M2-T4` e `M2-T5`: concluidos
- `M2-T6`: concluido
- `M2`: fechado no repositorio, incluindo constraint forte para inbound WhatsApp
- `M3`: fora do go-live base, mas recorte operacional de Z-API/Pagar.me retomado em producao em `2026-04-06`
- `M4`: concluido na VPS real
- validacoes reais de host confirmaram `nginx -t`, `php-fpm8.3 -tt`, `systemd-analyze verify`, `redis-cli ping` e `pg_isready`
- banco, extensoes, `.env` base e certificado de origem ja foram criados na VPS real
- o pool `www` padrao do PHP-FPM foi removido na VPS dedicada
- `M5-T1` ate `M5-T4`: concluidos na VPS real
- smoke test minimo ja validou landing, admin, `/up`, `/health/live`, `/health/ready` e handshake websocket
- validacao operacional pos-deploy confirmou `Horizon`, `Reverb` e `eventovivo-scheduler.timer` ativos
- validacao operacional pos-deploy confirmou zero failed jobs e backlog zerado nas filas base
- hotfix do admin para `/login` foi publicado na release `20260406_130951`
- o unit do `Reverb` foi endurecido apos validacao real, removendo o `ExecStop` incorreto que referenciava `MAINPID`
- `super-admin` de producao foi criado e validado por `auth/login` + `auth/me`
- rollback validado entre as releases `20260406_130951` e `20260406_133407`, com smoke test incluindo login real
- os scripts de `deploy` e `rollback` foram alinhados para reciclar `Horizon` e `Reverb` por `systemctl reload`
- hotfix do admin para payloads reais de `dashboard`, `play` e `plans` foi
  publicado na release `20260406_182927`;
- release ativa confirmada apos o hotfix:
  `/var/www/eventovivo/releases/20260406_182927`;
- o vhost do admin foi ajustado para nao cachear `/`, `/index.html`, `/sw.js`
  e `/manifest.webmanifest`;
- o deploy do admin agora remove assets Vite nao referenciados pelo build atual;
- validacao real confirmou que `/api/v1/dashboard/stats`,
  `/api/v1/events?module=play&per_page=24`, `/api/v1/plans` e
  `/api/v1/audit` retornam payloads coerentes para o `super-admin`;
- usuario da Umalu criado como `partner-owner`, login real validado e acesso
  liberado por plano interno `bonus-full-umalu`;
- canais do evento bonificado ficaram ativos para `public_upload_link`,
  `whatsapp_direct` e `whatsapp_group`;
- pendencia operacional pos-hotfix: purgar cache da Cloudflare para `/sw.js` e
  chunks antigos ainda servidos como `CF-Cache-Status: HIT`;
- preflight local antes da VPS executado:
  - scripts validados com `bash -n`
  - health/queue/horizon validados em testes
  - `apps/web` e `apps/landing` validados com `type-check` e `build`
- proxima frente recomendada: fechar as validacoes funcionais restantes de upload publico e publish -> wall

Este plano existe para responder 6 perguntas de execucao:

1. o que precisa entrar no repo antes de tocar na VPS;
2. o que precisa ser endurecido no codigo antes do primeiro go-live;
3. quais scripts e templates operacionais precisam ser versionados;
4. em que ordem a maquina deve ser provisionada;
5. quais gates de validacao precisam existir antes de chamar a stack de pronta;
6. o que fica explicitamente fora da fase 1.

## Como usar este plano

Regra simples:

- o runbook continua sendo a fonte de topologia, stack e operacao;
- este arquivo vira a fonte de backlog, sequenciamento e criterios de aceite;
- a regra e `repo primeiro, host depois`;
- nenhuma etapa de VPS deve comecar sem que os artefatos operacionais do repo
  estejam versionados;
- o go-live base nao deve depender de fechar tudo de WhatsApp inbound se esse
  fluxo ainda nao estiver pronto ponta a ponta.

Cada task abaixo aponta para:

- objetivo;
- estado atual;
- subtasks;
- dependencias;
- criterio de aceite;
- arquivos e areas mais provaveis de impacto.

Observacao:

- em tasks ja concluidas, o campo `Estado atual` pode descrever o ponto de
  partida historico da tarefa;
- o status corrente e a fonte de verdade ficam no snapshot deste arquivo e em
  [docs/architecture/production-vps-delivery-checklist.md](./production-vps-delivery-checklist.md).

## Premissas fechadas

Estas decisoes ja devem ser tratadas como travadas para a fase 1:

- 4 superficies publicas:
  - `eventovivo.com.br`
  - `admin.eventovivo.com.br`
  - `api.eventovivo.com.br`
  - `ws.eventovivo.com.br`
- Cloudflare como edge, proxy e TLS;
- deploy por releases com symlink `current`;
- Reverb local em `127.0.0.1:8080`, publicado pelo Nginx;
- storage persistente fora da release;
- object storage como destino-alvo de midia publica no medio prazo;
- sem Docker e sem Kubernetes nesta fase;
- sem `vLLM` local como caminho critico da primeira subida;
- preservar a taxonomia atual de filas do repo;
- manter uma conexao Redis de queue unica na fase 1 e ajustar `retry_after`
  no nivel da conexao;
- considerar conexoes de queue separadas apenas quando houver evidencia de que
  lanes muito diferentes estao exigindo isso;
- manter `landing + admin + api + horizon + reverb + upload publico + wall`
  como escopo base do primeiro go-live;
- tratar `WhatsApp inbound oficial` como gate separado se o pipeline ainda nao
  estiver fechado ponta a ponta.

## Artefatos que o repositorio precisa ganhar

Antes do provisionamento da VPS, estes artefatos devem existir e estar
versionados no repo:

| Caminho alvo | Papel |
| --- | --- |
| `docs/architecture/production-vps-runbook.md` | Fonte de topologia e operacao |
| `docs/execution-plans/production-vps-execution-plan.md` | Fonte de backlog e ordem de execucao |
| `deploy/nginx/nginx.conf` | Config global do Nginx |
| `deploy/nginx/sites/eventovivo-landing.conf` | Vhost da landing |
| `deploy/nginx/sites/eventovivo-admin.conf` | Vhost do admin |
| `deploy/nginx/sites/eventovivo-api.conf` | Vhost da API |
| `deploy/nginx/sites/eventovivo-ws.conf` | Reverse proxy do Reverb |
| `deploy/php/opcache-production.ini` | Baseline de OPcache para producao |
| `deploy/php-fpm/eventovivo.conf` | Pool dedicado do PHP-FPM |
| `deploy/redis/eventovivo.conf` | Baseline de Redis para a VPS unica |
| `deploy/postgresql/eventovivo.conf` | Baseline de PostgreSQL local |
| `deploy/systemd/eventovivo-horizon.service` | Worker manager do Horizon |
| `deploy/systemd/eventovivo-reverb.service` | Reverb em processo gerenciado |
| `deploy/systemd/eventovivo-scheduler.service` | Runner do scheduler |
| `deploy/systemd/eventovivo-scheduler.timer` | Execucao do scheduler a cada minuto |
| `deploy/sudoers/eventovivo-deploy-systemctl` | Sudoers minimo para recycle via usuario `deploy` |
| `deploy/logrotate/eventovivo` | Rotacao de logs da app e servicos |
| `deploy/examples/apps-api.env.production.example` | Contrato de env da API em producao |
| `deploy/examples/apps-web.env.production.example` | Contrato de env do admin em producao |
| `deploy/examples/apps-landing.env.production.example` | Contrato de env da landing em producao |
| `scripts/deploy/deploy.sh` | Deploy por release |
| `scripts/deploy/rollback.sh` | Rollback por symlink |
| `scripts/deploy/healthcheck.sh` | Validacao da release antes do switch |
| `scripts/deploy/smoke-test.sh` | Smoke test apos deploy |
| `scripts/ops/bootstrap-host.sh` | Bootstrap idempotente do host |
| `scripts/ops/install-configs.sh` | Instalacao de templates versionados no host |
| `scripts/ops/verify-host.sh` | Validacao dos gates tecnicos do host |

Observacao importante:

- `bootstrap-host.sh` e `install-configs.sh` so fazem sentido se forem
  idempotentes e explicitamente seguros para Ubuntu 24.04;
- se o time preferir nao automatizar toda a base do host, esses scripts podem
  nascer como wrappers pequenos sobre comandos ja documentados.

## Regra de sequenciamento

Executar na ordem abaixo:

1. fechar documentacao e contratos operacionais;
2. versionar templates e scripts no repo;
3. endurecer a aplicacao para producao;
4. decidir se `WhatsApp inbound` entra ou nao no primeiro go-live;
5. provisionar o host com base nos artefatos versionados;
6. executar primeiro deploy controlado;
7. validar throughput e so depois pensar em escala horizontal.

Motivo:

- subir a VPS antes de versionar os artefatos operacionais espalha configuracao
  critica fora do repo;
- endurecer o host sem endurecer `queue.php`, `horizon.php` e observabilidade
  muda pouco a estabilidade real;
- habilitar `WhatsApp inbound` antes de fechar idempotencia, autenticidade e
  pipeline canonicamente completo aumenta volume sem aumentar controle;
- escalar host unico cedo demais mascara gargalos de desenho.

## M0 - Fonte unica e escopo do go-live

Objetivo:

- fechar a fonte de verdade operacional e o escopo da primeira subida.

### TASK M0-T1 - Congelar o escopo da fase 1

Estado atual:

- o runbook ja existe;
- este execution plan passa a existir nesta rodada;
- o README ja referencia o runbook e este plano;
- o escopo base validado anteriormente e `landing + admin + api + reverb +
  horizon + upload publico + wall`.

Subtasks:

1. manter `production-vps-runbook.md` como fonte de topologia;
2. manter este arquivo como backlog de execucao;
3. explicitar no README a navegacao entre os dois documentos;
4. deixar claro que a fase 1 nao inclui:
   - rename massivo de filas
   - Docker/Kubernetes
   - `vLLM` local no host principal
   - go-live obrigatorio de `WhatsApp inbound`
5. deixar claro que a fase 1 inclui:
   - deploy por releases
   - Reverb atras do Nginx
   - Horizon com filas criticas isoladas
   - storage fora da release

Criterio de aceite:

- qualquer pessoa do time consegue responder o que entra e o que nao entra no
  primeiro go-live sem depender de conversa paralela.

Dependencias:

- nenhuma.

Arquivos e areas provaveis:

- `docs/architecture/production-vps-runbook.md`
- `docs/execution-plans/production-vps-execution-plan.md`
- `README.md`

## M1 - Repositorio operacional versionado

Objetivo:

- parar de depender de configuracao solta no host e passar a versionar a base
  operacional da subida.

### TASK M1-T1 - Criar a arvore `deploy/` e `scripts/` de operacao

Estado atual:

- o repo ainda nao tem pasta `deploy/`;
- `scripts/` hoje contem apenas geradores.

Subtasks:

1. criar a arvore inicial:
   - `deploy/nginx`
   - `deploy/php`
   - `deploy/php-fpm`
   - `deploy/redis`
   - `deploy/postgresql`
   - `deploy/systemd`
   - `deploy/sudoers`
   - `deploy/logrotate`
   - `deploy/examples`
   - `scripts/deploy`
   - `scripts/ops`
2. colocar um `README.md` curto em `deploy/` explicando como instalar os
   templates no host;
3. definir convencao de nomes para arquivos e services;
4. decidir quais itens serao templates puros e quais serao scripts executaveis.

Criterio de aceite:

- a estrutura do repo deixa claro onde vivem templates, exemplos de ambiente e
  scripts de deploy/ops.

Dependencias:

- M0-T1.

Arquivos e areas provaveis:

- `deploy/`
- `scripts/`

### TASK M1-T2 - Versionar templates de Nginx

Estado atual:

- o runbook ja descreve os arquivos, mas eles ainda nao existem no repo.

Subtasks:

1. criar `deploy/nginx/nginx.conf`;
2. criar os 4 vhosts:
   - `eventovivo-landing.conf`
   - `eventovivo-admin.conf`
   - `eventovivo-api.conf`
   - `eventovivo-ws.conf`
3. documentar limites importantes:
   - `client_max_body_size`
   - rate limit da API
   - headers de proxy para websocket
4. explicitar restore do IP real do Cloudflare no Nginx;
5. alinhar isso com `TrustProxies` ou configuracao equivalente no Laravel;
6. adicionar comentarios operacionais minimos dentro dos arquivos;
7. registrar receita de validacao com `nginx -t`.

Criterio de aceite:

- o time consegue instalar os arquivos no Ubuntu 24.04 sem reescrever bloco
  manualmente;
- o design de 4 superficies publicas fica refletido em arquivos versionados.

Dependencias:

- M1-T1.

Arquivos e areas provaveis:

- `deploy/nginx/nginx.conf`
- `deploy/nginx/sites/*.conf`
- `apps/api/bootstrap/app.php`

### TASK M1-T3 - Versionar templates de PHP-FPM, systemd e logrotate

Estado atual:

- o desenho ja esta no runbook, mas ainda nao existe como artefato do repo.

Subtasks:

1. criar `deploy/php-fpm/eventovivo.conf`;
2. criar `deploy/php/opcache-production.ini`;
3. criar:
   - `eventovivo-horizon.service`
   - `eventovivo-reverb.service`
   - `eventovivo-scheduler.service`
   - `eventovivo-scheduler.timer`
4. criar `deploy/redis/eventovivo.conf`;
5. criar `deploy/postgresql/eventovivo.conf`;
6. criar `deploy/logrotate/eventovivo`;
7. documentar limites de `nofile`, restart policy, `ExecReload`, Redis e logs;
8. registrar validacao com:
   - `php-fpm8.3 -tt`
   - `systemd-analyze verify`
   - `redis-cli ping`
   - `pg_isready`

Criterio de aceite:

- os processos permanentes da stack deixam de depender de copia manual de
  trechos soltos da documentacao.

Dependencias:

- M1-T1.

Arquivos e areas provaveis:

- `deploy/php/opcache-production.ini`
- `deploy/php-fpm/eventovivo.conf`
- `deploy/systemd/*.service`
- `deploy/systemd/*.timer`
- `deploy/logrotate/eventovivo`

### TASK M1-T4 - Fechar o contrato de ambientes

Estado atual:

- `apps/api/.env.example`, `apps/web/.env.example` e `apps/landing/.env.example`
  ja existem;
- o contrato de producao ainda nao esta separado por app;
- `apps/api/.env.example` ainda carrega drift entre `REDIS_CLIENT=predis` e o
  default de `config/database.php`, que e `phpredis`.

Subtasks:

1. revisar os `.env.example` das 3 apps;
2. corrigir ou documentar divergencias entre examples e defaults do codigo;
3. criar exemplos operacionais de producao em `deploy/examples/`;
4. separar o que e:
   - variavel obrigatoria
   - variavel opcional
   - variavel apenas de dev/local
5. deixar explicito quais segredos devem viver apenas em
   `/var/www/eventovivo/shared/.env`.
6. deixar explicito que o build de `apps/web` e `apps/landing` depende de
   arquivos dedicados fora da release:
   - `/var/www/eventovivo/shared/apps-web.env.production`
   - `/var/www/eventovivo/shared/apps-landing.env.production`

Criterio de aceite:

- existe um contrato de ambiente claro para API, admin e landing;
- o time nao precisa montar `.env` de producao por memoria.

Dependencias:

- M1-T1.

Arquivos e areas provaveis:

- `apps/api/.env.example`
- `apps/web/.env.example`
- `apps/landing/.env.example`
- `deploy/examples/*.env.production.example`

### TASK M1-T5 - Criar scripts versionados de deploy e operacao

Estado atual:

- nao existe ainda um fluxo de deploy scriptado dentro do repo.

Subtasks:

1. criar `scripts/deploy/deploy.sh` com:
   - criacao de release
   - install de dependencias
   - build de `apps/web` e `apps/landing`
   - `storage:link` ou politica equivalente de `public/storage`
   - caches do Laravel
   - migration
   - switch do symlink
   - recycle controlado de servicos
2. criar `scripts/deploy/rollback.sh`;
3. criar `scripts/deploy/healthcheck.sh`;
4. criar `scripts/deploy/smoke-test.sh`;
5. criar `scripts/ops/bootstrap-host.sh` para o setup base do host, se o time
   quiser automatizar esse passo;
6. criar `scripts/ops/install-configs.sh` para copiar ou linkar templates do
   repo para `/etc`;
7. criar `scripts/ops/verify-host.sh`;
8. validar shell syntax com `bash -n`.

Criterio de aceite:

- o deploy deixa de ser uma sequencia informal de comandos;
- rollback e health check passam a ter implementacao versionada.

Dependencias:

- M1-T2
- M1-T3
- M1-T4

Arquivos e areas provaveis:

- `scripts/deploy/*.sh`
- `scripts/ops/*.sh`

## M2 - Endurecimento da aplicacao antes da VPS

Objetivo:

- preparar o backend e a operacao para producao antes de subir o host.

### TASK M2-T1 - Criar endpoints de health para operacao

Estado atual:

- o runbook recomenda `/health/live` e `/health/ready`, mas eles ainda nao
  existem no backend.

Subtasks:

1. definir o ownership do endpoint:
   - `Shared`
   - ou um modulo operacional explicito
2. criar `/health/live` com resposta leve;
3. criar `/health/ready` checando:
   - banco
   - Redis
   - storage principal
   - configuracao minima de queue
4. proteger para nao virar endpoint caro;
5. adicionar testes de feature.

Criterio de aceite:

- a stack responde um check liveness e readiness sem depender de Horizon,
  websocket ou UI;
- o endpoint permite uso em smoke test e monitoracao externa.

Dependencias:

- M0-T1.

Arquivos e areas provaveis:

- `apps/api/routes/`
- `apps/api/app/Shared/`
- modulo operacional a definir
- testes de feature da API

### TASK M2-T2 - Endurecer `config/queue.php`

Estado atual:

- `block_for` na fila Redis esta `null`;
- `after_commit` na fila Redis esta `false`;
- `retry_after` esta em `90`;
- a conexao Redis de queue e unica, entao `retry_after` vale para a conexao e
  nao para cada fila logica.

Subtasks:

1. adotar `block_for=3` ou `5` por env;
2. adotar `after_commit=true` por env ou por default do ambiente produtivo;
3. revisar `retry_after` no nivel da conexao Redis real;
4. alinhar `timeout` dos workers alguns segundos abaixo do `retry_after`;
5. documentar explicitamente no codigo e no runbook que `retry_after` nao e por
   lane;
6. manter uma conexao de queue unica na fase 1;
7. documentar que DB logico separado nao isola `maxmemory-policy`;
8. abrir backlog de separacao por conexao apenas quando a carga justificar.

Criterio de aceite:

- a conexao Redis de queue passa a bloquear leitura com custo menor de CPU;
- jobs, listeners e broadcasts deixam de correr antes do commit quando
  dependerem de estado persistido;
- nao existe mismatch gritante entre `retry_after` e `timeout`.

Dependencias:

- M1-T4.

Arquivos e areas provaveis:

- `apps/api/config/queue.php`
- `apps/api/.env.example`
- `docs/api/queues.md`

### TASK M2-T3 - Endurecer `config/horizon.php` sem churn de taxonomia

Estado atual:

- as filas principais ja existem e estao separadas;
- quase todos os supervisors ainda operam com `maxJobs=0` e `maxTime=0`;
- `whatsapp-*` ainda nao tem supervisor dedicado;
- o repo ja usa uma taxonomia de filas razoavel, entao renomear agora traria
  mais churn que ganho.

Subtasks:

1. preservar a taxonomia atual:
   - `webhooks`
   - `media-download`
   - `media-fast`
   - `media-process`
   - `media-safety`
   - `media-vlm`
   - `face-index`
   - `media-publish`
   - `broadcasts`
   - `notifications`
   - `analytics`
   - `billing`
2. manter `webhooks`, `media-fast`, `media-publish` e `broadcasts` como lanes
   sagradas;
3. ativar `maxJobs` e `maxTime` nas filas pesadas;
4. revisar `timeout`, `tries`, `memory`, `balanceCooldown` e `balanceMaxShift`;
5. expor tuning por env sempre que fizer sentido;
6. decidir se `analytics` e `billing` continuam junto do supervisor default ou
   merecem desmembramento;
7. adicionar `whatsapp-*` apenas depois de M3.

Criterio de aceite:

- workers pesados passam a ser reciclados;
- filas criticas nao ficam refens de crescimento de processamento pesado;
- o arquivo do Horizon reflete throughput real, nao apenas defaults do
  framework.

Dependencias:

- M2-T2.

Arquivos e areas provaveis:

- `apps/api/config/horizon.php`
- `apps/api/.env.example`
- `docs/api/queues.md`

### TASK M2-T4 - Criar baseline de idempotencia, locks e resiliencia de jobs

Estado atual:

- o uso de `ShouldBeUnique`, `WithoutOverlapping` e middlewares de rate limit
  ainda e muito limitado;
- a deduplicacao de inbound no pipeline geral ainda merece ampliacao, mas o
  `WhatsAppInboundRouter` ja conta com constraint forte em banco para
  `whatsapp_messages(instance_id, direction, provider_message_id)`;
- jobs de imagem ainda precisam de postura mais defensiva em memoria e retry.

Subtasks:

1. mapear jobs que precisam de `ShouldBeUnique`;
2. mapear jobs que precisam de `WithoutOverlapping`;
3. adicionar unique indexes ou constraints nas tabelas de inbound onde fizer
   sentido;
4. aplicar `RateLimitedWithRedis` ou `ThrottlesExceptions` em jobs de provider;
5. revisar `tries` dos jobs que usam locks ou throttling;
6. liberar recursos pesados ao final de jobs de imagem;
7. adicionar testes unitarios e feature para cenarios duplicados.

Criterio de aceite:

- payload repetido ou corrida de processamento nao gera trabalho duplicado
  silencioso;
- provedores externos nao derrubam a lane inteira por avalanche de retry.

Dependencias:

- M2-T2
- M2-T3

Arquivos e areas provaveis:

- `apps/api/app/Modules/WhatsApp/`
- `apps/api/app/Modules/InboundMedia/`
- `apps/api/app/Modules/MediaProcessing/Jobs/`
- `apps/api/database/migrations/`

### TASK M2-T5 - Revisar estrategia de broadcast de alto volume

Estado atual:

- o `Wall` ja usa fila `broadcasts` para boa parte dos eventos;
- sinais operacionais raros ainda usam `ShouldBroadcastNow`, o que pode ser
  aceitavel;
- o feed de moderacao continua em `ShouldBroadcastNow`, o que pesa bem mais em
  cenarios de foto em alta frequencia.

Subtasks:

1. manter `ShouldBroadcastNow` apenas para sinais operacionais raros e pequenos;
2. migrar eventos de foto/moderacao de alto volume para fila `broadcasts`;
3. confirmar `ShouldDispatchAfterCommit` onde o estado persistido fizer parte do
   payload;
4. validar que o wall continua responsivo sem empurrar broadcast de massa para
   a thread imediata da app.

Criterio de aceite:

- o pipeline de foto deixa de depender de broadcast imediato em alta
  frequencia;
- o controle operacional raro continua simples e rapido.

Dependencias:

- M2-T2
- M2-T3

Arquivos e areas provaveis:

- `apps/api/app/Modules/MediaProcessing/Events/`
- `apps/api/app/Modules/MediaProcessing/Services/`
- `apps/api/app/Modules/Wall/Events/`

### TASK M2-T6 - Ligar observabilidade minima obrigatoria

Estado atual:

- a base minima ja existe:
  - `request_id` e `trace_id` propagados por request e job;
  - `queue:monitor` configurado para filas criticas;
  - telemetria de `inbound -> publish` registrada no publish;
  - politica de degradacao operacional implementada por env;
  - gates de `Horizon`, `Telescope` e `Pulse` fechados no app.

Subtasks:

1. propagar `request_id`, `trace_id` ou contexto equivalente para jobs;
2. configurar `queue:monitor` para filas criticas;
3. definir thresholds iniciais de backlog:
   - `webhooks`
   - `media-fast`
   - `media-publish`
   - `broadcasts`
4. registrar latencia de `inbound -> publish`;
5. explicitar politica de degradacao operacional em pico:
   - cortar `media-vlm`
   - pausar `face-index`
   - proteger `webhooks`, `media-fast`, `media-publish` e `broadcasts`
6. proteger `Horizon`, `Telescope` e `Pulse`;
7. alinhar isso com logs rotacionados e dashboards operacionais.

Criterio de aceite:

- o time consegue identificar fila congestionada, job falho recorrente e
  latencia anormal sem depender de investigacao manual no codigo.

Dependencias:

- M2-T1
- M2-T2
- M2-T3

Arquivos e areas provaveis:

- `apps/api/routes/console.php`
- `apps/api/app/`
- `apps/api/config/logging.php`
- `deploy/logrotate/`

## M3 - Fechamento condicional do WhatsApp para producao

Objetivo:

- transformar a borda ja existente de WhatsApp em fluxo produtivo confiavel.

Observacao:

- este milestone e obrigatorio apenas se `WhatsApp inbound` entrar no primeiro
  go-live;
- se a fase 1 subir sem esse canal, M3 pode ficar para a rodada seguinte.
- nesta rodada, o go-live base subiu sem depender de `M3`;
- depois do go-live base, o recorte operacional minimo de Z-API/Pagar.me foi
  retomado em producao para o evento bonificado da Umalu Eventos.

### TASK M3-T1 - Validar autenticidade e persistir o payload cedo

Estado atual:

- o controller ja responde rapido;
- a autenticidade ainda nao esta fechada de ponta a ponta;
- o raw payload ainda nao nasce em uma estrategia clara de quarentena e
  retencao quando a instancia falha.

Subtasks:

1. fechar autenticidade por provider;
2. persistir o payload bruto cedo o suficiente para auditoria tecnica;
3. separar estados:
   - aceito
   - ignorado
   - quarentena
4. aplicar mascaramento do que for sensivel;
5. definir retencao curta e limpeza automatica;
6. gravar motivo de descarte, erro ou roteamento invalido.

Criterio de aceite:

- o time consegue rastrear webhook recebido, inclusive rejeitado, sem abrir um
  passivo operacional de retencao infinita.

Dependencias:

- M2-T4
- M2-T6

Arquivos e areas provaveis:

- `apps/api/app/Modules/WhatsApp/Http/Controllers/`
- `apps/api/app/Modules/WhatsApp/Jobs/`
- `apps/api/app/Modules/WhatsApp/Models/`
- `apps/api/database/migrations/`

### TASK M3-T2 - Fechar o pipeline canonico `WhatsApp -> InboundMedia -> EventMedia`

Estado atual:

- o caminho posterior ainda depende de jobs em scaffold.

Subtasks:

1. fechar `InboundMedia\\Jobs\\ProcessInboundWebhookJob`;
2. fechar `NormalizeInboundMessageJob`;
3. fechar `DownloadInboundMediaJob`;
4. definir regras de normalizacao por tipo de mensagem;
5. garantir que a midia chega em `event_media` com status coerente;
6. criar testes de feature cobrindo mensagem com foto.

Criterio de aceite:

- uma foto recebida via provider consegue atravessar o pipeline e entrar na
  trilha padrao de processamento de midia.

Dependencias:

- M3-T1.

Arquivos e areas provaveis:

- `apps/api/app/Modules/InboundMedia/Jobs/`
- `apps/api/app/Modules/MediaProcessing/Jobs/`
- `apps/api/app/Modules/WhatsApp/Listeners/`
- testes de `InboundMedia` e `WhatsApp`

### TASK M3-T3 - Fechar a fiacao de filas `whatsapp-*`

Estado atual:

- alguns jobs do modulo ja despacham para `whatsapp-inbound`, `whatsapp-send` e
  `whatsapp-sync`;
- em `2026-04-06`, foram adicionados supervisores dedicados para essas filas no
  Horizon;
- em producao, o POST de status da Z-API foi validado e consumido em
  `whatsapp-inbound` com `processing_status=processed`;
- a ponte canonica completa `WhatsApp -> InboundMedia -> EventMedia` ainda segue
  como trabalho de fechamento para midia inbound real de foto.

Subtasks:

1. auditar todo dispatch real do modulo;
2. confirmar quais passos ficam em `webhooks` e quais vao para
   `whatsapp-inbound`;
3. adicionar supervisores `whatsapp-*` no Horizon apenas depois da auditoria;
4. colocar observabilidade dedicada para essas filas;
5. validar que o backlog de WhatsApp nao compete com wall e publish.
6. cadastrar a instancia Z-API de producao no banco com provider, tokens e
   URLs de webhook documentadas.
7. validar endpoint de status com callback seguro antes de liberar inbound real.

Criterio de aceite:

- existe consistencia entre nome da fila, ponto de dispatch, supervisor e
  monitoracao;
- o throughput de WhatsApp nao sacrifica lanes sagradas.
- em producao, `queues:whatsapp-inbound`, `queues:whatsapp-send` e
  `queues:whatsapp-sync` ficam com backlog controlado apos callback de teste.

Dependencias:

- M2-T3
- M3-T2

Arquivos e areas provaveis:

- `apps/api/config/horizon.php`
- `apps/api/config/whatsapp.php`
- `apps/api/app/Modules/WhatsApp/`

## M4 - Bootstrap da VPS

Objetivo:

- provisionar o host com base no que foi versionado no repo.

### TASK M4-T1 - Hardening do sistema base

Subtasks:

1. atualizar Ubuntu 24.04;
2. criar usuario `deploy`;
3. manter `root` apenas para administracao;
4. desabilitar login root por senha;
5. habilitar `ufw` com `22`, `80`, `443`;
6. instalar `fail2ban`;
7. configurar timezone e NTP;
8. habilitar `unattended-upgrades` por APT/timers;
9. instalar `postgresql-16-pgvector`, porque o schema atual depende da
   extensao `vector`.

Criterio de aceite:

- o host nasce minimamente endurecido antes de instalar a stack da aplicacao.

Dependencias:

- M1-T5.

### TASK M4-T2 - Instalar runtime e servicos base

Subtasks:

1. instalar:
   - Nginx
   - PHP-FPM 8.3 e extensoes
   - Composer
   - Node.js 24 LTS
   - PostgreSQL 16
   - `postgresql-16-pgvector`
   - `php8.3-opcache`
   - Redis 7
2. instalar extensoes de Postgres necessarias;
3. validar `php`, `composer`, `node`, `npm`, `psql` e `redis-cli`;
4. manter PostgreSQL e Redis privados;
5. definir `maxmemory-policy` conservadora no Redis e registrar que DB logica
   nao isola eviction.

Criterio de aceite:

- todos os binarios e servicos base estao presentes e operacionais.

Dependencias:

- M4-T1.

### TASK M4-T3 - Preparar estrutura da app e segredos

Subtasks:

1. criar `/var/www/eventovivo`;
2. criar `releases`, `shared` e `scripts`;
3. criar `/var/www/eventovivo/shared/.env`;
4. criar `/var/www/eventovivo/shared/apps-web.env.production`;
5. criar `/var/www/eventovivo/shared/apps-landing.env.production`;
6. preparar `shared/storage`;
7. preparar `shared/bootstrap-cache`;
8. instalar segredos da aplicacao e acessos de provider;
9. validar permissao de `deploy` e `www-data`.

Criterio de aceite:

- o host esta pronto para receber releases sem gravar dados persistentes dentro
  da release.

Dependencias:

- M1-T4
- M1-T5
- M4-T2

### TASK M4-T4 - Instalar configs versionadas do repo no host

Subtasks:

1. instalar configs de Nginx;
2. instalar pool do PHP-FPM;
3. desabilitar o pool padrao `www.conf` do PHP-FPM no host dedicado;
4. instalar units do systemd;
5. instalar sudoers minimo do usuario `deploy`;
6. instalar `logrotate`;
7. validar:
   - `nginx -t`
   - `php-fpm8.3 -tt`
   - `systemd-analyze verify`
   - `redis-cli ping`
   - `pg_isready`
8. manter o site default do Nginx desabilitado;
9. preparar units para habilitacao apenas depois do primeiro deploy
   bem-sucedido.

Criterio de aceite:

- o host passa a depender de templates versionados, nao de configuracao manual.
- o host nao mantem um segundo pool PHP-FPM padrao sem uso.

Dependencias:

- M1-T2
- M1-T3
- M4-T3

### TASK M4-T5 - Fechar Cloudflare e DNS

Subtasks:

1. criar registros `A` proxied para:
   - `@`
   - `www`
   - `admin`
   - `api`
   - `ws`
2. ativar `Full (strict)`;
3. validar websocket proxied;
4. validar IP real do cliente em logs e rate limit;
5. alinhar limites de upload com o plano real do Cloudflare.

Criterio de aceite:

- os 4 hosts publicos ficam roteando corretamente para a maquina.

Dependencias:

- M4-T4.

## M5 - Primeiro deploy funcional

Objetivo:

- subir a stack base na VPS com rollback controlado.

### TASK M5-T1 - Executar primeiro deploy por release

Subtasks:

1. copiar ou clonar o repo para uma release nova;
2. linkar `shared/.env` e `shared/storage`;
3. linkar os envs de build do frontend:
   - `shared/apps-web.env.production`
   - `shared/apps-landing.env.production`
4. instalar dependencias da API;
5. buildar `apps/web` e `apps/landing`;
6. validar no healthcheck que o dist do admin contem:
   - `VITE_API_BASE_URL`
   - `VITE_REVERB_HOST`
7. remover assets Vite do admin que nao sejam referenciados por `index.html`
   ou `sw.js` da release atual;
8. rodar migrations compativeis com zero-downtime;
9. executar `healthcheck.sh`;
10. trocar o symlink `current`.

Observacao operacional:

- build na propria VPS e aceitavel na fase 1;
- se tempo de build ou consumo de CPU comecarem a competir com o produto,
  mover build para CI e publicar artefato vira prioridade.
- depois de hotfix em service worker ou cache do admin, validar headers da
  origin e purgar a Cloudflare se o edge continuar servindo chunks antigos.

Criterio de aceite:

- a release sobe sem editar arquivos manualmente no host.

Dependencias:

- M1-T5
- M4-T4
- M4-T5

### TASK M5-T2 - Reciclar processos e validar servicos

Subtasks:

1. terminar Horizon de forma limpa;
2. reiniciar ou reciclar Reverb;
3. recarregar PHP-FPM;
4. recarregar Nginx;
5. habilitar:
   - `eventovivo-horizon.service`
   - `eventovivo-reverb.service`
   - `eventovivo-scheduler.timer`
   com `enable --now`
6. conferir que o sudoers do usuario `deploy` cobre apenas os `systemctl`
   esperados;
7. conferir `systemctl status` dos servicos;
8. validar logs iniciais.

Criterio de aceite:

- a release nova passa a ser servida sem restart bruto desnecessario e sem
  estado quebrado em workers long-lived.

Dependencias:

- M5-T1.

### TASK M5-T3 - Rodar smoke test do go-live base

Subtasks:

1. validar `eventovivo.com.br`;
2. validar `admin.eventovivo.com.br`;
3. validar `api.eventovivo.com.br/health/live`;
4. validar `api.eventovivo.com.br/health/ready`;
5. validar handshake em `wss://ws.eventovivo.com.br`;
6. validar login admin;
7. validar upload publico;
8. validar que o job entra na fila esperada;
9. validar chegada no wall;
10. validar que pelo menos um evento realtime chega ao cliente conectado.

Criterio de aceite:

- landing, admin, API, websocket, upload e wall funcionam na maquina real.

Dependencias:

- M2-T1
- M5-T2

### TASK M5-T4 - Testar rollback de verdade

Subtasks:

1. apontar `current` para a release anterior;
2. reciclar servicos;
3. rodar smoke test reduzido;
4. documentar o tempo e os passos do rollback.

Observacao operacional:

- rollback validado em producao entre as releases `20260406_130951` e
  `20260406_133407`;
- a validacao incluiu `smoke-test.sh` com login real do `super-admin`,
  `/health/live`, `/health/ready` e handshake websocket;
- a release final ativa apos o teste voltou para `20260406_133407`.

Criterio de aceite:

- rollback deixa de ser hipotese e passa a ser procedimento validado.

Dependencias:

- M5-T3.

## M6 - Hardening de throughput e capacidade

Objetivo:

- preparar a VPS para cenarios como 20 eventos simultaneos e centenas de fotos
  por minuto sem sacrificar ingest, publish e wall.

### TASK M6-T1 - Rodar carga sintetica com cenarios progressivos

Subtasks:

1. definir cenarios de carga:
   - 5 eventos simultaneos
   - 10 eventos simultaneos
   - 20 eventos simultaneos
2. medir bursts de fotos por minuto por evento;
3. medir:
   - backlog por fila
   - latencia `inbound -> publish`
   - latencia de wall
   - uso de CPU
   - uso de RAM
   - IO wait
   - pressao em Redis e PostgreSQL
4. registrar resultados por rodada.

Criterio de aceite:

- existe linha de base real para tuning, e nao apenas chute.

Dependencias:

- M5-T3.

### TASK M6-T2 - Ajustar budgets de runtime com base em carga real

Subtasks:

1. revisar `pm.max_children` do PHP-FPM;
2. revisar `maxProcesses` do Horizon por lane;
3. revisar `retry_after` e `timeout`;
4. revisar Redis:
   - memoria
   - AOF
   - backlog
5. revisar PostgreSQL:
   - buffers
   - cache
   - conexoes
6. revisar uso de disco local e object storage.

Criterio de aceite:

- a VPS sustenta a carga alvo sem starvation das filas sagradas e sem vazamento
  evidente de memoria nos workers.

Dependencias:

- M6-T1.

### TASK M6-T3 - Decidir os primeiros passos de escala horizontal

Subtasks:

1. definir o gatilho para separar um segundo node de workers;
2. definir o gatilho para separar conexoes de queue por perfil de carga;
3. definir o gatilho para mover Redis e PostgreSQL para servicos dedicados;
4. decidir quando `ext-uv` passa a ser necessario no Reverb;
5. decidir quando `FaceSearch` e IA pesada deixam de caber no host unico.

Criterio de aceite:

- existe um plano de crescimento sem refazer deploy, DNS e topologia do zero.

Dependencias:

- M6-T2.

## Gates objetivos de readiness

Antes de chamar a stack de pronta, estes gates precisam estar verdes:

- repo com `deploy/` e `scripts/` versionados;
- restore do IP real do Cloudflare alinhado com trusted proxies;
- `queue.php` endurecido com `block_for` e `after_commit`;
- `horizon.php` endurecido com reciclagem de workers;
- health endpoints ativos;
- Nginx, PHP-FPM e systemd instalados a partir de templates do repo;
- OPcache habilitado para producao;
- deploy e rollback scriptados;
- smoke test do go-live base aprovado;
- backlog controlado nas filas sagradas sob carga sintetica;
- logs rotacionados;
- Cloudflare validado com `Full (strict)`;
- storage persistente fora da release;
- backup diario configurado;
- restore testado pelo menos uma vez;
- `WhatsApp inbound` explicitamente:
  - fora do go-live
  - ou fechado por M3.

## Validacoes obrigatorias por fase

Validacoes de repo:

- `cd apps/api && php artisan test`
- `cd apps/web && npm run type-check`
- `bash -n scripts/deploy/*.sh`
- `bash -n scripts/ops/*.sh`

Validacoes de host:

- `nginx -t`
- `php-fpm8.3 -tt`
- `systemd-analyze verify /etc/systemd/system/eventovivo-horizon.service`
- `systemd-analyze verify /etc/systemd/system/eventovivo-reverb.service`
- `systemd-analyze verify /etc/systemd/system/eventovivo-scheduler.service`
- `systemd-analyze verify /etc/systemd/system/eventovivo-scheduler.timer`

Validacoes de produto em producao:

- login no admin;
- criacao de evento;
- upload publico;
- processamento e publish;
- atualizacao no wall;
- websocket conectado;
- observabilidade respondendo com backlog e falhas por fila.

## Primeira execucao recomendada

Se a execucao comecar agora, a ordem recomendada e esta:

1. fechar `M1-T1` ate `M1-T5`;
2. executar `M2-T1`, `M2-T2` e `M2-T3`;
3. executar `M2-T4`, `M2-T5` e `M2-T6`;
4. decidir se `M3` entra no primeiro go-live ou fica para a rodada seguinte;
5. se `M3` ficar fora do go-live, registrar isso explicitamente e seguir;
6. provisionar a VPS com `M4`;
7. subir o primeiro deploy com `M5`;
8. rodar carga e tuning com `M6`.

Se o objetivo for subir o produto base o quanto antes, a recomendacao pratica e:

- nao travar a subida base esperando `WhatsApp inbound`;
- nao abrir refactor de nomes de fila;
- nao colocar IA pesada local na mesma VPS;
- nao subir a maquina antes de versionar deploy, systemd, Nginx e health checks.

## Sequencia operacional pronta para a VPS

Esta e a sequencia recomendada para a primeira ida a maquina Ubuntu 24.04.

### 1. Bootstrap do host

Executar como `root`:

```bash
cd /tmp
git clone <repo> eventovivo-bootstrap
cd eventovivo-bootstrap
sudo bash scripts/ops/bootstrap-host.sh --enable-ufw
```

Checklist detalhado:

- atualizar `apt` e instalar runtime base;
- instalar `php8.3-opcache`;
- instalar `postgresql-16` e `postgresql-client-16`;
- instalar Redis 7;
- instalar Node.js 24 LTS via NodeSource;
- criar usuario `deploy` se ele ainda nao existir;
- criar `/var/www/eventovivo/{releases,shared,scripts}`;
- preparar `shared/storage`, `shared/bootstrap-cache` e `shared/run/reverb`;
- habilitar `unattended-upgrades` por timers;
- habilitar `nginx`, `php8.3-fpm`, `redis-server`, `postgresql` e `fail2ban`.

### 2. Instalar templates versionados no host

Executar como `root`:

```bash
cd /tmp/eventovivo-bootstrap
sudo bash scripts/ops/install-configs.sh --repo-root "$(pwd)"
```

Checklist detalhado:

- instalar `nginx.conf` e os 4 vhosts;
- instalar `cloudflare-real-ip.conf`;
- instalar `opcache-production.ini`;
- instalar o pool `eventovivo.conf` do PHP-FPM;
- instalar os baselines de Redis e PostgreSQL;
- instalar o sudoers minimo do usuario `deploy`;
- instalar units `systemd` de Horizon, Reverb e scheduler;
- instalar `logrotate`;
- desabilitar o site default do Nginx;
- copiar scripts de `deploy/` e `ops/` para `/var/www/eventovivo/scripts`;
- executar `systemctl daemon-reload`.

### 3. Instalar certificado, aplicar baselines de dados e preparar a app

Executar antes do deploy:

```bash
sudo install -m 600 /path/to/eventovivo-origin.crt /etc/ssl/certs/eventovivo-origin.crt
sudo install -m 600 /path/to/eventovivo-origin.key /etc/ssl/private/eventovivo-origin.key
sudo systemctl restart redis-server
sudo systemctl restart postgresql
sudo mkdir -p /var/www/eventovivo/shared
sudo nano /var/www/eventovivo/shared/.env
sudo chown deploy:www-data /var/www/eventovivo/shared/.env
sudo chmod 640 /var/www/eventovivo/shared/.env
```

Checklist detalhado:

- instalar o certificado de origem antes de validar o Nginx;
- reiniciar Redis e PostgreSQL para aplicar os drop-ins locais;
- preencher `.env` com base em `deploy/examples/apps-api.env.production.example`;
- revisar conexoes de PostgreSQL e Redis;
- revisar URLs de `APP_URL`, `FRONTEND_URL`, `REVERB_*` e `SANCTUM_STATEFUL_DOMAINS`;
- revisar segredos de providers e object storage;
- confirmar que `APP_KEY` esta definido;
- confirmar que `QUEUE_CONNECTION=redis` e `BROADCAST_CONNECTION=reverb`.

### 4. Validar o host antes do primeiro deploy

Executar:

```bash
sudo bash /var/www/eventovivo/scripts/verify-host.sh --require-shared-env
```

Checklist detalhado:

- validar `nginx -t`;
- validar `php-fpm8.3 -tt`;
- validar `systemd-analyze verify`;
- validar `redis-cli ping`;
- validar `pg_isready`;
- validar `composer`, `node`, `npm` e `psql`;
- validar OPcache carregado;
- validar estrutura de `/var/www/eventovivo`.

### 5. Fechar Cloudflare e DNS

Checklist detalhado:

- criar `A` proxied para `@`, `www`, `admin`, `api` e `ws`;
- ativar `Full (strict)`;
- validar `CF-Connecting-IP` e logs com IP real;
- validar `wss://ws.eventovivo.com.br`.

### 6. Executar o primeiro deploy

Executar como `deploy`:

```bash
sudo -iu deploy bash -lc 'cd /tmp/eventovivo-bootstrap && bash scripts/deploy/deploy.sh'
```

Checklist detalhado:

- criar release nova;
- instalar dependencias PHP;
- buildar `apps/web` e `apps/landing`;
- linkar `shared/.env` e `shared/storage`;
- executar `php artisan storage:link` de forma deterministica;
- aquecer caches do Laravel;
- rodar migrations compativeis com zero-downtime;
- executar `healthcheck.sh`;
- trocar o symlink `current`;
- reciclar Horizon e Reverb;
- recarregar PHP-FPM e Nginx.
- usar o sudoers minimo do `deploy` apenas para os `systemctl` previstos.

### 7. Rodar smoke test minimo

Executar:

```bash
bash /var/www/eventovivo/scripts/smoke-test.sh
```

Checklist detalhado:

- landing responde `200`;
- admin carrega;
- `/health/live` responde `200`;
- `/health/ready` responde `200` com DB, Redis e storage operacionais;
- websocket conecta;
- login do admin funciona;
- upload publico persiste;
- job entra na fila esperada;
- wall recebe pelo menos um evento realtime.
