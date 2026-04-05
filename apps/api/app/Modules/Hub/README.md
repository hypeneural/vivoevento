# Hub Module
## Responsabilidade
Pagina oficial do evento com links centrais (galeria, upload, wall, play) e configurador de hot site mobile-first.

## Entidades
- **EventHubSetting** - configuracoes do Hub por evento
- **HubPreset** - biblioteca de modelos reutilizaveis por organizacao

## Rotas
| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | /api/v1/hub/presets | Listar modelos salvos da organizacao |
| POST | /api/v1/hub/presets | Salvar modelo reutilizavel do Hub |
| GET | /api/v1/events/{id}/hub/settings | Config do hub |
| GET | /api/v1/events/{id}/hub/insights | Performance operacional do hub |
| PATCH | /api/v1/events/{id}/hub/settings | Atualizar config |
| POST | /api/v1/events/{id}/hub/hero-image | Upload direto da imagem de destaque |
| POST | /api/v1/events/{id}/hub/sponsor-logo | Upload direto de logo para parceiros |
| GET | /api/v1/public/events/{slug}/hub | Hub publico |
| POST | /api/v1/public/events/{slug}/hub/click | Tracking publico de CTA |

## Dados suportados
- Headline, subheadline e texto de boas-vindas
- Imagem de destaque no topo do hub
- Upload de logos para parceiros e patrocinadores
- Estilo padrao dos botoes
- Botoes preset e customizados com ordem, rotulo, icone e cores
- Builder config tipado para layout, tema e blocos do hot site
- Biblioteca de modelos por organizacao para reaproveitar tema, blocos, botoes e cores
- Tracking publico de page view e clique por CTA
- Insights operacionais por evento com resumo, timeline e top botoes
