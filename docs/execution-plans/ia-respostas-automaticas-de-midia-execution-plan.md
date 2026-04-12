# IA Respostas Automaticas De Midia Execution Plan

## Objetivo

Esta doc fecha a proxima frente real do produto para respostas automaticas baseadas em imagem.

O foco aqui e:

- tirar essa configuracao de `Settings`;
- criar uma area propria no painel para IA;
- suportar teste do prompt com ate `3` imagens apenas no ambiente de teste;
- manter o pipeline produtivo simples, assincrono e com `1` imagem por midia;
- elevar observabilidade para homologacao real com WhatsApp e providers externos.

Esta doc complementa:

- `docs/execution-plans/photo-processing-ai-hardening-execution-plan.md`
- `docs/execution-plans/whatsapp-ai-media-reply-real-validation-execution-plan.md`

---

## O Que Realmente E Importante

### 1. Tunnel publico e blocker operacional imediato

Estado validado localmente:

- URL publica configurada:
  - `https://webhooks-local.eventovivo.com.br/api/v1/webhooks/whatsapp/zapi/3BDB98A79042D03232CC1ABE514C6FD4/inbound`
- existe state file local em:
  - `%USERPROFILE%\\.cloudflared\\eventovivo-local-webhooks.state.json`
- existem scripts oficiais do projeto:
  - `scripts/ops/start-cloudflare-named-webhook-tunnel.ps1`
  - `scripts/ops/setup-cloudflare-named-webhook-tunnel.ps1`
- o state file aponta para:
  - `http://localhost:8000`

Revalidacao operacional executada em `2026-04-08`:

- `http://127.0.0.1:8000/up` respondeu `200`
- o named tunnel foi reativado com:
  - `powershell -ExecutionPolicy Bypass -File scripts/ops/start-cloudflare-named-webhook-tunnel.ps1`
- o state file foi atualizado para:
  - `child_process_id = 24760`
- `https://webhooks-local.eventovivo.com.br/up` respondeu `200`
- um `POST` sintetico no endpoint publico inbound respondeu:
  - `200 {"status":"received"}`
- o hostname fixo do webhook voltou a responder pelo tunnel nomeado sem `530` nem `1033`

Leitura:

- o blocker inicial nao era credencial de Cloudflare;
- o blocker real era tunnel parado + necessidade de origem local saudavel;
- nesta rodada o tunnel ficou funcional;
- para homologacao real, a fila `whatsapp-inbound` precisa continuar consumindo jobs.

### 2. A configuracao de IA nao deve ficar em `Settings`

Estado atual validado:

- a configuracao principal agora esta em:
  - [MediaAutomaticRepliesPage.tsx](C:/laragon/www/eventovivo/apps/web/src/modules/ai/MediaAutomaticRepliesPage.tsx)
- a `SettingsPage` deixou de expor essa feature ao usuario
- as rotas dedicadas da nova area sao:
  - `GET /api/v1/ia/respostas-de-midia/configuracao`
  - `PATCH /api/v1/ia/respostas-de-midia/configuracao`

Leitura:

- isso foi aceitavel para fechar backend inicial;
- nao e um bom contrato de produto;
- essa funcionalidade ja virou area propria do sistema.

### 3. O front precisa parar de expor termos em ingles

Estado atual validado:

- a area `IA > Respostas automaticas de midia` ja ficou 100% em portugues
- o fluxo visivel da feature nao expoe mais:
  - `reply_text`
  - `override`
  - `playground`
  - `enrich_only`
  - `gate`

Leitura:

- no backend, nomes internos podem ficar por enquanto;
- no front, essa feature precisa ficar 100% em portugues.

### 4. O teste com ate 3 imagens faz sentido, mas so no ambiente de teste

Estado atual validado:

- o contrato produtivo atual do `MediaIntelligence` usa `1` imagem por midia;
- o normalizador atual:
  - [OpenAiCompatibleMultimodalPayloadNormalizer.php](C:/laragon/www/eventovivo/apps/api/app/Modules/MediaIntelligence/Services/OpenAiCompatibleMultimodalPayloadNormalizer.php)
- ele mantem apenas a primeira imagem do payload.

Leitura:

- isso e bom para o pipeline produtivo atual;
- o teste com `1..3` imagens deve nascer em rota/servico proprio;
- nao vale contaminar o fluxo operacional do WhatsApp agora.

### 5. Prompt limpo e variavel oficial precisam ser regra de backend

Decisoes:

- variavel oficial inicial:
  - `{nome_do_evento}`
- a resolucao da variavel acontece no backend;
- o log precisa guardar:
  - template original
  - variaveis resolvidas
  - prompt efetivo enviado

Leitura:

- regra da aplicacao nao deve ir no prompt;
- a IA precisa receber so a tarefa visual.

### 6. Historico de testes precisa ir para banco

Leitura:

- log em arquivo sozinho nao basta;
- o produto vai precisar reabrir testes, comparar prompts e diagnosticar regressao;
- aqui uma tabela dedicada se justifica.

### 7. Presets merecem catalogo proprio

Leitura:

- diferente de textos fixos por evento, presets sao reaproveitaveis;
- o produto quer categorias como:
  - casamentos
  - corporativos
  - 15 anos
  - jovem moderno
- isso vira catalogo administravel.
- o preset funciona como base da instrucao do prompt;
- quando um preset e selecionado no teste do prompt, o texto de instrucao e preenchido automaticamente;
- o catalogo agora fica em aba propria, separado do historico.

---

## Decisoes Fechadas

1. criar uma area propria no painel:
   - `IA > Respostas automaticas de midia`
2. remover essa configuracao da `SettingsPage`
3. manter nomes internos atuais no backend nesta fase
4. no front, nao mostrar `reply_text`, `override` nem `playground`
5. suportar `{nome_do_evento}` como variavel oficial inicial
6. limpar o prompt enviado ao modelo
7. teste do prompt aceita `1..3` imagens
8. pipeline produtivo continua com `1` imagem por midia
9. teste do prompt executa de forma sincrona
10. pipeline real continua assincrono em fila
11. todo teste de prompt fica salvo em banco e em canal de log dedicado
12. presets ficam em catalogo proprio
13. a homologacao real via WhatsApp depende de tunnel publico e origin local saudaveis

---

## Impacto No Banco

### `event_media_intelligence_settings`

Adicionar:

- `reply_text_mode`
- `reply_fixed_templates_json`
- `reply_prompt_preset_id`

### `media_intelligence_global_settings`

Adicionar:

- `reply_text_fixed_templates_json`
- `reply_prompt_preset_id`

### nova `ai_media_reply_prompt_presets`

Campos sugeridos:

- `id`
- `slug`
- `name`
- `category`
- `description`
- `prompt_template`
- `sort_order`
- `is_active`
- `created_by`
- timestamps

### nova `ai_media_reply_test_runs`

Campos sugeridos:

- `id`
- `trace_id`
- `user_id`
- `event_id`
- `provider_key`
- `model_key`
- `status`
- `prompt_template`
- `prompt_resolved`
- `prompt_variables_json`
- `images_json`
- `request_payload_json`
- `response_payload_json`
- `response_text`
- `latency_ms`
- `error_message`
- timestamps

### `event_media_safety_evaluations`

Adicionar:

- `request_payload_json`

### `event_media_vlm_evaluations`

Adicionar:

- `request_payload_json`

### `whatsapp_message_feedbacks` e `telegram_message_feedbacks`

Adicionar:

- `resolution_json`

---

## Impacto No Backend

### Area de IA dedicada

Continuar dentro do modulo `MediaIntelligence`.

Rotas novas recomendadas:

- `GET /api/v1/ia/respostas-de-midia/configuracao`
- `PATCH /api/v1/ia/respostas-de-midia/configuracao`
- `GET /api/v1/ia/respostas-de-midia/presets`
- `POST /api/v1/ia/respostas-de-midia/presets`
- `PATCH /api/v1/ia/respostas-de-midia/presets/{preset}`
- `DELETE /api/v1/ia/respostas-de-midia/presets/{preset}`
- `POST /api/v1/ia/respostas-de-midia/testes`
- `GET /api/v1/ia/respostas-de-midia/testes`
- `GET /api/v1/ia/respostas-de-midia/testes/{id}`

### Producao

Evoluir:

- `ReplyTextMode`
- `ReplyTextConfigResolver`
- `FixedReplyTextSelector`
- `MediaReplyPromptTemplateRenderer`
- `MediaReplyPromptVariablesResolver`

Arquivos centrais:

- `EventMediaIntelligenceSetting.php`
- `MediaIntelligenceGlobalSetting.php`
- `UpsertEventMediaIntelligenceSettingsAction.php`
- `UpsertMediaIntelligenceGlobalSettingsAction.php`
- `MediaReplyTextPromptResolver.php`
- `PublishedMediaReplyTextResolver.php`
- `PublishedMediaAiReplyDispatcher.php`
- `EvaluateMediaPromptJob.php`

### Teste do prompt

Criar trilha propria e sincrona:

- `MediaReplyPromptTestController`
- `MediaReplyPromptTestService`
- `MediaReplyPromptTestRun`
- `MediaReplyPromptPreset`

Decisao importante:

- nao reutilizar diretamente o normalizador produtivo para multiplas imagens;
- criar uma trilha separada para o teste do prompt;
- o normalizador produtivo continua congelado em `1` imagem.

### Observabilidade

Criar:

- canal `ai-media-reply-tests`
- canal `ai-pipeline`

Propagar:

- `trace_id`
- `event_id`
- `media_id`
- `provider_key`
- `model_key`
- `latency_ms`
- `path_used`

### Tunnel / webhook

Antes da homologacao real:

- garantir `cloudflared` ativo no named tunnel
- garantir origin local respondendo em `localhost:8000`
- garantir health publico verde em `/up`

Scripts oficiais:

- `scripts/ops/start-cloudflare-named-webhook-tunnel.ps1`
- `scripts/ops/setup-cloudflare-named-webhook-tunnel.ps1`

---

## Impacto No Frontend

### Navegacao

Criar area propria:

- `IA`
- pagina: `Respostas automaticas de midia`

Abas:

- `Configuracao`
- `Teste do prompt`
- `Historico`

Arquivos centrais:

- `apps/web/src/App.tsx`
- `apps/web/src/app/routing/route-preload.ts`
- novo modulo em `apps/web/src/modules/ia/`

### Configuracao

Remover da `SettingsPage`:

- prompt global de IA para resposta automatica

Criar tela propria com:

- instrucao padrao
- modo padrao
- textos fixos padrao
- presets
- variaveis disponiveis

### Evento

No form do evento:

- labels em portugues:
  - `Resposta automatica`
  - `Texto de instrucao do evento`
  - `Textos fixos`
  - `Preset`

Arquivo central:

- `apps/web/src/modules/events/components/media-intelligence/EventMediaIntelligenceSettingsForm.tsx`

### Teste do prompt

Tela com:

- selecao de evento opcional
- upload de `1..3` imagens
- escolha de provider/modelo
- preset opcional
- texto de instrucao
- visualizacao do:
  - prompt efetivo
  - resposta gerada
  - tempo de resposta
  - provider/modelo
  - status
  - payload tecnico

### Historico

Tela com:

- lista de testes anteriores
- filtros por:
  - evento
  - provider
  - status
  - data
- detalhe do teste com:
  - imagens
  - prompt template
  - variaveis
  - prompt efetivo
  - payload request/response

---

## Contrato De Prompt

Prompt base recomendado:

`Gere uma resposta curta em portugues do Brasil, natural, calorosa e coerente com o que aparece na imagem. Use no maximo 2 emojis quando fizer sentido. Nao use hashtags, aspas, frases longas nem invente detalhes. Voce pode mencionar {nome_do_evento} de forma natural apenas se isso combinar com a cena. Se a imagem nao trouxer contexto suficiente para uma resposta segura e adequada, retorne vazio.`

Regras:

- nao mencionar estado da feature
- nao dizer `quando reply_text estiver habilitado`
- nao misturar regra de aplicacao com instrucao de modelo

---

## Presets De Prompt

Catalogo inicial recomendado:

- `Casamentos`
- `Corporativos`
- `15 anos`
- `Jovem moderno`
- `Infantil`
- `Neutro padrao`

Cada preset precisa ter:

- nome
- categoria
- descricao curta
- template de prompt
- ativo/inativo

---

## Fases E Tarefas

### Fase T0 - Tunnel e prontidao operacional

- [x] `T0-T1` validar `localhost:8000/up` verde localmente
- [x] `T0-T2` reativar o named tunnel com `scripts/ops/start-cloudflare-named-webhook-tunnel.ps1`
- [x] `T0-T3` validar `https://webhooks-local.eventovivo.com.br/up` sem `530`
- [x] `T0-T4` validar inbound publico chegando ao backend e persistindo em `whatsapp_inbound_events`
- [x] `T0-T5` registrar comando/owner do tunnel no runbook operacional

### Fase IA-RM-01 - Area dedicada de IA

- [x] `IA-RM-01-T1` criar rota/pagina `IA > Respostas automaticas de midia`
- [x] `IA-RM-01-T2` remover configuracao dessa feature da `SettingsPage`
- [x] `IA-RM-01-T3` mover APIs do front para modulo proprio
- [x] `IA-RM-01-T4` manter compatibilidade backend com rotas atuais durante transicao

Status desta rodada:

- a rota `/ia/respostas-de-midia` foi criada e protegida por `settings.manage`
- a navegacao lateral agora expoe `IA` como entrada propria
- a configuracao global deixou de ficar acessivel pela `SettingsPage`
- o front novo usa modulo proprio `apps/web/src/modules/ai`
- o backend continua usando as rotas atuais de `media-intelligence/global-settings` durante a transicao

### Fase IA-RM-02 - Nomenclatura em portugues

- [x] `IA-RM-02-T1` trocar labels visiveis de `reply_text`
- [x] `IA-RM-02-T2` trocar labels visiveis de `override`
- [x] `IA-RM-02-T3` trocar labels visiveis de `playground`
- [x] `IA-RM-02-T4` trocar labels visiveis de `Enrich only` e `Gate` na area de IA
- [x] `IA-RM-02-T5` validar ausencia de termos em ingles no fluxo dessa feature

Status desta rodada:

- `Reply por IA` virou `Resposta automatica por IA`
- `Prompt de reply_text do evento` virou `Texto de instrucao do evento`
- `Contrato do reply_text` virou `Contrato da resposta automatica`
- `Enrich only` virou `Apenas enriquecer`
- `Gate` virou `Bloquear`
- a nova area dedicada usa `Teste do prompt` e `Historico` em vez de termos em ingles

### Fase IA-RM-03 - Variaveis e prompt limpo

- [x] `IA-RM-03-T1` implementar renderer de variaveis com `{nome_do_evento}`
- [x] `IA-RM-03-T2` limpar o prompt produtivo enviado ao provider
- [x] `IA-RM-03-T3` persistir template, variaveis e prompt efetivo nos logs certos
- [x] `IA-RM-03-T4` mostrar `Variaveis disponiveis` na UI

Status desta rodada:

- `{nome_do_evento}` passou a ser resolvida no backend por `MediaReplyTextPromptResolver`
- o prompt padrao deixou de mencionar estado interno da feature
- o payload produtivo deixou de mandar instrucoes como `Quando reply_text estiver habilitado`
- a UI dedicada passou a mostrar explicitamente `Variaveis disponiveis`
- `event_media_vlm_evaluations` agora persiste `request_payload_json` e `prompt_context_json`
- o detalhe da midia agora expoe `request_payload` e `prompt_context` da ultima avaliacao VLM
- a bateria backend que cobre `MediaIntelligence`, `EventMediaList`, `WhatsApp` e `Telegram` passou com `65` testes e `450` assertions

### Fase IA-RM-04 - Modo de resposta automatica

- [x] `IA-RM-04-T1` criar enum `ReplyTextMode`
- [x] `IA-RM-04-T2` migrar `reply_text_enabled` para modo explicito
- [x] `IA-RM-04-T3` adicionar textos fixos globais
- [x] `IA-RM-04-T4` adicionar textos fixos por evento
- [x] `IA-RM-04-T5` criar seletor deterministico de texto fixo
- [x] `IA-RM-04-T6` adaptar dispatch de WhatsApp/Telegram para registrar `resolution_json`

Status desta rodada:

- `EventMediaIntelligenceSetting` agora resolve `disabled|ai|fixed_random` sem depender do boolean visivel no front
- o backend continua aceitando `reply_text_enabled` por compatibilidade, mas o contrato novo do painel usa `reply_text_mode`
- a area `IA > Respostas automaticas de midia` agora salva:
  - `instrucao padrao`
  - `textos fixos padrao`
- o formulario do evento agora salva:
  - `tipo de resposta automatica`
  - `texto de instrucao do evento`
  - `textos fixos do evento`
- `PublishedMediaReplyTextResolver` agora devolve contexto de resolucao com:
  - `mode`
  - `source`
  - `reply_text`
  - `template`
  - `variables`
  - `evaluation_id`
- `whatsapp_message_feedbacks` e `telegram_message_feedbacks` agora persistem `resolution_json`
- os fluxos publicados de WhatsApp e Telegram foram revalidados com `resolution_json`
- a bateria do front passou com:
  - `8` testes
  - `type-check` verde
  - observacao operacional:
    - no Windows, o `Vitest` precisou rodar sem sandbox por `spawn EPERM` do `esbuild`

### Fase IA-RM-05 - Presets

- [x] `IA-RM-05-T1` criar tabela `ai_media_reply_prompt_presets`
- [x] `IA-RM-05-T2` criar CRUD backend de presets
- [x] `IA-RM-05-T3` criar CRUD frontend de presets
- [x] `IA-RM-05-T4` permitir preset global padrao
- [x] `IA-RM-05-T5` permitir preset por evento

Status desta rodada:

- foi criada a tabela `ai_media_reply_prompt_presets`
- foi criada tambem a tabela `ai_media_reply_prompt_categories`
- o backend agora expoe CRUD administrativo dedicado de:
  - categorias
  - presets
- a area `IA > Respostas automaticas de midia` ganhou a aba `Catalogo`
- a configuracao global agora aceita `preset padrao`
- o evento agora aceita `preset do evento`
- o teste do prompt agora preenche automaticamente o `texto de instrucao` quando um preset e selecionado
- categorias padrao semeadas:
  - `Casamento`
  - `15 anos`
  - `Corporativo`
  - `Festas`
- presets iniciais semeados:
  - `Casamento romantico`
  - `15 anos com brilho`
  - `Corporativo acolhedor`
  - `Festa jovem moderna`
- a precedencia de estilo passou a respeitar:
  - preset do evento
  - preset global
  - instrucao do evento
  - instrucao padrao

### Fase IA-RM-06 - Teste do prompt

- [x] `IA-RM-06-T1` criar endpoint sincrono de teste
- [x] `IA-RM-06-T2` aceitar `1..3` imagens no teste
- [x] `IA-RM-06-T3` criar payload factory proprio para multiplas imagens
- [x] `IA-RM-06-T4` manter pipeline produtivo congelado em `1` imagem
- [x] `IA-RM-06-T5` retornar resposta, prompt efetivo, provider, modelo, latencia e dados tecnicos

Status desta rodada:

- o backend agora expoe `POST /api/v1/ia/respostas-de-midia/testes`
- a validacao aceita `1..3` imagens com regras de arquivo por item
- foi criada uma payload factory propria para o teste, sem contaminar o fluxo produtivo
- o pipeline produtivo continua congelado em `1` imagem por midia
- a tela `Teste do prompt` agora mostra:
  - resposta gerada
  - prompt efetivo
  - provider
  - modelo
  - latencia
  - payload enviado
  - payload recebido

### Fase IA-RM-07 - Historico de testes e logs

- [x] `IA-RM-07-T1` criar tabela `ai_media_reply_test_runs`
- [x] `IA-RM-07-T2` criar canal `ai-media-reply-tests`
- [x] `IA-RM-07-T3` salvar request/response completos do teste
- [x] `IA-RM-07-T4` salvar `trace_id` em cada teste
- [x] `IA-RM-07-T5` criar listagem e detalhe de historico no painel

Status desta rodada:

- foi criada a tabela `ai_media_reply_test_runs`
- cada execucao de teste agora persiste:
  - `trace_id`
  - contexto do prompt
  - imagens
  - request payload
  - response payload
  - resposta final
  - latencia
  - erro
- foi criado o canal dedicado `ai-media-reply-tests`
- a aba `Historico` agora lista e abre o detalhe de cada teste salvo

### Fase IA-RM-08 - Observabilidade do pipeline produtivo

- [x] `IA-RM-08-T1` adicionar `request_payload_json` em safety
- [x] `IA-RM-08-T2` adicionar `request_payload_json` em VLM
- [x] `IA-RM-08-T3` propagar `trace_id` do webhook ate feedback outbound
- [x] `IA-RM-08-T4` criar endpoint de debug agregado por midia
- [x] `IA-RM-08-T5` ampliar resources protegidas de log

Status desta rodada:

- `event_media_safety_evaluations` passou a persistir `request_payload_json`
- `event_media_vlm_evaluations` ja persiste:
  - `prompt_context_json`
  - `request_payload_json`
  - `raw_response_json`
- `trace_id` foi propagado no codigo para:
  - `whatsapp_inbound_events`
  - `channel_webhook_logs`
  - `inbound_messages`
  - `whatsapp_message_feedbacks`
  - `telegram_message_feedbacks`
- foi criado o endpoint protegido:
  - `GET /api/v1/media/{eventMedia}/ia-debug`
- o detalhe agregado da midia agora expoe:
  - inbound message
  - webhook logs
  - safety request/response
  - VLM prompt context + request/response
  - feedbacks de WhatsApp/Telegram
  - dispatch logs do WhatsApp
- a observabilidade ficou ancorada por testes automatizados de `MediaProcessing`, `WhatsApp` e `Telegram`

### Fase IA-RM-09 - Homologacao real

- [x] `IA-RM-09-T1` ativar evento real de teste com moderacao por IA
- [x] `IA-RM-09-T2` ativar resposta automatica por IA no evento
- [x] `IA-RM-09-T3` validar intake por grupo
- [x] `IA-RM-09-T4` validar intake por DM
- [x] `IA-RM-09-T5` repetir homologacao com resposta fixa
- [x] `IA-RM-09-T6` consolidar artefatos e checklist final

Status desta rodada:

- o evento real de homologacao local ficou ativado:
  - `event_id = 31`
  - `title = Homologacao WhatsApp Z-API Local`
- codigos operacionais atuais:
  - grupo: `GRUPO-EVIVO-TESTE`
  - DM: `FOTOS-EVIVO-TESTE`
- o canal de grupo ja esta vinculado localmente com:
  - `group_external_id = 120363425796926861-group`
- a rodada foi validada em dois estados do evento:
  - primeiro com `MediaIntelligence.reply_text_mode = ai` para validar DM e grupo com resposta automatica por IA
  - depois com `MediaIntelligence.reply_text_mode = fixed_random` para validar a resposta fixa no proprio grupo
- apos a validacao manual, o evento local `31` foi restaurado para:
  - `MediaIntelligence.reply_text_mode = ai`
  - objetivo:
    - manter os proximos testes reais de imagem no estado principal do produto
    - preservar a evidÃªncia de video/fixo nos feedbacks ja gravados
- a configuracao real do evento, no momento da homologacao por IA, ficou assim:
  - `ContentModeration.enabled = true`
  - `ContentModeration.provider_key = openai`
  - `ContentModeration.mode = enforced`
  - `MediaIntelligence.enabled = true`
  - `MediaIntelligence.provider_key = openrouter`
  - `MediaIntelligence.model_key = openai/gpt-4.1-mini`
  - `MediaIntelligence.mode = enrich_only`
  - `MediaIntelligence.reply_text_mode = ai`
  - `MediaIntelligence.reply_prompt_preset_id = 4`
- depois da validacao por IA, a configuracao global ficou endurecida para a rodada de resposta fixa:
  - `reply_text_fixed_templates_json = ["Momento de risadas e lembrancas! ðŸ“±ðŸŽ‰", "Memorias que fazem o coracao sorrir! ðŸŽ‰ðŸ“¸", "Paz, amor e um sorriso! âœŒï¸ðŸ˜Š"]`
  - `reply_ai_rate_limit_enabled = true`
  - `reply_ai_rate_limit_max_messages = 10`
  - `reply_ai_rate_limit_window_minutes = 10`
- validacoes operacionais reexecutadas nesta rodada:
  - `content-moderation:smoke-openai` verde
  - `media-intelligence:smoke-openrouter` verde
  - endpoint publico inbound respondeu `200`
- o gap real encontrado nesta rodada nao era o webhook nem o bind do grupo:
  - o webhook publico estava chegando
  - o grupo `Evento vivo 1` ja estava vinculado ao `event_id = 31`
  - o problema operacional era backlog sem worker local persistente para a cadeia:
    - `whatsapp-inbound`
    - `webhooks`
    - `media-download`
    - `media-fast`
    - `media-safety`
    - `media-vlm`
    - `media-publish`
    - `whatsapp-send`
- o worker local persistente foi ativado com:
  - `php artisan queue:work redis --queue=whatsapp-inbound,webhooks,media-download,media-fast,media-safety,media-vlm,media-publish,whatsapp-send --tries=3 --timeout=180 --sleep=1 --memory=512`
  - `PID = 25384`
- evidencias reais do grupo `Evento vivo 1` nesta rodada:
  - inbound real por grupo persistido em `inbound_messages`
  - `event_media` reais publicadas:
    - `11340`
    - `11341`
    - `11342`
    - `11343`
  - moderacao real:
    - `safety_status = pass`
    - `moderation_status = approved`
    - `publication_status = published`
  - feedbacks reais persistidos:
    - `reaction` fase `detected`
    - `reaction` fase `published`
    - `reply` fase `published`
  - dispatches reais `sendText` com `HTTP 200`:
    - `messageId = 2ABEC0628986BD04BDE6`
    - `messageId = 2A7CDACFED15CD33D159`
- a resposta automatica de IA ficou efetivamente confirmada no grupo com `resolution_json` preenchido, incluindo:
  - `mode = ai`
  - `source = vlm`
  - `variables.nome_do_evento = Homologacao WhatsApp Z-API Local`
- evidencias reais de DM nesta rodada:
  - midias `11344`, `11345` e `11347`
  - todas com:
    - `source_type = whatsapp_direct`
    - `safety_status = pass`
    - `moderation_status = approved`
    - `publication_status = published`
  - feedbacks publicados persistidos:
    - `56`, `58`, `60` para `reply`
    - textos enviados:
      - `Um momento doce e especial para celebrar! ðŸŽ‰`
      - `Um momento especial de celebracao e alegria! ðŸŽ‰`
      - `Um toque de conforto para o dia a dia.`
  - dispatches reais `sendText` em DM com `HTTP 200` e `privateAnswer = false`
- observacao importante de DM:
  - a midia `11346` expunha um bug real separado do fluxo de imagem
  - causa raiz validada:
    - o payload do Z-API trazia `video` corretamente
    - mas tambem trazia `photo` escalar de avatar do remetente
    - o normalizador canonico acabava salvando `message_type = photo`
    - depois o pipeline tentava rodar `variants` de imagem em um arquivo `video/mp4`
  - endurecimento aplicado:
    - o roteador de WhatsApp agora injeta `message_type` e `mime_type` explicitos no payload encaminhado
    - `NormalizeInboundMessageJob` deixou de tratar `photo` escalar como midia valida
    - `DownloadInboundMediaJob` agora infere `media_type = video` pelo `mime_type`
    - videos nao passam mais pela trilha de `variants`, `safety` nem `vlm`
    - video segue para moderacao/publicacao com `safety_status = skipped` e `vlm_status = skipped`
  - validacao real do legado `11346` apos o fix:
    - `media_type = video`
    - `mime_type = video/mp4`
    - `processing_status = processed`
    - `moderation_status = approved`
    - `publication_status = published`
    - `safety_status = skipped`
    - `vlm_status = skipped`
  - feedbacks reais persistidos para `11346`:
    - `63` reacao `published`
    - `64` reply `published`
  - resposta textual validada nesta rodada:
    - `Momento de risadas e lembrancas! ðŸ“±ðŸŽ‰`
    - origem:
      - `mode = fixed_random`
      - `source = global_fixed_template`
  - dispatches reais `HTTP 200`:
    - `send-reaction` para `messageId = 2A21968460B09237C28F`
    - `send-text` para `messageId = 2A21968460B09237C28F`
  - regra endurecida de produto:
    - video nao usa resposta por IA
    - quando o modo do evento for `ai`, video fica sem texto e envia apenas reacao
    - quando o modo do evento for `fixed_random`, video pode responder com texto fixo
- comportamento atual validado do produto em `whatsapp_group`:
  - a frase automatica aparece no proprio grupo
  - o envio usa `send-text` com `privateAnswer = false`
  - no grupo, o comportamento visivel fica:
    - reacao `detected`
    - reacao `published`
    - resposta textual encadeada ao `messageId`
- homologacao real com resposta fixa tambem foi confirmada:
  - a midia `11343` foi reprocessada em `2026-04-08 06:46`
  - feedbacks novos persistidos:
    - `61` reacao `published`
    - `62` reply `published`
  - `resolution_json` final:
    - `mode = fixed_random`
    - `source = global_fixed_template`
    - `reply_text = Paz, amor e um sorriso! âœŒï¸ðŸ˜Š`
  - dispatch real no grupo com `HTTP 200`:
    - `messageId = 2A1F381C0980872C1936`
    - `privateAnswer = false`
    - `message = Paz, amor e um sorriso! âœŒï¸ðŸ˜Š`
- local exato para alterar textos fixos:
  - global:
    - `IA > Respostas automaticas de midia > Configuracao > Textos fixos padrao`
  - por evento:
    - pagina do evento > `IA da midia` > `Resposta automatica = Texto fixo aleatorio`
    - campo `Textos fixos`
- endurecimento adicional de intake DM aplicado apos a homologacao:
  - dois videos reais recebidos em `2026-04-08 13:43` chegaram ao webhook Z-API e foram persistidos como mensagens `619` e `621`
  - eles nao geraram `reaction detected`, `reaction published` nem `reply` porque a sessao DM do remetente ja estava expirada
  - causa raiz validada:
    - os webhooks entraram normalmente
    - nao houve `Routing inbound media to gallery pipeline`
    - o contexto do evento nao foi resolvido porque a janela da sessao direta ja tinha acabado
  - endurecimento aplicado:
    - `RouteInboundToMediaPipeline` agora registra `warning` explicito quando a midia direta chega sem sessao ativa
    - `WhatsAppDirectIntakeSessionService` agora responde com instrucao de reativacao do codigo do evento
    - o aviso de reativacao tem cooldown de `5` minutos para nao spammar o participante em burst
  - runbook operacional validado:
    - apos aplicar o fix, o worker local foi reiniciado para carregar o codigo novo
    - o worker atual ficou ativo em background apos o `queue:restart`
- endurecimento adicional aplicado nesta rodada:
  - o limitador de resposta automatica por IA ficou ativo globalmente em:
    - `10` respostas
    - janela de `10` minutos
  - configuracao exposta em:
    - `IA > Respostas automaticas de midia > Configuracao > Limite de respostas por IA por participante`
  - nova validacao operacional do telefone final `483594`:
    - imagem real em DM continuou entrando ponta a ponta
    - o sticker real recebido na mesma rodada mostrou um gap de produto:
      - ele ainda estava indo para o pipeline de galeria antes do fix
    - endurecimento aplicado e testado:
      - `RouteInboundToMediaPipeline` agora ignora `messageType = sticker` para intake de galeria
      - sticker deixa de gerar midia de evento, reacoes de publicacao e resposta automatica de galeria
  - endurecimento de sessao ativa:
    - se o mesmo codigo do evento for reenviado com a sessao ainda ativa
    - o sistema agora responde informando que o remetente ja esta vinculado ao evento
    - a sessao e renovada sem fechar contexto nem criar ambiguidade operacional

---

## Bateria De Testes Minima

### Backend unit

- [x] renderer de variaveis
- [x] seletor deterministico de texto fixo
- [x] precedencia global vs evento vs preset
- [x] payload factory de teste com `1..3` imagens

### Backend feature

- [x] CRUD de presets
- [x] configuracao global da area de IA
- [x] configuracao por evento
- [x] endpoint de teste com limite de imagens
- [x] historico de testes
- [x] `resolution_json` em WhatsApp/Telegram
- [x] smoke real OpenAI segue verde
- [x] smoke real OpenRouter segue verde

### Frontend

- [x] pagina `IA > Respostas automaticas de midia`
- [x] labels 100% em portugues
- [x] teste do prompt com ate `3` imagens
- [x] historico de testes
- [x] historico de eventos reais com filtros operacionais
- [x] remocao da configuracao de `Settings`
- [x] aba `Catalogo` com CRUD de categorias e presets
- [x] selecao de preset preenchendo o `texto de instrucao`
- [x] filtros do catalogo por categoria e nome/texto da instrucao

### Manual

- [x] validar grupo `#ATIVAR#GRUPO-EVIVO-TESTE`
- [x] validar imagem em grupo com moderacao + resposta automatica
- [x] validar imagem em DM com moderacao + resposta automatica
- [x] validar resposta fixa
- [x] validar video em DM sem passar pela IA
- [x] validar DM expirado com instrucao de reativacao e cooldown
- [x] validar codigo reenviado em sessao ativa com mensagem explicita de vinculo
- [x] endurecer sticker para nao entrar no pipeline de galeria/telao
- [x] endurecer audio para nao entrar em galeria/telao e manter captura vinculada ao evento

---

## Proxima Sequencia Recomendada

1. separar workers por prioridade para reduzir latencia de `reaction detected` e `send-text`
2. manter o limite de respostas por IA sob observacao em rodada real com burst maior de imagens
3. decidir se o limitador fica ativo por padrao em homologacao/producao
4. decidir se o comportamento padrao de video em producao sera:
   - sem texto
   - ou texto fixo
5. consolidar eventual runbook de rollback do modo `fixed_random` para `ai`

Atualizacao apos a rodada atual:

1. `IA-RM-01` ate `IA-RM-09` estao fechadas
2. a homologacao real local ficou comprovada em:
   - grupo com resposta por IA
   - DM com resposta por IA
   - grupo reprocessado com `texto fixo aleatorio`
   - DM de video com resposta nao-IA
3. o limitador de resposta por IA ficou implementado, testado e ativado em `10/10`
4. o painel agora tambem cobre:
   - filtros do catalogo
   - historico de eventos reais
5. sticker deixou de contaminar o intake de galeria/telao
6. reenvio do mesmo codigo em sessao ativa agora devolve mensagem explicita de vinculo ja existente
7. audio passou a ser persistido fora de `EventMedia`, mantendo vinculo ao evento para futura trilha de gravacoes

Resumo de validacao desta rodada:

- backend:
  - `48` testes e `421` assertions na regressao focada de observabilidade e IA
  - `11` testes e `73` assertions na trilha focada de schema/payload/smoke `OpenRouter`
- frontend:
  - `31` testes verdes cobrindo:
    - pagina dedicada de IA
    - formulario do evento
    - remocao de `Settings`
    - navegacao lateral
    - catalogo de categorias/presets
    - filtros do catalogo
    - historico de eventos reais
    - detalhe de execucao real
- `npm run type-check` verde
- `content-moderation:smoke-openai` verde com `omni-moderation-latest`
- `media-intelligence:smoke-openrouter` verde com `openai/gpt-4.1-mini`
- worker local persistente de homologacao ativo e consumindo:
  - `whatsapp-inbound`
  - `webhooks`
  - `media-download`
  - `media-fast`
  - `media-safety`
  - `media-vlm`
  - `media-publish`
  - `whatsapp-send`
- backlog de `whatsapp-inbound` drenado e intake por grupo/DM comprovado em producao local
- worker reiniciado nesta rodada para carregar:
  - mensagem de sessao ativa ja vinculada
  - endurecimento de sticker fora do intake de galeria

## Runbook Operacional Do Tunnel

Comando principal:

- `powershell -ExecutionPolicy Bypass -File scripts/ops/start-cloudflare-named-webhook-tunnel.ps1`

Comando de setup, se o named tunnel precisar ser reprovisionado:

- `powershell -ExecutionPolicy Bypass -File scripts/ops/setup-cloudflare-named-webhook-tunnel.ps1`

Ownership operacional recomendado:

- execucao local: operador da homologacao ou desenvolvedor responsavel pela rodada
- validacao final: quem estiver conduzindo o teste real com Z-API/WhatsApp

Worker local de homologacao, quando a rodada exigir resposta em tempo real:

- `cd apps/api && php artisan queue:work redis --queue=whatsapp-inbound,webhooks,media-download,media-fast,media-safety,media-vlm,media-publish,whatsapp-send --tries=3 --timeout=180 --sleep=1 --memory=512`

---

## Fontes Oficiais Validadas

- Cloudflare Tunnel:
  - https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/do-more-with-tunnels/local-management/create-local-tunnel/
- Laravel validation:
  - https://laravel.com/docs/12.x/validation#validating-arrays
- Laravel logging:
  - https://laravel.com/docs/12.x/logging
- Laravel queues:
  - https://laravel.com/docs/12.x/queues
- Z-API reply message:
  - https://developer.z-api.io/en/message/reply-message
- OpenAI Moderations:
  - https://platform.openai.com/docs/api-reference/moderations/create
