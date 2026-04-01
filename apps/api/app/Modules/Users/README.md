# Users Module

## Responsabilidade
Gerenciar usuários do sistema, vinculados a organizações.

## Entidades
- **User** — usuário do sistema (extends Authenticatable)

## Rotas
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | /api/v1/me | MeController@show | Usuário autenticado |
| PATCH | /api/v1/me | MeController@update | Atualizar perfil |
| GET | /api/v1/users | UserController@index | Listar usuários |
| GET | /api/v1/users/{id} | UserController@show | Detalhes do user |

## Dependências
- Organizations (membros)
- Roles (permissões via Spatie)
