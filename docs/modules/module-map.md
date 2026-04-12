# Mapa de Modulos - Evento Vivo

## Visao Geral

| # | Modulo | Camada | Etapa | Descricao |
|---|--------|--------|-------|-----------|
| 1 | Organizations | Core | 1 | Contas e organizacoes parceiras |
| 2 | Users | Core | 1 | Usuarios do sistema |
| 3 | Roles | Core | 1 | Perfis e permissoes |
| 4 | Auth | Core | 1 | Autenticacao e tokens |
| 5 | Events | Core | 1 | Eventos como entidade principal |
| 6 | Channels | Ingestao | 2 | Canais de entrada como WhatsApp e link |
| 7 | InboundMedia | Ingestao | 2 | Recepcao e normalizacao de webhooks |
| 8 | WhatsApp | Ingestao / Messaging | 2 | Core WhatsApp: instancias, envio, inbound e automacao |
| 9 | Telegram | Ingestao / Messaging | 2 | Core Telegram privado: webhook, idempotencia e sessao direta por codigo |
| 10 | MediaProcessing | Processamento | 2 | Download, variantes, moderacao e publicacao |
| 11 | ContentModeration | Processamento | 2 | Safety moderation e avaliacoes de risco por foto |
| 12 | FaceSearch | Processamento | 2 | Configuracao, indexacao facial por evento e base vetorial inicial |
| 13 | EventPeople | Experiencia / IA | 3 | Pessoas do evento, relacoes, review guiado e base de cobertura inteligente |
| 14 | MediaIntelligence | Processamento | 2 | VLM rapido, prompts por evento e historico semantico por foto |
| 15 | Gallery | Experiencia | 3 | Galeria publica, curadoria e builder versionado da experiencia |
| 16 | Wall | Experiencia | 3 | Telao e slideshow realtime |
| 17 | EventOperations | Experiencia / Operacoes | 3 | Sala operacional realtime por evento |
| 18 | Play | Experiencia | 4 | Jogos interativos |
| 19 | Hub | Experiencia | 4 | Pagina central do evento |
| 20 | Plans | Negocio | 5 | Catalogo de planos |
| 21 | Billing | Negocio | 5 | Assinaturas e cobrancas |
| 22 | Partners | Negocio | 5 | Camada B2B para parceiros |
| 23 | Analytics | Suporte | 5 | Metricas e consolidados |
| 24 | Audit | Suporte | 5 | Trilha de auditoria |
| 25 | Notifications | Suporte | 5 | Avisos e alertas |

## Dependencias entre Modulos

```mermaid
graph TD
    Organizations --> Users
    Events --> Organizations
    Events --> Users
    Channels --> Events
    InboundMedia --> Channels
    InboundMedia --> Events
    WhatsApp --> Organizations
    WhatsApp --> Events
    WhatsApp --> InboundMedia
    Telegram --> Events
    Telegram --> InboundMedia
    MediaProcessing --> InboundMedia
    MediaProcessing --> Events
    ContentModeration --> MediaProcessing
    ContentModeration --> Events
    FaceSearch --> Events
    FaceSearch --> MediaProcessing
    EventPeople --> Events
    EventPeople --> FaceSearch
    EventPeople --> MediaProcessing
    MediaIntelligence --> Events
    MediaIntelligence --> MediaProcessing
    Gallery --> MediaProcessing
    Gallery --> Events
    Wall --> MediaProcessing
    Wall --> Events
    EventOperations --> Events
    EventOperations --> InboundMedia
    EventOperations --> MediaProcessing
    EventOperations --> Gallery
    EventOperations --> Wall
    EventOperations --> Audit
    Play --> Events
    Hub --> Events
    Billing --> Plans
    Billing --> Organizations
    Partners --> Organizations
    Analytics --> Events
    Analytics --> Organizations
    Audit --> Users
```

## Fluxo Principal de Midia

```text
Webhook -> InboundMedia -> MediaProcessing -> Gallery/Wall
           (webhooks)     (media-download)   (media-publish)
                          (media-process)
                          (media-safety)
```

## Fluxo WhatsApp

```text
Z-API Webhook -> WhatsApp Module -> [Se midia+evento] -> InboundMedia -> MediaProcessing -> Gallery
                     |
                     +-> [Se texto/sistema] -> Persiste internamente
                     |
                     +-> [Se grupo vinculado] -> Auto-reaction / Auto-reply
```

## Fluxo de Envio WhatsApp

```text
Frontend -> API -> WhatsAppMessagingService -> SendWhatsAppMessageJob -> ProviderAdapter -> Z-API
                         |                          |
                         +-> whatsapp_messages      +-> dispatch_log
                             (status: queued)          (audit trail)
```
