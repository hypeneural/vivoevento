# Analise e proposta - controle remoto externo do telao

Data da analise: 2026-04-06

## Resumo

O modulo `Wall` ja tem a base tecnica para um controle remoto mobile-first em React + TypeScript sem exigir um backend novo na V1.

A API atual ja cobre:

- leitura de estado do telao;
- diagnostico das telas conectadas;
- start, pause, full-stop, expire e reset;
- comandos operacionais do player;
- realtime privado para o manager via Reverb.

A recomendacao e separar claramente dois produtos:

1. `Editor do telao`
   - continua em `/events/:id/wall`
   - foco em configuracao, simulacao, politicas e diagnostico detalhado

2. `Controle remoto do telao`
   - nova superficie mobile-first e PWA
   - foco em operar rapido no celular
   - sem excesso de informacao
   - com botoes grandes, feedback imediato e confirmacoes seguras

## 1. Auditoria da pagina atual `events/:id/wall`

## O que a pagina atual faz bem

- carrega `settings`, `diagnostics`, `options` e `simulation` do wall;
- permite operar o wall com `start`, `pause`, `full-stop`, `expire` e `reset`;
- oferece comandos tecnicos de manutencao:
  - `clear-cache`
  - `revalidate-assets`
  - `reinitialize-engine`
- mostra diagnostico por player conectado;
- tem realtime privado via `event.{eventId}.wall`;
- e muito boa como tela desktop de operacao e configuracao.

## O que atrapalha para uso como controle remoto no celular

### 1. Mistura editor + operacao critica + diagnostico profundo

A tela atual concentra no mesmo fluxo:

- configuracao visual;
- politica da fila;
- simulacao;
- diagnostico detalhado;
- operacao do player.

No celular isso aumenta carga cognitiva. O gestor quer apertar 1 ou 2 botoes com confianca, nao navegar por secoes longas.

### 2. As acoes mais importantes nao sao o centro da experiencia

Hoje:

- `Pausar` ou `Resumir` ficam pequenos no header;
- `Parar completamente` e `Encerrar telao` ficam mais abaixo, apos diagnostico;
- os comandos tecnicos ficam no bloco de manutencao, nao em um fluxo simplificado.

Para uso em celular e no calor da operacao, isso e ruim. A acoes criticas precisam estar no topo, grandes e impossiveis de perder.

### 3. A pagina atual e desktop-first

A composicao atual:

- usa grade em multiplas colunas;
- depende de blocos informativos pequenos;
- usa varios botoes `size="sm"`;
- assume leitura longa e scroll para chegar nas acoes avancadas.

Isso funciona no painel. Nao funciona como "controle remoto".

### 4. Ha informacao demais para quem so quer operar

Exemplos de dados importantes no editor, mas excessivos no remoto:

- simulacao da fila;
- explicacao da politica;
- sliders de configuracao;
- toggles visuais;
- detalhe de cache e storage por player;
- cards completos de runtime por tela.

No remoto, isso deve virar:

- um resumo curto;
- um modal ou bottom sheet opcional;
- nunca o foco da tela inicial.

### 5. A logica de "salve antes de operar" e correta no editor, mas ruim no remoto

A tela atual tenta salvar o draft antes de executar acoes. Isso e correto para configuracao.

No controle remoto, a acao operacional deve ser independente do editor. Se o gestor quer pausar ou parar agora, a acao nao pode depender de rascunho de configuracao local.

### 6. O endpoint `stop` nao deve ser usado no remoto

No backend atual:

- `pause` pausa;
- `full-stop` realmente para;
- `stop` tambem cai em `paused`.

Conclusao:

- o remoto deve usar apenas `pause`, `start`, `full-stop`, `expire` e `reset`;
- `stop` deve ser tratado como alias legado e nao como botao proprio.

## Conclusao da auditoria

A pagina atual `events/:id/wall` deve continuar existindo como editor/manager completo.

Mas ela nao deve virar o controle remoto. O melhor desenho e criar uma segunda pagina dedicada, extremamente focada em operacao mobile.

## 2. Objetivo do controle remoto

O controle remoto deve ser:

- app-first mobile;
- instalavel como PWA;
- rapido de abrir;
- rapido de entender;
- seguro para acoes destrutivas;
- operavel com uma mao;
- robusto em internet movel;
- simples o suficiente para um gestor usar sem treinamento tecnico.

## Resultado esperado

Ao abrir o remoto, o operador deve conseguir em ate 3 segundos:

- ver se o telao esta ao vivo, pausado ou encerrado;
- pausar ou retomar;
- parar completamente;
- encerrar;
- abrir a folha de atalhos de manutencao;
- ter um feedback claro de sucesso/erro.

## 3. Recomendacao de produto

## V1 recomendada

Criar um remoto protegido por login, dentro do mesmo app web:

- mesma base React/TypeScript;
- mesma autenticacao do admin web;
- mesmas permissoes `wall.manage`;
- mesma API atual;
- mesma infraestrutura de Reverb;
- nova rota dedicada e novo layout sem sidebar.

### Vantagens

- entrega rapida;
- sem backend novo para acao operacional;
- reuso maximo do que ja existe;
- consistente com o painel atual;
- pode virar PWA imediatamente.

## V2 opcional

Se "externo" significar compartilhar com um operador sem acesso ao painel completo:

- criar sessao remota temporaria por evento;
- link curto ou QR;
- token de curta duracao;
- escopo restrito a um wall;
- trilha de auditoria especifica.

Isso exigiria backend novo. Nao e necessario para a V1.

## Recomendacao objetiva

Implementar V1 primeiro.

Se a operacao provar necessidade de delegar o remoto a terceiros, evoluir para V2.

## 4. Arquitetura sugerida

## Rota

Sugestao principal:

- `/remote/events/:id/wall`

Caracteristicas:

- autenticada;
- fora do `AdminLayout`;
- sem sidebar;
- sem header administrativo pesado;
- com UI focada em toque e PWA standalone.

## Relacao com a rota atual

A tela atual `/events/:id/wall` ganha um CTA claro:

- `Abrir controle remoto`

Esse CTA abre a nova rota:

- no mesmo app;
- de preferencia em nova aba;
- idealmente com `display: standalone` quando instalada como PWA.

## Estrutura sugerida no frontend

Como isso ainda pertence ao modulo `wall`, a estrutura recomendada e:

```txt
apps/web/src/modules/wall/
  pages/
    EventWallManagerPage.tsx
    EventWallRemotePage.tsx
  remote/
    components/
      WallRemoteHeader.tsx
      WallRemotePrimaryActions.tsx
      WallRemoteTransportPad.tsx
      WallRemoteQuickTools.tsx
      WallRemoteStatusSheet.tsx
      WallRemoteDangerSheet.tsx
      WallRemoteMaintenanceSheet.tsx
      WallRemoteInstallBanner.tsx
    hooks/
      useWallRemoteController.ts
      useWallRemoteStatus.ts
    types/
      remote.ts
```

## Dados e hooks

O remoto pode reaproveitar diretamente:

- `getEventWallSettings`
- `getEventWallDiagnostics`
- `runEventWallAction`
- `runEventWallPlayerCommand`
- `useWallManagerRealtime`

Nao deve carregar:

- `simulateEventWall`
- `getWallOptions`
- formulario completo de `updateEventWallSettings`

## 5. UX mobile-first

## Princípios

- um comando por intencao;
- pouco texto;
- toque grande;
- feedback visual e textual imediato;
- confirmacao forte para acoes destrutivas;
- diagnostico enxuto por padrao;
- detalhes apenas em modal ou bottom sheet.

## Hierarquia ideal da tela

### 1. Faixa superior fixa com acoes criticas

Dois botoes grandes, sempre visiveis:

- `Parar completamente`
- `Encerrar`

Regras:

- altura minima de 56-64px;
- largura grande;
- cores destrutivas claras;
- icones fortes;
- confirmacao em sheet/modal antes da execucao.

Isso atende o pedido do usuario: botoes grandes no topo para uso rapido no celular.

### 2. Bloco principal com Play/Pause gigante

No centro da tela:

- um botao gigante de transporte
- estado binario:
  - `Pausar` quando live
  - `Retomar` quando paused
  - `Iniciar` quando draft/stopped

Esse botao deve ser o principal alvo do polegar:

- minimo 120px de altura;
- icone muito maior;
- rotulo curto;
- fundo forte;
- animacao curta de pressao;
- spinner de loading quando mutando.

### 3. Grade de atalhos tipo controle remoto

Logo abaixo:

- `Atualizar fotos`
- `Reiniciar exibicao`
- `Limpar cache`
- `Abrir telao`

Todos grandes, quadrados ou semi-quadrados, com icone e texto.

### 4. Rodape resumido de status

Mostrar so o essencial:

- nome do evento;
- status do telao;
- online players;
- ultimo sinal;
- realtime conectado/desconectado.

Mais detalhes devem abrir em modal ou bottom sheet.

### 5. Bottom sheet "Mais"

Conteudo ideal:

- `Resetar telao`
- `Copiar codigo`
- `Copiar link`
- lista resumida das telas conectadas
- diagnostico mais tecnico

## Modais e sub-opcoes

Como voce pediu sub-opcoes em modal, o desenho recomendado e:

### Modal de "Parar completamente"

Conteudo:

- explicacao curta: "interrompe a exibicao publica agora";
- botao principal: `Confirmar parada`;
- acao secundaria: `Pausar apenas`;
- acao terciaria: `Cancelar`.

### Modal de "Encerrar"

Conteudo:

- explicacao curta: "encerra o wall e invalida a exibicao atual";
- botao principal: `Encerrar agora`;
- acao secundaria: `Parar completamente`;
- acao terciaria: `Cancelar`.

### Modal de "Ferramentas"

Conteudo:

- `Atualizar fotos`
- `Reiniciar exibicao`
- `Limpar cache`
- explicacoes curtas por linha

### Modal de "Status"

Conteudo:

- contagem de telas conectadas;
- ultimo sinal;
- cards enxutos por player;
- sem excesso de texto tecnico por padrao.

## 6. Direcao visual

## Linguagem

O remoto nao deve parecer "mais uma pagina admin".

Direcao recomendada:

- clima de console de palco;
- fundo escuro grafite;
- superfices foscas;
- acentos fortes:
  - verde sinal para live
  - ambar para pausado
  - vermelho para parada/encerramento
  - ciano discreto para status tecnico

## Tipografia

Sugestao:

- titulos: `Space Grotesk` ou `Sora`
- labels e numeros: `IBM Plex Sans`
- codigo do wall e timestamps: `IBM Plex Mono`

## Tokens sugeridos

```css
:root {
  --remote-bg: #0b1020;
  --remote-surface: #121a2f;
  --remote-surface-2: #18223d;
  --remote-border: rgba(255,255,255,0.10);
  --remote-text: #f5f7fb;
  --remote-text-muted: rgba(245,247,251,0.68);
  --remote-live: #16c47f;
  --remote-paused: #ffb020;
  --remote-danger: #ef4444;
  --remote-info: #39bdf8;
}
```

## Comportamento visual

- radial gradient leve no fundo;
- sombra curta e densa nos botoes;
- press scale de 0.98 no toque;
- sheets com slide de baixo para cima;
- animacao curta de sucesso apos comando;
- se suportado, `navigator.vibrate()` em sucesso ou acao critica.

## 7. Componentes recomendados

## `EventWallRemotePage`

Responsavel por:

- compor a tela;
- ligar queries e mutations;
- orquestrar modais/sheets;
- decidir qual CTA principal aparece.

## `WallRemoteHeader`

Responsavel por:

- nome do evento;
- status do wall;
- chip de realtime;
- contador de telas online;
- atalho para voltar ao editor.

## `WallRemoteCriticalStrip`

Responsavel por:

- botoes grandes:
  - `Parar completamente`
  - `Encerrar`

## `WallRemoteTransportPad`

Responsavel por:

- botao gigante `Iniciar` / `Pausar` / `Retomar`;
- CTA principal da tela;
- loading state e sucesso.

## `WallRemoteQuickTools`

Responsavel por:

- `Atualizar fotos`
- `Reiniciar exibicao`
- `Limpar cache`
- `Abrir telao`

## `WallRemoteDangerSheet`

Responsavel por:

- confirmar `full-stop`;
- confirmar `expire`;
- oferecer sub-opcoes.

## `WallRemoteMaintenanceSheet`

Responsavel por:

- comandos tecnicos secundarios;
- microcopys com contexto;
- execucao segura com feedback.

## `WallRemoteStatusSheet`

Responsavel por:

- mostrar estado enxuto do wall;
- listar players conectados;
- exibir ultimo sinal e saude geral.

## `useWallRemoteController`

Responsavel por:

- encapsular mutations;
- consolidar loading states;
- expor funcoes:
  - `start`
  - `pause`
  - `fullStop`
  - `expire`
  - `reset`
  - `clearCache`
  - `revalidateAssets`
  - `reinitializeEngine`

## `useWallRemoteStatus`

Responsavel por:

- buscar settings e diagnostics;
- conectar realtime;
- derivar `isLive`, `isPaused`, `isTerminal`, `onlinePlayers`, `lastSeenAt`.

## 8. Integracao com a API atual

## Endpoints necessarios na V1

### Bootstrap do remoto

- `GET /events/{event}/wall/settings`
- `GET /events/{event}/wall/diagnostics`

### Operacao

- `POST /events/{event}/wall/start`
- `POST /events/{event}/wall/pause`
- `POST /events/{event}/wall/full-stop`
- `POST /events/{event}/wall/expire`
- `POST /events/{event}/wall/reset`
- `POST /events/{event}/wall/player-command`

### Realtime

- canal privado `event.{eventId}.wall`
- eventos:
  - `wall.settings.updated`
  - `wall.status.changed`
  - `wall.expired`
  - `wall.diagnostics.updated`

## Mapeamento de comandos tecnicos

`POST /events/{event}/wall/player-command`

com:

- `clear-cache`
- `revalidate-assets`
- `reinitialize-engine`

Esses comandos sao ideais para o menu "Ferramentas".

## O que nao precisa na V1

- edicao completa de settings;
- upload de assets;
- simulacao da fila;
- options do editor;
- preview via iframe como verdade absoluta do que esta passando.

## 9. Realtime e comportamento da tela

## Como o remoto deve reagir

Ao executar uma acao:

1. mostra loading local imediatamente;
2. exibe feedback otimista curto;
3. espera invalidacao/realtime do manager;
4. atualiza status principal sem obrigar reload.

## Se o realtime falhar

Fallback recomendado:

- manter `useWallManagerRealtime`;
- se o estado for `offline` ou `disconnected`, habilitar refetch periodico de `diagnostics` e `settings` a cada 10-15s;
- mostrar faixa ambar:
  - `Atualizacao ao vivo indisponivel`
  - `Puxando status periodicamente`

## Preview do que esta passando

Nao recomendo usar iframe do player como preview principal do remoto.

Motivo:

- a arquitetura atual do wall sincroniza bem estado, mas nao garante frame sync perfeito entre players;
- um iframe seria outro player, nao um espelho autoritativo da tela real;
- no celular isso ainda adiciona peso visual e operacional desnecessario.

Se houver preview no futuro, ele deve ser:

- opcional;
- marcado como `aproximado`;
- nunca o elemento central da decisao operacional.

## 10. Autenticacao e seguranca

## V1

Usar a autenticacao atual do app web:

- token bearer no frontend;
- `auth:sanctum` no backend;
- policy `manageWall`.

Isso ja atende bem um PWA instalado no celular do gestor.

## Regras

- acessar remoto exige `wall.manage`;
- abrir dados resumidos pode exigir `wall.view`, mas a tela de controle deve continuar sob `wall.manage`;
- todas as acoes devem gerar auditoria de origem.

## Recomendacao de auditoria

Adicionar contexto aos logs/eventos de auditoria:

- `surface = wall-remote-pwa`
- `event_id`
- `wall_code`
- `user_id`
- `action`
- `issued_at`
- `device_label` opcional

## V2 externo compartilhavel

Se quiser link externo para operador sem conta:

- criar sessao remota assinada e temporaria;
- escopo por `event_id` + `wall_id`;
- expirar automaticamente;
- permitir revoke;
- limitar somente:
  - `pause`
  - `start`
  - `full-stop`
  - `expire`
  - `player-command`
- auditar cada acao com o identificador da sessao remota.

## 11. PWA

## O que ja existe

O app web ja usa `vite-plugin-pwa`.

Logo:

- a base de service worker e manifest ja existe;
- o remoto pode nascer no mesmo app;
- o modo standalone ja e viavel.

## O que precisa ajustar

Hoje o manifest esta orientado ao modulo `Play`:

- `name: Evento Vivo Play`
- `short_name: EV Play`
- descricao focada em jogos publicos

Para um remoto de telao dentro do mesmo app, isso precisa ser revisto.

## Recomendacao

Na implementacao do remoto:

- rebrand do PWA para o produto principal `Evento Vivo`;
- descricao neutra da plataforma;
- melhorar `start_url` para retomada coerente da ultima rota ou uma entrada mais generica;
- adicionar banner de instalacao apenas nas rotas remotas/mobile-first.

## Comportamentos de PWA recomendados

- abrir sem chrome do admin;
- manter ultimo evento remoto acessado;
- instalar com CTA discreto;
- preservar sessao;
- reduzir impacto visual de reconnect;
- funcionar bem em 4G.

## 12. Fluxo recomendado da tela remota

## Estado live

Topo:

- `Parar completamente`
- `Encerrar`

Centro:

- botao gigante `Pausar`

Abaixo:

- `Atualizar fotos`
- `Reiniciar exibicao`
- `Limpar cache`
- `Abrir telao`

Rodape:

- `Ao vivo`
- `2/3 telas online`
- `Ultimo sinal 21:05:14`
- `Mais detalhes`

## Estado paused

Topo:

- `Parar completamente`
- `Encerrar`

Centro:

- botao gigante `Retomar`

Abaixo:

- atalhos iguais

## Estado stopped

Topo:

- `Encerrar`
- `Resetar`

Centro:

- botao gigante `Iniciar`

## Estado expired

Topo:

- `Resetar`

Centro:

- mensagem curta:
  - `Telão encerrado`
- CTA:
  - `Gerar novo ciclo`

## 13. Roadmap de implementacao

## Fase 1 - remoto autenticado simples

- nova rota `/remote/events/:id/wall`
- layout sem sidebar
- header enxuto
- botoes gigantes de controle
- reuse da API atual
- realtime do manager
- modais de confirmacao

Sem backend novo.

## Fase 2 - refinamento mobile/PWA

- install banner;
- haptics;
- memorizar ultimo evento remoto;
- bottom sheets;
- copy refinado;
- atalhos mais inteligentes.

## Fase 3 - auditoria e endurecimento

- trilha de auditoria especifica do remoto;
- telemetria de uso;
- limites por perfil;
- confirmacoes mais fortes para acoes destrutivas.

## Fase 4 - remoto compartilhavel por link/token

Somente se o negocio realmente precisar.

## 14. Recomendacao final

O caminho tecnicamente mais correto para a stack atual e:

1. manter `/events/:id/wall` como editor e manager completo;
2. criar uma nova pagina `mobile-first` dedicada ao remoto;
3. implementar essa V1 usando a API atual e o realtime privado ja existentes;
4. tratar preview do telao como opcional e nunca como espelho exato;
5. deixar o remoto compartilhavel por token como fase posterior.

## Decisao recomendada

Se a pergunta e "como eu faria agora, em React TypeScript, ligado nessa API?", a resposta e:

- eu criaria `EventWallRemotePage` dentro do modulo `wall`;
- abriria por `/remote/events/:id/wall`;
- usaria a autenticacao atual;
- usaria somente os endpoints operacionais e diagnostico;
- desenharia a UI como um controle remoto de palco:
  - top bar destrutiva fixa;
  - play/pause gigante;
  - atalhos grandes;
  - detalhes em modal;
  - zero simulacao e zero configuracao pesada na tela inicial.
