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
| POST | `/api/v1/events/{event}/access/invitations/{invitation}/resend` | Rotacionar token e reenviar convite pendente |
| POST | `/api/v1/events/{event}/access/invitations/{invitation}/revoke` | Revogar convite pendente antes do aceite |

## Status atual

Concluido:

- `EventAccessService` ja consulta `event_team_members`;
- `GET /auth/me` ja expoe `workspaces.event_accesses` e `active_context`;
- `/my-events` ja existe no frontend para usuarios event-scoped;
- presets de evento ja saem do backend;
- convites pendentes por evento ja sao persistidos com `existing_user_id` quando o usuario da plataforma ja existe.
- leitura publica do convite por evento ja existe em `/api/v1/public/event-invitations/{token}`;
- aceite publico do convite por evento ja cria usuario novo sem criar organizacao quando necessario;
- aceite autenticado do convite por evento ja reutiliza o `users.id` existente sem duplicacao.
- criacao de convite com `send_via_whatsapp=true` ja tenta despachar pela instancia padrao do evento ou da organizacao.
- reenvio agora rotaciona o token e pode reenfileirar o convite por WhatsApp.
- revogacao agora bloqueia imediatamente o token publico do convite.
- a UI administrativa `/events/:eventId/access` ja existe no web com equipe ativa, convites pendentes, reenvio, revogacao e remocao de acesso ativo.

Pendente:

- aceite publico ainda nao cobre expiracao com renovacao automatica de token;
- reenvio manual com canal diferente de WhatsApp ainda nao tem botao dedicado no web;
- auditoria detalhada de entrega por `instance_id/message_id` ainda nao foi materializada no payload da API.
