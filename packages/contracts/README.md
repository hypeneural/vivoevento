# Evento Vivo — Contratos de API

Este pacote contém os schemas e contratos compartilhados entre frontend e backend.

## Estrutura Futura

```
contracts/
├── schemas/          # JSON Schema / OpenAPI
├── responses/        # Response contracts
└── events/           # Event payload contracts
```

## Uso

Os contratos servem como fonte de verdade para:
- Validação de request/response
- Geração de tipos TypeScript
- Documentação da API
