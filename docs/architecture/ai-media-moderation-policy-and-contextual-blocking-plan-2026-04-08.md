# Moderacao De Midia Por IA: Safety Objetiva, Bloqueio Contextual E Plano De Evolucao

Data da analise: 2026-04-08

## Objetivo

Esta doc consolida o que realmente precisa ser feito para evoluir a moderacao por IA da midia no `eventovivo`.

O foco aqui e separar duas camadas que hoje estao misturadas na pratica:

- `Safety objetiva`
- `Bloqueio contextual`

Tambem fecha:

- o que a API oficial da OpenAI suporta de verdade;
- o que o codigo atual faz hoje;
- o que precisa mudar no banco, backend e front;
- como deve funcionar a configuracao global e a sobreposicao por evento;
- como deve ficar o historico de bloqueios para operacao e auditoria;
- o diagnostico do lote real mais recente via webhook WhatsApp.

Leitura complementar:

- `docs/architecture/ia-respostas-automaticas-de-midia-execution-plan.md`
- `docs/architecture/photo-processing-ai-hardening-execution-plan.md`

---

## Escopo Real Analisado

### Backend

- `apps/api/app/Modules/WhatsApp/Listeners/RouteInboundToMediaPipeline.php`
- `apps/api/app/Modules/InboundMedia/Jobs/ProcessInboundWebhookJob.php`
- `apps/api/app/Modules/InboundMedia/Jobs/NormalizeInboundMessageJob.php`
- `apps/api/app/Modules/InboundMedia/Http/Controllers/PublicUploadController.php`
- `apps/api/app/Modules/MediaProcessing/Jobs/DownloadInboundMediaJob.php`
- `apps/api/app/Modules/MediaProcessing/Jobs/GenerateMediaVariantsJob.php`
- `apps/api/app/Modules/ContentModeration/Jobs/AnalyzeContentSafetyJob.php`
- `apps/api/app/Modules/MediaIntelligence/Jobs/EvaluateMediaPromptJob.php`
- `apps/api/app/Modules/MediaProcessing/Jobs/RunModerationJob.php`
- `apps/api/app/Modules/MediaProcessing/Actions/FinalizeMediaDecisionAction.php`
- `apps/api/app/Modules/ContentModeration/Services/OpenAiContentModerationProvider.php`
- `apps/api/app/Modules/MediaIntelligence/Services/OpenAiCompatibleVisualReasoningPayloadFactory.php`
- `apps/api/app/Modules/MediaProcessing/Http/Resources/EventMediaDetailResource.php`

### Frontend

- `apps/web/src/modules/events/components/content-moderation/EventContentModerationSettingsCard.tsx`
- `apps/web/src/modules/events/components/content-moderation/EventContentModerationSettingsForm.tsx`
- `apps/web/src/modules/events/components/media-intelligence/EventMediaIntelligenceSettingsCard.tsx`
- `apps/web/src/modules/events/components/media-intelligence/EventMediaIntelligenceSettingsForm.tsx`
- `apps/web/src/modules/ai/MediaAutomaticRepliesPage.tsx`
- `apps/web/src/modules/moderation/components/ModerationReviewPanel.tsx`
- `apps/web/src/App.tsx`

Leitura:

- a analise abaixo nao parte de mock ou ideia abstrata;
- ela parte do fluxo real que hoje recebe midia, cria `EventMedia`, roda filas separadas e expoe resultado no painel.

---

## Stack Atual Relevante

### Backend

- `Laravel 12`
- `PostgreSQL`
- `Redis + queues`
- modulos principais desta trilha:
  - `WhatsApp`
  - `InboundMedia`
  - `MediaProcessing`
  - `ContentModeration`
  - `MediaIntelligence`

### Frontend

- `React 18`
- `TypeScript`
- `TanStack Query`
- `React Hook Form + Zod`
- superficie administrativa atual dividida entre:
  - detalhe do evento
  - central de moderacao
  - pagina dedicada `IA > Respostas automaticas de midia`

### Leitura arquitetural

- a recepcao da midia e a moderacao ja estao modularizadas;
- a decisao final de publicacao nao fica espalhada;
- ela converge em `FinalizeMediaDecisionAction`;
- o problema atual nao e falta de pipeline;
- o problema atual e que a camada de configuracao e explicacao para operador ainda nao acompanha a arquitetura real.

---

## Como A Midia Entra E O Que Acontece Hoje

### 1. Recepcao

Entradas reais hoje:

- WhatsApp via webhook
- Telegram via webhook
- upload publico por link/QR

No caso de WhatsApp:

- `RouteInboundToMediaPipeline` resolve contexto do evento;
- comandos de ativacao e sessao sao tratados antes;
- `sticker` e ignorado como galeria;
- `audio` e capturado como `event_audio`, nao como foto/video da galeria;
- imagem e video elegiveis sao enviados para `ProcessInboundWebhookJob`.

### 2. Normalizacao

O webhook nao cria `EventMedia` direto.

Ele passa por duas etapas primeiro:

- `ChannelWebhookLog`
- `InboundMessage`

Fluxo atual:

1. `ProcessInboundWebhookJob` persiste o payload bruto em `ChannelWebhookLog`;
2. `NormalizeInboundMessageJob` cria `InboundMessage` com:
   - `event_id`
   - `event_channel_id`
   - `message_type`
   - `body_text`
   - `media_url`
   - `capture_target`
   - `trace_id`
3. se houver midia baixavel, `DownloadInboundMediaJob` e enfileirado.

Leitura:

- isso e importante porque a auditoria do webhook e separada da entidade de galeria;
- o pipeline nao depende do provider entregar payload perfeito em uma unica etapa.

### 3. Download e convergencia em `EventMedia`

`DownloadInboundMediaJob` faz a convergencia operacional.

Comportamento atual:

- `audio`:
  - baixa arquivo
  - salva em `events/{event}/audio-recordings`
  - atualiza `InboundMessage`
  - nao cria `EventMedia`
- `image` e `video`:
  - baixa arquivo
  - salva original
  - cria `EventMedia`
  - inicializa:
    - `moderation_status=pending`
    - `publication_status=draft`
    - `safety_status=queued|skipped`
    - `vlm_status=queued|skipped`
    - `face_index_status=queued|skipped`

Leitura:

- a moderacao por IA acontece depois que a midia ja virou entidade propria do dominio;
- por isso a configuracao de safety/contexto deve mirar o pipeline de `EventMedia`, nao apenas a borda do webhook.

### 4. Upload publico cai no mesmo pipeline base

`PublicUploadController` nao passa por `ChannelWebhookLog` nem `InboundMessage`.

Mas depois da criacao ele converge no mesmo tronco:

- cria `EventMedia`
- dispara `GenerateMediaVariantsJob`
- entra no mesmo funil de safety, VLM e decisao final

Leitura:

- o produto tem multiplas portas de entrada;
- mas o pipeline inteligente ja foi desenhado para convergir.

### 5. Variantes, safety, VLM e decisao final

Pipeline atual para imagem:

1. `GenerateMediaVariantsJob`
2. `AnalyzeContentSafetyJob`
3. opcionalmente `EvaluateMediaPromptJob`
4. `RunModerationJob`
5. `PublishMediaJob` quando aprovado

Pontos importantes:

- `GenerateMediaVariantsJob` gera `fast_preview`, `thumb`, `gallery` e `wall`;
- `AnalyzeContentSafetyJob` roda em `media-safety`;
- `EvaluateMediaPromptJob` roda em `media-vlm`;
- `RunModerationJob` nao chama provider externo;
  - ele so consolida estados e define o `moderation_status` final;
- `FinalizeMediaDecisionAction` e a matriz unica da decisao automatica.

Leitura:

- isso e um desenho bom;
- safety e contexto ja estao separados em etapas tecnicas;
- o que ainda nao esta separado direito e o produto e a administracao dessas etapas.

### 6. Regimes diferentes por tipo de midia

Hoje:

- imagem:
  - passa por variantes
  - pode passar por safety
  - pode passar por VLM
- video:
  - nao passa por variantes de imagem
  - nao passa por VLM visual atual
  - segue para decisao e publicacao com regra simplificada
- audio:
  - fica capturado no evento
  - nao entra na galeria/telao
- sticker:
  - nao entra na galeria

Leitura:

- a doc de moderacao precisa refletir isso;
- nem todo anexo que entra no canal deve ser tratado como "midia moderavel da galeria".

---

## Como O Back E O Front Administram Isso Hoje

### Backend administrativo atual

Hoje existem duas superficies distintas:

1. `ContentModeration`
   - endpoint por evento:
     - `/api/v1/events/{event}/content-moderation/settings`
2. `MediaIntelligence`
   - endpoint por evento para VLM
   - endpoints globais hoje focados em `IA > Respostas automaticas de midia`

Leitura:

- `Safety` ja tem endpoint administrativo por evento;
- `VLM contextual` tambem tem configuracao por evento;
- mas ainda nao existe uma area global dedicada para a politica de moderacao por IA como produto proprio.

### Front atual no detalhe do evento

Hoje o detalhe do evento expoe dois cards separados:

- `Safety por evento`
- `VLM por evento`

O que o operador consegue fazer hoje:

- em `Safety`:
  - habilitar/desabilitar
  - provider
  - fallback
  - thresholds de `nudity`, `violence` e `self_harm`
- em `VLM`:
  - habilitar/desabilitar
  - escolher provider/modelo
  - escolher `enrich_only` ou `gate`
  - editar `approval_prompt`
  - editar `caption_style_prompt`
  - configurar resposta automatica da midia

Leitura:

- isso prova que a operacao atual esta fragmentada;
- o operador configura `safety` em um card;
- configura `contexto` e `resposta automatica` em outro;
- e a pagina global de `IA` ainda fala de respostas, presets e testes, nao de moderacao.

### Front atual na central de moderacao

A central `/moderation` ja consome a ultima leitura de IA da midia.

Hoje o painel mostra:

- `safety_status`
- `vlm_status`
- ultima avaliacao de safety:
  - decisao
  - motivos
  - scores
- ultima avaliacao de VLM:
  - decisao
  - motivo textual
  - legenda sugerida
  - tags

O detalhe da API ja expoe tambem:

- `request_payload` da safety
- `request_payload` do VLM
- `prompt_context` do VLM

Leitura:

- a camada de observabilidade ja esta melhor do que a camada de configuracao;
- o sistema ja registra bastante coisa;
- mas ainda nao entrega um produto claro para operador entender "qual regra bloqueou o que".

### Pagina global `IA`

Hoje a rota:

- `/ia/respostas-de-midia`

ja existe e e uma boa ancora para crescer.

Mas ela esta focada em:

- instrucao padrao de resposta
- textos fixos
- presets
- teste do prompt
- historico de respostas

Leitura:

- o lugar conceitualmente certo para a nova administracao de moderacao por IA e dentro de `IA`;
- mas isso ainda nao foi levado para la no codigo atual.

---

## Resumo Executivo

Leitura curta:

- a stack atual ja tem um pipeline unico e razoavelmente maduro para recepcao, download, variantes, safety, VLM e publicacao;
- a camada atual de `ContentModeration` esta objetiva, mas simplificada demais;
- a camada atual de `MediaIntelligence` esta assumindo um papel de bloqueio contextual com prompt bruto;
- a observabilidade ja existe em nivel bom:
  - `ChannelWebhookLog`
  - `InboundMessage`
  - `EventMediaSafetyEvaluation`
  - `EventMediaVlmEvaluation`
  - detalhe da midia na central de moderacao;
- o principal gap nao e "falta de IA";
- o principal gap e a administracao estar fragmentada entre cards por evento, feed de moderacao e uma pagina global de IA que hoje fala de respostas, nao de moderacao;
- isso explica por que uma imagem de camera passou na safety e mesmo assim foi rejeitada com a frase:
  - `Esta imagem nao representa o contexto da Homologacao WhatsApp Z-API Local.`
- esse texto nao veio da OpenAI Moderations API;
- esse texto veio da trilha de `VLM contextual`.

Conclusao pratica:

- `Safety objetiva` e `bloqueio contextual` precisam virar produtos separados, mesmo que coexistam no pipeline;
- `alcool` e `cigarro` nao devem nascer como categoria da OpenAI Moderations API, porque a API nao expone isso como categoria pronta;
- esses toggles pertencem ao bloco de `bloqueio contextual`;
- o painel de IA precisa oferecer:
  - configuracao global;
  - sobreposicao por evento;
  - uma tela unica para a politica de moderacao, sem espalhar a operacao entre `Safety`, `VLM` e `resposta automatica`;
  - modo de entrada `imagem` ou `imagem + caption`;
  - historico real de rejeicoes contextuais;
  - motivacao clara do bloqueio;
  - politica estruturada, em vez de um prompt principal solto.

---

## 1. O Que A OpenAI Suporta De Verdade

## 1.1 Moderations API

Validacao oficial:

- a doc oficial da OpenAI confirma que `omni-moderation-latest` aceita moderacao de imagem e tambem entrada multimodal com imagem + texto;
- a mesma doc confirma que a resposta inclui:
  - `flagged`
  - `categories`
  - `category_scores`
  - `category_applied_input_types`

Fontes oficiais:

- `https://developers.openai.com/api/docs/guides/moderation#quickstart`
- `https://developers.openai.com/api/docs/guides/moderation#content-classifications`

Leitura importante:

- `category_applied_input_types` informa se o score veio da imagem, do texto ou dos dois;
- isso e importante para auditoria e para explicar por que um item foi sinalizado;
- hoje o backend ja persiste `provider_category_input_types_json`, o que e bom e deve ser preservado.

## 1.2 Categorias reais da Moderations API

Categorias relevantes hoje:

- `sexual`
- `sexual/minors`
- `self-harm`
- `self-harm/intent`
- `self-harm/instructions`
- `violence`
- `violence/graphic`
- categorias textuais como:
  - `harassment`
  - `hate`
  - `illicit`

Leitura importante:

- a OpenAI nao expone categoria pronta de:
  - `alcool`
  - `cigarro`
  - `tabaco`
  - `vape`
- portanto, esse tipo de regra nao deve ser modelado como se fosse uma categoria nativa da Safety API.

Conclusao:

- `alcool` e `cigarro` pertencem a uma politica contextual do produto;
- nao pertencem ao threshold objetivo da OpenAI Moderations API.

## 1.3 Structured Outputs / JSON schema

Validacao oficial:

- a doc oficial distingue `JSON mode` de `Structured Outputs`;
- `Structured Outputs` garantem aderencia ao schema quando suportados;
- isso e o modelo certo para respostas estruturadas do bloco contextual.

Fonte oficial:

- `https://developers.openai.com/api/docs/guides/structured-outputs#structured-outputs-vs-json-mode`

Leitura pratica:

- para o bloco contextual, continuar usando resposta estruturada e a decisao correta;
- mas o schema atual e pequeno demais para explicar bem a rejeicao para operador.

---

## 2. Como O Codigo Esta Hoje

## 2.1 Safety objetiva

Hoje a safety objetiva esta em `ContentModeration`.

Arquivos principais:

- [OpenAiContentModerationProvider.php](C:/laragon/www/eventovivo/apps/api/app/Modules/ContentModeration/Services/OpenAiContentModerationProvider.php)
- [EventContentModerationSetting.php](C:/laragon/www/eventovivo/apps/api/app/Modules/ContentModeration/Models/EventContentModerationSetting.php)
- [EventContentModerationSettingsForm.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/events/components/content-moderation/EventContentModerationSettingsForm.tsx)

Comportamento atual:

- sempre envia imagem para a Moderations API;
- se existir `caption` ou `body_text`, tambem envia texto junto;
- depois mapeia as categorias do provider para apenas 3 eixos internos:
  - `nudity`
  - `violence`
  - `self_harm`

Leitura:

- isso funciona para um gate inicial;
- mas esta acoplado a uma leitura simplificada demais;
- hoje nao existe configuracao para escolher:
  - apenas imagem
  - imagem + caption

## 2.2 Bloqueio contextual

Hoje o bloqueio contextual esta acontecendo via `MediaIntelligence`.

Arquivos principais:

- [EventMediaIntelligenceSetting.php](C:/laragon/www/eventovivo/apps/api/app/Modules/MediaIntelligence/Models/EventMediaIntelligenceSetting.php)
- [OpenAiCompatibleVisualReasoningPayloadFactory.php](C:/laragon/www/eventovivo/apps/api/app/Modules/MediaIntelligence/Services/OpenAiCompatibleVisualReasoningPayloadFactory.php)
- [VisualReasoningResponseSchemaFactory.php](C:/laragon/www/eventovivo/apps/api/app/Modules/MediaIntelligence/Services/VisualReasoningResponseSchemaFactory.php)

Comportamento atual:

- usa `approval_prompt` textual livre;
- injeta o nome do evento no prompt;
- injeta `caption` e `body_text` quando existem;
- injeta tambem a instrucao da resposta automatica da imagem;
- pede um JSON estruturado com:
  - `decision`
  - `review`
  - `reason`
  - `short_caption`
  - `reply_text`
  - `tags`

Leitura:

- essa camada hoje esta fazendo mais do que "legenda" ou "resposta automatica";
- ela esta atuando como `juiz contextual`;
- so que a politica contextual esta representada por prompt bruto, nao por regras de produto.

## 2.3 Caso real validado: imagem da camera

Caso real:

- `event_media_id=11354`

Estado real:

- `safety_status=pass`
- `vlm_status=rejected`
- `moderation_status=approved`
- `publication_status=published`

Resultado:

- a OpenAI Moderations API nao bloqueou a imagem;
- o `VLM contextual` rejeitou a imagem por estar fora do contexto do evento.

Mensagem salva:

- `Esta imagem nao representa o contexto da Homologacao WhatsApp Z-API Local.`

Motivo salvo:

- a imagem mostrava uma camera fotografica antiga e o modelo entendeu que isso nao tinha relacao com o contexto do evento.

Conclusao:

- hoje o operador pode interpretar esse caso como "moderação IA bloqueou";
- mas tecnicamente isso nao foi Safety;
- foi contexto.

---

## 3. Problema De Produto Atual

Hoje o produto mistura tres coisas diferentes:

1. risco objetivo da imagem
2. aderencia ao contexto do evento
3. estilo da resposta automatica

Isso cria confusao operacionais:

- o operador nao sabe se a imagem falhou por safety ou por contexto;
- o painel atual de moderacao nao espelha a politica que o operador imagina configurar;
- a frase de rejeicao contextual aparece sem uma estrutura de politica que explique o motivo;
- o usuario do painel nao consegue configurar `alcool` ou `cigarro` do jeito que a UI mockada sugere;
- o operador nao consegue filtrar facilmente:
  - rejeicoes objetivas
  - rejeicoes contextuais
  - qual input contribuiu para o flag
  - qual preset ou politica estava ativa

---

## 4. Decisao De Arquitetura

## 4.1 Separacao obrigatoria

O sistema precisa separar formalmente:

### A. Safety objetiva

Responsabilidade:

- avaliar risco objetivo do conteudo contra categorias suportadas pela OpenAI Moderations API;
- produzir bloqueio ou review por thresholds claros;
- nao depender do contexto do evento para decidir.

Exemplos:

- nudez
- violencia
- autoagressao

### B. Bloqueio contextual

Responsabilidade:

- avaliar se a imagem faz sentido para o evento e para a politica visual daquele evento;
- aplicar regras de negocio como:
  - permitir alcool
  - permitir cigarro
  - bloquear mascaras, fantasia, brindes, armas cenicas etc
  - respeitar excecoes permitidas

Exemplos:

- foto de camera isolada em evento onde so faz sentido gente/festa
- imagem com cigarro quando o evento quer bloquear isso
- imagem com bebida quando o evento permite

### C. Resposta automatica

Responsabilidade:

- so responder texto curto se a midia ja passou nas camadas anteriores;
- nao decidir bloqueio.

## 4.2 Estado consolidado do dominio

O codigo atual ja separa alguns estados tecnicos:

- `processing_status`
- `safety_status`
- `vlm_status`
- `moderation_status`
- `publication_status`
- `decision_source`

Mas isso ainda nao forma uma maquina de estados de dominio forte o suficiente.

Evidencia concreta:

- o `EventMediaResource` hoje calcula o `status` de front priorizando `publication_status=published`;
- isso significa que uma midia pode continuar aparecendo como publicada mesmo quando uma camada tecnica relevante registra rejeicao contextual.

Leitura:

- o pipeline tem boas etapas;
- o estado consolidado ainda esta simplificado demais.

Modelo alvo recomendado:

- manter estados tecnicos por etapa:
  - `safety_decision`
  - `context_decision`
  - `operator_decision`
  - `publication_decision`
- e calcular um estado consolidado:
  - `effective_media_state`

Exemplo de saidas consolidadas:

- `received`
- `processing`
- `pending_review`
- `approved_ready`
- `published`
- `rejected_safety`
- `rejected_context`
- `rejected_operator`
- `failed_pipeline`

Regra importante:

- `effective_media_state` nunca deve resultar em publicado quando uma camada bloqueante efetiva rejeitou a midia;
- `publication_status` deve virar consequencia do estado consolidado, nao a unica fonte da verdade visual.

## 4.3 Politica resolvida e snapshots

`global + evento` e pouco para o produto que esta se formando.

Modelo recomendado:

1. `preset`
2. `global`
3. `event_override`
4. `runtime_override` opcional

Leitura:

- `runtime_override` nao precisa ficar aberto para qualquer operador;
- ele faz sentido para supervisor ou trilha controlada de homologacao;
- o usuario comum deve ver so:
  - `Herdando politica`
  - `Personalizado`

O backend deve resolver a politica efetiva e persistir snapshot por avaliacao.

Campos recomendados por avaliacao:

- `policy_snapshot_json`
- `policy_sources_json`

Exemplo de leitura esperada:

- `allow_alcohol=true (source=preset)`
- `allow_tobacco=false (source=event_override)`
- `context_scope=image_only (source=global)`

---

## 5. Modelo Alvo De Produto

## 5.1 Area de front

A configuracao deve ficar em `IA`, nao em `Configuracoes`.

Estrutura recomendada:

- `IA > Moderacao de midia`
- `IA > Respostas automaticas de midia`

Para `Moderacao de midia`, abas recomendadas:

- `Visao geral`
- `Safety objetiva`
- `Bloqueio contextual`
- `Historico`

Bloco lateral fixo recomendado:

- `Politica efetiva agora`

Conteudo minimo desse bloco:

- preset ativo
- nivel de rigor
- escopos ativos
- heranca ou personalizacao
- fallback
- versao da politica

## 5.2 Escopo global e por evento

As duas camadas precisam suportar:

- configuracao geral
- configuracao por evento que sobrepoe a geral

Regra recomendada:

- no banco, os campos de evento devem aceitar `null` para herdar o global;
- o backend resolve a politica efetiva;
- o log guarda:
  - valores globais
  - preset aplicado
  - overrides do evento
  - overrides de runtime, quando existirem
  - politica efetiva resolvida

## 5.3 UX operacional recomendada

No detalhe do evento:

- mostrar resumo operacional, nao a configuracao bruta
- exemplos:
  - `Seguranca: equilibrada`
  - `Contexto: casamento padrao`
  - `Resposta automatica: ativa`
  - `Politica: herdando do global`
- CTA:
  - `Personalizar politica deste evento`

Na area `IA > Moderacao de midia`:

- manter edicao completa
- separar a experiencia em tres modos:
  - `Basico`
  - `Avancado`
  - `Auditoria`

No modo `Basico`:

- preset
- rigor
- permitir alcool
- permitir cigarro
- pessoas obrigatorias / contexto obrigatorio quando existir
- resposta automatica

No modo `Avancado`:

- bloqueios adicionais
- excecoes
- scopes
- fallbacks
- versionamento

No modo `Auditoria`:

- prompt resolvido
- payload enviado
- resposta estruturada
- `reason_code`
- snapshot da politica

## 5.4 Presets como centro do produto

Os presets precisam deixar de ser detalhe e virar a camada principal de configuracao para leigo.

Catalogo inicial recomendado:

- `Casamento equilibrado`
- `Casamento rigido`
- `Formatura`
- `Corporativo restrito`
- `Aniversario infantil`
- `Homologacao livre`

Leitura:

- isso melhora custo-beneficio de operacao;
- reduz necessidade de prompt livre;
- acelera onboarding do operador;
- diminui risco de configuracao errada em evento real.

---

## 6. Politica Estruturada Que Precisa Ir Para O Banco

## 6.1 Safety objetiva

Manter em `ContentModeration`, mas ampliar o contrato.

Campos novos recomendados para configuracao global:

- `enabled`
- `provider_key`
- `objective_safety_scope`
  - `image_only`
  - `image_and_text_context`
- `mode`
  - `enforced`
  - `observe_only`
- `fallback_mode`
  - `review`
  - `block`
- `threshold_version`
- `hard_block_thresholds_json`
- `review_thresholds_json`

Campos equivalentes por evento:

- mesmos campos, todos com `nullable` para herdar do global, exceto `event_id`

Observacao:

- o provider hoje inclui texto automaticamente quando existe `caption` ou `body_text`;
- isso precisa virar escolha explicita;
- a OpenAI Moderations API ja expone `category_applied_input_types`, entao essa granularidade vale a pena.

## 6.2 Bloqueio contextual

Essa e a parte que precisa parar de ser prompt bruto como regra principal.

Campos globais recomendados:

- `enabled`
- `provider_key`
- `model_key`
- `context_scope`
  - `image_only`
  - `image_and_text_context`
- `allow_alcohol`
- `allow_tobacco`
- `blocked_terms_json`
- `allowed_exceptions_json`
- `freeform_instruction`
- `fallback_mode`
  - `review`
  - `skip`
- `require_json_output`
- `prompt_version`
- `policy_version`

Campos equivalentes por evento:

- mesmos campos, com `nullable` para herdar do global

Leitura:

- `blocked_terms_json` e a materializacao de `bloquear_tambem`
- `allowed_exceptions_json` e a materializacao de `excecoes_permitidas`
- `freeform_instruction` continua existindo, mas como complemento, nao como centro da politica

## 6.3 Contexto textual normalizado

`caption` e `body_text` nao devem entrar de forma crua em toda camada.

Precisa existir uma etapa de normalizacao:

- `normalized_text_context_mode`
  - `none`
  - `body_only`
  - `caption_only`
  - `body_plus_caption`
  - `operator_summary`
- `normalized_text_context`

Leitura:

- isso reduz ruidao de legenda ruim;
- deixa a explicacao operacional mais clara;
- facilita dizer no log:
  - `usou so imagem`
  - `usou imagem + texto recebido`
  - `usou resumo do operador`

## 6.4 Scope da resposta automatica

A resposta automatica nao precisa herdar exatamente o mesmo scope de safety e contexto.

Campo recomendado:

- `reply_scope`
  - `image_only`
  - `image_and_text_context`

Leitura:

- safety, contexto e resposta automatica nao precisam operar no mesmo regime;
- separar isso reduz falso positivo e mantem a resposta automatica util.

## 6.5 Banco recomendado

Opcao com menor arrependimento:

### Modulo `ContentModeration`

Criar:

- `content_moderation_global_settings`

E ampliar:

- `event_content_moderation_settings`

### Modulo `MediaIntelligence`

Criar:

- `media_contextual_moderation_global_settings`

E ampliar:

- `event_media_intelligence_settings`

E ampliar logs/evaluations com:

- `policy_snapshot_json`
- `policy_sources_json`
- `normalized_text_context`
- `normalized_text_context_mode`

Motivo:

- respeita a separacao de dominio;
- evita jogar tudo em uma tabela generica de IA;
- deixa o front de `IA` agregado, mas o backend continua limpo.

---

## 7. Prompt Builder Contextual

## 7.1 Regra

O backend deve gerar o prompt contextual a partir da politica estruturada.

O operador nao deve precisar escrever a regra principal em texto livre.

## 7.2 Entrada do builder

Entrada efetiva recomendada:

- preset efetivo
- politica global
- override do evento
- override de runtime, quando existir
- nome do evento
- `context_scope`
- `normalized_text_context`, se o modo permitir

## 7.3 Saida do builder

O builder deve produzir:

- `policy_json`
- `policy_snapshot_json`
- `policy_sources_json`
- `prompt_template`
- `prompt_resolved`
- `variables_json`

## 7.4 Estrutura recomendada da instrucao contextual

Exemplo de direcao:

- avaliar se a imagem e adequada ao contexto do evento;
- considerar a politica efetiva:
  - alcool permitido ou nao
  - cigarro permitido ou nao
  - lista de bloqueios adicionais
  - lista de excecoes
- nao inventar contexto que nao esteja visivel;
- se a imagem for ambigua, usar `review`;
- devolver motivo objetivo e curto.

## 7.5 Campo livre

`freeform_instruction` continua existindo, mas:

- opcional
- complementar
- com prioridade menor do que a politica estruturada

---

## 8. JSON Schema Contextual Precisa Evoluir

## 8.1 Schema atual

Hoje o schema devolve:

- `decision`
- `review`
- `reason`
- `short_caption`
- `reply_text`
- `tags`

Isso e util, mas ainda fraco para operacao.

## 8.2 Schema recomendado

Evoluir para algo como:

```json
{
  "decision": "approve|review|reject",
  "review": true,
  "reason": "texto curto",
  "reason_code": "context.out_of_scope|policy.alcohol|policy.tobacco|policy.blocked_term|policy.uncertain",
  "matched_policies": ["policy.tobacco"],
  "matched_exceptions": [],
  "input_scope_used": "image_only|image_and_text_context",
  "input_types_considered": ["image", "text"],
  "confidence_band": "high|medium|low",
  "publish_eligibility": "auto_publish|review_only|reject",
  "short_caption": "texto",
  "reply_text": "texto",
  "tags": ["camera", "objeto"]
}
```

Leitura:

- `reason_code` e obrigatorio para historico e filtro;
- `matched_policies` melhora muito a explicacao para operador;
- `input_scope_used` e `input_types_considered` ajudam a explicar se o caption pesou na decisao.
- `confidence_band` ajuda triagem operacional;
- `publish_eligibility` aproxima a resposta do estado final do dominio.

---

## 9. Historico Que Precisa Existir

## 9.1 Safety objetiva

Ja temos boa parte do necessario em `event_media_safety_evaluations`.

Precisa garantir exposicao no painel de:

- imagem
- evento
- provider/model
- `flagged`
- categorias do provider
- scores do provider
- `category_applied_input_types`
- thresholds aplicados
- motivo final do bloqueio ou review

## 9.2 Bloqueio contextual

Precisa existir um historico dedicado e filtravel.

Pode ser alimentado de duas formas:

- pela propria `event_media_vlm_evaluations`, enriquecida;
- ou por uma view/endpoint agregado especifico para operador.

Filtros minimos:

- evento
- periodo
- modelo
- provider
- `decision`
- `reason_code`
- preset/politica
- remetente
- telefone
- tipo de midia

Campos minimos na resposta:

- preview da imagem
- caption recebido
- texto normalizado considerado
- `effective_media_state`
- politica efetiva resolvida
- prompt final
- payload enviado
- payload respondido
- motivo
- `reason_code`
- se usou `image_only` ou `image_and_text_context`

---

## 10. Validacao Real Do Webhook E Do Lote Mais Recente

## 10.1 DM com `#FOTOS-EVIVO-TESTE`

Validacao real:

- o codigo com `#` esta funcionando;
- mensagens reais:
  - `672`
  - `675`

O problema do lote que respondeu:

- `Seu acesso para ... nao esta mais ativo`

nao foi falha no parser do codigo.

O problema real foi:

- a sessao do remetente `554896553954` tinha sido encerrada por `Sair`
- mensagem real:
  - `694`

Depois disso chegaram:

- `710` video
- `711` sticker
- `712` imagem

Como a sessao ja estava fechada, o webhook respondeu corretamente com a instrucao de reativacao.

## 10.2 Sticker

Validacao real:

- sticker nao deve virar midia de galeria;
- isso ja esta corrigido no listener do WhatsApp.

## 10.3 Audio

Regra de produto validada:

- audio nao vai para galeria ou telao;
- mas deve ficar capturado e vinculado ao evento para uso futuro.

Estado atual:

- o codigo ja foi corrigido para isso;
- os testes automatizados passaram;
- mas o worker local estava rodando binario antigo quando entrou um audio real mais tarde.

Evidencia:

- o log das `19:11` ainda mostrava o audio passando pelo fluxo antigo;
- isso indicava worker stale, nao regressao do repositorio.

Acao executada:

- worker local reiniciado para carregar o codigo atual.

Leitura:

- a proxima validacao real de audio precisa ser feita com o worker novo ja ativo;
- o caminho correto agora e pedir um audio novo de teste.

## 10.4 Imagem da camera rejeitada

Diagnostico real:

- nao foi bloqueio da OpenAI Moderations API;
- foi rejeicao contextual do `VLM`.

Isso confirma a necessidade de separar no produto:

- `Safety objetiva`
- `Bloqueio contextual`

## 10.5 Performance, custo-beneficio e endurecimento operacional

Leitura da stack atual:

- o projeto ja usa filas separadas por responsabilidade:
  - `webhooks`
  - `media-download`
  - `media-fast`
  - `media-safety`
  - `media-vlm`
  - `media-publish`
  - `whatsapp-send`
- os jobs centrais ja usam base boa de resiliencia:
  - `ShouldBeUnique`
  - `WithoutOverlapping`
  - `ThrottlesExceptionsWithRedis`
- o projeto ja tem `Horizon` configurado com supervisors e thresholds por fila.

Conclusao pragmatica:

- nao faz sentido explodir em muitas filas novas sem medir gargalo primeiro;
- o maior ganho de custo-beneficio no curto prazo e endurecer a topologia atual e separar o que hoje ainda compete em `media-fast`.

Melhoria imediata recomendada:

1. manter:
   - `webhooks`
   - `media-download`
   - `media-safety`
   - `media-vlm`
   - `media-publish`
2. considerar dividir `media-fast` em:
   - `media-variants`
   - `media-decision`
3. manter `whatsapp-send` isolada para feedback rapido ao usuario

Motivo:

- `GenerateMediaVariantsJob` e `RunModerationJob` hoje disputam a mesma fila;
- separar essas duas etapas reduz latencia perceptivel sem multiplicar demais a operacao.

Endurecimento recomendado:

- `ProcessInboundWebhookJob`
  - idempotencia por provider + external_message_id + attachment_index
- `DownloadInboundMediaJob`
  - continuar unico por midia + checagem reentrante
- `GenerateMediaVariantsJob`
  - manter lock por `event_media_id`
- `FinalizeMediaDecisionAction`
  - transacao + `lockForUpdate`
- `PublishMediaJob`
  - continuar idempotente e reentrante
- locks atomicos fora do banco
  - `Cache::lock` onde houver disputa entre workers e webhook replay

Observacao importante:

- o codigo ja tem boa base de idempotencia e overlap em `variants`, `safety`, `vlm`, `moderation` e `publish`;
- o proximo passo e padronizar essa disciplina tambem na borda e no estado consolidado.

## 10.6 Sinal real dos testes automatizados

Resultados validados nesta analise:

- rodada backend focada:
  - `93 passed`
  - `3 failed`
- rodada frontend focada:
  - `10 passed`

Leitura de consistencia:

- a bateria backend repetiu os mesmos 3 pontos de falha;
- a bateria frontend focada ficou verde;
- isso reforca que a principal lacuna atual esta no endurecimento do backend e dos fixtures de provider.

Falhas backend relevantes:

- `ContentModerationCircuitBreakerTest`
  - o teste nao observou as 2 chamadas esperadas antes de abrir o circuito;
- `VllmVisualReasoningProviderTest`
  - os fixtures nao entregaram asset local valido para o fallback binario;
  - por isso a falha aconteceu antes do parse do JSON invalido esperado.

Leitura:

- isso nao invalida a direcao da doc;
- mas mostra dois pontos reais a endurecer:
  - cobertura de circuit breaker na camada de safety
  - fixtures e contratos de fallback binario nos providers VLM

## 10.7 Performance do painel administrativo

Na stack atual de front, as melhorias com melhor custo-beneficio sao:

- `enabled` para queries dependentes
- `placeholderData` para evitar flicker
- `prefetch` do resumo e dos historicos antes de abrir a tela
- `optimistic update` no resumo lateral da politica efetiva

Leitura:

- isso combina com o stack atual de `TanStack Query`;
- nao exige reescrever o painel;
- reduz a sensacao de waterfall e tela piscando.

Para a nova area `IA > Moderacao de midia`, o minimo recomendavel e:

- prefetch de:
  - summary
  - politica efetiva
  - ultimas avaliacoes
  - filtros/facets do historico
- uso de `placeholderData` ao trocar abas e filtros
- manter o resumo lateral estavel durante refetch

## 10.8 Estrutura de formulario recomendada

Para o front novo, a melhor abordagem e um formulario hierarquico:

- contexto unico de formulario
- listas dinamicas para bloqueios e excecoes
- watchers isolados para o preview lateral

Leitura:

- isso e mais barato e performatico do que espalhar estado por muitos componentes independentes;
- e a forma mais segura de sustentar:
  - modo basico
  - modo avancado
  - modo auditoria

---

## 11. O Que Precisamos Implementar

## IA-MOD-01 — Separar as duas camadas no produto

Objetivo:

- separar `Safety objetiva` de `Bloqueio contextual` no modelo mental, no banco, no backend e no front.

Tarefas:

- criar contratos independentes de configuracao;
- criar resolucao efetiva global + evento para cada camada;
- separar os historicos no painel;
- ajustar a copy da UI.

Critério de aceite:

- operador entende claramente por que a midia caiu:
  - safety
  - contexto

## IA-MOD-02 — Criar configuracao global de Safety objetiva

Objetivo:

- tirar o default hardcoded e ter configuracao global real.

Tarefas:

- criar `content_moderation_global_settings`;
- criar action/resource/request;
- criar endpoints administrativos em `IA`;
- permitir override por evento.

Critério de aceite:

- existe configuracao geral editavel;
- evento pode herdar ou sobrepor.

## IA-MOD-03 — Adicionar `analysis_scope`

Objetivo:

- permitir escolher:
  - `apenas imagem`
  - `imagem + caption`

Tarefas:

- adicionar campo global e por evento;
- aplicar no provider de Safety;
- aplicar no builder contextual;
- persistir no log o escopo efetivo.

Critério de aceite:

- operador escolhe o escopo;
- log mostra o escopo usado.

## IA-MOD-04 — Trocar prompt bruto por politica estruturada

Objetivo:

- tornar o bloqueio contextual gerenciavel.

Tarefas:

- criar campos estruturados:
  - `allow_alcohol`
  - `allow_tobacco`
  - `blocked_terms_json`
  - `allowed_exceptions_json`
  - `freeform_instruction`
- criar resolvedor de politica efetiva;
- criar prompt builder backend;
- manter campo livre como complementar.

Critério de aceite:

- operador nao depende mais de prompt livre para o caso basico.

## IA-MOD-05 — Evoluir JSON schema contextual

Objetivo:

- dar explicacao forte para operacao.

Tarefas:

- adicionar:
  - `reason_code`
  - `matched_policies`
  - `matched_exceptions`
  - `input_scope_used`
  - `input_types_considered`
- atualizar parser, DTO e persistencia;
- ampliar o historico.

Critério de aceite:

- rejeicao contextual aparece com motivo estruturado e filtravel.

## IA-MOD-06 — Criar area de front em `IA > Moderacao de midia`

Objetivo:

- mover a configuracao para o lugar correto do produto.

Tarefas:

- criar pagina dedicada;
- criar abas:
  - `Safety objetiva`
  - `Bloqueio contextual`
  - `Historico`
- incluir configuracao global;
- incluir seletor de evento para sobreposicao.

Critério de aceite:

- nenhuma configuracao de moderacao por IA fica escondida em tela errada.

## IA-MOD-07 — Historico operacional de bloqueios contextuais

Objetivo:

- permitir que operador entenda comportamento real em producao.

Tarefas:

- endpoint agregado para historico;
- filtros por evento, periodo, motivo, modelo, remetente;
- thumbnail + caption + politica + decisao;
- payload tecnico acessivel.

Critério de aceite:

- operador consegue entender porque a IA bloqueou e em qual politica isso caiu.

## IA-MOD-08 — Testes automatizados

Objetivo:

- garantir que a mudanca nao vire regressao silenciosa.

Tarefas backend:

- unit para resolvedor de politica;
- unit para prompt builder;
- feature para `analysis_scope=image_only`;
- feature para `analysis_scope=image_and_caption`;
- feature para historico contextual;
- feature para merge global + evento.

Tarefas frontend:

- testes de labels;
- testes de heranca global/evento;
- testes de toggle `alcool/cigarro`;
- testes do historico e filtros.

## 11A. Plano Revisado Com Base Na Stack Atual

Este plano revisado substitui a sequencia recomendada do bloco anterior.

Ele reflete melhor:

- a stack real ja existente;
- a necessidade de resposta quase imediata no fluxo;
- a necessidade de custo-beneficio em producao;
- a necessidade de estados consolidados mais fortes;
- a necessidade de explicacao operacional para usuario leigo.

## IA-MOD-R1 - Separar as camadas e endurecer o estado final da midia

Objetivo:

- separar `Safety objetiva`, `bloqueio contextual`, `decisao operacional` e `publicacao` como camadas tecnicas independentes;
- calcular um `effective_media_state` coerente para o dominio.

Tarefas:

- manter estados tecnicos por etapa:
  - `safety_decision`
  - `context_decision`
  - `operator_decision`
  - `publication_decision`
- criar `effective_media_state` calculado no backend;
- impedir que a API exponha como `published` uma midia que tenha rejeicao relevante ativa;
- revisar resources, filtros e labels do painel para usar o estado consolidado.

Status desta rodada:

- [x] resolver backend para `effective_media_state`
- [x] exposicao de `safety_decision`, `context_decision`, `operator_decision` e `publication_decision`
- [x] ajuste do `status` do resource para usar estado consolidado
- [x] cobertura automatizada para conflito `published` + rejeicao contextual bloqueante

Criterio de aceite:

- a trilha tecnica por etapa continua rastreavel;
- o estado consolidado nunca mascara uma rejeicao relevante.

## IA-MOD-R2 - Resolver a politica em quatro camadas e salvar snapshot versionado

Objetivo:

- transformar a configuracao em politica resolvida, rastreavel e explicavel.

Tarefas:

- suportar estas camadas:
  - `preset`
  - `global`
  - `event_override`
  - `runtime_override`
- criar `policy_snapshot_json` com os valores efetivos usados na avaliacao;
- criar `policy_sources_json` indicando a origem efetiva de cada campo;
- versionar a politica para auditoria e replay tecnico;
- restringir `runtime_override` a perfis supervisor/administrador.

Status desta rodada:

- [x] persistencia de `policy_snapshot_json` em `event_media_safety_evaluations`
- [x] persistencia de `policy_sources_json` em `event_media_safety_evaluations`
- [x] persistencia de `policy_snapshot_json` em `event_media_vlm_evaluations`
- [x] persistencia de `policy_sources_json` em `event_media_vlm_evaluations`
- [x] exposicao de snapshot/source no payload detalhado da midia
- [x] semantica do snapshot ajustada para manter `provider_key` e `model_key` como politica efetiva, promovendo override apenas quando houve fallback real

Criterio de aceite:

- cada avaliacao guarda a politica efetiva e a origem de cada campo;
- operador comum ve apenas `Herdando do global` ou `Personalizado`.

## IA-MOD-R3 - Criar configuracao global real de Safety objetiva

Objetivo:

- tirar defaults duros do codigo e administrar Safety como produto.

Tarefas:

- criar `content_moderation_global_settings`;
- criar action, request, resource e endpoints administrativos;
- permitir override por evento;
- explicitar se a analise usa:
  - `somente imagem`
  - `imagem + contexto textual`
- persistir no log o escopo efetivo aplicado.

Status desta rodada:

- [x] tabela `content_moderation_global_settings`
- [x] model/factory/action/request/resource/controller para configuracao global de safety
- [x] endpoints administrativos:
  - `GET /api/v1/content-moderation/global-settings`
  - `PATCH /api/v1/content-moderation/global-settings`
- [x] heranca efetiva `global -> evento` no endpoint do evento
- [x] reset do evento para voltar a herdar o global via `inherit_global=true`
- [x] runtime do pipeline de safety usando configuracao global quando nao existe override do evento
- [x] `analysis_scope` inicial para safety objetiva:
  - `image_only`
  - `image_and_text_context`
- [x] log/run result da safety enriquecidos com `input_scope_used`

Validacao desta rodada:

- [x] `ContentModerationGlobalSettingsTest`
- [x] `ContentModerationSettingsTest`
- [x] `ContentModerationPipelineTest`
- [x] `OpenAiContentModerationProviderTest`
- [x] regressao ampliada das areas tocadas: `113 passed`, `814 assertions`

Criterio de aceite:

- existe configuracao global editavel;
- evento pode herdar ou sobrepor;
- o log mostra qual escopo de Safety foi usado.

## IA-MOD-R4 - Separar scopes por camada e normalizar o contexto textual

Objetivo:

- reduzir ruido, aumentar explicabilidade e evitar que `caption` e `body_text` crus contaminem todas as decisoes.

Tarefas:

- separar:
  - `objective_safety_scope`
  - `context_scope`
  - `reply_scope`
- criar `normalized_text_context_mode` com opcoes:
  - `none`
  - `body_only`
  - `caption_only`
  - `body_plus_caption`
  - `operator_summary`
- persistir `normalized_text_context`;
- exibir no historico se a decisao usou:
  - apenas imagem
  - imagem + texto recebido
  - imagem + resumo operacional

Status desta rodada:

- [x] alias semantico de `objective_safety_scope` sobre o `analysis_scope` de safety
- [x] `context_scope` em `event_media_intelligence_settings`
- [x] `reply_scope` em `event_media_intelligence_settings`
- [x] `normalized_text_context_mode` em safety global, safety por evento e VLM por evento
- [x] builder backend para `normalized_text_context`
- [x] persistencia de `normalized_text_context` e `normalized_text_context_mode` em:
  - `event_media_safety_evaluations`
  - `event_media_vlm_evaluations`
- [x] `prompt_context_json` enriquecido com:
  - `context_scope`
  - `reply_scope`
  - `normalized_text_context`
  - `normalized_text_context_mode`
- [x] historico real de respostas expondo escopo e texto normalizado

Validacao desta rodada:

- [x] `NormalizedTextContextBuilderTest`
- [x] `ContentModerationGlobalSettingsTest`
- [x] `ContentModerationSettingsTest`
- [x] `OpenAiContentModerationProviderTest`
- [x] `ContentModerationPipelineTest`
- [x] `MediaIntelligenceSettingsTest`
- [x] `UpsertEventMediaIntelligenceSettingsActionTest`
- [x] `OpenAiCompatibleVisualReasoningPayloadFactoryTest`
- [x] `MediaIntelligencePipelineTest`
- [x] `MediaReplyEventHistoryTest`
- [x] regressao focal de `R4`: `38 passed`, `250 assertions`
- [x] regressao ampliada das areas tocadas apos `R4` + `R8`: `176 passed`, `1246 assertions`

Criterio de aceite:

- cada camada usa apenas o escopo configurado para ela;
- operador consegue entender qual texto entrou ou nao entrou na decisao.

## IA-MOD-R5 - Trocar prompt bruto por politica estruturada + builder backend

Objetivo:

- tornar o bloqueio contextual gerenciavel, barato de operar e menos dependente de prompt livre.

Tarefas:

- criar campos estruturados:
  - `allow_alcohol`
  - `allow_tobacco`
  - `required_people_context`
  - `blocked_terms_json`
  - `allowed_exceptions_json`
  - `freeform_instruction`
- manter `freeform_instruction` como complementar, nao como regra principal;
- criar `ContextualModerationPolicyResolver`;
- criar builder backend do prompt a partir da politica resolvida;
- mapear presets de produto:
  - `Casamento equilibrado`
  - `Casamento rigido`
  - `Formatura`
  - `Corporativo restrito`
  - `Aniversario infantil`
  - `Homologacao livre`

Criterio de aceite:

- o operador basico consegue configurar o caso principal com preset + poucos toggles;
- o modo avancado continua disponivel sem exigir prompt livre para regra de negocio basica.

## IA-MOD-R6 - Evoluir o schema contextual e separar elegibilidade de publicacao

Objetivo:

- transformar a resposta contextual em decisao estruturada e realmente operacional.

Tarefas:

- adicionar ao schema:
  - `reason_code`
  - `matched_policies`
  - `matched_exceptions`
  - `input_scope_used`
  - `input_types_considered`
  - `confidence_band`
  - `publish_eligibility`
- atualizar parser, DTO, persistencia e resources;
- separar claramente:
  - `approve`
  - `review_only`
  - `reject`
- usar `publish_eligibility` para apoiar `effective_media_state`.

Criterio de aceite:

- rejeicao contextual aparece com motivo estruturado e filtravel;
- casos incertos podem cair em revisao sem serem confundidos com aprovacao plena.

## IA-MOD-R7 - Mover a administracao principal para `IA > Moderacao de midia`

Objetivo:

- colocar a experiencia de configuracao no lugar certo do produto e reduzir complexidade no detalhe do evento.

Tarefas:

- criar pagina dedicada com abas:
  - `Visao geral`
  - `Safety objetiva`
  - `Contexto do evento`
  - `Historico`
- criar bloco lateral fixo:
  - `Politica efetiva agora`
- no detalhe do evento, deixar apenas:
  - resumo operacional
  - status de heranca
  - CTA `Personalizar politica deste evento`
- modelar a UI em tres modos:
  - `Basico`
  - `Avancado`
  - `Auditoria`

Criterio de aceite:

- a edicao completa fica centralizada em `IA > Moderacao de midia`;
- o detalhe do evento deixa de expor configuracao bruta para operador leigo.

## IA-MOD-R8 - Endurecer o backend para producao real

Objetivo:

- garantir rapidez, idempotencia e seguranca sob alta concorrencia.

Tarefas:

- aplicar idempotencia por webhook e anexo;
- endurecer jobs criticos com:
  - `ShouldBeUnique`
  - `WithoutOverlapping`
  - `ThrottlesExceptions`
- proteger finalizacao de decisao com transacao + lock;
- garantir que `PublishMediaJob` seja reentrante e idempotente;
- revisar segmentacao das filas de negocio:
  - `inbound-webhooks`
  - `media-download`
  - `media-variants`
  - `media-safety`
  - `media-context`
  - `media-publish`
  - `media-audit`
- acompanhar throughput, runtime e falhas via Horizon antes de aumentar infraestrutura.

Status desta rodada:

- [x] log de finalizacao de moderacao enriquecido com estado consolidado e relevancia de cada camada
- [x] fallback de provider por camada
- [x] endurecimento transacional explicito da finalizacao
- [x] revisao fina da segmentacao de filas
- [x] separacao pratica do antigo `media-fast` em:
  - `media-variants`
  - `media-audit`
- [x] alinhamento de `queue_name`, tags, waits do Horizon e `queue:monitor`
- [x] manutencao pragmatica das filas que ja estavam isoladas e com bom custo-beneficio:
  - `webhooks`
  - `media-download`
  - `media-safety`
  - `media-vlm`
  - `media-publish`

Validacao desta rodada:

- [x] `ContentModerationPipelineTest`
- [x] `MediaIntelligencePipelineTest`
- [x] `FinalizeMediaDecisionActionTest`
- [x] `ContentModerationProviderManagerTest`
- [x] `VisualReasoningProviderManagerTest`
- [x] `ContentModerationCircuitBreakerTest`
- [x] `VllmVisualReasoningProviderTest`
- [x] `HorizonConfigTest`
- [x] `QueueMonitorScheduleTest`
- [x] `MediaPipelineJobsTest`
- [x] `PublicUploadTest`
- [x] regressao ampliada das areas tocadas: `176 passed`, `1246 assertions`

Criterio de aceite:

- o fluxo permanece rapido para o usuario;
- retries e duplicacoes nao geram estado corrompido;
- throughput e gargalos ficam visiveis de forma objetiva.

## IA-MOD-R9 - Criar historico operacional explicavel

Objetivo:

- trocar feed tecnico cru por explicacao operacional util.

Tarefas:

- criar historico de eventos reais com filtros por:
  - evento
  - periodo
  - preset/politica
  - modelo
  - sucesso ou falha
  - remetente
- exibir por item:
  - miniatura
  - tipo de midia
  - origem
  - `effective_media_state`
  - motivo humano
  - `reason_code`
  - politica ativa
  - heranca do global
  - texto considerado ou nao
- manter drawer tecnico com:
  - prompt resolvido
  - payload
  - schema retornado
  - snapshot da politica

Criterio de aceite:

- operador comum entende o comportamento da IA sem abrir payload cru;
- auditoria tecnica continua possivel quando necessario.

## IA-MOD-R10 - Criar laboratorio e testes automatizados

Objetivo:

- validar politica antes de publicar e impedir regressao silenciosa.

Tarefas de produto:

- criar simulacao/laboratorio com ate `3` midias para homologar a politica antes de ativar;
- mostrar Safety, contexto, resposta e elegibilidade final lado a lado.

Tarefas backend:

- unit para resolvedor de politica;
- unit para builder contextual;
- unit para calculo de `effective_media_state`;
- feature para scopes separados;
- feature para merge `preset + global + event_override + runtime_override`;
- feature para historico operacional;
- feature para idempotencia e concorrencia de jobs criticos.

Tarefas frontend:

- testes de resumo lateral reativo;
- testes de heranca global/evento;
- testes de presets e toggles basicos;
- testes de filtros do historico;
- testes de modos `Basico`, `Avancado` e `Auditoria`;
- testes de `placeholderData`, prefetch e transicoes sem flicker forte nas trocas principais.

Criterio de aceite:

- baterias backend e frontend verdes cobrindo cenarios centrais;
- laboratorio permite homologar a politica sem contaminar producao.

Critério de aceite:

- baterias backend e frontend verdes cobrindo os cenarios principais.

---

## 12. Recomendacao De Sequencia

1. implementar `IA-MOD-R1`
2. implementar `IA-MOD-R2`
3. implementar `IA-MOD-R3`
4. implementar `IA-MOD-R4`
5. implementar `IA-MOD-R8`
6. implementar `IA-MOD-R5`
7. implementar `IA-MOD-R6`
8. implementar `IA-MOD-R7`
9. implementar `IA-MOD-R9`
10. implementar `IA-MOD-R10`
11. rodar homologacao real com:
    - imagem
    - imagem + texto
    - video
    - sticker
    - audio
12. usar o laboratorio antes de promover politica para producao

---

## 13. Conclusao

O sistema ja tem base suficiente para subir de nivel, mas a fronteira entre as camadas ainda esta frouxa para operacao forte em producao.

Hoje:

- a OpenAI Moderations API cobre a `Safety objetiva`;
- o `VLM` esta cobrindo `bloqueio contextual`;
- o pipeline tecnico ja existe;
- o painel administrativo e o estado consolidado ainda apresentam isso de forma confusa para o operador.

A decisao correta agora e:

- separar as camadas;
- endurecer o estado consolidado da midia com `effective_media_state`;
- estruturar a politica contextual em banco;
- resolver a politica por `preset + global + event_override + runtime_override`;
- separar scopes por camada e normalizar o contexto textual;
- enriquecer o schema contextual com motivo, confianca e elegibilidade de publicacao;
- mover a administracao principal para `IA > Moderacao de midia`;
- manter o detalhe do evento como resumo operacional e override simples;
- endurecer filas, locks, idempotencia e observabilidade antes de escalar infraestrutura.

Leitura final:

- isso preserva custo-beneficio porque reaproveita a stack atual em vez de reinventar o pipeline;
- isso preserva performance porque foca primeiro em fila, estado, cache, prefetch e UI reativa;
- isso preserva seguranca porque separa melhor as decisoes e deixa a auditoria realmente explicavel.

## Fontes Oficiais Validadas

- OpenAI Moderation guide:
  - `https://developers.openai.com/api/docs/guides/moderation#quickstart`
- OpenAI moderation content classifications:
  - `https://developers.openai.com/api/docs/guides/moderation#content-classifications`
- OpenAI structured outputs:
  - `https://developers.openai.com/api/docs/guides/structured-outputs#structured-outputs-vs-json-mode`
- Laravel Queues:
  - `https://laravel.com/docs/12.x/queues`
- Laravel Horizon:
  - `https://laravel.com/docs/12.x/horizon`
- TanStack Query React docs:
  - `https://tanstack.com/query/latest/docs/framework/react/`
- React Hook Form docs:
  - `https://react-hook-form.com/docs/useform`
  - `https://react-hook-form.com/docs/useformcontext`
  - `https://react-hook-form.com/docs/usefieldarray`
  - `https://react-hook-form.com/docs/usewatch`
