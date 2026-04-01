# Play Module

## Responsabilidade
Jogos interativos: memória, puzzle e ranking.

## Entidades
- **EventPlaySetting** — configurações do Play por evento

## Rotas
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /api/v1/events/{id}/play/settings | Config do play |
| PATCH | /api/v1/events/{id}/play/settings | Atualizar config |
| GET | /api/v1/public/events/{slug}/play | Manifest público |
