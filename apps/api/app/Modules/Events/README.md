# Events Module

## Responsabilidade
Gerenciar eventos do Evento Vivo — o núcleo do produto.

## Entidades
- **Event** — evento principal com branding, status, datas
- **EventModule** — módulos habilitados por evento (gallery, wall, play, hub)
- **EventBanner** — banners promocionais do evento

## Casos de Uso
- Criar evento
- Editar evento
- Publicar evento
- Pausar evento
- Encerrar evento
- Arquivar evento
- Duplicar evento

## Rotas
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | /api/v1/events | EventController@index | Listar eventos |
| POST | /api/v1/events | EventController@store | Criar evento |
| GET | /api/v1/events/{id} | EventController@show | Detalhes |
| PATCH | /api/v1/events/{id} | EventController@update | Atualizar |
| DELETE | /api/v1/events/{id} | EventController@destroy | Remover |
| POST | /api/v1/events/{id}/publish | EventStatusController@publish | Publicar |
| POST | /api/v1/events/{id}/archive | EventStatusController@archive | Arquivar |
| GET | /api/v1/events/{id}/share-links | EventQrController@shareLinks | Links publicos e identificadores |
| PATCH | /api/v1/events/{id}/public-links | EventQrController@updateIdentifiers | Atualizar slug/slug de envio |
| POST | /api/v1/events/{id}/public-links/regenerate | EventQrController@regenerateIdentifiers | Regenerar slug/upload slug/wall code |

## Dependências
- Organizations
- Users (created_by)
- Channels
- MediaProcessing
- Gallery, Wall, Play, Hub (settings)
