# Organizations Module

## Responsabilidade
Gerenciar organizações (contas) do Evento Vivo. Uma organização pode ser fotógrafo, cerimonial, agência, marca ou anfitrião.

## Entidades
- **Organization** — conta principal que possui eventos
- **OrganizationMember** — relação user ↔ organization

## Rotas
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | /api/v1/organizations | index | Listar organizações |
| POST | /api/v1/organizations | store | Criar organização |
| GET | /api/v1/organizations/{id} | show | Detalhes da org |
| PATCH | /api/v1/organizations/{id} | update | Atualizar org |
| DELETE | /api/v1/organizations/{id} | destroy | Remover org |

## Dependências
- Users (membros)
- Plans/Billing (assinaturas)
