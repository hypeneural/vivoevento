# Vibecoding Agent-Friendly Repo Audit - 2026-04-12

## Objetivo

Auditar o estado atual do monorepo `eventovivo` para deixar o repositorio mais facil de interpretar por agentes no IDE e mais produtivo para vibecoding, sem perder a arquitetura modular existente.

Esta leitura foi validada contra:

- estrutura real do repo em `2026-04-12`;
- stack declarada em `README.md`, `AGENTS.md`, `apps/api/composer.json`, `apps/web/package.json` e `apps/landing/package.json`;
- docs oficiais atuais do Codex/OpenAI e do VS Code para customizacoes de agentes, prompt files, instructions e monorepo discovery.

---

## Veredito Executivo

O repositorio ja tem uma base muito melhor que a media para agentic development:

- backend modular por dominio;
- frontend tambem organizado por modulo;
- documentacao tecnica abundante;
- testes relevantes em varias frentes;
- scripts de setup, dev, deploy e operacao;
- um `AGENTS.md` raiz que ja expressa ownership, convencoes e restricoes reais.

Mas hoje o ganho de produtividade para vibecoding ainda esta abaixo do potencial por cinco motivos centrais:

1. o contexto persistente esta forte, mas esta pesado e com drift;
2. a documentacao ativa esta concentrada demais em `docs/architecture/`;
3. faltam artefatos nativos do IDE para customizacao contextual;
4. o contrato de ambiente local/CI esta inconsistente em alguns pontos;
5. a verificacao automatizada nao esta uniforme entre apps.

Conclusao pratica:

- o problema principal nao e falta de documentacao;
- o problema principal e falta de fronteiras operacionais legiveis para agentes.
- o repo hoje esta mais proximo de `bem documentado` do que de `agent-native`;
- o ganho maior agora vem de transformar regras difusas em execucao previsivel, com configuracao de projeto, papeis claros e fonte de verdade por camada.

### O que realmente importa

Validando contra as docs oficiais atuais, a hierarquia mais importante para este monorepo ficou assim:

1. `.github/copilot-instructions.md`
   - baseline sempre-on do workspace no VS Code;
   - deve conter apenas convencoes transversais curtas.
2. `AGENTS.md`
   - contrato operacional do agente;
   - ownership do repo, como validar, o que significa pronto e restricoes reais.
3. `.github/instructions/**/*.instructions.md`
   - regras por stack, linguagem e pasta;
   - entram quando o arquivo ou a tarefa pedem contexto mais especifico.
4. `AGENTS.override.md` perto do codigo
   - override local para trabalho altamente especializado em `apps/api`, `apps/web`, `apps/landing` e `docs`.
5. `.github/prompts/*.prompt.md`
   - workflows repetidos;
   - devem entrar cedo, nao so depois de toda a limpeza fina.
6. `.github/agents/*.agent.md`
   - planner, implementer e reviewer com ferramentas e handoffs claros.
7. `skills` de repositorio
   - workflows especializados, pequenos e carregados sob demanda;
   - nao sao o lugar para despejar toda a inteligencia do repo.
8. `hooks`
   - enforcement deterministico em pontos do ciclo de vida;
   - devem entrar depois que o baseline ja estiver claro.

Regra pratica validada:

- raiz curta;
- contexto especifico perto do codigo;
- `instructions` e `AGENTS` guardam regra;
- prompt files guardam fluxo leve e repetido;
- custom agents guardam papel, ferramentas e handoffs;
- skills guardam workflows especializados com exemplos, scripts e recursos;
- hooks guardam validacao e enforcement deterministico;
- MCP entra apenas quando o contexto relevante esta fora do repo ou muda com frequencia.

### Veredito real

O Eventovivo nao sofre principalmente de falta de markdown.

Ele sofre de falta de um trilho canonico que diga ao agente:

- qual arquivo manda em cada tipo de decisao;
- qual fluxo seguir quando a tarefa sai de analise para execucao;
- qual agente deve assumir cada etapa;
- qual validacao minima precisa acontecer antes de encerrar.

Enquanto essa divisao nao existir de forma nativa no IDE e no Codex, o risco e continuar usando documentos como se fossem ao mesmo tempo:

- regra;
- workflow;
- checklist;
- historico;
- estado vivo.

---

## Estado Atual Validado

### O que ja ajuda bastante

- `AGENTS.md` raiz ja define arquitetura, ownership e convencoes reais do produto.
- `apps/api/app/Modules/*` e `apps/web/src/modules/*` deixam o ownership bem claro.
- `docs/modules/module-map.md` ja resume o mapa de dominio.
- `README.md` raiz esta muito mais rica que os READMEs secundarios.
- `Makefile` concentra comandos uteis de setup, dev, test e operacao.
- O backend esta em Laravel `13.x` com stack moderna para filas, realtime e observabilidade.
- O frontend admin e a landing estao em React `18` + TypeScript + Vite `5`.

### O que esta atrapalhando mais do que deveria

- `.github/` ja tem `copilot-instructions`, `instructions`, `prompts`, `agents` e um workflow dedicado para a suite completa da API, mas esse trilho ainda precisa virar habito operacional do time.
- `.agents/skills/` e as skills locais por area ja foram materializados como camada de workflows especializados; o gap restante agora e calibrar discovery e uso real sem inflar contexto.
- o baseline do workspace ja foi melhorado com `.vscode/settings.json`, `eventovivo.code-workspace` e `.codex/config.toml`, e agora ja existe um runbook canonico para diagnostico dessas camadas; o gap restante e transformar isso em habito operacional do time.
- `docs/architecture/` ainda concentra muita referencia historica, imagens de validacao e analises; hoje ha `92` arquivos ali e `31` planos ja foram separados para `docs/execution-plans/`.
- O repo raiz esta com artefatos temporarios e copias de release/deploy que poluem busca e contexto.
- o contrato oficial de Node ja foi formalizado, mas ainda precisa ser mantido alinhado em futuras mudancas de CI e onboarding.
- A CI em `.github/workflows/moderation.yml` cobre apenas uma fatia do produto, nao o monorepo inteiro.
- `gh` ja foi instalado neste ambiente, mas ainda nao esta autenticado; a inspeccao detalhada de GitHub Actions ainda depende de `gh auth login`, `GH_TOKEN` ou outra sessao autenticada.

---

## Analise Detalhada da Stack

### 1. Backend

O backend atual esta tecnicamente bem posicionado para agentic work:

- Laravel `13`;
- PHP `8.3`;
- Horizon, Reverb, Pulse, Telescope e Pennant;
- modulos por dominio em `apps/api/app/Modules/*`;
- stack de dados e permissao previsivel com Spatie.

Veredito:

- a stack backend nao e gargalo para vibecoding;
- o problema esta mais em contrato operacional e descoberta de contexto do que em tecnologia;
- o maior drift aqui era documental e ja foi parcialmente corrigido com um README operacional em `apps/api/README.md`.

### 2. Painel admin e experiencias web

O `apps/web` tambem esta em uma stack adequada:

- React `18`;
- TypeScript `5`;
- Vite `5`;
- TanStack Query `5`;
- Radix + shadcn/ui;
- Vitest;
- `pusher-js` compativel com Reverb.

Veredito:

- a stack frontend tambem nao e o problema;
- o painel ja tem modularidade e runtime suficientes para trabalho orientado por agente;
- o que falta e reduzir variacao humana no fluxo de execucao.

Sinais reais:

- `npm run type-check` passou;
- o subset de moderation da CI passou com `44` testes;
- a CI atual cobre apenas uma vertical do produto;
- ha warning de backlog em migracao futura do React Router v7.

### 3. Landing

O `apps/landing` esta separado e isso ajuda ownership, mas sua stack e mais sensivel a drift de teste:

- React `18`;
- Vite `5`;
- GSAP, `motion`, Lenis, Rive;
- Sass modular;
- Vitest.

Veredito:

- a separacao da landing foi uma boa decisao arquitetural;
- em contrapartida, a combinacao de motion, storytelling e copy torna os testes mais frageis;
- isso ja apareceu na pratica: a suite atual quebrou por drift entre componente real e assercoes antigas.

### 4. Infra, scripts e operacao

Ha bons sinais de maturidade operacional:

- `Makefile` com setup, dev, test, lint e utilitarios;
- `deploy/` e `scripts/` versionados;
- superficies publicas separadas por dominio;
- infra local e de producao bem clara no `README.md`.

Veredito:

- o repo ja esta maduro o bastante para usar configuracao de projeto do Codex;
- hoje falta apenas transformar essa maturidade operacional em defaults explicitos para o agente.

### 5. O que a stack esta dizendo sobre o problema real

Cruzar manifests, CI, docs e estrutura mostra um padrao consistente:

- a base tecnica esta suficientemente moderna;
- o modulo por dominio ja reduz ambiguidade de ownership;
- a dor real esta em execucao e precedencia de contexto.

Em resumo:

- o Eventovivo nao precisa de outra stack para vibecoding performar bem;
- ele precisa de fronteiras operacionais melhores.

---

## Drift e Itens Desatualizados

### 1. Stack documentada com drift

- `apps/api/README.md` ja foi substituido por um README operacional do backend.
- `apps/api/composer.json` ainda carrega `name`, `description` e `keywords` do skeleton Laravel, entao ainda existe um resto pequeno de identidade desatualizada no manifesto da API.
- o contrato oficial de Node agora foi fechado em `22 LTS` com `.nvmrc`, `README.md` e CI alinhados.
- antes desse ajuste, o repo estava assim:
  - a CI de `apps/web` usa Node `22`;
  - o ambiente local validado nesta auditoria estava em Node `22.14.0`;
  - o `README.md` ainda pedia Node `24 LTS`.

### 2. Contexto persistente bom, mas grande demais

- O `AGENTS.md` raiz esta forte em conteudo, mas hoje esta longo para ser sempre-on.
- As docs atuais do Codex recomendam reduzir o tamanho do `AGENTS.md` e usar nesting/overrides por area.
- Antes de adicionar mais sempre-on context, o correto e enxugar o que ja existe na raiz.
- No VS Code, o ponto de partida recomendado hoje e `/.github/copilot-instructions.md`; `AGENTS.md` continua importante, mas nao deve carregar sozinho o repo nas costas.

### 3. Estrutura de docs pouco amigavel para descoberta automatica

Antes da separacao, o padrao mais comum estava assim:

- `docs/architecture/<analysis>.md`
- `docs/architecture/<execution-plan>.md`

O ajuste melhorou a descoberta, mas ainda nao esta completo porque:

- ainda nao existe um lugar canonico adotado para estado ativo de feature;
- parte do historico ainda referencia o bucket antigo;
- `docs/active/` ainda nao foi institucionalizado como contexto vivo.

### 4. Dois sistemas de contexto ativos

Hoje ha conhecimento espalhado entre:

- `docs/architecture/*`;
- `AGENTS.md`;
- `README.md`;
- `.kiro/specs/*`.

Isso nao e necessariamente errado, mas sem regra clara vira contexto concorrente.

Regra recomendada:

- `copilot-instructions.md` e `AGENTS.md` definem o baseline;
- `AGENTS.override.md` define comportamento local por area;
- `docs/active/<feature>/` e o unico contexto vivo de uma feature em andamento;
- o restante de `docs/architecture/` deve ser tratado como historico, referencia ou diagnostico.
- `.kiro/specs/*` deve ser tratado como contexto auxiliar importado, nunca como instrucao vigente por padrao.

### 5. Poluicao de busca

No estado atual da auditoria havia no topo do repo:

- `tmp_moderation_deploy_source/`
- `tmp_moderation_release/`
- `tmp-flow-*.png`
- `test-results/`

Esses artefatos aumentam o ruido de busca e fazem agentes encontrarem caminhos duplicados com mais facilidade.

Estado atual:

- os padroes principais desses artefatos ja foram adicionados ao `.gitignore`;
- a limpeza fisica desses arquivos pode continuar sendo feita sem risco, mas o ruido de busca e de `git status` ja caiu.

---

## Testes Rodados em 2026-04-12

### Passou

- `apps/web`: `npm run type-check`
- `apps/web`: smoke subset igual ao workflow de moderation
  - `8` arquivos
  - `44` testes passando
- `apps/landing`: `npm run type-check`
- `apps/landing`: `npm run test`
  - `16` arquivos
  - `157` testes passando
- `apps/api`: `php artisan test tests/Unit/Modules/MediaProcessing/VideoMetadataExtractorServiceTest.php`
  - `4` testes
  - `51` assertions

### Corrigido nesta rodada

- `apps/landing`
  - `FinalCTASection.test.tsx` passou a mockar `useSmoothScroll` e ficou alinhado ao componente real;
  - `CTAFloating.test.tsx` voltou a refletir o contrato de acessibilidade e persistencia em `sessionStorage`;
  - `TestimonialsSection.test.tsx` e `TestimonialsSection.fallback.test.tsx` foram reescritos para o layout e o fallback atuais;
  - `FAQSection.test.tsx` ficou deterministico com `matchMedia` no setup e `fireEvent.keyDown` nas assercoes de teclado.

- `apps/api`
  - `Tests\\Unit\\Modules\\MediaProcessing\\VideoMetadataExtractorServiceTest` voltou a passar com assercao tolerante a `command` serializado como array ou string.

### Revalidado localmente depois do baseline

- `apps/api`: `php artisan test --compact --stop-on-failure`
  - `1220` testes passando
  - `7` skipped
  - `2` todos
  - `9985` assertions
  - duracao de `549.13s`

- `apps/api`: sequencia exata do workflow remoto
  - `Copy-Item .env.example .env -Force`
  - `php artisan key:generate --ansi`
  - `php artisan config:clear --ansi`
  - `php artisan test --compact`
  - resultado:
    - `1222` testes passando
    - `7` skipped
    - `2` todos
    - `9995` assertions
    - duracao de `727.33s`

Implicacao pratica:

- a suite completa da API ja tem prova local forte;
- a CI agora serve para repetir esse resultado no GitHub Actions, nao mais para descobrir se o baseline minimo funciona;
- se o run remoto continuar falhando, o proximo gargalo e visibilidade de log, nao baseline local.

### Sinal adicional

- O subset de testes do `apps/web` emite warnings de migracao futura do React Router v7.
- Isso nao quebra agora, mas ja e um lembrete de backlog tecnico.
- `apps/landing` ainda emite warnings nao bloqueantes:
  - `Dart Sass legacy-js-api` deprecado;
  - `layoutId` passando para DOM no mock de `ExperienceModulesSection.test.tsx`;
  - `Not implemented: navigation to another Document` nos cliques reais de link em `CTAFloating.test.tsx`.

---

## Recomendacao de Estrutura Alvo

### 1. Baseline do IDE + contrato do agente

Objetivo:

- separar claramente regras sempre-on do workspace, contrato do agente e regras contextuais;
- deixar a raiz leve;
- empurrar especificidade para perto do codigo.

Estrutura recomendada:

```text
.github/
  copilot-instructions.md
  instructions/
    backend/laravel.instructions.md
    frontend/react.instructions.md
    docs/docs.instructions.md
    testing/tests.instructions.md
```

Regra:

- `.github/copilot-instructions.md` vira o baseline curto do VS Code;
- `AGENTS.md` fica como contrato de repo, ownership modular, validacao e done;
- `*.instructions.md` entram por contexto de arquivo/tarefa.
- no VS Code, instruction files multiplos se combinam sem ordem garantida;
- por isso, regra que depende de precedencia deve ir para `AGENTS.override.md`, nao para uma disputa entre varios `*.instructions.md`.

### 2. Fonte de verdade por camada

O repositorio precisa declarar explicitamente qual artefato manda em cada tipo de decisao.

Mapa recomendado:

- `.github/copilot-instructions.md`
  - convencoes transversais do workspace;
- `AGENTS.md`
  - contrato operacional do agente e definicao de pronto;
- `AGENTS.override.md`
  - regras especializadas por app, pasta ou dominio;
- `.github/instructions/**/*.instructions.md`
  - guias contextuais por stack e por pasta com `applyTo`;
- `docs/active/<feature>/`
  - unico contexto vivo de uma feature em andamento;
- `docs/execution-plans/`
  - plano executavel da feature;
- `docs/architecture/`
  - diagnostico e historico, nunca instrucao vigente por padrao;
- `README.md`
  - onboarding humano e visao geral do produto;
- `.kiro/specs/*`
  - referencia secundaria apenas quando explicitamente mencionada.

### 3. Overrides por area

Estrutura recomendada:

```text
AGENTS.md
apps/
  api/AGENTS.override.md
  web/AGENTS.override.md
  landing/AGENTS.override.md
docs/AGENTS.override.md
```

Regra:

- a raiz explica o que vale para todo o monorepo;
- `apps/api/AGENTS.override.md` detalha modulos Laravel, Actions, migrations, contratos HTTP, filas e testes;
- `apps/web/AGENTS.override.md` detalha modulos React, roteamento, acessibilidade, testes e runtime publico;
- `apps/landing/AGENTS.override.md` detalha performance, animacao, copy e validacoes especificas da landing;
- `docs/AGENTS.override.md` detalha como escrever analise, execution plan, `STATUS.md` e `VERIFY.md`.

### 4. Docs mais descobriveis por agente

Estrutura recomendada:

```text
docs/
  architecture/
  execution-plans/
  runbooks/
  active/
    <feature-name>/
      STATUS.md
      DECISIONS.md
      VERIFY.md
```

Regra:

- `architecture/` para analise, decisao e leitura de estado;
- `execution-plans/` para planos executaveis;
- `runbooks/` para operacao e troubleshooting;
- `active/` para o estado vivo de features longas.
- apenas `docs/active/<feature>/` deve ser tratado como contexto operacional vivo;
- o restante deve ser consumido como referencia, nao como instrucao vigente por padrao.

### 5. Configuracao explicita do Codex por projeto

O repo precisa manter um `.codex/config.toml` no root.

Estado atual:

- a base inicial desse arquivo ja foi criada no P0;
- os proximos ajustes devem ser pequenos e guiados por uso real, nao por especulacao.

Validacao oficial:

- Codex usa `~/.codex/config.toml` para defaults pessoais;
- aceita overrides de projeto em `.codex/config.toml`;
- CLI, IDE e app compartilham as mesmas camadas;
- `approval_policy`, `sandbox_mode`, `model`, `plan_mode_reasoning_effort`, `profiles` e MCP podem nascer ali;
- `instructions` e reservado; para instrucao persistente o caminho recomendado continua sendo `AGENTS.md` ou `model_instructions_file`.

Regra:

- manter defaults seguros no projeto;
- tirar do prompt decisoes repetidas sobre modelo, aprovacao e sandbox;
- usar profiles depois, quando planner e implementer tiverem necessidades claramente distintas.

Shape inicial recomendado:

```toml
#:schema https://developers.openai.com/codex/config-schema.json

model = "gpt-5.4"
approval_policy = "on-request"
sandbox_mode = "workspace-write"
plan_mode_reasoning_effort = "medium"
model_reasoning_effort = "medium"
personality = "pragmatic"
web_search = "cached"
```

### 6. Workflows repetidos em prompt files

Entrar logo depois da limpeza minima da raiz.

Estrutura recomendada:

```text
.github/prompts/
  plan-feature.prompt.md
  implement-from-plan.prompt.md
  review-module.prompt.md
  trace-feature-flow.prompt.md
  verify-feature.prompt.md
```

Regra:

- prompt files sao o melhor formato para o fluxo atual de `analise -> plano -> implementacao -> review`;
- devem nascer cedo porque o VS Code os trata como slash commands reutilizaveis;
- devem virar o trilho operacional principal do IDE antes de ampliar `skills`.

### 7. Papeis claros de agente

Entrar junto com os prompt files, nao muito depois.

Estrutura recomendada:

```text
.github/agents/
  planner.agent.md
  implementer.agent.md
  reviewer.agent.md
```

Regra:

- `planner` deve ser read-only e orientado a plano;
- `implementer` deve ter edicao e terminal;
- `reviewer` deve focar em diff, testes, regressao e validacao;
- handoffs entre eles devem ser explicitos.
- controles como `tools`, `agents`, `user-invocable` e `disable-model-invocation` devem ficar em custom agents, nao em skills.

### 8. Observabilidade das customizacoes

Depois que `instructions`, `prompts`, `agents` e `skills` entrarem, o problema deixa de ser falta de contexto e passa a ser diagnostico de precedencia.

Regra:

- usar o Chat Customizations editor para visualizar e editar a arvore de customizacao;
- usar Diagnostics para verificar quais instructions, prompt files, agents e skills estao carregados;
- usar Agent Debug Logs e Chat Debug view para investigar override errado, prompt file nao aplicado ou ferramenta inesperada.

Checklist minimo apos cada rodada grande:

1. quais instructions o chat carregou;
2. qual agent esta ativo e com quais tools;
3. se o diretorio atual puxou o `AGENTS.override.md` esperado.

### 9. Skills pequenas, estreitas e portateis

Entrar assim que o baseline e os primeiros prompt files estiverem estaveis.

Validacao oficial:

- OpenAI/Codex recomenda skill focada em um trabalho so, com `2` a `3` casos de uso concretos, descricao clara de quando usar e scripts apenas quando melhorarem confiabilidade;
- VS Code separa skills de custom instructions: instructions definem convencoes, skills empacotam workflows especializados com scripts, exemplos e recursos;
- VS Code suporta project skills em `.github/skills/`, `.claude/skills/` e `.agents/skills/`;
- para maximizar portabilidade com Codex CLI e outras ferramentas compativeis, o caminho mais seguro para shared team skills e `.agents/skills/`.

Estrutura recomendada:

```text
.agents/skills/
  feature-delivery/
  contract-impact-check/
apps/api/.agents/skills/
  laravel-module-change/
apps/web/.agents/skills/
  react-module-change/
docs/.agents/skills/
  verify-and-close/
```

Estado atual:

- a primeira leva dessas `5` skills agora existe exatamente nesses caminhos;
- elas nasceram sem scripts nem assets para manter escopo estreito e seguir a recomendacao oficial de adicionar automacao apenas quando houver repeticao real.

Regra:

- nao usar skill para regra de estilo, convencao de naming ou ownership;
- isso continua em `copilot-instructions`, `AGENTS.md` e `*.instructions.md`;
- skill deve empacotar um workflow claro, com entrada, saida e verificacao;
- skills amplas demais tendem a piorar discovery e precisao;
- workflows de alto risco como billing, migracao destrutiva, deploy e refactor estrutural devem continuar com invocacao manual e controles adicionais.

Cinco skills que fazem mais sentido primeiro:

1. `feature-delivery`
   - le analise, cria ou atualiza execution plan, prepara `docs/active/<feature>/STATUS.md` e `VERIFY.md`, e so depois orienta implementacao.
2. `laravel-module-change`
   - localiza modulo, valida fronteiras, Requests, Actions, Services, Policies, Resources, jobs e testes.
3. `react-module-change`
   - valida modulo, rota, query/cache, componentes compartilhados, acessibilidade e typecheck.
4. `contract-impact-check`
   - revisa efeito de mudancas em endpoint, payload, schema, evento, fila e consumidores frontend-backend.
5. `verify-and-close`
   - decide quais comandos rodar, registra evidencias em `VERIFY.md` e fecha a tarefa com criterio objetivo.

### 10. Hooks minimos e de alto valor

Entrar depois da base, dos agentes e das primeiras skills.

Validacao oficial:

- hooks executam comandos shell em pontos deterministas do ciclo de vida;
- sao apropriados para formatter, lint, bloqueio de comandos perigosos, auditoria e validacoes obrigatorias;
- continuam em Preview no VS Code e devem ser introduzidos com pouco escopo.

Regra:

- usar hooks para enforcement, nao para substituir modelagem de processo;
- comecar com `PostToolUse` para formatar arquivos alterados e `PreToolUse` para bloquear comandos perigosos;
- evitar hook amplo demais logo no inicio;
- aplicar primeiro apenas em agentes ou workflows de alto valor.

### 11. Ergonomia operacional do time

O repo tambem precisa de um padrao de uso diario.

Regra recomendada:

- tarefa simples:
  - reasoning `low` ou `medium`;
  - prompt curto com `Goal`, `Context`, `Constraints` e `Done when`;
- tarefa longa ou ambigua:
  - `/plan` primeiro;
  - depois handoff para `implementer`;
- tarefa pesada:
  - agent mode ou cloud/background, nao chat solto;
- sempre:
  - abrir arquivos-chave no editor;
  - anexar contexto com `@file` no Codex ou `#file` / `#selection` / `#codebase` no VS Code quando isso reduzir ambiguidade.

### 12. Contrato de review e verificacao

Estrutura recomendada:

```text
code_review.md
docs/active/<feature>/VERIFY.md
```

Regra:

- `code_review.md` deve ser referenciado pelo `AGENTS.md`;
- review nao pode depender de memoria manual;
- `VERIFY.md` deve listar comandos, escopo validado e criterio de aceite objetivo.

### 13. Ambiente local e CI com contrato unico

Padrao recomendado:

- manter Node `22 LTS` como versao oficial atual;
- registrar isso em `.nvmrc` ou equivalente;
- manter README, CI e dev local alinhados na mesma versao;
- manter smoke tests por app + checks de typecheck/lint.

---

## Ordem Recomendada de Melhoria

### P0 - Ajustes imediatos restantes

Prioridade alta, baixo risco:

1. Tirar artefatos temporarios da raiz ou isolar em pasta scratch ignorada.
2. Revalidar a suite completa de `apps/api` em CI para ter uma prova remota repetivel alem da prova local.

### P1 - Produtividade

Estado atual:

1. os prompt files principais ja existem;
2. `planner`, `implementer` e `reviewer` ja existem;
3. `code_review.md` ja foi adotado;
4. `VERIFY.md` e `STATUS.md` ja tem template e caso real em `docs/active/`;
5. `docs/execution-plans/` ja foi separado de `docs/architecture/`;
6. a suite completa de `apps/api` ja foi validada localmente em janela maior;
7. o gap restante aqui e operar essas camadas de forma previsivel, nao mais cria-las.

### P2 - Operacao e extensao

1. Padronizar um template unico para execution plan.
2. Criar apenas um conjunto inicial de `5` skills estreitas.
3. Adotar uma rotina fixa de diagnostico das customizacoes carregadas.
4. Introduzir dois hooks minimos e bem controlados.
5. Introduzir MCP apenas onde o contexto estiver fora do repo ou mudar com frequencia.
6. Avaliar subagents apenas quando a tarefa justificar paralelismo real.
7. Expandir skills so depois de evidencias de repeticao e ganho real.

---

## Quick Wins Aplicados Nesta Rodada

Foram aplicados quick wins de baixo risco e a base do P0:

1. `eventovivo.code-workspace`
   - workspace multi-root para API, web, landing, packages, docs e scripts.

2. `.vscode/settings.json`
   - ativa `chat.useCustomizationsInParentRepositories`;
   - melhora legibilidade com `workbench.editor.labelFormat = medium`;
   - reduz ruido visual e de busca de artefatos temporarios locais.

3. `AGENTS.md`
   - slimado para virar contrato curto de repo.

4. `.github/copilot-instructions.md`
   - baseline sempre-on do workspace no VS Code.

5. `.github/instructions/`
   - regras por stack para backend, frontend, docs e testes.

6. `.codex/config.toml`
   - defaults seguros de projeto para modelo, aprovacao e sandbox.

7. `apps/api/AGENTS.override.md`, `apps/web/AGENTS.override.md`, `apps/landing/AGENTS.override.md` e `docs/AGENTS.override.md`
   - especializacao local por area, mais proxima do codigo.

8. `.nvmrc` + `README.md`
   - contrato oficial de Node fechado em `22 LTS`.

9. `docs/execution-plans/`
   - planos executaveis separados do bucket historico de `docs/architecture`.

10. `apps/api/README.md`
   - README padrao do Laravel substituido por README operacional do backend.

11. testes de `apps/landing` e do caso quebrado de `apps/api`
   - suites quebradas alinhadas ao comportamento real atual.

12. `.github/prompts/` e `.github/agents/`
   - slash commands e papeis especializados agora existem no repo.

13. `code_review.md`
   - contrato de review separado do prompt solto e referenciado por `AGENTS.md`.

14. `.github/workflows/api-suite.yml`
   - workflow dedicado para a suite completa da API, cobrindo o gap entre validacao local parcial e revalidacao em CI.

15. `docs/active/_template/` e `docs/active/vibecoding-agent-friendly-repo/`
   - institucionalizacao do padrao `STATUS.md`, `DECISIONS.md` e `VERIFY.md` com um caso real em uso.

16. `docs/execution-plans/_template/EXECUTION-PLAN.md`
   - template unico e canonico para novos planos executaveis.

17. `.agents/skills/`, `apps/api/.agents/skills/`, `apps/web/.agents/skills/` e `docs/.agents/skills/`
   - primeira leva de skills estreitas e portateis, sem scripts ou assets prematuros.

18. `docs/runbooks/codex-customizations-diagnostics-runbook.md`
   - rotina canonica para verificar precedencia, skills descobertas, configuracao do Codex e paridade local da API.

Observacao importante:

- estes ajustes ja melhoram descoberta, precedencia e previsibilidade do agente;
- o baseline e o primeiro trilho de produtividade ja existem;
- a proxima rodada correta passa a ser endurecimento de uso: diagnostico de customizacoes, validacao recorrente em CI com log acessivel e iteracao das skills a partir de uso real.

---

## Referencias Oficiais Consultadas

### OpenAI / Codex

- Codex best practices:
  - https://developers.openai.com/codex/learn/best-practices
- Codex AGENTS guide:
  - https://developers.openai.com/codex/guides/agents-md
- Codex configuration reference:
  - https://developers.openai.com/codex/config-reference
- Codex CLI slash commands:
  - https://developers.openai.com/codex/cli/slash-commands
- Codex pricing FAQ sobre contexto e AGENTS:
  - https://developers.openai.com/codex/pricing
- Ponto validado nestas docs:
  - `AGENTS.md` deve ser curto, pratico e pode ser aninhado com `AGENTS.override.md`;
  - review, testes e checks devem fazer parte explicita do contrato do agente;
  - `code_review.md` referenciado pelo `AGENTS.md` e um padrao valido;
  - skills entram quando o workflow ja deixou de ser apenas prompt reutilizavel;
  - cada skill deve ficar focada em um trabalho, com descricao clara de quando usar;
  - scripts e assets dentro da skill so valem quando melhoram confiabilidade;
  - shared team skills em repositorio sao um padrao valido em `.agents/skills`;
  - prompt curto com `Goal`, `Context`, `Constraints` e `Done when` e o formato recomendado de partida;
  - `/plan` deve entrar cedo quando a tarefa for longa, ambigua ou multi-step;
  - `@file` e mencoes de arquivo ajudam a ancorar contexto;
  - `.codex/config.toml` e o lugar correto para defaults de modelo, aprovacao, sandbox, profiles e MCP.

### VS Code

- Customization overview:
  - https://code.visualstudio.com/docs/copilot/customization/overview
- Custom instructions:
  - https://code.visualstudio.com/docs/copilot/customization/custom-instructions
- Prompt files:
  - https://code.visualstudio.com/docs/copilot/customization/prompt-files
- Custom agents:
  - https://code.visualstudio.com/docs/copilot/customization/custom-agents
- Agent skills:
  - https://code.visualstudio.com/docs/copilot/customization/agent-skills
- Hooks:
  - https://code.visualstudio.com/docs/copilot/customization/hooks
- Multi-root workspaces:
  - https://code.visualstudio.com/docs/editing/workspaces/multi-root-workspaces
- Customize AI for your project:
  - https://code.visualstudio.com/docs/copilot/guides/customize-copilot-guide
- VS Code 1.112 update notes:
  - https://code.visualstudio.com/updates/
- Ponto validado nestas docs:
  - o baseline recomendado hoje e `/.github/copilot-instructions.md`;
  - `.instructions.md` devem ser usadas para regras por linguagem, framework e pasta;
  - prompt files devem ser usados cedo para tarefas repetidas;
  - custom agents devem modelar papeis especializados com ferramentas e handoffs claros;
  - skills sao para workflows mais complexos e especializados, com scripts e recursos adicionais;
  - VS Code suporta project skills em `.github/skills/`, `.claude/skills/` e `.agents/skills/`;
  - propriedades como `user-invocable`, `disable-model-invocation`, `tools` e `agents` pertencem a custom agents, nao a skills;
  - `chat.useCustomizationsInParentRepositories` coleta customizacoes do workspace ate a raiz Git;
  - hooks sao apropriados para enforcement deterministico, mas seguem em Preview e pedem adocao gradual;
  - o VS Code aplica customizacoes ao subir da pasta aberta ate a raiz Git;
  - quando parent discovery esta ligado, isso vale para `copilot-instructions`, `AGENTS`, instructions, prompt files, custom agents, skills e hooks;
  - a visibilidade das customizacoes carregadas deve ser feita pelo editor e diagnostics de customizacao.

---

## Proxima Acao Recomendada

Se a proxima rodada for de execucao, eu faria nesta ordem:

1. validar o routing das novas skills em uso real e ajustar descricao quando houver ativacao errada ou ausente;
2. usar o runbook de diagnostico sempre que houver rodada grande de customizacao ou divergencia entre local e CI;
3. autenticar o `gh` local ou fornecer `GH_TOKEN` para fechar o gap de visibilidade do run remoto da API;
4. so depois experimentar `hooks` minimos e bem controlados em workflows de alto valor;
5. manter MCP como passo posterior, apenas quando houver contexto realmente externo ao repo.
