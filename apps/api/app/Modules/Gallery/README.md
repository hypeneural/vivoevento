# Gallery Module

## Responsabilidade
Galeria ao vivo, curadoria, destaques e exibição pública.

## Rotas
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /api/v1/gallery | Catalogo admin da galeria com filtros |
| GET | /api/v1/events/{id}/gallery | Listar galeria (admin) |
| POST | /api/v1/events/{id}/gallery/{media}/publish | Publicar na galeria |
| POST | /api/v1/events/{id}/gallery/{media}/feature | Toggle destaque |
| DELETE | /api/v1/events/{id}/gallery/{media} | Remover da galeria |
| GET | /api/v1/public/events/{slug}/gallery | Galeria pública |

## Dependências
- Events, MediaProcessing
