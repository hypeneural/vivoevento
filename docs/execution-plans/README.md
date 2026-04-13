# Execution Plans

Esta pasta guarda planos executaveis por feature.

Use `docs/execution-plans/` para:

- ordem de implementacao
- milestones
- criterios de aceite
- estrategia de validacao
- riscos, fallback e rollback

Nao use esta pasta para:

- diagnostico historico
- runbook operacional continuo
- estado vivo de feature longa

Fonte de verdade por camada:

- `docs/active/<feature>/` -> contexto vivo e status da feature
- `docs/execution-plans/` -> plano executavel
- `docs/architecture/` -> analise, decisao e referencia historica

Convencao recomendada:

- `<feature>-execution-plan-YYYY-MM-DD.md`
- `<feature>-implementation-plan-YYYY-MM-DD.md` apenas quando o nome ja estiver consolidado assim
- usar `docs/execution-plans/_template/EXECUTION-PLAN.md` como ponto de partida

Checklist minimo de um plano bom:

1. objetivo
2. escopo e fora de escopo
3. estado atual validado
4. estrategia
5. ordem critica
6. validacao
7. criterio de aceite
8. riscos
9. fallback ou rollback

Template canonico:

- `docs/execution-plans/_template/EXECUTION-PLAN.md`
