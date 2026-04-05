# Production VPS Command Sequence

## Objetivo

Este documento transforma o plano `M4 -> M5` em uma sequencia unica de
comandos para a VPS Ubuntu 24.04.

Fonte de verdade complementar:

- [docs/architecture/production-vps-runbook.md](./production-vps-runbook.md)
- [docs/architecture/production-vps-execution-plan.md](./production-vps-execution-plan.md)
- [docs/architecture/production-vps-delivery-checklist.md](./production-vps-delivery-checklist.md)

## Premissas

- host novo com Ubuntu 24.04;
- acesso SSH com usuario administrativo;
- o repo pode ser clonado na VPS;
- `WhatsApp inbound` continua fora do go-live atual;
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

## 7. Criar o `.env` de producao

Executar como `root`:

```bash
sudo mkdir -p /var/www/eventovivo/shared
sudo cp /tmp/eventovivo-bootstrap/deploy/examples/apps-api.env.production.example /var/www/eventovivo/shared/.env
sudo chown deploy:www-data /var/www/eventovivo/shared/.env
sudo chmod 640 /var/www/eventovivo/shared/.env
sudo nano /var/www/eventovivo/shared/.env
```

Checklist minima dentro do `.env`:

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

## 8. Validar o host antes do primeiro deploy

Executar como usuario administrativo:

```bash
sudo bash /var/www/eventovivo/scripts/verify-host.sh --require-shared-env
```

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
sudo systemctl enable eventovivo-horizon.service
sudo systemctl enable eventovivo-reverb.service
sudo systemctl enable eventovivo-scheduler.timer
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
