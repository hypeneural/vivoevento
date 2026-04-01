# Clients Module

## Responsabilidade
Gestão de clientes finais da organização parceira (noiva/noivo, aniversariante, empresa contratante).

## Entidades
- **Client** — cliente final vinculado à organização

## Campos principais
| Campo | Tipo | Descrição |
|-------|------|-----------|
| organization_id | FK | Organização dona |
| type | enum | pessoa_fisica, empresa |
| name | string | Nome do cliente |
| email | string | E-mail de contato |
| phone | string | Telefone |
| document_number | string | CPF/CNPJ |
| notes | text | Observações internas |

## Rotas
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /api/v1/clients | Listar clientes da organização |
| POST | /api/v1/clients | Cadastrar cliente |
| GET | /api/v1/clients/{id} | Detalhes |
| PATCH | /api/v1/clients/{id} | Atualizar |
| DELETE | /api/v1/clients/{id} | Remover (soft delete) |
| GET | /api/v1/clients/{id}/events | Eventos do cliente |

## Regras de negócio
- Cliente sempre pertence a uma organização
- Usuário só vê clientes da própria organização
- Evento pode ter client_id nullable
