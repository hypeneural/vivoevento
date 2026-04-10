# Ops Scripts

Scripts de operacao do host Ubuntu 24.04 para a fase 1 da VPS.

## Arquivos

- `bootstrap-host.sh`: instala pacotes base, cria estrutura inicial e habilita
  servicos de runtime. Agora tambem instala `ffmpeg` para a trilha oficial de
  video do wall.
- `install-configs.sh`: instala os templates versionados do repo em `/etc` e
  copia os scripts operacionais para `/var/www/eventovivo/scripts`.
- `verify-host.sh`: roda as validacoes de `nginx`, `php-fpm`, `systemd`,
  `redis`, `postgres`, `composer`, `node`, `npm`, `psql`, `ffmpeg` e
  `ffprobe`, alem de conferir a estrutura base da app. Quando a release atual
  ja existe, tambem executa `php artisan media:tooling-status`.
- `homologate-wall-video.sh`: gera um relatorio executavel da matriz de
  homologacao do wall por classe de device/rede, reaproveitando `media:tooling-status`
  e o boot publico do wall.

## Ordem recomendada

1. rodar `bootstrap-host.sh`
2. rodar `install-configs.sh`
3. instalar o certificado de origem usado pelo Nginx
4. reiniciar `redis-server` e `postgresql` para aplicar os baselines locais
5. criar `shared/.env` e segredos
6. rodar `verify-host.sh`
7. depois do primeiro deploy, rodar `homologate-wall-video.sh` para pelo menos
   um host de homologacao e registrar o resultado por `device_class` /
   `network_class`
8. habilitar e iniciar os services da app apenas depois do primeiro deploy
   valido

## Detalhe importante de storage

Quando a fase 1 operar com `FILESYSTEM_DISK=public`, o host precisa garantir:

- `shared/storage/app/public`
- `shared/storage/framework/cache/data`
- `shared/storage/framework/sessions`
- `shared/storage/framework/testing`
- `shared/storage/framework/views`

Todos esses caminhos devem permanecer com ownership `deploy:www-data`.
