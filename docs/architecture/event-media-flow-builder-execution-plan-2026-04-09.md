# Event media flow builder execution plan - 2026-04-09

## Objetivo

Este documento transforma a analise da `Jornada da midia` em um plano de execucao implementavel.

Ele cobre:

- decisoes tecnicas tiradas da documentacao oficial do `React Flow`;
- trilha real da midia no Evento Vivo, desde entrada ate destinos finais;
- condicionais reais de moderacao, safety, VLM, respostas e publicacao;
- subtarefas backend/frontend bem definidas;
- criterios de aceite por etapa;
- testes ja validados antes deste plano;
- testes obrigatorios para a implementacao.

Documento base:

- `docs/architecture/event-media-flow-builder-analysis-2026-04-09.md`

---

## Resultado dos testes executados antes do plano

## Frontend

Comando:

```bash
cd apps/web
npm run test -- src/modules/events/intake.test.ts src/modules/events/components/content-moderation/EventContentModerationSettingsForm.test.tsx src/modules/events/components/media-intelligence/EventMediaIntelligenceSettingsForm.test.tsx src/modules/events/components/TelegramOperationalStatusCard.test.tsx src/modules/moderation/feed-utils.test.ts
```

Resultado:

- `5` test files passaram;
- `19` testes passaram;
- cobertura validada:
  - normalizacao de canais de entrada do evento;
  - UI de Safety por evento;
  - UI de MediaIntelligence por evento;
  - labels visiveis de resposta automatica em portugues;
  - Telegram operational status;
  - filtros/estado de moderacao.

## Backend

Comando:

```bash
cd apps/api
php artisan test tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Events/EventIntakeChannelsTelegramPrivateTest.php tests/Feature/ContentModeration/ContentModerationSettingsTest.php tests/Feature/MediaIntelligence/MediaIntelligenceSettingsTest.php tests/Unit/MediaProcessing/MediaEffectiveStateResolverTest.php tests/Unit/MediaProcessing/FinalizeMediaDecisionActionTest.php tests/Unit/ContentModeration/UpsertEventContentModerationSettingsActionTest.php tests/Unit/MediaIntelligence/UpsertEventMediaIntelligenceSettingsActionTest.php
```

Resultado:

- `41` testes passaram;
- `264` assertions passaram;
- cobertura validada:
  - intake channels por evento;
  - Telegram privado por evento;
  - bloqueios por entitlement;
  - ContentModeration settings;
  - MediaIntelligence settings;
  - validacao de `gate` com fallback obrigatorio `review`;
  - action de upsert de Safety;
  - action de upsert de MediaIntelligence;
  - matriz de decisao final da midia;
  - resolver de estado efetivo da midia.

Leitura pratica:

- a base atual que o builder vai editar esta verde;
- o plano deve reaproveitar essas actions e resources;
- nao ha necessidade de criar uma engine paralela de fluxo na V1.

---

## Ultimas duvidas validadas nesta rodada

## Fontes oficiais revalidadas em `2026-04-09`

- `React Flow Performance` confirma que os principais ganhos vem de memoizacao, de evitar re-renders desnecessarios e de nao depender diretamente de `nodes` e `edges` inteiros em componentes derivados.
- `React Flow Hooks and Providers` confirma que `ReactFlowProvider` deve entrar quando hooks internos precisarem ser usados fora do `<ReactFlow />`, quando houver mais de um flow na pagina ou quando a aplicacao estiver integrada a client-side router.
- `React Flow Testing` confirma que `Cypress` ou `Playwright` sao a trilha recomendada para app real porque o renderer precisa medir DOM para desenhar edges; `Jest/Vitest` ficam melhores para regras puras e mocks.
- `React Flow Handles` confirma que `sourceHandle` e `targetHandle` aceitam `id` estavel por handle, o que valida a decisao de modelar branches V1 por IDs semanticos.
- `ReactFlow API Reference` confirma a existencia e semantica das props que a V1 precisa para canvas travado e acessivel:
  - `nodesDraggable`
  - `nodesConnectable`
  - `elementsSelectable`
  - `fitView`
  - `ariaLabelConfig`
  - `onlyRenderVisibleElements`

## Testes executados nesta rodada

## Frontend

Comando:

```bash
cd apps/web
npm run test -- src/modules/events/event-media-flow-builder-architecture-characterization.test.ts src/modules/events/intake.test.ts
```

Resultado:

- `2` test files passaram;
- `7` testes passaram;
- cobertura validada:
  - `@xyflow/react` ainda nao esta instalado no repo;
  - a rota `/events/:id/flow` ainda nao existe;
  - `react-resizable-panels` ja existe para reaproveitar layout canvas + inspector;
  - `EventEditorPage` ja edita agregado real do evento;
  - `HubPage` ja prova um editor rico com `builder_config` por evento.

## Backend

Comando:

```bash
cd apps/api
php artisan test tests/Feature/Events/EventJourneyArchitectureCharacterizationTest.php tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Hub/HubSettingsTest.php
```

Resultado:

- `13` testes passaram;
- `116` assertions passaram;
- cobertura validada:
  - `GET /events/{id}` ja retorna o agregado do evento com `intake_defaults`, `intake_channels` e `hub.builder_config` no mesmo payload;
  - a trilha de intake por evento continua verde;
  - o precedente de `builder_config_json` no Hub continua verde.

## Leitura pratica desta validacao

- `Events` ja e o aggregate root real para a V1 do builder.
- Nao precisamos criar um modulo novo nem um store paralelo de jornada para ler o estado atual.
- O padrao de `builder_config` por evento ja existe no `Hub` e reduz muito o risco de introduzir `journey_builder_config` ou projection equivalente no futuro.
- A principal lacuna real hoje nao e conceitual; e de fundacao de interface:
  - instalar `@xyflow/react`;
  - registrar a rota nova;
  - criar projection visual dedicada;
  - ligar o inspector ao save sem duplicar regra.

## Bateria automatica adicional antes da execucao

### Backend

Comando:

```bash
cd apps/api
php artisan test tests/Feature/Events/EventJourneyArchitectureCharacterizationTest.php tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Events/EventIntakeChannelsTelegramPrivateTest.php tests/Feature/Events/EventIntakeBlacklistTest.php tests/Feature/Telegram/TelegramPrivateMediaIntakePipelineTest.php tests/Feature/Telegram/TelegramFeedbackAutomationTest.php tests/Feature/ContentModeration/ContentModerationSettingsTest.php tests/Feature/ContentModeration/ContentModerationPipelineTest.php tests/Feature/ContentModeration/ContentModerationObserveOnlyTest.php tests/Feature/MediaIntelligence/MediaIntelligenceSettingsTest.php tests/Feature/MediaIntelligence/MediaIntelligencePipelineTest.php tests/Unit/ContentModeration/UpsertEventContentModerationSettingsActionTest.php tests/Unit/MediaIntelligence/UpsertEventMediaIntelligenceSettingsActionTest.php tests/Unit/MediaIntelligence/PublishedMediaReplyTextResolverTest.php tests/Unit/MediaIntelligence/MediaReplyTextPromptResolverTest.php tests/Unit/MediaProcessing/MediaEffectiveStateResolverTest.php tests/Unit/MediaProcessing/FinalizeMediaDecisionActionTest.php
```

Resultado:

- `86` testes passaram;
- `1` falhou;
- `666` assertions passaram.

Bloqueio confirmado:

- `ContentModerationPipelineTest` quebra no cenario de fallback `data_url`;
- `EventMediaResource` ainda assume asset com `url` e `source` sempre presentes;
- isso precisa ser estabilizado antes de reaproveitar a trilha de Safety como baseline de execucao.

### Frontend

Comandos:

```bash
cd apps/web
npm run type-check
npm run test -- src/modules/events/intake.test.ts src/modules/events/components/content-moderation/EventContentModerationSettingsForm.test.tsx src/modules/events/components/media-intelligence/EventMediaIntelligenceSettingsForm.test.tsx src/modules/events/components/TelegramOperationalStatusCard.test.tsx src/modules/moderation/feed-utils.test.ts src/modules/moderation/moderation-architecture.test.ts src/modules/moderation/moderation-event-scope.contract.test.ts src/modules/wall/player/wall-theme-architecture-characterization.test.ts
```

Resultado:

- `type-check` passou;
- intake, forms de Safety, forms de MediaIntelligence, Telegram e caracterizacao do wall passaram;
- a bateria frontend relevante fechou verde no estado final rerodado desta validacao.

Observacao importante:

- `feed-utils.test.ts` falhou no primeiro lote e depois passou no rerun;
- o arquivo `apps/web/src/modules/moderation/feed-utils.ts` apareceu modificado no worktree durante a validacao;
- por isso esse ponto sai da lista de bloqueios confirmados e entra como item obrigatorio de revalidacao de baseline.

Leitura pratica:

- o plano continua valido;
- a execucao deve comecar por uma fase curta de estabilizacao de baseline, nao direto pela tela nova;
- o bloqueio confirmado e unico, hoje, esta no backend de assets.

## Status da execucao em `2026-04-10`

Revalidacao concluida no estado atual do workspace:

- a blindagem de assets ausentes foi aplicada em `EventMediaResource`;
- o cenario `data_url fallback` voltou a passar;
- a bateria backend relevante fechou com `87` testes passando e `670` assertions;
- a trilha frontend de duplicate cluster e a caracterizacao de moderacao passaram juntas;
- `npm run type-check` passou;
- a bateria frontend relevante fechou com `31` testes passando e `1` arquivo com cenarios skipped.

Leitura pratica:

- a Fase `0B` pode ser considerada concluida;
- o proximo passo de implementacao agora e a `Fase 1 - Backend projection`.

---

## Decisoes tiradas da documentacao oficial do React Flow

Fontes oficiais consultadas em `2026-04-09`:

- https://reactflow.dev/
- https://reactflow.dev/api-reference/react-flow
- https://reactflow.dev/learn/customization/custom-nodes
- https://reactflow.dev/learn/customization/handles
- https://reactflow.dev/learn/customization/edge-labels
- https://reactflow.dev/api-reference/components/node-toolbar
- https://reactflow.dev/learn/advanced-use/hooks-providers
- https://reactflow.dev/learn/advanced-use/performance
- https://reactflow.dev/learn/advanced-use/state-management
- https://reactflow.dev/learn/advanced-use/testing
- https://reactflow.dev/examples/grouping/sub-flows
- https://reactflow.dev/whats-new/2025-10-20

## Decisao 1 - usar `@xyflow/react`, nao kit visual pronto

Implementar com:

- `@xyflow/react`;
- custom nodes proprios do Evento Vivo;
- custom edges proprias;
- CSS do pacote base.

Nao usar na V1:

- `React Flow UI` como kit visual pronto;
- templates visuais externos;
- componentes que assumam `React 19 + Tailwind 4`.

Motivo:

- nosso repo esta em `React 18.3.1` e `TailwindCSS 3.4.17`;
- a experiencia precisa seguir o design system atual do painel;
- o kit visual pronto aumentaria o risco de incompatibilidade visual e tecnica.

Validacao do repo nesta rodada:

- `apps/web/package.json` ainda nao tem `@xyflow/react`;
- `apps/web/package.json` ja tem `react-resizable-panels`;
- `apps/web/src/App.tsx` ainda nao expoe `/events/:id/flow`.

## Decisao 2 - canvas travado

Configurar o canvas da V1 como visual e selecionavel, mas nao livre.

Props esperadas:

```tsx
<ReactFlow
  nodes={nodes}
  edges={edges}
  nodeTypes={nodeTypes}
  edgeTypes={edgeTypes}
  nodesDraggable={false}
  nodesConnectable={false}
  elementsSelectable
  nodesFocusable
  edgesFocusable={false}
  deleteKeyCode={null}
  selectNodesOnDrag={false}
  zoomOnDoubleClick={false}
  preventScrolling={false}
  fitView
  fitViewOptions={{ padding: 0.2 }}
  ariaLabelConfig={ptBrFlowAria}
/>
```

O usuario pode:

- selecionar um no;
- abrir inspector;
- simular caminho;
- ver labels e branches;
- usar zoom/pan se necessario.

O usuario nao pode:

- arrastar nos;
- criar conexoes;
- deletar arestas;
- editar handles;
- persistir posicoes.

Regras complementares da V1:

- nao usar `MiniMap`;
- nao usar `onlyRenderVisibleElements` na V1;
- manter toolbar minima:
  - `Centralizar fluxo`
  - `Ver detalhes tecnicos`
- preservar scroll natural da pagina enquanto o canvas for uma area de leitura guiada.

## Decisao 3 - handles estaveis para branch

Cada decisao deve ter handles com IDs estaveis.

Exemplo:

```ts
branches: [
  { id: 'approved', label: 'Aprovado' },
  { id: 'review', label: 'Revisao' },
  { id: 'blocked', label: 'Bloqueado' },
  { id: 'default', label: 'Padrao' },
]
```

O `sourceHandle` da edge deve usar o `branch.id`.

Isso pega a melhor ideia do benchmark externo analisado:

- condicoes ficam legiveis;
- branches sao testaveis;
- fallback `default` fica explicito;
- simulador pode destacar caminho sem inferencia fragil.

## Decisao 4 - `ReactFlowProvider` so se necessario

Usar `ReactFlowProvider` se:

- inspector lateral precisar consultar API interna do flow;
- simulador precisar centralizar highlight do path;
- toolbar externa precisar chamar `fitView` ou selecionar no.

Regras de uso:

- se `useOnSelectionChange` entrar, o callback precisa nascer memoizado;
- se o provider for usado para manter estado entre trocas de rota, ele precisa ficar acima da subarvore que sera remontada;
- se o inspector e o simulador conseguirem viver apenas com props e reducer local, nao adicionar provider por inercia.

Caso contrario:

- manter estado em componentes/reducer local;
- evitar complexidade de store global.

## Decisao 5 - `Zustand` fora da V1

Nao adicionar `Zustand` agora.

Motivo:

- o repo nao usa `Zustand`;
- a V1 nao tera canvas livre;
- a V1 nao tera undo/redo;
- a V1 nao tera edicao de nodes de dentro do proprio canvas.

Reavaliar `Zustand` quando existir:

- subflows editaveis;
- canvas com mutacoes em muitos pontos;
- undo/redo;
- colaboracao;
- templates customizados salvos.

## Decisao 6 - testes de canvas em browser real

A doc oficial do React Flow recomenda testar apps reais com browser porque edges e viewport dependem de medicao DOM.

Portanto:

- regras puras ficam em `Vitest`;
- canvas/interacao ficam em `Playwright`;
- snapshots visuais ficam em `Playwright`, com ambiente padronizado.

---

## Trilha real da midia no Evento Vivo

## Entrada

Meios reais existentes:

- WhatsApp privado;
- grupos de WhatsApp;
- Telegram privado;
- link/upload publico;
- entrada manual/admin indireta pela moderacao/catalogo.

Arquivos relevantes:

- `apps/api/app/Modules/Events/Support/EventIntakeChannelsStateBuilder.php`
- `apps/api/app/Modules/Events/Actions/SyncEventIntakeChannelsAction.php`
- `apps/web/src/modules/events/intake.ts`
- `apps/api/app/Modules/WhatsApp/Services/WhatsAppDirectIntakeSessionService.php`
- `apps/api/app/Modules/WhatsApp/Services/WhatsAppInboundEventContextResolver.php`
- `apps/api/app/Modules/Telegram/Actions/HandleTelegramPrivateWebhookAction.php`
- `apps/api/app/Modules/InboundMedia/Http/Controllers/PublicUploadController.php`

Condicionais reais:

- canal esta ativo ou inativo;
- evento tem entitlement do canal ou nao;
- WhatsApp precisa de instancia quando direto/grupo estiver ativo;
- WhatsApp direto e Telegram privado precisam de `media_inbox_code`;
- Telegram precisa de `bot_username`;
- grupos respeitam limite de pacote;
- blacklist pode bloquear remetente antes de criar `event_media`.

## Processamento

Etapas reais:

1. receber webhook;
2. normalizar mensagem;
3. rotear para evento;
4. avaliar blacklist;
5. baixar midia;
6. criar `event_media`;
7. gerar variantes;
8. rodar Safety;
9. rodar VLM;
10. decidir moderacao;
11. indexar faces se aplicavel;
12. publicar;
13. propagar para galeria/telao.

Arquivos e docs relevantes:

- `docs/flows/media-ingestion.md`
- `apps/api/app/Modules/MediaProcessing/Jobs/DownloadInboundMediaJob.php`
- `apps/api/app/Modules/MediaProcessing/Actions/FinalizeMediaDecisionAction.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaEffectiveStateResolver.php`
- `apps/api/app/Modules/ContentModeration/Jobs/AnalyzeContentSafetyJob.php`
- `apps/api/app/Modules/MediaIntelligence/Jobs/EvaluateMediaPromptJob.php`

Status reais:

- `processing_status`: `received`, `downloaded`, `processed`, `failed`;
- `moderation_status`: `pending`, `approved`, `rejected`;
- `publication_status`: `draft`, `published`, `hidden`, `deleted`;
- `safety_status`: `queued`, `skipped`, `pass`, `review`, `block`, `failed`;
- `vlm_status`: `queued`, `completed`, `review`, `rejected`, `skipped`, `failed`;
- `face_index_status`: `queued`, `processing`, `indexed`, `skipped`, `failed`.

## Decisao

Modos reais de moderacao do evento:

- `none`;
- `manual`;
- `ai`.

Regras validadas por teste:

- `none` aprova quando o pipeline base chega na decisao;
- `manual` mantem `pending` ate revisao humana;
- `ai` usa Safety e, opcionalmente, VLM;
- Safety `enforced` pode bloquear ou mandar para review;
- Safety `observe_only` nao deve bloquear efetivamente;
- VLM `enrich_only` nao bloqueia publish;
- VLM `gate` participa da decisao quando `enabled=true`;
- `gate` exige fallback `review`, nao `skip`;
- falha tecnica nunca deve virar aprovacao automatica;
- video hoje nao passa pela mesma regra bloqueante de Safety/VLM de imagem no resolver atual.

Arquivos relevantes:

- `apps/api/app/Modules/Events/Models/Event.php`
- `apps/api/app/Modules/MediaProcessing/Actions/FinalizeMediaDecisionAction.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaEffectiveStateResolver.php`
- `apps/api/app/Modules/ContentModeration/Models/EventContentModerationSetting.php`
- `apps/api/app/Modules/MediaIntelligence/Models/EventMediaIntelligenceSetting.php`

## Respostas automaticas

Respostas reais existentes:

- reacao inicial de detectado/processando em canais suportados;
- reacao de publicado;
- reacao/reply de rejeitado quando politica/entitlement permitir;
- resposta por IA apos publish;
- resposta por template fixo aleatorio apos publish.

Arquivos relevantes:

- `apps/api/app/Modules/WhatsApp/Services/WhatsAppFeedbackAutomationService.php`
- `apps/api/app/Modules/Telegram/Services/TelegramFeedbackAutomationService.php`
- `apps/api/app/Modules/MediaIntelligence/Services/PublishedMediaAiReplyDispatcher.php`
- `apps/api/app/Modules/MediaIntelligence/Models/EventMediaIntelligenceSetting.php`
- `apps/web/src/modules/events/components/media-intelligence/EventMediaIntelligenceSettingsForm.tsx`

Condicionais reais:

- `reply_text_mode = disabled`;
- `reply_text_mode = ai`;
- `reply_text_mode = fixed_random`;
- reply por IA pode usar preset ou override;
- reply fixo usa lista de templates;
- resposta final depende de canal de origem e da existencia de mensagem original.

## Destinos

Destinos reais hoje:

- moderacao manual;
- galeria/public catalog apos `approved + published`;
- wall/telao por eventos de publish;
- FaceSearch como indexacao non-blocking;
- historico operacional e analytics.

Fora da V1:

- impressao;
- rolo fisico;
- branching livre por destino;
- publicar no wall mas nao na galeria como caminho visual independente;
- randomizacao de publicacao por branch.

---

## Perguntas de produto/arquitetura ja respondidas

## A midia chega direto no telao?

Nao.

Ela entra por um canal, vira `event_media`, passa por processamento/moderacao e so depois fica disponivel para galeria/telao quando publicada.

## O fluxo visual deve executar a regra?

Nao na V1.

A regra continua nas actions e services existentes. O flow builder e uma projection visual com edicao guiada.

## Podemos salvar `nodes` e `edges` como fonte da verdade?

Nao na V1.

Isso duplicaria regra e criaria uma engine paralela desnecessaria.

## O que vira branch visual real?

Branches V1:

- canal ativo/inativo;
- remetente bloqueado;
- midia detectada;
- Safety aprovado/review/bloqueado;
- VLM enriquece/gate/review;
- revisao manual;
- aprovado;
- recusado;
- publicado;
- resposta automatica ativa/inativa.

Nao entram como branch configuravel V1:

- tipo foto vs video com regra custom;
- caption existe/nao existe com regra custom;
- destino exclusivo wall/galeria;
- impressao;
- loops.

## A noiva ou cerimonial deve poder criar passos?

Nao.

Ela deve ligar/desligar capacidades e editar configuracoes guiadas.

## A equipe interna pode ter modo avancado?

Sim, mas nao na primeira entrega.

O modo avancado deve ser fase 2 ou 3, depois da V1 validada.

---

## Arquitetura alvo da V1

## Backend

Modulo dono:

- `Events`

Novas rotas:

```php
GET   /api/v1/events/{event}/journey-builder
PATCH /api/v1/events/{event}/journey-builder
```

Novas classes:

```text
apps/api/app/Modules/Events/
|-- Actions/
|   |-- BuildEventJourneyProjectionAction.php
|   |-- BuildEventJourneySummaryAction.php
|   `-- UpdateEventJourneyAction.php
|-- Data/
|   |-- EventJourneyBranchData.php
|   |-- EventJourneyCapabilityData.php
|   |-- EventJourneyNodeData.php
|   |-- EventJourneyProjectionData.php
|   |-- EventJourneyScenarioData.php
|   `-- EventJourneyStageData.php
|-- Http/
|   |-- Controllers/
|   |   `-- EventJourneyController.php
|   |-- Requests/
|   |   `-- UpdateEventJourneyRequest.php
|   `-- Resources/
|       `-- EventJourneyResource.php
```

Actions reutilizadas:

- `UpdateEventAction`;
- `SyncEventIntakeChannelsAction`;
- `SyncEventIntakeBlacklistAction`;
- `UpsertEventContentModerationSettingsAction`;
- `UpsertEventMediaIntelligenceSettingsAction`.

Servicos de leitura reutilizados:

- `EventIntakeChannelsStateBuilder`;
- `EventIntakeBlacklistStateBuilder`;
- `ContentModerationSettingsResolver`;
- `ContextualModerationPolicyResolver`;
- `MediaEffectiveStateResolver` como referencia de linguagem de decisao.

## Frontend

Modulo dono:

- `apps/web/src/modules/events`

Novas pastas:

```text
apps/web/src/modules/events/
|-- pages/
|   `-- EventJourneyBuilderPage.tsx
`-- journey/
    |-- api.ts
    |-- types.ts
    |-- buildJourneyGraph.ts
    |-- buildJourneySummary.ts
    |-- buildJourneyScenarios.ts
    |-- journey-layout.ts
    |-- journey-node-types.tsx
    |-- journey-edge-types.tsx
    |-- toJourneyUpdatePayload.ts
    |-- components/
    |   |-- JourneyFlowCanvas.tsx
    |   |-- JourneyNodeCard.tsx
    |   |-- JourneyDecisionNode.tsx
    |   |-- JourneyEdgeLabel.tsx
    |   |-- JourneyInspector.tsx
    |   |-- JourneyScenarioSimulator.tsx
    |   |-- JourneySummaryBanner.tsx
    |   |-- JourneyTemplateRail.tsx
    |   `-- JourneyValidationPanel.tsx
    `-- __tests__/
```

Dependencia nova:

```bash
cd apps/web
npm install @xyflow/react
```

Import base CSS:

```ts
import '@xyflow/react/dist/style.css';
```

Rota frontend sugerida:

```text
/events/:id/flow
```

Label:

```text
Jornada da midia
```

---

## Projection payload V1

Exemplo de shape:

```json
{
  "event": {
    "id": 123,
    "title": "Casamento Ana e Pedro",
    "moderation_mode": "ai",
    "modules": {
      "live": true,
      "wall": true,
      "hub": true,
      "play": false
    }
  },
  "capabilities": {
    "can_edit_intake": true,
    "can_edit_safety": true,
    "can_edit_media_intelligence": true,
    "can_use_wall": true,
    "can_use_print": false,
    "can_use_advanced_flow": false
  },
  "stages": [
    {
      "id": "entry",
      "label": "Entrada",
      "description": "De onde as fotos e videos chegam.",
      "nodes": []
    }
  ],
  "summary": {
    "human_text": "Quando uma foto chega pelo WhatsApp privado..."
  },
  "scenarios": [],
  "validation": {
    "blocking": [],
    "warnings": []
  }
}
```

Observacao:

- `nodes` e `edges` nao devem virar regra canonica;
- o backend retorna estado semantico;
- o frontend monta `nodes/edges` no adapter `buildJourneyGraph`.

---

## Modelo de nodes V1

## Entrada

Nodes:

- `entry_whatsapp_direct`;
- `entry_whatsapp_groups`;
- `entry_telegram_private`;
- `entry_public_upload`;
- `entry_blacklist`.

Editaveis:

- WhatsApp direto;
- WhatsApp grupos;
- Telegram;
- link de upload;
- blacklist por drawer separado.

Leitura:

- entitlement;
- status de instancia;
- contagem de grupos;
- bot username.

## Processamento

Nodes:

- `process_receive`;
- `process_download`;
- `process_variants`;
- `process_safety`;
- `process_vlm`.

Editaveis:

- Safety;
- VLM/MediaIntelligence.

Leitura:

- download;
- variantes;
- filas tecnicas;
- face index como detalhe tecnico, nao node principal da V1.

## Decisao

Nodes:

- `decision_moderation_mode`;
- `decision_safety`;
- `decision_vlm_gate`;
- `decision_manual_review`;
- `decision_effective_state`.

Branches:

- `none_mode`;
- `manual_review`;
- `ai_safety_pass`;
- `ai_safety_review`;
- `ai_safety_block`;
- `ai_vlm_approved`;
- `ai_vlm_review`;
- `ai_vlm_rejected`;
- `default`.

## Saida

Nodes:

- `output_feedback_detected`;
- `output_feedback_published`;
- `output_feedback_rejected`;
- `output_ai_reply`;
- `output_gallery`;
- `output_wall`.

Fora da V1:

- `output_print`;
- `output_randomization`;
- `output_external_webhook`.

---

## Plano de execucao detalhado

## Fase 0 - Preparacao e contrato

### Tarefa 0.1 - Congelar escopo da V1

Subtarefas:

- confirmar que V1 nao tera canvas livre;
- confirmar que V1 nao salva DSL;
- confirmar que V1 nao cria saidas de impressao;
- confirmar que V1 usa `Events` como modulo dono;
- confirmar que `React Flow UI` nao sera usado.

Criterios de aceite:

- plano aprovado;
- escopo V1 documentado;
- itens fora de escopo listados no roadmap.

Testes:

- sem teste automatizado.

### Tarefa 0.2 - Adicionar dependencia visual

Subtarefas:

- instalar `@xyflow/react`;
- confirmar lockfile alterado;
- importar CSS base em escopo controlado;
- criar smoke component local se necessario.

Criterios de aceite:

- build do frontend reconhece `@xyflow/react`;
- nenhum warning de peer dependency bloqueante;
- nenhum componente do kit `React Flow UI` importado.

Testes:

```bash
cd apps/web
npm run type-check
npm run test -- src/modules/events/intake.test.ts
```

---

## Fase 0B - Estabilizacao da baseline

Status:

- revalidada com sucesso em `2026-04-10` no estado atual do workspace.

### Tarefa 0.3 - Blindar assets ausentes em `EventMediaResource`

Subtarefas:

- revisar a resolucao de `thumbnail`, `preview`, `moderation_thumbnail` e `moderation_preview`;
- tolerar retorno nulo ou shape parcial vindo de `MediaAssetUrlService`;
- definir fallback consistente para `url` e `source` ausentes;
- garantir que o cenario `data_url` continue funcionando sem quebrar resource/broadcast.

Criterios de aceite:

- `ContentModerationPipelineTest` volta a passar;
- resources de midia nao explodem quando o asset publico nao esta disponivel;
- a trilha de Safety continua serializando payload valido em cenarios degradados.

Testes:

```bash
cd apps/api
php artisan test tests/Feature/ContentModeration/ContentModerationPipelineTest.php
```

### Tarefa 0.4 - Revalidar a trilha de duplicate cluster no frontend

Subtarefas:

- preservar a mudanca local ja presente em `apps/web/src/modules/moderation/feed-utils.ts`;
- rerodar `feed-utils.test.ts` e `moderation-architecture.test.ts`;
- verificar que o painel de revisao continua lendo apenas o cluster correto;
- tratar qualquer regressao nova como bloqueio antes da feature.

Criterios de aceite:

- bateria de duplicate cluster permanece verde;
- painel de revisao de duplicadas nao mistura grupos;
- nenhuma alteracao paralela do worktree e sobrescrita sem validacao.

Testes:

```bash
cd apps/web
npm run test -- src/modules/moderation/feed-utils.test.ts src/modules/moderation/moderation-architecture.test.ts
```

### Tarefa 0.5 - Revalidar a bateria base

Subtarefas:

- rerodar a bateria backend ampliada;
- rerodar `type-check`;
- rerodar a bateria frontend ampliada usada nesta validacao;
- registrar os resultados na doc se algo mudar.

Criterios de aceite:

- baseline relevante para a jornada volta a ficar verde;
- qualquer novo bloqueio aparece documentado antes da Fase 1.

Testes:

```bash
cd apps/api
php artisan test tests/Feature/Events/EventJourneyArchitectureCharacterizationTest.php tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Events/EventIntakeChannelsTelegramPrivateTest.php tests/Feature/Events/EventIntakeBlacklistTest.php tests/Feature/Telegram/TelegramPrivateMediaIntakePipelineTest.php tests/Feature/Telegram/TelegramFeedbackAutomationTest.php tests/Feature/ContentModeration/ContentModerationSettingsTest.php tests/Feature/ContentModeration/ContentModerationPipelineTest.php tests/Feature/ContentModeration/ContentModerationObserveOnlyTest.php tests/Feature/MediaIntelligence/MediaIntelligenceSettingsTest.php tests/Feature/MediaIntelligence/MediaIntelligencePipelineTest.php tests/Unit/ContentModeration/UpsertEventContentModerationSettingsActionTest.php tests/Unit/MediaIntelligence/UpsertEventMediaIntelligenceSettingsActionTest.php tests/Unit/MediaIntelligence/PublishedMediaReplyTextResolverTest.php tests/Unit/MediaIntelligence/MediaReplyTextPromptResolverTest.php tests/Unit/MediaProcessing/MediaEffectiveStateResolverTest.php tests/Unit/MediaProcessing/FinalizeMediaDecisionActionTest.php

cd apps/web
npm run type-check
npm run test -- src/modules/events/intake.test.ts src/modules/events/components/content-moderation/EventContentModerationSettingsForm.test.tsx src/modules/events/components/media-intelligence/EventMediaIntelligenceSettingsForm.test.tsx src/modules/events/components/TelegramOperationalStatusCard.test.tsx src/modules/moderation/feed-utils.test.ts src/modules/moderation/moderation-architecture.test.ts src/modules/moderation/moderation-event-scope.contract.test.ts src/modules/wall/player/wall-theme-architecture-characterization.test.ts
```

---

## Fase 1 - Backend projection

### Tarefa 1.1 - Criar Data objects da jornada

Subtarefas:

- criar `EventJourneyProjectionData`;
- criar `EventJourneyStageData`;
- criar `EventJourneyNodeData`;
- criar `EventJourneyBranchData`;
- criar `EventJourneyCapabilityData`;
- criar `EventJourneyScenarioData`;
- padronizar `id`, `kind`, `label`, `description`, `active`, `editable`, `status`, `summary`, `config_preview`, `branches`.

Criterios de aceite:

- data objects representam todos os nodes V1;
- todos os IDs sao estaveis e documentados;
- nenhum campo tecnico sensivel vaza para a UI.

Testes:

```bash
cd apps/api
php artisan test --filter=EventJourneyProjectionDataTest
```

Status em `2026-04-10`:

- [x] `EventJourneyProjectionData` criado;
- [x] `EventJourneyStageData` criado;
- [x] `EventJourneyNodeData` criado;
- [x] `EventJourneyBranchData` criado;
- [x] `EventJourneyCapabilityData` criado;
- [x] `EventJourneyScenarioData` criado;
- [x] contrato serializavel validado por teste unitario;
- [x] campos operacionais padronizados como projection read-only, sem DSL de execucao.

### Tarefa 1.2 - Criar `BuildEventJourneyProjectionAction`

Subtarefas:

- carregar evento com `modules`, `channels`, `defaultWhatsAppInstance`, `whatsappGroupBindings`, `mediaSenderBlacklists`, `contentModerationSettings`, `mediaIntelligenceSettings`, `wallSettings`;
- resolver `intake_defaults` e `intake_channels`;
- resolver entitlements;
- resolver Safety efetivo;
- resolver MediaIntelligence efetivo;
- montar capacidades;
- montar stages;
- montar nodes;
- montar branches;
- montar warnings;
- definir budget de query para o endpoint desde a primeira entrega.

Criterios de aceite:

- projection mostra os quatro blocos: Entrada, Processamento, Decisao, Saida;
- projection reflete canais ativos/inativos;
- projection reflete `moderation_mode`;
- projection reflete Safety `enabled`, `mode`, `fallback_mode`;
- projection reflete VLM `enabled`, `mode`, `reply_text_mode`;
- projection marca `print` como indisponivel na V1;
- projection respeita budget de query acordado para evitar `N+1`.

Testes:

```bash
cd apps/api
php artisan test --filter=BuildEventJourneyProjectionActionTest
```

Casos obrigatorios:

- [x] evento manual sem IA;
- [x] evento `none`;
- [x] evento `ai` com Safety desligado;
- [x] evento `ai` com Safety enforced;
- [x] evento `ai` com Safety observe_only;
- [x] evento `ai` com VLM enrich_only;
- [x] evento `ai` com VLM gate;
- [x] evento com WhatsApp direto;
- [x] evento com grupos;
- [x] evento com Telegram;
- [x] evento com upload publico;
- [x] evento sem entitlement de canal;
- [x] budget de query protegido com `expectsDatabaseQueryCount`.

Status em `2026-04-10`:

- [x] `BuildEventJourneyProjectionAction` implementada no modulo `Events`;
- [x] a action carrega `modules`, `channels`, `defaultWhatsAppInstance`, `whatsappGroupBindings`, `mediaSenderBlacklists`, `contentModerationSettings`, `mediaIntelligenceSettings` e `wallSettings`;
- [x] a action reaproveita `EventIntakeChannelsStateBuilder`, `ContentModerationSettingsResolver` e `ContextualModerationPolicyResolver`;
- [x] a projection retorna as faixas `Entrada`, `Processamento`, `Decisao` e `Saida`;
- [x] a projection retorna nodes estaveis para canais, Safety, VLM, decisoes, galeria, wall, print e silencio/arquivo;
- [x] `print` fica `unavailable` na V1;
- [x] canais ativos sem entitlement aparecem como `locked` e geram `warnings`;
- [x] o teste de budget da action ficou em `8` queries para o cenario completo com settings persistidos;
- [x] o endpoint ainda nao existe; a protecao de budget do endpoint deve ser repetida na `Tarefa 1.4`.

Bateria validada em `2026-04-10`:

```bash
cd apps/api
php artisan test tests/Unit/Events/EventJourneyProjectionDataTest.php tests/Unit/Events/BuildEventJourneyProjectionActionTest.php
php artisan test tests/Unit/Events/EventJourneyProjectionDataTest.php tests/Unit/Events/BuildEventJourneyProjectionActionTest.php tests/Feature/Events/EventJourneyArchitectureCharacterizationTest.php tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Events/EventIntakeChannelsTelegramPrivateTest.php tests/Feature/ContentModeration/ContentModerationSettingsTest.php tests/Feature/MediaIntelligence/MediaIntelligenceSettingsTest.php
```

Resultado:

- `14` testes novos passaram;
- `83` assertions novas passaram;
- bateria de regressao direcionada passou com `36` testes;
- bateria de regressao direcionada passou com `297` assertions.

### Tarefa 1.3 - Criar resumo humano automatico

Subtarefas:

- criar `BuildEventJourneySummaryAction`;
- gerar frase curta baseada em canais ativos, modo de moderacao, Safety, VLM, resposta automatica e destinos ativos;
- evitar linguagem tecnica;
- garantir fallback quando tudo esta desligado.

Criterios de aceite:

- resumo e legivel por noiva/cerimonial;
- resumo muda quando canais/moderacao/resposta mudam;
- resumo nao promete impressao ou destino inexistente.

Testes:

```bash
cd apps/api
php artisan test --filter=BuildEventJourneySummaryActionTest
```

Status em `2026-04-10`:

- [x] `BuildEventJourneySummaryAction` criada;
- [x] o resumo foi extraido da projection para action dedicada;
- [x] `BuildEventJourneyProjectionAction` agora consome a action dedicada em vez de manter string inline;
- [x] o resumo considera canais ativos, modo de moderacao, Safety, VLM, resposta automatica e destinos visiveis;
- [x] o resumo evita linguagem tecnica pesada no texto final;
- [x] o resumo faz fallback quando nao ha canais ativos;
- [x] o resumo ignora `print` mesmo se esse destino aparecer tecnicamente no contexto;
- [x] a projection continua com o mesmo contrato `summary.human_text`.

Bateria validada em `2026-04-10`:

```bash
cd apps/api
php artisan test tests/Unit/Events/BuildEventJourneySummaryActionTest.php tests/Unit/Events/BuildEventJourneyProjectionActionTest.php
php artisan test tests/Unit/Events/EventJourneyProjectionDataTest.php tests/Unit/Events/BuildEventJourneySummaryActionTest.php tests/Unit/Events/BuildEventJourneyProjectionActionTest.php tests/Feature/Events/EventJourneyArchitectureCharacterizationTest.php tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Events/EventIntakeChannelsTelegramPrivateTest.php tests/Feature/ContentModeration/ContentModerationSettingsTest.php tests/Feature/MediaIntelligence/MediaIntelligenceSettingsTest.php
```

Resultado:

- bateria unitária da extração passou com `18` testes e `76` assertions;
- bateria de regressão direcionada passou com `41` testes e `304` assertions.

### Tarefa 1.4 - Criar controller e rotas

Subtarefas:

- criar `EventJourneyController`;
- adicionar `show`;
- adicionar `update`;
- registrar rotas em `apps/api/app/Modules/Events/routes/api.php`;
- usar `auth:sanctum`;
- usar `events.view` para leitura;
- usar `events.update` para update.

Criterios de aceite:

- `GET /api/v1/events/{event}/journey-builder` retorna projection;
- `PATCH /api/v1/events/{event}/journey-builder` atualiza configuracoes permitidas;
- respostas seguem envelope padrao do `BaseController`;
- acesso cross-organization retorna `403`.

Testes:

```bash
cd apps/api
php artisan test --filter=EventJourneyControllerTest
```

Casos obrigatorios:

- `GET` autorizado;
- `GET` sem permissao;
- `PATCH` autorizado;
- `PATCH` sem permissao;
- `PATCH` invalido retorna `422`;
- payload final preserva shape.

Status em `2026-04-10`:

- [x] `EventJourneyController` criado;
- [x] `EventJourneyResource` criado;
- [x] rota `GET /api/v1/events/{event}/journey-builder` registrada no modulo `Events`;
- [x] rota `PATCH /api/v1/events/{event}/journey-builder` registrada no modulo `Events`;
- [x] leitura usa `authorize('view', $event)` e envelope padrao do `BaseController`;
- [x] escrita usa `UpdateEventJourneyRequest`, `authorize('update', $event)` e envelope padrao do `BaseController`;
- [x] o endpoint retorna a projection read-only completa com `meta.request_id`;
- [x] `GET` autorizado validado;
- [x] `GET` cross-organization validado com `403`;
- [x] `PATCH` autorizado validado;
- [x] `PATCH` invalido validado com `422`;
- [x] `PATCH` sem permissao validado com `403`;
- [x] o payload final do `PATCH` preserva o mesmo shape da projection de leitura;
- [x] budget do endpoint protegido com `expectsDatabaseQueryCount(14)` no cenario read-only completo;
- [x] o `PATCH /events/{event}/journey-builder` ficou fechado depois da `Tarefa 1.6`.

Bateria validada em `2026-04-10`:

```bash
cd apps/api
php artisan test tests/Feature/Events/EventJourneyControllerTest.php tests/Unit/Events/EventJourneyProjectionDataTest.php tests/Unit/Events/BuildEventJourneySummaryActionTest.php tests/Unit/Events/BuildEventJourneyProjectionActionTest.php
```

Resultado:

- `22` testes passaram;
- `135` assertions passaram.

Regressao ampliada em `2026-04-10`:

```bash
cd apps/api
php artisan test tests/Feature/Events/EventJourneyControllerTest.php tests/Unit/Events/EventJourneyProjectionDataTest.php tests/Unit/Events/BuildEventJourneySummaryActionTest.php tests/Unit/Events/BuildEventJourneyProjectionActionTest.php tests/Feature/Events/EventJourneyArchitectureCharacterizationTest.php tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Events/EventIntakeChannelsTelegramPrivateTest.php tests/Feature/ContentModeration/ContentModerationSettingsTest.php tests/Feature/MediaIntelligence/MediaIntelligenceSettingsTest.php
```

Resultado:

- `44` testes passaram;
- `349` assertions passaram.

Revalidacao do `PATCH` em `2026-04-10`:

```bash
cd apps/api
php artisan test tests/Unit/Events/UpdateEventJourneyActionTest.php tests/Feature/Events/EventJourneyControllerTest.php
```

Resultado:

- `11` testes passaram;
- `125` assertions passaram.

### Tarefa 1.5 - Criar `UpdateEventJourneyRequest`

Subtarefas:

- validar `moderation_mode`;
- validar `modules`;
- validar `intake_defaults`;
- validar `intake_channels`;
- validar `content_moderation`;
- validar `media_intelligence`;
- validar consistencias:
  - `media_intelligence.mode=gate` exige `fallback_mode=review`;
  - Telegram ativo exige `bot_username` e `media_inbox_code`;
  - WhatsApp direto ativo exige `media_inbox_code`;
  - TTL entre `1` e `4320`;
  - provider permitido;
- usar `after()` para regras compostas e erros cruzados que nao cabem bem apenas em arrays de rules.

Criterios de aceite:

- validacoes duplicam o minimo necessario para o endpoint agregador;
- validacoes criticas continuam tambem nas actions especificas;
- erro retorna paths usaveis pelo inspector;
- regras cruzadas aparecem com mensagens entendiveis para quem esta operando o evento.

Testes:

```bash
cd apps/api
php artisan test --filter=UpdateEventJourneyRequestTest
```

Status em `2026-04-10`:

- [x] `UpdateEventJourneyRequest` criado;
- [x] o request agrega validacao de `moderation_mode`, `modules`, `intake_defaults`, `intake_channels`, `content_moderation` e `media_intelligence`;
- [x] as regras cruzadas foram implementadas via `after()`, alinhadas ao padrao oficial de `FormRequest` do Laravel;
- [x] `media_intelligence.mode=gate` exige `fallback_mode=review`;
- [x] `telegram.enabled=true` exige `bot_username` e `media_inbox_code`;
- [x] `whatsapp_direct.enabled=true` exige `media_inbox_code`;
- [x] TTL de canais continua protegido entre `1` e `4320`;
- [x] `reply_text_mode=fixed_random` exige ao menos um template;
- [x] `modules.wall=true` falha quando o evento nao tem entitlement para wall;
- [x] `whatsapp_instance_id` falha quando a instancia nao pertence a mesma organizacao do evento;
- [x] validacao do `provider_key/model_key` de OpenRouter foi reaproveitada no patch agregador;
- [x] o request esta pronto para ser usado no `PATCH /events/{event}/journey-builder` da `Tarefa 1.6`.

Bateria validada em `2026-04-10`:

```bash
cd apps/api
php artisan test tests/Unit/Events/UpdateEventJourneyRequestTest.php
php artisan test tests/Unit/Events/UpdateEventJourneyRequestTest.php tests/Feature/Events/EventJourneyControllerTest.php tests/Unit/Events/EventJourneyProjectionDataTest.php tests/Unit/Events/BuildEventJourneySummaryActionTest.php tests/Unit/Events/BuildEventJourneyProjectionActionTest.php tests/Feature/Events/EventJourneyArchitectureCharacterizationTest.php tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Events/EventIntakeChannelsTelegramPrivateTest.php tests/Feature/ContentModeration/ContentModerationSettingsTest.php tests/Feature/MediaIntelligence/MediaIntelligenceSettingsTest.php
```

Resultado:

- bateria unitária do request passou com `10` testes e `13` assertions;
- regressão ampliada passou com `54` testes e `362` assertions.

### Tarefa 1.6 - Criar `UpdateEventJourneyAction`

Subtarefas:

- abrir transacao;
- chamar `UpdateEventAction` para `moderation_mode`, `modules`, `intake_defaults`, `intake_channels` e blacklist se entrar na V1;
- chamar `UpsertEventContentModerationSettingsAction`;
- chamar `UpsertEventMediaIntelligenceSettingsAction`;
- retornar projection atualizada.

Criterios de aceite:

- save e atomico;
- erro em uma parte nao deixa estado parcial;
- projection retornada ja mostra novo estado.

Testes:

```bash
cd apps/api
php artisan test --filter=UpdateEventJourneyActionTest
```

Casos obrigatorios:

- atualizar canais + moderacao no mesmo request;
- atualizar Safety e VLM no mesmo request;
- falha em entitlement reverte alteracoes;
- `gate + skip` falha;
- reply fixed_random persiste templates;
- reply ai persiste preset/override.

Status em `2026-04-10`:

- [x] `UpdateEventJourneyAction` criado no modulo `Events`;
- [x] a action abre transacao externa para orquestrar o save agregado;
- [x] `UpdateEventAction` foi reutilizada para `moderation_mode`, `modules`, `intake_defaults` e `intake_channels`;
- [x] `UpsertEventContentModerationSettingsAction` foi ligada ao save agregado;
- [x] `UpsertEventMediaIntelligenceSettingsAction` foi ligada ao save agregado;
- [x] a action retorna a projection revalidada apos commit;
- [x] o save ficou atomico para `Event`, intake, Safety e VLM;
- [x] falhas de entitlement em intake continuam revertendo alteracoes;
- [x] `gate + skip` passou a falhar tambem dentro do orquestrador, protegendo rollback real;
- [x] `reply_text_mode=fixed_random` persiste templates no save agregado;
- [x] `reply_text_mode=ai` persiste `reply_prompt_preset_id` e `reply_prompt_override`;
- [x] o `PATCH` do controller passou a usar a action transacional e devolver a projection final.

Bateria validada em `2026-04-10`:

```bash
cd apps/api
php artisan test tests/Unit/Events/UpdateEventJourneyActionTest.php tests/Feature/Events/EventJourneyControllerTest.php
php artisan test tests/Unit/Events/UpdateEventJourneyActionTest.php tests/Unit/Events/UpdateEventJourneyRequestTest.php tests/Feature/Events/EventJourneyControllerTest.php tests/Unit/Events/EventJourneyProjectionDataTest.php tests/Unit/Events/BuildEventJourneySummaryActionTest.php tests/Unit/Events/BuildEventJourneyProjectionActionTest.php tests/Feature/Events/EventJourneyArchitectureCharacterizationTest.php tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Events/EventIntakeChannelsTelegramPrivateTest.php tests/Feature/ContentModeration/ContentModerationSettingsTest.php tests/Feature/MediaIntelligence/MediaIntelligenceSettingsTest.php
```

Resultado:

- bateria focada da action/controlador passou com `11` testes e `125` assertions;
- regressao ampliada passou com `62` testes e `442` assertions.

---

## Fase 2 - Frontend data layer e adapters

### Tarefa 2.1 - Criar types da projection

Subtarefas:

- criar `journey/types.ts`;
- tipar stages;
- tipar nodes;
- tipar branches;
- tipar capabilities;
- tipar scenarios;
- tipar update payload;
- evitar acoplamento direto com `Node` e `Edge` do React Flow nos types de dominio.

Criterios de aceite:

- types representam o payload backend;
- graph adapter e o unico ponto que conhece `@xyflow/react`;
- update payload reusa conceitos reais do evento.

Testes:

```bash
cd apps/web
npm run type-check
```

Status em `2026-04-10`:

- [x] `apps/web/src/modules/events/journey/types.ts` criado;
- [x] stages, nodes, branches, capabilities, scenarios e summary receberam types dedicados;
- [x] a projection e o payload de update foram tipados com os conceitos reais do backend;
- [x] os types de dominio continuam desacoplados de `Node` e `Edge` de `@xyflow/react`;
- [x] a caracterizacao do modulo agora protege esse desacoplamento.

Bateria validada em `2026-04-10`:

```bash
cd apps/web
npm run test -- src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
npm run type-check
```

Resultado:

- teste de caracterizacao do modulo passou com `4` testes;
- `type-check` passou sem erros.

### Tarefa 2.2 - Criar API client e query keys

Subtarefas:

- adicionar `getEventJourneyBuilder`;
- adicionar `updateEventJourneyBuilder`;
- adicionar query key dedicada;
- invalidar detail do evento, journey builder e settings relacionadas se necessario;
- manter draft local no frontend e evitar optimistic update da projection inteira;
- manter a mutation pendente ate terminar a invalidacao relevante.

Criterios de aceite:

- `TanStack Query` controla fetch/mutation;
- mutation invalida projection apos save;
- pagina nao chama cinco endpoints separados para salvar;
- refresh visual apos save sempre vem do projection payload revalidado.

Testes:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/api.test.ts
```

Status em `2026-04-10`:

- [x] `apps/web/src/modules/events/journey/api.ts` criado;
- [x] `getEventJourneyBuilder` e `updateEventJourneyBuilder` ligados ao endpoint agregador;
- [x] `queryKeys.events.journeyBuilder(id)` adicionado em `apps/web/src/lib/query-client.ts`;
- [x] a invalidacao apos save cobre a projection, os detalhes do evento e as settings relacionadas hoje consumidas por chaves legadas;
- [x] a mutation helper aguarda o fim das invalidacoes relevantes antes de encerrar `onSuccess`;
- [x] a tela futura continua livre de optimistic patch global da projection.

Bateria validada em `2026-04-10`:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/api.test.ts src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
npm run type-check
```

Resultado:

- `5` testes passaram na bateria de API do builder;
- `4` testes passaram na caracterizacao do modulo;
- `type-check` passou sem erros.

### Tarefa 2.3 - Criar `buildJourneyGraph`

Subtarefas:

- converter projection para `nodes`;
- converter branches para `edges`;
- aplicar posicoes fixas por stage;
- gerar handles estaveis;
- aplicar classes por `kind/status`;
- gerar edge labels.

Criterios de aceite:

- graph e deterministico;
- mesma projection gera mesmos IDs/posicoes;
- nodes inativos continuam visiveis se forem importantes para entendimento;
- nodes fora de entitlement aparecem como locked, nao como invisiveis.

Testes:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/buildJourneyGraph.test.ts
```

Casos obrigatorios:

- quatro stages sempre aparecem;
- branch `default` existe em decisoes;
- Safety desligado remove branch bloqueante ou marca como inativo;
- VLM enrich_only nao cria branch bloqueante;
- VLM gate cria branch de review/rejected;
- wall desligado marca output wall como inativo.

Status em `2026-04-10`:

- [x] `apps/web/src/modules/events/journey/buildJourneyGraph.ts` criado;
- [x] a projection agora vira `stages`, `nodes` e `edges` em uma camada pura e desacoplada do renderer;
- [x] os IDs de edge seguem o padrao estavel `source:branch->target`;
- [x] os handles de saida seguem o padrao estavel `branch:<branchId>`;
- [x] as posicoes do canvas usam mapa deterministico por node conhecido, com fallback previsivel por stage;
- [x] nodes e edges inativos continuam representados com classes semanticas, em vez de sumirem do fluxo.

Bateria validada em `2026-04-10`:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/buildJourneyGraph.test.ts src/modules/events/journey/__tests__/api.test.ts src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
npm run test -- src/modules/events
npm run type-check
```

Resultado:

- `6` testes passaram na bateria do graph adapter;
- `15` testes passaram na bateria combinada do builder inicial;
- `47` testes passaram na regressao do modulo `events`;
- `type-check` passou sem erros.

### Tarefa 2.4 - Criar resumo e simulador local

Subtarefas:

- criar `buildJourneySummary`;
- criar `buildJourneyScenarios`;
- criar `simulateJourneyScenario`;
- cada scenario retorna lista de node IDs e edge IDs destacados.

Cenarios V1:

- foto com legenda por WhatsApp privado;
- foto sem legenda por grupo;
- video por Telegram;
- remetente bloqueado;
- Safety bloqueou;
- Safety mandou para review;
- VLM gate pediu review;
- aprovado e publicado;
- rejeitado com resposta.

Criterios de aceite:

- simulador nao chama IA;
- simulador nao altera dados;
- simulador explica por que o caminho foi escolhido.

Testes:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/buildJourneyScenarios.test.ts
```

Status em `2026-04-10`:

- [x] `apps/web/src/modules/events/journey/buildJourneySummary.ts` criado;
- [x] `apps/web/src/modules/events/journey/buildJourneyScenarios.ts` criado;
- [x] `simulateJourneyScenario` passou a operar sobre `node IDs` e `edge IDs` estaveis do graph adapter;
- [x] os nove cenarios curados da V1 foram fixados em uma catalogacao local guiada;
- [x] o resumo humano agora aceita o estado base da projection ou a explicacao especifica de um cenario simulado;
- [x] cenarios indisponiveis continuam explicados com `unavailableReason`, sem inventar IA nem mutacao de estado.

Bateria validada em `2026-04-10`:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/buildJourneyScenarios.test.ts src/modules/events/journey/__tests__/buildJourneyGraph.test.ts src/modules/events/journey/__tests__/api.test.ts src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
npm run test -- src/modules/events
npm run type-check
```

Resultado:

- `6` testes passaram na bateria do simulador e resumo local;
- `21` testes passaram na bateria combinada do builder inicial;
- `53` testes passaram na regressao do modulo `events`;
- `type-check` passou sem erros.

### Tarefa 2.5 - Criar `toJourneyUpdatePayload`

Subtarefas:

- mapear inspector state para payload backend;
- preservar campos nao editados;
- normalizar TTL e templates;
- limpar campos que nao se aplicam:
  - reply override quando `reply_text_mode != ai`;
  - fixed templates quando `reply_text_mode != fixed_random`.

Criterios de aceite:

- payload e minimo e valido;
- nao apaga settings sem interacao do usuario;
- segue comportamento ja validado nos forms existentes.

Testes:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/toJourneyUpdatePayload.test.ts
```

Status em `2026-04-10`:

- [x] `apps/web/src/modules/events/journey/toJourneyUpdatePayload.ts` criado;
- [x] o mapper agora aceita draft do inspector e `dirtyFields` recursivo sem depender de APIs novas do `react-hook-form`;
- [x] o payload sai minimo e so inclui chaves realmente tocadas;
- [x] TTL, thresholds, `whatsapp_instance_id`, preset de reply e templates fixos sao normalizados para o contrato real do backend;
- [x] campos de reply que nao se aplicam sao limpos de forma coerente com o modo atual (`ai`, `fixed_random`, `disabled`);
- [x] arrays de grupos e blacklist passam a ser tratados como substituicao inteira quando houver interacao;
- [x] configuracoes nao tocadas continuam fora do payload e nao sao apagadas.

Bateria validada em `2026-04-10`:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/toJourneyUpdatePayload.test.ts src/modules/events/journey/__tests__/buildJourneyScenarios.test.ts src/modules/events/journey/__tests__/buildJourneyGraph.test.ts src/modules/events/journey/__tests__/api.test.ts src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
npm run type-check
```

Resultado:

- `7` testes passaram na bateria do mapper de update;
- `28` testes passaram na bateria combinada do builder inicial;
- `type-check` passou sem erros.

Observacao desta rodada:

- a regressao ampliada `npm run test -- src/modules/events` expôs `1` falha fora da trilha da jornada em `src/modules/events/EventDetailPage.test.tsx`, relacionada ao bloco de branding aplicado; a falha nao toca `journey/*` e ficou registrada como risco residual fora do escopo desta tarefa.

---

## Fase 3 - UI da Jornada da midia

### Tarefa 3.1 - Criar pagina `EventJourneyBuilderPage`

Subtarefas:

- criar page em `apps/web/src/modules/events/pages`;
- registrar rota em `App.tsx`;
- adicionar entrada na navegacao do evento;
- carregar projection por query;
- tratar loading/error/empty;
- manter titulo `Jornada da midia`;
- manter subtitulo `Como o evento trata cada foto ou video recebido`;
- renderizar legenda fixa com `Entrada`, `Processamento`, `Decisao`, `Saida`;
- adicionar acoes minimas `Centralizar fluxo` e `Ver detalhes tecnicos`;
- manter modo simples como experiencia padrao.

Criterios de aceite:

- pagina abre para evento autorizado;
- pagina mostra summary humano;
- pagina mostra canvas e inspector vazio;
- erro de permissao e tratado;
- pagina nao parece editor tecnico livre.

Testes:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/EventJourneyBuilderPage.test.tsx
```

Status em `2026-04-10`:

- [x] `apps/web/src/modules/events/pages/EventJourneyBuilderPage.tsx` criado como shell guiado da jornada;
- [x] a rota `/events/:id/flow` foi registrada em `apps/web/src/App.tsx`;
- [x] `apps/web/src/app/routing/route-preload.ts` passou a preaquecer a nova page dentro do matcher de `/events`;
- [x] `apps/web/src/modules/events/EventDetailPage.tsx` ganhou atalho visivel para `Jornada da midia`;
- [x] a page carrega a projection real por query e trata `loading`, `403`, erro generico e `stages=[]`;
- [x] o modo simples virou a experiencia padrao com titulo, subtitulo, resumo humano, legenda fixa e split `canvas + inspector`;
- [x] o lado esquerdo ficou como `fluxo visual guiado`, sem canvas livre nem dependencia de `@xyflow/react` nesta tarefa;
- [x] o lado direito ficou reservado para inspector, com estado vazio e bloco opcional de detalhes tecnicos;
- [x] a bateria residual de `EventDetailPage` foi saneada e a regressao ampla de `src/modules/events` voltou a fechar verde.

Bateria validada em `2026-04-10`:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/EventJourneyBuilderPage.test.tsx src/modules/events/EventDetailPage.test.tsx src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
npm run test -- src/modules/events
npm run type-check
```

Resultado:

- `4` testes passaram na bateria da nova page;
- `10` testes passaram na bateria focada `page + detail + characterization`;
- `69` testes passaram na regressao ampliada do modulo `events`;
- `type-check` passou sem erros.

### Tarefa 3.2 - Criar `JourneyFlowCanvas`

Subtarefas:

- renderizar `ReactFlow`;
- registrar `nodeTypes`;
- registrar `edgeTypes`;
- usar props travadas;
- aplicar `fitView`;
- aplicar `fitViewOptions` estavel;
- aplicar `ariaLabelConfig` em portugues;
- garantir container com largura e altura definidas;
- definir altura minima desktop e mobile para o canvas;
- selecionar node;
- destacar path simulado;
- manter `nodeTypes` e `edgeTypes` fora do componente ou memoizados;
- memoizar callbacks e objetos de config;
- aplicar `nopan` nos controles interativos internos;
- esconder handles condicionais com `visibility` e nao com `display: none`;
- chamar `useUpdateNodeInternals` se um node trocar handles dinamicamente;
- nao usar `MiniMap` e nao usar `onlyRenderVisibleElements` na V1.

Criterios de aceite:

- nao e possivel arrastar nodes;
- nao e possivel conectar nodes;
- selecao abre inspector;
- path simulado destaca nodes/edges;
- canvas continua usavel com zoom/pan;
- scroll da pagina nao fica preso de forma acidental;
- canvas nao quebra por falta de dimensao do container.

Testes:

- unitarios apenas para wrappers puros;
- comportamento real em Playwright.

Status em `2026-04-11`:

- [x] `@xyflow/react` foi instalado em `apps/web/package.json`;
- [x] `apps/web/src/modules/events/journey/JourneyFlowCanvas.tsx` foi criado como wrapper travado do `ReactFlow`;
- [x] `nodeTypes` e `edgeTypes` ficaram estaveis fora do corpo do componente;
- [x] `fitViewOptions` e `ariaLabelConfig` em portugues ficaram memoizados/constantes;
- [x] o canvas foi travado com `nodesDraggable={false}`, `nodesConnectable={false}`, `selectNodesOnDrag={false}`, `zoomOnDoubleClick={false}` e `preventScrolling={false}`;
- [x] `onlyRenderVisibleElements` ficou explicitamente `false` na V1;
- [x] handles continuam visiveis com `visibility`, sem `display: none`;
- [x] labels de edge usam `nopan` e o simulador agora destaca `node IDs` e `edge IDs` reais no canvas;
- [x] `EventJourneyBuilderPage` passou a consumir `JourneyFlowCanvas`, abrir inspector por selecao real do node e acionar `fitView` pela toolbar externa sem `ReactFlowProvider`;
- [x] o estado da page foi ajustado para respeitar a ordem dos hooks, com `graph`, `scenarios` e `selectedNode` derivados de forma estavel mesmo em `loading/error`.

Bateria validada em `2026-04-11`:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/JourneyFlowCanvas.test.tsx src/modules/events/journey/__tests__/EventJourneyBuilderPage.test.tsx src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
npm run test -- src/modules/events
npm run type-check
```

Resultado:

- `3` testes passaram na bateria do `JourneyFlowCanvas`;
- `11` testes passaram na bateria focada `canvas + page + characterization`;
- `72` testes passaram na regressao ampliada do modulo `events`;
- `type-check` passou sem erros.

### Tarefa 3.3 - Criar nodes customizados

Subtarefas:

- criar `JourneyNodeCard`;
- criar `JourneyDecisionNode`;
- criar chips `Ativo`, `Desativado`, `Obrigatorio`, `Opcional`, `Automatico`, `Bloqueado pelo pacote`;
- criar previews curtos de config;
- criar estados visuais por stage;
- mapear cores de stage em tokens semanticos da jornada;
- garantir foco visivel em card selecionado, acoes e chips interativos.

Criterios de aceite:

- card fala linguagem operacional;
- card nao mostra formulario inteiro;
- card tem nome acessivel;
- card permite selecao por teclado;
- design nao depende de hex solto ou foco invisivel.

Testes:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/JourneyNodeCard.test.tsx
```

Status em `2026-04-11`:

- [x] `apps/web/src/modules/events/journey/JourneyNodeCard.tsx` foi criado como camada pura de apresentacao;
- [x] `apps/web/src/modules/events/journey/JourneyFlowNodes.tsx` passou a concentrar os custom nodes e o `nodeTypes` estavel do `React Flow`;
- [x] a UI inline saiu de `JourneyFlowCanvas.tsx`, que agora ficou focado em `ReactFlow`, edges e callbacks;
- [x] os chips visuais foram humanizados para `Ativo`, `Desativado`, `Obrigatorio`, `Opcional`, `Automatico` e `Bloqueado pelo pacote`;
- [x] os nos de decisao passaram a mostrar uma faixa propria de caminhos/branches sem poluir o wrapper do canvas;
- [x] o wrapper do canvas deixou de empurrar `width/height` diretamente no node e passou a usar `style`, alinhando melhor com a trilha oficial de custom nodes.

Bateria validada em `2026-04-11`:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/JourneyNodeCard.test.tsx src/modules/events/journey/__tests__/JourneyFlowCanvas.test.tsx src/modules/events/journey/__tests__/EventJourneyBuilderPage.test.tsx
npm run test -- src/modules/events
npm run type-check
```

Resultado:

- `2` testes passaram na bateria de `JourneyNodeCard`;
- `9` testes passaram na bateria focada `node card + canvas + page`;
- `74` testes passaram na regressao ampliada do modulo `events`;
- `type-check` passou sem erros.

### Tarefa 3.4 - Criar edges customizadas

Subtarefas:

- criar edge com label;
- aplicar cor por outcome;
- suportar highlight do simulador;
- evitar labels longas.

Criterios de aceite:

- edges sao legiveis;
- labels usam `Sim`, `Nao`, `Review`, `Bloqueado`, `Publicado`;
- simulacao destaca caminho sem poluir o resto.

Testes:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/JourneyEdgeLabel.test.tsx
```

Status em `2026-04-11`:

- [x] `apps/web/src/modules/events/journey/JourneyEdgeLabel.tsx` foi criado como camada pura para o label da edge;
- [x] `apps/web/src/modules/events/journey/JourneyFlowEdges.tsx` passou a concentrar o custom edge, o `edgeTypes` estavel e o mapper `toJourneyFlowEdge`;
- [x] `JourneyFlowCanvas.tsx` deixou de carregar a edge inline e passou a consumir o modulo proprio;
- [x] a edge customizada passou a usar `getBezierPath`, alinhando o path com a API oficial do `React Flow`;
- [x] a cor do stroke e o chip da label continuam reagindo a `active`, `inactive`, `locked`, `unavailable` e `highlighted`;
- [x] o adapter `buildJourneyGraph.ts` foi redistribuido para reduzir sobreposicao visual entre nos, com novas coordenadas deterministicas por faixa;
- [x] a bateria agora protege explicitamente que nos da mesma faixa nao se sobrepoem;
- [x] o viewport da page foi aberto com mais altura util (`min-h` maior e `fitView` com padding menor) para reduzir a compressao inicial do canvas.

Bateria validada em `2026-04-11`:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/JourneyEdgeLabel.test.tsx src/modules/events/journey/__tests__/buildJourneyGraph.test.ts src/modules/events/journey/__tests__/JourneyFlowCanvas.test.tsx src/modules/events/journey/__tests__/EventJourneyBuilderPage.test.tsx
npm run test -- src/modules/events
npm run type-check
```

Resultado:

- `1` teste passou na bateria de `JourneyEdgeLabel`;
- `7` testes passaram na bateria de `buildJourneyGraph`, incluindo a protecao de nao sobreposicao por faixa;
- `15` testes passaram na bateria focada `edge + graph + canvas + page`;
- `76` testes passaram na regressao ampliada do modulo `events`;
- `type-check` passou sem erros.

### Tarefa 3.5 - Criar inspector lateral

Subtarefas:

- desktop: painel lateral direito;
- mobile: drawer inferior;
- mapear cada node para inspector section;
- reutilizar forms existentes quando possivel:
  - `EventContentModerationSettingsForm`;
  - `EventMediaIntelligenceSettingsForm`;
- criar forms leves para canais de entrada, modo de moderacao, destinos e respostas;
- manter `SheetTitle` e `SheetDescription` em todas as variantes do inspector;
- manter botoes de icone com nome acessivel;
- tratar tooltip como ajuda secundaria, nao como instrucoes principais;
- separar claramente estado em rascunho local vs estado salvo.

Criterios de aceite:

- click no node abre inspector certo;
- Safety pode ser editado;
- MediaIntelligence pode ser editado;
- canais podem ser editados;
- mudancas mostram preview antes do save;
- salvar atualiza projection;
- foco ao abrir e fechar o inspector e previsivel;
- mobile drawer abre com cabecalho completo e legivel.

Testes:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/JourneyInspector.test.tsx
```

Status em `2026-04-11`:

- [x] `apps/web/src/modules/events/journey/JourneyInspector.tsx` foi criado como inspector responsivo da jornada;
- [x] o desktop manteve painel lateral direito nao modal, coerente com o split `canvas + inspector` da V1;
- [x] o mobile passou a abrir drawer inferior com `DrawerTitle` e `DrawerDescription` explicitos;
- [x] cada `node.id` relevante agora resolve uma `inspector section` propria;
- [x] formularios leves entraram para `modo de moderacao`, `WhatsApp privado`, `WhatsApp grupos`, `Telegram` e `Link de envio`;
- [x] `EventContentModerationSettingsForm` foi reaproveitado para Safety;
- [x] `EventMediaIntelligenceSettingsForm` foi reaproveitado para VLM, gate de contexto e resposta automatica;
- [x] o save continua centralizado em `updateEventJourneyBuilder`, sem mutation paralela por form;
- [x] `buildJourneyInspectorDraft.ts` passou a gerar o draft minimo dos formularios leves antes do patch agregado;
- [x] `toJourneyUpdatePayload` continua sendo usado para patch minimo dos canais e do modo de moderacao;
- [x] Safety e MediaIntelligence usam leitura detalhada temporaria pelos endpoints existentes para hidratar os formularios reaproveitados, mas persistem pelo endpoint agregado da jornada;
- [x] o inspector ja cobre a primeira fatia editavel do builder: moderacao, canais, Safety e MediaIntelligence;
- [x] nodes ainda fora dessa primeira fatia continuam em modo read-only com CTA explicito para o editor completo do evento.

Bateria validada em `2026-04-11`:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/JourneyInspector.test.tsx src/modules/events/journey/__tests__/EventJourneyBuilderPage.test.tsx
npm run test -- src/modules/events
npm run type-check
```

Resultado:

- `6` testes passaram na bateria de `JourneyInspector`;
- `10` testes passaram na bateria focada `inspector + page`;
- `82` testes passaram na regressao ampliada do modulo `events`;
- `type-check` passou sem erros.

### Tarefa 3.6 - Criar templates guiados

Templates V1:

- `Aprovacao direta`;
- `Revisao manual`;
- `IA moderando`;
- `Hibrido IA + humano`;
- `Evento social simples`;
- `Evento corporativo controlado`.

Subtarefas:

- criar catalogo local;
- cada template aplica patch sem salvar imediatamente;
- mostrar diff humano;
- exigir confirmacao;
- atualizar resumo e projection local em rascunho antes do save real.

Criterios de aceite:

- template nao salva sozinho;
- template nao ativa capability sem entitlement;
- diff e compreensivel;
- usuario consegue desfazer antes de salvar;
- usuario entende o que mudou no rascunho antes de persistir.

Testes:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/JourneyTemplateRail.test.tsx
```

### Tarefa 3.7 - Criar simulador de cenarios

Subtarefas:

- criar botoes de cenario;
- destacar path;
- abrir explicacao lateral;
- limpar simulacao;
- nao alterar form state.

Criterios de aceite:

- usuario entende caminho de foto aprovada;
- usuario entende caminho de midia bloqueada;
- usuario entende que video tem tratamento diferente quando IA de imagem nao se aplica;
- simulacao nao dispara request.

Testes:

```bash
cd apps/web
npm run test -- src/modules/events/journey/__tests__/JourneyScenarioSimulator.test.tsx
```

---

## Fase 4 - Playwright e validacao visual

### Tarefa 4.1 - Criar E2E da pagina

Subtarefas:

- mockar/semear evento com canais ativos;
- abrir `/events/:id/flow`;
- selecionar node de Safety;
- alterar configuracao;
- salvar;
- validar resumo atualizado;
- selecionar scenario;
- validar destaque de caminho.

Criterios de aceite:

- fluxo principal funciona em browser real;
- seletores usam `getByRole`, `getByLabel`, `getByText`;
- nao depender de CSS selector fragil;
- navegacao por teclado continua funcional no canvas, toolbar e inspector.

Teste:

```bash
cd apps/web
npm run test:e2e -- event-journey-builder.spec.ts
```

### Tarefa 4.2 - Criar visual e aria snapshots

Estados:

- fluxo padrao manual;
- fluxo com IA moderando;
- fluxo com Safety bloqueando;
- fluxo com multiplos canais;
- drawer mobile aberto;
- path simulado destacado.

Criterios de aceite:

- snapshots rodam em ambiente consistente;
- dados dinamicos sao mascarados;
- elementos volateis recebem estabilizacao visual quando necessario;
- snapshots nao viram fonte unica de verdade;
- `toMatchAriaSnapshot()` cobre pelo menos pagina base e inspector aberto;
- snapshots semanticos usam labels em portugues alinhadas a UX.

Teste:

```bash
cd apps/web
npm run test:e2e -- event-journey-builder-visual.spec.ts
```

### Tarefa 4.3 - Criar smoke de acessibilidade

Subtarefas:

- validar botoes e acoes principais por `role` e nome acessivel;
- validar titulo e descricao do inspector;
- validar foco visivel em navegacao por teclado;
- validar ausencia de controles essenciais apenas em tooltip;
- validar drawer mobile com cabecalho acessivel.

Criterios de aceite:

- pagina principal pode ser percorrida com teclado;
- inspector anuncia contexto suficiente para screen reader;
- botoes so com icone nao entram sem label acessivel.

Teste:

```bash
cd apps/web
npm run test:e2e -- event-journey-builder-accessibility.spec.ts
```

---

## Fase 5 - Documentacao e rollout

### Tarefa 5.1 - Atualizar docs de arquitetura

Arquivos:

- `docs/architecture/event-media-flow-builder-analysis-2026-04-09.md`;
- este plano de execucao;
- se a implementacao alterar fluxo real, atualizar `docs/flows/media-ingestion.md`.

Criterios de aceite:

- doc reflete o que foi implementado;
- itens fora da V1 continuam marcados como roadmap;
- links oficiais continuam listados.

### Tarefa 5.2 - Atualizar docs frontend/backend do modulo

Subtarefas:

- atualizar `apps/web/README.md` se a rota virar pagina formal;
- atualizar README do modulo `Events` se houver padrao de endpoint agregador;
- registrar endpoints novos onde o repo documenta rotas relevantes.

Criterios de aceite:

- novo ponto de entrada localizavel;
- ownership do modulo claro.

### Tarefa 5.3 - Rollout controlado

Subtarefas:

- se necessario, proteger a rota por feature flag;
- liberar primeiro para admins/operadores internos;
- validar com um evento real de homologacao;
- coletar feedback de noiva/cerimonial.

Criterios de aceite:

- editor atual continua disponivel;
- nao ha regressao em edicao de evento;
- suporte consegue explicar a jornada.

---

## Matriz de testes obrigatoria por entrega

## Backend

Comandos minimos por PR:

```bash
cd apps/api
php artisan test tests/Feature/ContentModeration/ContentModerationPipelineTest.php
php artisan test --filter=EventJourney
php artisan test tests/Feature/Events/EventJourneyArchitectureCharacterizationTest.php
php artisan test tests/Feature/Events/EventIntakeChannelsTest.php tests/Feature/Events/EventIntakeChannelsTelegramPrivateTest.php
php artisan test tests/Feature/ContentModeration/ContentModerationSettingsTest.php tests/Feature/MediaIntelligence/MediaIntelligenceSettingsTest.php
php artisan test tests/Unit/MediaProcessing/MediaEffectiveStateResolverTest.php tests/Unit/MediaProcessing/FinalizeMediaDecisionActionTest.php
```

Obrigatorio passar:

- projection;
- update;
- permissoes;
- entitlements;
- Safety;
- VLM;
- matriz de decisao;
- shape exato do contrato;
- budget de query do projection.

## Frontend

Comandos minimos por PR:

```bash
cd apps/web
npm run type-check
npm run test -- src/modules/moderation/feed-utils.test.ts
npm run test -- src/modules/events/journey
npm run test -- src/modules/events/event-media-flow-builder-architecture-characterization.test.ts
npm run test -- src/modules/events/intake.test.ts src/modules/events/components/content-moderation/EventContentModerationSettingsForm.test.tsx src/modules/events/components/media-intelligence/EventMediaIntelligenceSettingsForm.test.tsx
```

Obrigatorio passar:

- graph adapter;
- summary;
- scenario simulator;
- update payload mapper;
- inspector;
- node cards;
- forms reaproveitados;
- caracterizacao de rota/dependencia/fundacao;
- regras de rascunho local vs estado salvo.

## Playwright

Comando minimo quando UI estiver pronta:

```bash
cd apps/web
npm run test:e2e -- event-journey-builder.spec.ts
npm run test:e2e -- event-journey-builder-visual.spec.ts
npm run test:e2e -- event-journey-builder-accessibility.spec.ts
```

Obrigatorio passar:

- abrir pagina;
- selecionar node;
- editar inspector;
- salvar;
- simular scenario;
- validar mobile drawer;
- validar aria snapshots principais;
- validar labels e foco basicos.

## Full regression antes de merge final

Backend:

```bash
cd apps/api
php artisan test
```

Frontend:

```bash
cd apps/web
npm run type-check
npm run test
npm run test:e2e
```

---

## Definition of done da V1

A V1 so esta pronta quando:

- a Fase 0B deixou a baseline relevante verde de novo;
- endpoint `GET journey-builder` retorna projection completa;
- endpoint `PATCH journey-builder` salva configuracoes reais do evento;
- a pagina renderiza quatro faixas fixas;
- `React Flow` esta travado contra canvas livre;
- `React Flow` usa labels e acessibilidade em portugues;
- nodes usam linguagem operacional;
- inspector edita canais, moderacao, Safety, VLM e resposta;
- inspector sempre abre com titulo e descricao claros;
- summary humano e gerado automaticamente;
- simulador destaca pelo menos seis cenarios;
- templates guiados aplicam patch sem salvar automaticamente;
- testes backend novos passam;
- testes frontend novos passam;
- E2E principal passa;
- docs foram atualizadas;
- editor atual do evento continua funcionando.

---

## Riscos e mitigacoes

## Risco 1 - Duplicar regra em nodes/edges

Mitigacao:

- `nodes/edges` sao apenas projection;
- regras continuam em actions e services;
- update payload espelha settings reais.

## Risco 2 - Canvas virar automacao livre

Mitigacao:

- `nodesDraggable=false`;
- `nodesConnectable=false`;
- sem create edge;
- sem delete;
- sem DSL na V1;
- sem `MiniMap` e sem controles de editor avancado.

## Risco 3 - Simulador prometer mais do que o backend faz

Mitigacao:

- scenarios limitados a capacidades reais;
- impressao e branch destino ficam fora da V1;
- video recebe explicacao especifica.

## Risco 4 - Testes unitarios nao pegarem problema de canvas

Mitigacao:

- graph/summary em Vitest;
- canvas e edges reais em Playwright;
- snapshots visuais para estados chave;
- `aria snapshots` para estados centrais.

## Risco 5 - Save parcial deixar evento inconsistente

Mitigacao:

- `UpdateEventJourneyAction` transacional;
- reuso das actions existentes;
- tests de rollback em falha de entitlement/validacao;
- sem optimistic patch global da projection no frontend.

---

## Ordem recomendada de implementacao

1. Concluido: estabilizar baseline backend de assets e revalidar a trilha frontend de duplicate cluster.
2. Concluido: rerodar a bateria base.
3. Proximo passo: criar backend read-only projection.
4. Criar graph adapter frontend com dados mockados.
5. Criar pagina com canvas travado read-only.
6. Criar inspector read-only.
7. Criar update backend.
8. Ligar inspector ao update real.
9. Adicionar templates guiados.
10. Adicionar simulador.
11. Adicionar Playwright.
12. Atualizar docs e liberar em rollout controlado.

Essa ordem reduz risco porque:

- limpa os bloqueios reais ja detectados antes de abrir a feature;
- valida primeiro o contrato;
- permite screenshot cedo;
- evita mexer em save antes de ter leitura visual aprovada;
- protege a pipeline real enquanto a UI amadurece.
