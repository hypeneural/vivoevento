# Audit Module

## Responsabilidade

Trilha de auditoria via Spatie Activitylog, com filtros seguros por permissao e escopo de organizacao.

## Rotas

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | /api/v1/audit | Listar logs de auditoria |
| GET | /api/v1/audit/filters | Opcoes de filtros da auditoria |
| GET | /api/v1/events/{event}/timeline | Timeline de auditoria por evento |
