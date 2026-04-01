# Evento Vivo — Architecture Overview

## Visão Geral

O Evento Vivo é uma plataforma SaaS de experiências vivas para eventos. A arquitetura segue os princípios de:

- **API-first**: Toda funcionalidade exposta via REST API
- **Modular por domínio**: Cada domínio de negócio é um módulo isolado
- **Monorepo simples**: Tudo em um repositório para facilitar manutenção

## Diagrama de Alto Nível

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENTS                                   │
│  (React SPA · Mobile App · Public Pages · External Webhooks)     │
└─────────────────┬───────────────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                      API GATEWAY                                 │
│              Laravel (Sanctum + Fortify)                         │
│         Rate Limiting · CORS · Auth · Versioning                 │
└────────┬──────────────┬──────────────┬──────────────────────────┘
         │              │              │
         ▼              ▼              ▼
┌────────────┐  ┌────────────┐  ┌────────────┐
│   Auth     │  │   Events   │  │  Channels  │
│   Module   │  │   Module   │  │   Module   │
└────────────┘  └────────────┘  └──────┬─────┘
                                       │
                                       ▼
                                ┌────────────┐     ┌────────────┐
                                │ InboundMedia│────▶│ Media      │
                                │   Module   │     │ Processing │
                                └────────────┘     └──────┬─────┘
                                                          │
                              ┌───────────────────────────┤
                              ▼              ▼            ▼
                       ┌──────────┐  ┌──────────┐  ┌──────────┐
                       │ Gallery  │  │   Wall   │  │   Play   │
                       │  Module  │  │  Module  │  │  Module  │
                       └──────────┘  └──────────┘  └──────────┘
                                            │
                                            ▼
                                     ┌──────────┐
                                     │  Reverb  │
                                     │ WebSocket│
                                     └──────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    INFRAESTRUTURA                                │
│                                                                  │
│  PostgreSQL    Redis    MinIO (S3)    Horizon    Mailpit          │
│  (dados)      (cache/  (arquivos)   (filas)    (e-mail dev)      │
│               filas)                                             │
└─────────────────────────────────────────────────────────────────┘
```

## Princípios

1. **Controller Fino**: Recebe request → chama Action → retorna Resource
2. **Actions para Escrita**: Toda operação de escrita passa por uma Action
3. **Queries para Leitura**: Listagens complexas usam Query Objects
4. **Jobs para Async**: Todo processamento pesado vai para filas
5. **Events para Desacoplamento**: Módulos se comunicam via eventos
6. **Policies para Autorização**: Permissões centralizadas por módulo

## Stack de Filas

| Fila | Workers | Uso |
|------|---------|-----|
| `webhooks` | 2 | Webhooks recebidos |
| `media-download` | 3 | Download de mídia |
| `media-process` | 3 | Geração de variantes |
| `media-publish` | 2 | Publicação de mídia |
| `notifications` | 2 | E-mails e alertas |
| `analytics` | 1 | Agregação de métricas |
| `billing` | 1 | Processamento de cobranças |
| `default` | 2 | Fallback geral |

## Broadcasting (Reverb)

| Canal | Tipo | Dados |
|-------|------|-------|
| `event.{id}.gallery` | Private | Novas mídias na galeria |
| `event.{id}.wall` | Private | Próxima mídia do slideshow |
| `event.{id}.moderation` | Private | Status de moderação |
| `event.{id}.play` | Private | Resultados de jogos |
