# Analise Validada do WebSocket e do Telao ao Vivo

Data da validacao: 2026-04-05

## Objetivo

Validar a arquitetura atual do modulo `Wall` e responder, com base no codigo e no ambiente local, se:

1. tres players do mesmo telao, em PCs e redes diferentes, executam as midias no mesmo tempo;
2. um comando de pausa/parada enviado pelo painel chega junto em todas as telas;
3. um iframe dentro de `/events/1/wall` poderia mostrar exatamente o que outras telas estao exibindo em tempo real.

## Validacao executada

- leitura do fluxo backend do modulo `Wall`;
- leitura do player publico do frontend;
- validacao da configuracao de Reverb, canais e proxy local;
- execucao de testes backend do Wall e do pipeline relacionado;
- execucao de type-check e testes frontend do player/manager;
- validacao do endpoint publico real do wall `BG6JY36L` no ambiente local.

### Resultado da validacao

- `php artisan test --filter=Wall`: 19 testes aprovados
- `npm run type-check`: OK
- `npm run test -- src/modules/wall/player/hooks/useWallPlayer.test.tsx src/modules/wall/player/hooks/useWallEngine.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx`: 14 testes aprovados
- `http://localhost:5173/wall/player/BG6JY36L`: respondeu `200`
- `http://localhost:8000/api/v1/public/wall/BG6JY36L/state`: respondeu `live`
- `http://localhost:8000/api/v1/public/wall/BG6JY36L/boot`: respondeu com payload valido do evento e da fila
- porta `localhost:8080`: acessivel, coerente com Reverb local

## Estrutura atual do realtime do telao

### 1. Canais

- O player publico usa o canal publico `wall.{wallCode}`.
- O painel administrativo usa o canal privado `event.{eventId}.wall`.
- O canal publico do player nao exige autenticacao interativa.
- O canal privado do manager exige permissao `wall.view`.

### 2. Boot do player

Quando o player abre `/wall/player/:code`, ele:

- faz `GET /api/v1/public/wall/{wallCode}/boot`;
- recebe `event`, `files` e `settings`;
- monta a fila localmente no browser;
- passa a ouvir o canal publico do wall;
- envia heartbeats periodicos para diagnostico.

### 3. Heartbeat e diagnostico

O player envia heartbeat com:

- `player_instance_id`
- `runtime_status`
- `connection_status`
- `current_item_id`
- contadores de cache/ready/loading/error/stale
- `last_sync_at`

Mas esse heartbeat nao e uma timeline autoritativa do wall. Ele serve para diagnostico operacional. Hoje:

- o intervalo padrao de heartbeat e `20s`;
- um player so e marcado como offline depois de `60s` sem heartbeat;
- o painel ve um resumo consolidado;
- o painel nao recebe um stream autoritativo de "midia atual do wall" a cada troca de slide.

## Como o fluxo funciona de ponta a ponta

### 1. Midias publicadas/atualizadas/removidas

Quando a pipeline publica ou altera uma midia elegivel:

- o backend emite eventos de wall como `wall.media.published`, `wall.media.updated` e `wall.media.deleted`;
- esses eventos usam `ShouldBroadcast` e entram na fila dedicada `broadcasts`;
- os players recebem os eventos e atualizam suas filas locais.

Conclusao:

- a entrada de novas midias no wall depende de fila;
- portanto existe latencia potencial de fila, mesmo com internet boa.

### 2. Comandos operacionais

Comandos como status e player command usam broadcast imediato:

- `wall.status.changed`
- `wall.settings.updated`
- `wall.expired`
- `wall.player.command`

Esses eventos usam `ShouldBroadcastNow` apos commit.

Conclusao:

- pausa, retomada, parada completa, expiracao e comandos operacionais nao dependem da fila `broadcasts`;
- esses comandos tendem a chegar quase juntos nos players conectados.

### 3. Execucao da timeline

Este e o ponto mais importante para sua duvida.

Hoje a timeline do wall nao e controlada pelo servidor. Cada player:

- guarda estado local no browser;
- escolhe o `currentItemId` localmente;
- avanca para o proximo item com `setTimeout(interval_ms)`;
- aplica regras locais de fairness, cooldown e replay;
- preserva estado local em `localStorage` e `IndexedDB`.

Conclusao tecnica:

- o backend sincroniza estado e fila;
- o browser sincroniza a execucao;
- portanto o sistema atual e `near real-time`, mas nao e `hard synchronized playback`.

## Resposta direta para 3 players em PCs e redes diferentes

## Cenario

Tres computadores diferentes abrem o mesmo wall, por exemplo `BG6JY36L`, todos com internet boa.

## O que tende a acontecer

- os tres fazem boot do mesmo wall;
- os tres assinam o mesmo canal publico;
- os tres recebem os mesmos eventos de status e de midia;
- os tres usam as mesmas configuracoes de `interval_ms`, layout e politica de selecao.

## O que isso garante

- consistencia funcional alta;
- ordem da fila geralmente muito parecida;
- comandos operacionais chegando quase ao mesmo tempo.

## O que isso nao garante

- trocar a midia no mesmo milissegundo;
- iniciar video no mesmo frame;
- manter o mesmo slide exatamente alinhado por muito tempo;
- espelhar perfeitamente uma tela na outra como se fosse um unico render distribuido.

## Fontes reais de drift entre os players

- cada browser roda seu proprio `setTimeout`;
- cada device faz prefetch/cache no seu proprio ritmo;
- cada player pode ter tempos diferentes de decode/render;
- eventos de midia passam por fila `broadcasts`;
- reconexao e resync podem acontecer em momentos diferentes;
- um player pode preservar estado local diferente de outro;
- videos sao executados pelo elemento `video` de cada browser, nao por um relogio central.

## Conclusao para a pergunta principal

Sim, pode haver delay entre as midias executadas nos 3 players.

Esse delay, com internet boa e fila saudavel, tende a ser pequeno e operacionalmente aceitavel. Mas a arquitetura atual nao garante sincronismo perfeito entre telas.

## Se eu pausar ou parar pelo painel, os 3 vao parar simultaneamente?

### Resposta curta

Quase juntos: sim.
Exatamente no mesmo instante: nao ha garantia.

### Detalhe importante

No painel atual existe diferenca entre:

- `Pausar`: congela a exibicao no estado atual;
- `Parar completamente`: entra em `stopped` e mostra tela encerrada;
- `Encerrar telao`: expira o wall.

O fluxo do painel nao usa um "master clock". Ele envia um evento operacional imediato. Cada player reage quando recebe esse evento e quando o browser processa o repaint.

### Comportamento esperado por comando

- `Pausar`
  - todos os players devem congelar quase ao mesmo tempo;
  - cada um congela no slide/frame em que estava no momento em que recebeu o evento.

- `Parar completamente`
  - todos os players devem sair da exibicao e mostrar a tela de parado quase ao mesmo tempo.

- `Resumir`
  - todos os players voltam a andar quase juntos;
  - mesmo assim podem continuar com pequeno drift porque o timer volta em cada browser local.

## Se eu colocar um iframe em `/events/1/wall`, ele mostra em tempo real exatamente o que outras telas estao exibindo?

### Resposta curta

Nao como "espelho perfeito".

### O que esse iframe seria na arquitetura atual

Se voce embutir o proprio player publico, por exemplo:

- `iframe src="/wall/player/BG6JY36L"`

esse iframe vira mais um player do wall.

Ele:

- faz boot proprio;
- abre conexao websocket propria;
- roda engine propria;
- recebe os mesmos eventos realtime;
- executa a fila localmente.

Ou seja:

- ele participa do wall;
- ele nao observa um player remoto especifico;
- ele nao recebe um stream autoritativo de "o que a tela A esta mostrando agora".

### Entao ele vai parecer igual?

Na pratica, com internet boa e tudo saudavel, ele deve parecer muito proximo das outras telas.

Mas ele ainda pode:

- trocar alguns milissegundos antes ou depois;
- entrar em drift com o tempo;
- divergir temporariamente se um asset carregar mais lento;
- voltar diferente apos reconnect/resync.

### Ponto importante para monitoramento

Hoje o backend nao mantem uma timeline central do wall.

O painel recebe diagnostico por heartbeat, e nao um broadcast por troca de slide. Portanto, o manager atual nao tem como afirmar em tempo real e com precisao "esta exatamente esta midia passando agora em todas as telas".

## Observacao importante sobre iframe no mesmo navegador

No mesmo navegador/origem e com o mesmo `wall_code`, o player usa chaves de persistencia por `wall_code` em `localStorage`/`IndexedDB`.

Isso significa que um iframe e outro player aberto no mesmo navegador podem compartilhar:

- `player_instance_id`
- runtime persistido
- `currentItemId` persistido

Impacto pratico:

- o diagnostico pode consolidar essas instancias como se fossem o mesmo player;
- esse cenario nao representa bem 2 telas independentes.

Para validar multi-player real, o teste correto e em navegadores/maquinas diferentes.

## O que esta sincronizado hoje e o que nao esta

### Sincronizado hoje

- disponibilidade do wall
- status do wall
- settings do wall
- entrada/saida de midias da fila
- comandos operacionais do player
- diagnostico operacional agregado

### Nao sincronizado hoje

- relogio central da timeline
- `current slide` autoritativo
- `started_at` da midia atual compartilhado entre players
- correcao de drift entre players
- sincronismo frame a frame

## Resposta final objetiva

Para a stack atual, a resposta correta e:

- os 3 players vao reagir quase juntos a comandos operacionais, principalmente pausa/parada, porque esses eventos sao imediatos;
- as midias nao tem garantia de tocar exatamente no mesmo tempo entre os 3 PCs, porque a timeline roda localmente em cada browser;
- um iframe dentro do painel pode funcionar como mais um player em tempo real, mas nao como espelho perfeito do que outra tela remota esta exibindo;
- se voce precisa que todas as telas exibam exatamente a mesma midia no mesmo instante, a arquitetura atual ainda nao entrega essa garantia.

## Se o produto precisar de sincronismo forte

Seria necessario evoluir a arquitetura para algo como:

- timeline autoritativa no backend;
- payload com `current_item_id`, `started_at`, `next_change_at` e `playlist_version`;
- correcao de drift por clock sincronizado;
- resync por timestamps absolutos, nao so por fila;
- monitor central de playback real, nao apenas heartbeat diagnostico.

Sem isso, o comportamento correto a se esperar e: realtime operacional muito bom, mas nao sincronismo rigido de broadcast.
