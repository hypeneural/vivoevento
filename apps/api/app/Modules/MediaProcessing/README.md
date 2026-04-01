# MediaProcessing Module

## Responsabilidade
Download, geração de variantes, watermark, thumbnails e moderação de mídia.

## Entidades
- **EventMedia** — mídia do evento com status de processamento e moderação
- **EventMediaVariant** — variantes geradas (thumb, gallery, wall, etc.)
- **MediaProcessingRun** — log de cada execução de processamento

## Jobs (Pipeline)
| Job | Fila | Responsabilidade |
|-----|------|-----------------|
| DownloadInboundMediaJob | media-download | Baixar arquivo da URL |
| GenerateMediaVariantsJob | media-process | Gerar thumb, gallery, wall, etc. |
| RunModerationJob | media-process | Aplicar regras de moderação |
| PublishMediaJob | media-publish | Publicar mídia aprovada |
| BroadcastMediaUpdateJob | media-publish | Enviar update realtime |

## Rotas
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /api/v1/events/{id}/media | Listar mídias do evento |
| GET | /api/v1/media/{id} | Detalhes da mídia |
| POST | /api/v1/media/{id}/approve | Aprovar mídia |
| POST | /api/v1/media/{id}/reject | Rejeitar mídia |
| DELETE | /api/v1/media/{id} | Remover mídia |

## Dependências
- Events, InboundMedia, Gallery, Wall
