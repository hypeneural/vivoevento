# Landing Page, Monorepo e Deploy

Este documento consolida a estrategia atual para publicar o Evento Vivo com quatro superficies separadas, mas coerentes no mesmo monorepo:

- `eventovivo.com.br`: landing page de captura.
- `admin.eventovivo.com.br`: painel administrativo.
- `api.eventovivo.com.br`: API Laravel.
- `ws.eventovivo.com.br`: WebSockets do Reverb.

## Estado real do repositorio

Hoje o monorepo esta organizado assim:

```text
eventovivo/
|-- apps/
|   |-- api/        # Laravel
|   |-- landing/    # Landing page React + Vite
|   `-- web/        # Painel admin + player publico do wall
|-- docs/
|-- packages/
|-- scripts/
`-- docker/
```

Observacoes importantes:

- O painel continua fisicamente em `apps/web`, mesmo que em producao ele seja servido no subdominio `admin.eventovivo.com.br`.
- A landing entrou como app separado em `apps/landing` para evitar misturar marketing com o painel.
- O build atual da landing e um build estatico do Vite, gerado em `apps/landing/dist`.

## Topologia de dominios

Para reduzir atrito operacional e manter uma arquitetura previsivel:

- `eventovivo.com.br` e `www.eventovivo.com.br` servem a landing.
- `admin.eventovivo.com.br` serve o app React do painel.
- `api.eventovivo.com.br` aponta para o Laravel via PHP-FPM.
- `ws.eventovivo.com.br` faz reverse proxy para o processo local do Reverb.

Essa separacao evita acoplamento entre marketing e admin, sem mover o backend para um dominio de terceiros.

## Cloudflare e TLS

No Cloudflare:

- crie registros `A` para `@`, `www`, `admin`, `api` e `ws`;
- mantenha o proxy ligado;
- configure SSL/TLS como `Full (strict)`.

Nao use `Flexible`. Para Sanctum, cookies seguros e WebSockets, esse modo costuma causar loop de redirect, inconsistencias de scheme e quebra de sessao.

## Deploy por releases

O layout recomendado na VPS continua sendo o modelo por releases:

```text
/var/www/eventovivo/
|-- releases/
|   |-- 20260401-220000/
|   `-- 20260401-231500/
|-- shared/
|   |-- .env
|   `-- storage/
`-- current -> /var/www/eventovivo/releases/20260401-231500
```

Fluxo recomendado:

1. criar uma nova pasta em `releases/`;
2. subir o codigo nela;
3. rodar `composer install` e os `npm install` necessarios;
4. gerar os builds de `apps/web` e `apps/landing`;
5. aquecer caches do Laravel;
6. apontar o symlink `current` para a release nova;
7. reiniciar ou recarregar os processos necessarios.

Rotina minima no backend:

```bash
cd /var/www/eventovivo/current/apps/api
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:terminate
php artisan reverb:restart
```

## Build outputs

Os diretorios finais esperados em producao sao:

- `apps/landing/dist`
- `apps/web/dist`
- `apps/api/public`

O CI/CD deve garantir que esses caminhos existam dentro da release antes da troca do symlink.

## Nginx

### Landing

```nginx
server {
    listen 443 ssl http2;
    server_name eventovivo.com.br www.eventovivo.com.br;

    root /var/www/eventovivo/current/apps/landing/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

Como a landing atual e uma SPA leve de rota unica, o fallback para `index.html` e suficiente.

### Painel administrativo

```nginx
server {
    listen 443 ssl http2;
    server_name admin.eventovivo.com.br;

    root /var/www/eventovivo/current/apps/web/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

### API Laravel

```nginx
server {
    listen 443 ssl http2;
    server_name api.eventovivo.com.br;

    root /var/www/eventovivo/current/apps/api/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
    }
}
```

### Reverb / WebSockets

```nginx
server {
    listen 443 ssl http2;
    server_name ws.eventovivo.com.br;

    proxy_read_timeout 60m;
    proxy_connect_timeout 60m;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
    }
}
```

O processo do Reverb deve ficar restrito ao localhost. O Nginx termina o TLS e expõe apenas `wss://ws.eventovivo.com.br`.

## Variaveis de ambiente da landing

`apps/landing/.env` aceita pelo menos:

- `VITE_PUBLIC_SITE_URL`
- `VITE_ADMIN_URL`
- `VITE_PRIMARY_CTA_URL`
- `VITE_WHATSAPP_NUMBER`
- `VITE_WHATSAPP_MESSAGE`
- `VITE_INSTAGRAM_URL`
- `VITE_LINKEDIN_URL`

Isso permite ajustar CTA principal, contato e links sociais sem editar o codigo do app.

## Checklist de release

- `apps/landing` instalado e buildado com sucesso.
- `apps/web` instalado e buildado com sucesso.
- `apps/api` com dependencias, cache e migracoes em dia.
- Nginx apontando para `current`.
- Cloudflare em `Full (strict)`.
- Reverb exposto apenas pelo Nginx.
