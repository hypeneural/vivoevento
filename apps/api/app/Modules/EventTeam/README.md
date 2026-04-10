# EventTeam Module

## Responsabilidade

Controlar acessos por evento para terceiros e operadores event-scoped sem confundir isso com a equipe organizacional.

O modulo agora cobre duas camadas:

- `event_team_members`: acessos ativos ao evento;
- `event_team_invitations`: convites pendentes para acessos futuros ao evento.

## Entidades

- `EventTeamMember`: vinculo ativo usuario <-> evento com role persistida.
- `EventTeamInvitation`: convite pendente tokenizado para um evento especifico.

## Presets de acesso por evento

Os presets expostos por `GET /api/v1/access/presets` sao a fonte recomendada para a UI.

| Preset | Role persistida | Uso |
|------|------|------|
| `event.manager` | `manager` | Gerenciar equipe e operacao do evento |
| `event.operator` | `operator` | Operar wall/play e moderar midias |
| `event.moderator` | `moderator` | Moderar midias |
| `event.media-viewer` | `viewer` | Ver midias e resumo do evento |

## Regra de autorizacao

- Gestao da equipe ativa e dos convites usa `EventPolicy::manageTeam`.
- `super-admin` e `platform-admin` seguem bypass global.
- Owner/manager da organizacao podem gerenciar a equipe do proprio evento.
- `event.manager` pode gerenciar somente a equipe do evento ao qual esta vinculado.
- `operator`, `moderator` e `media-viewer` nao gerenciam equipe.

## Rotas

### Equipe ativa

| Metodo | Rota | Descricao |
|------|------|------|
| GET | `/api/v1/events/{event}/team` | Listar equipe ativa do evento |
| POST | `/api/v1/events/{event}/team` | Vincular usuario existente ao evento |
| PATCH | `/api/v1/events/{event}/team/{member}` | Alterar role/preset do membro |
| DELETE | `/api/v1/events/{event}/team/{member}` | Remover membro ativo |

### Convites pendentes

| Metodo | Rota | Descricao |
|------|------|------|
| GET | `/api/v1/events/{event}/access/invitations` | Listar convites pendentes do evento |
| POST | `/api/v1/events/{event}/access/invitations` | Criar convite pendente reutilizando usuario existente quando aplicavel |

## Status atual

Concluido:

- `EventAccessService` ja consulta `event_team_members`;
- `GET /auth/me` ja expoe `workspaces.event_accesses` e `active_context`;
- `/my-events` ja existe no frontend para usuarios event-scoped;
- presets de evento ja saem do backend;
- convites pendentes por evento ja sao persistidos com `existing_user_id` quando o usuario da plataforma ja existe.

Pendente:

- aceite publico do convite por evento;
- reenvio e revogacao do convite;
- envio opcional do link por WhatsApp do evento/organizacao;
- UI administrativa `/events/:eventId/access`.
