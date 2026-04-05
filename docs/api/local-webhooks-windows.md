# Tunnel local para webhooks no Windows

Este guia cobre o fluxo real do monorepo `eventovivo` para expor a API local no Windows e receber webhooks da Z-API e do Pagar.me.

## Recomendacao atual

Para Z-API, prefira Cloudflare Quick Tunnel.

Motivo pratico validado em `2026-04-05`:

- `GET` publico no dominio `trycloudflare.com` respondeu direto com a aplicacao local.
- `POST` publico para `/api/v1/webhooks/whatsapp/.../inbound` respondeu `200 {"status":"received"}` sem header especial.
- O mesmo teste persistiu em `whatsapp_inbound_events` e `whatsapp_messages`.
- No Pinggy free, foi observada uma tela de cautela/interstitial no caminho, o que pode bloquear webhooks automatizados da Z-API.

## Rotas confirmadas no backend

As rotas abaixo foram validadas no codigo atual do Laravel:

- `POST /api/v1/webhooks/whatsapp/{provider}/{instanceKey}/inbound`
- `POST /api/v1/webhooks/whatsapp/{provider}/{instanceKey}/status`
- `POST /api/v1/webhooks/whatsapp/{provider}/{instanceKey}/delivery`
- `POST /api/v1/webhooks/billing/{provider}`

Para o seu caso, as URLs publicas corretas sao:

- Z-API inbound: `https://SEU-TUNNEL/api/v1/webhooks/whatsapp/zapi/{EXTERNAL_INSTANCE_ID}/inbound`
- Z-API status: `https://SEU-TUNNEL/api/v1/webhooks/whatsapp/zapi/{EXTERNAL_INSTANCE_ID}/status`
- Z-API delivery: `https://SEU-TUNNEL/api/v1/webhooks/whatsapp/zapi/{EXTERNAL_INSTANCE_ID}/delivery`
- Pagar.me: `https://SEU-TUNNEL/api/v1/webhooks/billing/pagarme`

## Z-API com URL unica

Se a sua conta/painel da Z-API expoe apenas um campo para webhook de mensagens recebidas, use sempre:

```text
https://SEU-TUNNEL/api/v1/webhooks/whatsapp/zapi/SEU_EXTERNAL_INSTANCE_ID/inbound
```

Esse e o endpoint correto do projeto para `Ao receber`.

Se quiser validar o webhook enviando uma mensagem da propria instancia para outro numero, ative tambem `notifySentByMe=true` no webhook `Ao receber` da Z-API. Pela documentacao atual da Z-API, isso e feito no endpoint:

```text
PUT https://api.z-api.io/instances/{id}/token/{token}/update-notify-sent-by-me
Header: Client-Token: {TOKEN_DE_SEGURANCA}
Body: { "notifySentByMe": true }
```

Observacao importante:

- isso depende de o webhook `Ao receber` ja estar apontando para o endpoint `.../inbound`;
- no codigo atual do `eventovivo`, o `Token de Seguranca` fica salvo na instancia como credencial da Z-API, mas o recebimento HTTP do webhook ainda nao faz rejeicao por token no controller.

## Cloudflare Quick Tunnel

### Script criado

Arquivos:

- `scripts/ops/start-cloudflare-webhook-tunnel.ps1`
- `scripts/ops/start-cloudflare-webhook-tunnel.cmd`

O script:

- le `apps/api/.env` e usa `APP_URL` como alvo local por padrao;
- usa `scripts/ops/bin/cloudflared.exe` se existir, ou `cloudflared` do `PATH`;
- abre um Quick Tunnel do Cloudflare para a API local;
- detecta a URL publica `https://RANDOM.trycloudflare.com` pelos logs do `cloudflared`;
- grava o estado atual em `%TEMP%\eventovivo-cloudflare-webhook.json`;
- reabre o tunnel automaticamente se o processo cair;
- imprime as URLs prontas de Z-API e Pagar.me.

### Instalacao

No ambiente local atual, o binario foi validado em:

```text
scripts/ops/bin/cloudflared.exe
```

Alternativas suportadas pela documentacao oficial do Cloudflare no Windows:

- `winget install --id Cloudflare.cloudflared`
- download direto do executavel oficial `cloudflared-windows-amd64.exe`

### Como usar

Se a sua API local responde em `http://localhost:8000`, basta rodar:

```powershell
.\scripts\ops\start-cloudflare-webhook-tunnel.ps1
```

Ou no CMD / duplo clique:

```bat
scripts\ops\start-cloudflare-webhook-tunnel.cmd
```

Se quiser a URL completa da Z-API sem placeholder:

```powershell
.\scripts\ops\start-cloudflare-webhook-tunnel.ps1 -ZApiInstanceKey "SEU_EXTERNAL_INSTANCE_ID"
```

Se a API estiver em outro host ou porta:

```powershell
.\scripts\ops\start-cloudflare-webhook-tunnel.ps1 -LocalTargetUrl "http://eventovivo.test"
.\scripts\ops\start-cloudflare-webhook-tunnel.ps1 -LocalTargetUrl "http://127.0.0.1:8080"
```

Se quiser apenas ver o comando e as URLs esperadas sem conectar:

```powershell
.\scripts\ops\start-cloudflare-webhook-tunnel.ps1 -DryRun
```

### Arquivo de estado

Cada conexao atualiza:

```text
%TEMP%\eventovivo-cloudflare-webhook.json
```

Esse arquivo guarda:

- `public_base_url`
- `public_health_url`
- `urls.zapi_inbound`
- `urls.zapi_status`
- `urls.zapi_delivery`
- `urls.pagarme`

Se o `cloudflared` reiniciar, a URL muda e o script atualiza esse arquivo.

### Limitacao importante

Quick Tunnel e para desenvolvimento e testes. O dominio `trycloudflare.com` e aleatorio e muda quando o processo reinicia.

Outro ponto importante da documentacao oficial: Quick Tunnel nao e suportado se existir `config.yml` ou `config.yaml` em `~/.cloudflared`. O script valida isso antes de conectar.

## Cloudflare Named Tunnel

Para um hostname fixo no dominio do projeto, o fluxo preparado neste repositorio usa:

- tunnel name: `eventovivo-local-webhooks`
- hostname fixo: `webhooks-local.eventovivo.com.br`

URLs fixas planejadas:

- Z-API inbound: `https://webhooks-local.eventovivo.com.br/api/v1/webhooks/whatsapp/zapi/SEU_EXTERNAL_INSTANCE_ID/inbound`
- Z-API status: `https://webhooks-local.eventovivo.com.br/api/v1/webhooks/whatsapp/zapi/SEU_EXTERNAL_INSTANCE_ID/status`
- Z-API delivery: `https://webhooks-local.eventovivo.com.br/api/v1/webhooks/whatsapp/zapi/SEU_EXTERNAL_INSTANCE_ID/delivery`
- Pagar.me: `https://webhooks-local.eventovivo.com.br/api/v1/webhooks/billing/pagarme`

Validacao real do hostname fixo em `2026-04-05`:

- `GET https://webhooks-local.eventovivo.com.br/up` respondeu `200`;
- `POST` manual para `https://webhooks-local.eventovivo.com.br/api/v1/webhooks/billing/pagarme`
  respondeu `accepted=true`, `queued=true`;
- um checkout Pix real do `eventovivo` gerou
  `billing_order_uuid = e6781d7d-cffc-46a6-95fa-a2faaa9e0c1e` e
  `gateway_order_id = or_9nL0kBEjiQHZwJ83`;
- a Pagar.me registrou o hook real `hook_NQnjE65KiRIyVeKA` com
  `response_status = 200` para esse hostname;
- a reentrega oficial via `POST /hooks/hook_NQnjE65KiRIyVeKA/retry` bateu no
  mesmo `BillingGatewayEvent` local sem duplicar `Payment` nem
  `EventPurchase`.

Arquivos criados:

- `scripts/ops/setup-cloudflare-named-webhook-tunnel.ps1`
- `scripts/ops/setup-cloudflare-named-webhook-tunnel.cmd`
- `scripts/ops/start-cloudflare-named-webhook-tunnel.ps1`
- `scripts/ops/start-cloudflare-named-webhook-tunnel.cmd`

O setup:

- exige autenticacao da conta Cloudflare na zona `eventovivo.com.br`;
- cria o named tunnel se ele ainda nao existir;
- cria ou sobrescreve o DNS `webhooks-local.eventovivo.com.br`;
- grava o estado em `~/.cloudflared/eventovivo-local-webhooks.state.json`;
- opcionalmente atualiza o webhook da Z-API.

O start:

- usa o state file do named tunnel;
- sobe `cloudflared tunnel run --url http://localhost:8000 eventovivo-local-webhooks`;
- valida o health publico no hostname fixo;
- opcionalmente reaplica a URL da Z-API.

Comandos esperados:

```powershell
.\scripts\ops\setup-cloudflare-named-webhook-tunnel.ps1 -LaunchLogin
.\scripts\ops\start-cloudflare-named-webhook-tunnel.ps1 -UpdateZApiWebhook
```

Observacao pratica:

- sem `~/.cloudflared/cert.pem`, o setup nao consegue criar o tunnel nem publicar o DNS;
- isso depende de concluir o login oficial `cloudflared tunnel login eventovivo.com.br` no navegador;
- depois que o tunnel existe, o start pode ser reutilizado para manter o hostname fixo.

## Validacoes recomendadas

### 1. API local

O script tenta validar a API local em:

- `/up`
- `/health/live`
- `/`

Se nenhuma responder, o tunnel ainda pode abrir, mas o provider vai falhar ao chamar sua API.

### 2. URL publica

Depois de abrir o tunnel, o script tenta acessar o health publico da URL gerada.

### 3. Z-API

O modulo `WhatsApp` responde `200` imediatamente e envia o processamento para a fila `whatsapp-inbound`.

No ambiente local atual, essa fila nao esta coberta pelo Horizon por padrao. Para validar de ponta a ponta, rode tambem:

```powershell
cd apps/api
php artisan queue:work redis --queue=whatsapp-inbound --tries=3
```

### 4. Pagar.me

O webhook do billing entra em `POST /api/v1/webhooks/billing/pagarme` e o processamento vai para a fila `billing`.

Se nao estiver rodando Horizon, rode:

```powershell
cd apps/api
php artisan queue:work redis --queue=billing --tries=3
```

## Basic Auth do Pagar.me

O controller atual valida Basic Auth apenas se estas duas variaveis estiverem preenchidas em `apps/api/.env`:

```dotenv
PAGARME_WEBHOOK_BASIC_AUTH_USER=eventovivo-webhook
PAGARME_WEBHOOK_BASIC_AUTH_PASSWORD="troque-isto"
```

Se a senha tiver `#`, `;` ou espacos, use aspas no `.env`. Sem isso o parser do Laravel pode truncar o valor.

Se ambas estiverem vazias, pelo codigo atual o endpoint aceita o webhook sem autenticacao.

## Pinggy

O script antigo continua disponivel:

- `scripts/ops/start-webhook-tunnel.ps1`
- `scripts/ops/start-webhook-tunnel.cmd`

Mas, para a Z-API, o Pinggy free nao e a melhor opcao neste projeto porque foi observada uma tela de cautela/interstitial em requests publicos normais.

## Referencias oficiais usadas para este fluxo

- Cloudflare Quick Tunnels: https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/do-more-with-tunnels/trycloudflare/
- Cloudflare downloads para Windows: https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/downloads/
- Pinggy SSH usage: https://pinggy.io/docs/usages/
- Pinggy Web Debugger API: https://pinggy.io/docs/api/web_debugger_api/
