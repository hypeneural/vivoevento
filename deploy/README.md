# Deploy Assets

Este diretorio concentra os artefatos operacionais versionados da subida da VPS
de producao.

## Estrutura

- `nginx/`: configuracao global, vhosts e restore do IP real do Cloudflare.
- `php/`: baseline de runtime do PHP, como OPcache.
- `php-fpm/`: pool dedicado da aplicacao.
- `redis/`: baseline operacional do Redis para filas, cache e sessao.
- `postgresql/`: baseline operacional do PostgreSQL local da VPS.
- `systemd/`: services e timer da stack.
- `sudoers/`: permissoes minimas para o usuario `deploy` reciclar servicos.
- `logrotate/`: rotacao de logs operacionais.
- `examples/`: contratos de ambiente e exemplos de producao.

## Ordem recomendada de instalacao no host

1. Instalar `nginx/nginx.conf`, `nginx/conf.d/cloudflare-real-ip.conf` e os
   vhosts em `nginx/sites/`.
2. Instalar `php/opcache-production.ini` e `php-fpm/eventovivo.conf`.
3. Instalar `redis/eventovivo.conf` e `postgresql/eventovivo.conf`.
4. Instalar `systemd/*.service` e `systemd/*.timer`.
5. Instalar `sudoers/eventovivo-deploy-systemctl`.
6. Instalar `logrotate/eventovivo`.
7. Validar:
   - `nginx -t`
   - `php-fpm8.3 -tt`
   - `systemd-analyze verify <unit>`
   - `redis-cli ping`
   - `pg_isready -h 127.0.0.1 -p 5432`

## Observacoes operacionais

- o arquivo `nginx/conf.d/cloudflare-real-ip.conf` precisa ser mantido em sincronia
  com as faixas oficiais do Cloudflare;
- o app Laravel deve continuar alinhado com trusted proxies no bootstrap;
- Redis e PostgreSQL continuam privados no host unico da fase 1;
- `shared/storage/app/public` e os subdiretorios de `shared/storage/framework`
  precisam existir com ownership `deploy:www-data` para o deploy e o
  `/health/ready` funcionarem com `FILESYSTEM_DISK=public`;
- o vhost do admin deve manter `/`, `/index.html`, `/sw.js` e
  `/manifest.webmanifest` sem cache imutavel; somente assets versionados em
  `/assets/` devem usar cache longo;
- os arquivos em `/etc/nginx/sites-enabled` devem ser symlinks para
  `/etc/nginx/sites-available`; backups nao devem ficar em `sites-enabled`,
  porque o include global carrega tudo que estiver nesse diretorio;
- se `deploy.sh` e `rollback.sh` forem executados pelo usuario `deploy`, o
  sudoers versionado precisa estar instalado para liberar apenas os `systemctl`
  necessarios;
- scripts de deploy e bootstrap do host vivem em `scripts/` e avancam em outra
  etapa do execution plan.
