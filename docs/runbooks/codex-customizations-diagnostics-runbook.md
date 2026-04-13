# Codex Customizations Diagnostics Runbook - 2026-04-12

## Objetivo

Padronizar a verificacao das camadas agent-native do repo quando houver mudanca em:

- `AGENTS.md` e `AGENTS.override.md`
- `.github/copilot-instructions.md`
- `.github/instructions/**/*.instructions.md`
- `.github/prompts/*.prompt.md`
- `.github/agents/*.agent.md`
- `.agents/skills/`
- `.codex/config.toml`

Este runbook existe para responder rapidamente:

1. qual contexto o Codex esta carregando;
2. qual override esta vencendo no diretorio atual;
3. quais skills aparecem como elegiveis;
4. qual e o estado atual da revalidacao da suite completa da API.

---

## Quando usar

Use este runbook quando:

- uma nova camada de customizacao foi adicionada ou alterada;
- o agente parecer ignorar regra local;
- uma skill nao ativar ou ativar fora de contexto;
- o time quiser confirmar o baseline antes de iniciar uma feature longa;
- a CI da API divergir do resultado local.

---

## Fontes oficiais

- OpenAI Codex best practices:
  - `https://developers.openai.com/codex/learn/best-practices`
- OpenAI Codex AGENTS guide:
  - `https://developers.openai.com/codex/guides/agents-md`
- OpenAI Codex config reference:
  - `https://developers.openai.com/codex/config-reference`
- VS Code customization overview:
  - `https://code.visualstudio.com/docs/copilot/customization/overview`
- VS Code custom instructions:
  - `https://code.visualstudio.com/docs/copilot/customization/custom-instructions`
- VS Code custom agents:
  - `https://code.visualstudio.com/docs/copilot/customization/custom-agents`
- VS Code agent skills:
  - `https://code.visualstudio.com/docs/copilot/customization/agent-skills`

---

## Pre-requisitos

### Codex local

- `codex` disponivel no PATH
- projeto aberto em pasta confiavel

### GitHub Actions

Preferencial:

- `gh` instalado e autenticado

Fallback:

- sem `gh`, usar a API publica do GitHub ou a pagina publica do run
- isso normalmente so mostra status e anotacoes resumidas, nao o log completo

---

## Checklist rapido

### 1. Confirmar baseline do projeto

```powershell
Get-Location
Get-Content .codex\config.toml
codex --help
codex debug --help
```

Sinais esperados hoje:

- `model = "gpt-5.4"`
- `approval_policy = "on-request"`
- `sandbox_mode = "workspace-write"`
- `personality = "pragmatic"`
- `web_search = "cached"`

### 2. Confirmar qual AGENTS vence por diretorio

```powershell
codex debug prompt-input "check"
```

Rodar pelo menos em:

- raiz do repo
- `apps/api`
- `docs`

Sinais esperados hoje:

- na raiz, aparece o `AGENTS.md` raiz
- em `apps/api`, aparece o bloco `API Override`
- em `docs`, aparece o bloco `Docs Override`

### 3. Confirmar skills disponiveis por escopo

Usar o mesmo comando:

```powershell
codex debug prompt-input "check"
```

Sinais esperados hoje:

- na raiz:
  - `feature-delivery`
  - `contract-impact-check`
- em `apps/api`:
  - os dois da raiz
  - `laravel-module-change`
- em `docs`:
  - os dois da raiz
  - `verify-and-close`

Observacao:

- a skill aparecer na lista prova discovery por escopo;
- isso nao prova, sozinho, que a ativacao automatica esta boa em todo prompt;
- quando houver ativacao errada, ajustar primeiro a `description` da skill.

### 4. Confirmar superfice do VS Code

No VS Code, verificar:

1. Chat Customizations editor
2. Diagnostics de customizacao
3. agent ativo, tools ativas e prompt file selecionado

Checklist minimo:

- `copilot-instructions` carregado
- override do diretorio atual carregado
- prompt file correto quando houver slash command
- agent correto para a etapa atual

### 5. Confirmar paridade local da API

Rodar exatamente a sequencia do workflow:

```powershell
cd apps/api
Copy-Item .env.example .env -Force
php artisan key:generate --ansi
php artisan config:clear --ansi
php artisan test --compact
```

Resultado validado em `2026-04-12`:

- `1222` passed
- `7` skipped
- `2` todos
- `9995` assertions
- `727.33s`

Implicacao:

- o baseline do workflow ja passa localmente sem env override extra;
- se a CI remota continuar falhando, o proximo problema provavel esta no runner, no ambiente da Actions, ou em falta de visibilidade do log.

### 6. Confirmar estado do workflow remoto

Fallback sem `gh`:

```powershell
$headers = @{ 'User-Agent' = 'Codex' }
$uri = 'https://api.github.com/repos/hypeneural/vivoevento/actions/workflows/api-suite.yml/runs?branch=codex/agent-native-p1&per_page=5'
(Invoke-RestMethod -Uri $uri -Headers $headers).workflow_runs |
  Select-Object id,status,conclusion,head_sha,html_url
```

Preferencial com `gh`:

```powershell
gh auth status
gh run view <run-id> --json name,workflowName,conclusion,status,url,event,headBranch,headSha,jobs
gh run view <run-id> --log
```

Estado conhecido em `2026-04-12`:

- o run `24316799489` terminou com `failure`
- a pagina publica mostra apenas:
  - `Process completed with exit code 2`
- sem `gh` autenticado ou sessao autenticada no GitHub, o log detalhado nao fica disponivel daqui

---

## O que registrar depois do diagnostico

Atualizar estes arquivos:

- `docs/active/<feature>/STATUS.md`
- `docs/active/<feature>/VERIFY.md`

Registrar:

- comandos rodados
- o que foi confirmado
- o que continua pendente
- URL do run remoto, quando houver

---

## Limites conhecidos

- o comando `codex debug prompt-input` ajuda a verificar precedencia e discovery, mas nao substitui uso real dos prompts;
- a API publica do GitHub nao entrega o mesmo nivel de detalhe que `gh run view --log`;
- se `gh` nao estiver instalado, a depuracao de CI fica parcialmente cega;
- logs publicos da Actions podem exigir autenticacao para detalhamento completo.
