# Plano de Execucao - Telao ao Vivo

## Objetivo

Transformar o modulo `Wall` em um produto de telao social:

- justo por remetente;
- resiliente em runtime;
- configuravel pelo painel;
- observavel em operacao real;
- previsivel em realtime e filas.

Este plano cobre backend, frontend, WebSocket, filas, painel administrativo e rollout.

---

## Estado de Partida

Ja existe no codigo:

- modulo `Wall` formal no backend;
- player publico com `boot`, `state` e realtime;
- manager `/events/:id/wall` com controles basicos;
- contrato compartilhado do wall;
- fila `broadcasts` isolada;
- pipeline `MediaProcessing -> Wall` tipada.

O que ainda falta para o launch do telao social:

- politica adaptativa por volume e fase mais rica;
- priorizacao editorial controlada;
- thresholds operacionais e historico do diagnostico;
- refino de burst por preset e fase;
- reorganizacao final do manager em abas.

## Status em 2026-04-03

Ja entregue:

- `sender_key`, `source_type` e `duplicate_cluster_key` no contrato do wall;
- fairness por remetente, backlog gradual, cooldown, janela e replay adaptativo no player;
- persistencia local, `assetStatus`, Cache API e fallback `stale`;
- presets e configuracao de fila no manager;
- simulacao com `draft settings + fila real atual`;
- heartbeat publico do player com cache, persistencia, fallback e ultimo sync;
- diagnostico agregado + por aparelho no manager;
- canal privado `event.{eventId}.wall` para invalidacao administrativa;
- comandos operacionais de limpar cache, revalidar assets e reinicializar player.

---

## Fase 0 - Contrato e defaults

Objetivo:

- fechar contrato de dados e baseline do produto antes de espalhar logica.

### 0.1 Fechar o contrato de settings do wall

Tarefas:

- definir shape alvo de `settings.visual`, `settings.selector`, `settings.editorial`, `settings.idle` e `settings.runtime`;
- decidir quais campos continuam em colunas existentes e quais migram para JSON;
- alinhar backend, frontend e docs.

Subtarefas:

- [x] definir `selection_mode` e `event_phase`;
- [ ] definir `selector_config_json`, `editorial_config_json`, `idle_config_json` e `runtime_config_json`;
- [x] revisar `ApiWallSettings` e `WallSettingsResource`;
- [ ] documentar os valores default do modo `balanced`.

Critério de aceite:

- existe um shape unico de configuracao aceito por API, resource e frontend.

### 0.2 Fechar o contrato de midia do player

Tarefas:

- enriquecer o payload do wall com campos que a engine precisa.

Subtarefas:

- [x] adicionar `sender_key`;
- [x] adicionar `source_type`;
- [ ] adicionar `highlight_score` opcional;
- [x] adicionar `duplicate_cluster_key` opcional;
- [ ] decidir se `published_sequence` entra agora ou depois;
- [ ] alinhar `packages/shared-types`, backend e frontend.

Critério de aceite:

- o player consegue decidir fairness sem inferencia fraca de `sender_name`.

### 0.3 Publicar a politica padrao de produto

Tarefas:

- transformar a politica recomendada do doc em default real do sistema.

Subtarefas:

- [ ] fechar default social de `balanced`;
- [ ] fechar limites de burst e replay;
- [ ] fechar frequencia default da lane editorial;
- [ ] registrar guardrails nao-desligaveis no modo simples.

Critério de aceite:

- existe um preset default pronto para uso em evento social sem ajuste fino.

---

## Fase 1 - Backend de configuracao e elegibilidade

Objetivo:

- endurecer o backend do `Wall` para suportar o novo modelo.

### 1.1 Evoluir schema do `event_wall_settings`

Tarefas:

- adicionar os novos campos estruturados;
- manter compatibilidade com settings ja existentes.

Subtarefas:

- [x] criar migration para `selection_mode`;
- [x] criar migration para `event_phase`;
- [ ] criar migration para `selector_config_json`;
- [ ] criar migration para `editorial_config_json`;
- [ ] criar migration para `idle_config_json`;
- [ ] criar migration para `runtime_config_json`;
- [x] ajustar `EventWallSetting` com casts;
- [x] ajustar factory do wall.

Critério de aceite:

- o modelo persiste os novos blocos sem quebrar a leitura dos campos antigos.

### 1.2 Criar data objects e validacao

Tarefas:

- validar o novo contrato de forma tipada no modulo `Wall`.

Subtarefas:

- [ ] criar `WallSelectorConfigData`;
- [ ] criar `WallEditorialConfigData`;
- [ ] criar `WallIdleConfigData`;
- [ ] criar `WallRuntimeConfigData`;
- [x] ampliar `UpdateWallSettingsRequest`;
- [ ] criar enums para `selection_mode`, `event_phase`, `fairness_strength`, `burst_strategy` e `featured_priority_level`.

Critério de aceite:

- payload invalido de selector ou runtime falha com erro claro no backend.

### 1.3 Resolver identidade estavel do remetente

Tarefas:

- criar uma regra unica de `sender_key`.

Subtarefas:

- [ ] criar `WallSenderKeyResolver`;
- [ ] usar telefone normalizado para WhatsApp e inbound;
- [ ] usar `user:{id}` para usuario autenticado;
- [ ] definir estrategia para `guest_session_key` no upload publico;
- [ ] definir fallback seguro para `source_label` ou `sender_name`;
- [ ] integrar isso em `WallPayloadFactory`.

Critério de aceite:

- o mesmo remetente gera a mesma chave em ciclos diferentes de publicacao.

### 1.4 Adicionar suporte a cluster de duplicatas leves

Tarefas:

- preparar o backend para reduzir repeticao de fotos muito parecidas.

Subtarefas:

- [ ] criar `WallDuplicateClusterResolver` ou resolver provisoriamente via `duplicate_group_key`;
- [ ] definir `duplicate_cluster_key` no payload do wall quando disponivel;
- [ ] decidir regra minima para fotos sem cluster.

Critério de aceite:

- o player recebe identificador de cluster suficiente para espacamento leve.

---

## Fase 2 - Realtime, WebSocket e filas

Objetivo:

- separar melhor o que e evento publico do player e o que e diagnostico/admin.

### 2.1 Consolidar canais e eventos

Tarefas:

- manter `wall.{wallCode}` para player;
- usar `event.{eventId}.wall` como canal administrativo principal.

Subtarefas:

- [ ] revisar os eventos emitidos no canal publico;
- [x] criar evento `WallDiagnosticsUpdated` para admin;
- [x] ligar o manager ao canal privado `event.{eventId}.wall`;
- [x] remover dependencia do manager no canal publico sempre que possivel.

Critério de aceite:

- o player e o painel recebem eventos adequados ao seu dominio.

### 2.2 Introduzir heartbeat do player

Tarefas:

- coletar saude do runtime sem empurrar telemetria pelo canal publico.

Subtarefas:

- [x] criar endpoint `POST /public/wall/{wallCode}/heartbeat`;
- [x] criar request de heartbeat;
- [x] enviar resumo de runtime pelo player a cada 15 a 30s e em transicoes importantes;
- [x] registrar `ready/loading/error/stale`, ultimo sync e fallback aplicado.

Critério de aceite:

- o backend recebe diagnostico de runtime sem depender de polling manual do admin.

### 2.3 Agregar diagnostico de forma assincrona

Tarefas:

- tirar custo de agregacao do request do heartbeat.

Subtarefas:

- [x] criar job de recalculo agregado (`RecalculateWallDiagnosticsJob`);
- [x] usar fila `analytics` para agregacao;
- [x] criar `WallDiagnosticsService`;
- [x] criar endpoint `GET /events/{event}/wall/diagnostics`;
- [x] expor resumo agregado no resource do wall e em query dedicada.

Critério de aceite:

- o painel mostra diagnostico sem disputar recurso com `broadcasts`.

### 2.4 Endurecer operacao de filas

Tarefas:

- revisar tuning das filas que impactam o wall.

Subtarefas:

- [ ] revisar throughput de `broadcasts`;
- [ ] revisar throughput de `media-process` e `media-publish`;
- [ ] garantir tags de Horizon para `Wall` e heartbeats;
- [x] revisar waits e `maxProcesses` do supervisor `analytics` para o job de diagnostico e limpeza.

Critério de aceite:

- backlog e latencia de wall ficam observaveis e com thresholds definidos.

---

## Fase 3 - Engine do player

Objetivo:

- transformar o player do wall em um runtime real, nao em um carrossel linear.

### 3.1 Refatorar a estrutura do player

Tarefas:

- separar engine e hooks.

Subtarefas:

- [ ] criar `engine/selectors.ts`;
- [ ] criar `engine/reducer.ts`;
- [ ] criar `engine/cache.ts`;
- [ ] criar `engine/storage.ts`;
- [ ] criar `engine/isEligibleNow.ts`;
- [ ] criar `engine/selectBestItemWithinSender.ts`;
- [ ] criar `engine/computeSenderScore.ts`;
- [ ] criar `engine/applyPreset.ts`.

Critério de aceite:

- `useWallEngine.ts` vira orquestrador fino e a matematica principal sai dele.

### 3.2 Implementar `assetStatus`

Tarefas:

- introduzir estados reais de asset.

Subtarefas:

- [ ] adicionar `loading`, `ready`, `error`, `stale` ao runtime;
- [ ] bloquear item nao-`ready` na concorrencia;
- [ ] suportar fallback `stale`;
- [ ] manter item atual se o proximo asset falhar quando configurado.

Critério de aceite:

- o player nao trata "item existente" como "item pronto".

### 3.3 Implementar fairness por remetente

Tarefas:

- aplicar selector justo no wall.

Subtarefas:

- [ ] agrupar por `sender_key`;
- [ ] priorizar remetentes ainda nao exibidos;
- [ ] priorizar quem esta ha mais tempo sem aparecer;
- [ ] evitar o mesmo remetente em sequencia;
- [ ] respeitar cooldown e janela;
- [ ] reaplicar fairness no replay.

Critério de aceite:

- cenario "100 fotos vs 10 fotos" nao gera monopolizacao visivel.

### 3.4 Implementar burst backlog por remetente

Tarefas:

- absorver rajadas sem bloquear o usuario nem deixar dominar a tela.

Subtarefas:

- [ ] limitar elegiveis imediatos por remetente;
- [ ] manter backlog interno por remetente;
- [ ] liberar novos itens conforme o selector consome a lane;
- [ ] parametrizar isso por preset e volume.

Critério de aceite:

- rajadas grandes nao explodem a fila elegivel do player.

### 3.5 Implementar anti-sequencia parecida

Tarefas:

- evitar repeticao visual de varias fotos quase iguais.

Subtarefas:

- [ ] tratar `duplicate_cluster_key` no selector;
- [ ] espaciar itens do mesmo cluster quando houver alternativas;
- [ ] preferir primeiro o melhor item do cluster;
- [ ] reabrir o cluster apenas apos janela minima de diversidade.

Critério de aceite:

- o wall reduz sequencias chatas de fotos quase identicas.

### 3.6 Implementar persistencia local e cache

Tarefas:

- melhorar retomada e operacao em rede degradada.

Subtarefas:

- [x] persistir snapshot minimo em IndexedDB;
- [x] usar Cache API para logo, background e midias;
- [x] implementar prefetch do item atual, proximo e buffer curto;
- [x] expor se cache persistente esta ativo;
- [x] criar acoes de limpar e revalidar cache.

Critério de aceite:

- o wall se recupera melhor de refresh e oscilacao de rede.

---

## Fase 4 - Painel administrativo `/events/:id/wall`

Objetivo:

- dar ao admin dominio real do telao sem despejar complexidade de uma vez.

### 4.1 Reorganizar a pagina em abas

Tarefas:

- sair da tela longa unica e organizar o produto por dominio de decisao.

Subtarefas:

- [ ] criar abas `Geral`, `Fila e Justica`, `Destaques`, `Performance` e `Tela de Espera`;
- [ ] manter operacao do wall em destaque na aba `Geral`;
- [ ] separar visual de comportamento de fila;
- [ ] criar layout mobile-first para a pagina do manager.

Critério de aceite:

- o admin entende onde configurar estado, fila, visual e performance.

### 4.2 Implementar preset-first

Tarefas:

- permitir que o admin configure o wall com seguranca sem entrar no modo avancado.

Subtarefas:

- [x] adicionar selector de preset;
- [x] mostrar descricao curta de cada preset;
- [x] aplicar defaults ao trocar preset;
- [x] criar resumo textual do comportamento;
- [x] diferenciar preset ativo de configuracao personalizada.

Critério de aceite:

- o admin consegue configurar o wall sem entender pesos internos.

### 4.3 Implementar campos de fila e justica

Tarefas:

- expor os controles que realmente definem monopolizacao, replay e burst.

Subtarefas:

- [ ] adicionar `max_consecutive_per_sender`;
- [x] adicionar `sender_cooldown_seconds`;
- [x] adicionar `sender_window_limit`;
- [x] adicionar `sender_window_minutes`;
- [x] adicionar `max_eligible_items_per_sender`;
- [ ] adicionar `prefer_unseen_senders`;
- [ ] adicionar `fairness_strength`;
- [ ] adicionar `replay_enabled`;
- [x] adicionar `max_replays_per_item`;
- [ ] adicionar `min_repeat_interval_minutes`;
- [x] persistir thresholds de replay adaptativo por volume;
- [ ] adicionar `adaptive_replay_enabled`;
- [ ] adicionar `burst_control_enabled`;
- [ ] adicionar `burst_sender_limit`;
- [ ] adicionar `burst_window_minutes`;
- [ ] adicionar `burst_strategy`.

Critério de aceite:

- os controles da fila cobrem justica, replay, burst e recencia.

### 4.4 Implementar simulacao

Tarefas:

- mostrar previsao de comportamento antes de salvar.

Subtarefas:

- [x] criar bloco "Simulacao da ordem provavel";
- [x] integrar `POST /events/{event}/wall/simulate`;
- [x] mostrar ETA de primeira aparicao;
- [x] mostrar risco de monopolizacao;
- [x] mostrar sequencia fake simplificada.

Critério de aceite:

- o admin enxerga o efeito da configuracao sem adivinhar.

### 4.5 Implementar Performance e Diagnostico

Tarefas:

- expor saude do runtime do player.

Subtarefas:

- [x] mostrar ultima sincronizacao;
- [x] mostrar conexao realtime;
- [x] mostrar `ready/loading/error/stale`;
- [x] mostrar cache ativo e persistencia;
- [x] mostrar quota, hit rate e fallback `stale` do cache;
- [x] mostrar fallback aplicado;
- [x] adicionar acoes de limpar cache, revalidar assets e reinicializar engine.

Critério de aceite:

- o operador consegue diagnosticar o wall sem abrir DevTools.

---

## Fase 5 - Presets por fase e adaptacao por volume

Objetivo:

- tornar o wall mais adequado ao fluxo real de evento social.

### 5.1 Presets por fase

Tarefas:

- adicionar nocao de fase do evento ao produto.

Subtarefas:

- [x] adicionar `event_phase = reception | flow | party | closing`;
- [x] mapear defaults por fase;
- [x] expor seletor de fase no painel;
- [x] aplicar comportamento coerente de tempo, fairness e replay por fase.

Critério de aceite:

- o admin consegue adaptar o wall ao momento da festa sem reconfiguracao pesada.

### 5.2 Politica adaptativa por volume

Tarefas:

- ajustar selector e replay conforme o backlog do evento.

Subtarefas:

- [x] definir thresholds de fila baixa, media e alta;
- [ ] endurecer fairness e burst em fila alta;
- [ ] aliviar replay e cooldown em fila baixa;
- [x] refletir isso no resumo da simulacao;
- [x] refletir isso no runtime e nas metricas.

Critério de aceite:

- o wall nao fica injusto com muita fila nem morto com pouca fila.

---

## Fase 6 - Observabilidade, testes e rollout

Objetivo:

- validar o wall em producao com seguranca.

### 6.1 Testes backend

Tarefas:

- cobrir os contratos e agregacoes novas.

Subtarefas:

- [ ] tests de resource e request do wall;
- [ ] tests de `sender_key`;
- [x] tests de heartbeat e diagnostico;
- [x] tests do endpoint de simulacao.

### 6.2 Testes frontend

Tarefas:

- cobrir a engine e o manager novo.

Subtarefas:

- [x] tests de fairness;
- [x] tests de burst backlog;
- [x] tests de anti-sequencia parecida;
- [ ] tests de status visual;
- [x] tests de `assetStatus`;
- [x] tests de presets no painel;
- [x] tests de simulacao renderizada.

### 6.3 Validacao em carga e operacao

Tarefas:

- medir se realtime e filas continuam saudaveis.

Subtarefas:

- [ ] validar backlog de `broadcasts`;
- [ ] validar agregacao em `analytics`;
- [ ] medir tempo de publicacao ate primeira exibicao;
- [ ] medir tempo medio ate primeira aparicao por remetente;
- [ ] medir concentracao de exibicao por remetente.

### 6.4 Rollout seguro

Tarefas:

- reduzir risco no deploy do novo wall.

Subtarefas:

- [ ] ativar via feature flag se necessario;
- [ ] manter fallback para presets basicos;
- [ ] permitir rollback do selector configurado para modo simples;
- [ ] documentar runbook de operacao do wall.

---

## Itens de Baixa Prioridade no Launch Social

Estes itens fazem sentido, mas nao devem bloquear a entrega principal:

- pausa por WhatsApp;
- reason codes muito sofisticados;
- prioridade refinada por `source_type`;
- dezenas de pesos finos no painel simples.

---

## Proxima Execucao Recomendada

1. endurecer policy por fase do evento e burst por preset;
2. adicionar thresholds e historico operacional de cache/runtime;
3. reorganizar `/events/:id/wall` em abas preset-first;
4. expandir cobertura de testes para resources, status visual e burst;
5. validar em fila real e carga controlada.
