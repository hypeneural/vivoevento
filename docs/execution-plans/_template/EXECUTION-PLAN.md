# <feature> execution plan - YYYY-MM-DD

## Objetivo

Descrever em um paragrafo o objetivo concreto da entrega.

Documento base e referencias obrigatorias:

- `docs/architecture/<analysis>.md`
- `docs/active/<feature>/STATUS.md` quando existir
- arquivos e modulos que precisam ser lidos antes de editar

---

## Escopo

Entra nesta entrega:

- item 1
- item 2

Fora de escopo:

- item 1
- item 2

---

## Estado atual validado

O que foi validado em codigo, testes ou comportamento real:

- estado atual 1
- estado atual 2

Gaps confirmados:

- gap 1
- gap 2

---

## Estrategia

Explicar a linha tecnica escolhida e por que ela e a mais adequada para a stack atual.

Decisoes fixadas antes da implementacao:

1. decisao 1
2. decisao 2

---

## Ordem critica

1. passo 1
2. passo 2
3. passo 3

Para cada passo, deixar explicito:

- arquivos ou modulos tocados
- dependencia anterior
- criterio minimo para seguir

---

## Validacao

Testes antes do codigo:

- teste ou cobertura 1
- teste ou cobertura 2

Comandos de verificacao:

```bash
# comandos exatos
```

---

## Criterio de aceite

- comportamento esperado 1
- comportamento esperado 2
- validacao minima que precisa estar verde

---

## Riscos

- risco 1
- risco 2

Mitigacoes:

- mitigacao 1
- mitigacao 2

---

## Fallback ou rollback

- como reduzir impacto se a entrega falhar
- o que pode ser desligado, revertido ou degradado

---

## Evidencia esperada ao fechar

- `docs/active/<feature>/VERIFY.md` atualizado quando a feature for longa
- comandos rodados e resultado registrado
- diff pequeno e revisavel
