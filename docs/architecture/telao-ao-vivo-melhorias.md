# Analise de Melhorias do Telao ao Vivo

## Objetivo

Este documento compara o estado atual do telao ao vivo no repositorio `C:\laragon\www\vip` com o estado atual do modulo `Wall` no `eventovivo`.

O foco aqui nao e repetir documentacao antiga. O foco e responder:

1. o que o `vip` ja faz melhor no player do telao;
2. o que o `eventovivo` ja faz melhor em arquitetura e backend;
3. quais gaps ainda existem no nosso telao;
4. qual deve ser a sequencia recomendada de evolucao.

Este documento tambem assume um recorte importante: o produto precisa funcionar muito bem em evento social real, como casamento, aniversario, 15 anos, formatura e confraternizacao. Nesse contexto, o telao precisa ser justo, vivo, resiliente e adaptado ao momento da festa.

## Documentos Relacionados

- estrategia e melhorias: `docs/architecture/telao-ao-vivo-melhorias.md`
- implementacao alvo: `docs/architecture/telao-ao-vivo-implementation.md`
- plano de execucao: `docs/architecture/telao-ao-vivo-execution-plan.md`

Nota da sprint atual:

- a simulacao ja esta integrada ao manager;
- o manager ja mostra diagnostico agregado e por aparelho;
- o player ja envia heartbeat publico com cache, persistencia, sync e fallback;
- o gap real que substitui essa frente agora e evoluir policy por fase do evento e thresholds operacionais mais ricos para cache e runtime.

---

## Resumo Executivo

Hoje o `eventovivo` ja tem uma base melhor de produto para crescer com seguranca:

- modulo backend formal de `Wall`, com `Actions`, `Policies`, `Services`, `Events`, `Resources` e rotas proprias;
- contrato compartilhado em `packages/shared-types`;
- integracao do wall com a pipeline tipada de `MediaProcessing`;
- controles administrativos de iniciar, pausar, parar, expirar e resetar;
- player publico com boot HTTP + realtime via Reverb/Pusher;
- cobertura minima de testes para endpoints e transicoes sensiveis.

Mas o `vip` ainda esta a frente em um ponto critico: o motor do player. O telao do `vip` foi desenhado como uma engine de runtime, nao apenas como um slideshow circular.

As diferencas mais relevantes sao:

- o `vip` tem algoritmo de fairness por remetente;
- o `vip` persiste estado local do player e usa cache de assets;
- o `vip` trata `status de controle` e `status visual` como coisas separadas;
- o `vip` degrada melhor quando a rede oscila ou quando uma midia falha;
- o `vip` ja aceita um fluxo operacional de pausa via WhatsApp e propaga isso em realtime.

Conclusao curta:

- backend e arquitetura de dominio: `eventovivo` esta melhor;
- inteligencia do player e resiliencia de runtime: `vip` esta melhor.

Leitura complementar importante para o nosso contexto:

- para evento social, o wall nao pode ser apenas justo;
- ele precisa parecer espontaneo, atual e adaptado ao momento da festa;
- por isso a proxima versao do produto precisa combinar fairness com comportamento adaptativo por volume e fase do evento.

---

## Stack Comparativa

## `vip`

### Backend

- Laravel `^12.0`
- PHP `^8.2`
- broadcasting via Pusher compativel com o player do slideshow
- modulo principal do telao acoplado em `VipGallery`

### Frontend do telao

- React 18 + Vite
- `laravel-echo` + `pusher-js`
- feature dedicada em `apps/web/src/features/vip-gallery/slideshow`
- engine propria com `selectors.ts`, `reducer.ts`, `cache.ts`, `storage.ts`

### Caracteristicas fortes

- fairness por remetente;
- cache de midia e branding;
- IndexedDB para persistir estado do player;
- asset runtime com `loading`, `ready`, `error`, `stale`;
- priorizacao de destaque por `highlight_score`.

## `eventovivo`

### Backend

- Laravel `^13.0`
- PHP `^8.3`
- Redis + Horizon + Reverb
- modulo formal em `apps/api/app/Modules/Wall`

### Frontend do telao

- React 18 + TypeScript + Vite 5
- `pusher-js`
- modulo dedicado em `apps/web/src/modules/wall`
- contrato compartilhado em `packages/shared-types/src/wall.ts`

### Caracteristicas fortes

- melhor separacao por dominio;
- pipeline `MediaProcessing -> Wall` tipada;
- eventos de midia e eventos operacionais bem separados;
- manager administrativo ja integrado ao painel;
- autorizacao e canais privados bem definidos;
- base mais pronta para evoluir sem virar regra espalhada.

---

## O Que o `vip` Faz Hoje no Telao

## 1. Fair-play real na concorrencia de fotos

No `vip`, a escolha do proximo slide nao e FIFO e nem um loop linear por indice.

A engine:

- agrupa a fila por `sender_key`;
- prioriza quem ainda nao apareceu;
- depois escolhe quem esta ha mais tempo sem aparecer;
- evita repetir o mesmo remetente em sequencia quando ha alternativas;
- reaplica a logica no replay.

Na pratica, isso evita que um unico convidado monopolize a tela mesmo enviando varias fotos seguidas.

## 2. Runtime local do player

O player do `vip` mantem um estado mais rico:

- `playedAt`;
- `assetStatus`;
- orientacao e dimensoes da midia;
- `cachedAt`;
- erro local de asset.

Adicionalmente:

- persiste estado em IndexedDB;
- pede storage persistente do navegador;
- usa Cache API para branding, imagens e videos;
- monta fila de prefetch antes de renderizar.

Isso deixa o telao mais estavel em TV, kiosk e rede ruim.

## 3. Status visual inteligente

No `vip`, o status vindo do backend nao e usado diretamente como estado final da UI.

Existe uma resolucao intermediaria:

- `active` com midia renderizavel vira `playing`;
- `active` sem midia disponivel vira `idle`;
- `paused` congela o avanco;
- `expired`, `archived` e `disabled` tem telas proprias.

Isso evita tela preta ou comportamento confuso quando o evento esta ativo, mas a fila esta vazia ou os assets ainda nao estao prontos.

## 4. Operacao event-driven mais rica

No `vip`, o telao aceita:

- `new-media`;
- `media-updated`;
- `media-deleted`;
- `settings-updated`;
- `status-changed`;
- `expired`.

E o fluxo operacional inclui pausa por comando textual via WhatsApp quando o evento permite esse comportamento.

---

## O Que o `eventovivo` Tem Hoje

## 1. Backend melhor estruturado

O modulo `Wall` do `eventovivo` esta mais alinhado com a arquitetura desejada do monorepo:

- `Actions` para start, stop, expire e reset;
- `Services` para eligibility, payload e broadcaster;
- `Policies` e autorizacao por evento;
- `Resources` especificos para boot e settings;
- listeners tipados consumindo eventos reais do pipeline de midia.

Esse ponto e melhor do que o `vip`.

## 2. Contrato e integracao mais limpos

O `eventovivo` ja formalizou:

- tipos compartilhados do wall;
- boot publico;
- state publico;
- nomes canonicos dos eventos realtime;
- separacao entre broadcast imediato e broadcast enfileirado.

Isso reduz drift entre backend e frontend.

## 3. Manager administrativo mais maduro

Hoje ja existem:

- painel de configuracao do wall;
- controles de start, pause, full-stop, expire e reset;
- upload de background e logo;
- opcao de layout, transicao, QR, branding, neon e credito do remetente.

## 4. Player funcional, mas simples

O player atual faz:

- boot inicial por HTTP;
- sincronizacao periodica;
- assinatura realtime;
- exibicao por layout;
- pausa visual;
- avanco automatico por timer.

Mas a logica de fila ainda e simples:

- a fila entra em ordem mais recente primeiro;
- novas midias entram no topo;
- o avanco e apenas `(currentIndex + 1) % items.length`.

Isso significa que o wall atual ainda funciona mais como carrossel linear do que como engine justa de telao ao vivo.

---

## Gaps do `eventovivo` em Relacao ao `vip`

## Gap 1. Nao existe fairness por remetente

Hoje o `Wall` nao tem:

- `sender_key` no contrato;
- `playedAt` no runtime;
- agrupamento por remetente;
- selecao justa do proximo item.

Consequencia:

- quem envia muitas fotos em sequencia pode ocupar boa parte da fila recente;
- o telao tende a refletir muito mais a ordem de chegada do que equilibrio de participacao.

Esse e o maior gap funcional.

## Gap 2. O runtime do player ainda nao e resiliente

Hoje o player nao tem:

- IndexedDB para persistir estado;
- cache explicito de branding e midia;
- estados `ready/loading/error/stale`;
- prefetch ordenado;
- recuperacao local de midia ja vista quando a API oscila.

Consequencia:

- uma TV recarregada depende mais da API e da rede;
- falha de asset e instabilidade de conexao degradam pior;
- o texto do player ja menciona "cache ativo", mas o runtime ainda nao implementa essa camada de verdade.

## Gap 3. O status visual ainda e raso

Hoje o mapeamento de status do player e simples:

- `live` vira `playing` se houver itens;
- `live` vira `idle` se nao houver itens;
- `paused`, `stopped`, `expired` sao diretos.

O que ainda falta:

- distinguir midia presente de midia realmente renderizavel;
- preservar item atual com status degradado;
- tratar asset quebrado sem derrubar a experiencia toda;
- tomar decisao visual baseada em "fila pronta", nao apenas em "fila existente".

## Gap 4. Payload do wall ainda e pobre para priorizacao

O payload atual do wall carrega:

- `sender_name`;
- `caption`;
- `is_featured`;
- `created_at`.

Mas nao carrega:

- `sender_key` estavel;
- `source_type`;
- `highlight_score`;
- informacao de exibicao previa;
- prioridade operacional.

Sem isso, o frontend nao consegue reproduzir a inteligencia do `vip`.

## Gap 5. Falta uma politica formal de selecao

Hoje existe comportamento tecnico do player, mas nao existe ainda uma politica de produto claramente definida para:

- cooldown por remetente;
- limite por janela;
- controle de rajada;
- repeticao por item;
- lane editorial controlada;
- presets operacionais.

Sem isso, o wall tende a crescer por regras soltas.

## Gap 6. Falta comportamento adaptativo para evento social

Hoje a implementacao e a doc apontam bem para fairness, mas ainda falta assumir explicitamente que evento social muda muito conforme:

- momento do evento;
- volume de envio;
- quantidade de remetentes ativos;
- quantidade de backlog novo.

O wall ainda nao trata de forma formal:

- fase do evento, como recepcao, fluxo, pista e encerramento;
- politica adaptativa por volume de fila;
- replay mais livre quando o evento esta com pouco conteudo;
- fairness mais duro quando o volume sobe.

Sem isso, o wall corre dois riscos:

- ficar rigido demais quando ha pouco conteudo;
- ficar injusto demais quando ha muito conteudo.

## Gap 7. Falta tratar sequencias muito parecidas

Em evento social, o problema nao e apenas um remetente mandar muito. Muitas vezes ele manda varias fotos quase iguais do mesmo momento.

Hoje ainda falta uma regra clara para:

- agrupar fotos muito parecidas;
- reduzir prioridade de sequencias quase duplicadas;
- espaciar imagens do mesmo cluster visual.

Sem isso, o fairness por remetente melhora a distribuicao entre pessoas, mas o wall ainda pode ficar repetitivo.

## Gap 8. Falta um canal operacional externo para pausar e retomar

No `eventovivo`, a pausa do wall hoje depende do painel administrativo.

Ainda nao existe, no fluxo atual do wall:

- pausa por comando do WhatsApp;
- pausa por automacao operacional;
- reason codes mais ricos para multiplas origens de comando.

Isso nao e bloqueador para o MVP do wall, mas faz diferenca em operacao real de evento. Para evento social, porem, esse tema deve ficar abaixo de fairness, burst control, anti-repeticao e resiliencia do player.

## Gap 9. Cobertura de testes do player ainda e curta

Hoje a cobertura valida alguns cenarios importantes, mas ainda nao cobre:

- fairness;
- remocao de midia durante reproducao;
- retomada apos reconnect com fila alterada;
- comportamento com assets invalidos;
- replay justo apos primeira passada completa;
- rajada de um mesmo remetente com backlog gradual;
- comportamento em fila baixa, media e alta;
- anti-sequencia parecida.

---

## O Que Vale Preservar no `eventovivo`

Ao evoluir o telao, nao devemos copiar o `vip` de forma literal.

Precisamos preservar estas vantagens atuais:

- toda regra importante continuar no modulo `Wall`;
- payloads continuarem centralizados via `WallPayloadFactory`;
- contratos continuarem versionados em `packages/shared-types`;
- listeners continuarem consumindo eventos tipados do pipeline;
- controles administrativos continuarem passando por `Actions`.

Ou seja:

- copiar o comportamento do player faz sentido;
- copiar o acoplamento estrutural do `vip` nao faz sentido.

---

## Modelo Recomendado de Regras

O ponto mais importante para a proxima evolucao do wall e este:

- o selector nao deve escolher "a proxima foto";
- o selector deve escolher "quem pode concorrer agora", "qual remetente vence agora" e "qual item daquele remetente entra agora".

Se essa separacao nao existir, o wall vira apenas um FIFO disfarcado.

## Como o selector deve pensar

A decisao recomendada para o proximo slide e:

1. filtrar apenas itens elegiveis e renderizaveis;
2. agrupar esses itens por `sender_key`;
3. escolher o melhor candidato dentro de cada remetente;
4. escolher o remetente vencedor com base em fairness;
5. aplicar lane editorial apenas quando o ciclo permitir;
6. marcar runtime de exibicao, janela e repeticao.

Em outras palavras:

- o backend define identidade, elegibilidade e configuracao;
- o player roda o selector e decide o proximo slide;
- o painel nao deve expor algoritmo bruto por padrao, e sim presets e controles claros.

## Camadas da decisao

### Camada 1. Elegibilidade

Primeiro o sistema decide se o item pode concorrer agora.

Exemplos de regras que fazem sentido:

- so midia aprovada e pronta;
- bloquear asset que nao esteja `ready`;
- bloquear item que atingiu limite de repeticoes;
- bloquear item ainda em cooldown;
- bloquear remetente silenciado;
- bloquear duplicatas ou itens fora da janela do evento;
- bloquear item de sequencia quase identica que acabou de aparecer, quando existir alternativa melhor.

### Camada 2. Fairness por remetente

Depois, entre os elegiveis, o sistema decide qual remetente deve aparecer agora.

Regras-base recomendadas:

1. priorizar remetente que ainda nao apareceu;
2. depois priorizar quem esta ha mais tempo sem aparecer;
3. respeitar cooldown e limite por janela;
4. nunca repetir o mesmo remetente se houver alternativa;
5. usar round-robin por remetente como base mental do motor.

Esse conjunto resolve o problema classico de "100 fotos de A contra 10 de B" sem impedir que A continue aparecendo ao longo do evento.

### Camada 3. Freshness

O wall tambem precisa parecer vivo, nao apenas justo.

Por isso faz sentido incluir:

- pequeno bonus para fotos recentes;
- janela curta de novidade, por exemplo 2 a 5 minutos;
- recencia sempre como bonus secundario, nunca como criterio dominante sobre fairness.

### Camada 4. Priorizacao editorial

Conteudo editorial faz sentido, mas precisa ser limitado.

Regra recomendada:

- manter fairness como regra principal;
- permitir lane de destaque para `is_featured` ou futuro `highlight_score`;
- inserir 1 destaque a cada N exibicoes normais;
- nunca deixar destaque virar spam do operador.

Para social, o default deve ser discreto. Faz mais sentido algo como 1 destaque a cada 7 ou 8 exibicoes do que 1 a cada 5.

### Camada 5. Saturacao e burst control

Em eventos reais, o problema nao e so "qual foto mostrar", mas "como sobreviver a rajadas".

Regras recomendadas:

- limite de itens elegiveis por remetente;
- limite por remetente em uma janela de tempo;
- desacelerar entrada de rajadas sem precisar bloquear o usuario;
- reduzir prioridade de quem enviou muito em pouco tempo.

Em evento social, a forma preferida de fazer isso nao e bloqueio duro. O melhor caminho e:

- deixar apenas 3 a 5 itens do remetente como elegiveis imediatos;
- manter o restante em backlog interno daquele remetente;
- liberar novos elegiveis conforme a engine vai consumindo o backlog.

### Camada 6. Replay e repeticao

Quando faltar conteudo novo, o replay tambem precisa ser justo.

Regras recomendadas:

- replay justo por remetente;
- intervalo minimo para repetir a mesma foto;
- limite maximo de exibicoes por item durante o evento.

Em evento social, o replay nao deve ser totalmente fixo. Ele precisa reagir ao volume:

- fila alta: repetir mais tarde;
- fila media: repetir em prazo intermediario;
- fila baixa: repetir mais cedo para o wall nao parecer morto.

### Camada 7. Adaptacao por volume e fase do evento

Para evento social, o wall deve reagir ao contexto.

Exemplos:

- se ha poucos remetentes ativos, fairness pode ser mais leve e replay mais rapido;
- se ha muitos remetentes ativos, fairness e burst control precisam endurecer;
- se o evento esta no encerramento, replay pode ficar mais livre;
- se o evento esta no pico da pista, recencia pode ganhar um pouco mais de peso.

O wall nao deve ser estatico do inicio ao fim do evento.

---

## Melhorias Recomendadas

## Fase 1. Paridade funcional do player

### 1. Criar uma engine real de wall no frontend

Evoluir `apps/web/src/modules/wall/player` para ter tambem:

- `engine/selectors.ts`
- `engine/reducer.ts`
- `engine/cache.ts`
- `engine/storage.ts`

Objetivo:

- sair do carrossel linear;
- isolar a matematica da fila;
- preparar o player para fairness, persistencia e fallback.

### 2. Adicionar fairness por remetente

Adicionar no contrato do wall:

- `sender_key?: string | null`

E no runtime:

- `playedAt?: string | null`
- `assetStatus`

A logica recomendada e portar a estrategia do `vip`, mas adaptando para o dominio do `Wall`.

Criterio recomendado:

1. priorizar remetentes ainda nao exibidos;
2. depois priorizar quem esta ha mais tempo sem aparecer;
3. nunca repetir o remetente atual se houver outro pronto;
4. no replay, reaplicar a mesma politica.

### 3. Definir uma identidade estavel de remetente

Esse ponto e obrigatorio para fairness funcionar bem.

Sugestao de regra:

- WhatsApp e inbound: usar telefone normalizado;
- usuario autenticado: usar `user:{id}`;
- upload publico: criar um `guest_session_key` persistido por sessao publica;
- fallback final: `source_label` ou `sender_name`.

Sem isso, dois convidados com o mesmo nome ou o mesmo convidado com nomes diferentes quebram a justica da fila.

### 4. Separar `status de controle` de `status visual`

Adicionar um resolvedor semelhante ao do `vip`:

- `live` + midia pronta = `playing`;
- `live` + fila vazia ou invalida = `idle`;
- `paused` = `paused`;
- `expired` = `expired`;
- `disabled/stopped` = tela terminal.

Isso deve acontecer no frontend do player, nao no controller.

### 5. Formalizar uma politica padrao do wall

Antes de abrir muitos controles no painel, vale documentar e implementar uma politica padrao do produto.

Politica recomendada para v1 social:

- so entra midia aprovada e pronta;
- agrupar por `sender_key`;
- priorizar remetentes que ainda nao apareceram;
- depois priorizar quem esta ha mais tempo sem aparecer;
- nunca repetir o mesmo remetente se houver alternativa;
- limitar a 2 ou 3 fotos por remetente a cada 10 minutos;
- limitar a 6 a 8 fotos elegiveis simultaneas por remetente;
- aplicar bonus leve para fotos recem-chegadas nos primeiros 2 a 4 minutos;
- inserir 1 destaque a cada 7 exibicoes normais;
- repetir a mesma foto no maximo 2 vezes no evento;
- repetir a mesma foto com intervalo adaptativo:
  - fila alta: 20 minutos;
  - fila media: 12 minutos;
  - fila baixa: 8 minutos.

Essa politica tende a funcionar melhor em festa real, porque mantem justica sem deixar o wall parecer travado quando o volume cai.

### 6. Adicionar anti-sequencia parecida

Mesmo com fairness por remetente, o wall ainda pode ficar repetitivo se varias fotos quase iguais concorrerem ao mesmo tempo.

Por isso faz sentido introduzir ao menos uma regra leve de:

- `duplicate_cluster_key` ou equivalente;
- espacamento entre itens muito parecidos;
- preferencia pela melhor foto do cluster antes de reabrir o restante.

### 7. Ampliar os testes de engine

Criar testes para:

- 10 usuarios enviando multiplas fotos ao mesmo tempo;
- atualizacao de URL da midia durante exibicao;
- exclusao do item atual;
- replay apos primeira rodada completa;
- pausa e retomada sem pular item injustamente;
- backlog gradual por remetente em rajada;
- reducao de repeticao em fotos quase iguais.

## Fase 2. Configuracao e presets

### 8. Implementar presets e configuracao do selector

O painel nao deve expor pesos matematicos como experiencia principal.

O recomendado e oferecer presets claros:

- `balanced`
- `live`
- `inclusive`
- `editorial`

E deixar "custom" ou pesos detalhados apenas em modo avancado.

Presets sugeridos:

- `balanced`: fairness forte e recencia moderada; deve ser o default do produto;
- `live`: mais responsivo ao que acabou de chegar, sem desligar fairness;
- `inclusive`: foco em distribuir participacao entre mais pessoas;
- `editorial`: fairness base com lane de destaque controlada, mas mais discreta no social do que em eventos corporativos.

### 9. Adicionar presets por fase do evento

Para evento social, alem do preset geral, faz sentido existir nocao de fase do evento, mesmo que inicialmente manual no painel.

Fases sugeridas:

- `reception`
- `flow`
- `party`
- `closing`

Comportamento esperado:

- `reception`: mais inclusivo, menos replay, 8 a 10 segundos por slide;
- `flow`: modo equilibrado padrao;
- `party`: mais vivo, mais recencia, 5 a 7 segundos por slide, burst control mais duro;
- `closing`: replay mais flexivel, fairness ainda presente, mas menos rigido.

### 10. Guardar configuracao clara por wall ou evento

Faz sentido guardar configuracao explicita do selector, nao apenas flags soltas.

Exemplo de shape v1:

```json
{
  "selection_mode": "balanced",
  "event_phase": "flow",
  "slide_duration_seconds": 10,
  "max_consecutive_per_sender": 1,
  "sender_cooldown_seconds": 60,
  "sender_window_limit": 3,
  "sender_window_minutes": 10,
  "max_eligible_items_per_sender": 8,
  "prefer_unseen_senders": true,
  "freshness_boost_minutes": 3,
  "featured_every_n_slides": 7,
  "max_replays_per_item": 2,
  "min_repeat_interval_minutes": 12,
  "avoid_same_sender_if_alternative_exists": true,
  "burst_control_enabled": true,
  "adaptive_volume_enabled": true
}
```

Pesos matematicos mais finos podem existir depois, mas nao precisam ser a interface principal do produto no primeiro momento.

### 11. Expor controles simples no painel

Configuracoes que fazem sentido no painel principal:

- tempo por slide;
- maximo consecutivo do mesmo remetente;
- tempo minimo entre aparicoes do mesmo remetente;
- maximo por remetente em uma janela;
- janela de controle;
- quantidade maxima elegivel por remetente;
- repetir fotos ou nao;
- maximo de repeticoes por foto;
- inserir destaque a cada N exibicoes;
- peso de recencia em formato simples: baixo, medio ou alto.

UX recomendada:

- preset como decisao principal;
- fase do evento como ajuste rapido complementar;
- resumo textual do comportamento configurado;
- modo avancado escondido por padrao;
- idealmente uma simulacao de ordem estimada dos proximos slides para gerar confianca operacional.

## Fase 3. Resiliencia operacional do player

### 12. Implementar cache e persistencia local

Adicionar:

- Cache API para imagens, videos, logo e background;
- IndexedDB para estado resumido do player;
- fila de prefetch baseada no proximo item justo;
- fallback para midia `stale` quando download novo falhar.

Resultado esperado:

- TV recupera melhor depois de refresh;
- o telao continua operacional mesmo com rede degradada;
- menos sensacao de "piscar" quando uma midia e atualizada.

### 13. Introduzir runtime de assets

Cada item do wall deveria ter algo como:

- `loading`;
- `ready`;
- `error`;
- `stale`.

Isso permite:

- tocar so midia realmente pronta;
- manter midia antiga em cache enquanto a nova ainda baixa;
- evitar que o player caia para `idle` so porque recebeu item novo ainda nao carregado.

## Fase 4. Adaptacao por volume e editorial

### 14. Adicionar politica adaptativa por volume

Em evento social, a politica de selecao nao deve ser fixa o tempo todo.

Regras recomendadas:

- fila baixa: fairness mais leve e replay mais rapido;
- fila media: comportamento equilibrado padrao;
- fila alta: fairness mais forte, cooldown maior e lane editorial mais rara.

### 15. Adicionar score de prioridade

Hoje o `eventovivo` ja tem `is_featured` e `sort_order`, mas o player so usa isso para layout e nao para selecao da fila.

Sugestao:

- manter fairness como regra-base;
- permitir uma lane de prioridade editorial para `is_featured` ou um futuro `highlight_score`;
- limitar essa lane para nao quebrar o fair-play.

### 16. Ampliar o payload do wall

Sugestao de novos campos em `packages/shared-types/src/wall.ts`:

- `sender_key`;
- `source_type`;
- `highlight_score`;
- `short_caption`;
- `duplicate_cluster_key`;
- `published_sequence` ou `published_at` obrigatorio.

Isso abre espaco para:

- fairness;
- layouts mais inteligentes;
- ranking editorial;
- agrupamento leve de sequencias muito parecidas.

## Fase 5. Operacao externa e refinamentos

### 17. Criar comandos operacionais externos

Depois que o player estiver solido, vale evoluir para:

- pausa por WhatsApp;
- pausa por automacao do evento;
- parada por janela de horario;
- mensagens de reason padronizadas.

Esse passo deve nascer dentro do modulo `Wall`, mesmo que consuma eventos do modulo `WhatsApp`, mas para evento social ele deve ficar abaixo de fairness, burst control, anti-repeticao e resiliencia do player.

---

## Arquitetura Recomendada Dentro do `eventovivo`

## Backend

### Modulo `Wall`

Manter a responsabilidade aqui:

- `Services/WallPayloadFactory.php`
  - enriquecer payload com `sender_key`, `source_type` e prioridade;
- `Services/WallEligibilityService.php`
  - continuar decidindo o que pode aparecer;
- `Http/Resources/WallBootResource.php`
  - continuar alinhado com o contrato compartilhado.

Responsabilidades esperadas do backend:

- identidade estavel do remetente;
- elegibilidade da midia;
- configuracao do selector;
- prioridade editorial;
- agrupamento leve de duplicatas ou sequencias parecidas;
- payload rico o suficiente para o player decidir bem.

Se a logica de identidade do remetente crescer, vale extrair:

- `Support/WallSenderKeyResolver.php`

Se a priorizacao editorial crescer, vale extrair:

- `Support/WallPriorityResolver.php`

Se a configuracao do selector crescer, vale extrair:

- `Data/WallSelectorConfigData.php`

Se o controle de similaridade crescer, vale extrair:

- `Support/WallDuplicateClusterResolver.php`

## Frontend

### Modulo `wall/player`

Trazer a mesma separacao saudavel ja vista no `vip`:

- `engine/selectors.ts`
  - selecao justa do proximo item;
- `engine/reducer.ts`
  - estado de runtime;
- `engine/cache.ts`
  - prefetch e Cache API;
- `engine/storage.ts`
  - persistencia local.

Arquivos que fazem sentido existir quando a engine amadurecer:

- `engine/isEligibleNow.ts`
- `engine/selectBestItemWithinSender.ts`
- `engine/computeSenderScore.ts`
- `engine/applyPreset.ts`

Responsabilidades esperadas do frontend:

- runtime;
- fairness;
- asset readiness;
- decisao do proximo item;
- cache e persistencia local.

O hook `useWallEngine.ts` deve virar uma orquestracao fina, nao o lugar da matematica principal.

---

## Guardrails Obrigatorios

Mesmo com presets e customizacao, algumas protecoes devem ser tratadas como obrigatorias:

- `sender_key` estavel;
- item so concorre se asset estiver `ready`;
- nao repetir o mesmo remetente se houver alternativa;
- limite de repeticao do mesmo item;
- lane editorial sempre limitada;
- anti-rajada minimamente ativa.

Em evento social, tambem faz sentido tratar como fortemente recomendados:

- limite elegivel por remetente mais baixo;
- replay contextual por volume;
- anti-sequencia parecida;
- backlog gradual por remetente em rajadas.

Sem essas protecoes, o sistema continua vulneravel a spam de convidado ou de operador.

---

## Metricas Operacionais Recomendadas

Quando a engine estiver em producao, vale medir:

- tempo medio ate a primeira aparicao por remetente;
- percentual de remetentes ativos que apareceram ao menos uma vez;
- concentracao de exibicao por remetente;
- itens bloqueados por cooldown, janela ou burst;
- taxa de falha de asset;
- tempo medio entre publicacao e primeira exibicao no wall;
- tempo estimado ate aparecer pela primeira vez;
- quantidade de itens segurados em backlog por remetente;
- percentual de itens reduzidos por anti-sequencia parecida.

Essas metricas ajudam a validar se o produto esta realmente justo e vivo, e nao apenas teoricamente bem desenhado.

---

## Ordem Recomendada de Execucao

## Sprint 1

1. adicionar `sender_key` ao contrato do wall;
2. resolver identidade estavel de remetente no backend;
3. extrair `selectors.ts` e `reducer.ts` do player;
4. implementar fairness e guardrails minimos por remetente;
5. introduzir backlog gradual por remetente e anti-sequencia parecida;
6. ampliar testes do player.

## Sprint 2

1. persistir configuracao do selector no wall;
2. introduzir presets `balanced`, `live`, `inclusive` e `editorial`;
3. introduzir fases `reception`, `flow`, `party` e `closing`;
4. expor controles simples no painel;
5. publicar politica padrao do produto.

## Sprint 3

1. adicionar `assetStatus`;
2. implementar prefetch e cache;
3. persistir estado minimo em IndexedDB;
4. melhorar a decisao de `status visual`.

## Sprint 4

1. adicionar politica adaptativa por volume;
2. adicionar priorizacao editorial controlada;
3. evoluir payload com score, metadata curta e `duplicate_cluster_key`;
4. medir latencia ponta a ponta e saude do player.

## Sprint 5

1. integrar comandos operacionais externos;
2. enriquecer reason codes operacionais;
3. revisar controles avancados do painel com base no uso real.

## Status Atual da Implementacao

Itens ja implementados nesta primeira leva:

- `sender_key` entrou no contrato compartilhado do wall;
- o backend agora resolve identidade estavel de remetente no payload publico;
- o payload do wall agora inclui `source_type` e `duplicate_cluster_key`;
- o player ganhou `engine/reducer.ts`, `engine/selectors.ts`, `engine/cache.ts` e `engine/storage.ts`;
- o hook `useWallEngine.ts` virou orquestracao fina;
- a selecao do proximo slide agora evita repetir o mesmo remetente quando existe alternativa;
- o selector agora limita a janela elegivel por remetente e libera backlog gradualmente;
- o selector agora evita sequencia imediata do mesmo `duplicate_cluster_key` quando ha alternativa;
- o runtime agora guarda historico minimo por remetente para cooldown e janela temporal;
- o selector agora respeita cooldown por remetente e limite por janela quando existe alternativa;
- o replay agora reage ao volume da fila com regras diferentes para fila baixa, media e alta;
- o selector agora formaliza `max_replays_per_item`, com fallback de continuidade quando toda a fila ja estourou esse budget;
- os thresholds do replay adaptativo por volume agora sao parte da policy persistida do wall;
- o manager do wall agora expõe presets e controles de replay adaptativo no painel;
- o reconnect agora preserva o item atual quando ele ainda existe na nova fila;
- a remocao do item atual durante reproducao agora troca para o proximo item justo;
- o replay apos a primeira rodada completa agora esta coberto por teste;
- o runtime agora espelha estado minimo do player em `localStorage` e `IndexedDB`;
- o runtime passou a classificar asset como `idle`, `loading`, `ready`, `stale` e `error`;
- o player agora usa `Cache API` como fallback local de midia quando a rede falha;
- o heartbeat do player agora reporta capacidade real de cache e persistencia do aparelho;
- o heartbeat do player agora reporta quota, uso, hit rate e fallback `stale` do cache local;
- o backend agora agrega diagnostico por aparelho e resumo do wall em fila `analytics`;
- o manager agora usa o canal privado `event.{eventId}.wall` para invalidacao seletiva e diagnostico;
- o manager agora mostra bloco de simulacao com fila real + draft e preview dos proximos slides;
- o manager agora aplica fase do evento no resumo, na simulacao e no runtime efetivo do wall;
- o manager agora mostra bloco de diagnostico operacional com cards por aparelho e comandos de cache/runtime;
- os testes do player agora cobrem fairness, fallback de identidade, backlog gradual, anti-sequencia, cooldown, janela, reconnect, exclusao e replay.

Itens ainda parciais ou pendentes da fase:

- priorizacao editorial controlada com `highlight_score` ainda nao entrou;
- o diagnostico ainda nao tem historico temporal, thresholds configuraveis, quota pressure consolidada e leitura de latencia ponta a ponta.

## Taskboard Detalhado da Sprint 1

### Task 1. Enriquecer contrato e payload do wall

- `[x]` Subtask 1.1 adicionar `sender_key` ao contrato compartilhado
- `[x]` Subtask 1.2 resolver identidade estavel de remetente no backend
- `[x]` Subtask 1.3 expor `source_type` no payload publico
- `[x]` Subtask 1.4 expor `duplicate_cluster_key` no payload publico

### Task 2. Extrair engine de runtime do player

- `[x]` Subtask 2.1 criar `engine/selectors.ts`
- `[x]` Subtask 2.2 criar `engine/reducer.ts`
- `[x]` Subtask 2.3 criar `engine/cache.ts`
- `[x]` Subtask 2.4 criar `engine/storage.ts`
- `[x]` Subtask 2.5 reduzir `useWallEngine.ts` para orquestracao

### Task 3. Implementar politica inicial de selecao social

- `[x]` Subtask 3.1 evitar repetir o mesmo remetente quando existe alternativa
- `[x]` Subtask 3.2 considerar apenas assets renderizaveis e priorizar `ready`
- `[x]` Subtask 3.3 aplicar janela elegivel por remetente com `maxEligibleItemsPerSender = 3`
- `[x]` Subtask 3.4 liberar backlog gradualmente conforme itens do remetente sao consumidos
- `[x]` Subtask 3.5 evitar sequencia imediata do mesmo `duplicate_cluster_key` quando ha alternativa
- `[x]` Subtask 3.6 formalizar cooldown por remetente
- `[x]` Subtask 3.7 formalizar limite por remetente em janela de tempo
- `[x]` Subtask 3.8 formalizar replay adaptativo por volume
- `[x]` Subtask 3.9 formalizar limite maximo de exibicoes por item

### Task 4. Cobertura de testes da engine

- `[x]` Subtask 4.1 validar payload publico enriquecido no backend
- `[x]` Subtask 4.2 validar fairness por remetente no frontend
- `[x]` Subtask 4.3 validar fallback de identidade sem `sender_key`
- `[x]` Subtask 4.4 validar backlog gradual por remetente no selector
- `[x]` Subtask 4.5 validar anti-sequencia parecida no selector
- `[x]` Subtask 4.6 validar cooldown por remetente no selector
- `[x]` Subtask 4.7 validar limite por janela temporal no selector
- `[x]` Subtask 4.8 validar reconnect com fila alterada
- `[x]` Subtask 4.9 validar exclusao do item atual durante reproducao
- `[x]` Subtask 4.10 validar replay completo apos primeira rodada
- `[x]` Subtask 4.11 validar limite maximo de exibicoes por item

### Task 5. Proxima frente recomendada

- `[x]` Subtask 5.1 mover policy defaults para configuracao persistida do wall
- `[x]` Subtask 5.2 introduzir presets `balanced`, `live`, `inclusive` e `editorial`
- `[x]` Subtask 5.3 adicionar IndexedDB + Cache API com fallback `stale`
- `[x]` Subtask 5.4 iniciar politica adaptativa por volume e fase do evento

## Premissas de Replay Hoje Persistidas no Wall

Hoje o wall persiste estes defaults de replay adaptativo e o player usa fallback interno apenas como protecao:

- fila baixa: ate `6` itens elegiveis, com replay preferencial a partir de `8` minutos;
- fila media: de `7` a `12` itens elegiveis, com replay preferencial a partir de `12` minutos;
- fila alta: acima de `12` itens elegiveis, com replay preferencial a partir de `20` minutos;
- cada item tenta no maximo `2` replays no modo equilibrado antes de sair da rota preferencial;
- se ainda nao existir nenhum item maduro para replay, o selector faz fallback para nao deixar o wall morrer.
- se toda a fila ja tiver estourado o budget de replay por item, o selector relaxa esse guardrail para preservar continuidade visual.

## Pontos Que Faltavam Ficar Explicitos na Doc

Ao revisar a documentacao depois desta sprint, os pontos abaixo ainda merecem formalizacao explicita:

1. o payload ainda nao formaliza `highlight_score`, `published_at` obrigatorio ou `published_sequence`, que vao fazer diferenca na fase editorial e de freshness;
2. a doc ainda nao fecha como a policy adaptativa por fase do evento vai sobrescrever ou combinar com os presets;
3. a simulacao do wall ja esta integrada ao manager, e o gap remanescente desta frente esta em enriquecer thresholds operacionais, historico do diagnostico e presets automativos por fase;
4. o critério de “volume” ainda e baseado em quantidade de itens, e nao em volume por remetente ou recencia da festa.

---

## Recomendacao Final

Se a meta e deixar o telao do `eventovivo` realmente competitivo para uso em evento ao vivo, o primeiro investimento nao deve ser em mais layout nem em mais painel.

O investimento certo e:

1. fairness por remetente;
2. runtime resiliente de player;
3. identidade estavel de origem da midia;
4. politica adaptativa por volume e fase;
5. testes de comportamento da fila.

Em resumo:

- o `eventovivo` ja tem um backend mais solido para o wall;
- o proximo salto de qualidade esta no player;
- o melhor caminho e absorver a inteligencia do `vip` sem abandonar a arquitetura modular atual do `Wall`;
- para evento social, o wall precisa ser justo, estavel e adaptado ao momento real da festa.

Frase-guia recomendada para o produto:

"O telao deve parecer espontaneo e atual, sem deixar ninguem dominar a experiencia."
