# Hub Module
## Responsabilidade
Página oficial do evento com links centrais (galeria, upload, wall, play).
## Entidades
- **EventHubSetting** — configurações do Hub por evento
## Rotas
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /api/v1/events/{id}/hub/settings | Config do hub |
| PATCH | /api/v1/events/{id}/hub/settings | Atualizar config |
| GET | /api/v1/public/events/{slug}/hub | Hub público |
