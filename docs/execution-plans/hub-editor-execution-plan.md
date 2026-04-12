# Hub Editor Evolution Plan

## Context

O Hub atual do Evento Vivo ja cobre o basico de um agregador de links para eventos:

- tema completo com layout base
- preview integrado em React
- hero com upload manual
- CTA preset e customizados
- controles de cor por tema e por botao
- selecao previa do evento antes do editor

O gap principal nao e mais visual. O gap esta em produto e instrumentacao:

- ainda nao existe builder orientado a blocos tipados
- nao existe analytics por CTA do Hub
- o editor nao mostra performance operacional do Hub
- falta uma galeria de temas mais rica e editorial
- falta um bloco social proprio, em vez de tratar tudo como CTA generico

O produto analisado em `C:\Users\Usuario\Desktop\product` mostrou quatro ideias fortes que valem importar:

1. temas funcionam como pacotes completos, nao apenas paleta
2. blocos sao entidades de produto com ciclo de vida e metrica
3. pageview e clique precisam ser medidos separadamente
4. o bloco social precisa existir como componente proprio

Ao mesmo tempo, algumas escolhas de la nao devem ser copiadas:

- painel de customizacao excessivamente denso
- mistura de design, SEO, pixels, PWA e codigo custom no mesmo lugar
- controles avancados demais antes do basico

## Product Goals

1. Transformar o Hub em um builder robusto de experiencia publica para eventos.
2. Dar feedback operacional real no editor: views, cliques, CTR e top CTAs.
3. Subir o nivel visual dos presets sem transformar o editor em uma tela confusa.
4. Evoluir do modelo atual de CTA list para um modelo de blocos tipados.
5. Manter a edicao rapida para operacao de evento e nao virar um CMS generico.

## Principles

1. Tema precisa aplicar estrutura, copy base, ordem de CTA e linguagem visual.
2. Toda interacao publica importante do Hub precisa ser rastreavel.
3. O editor precisa separar claramente preset, conteudo, blocos, analytics e avancado.
4. O Hub deve continuar mobile-first, com preview fiel ao render publico.
5. O backend deve seguir o modulo `Hub`, usando actions para escrita e queries para leitura.

## Imported Inspirations

### Theme gallery

- transformar o bloco de temas em uma galeria mais visual, com cards maiores
- cada tema deve mostrar:
  - nome
  - mood
  - swatches
  - mini mock do hero
  - mini mock da CTA list
  - perfil de uso, ex: casamento, show, congresso

### Full preset application

- tema deve aplicar:
  - `layout_key`
  - `theme_tokens`
  - `button_style`
  - ordem dos CTAs preset
  - labels e icones base
  - visibilidade padrao de blocos
  - copy sugerida do hero e welcome

### Performance feedback

- o editor deve exibir:
  - page views
  - visitantes unicos
  - cliques em CTA
  - CTR
  - top botoes
  - timeline curta por periodo

### Social block

- criar um bloco proprio para redes sociais
- suportar pelo menos:
  - instagram
  - whatsapp
  - tiktok
  - youtube
  - spotify
  - site
  - mapa
- cada item precisa ter preview, estilo e analytics

## Phases

## Current status

Entregue ate agora:

- Phase 0 concluida
- Phase 1 concluida
- Phase 2 parcialmente concluida
- Phase 4 parcialmente iniciada por `social_strip`

Detalhe do que ja entrou no codigo:

- tracking publico com `hub.button_click` por CTA e `hub.social_click` por item social
- painel de performance no editor com page views, visitantes unicos, cliques, CTR, timeline e top interacoes
- galeria de temas mais visual, com mood, swatches e perfil recomendado
- tema aplicando copy base, layout, ordem de presets e visibilidade inicial de blocos
- `social_strip` no contrato do builder, no editor, no render publico e no analytics
- `countdown` no contrato do builder, no editor e no render publico, com modo padrao baseado em `starts_at` do evento e opcao de data manual

Proxima frente recomendada:

- consolidar o builder por blocos tipados alem do conjunto atual
- entrar em `info_grid` e `sponsor_strip`
- separar melhor o editor em `Presets`, `Conteudo`, `Blocos`, `Analytics` e `Avancado`

### Phase 0 - Governance, contracts and cleanup

Objetivo: consolidar o produto do Hub antes de ampliar o escopo.

Tasks:

1. Formalizar o roadmap do Hub no repositorio.
   Subtasks:
   - documentar metas de produto
   - documentar o que sera importado do benchmark externo
   - definir o que nao deve ser copiado
   - quebrar por fases, dependencias e aceite

2. Revisar o contrato atual do modulo `Hub`.
   Subtasks:
   - mapear endpoints atuais
   - mapear payload admin/publico
   - mapear lacunas do editor e do render publico
   - listar campos que precisam continuar retrocompativeis

3. Preparar observabilidade minima do Hub.
   Subtasks:
   - separar pageview de clique
   - definir nomes de eventos de analytics do Hub
   - definir metadata padrao para CTA

Acceptance:

- roadmap salvo no repositorio
- nomenclatura de analytics definida
- escopo das fases acordado no codigo e na documentacao

### Phase 1 - Instrumentation and operational insight

Objetivo: fazer o Hub medir e mostrar o que esta acontecendo.

Tasks:

1. Criar tracking publico de clique por CTA.
   Subtasks:
   - criar endpoint publico do Hub para tracking de clique
   - aceitar apenas `button_id`, sem confiar em URL vinda do cliente
   - resolver o CTA a partir da configuracao atual do evento
   - gravar `hub.button_click`
   - incluir metadata:
     - `button_id`
     - `button_label`
     - `button_type`
     - `preset_key`
     - `button_icon`
     - `button_position`
     - `resolved_url`
     - `surface`
   - tornar o endpoint resiliente para `sendBeacon`

2. Instrumentar o frontend publico.
   Subtasks:
   - disparar tracking no click real do CTA
   - usar `navigator.sendBeacon` com fallback `fetch keepalive`
   - nao rastrear preview do editor
   - nao bloquear a navegacao publica

3. Expor analytics operacionais do Hub no admin.
   Subtasks:
   - criar query do modulo `Hub` para resumir `hub.page_view` e `hub.button_click`
   - retornar:
     - resumo
     - timeline
     - performance por botao
     - top botoes
   - permitir filtro por janela, ex: 7, 30 ou 90 dias

4. Mostrar essas metricas no editor.
   Subtasks:
   - criar painel de performance
   - mostrar page views, visitantes unicos, cliques e CTR
   - listar top botoes
   - mostrar badges de clique nos cards dos botoes do editor

5. Cobrir tudo com testes.
   Subtasks:
   - feature test do endpoint publico de clique
   - feature test do endpoint admin de insights
   - teste de regressao para `hub.page_view`

Acceptance:

- clique real do CTA aparece em analytics
- editor mostra performance operacional do Hub
- preview continua intacto
- testes backend passam

### Phase 2 - Theme gallery and preset packs

Objetivo: tornar a escolha de tema mais forte, clara e imediata.

Tasks:

1. Evoluir a galeria de temas.
   Subtasks:
   - trocar a lista simples atual por uma galeria com cards visuais
   - adicionar swatches, mood e perfil do tema
   - adicionar mini composicao do hero e do CTA

2. Enriquecer os presets.
   Subtasks:
   - aplicar copy sugerida por tipo de evento
   - aplicar variacao de shape, spacing e densidade
   - aplicar recomendacao inicial de blocos
   - permitir reset por tema

3. Introduzir fontes curatoriais.
   Subtasks:
   - definir familias de titulo e corpo
   - associar fontes aos presets
   - manter fallback seguro para performance

Acceptance:

- ao clicar em um tema, o Hub muda de forma nitida e completa
- o usuario entende o perfil de cada preset sem testar no escuro

### Phase 3 - Typed block system

Objetivo: sair do modelo atual de 4 secoes fixas para um builder modular.

Tasks:

1. Definir o contrato de blocos do Hub.
   Subtasks:
   - criar tipo base de bloco
   - definir `id`, `type`, `enabled`, `sort_order`, `style`
   - suportar versionamento do builder

2. Introduzir os primeiros blocos tipados.
   Subtasks:
   - `hero`
   - `meta_cards`
   - `welcome`
   - `cta_group`
   - `social_strip`
   - `countdown`
   - `info_grid`
   - `sponsor_strip`

3. Criar catalogo de blocos.
   Subtasks:
   - cards de inclusao
   - categorias
   - busca
   - presets recomendados

4. Melhorar o editor de blocos.
   Subtasks:
   - duplicar
   - esconder
   - reordenar
   - remover
   - resetar estilo do bloco

Acceptance:

- o Hub suporta blocos reais
- o usuario entende claramente a estrutura da pagina

### Phase 4 - Social block and richer CTA system

Objetivo: elevar os blocos de link para um nivel mais util para eventos.

Tasks:

1. Criar `social_strip`.
   Subtasks:
   - definir provedores suportados
   - permitir handle ou URL completa
   - estilizar icone, fundo, borda e tamanho
   - suportar analytics por item

2. Evoluir o CTA.
   Subtasks:
   - permitir descricao curta opcional
   - permitir badge, ex: "novo", "ao vivo"
   - permitir CTA com icone no topo ou na lateral
   - permitir CTA com variante editorial

3. Melhorar relacao entre presets e CTAs.
   Subtasks:
   - presets devem nascer com labels melhores por contexto
   - presets devem recomendar ordem com base em modulo ativo
   - CTAs indisponiveis devem ser claramente marcados

Acceptance:

- Hub deixa de ser apenas uma lista de links
- redes sociais viram um bloco proprio, claro e medivel

### Phase 5 - Advanced content controls

Objetivo: abrir personalizacao avancada sem poluir o fluxo principal.

Tasks:

1. Criar area `Avancado`.
   Subtasks:
   - espacamento global
   - raio padrao
   - intensidade de sombra
   - opacidade do hero

2. Criar area `Assets`.
   Subtasks:
   - historico recente de hero
   - reutilizacao da capa do evento
   - biblioteca curta de imagens do evento

3. Criar regras de visibilidade realmente uteis para evento.
   Subtasks:
   - agendamento por bloco
   - destaque por horario
   - condicao por modulo ativo
   - CTA fallback quando modulo estiver offline

Acceptance:

- avancado existe, mas isolado
- o fluxo padrao continua rapido

### Phase 6 - Security, audit and QA hardening

Objetivo: garantir rastreabilidade e seguranca operacional.

Tasks:

1. Auditar alteracoes do Hub.
   Subtasks:
   - registrar mudancas estruturais no `/audit`
   - registrar upload/troca de hero
   - registrar alteracao de tema e blocos

2. Endurecer tracking publico.
   Subtasks:
   - limitar payload aceito
   - impedir tracking arbitrario
   - manter respostas neutras para entradas invalidas

3. Cobertura de regressao.
   Subtasks:
   - testes de payload admin/publico
   - testes de analytics do Hub
   - testes de render basico do editor
   - smoke de build e type-check

Acceptance:

- alteracoes importantes do Hub sao auditaveis
- tracking publico nao abre superficie desnecessaria
- regressao principal coberta

## Recommended Execution Order

1. Phase 0
2. Phase 1
3. Phase 2
4. Phase 3
5. Phase 4
6. Phase 6
7. Phase 5

Motivo:

- primeiro medir
- depois evoluir preset
- depois modularizar
- so depois abrir customizacao avancada

## What starts now

As primeiras entregas executadas neste ciclo foram:

1. roadmap detalhado salvo no repositorio
2. tracking publico de clique dos CTAs do Hub
3. endpoint admin de insights do Hub
4. painel inicial de performance dentro do editor

As entregas em execucao agora sao:

1. galeria de temas mais forte e editorial
2. `social_strip` como bloco proprio
3. base do builder pronta para ampliar os proximos blocos tipados
