# Channels Module

## Responsabilidade
Gerenciar canais de entrada de mídia: WhatsApp, link público, QR, Telegram.

## Entidades
- **EventChannel** — canal vinculado a um evento

## Rotas
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /api/v1/event-channels | Listar canais |
| POST | /api/v1/event-channels | Criar canal |
| GET | /api/v1/event-channels/{id} | Detalhes |
| DELETE | /api/v1/event-channels/{id} | Remover |

## Dependências
- Events
