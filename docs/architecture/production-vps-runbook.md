# Runbook de Producao na VPS

## Objetivo

Este documento consolida a documentacao operacional ja existente do Evento Vivo
em uma referencia unica para subir a stack em producao na VPS.

Ele cobre:

- topologia recomendada;
- stack real do repositorio;
- infraestrutura minima da VPS;
- configuracao de Nginx, PHP-FPM, Horizon, Reverb e scheduler;
- estrategia de deploy por releases;
- filas, scaling e operacao sob carga;
- gaps atuais do codigo que impactam o go-live;
- plano de implementacao em fases.

O backlog detalhado de execucao desta estrategia esta em:

- [docs/execution-plans/production-vps-execution-plan.md](../execution-plans/production-vps-execution-plan.md)
- [docs/architecture/production-vps-command-sequence.md](./production-vps-command-sequence.md)

## Fontes base desta consolidacao

Este runbook foi montado a partir destas fontes internas:

- [docs/architecture/landing-page-deployment.md](./landing-page-deployment.md)
- [docs/api/queues.md](../api/queues.md)
- [docs/flows/media-ingestion.md](../flows/media-ingestion.md)
- [docs/flows/whatsapp-inbound.md](../flows/whatsapp-inbound.md)
- [docs/architecture/event-media-intake-architecture.md](./event-media-intake-architecture.md)
- [docs/execution-plans/photo-processing-ai-execution-plan.md](../execution-plans/photo-processing-ai-execution-plan.md)
- [README.md](../../README.md)

Quando houver divergencia entre documentacao antiga e o codigo atual, este
documento assume como referencia o estado real do repositorio em `main`.

## Perfil da VPS de producao

Ambiente alvo informado:

- OS: Ubuntu 24.04
- CPU: 8 vCPU
- RAM: 24 GB
- Storage: 200 GB NVMe
- Link: 600 Mbit/s
- Snapshots: 3

Leitura pratica:

- essa VPS e suficiente para a fase 1 de producao do Evento Vivo;
- ela suporta bem `landing + admin + api + horizon + reverb + redis + postgres`
  no mesmo host;
- ela nao deve assumir o papel de host principal para inferencia pesada de IA;
- ela precisa tratar midia como dado persistente fora da release e, de
  preferencia, fora do disco local principal.

## Estado real da stack

### Backend

- PHP 8.3.x
- Laravel 13
- PostgreSQL 16
- Redis 7
- Horizon
- Reverb
- Sanctum + Fortify
- Telescope + Pulse

### Frontend

- `apps/landing`: React 18 + Vite 5
- `apps/web`: React 18 + Vite 5

### Dominio operacional

- `Wall` e a feature mais madura da plataforma hoje;
- `upload publico` ja funciona;
- `MediaProcessing` tem pipeline forte depois que a midia ja existe em
  `event_media`;
- `WhatsApp` tem borda forte, mas ainda nao fecha toda a ingestao fim a fim;
- parte do admin ainda opera com mocks e precisa ser vista como superficie
  parcialmente integrada.

Observacao importante de runtime:

- o backend foi explicitamente travado em `php >=8.3 <8.4` no
  [composer.json](../../apps/api/composer.json) para manter coerencia com o
  host Ubuntu 24.04 da fase 1;
- sem esse travamento, o Composer podia selecionar componentes `symfony/* 8.x`
  que exigem `php >=8.4`, quebrando o primeiro deploy mesmo com a VPS correta.

## Principios operacionais

Este runbook assume estas decisoes como base:

1. quatro superficies publicas separadas;
2. deploy por releases com symlink `current`;
3. Reverb escutando apenas localmente, publicado via Nginx;
4. filas isoladas por lane de prioridade;
5. storage persistente fora da release;
6. migrations compativeis com zero-downtime;
7. observabilidade minima desde o primeiro deploy.

## Topologia publica recomendada

### Hosts publicos

- `eventovivo.com.br` -> landing
- `www.eventovivo.com.br` -> redirect para landing principal
- `admin.eventovivo.com.br` -> painel administrativo
- `api.eventovivo.com.br` -> API Laravel
- `ws.eventovivo.com.br` -> Reverb atras do Nginx

### Servicos no mesmo host nesta fase

Pode permanecer no mesmo host:

- Nginx
- PHP-FPM
- Laravel API
- Horizon
- Reverb
- Redis
- PostgreSQL

### Servicos que nao devem ser o foco do mesmo host agora

Nao recomendo colocar como caminho critico neste mesmo host:

- `vLLM` local para `MediaIntelligence`;
- MinIO local como storage principal de longo prazo;
- pipeline pesada de IA sem isolamento;
- ingestao oficial de WhatsApp antes de fechar os gaps do modulo.

## Layout recomendado da VPS

```text
/var/www/eventovivo
|-- current -> /var/www/eventovivo/releases/20260404_230000
|-- releases
|   |-- 20260404_230000
|   |-- 20260405_010000
|   `-- ...
|-- shared
|   |-- .env
|   |-- storage
|   |   |-- app
|   |   |   `-- public
|   |   |-- framework
|   |   |   |-- cache/data
|   |   |   |-- sessions
|   |   |   |-- testing
|   |   |   `-- views
|   |   `-- logs
|   |-- bootstrap-cache
|   `-- run
|       `-- reverb
`-- scripts
    |-- deploy.sh
    |-- rollback.sh
    `-- healthcheck.sh
```

### Observacoes importantes

- `shared/storage` e obrigatorio porque parte do codigo ainda grava em disco
  `public` local;
- `shared/storage/app/public` e obrigatorio quando `FILESYSTEM_DISK=public`
  estiver ativo no go-live base;
- a release nunca deve ser usada como storage primario de midia ou logs;
- scripts de deploy e rollback devem viver fora das releases.
- se `deploy.sh` rodar com o usuario `deploy`, o host precisa de um sudoers
  minimo para os `systemctl` usados no recycle de servicos.
- numa VPS dedicada ao Evento Vivo, o pool padrao `www.conf` do PHP-FPM deve
  ser desabilitado para evitar socket extra, workers ociosos e ambiguidade
  operacional.
- se o object storage externo ainda nao estiver disponivel no primeiro deploy,
  a fase 1 pode operar com `FILESYSTEM_DISK=public` em cima de `shared/storage`;
  quando S3 estiver pronto, o `.env` pode migrar para `FILESYSTEM_DISK=s3`.

## Cloudflare

### Configuracao recomendada

- criar registros `A` proxied para `@`, `www`, `admin`, `api` e `ws`;
- manter proxy ligado;
- usar SSL/TLS `Full (strict)`;
- instalar certificado de origem valido no Nginx;
- manter WebSockets habilitados na zona;
- alinhar limites de upload com o plano Cloudflare real.

### Pontos operacionais

- `Full (strict)` e o modo recomendado pela propria Cloudflare para origem com
  certificado valido;
- se usar Origin CA, o certificado so vale entre Cloudflare e origin;
- se o plano for Free ou Pro, o limite padrao de request body proxied e
  100 MB; Business sobe para 200 MB; Enterprise para 500+ MB;
- se um fluxo exigir upload maior que isso, a arquitetura precisa quebrar o
  upload em partes ou usar record sem proxy.

### IP real do cliente e trusted proxies

Se o trafego entrar pelo Cloudflare, o host precisa restaurar o IP real do
cliente e nao operar apenas com o IP do proxy.

Isso precisa existir em dois lugares:

- Nginx:
  - confiar apenas nas redes oficiais do Cloudflare com `set_real_ip_from`;
  - usar `real_ip_header CF-Connecting-IP`;
  - habilitar `real_ip_recursive on`;
- Laravel:
  - alinhar `TrustProxies` ou configuracao equivalente no bootstrap;
  - garantir que logs, rate limit e auditoria enxerguem o IP real do cliente.

Sem isso, estes sintomas aparecem cedo:

- rate limit distorcido;
- logs menos uteis;
- auditoria de origem ruim;
- regras por IP inconsistentes entre edge, Nginx e app.

Referencias oficiais:

- https://developers.cloudflare.com/ssl/origin-configuration/ssl-modes/full-strict/
- https://developers.cloudflare.com/ssl/origin-configuration/origin-ca/
- https://developers.cloudflare.com/network/websockets/
- https://developers.cloudflare.com/workers/platform/limits/
- https://developers.cloudflare.com/cache/how-to/purge-cache/
- https://developers.cloudflare.com/support/troubleshooting/restoring-visitor-ips/restoring-original-visitor-ips/
- https://www.cloudflare.com/ips/

### Cache do admin SPA e Cloudflare

O admin e uma SPA com service worker. Por isso, HTML e arquivos de bootstrap do
PWA nao podem ser servidos com cache imutavel.

Regras obrigatorias:

- `/`, `/index.html`, `/sw.js` e `/manifest.webmanifest` devem responder com
  `Cache-Control: no-cache, no-store, must-revalidate`;
- assets versionados em `/assets/*.js` e `/assets/*.css` podem continuar com
  cache longo e `immutable`;
- depois de corrigir cache ou service worker, purgar pelo menos:
  - `https://admin.eventovivo.com.br/sw.js`
  - `https://admin.eventovivo.com.br/`
  - os chunks antigos que ainda aparecerem como `CF-Cache-Status: HIT`
- se houver muitos chunks antigos ou se o painel continuar servindo bundle
  antigo, usar `Purge Everything` na zona como fallback operacional.

Sintomas de cache errado:

- o navegador carrega chunks removidos da release atual;
- `curl` direto na origin retorna `404`, mas a URL publica via Cloudflare ainda
  retorna `200` com `CF-Cache-Status: HIT`;
- a tela do admin mostra erros de runtime que ja foram corrigidos na release
  atual.

## Organizacao inicial da VPS

### Usuarios

Separacao recomendada:

- `root` -> administracao do host
- `deploy` -> releases e automacao de deploy
- `www-data` -> execucao de Nginx, PHP-FPM e servicos da app

### Permissoes iniciais

```bash
sudo mkdir -p /var/www/eventovivo/{releases,shared,scripts}
sudo chown -R deploy:www-data /var/www/eventovivo
sudo find /var/www/eventovivo -type d -exec chmod 775 {} \;
```

### Hardening minimo

- SSH apenas com chave;
- desabilitar login root por senha;
- `ufw` abrindo apenas `22`, `80`, `443`;
- Redis e PostgreSQL escutando apenas em localhost ou rede privada;
- `fail2ban` ativo;
- swap pequena apenas para emergencia;
- `unattended-upgrades` habilitado;
- timezone e NTP corretos.

## Pacotes do host

### Base

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y \
  ca-certificates curl fail2ban git jq logrotate nginx redis-server redis-tools rsync \
  postgresql-16 postgresql-client-16 postgresql-contrib ufw unzip \
  php8.3-fpm php8.3-cli php8.3-pgsql php8.3-redis php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd php8.3-exif \
  php8.3-opcache composer
```

### Dependencias adicionais

- Node.js 24 LTS via metodo de instalacao atualizado
- npm

Observacao:

- no Ubuntu 24.04, o pacote padrao da distro nao acompanha o LTS mais novo do
  Node.js;
- para a fase 1, o repo provisiona Node.js 24 LTS via NodeSource;
- isso e uma escolha operacional inferida a partir do estado atual dos pacotes
  e da linha LTS do Node, nao uma exigencia do framework.

### Dependencias opcionais

- `htop`, `iotop`, `ncdu`, `pg_activity`, `redis-tools`

## Banco, Redis e storage

### PostgreSQL

#### Papel

- verdade transacional do produto;
- migrations Laravel;
- tabelas de dominio;
- `pgvector` como requisito do schema atual, por causa da migration de faces.

#### Recomendacoes

- usar PostgreSQL 16;
- garantir o pacote `postgresql-16-pgvector` no host;
- criar extensoes `uuid-ossp`, `pgcrypto` e `vector`;
- bind em localhost ou interface privada;
- backup diario e teste de restore.

#### Tuning inicial sugerido

Comeco conservador para 24 GB de RAM:

- `shared_buffers = 4GB`
- `effective_cache_size = 12GB`
- `work_mem = 16MB`
- `maintenance_work_mem = 512MB`
- `wal_compression = on`
- `max_connections` controlado

Observacao:

- isso e ponto de partida, nao tuning final;
- revise a partir de uso real de filas, dashboard e queries de eventos.
- o estado atual das migrations ja executa `CREATE EXTENSION vector`, entao o
  host de producao precisa ter `pgvector` disponivel mesmo que `FaceSearch`
  fique desativado funcionalmente no primeiro go-live.
- no Ubuntu 24.04, `postgresql.service` e um wrapper; use `pg_lsclusters` e
  `pg_isready` como checagem real do cluster `16/main`.

### Redis

#### Papel

- filas;
- cache;
- sessao;
- Horizon;
- Reverb scaling channel quando ativado.

#### Recomendacoes

- Redis 7 com `appendonly yes`;
- `bind 127.0.0.1` ou rede privada;
- se Redis ficar estritamente em localhost, loopback + `protected-mode yes` ja
  e o baseline da fase 1;
- se Redis sair de localhost ou rede privada estrita, senha ou ACL passa a ser
  obrigatoria e deve entrar por override local nao versionado;
- memoria maxima configurada com politica explicita.

#### Separacao logica

O codigo hoje ja suporta pelo menos:

- `REDIS_DB=0` para app, filas e Horizon
- `REDIS_CACHE_DB=1` para cache

Recomendacao adicional:

- `SESSION_CONNECTION=cache` para manter sessao fora do DB principal do Redis;
- revisar depois se vale separar sessao em outro Redis ou outra base.

#### Eviction e isolamento operacional

Ponto importante:

- DB logico diferente nao isola politica de memoria;
- `maxmemory-policy` vale para a instancia Redis, nao para cada DB.

Consequencia pratica:

- se queue, cache e sessao compartilham a mesma instancia, uma politica de
  eviction agressiva pode distorcer o comportamento de cache e comprometer a
  previsibilidade operacional;
- para a fase 1, prefira politica conservadora e folga real de memoria;
- `noeviction` tende a ser o baseline mais seguro quando queue e sessao dividem
  a mesma instancia;
- quando cache comecar a competir com queue ou sessao, a resposta correta e
  separar instancia ou servico, e nao apertar mais a politica de eviction.

### Storage de midia

#### Regra

**Nao usar a release como storage real de midia.**

#### Leitura do codigo atual

Hoje o projeto mistura dois modos:

- parte da stack ja esta preparada para S3-compatible;
- o upload publico ainda grava em `disk=public` local.

Consequencia:

- `shared/storage` continua obrigatorio;
- mesmo assim, o alvo de maturidade deve ser object storage externo para
  originais e variantes publicas.

#### Recomendacao pratica

Fase 1:

- manter `shared/storage` operacional;
- garantir `shared/storage/app/public`;
- servir o que ainda usa `public`;
- garantir politica deterministica para `public/storage`, via `storage:link`
  na primeira instalacao ou passo idempotente equivalente no deploy;
- planejar migracao gradual das midias para object storage.

Fase 2:

- mover uploads e variantes publicas para S3-compatible;
- deixar local apenas para cache tecnico, sessions e logs temporarios.

## Semantica de filas que realmente faz sentido no estado atual

### Preservar a taxonomia atual do repositorio

Para a fase 1, nao faz sentido renomear todas as filas ou abrir uma segunda
taxonomia conceitual.

O que faz sentido e:

- preservar filas ja consolidadas como `webhooks`, `media-download`,
  `media-fast`, `media-process`, `media-safety`, `media-vlm`, `face-index`,
  `media-publish`, `broadcasts`, `notifications`, `analytics` e `billing`;
- endurecer `queue.php`, `horizon.php`, dispatch real e observabilidade;
- evitar agrupar cedo demais varias filas criticas no mesmo supervisor sem
  evidencia de carga que justifique isso.

### Webhook deve admitir e sair rapido

Regra operacional:

- validar autenticidade o mais cedo possivel;
- persistir o payload bruto o quanto antes;
- registrar uma chave idempotente tecnica;
- responder rapido;
- deixar download, normalizacao pesada, transformacao, IA, publish e broadcast
  para jobs assicronos.

No estado atual do repo, o modulo `WhatsApp` ja segue parcialmente essa linha no
controller. O ganho principal nao esta em mexer no controller, e sim em fechar a
orquestracao posterior e endurecer a topologia de filas.

### Persistencia de payload bruto: com quarentena e retencao

Persistir raw payload faz sentido para observabilidade e replay tecnico, mas
deve obedecer estas regras:

- retencao curta;
- mascaramento do que for sensivel;
- chave idempotente tecnica;
- motivo do descarte ou da falha de roteamento;
- diferenciacao entre payload aceito, ignorado e payload em quarentena.

Isso evita trocar um problema de rastreabilidade por outro de volume e ruido.

### Idempotencia em tres camadas

Para webhook e ingestao de midia, o mais robusto e combinar:

1. chave idempotente logica derivada do payload ou do provider;
2. unique index ou constraint forte no banco;
3. lock no job quando houver risco de corrida de processamento.

Consulta previa sozinha nao basta quando dois workers observam a mesma carga ao
mesmo tempo.

### `retry_after` e por conexao, nao por fila logica

Ponto importante:

- `retry_after` pertence a conexao de queue;
- ele nao e ajustado por fila de forma automatica.

Consequencia pratica:

- se filas muito diferentes compartilham a mesma conexao Redis, o
  `retry_after` precisa cobrir o pior caso aceitavel daquela conexao;
- se a operacao exigir perfis muito diferentes, a topologia precisa evoluir
  para conexoes de queue separadas por grupo de filas.

Para a fase 1, faz sentido:

- manter uma conexao Redis de queue unica;
- ajustar `retry_after` com folga realista;
- manter `timeout` dos workers alguns segundos abaixo dele.

Para a fase 2, se as lanes pesadas crescerem:

- separar conexoes de queue para grupos de filas com perfis muito diferentes.

### `block_for` e `after_commit`

Para o estado atual do Evento Vivo, faz sentido adotar:

- `block_for=3` ou `5` na conexao Redis de queue;
- `after_commit=true` na conexao Redis de queue.

Beneficios:

- menos polling agressivo e CPU desperdicada;
- menor risco de jobs, listeners e broadcasts observarem estado ainda nao
  persistido.

### `ShouldBroadcastNow`: usar com criterio

Nao faz sentido proibir todo broadcast imediato.

O recorte correto e:

- sinais operacionais raros podem continuar imediatos;
- fluxo de foto em alta frequencia nao deve usar `ShouldBroadcastNow`.

No estado atual do repo, isso indica:

- `WallStatusChanged`, `WallSettingsUpdated`, `WallExpired` e sinais equivalentes
  continuam candidatos aceitaveis para imediato;
- broadcasts de moderacao e pipeline de foto devem migrar para fila dedicada
  quando o volume justificar.

### Supervisores `whatsapp-*`: so com a fiacao fechada

Adicionar supervisores `whatsapp-inbound`, `whatsapp-send` e `whatsapp-sync`
faz sentido, mas apenas quando:

- o dispatch real para essas filas estiver consistente ponta a ponta;
- a orquestracao posterior nao estiver mais caindo em caminhos incompletos;
- a observabilidade desse fluxo estiver minimamente fechada.

Criar o supervisor antes de fechar a fiacao inteira passa sensacao de ordem,
mas nao resolve throughput real.

## Nginx global

Arquivo sugerido: `/etc/nginx/nginx.conf`

```nginx
user www-data;
worker_processes auto;
worker_rlimit_nofile 65535;
pid /run/nginx.pid;

events {
    worker_connections 4096;
    multi_accept on;
    use epoll;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    server_tokens off;
    charset utf-8;

    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 30;
    keepalive_requests 1000;
    types_hash_max_size 4096;
    client_max_body_size 100M;
    client_body_timeout 30s;
    client_header_timeout 30s;
    send_timeout 30s;
    reset_timedout_connection on;

    open_file_cache max=200000 inactive=20s;
    open_file_cache_valid 30s;
    open_file_cache_min_uses 2;
    open_file_cache_errors on;

    gzip on;
    gzip_comp_level 5;
    gzip_min_length 1024;
    gzip_proxied any;
    gzip_vary on;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/json
        application/javascript
        application/xml+rss
        application/xml
        image/svg+xml;

    log_format main '$remote_addr - $remote_user [$time_local] '
                    '"$request" $status $body_bytes_sent '
                    '"$http_referer" "$http_user_agent" '
                    'rt=$request_time uct="$upstream_connect_time" '
                    'uht="$upstream_header_time" urt="$upstream_response_time"';

    access_log /var/log/nginx/access.log main;
    error_log /var/log/nginx/error.log warn;

    limit_req_zone $binary_remote_addr zone=api_limit:10m rate=20r/s;
    limit_conn_zone $binary_remote_addr zone=conn_limit:10m;

    map $http_upgrade $connection_upgrade {
        default upgrade;
        ''      close;
    }

    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}
```

### Notas

- `worker_rlimit_nofile` ajuda quando o volume de conexoes WebSocket cresce;
- `client_max_body_size` precisa respeitar os limites do Cloudflare;
- restaure o IP real do Cloudflare no Nginx antes de aplicar rate limit ou
  auditar origem;
- nao exponha Reverb diretamente ao publico.

## VHosts Nginx

### Landing

Arquivo: `/etc/nginx/sites-available/eventovivo-landing.conf`

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name eventovivo.com.br www.eventovivo.com.br;

    return 301 https://eventovivo.com.br$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name www.eventovivo.com.br;

    return 301 https://eventovivo.com.br$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name eventovivo.com.br;

    root /var/www/eventovivo/current/apps/landing/dist;
    index index.html;

    ssl_certificate     /etc/ssl/certs/eventovivo-origin.crt;
    ssl_certificate_key /etc/ssl/private/eventovivo-origin.key;

    access_log /var/log/nginx/eventovivo-landing.access.log main;
    error_log  /var/log/nginx/eventovivo-landing.error.log warn;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|svg|webp|ico|woff|woff2)$ {
        expires 30d;
        access_log off;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
}
```

### Admin SPA

Arquivo: `/etc/nginx/sites-available/eventovivo-admin.conf`

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name admin.eventovivo.com.br;

    return 301 https://admin.eventovivo.com.br$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name admin.eventovivo.com.br;

    root /var/www/eventovivo/current/apps/web/dist;
    index index.html;

    ssl_certificate     /etc/ssl/certs/eventovivo-origin.crt;
    ssl_certificate_key /etc/ssl/private/eventovivo-origin.key;

    access_log /var/log/nginx/eventovivo-admin.access.log main;
    error_log  /var/log/nginx/eventovivo-admin.error.log warn;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location = /sw.js {
        expires off;
        add_header Cache-Control "no-cache, no-store, must-revalidate" always;
        try_files /sw.js =404;
    }

    location = /manifest.webmanifest {
        expires off;
        add_header Cache-Control "no-cache, no-store, must-revalidate" always;
        try_files /manifest.webmanifest =404;
    }

    location = /index.html {
        expires off;
        add_header Cache-Control "no-cache, no-store, must-revalidate" always;
        try_files /index.html =404;
    }

    location / {
        expires off;
        add_header Cache-Control "no-cache, no-store, must-revalidate" always;
        try_files $uri $uri/ /index.html;
    }

    location @admin_asset_not_found {
        expires off;
        add_header Cache-Control "no-cache, no-store, must-revalidate" always;
        return 404;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|svg|webp|ico|woff|woff2)$ {
        access_log off;
        add_header Cache-Control "public, immutable";
        try_files $uri @admin_asset_not_found;
    }
}
```

### API Laravel

Arquivo: `/etc/nginx/sites-available/eventovivo-api.conf`

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name api.eventovivo.com.br;

    return 301 https://api.eventovivo.com.br$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name api.eventovivo.com.br;

    root /var/www/eventovivo/current/apps/api/public;
    index index.php;

    ssl_certificate     /etc/ssl/certs/eventovivo-origin.crt;
    ssl_certificate_key /etc/ssl/private/eventovivo-origin.key;

    access_log /var/log/nginx/eventovivo-api.access.log main;
    error_log  /var/log/nginx/eventovivo-api.error.log warn;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    limit_req zone=api_limit burst=40 nodelay;
    limit_conn conn_limit 50;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(env|log|ini|sql|sh|lock|md)$ {
        deny all;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm-eventovivo.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_read_timeout 120s;
        fastcgi_send_timeout 120s;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|svg|webp|ico|woff|woff2)$ {
        expires 7d;
        access_log off;
        add_header Cache-Control "public";
        try_files $uri =404;
    }
}
```

### WebSocket / Reverb

Arquivo: `/etc/nginx/sites-available/eventovivo-ws.conf`

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name ws.eventovivo.com.br;

    return 301 https://ws.eventovivo.com.br$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ws.eventovivo.com.br;

    ssl_certificate     /etc/ssl/certs/eventovivo-origin.crt;
    ssl_certificate_key /etc/ssl/private/eventovivo-origin.key;

    access_log /var/log/nginx/eventovivo-ws.access.log main;
    error_log  /var/log/nginx/eventovivo-ws.error.log warn;

    location / {
        proxy_http_version 1.1;
        proxy_pass http://127.0.0.1:8080;

        proxy_set_header Host $host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;

        proxy_read_timeout 3600;
        proxy_send_timeout 3600;
        proxy_connect_timeout 60;
        proxy_buffering off;
    }
}
```

## PHP-FPM

### Pool dedicado recomendado

Arquivo: `/etc/php/8.3/fpm/pool.d/eventovivo.conf`

```ini
[eventovivo]
user = www-data
group = www-data

listen = /run/php/php8.3-fpm-eventovivo.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 28
pm.start_servers = 6
pm.min_spare_servers = 4
pm.max_spare_servers = 10
pm.max_requests = 500

request_terminate_timeout = 120s
request_slowlog_timeout = 5s
slowlog = /var/log/php8.3-fpm-eventovivo-slow.log

php_admin_value[error_log] = /var/log/php8.3-fpm-eventovivo-error.log
php_admin_flag[log_errors] = on

php_value[upload_max_filesize] = 64M
php_value[post_max_size] = 64M
php_value[memory_limit] = 512M
php_value[max_execution_time] = 120
php_value[max_input_time] = 120
php_value[date.timezone] = America/Sao_Paulo
```

### Motivos para usar pool dedicado

- isolamento de logs;
- tuning independente;
- base melhor para crescer para outros apps;
- evita misturar comportamento com o pool default do host.

### Leitura correta de `pm.max_children`

`pm.max_children = 28` deve ser tratado como ponto de partida para esta VPS, e
nao como numero fechado.

Regra pratica:

- iniciar nessa faixa;
- medir RSS real dos workers em carga;
- revisar depois dos primeiros testes de throughput.

Isso importa porque o mesmo host ainda vai sustentar Nginx, Horizon, Reverb,
Redis e PostgreSQL.

### OPcache em producao

Tratar OPcache como requisito operacional, nao como detalhe opcional.

Minimo recomendado:

- OPcache habilitado em producao;
- `memory_consumption` e `max_accelerated_files` revistos para o monorepo;
- invalidacao coerente com deploy por release;
- reload de PHP-FPM como mecanismo deterministico de troca de codigo, sem
  depender apenas de timestamp.

## Services systemd

### Horizon

Arquivo: `/etc/systemd/system/eventovivo-horizon.service`

```ini
[Unit]
Description=Eventovivo Laravel Horizon
After=network.target redis-server.service postgresql.service php8.3-fpm.service
Wants=redis-server.service postgresql.service

[Service]
Type=simple
User=www-data
Group=www-data
UMask=0002
LimitNOFILE=65535
Restart=always
RestartSec=5
WorkingDirectory=/var/www/eventovivo/current/apps/api

ExecStart=/usr/bin/php artisan horizon
ExecReload=/usr/bin/php artisan horizon:terminate
ExecStop=/usr/bin/php artisan horizon:terminate

TimeoutStopSec=60
KillSignal=SIGTERM

StandardOutput=append:/var/log/eventovivo-horizon.log
StandardError=append:/var/log/eventovivo-horizon-error.log

[Install]
WantedBy=multi-user.target
```

### Reverb

Arquivo: `/etc/systemd/system/eventovivo-reverb.service`

```ini
[Unit]
Description=Eventovivo Laravel Reverb
After=network.target redis-server.service php8.3-fpm.service
Wants=redis-server.service

[Service]
Type=simple
User=www-data
Group=www-data
UMask=0002
LimitNOFILE=65535
Restart=always
RestartSec=5
WorkingDirectory=/var/www/eventovivo/current/apps/api

ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
ExecReload=/usr/bin/php artisan reverb:restart
ExecStop=/bin/kill -s TERM $MAINPID

TimeoutStopSec=30
KillSignal=SIGTERM

StandardOutput=append:/var/log/eventovivo-reverb.log
StandardError=append:/var/log/eventovivo-reverb-error.log

[Install]
WantedBy=multi-user.target
```

### Scheduler via systemd timer

Service: `/etc/systemd/system/eventovivo-scheduler.service`

```ini
[Unit]
Description=Eventovivo Laravel Scheduler

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=/var/www/eventovivo/current/apps/api
ExecStart=/usr/bin/php artisan schedule:run
StandardOutput=append:/var/log/eventovivo-scheduler.log
StandardError=append:/var/log/eventovivo-scheduler-error.log
```

Timer: `/etc/systemd/system/eventovivo-scheduler.timer`

```ini
[Unit]
Description=Run Eventovivo Scheduler every minute

[Timer]
OnCalendar=*-*-* *:*:00
Persistent=true
Unit=eventovivo-scheduler.service

[Install]
WantedBy=timers.target
```

### Ativacao

```bash
sudo systemctl daemon-reload
sudo systemctl enable eventovivo-horizon.service
sudo systemctl enable eventovivo-reverb.service
sudo systemctl enable eventovivo-scheduler.timer
```

Observacao:

- instalar e habilitar as units pode acontecer em `M4`;
- iniciar ou reciclar `Horizon`, `Reverb` e `scheduler` de forma confiavel
  depende de o symlink `current` ja apontar para uma release valida;
- por isso, o primeiro start pratico dessas units deve acontecer junto do
  primeiro deploy funcional em `M5`.

### Endurecimentos recomendados

- `LimitNOFILE` e especialmente importante para Reverb e para a combinacao
  Nginx + WebSocket;
- `UMask=0002` ajuda a manter a politica de permissao coerente com
  `deploy:www-data`;
- `ExecReload` faz sentido quando o deploy quiser reciclar processos sem depender
  apenas de restart bruto;
- logs desses services precisam de rotacao explicita.

### Por que scheduler e obrigatorio

O projeto agenda pelo menos:

- `horizon:snapshot` a cada 5 minutos;
- limpeza diaria de snapshots do `Wall`.

Sem scheduler, parte da observabilidade e da manutencao operacional se perde.

## Variaveis de ambiente de producao

### Regra importante

O arquivo principal de producao deve viver em:

```bash
/var/www/eventovivo/shared/.env
```

### Referencias de base

- usar `/.env.example` da raiz como base operacional;
- usar `apps/api/.env.example` como referencia de variaveis reais da API;
- usar `apps/web/.env.example` para o admin;
- usar `apps/landing/.env.example` para a landing;
- complementar esse material com exemplos operacionais de producao em
  `deploy/examples/`.

### API

Variaveis que precisam ser revistas antes do primeiro go-live:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://api.eventovivo.com.br`
- `FRONTEND_URL=https://admin.eventovivo.com.br`
- `DB_CONNECTION=pgsql`
- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `SESSION_DRIVER=redis`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_DOMAIN=.eventovivo.com.br` se a estrategia de cookie pedir isso
- `SANCTUM_STATEFUL_DOMAINS=admin.eventovivo.com.br`
- `BROADCAST_CONNECTION=reverb`
- `FILESYSTEM_DISK=s3` quando o fluxo de object storage estiver fechado
- `REVERB_HOST=ws.eventovivo.com.br`
- `REVERB_PORT=443`
- `REVERB_SCHEME=https`
- `REVERB_ALLOWED_ORIGINS=https://admin.eventovivo.com.br,https://eventovivo.com.br`

### Admin

- `VITE_API_BASE_URL=https://api.eventovivo.com.br/api/v1`
- `VITE_REVERB_APP_KEY=<key>`
- `VITE_REVERB_HOST=ws.eventovivo.com.br`
- `VITE_REVERB_PORT=443`
- `VITE_REVERB_SCHEME=https`
- `VITE_USE_MOCK=false`

### Landing

- `VITE_PUBLIC_SITE_URL=https://eventovivo.com.br`
- `VITE_ADMIN_URL=https://admin.eventovivo.com.br`
- `VITE_PRIMARY_CTA_URL`
- `VITE_WHATSAPP_NUMBER`
- `VITE_WHATSAPP_MESSAGE`
- `VITE_INSTAGRAM_URL`
- `VITE_LINKEDIN_URL`

## Deploy por releases

### Regra principal

Zero-downtime de verdade depende de migrations backward-compatible.

Exemplos:

- adicionar coluna nullable primeiro;
- nao fazer rename destrutivo no mesmo deploy;
- nao remover campo legado na mesma release em que o app novo depende do campo
  novo.

### Pre-requisitos permanentes

Antes do primeiro deploy, deixar pronto:

- `/var/www/eventovivo/shared/.env`
- `/var/www/eventovivo/shared/apps-web.env.production`
- `/var/www/eventovivo/shared/apps-landing.env.production`
- `/var/www/eventovivo/shared/storage`
- banco criado
- Redis pronto
- Nginx e PHP-FPM ativos
- services `systemd` criados
- object storage definido ou fallback documentado

### Fluxo de deploy

#### 1. Criar release nova

```bash
RELEASE=$(date +%Y%m%d_%H%M%S)
mkdir -p /var/www/eventovivo/releases/$RELEASE
```

#### 2. Subir codigo da release

Pode ser via `git clone`, `git archive`, `rsync` ou CI/CD.

#### 3. Linkar `.env` e storage compartilhado

```bash
cd /var/www/eventovivo/releases/$RELEASE/apps/api

ln -nfs /var/www/eventovivo/shared/.env .env
ln -nfs /var/www/eventovivo/shared/apps-web.env.production ../web/.env.production.local
ln -nfs /var/www/eventovivo/shared/apps-landing.env.production ../landing/.env.production.local
rm -rf storage
ln -nfs /var/www/eventovivo/shared/storage storage
mkdir -p bootstrap/cache
```

#### 4. Instalar dependencias PHP

```bash
cd /var/www/eventovivo/releases/$RELEASE/apps/api
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
if [ -L public/storage ]; then rm -f public/storage; fi
php artisan storage:link
```

#### 5. Build do frontend

```bash
cd /var/www/eventovivo/releases/$RELEASE/apps/web
npm ci
npm run build

cd /var/www/eventovivo/releases/$RELEASE/apps/landing
npm ci
npm run build
```

Observacao:

- build na propria VPS e aceitavel na fase 1;
- se o tempo de deploy ou o consumo de CPU comecarem a competir com o produto
  em horario de evento, mover build para CI e publicar artefato passa a ser a
  escolha correta.

#### 6. Warmup do Laravel

```bash
cd /var/www/eventovivo/releases/$RELEASE/apps/api

php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear || true

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true
```

#### 7. Rodar migrations

```bash
php artisan migrate --force
```

#### 8. Health check da release

Script sugerido: `/var/www/eventovivo/scripts/healthcheck.sh`

```bash
#!/usr/bin/env bash
set -e

cd "$1/apps/api"

php artisan about > /dev/null
test -f public/index.php
test -f ../web/dist/index.html
test -f ../landing/dist/index.html
```

#### 9. Switch atomico do symlink

```bash
ln -nfs /var/www/eventovivo/releases/$RELEASE /var/www/eventovivo/current
```

#### 10. Reiniciar processos long-running de forma limpa

```bash
cd /var/www/eventovivo/current/apps/api

php artisan horizon:terminate || true
php artisan reverb:restart || true

sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx
sudo systemctl restart eventovivo-horizon
sudo systemctl restart eventovivo-reverb
```

#### 11. Smoke tests

Validar imediatamente:

- landing responde `200`;
- admin carrega o build atual sem asset quebrado;
- `https://api.eventovivo.com.br/health/live` responde `200`;
- `https://api.eventovivo.com.br/health/ready` valida DB, Redis e storage;
- login admin funciona;
- upload publico persiste o registro esperado;
- o job entra na fila esperada;
- publish atualiza o wall;
- o handshake de `wss://ws.eventovivo.com.br` funciona e pelo menos um evento
  realtime chega ao cliente conectado.

#### 12. Limpeza de releases antigas

```bash
cd /var/www/eventovivo/releases
ls -1dt */ | tail -n +6 | xargs -r rm -rf
```

## Rollback

### 1. Listar releases

```bash
ls -1dt /var/www/eventovivo/releases/*
```

### 2. Reapontar `current`

```bash
ln -nfs /var/www/eventovivo/releases/RELEASE_ANTERIOR /var/www/eventovivo/current
```

### 3. Reiniciar processos

```bash
cd /var/www/eventovivo/current/apps/api

php artisan horizon:terminate || true
php artisan reverb:restart || true

sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx
sudo systemctl restart eventovivo-horizon
sudo systemctl restart eventovivo-reverb
```

### 4. Alerta principal

Rollback de codigo e simples.

Rollback de migration destrutiva e o ponto mais perigoso do processo.

## Filas e scaling

### Filas criticas atuais

Segundo a documentacao consolidada e o `config/horizon.php`, as filas
sensiveis hoje sao:

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
- `default`
- `analytics`
- `billing`

### Filas adicionais do modulo WhatsApp

O codigo tambem usa:

- `whatsapp-inbound`
- `whatsapp-send`
- `whatsapp-sync`

Status em `2026-04-06`: o go-live base subiu sem depender dessas filas, mas o
recorte operacional de Z-API/Pagar.me ja retomou `whatsapp-*` em producao.
O Horizon agora sobe:

- `supervisor-whatsapp-inbound`
- `supervisor-whatsapp-send`
- `supervisor-whatsapp-sync`

Gap restante: o pipeline canonico completo `WhatsApp -> InboundMedia ->
EventMedia` ainda precisa fechar os jobs de normalizacao/download para midia
real em alto volume.

### Regra de ouro

Estas filas nao podem competir com jobs pesados:

- `media-fast`
- `media-publish`
- `broadcasts`
- `webhooks`
- `whatsapp-inbound`

Essas sao as filas que protegem a experiencia ao vivo.

### Sizing inicial recomendado para esta VPS

Ponto de partida pragmatico:

- `webhooks`: 4
- `media-download`: 4
- `media-fast`: 4
- `media-process`: 2
- `media-safety`: 3
- `media-vlm`: 0 a 2, apenas se provider externo estiver estavel
- `face-index`: 1
- `media-publish`: 3
- `broadcasts`: 2
- `notifications`: 1
- `default`: 2
- `analytics`: dentro do `default`, ou supervisor proprio no futuro
- `billing`: dentro do `default`, ou supervisor proprio no futuro
- `whatsapp-inbound`: 3
- `whatsapp-send`: 2
- `whatsapp-sync`: 1

Observacao:

- isso nao significa alterar tudo de uma vez no primeiro deploy;
- significa ter alvo de tuning para quando a fase 2 entrar.

### Estrategia para 20 eventos simultaneos

Para suportar dezenas de eventos com centenas de fotos por minuto:

1. proteger `media-fast`, `media-publish` e `broadcasts`;
2. manter `face-index` e VLM como enriquecimento nao bloqueante;
3. evitar IA pesada local no mesmo host;
4. manter Redis saudavel e sem swapping;
5. medir backlog por fila e nao so CPU;
6. preparar crescimento horizontal do worker antes de saturar o node unico.

### Politica de degradacao operacional

Em incidente real ou pico fora do esperado, a ordem de defesa deve proteger o
ao vivo antes de proteger enriquecimento.

Ordem pratica de degradacao:

1. cortar `media-vlm`;
2. pausar `face-index`;
3. reduzir `media-safety` se existir fallback operacional aceitavel;
4. preservar `webhooks`, `media-fast`, `media-publish` e `broadcasts`.

No estado atual do repo, isso ja pode ser operado por ambiente com:

- `OPS_DEGRADE_MEDIA_VLM_ENABLED=false`
- `OPS_DEGRADE_FACE_INDEX_ENABLED=false`
- `OPS_DEGRADE_MEDIA_SAFETY_MODE=review|block`

Esses toggles ja sao respeitados no dispatch e tambem nos jobs que ja estavam
enfileirados, evitando que backlog pesado continue consumindo CPU durante o
incidente.

### Caminho de escala

Ordem recomendada:

1. tunar `maxProcesses` por fila;
2. separar object storage de vez;
3. adicionar segundo node de workers/Horizon;
4. separar Redis e PostgreSQL se a carga justificar;
5. considerar vector store externo no futuro apenas depois do produto base
   estabilizar.

## Observabilidade minima

### Logs

No minimo:

- Nginx access/error
- PHP-FPM error/slowlog
- Horizon
- Reverb
- scheduler
- Laravel
- `whatsapp.log`

### Metricas

Monitorar desde o primeiro go-live:

- CPU
- RAM
- IO wait
- espaco livre em disco
- conexoes PostgreSQL
- memoria Redis
- lag por fila
- erros 5xx
- conexoes WebSocket

### App

Usar:

- Horizon dashboard protegido
- Telescope dashboard protegido
- Pulse dashboard protegido
- `horizon:snapshot`
- metrica de pipeline por evento
- logs de `LongWaitDetected`
- telemetria `media_pipeline.published` com `upload_to_publish_seconds` e
  `inbound_to_publish_seconds`

### Retencao

Criar `logrotate` para:

- `/var/log/nginx/*.log`
- `/var/log/eventovivo-*.log`
- logs do PHP-FPM

## Backup e restore

Snapshot da VPS ajuda, mas nao substitui backup aplicacional testado.

Minimo recomendado:

- backup diario do PostgreSQL;
- backup do storage de midia segundo a politica de retencao definida;
- protecao de segredos criticos fora da release, incluindo `.env` e `APP_KEY`;
- restore testado pelo menos uma vez antes de chamar a operacao de pronta;
- politica minima de retencao registrada.

## Gaps atuais do repositorio que impactam producao

### P0 antes de chamar a stack de "fechada"

1. `NormalizeInboundMessageJob` e `DownloadInboundMediaJob` seguem em scaffold.
2. upload publico ainda grava em disco `public` local.
3. ha drift documental entre `Laravel 12` e `Laravel 13`.

### P1 para endurecimento operacional

1. adicionar unique indexes em pontos criticos de inbound e idempotencia;
2. reduzir dependencias de mocks no admin;
3. fechar estrategia de object storage para midia publica;
4. consolidar playbook de degradacao operacional por lane e por incidente.

### P2 para escala de produto

1. tirar IA pesada do host principal;
2. separar worker node do web node se o throughput crescer;
3. endurecer setup de `pgvector` em producao;
4. introduzir playbooks de incidente por fila.

## Fora de escopo do primeiro go-live

Nao recomendo como prioridade agora:

- Docker/Kubernetes
- Octane
- vLLM local
- MinIO local como storage principal de longo prazo
- deploy manual sem script
- WhatsApp inbound full production sem fechar pipeline

## Sequencia de implantacao da VPS zerada

### Etapa 1 - Base do host

- instalar pacotes do sistema;
- instalar Node e Composer;
- criar usuarios e permissoes;
- configurar SSH, UFW e fail2ban.

### Etapa 2 - Dados e cache

- criar banco e usuario PostgreSQL;
- habilitar extensoes;
- configurar Redis com AOF e senha;
- definir `maxmemory-policy` conservadora e validar folga real de memoria;
- validar backup inicial.

### Etapa 3 - Runtime web

- configurar PHP-FPM pool dedicado;
- instalar e validar Nginx;
- instalar certificado de origem;
- publicar os 4 hosts.

### Etapa 4 - Estrutura de deploy

- criar `/var/www/eventovivo`;
- preparar `shared/.env` e `shared/storage`;
- criar scripts `deploy.sh`, `rollback.sh`, `healthcheck.sh`.

### Etapa 5 - Services

- subir `eventovivo-horizon.service`;
- subir `eventovivo-reverb.service`;
- subir `eventovivo-scheduler.timer`.

### Etapa 6 - Primeiro deploy funcional

- release;
- dependencias;
- builds;
- cache warmup;
- migrate;
- switch de symlink;
- smoke tests.

### Etapa 7 - Hardening operacional

- `logrotate`;
- dashboard Horizon protegido;
- validacao de fila e wall em carga;
- monitoracao basica.

### Etapa 8 - Fase 2 de produto

- supervisores `whatsapp-*`;
- fechamento de `InboundMedia`;
- object storage mais consistente;
- tuning fino de scaling.

## Plano de implementacao

### Fase 0 - Preparacao documental e templates

Objetivo:

- fechar a base operacional versionada no repo.

Entregas:

- este runbook;
- templates reais de Nginx;
- templates de systemd;
- revisao dos `.env.example` existentes e exemplos operacionais de producao;
- script padrao de deploy.

### Fase 1 - Go-live do produto base

Objetivo:

- subir `landing + admin + api + reverb + horizon + upload publico + wall`.

Escopo:

- sem WhatsApp inbound oficial;
- sem IA pesada local;
- com filas criticas isoladas.

Criterio de aceite:

- landing publica no dominio principal;
- admin autenticando contra a API;
- upload publico gerando variantes;
- wall recebendo publicacao em tempo real;
- backlog sustentavel em carga moderada.

### Fase 2 - Endurecimento de throughput

Objetivo:

- preparar a plataforma para multiplos eventos simultaneos.

Entregas:

- tuning de Horizon;
- tuning de PHP-FPM;
- tuning de Redis;
- tuning de PostgreSQL;
- object storage mais consistente;
- testes de carga com bursts de fotos.

Criterio de aceite:

- filas sagradas sem starvation;
- wall sem atraso perceptivel em pico controlado;
- upload publico sem falhas operacionais em burst.

### Fase 3 - Fechamento do WhatsApp para producao

Objetivo:

- transformar a borda forte de WhatsApp em fluxo produtivo completo.

Entregas:

- supervisores `whatsapp-*`;
- implementacao de `DownloadInboundMediaJob`;
- fechamento de `NormalizeInboundMessageJob`;
- testes de payload real do provider;
- observabilidade dedicada do modulo.

Criterio de aceite:

- inbound com midia chegando em `event_media`;
- deduplicacao confiavel;
- backlog de WhatsApp sem impactar wall e publish.

### Fase 4 - Escala horizontal

Objetivo:

- sair do host unico quando o throughput justificar.

Entregas:

- segundo node de workers/Horizon;
- isolamento maior de servicos;
- possivel separacao de Redis/PostgreSQL;
- vector scaling quando `FaceSearch` crescer.

Criterio de aceite:

- capacidade de crescer sem refazer toda a arquitetura de deploy.

## Checklist final de readiness

- [ ] Cloudflare configurado com proxy e `Full (strict)`
- [ ] IP real do cliente restaurado no Nginx e trusted proxies alinhados no app
- [ ] 4 hosts publicos respondendo corretamente
- [ ] Reverb acessivel apenas via Nginx
- [ ] `shared/.env` e `shared/storage` prontos
- [ ] PostgreSQL 16 com extensoes necessarias
- [ ] Redis 7 com AOF e senha
- [ ] politica de `maxmemory` revisada para nao distorcer queue e sessao
- [ ] PHP-FPM pool dedicado ativo
- [ ] OPcache habilitado e revisado para producao
- [ ] `eventovivo-horizon.service` ativo
- [ ] `eventovivo-reverb.service` ativo
- [ ] `eventovivo-scheduler.timer` ativo
- [ ] deploy por releases funcionando
- [ ] rollback por symlink testado
- [ ] `public/storage` tratado por `storage:link` ou politica equivalente
- [ ] smoke test minimo de landing, admin, API, upload, wall e websocket feito
- [ ] logs com rotacao configurada
- [ ] observabilidade minima ligada
- [ ] backup diario configurado
- [ ] restore testado pelo menos uma vez
- [x] supervisores `whatsapp-*` ativos em producao
- [ ] politica de storage definida

## Conclusao

O Evento Vivo ja tem base suficiente para subir em producao com robustez, desde
que o go-live respeite a fronteira entre:

- o que ja esta maduro para operacao;
- o que ainda esta em transicao no codigo.

O desenho recomendado para esta VPS e:

- 1 node principal;
- Nginx;
- PHP-FPM pool dedicado;
- Laravel API;
- Horizon;
- Reverb local atras do Nginx;
- Redis local;
- PostgreSQL local;
- deploy por releases;
- storage persistente fora da release;
- filas criticas isoladas.

Esse desenho e coerente com a arquitetura atual do monorepo e prepara o caminho
para crescer sem recomecar a operacao do zero.
