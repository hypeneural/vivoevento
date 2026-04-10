# Deploy Scripts

Scripts versionados de deploy por release para a VPS de producao.

## Arquivos

- `deploy.sh`: cria release, instala dependencias, builda frontends, aquece
  caches, roda migrations, troca `current` e recicla os processos long-lived.
- `rollback.sh`: reaponta `current` para uma release anterior e recicla os
  servicos da app.
- `healthcheck.sh`: valida a release antes do switch, incluindo `public/storage`
  e artefatos dos frontends.
- `smoke-test.sh`: executa o smoke test minimo do go-live base.

## Ordem recomendada

1. garantir que o host ja passou por `bootstrap-host.sh`, `install-configs.sh`
   e `verify-host.sh`
2. criar `/var/www/eventovivo/shared/.env`
3. executar `deploy.sh`
4. validar o go-live base com `smoke-test.sh`
5. se o host participa do rollout de video do wall, rodar `scripts/ops/homologate-wall-video.sh`
6. se necessario, usar `rollback.sh`

## Observacoes

- `deploy.sh` assume release imutavel e symlink `current`
- `storage:link` e tratado de forma deterministica no deploy
- o build do admin remove assets Vite nao referenciados por `index.html` ou
  `sw.js` da release atual, reduzindo o risco de chunk antigo sobreviver na
  origin
- `healthcheck.sh` tambem valida `storage/app/public` quando a fase 1 usa
  `FILESYSTEM_DISK=public`
- `smoke-test.sh` exige `/health/live` e `/health/ready` por padrao
- `healthcheck.sh` valida `php artisan media:tooling-status` por padrao antes
  do switch da release; use `REQUIRE_MEDIA_TOOLING=0` apenas se o host ainda
  nao participa do rollout de video do wall
- se a Cloudflare continuar servindo `/sw.js` ou chunks antigos como
  `CF-Cache-Status: HIT`, purgar o cache no painel antes de validar o navegador
- `deploy.sh` e `rollback.sh` assumem que o usuario `deploy` tem acesso
  apenas ao sudoers minimo para os `systemctl` previstos
- `rollback.sh` nao resolve migration destrutiva; isso continua sendo uma
  responsabilidade do desenho do schema
