# Ops Scripts

Scripts de operacao do host Ubuntu 24.04 para a fase 1 da VPS.

## Arquivos

- `bootstrap-host.sh`: instala pacotes base, cria estrutura inicial e habilita
  servicos de runtime.
- `install-configs.sh`: instala os templates versionados do repo em `/etc` e
  copia os scripts operacionais para `/var/www/eventovivo/scripts`.
- `verify-host.sh`: roda as validacoes de `nginx`, `php-fpm`, `systemd`,
  `redis`, `postgres`, `composer`, `node`, `npm` e `psql`, alem de conferir a
  estrutura base da app.

## Ordem recomendada

1. rodar `bootstrap-host.sh`
2. rodar `install-configs.sh`
3. instalar o certificado de origem usado pelo Nginx
4. reiniciar `redis-server` e `postgresql` para aplicar os baselines locais
5. criar `shared/.env` e segredos
6. rodar `verify-host.sh`
7. habilitar e iniciar os services da app apenas depois do primeiro deploy
   valido
