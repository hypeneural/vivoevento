# Cloudflare + VPS - guia de apontamento do Evento Vivo

Data desta analise: 2026-04-06

## Objetivo

Este documento explica, de forma pratica, o que precisa ser feito para colocar
o dominio `eventovivo.com.br` atras da Cloudflare e apontado para a VPS de
producao `173.212.250.55`.

Ele consolida:

- o que a documentacao interna do projeto ja define;
- o estado atual do DNS do dominio no momento desta analise;
- quais registros criar na Cloudflare;
- o que precisa existir na VPS;
- quais configuracoes ativar no painel da Cloudflare;
- como validar se tudo ficou correto.

## Resumo executivo

Hoje o dominio ja esta delegado para a Cloudflare, os hostnames principais ja
estao publicados como `Proxied` e a origin da VPS ja responde com o certificado
de origem instalado.

O caminho correto para este repositorio e:

1. manter a Cloudflare como DNS autoritativo e proxy;
2. criar registros `A` proxied para `@`, `www`, `admin`, `api` e `ws`;
3. apontar todos para `173.212.250.55`;
4. gerar um certificado de origem na Cloudflare;
5. instalar esse certificado no Nginx da VPS;
6. ativar `SSL/TLS = Full (strict)`;
7. confirmar que a VPS aceita `80` e `443`, mas deixa `8080` apenas local.

## O que a documentacao interna do projeto ja define

A documentacao interna ja fechou a topologia publica da fase 1:

- `eventovivo.com.br` -> landing
- `www.eventovivo.com.br` -> redirect para a landing principal
- `admin.eventovivo.com.br` -> painel administrativo
- `api.eventovivo.com.br` -> API Laravel
- `ws.eventovivo.com.br` -> Reverb atras do Nginx

Arquivos internos mais importantes para isso:

- `docs/architecture/production-vps-runbook.md`
- `docs/architecture/production-vps-command-sequence.md`
- `docs/architecture/landing-page-deployment.md`
- `deploy/nginx/sites/eventovivo-landing.conf`
- `deploy/nginx/sites/eventovivo-admin.conf`
- `deploy/nginx/sites/eventovivo-api.conf`
- `deploy/nginx/sites/eventovivo-ws.conf`
- `deploy/nginx/conf.d/cloudflare-real-ip.conf`
- `deploy/examples/apps-api.env.production.example`

Conclusao pratica:

- o projeto ja foi desenhado para rodar com Cloudflare na borda;
- o Nginx ja espera um certificado de origem em:
  - `/etc/ssl/certs/eventovivo-origin.crt`
  - `/etc/ssl/private/eventovivo-origin.key`
- o backend ja esta preparado para trusted proxies atras do Nginx;
- o `ws.eventovivo.com.br` ja foi previsto para WebSockets/Reverb.

## Estado atual observado em 2026-04-06

Validacao publica feita nesta analise:

- `eventovivo.com.br` ja usa nameservers da Cloudflare:
  - `hank.ns.cloudflare.com`
  - `sue.ns.cloudflare.com`
- os hostnames principais ja foram publicados como `Proxied`:
  - `eventovivo.com.br`
  - `www.eventovivo.com.br`
  - `admin.eventovivo.com.br`
  - `api.eventovivo.com.br`
  - `ws.eventovivo.com.br`
- a resolucao publica agora retorna IPs anycast da Cloudflare, o que e esperado
  para records proxied;
- landing, admin e os endpoints `health/live` e `health/ready` ja respondem
  publicamente com `200`;
- isso confirma que a origin HTTPS esta operacional atras da Cloudflare.

Isso significa que:

- voce nao precisa trocar nameservers no registrador, a menos que eles tenham
  sido alterados depois desta data;
- os registros DNS principais ja estao criados;
- o certificado de origem ja foi instalado na VPS;
- o Nginx da origin ja esta valido em `443`;
- o proximo passo relevante e manter o modo `SSL/TLS = Full (strict)` e seguir
  com os testes de produto.

## Qual apontamento criar na Cloudflare

Para seguir o desenho oficial do projeto, crie estes registros:

| Tipo | Nome | Conteudo | Proxy status | TTL | Uso |
| --- | --- | --- | --- | --- | --- |
| `A` | `@` | `173.212.250.55` | `Proxied` | `Auto` | landing principal |
| `A` | `www` | `173.212.250.55` | `Proxied` | `Auto` | redirect para raiz |
| `A` | `admin` | `173.212.250.55` | `Proxied` | `Auto` | painel admin |
| `A` | `api` | `173.212.250.55` | `Proxied` | `Auto` | API Laravel |
| `A` | `ws` | `173.212.250.55` | `Proxied` | `Auto` | WebSocket/Reverb |

## O que nao mexer

Nao altere estes registros sem necessidade:

- MX/TXT atuais do dominio
- `_dmarc`
- SPF do dominio
- o record de tunnel `webhooks-local`, se ele ainda for usado para desenvolvimento

Esses registros devem continuar como `DNS only` quando forem registros de e-mail
ou tunnel especifico.

## O que preencher no painel da Cloudflare

Na tela `DNS > Records`, para cada host acima:

1. `Type`: `A`
2. `Name`: use `@`, `www`, `admin`, `api` ou `ws`
3. `IPv4 address`: `173.212.250.55`
4. `Proxy status`: `Proxied`
5. `TTL`: `Auto`
6. `Save`

## O que precisa existir na VPS

Para esse apontamento funcionar, a VPS precisa responder corretamente na
origem.

### Portas que precisam estar acessiveis

No minimo:

- `22/tcp` para SSH
- `80/tcp` para HTTP
- `443/tcp` para HTTPS

Nao exponha publicamente:

- `8080/tcp` do Reverb
- `5432/tcp` do PostgreSQL
- `6379/tcp` do Redis

No projeto, o Reverb foi desenhado para rodar localmente em `127.0.0.1:8080`
e ser publicado apenas pelo Nginx via `ws.eventovivo.com.br`.

### Certificado que precisa ser instalado na origem

O Nginx versionado no repo espera estes arquivos:

- `/etc/ssl/certs/eventovivo-origin.crt`
- `/etc/ssl/private/eventovivo-origin.key`

Instalacao prevista na doc interna:

```bash
sudo install -m 600 <origin-cert-path> /etc/ssl/certs/eventovivo-origin.crt
sudo install -m 600 <origin-key-path> /etc/ssl/private/eventovivo-origin.key
sudo nginx -t
sudo systemctl reload nginx
```

### Virtual hosts que ja precisam estar ativos no Nginx

O repo ja traz estes vhosts:

- landing para `eventovivo.com.br`
- redirect de `www.eventovivo.com.br`
- admin para `admin.eventovivo.com.br`
- API para `api.eventovivo.com.br`
- WebSocket para `ws.eventovivo.com.br`

Entao o apontamento DNS sozinho nao basta. A VPS precisa estar com os templates
de `deploy/nginx/` instalados.

## Qual configuracao fazer no Cloudflare

### 1. SSL/TLS mode

Use:

- `SSL/TLS > Overview > Full (strict)`

Nao use:

- `Flexible`

Para este projeto, `Flexible` e o modo errado porque o repositorio foi desenhado
com cookies seguros, proxy reverso e WebSockets via HTTPS/WSS.

### 2. Origin certificate

No painel da Cloudflare:

1. abra `SSL/TLS`
2. abra `Origin Server`
3. clique em `Create certificate`
4. gere a chave pela propria Cloudflare
5. inclua estes hostnames no certificado:
   - `eventovivo.com.br`
   - `*.eventovivo.com.br`
6. salve o certificado e a chave
7. copie ambos para a VPS nos caminhos esperados pelo Nginx

### 3. WebSockets

Verifique em:

- `Network > WebSockets`

O valor esperado e:

- `On`

### 4. Proxy

Todos os hostnames web do projeto devem ficar com nuvem laranja:

- `@`
- `www`
- `admin`
- `api`
- `ws`

## Ordem recomendada de execucao

### Etapa 1 - fechar a VPS

1. instalar os templates versionados do repo
2. instalar o certificado de origem
3. validar `nginx -t`
4. garantir que a VPS responde em `80` e `443`

### Etapa 2 - criar os registros DNS

1. confirmar `A @ -> 173.212.250.55`
2. confirmar `A www -> 173.212.250.55`
3. confirmar `A admin -> 173.212.250.55`
4. confirmar `A api -> 173.212.250.55`
5. confirmar `A ws -> 173.212.250.55`
6. confirmar que todos permanecem como `Proxied`

### Etapa 3 - ativar SSL/TLS correto

1. gerar Origin CA certificate
2. instalar na VPS
3. mudar para `Full (strict)`
4. conferir se nao existe erro `526`

### Etapa 4 - validar publicamente

1. abrir `https://eventovivo.com.br`
2. abrir `https://admin.eventovivo.com.br`
3. abrir `https://api.eventovivo.com.br/health/live`
4. abrir `https://api.eventovivo.com.br/health/ready`
5. validar o realtime usando `ws.eventovivo.com.br`

### Etapa 5 - purgar cache apos hotfix do admin

Se o admin carregar bundle antigo ou mostrar erro de console ja corrigido na
release atual, valide os headers:

```bash
curl -I https://admin.eventovivo.com.br/sw.js
curl -I https://admin.eventovivo.com.br/assets/NOME_DO_CHUNK_ANTIGO.js
```

Se a resposta publica vier com `CF-Cache-Status: HIT`, mas a origin direta ja
retornar `404` ou headers novos, purgue no painel da Cloudflare pelo menos:

- `https://admin.eventovivo.com.br/sw.js`
- `https://admin.eventovivo.com.br/`
- chunks antigos citados no console do navegador

Use `Purge Everything` apenas como fallback quando houver muitos chunks antigos
ou quando o navegador continuar preso em service worker/bundle anterior.

## Resultado esperado depois do apontamento

Depois de tudo certo:

- o DNS publico nao vai mostrar diretamente `173.212.250.55` para os hostnames
  proxied; ele vai mostrar IPs anycast da Cloudflare;
- isso e esperado;
- o trafego real continuara chegando na sua VPS `173.212.250.55` por tras da
  Cloudflare.

## Comandos uteis de verificacao

### Verificar nameservers

```bash
nslookup -type=ns eventovivo.com.br
```

### Verificar se os hostnames passaram a resolver

```bash
nslookup eventovivo.com.br
nslookup admin.eventovivo.com.br
nslookup api.eventovivo.com.br
nslookup ws.eventovivo.com.br
```

### Validar HTTP/HTTPS

```bash
curl -I https://eventovivo.com.br
curl -I https://admin.eventovivo.com.br
curl -I https://api.eventovivo.com.br/health/live
curl -I https://api.eventovivo.com.br/health/ready
```

### Validar origem diretamente

Se quiser testar a origem sem depender do DNS publico proxied:

```bash
curl --resolve eventovivo.com.br:443:173.212.250.55 -k https://eventovivo.com.br
curl --resolve admin.eventovivo.com.br:443:173.212.250.55 -k https://admin.eventovivo.com.br
curl --resolve api.eventovivo.com.br:443:173.212.250.55 -k https://api.eventovivo.com.br/health/live
```

O `-k` e necessario quando a origem usa Cloudflare Origin CA, porque esse
certificado e valido entre Cloudflare e a VPS, nao para o navegador confiar
diretamente na origem.

## Erros comuns que vao quebrar esse setup

### 1. Criar os registros como `DNS only`

Sintoma:

- a origem fica exposta
- voce perde a camada de proxy/protecao da Cloudflare
- o DNS passa a mostrar o IP real da VPS

### 2. Usar `Flexible`

Sintoma:

- loops de redirect
- problemas de scheme
- quebra de cookies seguros
- comportamento inconsistente em auth e WebSockets

### 3. Ativar `Full (strict)` sem certificado valido na origem

Sintoma:

- erro `526` na Cloudflare

### 3.1 Origin ainda sem HTTPS valido no Nginx

Sintoma:

- erro `521` na Cloudflare
- `verify-host.sh` falha em `missing TLS certificate`
- `nginx -t` falha porque:
  - `/etc/ssl/certs/eventovivo-origin.crt` ainda nao existe
  - `/etc/ssl/private/eventovivo-origin.key` ainda nao existe

### 4. Nao criar o host `ws`

Sintoma:

- falha no realtime
- Reverb/WSS nao conecta

### 5. Expor `8080` publicamente

Sintoma:

- aumento de superficie de ataque
- bypass do desenho oficial do projeto

### 6. Cache antigo de service worker ou chunk do admin

Sintoma:

- a origin direta retorna a release nova, mas o browser ainda executa chunk
  antigo;
- `curl` publico mostra `CF-Cache-Status: HIT` para `/sw.js` ou para um chunk
  removido;
- telas como dashboard, play ou plans continuam quebrando com erro de runtime
  ja corrigido na release atual.

Acao:

- purgar `/sw.js`, `/` e os chunks antigos no painel da Cloudflare;
- se persistir, limpar site data ou unregister do service worker no navegador
  usado para validar.

## Observacao sobre IPv6

A VPS mostra que tem IPv6, mas este documento nao recomenda publicar `AAAA`
neste primeiro passo sem antes validar:

- qual e o endereco IPv6 final correto da origem;
- se o Nginx esta respondendo bem em IPv6;
- se firewall e roteamento IPv6 estao corretos.

Para subir rapido e com menos risco, publique primeiro apenas os `A` records de
IPv4.

## Observacao sobre "codigo para colocar no Cloudflare"

Para este cenario, voce nao precisa colar codigo de aplicacao na Cloudflare.

O que realmente precisa preencher no painel e:

- os registros DNS;
- o modo SSL/TLS;
- o certificado de origem que a propria Cloudflare gera para voce;
- a validacao de WebSockets.

Se depois voce quiser automatizar isso por API, da para fazer, mas nao e
necessario para o primeiro go-live.

## Checklist final

- [ ] nameservers continuam sendo `hank.ns.cloudflare.com` e `sue.ns.cloudflare.com`
- [x] `A @ -> 173.212.250.55` criado como `Proxied`
- [x] `A www -> 173.212.250.55` criado como `Proxied`
- [x] `A admin -> 173.212.250.55` criado como `Proxied`
- [x] `A api -> 173.212.250.55` criado como `Proxied`
- [x] `A ws -> 173.212.250.55` criado como `Proxied`
- [ ] `SSL/TLS = Full (strict)`
- [x] certificado de origem instalado no Nginx
- [ ] portas `80` e `443` liberadas na VPS
- [ ] `8080` do Reverb acessivel apenas localmente
- [x] `https://eventovivo.com.br` respondendo
- [x] `https://admin.eventovivo.com.br` respondendo
- [x] `https://api.eventovivo.com.br/health/live` respondendo
- [x] `https://api.eventovivo.com.br/health/ready` respondendo
- [x] realtime via `ws.eventovivo.com.br` funcionando
- [ ] cache da Cloudflare purgado para `/sw.js` e chunks antigos do admin apos
  hotfix de console

## Fontes internas

- `docs/architecture/production-vps-runbook.md`
- `docs/architecture/production-vps-command-sequence.md`
- `docs/architecture/landing-page-deployment.md`
- `deploy/nginx/conf.d/cloudflare-real-ip.conf`
- `deploy/nginx/sites/eventovivo-landing.conf`
- `deploy/nginx/sites/eventovivo-admin.conf`
- `deploy/nginx/sites/eventovivo-api.conf`
- `deploy/nginx/sites/eventovivo-ws.conf`
- `deploy/examples/apps-api.env.production.example`

## Fontes oficiais da Cloudflare

- https://developers.cloudflare.com/dns/proxy-status/
- https://developers.cloudflare.com/dns/zone-setups/full-setup/setup/
- https://developers.cloudflare.com/ssl/origin-configuration/ssl-modes/full-strict/
- https://developers.cloudflare.com/ssl/origin-configuration/origin-ca/
- https://developers.cloudflare.com/network/websockets/
- https://developers.cloudflare.com/fundamentals/reference/network-ports/
- https://developers.cloudflare.com/cache/how-to/purge-cache/
- https://developers.cloudflare.com/support/troubleshooting/restoring-visitor-ips/restoring-original-visitor-ips/
