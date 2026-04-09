# Event media flow builder analysis - 2026-04-09

## Objetivo

Este documento consolida:

- a leitura real da stack atual do Evento Vivo para uma tela de edicao visual da jornada da midia;
- o que ja existe hoje no frontend e no backend e pode ser reaproveitado;
- o que ainda nao existe e precisa ser criado para um builder guiado;
- como desenhar uma pagina simples, visual e legivel para noiva, cerimonial e operador;
- por que o produto deve nascer como builder guiado e nao como canvas livre;
- qual e a melhor arquitetura tecnica para uma V1 em estilo fluxo;
- o que cabe na V1, o que deve ficar para fases seguintes e o que ainda nao existe no produto.

O foco aqui e o estado atual do codigo, nao uma arquitetura imaginaria.

---

## Veredito executivo

O Evento Vivo ja tem base suficiente para entregar uma pagina de "Jornada da midia" forte sem precisar criar uma engine nova de automacao.

Hoje o repositorio ja oferece:

- edicao de canais de entrada por evento;
- edicao de moderacao por evento;
- edicao de MediaIntelligence e respostas automaticas por evento;
- pipeline de midia bem definido do intake ate publish;
- padrao de painel lateral com `Sheet` e `Drawer`;
- componentes para layouts administrativos densos com `shadcn/ui`, `Tailwind` e `TanStack Query`;
- backend modular com ownership claro em `Events`, `WhatsApp`, `Telegram`, `InboundMedia`, `MediaProcessing`, `ContentModeration`, `MediaIntelligence`, `Gallery` e `Wall`.

O principal gap nao e "desenhar caixinhas".

O principal gap e:

1. nao existe hoje uma projecao unica da jornada do evento;
2. nao existe uma pagina unica que agregue essa configuracao;
3. nao existe um payload/view model pensado para leitura visual;
4. o produto ainda nao tem um DSL proprio de fluxo, nem deveria ter isso na V1.

Decisao recomendada:

- criar uma pagina nova de jornada visual do evento;
- usar um backbone vertical fixo com quatro faixas semanticas:
  - Entrada
  - Processamento
  - Decisao
  - Saida
- usar linguagem de operacao, nao linguagem de engine;
- usar `React Flow` apenas como renderer visual travado, nao como fonte da regra de negocio;
- manter a fonte de verdade nas configuracoes reais ja existentes por evento;
- introduzir um endpoint agregador/orquestrador no modulo `Events`, sem criar ainda um novo modulo backend so para isso.

Conclusao pratica:

- sim, da para fazer uma tela no estilo n8n/React Flow;
- nao, ela nao deve nascer como canvas livre;
- a melhor V1 e um builder guiado com trilho fixo, inspector lateral, simulacao e resumo humano automatico.

---

## Leitura real da stack atual

## Frontend auditado hoje

O frontend em `apps/web` esta hoje em:

- React `18.3.1`
- TypeScript `5.8.3`
- Vite `5.4.19`
- TailwindCSS `3.4.17`
- Framer Motion `12.38.0`
- TanStack Query `5.83.0`
- React Router DOM `6.30.1`
- shadcn/ui sobre Radix
- `react-resizable-panels`
- `vaul` para drawer
- Vitest e Playwright

Leitura pratica:

- o stack atual e muito bom para tela administrativa rica;
- ja existe padrao de `Sheet`, `Drawer`, `Tabs`, `Card`, `Badge`, `Switch`, `Select`, `Form`;
- ja existe `react-resizable-panels` para layout principal + inspector;
- nao existe hoje dependencia de `React Flow`;
- nao existe hoje `Zustand`;
- nao existe hoje `Dagre` ou `ELK`.

Implicacao:

- da para construir a V1 sem mudar o modelo da aplicacao inteira;
- se o time quiser estilo n8n, faz sentido adicionar `@xyflow/react`;
- para a V1 nao ha necessidade tecnica de adicionar `Zustand` nem engine de auto-layout.

## Backend auditado hoje

O backend em `apps/api` esta hoje em:

- PHP `>=8.3 <8.4`
- Laravel Framework `13.x`
- Laravel Reverb `1.9`
- Laravel Horizon `5.45`
- Laravel Sanctum `4.3`
- Spatie Data `4.20`
- Spatie Permission `7.2`

Observacao importante:

- o `AGENTS.md` fala em Laravel `12`, mas o `composer.json` atual do repositorio ja esta em Laravel `13.x`.

Leitura pratica:

- o backend ja esta modularizado e suficientemente maduro para expor um payload agregador da jornada;
- a execucao real da pipeline ja esta espalhada em modulos corretos;
- o ponto novo que falta e uma camada de orquestracao para leitura e escrita centralizada da experiencia visual.

---

## O que ja existe hoje e pode ser reaproveitado

## 1. O evento ja e o aggregate root certo para a experiencia

Arquivos centrais:

- `apps/api/app/Modules/Events/Http/Controllers/EventController.php`
- `apps/api/app/Modules/Events/Support/EventIntakeChannelsStateBuilder.php`
- `apps/api/app/Modules/Events/Actions/SyncEventIntakeChannelsAction.php`
- `apps/web/src/modules/events/components/EventEditorPage.tsx`
- `apps/web/src/modules/events/intake.ts`

Hoje o modulo `Events` ja agrega:

- `channels`
- `defaultWhatsAppInstance`
- `whatsappGroupBindings`
- `contentModerationSettings`
- `mediaIntelligenceSettings`
- `wallSettings`
- `playSettings`
- `hubSettings`

Leitura pratica:

- a nova tela deve ficar ancorada no dominio `Events`;
- nao faz sentido espalhar a pagina nova em `wall`, `moderation` ou `whatsapp`;
- o evento e o objeto que o usuario entende e o lugar onde a configuracao ja converge.

## 2. Os canais de entrada ja existem como configuracao real

Estado atual por evento:

- `whatsapp_groups`
- `whatsapp_direct`
- `public_upload`
- `telegram`

Isso ja esta consolidado em:

- `SyncEventIntakeChannelsAction`
- `EventIntakeChannelsStateBuilder`
- `apps/web/src/modules/events/intake.ts`

O que ja e editavel hoje:

- habilitar/desabilitar canal;
- escolher instancia WhatsApp;
- definir `media_inbox_code`;
- definir `session_ttl_minutes`;
- configurar grupos WhatsApp;
- ligar Telegram privado;
- ativar link de upload.

Leitura pratica:

- o bloco `Entrada` do builder ja pode nascer em cima do estado real do backend;
- nao precisa existir um formato novo de configuracao so para canais.

## 3. A pipeline da midia ja esta bem definida

Documentos e codigo centrais:

- `docs/flows/media-ingestion.md`
- `apps/api/app/Modules/MediaProcessing/README.md`
- `apps/api/app/Modules/ContentModeration/README.md`
- `apps/api/app/Modules/MediaIntelligence/README.md`
- `apps/api/app/Modules/MediaProcessing/Services/MediaEffectiveStateResolver.php`

Pipeline atual:

1. intake/webhook
2. normalizacao
3. download
4. variantes
5. safety
6. VLM
7. decisao final
8. publicacao
9. wall/gallery

Statuses ja existentes:

- `processing_status`
- `moderation_status`
- `publication_status`
- `safety_status`
- `vlm_status`
- `face_index_status`

Leitura pratica:

- o builder pode mostrar um fluxo fiel ao que o sistema realmente executa;
- o passo de "decisao" nao precisa ser inventado, ele ja existe como matriz efetiva;
- a simulacao de caminho pode usar exatamente esses estados e regras.

## 4. Safety e VLM ja sao configuraveis por evento

Arquivos centrais:

- `apps/web/src/modules/events/components/content-moderation/EventContentModerationSettingsForm.tsx`
- `apps/web/src/modules/events/components/media-intelligence/EventMediaIntelligenceSettingsForm.tsx`
- `apps/api/app/Modules/ContentModeration/Http/Resources/EventContentModerationSettingResource.php`
- `apps/api/app/Modules/MediaIntelligence/Http/Resources/EventMediaIntelligenceSettingResource.php`

Hoje ja existem configuracoes reais para:

### Safety

- `enabled`
- `provider_key`
- `mode = enforced | observe_only`
- `threshold_version`
- `fallback_mode = review | block`
- thresholds por categoria
- `analysis_scope`

### MediaIntelligence

- `enabled`
- `provider_key`
- `model_key`
- `mode = enrich_only | gate`
- `prompt_version`
- `approval_prompt`
- `caption_style_prompt`
- `fallback_mode = review | skip`
- `reply_text_mode = disabled | ai | fixed_random`
- `reply_prompt_override`
- `reply_fixed_templates`
- `reply_prompt_preset_id`

Leitura pratica:

- o bloco `Processamento` e parte do bloco `Saida` ja podem ser editados por um inspector guiado;
- o builder nao precisa inventar "resposta com IA" do zero;
- isso ja existe no backend, inclusive com dispatcher real apos publicacao.

## 5. Ja existe feedback automatico por canal

Arquivos centrais:

- `apps/api/app/Modules/WhatsApp/Services/WhatsAppFeedbackAutomationService.php`
- `apps/api/app/Modules/Telegram/Services/TelegramFeedbackAutomationService.php`
- `apps/api/app/Modules/MediaIntelligence/Services/PublishedMediaAiReplyDispatcher.php`

Hoje ja existem respostas/feedbacks reais como:

### WhatsApp

- reacao de `detected`
- reacao de `published`
- reacao de `rejected`
- reply textual em `published` quando `reply_text_mode` gerar texto
- reply textual em `rejected` via policy/entitlement

### Telegram

- `session_activated`
- `session_closed`
- `detected` com `sendChatAction` + reacao
- `published` com reacao + reply opcional
- `rejected`
- `blocked`

Leitura pratica:

- o builder consegue representar saidas humanas reais;
- mas precisa deixar claro que algumas mensagens ainda sao globais/padronizadas, nao overrides livres por evento.

## 6. Ja existe linguagem de inspector lateral no frontend

Padroes prontos:

- `Sheet` e `Drawer` em `apps/web/src/components/ui/sheet.tsx` e `drawer.tsx`
- `react-resizable-panels` em `apps/web/src/components/ui/resizable.tsx`
- inspector do wall em `apps/web/src/modules/wall/pages/EventWallManagerPage.tsx`
- details sheets do wall
- painel lateral e drawer na moderacao

Leitura pratica:

- a V1 nao precisa inventar um layout administrativo novo;
- o padrao certo e canvas central + inspector lateral no desktop + drawer no mobile.

---

## O que ainda nao existe

## 1. Nao existe uma pagina unica de jornada da midia

Hoje o usuario precisa navegar entre:

- editor do evento;
- configuracao de canais;
- card de Safety;
- card de MediaIntelligence;
- moderacao;
- wall;

Problema:

- a plataforma ja sabe executar a pipeline;
- mas o usuario final nao consegue "ler" o processo como uma jornada unica.

## 2. Nao existe uma projecao visual consolidada

Hoje o frontend recebe varias fatias:

- `intake_channels`
- `moderation_mode`
- settings de Safety
- settings de MediaIntelligence
- modulos ativos

Mas nao existe um payload tipo:

- "qual e a jornada visivel do evento?"
- "quais nos aparecem?"
- "quais condicoes estao ligadas?"
- "quais nos sao editaveis?"
- "quais nos sao so leitura?"

Esse e o principal gap estrutural.

## 3. Nao existe dependencia de canvas/flow

Hoje nao ha no `package.json`:

- `@xyflow/react`
- `zustand`
- `dagre`
- `elkjs`

Isso nao impede a feature.

Apenas significa que:

- o canvas precisara entrar conscientemente;
- nao vale adicionar metade do ecossistema logo na primeira entrega.

## 4. Nao existe um DSL de fluxo, e isso e bom para a V1

O sistema ja possui a fonte de verdade em campos reais como:

- `intake_channels.*`
- `moderation_mode`
- `content_moderation.*`
- `media_intelligence.*`
- `modules.wall`
- `modules.live`

Leitura pratica:

- criar um DSL novo de automacao agora duplicaria regra;
- a V1 deve projetar e editar o estado real existente;
- um DSL proprio so deve nascer quando o produto passar a suportar excecoes que os campos atuais nao cobrem.

## 5. Algumas saidas desejadas ainda nao existem como capability real

Exemplos que hoje nao sao first-class no produto:

- impressao/rolo;
- randomizacao de publish por fluxo;
- loop condicional;
- branch livre por caption ausente;
- branch livre por foto vs video com comportamento configuravel por evento;
- publicar no telao mas nao na galeria como politica independente por caminho;
- resposta inicial personalizada por evento com total liberdade.

Leitura pratica:

- esses itens podem aparecer como roadmap;
- nao devem contaminar a V1 com placeholders que parecem implementados.

---

## Mapa real de capacidades atuais para o builder

| Bloco | Capacidade | Estado atual | Builder V1 |
| --- | --- | --- | --- |
| Entrada | WhatsApp privado por codigo | Implementado | Editavel |
| Entrada | WhatsApp grupos | Implementado | Editavel |
| Entrada | Telegram privado | Implementado parcial, mas operacional | Editavel |
| Entrada | Link/upload publico | Implementado | Editavel |
| Entrada | Blacklist por remetente | Implementado | Editavel fora do fluxo principal, em policy drawer |
| Processamento | Reacao inicial de processamento | Existe por canal | Visivel; parcialmente editavel |
| Processamento | Download da midia | Implementado | No de leitura |
| Processamento | Geracao de variantes | Implementado | No de leitura |
| Processamento | Safety por IA | Implementado | Editavel |
| Processamento | VLM / contexto / caption / tags | Implementado | Editavel |
| Processamento | Resposta automatica por IA ou templates | Implementado apos publish | Editavel |
| Decisao | Modo do evento `none/manual/ai` | Implementado | Editavel |
| Decisao | Safety `enforced/observe_only` | Implementado | Editavel |
| Decisao | VLM `enrich_only/gate` | Implementado | Editavel |
| Decisao | Revisao manual | Implementado via moderacao e fallback | Editavel |
| Decisao | Foto vs video | Detectado pelo pipeline | Visivel em simulacao; politica granular ainda nao first-class |
| Decisao | Caption existe / nao existe | Dado existe | Visivel em simulacao; branch configuravel ainda nao first-class |
| Saida | Reacao de aprovado/publicado | Implementado | Visivel; parcialmente editavel |
| Saida | Reply textual de rejeicao | Parcial, mais global/entitlement | Visivel; editavel so se policy evoluir |
| Saida | Reply por IA/fixo-random | Implementado apos publish | Editavel |
| Saida | Publicar na galeria | Implementado | No de leitura / ligado ao pipeline |
| Saida | Disponibilizar no telao | Implementado via `Wall` consumindo `published` | Visivel; dependente de modulo wall, nao branch livre |
| Saida | Impressao/rolo | Nao implementado | Fora da V1 |
| Saida | Silencio total | Parcial, via desabilitar reply | Parcial |

---

## Recomendacao de produto

## 1. O nome certo nao e "automacao"

Para o usuario final, o modelo mental deve ser:

- "Jornada da midia"
- "Como o evento trata cada foto ou video recebido"

Nao:

- "workflow engine"
- "automacao"
- "regras"

Razao:

- noiva e cerimonial querem configurar uma operacao;
- elas nao querem sentir que estao programando um sistema.

## 2. O produto deve nascer como builder guiado

O n8n funciona com liberdade porque o usuario ja espera:

- pensar em IF/SWITCH;
- lidar com branching tecnico;
- assumir o custo cognitivo de automacao.

No Evento Vivo, esse custo e um bug de produto.

Portanto a V1 deve seguir estes principios:

- backbone fixo;
- poucos pontos de decisao;
- labels em linguagem humana;
- no maximo 8 a 12 nos principais;
- branches laterais so quando a funcionalidade estiver ativa;
- nada de criar/remover no arbitrariamente;
- nada de desenhar arestas manualmente;
- nada de coordenadas salvas por usuario.

## 3. A pagina deve ter quatro faixas fixas

A melhor leitura para o caso atual e:

1. Entrada
2. Processamento
3. Decisao
4. Saida

Essas faixas devem ser desenhadas como:

- bandas horizontais empilhadas verticalmente;
- cada banda com titulo, cor e explicacao curta;
- o fluxo principal sempre descendo do topo para baixo;
- variacoes aparecendo dos lados.

Exemplo de backbone:

```text
Receber midia
  ->
Preparar e analisar
  ->
Decidir se publica ou revisa
  ->
Responder e distribuir
```

## 4. A copia dos nos precisa falar lingua operacional

Evitar labels tecnicos como:

- `EvaluateMediaPromptJob`
- `AnalyzeContentSafetyJob`
- `SyncEventIntakeChannelsAction`

Preferir:

- Receber foto no WhatsApp privado
- Receber foto no grupo do WhatsApp
- Confirmar que a midia entrou
- Baixar e preparar a imagem
- Analisar seguranca com IA
- Entender contexto e legenda
- Enviar para revisao manual
- Aprovar automaticamente
- Responder ao convidado
- Publicar na galeria
- Disponibilizar no telao

## 5. O node nao deve abrir formulario inteiro de cara

Cada no deve mostrar apenas:

- icone;
- titulo curto;
- resumo de uma linha;
- badge de estado:
  - ativo
  - desativado
  - obrigatorio
  - opcional
  - automatico
  - leitura tecnica

Ao clicar:

- abre inspector lateral com edicao;
- se a capacidade for so leitura, abre explicacao e status real;
- se a capacidade depender de outro modulo, mostrar lock ou dependencia.

## 6. O inspector lateral e obrigatorio

Recomendacao:

- desktop: painel lateral direito;
- mobile: `Drawer` inferior;
- nunca usar modal central como experiencia principal.

Razao:

- o canvas precisa continuar visivel;
- o usuario precisa manter o contexto do fluxo enquanto edita;
- o repositorio ja tem esse padrao pronto.

## 7. O topo da tela deve gerar uma explicacao humana viva

Exemplo de frase:

> Quando uma foto chega pelo WhatsApp privado, o sistema confirma o recebimento, analisa seguranca, pode enviar para revisao manual e, quando aprovada, responde ao convidado e disponibiliza a midia no evento.

Isso ajuda em:

- onboarding;
- suporte;
- confianca do cliente;
- auditoria operacional.

---

## Recomendacao de UX para a V1

## Estrutura da tela

### Cabecalho

- titulo: `Jornada da midia`
- subtitulo: `Como o evento trata cada foto ou video recebido`
- chips com canais ativos:
  - WhatsApp privado
  - WhatsApp grupos
  - Telegram
  - Link de envio
- resumo humano automatico
- seletor de template inicial

### Corpo principal

- canvas vertical com quatro faixas fixas;
- backbone central;
- ramos laterais para condicoes ativas;
- edge labels curtas:
  - Sim
  - Nao
  - Revisao
  - Publicado
  - Bloqueado
  - Grupo
  - Privado

### Lado direito

- inspector do no selecionado;
- resumo do que esta ativo;
- campos editaveis;
- preview textual do impacto;
- CTA de salvar.

### Barra secundaria

- simulador de cenarios prontos;
- botao `Ver detalhes tecnicos`;
- botao `Comparar com template`;
- indicador de inconsistencias.

## Templates iniciais recomendados

- Aprovacao direta
- Revisao manual
- IA moderando
- Hibrido IA + humano
- Evento social simples
- Evento corporativo controlado

Esses templates devem aplicar patches sobre o estado atual do evento, nao coordenadas no canvas.

## Simulador de caminho

O simulador e uma das partes mais valiosas da V1.

Cenarios recomendados:

- Foto com legenda via WhatsApp privado
- Foto sem legenda via grupo
- Video via Telegram
- Midia bloqueada por safety
- Midia que vai para review manual
- Midia aprovada e publicada

Resultado esperado:

- o canvas destaca o caminho percorrido;
- o inspector explica por que cada decisao ocorreu;
- o resumo humano muda de acordo com o cenario.

---

## Recomendacao tecnica

## 1. React Flow sim, canvas livre nao

Recomendacao tecnica principal:

- usar `React Flow` para renderizacao visual;
- travar as interacoes para a V1.

O que habilitar:

- render de nodes e edges customizados;
- zoom e pan leves;
- highlight de caminho no simulador;
- mini feedback visual de branches;
- no selection controlado pela aplicacao.

O que bloquear:

- drag livre de nodes;
- create edge manual;
- delete node;
- reconectar handles;
- persistencia de coordenadas do usuario.

Leitura pratica:

- o usuario recebe a familiaridade visual de um flow builder;
- o produto continua sendo guiado e seguro.

## 2. Nao usar auto-layout engine na V1

Como a V1 tera:

- quatro faixas fixas;
- backbone principal conhecido;
- poucas variacoes laterais;

a melhor abordagem inicial e:

- layout deterministico por mapa de posicoes;
- sem `Dagre`;
- sem `ELK`;
- sem custo extra de layout engine.

Exemplo conceitual:

- `Entrada` em `y = 0`
- `Processamento` em `y = 320`
- `Decisao` em `y = 640`
- `Saida` em `y = 960`
- backbone central em `x = 480`
- variacoes laterais em `x = 180` e `x = 780`

Quando o produto pedir:

- subflows colapsaveis;
- dezenas de branches;
- agrupamentos dinamicos;

ai sim `ELK` passa a fazer sentido.

## 3. Nao introduzir Zustand na primeira entrega

Hoje o repo nao usa `Zustand`.

Para a V1, o estado pode ficar em:

- `TanStack Query` para leitura/escrita do backend;
- `react-hook-form` no inspector;
- reducer local para selecao, simulacao e estado visual.

Adicionar `Zustand` so vale a pena quando houver:

- multiplos paines sincronizados;
- undo/redo real;
- subflows complexos;
- composicao de canvas maior.

## 4. A fonte de verdade da V1 deve continuar sendo o estado real do evento

Na V1, a fonte de verdade deve permanecer em campos reais ja existentes:

- `intake_channels`
- `intake_defaults`
- `moderation_mode`
- `content_moderation`
- `media_intelligence`
- `modules`

O canvas nao deve salvar:

- nodes;
- edges;
- posicoes;
- regras arbitarias.

O canvas deve ser apenas:

- representacao;
- resumo;
- guia de edicao;
- simulador.

## 5. Criar uma projecao agregada da jornada

Mesmo sem DSL novo, a experiencia precisa de um payload agregador.

Recomendacao:

- novo endpoint no modulo `Events`;
- retorno em formato de `JourneyProjection`;
- escrita tambem centralizada por um `JourneyUpdate` orquestrador.

### Rotas sugeridas

- `GET /api/v1/events/{event}/journey-builder`
- `PATCH /api/v1/events/{event}/journey-builder`

### Ownership recomendado

Manter em `Events`, porque:

- o usuario edita um evento;
- o `Events` ja agrega dependencias;
- a pagina nao executa pipeline por si;
- a feature ainda e uma camada de orquestracao, nao um dominio autonomo.

Se no futuro surgir:

- DSL propria;
- simulador server-side robusto;
- versoes de fluxo;
- templates persistidos;

ai faz sentido extrair um modulo `EventJourney`.

## 6. Orquestracao backend recomendada

### Leitura

O backend agregador pode reaproveitar:

- `EventIntakeChannelsStateBuilder`
- `EventIntakeBlacklistStateBuilder`
- `ContentModerationSettingsResolver`
- `EventMediaIntelligenceSettingsController` ou action equivalente
- `MediaEffectiveStateResolver` para legendas de decisao

### Escrita

O backend agregador deve distribuir para as actions corretas:

- `UpdateEventAction` para `moderation_mode`, `modules` e campos base;
- `SyncEventIntakeChannelsAction` para canais;
- `UpsertEventContentModerationSettingsAction` para Safety;
- `UpsertEventMediaIntelligenceSettingsAction` para VLM e replies.

Importante:

- o frontend nao deve chamar cinco endpoints distintos para salvar a jornada inteira;
- a tela nova precisa de uma operacao coesa de salvar.

## 7. Estrutura frontend recomendada

Como a feature e event-centric, o melhor encaixe e dentro de `events`.

Estrutura sugerida:

```text
apps/web/src/modules/events/
+-- pages/
|   `-- EventJourneyBuilderPage.tsx
+-- journey/
|   +-- api.ts
|   +-- types.ts
|   +-- buildJourneyGraph.ts
|   +-- summary.ts
|   +-- simulator.ts
|   +-- components/
|   |   +-- JourneyFlowCanvas.tsx
|   |   +-- JourneyNodeCard.tsx
|   |   +-- JourneyEdge.tsx
|   |   +-- JourneyInspector.tsx
|   |   +-- JourneySummaryBanner.tsx
|   |   +-- JourneyTemplateRail.tsx
|   |   `-- JourneyScenarioSimulator.tsx
|   `-- mappers/
|       +-- fromProjection.ts
|       `-- toUpdatePayload.ts
```

Rotas sugeridas:

- `apps/web/src/App.tsx`: `/events/:id/flow`
- label na UI: `Jornada da midia`

## 8. Estrutura backend recomendada

```text
apps/api/app/Modules/Events/
+-- Actions/
|   +-- BuildEventJourneyProjectionAction.php
|   `-- UpdateEventJourneyAction.php
+-- Data/
|   +-- EventJourneyProjectionData.php
|   +-- EventJourneyStageData.php
|   `-- EventJourneyNodeData.php
+-- Http/
|   +-- Controllers/
|   |   `-- EventJourneyController.php
|   +-- Requests/
|   |   `-- UpdateEventJourneyRequest.php
|   `-- Resources/
|       `-- EventJourneyResource.php
`-- routes/
    `-- api.php
```

---

## Modelo de dados recomendado para a V1

## 1. Projection payload

Exemplo conceitual:

```json
{
  "event": {
    "id": 123,
    "title": "Casamento Ana e Pedro",
    "moderation_mode": "ai",
    "modules": {
      "live": true,
      "wall": true
    }
  },
  "capabilities": {
    "supports_react_flow_visual_mode": true,
    "supports_manual_review": true,
    "supports_ai_reply": true,
    "supports_print": false
  },
  "stages": [
    {
      "id": "entry",
      "label": "Entrada",
      "nodes": [
        {
          "id": "whatsapp_direct",
          "label": "WhatsApp privado",
          "kind": "entry",
          "active": true,
          "editable": true,
          "summary": "Recebe midias por codigo e sessao privada."
        }
      ]
    }
  ],
  "summary": {
    "human_text": "Quando uma foto chega..."
  },
  "simulation_presets": [
    "photo_whatsapp_private_with_caption",
    "photo_whatsapp_group_without_caption",
    "video_telegram",
    "blocked_sender"
  ]
}
```

Importante:

- isso e projecao de leitura;
- nao e DSL de execucao;
- nao duplica tabela de regras.

## 2. Payload de update

Para a V1, o update deve continuar espelhando campos reais.

Exemplo conceitual:

```json
{
  "moderation_mode": "ai",
  "intake_defaults": {
    "whatsapp_instance_id": 10,
    "whatsapp_instance_mode": "shared"
  },
  "intake_channels": {
    "whatsapp_direct": {
      "enabled": true,
      "media_inbox_code": "NOIVA2026",
      "session_ttl_minutes": 180
    },
    "public_upload": {
      "enabled": true
    },
    "telegram": {
      "enabled": false
    }
  },
  "content_moderation": {
    "enabled": true,
    "mode": "enforced",
    "fallback_mode": "review"
  },
  "media_intelligence": {
    "enabled": true,
    "mode": "gate",
    "reply_text_mode": "ai"
  },
  "modules": {
    "live": true,
    "wall": true
  }
}
```

Leitura pratica:

- o builder salva configuracao do produto, nao um desenho;
- o desenho e sempre regenerado a partir desse estado.

---

## Como mapear os nos da V1

## Faixa 1 - Entrada

Nos recomendados:

- WhatsApp privado
- WhatsApp grupos
- Telegram privado
- Link de envio

Politicas auxiliares:

- blacklist de remetente;
- instancia WhatsApp;
- TTL de sessao;
- codigos de inbox.

## Faixa 2 - Processamento

Nos recomendados:

- Confirmar recebimento
- Baixar midia
- Preparar variantes
- Analisar seguranca com IA
- Entender contexto e legenda

Detalhes:

- `Confirmar recebimento` pode resumir o feedback inicial;
- `Baixar midia` e `Preparar variantes` devem ser tecnicos e compactos;
- `Analisar seguranca com IA` e `Entender contexto e legenda` sao os principais nos editaveis dessa faixa.

## Faixa 3 - Decisao

Nos recomendados:

- Modo do evento
- Revisao manual
- Aprovar automaticamente
- Bloquear por risco

Branches recomendados:

- Safety bloqueou
- Safety mandou para review
- VLM bloqueou
- VLM pediu review
- Operador decidiu manualmente

## Faixa 4 - Saida

Nos recomendados:

- Reagir ao convidado
- Responder automaticamente
- Publicar na galeria
- Disponibilizar no telao

Nos futuros, fora da V1:

- Enviar para impressao
- Aplicar randomizacao
- Encadear fluxo secundario

---

## O que eu evitaria na primeira entrega

Eu evitaria:

- canvas totalmente livre;
- suporte a loops;
- persistencia de posicao de nodes;
- nos tecnicos demais visiveis por padrao;
- duplicar todas as forms atuais dentro do canvas;
- criar configuracao paralela a `content_moderation` e `media_intelligence`;
- simular IA real no client;
- prometer saidas que o backend ainda nao suporta.

Tambem evitaria substituir o editor atual de evento de uma vez.

Melhor estrategia:

- lancar a jornada como nova pagina guiada;
- manter o editor atual como fallback operacional/admin;
- migrar trafego aos poucos.

---

## Roadmap recomendado

## Fase 1 - Builder guiado e seguro

- nova pagina `Jornada da midia`;
- React Flow como renderer travado;
- backbone vertical com quatro faixas;
- 8 a 12 nos principais;
- inspector lateral;
- resumo humano automatico;
- simulador de cenarios prontos;
- save centralizado por endpoint agregador;
- sem DSL nova;
- sem auto-layout engine.

## Fase 1.5 - Operacao mais rica

- templates oficiais;
- validacoes visuais de inconsistencia;
- modo `ver detalhes tecnicos`;
- destaque de locks por entitlement;
- resumo de impacto antes de salvar.

## Fase 2 - Visual mode mais profundo

- subflows colapsaveis;
- detalhes por canal;
- comparacao entre templates;
- excecoes por origem ou tipo de midia;
- possivel adocao de `ELK` se o grafo crescer.

## Fase 3 - DSL propria, se o produto merecer

So considerar uma DSL/engine nova quando houver demanda real por:

- regras por caption;
- regras por tipo de midia;
- publish granular por destino;
- excecoes por canal;
- persistencia de templates customizados;
- simulacao server-side com versionamento.

Antes disso, a DSL seria engenharia prematura.

---

## Riscos reais

## R1. O time confundir visual com flexibilidade total

Se a tela parecer "canvas livre", o usuario vai esperar:

- criar passo arbitrario;
- mover tudo;
- fazer branch para qualquer regra.

Isso vai muito alem da capacidade atual do backend.

Mitigacao:

- backbone travado;
- copia guiada;
- mensagens claras de "automatico", "opcional" e "em breve".

## R2. Duplicar configuracao

Se o builder passar a salvar um JSON proprio desde o inicio, sem necessidade real:

- o time vai manter dois modelos;
- bugs de divergencia vao aparecer;
- suporte vai perder confianca no estado do evento.

Mitigacao:

- V1 so projeta e atualiza o estado real existente.

## R3. O canvas ficar bonito, mas cansativo

Esse e o maior risco de produto.

Uma tela visual so vale a pena se:

- reduzir duvida;
- reduzir navegacao entre abas;
- reduzir medo de errar;
- aumentar entendimento do evento.

Se ela virar um builder "legal", mas mais lento que o form atual, falhou.

Mitigacao:

- manter foco em entendimento e edicao guiada;
- usar frase humana, simulacao e templates;
- nao tentar vencer n8n no primeiro dia.

---

## Decisao recomendada

Se a meta e criar uma pagina visual simples, clara e forte para noiva, cerimonial e operador, eu recomendo:

1. criar a experiencia como `Jornada da midia` dentro do modulo `Events`;
2. usar `React Flow` apenas como shell visual travado;
3. organizar o canvas em quatro faixas fixas e fluxo vertical;
4. manter a fonte de verdade nos settings reais do evento;
5. criar um endpoint agregador no backend para leitura/escrita coesa;
6. reaproveitar os formularios e actions ja existentes de canais, Safety e MediaIntelligence;
7. lancar a V1 com simulador, resumo humano e templates;
8. deixar branches avancados, DSL propria e outputs novos para fases posteriores.

---

## Resumo final

O Evento Vivo ja tem o mais dificil:

- pipeline real;
- ownership modular;
- configuracoes por evento;
- UI administrativa madura.

O que falta agora nao e uma engine de automacao.

O que falta e uma camada de produto que traduza a pipeline para uma experiencia humana, visual e guiada.

Essa camada deve nascer como:

- uma projecao visual do estado real;
- um editor guiado com trilhos;
- um canvas legivel, nao programavel;
- um mapa de processo, nao um IDE.

Esse caminho entrega:

- clareza para o cliente final;
- baixo risco arquitetural;
- alto reaproveitamento da stack atual;
- espaco real para crescer depois para um modo visual mais poderoso.
