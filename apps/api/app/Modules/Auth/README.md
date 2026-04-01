# Auth Module

## Responsabilidade
Login, logout, sessão, tokens, recuperação de senha, verificação de e-mail.

## Rotas
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| POST | /api/v1/auth/login | LoginController@login | Autenticar |
| POST | /api/v1/auth/logout | LoginController@logout | Encerrar sessão |

## Dependências
- Users (model User)
- Laravel Sanctum (tokens)
- Laravel Fortify (password reset, email verification)
