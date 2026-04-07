# Auditoria de Copy do Painel Web

## Objetivo

Mapear os textos atuais do painel em `apps/web` e validar os principais pontos de melhoria de UX writing para deixar a interface:

- mais coerente com a linguagem do produto;
- menos técnica;
- mais clara para operação em evento;
- totalmente em português simples.

## Resumo Executivo

O painel já tem uma base visual consistente, mas a copy ainda sofre com três problemas centrais:

1. A plataforma mistura nomes internos de produto com nomes de operação.
   - `Wall`, `Play` e `Hub` ainda aparecem em navegação, cards, tabs, listas e ações.
   - Para o usuário do painel, os termos mais claros são `Telão`, `Jogos` e `Links`.

2. O front expõe linguagem técnica demais.
   - Exemplos atuais: `Assets`, `runtime`, `player`, `heartbeat`, `fallback`, `slug`, `preset`, `layout`, `analytics`, `billing`.
   - Isso aparece com mais força nos módulos de Telão, Jogos e Links.

3. Em vários pontos a interface explica implementação em vez de explicar intenção.
   - Exemplo atual: "Preview da ordem provável usando o draft atual das configurações sobre a fila real do evento."
   - Melhor direção: explicar o que o operador consegue entender ou fazer.

## Diretriz de Vocabulário

### Termos de produto

| Atual | Recomendado |
|---|---|
| Wall | Telão |
| Play | Jogos |
| Hub | Links |
| Live / Live Gallery | Galeria ao vivo |
| Analytics | Relatórios ou Métricas |
| Billing | Cobrança |

### Termos técnicos

| Atual | Recomendado |
|---|---|
| Assets | Fotos, mídias ou itens carregados |
| Runtime | Execução, carregamento ou motor do jogo |
| Player | Tela do telão, aparelho ou exibidor |
| Heartbeat | Último sinal |
| Sync | Sincronização |
| Fallback | Reserva automática, usar fotos do evento automaticamente |
| Draft | Ajustes atuais, alterações não salvas |
| Preset | Modelo pronto |
| Layout | Modelo visual ou estilo de exibição |
| Headline / Subheadline | Título / Subtítulo |
| Slug | Endereço do link, identificador público ou final do link |

## Mapa de Prioridade

### Prioridade 1: navegação e nomenclatura global

Esses textos contaminam o painel inteiro porque alimentam navegação, breadcrumbs e rótulos compartilhados.

- `apps/web/src/app/layouts/AppSidebar.tsx`
  - Navegação lateral ainda usa `Wall`, `Play`, `Hub` e `Analytics`.
- `apps/web/src/shared/components/Breadcrumbs.tsx`
  - Breadcrumbs ainda usam `Wall`, `Play`, `Hub`, `Planos & Billing` e `Analytics`.
- `apps/web/src/shared/auth/modules.ts`
  - O catálogo central de módulos ainda usa `Wall`, `Play`, `Hub`, `Analytics` e `Planos e billing`.
- `apps/web/src/modules/events/types.ts`
  - `EVENT_MODULE_LABELS` ainda centraliza `Live`, `Wall`, `Play` e `Hub`.

Sem corrigir esses pontos primeiro, o painel continua inconsistente mesmo que telas individuais sejam revisadas.

### Prioridade 2: telas de evento

As telas de evento concentram a maior parte do uso diário e repetem a nomenclatura antiga.

- `apps/web/src/modules/events/EventDetailPage.tsx`
  - Cards de módulo usam `Live`, `Wall`, `Play`, `Hub`.
  - Bloco de links usa `Código do wall`, `Gerenciar wall` e `Abrir player`.
  - Tab de Links ainda conta ações visíveis com `Wall` e `Play`.
  - Alguns status aparecem crus, como `draft`.
- `apps/web/src/modules/events/components/EventEditorPage.tsx`
  - Seleção de módulos usa `Live Gallery`, `Wall`, `Play` e `Hub`.
  - Descrições ainda refletem linguagem interna do produto.
- `apps/web/src/modules/events/components/PublicLinkCard.tsx`
  - Ainda mostra `Slug público`, `Slug de envio` e `Código do wall`.

### Prioridade 3: módulo Telão

É o módulo com pior carga técnica de copy. Mistura linguagem operacional com termos de engenharia.

- `apps/web/src/modules/wall/pages/WallHubPage.tsx`
  - Título `Wall / Telão` mostra a duplicidade logo na entrada.
  - Mantém `Wall code`, `Configurar wall`, `Abrir player`.
  - Usa `slideshow` e status internos em alguns textos.
- `apps/web/src/modules/wall/pages/EventWallManagerPage.tsx`
  - Problemas críticos:
    - `Saúde do wall`
    - `Assets do runtime`
    - `Último heartbeat`
    - `Comandos operacionais do player`
    - `Revalidar assets`
    - `Reinicializar player`
    - `draft`, `selector`, `preset`, `fairness`, `replay`
  - A seção de simulação ainda descreve o mecanismo interno em vez de explicar o resultado esperado.

### Prioridade 4: módulo Jogos

O módulo já está melhor que Telão, mas ainda depende demais de nomes internos e termos técnicos.

- `apps/web/src/modules/play/pages/PlayHubPage.tsx`
  - Página ainda se apresenta como `Play`.
  - Usa `Hub público pronto`, `Gerenciar Play`, `slug`, `módulos`, e exibe `event.status` cru.
- `apps/web/src/modules/play/pages/EventPlayManagerPage.tsx`
  - Mantém `Play - {evento}`, `Hub público do Play`, `Assets vinculados`, `slug`, `runtime de assets`, `Fallback automático de assets`, `Analytics do Play`.
- `apps/web/src/modules/play/components/EventPlayGameCard.tsx`
  - Usa `Assets`, `Slug público`, `fallback`, `Sincronizar assets`.
  - Expõe termos técnicos nas regras e no gerenciamento de fotos.

### Prioridade 5: módulo Links

A tela já está relativamente mais próxima da linguagem de negócio, mas ainda usa o nome antigo do produto e alguns termos técnicos demais.

- `apps/web/src/modules/hub/HubPage.tsx`
  - Título ainda é `Hub do Evento`.
  - Abertura ainda fala em `Abrir Hub`.
  - Expõe `Slug`, `layout`, `tema`, `modelo`, `preset_key`, `insights`.
  - O texto "editor do agregador" está funcional, mas pode ser simplificado para `editor da página de links`.
- `apps/web/src/modules/hub/PublicHubPage.tsx`
  - Ainda chama a página pública de `hub`.

### Prioridade 6: termos em inglês fora dos módulos principais

- `apps/web/src/modules/dashboard/DashboardPage.tsx`
  - Ação rápida ainda usa `Analytics`.
- `apps/web/src/modules/analytics/AnalyticsPage.tsx`
  - Título principal ainda usa `Analytics`.
- `apps/web/src/modules/plans/PlansPage.tsx`
  - Página ainda usa `Planos e Billing`, `Atualizar billing`, `Abrir checkout`.
- `apps/web/src/modules/audit/AuditPage.tsx`
  - Categoria ainda usa `Billing`.

## Exemplos de Reescrita Recomendados

### Global

| Atual | Recomendado |
|---|---|
| Wall | Telão |
| Play | Jogos |
| Hub | Links |
| Analytics | Relatórios |
| Billing | Cobrança |

### Evento

| Atual | Recomendado |
|---|---|
| Código do wall | Código do telão |
| Gerenciar wall | Configurar telão |
| Abrir player | Abrir telão |
| Hub público | Página pública de links |
| Slug público | Endereço público |
| Slug de envio | Endereço de envio |

### Telão

| Atual | Recomendado |
|---|---|
| Saúde do wall | Saúde do telão |
| Assets do runtime | Mídias carregadas |
| Último heartbeat | Último sinal |
| Players online | Telas conectadas |
| Comandos operacionais do player | Comandos da tela do telão |
| Revalidar assets | Atualizar mídias |
| Reinicializar player | Reiniciar tela |
| Preview da ordem provável usando o draft atual das configurações sobre a fila real do evento | Simulação da próxima ordem de exibição com as configurações atuais do telão |
| Ajuste o draft do wall para gerar a simulação com a fila atual do evento | Ajuste as configurações do telão para simular a próxima ordem de exibição |

### Jogos

| Atual | Recomendado |
|---|---|
| Play | Jogos |
| Hub público do Play | Página pública dos jogos |
| Assets vinculados | Fotos vinculadas |
| Slug | Endereço do jogo |
| Fallback automático de assets | Usar fotos do evento automaticamente |
| Runtime de assets | Carregamento das fotos do jogo |
| Analytics do Play | Métricas dos jogos |

### Links

| Atual | Recomendado |
|---|---|
| Hub do Evento | Links do evento |
| Abrir Hub | Abrir página de links |
| Hub ativo | Links ativos |
| Editor do agregador | Editor da página de links |
| Headline / Subheadline | Título / Subtítulo |
| Preset / Modelo / preset_key | Modelo pronto / código interno do modelo |

## Problemas Específicos de UX Writing

### 1. Interface mistura nome do módulo com nome da função

Exemplo:

- `Wall / Telão`
- `Hub do Evento`
- `Hub público do Play`

Isso gera dúvida sobre o que é marca de produto e o que é ação prática.

### 2. Interface expõe detalhes internos da arquitetura

Exemplo:

- `runtime`
- `heartbeat`
- `fallback`
- `draft`
- `selector`
- `preset`

Esses termos até fazem sentido para time técnico, mas não para operador, parceiro ou cliente.

### 3. Alguns textos explicam demais o backend

Exemplo:

- "O backend continua gerando sessão pública, runtime de assets e ranking por jogo."

Para UX do painel, o foco deve ser:

- o que foi ativado;
- o que está pronto;
- o que ainda falta;
- qual ação o usuário pode tomar agora.

## Ordem Recomendada de Correção

1. Padronizar dicionários centrais e navegação.
   - Sidebar, breadcrumbs, catálogo de módulos e labels compartilhados.

2. Corrigir telas de evento.
   - Lista, detalhe, editor e cards de links.

3. Corrigir o módulo Telão.
   - Primeiro a visão de lista.
   - Depois o gerenciador detalhado.

4. Corrigir o módulo Jogos.
   - Lista de eventos.
   - Gerenciador do evento.
   - Cards de jogo.

5. Corrigir o módulo Links.
   - Renomear `Hub` para `Links` na experiência administrativa.
   - Manter a rota técnica se necessário, mas não o nome na interface.

6. Fechar com termos restantes.
   - `Analytics`, `Billing`, `Live Gallery`, `slug`, `preset`, `layout`.

## Conclusão

O principal problema hoje não é falta de texto, e sim falta de padronização de linguagem.

O painel precisa trocar:

- linguagem de produto interno por linguagem de operação;
- termos técnicos por termos orientados a tarefa;
- frases de arquitetura por frases de ação e entendimento rápido.

Se a próxima etapa for implementação, a melhor abordagem é começar por um dicionário central de labels e uma revisão das telas em ordem de prioridade:

1. navegação global;
2. eventos;
3. telão;
4. jogos;
5. links;
6. analytics e cobrança.
