# Roles Module

## Responsabilidade
Perfis e permissões do sistema, gerenciados via Spatie Permission.

## Roles Iniciais
- `super-admin` — acesso total
- `platform-admin` — admin da plataforma
- `partner-owner` — dono da organização parceira
- `partner-manager` — gerente da organização
- `event-operator` — operador de evento
- `viewer` — apenas visualização

## Rotas
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /api/v1/roles | Listar roles |
| GET | /api/v1/roles/{id} | Detalhes da role |

## Dependências
- Spatie Permission
- Users
