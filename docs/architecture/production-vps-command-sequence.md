# Production VPS Command Sequence

## Objetivo

Este documento transforma o plano `M4 -> M5` em uma sequencia unica de
comandos para a VPS Ubuntu 24.04.

Fonte de verdade complementar:

- [docs/architecture/production-vps-runbook.md](./production-vps-runbook.md)
- [docs/execution-plans/production-vps-execution-plan.md](../execution-plans/production-vps-execution-plan.md)
- [docs/architecture/production-vps-delivery-checklist.md](./production-vps-delivery-checklist.md)

## Premissas

- host novo com Ubuntu 24.04;
- acesso SSH com usuario administrativo;
- o repo pode ser clonado na VPS;
- `WhatsApp inbound` continua fora do go-live atual;
- o backend esta travado em `php >=8.3 <8.4` para evitar drift de dependencias
  incompatibilizando o host Ubuntu 24.04;
- o schema atual exige `pgvector`, entao `postgresql-16-pgvector` entra no
  bootstrap padrao;
- o deploy inicial sera executado pelo usuario `deploy`.

## Variaveis que precisam ser substituidas

Antes de executar, substitua:

- `<repo-url>`
- `<branch>`
- `<db-name>`
- `<db-user>`
- `<db-password>`
- `<origin-cert-path>`
- `<origin-key-path>`

## 1. Clonar o repo temporariamente na VPS

Executar como usuario administrativo:

```bash
cd /tmp
rm -rf /tmp/eventovivo-bootstrap
git clone --depth=1 --branch <branch> <repo-url> /tmp/eventovivo-bootstrap
cd /tmp/eventovivo-bootstrap
```

Validar que o bootstrap realmente contem os artefatos operacionais desta fase:

```bash
test -d /tmp/eventovivo-bootstrap/deploy
test -f /tmp/eventovivo-bootstrap/scripts/ops/bootstrap-host.sh
test -f /tmp/eventovivo-bootstrap/scripts/ops/install-configs.sh
test -f /tmp/eventovivo-bootstrap/scripts/ops/verify-host.sh
test -f /tmp/eventovivo-bootstrap/scripts/deploy/deploy.sh
```

Se o clone remoto ainda nao contem os artefatos mais recentes da VPS, nao siga
com um bootstrap incompleto. Envie a arvore validada localmente para a VPS e
reuse o mesmo caminho `/tmp/eventovivo-bootstrap`:

```bash
scp -r ./deploy root@<server>:/tmp/eventovivo-bootstrap/
scp -r ./scripts/ops root@<server>:/tmp/eventovivo-bootstrap/scripts/
scp -r ./scripts/deploy root@<server>:/tmp/eventovivo-bootstrap/scripts/
scp ./docs/architecture/production-vps-*.md root@<server>:/tmp/eventovivo-bootstrap/docs/architecture/
```

## 2. Bootstrap do host

Executar como `root`:

```bash
sudo bash /tmp/eventovivo-bootstrap/scripts/ops/bootstrap-host.sh --enable-ufw
```

Validacoes imediatas:

```bash
php -v
composer --version
node --version
npm --version
psql --version
redis-cli --version
```

## 3. Instalar templates versionados

Executar como `root`:

```bash
sudo bash /tmp/eventovivo-bootstrap/scripts/ops/install-configs.sh --repo-root /tmp/eventovivo-bootstrap
```

## 4. Instalar certificado de origem do Cloudflare

Executar como `root`:

```bash
sudo install -m 600 <origin-cert-path> /etc/ssl/certs/eventovivo-origin.crt
sudo install -m 600 <origin-key-path> /etc/ssl/private/eventovivo-origin.key
```

## 5. Criar banco, usuario e extensoes

Executar como `root`:

```bash
sudo -u postgres psql <<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '<db-user>') THEN
        CREATE ROLE <db-user> LOGIN PASSWORD '<db-password>';
    END IF;
END
$$;
SQL

sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname = '<db-name>'" | grep -q 1 \
  || sudo -u postgres createdb --owner=<db-user> <db-name>

sudo -u postgres psql -d <db-name> <<'SQL'
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS vector;
SQL
```

Validacao:

```bash
sudo -u postgres psql -d <db-name> -c "\dx"
```

## 6. Reiniciar Redis e PostgreSQL para aplicar os baselines

Executar como `root`:

```bash
sudo systemctl restart redis-server
sudo systemctl restart postgresql
```

Observacao:

- no Ubuntu 24.04, `systemctl status postgresql` mostra um wrapper `active
  (exited)`;
- o sinal real de prontidao do cluster e `pg_lsclusters` e `pg_isready`.

## 7. Criar os envs de producao

Executar como `root`:

```bash
sudo mkdir -p /var/www/eventovivo/shared
sudo cp /tmp/eventovivo-bootstrap/deploy/examples/apps-api.env.production.example /var/www/eventovivo/shared/.env
sudo cp /tmp/eventovivo-bootstrap/deploy/examples/apps-web.env.production.example /var/www/eventovivo/shared/apps-web.env.production
sudo cp /tmp/eventovivo-bootstrap/deploy/examples/apps-landing.env.production.example /var/www/eventovivo/shared/apps-landing.env.production
sudo chown deploy:www-data /var/www/eventovivo/shared/.env
sudo chown deploy:www-data /var/www/eventovivo/shared/apps-web.env.production
sudo chown deploy:www-data /var/www/eventovivo/shared/apps-landing.env.production
sudo chmod 640 /var/www/eventovivo/shared/.env
sudo chmod 640 /var/www/eventovivo/shared/apps-web.env.production
sudo chmod 640 /var/www/eventovivo/shared/apps-landing.env.production
sudo nano /var/www/eventovivo/shared/.env
sudo nano /var/www/eventovivo/shared/apps-web.env.production
sudo nano /var/www/eventovivo/shared/apps-landing.env.production
```

Checklist minima dentro de `/var/www/eventovivo/shared/.env`:

- `APP_KEY`
- `APP_URL=https://api.eventovivo.com.br`
- `FRONTEND_URL=https://admin.eventovivo.com.br`
- `DB_DATABASE=<db-name>`
- `DB_USERNAME=<db-user>`
- `DB_PASSWORD=<db-password>`
- `QUEUE_CONNECTION=redis`
- `BROADCAST_CONNECTION=reverb`
- `REVERB_HOST=ws.eventovivo.com.br`
- `REVERB_PORT=443`
- `REVERB_SCHEME=https`
- `SANCTUM_STATEFUL_DOMAINS=admin.eventovivo.com.br`

Checklist minima dentro de `/var/www/eventovivo/shared/apps-web.env.production`:

- `VITE_API_BASE_URL=https://api.eventovivo.com.br/api/v1`
- `VITE_APP_URL=https://admin.eventovivo.com.br`
- `VITE_REVERB_HOST=ws.eventovivo.com.br`
- `VITE_REVERB_PORT=443`
- `VITE_REVERB_SCHEME=https`

Checklist minima dentro de `/var/www/eventovivo/shared/apps-landing.env.production`:

- `VITE_PUBLIC_SITE_URL=https://eventovivo.com.br`
- `VITE_ADMIN_URL=https://admin.eventovivo.com.br`

Observacao:

- se o object storage externo ainda nao estiver pronto no primeiro deploy,
  use `FILESYSTEM_DISK=public` para a fase 1 e mantenha a persistencia em
  `shared/storage`;
- nessa fase, garanta tambem `shared/storage/app/public`;
- quando S3 ou equivalente estiver fechado, troque para `FILESYSTEM_DISK=s3`
  e preencha `AWS_*`.

## 8. Validar o host antes do primeiro deploy

Executar como usuario administrativo:

```bash
sudo bash /var/www/eventovivo/scripts/verify-host.sh --require-shared-env
```

Se falhar em `nginx -t`, confirme primeiro a presenca de:

- `/etc/ssl/certs/eventovivo-origin.crt`
- `/etc/ssl/private/eventovivo-origin.key`
- `/var/www/eventovivo/shared/apps-web.env.production`
- `/var/www/eventovivo/shared/apps-landing.env.production`

Se falhar em `health/ready` com `FILESYSTEM_DISK=public`, confirme:

- `/var/www/eventovivo/shared/storage/app/public`
- ownership `deploy:www-data` nos subdiretorios de `shared/storage`

## 9. Configurar Cloudflare e DNS

Passos manuais no painel:

- criar `A` proxied para `@`, `www`, `admin`, `api` e `ws`
- ativar `Full (strict)`
- validar WebSockets habilitados

## 10. Executar o primeiro deploy

Executar como `deploy`:

```bash
sudo -iu deploy bash -lc 'cd /tmp/eventovivo-bootstrap && bash scripts/deploy/deploy.sh'
```

## 11. Habilitar os services da app

Executar como usuario administrativo:

```bash
sudo systemctl enable --now eventovivo-horizon.service
sudo systemctl enable --now eventovivo-reverb.service
sudo systemctl enable --now eventovivo-scheduler.timer
sudo systemctl status eventovivo-horizon.service --no-pager
sudo systemctl status eventovivo-reverb.service --no-pager
sudo systemctl status eventovivo-scheduler.timer --no-pager
```

## 12. Rodar o smoke test minimo

Executar como `deploy` ou usuario administrativo:

```bash
bash /var/www/eventovivo/scripts/smoke-test.sh
```

## 13. Testar rollback de forma valida

Observacao:

- rollback real exige pelo menos duas releases validas;
- se esta for a primeira release da maquina, gere uma segunda release controlada
  antes de testar rollback.

Executar como `deploy` depois da segunda release:

```bash
sudo -iu deploy bash -lc 'bash /var/www/eventovivo/scripts/rollback.sh --run-smoke-test'
```

## 14. Comandos de verificacao rapida

```bash
sudo nginx -t
sudo php-fpm8.3 -tt >/dev/null
redis-cli ping
pg_isready -h 127.0.0.1 -p 5432
curl -I https://eventovivo.com.br
curl -I https://admin.eventovivo.com.br
curl -I https://api.eventovivo.com.br/health/live
curl -I https://api.eventovivo.com.br/health/ready
```

## 15. Purge de cache Cloudflare apos hotfix de admin

Se uma release alterar service worker, HTML de bootstrap ou corrigir erro de
runtime do admin, validar se a Cloudflare ainda serve artefatos antigos:

```bash
curl -I https://admin.eventovivo.com.br/sw.js
curl -I https://admin.eventovivo.com.br/assets/NOME_DO_CHUNK_ANTIGO.js
```

Se aparecer `CF-Cache-Status: HIT` para `/sw.js` ou para um chunk que ja nao
existe na origin, purgar no painel da Cloudflare:

- `https://admin.eventovivo.com.br/sw.js`
- `https://admin.eventovivo.com.br/`
- chunks antigos que ainda aparecem como `HIT`

Se houver muitos chunks antigos ou se o navegador continuar preso em bundle
anterior, usar `Purge Everything` como fallback operacional e depois repetir o
smoke test.

## Observacoes finais

- o primeiro deploy ja roda `php artisan migrate --force`
- o deploy trata `storage:link` de forma deterministica
- o usuario `deploy` depende do sudoers versionado para reciclar apenas:
  - `nginx`
  - `php8.3-fpm`
  - `eventovivo-horizon`
  - `eventovivo-reverb`
- rollback real nao existe com apenas uma release disponivel
- se `verify-host.sh` falhar antes do deploy, corrigir o host primeiro e nao
  forcar a subida da release
