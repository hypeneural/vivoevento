# EventTeam Module

## Responsabilidade
Controle de equipe por evento — quem pode operar, moderar, visualizar cada evento.

## Entidades
- **EventTeamMember** — vínculo usuário ↔ evento com role específica

## Roles por evento
| Role | Pode |
|------|------|
| manager | Gerenciar configurações, equipe, moderação |
| operator | Operar wall, play, aprovar/rejeitar mídias |
| moderator | Aprovar/rejeitar mídias |
| viewer | Apenas visualizar |

## Rotas
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /api/v1/events/{id}/team | Listar equipe |
| POST | /api/v1/events/{id}/team | Adicionar membro |
| PATCH | /api/v1/events/{id}/team/{member} | Alterar role |
| DELETE | /api/v1/events/{id}/team/{member} | Remover |
