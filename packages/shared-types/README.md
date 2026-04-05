# Evento Vivo - Shared Types

Este pacote contem tipos TypeScript compartilhados entre as aplicacoes do monorepo.

## Estrutura

```text
shared-types/
├── src/
│   ├── index.ts
│   └── wall.ts
└── README.md
```

## Contratos Atuais

- `wall.ts`
  - payloads HTTP do player publico
  - payloads dos eventos realtime
  - nomes canonicos dos eventos do telao
  - status publicos, incluindo `disabled`
